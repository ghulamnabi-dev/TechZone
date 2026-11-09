<?php
/**
 * Products API (manage/products.php, manage/product.php).
 *
 * Seller accounts (core/permissions.php): every read is limited to products.seller_id = their seller
 * (seller_scope_products_sql); GET ?id= of another seller's product answers 404; every write
 * (JSON create/update, image, legacy multipart, PATCH) runs assert_product_owned() first (403), and a
 * product a seller creates is always theirs. Only owner/manager (sellers.manage) can move a product to
 * another seller (seller_id).
 */
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
require_once __DIR__ . '/../../core/catalog.php';   // record_price_point(): rate history for the storefront
require_once __DIR__ . '/../../core/attributes.php';   // Specifications card: attribute_form / _validate / _save

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


/** sellers.id of TechZone itself (products.seller_id default). */
const HOUSE_SELLER = 1;

/** Editable columns of the product page (manage/product.php), in audit order. */
const PRODUCT_EDITOR_FIELDS = ['name', 'category_id', 'sku', 'description', 'price', 'compare_at_price', 'cost_price',
    'low_stock_threshold', 'status', 'seo_title', 'seo_description'];

/**
 * Validate the product page's JSON body. Collects every problem so the page can show them
 * inline under each field (validation_problem()).
 *
 * @param array<string,string> $extraErrors problems found elsewhere (specifications), reported together
 * @return array normalised values keyed by PRODUCT_EDITOR_FIELDS (+ 'inventory' on create)
 */
function product_editor_input(PDO $conn, array $d, ?int $id, array $extraErrors = []): array
{
    $errors = [];
    $money = static function ($v, bool $nullable) {
        if ($v === null || $v === '') {
            return $nullable ? null : false;
        }
        $n = v_num($v, 0, 999999999);
        return $n === null ? false : round($n, 2);
    };

    $out = [];
    $out['name'] = v_str($d['name'] ?? '', 200, false);
    if ($out['name'] === null) {
        $errors['name'] = 'Give the product a name (up to 200 characters).';
    }
    $cat = $d['category_id'] ?? '';
    $out['category_id'] = ($cat === '' || $cat === null) ? null : v_id($cat);
    if ($out['category_id'] === 0) {
        $errors['category_id'] = 'Choose a category from the list.';
    } elseif ($out['category_id'] !== null) {
        $st = $conn->prepare('SELECT COUNT(*) FROM categories WHERE id = ?');
        $st->execute([$out['category_id']]);
        if (!(int) $st->fetchColumn()) {
            $errors['category_id'] = 'That category no longer exists.';
        }
    }
    $sku = v_str($d['sku'] ?? '', 40);
    if ($sku === null || ($sku !== '' && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\-]*$/', $sku))) {
        $errors['sku'] = 'SKU may use letters, numbers, dots and dashes (up to 40).';
    } else {
        $sku = strtoupper($sku);
        if ($sku !== '') {
            $st = $conn->prepare('SELECT id FROM products WHERE sku = ? AND id <> ?');
            $st->execute([$sku, $id ?? 0]);
            if ($st->fetchColumn()) {
                $errors['sku'] = "SKU $sku is already used by another product.";
            }
        }
    }
    $out['sku'] = $sku === '' ? null : $sku;
    $out['description'] = v_str($d['description'] ?? '', 20000);
    if ($out['description'] === null) {
        $errors['description'] = 'Description is too long (20,000 characters at most).';
    }
    $out['price'] = $money($d['price'] ?? null, false);
    if ($out['price'] === false) {
        $errors['price'] = 'Enter a price in rupees (0 or more).';
    }
    $out['compare_at_price'] = $money($d['compare_at_price'] ?? null, true);
    if ($out['compare_at_price'] === false) {
        $errors['compare_at_price'] = 'Enter a number, or leave it empty.';
    } elseif ($out['compare_at_price'] !== null && $out['price'] !== false && $out['compare_at_price'] <= $out['price']) {
        $errors['compare_at_price'] = 'Compare-at price must be higher than the price, or empty.';
    }
    $out['cost_price'] = $money($d['cost_price'] ?? null, true);
    if ($out['cost_price'] === false) {
        $errors['cost_price'] = 'Enter a number, or leave it empty.';
    }
    $out['low_stock_threshold'] = v_int(($d['low_stock_threshold'] ?? '') === '' ? 5 : $d['low_stock_threshold'], 0, 100000);
    if ($out['low_stock_threshold'] === null) {
        $errors['low_stock_threshold'] = 'Use a whole number from 0 to 100,000.';
    }
    $out['status'] = v_enum($d['status'] ?? 'draft', ['draft', 'active', 'archived']);
    if ($out['status'] === null) {
        $errors['status'] = 'Choose Draft, Active or Archived.';
    }
    $out['seo_title'] = v_str($d['seo_title'] ?? '', 200);
    if ($out['seo_title'] === null) {
        $errors['seo_title'] = 'Keep the page title under 200 characters (60 shows in search).';
    }
    $out['seo_description'] = v_str($d['seo_description'] ?? '', 320);
    if ($out['seo_description'] === null) {
        $errors['seo_description'] = 'Keep the meta description under 320 characters.';
    }
    $out['seo_title'] = $out['seo_title'] === '' ? null : $out['seo_title'];
    $out['seo_description'] = $out['seo_description'] === '' ? null : $out['seo_description'];
    if ($id === null) {
        $out['inventory'] = v_int(($d['inventory'] ?? '') === '' ? 0 : $d['inventory'], 0, 1000000);
        if ($out['inventory'] === null) {
            $errors['inventory'] = 'Opening stock must be a whole number (0 or more).';
        }
    }
    $errors += $extraErrors;
    if ($errors) {
        validation_problem($errors);
    }
    return $out;
}

