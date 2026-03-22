<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Check if customer is logged in
if (isset($_SESSION['customer_id'])) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'customer' => [
            'id' => $_SESSION['customer_id'],
            'name' => $_SESSION['customer_name'] ?? 'Customer',
            'email' => $_SESSION['customer_email'] ?? ''
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'logged_in' => false
    ]);
}
?>

