<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/uploads.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
$uploadDir = __DIR__ . '/../uploads';

try {
    // GET - all categories or one category
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $id = v_id($_GET['id']);
            $stmt = $conn->prepare("SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count FROM categories c WHERE c.id = ?");
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                json_error('Category not found', 404);
            }
            json_response(['success' => true, 'category' => $category]);
        }
        $stmt = $conn->query("SELECT c.*, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY c.id DESC");
        json_response(['success' => true, 'categories' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $isUpdate = $method === 'PUT' || ($method === 'POST' && ($_POST['_method'] ?? '') === 'PUT');

    // POST (create) / POST with _method=PUT (update; PHP only fills $_FILES on POST)
    if ($method === 'POST' || $isUpdate) {
        enforce_csrf_json();

        $id = v_id($_POST['id'] ?? 0);
        $name = v_str($_POST['name'] ?? '', 100);
        $description = v_str($_POST['description'] ?? '', 5000);

        if ($isUpdate && $id === 0) {
            json_error('Invalid data', 422);
        }
        if ($name === null || $name === '') {
            json_error('Category name is required (max 100 characters)', 422);
        }
        if ($description === null) {
            json_error('Description is too long (max 5000 characters)', 422);
        }

        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
        $stmt->execute([$name, $id]);
        if ($stmt->fetch()) {
            json_error($isUpdate ? 'Category name already exists' : 'Category already exists', 409);
        }

        $oldImage = null;
        if ($isUpdate) {
            $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_error('Category not found', 404);
            }
            $oldImage = $row['image'];
        }

        $newImage = null;
        if (upload_present('image')) {
            try {
                $newImage = store_uploaded_image('image', $uploadDir);   // stored as bare file name
            } catch (UploadException $e) {
                json_error($e->getMessage(), 422);
            }
        }

        if ($isUpdate) {
            $sql = "UPDATE categories SET name = ?, description = ?" . ($newImage !== null ? ", image = ?" : "") . " WHERE id = ?";
            $params = $newImage !== null ? [$name, $description, $newImage, $id] : [$name, $description, $id];
            $conn->prepare($sql)->execute($params);
            if ($newImage !== null && $oldImage) {
                delete_upload_if_unused($conn, $oldImage, $uploadDir);
            }
            json_response(['success' => true, 'message' => 'Category updated successfully']);
        }

        $stmt = $conn->prepare("INSERT INTO categories (name, description, image) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $newImage]);
        json_response(['success' => true, 'message' => 'Category added successfully', 'id' => $conn->lastInsertId()]);
    }

    // DELETE - ?id=
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $id = v_id($_GET['id'] ?? 0);
        if ($id === 0) {
            json_error('Invalid category ID', 422);
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            json_error('Cannot delete category with products', 409);
        }

        $stmt = $conn->prepare("SELECT image FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();

        $conn->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        if ($image) {
            delete_upload_if_unused($conn, $image, $uploadDir);
        }
        json_response(['success' => true, 'message' => 'Category deleted successfully']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not process the request.'), 500);
}
