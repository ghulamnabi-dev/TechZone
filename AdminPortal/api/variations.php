<?php
ob_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();
// Any write needs products.write (core/permissions.php); reads are open to every role.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_can('products.write');
}

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Request fields from multipart/urlencoded POST, falling back to a raw
 * urlencoded body (PUT/DELETE requests do not populate $_POST).
 */
function variation_fields(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $data = request_json();
    if ($data) {
        return $data;
    }
    $raw = file_get_contents('php://input');
    $parsed = [];
    if (is_string($raw) && $raw !== '') {
        if (strpos($raw, 'Content-Disposition: form-data') !== false) {
            if (preg_match('/name="id"\s*\r?\n\r?\n(\d+)/', $raw, $m)) {
                $parsed['id'] = $m[1];
            }
        } else {
            parse_str($raw, $parsed);
        }
    }
    return $parsed;
}

function variation_numbers(array $src): array
{
    $price = v_num(($src['price_modifier'] ?? '') === '' ? 0 : $src['price_modifier'], -99999999, 99999999);
    $inventory = v_int(($src['inventory'] ?? '') === '' ? 0 : $src['inventory'], 0, 1000000);
    $order = v_int(($src['display_order'] ?? '') === '' ? 0 : $src['display_order'], -100000, 100000);
    if ($price === null || $inventory === null || $order === null) {
        json_error('Price modifier, inventory and display order must be valid numbers (inventory >= 0)', 422);
    }
    $active = isset($src['is_active']) ? ((int) $src['is_active'] === 1 ? 1 : 0) : 1;
    $default = isset($src['is_default']) ? ((int) $src['is_default'] === 1 ? 1 : 0) : 0;
    return [$price, $inventory, $active, $default, $order];
}

try {
    // GET - variations for a product
    if ($method === 'GET') {
        if (!isset($_GET['product_id'])) {
            json_error('Product ID required', 422);
        }
        $stmt = $conn->prepare("SELECT * FROM product_variations WHERE product_id = ? ORDER BY variation_type, display_order");
        $stmt->execute([v_id($_GET['product_id'])]);
        json_response(['success' => true, 'variations' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    $isUpdate = $method === 'PUT' || ($method === 'POST' && ($_POST['_method'] ?? '') === 'PUT');

    // POST - add variation
    if ($method === 'POST' && !$isUpdate) {
        enforce_csrf_json();
        $productId = v_id($_POST['product_id'] ?? 0);
        $type = v_enum($_POST['variation_type'] ?? '', ['size', 'color']);
        $value = v_str($_POST['variation_value'] ?? '', 100);
        [$price, $inventory, $active, $default, $order] = variation_numbers($_POST);

        if ($value === null || $value === '') {
            json_error('Variation value is required (max 100 characters)', 422);
        }
        if ($type === null) {
            json_error('Invalid variation type', 422);
        }
        $stmt = $conn->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            json_error('Product not found', 404);
        }

        $conn->beginTransaction();
        if ($default === 1) {
            $conn->prepare("UPDATE product_variations SET is_default = 0 WHERE product_id = ? AND variation_type = ?")
                 ->execute([$productId, $type]);
        }
        try {
            $stmt = $conn->prepare("INSERT INTO product_variations (product_id, variation_type, variation_value, price_modifier, inventory, is_active, is_default, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$productId, $type, $value, $price, $inventory, $active, $default, $order]);
            $variationId = $conn->lastInsertId();
            $conn->commit();
        } catch (PDOException $e) {
            $conn->rollBack();
            if ($e->getCode() === '23000') {
                json_error('This variation already exists for this product', 409);
            }
            throw $e;
        }
        json_response(['success' => true, 'message' => 'Variation added successfully', 'variation_id' => $variationId]);
    }

    // PUT (or POST with _method=PUT) - update variation
    if ($isUpdate) {
        enforce_csrf_json();
        $src = variation_fields();
        $id = v_id($src['id'] ?? 0);
        $value = v_str($src['variation_value'] ?? '', 100);
        [$price, $inventory, $active, $default, $order] = variation_numbers($src);

        if ($id === 0) {
            json_error('Invalid variation ID', 422);
        }
        if ($value === null || $value === '') {
            json_error('Variation value is required (max 100 characters)', 422);
        }

        $stmt = $conn->prepare("SELECT product_id, variation_type, variation_value FROM product_variations WHERE id = ?");
        $stmt->execute([$id]);
        $variation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$variation) {
            json_error('Variation not found', 404);
        }

        if ($value !== $variation['variation_value']) {
            $check = $conn->prepare("SELECT id FROM product_variations WHERE product_id = ? AND variation_type = ? AND variation_value = ? AND id != ?");
            $check->execute([$variation['product_id'], $variation['variation_type'], $value, $id]);
            if ($check->fetch()) {
                json_error('This variation already exists for this product', 409);
            }
        }

        $conn->beginTransaction();
        if ($default === 1) {
            $conn->prepare("UPDATE product_variations SET is_default = 0 WHERE product_id = ? AND variation_type = ? AND id != ?")
                 ->execute([$variation['product_id'], $variation['variation_type'], $id]);
        }
        $stmt = $conn->prepare("UPDATE product_variations SET variation_value = ?, price_modifier = ?, inventory = ?, is_active = ?, is_default = ?, display_order = ? WHERE id = ?");
        $stmt->execute([$value, $price, $inventory, $active, $default, $order, $id]);
        $conn->commit();

        json_response(['success' => true, 'message' => 'Variation updated successfully']);
    }

    // DELETE - ?id= (or id in the body)
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $id = v_id($_GET['id'] ?? 0);
        if ($id === 0) {
            $id = v_id(variation_fields()['id'] ?? 0);
        }
        if ($id === 0) {
            json_error('Invalid variation ID', 422);
        }

        $stmt = $conn->prepare("SELECT product_id, variation_type FROM product_variations WHERE id = ?");
        $stmt->execute([$id]);
        $variation = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$variation) {
            json_error('Variation not found', 404);
        }

        $count = $conn->prepare("SELECT COUNT(*) FROM product_variations WHERE product_id = ? AND variation_type = ?");
        $count->execute([$variation['product_id'], $variation['variation_type']]);
        if ((int) $count->fetchColumn() <= 1) {
            json_error('Cannot delete the last variation. Every product must have at least one variation.', 409);
        }

        $conn->beginTransaction();
        $conn->prepare("DELETE FROM product_variations WHERE id = ?")->execute([$id]);

        // Make sure one variation of this type stays the default
        $hasDefault = $conn->prepare("SELECT COUNT(*) FROM product_variations WHERE product_id = ? AND variation_type = ? AND is_default = 1");
        $hasDefault->execute([$variation['product_id'], $variation['variation_type']]);
        if ((int) $hasDefault->fetchColumn() === 0) {
            $conn->prepare("UPDATE product_variations SET is_default = 1 WHERE product_id = ? AND variation_type = ? ORDER BY display_order, id LIMIT 1")
                 ->execute([$variation['product_id'], $variation['variation_type']]);
        }
        $conn->commit();

        json_response(['success' => true, 'message' => 'Variation deleted successfully']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not process the variation request.'), 500);
}
