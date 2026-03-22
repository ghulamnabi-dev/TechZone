<?php
require_once __DIR__ . '/paths.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
function checkLogin($returnJson = false) {
    if (!isset($_SESSION['admin_id'])) {
        if ($returnJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit();
        } else {
            header('Location: ' . admin_url('index.php'));
            exit();
        }
    }
}

// Get admin name
function getAdminName() {
    if (isset($_SESSION['admin_name'])) {
        return $_SESSION['admin_name'];
    }
    return "Admin";
}

