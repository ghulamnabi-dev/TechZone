<?php
/**
 * Orders API.
 *
 *   GET  ?id=N                one order + items + timeline
 *   GET  [?status=&search=]   list
 *   PUT  {id, status}         legacy status change (orders grid); walks the order forward through
 *                             core/orders.php operations so stock, payment and the timeline agree
 *   POST {id, action, …}      staff operations: confirm | payment | fulfil | ship | deliver | cancel | note
 *
 * Capabilities (core/permissions.php): confirm/fulfil/deliver -> orders.fulfil, payment -> orders.payment,
 * ship -> orders.ship, note -> orders.note, cancel -> orders.cancel.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/orders.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
$adminId = (int) $_SESSION['admin_id'];

/** Capability needed to move an order to $status via the legacy PUT. */
function order_status_capability(string $status): string
{
    return match ($status) {
        'Cancelled' => 'orders.cancel',
        'Shipped' => 'orders.ship',
        default => 'orders.fulfil',
    };
}

try {
    // GET - all orders or one order
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $id = v_id($_GET['id']);
            $order = order_get($conn, $id);
            if (!$order) {
                json_error('Order not found', 404);
            }

            $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt2->execute([$id]);
            $order['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            $stmt3 = $conn->prepare("SELECT * FROM order_shipments WHERE order_id = ? ORDER BY shipped_at");
            $stmt3->execute([$id]);
            $order['shipments'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            $order['timeline'] = order_timeline($conn, $id);

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

    // PUT - legacy status change (JSON body: id, status)
    if ($method === 'PUT') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? 0);
        $status = v_enum($data['status'] ?? '', ORDER_STATUSES);

        if ($id === 0 || $status === null) {
            json_error('Invalid data', 422);
        }
        require_can(order_status_capability($status));

        $before = order_get($conn, $id);
        if (!$before) {
            json_error('Order not found', 404);
        }
        if ($before['status'] === $status) {
            json_response(['success' => true, 'message' => 'Order is already ' . $status, 'order' => $before]);
        }
        $order = order_set_status($conn, $id, $status, $adminId);
        json_response(['success' => true, 'message' => 'Order status updated', 'order' => $order]);
    }

    // POST - staff operation (JSON body: id, action, …)
    if ($method === 'POST') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? 0);
        $action = v_enum($data['action'] ?? '', ['confirm', 'payment', 'fulfil', 'ship', 'deliver', 'cancel', 'note']);
        if ($id === 0 || $action === null) {
            json_error('Invalid data', 422);
        }
        require_can([
            'confirm' => 'orders.fulfil', 'fulfil' => 'orders.fulfil', 'deliver' => 'orders.fulfil',
            'payment' => 'orders.payment', 'ship' => 'orders.ship', 'note' => 'orders.note', 'cancel' => 'orders.cancel',
        ][$action]);

        switch ($action) {
            case 'confirm':
                $order = order_confirm($conn, $id, $adminId);
                $msg = 'Order confirmed';
                break;
            case 'payment':
                $amount = isset($data['amount']) && $data['amount'] !== '' ? v_num($data['amount'], 0, 1.0e9) : null;
                $order = order_record_payment($conn, $id, $amount, null, $adminId);
                $msg = 'Payment recorded';
                break;
            case 'fulfil':
                $items = null;
                if (isset($data['items']) && is_array($data['items'])) {
                    $items = [];
                    foreach ($data['items'] as $itemId => $qty) {
                        $items[(int) $itemId] = (int) $qty;
                    }
                }
                $order = order_fulfil($conn, $id, $items, $adminId);
                $msg = $order['fulfillment_status'] === 'partial' ? 'Items partially fulfilled' : 'Order fulfilled';
                break;
            case 'ship':
                $courier = v_str($data['courier'] ?? '', 60) ?? '';
                $tracking = v_str($data['tracking'] ?? '', 80);
                if ($courier === '') {
                    json_error('Choose a courier', 422);
                }
                $order = order_ship($conn, $id, $courier, $tracking, $adminId);
                $msg = 'Order shipped';
                break;
            case 'deliver':
                $order = order_deliver($conn, $id, $adminId);
                $msg = 'Order marked delivered';
                break;
            case 'cancel':
                $order = order_cancel($conn, $id, v_str($data['reason'] ?? '', 255) ?? '', $adminId);
                $msg = 'Order cancelled';
                break;
            default: // note
                order_add_note($conn, $id, v_str($data['body'] ?? '', 5000) ?? '', $adminId);
                $order = order_get($conn, $id);
                $msg = 'Note added';
        }
        json_response(['success' => true, 'message' => $msg, 'order' => $order, 'timeline' => order_timeline($conn, $id)]);
    }

    json_error('Invalid request method', 405);
} catch (OrderActionException | InventoryException $e) {
    json_error($e->getMessage(), 409);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Database error. Please try again.'), 500);
}
