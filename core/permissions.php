<?php
/**
 * Roles and capabilities (docs/ADMIN.md "Permissions").
 *
 * Four roles: owner, manager, staff and seller. Every capability maps to the roles that hold it in ONE
 * array (TZ_CAPABILITIES); pages hide what a role cannot do (window.TZ_CAPS) and the APIs enforce
 * it with require_can(). The signed-in admin is re-read from the database once per request, so a
 * role change or deactivation takes effect on the next click, not the next login.
 *
 * Seller role (docs/STOREFRONT.md "Marketplace"): a marketplace seller's account. It holds only the
 * order/catalogue capabilities below, and every one of them is SCOPED to that seller's products:
 *   - seller_gate() (called by checkLogin) allows a seller only the scripts in TZ_SELLER_ALLOWED
 *     (deny by default: customers, discounts, settings, staff, reviews, messages, analytics, sellers …);
 *   - current_seller_id() / seller_scope_products_sql() / seller_scope_orders_sql() narrow every query;
 *   - assert_product_owned() / assert_order_visible() stop a request for someone else's row.
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/http.php';

/** Capability => roles that hold it. The single source of truth for permissions. */
const TZ_CAPABILITIES = [
    // view everything (seller: only orders containing their products, only their lines)
    'orders.view'      => ['owner', 'manager', 'staff', 'seller'],
    'activity.view'    => ['owner', 'manager'],
    // day-to-day order work (seller: their own lines)
    'orders.fulfil'    => ['owner', 'manager', 'staff', 'seller'],
    'orders.payment'   => ['owner', 'manager', 'staff'],
    'orders.ship'      => ['owner', 'manager', 'staff', 'seller'],
    'orders.note'      => ['owner', 'manager', 'staff', 'seller'],
    'reviews.moderate' => ['owner', 'manager', 'staff'],
    'messages.write'   => ['owner', 'manager', 'staff'],
    // money and catalogue
    'orders.cancel'    => ['owner', 'manager'],
    'orders.refund'    => ['owner', 'manager'],
    'products.write'   => ['owner', 'manager', 'seller'],    // seller: own products only
    'inventory.adjust' => ['owner', 'manager', 'seller'],    // seller: own products only
    'discounts.write'  => ['owner', 'manager'],
    'customers.write'  => ['owner', 'manager'],
    // destructive
    'products.delete'  => ['owner'],
    'customers.delete' => ['owner'],
    // store configuration
    'settings.write'   => ['owner'],
    'shipping.write'   => ['owner'],
    'payments.write'   => ['owner'],
    'staff.manage'     => ['owner'],
    // marketplace
    'sellers.view'     => ['owner', 'manager'],
    'sellers.manage'   => ['owner', 'manager'],   // approve / reject applications, edit sellers, set a product's seller
    'sellers.payouts'  => ['owner'],              // mark a weekly payout paid
    'seller.portal'    => ['seller'],             // own store profile and payout statements
];

/**
 * Scripts a seller account may open (deny by default). "dir/file" => allowed HTTP methods (null = any;
 * the script then scopes every action itself). Everything else answers 403 for the seller role.
 */
const TZ_SELLER_ALLOWED = [
    'api/orders.php'        => null,          // scoped: only their orders / lines; fulfil, ship, note
    'api/products.php'      => null,          // scoped: own products; no delete
    'api/variations.php'    => null,          // scoped: options of own products
    'api/inventory.php'     => null,          // scoped: own products' stock and ledger
    'api/categories.php'    => ['GET'],       // read-only list for the product editor
    'api/search.php'        => ['GET'],       // scoped: own orders and products, no customers
    'api/notifications.php' => null,          // scoped: own orders to fulfil, own low stock
    'api/dashboard_stats.php' => ['GET'],     // seller home
    'api/seller_portal.php' => null,          // store profile + payout statements
    'api/profile.php'       => null,          // their own account (name, password)
    'api/logout.php'        => null,
    'manage/dashboard.php'  => ['GET'],
    'manage/orders.php'     => ['GET'],
    'manage/order.php'      => ['GET'],
    'manage/products.php'   => ['GET'],
    'manage/product.php'    => ['GET'],
    'manage/inventory.php'  => ['GET'],
    'manage/payouts.php'    => ['GET'],
    'manage/store.php'      => ['GET'],
    'manage/profile.php'    => ['GET'],
];

/** Display labels for the role badge. */
const TZ_ROLE_LABELS = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff', 'seller' => 'Seller'];

/**
 * The signed-in admin (id, name, username, email, role, is_active, last_login_at) or null.
 *
 * Read from the database once per request when a PDO connection ($conn) exists; falls back to
 * the session copy otherwise. A deactivated or deleted account is treated as signed out.
 *
 * @return array{id:int,name:string,username:string,email:string,role:string,is_active:int,last_login_at:?string,seller_id:?int,seller_name:?string,seller_status:?string}|null
 */
