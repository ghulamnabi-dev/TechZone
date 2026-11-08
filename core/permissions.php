<?php
/**
 * Roles and capabilities (docs/ADMIN.md "Permissions").
 *
 * Three roles: owner, manager, staff. Every capability maps to the roles that hold it in ONE
 * array (TZ_CAPABILITIES); pages hide what a role cannot do (window.TZ_CAPS) and the APIs enforce
 * it with require_can(). The signed-in admin is re-read from the database once per request, so a
 * role change or deactivation takes effect on the next click, not the next login.
 */

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/http.php';

/** Capability => roles that hold it. The single source of truth for permissions. */
const TZ_CAPABILITIES = [
    // view everything
    'orders.view'      => ['owner', 'manager', 'staff'],
    'activity.view'    => ['owner', 'manager'],
    // day-to-day order work
    'orders.fulfil'    => ['owner', 'manager', 'staff'],
    'orders.payment'   => ['owner', 'manager', 'staff'],
    'orders.ship'      => ['owner', 'manager', 'staff'],
    'orders.note'      => ['owner', 'manager', 'staff'],
    'reviews.moderate' => ['owner', 'manager', 'staff'],
    'messages.write'   => ['owner', 'manager', 'staff'],
    // money and catalogue
    'orders.cancel'    => ['owner', 'manager'],
    'orders.refund'    => ['owner', 'manager'],
    'products.write'   => ['owner', 'manager'],
    'inventory.adjust' => ['owner', 'manager'],
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
];

/** Display labels for the role badge. */
const TZ_ROLE_LABELS = ['owner' => 'Owner', 'manager' => 'Manager', 'staff' => 'Staff'];

/**
 * The signed-in admin (id, name, username, email, role, is_active, last_login_at) or null.
 *
 * Read from the database once per request when a PDO connection ($conn) exists; falls back to
 * the session copy otherwise. A deactivated or deleted account is treated as signed out.
 *
 * @return array{id:int,name:string,username:string,email:string,role:string,is_active:int,last_login_at:?string}|null
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
            $st = $conn->prepare('SELECT id, name, username, email, role, is_active, last_login_at FROM admins WHERE id = ?');
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
    ];
}

/**
 * Role of the signed-in admin ('owner' | 'manager' | 'staff'), or null when signed out.
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
