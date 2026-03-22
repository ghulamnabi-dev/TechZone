<?php
// Start output buffering to prevent any accidental output
ob_start();

session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    ob_end_flush();
    exit();
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirmPassword'] ?? '';
$phone = $_POST['phone'] ?? '';
$city = $_POST['city'] ?? '';
$address = $_POST['address'] ?? '';

if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
    ob_end_flush();
    exit();
}

if (strlen($password) < 6) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    ob_end_flush();
    exit();
}

if ($password !== $confirmPassword) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    ob_end_flush();
    exit();
}

// Validate phone format (if provided) - Pakistani format: 03xx-xxxxxxx
if (!empty($phone)) {
    if (!preg_match('/^03\d{2}-\d{7}$/', $phone)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Phone number must be in format 03xx-xxxxxxx']);
        ob_end_flush();
        exit();
    }
}

try {
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        ob_end_flush();
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if password column exists in customers table
    $checkPasswordCol = $conn->query("SHOW COLUMNS FROM customers LIKE 'password'");
    $hasPasswordColumn = ($checkPasswordCol->rowCount() > 0);
    
    // Insert customer - handle different column scenarios
    try {
        if ($hasPasswordColumn) {
            // Password column exists
            $stmt = $conn->prepare("INSERT INTO customers (name, email, password, phone, city, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $phone, $city, $address]);
        } else {
            // Password column doesn't exist - add it
            $conn->exec("ALTER TABLE customers ADD COLUMN password VARCHAR(255) AFTER email");
            $stmt = $conn->prepare("INSERT INTO customers (name, email, password, phone, city, address) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $hashedPassword, $phone, $city, $address]);
        }
    } catch(PDOException $insertException) {
        // Handle duplicate email error specifically
        $errorMsg = $insertException->getMessage();
        $errorCode = $insertException->getCode();
        
        // Check for duplicate entry error (code 23000)
        if ($errorCode == 23000 || strpos($errorMsg, 'Duplicate entry') !== false) {
            if (stripos($errorMsg, 'email') !== false || stripos($errorMsg, "'email'") !== false) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Email already registered']);
                ob_end_flush();
                exit();
            }
        }
        throw $insertException;
    }
    
    // Don't set session - user needs to login separately
    // Registration successful, redirect to login page
    
    ob_clean();
    echo json_encode([
        'success' => true, 
        'message' => 'Registration successful! Please login to continue.'
    ]);
    ob_end_flush();
} catch(PDOException $e) {
    // Provide user-friendly error messages
    $errorMessage = 'An error occurred during registration. Please try again.';
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    
    // Check for duplicate entry error (code 23000)
    if ($errorCode == 23000 || strpos($errorMsg, 'Duplicate entry') !== false || strpos($errorMsg, '1062') !== false) {
        if (stripos($errorMsg, 'email') !== false || stripos($errorMsg, "'email'") !== false || stripos($errorMsg, 'key \'email\'') !== false) {
            $errorMessage = 'Email already registered';
        } else {
            // Generic duplicate entry message
            $errorMessage = 'This information is already registered. Please use different details.';
        }
    }
    
    // Clean any output before sending JSON
    ob_clean();
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    ob_end_flush();
    exit();
}
?>
