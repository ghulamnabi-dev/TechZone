<?php
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../../core/permissions.php';

start_secure_session();

/**
 * Require a signed-in, active admin. Pages redirect to the login screen; APIs get 401 JSON.
 * An account that was deactivated (admins.is_active = 0) or deleted is signed out here.
 */
function checkLogin($returnJson = false) {
    $signedIn = isset($_SESSION['admin_id']);
    if ($signedIn && isset($GLOBALS['conn']) && current_admin() === null) {
        session_logout('admin');
        $signedIn = false;
    }
    if (!$signedIn) {
        if ($returnJson) {
            json_response(['success' => false, 'message' => 'Not authenticated'], 401);
        } else {
            header('Location: ' . admin_url('index.php'));
            exit();
        }
    }
    // Seller accounts: deny-by-default allowlist of scripts (core/permissions.php TZ_SELLER_ALLOWED)
    seller_gate();
}

// Get admin name
function getAdminName() {
    $admin = current_admin();
    if ($admin !== null && $admin['name'] !== '') {
        return $admin['name'];
    }
    return $_SESSION['admin_name'] ?? 'Admin';
}
