<?php
/**
 * Shared authentication helpers: login throttling, constant-time credential
 * checks and password policy. Used by the admin and customer login/signup APIs.
 */

require_once __DIR__ . '/bootstrap.php';

const LOGIN_MAX_FAILURES = 5;
const LOGIN_LOCK_SECONDS = 900;     // 15 minutes
const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 72;      // bcrypt only uses the first 72 bytes

/** Generic message for every failed login (no account enumeration). */
const LOGIN_FAILED_MESSAGE = 'Invalid email or password.';

function login_attempts_table(PDO $conn): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
        scope        VARCHAR(20) NOT NULL,
        ip_hash      CHAR(64) NOT NULL,
        failures     INT UNSIGNED NOT NULL DEFAULT 0,
        window_start DATETIME NOT NULL,
        locked_until DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_login_attempts_scope_ip (scope, ip_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function login_ip_hash(): string
{
    return hash('sha256', client_ip());
}

/**
 * Seconds remaining on a lockout for this scope (0 = not locked).
 * Checks both the per-IP table and a per-session counter.
 */
function login_lock_remaining(PDO $conn, string $scope): int
{
    $remaining = 0;

    $s = $_SESSION['login_throttle'][$scope] ?? null;
    if (is_array($s) && !empty($s['locked_until']) && $s['locked_until'] > time()) {
        $remaining = $s['locked_until'] - time();
    }

    try {
        login_attempts_table($conn);
        $stmt = $conn->prepare("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS remaining
                                FROM login_attempts WHERE scope = ? AND ip_hash = ? AND locked_until > NOW()");
        $stmt->execute([$scope, login_ip_hash()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $remaining = max($remaining, (int) $row['remaining']);
        }
    } catch (PDOException $e) {
        log_exception($e, 'login-throttle');   // fail open: never lock everyone out on a DB hiccup
    }

    return $remaining;
}

function login_register_failure(PDO $conn, string $scope): void
{
    // Session counter
    $now = time();
    $s = $_SESSION['login_throttle'][$scope] ?? ['failures' => 0, 'window_start' => $now, 'locked_until' => 0];
    if ($now - (int) $s['window_start'] > LOGIN_LOCK_SECONDS) {
        $s = ['failures' => 0, 'window_start' => $now, 'locked_until' => 0];
    }
    $s['failures'] = (int) $s['failures'] + 1;
    if ($s['failures'] >= LOGIN_MAX_FAILURES) {
        $s['locked_until'] = $now + LOGIN_LOCK_SECONDS;
    }
    $_SESSION['login_throttle'][$scope] = $s;

    // Per-IP counter (MySQL evaluates the assignments left to right)
    try {
        login_attempts_table($conn);
        $stmt = $conn->prepare("INSERT INTO login_attempts (scope, ip_hash, failures, window_start, locked_until)
            VALUES (?, ?, 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
              failures     = IF(window_start < NOW() - INTERVAL 15 MINUTE, 1, failures + 1),
              window_start = IF(failures = 1, NOW(), window_start),
              locked_until = IF(failures >= " . LOGIN_MAX_FAILURES . ", NOW() + INTERVAL 15 MINUTE, locked_until)");
        $stmt->execute([$scope, login_ip_hash()]);
    } catch (PDOException $e) {
        log_exception($e, 'login-throttle');
    }
}

function login_clear_failures(PDO $conn, string $scope): void
{
    unset($_SESSION['login_throttle'][$scope]);
    try {
        login_attempts_table($conn);
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE scope = ? AND ip_hash = ?");
        $stmt->execute([$scope, login_ip_hash()]);
    } catch (PDOException $e) {
        log_exception($e, 'login-throttle');
    }
}

function login_locked_message(int $seconds): string
{
    $minutes = max(1, (int) ceil($seconds / 60));
    return 'Too many failed login attempts. Please try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
}

/**
 * password_verify() that takes the same time whether or not the account exists.
 */
function verify_password_constant(string $password, ?string $hash): bool
{
    static $dummy = null;
    if ($hash === null || $hash === '') {
        $dummy ??= password_hash('timing-equaliser-' . bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        password_verify($password, $dummy);
        return false;
    }
    return password_verify($password, $hash);
}

/**
 * Returns an error message, or null when the password is acceptable.
 */
function password_policy_error(string $password): ?string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if (strlen($password) > PASSWORD_MAX_LENGTH) {
        return 'Password must be at most ' . PASSWORD_MAX_LENGTH . ' characters.';
    }
    return null;
}
