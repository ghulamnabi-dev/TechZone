<?php
/**
 * Discounts (promotions table; engine in core/discounts.php).
 *
 *   GET                       every discount + computed state (live/scheduled/ended/inactive) and target name
 *   GET ?id=N                 one discount + usage: orders that used its code, revenue, discount given
 *   GET ?check_code=X&id=N    {available} – live uniqueness check for the builder
 *   POST (multipart)          create, or update when id is set; optional image (homepage offer card)
 *   PUT  (JSON)               update without image
 *   DELETE {id} | ?id=        delete
 * Writes need discounts.write. Validation problems come back as 422 {errors:{field:message}}.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/uploads.php';
require_once __DIR__ . '/../../core/audit.php';

checkLogin(true);
json_headers();
// Any write needs discounts.write (core/permissions.php); reads are open to every role.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_can('discounts.write');
}

$method = $_SERVER['REQUEST_METHOD'];
$uploadDir = __DIR__ . '/../uploads';

/** Columns the audit trail compares. */
const PROMO_AUDIT_FIELDS = ['title', 'promo_code', 'kind', 'discount_percentage', 'discount_amount', 'applies_to', 'target_id',
    'min_order', 'usage_limit', 'starts_at', 'ends_at', 'is_active', 'link_url', 'display_order'];

/** "2026-12-08T23:59" / "2026-12-08 23:59:00" / "2026-12-08" -> "Y-m-d H:i:s"; '' -> null; invalid -> false */
function promo_datetime(mixed $v, bool $endOfDay = false): string|false|null
{
    $v = v_str($v, 25);
    if ($v === null) {
        return false;
    }
    if ($v === '') {
        return null;
    }
    foreach (['!Y-m-d\TH:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i:s', '!Y-m-d H:i', '!Y-m-d'] as $f) {
        $d = DateTime::createFromFormat($f, $v);
        if ($d && $d->format(substr($f, 1)) === $v) {
            if ($f === '!Y-m-d' && $endOfDay) {
                $d->setTime(23, 59, 59);
            }
            return $d->format('Y-m-d H:i:s');
        }
    }
    return false;
}

/**
 * Validate a discount from the builder (or the legacy offer form). Collects every error.
 *
 * @return array column => value for promotions
 */
