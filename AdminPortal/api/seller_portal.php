<?php
/**
 * Seller portal: the signed-in seller's own store profile and payout statements (seller.portal).
 *
 *   GET                                     {seller, statements, summary}
 *   PUT {about, phone, whatsapp, address}   the fields a seller may edit; name, market, commission and
 *                                           status stay with TechZone (manage/seller.php). Audited.
 *
 * The seller is always current_seller_id(): no id is ever read from the request.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/marketplace.php';

checkLogin(true);
json_headers();
require_can('seller.portal');

$sid = (int) current_seller_id();
$method = $_SERVER['REQUEST_METHOD'];

/** Public-facing fields of the seller row. */
function seller_portal_profile(PDO $conn, int $sid): array
{
    $s = marketplace_seller($conn, $sid) ?? [];
    return array_intersect_key($s, array_flip(['id', 'slug', 'name', 'market', 'address', 'city', 'phone', 'whatsapp', 'rating', 'about', 'joined_at', 'status', 'commission_pct']));
}

try {
    if ($method === 'GET') {
        $statements = seller_payout_statements($conn, $sid);
        json_response([
            'success' => true,
            'seller' => seller_portal_profile($conn, $sid),
            'statements' => $statements,
            'summary' => seller_payout_summary($conn, $sid, $statements),
        ]);
    }
    if ($method !== 'PUT') {
        json_error('Invalid request method', 405);
    }
    enforce_csrf_json();
    $data = request_json();
    $fields = array_values(array_filter(['about', 'phone', 'whatsapp', 'address'], static fn($f) => array_key_exists($f, $data)));
    [$vals, $errors] = marketplace_profile_input($data, $fields);
    if ($errors) {
        validation_problem($errors);
    }
    $seller = marketplace_seller($conn, $sid);
    [$before, $after] = audit_diff($seller, array_map(static fn($v) => $v === null ? null : (string) $v, $vals));
    if (!$after) {
        json_response(['success' => true, 'message' => 'No changes to save', 'changed' => [], 'seller' => seller_portal_profile($conn, $sid)]);
    }
    $conn->beginTransaction();
    $sets = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($after)));
    $conn->prepare("UPDATE sellers SET $sets WHERE id = ?")->execute([...array_map(static fn($k) => $vals[$k], array_keys($after)), $sid]);
    audit('seller.profile', 'seller', $sid, 'Updated the store profile of ' . $seller['name'] . ' (' . implode(', ', array_keys($after)) . ')', $before, $after);
    $conn->commit();
    json_response(['success' => true, 'message' => 'Store profile saved', 'changed' => array_keys($after), 'seller' => seller_portal_profile($conn, $sid)]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not save the store profile.'), 500);
}
