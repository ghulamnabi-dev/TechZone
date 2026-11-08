<?php
// Admin: customer list / detail (read-only). Never returns password hashes or tokens.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Invalid request method', 405);
}

// Explicit, non-sensitive columns only
const CUSTOMER_PUBLIC_COLUMNS = 'id, name, email, phone, city, address, created_at, updated_at';

try {
    if (isset($_GET['id'])) {
        $id = v_id($_GET['id']);
        $stmt = $conn->prepare("SELECT " . CUSTOMER_PUBLIC_COLUMNS . " FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$customer) {
            json_error('Customer not found', 404);
        }

        $stmt2 = $conn->prepare("SELECT id, order_date, status, total FROM orders WHERE customer_id = ? ORDER BY order_date DESC");
        $stmt2->execute([$id]);
        $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $totalOrders = count($orders);
        $totalSpent = 0;
        foreach ($orders as $order) {
            $totalSpent += (float) $order['total'];
        }

        $customer['orders'] = $orders;
        $customer['total_orders'] = $totalOrders;
        $customer['total_spent'] = $totalSpent;
        $customer['avg_order_value'] = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;

        json_response(['success' => true, 'customer' => $customer]);
    }

    // List with pagination (limit 1..1000, page >= 1)
    $page = v_int($_GET['page'] ?? 1, 1, 100000) ?? 1;
    $limit = v_int($_GET['limit'] ?? 10, 1, 1000) ?? 10;
    $offset = ($page - 1) * $limit;

    $where = " WHERE 1=1";
    $params = [];

    $search = v_str($_GET['search'] ?? '', 100) ?? '';
    if ($search !== '') {
        $where .= " AND (c.name LIKE ? OR c.email LIKE ?)";
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $city = v_str($_GET['city'] ?? '', 100) ?? '';
    if ($city !== '') {
        $where .= " AND c.city = ?";
        $params[] = $city;
    }

    $sql = "SELECT c.id, c.name, c.email, c.phone, c.city, c.address, c.created_at, c.updated_at,
                   COUNT(o.id) AS total_orders, COALESCE(SUM(o.total), 0) AS total_spent
            FROM customers c
            LEFT JOIN orders o ON o.customer_id = c.id" . $where . "
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM customers c" . $where);
    $stmt2->execute($params);
    $total = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];

    json_response(['success' => true, 'customers' => $customers, 'total' => $total, 'page' => $page, 'limit' => $limit]);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not load customers.'), 500);
}
