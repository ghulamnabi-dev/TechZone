<?php
/**
 * Small input-validation helpers. Each returns a normalised value or null when invalid.
 */

/**
 * Trimmed string of at most $max characters. Non-scalars and over-long values -> null.
 */
function v_str(mixed $value, int $max = 255, bool $allowEmpty = true): ?string
{
    if ($value === null) {
        $value = '';
    }
    if (!is_scalar($value)) {
        return null;
    }
    $value = trim((string) $value);
    if (!$allowEmpty && $value === '') {
        return null;
    }
    $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    return $len > $max ? null : $value;
}

/**
 * Integer within [$min, $max], or null.
 */
function v_int(mixed $value, int $min = 0, int $max = PHP_INT_MAX): ?int
{
    if (is_int($value)) {
        $int = $value;
    } elseif (is_string($value) && preg_match('/^\s*-?\d+\s*$/', $value)) {
        $int = (int) $value;
    } elseif (is_float($value) && floor($value) === $value) {
        $int = (int) $value;
    } else {
        return null;
    }
    return ($int < $min || $int > $max) ? null : $int;
}

/**
 * Positive id (1..2^31) or 0 when missing/invalid.
 */
function v_id(mixed $value): int
{
    return v_int($value, 1, 2147483647) ?? 0;
}

/**
 * Decimal number within [$min, $max], or null.
 */
function v_num(mixed $value, float $min = 0.0, float $max = 1.0e9): ?float
{
    if (is_int($value) || is_float($value)) {
        $num = (float) $value;
    } elseif (is_string($value) && is_numeric(trim($value))) {
        $num = (float) trim($value);
    } else {
        return null;
    }
    return ($num < $min || $num > $max || !is_finite($num)) ? null : $num;
}

function v_email(mixed $value): ?string
{
    $email = v_str($value, 150, false);
    if ($email === null) {
        return null;
    }
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    return $email === false ? null : strtolower($email);
}

/**
 * Y-m-d date string, '' -> null (allowed), invalid -> false.
 */
function v_date(mixed $value): string|false|null
{
    $value = v_str($value, 10);
    if ($value === null) {
        return false;
    }
    if ($value === '') {
        return null;
    }
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return ($d && $d->format('Y-m-d') === $value) ? $value : false;
}

/**
 * Link target safe to render as href: relative path ("/x", "page.php?x=1") or http(s) URL.
 * Rejects javascript:, data:, protocol-relative "//host" etc. Returns '' for empty, null if invalid.
 */
function v_link(mixed $value, int $max = 500): ?string
{
    $value = v_str($value, $max);
    if ($value === null) {
        return null;
    }
    if ($value === '') {
        return '';
    }
    if (preg_match('/[\x00-\x1F\x7F\\\\\s]/', $value)) {
        return null;
    }
    if (preg_match('#^https?://#i', $value)) {
        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }
    if (strpos($value, '//') === 0 || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
        return null;   // other schemes (javascript:, data:, ...) or protocol-relative
    }
    return $value;
}

function v_enum(mixed $value, array $allowed): ?string
{
    return (is_string($value) && in_array($value, $allowed, true)) ? $value : null;
}

/**
 * Pakistani mobile format used across the UI: 03xx-xxxxxxx ('' allowed).
 */
function v_phone(mixed $value): ?string
{
    $value = v_str($value, 30);
    if ($value === null) {
        return null;
    }
    return ($value === '' || preg_match('/^03\d{2}-\d{7}$/', $value)) ? $value : null;
}

/**
 * Stop with 422 and a field map the admin forms render inline under each input:
 * {"success":false,"message":"…","errors":{"price":"Price must be …"}}.
 *
 * @param array<string,string> $errors field name => message
 */
function validation_problem(array $errors, ?string $message = null): never
{
    $message ??= count($errors) === 1 ? (string) reset($errors) : 'Please fix the highlighted fields.';
    json_response(['success' => false, 'message' => $message, 'errors' => $errors], 422);
}
