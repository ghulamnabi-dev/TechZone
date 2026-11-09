<?php
/**
 * Marketplace back-office (docs/STOREFRONT.md "Marketplace"): seller applications, sellers, the
 * seller's view of an order, seller fulfilment/shipping and weekly payout statements.
 *
 * Scoping rules (enforced by the APIs with core/permissions.php helpers, implemented here):
 *   - a seller sees only orders that contain at least one of their products, and on those orders only
 *     their own lines, their own subtotal, the customer's name and city (never phone, email, address,
 *     payment details, totals or other sellers' items);
 *   - a seller fulfils / ships only their own lines; a seller shipment is an order_shipments row with
 *     seller_id set, and order_sync_status() counts it towards "Shipped" only once every line is covered.
 *
 * Payouts are computed, no money moves: for each Monday..Sunday week, the seller's lines delivered that
 * week (delivered_at of the TechZone shipment, or of the seller's own shipment) give gross; commission =
 * gross x sellers.commission_pct; net = gross - commission. A week is "Upcoming" until the owner marks it
 * paid (seller_payouts row: a snapshot of the figures + bank reference). Cancelled and refunded orders
 * never count.
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/validation.php';

/** A request for a row or line that belongs to another seller (the API answers 403). */
class SellerScopeException extends RuntimeException
{
}

/** House seller (TechZone itself); products.seller_id defaults to it and it is never paid out. */
const HOUSE_SELLER_ID = 1;

