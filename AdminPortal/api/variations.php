<?php
// Suppress any output before JSON
ob_start();
error_reporting(0); // Suppress PHP errors from being output
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);

// Clear any output
ob_clean();
header('Content-Type: application/json');

// Auto-create table if it doesn't exist and add is_default column
try {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'product_variations'");
    if ($tableCheck->rowCount() == 0) {
        $conn->exec("CREATE TABLE product_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            variation_type ENUM('size', 'color') NOT NULL,
            variation_value VARCHAR(100) NOT NULL,
            price_modifier DECIMAL(10, 2) DEFAULT 0,
            inventory INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            is_default TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_variation (product_id, variation_type, variation_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } else {
        // Check if is_default column exists, if not add it
        try {
            $colCheck = $conn->query("SHOW COLUMNS FROM product_variations LIKE 'is_default'");
            if ($colCheck->rowCount() == 0) {
                $conn->exec("ALTER TABLE product_variations ADD COLUMN is_default TINYINT(1) DEFAULT 0 AFTER is_active");
            }
        } catch (PDOException $e) {
            // Column might already exist
        }
    }
} catch (PDOException $e) {
    // Table might already exist
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get variations for a product
if ($method == 'GET') {
    try {
        if (isset($_GET['product_id'])) {
            $product_id = (int)$_GET['product_id'];
            
            // Check if table exists
            try {
                $stmt = $conn->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY variation_type, display_order");
                $stmt->execute([$product_id]);
                $variations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(array('success' => true, 'variations' => $variations));
            } catch (PDOException $e) {
                // Table doesn't exist, return empty array
                echo json_encode(array('success' => true, 'variations' => []));
            }
        } else {
            echo json_encode(array('success' => false, 'message' => 'Product ID required'));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
    }
}

// POST - Add new variation (only if not an update)
else if ($method == 'POST' && (!isset($_POST['_method']) || $_POST['_method'] != 'PUT')) {
    $product_id = (int)$_POST['product_id'];
    $variation_type = $_POST['variation_type'];
    $variation_value = trim($_POST['variation_value']);
    $price_modifier = isset($_POST['price_modifier']) ? (float)$_POST['price_modifier'] : 0;
    $inventory = isset($_POST['inventory']) ? (int)$_POST['inventory'] : 0;
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $is_default = isset($_POST['is_default']) ? (int)$_POST['is_default'] : 0;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    
    if (empty($variation_value)) {
        echo json_encode(array('success' => false, 'message' => 'Variation value is required'));
        exit();
    }
    
    if (!in_array($variation_type, ['size', 'color'])) {
        echo json_encode(array('success' => false, 'message' => 'Invalid variation type'));
        exit();
    }
    
    try {
        // If setting as default, unset other defaults of the same type
        if ($is_default == 1) {
            $stmt = $conn->prepare("UPDATE product_variations SET is_default = 0 WHERE product_id = ? AND variation_type = ?");
            $stmt->execute([$product_id, $variation_type]);
        }
        
        $stmt = $conn->prepare("INSERT INTO product_variations (product_id, variation_type, variation_value, price_modifier, inventory, is_active, is_default, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $variation_type, $variation_value, $price_modifier, $inventory, $is_active, $is_default, $display_order]);
        $variation_id = $conn->lastInsertId();
        
        echo json_encode(array('success' => true, 'message' => 'Variation added successfully', 'variation_id' => $variation_id));
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'unique_variation') !== false) {
            echo json_encode(array('success' => false, 'message' => 'This variation already exists for this product'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Error adding variation: ' . $e->getMessage()));
        }
    }
}

// PUT - Update variation (handled via POST with _method=PUT for file uploads compatibility)
else if ($method == 'PUT' || ($method == 'POST' && isset($_POST['_method']) && $_POST['_method'] == 'PUT')) {
    try {
        // Handle FormData (multipart/form-data) or URL-encoded data
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $variation_value = isset($_POST['variation_value']) ? trim($_POST['variation_value']) : '';
        $price_modifier = isset($_POST['price_modifier']) ? (float)$_POST['price_modifier'] : 0;
        $inventory = isset($_POST['inventory']) ? (int)$_POST['inventory'] : 0;
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
        $is_default = isset($_POST['is_default']) ? (int)$_POST['is_default'] : 0;
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
        
        // If not in POST, try to parse from php://input (for URL-encoded data)
        if ($id <= 0 || empty($variation_value)) {
            $input = file_get_contents('php://input');
            if (!empty($input)) {
                parse_str($input, $data);
                if ($id <= 0 && isset($data['id'])) {
                    $id = (int)$data['id'];
                }
                if (empty($variation_value) && isset($data['variation_value'])) {
                    $variation_value = trim($data['variation_value']);
                }
                if (isset($data['price_modifier'])) {
                    $price_modifier = (float)$data['price_modifier'];
                }
                if (isset($data['inventory'])) {
                    $inventory = (int)$data['inventory'];
                }
                if (isset($data['is_active'])) {
                    $is_active = (int)$data['is_active'];
                }
                if (isset($data['is_default'])) {
                    $is_default = (int)$data['is_default'];
                }
                if (isset($data['display_order'])) {
                    $display_order = (int)$data['display_order'];
                }
            }
        }
        
        if ($id <= 0) {
            echo json_encode(array('success' => false, 'message' => 'Invalid variation ID'));
            exit();
        }
        
        if (empty($variation_value)) {
            echo json_encode(array('success' => false, 'message' => 'Variation value is required'));
            exit();
        }
        
        // Get product_id and variation_type for this variation
        $stmt = $conn->prepare("SELECT product_id, variation_type, variation_value as old_variation_value FROM product_variations WHERE id = ?");
        $stmt->execute([$id]);
        $variation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$variation) {
            echo json_encode(array('success' => false, 'message' => 'Variation not found'));
            exit();
        }
        
        // Check if the new variation_value would conflict with another variation (excluding current one)
        if ($variation_value != $variation['old_variation_value']) {
            $checkStmt = $conn->prepare("SELECT id FROM product_variations WHERE product_id = ? AND variation_type = ? AND variation_value = ? AND id != ?");
            $checkStmt->execute([$variation['product_id'], $variation['variation_type'], $variation_value, $id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                echo json_encode(array('success' => false, 'message' => 'This variation already exists for this product'));
                exit();
            }
        }
        
        // If setting as default, unset other defaults of the same type
        if ($is_default == 1 && $variation) {
            $stmt = $conn->prepare("UPDATE product_variations SET is_default = 0 WHERE product_id = ? AND variation_type = ? AND id != ?");
            $stmt->execute([$variation['product_id'], $variation['variation_type'], $id]);
        }
        
        $stmt = $conn->prepare("UPDATE product_variations SET variation_value = ?, price_modifier = ?, inventory = ?, is_active = ?, is_default = ?, display_order = ? WHERE id = ?");
        $stmt->execute([$variation_value, $price_modifier, $inventory, $is_active, $is_default, $display_order, $id]);
        
        echo json_encode(array('success' => true, 'message' => 'Variation updated successfully'));
    } catch (PDOException $e) {
        // Check if it's a unique constraint violation
        if (strpos($e->getMessage(), 'unique_variation') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(array('success' => false, 'message' => 'This variation already exists for this product. Please use a different variation value.'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Error updating variation: ' . htmlspecialchars($e->getMessage())));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . htmlspecialchars($e->getMessage())));
    }
}

// DELETE - Delete variation
else if ($method == 'DELETE') {
    try {
        // Get ID from URL parameter first (most reliable)
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        // If not in URL, try to get from request body
        if ($id <= 0) {
            // Try POST data (if sent as POST with _method=DELETE)
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $id = (int)$_POST['id'];
            } else {
                // Try to parse from php://input (for URL-encoded or FormData)
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    // Check if it's multipart/form-data or URL-encoded
                    if (strpos($input, 'Content-Disposition: form-data') !== false) {
                        // Multipart form data - extract ID
                        if (preg_match('/name="id"\s*\r?\n\r?\n(\d+)/', $input, $matches)) {
                            $id = (int)$matches[1];
                        }
                    } else {
                        // URL-encoded data
                        parse_str($input, $data);
                        $id = isset($data['id']) ? (int)$data['id'] : 0;
                    }
                }
            }
        }
        
        if ($id <= 0) {
            echo json_encode(array('success' => false, 'message' => 'Invalid variation ID'));
            exit();
        }
        
        // Get product_id and variation_type before deleting
        $stmt = $conn->prepare("SELECT product_id, variation_type FROM product_variations WHERE id = ?");
        $stmt->execute([$id]);
        $variation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$variation) {
            echo json_encode(array('success' => false, 'message' => 'Variation not found'));
            exit();
        }
        
        $product_id = $variation['product_id'];
        $variation_type = $variation['variation_type'];
        
        // Check how many variations of this type exist for this product
        $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM product_variations WHERE product_id = ? AND variation_type = ?");
        $countStmt->execute([$product_id, $variation_type]);
        $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
        $variationCount = (int)$countResult['count'];
        
        // Prevent deletion if this is the last variation of this type
        if ($variationCount <= 1) {
            echo json_encode(array('success' => false, 'message' => 'Cannot delete the last variation. Every product must have at least one variation.'));
            exit();
        }
        
        // Delete the variation
        $stmt = $conn->prepare("DELETE FROM product_variations WHERE id = ?");
        $stmt->execute([$id]);
        
        // If the deleted variation was default, set the first remaining variation as default
        $remainingStmt = $conn->prepare("SELECT id FROM product_variations WHERE product_id = ? AND variation_type = ? ORDER BY display_order LIMIT 1");
        $remainingStmt->execute([$product_id, $variation_type]);
        $remaining = $remainingStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($remaining) {
            $updateDefaultStmt = $conn->prepare("UPDATE product_variations SET is_default = 1 WHERE id = ?");
            $updateDefaultStmt->execute([$remaining['id']]);
        }
        
        echo json_encode(array('success' => true, 'message' => 'Variation deleted successfully'));
    } catch (PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Error deleting variation: ' . htmlspecialchars($e->getMessage())));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . htmlspecialchars($e->getMessage())));
    }
}

else {
    echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
}

// Ensure no output after JSON
ob_end_flush();
exit();
?>

