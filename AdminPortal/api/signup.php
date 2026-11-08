<?php
// Admin self-registration.
// Disabled unless ADMIN_SIGNUP_CODE is set in the environment; when set, the
// request must carry a matching invite_code. Anyone could otherwise create an admin.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}

$inviteCode = env('ADMIN_SIGNUP_CODE');
if ($inviteCode === null) {
    json_error('Admin sign-up is disabled. Ask an existing administrator for access.', 403);
}

enforce_csrf_json();

$data = request_json() ?: $_POST;

$sentCode = is_string($data['invite_code'] ?? null) ? trim($data['invite_code']) : '';
// Check the lock before comparing, so a locked client cannot keep guessing codes.
$locked = login_lock_remaining($conn, 'admin_signup');
if ($locked > 0) {
    json_error(login_locked_message($locked), 429);
}
if ($sentCode === '' || !hash_equals($inviteCode, $sentCode)) {
    // Count bad invite codes like failed logins so the code cannot be brute-forced.
    login_register_failure($conn, 'admin_signup');
    json_error('A valid invite code is required to create an admin account.', 403);
}

$name     = v_str($data['name'] ?? '', 100);
$username = v_str($data['username'] ?? '', 50);
$email    = v_email($data['email'] ?? '');
$phone    = v_phone($data['phone'] ?? '');
$password = is_string($data['password'] ?? null) ? $data['password'] : '';

if (!$name || !$username || !($data['email'] ?? '') || $password === '') {
    json_error('All required fields must be filled', 422);
}
if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    json_error('Username must be 3-50 characters: letters, numbers and underscores only', 422);
}
if ($email === null) {
    json_error('Invalid email format', 422);
}
if ($phone === null) {
    json_error('Phone number must be in format 03xx-xxxxxxx', 422);
}
if ($err = password_policy_error($password)) {
    json_error($err, 422);
}

try {
    $stmt = $conn->prepare("SELECT username, email FROM admins WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $email]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        json_error(strcasecmp($row['username'], $username) === 0
            ? 'Username already taken. Please choose a different username.'
            : 'Email already registered', 409);
    }

    $stmt = $conn->prepare("INSERT INTO admins (name, username, email, phone, password) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);

    login_clear_failures($conn, 'admin_signup');
    json_response(['success' => true, 'message' => 'Account created successfully']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('This username or email is already registered.', 409);
    }
    json_error(safe_error_message($e, 'An error occurred during registration. Please try again.'), 500);
}
