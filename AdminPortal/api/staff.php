<?php
/**
 * Settings → Staff & roles. Owner only (staff.manage). Every write is audited.
 *
 *   GET                                       -> {staff:[{id,name,username,email,phone,role,is_active,last_login_at,created_at}], me}
 *   POST {action:"invite", name, email, role} -> creates the account; {temp_password} is returned once and never stored in clear
 *   POST {action:"reset_password", id}        -> new temporary password (returned once); outstanding reset links are revoked
 *   PUT  {id, role}                           -> change role
 *   PUT  {id, is_active: true|false}          -> reactivate / deactivate (a deactivated admin is signed out on their next click)
 * Guards: you cannot change your own role, deactivate or reset yourself, and at least one active owner must remain.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/audit.php';

checkLogin(true);
json_headers();
require_can('staff.manage');

$method = $_SERVER['REQUEST_METHOD'];
$me = (int) $_SESSION['admin_id'];
const STAFF_ROLES = ['owner', 'manager', 'staff'];

function staff_list(PDO $conn): array
{
    // seller accounts are managed with their shop on the Sellers page (manage/seller.php)
    $rows = $conn->query('SELECT id, name, username, email, phone, role, is_active, last_login_at, created_at FROM admins
        WHERE role <> \'seller\'
        ORDER BY is_active DESC, FIELD(role, \'owner\', \'manager\', \'staff\'), name')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['is_active'] = (int) $r['is_active'];
    }
    return $rows;
}

function staff_get(PDO $conn, int $id): ?array
{
    $st = $conn->prepare('SELECT id, name, username, email, role, is_active FROM admins WHERE id = ? FOR UPDATE');
    $st->execute([$id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

/** Active owners other than $exceptId. */
function staff_other_owners(PDO $conn, int $exceptId): int
{
    $st = $conn->prepare("SELECT COUNT(*) FROM admins WHERE role = 'owner' AND is_active = 1 AND id <> ?");
    $st->execute([$exceptId]);
    return (int) $st->fetchColumn();
}

/** 12-character temporary password without look-alike characters (no 0/O, 1/l/I). */
function staff_temp_password(): string
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

