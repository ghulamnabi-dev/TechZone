<?php
/**
 * Quick check: asset folder structure and hero image path.
 * Open this file in the browser to verify assets are reachable.
 * Delete or restrict access in production.
 */
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
echo "  " . (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/') . "/assets/images/hero/slide1.png\n";
echo "\nIf the image loads when you open that URL, the slider will work on the homepage.\n";
