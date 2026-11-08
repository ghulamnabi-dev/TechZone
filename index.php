<?php
/**
 * Repo root entry: send visitors to the marketing storefront.
 * Set Railway variable RAILPACK_PHP_ROOT_DIR=/app (see README or deploy notes).
 */
require_once __DIR__ . '/core/http.php';
send_security_headers();
header('Location: /MarketingSite/index.php', true, 302);
exit;
