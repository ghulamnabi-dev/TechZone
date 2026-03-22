<?php
/**
 * Marketing site URL helpers (works when the site lives in a subdirectory, e.g. /TechZone/Marketing Site).
 */

/**
 * Web path to Marketing Site root from the server document root (no trailing slash).
 * Example: /TechZone/Marketing Site
 */
function marketing_site_base_path(): string
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
 * Absolute URL path to a file under the Marketing Site root.
 *
 * @param string $path e.g. "shop/products.php" or "api/login.php"
 */
function site_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = marketing_site_base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

/**
 * Absolute filesystem path to Admin Portal (supports AdminPortal or legacy "Admin Portal" folder name).
 */
function admin_portal_filesystem_root(): string
{
    static $path = null;
    if ($path !== null) {
        return $path;
    }
    $candidates = [
        __DIR__ . '/../../AdminPortal',
        __DIR__ . '/../../Admin Portal',
    ];
    foreach ($candidates as $candidate) {
        $resolved = realpath($candidate);
        if ($resolved && is_dir($resolved)) {
            $path = $resolved;
            return $path;
        }
    }
    $path = '';
    return $path;
}

/**
 * Web path to Admin Portal root (sibling of Marketing Site), e.g. /TechZone/Admin Portal
 */
function admin_portal_public_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $adminRoot = admin_portal_filesystem_root();
    $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    if (!$adminRoot || !$doc || strpos($adminRoot, $doc) !== 0) {
        $base = '';
        return $base;
    }
    $rel = substr($adminRoot, strlen($doc));
    $rel = str_replace('\\', '/', $rel);
    $base = rtrim($rel, '/');
    return $base;
}

/**
 * Absolute URL path under Admin Portal (e.g. uploads/foo.jpg for product images).
 */
function admin_portal_asset_url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $base = admin_portal_public_path();
    if ($path === '') {
        return $base === '' ? '/' : $base;
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}
