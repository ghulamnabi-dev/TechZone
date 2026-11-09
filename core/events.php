<?php
/**
 * Order timeline (docs/ADMIN.md principle 1: an order is a page, not a row).
 *
 * order_event() appends to order_events; order_timeline() merges events, internal notes and
 * shipments into one chronological list for the order page, each entry with a human label.
 */

require_once __DIR__ . '/errors.php';

/** Event types allowed by the order_events.type ENUM. */
const ORDER_EVENT_TYPES = ['placed', 'confirmed', 'payment_recorded', 'fulfilled', 'shipped', 'delivered', 'cancelled', 'note', 'refund_requested'];

/**
 * Append an event to an order's timeline.
 *
 * @param PDO      $conn
 * @param int      $orderId
 * @param string   $type      one of ORDER_EVENT_TYPES
 * @param array    $payload   free-form details (amount, courier, tracking, reason, items…)
 * @param string   $actorType 'staff' | 'customer' | 'system'
 * @param int|null $actorId   admins.id / customers.id; for staff defaults to the session admin
 * @return int the new event id
 * @throws InvalidArgumentException for an unknown type / actor type
 */
function order_event(PDO $conn, int $orderId, string $type, array $payload = [], string $actorType = 'staff', ?int $actorId = null): int
{
    if (!in_array($type, ORDER_EVENT_TYPES, true)) {
        throw new InvalidArgumentException('Unknown order event type: ' . $type);
    }
    if (!in_array($actorType, ['staff', 'customer', 'system'], true)) {
        throw new InvalidArgumentException('Unknown actor type: ' . $actorType);
    }
    if ($actorId === null && $actorType === 'staff' && isset($_SESSION['admin_id'])) {
        $actorId = (int) $_SESSION['admin_id'];
    }
    $st = $conn->prepare('INSERT INTO order_events (order_id, type, actor_type, actor_id, payload) VALUES (?, ?, ?, ?, ?)');
    $st->execute([
        $orderId,
        $type,
        $actorType,
        $actorId,
        $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
    return (int) $conn->lastInsertId();
}

/** "JazzCash", "Cash on delivery", "Bank transfer" … (null for an empty method). */
function order_payment_method_label(?string $method): ?string
{
    if ($method === null || $method === '') {
        return null;
    }
    return ['cash_on_delivery' => 'Cash on delivery', 'jazzcash' => 'JazzCash', 'easypaisa' => 'Easypaisa', 'bank_transfer' => 'Bank transfer', 'card' => 'Card']
        [$method] ?? ucfirst(str_replace('_', ' ', $method));
}

/**
 * Human label for one timeline entry ("Shipped with TCS · 773401234561").
 *
 * @param string $type    event type, or 'note' / 'shipment' for the merged sources
 * @param array  $payload decoded payload
 */
function order_event_label(string $type, array $payload): string
{
    $money = static fn($v): string => 'Rs ' . number_format((float) $v, 0);
    switch ($type) {
        case 'placed':
            return 'Order placed' . (isset($payload['total']) ? ' · ' . $money($payload['total']) : '');
        case 'confirmed':
            return 'Order confirmed';
        case 'payment_recorded':
            $how = order_payment_method_label($payload['method'] ?? null);
            return 'Payment of ' . $money($payload['amount'] ?? 0) . ' recorded' . ($how ? ' (' . $how . ')' : '')
                . (!empty($payload['reference']) ? ' · ref ' . $payload['reference'] : '');
        case 'fulfilled':
            return !empty($payload['partial']) ? 'Items partially fulfilled' : 'Items fulfilled';
        case 'shipped':
            $label = 'Shipped' . (!empty($payload['courier']) ? ' with ' . $payload['courier'] : '');
            return $label . (!empty($payload['tracking']) ? ' · ' . $payload['tracking'] : '');
        case 'shipment':
            return 'Shipment created with ' . ($payload['courier'] ?? 'courier') . (!empty($payload['tracking']) ? ' · ' . $payload['tracking'] : '');
        case 'delivered':
            return 'Delivered';
        case 'cancelled':
            return 'Order cancelled' . (!empty($payload['reason']) ? ': ' . $payload['reason'] : '');
        case 'refund_requested':
            return 'Refund requested' . (isset($payload['amount']) ? ' · ' . $money($payload['amount']) : '');
        case 'note':
            return (string) ($payload['body'] ?? 'Note');
    }
    return ucfirst(str_replace('_', ' ', $type));
}

/**
 * Chronological timeline for an order: events + internal notes + shipments.
 *
 * Shipments already described by a "shipped" event (payload.shipment_id) are not repeated.
 * Every entry: kind ('event' | 'note' | 'shipment'), type, label, at (Y-m-d H:i:s),
 * actor_type, actor_id, actor_name, actor_role (owner/manager/staff/customer/null), payload (array). Oldest first.
 *
 * @return array<int, array{kind:string,type:string,label:string,at:string,actor_type:string,actor_id:?int,actor_name:?string,payload:array}>
 */
function order_timeline(PDO $conn, int $orderId): array
{
    $out = [];
    $covered = [];

    $st = $conn->prepare("SELECT e.id, e.type, e.actor_type, e.actor_id, e.payload, e.created_at,
            CASE e.actor_type WHEN 'staff' THEN a.name WHEN 'customer' THEN c.name ELSE NULL END AS actor_name,
            CASE e.actor_type WHEN 'staff' THEN a.role ELSE NULL END AS actor_role
        FROM order_events e
        LEFT JOIN admins a ON e.actor_type = 'staff' AND a.id = e.actor_id
        LEFT JOIN customers c ON e.actor_type = 'customer' AND c.id = e.actor_id
        WHERE e.order_id = ? ORDER BY e.created_at, e.id");
    $st->execute([$orderId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $payload = $r['payload'] !== null ? (json_decode((string) $r['payload'], true) ?: []) : [];
        if (!empty($payload['shipment_id'])) {
            $covered[(int) $payload['shipment_id']] = true;
        }
        $out[] = [
            'kind' => 'event',
            'id' => (int) $r['id'],
            'type' => $r['type'],
            'label' => order_event_label($r['type'], $payload),
            'at' => $r['created_at'],
            'actor_type' => $r['actor_type'],
            'actor_id' => $r['actor_id'] !== null ? (int) $r['actor_id'] : null,
            'actor_name' => $r['actor_type'] === 'system' ? 'TechZone' : $r['actor_name'],
            'actor_role' => $r['actor_type'] === 'customer' ? 'customer' : $r['actor_role'],
            'payload' => $payload,
        ];
    }

    $st = $conn->prepare('SELECT n.id, n.admin_id, n.body, n.created_at, a.name AS actor_name, a.role AS actor_role FROM order_notes n
        LEFT JOIN admins a ON a.id = n.admin_id WHERE n.order_id = ? ORDER BY n.created_at, n.id');
    $st->execute([$orderId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = [
            'kind' => 'note',
            'id' => (int) $r['id'],
            'type' => 'note',
            'label' => $r['body'],
            'at' => $r['created_at'],
            'actor_type' => 'staff',
            'actor_id' => $r['admin_id'] !== null ? (int) $r['admin_id'] : null,
            'actor_name' => $r['actor_name'],
            'actor_role' => $r['actor_role'],
            'payload' => ['body' => $r['body']],
        ];
    }

    $st = $conn->prepare('SELECT id, courier, tracking_number, shipped_at, delivered_at FROM order_shipments WHERE order_id = ? ORDER BY shipped_at, id');
    $st->execute([$orderId]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($covered[(int) $r['id']])) {
            continue;
        }
        $payload = ['shipment_id' => (int) $r['id'], 'courier' => $r['courier'], 'tracking' => $r['tracking_number'], 'delivered_at' => $r['delivered_at']];
        $out[] = [
            'kind' => 'shipment',
            'id' => (int) $r['id'],
            'type' => 'shipment',
            'label' => order_event_label('shipment', $payload),
            'at' => $r['shipped_at'],
            'actor_type' => 'system',
            'actor_id' => null,
            'actor_name' => null,
            'actor_role' => null,
            'payload' => $payload,
        ];
    }

    usort($out, static fn(array $a, array $b): int => [$a['at'], $a['kind'] === 'note' ? 1 : 0, $a['id']] <=> [$b['at'], $b['kind'] === 'note' ? 1 : 0, $b['id']]);
    return $out;
}

/**
 * Customer-facing timeline (storefront order page, public tracking page). Oldest first.
 *
 * Only what the customer should see: Order placed · Confirmed · Payment received · Shipped with
 * <courier> — tracking <no> · Tracking updated · Delivered · Cancelled. Internal notes, staff
 * names, fulfilment steps and payment details (amount, method, reference) are left out.
 *
 * @return array<int, array{type:string,label:string,at:string}>
 */
function order_customer_timeline(PDO $conn, int $orderId): array
{
    $st = $conn->prepare('SELECT type, payload, created_at FROM order_events WHERE order_id = ? ORDER BY created_at, id');
    $st->execute([$orderId]);
    $out = [];
    $paid = false;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $p = $r['payload'] !== null ? (json_decode((string) $r['payload'], true) ?: []) : [];
        $label = null;
        switch ($r['type']) {
            case 'placed':
                $label = 'Order placed';
                break;
            case 'confirmed':
                $label = 'Confirmed';
                break;
            case 'payment_recorded':
                if (!$paid) {
                    $label = 'Payment received';
                    $paid = true;
                }
                break;
            case 'shipped':
                $courier = trim((string) ($p['courier'] ?? ''));
                $label = $courier !== '' ? 'Shipped with ' . $courier : 'Shipped';
                if (!empty($p['tracking'])) {
                    $label .= ' — tracking ' . $p['tracking'];
                }
                break;
            case 'note':
                if (!empty($p['tracking_update'])) {
                    $label = 'Tracking updated: ' . ($p['courier'] ?? '') . (!empty($p['tracking']) ? ' — tracking ' . $p['tracking'] : '');
                }
                break;
            case 'delivered':
                $label = 'Delivered';
                break;
            case 'cancelled':
                $label = 'Cancelled';
                break;
        }
        if ($label !== null) {
            $out[] = ['type' => $r['type'] === 'note' ? 'tracking' : $r['type'], 'label' => $label, 'at' => $r['created_at']];
        }
    }
    return $out;
}

/**
 * Four-step progress for customers: 1 Placed · 2 Confirmed · 3 Shipped · 4 Delivered.
 *
 * @return array{step:int,cancelled:bool,steps:string[]}
 */
function order_progress(array $order): array
{
    $step = ['Pending' => 1, 'Processing' => 2, 'Shipped' => 3, 'Delivered' => 4][$order['status']] ?? 1;
    return ['step' => $step, 'cancelled' => $order['status'] === 'Cancelled', 'steps' => ['Placed', 'Confirmed', 'Shipped', 'Delivered']];
}
