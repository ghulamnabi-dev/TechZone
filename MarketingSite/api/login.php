<?php
// Customer login (AJAX, form-encoded: email, password)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/wishlist.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();

$email = v_str($_POST['email'] ?? '', 150) ?? '';
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

if ($email === '' || $password === '') {
    json_error('Please enter email and password', 422);
}

$locked = login_lock_remaining($conn, 'customer');
if ($locked > 0) {
    json_error(login_locked_message($locked), 429);
}

try {
    $stmt = $conn->prepare("SELECT id, name, email, password FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    // Same message and timing for unknown email, missing password and wrong password
    if (!verify_password_constant($password, $customer['password'] ?? null)) {
        login_register_failure($conn, 'customer');
        json_error(LOGIN_FAILED_MESSAGE, 401);
    }

    if (password_needs_rehash($customer['password'], PASSWORD_DEFAULT)) {
        $conn->prepare("UPDATE customers SET password = ? WHERE id = ?")
             ->execute([password_hash($password, PASSWORD_DEFAULT), $customer['id']]);
    }

    login_clear_failures($conn, 'customer');
    session_after_login();
    $_SESSION['customer_id'] = (int) $customer['id'];
    $_SESSION['customer_name'] = $customer['name'];
    $_SESSION['customer_email'] = $customer['email'];
    wishlist_merge_session($conn, (int) $customer['id']);   // guest wishlist -> account

    json_response([
        'success' => true,
        'message' => 'Login successful',
        'customer' => [
            'id' => (int) $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
        ],
    ]);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Login failed. Please try again.'), 500);
}
