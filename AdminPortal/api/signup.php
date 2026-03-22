<?php
// Start output buffering to prevent any accidental output
ob_start();

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Get data
$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name']);
$username = trim($data['username']);
$email = trim($data['email']);
$phone = trim($data['phone']);
$password = $data['password'];

// Validation
if (empty($name) || empty($username) || empty($email) || empty($password)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    ob_end_flush();
    exit();
}

// Validate username format
if (strlen($username) < 3) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Username must be at least 3 characters long']);
    ob_end_flush();
    exit();
}

if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores']);
    ob_end_flush();
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    ob_end_flush();
    exit();
}

// Validate phone format (if provided)
if (!empty($phone)) {
    // Pakistani phone format: 03xx-xxxxxxx
    if (!preg_match('/^03\d{2}-\d{7}$/', $phone)) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Phone number must be in format 03xx-xxxxxxx']);
        ob_end_flush();
        exit();
    }
}

if (strlen($password) < 6) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    ob_end_flush();
    exit();
}

try {
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Username already taken. Please choose a different username.']);
        ob_end_flush();
        exit();
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        ob_end_flush();
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new admin
    try {
        $stmt = $conn->prepare("INSERT INTO admins (name, username, email, phone, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $username, $email, $phone, $hashedPassword]);
    } catch(PDOException $insertException) {
        // Handle duplicate username error specifically
        $errorMsg = $insertException->getMessage();
        $errorCode = $insertException->getCode();
        
        // Check for duplicate entry error (code 23000)
        if ($errorCode == 23000 || strpos($errorMsg, 'Duplicate entry') !== false) {
            if (stripos($errorMsg, 'username') !== false || stripos($errorMsg, "'username'") !== false) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Username already taken. Please choose a different username.']);
                ob_end_flush();
                exit();
            }
            if (stripos($errorMsg, 'email') !== false || stripos($errorMsg, "'email'") !== false) {
                ob_clean();
                echo json_encode(['success' => false, 'message' => 'Email already registered']);
                ob_end_flush();
                exit();
            }
        }
        throw $insertException;
    }
    
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Account created successfully']);
    ob_end_flush();
    
} catch(PDOException $e) {
    // Provide user-friendly error messages
    $errorMessage = 'An error occurred during registration. Please try again.';
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    
    // Check for duplicate entry error (code 23000)
    if ($errorCode == 23000 || strpos($errorMsg, 'Duplicate entry') !== false || strpos($errorMsg, '1062') !== false) {
        if (stripos($errorMsg, 'username') !== false || stripos($errorMsg, "'username'") !== false || stripos($errorMsg, 'key \'username\'') !== false) {
            $errorMessage = 'Username already taken. Please choose a different username.';
        } else if (stripos($errorMsg, 'email') !== false || stripos($errorMsg, "'email'") !== false || stripos($errorMsg, 'key \'email\'') !== false) {
            $errorMessage = 'Email already registered';
        } else {
            // Generic duplicate entry message
            $errorMessage = 'This information is already registered. Please use different details.';
        }
    }
    
    ob_clean();
    echo json_encode(['success' => false, 'message' => $errorMessage]);
    ob_end_flush();
    exit();
}
?>