<?php
require_once __DIR__ . '/../config/customer_auth.php';

json_headers();

if (isset($_SESSION['customer_id'])) {
    json_response([
        'success' => true,
        'logged_in' => true,
        'customer' => [
            'id' => (int) $_SESSION['customer_id'],
            'name' => $_SESSION['customer_name'] ?? 'Customer',
            'email' => $_SESSION['customer_email'] ?? '',
        ],
    ]);
}
json_response(['success' => true, 'logged_in' => false]);
