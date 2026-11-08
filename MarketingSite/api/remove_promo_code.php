<?php
require_once __DIR__ . '/../../core/security.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();

unset($_SESSION['applied_promo_code'], $_SESSION['applied_promo_data']);

json_response(['success' => true, 'message' => 'Promo code removed']);
