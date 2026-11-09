<?php
/**
 * Sellers (admin): applications queue, sellers grid, seller detail, payouts. core/marketplace.php.
 *
 *   GET                         {applications, sellers, counts{pending,approved,rejected}, payable_total}   sellers.view
 *   GET ?application=N          one application (all fields, reviewer)                                     sellers.view
 *   GET ?id=N                   seller detail: profile, accounts, products, orders, earnings, statements   sellers.view
 *   POST {action:"approve", application_id, commission_pct, name?, market?, slug?}
 *                               creates the sellers row + a seller account (role seller) with a temporary
 *                               password returned ONCE (never stored in clear)                            sellers.manage
 *   POST {action:"reject", application_id, reason}                                                         sellers.manage
 *   PUT  {id, name, market, address, city, phone, whatsapp, about, commission_pct, status}                 sellers.manage
 *   POST {action:"mark_paid", seller_id, week_start, reference}                                           sellers.payouts
 *
 * Seller accounts never reach this file (seller_gate denies it). Every write is audited.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/marketplace.php';

checkLogin(true);
json_headers();
require_can('sellers.view');

$method = $_SERVER['REQUEST_METHOD'];
$me = (int) $_SESSION['admin_id'];

/** Same generator as Settings → Staff: 12 characters, no look-alikes, grouped 4-4-4. */
function seller_temp_password(): string
{
    $alpha = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $p = '';
        for ($i = 0; $i < 12; $i++) {
            $p .= $alpha[random_int(0, strlen($alpha) - 1)];
        }
    } while (!preg_match('/[a-z]/', $p) || !preg_match('/[A-Z]/', $p) || !preg_match('/[2-9]/', $p));
    return substr($p, 0, 4) . '-' . substr($p, 4, 4) . '-' . substr($p, 8);
}

/** Unique admins.username from the e-mail's local part. */
function seller_username(PDO $conn, string $email): string
{
    $base = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', strstr($email, '@', true) ?: 'seller'));
    $base = trim(substr($base, 0, 40), '_');
    if (strlen($base) < 3) {
        $base = 'seller_' . $base;
    }
    $name = $base;
    $st = $conn->prepare('SELECT 1 FROM admins WHERE username = ?');
    for ($i = 2; $i < 200; $i++) {
        $st->execute([$name]);
        if (!$st->fetchColumn()) {
            return $name;
        }
        $name = $base . $i;
    }
    return $base . bin2hex(random_bytes(3));
}

