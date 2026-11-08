<?php
require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/../../core/bootstrap.php';

start_secure_session();

// Check if customer is logged in
function checkCustomerLogin($redirect = true) {
    if (!isset($_SESSION['customer_id'])) {
        if ($redirect) {
            header('Location: ' . site_url('auth/login.php'));
            exit();
        }
        return false;
    }
    return true;
}

// JSON APIs: respond 401 instead of redirecting to the login page
function requireCustomerJson(): int {
    if (!isset($_SESSION['customer_id'])) {
        json_response(['success' => false, 'message' => 'Please log in to continue.'], 401);
    }
    return (int) $_SESSION['customer_id'];
}

// Get customer name
function getCustomerName() {
    if (isset($_SESSION['customer_name'])) {
        return $_SESSION['customer_name'];
    }
    return "Guest";
}

// Get customer ID
function getCustomerId() {
    if (isset($_SESSION['customer_id'])) {
        return $_SESSION['customer_id'];
    }
    return null;
}