/** Seller row or null. */
function marketplace_seller(PDO $conn, int $sellerId): ?array
{
    $st = $conn->prepare('SELECT * FROM sellers WHERE id = ?');
    $st->execute([$sellerId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/** "Al-Fatah Tech" -> "al-fatah-tech", unique in sellers.slug. */
function marketplace_unique_slug(PDO $conn, string $name, ?string $wanted = null): string
{
    $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($wanted !== null && trim($wanted) !== '' ? $wanted : $name)), '-');
    $base = substr($base !== '' ? $base : 'seller', 0, 70);
    $slug = $base;
    $st = $conn->prepare('SELECT 1 FROM sellers WHERE slug = ?');
    for ($i = 2; $i < 500; $i++) {
        $st->execute([$slug]);
        if (!$st->fetchColumn()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
    }
    return $base . '-' . bin2hex(random_bytes(3));
}

/** Pakistani phone "0300-2145566" -> wa.me digits "923002145566" (null when it does not look like a mobile). */
function marketplace_whatsapp_digits(?string $phone): ?string
{
    $d = preg_replace('/\D+/', '', (string) $phone);
    if (preg_match('/^03\d{9}$/', $d)) {
        return '92' . substr($d, 1);
    }
    if (preg_match('/^923\d{9}$/', $d)) {
        return $d;
    }
    return null;
}

/* ==========================================================================
   The seller's view of an order
   ========================================================================== */

/** The seller's own lines on an order (order_items + product image / SKU). */
function seller_order_lines(PDO $conn, int $orderId, int $sellerId): array
{
    $st = $conn->prepare('SELECT oi.id, oi.product_id, oi.product_name, oi.price, oi.quantity, oi.fulfilled_qty, oi.subtotal, oi.size, oi.color,
            p.imagelink, p.sku, p.seller_id
        FROM order_items oi JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = ? AND p.seller_id = ? ORDER BY oi.id');
    $st->execute([$orderId, $sellerId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** unfulfilled | partial | fulfilled for a set of lines. */
function seller_lines_state(array $lines): string
{
    $q = 0;
    $f = 0;
    foreach ($lines as $l) {
        $q += (int) $l['quantity'];
        $f += (int) $l['fulfilled_qty'];
    }
    return $q > 0 && $f >= $q ? 'fulfilled' : ($f > 0 ? 'partial' : 'unfulfilled');
}

/** admins.id of every account working for the seller. */
function seller_account_ids(PDO $conn, int $sellerId): array
{
    $st = $conn->prepare("SELECT id FROM admins WHERE seller_id = ? AND role = 'seller'");
    $st->execute([$sellerId]);
    return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Everything the order page shows a seller, and nothing else: their lines, their subtotal, name + city,
 * their shipments (and TechZone's, which carry their items too), their notes and a filtered timeline.
 * Returns null when the order has none of the seller's products.
 */
function seller_order_detail(PDO $conn, int $orderId, int $sellerId): ?array
{
    $order = order_get($conn, $orderId);
    $lines = $order ? seller_order_lines($conn, $orderId, $sellerId) : [];
    if (!$order || !$lines) {
        return null;
    }
    $mine = array_flip(array_map(static fn($l) => (int) $l['id'], $lines));
    $accounts = array_flip(seller_account_ids($conn, $sellerId));
    $seller = marketplace_seller($conn, $sellerId);

    $st = $conn->prepare('SELECT id, courier, tracking_number, shipped_at, delivered_at, seller_id FROM order_shipments
        WHERE order_id = ? AND (seller_id = ? OR seller_id IS NULL) ORDER BY shipped_at, id');
    $st->execute([$orderId, $sellerId]);
    $shipments = [];
    $own = null;
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $s) {
        $s['by'] = $s['seller_id'] === null ? 'TechZone' : ($seller['name'] ?? 'You');
        $s['own'] = $s['seller_id'] !== null;
        $shipments[] = $s;
        if ($s['own']) {
            $own = $s;
        }
    }

    $units = 0;
    $subtotal = 0.0;
    $left = 0;
    foreach ($lines as $l) {
        $units += (int) $l['quantity'];
        $subtotal += (float) $l['subtotal'];
        $left += (int) $l['quantity'] - (int) $l['fulfilled_qty'];
    }
    $state = seller_lines_state($lines);

    // Timeline: what concerns the seller's lines; TechZone staff appear as "TechZone"
    $timeline = [];
    foreach (order_timeline($conn, $orderId) as $t) {
        $p = $t['payload'];
        if ($t['kind'] === 'note') {
            if (!isset($accounts[(int) $t['actor_id']])) {
                continue;
            }
        } elseif ($t['kind'] === 'shipment') {
            $p = ['courier' => $p['courier'] ?? null, 'tracking' => $p['tracking'] ?? null];
        } else {
            switch ($t['type']) {
                case 'placed':
                    $t['label'] = 'Order placed';
                    $p = [];
                    break;
                case 'confirmed':
                case 'delivered':
                    $p = [];
                    break;
                case 'fulfilled':
                    $items = array_values(array_filter((array) ($p['items'] ?? []), static fn($i) => isset($mine[(int) ($i['item_id'] ?? 0)])));
                    if (!$items) {
                        continue 2;
                    }
                    $p = ['items' => $items];
                    $t['label'] = 'Items fulfilled';
                    break;
                case 'shipped':
                    if (!empty($p['seller_id']) && (int) $p['seller_id'] !== $sellerId) {
                        continue 2;
                    }
                    $p = ['courier' => $p['courier'] ?? null, 'tracking' => $p['tracking'] ?? null];
                    $t['label'] = order_event_label('shipped', $p + ['seller_name' => empty($t['payload']['seller_id']) ? 'TechZone' : ($seller['name'] ?? '')]);
                    break;
                case 'cancelled':
                    $p = [];
                    $t['label'] = 'Order cancelled';
                    break;
                default:   // payment_recorded, refund_requested, tracking notes: not the seller's business
                    continue 2;
            }
        }
        $staffOfShop = $t['actor_type'] === 'staff' && isset($accounts[(int) $t['actor_id']]);
        if ($t['actor_type'] === 'staff' && !$staffOfShop) {
            $t['actor_name'] = 'TechZone';
            $t['actor_role'] = null;
        }
        if ($t['actor_type'] === 'customer') {
            $t['actor_name'] = $order['customer_name'];
        }
        $t['actor_id'] = null;
        $t['payload'] = $p;
        $timeline[] = $t;
    }

    $ids = array_keys($accounts);
    $notes = [];
    if ($ids) {
        $in = implode(',', array_map('intval', $ids));
        $st = $conn->prepare("SELECT n.id, n.body, n.created_at, a.name AS admin_name, a.role AS admin_role
            FROM order_notes n JOIN admins a ON a.id = n.admin_id WHERE n.order_id = ? AND n.admin_id IN ($in) ORDER BY n.created_at DESC, n.id DESC");
        $st->execute([$orderId]);
        $notes = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $cancelled = $order['cancelled_at'] !== null || $order['status'] === 'Cancelled';
    $handedOver = in_array($order['status'], ['Shipped', 'Delivered'], true);
    return [
        'order' => [
            'id' => (int) $order['id'],
            'order_date' => $order['order_date'],
            'updated_at' => $order['updated_at'],
            'status' => $order['status'],
            'fulfillment_status' => $state,
            'customer_name' => $order['customer_name'],
            'city' => $order['city'],
            'cancelled_at' => $order['cancelled_at'],
            'subtotal' => round($subtotal, 2),
            'units' => $units,
        ],
        'items' => $lines,
        'shipments' => $shipments,
        'shipment' => $own ?? ($shipments ? $shipments[count($shipments) - 1] : null),
        'customer' => ['name' => $order['customer_name'], 'city' => $order['city']],
        'notes' => $notes,
        'timeline' => $timeline,
        'allowed' => [
            'fulfil' => !$cancelled && !$handedOver && $left > 0,
            'ship' => !$cancelled && !$handedOver && $own === null,
            'note' => true,
        ],
        'seller' => ['id' => $sellerId, 'name' => $seller['name'] ?? ''],
    ];
}

/**
 * Fulfil the seller's own lines (partial allowed). $items = order_items.id => qty; null = all of theirs left.
 *
 * @throws SellerScopeException when $items names a line that is not theirs
 * @throws OrderActionException for state problems
 */
function seller_order_fulfil(PDO $conn, int $orderId, int $sellerId, ?array $items, ?int $adminId = null): array
{
    $lines = seller_order_lines($conn, $orderId, $sellerId);
    $own = [];
    foreach ($lines as $l) {
        $own[(int) $l['id']] = (int) $l['quantity'] - (int) $l['fulfilled_qty'];
    }
    if ($items === null) {
        $items = array_filter($own, static fn($left) => $left > 0);
    } else {
        foreach (array_keys($items) as $itemId) {
            if (!array_key_exists((int) $itemId, $own)) {
                throw new SellerScopeException('That line belongs to another seller, so you can\'t fulfil it.');
            }
        }
    }
    if (!$items) {
        throw new OrderActionException("Nothing of yours is left to fulfil on order #$orderId.");
    }
    $order = order_get($conn, $orderId);
    if ($order && in_array($order['status'], ['Shipped', 'Delivered'], true)) {
        throw new OrderActionException("Order #$orderId has already been handed to the courier.");
    }
    return order_fulfil($conn, $orderId, $items, $adminId);
}

/**
 * The seller hands their own lines to a courier: fulfils whatever of theirs is left, records a shipment
 * with seller_id, a "shipped" timeline event naming the seller, and re-derives the order status.
 */
function seller_order_ship(PDO $conn, int $orderId, int $sellerId, string $courier, ?string $tracking, ?int $adminId = null): array
{
    $adminId = order_actor($adminId);
    $courier = mb_substr(trim($courier), 0, 60);
    $tracking = $tracking !== null && trim($tracking) !== '' ? mb_substr(trim($tracking), 0, 80) : null;
    if ($courier === '') {
        throw new OrderActionException('Choose a courier.');
    }
    return order_tx($conn, function () use ($conn, $orderId, $sellerId, $courier, $tracking, $adminId): array {
        $order = order_lock($conn, $orderId);
        order_assert_open($order, 'shipped');
        if (in_array($order['status'], ['Shipped', 'Delivered'], true)) {
            throw new OrderActionException("Order #$orderId is already {$order['status']}.");
        }
        $st = $conn->prepare('SELECT COUNT(*) FROM order_shipments WHERE order_id = ? AND seller_id = ?');
        $st->execute([$orderId, $sellerId]);
        if ((int) $st->fetchColumn() > 0) {
            throw new OrderActionException("You have already shipped your items on order #$orderId.");
        }
        $lines = seller_order_lines($conn, $orderId, $sellerId);
        if (!$lines) {
            throw new SellerScopeException('None of the items on this order are yours.');
        }
        $left = [];
        foreach ($lines as $l) {
            $n = (int) $l['quantity'] - (int) $l['fulfilled_qty'];
            if ($n > 0) {
                $left[(int) $l['id']] = $n;
            }
        }
        if ($left) {
            order_fulfil($conn, $orderId, $left, $adminId);
        }
        $conn->prepare('INSERT INTO order_shipments (order_id, courier, tracking_number, shipped_at, seller_id) VALUES (?, ?, ?, NOW(), ?)')
            ->execute([$orderId, $courier, $tracking, $sellerId]);
        $shipmentId = (int) $conn->lastInsertId();
        $seller = marketplace_seller($conn, $sellerId);
        $sellerName = $seller['name'] ?? ('Seller #' . $sellerId);
        $items = array_map(static fn($l) => ['item_id' => (int) $l['id'], 'name' => $l['product_name'], 'quantity' => (int) $l['quantity']], $lines);
        order_event($conn, $orderId, 'shipped', [
            'shipment_id' => $shipmentId, 'courier' => $courier, 'tracking' => $tracking,
            'seller_id' => $sellerId, 'seller_name' => $sellerName, 'items' => $items,
        ], 'staff', $adminId);
        $status = order_sync_status($conn, $orderId);
        audit('order.ship', 'order', $orderId, "Shipped {$sellerName}'s items on order #$orderId with $courier",
            ['status' => $order['status']], ['status' => $status, 'seller_id' => $sellerId, 'courier' => $courier, 'tracking' => $tracking, 'shipment_id' => $shipmentId]);
        return order_get($conn, $orderId);
    });
}

/**
 * The seller's orders list: one row per order containing their products, with only their units,
 * their subtotal (as "total") and the fulfilment state of their lines.
 */
function seller_orders_list(PDO $conn, int $sellerId, ?string $status = null, string $search = '', int $limit = 1000): array
{
    $sql = 'SELECT o.id, o.customer_name, o.city, o.order_date, o.updated_at, o.status,
            SUM(i.quantity) AS items_count, SUM(i.subtotal) AS total, SUM(i.fulfilled_qty) AS fulfilled_units,
            (SELECT COUNT(*) FROM order_shipments s WHERE s.order_id = o.id AND s.seller_id = ?) AS own_shipments
        FROM orders o JOIN order_items i ON i.order_id = o.id JOIN products p ON p.id = i.product_id
        WHERE p.seller_id = ?';
    $params = [$sellerId, $sellerId];
    if ($status !== null) {
        $sql .= ' AND o.status = ?';
        $params[] = $status;
    }
    if ($search !== '') {
        $sql .= ' AND (o.customer_name LIKE ? OR o.city LIKE ? OR o.id = ?)';
        $like = '%' . addcslashes($search, '%_\\') . '%';
        array_push($params, $like, $like, (int) ltrim($search, '#'));
    }
    $sql .= ' GROUP BY o.id, o.customer_name, o.city, o.order_date, o.updated_at, o.status ORDER BY o.order_date DESC, o.id DESC LIMIT ' . max(1, min(5000, $limit));
    $st = $conn->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $q = (int) $r['items_count'];
        $f = (int) $r['fulfilled_units'];
        $r['id'] = (int) $r['id'];
        $r['items_count'] = $q;
        $r['total'] = (float) $r['total'];
        $r['fulfillment_status'] = $q > 0 && $f >= $q ? 'fulfilled' : ($f > 0 ? 'partial' : 'unfulfilled');
        $r['shipped_by_me'] = (int) $r['own_shipments'] > 0;
        unset($r['fulfilled_units'], $r['own_shipments']);
    }
    unset($r);
    return $rows;
}

/** Open orders where the seller still has to fulfil or ship their lines (newest first): [{id, at}]. */
function seller_orders_to_fulfil(PDO $conn, int $sellerId): array
{
    $st = $conn->prepare("SELECT o.id, o.order_date AS at FROM orders o
        WHERE o.status IN ('Pending', 'Processing') AND o.cancelled_at IS NULL
          AND EXISTS (SELECT 1 FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = o.id AND p.seller_id = ?)
          AND NOT EXISTS (SELECT 1 FROM order_shipments s WHERE s.order_id = o.id AND s.seller_id = ?)
        ORDER BY o.order_date DESC, o.id DESC");
    $st->execute([$sellerId, $sellerId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/* ==========================================================================
   Payouts
   ========================================================================== */

/** Monday (Y-m-d) of the week containing $date. */
function payout_week_start(string $date): string
{
    $t = strtotime(substr($date, 0, 10));
    return date('Y-m-d', strtotime('-' . ((int) date('N', $t) - 1) . ' day', $t));
}

/** "2–8 Nov 2026" for the week starting $monday. */
function payout_week_label(string $monday): string
{
    $a = strtotime($monday);
    $b = strtotime('+6 day', $a);
    if (date('m', $a) === date('m', $b)) {
        return date('j', $a) . '–' . date('j M Y', $b);
    }
    return date('j M', $a) . ' – ' . date('j M Y', $b);
}

/** Today's date from the database clock (the same clock order dates use). */
function marketplace_today(PDO $conn): string
{
    return (string) $conn->query('SELECT CURDATE()')->fetchColumn();
}

/**
 * Gross of the seller's delivered lines per week: [week_start => ['gross' => float, 'lines' => int, 'units' => int]].
 * A line is delivered when a TechZone shipment of its order, or the seller's own shipment, has delivered_at.
 */
function seller_delivered_by_week(PDO $conn, int $sellerId): array
{
    $st = $conn->prepare("SELECT i.id, i.subtotal, i.quantity,
            (SELECT MAX(s.delivered_at) FROM order_shipments s WHERE s.order_id = i.order_id AND s.delivered_at IS NOT NULL
                AND (s.seller_id IS NULL OR s.seller_id = p.seller_id)) AS delivered_at
        FROM order_items i JOIN products p ON p.id = i.product_id JOIN orders o ON o.id = i.order_id
        WHERE p.seller_id = ? AND o.cancelled_at IS NULL AND o.payment_status <> 'refunded'");
    $st->execute([$sellerId]);
    $weeks = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($r['delivered_at'] === null) {
            continue;
        }
        $w = payout_week_start((string) $r['delivered_at']);
        $weeks[$w] = $weeks[$w] ?? ['gross' => 0.0, 'lines' => 0, 'units' => 0];
        $weeks[$w]['gross'] += (float) $r['subtotal'];
        $weeks[$w]['lines']++;
        $weeks[$w]['units'] += (int) $r['quantity'];
    }
    return $weeks;
}

/** Commission and net for a gross at a commission percentage. */
function payout_split(float $gross, float $pct): array
{
    $commission = round($gross * $pct / 100, 2);
    return ['gross' => round($gross, 2), 'commission_pct' => round($pct, 2), 'commission' => $commission, 'net' => round($gross - $commission, 2)];
}

/**
 * Weekly statements, newest first: every week with deliveries or a payout row, plus the current week.
 * Each: week_start, week_end, label, due_date (the Monday after), gross, commission_pct, commission, net,
 * lines, units, status ('paid' | 'upcoming'), closed (bool), current (bool), paid_at, reference.
 */
function seller_payout_statements(PDO $conn, int $sellerId): array
{
    $seller = marketplace_seller($conn, $sellerId);
    $pct = (float) ($seller['commission_pct'] ?? 8);
    $today = marketplace_today($conn);
    $thisWeek = payout_week_start($today);
    $weeks = seller_delivered_by_week($conn, $sellerId);

    $st = $conn->prepare('SELECT p.*, a.name AS paid_by_name FROM seller_payouts p LEFT JOIN admins a ON a.id = p.paid_by WHERE p.seller_id = ?');
    $st->execute([$sellerId]);
    $paid = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $paid[$r['week_start']] = $r;
    }
    $keys = array_unique(array_merge(array_keys($weeks), array_keys($paid), [$thisWeek]));
    rsort($keys);
    $out = [];
    foreach ($keys as $w) {
        $live = $weeks[$w] ?? ['gross' => 0.0, 'lines' => 0, 'units' => 0];
        if (isset($paid[$w])) {
            $p = $paid[$w];
            $fig = ['gross' => (float) $p['gross'], 'commission_pct' => (float) $p['commission_pct'], 'commission' => (float) $p['commission'], 'net' => (float) $p['net']];
        } else {
            $fig = payout_split($live['gross'], $pct);
        }
        $out[] = $fig + [
            'week_start' => $w,
            'week_end' => date('Y-m-d', strtotime($w . ' +6 day')),
            'label' => payout_week_label($w),
            'due_date' => date('Y-m-d', strtotime($w . ' +7 day')),
            'lines' => $live['lines'],
            'units' => $live['units'],
            'status' => isset($paid[$w]) ? 'paid' : 'upcoming',
            'closed' => $w < $thisWeek,
            'current' => $w === $thisWeek,
            'paid_at' => $paid[$w]['paid_at'] ?? null,
            'reference' => $paid[$w]['reference'] ?? null,
            'paid_by' => $paid[$w]['paid_by_name'] ?? null,
        ];
    }
    return $out;
}

/**
 * Payout summary for the seller home / admin lists:
 *   due        {date, net, gross, commission, weeks}  closed weeks not yet paid, due next payout Monday
 *   this_week  {gross, commission, net, date}         accruing, paid the Monday after this week
 *   paid_total, paid_last {date, net, reference}
 */
function seller_payout_summary(PDO $conn, int $sellerId, ?array $statements = null): array
{
    $rows = $statements ?? seller_payout_statements($conn, $sellerId);
    $today = marketplace_today($conn);
    $nextMonday = date('N', strtotime($today)) === '1' ? $today : date('Y-m-d', strtotime('next monday', strtotime($today)));
    $due = ['date' => $nextMonday, 'net' => 0.0, 'gross' => 0.0, 'commission' => 0.0, 'weeks' => 0];
    $thisWeek = ['gross' => 0.0, 'commission' => 0.0, 'net' => 0.0, 'date' => null];
    $paidTotal = 0.0;
    $paidLast = null;
    foreach ($rows as $r) {
        if ($r['status'] === 'paid') {
            $paidTotal += $r['net'];
            if ($paidLast === null || $r['paid_at'] > $paidLast['date']) {
                $paidLast = ['date' => $r['paid_at'], 'net' => $r['net'], 'reference' => $r['reference']];
            }
        } elseif ($r['closed'] && $r['gross'] > 0) {
            $due['net'] += $r['net'];
            $due['gross'] += $r['gross'];
            $due['commission'] += $r['commission'];
            $due['weeks']++;
        } elseif ($r['current']) {
            $thisWeek = ['gross' => $r['gross'], 'commission' => $r['commission'], 'net' => $r['net'], 'date' => $r['due_date']];
        }
    }
    foreach (['net', 'gross', 'commission'] as $k) {
        $due[$k] = round($due[$k], 2);
    }
    return ['today' => $today, 'due' => $due, 'this_week' => $thisWeek, 'paid_total' => round($paidTotal, 2), 'paid_last' => $paidLast];
}

/**
 * Owner marks a closed week paid (sellers.payouts): snapshots the computed figures with the reference.
 *
 * @throws OrderActionException (message safe to show) when the week is open, empty or already paid
 */
function seller_mark_paid(PDO $conn, int $sellerId, string $weekStart, string $reference, ?int $adminId = null): array
{
    $reference = mb_substr(trim($reference), 0, 80);
    if ($reference === '') {
        throw new OrderActionException('Enter the bank or transfer reference.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart) || payout_week_start($weekStart) !== $weekStart) {
        throw new OrderActionException('Choose a week from the statement.');
    }
    $seller = marketplace_seller($conn, $sellerId);
    if (!$seller || $sellerId === HOUSE_SELLER_ID) {
        throw new OrderActionException('Seller not found.');
    }
    $row = null;
    foreach (seller_payout_statements($conn, $sellerId) as $r) {
        if ($r['week_start'] === $weekStart) {
            $row = $r;
        }
    }
    if ($row === null || $row['gross'] <= 0) {
        throw new OrderActionException('Nothing was delivered for ' . $seller['name'] . ' in that week.');
    }
    if (!$row['closed']) {
        throw new OrderActionException('That week is still running; it can be paid from ' . date('D j M', strtotime($row['due_date'])) . '.');
    }
    if ($row['status'] === 'paid') {
        throw new OrderActionException('That week is already marked paid.');
    }
    $adminId = $adminId ?? (isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null);
    $conn->beginTransaction();
    try {
        $conn->prepare('INSERT INTO seller_payouts (seller_id, week_start, gross, commission_pct, commission, net, paid_at, reference, paid_by)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)')
            ->execute([$sellerId, $weekStart, $row['gross'], $row['commission_pct'], $row['commission'], $row['net'], $reference, $adminId]);
        $id = (int) $conn->lastInsertId();
        audit('seller.payout', 'seller', $sellerId, 'Marked ' . $seller['name'] . "'s payout for " . $row['label'] . ' paid (Rs ' . number_format($row['net'], 0) . ')',
            ['status' => 'upcoming'], ['status' => 'paid', 'week_start' => $weekStart, 'gross' => $row['gross'], 'commission' => $row['commission'], 'net' => $row['net'], 'reference' => $reference, 'payout_id' => $id]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            throw new OrderActionException('That week is already marked paid.');
        }
        throw $e;
    }
    return $row + ['status' => 'paid', 'reference' => $reference];
}

/**
 * Seller sales (their lines of non-cancelled orders, by order date) since a date: {revenue, orders, units}.
 */
function seller_sales_since(PDO $conn, int $sellerId, string $from): array
{
    $st = $conn->prepare("SELECT COALESCE(SUM(i.subtotal), 0) AS revenue, COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(i.quantity), 0) AS units
        FROM order_items i JOIN products p ON p.id = i.product_id JOIN orders o ON o.id = i.order_id
        WHERE p.seller_id = ? AND o.cancelled_at IS NULL AND o.order_date >= ?");
    $st->execute([$sellerId, $from]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return ['revenue' => (float) $r['revenue'], 'orders' => (int) $r['orders'], 'units' => (int) $r['units']];
}

/* ==========================================================================
   Seller profile input (admin seller page and the seller's own store profile)
   ========================================================================== */

/** Seller statuses staff can set (paused is kept for older rows). */
const SELLER_STATUSES = ['active', 'paused', 'suspended'];

/**
 * Validate the profile fields present in $fields from $d.
 * Returns [values, errors] with errors keyed by field for validation_problem().
 */
function marketplace_profile_input(array $d, array $fields): array
{
    $v = [];
    $e = [];
    foreach ($fields as $f) {
        $raw = $d[$f] ?? '';
        switch ($f) {
            case 'name':
                $v[$f] = v_str($raw, 120, false);
                if ($v[$f] === null) {
                    $e[$f] = 'Give the shop a name (up to 120 characters).';
                }
                break;
            case 'market':
                $v[$f] = v_str($raw, 80);
                if ($v[$f] === null) {
                    $e[$f] = 'Keep the market under 80 characters.';
                }
                break;
            case 'city':
                $v[$f] = v_str($raw, 80, false);
                if ($v[$f] === null) {
                    $e[$f] = 'Enter the city.';
                }
                break;
            case 'address':
                $v[$f] = v_str($raw, 255);
                if ($v[$f] === null) {
                    $e[$f] = 'Keep the address under 255 characters.';
                }
                break;
            case 'phone':
                $v[$f] = v_str($raw, 30);
                if ($v[$f] === null || ($v[$f] !== '' && !preg_match('/^[0-9+][0-9\-\s()]{6,19}$/', $v[$f]))) {
                    $e[$f] = 'Enter a phone number such as 042-35761234 or 0300-1234567.';
                }
                break;
            case 'whatsapp':
                $s = v_str($raw, 30);
                $digits = preg_replace('/\D+/', '', (string) $s);
                if ($digits !== '' && preg_match('/^03\d{9}$/', $digits)) {
                    $digits = '92' . substr($digits, 1);
                }
                $v[$f] = $digits;
                if ($s === null || (trim((string) $s) !== '' && !preg_match('/^\d{10,15}$/', $digits))) {
                    $e[$f] = 'Enter the WhatsApp number with country code, e.g. 923001234567.';
                }
                break;
            case 'about':
                $v[$f] = v_str($raw, 1000);
                if ($v[$f] === null) {
                    $e[$f] = 'Keep the description under 1,000 characters.';
                }
                break;
            case 'commission_pct':
                $n = v_num($raw === '' ? null : $raw, 0, 50);
                $v[$f] = $n === null ? null : round($n, 2);
                if ($n === null) {
                    $e[$f] = 'Commission must be a number from 0 to 50 (%).';
                }
                break;
            case 'status':
                $v[$f] = v_enum($raw, SELLER_STATUSES);
                if ($v[$f] === null) {
                    $e[$f] = 'Choose Active or Suspended.';
                }
                break;
        }
        if (array_key_exists($f, $v) && $v[$f] === '' && in_array($f, ['market', 'address', 'phone', 'whatsapp', 'about'], true)) {
            $v[$f] = null;
        }
    }
    return [$v, $e];
}

/**
 * Marketplace sellers (not the house) for the admin grid: products, 30-day sales, payable.
 */
function marketplace_sellers_overview(PDO $conn): array
{
    $rows = $conn->query("SELECT s.id, s.slug, s.name, s.market, s.city, s.status, s.commission_pct, s.rating, s.joined_at,
            (SELECT COUNT(*) FROM products p WHERE p.seller_id = s.id AND p.status <> 'archived') AS products,
            (SELECT COALESCE(SUM(i.subtotal), 0) FROM order_items i JOIN products p ON p.id = i.product_id JOIN orders o ON o.id = i.order_id
                WHERE p.seller_id = s.id AND o.cancelled_at IS NULL AND o.order_date >= CURDATE() - INTERVAL 29 DAY) AS sales_30d,
            (SELECT a.email FROM admins a WHERE a.seller_id = s.id AND a.role = 'seller' ORDER BY a.id LIMIT 1) AS account_email
        FROM sellers s WHERE s.id <> " . HOUSE_SELLER_ID . " ORDER BY s.name")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $sum = seller_payout_summary($conn, (int) $r['id']);
        $r['id'] = (int) $r['id'];
        $r['products'] = (int) $r['products'];
        $r['sales_30d'] = (float) $r['sales_30d'];
        $r['commission_pct'] = (float) $r['commission_pct'];
        $r['rating'] = (float) $r['rating'];
        $r['payable'] = $sum['due']['net'];
        $r['payable_weeks'] = $sum['due']['weeks'];
        $r['accruing'] = $sum['this_week']['net'];
        $r['paid_total'] = $sum['paid_total'];
    }
    unset($r);
    return $rows;
}
