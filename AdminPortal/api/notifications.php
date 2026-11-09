<?php
/**
 * Bell notifications (core/notifications.php). Every role.
 *   GET            -> {items:[{kind,icon,tone,title,sub,href,count,unseen,latest}], total, unseen}
 *   POST {seen:1}  -> marks everything currently listed as seen for this session
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/notifications.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    enforce_csrf_json();
    admin_notifications_mark_seen($conn);
} elseif ($method !== 'GET') {
    json_error('Invalid request method', 405);
}
$n = admin_notifications($conn);
$items = array_map(static function (array $it): array {
    unset($it['_ids']);
    $it['href'] = admin_url($it['href']);
    return $it;
}, array_values(array_filter($n['items'], static fn($it) => $it['count'] > 0)));
json_response(['success' => true, 'items' => $items, 'total' => $n['total'], 'unseen' => $n['unseen']]);
