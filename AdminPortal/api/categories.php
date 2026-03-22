<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);

ob_clean();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get all categories or one category
if ($method == 'GET') {
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute(array($id));
        $category = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt2 = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
        $stmt2->execute(array($id));
        $count = $stmt2->fetch(PDO::FETCH_ASSOC);
        $category['product_count'] = $count['product_count'];

        echo json_encode(array('success' => true, 'category' => $category));
    } else {
        $stmt = $conn->query("SELECT c.*, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY c.id DESC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(array('success' => true, 'categories' => $categories));
    }
    ob_end_flush();
    exit();
}

// PUT / UPDATE - handles POST with _method=PUT (needed for file uploads since PHP doesn't populate $_FILES on real PUT)
if ($method == 'PUT' || ($method == 'POST' && isset($_POST['_method']) && $_POST['_method'] == 'PUT')) {
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!$id || empty($name)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid data'));
        ob_end_flush();
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $stmt->execute(array($name, $id));
    if ($stmt->fetch()) {
        echo json_encode(array('success' => false, 'message' => 'Category name already exists'));
        ob_end_flush();
        exit();
    }

    $imageClause = '';
    $params = [$name, $description];

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            echo json_encode(array('success' => false, 'message' => 'Invalid image format'));
            ob_end_flush();
            exit();
        }
        $imageName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
            echo json_encode(array('success' => false, 'message' => 'Failed to upload image. Please check folder permissions.' ));
            ob_end_flush();
            exit();
        }
        $imageClause = ', image = ?';
        $params[] = $imageName;
    }

    $params[] = $id;
    $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ?" . $imageClause . " WHERE id = ?");
    $stmt->execute($params);

    echo json_encode(array('success' => true, 'message' => 'Category updated successfully'));
    ob_end_flush();
    exit();
}

// POST - Add new category
if ($method == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        echo json_encode(array('success' => false, 'message' => 'Category name is required'));
        ob_end_flush();
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute(array($name));
    if ($stmt->fetch()) {
        echo json_encode(array('success' => false, 'message' => 'Category already exists'));
        ob_end_flush();
        exit();
    }

    $imageName = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK && $_FILES['image']['size'] > 0) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            echo json_encode(array('success' => false, 'message' => 'Invalid image format'));
            ob_end_flush();
            exit();
        }
        $imageName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['image']['name']));
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageName)) {
            echo json_encode(array('success' => false, 'message' => 'Failed to upload image. Please check folder permissions.' ));
            ob_end_flush();
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO categories (name, description, image) VALUES (?, ?, ?)");
    $stmt->execute(array($name, $description, $imageName));

    echo json_encode(array('success' => true, 'message' => 'Category added successfully'));
    ob_end_flush();
    exit();
}

// DELETE - Delete category
if ($method == 'DELETE') {
    $id = intval($_GET['id'] ?? 0);

    $stmt = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
    $stmt->execute(array($id));
    $count = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($count['product_count'] > 0) {
        echo json_encode(array('success' => false, 'message' => 'Cannot delete category with products'));
        ob_end_flush();
        exit();
    }

    // Get image path and delete file from uploads to save space (before deleting DB row)
    $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
    $stmt->execute(array($id));
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['image'])) {
        $baseDir = __DIR__ . '/../uploads/';
        $filePath = $baseDir . basename($row['image']);
        if (file_exists($filePath) && is_file($filePath)) {
            @unlink($filePath);
        }
    }

    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute(array($id));

    echo json_encode(array('success' => true, 'message' => 'Category deleted successfully'));
    ob_end_flush();
    exit();
}

echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
ob_end_flush();
?>
