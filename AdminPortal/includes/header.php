<?php
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
checkLogin();
$adminName = getAdminName();
$adminInitial = strtoupper(substr($adminName, 0, 1));
$adminCsrfToken = csrf_token();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php if(isset($pageTitle)) echo $pageTitle . ' - '; ?>Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($adminCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body>
<?php include __DIR__ . '/url-script.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="admin-content">
    <div class="admin-header">
        <h4 class="admin-header-title">
            <?php if(isset($pageTitle)) echo $pageTitle; else echo 'Dashboard'; ?>
        </h4>
        <div class="admin-header-right">
            <div class="admin-header-info text-end">
                <strong><?php echo htmlspecialchars($adminName); ?></strong>
                <small>Administrator</small>
            </div>
            <a href="<?php echo htmlspecialchars(admin_url('manage/profile.php'), ENT_QUOTES, 'UTF-8'); ?>" class="admin-header-avatar text-decoration-none" title="Profile">
                <?php echo $adminInitial; ?>
            </a>
        </div>
    </div>
    <div class="admin-page-content">
