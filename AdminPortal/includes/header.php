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
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <title><?php if(isset($pageTitle)) echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' · '; ?>TechZone Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="csrf-token" content="<?= htmlspecialchars($adminCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
<?php if (!empty($useGrid)): ?>
    <!-- AG Grid Community 36.2.0 (MIT, vendored: assets/vendor/ag-grid/LICENSE.txt) + our wrapper -->
    <script src="<?php echo htmlspecialchars(admin_url('assets/vendor/ag-grid/ag-grid-community.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(admin_url('assets/js/grid.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/grid.js'); ?>"></script>
<?php endif; ?>
</head>
<body class="admin-body">
<?php include __DIR__ . '/url-script.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="sidebar-backdrop" data-sidebar-close></div>
<div class="admin-content">
    <header class="admin-header">
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="admin-header-title"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="admin-header-right">
            <div class="dropdown">
                <button type="button" class="user-menu-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                    <span class="who">
                        <strong><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></strong>
                        <small>Administrator</small>
                    </span>
                    <span class="admin-header-avatar"><?php echo htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8'); ?></span>
                    <i class="fas fa-chevron-down caret"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars(admin_url('manage/profile.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars(rtrim(dirname(admin_portal_base_path() ?: '/'), '/') . '/MarketingSite/index.php', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View storefront</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars(admin_url('api/logout.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a></li>
                </ul>
            </div>
        </div>
    </header>
    <main class="admin-page-content">