/** Ordered [{key, value}] rows of a product's specifications, for the editor. */
function product_attribute_rows(PDO $conn, int $id): array
{
    $rows = [];
    foreach (product_attributes($conn, $id) as $k => $v) {
        $rows[] = ['key' => $k, 'value' => $v];
    }
    return $rows;
}

/** Sales and rating figures for the product page's Insights card. */
function product_insights(PDO $conn, int $id): array
{
    $st = $conn->prepare("SELECT COALESCE(SUM(i.quantity), 0) AS units, COALESCE(SUM(i.subtotal), 0) AS revenue, COUNT(DISTINCT i.order_id) AS orders,
            COALESCE(SUM(CASE WHEN o.order_date >= NOW() - INTERVAL 30 DAY THEN i.quantity ELSE 0 END), 0) AS units_30d,
            MAX(o.order_date) AS last_sold_at
        FROM order_items i JOIN orders o ON o.id = i.order_id
        WHERE i.product_id = ? AND o.status <> 'Cancelled'");
    $st->execute([$id]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    $st = $conn->prepare("SELECT COUNT(*) AS n, AVG(rating) AS avg FROM reviews WHERE product_id = ? AND status = 'Approved'");
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return [
        'units' => (int) $s['units'],
        'revenue' => (float) $s['revenue'],
        'orders' => (int) $s['orders'],
        'units_30d' => (int) $s['units_30d'],
        'last_sold_at' => $s['last_sold_at'],
        'reviews' => (int) $r['n'],
        'avg_rating' => $r['avg'] !== null ? round((float) $r['avg'], 1) : null,
    ];
}

/**
 * seller_id to store for a product: a seller's own id (always); for owner/manager (sellers.manage) the
 * requested seller when one is sent; null = leave as is (create: the house, TechZone).
 */
function product_seller_choice(PDO $conn, array $d): ?int
{
    $sid = current_seller_id();
    if ($sid !== null) {
        return $sid;
    }
    if (!array_key_exists('seller_id', $d) || $d['seller_id'] === '' || $d['seller_id'] === null || !can('sellers.manage')) {
        return null;
    }
    $want = v_id($d['seller_id']);
    $st = $conn->prepare('SELECT id FROM sellers WHERE id = ?');
    $st->execute([$want]);
    if ($want === 0 || !$st->fetchColumn()) {
        validation_problem(['seller_id' => 'Choose a seller from the list.']);
    }
    return $want;
}

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
$uploadDir = __DIR__ . '/../uploads';
$sellerScope = seller_scope_products_sql('p');

try {
    // GET - all products or one product
    if ($method === 'GET') {
        // Specifications card: the field list for a category (the page refetches it when the category changes)
        if (isset($_GET['attribute_form'])) {
            $cat = ($_GET['category_id'] ?? '') === '' ? null : v_id($_GET['category_id']);
            json_response(['success' => true, 'form' => attribute_form($conn, $cat ?: null)]);
        }
        if (isset($_GET['id'])) {
            assert_product_owned(v_id($_GET['id']), 404);
            $stmt = $conn->prepare("SELECT p.*, c.name AS category_name, sl.name AS seller_name FROM products p LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN sellers sl ON sl.id = p.seller_id WHERE p.id = ? AND $sellerScope");
            $stmt->execute([v_id($_GET['id'])]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                json_response(['success' => false, 'message' => 'Product not found'], 404);
            }
            $product['imagelink'] = product_imagelink_for_client($product['imagelink'] ?? '');
            $product['insights'] = product_insights($conn, (int) $product['id']);
            $product['attributes'] = product_attribute_rows($conn, (int) $product['id']);
            $product['attribute_form'] = attribute_form($conn, $product['category_id'] !== null ? (int) $product['category_id'] : null);
            json_response(['success' => true, 'product' => $product]);
        }

        $stmt = $conn->query("SELECT p.*, c.name AS category_name, sl.name AS seller_name FROM products p LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN sellers sl ON sl.id = p.seller_id WHERE $sellerScope ORDER BY p.id DESC");
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


    // ---- product page (manage/product.php): JSON create / update, image-only upload ----
    $isJsonBody = stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json') !== false;
    if (($method === 'PUT' || $method === 'POST') && $isJsonBody) {
        enforce_csrf_json();
        $data = request_json();
        $id = $method === 'PUT' ? v_id($data['id'] ?? 0) : null;
        if ($method === 'PUT' && $id === 0) {
            json_error('Product ID is required for update', 422);
        }
        $existing = null;
        if ($id !== null) {
            assert_product_owned($id);
            $st = $conn->prepare('SELECT ' . implode(', ', PRODUCT_EDITOR_FIELDS) . ', seller_id FROM products WHERE id = ?');
            $st->execute([$id]);
            $existing = $st->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                json_error('Product not found', 404);
            }
        }
        // Specifications ride along with the save when the page sends them (older clients do not)
        $attrs = null;
        $attrErrors = [];
        if (array_key_exists('attributes', $data)) {
            $catRaw = $data['category_id'] ?? '';
            $catId = ($catRaw === '' || $catRaw === null) ? null : v_id($catRaw);
            [$attrs, $attrErrors] = attribute_validate(attribute_form($conn, $catId ?: null), $data['attributes'],
                ($data['status'] ?? '') === 'active');
        }
        $in = product_editor_input($conn, $data, $id, $attrErrors);
        $sellerChoice = product_seller_choice($conn, $data);
        if ($id === null) {
            $in['seller_id'] = $sellerChoice ?? HOUSE_SELLER;
        } elseif ($sellerChoice !== null && current_seller_id() === null) {
            $in['seller_id'] = $sellerChoice;   // owner/manager moved the product to another seller
        }
        $isActive = $in['status'] === 'active' ? 1 : 0;
        $conn->beginTransaction();
        try {
            if ($id === null) {
                $opening = $in['inventory'];
                unset($in['inventory']);
                $cols = array_keys($in);
                $st = $conn->prepare('INSERT INTO products (' . implode(', ', $cols) . ', is_active, inventory, sold, imagelink) VALUES ('
                    . implode(', ', array_fill(0, count($cols), '?')) . ', ?, 0, 0, NULL)');
                $st->execute([...array_values($in), $isActive]);
                $id = (int) $conn->lastInsertId();
                if ($in['sku'] === null) {
                    $in['sku'] = sprintf('TZ-%04d', $id);
                    $conn->prepare('UPDATE products SET sku = ? WHERE id = ? AND sku IS NULL')->execute([$in['sku'], $id]);
                }
                if ($opening > 0) {
                    adjust_inventory($conn, $id, null, $opening, 'received', 'Opening stock', (int) $_SESSION['admin_id']);
                }
                record_price_point($conn, $id, (float) $in['price']);
                if ($attrs) {
                    attribute_save($conn, $id, $attrs);
                }
                audit('product.create', 'product', $id, "Created {$in['sku']} {$in['name']}" . ($in['status'] !== 'active' ? " as {$in['status']}" : ''),
                    null, ['price' => $in['price'], 'status' => $in['status'], 'inventory' => $opening, 'seller_id' => $in['seller_id']]
                        + ($attrs ? ['specifications' => count($attrs)] : []));
                $conn->commit();
                json_response(['success' => true, 'message' => 'Product created', 'id' => $id, 'product_id' => $id]);
            }
            if ($in['sku'] === null) {
                $in['sku'] = $existing['sku'] ?: sprintf('TZ-%04d', $id);
            }
            $sets = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($in)));
            $conn->prepare("UPDATE products SET $sets, is_active = ? WHERE id = ?")->execute([...array_values($in), $isActive, $id]);
            record_price_point($conn, $id, (float) $in['price']);   // no-op when the price is unchanged
            $norm = static fn($v) => $v === null ? null : (is_float($v) ? number_format($v, 2, '.', '') : (string) $v);
            if (!isset($in['seller_id'])) {
                unset($existing['seller_id']);
            }
            [$before, $after] = audit_diff($existing, array_map($norm, $in));
            foreach (['description', 'seo_description'] as $long) {
                if (array_key_exists($long, $after)) {
                    $before[$long] = '…';
                    $after[$long] = '…';
                }
            }
            if ($attrs !== null) {
                // one audit entry for the whole save: spec changes appear as "spec.socket" etc.
                [$ab, $aa] = attribute_save($conn, $id, $attrs);
                foreach ($aa as $k => $v) {
                    $before["spec.$k"] = $ab[$k];
                    $after["spec.$k"] = $v;
                }
            }
            if ($after) {
                $verb = isset($after['status']) && count($after) === 1 ? ['active' => 'Activated', 'draft' => 'Moved to draft', 'archived' => 'Archived'][$in['status']] : 'Updated';
                audit(isset($after['status']) && count($after) === 1 ? 'product.status' : 'product.update', 'product', $id,
                    "$verb {$in['sku']} {$in['name']}" . ($verb === 'Updated' ? ' (' . implode(', ', array_unique(array_map(
                        static fn($k) => str_starts_with($k, 'spec.') ? 'specifications' : $k, array_keys($after)))) . ')' : ''), $before, $after);
            }
            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            if ($e instanceof PDOException && $e->getCode() === '23000') {
                validation_problem(['sku' => 'That SKU is already used by another product.']);
            }
            throw $e;
        }
        json_response(['success' => true, 'message' => $after ? 'Product saved' : 'No changes to save', 'id' => $id, 'changed' => array_keys($after)]);
    }

    // POST action=image: replace the product's one image (manage/product.php media card)
    if ($method === 'POST' && ($_POST['action'] ?? '') === 'image') {
        enforce_csrf_json();
        $id = v_id($_POST['id'] ?? 0);
        assert_product_owned($id);
        $st = $conn->prepare('SELECT imagelink, name, sku FROM products WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            json_error('Product not found', 404);
        }
        try {
            $imagelink = 'uploads/' . store_uploaded_image('image', $uploadDir);
        } catch (UploadException $e) {
            validation_problem(['image' => $e->getMessage()]);
        }
        $conn->prepare('UPDATE products SET imagelink = ? WHERE id = ?')->execute([$imagelink, $id]);
        audit('product.update', 'product', $id, 'Replaced the image of ' . trim(($row['sku'] ?? '') . ' ' . $row['name']), ['imagelink' => $row['imagelink']], ['imagelink' => $imagelink]);
        $old = (string) ($row['imagelink'] ?? '');
        if ($old !== '' && strpos($old, 'uploads/') === 0) {
            delete_upload_if_unused($conn, $old, $uploadDir);
        }
        json_response(['success' => true, 'message' => 'Image replaced', 'imagelink' => product_imagelink_for_client($imagelink)]);
    }

    // UPDATE - POST with _method=PUT (multipart, so image uploads work)
    // Stock is never written directly: a changed inventory becomes a 'correction' in the ledger.
    if ($isUpdate) {
        enforce_csrf_json();
        $id = v_id($_POST['id'] ?? 0);
        if ($id === 0) {
            json_error('Product ID is required for update', 422);
        }
        assert_product_owned($id);
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
            record_price_point($conn, $id, (float) $price);
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
            $stmt = $conn->prepare("INSERT INTO products (name, description, price, category_id, imagelink, inventory, sold, is_active, status, low_stock_threshold, seller_id) VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $categoryId, $imagelink, $sold, $isActive, $isActive === 1 ? 'active' : 'draft', (int) setting('low_stock_threshold', 5),
                product_seller_choice($conn, $_POST) ?? HOUSE_SELLER]);
            $productId = (int) $conn->lastInsertId();
            $sku = sprintf('TZ-%04d', $productId);
            $conn->prepare("UPDATE products SET sku = ? WHERE id = ? AND sku IS NULL")->execute([$sku, $productId]);
            if ($inventory > 0) {
                adjust_inventory($conn, $productId, null, $inventory, 'received', 'Opening stock', (int) $_SESSION['admin_id']);
            }
            record_price_point($conn, $productId, (float) $price);
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
        assert_product_owned($id);
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
