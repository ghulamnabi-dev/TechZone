<?php
/**
 * Sell on TechZone application (sell/apply.php).
 *
 * POST shop_name, owner_name, phone (03xx-xxxxxxx), email, market (Hafeez Centre|Hall Road|Saddar|Other),
 *      city, categories[] (optional), ntn (optional), message (optional)
 *      (CSRF; throttled per session and IP)
 *   → 200 {success, message, reference}   reference = "TZS-000123"
 *   → 422 {success:false, message, errors:{field: message}}
 * Stored in seller_applications with status 'pending'; reviewed in the admin portal.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/throttle.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();
$in = $_POST ?: request_json();

const SELLER_MARKETS = ['Hafeez Centre', 'Hall Road', 'Saddar', 'Other'];
const SELLER_CATEGORIES = ['Processors', 'Motherboards', 'Memory', 'Graphics cards', 'Storage', 'Power supplies', 'Cases and cooling',
                           'Monitors', 'Peripherals', 'Networking', 'Laptops', 'Furniture'];

$errors = [];
$shop = v_str($in['shop_name'] ?? '', 120);
$owner = v_str($in['owner_name'] ?? '', 100);
$phoneRaw = trim((string) (is_scalar($in['phone'] ?? null) ? $in['phone'] : ''));
// accept 03001234567 as well as 0300-1234567
if (preg_match('/^03\d{9}$/', $phoneRaw)) {
    $phoneRaw = substr($phoneRaw, 0, 4) . '-' . substr($phoneRaw, 4);
}
$phone = v_phone($phoneRaw);
$email = v_email(v_str($in['email'] ?? '', 150) ?? '');
$market = v_enum($in['market'] ?? '', SELLER_MARKETS);
$city = v_str($in['city'] ?? '', 80);
$ntn = v_str($in['ntn'] ?? '', 20);
$message = v_str($in['message'] ?? '', 2000);
$cats = $in['categories'] ?? [];
$cats = is_array($cats) ? array_values(array_intersect(SELLER_CATEGORIES, array_map('strval', $cats))) : [];

if ($shop === null || mb_strlen(trim($shop)) < 2) {
    $errors['shop_name'] = 'Enter your shop\'s name.';
}
if ($owner === null || mb_strlen(trim($owner)) < 2) {
    $errors['owner_name'] = 'Enter the owner\'s name.';
}
if ($phone === null || $phone === '') {
    $errors['phone'] = 'Enter a mobile number like 0300-1234567.';
}
if ($email === null) {
    $errors['email'] = 'Enter a valid email address.';
}
if ($market === null) {
    $errors['market'] = 'Choose the market your shop is in.';
}
if ($city === null || trim($city) === '') {
    $errors['city'] = 'Enter your city.';
}
if ($ntn === null || ($ntn !== '' && !preg_match('/^[0-9A-Za-z-]{5,20}$/', $ntn))) {
    $errors['ntn'] = 'An NTN is 7 digits and a check digit, like 1234567-8. Leave it empty if you do not have one.';
}
if ($message === null) {
    $errors['message'] = 'Keep the message under 2,000 characters.';
}
if ($errors) {
    validation_problem($errors);
}
if (!throttle_allow($conn, 'seller_apply', 3, 3600)) {
    json_error('You have sent several applications recently. Please wait an hour, or call us if it is urgent.', 429);
}

try {
    $dup = $conn->prepare("SELECT id FROM seller_applications WHERE status = 'pending' AND (email = ? OR phone = ?) LIMIT 1");
    $dup->execute([$email, $phone]);
    if ($id = $dup->fetchColumn()) {
        json_response(['success' => true, 'reference' => sprintf('TZS-%06d', $id),
                       'message' => 'We already have your application and will call you within two working days.']);
    }
    $st = $conn->prepare('INSERT INTO seller_applications (shop_name, owner_name, phone, email, market, city, categories, ntn, message, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');
    $st->execute([trim($shop), trim($owner), $phone, $email, $market, trim($city), $cats ? implode(', ', $cats) : null,
                  $ntn !== '' ? $ntn : null, $message !== '' ? $message : null]);
    $id = (int) $conn->lastInsertId();
    json_response(['success' => true, 'reference' => sprintf('TZS-%06d', $id),
                   'message' => 'Application received. We will call you within two working days.']);
} catch (PDOException $e) {
    log_exception($e, 'seller_apply');
    json_error('We could not save your application. Please try again, or call us.', 500);
}
