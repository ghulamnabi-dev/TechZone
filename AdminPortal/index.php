<?php
session_start();
require_once __DIR__ . '/config/paths.php';
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . admin_url('manage/dashboard.php'));
    exit();
}
require __DIR__ . '/auth/login.php';
