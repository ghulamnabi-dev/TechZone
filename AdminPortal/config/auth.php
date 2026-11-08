<?php
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/../../core/bootstrap.php';

start_secure_session();

// Check if admin is logged in
function checkLogin($returnJson = false) {
    if (!isset($_SESSION['admin_id'])) {
        if ($returnJson) {
            json_response(['success' => false, 'message' => 'Not authenticated'], 401);
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
