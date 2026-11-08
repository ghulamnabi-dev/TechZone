<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/uploads.php';
require_once __DIR__ . '/../../core/inventory.php';
require_once __DIR__ . '/../../core/audit.php';
require_once __DIR__ . '/../../core/settings.php';

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

/**
 * Validate the product form fields shared by create and update.
 * Responds 422 and exits on invalid input.
 */
function product_input(): array
{
    $name = v_str($_POST['name'] ?? '', 200);
    $description = v_str($_POST['description'] ?? '', 20000);
    $price = v_num($_POST['price'] ?? null, 0, 999999999);
    $categoryRaw = $_POST['category_id'] ?? '';
    $categoryId = ($categoryRaw === '' || $categoryRaw === null) ? null : v_id($categoryRaw);
    $inventory = v_int($_POST['inventory'] ?? 0, 0, 1000000);
    $sold = v_int(($_POST['sold'] ?? '') === '' ? 0 : $_POST['sold'], 0, 100000000);
    $isActive = isset($_POST['is_active']) ? ((int) $_POST['is_active'] === 1 ? 1 : 0) : 1;

    if ($name === null || $name === '') {
        json_error('Product name is required (max 200 characters)', 422);
    }
    if ($description === null) {
        json_error('Description is too long', 422);
    }
    if ($price === null) {
        json_error('Price must be a non-negative number', 422);
    }
    if ($categoryId === 0) {
        json_error('Invalid category', 422);
    }
    if ($inventory === null || $sold === null) {
        json_error('Inventory and sold must be non-negative whole numbers', 422);
    }
    return [$name, $description, $price, $categoryId, $inventory, $sold, $isActive];
}

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
$uploadDir = __DIR__ . '/../uploads';

