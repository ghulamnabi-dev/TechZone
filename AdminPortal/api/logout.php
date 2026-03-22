<?php
session_start();
session_destroy();
require_once __DIR__ . '/../config/paths.php';
header('Location: ' . admin_url('index.php'));
exit();
