<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
$adminId = (int) $_SESSION['admin_id'];

try {
    // GET - profile (no password hash)
    if ($method === 'GET') {
        $stmt = $conn->prepare("SELECT id, username, email, name, phone, role, last_login_at, created_at FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        json_response(['success' => true, 'admin' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    // PUT - update profile (JSON body: name, email, phone)
    if ($method === 'PUT') {
        enforce_csrf_json();
        $data = request_json();

        $name = v_str($data['name'] ?? '', 100);
        $emailRaw = v_str($data['email'] ?? '', 150);
        $phone = v_phone($data['phone'] ?? '');

        if ($name === null || $name === '') {
            json_error('Name is required (max 100 characters)', 422);
        }
        if ($emailRaw === null || $emailRaw === '') {
            json_error('Email is required', 422);
        }
        $email = v_email($emailRaw);
        if ($email === null) {
            json_error('Please enter a valid email address', 422);
        }
        if ($phone === null) {
            json_error('Phone number must be in format 03xx-xxxxxxx', 422);
        }

        $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
        $stmt->execute([$email, $adminId]);
        if ($stmt->fetch()) {
            json_error('Email already exists', 409);
        }

        $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $adminId]);
        $_SESSION['admin_name'] = $name;

        json_response(['success' => true, 'message' => 'Profile updated']);
    }

    // POST - change password (JSON body)
    if ($method === 'POST') {
        enforce_csrf_json();
        $data = request_json();

        $currentPassword = is_string($data['current_password'] ?? null) ? $data['current_password'] : '';
        $newPassword = is_string($data['new_password'] ?? null) ? $data['new_password'] : '';
        $confirmPassword = is_string($data['confirm_password'] ?? null) ? $data['confirm_password'] : '';

        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$admin || !password_verify($currentPassword, $admin['password'])) {
            json_error('Current password is incorrect', 422);
        }
        if (!hash_equals($newPassword, $confirmPassword)) {
            json_error('New passwords do not match', 422);
        }
        if ($err = password_policy_error($newPassword)) {
            json_error($err, 422);
        }

        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);

        // Invalidate outstanding reset links and rotate the session id
        $conn->prepare("DELETE FROM admin_password_resets WHERE admin_id = ?")->execute([$adminId]);
        session_regenerate_id(true);

        json_response(['success' => true, 'message' => 'Password changed successfully']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not update the profile.'), 500);
}
