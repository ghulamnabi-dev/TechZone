<?php
/**
 * Shared request bootstrap for MarketingSite and AdminPortal.
 *
 * Included by both config/database.php files (and the auth helpers), so every
 * page and API gets: env handling, error handling, security headers and a
 * hardened session.
 */

require_once __DIR__ . '/errors.php';

// Optional local .env file (never committed). Real environment variables win.
(static function (): void {
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file) || !is_readable($file)) {
        return;
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
})();

register_error_handlers();

require_once __DIR__ . '/http.php';
require_once __DIR__ . '/session.php';

send_security_headers();
start_secure_session();
