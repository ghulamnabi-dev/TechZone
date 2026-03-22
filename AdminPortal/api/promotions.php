<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);
header('Content-Type: application/json');

// Check if promotions table exists, if not create it
try {
    $conn->query("SELECT 1 FROM promotions LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $sql = "CREATE TABLE IF NOT EXISTS promotions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        image_url VARCHAR(500),
        discount_percentage DECIMAL(5, 2) DEFAULT 0,
        discount_amount DECIMAL(10, 2) DEFAULT 0,
        promo_code VARCHAR(50),
        start_date DATE,
        end_date DATE,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        link_url VARCHAR(500),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->exec($sql);
}

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get all promotions
if ($method == 'GET') {
    $query = "SELECT * FROM promotions ORDER BY display_order ASC, created_at DESC";
    $stmt = $conn->query($query);
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(array('success' => true, 'promotions' => $promotions));
}

// POST - Create new promotion or update (check for ID)
else if ($method == 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $discount_percentage = isset($_POST['discount_percentage']) ? (float)$_POST['discount_percentage'] : 0;
    $discount_amount = isset($_POST['discount_amount']) ? (float)$_POST['discount_amount'] : 0;
    $promo_code = $_POST['promo_code'] ?? '';
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;
    $link_url = $_POST['link_url'] ?? '';
    
    if (empty($title)) {
        echo json_encode(array('success' => false, 'message' => 'Title is required'));
        exit();
    }
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
        $uploadFile = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            // Store path relative to admin directory (will be adjusted in display code if needed)
            $image_url = 'uploads/' . $fileName;
        }
    } elseif ($id > 0) {
        // Keep existing image if updating and no new file uploaded
        $stmt = $conn->prepare("SELECT image_url FROM promotions WHERE id = ?");
        $stmt->execute(array($id));
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($promo) {
            $image_url = $promo['image_url'];
        }
    }
    
    try {
        if ($id > 0) {
            // Update existing promotion
            $stmt = $conn->prepare("UPDATE promotions SET title = ?, description = ?, image_url = ?, discount_percentage = ?, discount_amount = ?, promo_code = ?, start_date = ?, end_date = ?, is_active = ?, display_order = ?, link_url = ? WHERE id = ?");
            $stmt->execute(array($title, $description, $image_url, $discount_percentage, $discount_amount, $promo_code, $start_date ?: null, $end_date ?: null, $is_active, $display_order, $link_url, $id));
            
            echo json_encode(array('success' => true, 'message' => 'Promotion updated successfully'));
        } else {
            // Create new promotion
            $stmt = $conn->prepare("INSERT INTO promotions (title, description, image_url, discount_percentage, discount_amount, promo_code, start_date, end_date, is_active, display_order, link_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute(array($title, $description, $image_url, $discount_percentage, $discount_amount, $promo_code, $start_date ?: null, $end_date ?: null, $is_active, $display_order, $link_url));
            
            echo json_encode(array('success' => true, 'message' => 'Promotion created successfully', 'id' => $conn->lastInsertId()));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
    }
}

// PUT - Update promotion (kept for backward compatibility, but POST handles both now)
else if ($method == 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? 0;
    if ($id <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Invalid ID'));
        exit();
    }
    
    $title = $data['title'] ?? '';
    $description = $data['description'] ?? '';
    $image_url = $data['image_url'] ?? '';
    $discount_percentage = $data['discount_percentage'] ?? 0;
    $discount_amount = $data['discount_amount'] ?? 0;
    $promo_code = $data['promo_code'] ?? '';
    $start_date = $data['start_date'] ?? null;
    $end_date = $data['end_date'] ?? null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : 1;
    $display_order = $data['display_order'] ?? 0;
    $link_url = $data['link_url'] ?? '';
    
    if (empty($title)) {
        echo json_encode(array('success' => false, 'message' => 'Title is required'));
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE promotions SET title = ?, description = ?, image_url = ?, discount_percentage = ?, discount_amount = ?, promo_code = ?, start_date = ?, end_date = ?, is_active = ?, display_order = ?, link_url = ? WHERE id = ?");
        $stmt->execute(array($title, $description, $image_url, $discount_percentage, $discount_amount, $promo_code, $start_date ?: null, $end_date ?: null, $is_active, $display_order, $link_url, $id));
        
        echo json_encode(array('success' => true, 'message' => 'Promotion updated successfully'));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
    }
}

// DELETE - Delete promotion
else if ($method == 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? 0;
    
    if ($id <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Invalid ID'));
        exit();
    }
    
    try {
        // Get image path and delete file from uploads to save space (before deleting DB row)
        $stmt = $conn->prepare("SELECT image_url FROM promotions WHERE id = ?");
        $stmt->execute(array($id));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['image_url']) && strpos($row['image_url'], 'uploads/') === 0) {
            $filePath = __DIR__ . '/../' . $row['image_url'];
            if (file_exists($filePath) && is_file($filePath)) {
                @unlink($filePath);
            }
        }
        
        $stmt = $conn->prepare("DELETE FROM promotions WHERE id = ?");
        $stmt->execute(array($id));
        
        echo json_encode(array('success' => true, 'message' => 'Promotion deleted successfully'));
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Error: ' . $e->getMessage()));
    }
}
?>
