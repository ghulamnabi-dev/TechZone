<?php
/**
 * Admin: customers. Never returns password hashes or tokens.
 *
 *   GET                      list (tags, order count, lifetime value) – ?search= ?city= ?page= ?limit=
 *   GET ?id=N                profile: contact, tags, notes, orders, overview, timeline (customer-facing wording)
 *   GET ?tags=1              every tag name (for the tag picker)
 *   POST {action:'tag_add', customer_id, tag}        customers.write
 *   POST {action:'tag_remove', customer_id, tag_id}  customers.write
 *   POST {action:'note_add', customer_id, body}      customers.write
 *   DELETE ?id=N             customers.delete (orders are kept, unlinked)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/audit.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];

// Explicit, non-sensitive columns only
const CUSTOMER_PUBLIC_COLUMNS = 'id, name, email, phone, city, address, created_at, updated_at';

/**
 * What the customer saw happen, in the words the storefront uses (no staff names, no notes).
 */
function customer_event_wording(string $type, array $p): ?string
{
    $money = static fn($v): string => 'Rs ' . number_format((float) $v, 0);
    switch ($type) {
        case 'placed':
            return 'Placed the order' . (isset($p['total']) ? ' · ' . $money($p['total']) : '') . (!empty($p['promo_code']) ? ' with code ' . $p['promo_code'] : '');
        case 'confirmed':
            return 'Order confirmed';
        case 'payment_recorded':
            return 'Payment received' . (isset($p['amount']) ? ' · ' . $money($p['amount']) : '');
        case 'fulfilled':
            return !empty($p['partial']) ? 'Some items packed' : 'Order packed';
        case 'shipped':
            return 'Shipped' . (!empty($p['courier']) ? ' with ' . $p['courier'] : '') . (!empty($p['tracking']) ? ' · tracking ' . $p['tracking'] : '');
        case 'delivered':
            return 'Delivered';
        case 'cancelled':
            return 'Order cancelled' . (!empty($p['reason']) ? ' · ' . $p['reason'] : '');
        case 'refund_requested':
            return 'Refund requested';
    }
    return null;   // 'note' and anything internal
}

