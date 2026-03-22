<?php
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
    exit();
}

// Remove promo code from session
unset($_SESSION['applied_promo_code']);
unset($_SESSION['applied_promo_data']);

echo json_encode(array('success' => true, 'message' => 'Promo code removed'));
?>