function current_admin(): ?array
{
    static $cache = [];
    $id = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
    if ($id <= 0) {
        return null;
    }
    if (array_key_exists($id, $cache)) {
        return $cache[$id];
    }

    $conn = $GLOBALS['conn'] ?? null;
    if ($conn instanceof PDO) {
        try {
            $st = $conn->prepare('SELECT a.id, a.name, a.username, a.email, a.role, a.is_active, a.last_login_at, a.seller_id,
                    s.name AS seller_name, s.status AS seller_status
                FROM admins a LEFT JOIN sellers s ON s.id = a.seller_id WHERE a.id = ?');
            $st->execute([$id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_exception($e, 'current_admin');
            $row = false;
        }
        if (!$row || (int) $row['is_active'] !== 1) {
            return $cache[$id] = null;
        }
        $row['id'] = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'];
        $row['seller_id'] = $row['seller_id'] !== null ? (int) $row['seller_id'] : null;
        $_SESSION['admin_role'] = $row['role'];
        $_SESSION['admin_name'] = $row['name'];
        return $cache[$id] = $row;
    }

    return $cache[$id] = [
        'id' => $id,
        'name' => (string) ($_SESSION['admin_name'] ?? 'Admin'),
        'username' => (string) ($_SESSION['admin_username'] ?? ''),
        'email' => '',
        'role' => (string) ($_SESSION['admin_role'] ?? 'staff'),
        'is_active' => 1,
        'last_login_at' => null,
        'seller_id' => null,
        'seller_name' => null,
        'seller_status' => null,
    ];
}

/**
 * Role of the signed-in admin ('owner' | 'manager' | 'staff' | 'seller'), or null when signed out.
 */
function admin_role(): ?string
{
    $admin = current_admin();
    if ($admin === null) {
        return null;
    }
    return isset(TZ_ROLE_LABELS[$admin['role']]) ? $admin['role'] : 'staff';
}

/**
 * Human label for a role ("Owner").
 */
function role_label(?string $role): string
{
    return TZ_ROLE_LABELS[$role ?? ''] ?? 'Staff';
}

/**
 * Whether the signed-in admin holds a capability. Unknown capabilities are denied.
 */
function can(string $cap): bool
{
    $role = admin_role();
    if ($role === null || !isset(TZ_CAPABILITIES[$cap])) {
        return false;
    }
    return in_array($role, TZ_CAPABILITIES[$cap], true);
}

/**
 * All capabilities of the signed-in admin (for window.TZ_CAPS).
 *
 * @return string[]
 */
function admin_capabilities(): array
{
    return array_values(array_filter(array_keys(TZ_CAPABILITIES), 'can'));
}

/**
 * Stop with 403 unless the signed-in admin holds $cap.
 * JSON endpoints (request_wants_json()) get {"success":false,...}; pages get a small HTML page.
 */
function require_can(string $cap): void
{
    if (can($cap)) {
        return;
    }
    $message = "Your role doesn't allow this. Ask the store owner if you need access.";
    if (request_wants_json()) {
        json_response(['success' => false, 'message' => $message, 'capability' => $cap], 403);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $home = htmlspecialchars(function_exists('admin_url') ? admin_url('manage/dashboard.php') : '/', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>No access · TechZone Admin</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f5f7;'
        . 'font:13px/1.5 Inter,system-ui,sans-serif;color:#1a1d23}.c{background:#fff;border:1px solid #e1e3e8;border-radius:8px;padding:28px 32px;max-width:380px;'
        . 'box-shadow:0 1px 2px rgba(16,24,40,.06)}h1{font-size:16px;margin:0 0 6px}p{color:#5c6370;margin:0 0 16px}a{color:#1a1d23;font-weight:600}</style></head>'
        . '<body><div class="c"><h1>You don\'t have access to this page</h1><p>' . $msg . '</p><a href="' . $home . '">Back to Home</a></div></body></html>';
    exit();
}


/* ==========================================================================
   Seller scoping (security-critical: every API applies these on the server)
   ========================================================================== */

/** True when the signed-in account is a marketplace seller. */
function is_seller(): bool
{
    return admin_role() === 'seller';
}

/**
 * The sellers.id the signed-in account works for, or null for TechZone staff (owner/manager/staff).
 * A seller account without a seller row gets 0, which matches no product (ids start at 1).
 */
function current_seller_id(): ?int
{
    if (!is_seller()) {
        return null;
    }
    $sid = current_admin()['seller_id'] ?? null;
    return $sid !== null && (int) $sid > 1 ? (int) $sid : 0;   // 1 is the house: never a seller account
}

/**
 * SQL condition limiting products (alias $alias) to the signed-in seller; "1=1" for staff.
 * The id is an int cast by current_seller_id(), so it is safe to inline.
 */
function seller_scope_products_sql(string $alias = 'p'): string
{
    $sid = current_seller_id();
    return $sid === null ? '1=1' : $alias . '.seller_id = ' . (int) $sid;
}

/** SQL condition limiting orders (alias $alias) to those containing the signed-in seller's products; "1=1" for staff. */
function seller_scope_orders_sql(string $alias = 'o'): string
{
    $sid = current_seller_id();
    if ($sid === null) {
        return '1=1';
    }
    return 'EXISTS (SELECT 1 FROM order_items ssi JOIN products ssp ON ssp.id = ssi.product_id WHERE ssi.order_id = ' . $alias . '.id AND ssp.seller_id = ' . (int) $sid . ')';
}

/** Stop with a JSON (API) or HTML (page) error. */
function seller_deny(int $status, string $message): void
{
    if (request_wants_json()) {
        json_response(['success' => false, 'message' => $message], $status);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $home = htmlspecialchars(function_exists('admin_url') ? admin_url('manage/dashboard.php') : '/', ENT_QUOTES, 'UTF-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>No access · TechZone Admin</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f5f7;'
        . 'font:13px/1.5 Inter,system-ui,sans-serif;color:#1a1d23}.c{background:#fff;border:1px solid #e1e3e8;border-radius:8px;padding:28px 32px;max-width:380px;'
        . 'box-shadow:0 1px 2px rgba(16,24,40,.06)}h1{font-size:16px;margin:0 0 6px}p{color:#5c6370;margin:0 0 16px}a{color:#1a1d23;font-weight:600}</style></head>'
        . '<body><div class="c"><h1>' . ($status === 404 ? 'Not found' : 'You don\'t have access to this page') . '</h1><p>' . $msg . '</p><a href="' . $home . '">Back to Home</a></div></body></html>';
    exit();
}

/**
 * Stop unless the product belongs to the signed-in seller (no-op for TechZone staff).
 * $status 403 for writes ("not yours"), 404 for reads (does not confirm the product exists).
 */
function assert_product_owned(int $productId, int $status = 403): void
{
    $sid = current_seller_id();
    if ($sid === null) {
        return;
    }
    $conn = $GLOBALS['conn'] ?? null;
    $owner = false;
    if ($conn instanceof PDO && $productId > 0) {
        $st = $conn->prepare('SELECT seller_id FROM products WHERE id = ?');
        $st->execute([$productId]);
        $owner = $st->fetchColumn();
    }
    if ($owner === false || (int) $owner !== $sid || $sid === 0) {
        seller_deny($status, $status === 404 ? 'Product not found.' : 'This product belongs to another seller, so you can\'t change it.');
    }
}

/**
 * Stop with 404 unless the order contains at least one of the signed-in seller's products
 * (no-op for TechZone staff). 404, not 403, so order numbers of other shops are not confirmed.
 */
function assert_order_visible(int $orderId): void
{
    $sid = current_seller_id();
    if ($sid === null) {
        return;
    }
    $conn = $GLOBALS['conn'] ?? null;
    $ok = false;
    if ($conn instanceof PDO && $orderId > 0 && $sid > 0) {
        $st = $conn->prepare('SELECT 1 FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ? AND p.seller_id = ? LIMIT 1');
        $st->execute([$orderId, $sid]);
        $ok = (bool) $st->fetchColumn();
    }
    if (!$ok) {
        seller_deny(404, 'Order not found.');
    }
}

/**
 * Deny-by-default gate for seller accounts (called by checkLogin on every page and API).
 * Only TZ_SELLER_ALLOWED scripts with an allowed method pass; a suspended seller is signed out of everything.
 */
function seller_gate(): void
{
    if (!is_seller()) {
        return;
    }
    $admin = current_admin();
    if (current_seller_id() === 0 || ($admin['seller_status'] ?? '') === 'suspended') {
        seller_deny(403, 'This seller account is suspended or not linked to a shop. Contact TechZone.');
    }
    $file = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $key = basename(dirname($file)) . '/' . basename($file);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (!array_key_exists($key, TZ_SELLER_ALLOWED)) {
        seller_deny(403, "Seller accounts can't open this. It is only available to TechZone staff.");
    }
    $methods = TZ_SELLER_ALLOWED[$key];
    if ($methods !== null && !in_array($method, $methods, true) && $method !== 'HEAD') {
        seller_deny(403, "Seller accounts can't change this.");
    }
}