function customer_tags(PDO $conn, int $customerId): array
{
    $st = $conn->prepare('SELECT t.id, t.name FROM customer_tag_links l JOIN customer_tags t ON t.id = l.tag_id WHERE l.customer_id = ? ORDER BY t.name');
    $st->execute([$customerId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function customer_notes(PDO $conn, int $customerId): array
{
    $st = $conn->prepare('SELECT n.id, n.body, n.created_at, a.name AS admin_name FROM customer_notes n LEFT JOIN admins a ON a.id = n.admin_id
        WHERE n.customer_id = ? ORDER BY n.created_at DESC, n.id DESC');
    $st->execute([$customerId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function customer_exists(PDO $conn, int $id): ?array
{
    $st = $conn->prepare('SELECT id, name, email FROM customers WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

try {
    if ($method === 'GET') {
        if (isset($_GET['tags'])) {
            $rows = $conn->query('SELECT t.id, t.name, COUNT(l.customer_id) AS customers FROM customer_tags t LEFT JOIN customer_tag_links l ON l.tag_id = t.id GROUP BY t.id, t.name ORDER BY t.name')->fetchAll(PDO::FETCH_ASSOC);
            json_response(['success' => true, 'tags' => $rows]);
        }

        if (isset($_GET['id'])) {
            $id = v_id($_GET['id']);
            $stmt = $conn->prepare("SELECT " . CUSTOMER_PUBLIC_COLUMNS . " FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$customer) {
                json_error('Customer not found', 404);
            }

            $stmt2 = $conn->prepare("SELECT o.id, o.order_date, o.status, o.payment_status, o.fulfillment_status, o.total, o.promo_code, o.city,
                    (SELECT COALESCE(SUM(i.quantity), 0) FROM order_items i WHERE i.order_id = o.id) AS items
                FROM orders o WHERE o.customer_id = ? ORDER BY o.order_date DESC, o.id DESC");
            $stmt2->execute([$id]);
            $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            $live = array_values(array_filter($orders, static fn($o) => $o['status'] !== 'Cancelled'));
            $spent = array_sum(array_map(static fn($o) => (float) $o['total'], $live));
            $dates = array_map(static fn($o) => $o['order_date'], $orders);

            $customer['orders'] = $orders;
            $customer['total_orders'] = count($live);
            $customer['cancelled_orders'] = count($orders) - count($live);
            $customer['total_spent'] = $spent;
            $customer['avg_order_value'] = $live ? $spent / count($live) : 0;
            $customer['first_order_at'] = $dates ? min($dates) : null;
            $customer['last_order_at'] = $dates ? max($dates) : null;
            $customer['tags'] = customer_tags($conn, $id);
            $customer['notes'] = customer_notes($conn, $id);

            // timeline: their order events, customer-facing wording, newest first
            $ev = $conn->prepare("SELECT e.id, e.order_id, e.type, e.payload, e.created_at FROM order_events e JOIN orders o ON o.id = e.order_id
                WHERE o.customer_id = ? AND e.type <> 'note' ORDER BY e.created_at DESC, e.id DESC LIMIT 200");
            $ev->execute([$id]);
            $timeline = [];
            foreach ($ev->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $label = customer_event_wording($e['type'], json_decode((string) $e['payload'], true) ?: []);
                if ($label !== null) {
                    $timeline[] = ['id' => (int) $e['id'], 'order_id' => (int) $e['order_id'], 'type' => $e['type'], 'label' => $label, 'at' => $e['created_at']];
                }
            }
            $customer['timeline'] = $timeline;

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
                       COUNT(o.id) AS total_orders, COALESCE(SUM(o.total), 0) AS total_spent, MAX(o.order_date) AS last_order_at,
                       (SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ',') FROM customer_tag_links l JOIN customer_tags t ON t.id = l.tag_id WHERE l.customer_id = c.id) AS tags
                FROM customers c
                LEFT JOIN orders o ON o.customer_id = c.id AND o.status <> 'Cancelled'" . $where . "
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
    }

    if ($method === 'DELETE') {
        require_can('customers.delete');
        enforce_csrf_json();
        $id = v_id($_GET['id'] ?? (request_json()['id'] ?? 0));
        $c = customer_exists($conn, $id);
        if (!$c) {
            json_error('Customer not found', 404);
        }
        $conn->beginTransaction();
        $n = $conn->prepare('SELECT COUNT(*) FROM orders WHERE customer_id = ?');
        $n->execute([$id]);
        $orders = (int) $n->fetchColumn();
        $conn->prepare('DELETE FROM customers WHERE id = ?')->execute([$id]);   // tags/notes cascade; orders keep their copy of name/email
        audit('customer.delete', 'customer', $id, "Deleted customer {$c['name']} ({$c['email']})", ['name' => $c['name'], 'email' => $c['email'], 'orders' => $orders], null);
        $conn->commit();
        json_response(['success' => true, 'message' => 'Customer deleted']);
    }

    if ($method !== 'POST') {
        json_error('Invalid request method', 405);
    }
    require_can('customers.write');
    enforce_csrf_json();
    $d = request_json() ?: $_POST;
    $action = (string) ($d['action'] ?? '');
    $customerId = v_id($d['customer_id'] ?? 0);
    $customer = customer_exists($conn, $customerId);
    if (!$customer) {
        json_error('Customer not found', 404);
    }

    if ($action === 'tag_add') {
        $tag = v_str($d['tag'] ?? '', 50, false);
        $tag = $tag === null ? null : strtolower(preg_replace('/\s+/', ' ', $tag));
        if ($tag === null || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} &._\-]*$/u', $tag)) {
            validation_problem(['tag' => 'Tags are short words: letters, numbers, spaces and dashes (up to 50).']);
        }
        $conn->beginTransaction();
        $conn->prepare('INSERT IGNORE INTO customer_tags (name) VALUES (?)')->execute([$tag]);
        $st = $conn->prepare('SELECT id FROM customer_tags WHERE name = ?');
        $st->execute([$tag]);
        $tagId = (int) $st->fetchColumn();
        $ins = $conn->prepare('INSERT IGNORE INTO customer_tag_links (customer_id, tag_id) VALUES (?, ?)');
        $ins->execute([$customerId, $tagId]);
        if ($ins->rowCount() > 0) {
            audit('customer.tag', 'customer', $customerId, "Tagged {$customer['name']} as $tag", null, ['tag' => $tag]);
        }
        $conn->commit();
        json_response(['success' => true, 'message' => $ins->rowCount() > 0 ? 'Tag added' : 'Already tagged', 'tags' => customer_tags($conn, $customerId)]);
    }

    if ($action === 'tag_remove') {
        $tagId = v_id($d['tag_id'] ?? 0);
        $st = $conn->prepare('SELECT name FROM customer_tags WHERE id = ?');
        $st->execute([$tagId]);
        $name = $st->fetchColumn();
        $del = $conn->prepare('DELETE FROM customer_tag_links WHERE customer_id = ? AND tag_id = ?');
        $del->execute([$customerId, $tagId]);
        if ($del->rowCount() > 0) {
            audit('customer.untag', 'customer', $customerId, "Removed tag $name from {$customer['name']}", ['tag' => $name], null);
        }
        json_response(['success' => true, 'message' => 'Tag removed', 'tags' => customer_tags($conn, $customerId)]);
    }

    if ($action === 'note_add') {
        $body = v_str($d['body'] ?? '', 2000, false);
        if ($body === null) {
            validation_problem(['body' => 'Write a note (up to 2,000 characters).']);
        }
        $conn->beginTransaction();
        $conn->prepare('INSERT INTO customer_notes (customer_id, admin_id, body) VALUES (?, ?, ?)')->execute([$customerId, (int) $_SESSION['admin_id'], $body]);
        audit('customer.note', 'customer', $customerId, "Added a note to {$customer['name']}", null, ['note' => mb_substr($body, 0, 120)]);
        $conn->commit();
        json_response(['success' => true, 'message' => 'Note added', 'notes' => customer_notes($conn, $customerId)]);
    }

    json_error('Unknown action', 422);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not load customers.'), 500);
}
