<?php
/**
 * Orders API.
 *
 *   GET  ?id=N                everything the order page needs: order, items (image, fulfilled_qty), zone,
 *                             shipment(s), payment record, customer + stats, notes, timeline,
 *                             restock_units, actions (state AND role allow it), couriers, payment_methods
 *   GET  [?status=&search=]   list
 *   PUT  {id, status}         legacy status change (orders grid); walks the order forward through
 *                             core/orders.php operations so stock, payment and the timeline agree
 *   POST {id, action, …}      staff operations: confirm | payment {amount, method, reference} | fulfil {items}
 *                             | ship {courier, tracking, note} | tracking {courier, tracking} | deliver | cancel {reason} | note {body}
 *
 * Capabilities (core/permissions.php): confirm/fulfil/deliver -> orders.fulfil, payment -> orders.payment,
 * ship/tracking -> orders.ship, note -> orders.note, cancel -> orders.cancel.
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

/** Payment methods staff can record (value => label). */
const ORDER_PAYMENT_METHODS = ['jazzcash' => 'JazzCash', 'cash_on_delivery' => 'Cash on delivery', 'easypaisa' => 'Easypaisa', 'bank_transfer' => 'Bank transfer'];

/** Browser-ready product image URL (DB stores uploads/filename). */
function order_image_url(?string $link): string
{
    $t = trim((string) $link);
    if ($t === '' || preg_match('#^https?://#i', $t)) {
        return $t;
    }
    $rel = ltrim(str_replace('\\', '/', $t), '/');
    return strpos($rel, 'uploads/') === 0 ? admin_url($rel) : $t;
}

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
            $detail = $id > 0 ? order_detail($conn, $id) : null;
            if (!$detail) {
                json_error('Order not found', 404);
            }
            foreach ($detail['items'] as &$item) {
                $item['image'] = order_image_url($item['imagelink'] ?? null);
                unset($item['imagelink']);
            }
            unset($item);
            $order = $detail['order'];
            $order['zone_name'] = $detail['zone']['name'] ?? null;
            $allowed = $detail['allowed'];
            $can = [
                'confirm' => can('orders.fulfil'), 'fulfil' => can('orders.fulfil'), 'deliver' => can('orders.fulfil'),
                'payment' => can('orders.payment'), 'ship' => can('orders.ship'), 'tracking' => can('orders.ship'),
                'note' => can('orders.note'), 'cancel' => can('orders.cancel'),
            ];
            $actions = [];
            foreach ($allowed as $k => $ok) {
                $actions[$k] = $ok && ($can[$k] ?? false);
            }

            json_response([
                'success' => true,
                'order' => $order,
                'items' => $detail['items'],
                'zone' => $detail['zone'],
                'shipment' => $detail['shipment'],
                'shipments' => $detail['shipments'],
                'payment' => $detail['payment'],
                'customer' => $detail['customer'],
                'notes' => $detail['notes'],
                'timeline' => $detail['timeline'],
                'restock_units' => $detail['restock_units'],
                'actions' => $actions,
                'couriers' => ['TCS', 'Leopards', 'M&P'],
                'payment_methods' => ORDER_PAYMENT_METHODS,
            ]);
        }

        $query = "SELECT o.*, (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi WHERE oi.order_id = o.id) AS items_count FROM orders o WHERE 1=1";
        $params = [];

        $status = v_enum($_GET['status'] ?? '', ORDER_STATUSES);
        if ($status !== null) {
            $query .= " AND o.status = ?";
            $params[] = $status;
        }
        $search = v_str($_GET['search'] ?? '', 100) ?? '';
        if ($search !== '') {
            $query .= " AND (o.customer_name LIKE ? OR o.customer_email LIKE ?)";
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $query .= " ORDER BY o.order_date DESC, o.id DESC";

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
        $action = v_enum($data['action'] ?? '', ['confirm', 'payment', 'fulfil', 'ship', 'deliver', 'cancel', 'note', 'tracking']);
        if ($id === 0 || $action === null) {
            json_error('Invalid data', 422);
        }
        require_can([
            'confirm' => 'orders.fulfil', 'fulfil' => 'orders.fulfil', 'deliver' => 'orders.fulfil',
            'payment' => 'orders.payment', 'ship' => 'orders.ship', 'tracking' => 'orders.ship', 'note' => 'orders.note', 'cancel' => 'orders.cancel',
        ][$action]);

        switch ($action) {
            case 'confirm':
                $order = order_confirm($conn, $id, $adminId);
                $msg = 'Order confirmed';
                break;
            case 'payment':
                $amount = isset($data['amount']) && $data['amount'] !== '' ? v_num($data['amount'], 0, 1.0e9) : null;
                if (isset($data['amount']) && $data['amount'] !== '' && ($amount === null || $amount <= 0)) {
                    json_error('Enter the amount received', 422);
                }
                $payMethod = null;
                if (isset($data['method']) && $data['method'] !== '') {
                    $payMethod = v_enum($data['method'], array_keys(ORDER_PAYMENT_METHODS));
                    if ($payMethod === null) {
                        json_error('Choose a payment method', 422);
                    }
                }
                $order = order_record_payment($conn, $id, $amount, $payMethod, $adminId, v_str($data['reference'] ?? '', 80));
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
                $shipNote = v_str($data['note'] ?? '', 5000) ?? '';
                $order = order_tx($conn, function () use ($conn, $id, $courier, $tracking, $adminId, $shipNote): array {
                    $o = order_ship($conn, $id, $courier, $tracking, $adminId);
                    if (trim($shipNote) !== '' && can('orders.note')) {
                        order_add_note($conn, $id, $shipNote, $adminId);
                    }
                    return $o;
                });
                $msg = 'Order shipped';
                break;
            case 'tracking':
                $courier = v_str($data['courier'] ?? '', 60) ?? '';
                if ($courier === '') {
                    json_error('Choose a courier', 422);
                }
                $order = order_update_shipment($conn, $id, $courier, v_str($data['tracking'] ?? '', 80), $adminId);
                $msg = 'Tracking updated';
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
