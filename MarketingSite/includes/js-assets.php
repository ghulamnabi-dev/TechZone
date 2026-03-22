<?php
/**
 * Marketing site JavaScript under assets/js/.
 * Single place for paths and cache-busting after file moves.
 *
 * @param string $filename File name only (e.g. common.js)
 * @return string Absolute URL path from document root (works from auth/, shop/, etc.)
 */
function marketing_script_url(string $filename): string
{
    require_once __DIR__ . '/../config/paths.php';
    $filename = basename($filename);
    $full = __DIR__ . '/../assets/js/' . $filename;
    $v = file_exists($full) ? (int) filemtime($full) : time();

    return site_url('assets/js/' . rawurlencode($filename)) . '?v=' . $v;
}
