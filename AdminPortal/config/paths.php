<?php
/**
 * Admin portal URL helpers (subdirectory-safe, e.g. /TechZone/Admin Portal).
 */

function admin_portal_base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $root = realpath(__DIR__ . '/..');
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$root || !$doc || strpos($root, $doc) !== 0) {
        $base = '';
        return $base;
    }
    $rel = substr($root, strlen($doc));
    $rel = str_replace('\\', '/', $rel);
    $base = rtrim($rel, '/');
    return $base;
}

/**
 * Absolute URL path under Admin Portal root.
 *
 * @param string $path e.g. "manage/dashboard.php" or "api/orders.php"
 */
function admin_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = admin_portal_base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}
