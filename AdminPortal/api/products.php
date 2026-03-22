<?php
// Suppress any output before JSON
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../../core/security.php';

/** For JSON: browser-ready path; DB still stores uploads/filename */
function product_imagelink_for_client(?string $link): string
{
    if ($link === null || $link === '') {
        return '';
    }
    $t = trim($link);
    if (preg_match('#^https?://#i', $t)) {
        return $t;
    }
    $rel = ltrim(str_replace('\\', '/', $t), '/');
    if (strpos($rel, 'uploads/') === 0) {
        return admin_url($rel);
    }
    return $t;
}

checkLogin(true);

// Clear any output
ob_clean();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// CSRF token can come from POST body or X-CSRF-Token header (for multipart/DELETE)
function get_csrf_from_request() {
    $fromPost = isset($_POST['csrf_token']) ? trim((string) $_POST['csrf_token']) : '';
    if ($fromPost !== '') return $fromPost;
    $fromHeader = isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? trim((string) $_SERVER['HTTP_X_CSRF_TOKEN']) : '';
    return $fromHeader;
}

// GET - Get all products or one product
if ($method == 'GET') {
    if (isset($_GET['id'])) {
        // Get one product
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
        $stmt->execute(array($id));
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['imagelink'] = product_imagelink_for_client($product['imagelink'] ?? '');
            echo json_encode(array('success' => true, 'product' => $product));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Product not found'));
        }
    } else {
        // Get all products
        $stmt = $conn->query("SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$row) {
            $row['imagelink'] = product_imagelink_for_client($row['imagelink'] ?? '');
        }
        unset($row);
        echo json_encode(array('success' => true, 'products' => $products));
    }
}

// PUT - Update product (handled via POST with _method=PUT for file uploads)
// Check for update FIRST before processing as new product
if ($method == 'PUT' || ($method == 'POST' && ((isset($_POST['_method']) && $_POST['_method'] == 'PUT') || (isset($_POST['id']) && !empty($_POST['id']))))) {
    $sentToken = get_csrf_from_request();
    $storedToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if ($sentToken === '') {
        echo json_encode(array('success' => false, 'message' => 'Invalid request token (send csrf_token in form or X-CSRF-Token header)'));
        ob_end_flush();
        exit();
    }
    if ($storedToken === '' || !hash_equals($storedToken, $sentToken)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid request token (token mismatch or session expired)'));
        ob_end_flush();
        exit();
    }
    // Ensure id is set for update
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode(array('success' => false, 'message' => 'Product ID is required for update'));
        ob_end_flush();
        exit();
    }
    
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $inventory = $_POST['inventory'];
    $sold = $_POST['sold'];
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    // Get existing image first (before we potentially overwrite it)
    $stmt = $conn->prepare("SELECT imagelink FROM products WHERE id = ?");
    $stmt->execute(array($id));
    $existingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    $oldImagelink = $existingProduct ? $existingProduct['imagelink'] : '';
    
    $imagelink = $oldImagelink; // Default to existing image
    
    // Check if image file was uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            $imagelink = 'uploads/' . $fileName;
            // Delete old image file if it exists
            if (!empty($oldImagelink) && $oldImagelink != $imagelink && file_exists(__DIR__ . '/../' . $oldImagelink)) {
                @unlink(__DIR__ . '/../' . $oldImagelink);
            }
        }
    }
    
    try {
        // Check if is_active column exists, if not, update without it
        $checkCol = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
        if ($checkCol->rowCount() == 0) {
            // Column doesn't exist, update without is_active
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, imagelink = ?, inventory = ?, sold = ? WHERE id = ?");
            $stmt->execute(array($name, $description, $price, $category_id, $imagelink, $inventory, $sold, $id));
            echo json_encode(array('success' => true, 'message' => 'Product updated successfully. Note: Please run add_is_active_to_products.php to add the is_active column.'));
        } else {
            // Column exists, update with is_active
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, imagelink = ?, inventory = ?, sold = ?, is_active = ? WHERE id = ?");
            $stmt->execute(array($name, $description, $price, $category_id, $imagelink, $inventory, $sold, $is_active, $id));
            echo json_encode(array('success' => true, 'message' => 'Product updated successfully'));
        }
    } catch(PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
    ob_end_flush();
    exit();
}

