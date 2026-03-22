<?php
session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please enter email and password']);
    exit();
}

try {
    // Check if customer exists - first check if password column exists
    $stmt = $conn->prepare("SELECT * FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($customer) {
        // Check if customer has password field (for new customers)
        if (isset($customer['password'])) {
            if (password_verify($password, $customer['password'])) {
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];
                echo json_encode([
                    'success' => true, 
                    'message' => 'Login successful',
                    'customer' => [
                        'id' => $customer['id'],
                        'name' => $customer['name'],
                        'email' => $customer['email']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid password']);
            }
        } else {
            // For existing customers without password, we need to add password support
            // This is a fallback - in production, all customers should have passwords
            echo json_encode(['success' => false, 'message' => 'Please register first or reset your password']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
