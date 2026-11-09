<?php
/** Shared top of every help page: header, page helpers, help frame. Set $pageTitle first. */
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/pages.php';
require_once __DIR__ . '/../includes/help-layout.php';
require_once __DIR__ . '/../../core/shipping.php';