// POST - Add new product (only if not an update)
else if ($method == 'POST') {
    $sentToken = get_csrf_from_request();
    $storedToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if ($sentToken === '') {
        echo json_encode(array('success' => false, 'message' => 'Invalid request token (send csrf_token in form or X-CSRF-Token header)'));
        ob_end_flush();
        exit();
    }
    if ($storedToken === '' || !hash_equals($storedToken, $sentToken)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid request token (token mismatch or session expired)'));
        ob_end_flush();
        exit();
    }
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $inventory = $_POST['inventory'];
    $sold = $_POST['sold'];
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    $imagelink = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Sanitize filename
        $originalName = basename($_FILES['image']['name']);
        $fileName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $uploadFile = $uploadDir . $fileName;
        
        // Validate file is actually an image
        $imageFileType = strtolower(pathinfo($uploadFile, PATHINFO_EXTENSION));
        $allowedTypes = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        
        if (in_array($imageFileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
                // Verify file was actually moved
                if (file_exists($uploadFile)) {
                    $imagelink = 'uploads/' . $fileName;
                }
            }
        }
    }
    
    try {
        // Check if is_active column exists
        $checkCol = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
        if ($checkCol->rowCount() == 0) {
            // Column doesn't exist, insert without is_active
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, imagelink, inventory, sold) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($name, $description, $price, $category_id, $imagelink, $inventory, $sold));
            $product_id = $conn->lastInsertId();
            echo json_encode(array('success' => true, 'message' => 'Product added successfully. Note: Please run add_is_active_to_products.php to add the is_active column.', 'product_id' => $product_id, 'id' => $product_id));
        } else {
            // Column exists, insert with is_active
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, imagelink, inventory, sold, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($name, $description, $price, $category_id, $imagelink, $inventory, $sold, $is_active));
            $product_id = $conn->lastInsertId();
            echo json_encode(array('success' => true, 'message' => 'Product added successfully', 'product_id' => $product_id, 'id' => $product_id));
        }
        
        // Note: Default variation creation is handled by JavaScript after product creation
        // This ensures that if variations are provided in the form, they are used instead
    } catch(PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
    ob_end_flush();
    exit();
}

// DELETE - Delete product
else if ($method == 'DELETE') {
    $sentToken = get_csrf_from_request();
    $storedToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
    if ($sentToken === '') {
        echo json_encode(array('success' => false, 'message' => 'Invalid request token (send X-CSRF-Token header or csrf_token in body)'));
        ob_end_flush();
        exit();
    }
    if ($storedToken === '' || !hash_equals($storedToken, $sentToken)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid request token (token mismatch or session expired)'));
        ob_end_flush();
        exit();
    }
    try {
        if (!isset($_GET['id']) || empty($_GET['id'])) {
            echo json_encode(array('success' => false, 'message' => 'Product ID is required'));
            ob_end_flush();
            exit();
        }
        
        $id = intval($_GET['id']);
        
        // Check if product exists
        $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute(array($id));
        if ($stmt->rowCount() == 0) {
            echo json_encode(array('success' => false, 'message' => 'Product not found'));
            ob_end_flush();
            exit();
        }
        
        // Check if product has orders (order_items references products with ON DELETE RESTRICT)
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM order_items WHERE product_id = ?");
        $stmt->execute(array($id));
        $orderCheck = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($orderCheck['count'] > 0) {
            echo json_encode(array('success' => false, 'message' => 'Cannot delete product. It has been ordered by customers. Please remove all orders containing this product first.'));
            ob_end_flush();
            exit();
        }
        
        // Get image path and delete file from uploads to save space (before deleting DB row)
        $stmt = $conn->prepare("SELECT imagelink FROM products WHERE id = ?");
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['imagelink']) && strpos($row['imagelink'], 'uploads/') === 0) {
            $filePath = __DIR__ . '/../' . $row['imagelink'];
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        
        // Delete the product (product_variations and reviews will be deleted automatically due to CASCADE)
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute(array($id));
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(array('success' => true, 'message' => 'Product deleted successfully'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Failed to delete product'));
        }
    } catch (PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
}

// PATCH - Toggle product status (quick toggle)
else if ($method == 'PATCH') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? 0;
        $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;
        
        if ($id > 0 && $is_active !== null) {
            // Check if is_active column exists
            $checkCol = $conn->query("SHOW COLUMNS FROM products LIKE 'is_active'");
            if ($checkCol->rowCount() == 0) {
                echo json_encode(array('success' => false, 'message' => 'is_active column does not exist. Please run add_is_active_to_products.php first.'));
            } else {
                $stmt = $conn->prepare("UPDATE products SET is_active = ? WHERE id = ?");
                $stmt->execute(array($is_active, $id));
                echo json_encode(array('success' => true, 'message' => 'Product status updated successfully'));
            }
        } else {
            echo json_encode(array('success' => false, 'message' => 'Invalid request'));
        }
    } catch(PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
    ob_end_flush();
    exit();
}

ob_end_flush();
exit();
?>
