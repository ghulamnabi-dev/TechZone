<?php

/**
 * Simple CSRF protection helpers shared across the project.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate (or reuse) a CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
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
 * Validate a CSRF token from a POST request.
 *
 * @throws RuntimeException when the token is missing or invalid.
 */
function require_csrf_token(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';

    if (!$sent || !$stored || !hash_equals($stored, $sent)) {
        throw new RuntimeException('Invalid CSRF token.');
    }
}


