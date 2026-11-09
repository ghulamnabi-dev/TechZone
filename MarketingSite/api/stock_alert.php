<?php
/**
 * Back-in-stock alert ("Notify me", core/stock_alerts.php).
 *
 * POST product_id=…, email=…   (CSRF; throttled per session and IP)  → {success, message}
 * Signed-in customers may omit email; their account email is used.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/stock_alerts.php';
require_once __DIR__ . '/../../core/throttle.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();
$in = $_POST ?: request_json();

$productId = v_id($in['product_id'] ?? 0);
$raw = v_str($in['email'] ?? '', 150) ?? '';
if ($raw === '' && !empty($_SESSION['customer_email'])) {
    $raw = (string) $_SESSION['customer_email'];
}
$email = v_email($raw);
if ($productId === 0) {
    json_error('Invalid product', 422);
}
if ($email === null) {
    json_error('Please enter a valid email address', 422);
}
if (!throttle_allow($conn, 'stock_alert', 10, 600)) {
    json_error('You have asked for several alerts recently. Please wait a few minutes and try again.', 429);
}

try {
    $r = stock_alert_subscribe($conn, $productId, $email);
    json_response(['success' => true, 'in_stock' => $r['in_stock'],
                   'message' => $r['in_stock'] ? 'Good news: this is in stock now.' : 'We will email you when it is back in stock.']);
} catch (InvalidArgumentException $e) {
    json_error($e->getMessage(), 404);
} catch (PDOException $e) {
    log_exception($e, 'stock_alert');
    json_error('Something went wrong. Please try again.', 500);
}
