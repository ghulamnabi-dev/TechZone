<?php
require_once __DIR__ . '/paths.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
