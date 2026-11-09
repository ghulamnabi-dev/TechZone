<?php
/**
 * ⌘K global search. GET ?q= (min 1 char). Every role (everything is viewable by every role).
 * Returns up to 5 per group:
 *   orders     by #id, customer name, phone (digits only, so 0300-123 and 0300123 both match)
 *   products   by name or SKU
 *   customers  by name, email or phone
 * -> {success, q, groups:{orders:[…], products:[…], customers:[…]}}
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);
json_headers();

$q = trim(preg_replace('/\s+/', ' ', (string) ($_GET['q'] ?? '')));
$q = mb_substr($q, 0, 80);
$groups = ['orders' => [], 'products' => [], 'customers' => []];
if ($q === '') {
    json_response(['success' => true, 'q' => '', 'groups' => $groups]);
}

$like = '%' . addcslashes($q, '%_\\') . '%';
$num = preg_match('/^#?\s*(\d{1,10})$/', $q, $m) ? (int) $m[1] : 0;
$digits = preg_replace('/\D+/', '', $q);
$phoneLike = strlen($digits) >= 4 ? '%' . $digits . '%' : null;
$join = static fn(array $parts): string => implode(' · ', array_filter(array_map('strval', $parts), static fn($s) => trim($s) !== ''));
$phoneSql = static fn(string $col): string => "REPLACE(REPLACE(COALESCE($col, ''), '-', ''), ' ', '')";

try {
    // Orders: exact id first, then name / phone matches, newest first
    $where = ['o.customer_name LIKE ?'];
    $params = [$like];
    if ($num > 0) {
        $where[] = 'o.id = ?';
        $params[] = $num;
    }
    if ($phoneLike !== null) {
        $where[] = $phoneSql('o.customer_phone') . ' LIKE ?';
        $params[] = $phoneLike;
    }
    $st = $conn->prepare('SELECT o.id, o.customer_name, o.customer_phone, o.city, o.total, o.status, o.payment_status, o.fulfillment_status, o.order_date
        FROM orders o WHERE ' . implode(' OR ', $where) . ' ORDER BY (o.id = ?) DESC, o.order_date DESC LIMIT 5');
    $st->execute(array_merge($params, [$num]));
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $groups['orders'][] = [
            'id' => (int) $r['id'],
            'label' => 'Order #' . $r['id'] . ' · ' . $r['customer_name'],
            'sub' => $join([$r['customer_phone'] ?? '', $r['city'] ?? '', date('j M Y', strtotime($r['order_date']))]),
            'total' => (float) $r['total'],
            'status' => $r['status'],
            'href' => admin_url('manage/order.php?id=' . (int) $r['id']),
        ];
    }

    // Products: SKU exact match ranks first
    $st = $conn->prepare("SELECT p.id, p.name, p.sku, p.price, p.inventory, p.status, c.name AS category
        FROM products p LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.name LIKE ? OR p.sku LIKE ?
        ORDER BY (p.sku = ?) DESC, (p.status = 'archived'), p.sold DESC, p.name LIMIT 5");
    $st->execute([$like, $like, $q]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $groups['products'][] = [
            'id' => (int) $r['id'],
            'label' => $r['name'],
            'sub' => $join([$r['sku'] ?? '', $r['category'] ?? '', (int) $r['inventory'] . ' in stock']),
            'price' => (float) $r['price'],
            'status' => $r['status'],
            'href' => admin_url('manage/product.php?id=' . (int) $r['id']),
        ];
    }

    // Customers
    $where = ['c.name LIKE ?', 'c.email LIKE ?'];
    $params = [$like, $like];
    if ($phoneLike !== null) {
        $where[] = $phoneSql('c.phone') . ' LIKE ?';
        $params[] = $phoneLike;
    }
    $st = $conn->prepare('SELECT c.id, c.name, c.email, c.phone, c.city FROM customers c WHERE ' . implode(' OR ', $where) . ' ORDER BY (c.name LIKE ?) DESC, c.name LIMIT 5');
    $st->execute(array_merge($params, [addcslashes($q, '%_\\') . '%']));
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $groups['customers'][] = [
            'id' => (int) $r['id'],
            'label' => $r['name'],
            'sub' => $join([$r['email'], $r['phone'] ?? '', $r['city'] ?? '']),
            'href' => admin_url('manage/customer.php?id=' . (int) $r['id']),
        ];
    }
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Search is unavailable right now.'), 500);
}

json_response(['success' => true, 'q' => $q, 'groups' => $groups]);
