<?php
// Login page
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Get username and password
$username = $_POST['username'];
$password = $_POST['password'];

// Check if empty
if (empty($username) || empty($password)) {
    echo json_encode(array('success' => false, 'message' => 'Please enter username and password'));
    exit();
}

// Check in database
try {
    $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute(array($username));
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        // Check password
        if (password_verify($password, $admin['password'])) {
            // Set session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['name'];
            
            echo json_encode(array('success' => true, 'message' => 'Login successful'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Invalid password'));
        }
    } else {
        echo json_encode(array('success' => false, 'message' => 'User not found'));
    }
} catch(PDOException $e) {
    echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
}

