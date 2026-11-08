<?php
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../../core/bootstrap.php';

// Logs out the admin identity only (a customer signed in on the same browser stays signed in).
session_logout('admin');
header('Location: ' . admin_url('index.php'));
exit();