function seller_application(PDO $conn, int $id, bool $lock = false): ?array
{
    $st = $conn->prepare('SELECT a.*, r.name AS reviewed_by_name, s.name AS seller_name, s.slug AS seller_slug
        FROM seller_applications a LEFT JOIN admins r ON r.id = a.reviewed_by LEFT JOIN sellers s ON s.id = a.seller_id
        WHERE a.id = ?' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function seller_applications(PDO $conn): array
{
    return $conn->query("SELECT a.id, a.shop_name, a.owner_name, a.phone, a.email, a.market, a.city, a.categories, a.ntn, a.message,
            a.status, a.review_note, a.seller_id, a.reviewed_at, a.created_at, r.name AS reviewed_by_name
        FROM seller_applications a LEFT JOIN admins r ON r.id = a.reviewed_by
        ORDER BY FIELD(a.status, 'pending', 'approved', 'rejected'), a.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['application'])) {
            $app = seller_application($conn, v_id($_GET['application']));
            if (!$app) {
                json_error('Application not found', 404);
            }
            json_response(['success' => true, 'application' => $app]);
        }

        if (isset($_GET['id'])) {
            $id = v_id($_GET['id']);
            $seller = $id > 0 && $id !== HOUSE_SELLER_ID ? marketplace_seller($conn, $id) : null;
            if (!$seller) {
                json_error('Seller not found', 404);
            }
            $st = $conn->prepare("SELECT id, name, email, is_active, last_login_at, created_at FROM admins WHERE seller_id = ? AND role = 'seller' ORDER BY id");
            $st->execute([$id]);
            $accounts = $st->fetchAll(PDO::FETCH_ASSOC);
            $st = $conn->prepare("SELECT p.id, p.name, p.sku, p.price, p.inventory, p.low_stock_threshold, p.status, p.sold, p.imagelink, p.updated_at, c.name AS category_name
                FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.seller_id = ? ORDER BY p.status = 'archived', p.name");
            $st->execute([$id]);
            $products = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as &$p) {
                $link = (string) ($p['imagelink'] ?? '');
                $p['imagelink'] = $link !== '' && strpos($link, 'uploads/') === 0 ? admin_url($link) : $link;
            }
            unset($p);
            $statements = seller_payout_statements($conn, $id);
            $today = marketplace_today($conn);
            $st = $conn->prepare('SELECT id, shop_name, owner_name, email, created_at, reviewed_at FROM seller_applications WHERE seller_id = ? ORDER BY id LIMIT 1');
            $st->execute([$id]);
            json_response([
                'success' => true,
                'seller' => $seller,
                'accounts' => $accounts,
                'application' => $st->fetch(PDO::FETCH_ASSOC) ?: null,
                'products' => $products,
                'orders' => seller_orders_list($conn, $id, null, '', 50),
                'earnings' => [
                    'd30' => seller_sales_since($conn, $id, date('Y-m-d', strtotime($today . ' -29 day'))),
                    'lifetime' => seller_sales_since($conn, $id, '2000-01-01'),
                    'summary' => seller_payout_summary($conn, $id, $statements),
                ],
                'statements' => $statements,
                'can' => ['manage' => can('sellers.manage'), 'payouts' => can('sellers.payouts')],
            ]);
        }

        $apps = seller_applications($conn);
        $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        foreach ($apps as $a) {
            $counts[$a['status']]++;
        }
        $sellers = marketplace_sellers_overview($conn);
        json_response([
            'success' => true,
            'applications' => $apps,
            'sellers' => $sellers,
            'counts' => $counts,
            'payable_total' => round(array_sum(array_column($sellers, 'payable')), 2),
            'accruing_total' => round(array_sum(array_column($sellers, 'accruing')), 2),
            'can' => ['manage' => can('sellers.manage'), 'payouts' => can('sellers.payouts')],
        ]);
    }

    enforce_csrf_json();
    $data = request_json();
    $action = (string) ($data['action'] ?? '');

    // ---- approve an application: sellers row + seller account, temp password shown once ----
    if ($method === 'POST' && $action === 'approve') {
        require_can('sellers.manage');
        $appId = v_id($data['application_id'] ?? 0);
        $errors = [];
        [$vals, $errors] = marketplace_profile_input($data + ['commission_pct' => 8], ['commission_pct']);
        $name = v_str($data['name'] ?? '', 120);
        $market = v_str($data['market'] ?? '', 80);
        $slugIn = v_str($data['slug'] ?? '', 80);
        if ($slugIn === null || ($slugIn !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slugIn))) {
            $errors['slug'] = 'Use lower-case letters, numbers and dashes (e.g. pc-zone-saddar).';
        }
        if ($name === null) {
            $errors['name'] = 'Keep the shop name under 120 characters.';
        }
        if ($market === null) {
            $errors['market'] = 'Keep the market under 80 characters.';
        }
        $conn->beginTransaction();
        $app = $appId > 0 ? seller_application($conn, $appId, true) : null;
        if (!$app) {
            $conn->rollBack();
            json_error('Application not found', 404);
        }
        if ($app['status'] !== 'pending') {
            $conn->rollBack();
            json_error('This application was already ' . $app['status'] . '.', 409);
        }
        $email = v_email($app['email']);
        $st = $conn->prepare('SELECT 1 FROM admins WHERE email = ?');
        $st->execute([$email]);
        if ($email === null) {
            $errors['email'] = 'The application has no valid e-mail address for the seller account.';
        } elseif ($st->fetchColumn()) {
            $errors['email'] = "$email already has a TechZone account. Ask the seller for another e-mail.";
        }
        if ($slugIn !== null && $slugIn !== '') {
            $st = $conn->prepare('SELECT 1 FROM sellers WHERE slug = ?');
            $st->execute([$slugIn]);
            if ($st->fetchColumn()) {
                $errors['slug'] = "The address /$slugIn is taken by another seller.";
            }
        }
        if ($errors) {
            $conn->rollBack();
            validation_problem($errors);
        }
        $shop = $name !== null && $name !== '' ? $name : $app['shop_name'];
        $slug = $slugIn !== null && $slugIn !== '' ? $slugIn : marketplace_unique_slug($conn, $shop);
        $market = $market !== null && $market !== '' ? $market : $app['market'];
        $pct = (float) $vals['commission_pct'];
        $conn->prepare("INSERT INTO sellers (slug, name, market, city, phone, whatsapp, rating, about, joined_at, status, commission_pct)
            VALUES (?, ?, ?, ?, ?, ?, 0.0, NULL, CURDATE(), 'active', ?)")
            ->execute([$slug, $shop, $market, $app['city'] ?: 'Lahore', $app['phone'], marketplace_whatsapp_digits($app['phone']), $pct]);
        $sellerId = (int) $conn->lastInsertId();
        $temp = seller_temp_password();
        $conn->prepare("INSERT INTO admins (name, username, email, phone, password, role, is_active, seller_id) VALUES (?, ?, ?, ?, ?, 'seller', 1, ?)")
            ->execute([$app['owner_name'], seller_username($conn, $email), $email, $app['phone'], password_hash($temp, PASSWORD_DEFAULT), $sellerId]);
        $accountId = (int) $conn->lastInsertId();
        $conn->prepare("UPDATE seller_applications SET status = 'approved', seller_id = ?, reviewed_by = ?, reviewed_at = NOW(), review_note = NULL WHERE id = ?")
            ->execute([$sellerId, $me, $appId]);
        audit('seller.approve', 'seller_application', $appId, "Approved {$shop}'s seller application ({$pct}% commission)",
            ['status' => 'pending'], ['status' => 'approved', 'seller_id' => $sellerId, 'commission_pct' => $pct, 'slug' => $slug]);
        audit('staff.create', 'admin', $accountId, 'Created the seller account for ' . $app['owner_name'] . ' (' . $shop . ')', null,
            ['email' => $email, 'role' => 'seller', 'seller_id' => $sellerId]);
        $conn->commit();
        json_response([
            'success' => true,
            'message' => 'Application approved',
            'seller_id' => $sellerId,
            'seller' => ['id' => $sellerId, 'name' => $shop, 'slug' => $slug],
            'email' => $email,
            'temp_password' => $temp,
            'login_url' => absolute_url(admin_url('index.php')),
        ]);
    }

    // ---- reject with a reason ----
    if ($method === 'POST' && $action === 'reject') {
        require_can('sellers.manage');
        $appId = v_id($data['application_id'] ?? 0);
        $reason = v_str($data['reason'] ?? '', 255, false);
        if ($reason === null) {
            validation_problem(['reason' => 'Give the reason (up to 255 characters). It stays on the application.']);
        }
        $conn->beginTransaction();
        $app = $appId > 0 ? seller_application($conn, $appId, true) : null;
        if (!$app) {
            $conn->rollBack();
            json_error('Application not found', 404);
        }
        if ($app['status'] !== 'pending') {
            $conn->rollBack();
            json_error('This application was already ' . $app['status'] . '.', 409);
        }
        $conn->prepare("UPDATE seller_applications SET status = 'rejected', review_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute([$reason, $me, $appId]);
        audit('seller.reject', 'seller_application', $appId, "Rejected {$app['shop_name']}'s seller application", ['status' => 'pending'], ['status' => 'rejected', 'reason' => $reason]);
        $conn->commit();
        json_response(['success' => true, 'message' => 'Application rejected']);
    }

    // ---- mark a week paid (owner) ----
    if ($method === 'POST' && $action === 'mark_paid') {
        require_can('sellers.payouts');
        $sid = v_id($data['seller_id'] ?? 0);
        $week = (string) (v_str($data['week_start'] ?? '', 10) ?? '');
        $ref = v_str($data['reference'] ?? '', 80);
        if ($ref === null || $ref === '') {
            validation_problem(['reference' => 'Enter the bank or transfer reference (up to 80 characters).']);
        }
        try {
            $row = seller_mark_paid($conn, $sid, $week, $ref, $me);
        } catch (OrderActionException $e) {
            json_error($e->getMessage(), 409);
        }
        json_response(['success' => true, 'message' => 'Payout marked paid', 'statement' => $row]);
    }

    // ---- edit a seller's profile, commission and status ----
    if ($method === 'PUT') {
        require_can('sellers.manage');
        $id = v_id($data['id'] ?? 0);
        $seller = $id > 0 && $id !== HOUSE_SELLER_ID ? marketplace_seller($conn, $id) : null;
        if (!$seller) {
            json_error('Seller not found', 404);
        }
        $fields = ['name', 'market', 'address', 'city', 'phone', 'whatsapp', 'about', 'commission_pct', 'status'];
        [$vals, $errors] = marketplace_profile_input($data, array_values(array_filter($fields, static fn($f) => array_key_exists($f, $data))));
        if ($errors) {
            validation_problem($errors);
        }
        if (!$vals) {
            json_response(['success' => true, 'message' => 'No changes to save', 'changed' => []]);
        }
        $norm = static fn($v) => $v === null ? null : (is_float($v) ? number_format($v, 2, '.', '') : (string) $v);
        [$before, $after] = audit_diff($seller, array_map($norm, $vals));
        if (!$after) {
            json_response(['success' => true, 'message' => 'No changes to save', 'changed' => []]);
        }
        $sets = implode(', ', array_map(static fn($c) => "$c = ?", array_keys($after)));
        $conn->beginTransaction();
        $conn->prepare("UPDATE sellers SET $sets WHERE id = ?")->execute([...array_map(static fn($k) => $vals[$k], array_keys($after)), $id]);
        $verb = isset($after['status']) && count($after) === 1
            ? ($after['status'] === 'suspended' ? 'Suspended ' : ($after['status'] === 'active' ? 'Reactivated ' : 'Paused '))
            : 'Updated ';
        audit(isset($after['status']) ? 'seller.status' : 'seller.update', 'seller', $id,
            $verb . $seller['name'] . ($verb === 'Updated ' ? ' (' . implode(', ', array_keys($after)) . ')' : ''), $before, $after);
        $conn->commit();
        json_response(['success' => true, 'message' => 'Seller saved', 'changed' => array_keys($after), 'seller' => marketplace_seller($conn, $id)]);
    }

    json_error('Invalid request', 405);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Database error. Please try again.'), 500);
}
