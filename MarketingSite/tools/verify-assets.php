<?php
/**
 * Quick check: asset folder structure and hero image path (developer tool).
 *
 * Disabled unless ENABLE_DEV_TOOLS=1 is set in the environment, because it
 * discloses absolute filesystem paths. Returns 404 otherwise.
 */
require_once __DIR__ . '/../../core/errors.php';
if (!env_flag('ENABLE_DEV_TOOLS')) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}
$base = __DIR__;
$assetBase = dirname($base) . '/assets';
$heroImage = $assetBase . '/images/hero/slide1.png';

header('Content-Type: text/plain; charset=utf-8');

echo "Asset path verification\n";
echo "=======================\n\n";
echo "Script directory (tools): " . $base . "\n";
echo "Assets folder: " . $assetBase . "\n";
echo "Hero image path: " . $heroImage . "\n\n";

echo "Assets folder exists: " . (is_dir($assetBase) ? 'YES' : 'NO') . "\n";
echo "Hero image exists: " . (file_exists($heroImage) ? 'YES' : 'NO') . "\n\n";

echo "URL you should use in browser for slide1:\n";
echo "  " . (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) . htmlspecialchars(rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'), ENT_QUOTES, 'UTF-8') . "/assets/images/hero/slide1.png\n";
echo "\nIf the image loads when you open that URL, the slider will work on the homepage.\n";
