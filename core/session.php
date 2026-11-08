<?php
/**
 * Session bootstrap with hardened cookie parameters.
 *
 * Admin and customer identities live in the same PHP session under separate
 * keys (admin_* / customer_*), so logging out of one does not log out the other.
 */

require_once __DIR__ . '/http.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (PHP_SAPI !== 'cli' && !headers_sent()) {
        ini_set('session.use_strict_mode', '1');   // reject unknown (attacker-chosen) session ids
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    @session_start();
}

/**
 * Call right after a successful login: new session id (prevents fixation) and a fresh CSRF token.
 */
function session_after_login(): void
{
    start_secure_session();
    session_regenerate_id(true);
    // Rotate the CSRF token unless another identity (admin/customer) is already
    // signed in within this session and may have pages open that use it.
    if (!isset($_SESSION['admin_id']) && !isset($_SESSION['customer_id'])) {
        unset($_SESSION['csrf_token']);
    }
}

/**
 * Log out one identity ("admin" or "customer").
 * If no other identity remains, the session is destroyed and the cookie cleared.
 */
function session_logout(string $scope): void
{
    start_secure_session();

    $prefix = $scope === 'admin' ? 'admin_' : 'customer_';
    foreach (array_keys($_SESSION) as $key) {
        if (strpos((string) $key, $prefix) === 0) {
            unset($_SESSION[$key]);
        }
    }
    if ($scope === 'customer') {
        unset($_SESSION['cart'], $_SESSION['applied_promo_code'], $_SESSION['applied_promo_data']);
    }

    $otherLoggedIn = $scope === 'admin' ? isset($_SESSION['customer_id']) : isset($_SESSION['admin_id']);
    if ($otherLoggedIn) {
        session_regenerate_id(true);
        return;
    }

    $_SESSION = [];
    if (ini_get('session.use_cookies') && !headers_sent()) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?: 'Lax',
        ]);
    }
    session_destroy();
}
