<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
$customer_id = requireCustomerJson();
enforce_csrf_json();

$product_id = v_id($_POST['product_id'] ?? 0);
$rating = v_int($_POST['rating'] ?? 0, 1, 5);
$comment = v_str($_POST['comment'] ?? '', 2000);

if ($product_id === 0) {
    json_error('Invalid product', 422);
}
if ($rating === null) {
    json_error('Rating must be between 1 and 5', 422);
}
if ($comment === null) {
    json_error('Review is too long (max 2000 characters)', 422);
}

try {
    $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    if (!$stmt->fetch()) {
        json_error('Invalid product', 404);
    }

    $customer_name = (string) ($_SESSION['customer_name'] ?? '');
    if ($customer_name === '') {
        $stmt = $conn->prepare("SELECT name FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer_name = (string) ($stmt->fetchColumn() ?: '');
        if ($customer_name === '') {
            json_error('Customer not found', 404);
        }
    }

    $stmt = $conn->prepare("SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?");
    $stmt->execute([$product_id, $customer_id]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        // Edited reviews go back to moderation
        $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, status = 'Pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$rating, $comment, $existingId]);
        json_response(['success' => true, 'message' => 'Review updated successfully', 'action' => 'updated']);
    }

    $stmt = $conn->prepare("INSERT INTO reviews (product_id, customer_id, customer_name, rating, comment, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
    $stmt->execute([$product_id, $customer_id, $customer_name, $rating, $comment]);
    json_response(['success' => true, 'message' => 'Review submitted successfully', 'action' => 'created']);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Error submitting review. Please try again.'), 500);
}
