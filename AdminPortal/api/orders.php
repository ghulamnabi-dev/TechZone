<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
const ORDER_STATUSES = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

try {
    // GET - all orders or one order
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $id = v_id($_GET['id']);
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                json_error('Order not found', 404);
            }

            $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt2->execute([$id]);
            $order['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            json_response(['success' => true, 'order' => $order]);
        }

        $query = "SELECT * FROM orders WHERE 1=1";
        $params = [];

        $status = v_enum($_GET['status'] ?? '', ORDER_STATUSES);
        if ($status !== null) {
            $query .= " AND status = ?";
            $params[] = $status;
        }
        $search = v_str($_GET['search'] ?? '', 100) ?? '';
        if ($search !== '') {
            $query .= " AND (customer_name LIKE ? OR customer_email LIKE ?)";
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $query .= " ORDER BY order_date DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        json_response(['success' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // PUT - update order status (JSON body: id, status)
    if ($method === 'PUT') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? 0);
        $status = v_enum($data['status'] ?? '', ORDER_STATUSES);

        if ($id === 0 || $status === null) {
            json_error('Invalid data', 422);
        }

        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        if ($stmt->rowCount() > 0) {
            json_response(['success' => true, 'message' => 'Order status updated']);
        }
        json_response(['success' => false, 'message' => 'Order not found or status unchanged']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Database error. Please try again.'), 500);
}