function staff_username(PDO $conn, string $email): string
{
    $base = strtolower(preg_replace('/[^A-Za-z0-9_]+/', '_', strstr($email, '@', true) ?: 'staff'));
    $base = trim(substr($base, 0, 40), '_');
    if (strlen($base) < 3) {
        $base = 'staff_' . $base;
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

try {
    if ($method === 'GET') {
        json_response(['success' => true, 'staff' => staff_list($conn), 'me' => $me]);
    }
    enforce_csrf_json();
    $data = request_json();

    if ($method === 'POST' && ($data['action'] ?? '') === 'invite') {
        $errors = [];
        $name = v_str($data['name'] ?? '', 100);
        if ($name === null || $name === '') {
            $errors['name'] = 'Name is required (max 100 characters).';
        }
        $email = v_email(v_str($data['email'] ?? '', 150) ?? '');
        if ($email === null) {
            $errors['email'] = 'Enter a valid email address.';
        }
        $role = v_enum($data['role'] ?? '', STAFF_ROLES);
        if ($role === null) {
            $errors['role'] = 'Choose a role.';
        }
        if (!$errors) {
            $st = $conn->prepare('SELECT 1 FROM admins WHERE email = ?');
            $st->execute([$email]);
            if ($st->fetchColumn()) {
                $errors['email'] = 'Someone on the team already uses this email.';
            }
        }
        if ($errors) {
            json_response(['success' => false, 'message' => 'Check the highlighted fields.', 'errors' => $errors], 422);
        }
        $temp = staff_temp_password();
        $conn->beginTransaction();
        $username = staff_username($conn, $email);
        $conn->prepare('INSERT INTO admins (name, username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$name, $username, $email, password_hash($temp, PASSWORD_DEFAULT), $role]);
        $id = (int) $conn->lastInsertId();
        audit('staff.create', 'admin', $id, 'Invited ' . $name . ' as ' . role_label($role), null, ['name' => $name, 'email' => $email, 'role' => $role]);
        $conn->commit();
        json_response(['success' => true, 'message' => $name . ' invited', 'id' => $id, 'email' => $email, 'temp_password' => $temp, 'staff' => staff_list($conn)]);
    }

    $id = v_id($data['id'] ?? 0);
    if ($id === 0) {
        json_error('Choose a team member', 422);
    }

    if ($method === 'POST' && ($data['action'] ?? '') === 'reset_password') {
        if ($id === $me) {
            json_error('Change your own password on your Profile page.', 409);
        }
        $conn->beginTransaction();
        $t = staff_get($conn, $id);
        if (!$t) {
            $conn->rollBack();
            json_error('Team member not found', 404);
        }
        $temp = staff_temp_password();
        $conn->prepare('UPDATE admins SET password = ? WHERE id = ?')->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
        $conn->prepare('DELETE FROM admin_password_resets WHERE admin_id = ?')->execute([$id]);
        audit('staff.password_reset', 'admin', $id, 'Reset the password for ' . $t['name'], null, null);
        $conn->commit();
        json_response(['success' => true, 'message' => 'Password reset', 'temp_password' => $temp, 'email' => $t['email'], 'staff' => staff_list($conn)]);
    }

    if ($method !== 'PUT') {
        json_error('Invalid request method', 405);
    }

    $conn->beginTransaction();
    $t = staff_get($conn, $id);
    if (!$t) {
        $conn->rollBack();
        json_error('Team member not found', 404);
    }
    if ($t['role'] === 'seller') {
        $conn->rollBack();
        json_error('Seller accounts are managed with their shop on the Sellers page.', 409);
    }
    $message = 'Nothing changed';
    if (array_key_exists('role', $data)) {
        $role = v_enum($data['role'], STAFF_ROLES);
        if ($role === null) {
            $conn->rollBack();
            json_error('Choose a role', 422);
        }
        if ($id === $me && $role !== $t['role']) {
            $conn->rollBack();
            json_error("You can't change your own role. Ask another owner.", 409);
        }
        if ($t['role'] === 'owner' && $role !== 'owner' && (int) $t['is_active'] === 1 && staff_other_owners($conn, $id) === 0) {
            $conn->rollBack();
            json_error('At least one owner must remain. Make someone else an owner first.', 409);
        }
        if ($role !== $t['role']) {
            $conn->prepare('UPDATE admins SET role = ? WHERE id = ?')->execute([$role, $id]);
            audit('staff.role', 'admin', $id, 'Changed ' . $t['name'] . ' from ' . role_label($t['role']) . ' to ' . role_label($role), ['role' => $t['role']], ['role' => $role]);
            $message = 'Role changed';
        }
    }
    if (array_key_exists('is_active', $data)) {
        $active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        if ($id === $me && $active === 0) {
            $conn->rollBack();
            json_error("You can't deactivate your own account.", 409);
        }
        if ($active === 0 && $t['role'] === 'owner' && staff_other_owners($conn, $id) === 0) {
            $conn->rollBack();
            json_error('At least one active owner must remain.', 409);
        }
        if ($active !== (int) $t['is_active']) {
            $conn->prepare('UPDATE admins SET is_active = ? WHERE id = ?')->execute([$active, $id]);
            if ($active === 0) {
                $conn->prepare('DELETE FROM admin_password_resets WHERE admin_id = ?')->execute([$id]);
            }
            audit($active ? 'staff.reactivate' : 'staff.deactivate', 'admin', $id, ($active ? 'Reactivated ' : 'Deactivated ') . $t['name'],
                ['is_active' => (bool) $t['is_active']], ['is_active' => (bool) $active]);
            $message = $active ? 'Account reactivated' : 'Account deactivated';
        }
    }
    $conn->commit();
    json_response(['success' => true, 'message' => $message, 'staff' => staff_list($conn)]);
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    json_error(safe_error_message($e, 'Could not update the team.'), 500);
}
