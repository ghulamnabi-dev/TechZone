<?php
/**
 * Request throttling for abusable storefront endpoints (stock alerts, wishlist, builder cart,
 * seller applications). Two counters, both must allow the request:
 *   - per session (cheap, survives nothing but the session), and
 *   - per client IP in the login_attempts table (scope = the bucket name, max 20 chars).
 * Fails open on a database error so a hiccup never blocks shoppers.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth.php';   // login_attempts_table(), login_ip_hash()

/**
 * Count one hit for $scope and say whether it is within the limit.
 *
 * @param PDO    $conn
 * @param string $scope         bucket name, e.g. "stock_alert" (stored in login_attempts.scope)
 * @param int    $max           hits allowed per window
 * @param int    $windowSeconds window length
 * @return bool true = allowed; false = over the limit (respond 429)
 *
 * Example: if (!throttle_allow($conn, 'stock_alert', 10, 600)) json_error('Too many requests', 429);
 */
function throttle_allow(PDO $conn, string $scope, int $max, int $windowSeconds): bool
{
    $scope = substr($scope, 0, 20);
    $now = time();

    $hits = array_values(array_filter($_SESSION['throttle'][$scope] ?? [], static fn($t) => is_int($t) && $t > $now - $windowSeconds));
    if (count($hits) >= $max) {
        return false;
    }
    $hits[] = $now;
    $_SESSION['throttle'][$scope] = $hits;

    try {
        login_attempts_table($conn);
        // assignments run left to right: a window that has expired restarts at 1
        $st = $conn->prepare('INSERT INTO login_attempts (scope, ip_hash, failures, window_start, locked_until)
            VALUES (?, ?, 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
              failures     = IF(window_start < NOW() - INTERVAL ? SECOND, 1, failures + 1),
              window_start = IF(failures = 1, NOW(), window_start)');
        $st->execute([$scope, login_ip_hash(), $windowSeconds]);
        $st = $conn->prepare('SELECT failures FROM login_attempts WHERE scope = ? AND ip_hash = ?');
        $st->execute([$scope, login_ip_hash()]);
        // the IP budget is wider than the session one (shared offices, mobile carrier NAT)
        return (int) $st->fetchColumn() <= $max * 5;
    } catch (PDOException $e) {
        log_exception($e, 'throttle');
        return true;
    }
}