function promotion_input(PDO $conn, array $src, int $id): array
{
    $e = [];
    $legacy = !isset($src['kind']);
    $code = strtoupper((string) v_str($src['promo_code'] ?? '', 50));
    if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,29}$/', $code)) {
        $e['promo_code'] = 'Use 3–30 letters, numbers, dashes or underscores, e.g. LAHORE5.';
    } else {
        $st = $conn->prepare('SELECT COUNT(*) FROM promotions WHERE UPPER(promo_code) = ? AND id <> ?');
        $st->execute([$code, $id]);
        if ((int) $st->fetchColumn() > 0) {
            $e['promo_code'] = "$code is already used by another discount.";
        }
    }

    if ($legacy) {
        $pct = v_num(($src['discount_percentage'] ?? '') === '' ? 0 : $src['discount_percentage'], 0, 100) ?? 0;
        $amt = v_num(($src['discount_amount'] ?? '') === '' ? 0 : $src['discount_amount'], 0, 99999999) ?? 0;
        $kind = $pct > 0 ? 'percent' : ($amt > 0 ? 'fixed' : 'free_shipping');
        $value = $kind === 'percent' ? $pct : $amt;
        $src += ['starts_at' => $src['start_date'] ?? '', 'ends_at' => $src['end_date'] ?? '', 'applies_to' => 'all'];
    } else {
        $kind = v_enum($src['kind'] ?? '', ['percent', 'fixed', 'free_shipping']);
        $value = v_num(($src['value'] ?? '') === '' ? 0 : $src['value'], 0, 99999999);
    }
    if ($kind === null) {
        $e['kind'] = 'Choose percentage, fixed amount or free shipping.';
    }

    $min = v_num(($src['min_order'] ?? '') === '' ? 0 : $src['min_order'], 0, 999999999);
    if ($min === null) {
        $e['min_order'] = 'Enter an amount in rupees, or 0 for no minimum.';
    }
    if ($kind === 'percent' && ($value === null || $value < 1 || $value > 90)) {
        $e['value'] = 'A percentage discount is between 1% and 90%.';
    } elseif ($kind === 'fixed') {
        if ($value === null || $value < 1) {
            $e['value'] = 'Enter the amount off in rupees.';
        } elseif ($min !== null && $value > $min) {
            $e['value'] = 'A fixed discount can’t be more than the minimum order. Raise the minimum to at least Rs ' . number_format($value, 0) . '.';
        }
    }

    $applies = v_enum($src['applies_to'] ?? 'all', ['all', 'category', 'product']) ?? 'all';
    $target = null;
    if ($applies !== 'all') {
        $target = v_id($src['target_id'] ?? 0);
        $table = $applies === 'category' ? 'categories' : 'products';
        $ok = false;
        if ($target > 0) {
            $st = $conn->prepare("SELECT COUNT(*) FROM $table WHERE id = ?");
            $st->execute([$target]);
            $ok = (int) $st->fetchColumn() > 0;
        }
        if (!$ok) {
            $e['target_id'] = $applies === 'category' ? 'Choose a category.' : 'Search for a product and pick it.';
        }
    }
    if ($kind === 'free_shipping') {
        $applies = 'all';   // shipping is per order, not per line
        $target = null;
    }

    $limitRaw = $src['usage_limit'] ?? '';
    $limit = ($limitRaw === '' || $limitRaw === null) ? null : v_int($limitRaw, 1, 10000000);
    if ($limit === null && $limitRaw !== '' && $limitRaw !== null) {
        $e['usage_limit'] = 'Use a whole number (1 or more), or leave empty for unlimited.';
    } elseif ($limit !== null && $id > 0) {
        $st = $conn->prepare('SELECT used_count FROM promotions WHERE id = ?');
        $st->execute([$id]);
        $used = (int) $st->fetchColumn();
        if ($limit < $used) {
            $e['usage_limit'] = "It has already been used $used times; the limit can’t be lower.";
        }
    }

    $starts = promo_datetime($src['starts_at'] ?? '');
    $noEnd = !empty($src['no_end']) && $src['no_end'] !== '0' && $src['no_end'] !== 'false';
    $ends = $noEnd ? null : promo_datetime($src['ends_at'] ?? '', true);
    if ($starts === false) {
        $e['starts_at'] = 'Pick a start date and time.';
    }
    if ($ends === false) {
        $e['ends_at'] = 'Pick an end date and time, or tick “No end date”.';
    } elseif ($ends !== null && $starts && $ends <= $starts) {
        $e['ends_at'] = 'The end must be after the start.';
    }

    $title = v_str($src['title'] ?? '', 200);
    $description = v_str($src['description'] ?? '', 5000);
    $link = v_link($src['link_url'] ?? '');
    $order = v_int(($src['display_order'] ?? '') === '' ? 0 : $src['display_order'], -100000, 100000);
    if ($title === null) {
        $e['title'] = 'Keep the offer title under 200 characters.';
    }
    if ($description === null) {
        $e['description'] = 'Description is too long.';
    }
    if ($link === null) {
        $e['link_url'] = 'Use a path like /MarketingSite/shop/products.php or an http(s) URL.';
    }
    if ($order === null) {
        $e['display_order'] = 'Use a whole number.';
    }
    if ($e) {
        validation_problem($e);
    }

    return [
        'title' => $title !== '' ? $title : $code,
        'description' => $description,
        'promo_code' => $code,
        'kind' => $kind,
        'discount_percentage' => $kind === 'percent' ? round($value, 2) : 0,
        'discount_amount' => $kind === 'fixed' ? round($value, 2) : 0,
        'applies_to' => $applies,
        'target_id' => $target,
        'min_order' => round($min, 2),
        'usage_limit' => $limit,
        'starts_at' => $starts,
        'ends_at' => $ends,
        'start_date' => $starts ? substr($starts, 0, 10) : null,
        'end_date' => $ends ? substr($ends, 0, 10) : null,
        'is_active' => isset($src['is_active']) ? ((int) $src['is_active'] === 1 || $src['is_active'] === true || $src['is_active'] === 'true' ? 1 : 0) : 1,
        'link_url' => $link,
        'display_order' => $order,
    ];
}

/** SELECT list with the computed state, read by the database clock (same one evaluate_promo uses). */
const PROMO_SELECT = "SELECT p.*,
        CASE WHEN p.applies_to = 'category' THEN (SELECT c.name FROM categories c WHERE c.id = p.target_id)
             WHEN p.applies_to = 'product' THEN (SELECT pr.name FROM products pr WHERE pr.id = p.target_id) END AS target_name,
        CASE WHEN p.is_active = 0 THEN 'inactive'
             WHEN COALESCE(p.starts_at, TIMESTAMP(p.start_date)) > NOW() THEN 'scheduled'
             WHEN COALESCE(p.ends_at, TIMESTAMP(p.end_date, '23:59:59')) < NOW() THEN 'ended'
             WHEN p.usage_limit IS NOT NULL AND p.used_count >= p.usage_limit THEN 'ended'
             ELSE 'live' END AS state
    FROM promotions p";

function promo_audit_view(array $row): array
{
    $out = [];
    foreach (PROMO_AUDIT_FIELDS as $f) {
        $v = $row[$f] ?? null;
        $out[$f] = $v === null ? null : (is_float($v) ? number_format($v, 2, '.', '') : (string) $v);
    }
    return $out;
}

