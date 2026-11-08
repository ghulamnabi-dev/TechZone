<?php
// Admin login (AJAX, form-encoded: username = email, password)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();

$email = v_str($_POST['username'] ?? '', 150) ?? '';
$password = $_POST['password'] ?? '';
$password = is_string($password) ? $password : '';

if ($email === '' || $password === '') {
    json_error('Please enter email and password', 422);
}

$locked = login_lock_remaining($conn, 'admin');
if ($locked > 0) {
    json_error(login_locked_message($locked), 429);
}

try {
    $stmt = $conn->prepare("SELECT id, username, name, password FROM admins WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!verify_password_constant($password, $admin['password'] ?? null)) {
        login_register_failure($conn, 'admin');
        json_error(LOGIN_FAILED_MESSAGE, 401);
    }

    if (password_needs_rehash($admin['password'], PASSWORD_DEFAULT)) {
        $up = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $up->execute([password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
    }

    login_clear_failures($conn, 'admin');
    session_after_login();
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['name'];

    json_response(['success' => true, 'message' => 'Login successful']);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Login failed. Please try again.'), 500);
}
