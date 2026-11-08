<?php
/**
 * Central error handling.
 *
 * - APP_DEBUG=1 (env) shows error details; otherwise errors are logged with
 *   error_log() and visitors get a generic message.
 * - Uncaught exceptions and fatal errors become a clean HTTP 500
 *   (JSON for API requests, a small HTML page otherwise).
 */

if (!function_exists('env')) {
    /**
     * Read a configuration value from the environment (12-factor).
     */
    function env(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }
}

function env_flag(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function app_debug(): bool
{
    static $debug = null;
    if ($debug === null) {
        $debug = env_flag('APP_DEBUG');
    }
    return $debug;
}

/**
 * True when the current request is for a JSON endpoint (any script under an api/ folder)
 * or explicitly asks for JSON.
 */
function request_wants_json(): bool
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/api/') !== false) {
        return true;
    }
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    return stripos($accept, 'application/json') !== false;
}

/**
 * Abort the request with a generic error. Never leaks internals unless APP_DEBUG is on.
 */
function app_fail(int $status = 500, string $publicMessage = 'An unexpected error occurred. Please try again later.', ?Throwable $e = null): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
    }

    $detail = ($e !== null && app_debug())
        ? get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
        : null;

    if (request_wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }
        $payload = ['success' => false, 'message' => $publicMessage];
        if ($detail !== null) {
            $payload['debug'] = $detail;
        }
        echo json_encode($payload);
    } else {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error</title></head><body style="font-family:sans-serif;padding:2rem">';
        echo '<h1>Something went wrong</h1><p>' . htmlspecialchars($publicMessage, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($detail !== null) {
            echo '<pre>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</pre>';
        }
        echo '</body></html>';
    }
    exit();
}

/**
 * Log an exception with request context.
 */
function log_exception(Throwable $e, string $context = ''): void
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? 'cli');
    error_log(sprintf(
        '[TechZone]%s %s: %s in %s:%d (uri=%s)',
        $context !== '' ? ' [' . $context . ']' : '',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $uri
    ));
}

/**
 * Return a message that is safe to show to users: details only when APP_DEBUG is on.
 */
function safe_error_message(Throwable $e, string $generic = 'A server error occurred. Please try again.'): string
{
    log_exception($e);
    return app_debug() ? $generic . ' (' . $e->getMessage() . ')' : $generic;
}

function register_error_handlers(): void
{
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    // Never print PHP warnings/notices into pages or JSON unless debugging.
    ini_set('display_errors', app_debug() ? '1' : '0');
    ini_set('display_startup_errors', app_debug() ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('html_errors', '0');

    set_exception_handler(static function (Throwable $e): void {
        log_exception($e, 'uncaught');
        app_fail(500, 'An unexpected error occurred. Please try again later.', $e);
    });

    register_shutdown_function(static function (): void {
        $err = error_get_last();
        if ($err === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatal, true)) {
            return;
        }
        error_log(sprintf('[TechZone] [fatal] %s in %s:%d', $err['message'], $err['file'], $err['line']));
        if (!headers_sent()) {
            app_fail(500, 'An unexpected error occurred. Please try again later.',
                app_debug() ? new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']) : null);
        }
    });
}