try {
    if ($method === 'GET') {
        if (isset($_GET['check_code'])) {
            $code = strtoupper((string) v_str($_GET['check_code'], 50));
            $st = $conn->prepare('SELECT COUNT(*) FROM promotions WHERE UPPER(promo_code) = ? AND id <> ?');
            $st->execute([$code, v_id($_GET['id'] ?? 0)]);
            json_response(['success' => true, 'code' => $code, 'available' => (int) $st->fetchColumn() === 0]);
        }
        if (isset($_GET['id'])) {
            $st = $conn->prepare(PROMO_SELECT . ' WHERE p.id = ?');
            $st->execute([v_id($_GET['id'])]);
            $promo = $st->fetch(PDO::FETCH_ASSOC);
            if (!$promo) {
                json_error('Discount not found', 404);
            }
            $usage = ['orders' => [], 'count' => 0, 'revenue' => 0.0, 'discount' => 0.0];
            if ($promo['promo_code'] !== null && $promo['promo_code'] !== '') {
                $st = $conn->prepare("SELECT id, order_date, customer_id, customer_name, status, total, discount_amount
                    FROM orders WHERE UPPER(promo_code) = ? ORDER BY order_date DESC LIMIT 100");
                $st->execute([strtoupper($promo['promo_code'])]);
                $usage['orders'] = $st->fetchAll(PDO::FETCH_ASSOC);
                foreach ($usage['orders'] as $o) {
                    if ($o['status'] !== 'Cancelled') {
                        $usage['count']++;
                        $usage['revenue'] += (float) $o['total'];
                        $usage['discount'] += (float) $o['discount_amount'];
                    }
                }
            }
            $promo['usage'] = $usage;
            json_response(['success' => true, 'promotion' => $promo]);
        }
        $stmt = $conn->query(PROMO_SELECT . ' ORDER BY p.display_order ASC, p.created_at DESC');
        json_response(['success' => true, 'promotions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // POST (multipart, optional image) = create or update by id; PUT (JSON) = update
    if ($method === 'POST' || $method === 'PUT') {
        enforce_csrf_json();
        $src = $method === 'PUT' ? request_json() : (request_json() ?: $_POST);
        $id = v_id($src['id'] ?? 0);
        if ($method === 'PUT' && $id === 0) {
            json_error('Invalid ID', 422);
        }
        $old = null;
        if ($id > 0) {
            $st = $conn->prepare('SELECT * FROM promotions WHERE id = ?');
            $st->execute([$id]);
            $old = $st->fetch(PDO::FETCH_ASSOC);
            if (!$old) {
                json_error('Discount not found', 404);
            }
        }
        $row = promotion_input($conn, $src, $id);

        $oldImage = $old['image_url'] ?? null;
        $row['image_url'] = $oldImage ?? '';
        if ($method === 'POST' && upload_present('image')) {
            try {
                $row['image_url'] = 'uploads/' . store_uploaded_image('image', $uploadDir);
            } catch (UploadException $ex) {
                validation_problem(['image' => $ex->getMessage()]);
            }
        }

        if ($id > 0) {
            $sets = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($row)));
            $conn->prepare("UPDATE promotions SET $sets WHERE id = ?")->execute([...array_values($row), $id]);
            [$before, $after] = audit_diff(promo_audit_view($old), promo_audit_view($row));
            if ($after || $row['image_url'] !== $oldImage) {
                audit('discount.update', 'promotion', $id, 'Updated discount ' . $row['promo_code'] . ($after ? ' (' . implode(', ', array_keys($after)) . ')' : ' (image)'), $before ?: null, $after ?: null);
            }
            if ($oldImage && $oldImage !== $row['image_url'] && strpos($oldImage, 'uploads/') === 0) {
                delete_upload_if_unused($conn, $oldImage, $uploadDir);
            }
            json_response(['success' => true, 'message' => 'Discount saved', 'id' => $id]);
        }

        $cols = array_keys($row);
        $conn->prepare('INSERT INTO promotions (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($row));
        $newId = (int) $conn->lastInsertId();
        audit('discount.create', 'promotion', $newId, 'Created discount ' . $row['promo_code'], null, promo_audit_view($row));
        json_response(['success' => true, 'message' => 'Discount created', 'id' => $newId]);
    }

    // DELETE - JSON body {id} (or ?id=)
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? ($_GET['id'] ?? 0));
        if ($id === 0) {
            json_error('Invalid ID', 422);
        }

        $stmt = $conn->prepare("SELECT image_url, promo_code, title FROM promotions WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            json_error('Discount not found', 404);
        }
        $conn->prepare("DELETE FROM promotions WHERE id = ?")->execute([$id]);
        audit('discount.delete', 'promotion', $id, 'Deleted discount ' . ($row['promo_code'] ?: $row['title']), ['promo_code' => $row['promo_code']], null);
        $image = $row['image_url'];
        if ($image && strpos($image, 'uploads/') === 0) {
            delete_upload_if_unused($conn, $image, $uploadDir);
        }
        json_response(['success' => true, 'message' => 'Discount deleted']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not save the discount.'), 500);
}
