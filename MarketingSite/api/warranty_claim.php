<?php
/**
 * Warranty claim (help/warranty.php). Stored in contact_messages (no new table) with the subject
 * "Warranty claim: order #1042, <part>" so it shows up in the admin inbox.
 *
 * POST name, email, phone (03xx-xxxxxxx), order_id, product, fault   (CSRF; throttled)
 *   → 200 {success, message, reference}   reference = "WC-000123" (the contact_messages id)
 *   → 422 {success:false, message, errors:{field: message}}
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

$errors = [];
$name = v_str($in['name'] ?? '', 100);
$email = v_email(v_str($in['email'] ?? '', 150) ?? '');
$phoneRaw = trim((string) (is_scalar($in['phone'] ?? null) ? $in['phone'] : ''));
if (preg_match('/^03\d{9}$/', $phoneRaw)) {
    $phoneRaw = substr($phoneRaw, 0, 4) . '-' . substr($phoneRaw, 4);
}
$phone = v_phone($phoneRaw);
$orderId = v_int(ltrim(trim((string) (is_scalar($in['order_id'] ?? null) ? $in['order_id'] : '')), '#'), 1, 99999999);
$product = v_str($in['product'] ?? '', 150);
$fault = v_str($in['fault'] ?? '', 3000);

if ($name === null || mb_strlen(trim($name)) < 2) {
    $errors['name'] = 'Enter your name.';
}
if ($email === null) {
    $errors['email'] = 'Enter a valid email address.';
}
if ($phone === null || $phone === '') {
    $errors['phone'] = 'Enter a mobile number like 0300-1234567.';
}
if ($orderId === null) {
    $errors['order_id'] = 'Enter the order number, digits only.';
}
if ($product === null || mb_strlen(trim($product)) < 2) {
    $errors['product'] = 'Tell us which part the claim is for.';
}
if ($fault === null || mb_strlen(trim($fault)) < 15) {
    $errors['fault'] = 'Describe the fault in a sentence or two (at least 15 characters).';
}
if ($errors) {
    validation_problem($errors);
}

try {
    // the order must exist and belong to this email (or to the signed-in customer)
    $st = $conn->prepare('SELECT o.id, o.customer_id, o.customer_email, c.email AS account_email FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = ?');
    $st->execute([$orderId]);
    $order = $st->fetch(PDO::FETCH_ASSOC);
    $mine = $order && (
        (isset($_SESSION['customer_id']) && (int) $order['customer_id'] === (int) $_SESSION['customer_id'])
        || strcasecmp((string) $order['customer_email'], $email) === 0
        || strcasecmp((string) $order['account_email'], $email) === 0
    );
    if (!$mine) {
        validation_problem(['order_id' => 'We could not find that order for this email. Use the email you ordered with.']);
    }
    if (!throttle_allow($conn, 'warranty_claim', 5, 3600)) {
        json_error('You have sent several claims recently. Please wait an hour, or call us.', 429);
    }
    $subject = mb_substr('Warranty claim: order #' . $orderId . ', ' . trim($product), 0, 200);
    $message = "Order: #{$orderId}\nPart: " . trim($product) . "\nPhone: {$phone}\n\n" . trim($fault);
    $conn->prepare('INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)')
         ->execute([trim($name), $email, $subject, $message]);
    $id = (int) $conn->lastInsertId();
    json_response(['success' => true, 'reference' => sprintf('WC-%06d', $id),
                   'message' => 'Claim received. We will reply within a working day.']);
} catch (PDOException $e) {
    log_exception($e, 'warranty_claim');
    json_error('We could not save your claim. Please try again, or call us.', 500);
}