try {
    // GET - all products or one product
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $stmt = $conn->prepare("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
            $stmt->execute([v_id($_GET['id'])]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                json_response(['success' => false, 'message' => 'Product not found'], 404);
            }
            $product['imagelink'] = product_imagelink_for_client($product['imagelink'] ?? '');
            json_response(['success' => true, 'product' => $product]);
        }

        $stmt = $conn->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.id DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$row) {
            $row['imagelink'] = product_imagelink_for_client($row['imagelink'] ?? '');
        }
        unset($row);
        json_response(['success' => true, 'products' => $products]);
    }

    $isUpdate = $method === 'PUT'
        || ($method === 'POST' && ((($_POST['_method'] ?? '') === 'PUT') || !empty($_POST['id'])));

    // Writes: products.write; DELETE additionally products.delete (owner only)
    if ($method !== 'GET') {
        require_can($method === 'DELETE' ? 'products.delete' : 'products.write');
    }

    // UPDATE - POST with _method=PUT (multipart, so image uploads work)
    // Stock is never written directly: a changed inventory becomes a 'correction' in the ledger.
    if ($isUpdate) {
        enforce_csrf_json();
        $id = v_id($_POST['id'] ?? 0);
        if ($id === 0) {
            json_error('Product ID is required for update', 422);
        }
        [$name, $description, $price, $categoryId, $inventory, $sold, $isActive] = product_input();

        $stmt = $conn->prepare("SELECT name, description, price, category_id, imagelink, inventory, sold, is_active, status, sku FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            json_error('Product not found', 404);
        }
        $oldImagelink = (string) ($existing['imagelink'] ?? '');
        $imagelink = $oldImagelink;

        if (upload_present('image')) {
            try {
                $imagelink = 'uploads/' . store_uploaded_image('image', $uploadDir);
            } catch (UploadException $e) {
                json_error($e->getMessage(), 422);
            }
        }

        // is_active is the storefront switch; status keeps 'archived' unless re-activated
        $status = $isActive === 1 ? 'active' : ($existing['status'] === 'archived' ? 'archived' : 'draft');
        $conn->beginTransaction();
        try {
            $lock = $conn->prepare("SELECT inventory FROM products WHERE id = ? FOR UPDATE");
            $lock->execute([$id]);
            $delta = $inventory - (int) $lock->fetchColumn();
            if ($delta !== 0) {
                require_can('inventory.adjust');
                adjust_inventory($conn, $id, null, $delta, 'correction', 'Edited on the product form', (int) $_SESSION['admin_id']);
            }
            $stmt = $conn->prepare("UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, imagelink = ?, sold = ?, is_active = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $description, $price, $categoryId, $imagelink, $sold, $isActive, $status, $id]);
            [$before, $after] = audit_diff($existing, [
                'name' => $name, 'description' => $description, 'price' => number_format((float) $price, 2, '.', ''),
                'category_id' => $categoryId, 'imagelink' => $imagelink, 'inventory' => $inventory, 'sold' => $sold, 'status' => $status,
            ]);
            if (isset($before['description'])) {
                $before['description'] = '…';
                $after['description'] = '…';
            }
            audit('product.update', 'product', $id, 'Updated ' . ($existing['sku'] ? $existing['sku'] . ' ' : '') . $name
                . ($after ? ' (' . implode(', ', array_keys($after)) . ')' : ''), $before ?: null, $after ?: null);
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            if ($e instanceof InventoryException) {
                json_error($e->getMessage(), 409);
            }
            throw $e;
        }

        if ($imagelink !== $oldImagelink && strpos($oldImagelink, 'uploads/') === 0) {
            delete_upload_if_unused($conn, $oldImagelink, $uploadDir);
        }
        json_response(['success' => true, 'message' => 'Product updated successfully']);
    }

    // CREATE
    if ($method === 'POST') {
        enforce_csrf_json();
        [$name, $description, $price, $categoryId, $inventory, $sold, $isActive] = product_input();

        $imagelink = '';
        if (upload_present('image')) {
            try {
                $imagelink = 'uploads/' . store_uploaded_image('image', $uploadDir);
            } catch (UploadException $e) {
                json_error($e->getMessage(), 422);
            }
        }

        // Created with 0 stock; the opening quantity goes through the ledger as 'received'.
        $conn->beginTransaction();
        try {
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, imagelink, inventory, sold, is_active, status, low_stock_threshold) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $categoryId, $imagelink, $sold, $isActive, $isActive === 1 ? 'active' : 'draft', (int) setting('low_stock_threshold', 5)]);
            $productId = (int) $conn->lastInsertId();
            $sku = sprintf('TZ-%04d', $productId);
            $conn->prepare("UPDATE products SET sku = ? WHERE id = ? AND sku IS NULL")->execute([$sku, $productId]);
            if ($inventory > 0) {
                adjust_inventory($conn, $productId, null, $inventory, 'received', 'Opening stock', (int) $_SESSION['admin_id']);
            }
            audit('product.create', 'product', $productId, "Created $sku $name", null, ['price' => $price, 'inventory' => $inventory, 'status' => $isActive === 1 ? 'active' : 'draft']);
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }
        // Default variation creation is handled by the admin JS after product creation.
        json_response(['success' => true, 'message' => 'Product added successfully', 'product_id' => $productId, 'id' => $productId]);
    }

    // DELETE - ?id=
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $id = v_id($_GET['id'] ?? 0);
        if ($id === 0) {
            json_error('Product ID is required', 422);
        }

        $stmt = $conn->prepare("SELECT imagelink, name, sku FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            json_error('Product not found', 404);
        }

        // order_items references products with ON DELETE RESTRICT
        $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            json_error('Cannot delete product. It has been ordered by customers. Please remove all orders containing this product first.', 409);
        }

        // product_variations and reviews are removed by ON DELETE CASCADE
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            json_error('Failed to delete product', 500);
        }
        audit('product.delete', 'product', $id, 'Deleted ' . trim(($row['sku'] ?? '') . ' ' . $row['name']), ['name' => $row['name']], null);
        if (!empty($row['imagelink']) && strpos($row['imagelink'], 'uploads/') === 0) {
            delete_upload_if_unused($conn, $row['imagelink'], $uploadDir);
        }
        json_response(['success' => true, 'message' => 'Product deleted successfully']);
    }

    // PATCH - quick active/inactive toggle (JSON body: id, is_active)
    if ($method === 'PATCH') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? 0);
        $isActive = isset($data['is_active']) ? ((int) $data['is_active'] === 1 ? 1 : 0) : null;
        if ($id === 0 || $isActive === null) {
            json_error('Invalid request', 422);
        }
        $stmt = $conn->prepare("SELECT name, sku, status FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $cur = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cur) {
            json_error('Product not found', 404);
        }
        $status = $isActive === 1 ? 'active' : ($cur['status'] === 'archived' ? 'archived' : 'draft');
        $stmt = $conn->prepare("UPDATE products SET is_active = ?, status = ? WHERE id = ?");
        $stmt->execute([$isActive, $status, $id]);
        if ($status !== $cur['status']) {
            audit('product.status', 'product', $id, ($isActive ? 'Activated ' : 'Deactivated ') . trim(($cur['sku'] ?? '') . ' ' . $cur['name']), ['status' => $cur['status']], ['status' => $status]);
        }
        json_response(['success' => true, 'message' => 'Product status updated successfully']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Database error. Please try again.'), 500);
}
