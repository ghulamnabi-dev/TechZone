<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
    exit();
}

$promo_code = isset($_POST['promo_code']) ? trim(strtoupper($_POST['promo_code'])) : '';

if (empty($promo_code)) {
    echo json_encode(array('success' => false, 'message' => 'Please enter a promo code'));
    exit();
}

try {
    // Check if promotions table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'promotions'");
    if ($tableCheck->rowCount() == 0) {
        echo json_encode(array('success' => false, 'message' => 'Promotions feature not available'));
        exit();
    }
    
    // Find active promotion with matching promo code
    $today = date('Y-m-d');
    $stmt = $conn->prepare("
        SELECT * FROM promotions 
        WHERE UPPER(promo_code) = ? 
        AND is_active = 1 
        AND (start_date IS NULL OR start_date <= ?) 
        AND (end_date IS NULL OR end_date >= ?)
        LIMIT 1
    ");
    $stmt->execute([$promo_code, $today, $today]);
    $promotion = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$promotion) {
        echo json_encode(array('success' => false, 'message' => 'Invalid or expired promo code'));
        exit();
    }
    
    // Calculate cart total to validate minimum requirements if needed
    $cart_total = 0;
    if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            if (isset($item['price']) && isset($item['quantity'])) {
                $cart_total += $item['price'] * $item['quantity'];
            }
        }
    }
    
    // Store promo code in session
    $_SESSION['applied_promo_code'] = $promo_code;
    $_SESSION['applied_promo_data'] = $promotion;
    
    // Calculate discount
    $discount = 0;
    if ($promotion['discount_percentage'] > 0) {
        $discount = ($cart_total * $promotion['discount_percentage']) / 100;
    } elseif ($promotion['discount_amount'] > 0) {
        $discount = $promotion['discount_amount'];
    }
    
    echo json_encode(array(
        'success' => true,
        'message' => 'Promo code applied successfully!',
        'discount' => $discount,
        'discount_percentage' => $promotion['discount_percentage'],
        'discount_amount' => $promotion['discount_amount'],
        'promo_title' => $promotion['title']
    ));
    
} catch (PDOException $e) {
    echo json_encode(array('success' => false, 'message' => 'Error validating promo code: ' . $e->getMessage()));
}
?>

