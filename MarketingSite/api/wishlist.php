<?php
/**
 * Wishlist API (core/wishlist.php). Works for guests (session) and signed-in customers (account).
 *
 * GET  [?full=1]                          → {success, ids:[…], count, items?:[product…]}
 * POST product_id=…, action=toggle|add|remove   (CSRF; throttled)  → {success, saved, count, message}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../includes/format.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/catalog.php';
require_once __DIR__ . '/../../core/wishlist.php';
require_once __DIR__ . '/../../core/throttle.php';

json_headers();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $ids = wishlist_ids($conn);
        $out = ['success' => true, 'ids' => $ids, 'count' => count($ids)];
        if (!empty($_GET['full']) && $ids) {
            $st = $conn->prepare('SELECT p.*, c.name AS category_name, c.part_type FROM products p LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.id IN (' . catalog_placeholders($ids) . ') AND ' . catalog_visible_sql());
            $st->execute($ids);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[(int) $r['id']] = $r;
            }
            $attrs = products_attributes($conn, array_keys($rows));
            $changes = price_change($conn, array_keys($rows));
            $out['items'] = [];
            foreach ($ids as $id) {      // keep wishlist order (newest first)
                if (isset($rows[$id])) {
                    $p = $rows[$id] + ['key_specs' => key_specs($attrs[$id] ?? []), 'change' => $changes[$id] ?? null];
                    $out['items'][] = product_json($p);
                }
            }
        }
        json_response($out);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_error('Invalid request method', 405);
    }
    enforce_csrf_json();
    $in = $_POST ?: request_json();
    if (!throttle_allow($conn, 'wishlist', 60, 600)) {
        json_error('Too many changes in a short time. Please wait a few minutes.', 429);
    }
    $productId = v_id($in['product_id'] ?? 0);
    $action = v_enum($in['action'] ?? 'toggle', ['toggle', 'add', 'remove']);
    if ($productId === 0 || $action === null) {
        json_error('Invalid product', 422);
    }
    try {
        if ($action === 'toggle') {
            $r = wishlist_toggle($conn, $productId);
        } else {
            $action === 'add' ? wishlist_add($conn, $productId) : wishlist_remove($conn, $productId);
            $r = ['saved' => $action === 'add', 'count' => count(wishlist_ids($conn))];
        }
    } catch (InvalidArgumentException $e) {
        json_error($e->getMessage(), 422);   // fixed messages from core/wishlist.php, no user input
    }
    json_response(['success' => true, 'saved' => $r['saved'], 'count' => $r['count'],
                   'message' => $r['saved'] ? 'Saved to your wishlist' : 'Removed from your wishlist']);
} catch (PDOException $e) {
    log_exception($e, 'wishlist');
    json_error('Something went wrong. Please try again.', 500);
}
