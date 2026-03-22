<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/customer_auth.php';

header('Content-Type: application/json');

checkCustomerLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
$customer_id = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'] ?? '';

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit();
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit();
}

if (empty($customer_name)) {
    // Get customer name from database
    $stmt = $conn->prepare("SELECT name FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($customer) {
        $customer_name = $customer['name'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Customer not found']);
        exit();
    }
}

try {
    // Check if customer already reviewed this product
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?");
    $stmt->execute([$product_id, $customer_id]);
    $existingReview = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingReview) {
        // Update existing review
        $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, status = 'Pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$rating, $comment, $existingReview['id']]);
        echo json_encode(['success' => true, 'message' => 'Review updated successfully', 'action' => 'updated']);
    } else {
        // Insert new review
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, customer_id, customer_name, rating, comment, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
        $stmt->execute([$product_id, $customer_id, $customer_name, $rating, $comment]);
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully', 'action' => 'created']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error submitting review: ' . $e->getMessage()]);
}
?>

