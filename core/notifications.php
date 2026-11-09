<?php
/**
 * Bell notifications (docs/ADMIN.md "🔔"), computed on the fly: there is no notifications table.
 *
 * Each kind is a live query that returns the ids of the items behind it. "Mark seen" stores those
 * ids in the admin's session ($_SESSION['admin_ntf_seen'][kind]); the badge counts only ids that
 * were not there when the admin last looked, so it clears until something new appears. The
 * session key starts with admin_ so signing out drops it with the rest of the admin identity.
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/settings.php';

/**
 * Every notification kind with its current ids, count, unseen count and link.
 *
 * @return array{items: list<array{kind:string,icon:string,tone:string,title:string,sub:string,href:string,count:int,unseen:int,latest:?string}>, total:int, unseen:int}
 */
function admin_notifications(PDO $conn): array
{
    $defs = function_exists('current_seller_id') && current_seller_id() !== null
        ? seller_notification_defs((int) current_seller_id())
        : admin_notification_defs();
    $seen = $_SESSION['admin_ntf_seen'] ?? [];
    $out = [];
    $total = 0;
    $unseenTotal = 0;
    foreach ($defs as $kind => $d) {
        try {
            $rows = $conn->query($d['sql'])->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_exception($e, 'notifications-' . $kind);
            $rows = [];
        }
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $seenIds = array_flip(array_map('intval', (array) ($seen[$kind] ?? [])));
        $unseen = 0;
        foreach ($ids as $id) {
            if (!isset($seenIds[$id])) {
                $unseen++;
            }
        }
        $n = count($ids);
        $total += $n;
        $unseenTotal += $unseen;
        $out[] = [
            'kind' => $kind,
            'icon' => $d['icon'],
            'tone' => $d['tone'],
            'title' => $n . ' ' . $d['title'][$n === 1 ? 0 : 1],
            'sub' => $d['sub'],
            'href' => $d['href'],
            'count' => $n,
            'unseen' => $unseen,
            'latest' => $rows[0]['at'] ?? null,
            '_ids' => $ids,
        ];
    }
    return ['items' => $out, 'total' => $total, 'unseen' => $unseenTotal];
}

/**
 * Seller accounts: only their own work. Orders containing their products that they have not shipped yet,
 * and their products at or below the low-stock threshold. $sellerId is an int, safe to inline.
 */
function seller_notification_defs(int $sellerId): array
{
    return [
        'orders' => [
            'sql' => "SELECT o.id, o.order_date AS at FROM orders o WHERE o.status IN ('Pending', 'Processing') AND o.cancelled_at IS NULL
                AND EXISTS (SELECT 1 FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = o.id AND p.seller_id = $sellerId)
                AND NOT EXISTS (SELECT 1 FROM order_shipments s WHERE s.order_id = o.id AND s.seller_id = $sellerId)
                ORDER BY o.order_date DESC",
            'icon' => 'fa-box', 'tone' => 'info', 'href' => 'manage/orders.php?tab=unfulfilled',
            'title' => ['order to fulfil', 'orders to fulfil'], 'sub' => 'Orders with your items, not shipped yet',
        ],
        'low_stock' => [
            'sql' => "SELECT id, updated_at AS at FROM products WHERE seller_id = $sellerId AND status <> 'archived' AND inventory <= low_stock_threshold ORDER BY inventory, id",
            'icon' => 'fa-triangle-exclamation', 'tone' => 'warn', 'href' => 'manage/inventory.php',
            'title' => ['of your products low on stock', 'of your products low on stock'], 'sub' => 'At or below their threshold',
        ],
    ];
}

/** TechZone staff notification kinds. */
function admin_notification_defs(): array
{
    return [
        'orders' => [
            'sql' => "SELECT id, order_date AS at FROM orders WHERE status = 'Pending' AND order_date >= NOW() - INTERVAL 1 DAY ORDER BY order_date DESC",
            'icon' => 'fa-bag-shopping', 'tone' => 'info', 'href' => 'manage/orders.php?tab=open',
            'title' => ['new order to confirm', 'new orders to confirm'], 'sub' => 'Placed in the last 24 hours',
        ],
        'payments' => [
            'sql' => "SELECT id, created_at AS at FROM order_events WHERE type = 'payment_recorded' AND created_at >= CURDATE() ORDER BY created_at DESC",
            'icon' => 'fa-money-bill-wave', 'tone' => 'ok', 'href' => 'manage/orders.php?tab=all',
            'title' => ['payment recorded today', 'payments recorded today'], 'sub' => 'Cash on delivery and JazzCash',
        ],
        'low_stock' => [
            'sql' => "SELECT id, updated_at AS at FROM products WHERE status <> 'archived' AND inventory <= low_stock_threshold ORDER BY inventory, id",
            'icon' => 'fa-triangle-exclamation', 'tone' => 'warn', 'href' => 'manage/inventory.php',
            'title' => ['product low on stock', 'products low on stock'], 'sub' => 'At or below their threshold',
        ],
        'reviews' => [
            'sql' => "SELECT id, created_at AS at FROM reviews WHERE status = 'Pending' ORDER BY created_at DESC",
            'icon' => 'fa-star', 'tone' => 'neutral', 'href' => 'manage/reviews.php',
            'title' => ['review awaiting approval', 'reviews awaiting approval'], 'sub' => 'Hidden on the storefront until approved',
        ],
        'messages' => [
            'sql' => "SELECT id, created_at AS at FROM contact_messages WHERE status = 'new' ORDER BY created_at DESC",
            'icon' => 'fa-envelope', 'tone' => 'neutral', 'href' => 'manage/contact_messages.php',
            'title' => ['unread message', 'unread messages'], 'sub' => 'From the contact form',
        ],
    ] + (function_exists('can') && can('sellers.view') ? [
        'applications' => [
            'sql' => "SELECT id, created_at AS at FROM seller_applications WHERE status = 'pending' ORDER BY created_at DESC",
            'icon' => 'fa-store', 'tone' => 'info', 'href' => 'manage/sellers.php',
            'title' => ['seller application to review', 'seller applications to review'], 'sub' => 'From the Sell on TechZone form',
        ],
    ] : []);
}

/**
 * Remember every current notification id as seen (session only).
 */
function admin_notifications_mark_seen(PDO $conn): void
{
    $seen = [];
    foreach (admin_notifications($conn)['items'] as $it) {
        $seen[$it['kind']] = array_slice($it['_ids'], 0, 500);
    }
    $_SESSION['admin_ntf_seen'] = $seen;
}
