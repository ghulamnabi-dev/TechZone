<?php
require_once '../config/database.php';
require_once '../config/auth.php';

checkLogin(true);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$adminId = $_SESSION['admin_id'];

// GET - Get profile
if ($method == 'GET') {
    $stmt = $conn->prepare("SELECT id, username, email, name, phone, created_at FROM admins WHERE id = ?");
    $stmt->execute(array($adminId));
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(array('success' => true, 'admin' => $admin));
}

// PUT - Update profile
else if ($method == 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($data['name']);
    $email = trim($data['email']);
    $phone = trim($data['phone']);
    
    // Validate required fields
    if (empty($name)) {
        echo json_encode(array('success' => false, 'message' => 'Name is required'));
        exit();
    }
    
    if (empty($email)) {
        echo json_encode(array('success' => false, 'message' => 'Email is required'));
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array('success' => false, 'message' => 'Please enter a valid email address'));
        exit();
    }
    
    // Validate phone format (if provided)
    if (!empty($phone)) {
        // Pakistani phone format: 03xx-xxxxxxx
        if (!preg_match('/^03\d{2}-\d{7}$/', $phone)) {
            echo json_encode(array('success' => false, 'message' => 'Phone number must be in format 03xx-xxxxxxx'));
            exit();
        }
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
    $stmt->execute(array($email, $adminId));
    if ($stmt->fetch()) {
        echo json_encode(array('success' => false, 'message' => 'Email already exists'));
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE admins SET name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->execute(array($name, $email, $phone, $adminId));
    
    $_SESSION['admin_name'] = $name;
    
    echo json_encode(array('success' => true, 'message' => 'Profile updated'));
}

// POST - Change password
else if ($method == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $currentPassword = $data['current_password'];
    $newPassword = $data['new_password'];
    $confirmPassword = $data['confirm_password'];
    
    // Get current password
    $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
    $stmt->execute(array($adminId));
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verify current password
    if (!password_verify($currentPassword, $admin['password'])) {
        echo json_encode(array('success' => false, 'message' => 'Current password is incorrect'));
        exit();
    }
    
    // Check if new passwords match
    if ($newPassword != $confirmPassword) {
        echo json_encode(array('success' => false, 'message' => 'New passwords do not match'));
        exit();
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $stmt->execute(array($hashedPassword, $adminId));
    
    echo json_encode(array('success' => true, 'message' => 'Password changed successfully'));
}
?>
