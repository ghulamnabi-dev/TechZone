<?php
/**
 * Stock levels and the stock ledger (docs/ADMIN.md: "stock is only changed through this").
 *
 *   GET                    products with stock, threshold, 30-day sales and days of cover + the last 50 adjustments
 *   GET ?product_id=N      that product's adjustment history (newest first, max 200)
 *   POST {product_id, variation_id?, delta, reason, reference?, note?}
 *                          one manual adjustment through adjust_inventory(); needs inventory.adjust
 */
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/inventory.php';
require_once __DIR__ . '/../../core/audit.php';
require_once __DIR__ . '/../../core/settings.php';

/** Reasons staff can pick by hand; sold / cancelled_order only come from orders. */
const INVENTORY_MANUAL_REASONS = ['received', 'returned', 'damaged', 'correction'];

checkLogin(true);
json_headers();
$method = $_SERVER['REQUEST_METHOD'];

/** Ledger rows with product and staff names. */
function inventory_ledger(PDO $conn, ?int $productId, int $limit): array
{
    $where = $productId !== null ? 'WHERE a.product_id = ?' : '';
    $st = $conn->prepare("SELECT a.id, a.product_id, a.variation_id, a.delta, a.reason, a.reference, a.created_at,
            p.name AS product_name, p.sku, v.variation_value, ad.name AS admin_name
        FROM inventory_adjustments a
        JOIN products p ON p.id = a.product_id
        LEFT JOIN product_variations v ON v.id = a.variation_id
        LEFT JOIN admins ad ON ad.id = a.admin_id
        $where
        ORDER BY a.created_at DESC, a.id DESC
        LIMIT " . max(1, min(500, $limit)));
    $st->execute($productId !== null ? [$productId] : []);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['product_id'])) {
            $pid = v_id($_GET['product_id']);
            json_response(['success' => true, 'adjustments' => inventory_ledger($conn, $pid, 200)]);
        }
        $rows = $conn->query("SELECT p.id, p.name, p.sku, p.imagelink, p.status, p.inventory, p.low_stock_threshold, p.updated_at,
                c.name AS category_name,
                COALESCE(s.sold_30d, 0) AS sold_30d
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (SELECT i.product_id, SUM(i.quantity) AS sold_30d
                       FROM order_items i JOIN orders o ON o.id = i.order_id
                       WHERE o.status <> 'Cancelled' AND o.order_date >= NOW() - INTERVAL 30 DAY
                       GROUP BY i.product_id) s ON s.product_id = p.id
            ORDER BY p.name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $stock = (int) $r['inventory'];
            $threshold = (int) $r['low_stock_threshold'];
            $daily = (int) $r['sold_30d'] / 30;
            $r['stock_state'] = $stock <= 0 ? 'out' : ($stock <= $threshold ? 'low' : 'ok');
            $r['days_cover'] = $daily > 0 ? (int) floor($stock / $daily) : null;
            $link = (string) ($r['imagelink'] ?? '');
            $r['imagelink'] = $link !== '' && strpos($link, 'uploads/') === 0 ? admin_url($link) : $link;
        }
        unset($r);
        json_response(['success' => true, 'products' => $rows, 'adjustments' => inventory_ledger($conn, null, 50)]);
    }

    if ($method !== 'POST') {
        json_error('Invalid request method', 405);
    }
    require_can('inventory.adjust');
    enforce_csrf_json();
    $d = request_json() ?: $_POST;

    $errors = [];
    $productId = v_id($d['product_id'] ?? 0);
    $variationRaw = $d['variation_id'] ?? null;
    $variationId = ($variationRaw === null || $variationRaw === '') ? null : v_id($variationRaw);
    $delta = v_int($d['delta'] ?? null, -100000, 100000);
    $reason = v_enum($d['reason'] ?? '', INVENTORY_MANUAL_REASONS);
    $reference = v_str($d['reference'] ?? '', 80);
    $note = v_str($d['note'] ?? '', 200);
    if ($productId === 0) {
        $errors['product_id'] = 'Choose a product.';
    }
    if ($variationId === 0) {
        $errors['variation_id'] = 'That option does not exist.';
    }
    if ($delta === null || $delta === 0) {
        $errors['delta'] = 'Enter a change such as 10 or -2 (not 0).';
    }
    if ($reason === null) {
        $errors['reason'] = 'Choose a reason: received, returned, damaged or correction.';
    } elseif ($delta !== null && $delta < 0 && in_array($reason, ['received', 'returned'], true)) {
        $errors['delta'] = ucfirst($reason) . ' stock adds to the count; use a positive number.';
    } elseif ($delta !== null && $delta > 0 && $reason === 'damaged') {
        $errors['delta'] = 'Damaged stock comes off the count; use a negative number.';
    }
    if ($reference === null) {
        $errors['reference'] = 'Keep the reference under 80 characters.';
    }
    if ($note === null) {
        $errors['note'] = 'Keep the note under 200 characters.';
    }
    if ($errors) {
        validation_problem($errors);
    }

    $st = $conn->prepare('SELECT name, sku, inventory FROM products WHERE id = ?');
    $st->execute([$productId]);
    $product = $st->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        json_error('Product not found', 404);
    }
    // reference column holds "PO-123 · note" (inventory_adjustments has no separate note)
    $ref = trim(implode(' · ', array_filter([$reference, $note], static fn($v) => $v !== '')));

    $conn->beginTransaction();
    try {
        $adjId = adjust_inventory($conn, $productId, $variationId, $delta, $reason, $ref !== '' ? $ref : null, (int) $_SESSION['admin_id']);
        $after = (int) $product['inventory'] + $delta;
        audit('inventory.adjust', 'product', $productId,
            sprintf('Adjusted stock of %s %s by %s (%s)', $product['sku'] ?? '', $product['name'], ($delta > 0 ? '+' : '') . $delta, $reason),
            ['inventory' => (int) $product['inventory']],
            ['inventory' => $after, 'reason' => $reason, 'reference' => $ref !== '' ? $ref : null, 'adjustment_id' => $adjId]);
        $conn->commit();
    } catch (InventoryException $e) {
        $conn->rollBack();
        validation_problem(['delta' => $e->getMessage() . ' Stock cannot go below zero.']);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    $st = $conn->prepare('SELECT inventory, low_stock_threshold FROM products WHERE id = ?');
    $st->execute([$productId]);
    $now = $st->fetch(PDO::FETCH_ASSOC);
    $rows = inventory_ledger($conn, $productId, 1);
    json_response([
        'success' => true,
        'message' => 'Stock adjusted',
        'inventory' => (int) $now['inventory'],
        'low_stock_threshold' => (int) $now['low_stock_threshold'],
        'adjustment' => $rows[0] ?? null,
    ]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not update stock.'), 500);
}
