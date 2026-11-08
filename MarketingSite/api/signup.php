<?php
// Customer registration (AJAX, form-encoded)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();

$name = v_str($_POST['name'] ?? '', 100);
$emailRaw = v_str($_POST['email'] ?? '', 150);
$password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
$confirmPassword = is_string($_POST['confirmPassword'] ?? null) ? $_POST['confirmPassword'] : '';
$phone = v_phone($_POST['phone'] ?? '');
$city = v_str($_POST['city'] ?? '', 100);
$address = v_str($_POST['address'] ?? '', 500);

if (!$name || !$emailRaw || $password === '' || $confirmPassword === '') {
    json_error('Please fill in all required fields', 422);
}
$email = v_email($emailRaw);
if ($email === null) {
    json_error('Please enter a valid email address', 422);
}
if ($err = password_policy_error($password)) {
    json_error($err, 422);
}
if (!hash_equals($password, $confirmPassword)) {
    json_error('Passwords do not match', 422);
}
if ($phone === null) {
    json_error('Phone number must be in format 03xx-xxxxxxx', 422);
}
if ($city === null || $address === null) {
    json_error('City (max 100) or address (max 500 characters) is too long', 422);
}

try {
    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_error('Email already registered', 409);
    }

    $stmt = $conn->prepare("INSERT INTO customers (name, email, password, phone, city, address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $city, $address]);

    // No session is created; the customer logs in separately.
    json_response(['success' => true, 'message' => 'Registration successful! Please login to continue.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        json_error('Email already registered', 409);
    }
    json_error(safe_error_message($e, 'An error occurred during registration. Please try again.'), 500);
}
