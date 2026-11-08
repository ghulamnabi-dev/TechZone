<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/uploads.php';
require_once __DIR__ . '/../../core/audit.php';

/**
 * Keep the discount-engine columns in step with the legacy form fields: the schedule follows
 * start/end date, and kind follows which discount field is set (free_shipping is kept when
 * both are zero).
 */
function promotion_sync_engine(PDO $conn, int $id): void
{
    $conn->prepare("UPDATE promotions SET
            starts_at = IF(start_date IS NULL, NULL, TIMESTAMP(start_date)),
            ends_at = IF(end_date IS NULL, NULL, TIMESTAMP(end_date, '23:59:59')),
            kind = CASE WHEN discount_percentage > 0 THEN 'percent' WHEN discount_amount > 0 THEN 'fixed'
                        WHEN kind = 'free_shipping' THEN 'free_shipping' ELSE kind END
        WHERE id = ?")->execute([$id]);
}

checkLogin(true);
json_headers();
// Any write needs discounts.write (core/permissions.php); reads are open to every role.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_can('discounts.write');
}

$method = $_SERVER['REQUEST_METHOD'];
$uploadDir = __DIR__ . '/../uploads';

/**
 * Validate promotion fields from $src (POST form or JSON body). Exits 422 on invalid input.
 */
function promotion_input(array $src): array
{
    $title = v_str($src['title'] ?? '', 200);
    $description = v_str($src['description'] ?? '', 5000);
    $pct = v_num(($src['discount_percentage'] ?? '') === '' ? 0 : $src['discount_percentage'], 0, 100);
    $amount = v_num(($src['discount_amount'] ?? '') === '' ? 0 : $src['discount_amount'], 0, 99999999);
    $code = v_str($src['promo_code'] ?? '', 50);
    $start = v_date($src['start_date'] ?? '');
    $end = v_date($src['end_date'] ?? '');
    $isActive = isset($src['is_active']) ? ((int) $src['is_active'] === 1 ? 1 : 0) : 1;
    $order = v_int(($src['display_order'] ?? '') === '' ? 0 : $src['display_order'], -100000, 100000);
    // Rendered as an href on the storefront: only relative paths or http(s) URLs (no javascript:)
    $link = v_link($src['link_url'] ?? '');

    if ($title === null || $title === '') {
        json_error('Title is required (max 200 characters)', 422);
    }
    if ($description === null) {
        json_error('Description is too long', 422);
    }
    if ($pct === null || $amount === null) {
        json_error('Discounts must be numbers (percentage 0-100)', 422);
    }
    if ($code === null || ($code !== '' && !preg_match('/^[A-Za-z0-9_-]+$/', $code))) {
        json_error('Promo code may only contain letters, numbers, dashes and underscores (max 50)', 422);
    }
    if ($start === false || $end === false) {
        json_error('Dates must be in YYYY-MM-DD format', 422);
    }
    if ($start !== null && $end !== null && $end < $start) {
        json_error('End date must be on or after the start date', 422);
    }
    if ($order === null) {
        json_error('Invalid display order', 422);
    }
    if ($link === null) {
        json_error('Link must be a relative path or an http(s) URL', 422);
    }
    return [$title, $description, $pct, $amount, $code, $start, $end, $isActive, $order, $link];
}

try {
    // GET - all promotions
    if ($method === 'GET') {
        $stmt = $conn->query("SELECT * FROM promotions ORDER BY display_order ASC, created_at DESC");
        json_response(['success' => true, 'promotions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // POST - create, or update when id is given (multipart, optional image)
    if ($method === 'POST') {
        enforce_csrf_json();
        $id = v_id($_POST['id'] ?? 0);
        [$title, $description, $pct, $amount, $code, $start, $end, $isActive, $order, $link] = promotion_input($_POST);

        $oldImage = null;
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT image_url FROM promotions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_error('Promotion not found', 404);
            }
            $oldImage = $row['image_url'];
        }

        $imageUrl = $oldImage ?? '';
        if (upload_present('image')) {
            try {
                $imageUrl = 'uploads/' . store_uploaded_image('image', $uploadDir);
            } catch (UploadException $e) {
                json_error($e->getMessage(), 422);
            }
        }

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE promotions SET title = ?, description = ?, image_url = ?, discount_percentage = ?, discount_amount = ?, promo_code = ?, start_date = ?, end_date = ?, is_active = ?, display_order = ?, link_url = ? WHERE id = ?");
            $stmt->execute([$title, $description, $imageUrl, $pct, $amount, $code, $start, $end, $isActive, $order, $link, $id]);
            if ($oldImage && $oldImage !== $imageUrl && strpos($oldImage, 'uploads/') === 0) {
                delete_upload_if_unused($conn, $oldImage, $uploadDir);
            }
            promotion_sync_engine($conn, $id);
            audit('discount.update', 'promotion', $id, 'Updated discount ' . ($code ?: $title));
            json_response(['success' => true, 'message' => 'Promotion updated successfully']);
        }

        $stmt = $conn->prepare("INSERT INTO promotions (title, description, image_url, discount_percentage, discount_amount, promo_code, start_date, end_date, is_active, display_order, link_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $imageUrl, $pct, $amount, $code, $start, $end, $isActive, $order, $link]);
        $newId = (int) $conn->lastInsertId();
        promotion_sync_engine($conn, $newId);
        audit('discount.create', 'promotion', $newId, 'Created discount ' . ($code ?: $title));
        json_response(['success' => true, 'message' => 'Promotion created successfully', 'id' => $newId]);
    }

    // PUT - JSON update (kept for backward compatibility; image cannot be changed here)
    if ($method === 'PUT') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? 0);
        if ($id === 0) {
            json_error('Invalid ID', 422);
        }
        [$title, $description, $pct, $amount, $code, $start, $end, $isActive, $order, $link] = promotion_input($data);

        $stmt = $conn->prepare("UPDATE promotions SET title = ?, description = ?, discount_percentage = ?, discount_amount = ?, promo_code = ?, start_date = ?, end_date = ?, is_active = ?, display_order = ?, link_url = ? WHERE id = ?");
        $stmt->execute([$title, $description, $pct, $amount, $code, $start, $end, $isActive, $order, $link, $id]);
        promotion_sync_engine($conn, $id);
        audit('discount.update', 'promotion', $id, 'Updated discount ' . ($code ?: $title));
        json_response(['success' => true, 'message' => 'Promotion updated successfully']);
    }

    // DELETE - JSON body {id} (or ?id=)
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id === 0) {
            json_error('Invalid ID', 422);
        }

        $stmt = $conn->prepare("SELECT image_url FROM promotions WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetchColumn();

        $conn->prepare("DELETE FROM promotions WHERE id = ?")->execute([$id]);
        audit('discount.delete', 'promotion', $id, 'Deleted discount #' . $id);
        if ($image && strpos($image, 'uploads/') === 0) {
            delete_upload_if_unused($conn, $image, $uploadDir);
        }
        json_response(['success' => true, 'message' => 'Promotion deleted successfully']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not save the promotion.'), 500);
}
