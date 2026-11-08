<?php
/**
 * HTTP helpers: security headers, JSON responses, request parsing, safe redirects.
 */

require_once __DIR__ . '/errors.php';

/**
 * True when the request reached us over HTTPS (directly or via a TLS-terminating proxy).
 */
function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
}

/**
 * Client IP for rate limiting. X-Forwarded-For is only honoured when TRUST_PROXY=1
 * (e.g. behind Railway / a load balancer), otherwise it is trivially spoofable.
 */
function client_ip(): string
{
    if (env_flag('TRUST_PROXY')) {
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Baseline security headers for every HTML page and JSON response.
 * The CSP is Report-Only for now (the UI still uses inline scripts / CDNs),
 * so it documents the intended policy without breaking anything.
 */
function send_security_headers(): void
{
    static $sent = false;
    if ($sent || headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    $sent = true;

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cross-Origin-Opener-Policy: same-origin');

    $cdn = 'https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://code.jquery.com';
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' $cdn",
        "style-src 'self' 'unsafe-inline' $cdn https://fonts.googleapis.com",
        "font-src 'self' data: $cdn https://fonts.gstatic.com",
        "img-src 'self' data: blob: https:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
    ]);
    header('Content-Security-Policy-Report-Only: ' . $csp);

    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/**
 * Mark the response as JSON (UTF-8, not cacheable).
 */
function json_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

/**
 * Send a JSON response and stop.
 */
function json_response(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
    }
    json_headers();
    echo json_encode($payload);
    exit();
}

function json_error(string $message, int $status = 400): void
{
    json_response(['success' => false, 'message' => $message], $status);
}

/**
 * Decoded JSON request body (cached). Returns [] for empty / invalid bodies.
 */
function request_json(): array
{
    static $data = null;
    if ($data !== null) {
        return $data;
    }
    $data = [];
    $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $data;
    }
    if ($type === '' || strpos($type, 'json') !== false || in_array($raw[0], ['{', '['], true)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    return $data;
}

/**
 * Only allow same-site relative redirect targets ("/path?query"). Anything that could
 * leave the site (scheme, "//host", backslashes, control chars) falls back.
 */
function safe_redirect_path(?string $target, string $fallback): string
{
    $target = trim((string) $target);
    if ($target === '' || strlen($target) > 500) {
        return $fallback;
    }
    if ($target[0] !== '/' || (isset($target[1]) && ($target[1] === '/' || $target[1] === '\\'))) {
        return $fallback;
    }
    if (!preg_match('#^/[A-Za-z0-9/_\-.~%?=&+]*$#', $target)) {
        return $fallback;
    }
    return $target;
}

/**
 * Absolute URL for links sent out of band (emails). Uses APP_URL when configured so a
 * spoofed Host header cannot redirect password-reset links to another domain.
 */
function absolute_url(string $path): string
{
    $base = env('APP_URL');
    if ($base === null) {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (!preg_match('/^[A-Za-z0-9.\-]+(:\d+)?$/', $host)) {
            $host = 'localhost';
        }
        $base = (is_https() ? 'https' : 'http') . '://' . $host;
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}
