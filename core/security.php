<?php
/**
 * CSRF protection helpers shared across the project.
 *
 * Token sources accepted (in order): X-CSRF-Token header, csrf_token POST field,
 * csrf_token key in a JSON request body.
 *
 * Transitional mode: while CSRF_STRICT is not enabled, a JSON/AJAX request that
 * carries NO token is accepted only if its Origin (or Referer) header is this
 * same host. Browsers always send Origin on cross-site POST/PUT/DELETE, so forged
 * requests are still rejected; a request with a WRONG token is always rejected.
 * Set CSRF_STRICT=1 once every fetch() sends the X-CSRF-Token header.
 */

require_once __DIR__ . '/bootstrap.php';

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Return a hidden input field with the current CSRF token.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * The token sent with the current request ('' when none).
 */
function csrf_request_token(): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && trim($header) !== '') {
        return trim($header);
    }
    $post = $_POST['csrf_token'] ?? '';
    if (is_string($post) && trim($post) !== '') {
        return trim($post);
    }
    $json = request_json();
    $body = $json['csrf_token'] ?? '';
    if (is_string($body) && trim($body) !== '') {
        return trim($body);
    }
    return '';
}

function csrf_token_is_valid(string $sent): bool
{
    $stored = $_SESSION['csrf_token'] ?? '';
    return $sent !== '' && is_string($stored) && $stored !== '' && hash_equals($stored, $sent);
}

/**
 * True when the Origin (or, failing that, Referer) header names this host.
 */
function request_is_same_origin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return false;
    }
    $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($source === '' || $source === 'null') {
        $source = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    }
    if ($source === '') {
        return false;
    }
    $parts = parse_url($source);
    if (!$parts || empty($parts['host'])) {
        return false;
    }
    $origin = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $hostNoPort = preg_replace('/:\d+$/', '', $host);
    return $origin === $host || (!isset($parts['port']) && strtolower($parts['host']) === $hostNoPort);
}

function csrf_strict(): bool
{
    return env_flag('CSRF_STRICT');
}

/**
 * Validate a CSRF token for a classic HTML form POST (token must be present).
 *
 * @throws RuntimeException when the token is missing or invalid.
 */
function require_csrf_token(): void
{
    start_secure_session();
    if (!csrf_token_is_valid(csrf_request_token())) {
        throw new RuntimeException('Invalid CSRF token.');
    }
}

/**
 * Validate CSRF for JSON / AJAX endpoints: token from header, POST field or JSON body.
 * See the file header for the transitional same-origin fallback.
 *
 * @throws RuntimeException when the request must be rejected.
 */
function require_csrf_token_any(): void
{
    start_secure_session();
    $sent = csrf_request_token();
    if ($sent !== '') {
        if (!csrf_token_is_valid($sent)) {
            throw new RuntimeException('Invalid CSRF token.');
        }
        return;
    }
    if (!csrf_strict() && request_is_same_origin()) {
        return;
    }
    throw new RuntimeException('Missing CSRF token.');
}

/**
 * JSON endpoint guard: on failure respond 403 JSON and stop.
 */
function enforce_csrf_json(): void
{
    try {
        require_csrf_token_any();
    } catch (RuntimeException $e) {
        json_error('Invalid or missing security token. Please refresh the page and try again.', 403);
    }
}
