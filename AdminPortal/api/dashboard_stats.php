<?php
/**
 * Home (operations overview). Every role; recent activity only for activity.view.
 *
 * -> today      {orders, revenue, aov, collected, yesterday:{orders, revenue}}   yesterday = by this time yesterday
 *    todo       {to_fulfil, to_collect, low_stock, reviews, messages}
 *    sales      [{date, revenue, orders}] last 30 days, zero-filled, oldest first
 *    payment_mix [{method, label, orders, revenue}] this calendar month
 *    top_products [{id, name, units, revenue}] last 30 days
 *    recent_orders, recent_activity (null when the role cannot see the activity log)
 * Cancelled orders never count towards revenue.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);
json_headers();

try {
    $t = $conn->query("SELECT
            SUM(order_date >= CURDATE()) AS orders_today,
            COALESCE(SUM(CASE WHEN order_date >= CURDATE() THEN total END), 0) AS revenue_today,
            SUM(order_date >= CURDATE() - INTERVAL 1 DAY AND order_date < NOW() - INTERVAL 1 DAY) AS orders_yday,
            COALESCE(SUM(CASE WHEN order_date >= CURDATE() - INTERVAL 1 DAY AND order_date < NOW() - INTERVAL 1 DAY THEN total END), 0) AS revenue_yday
        FROM orders WHERE status <> 'Cancelled' AND order_date >= CURDATE() - INTERVAL 1 DAY")->fetch(PDO::FETCH_ASSOC);
    $collected = (float) $conn->query("SELECT COALESCE(SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.amount')) AS DECIMAL(12,2))), 0)
        FROM order_events WHERE type = 'payment_recorded' AND created_at >= CURDATE()")->fetchColumn();
    $ordersToday = (int) $t['orders_today'];
    $today = [
        'orders' => $ordersToday,
        'revenue' => (float) $t['revenue_today'],
        'aov' => $ordersToday ? round((float) $t['revenue_today'] / $ordersToday, 2) : 0,
        'collected' => $collected,
        'yesterday' => ['orders' => (int) $t['orders_yday'], 'revenue' => (float) $t['revenue_yday']],
    ];

    // To-do: the same rules as the Orders tabs, Inventory, Reviews and Messages
    $todo = array_map('intval', $conn->query("SELECT
        (SELECT COUNT(*) FROM orders WHERE status <> 'Cancelled' AND fulfillment_status <> 'fulfilled') AS to_fulfil,
        (SELECT COUNT(*) FROM orders WHERE status IN ('Shipped', 'Delivered') AND payment_status = 'pending' AND payment_method = 'cash_on_delivery') AS to_collect,
        (SELECT COUNT(*) FROM products WHERE status <> 'archived' AND inventory <= low_stock_threshold) AS low_stock,
        (SELECT COUNT(*) FROM reviews WHERE status = 'Pending') AS reviews,
        (SELECT COUNT(*) FROM contact_messages WHERE status = 'new') AS messages")->fetch(PDO::FETCH_ASSOC));

    // Sales: last 30 days (today included), zero-filled
    $byDay = [];
    foreach ($conn->query("SELECT DATE(order_date) AS d, COUNT(*) AS n, SUM(total) AS revenue FROM orders
            WHERE status <> 'Cancelled' AND order_date >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(order_date)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byDay[$r['d']] = $r;
    }
    $todayDate = $conn->query('SELECT CURDATE()')->fetchColumn();
    $sales = [];
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime($todayDate . ' -' . $i . ' day'));
        $sales[] = ['date' => $d, 'revenue' => (float) ($byDay[$d]['revenue'] ?? 0), 'orders' => (int) ($byDay[$d]['n'] ?? 0)];
    }

    $labels = ['cash_on_delivery' => 'Cash on delivery', 'jazzcash' => 'JazzCash'];
    $mix = [];
    foreach ($conn->query("SELECT payment_method, COUNT(*) AS n, SUM(total) AS revenue FROM orders
            WHERE status <> 'Cancelled' AND order_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY payment_method ORDER BY revenue DESC")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $mix[] = ['method' => $r['payment_method'], 'label' => $labels[$r['payment_method']] ?? ucwords(str_replace('_', ' ', $r['payment_method'])),
            'orders' => (int) $r['n'], 'revenue' => (float) $r['revenue']];
    }

    $top = array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'sku' => $r['sku'], 'units' => (int) $r['units'], 'revenue' => (float) $r['revenue']],
        $conn->query("SELECT p.id, COALESCE(p.name, MAX(oi.product_name)) AS name, p.sku, SUM(oi.quantity) AS units, SUM(oi.subtotal) AS revenue
            FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id
            WHERE o.status <> 'Cancelled' AND o.order_date >= CURDATE() - INTERVAL 29 DAY
            GROUP BY oi.product_id, p.id, p.name, p.sku ORDER BY units DESC, revenue DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC));

    $recentOrders = $conn->query("SELECT id, customer_name, city, total, status, payment_status, fulfillment_status, payment_method, order_date
        FROM orders ORDER BY order_date DESC, id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

    $activity = null;
    if (can('activity.view')) {
        $activity = $conn->query("SELECT a.id, a.created_at, a.action, a.entity_type, a.entity_id, a.summary, ad.name AS actor_name, ad.role AS actor_role
            FROM audit_log a LEFT JOIN admins ad ON ad.id = a.admin_id ORDER BY a.created_at DESC, a.id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    }

    json_response([
        'success' => true,
        'today' => $today,
        'todo' => $todo,
        'sales' => $sales,
        'payment_mix' => $mix,
        'top_products' => $top,
        'recent_orders' => $recentOrders,
        'recent_activity' => $activity,
    ]);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not load the overview.'), 500);
}
