<?php
/**
 * Admin shell: <head>, 240px sidebar, 56px topbar (⌘K search, notifications, account) and the
 * page heading. Pages set before including it:
 *   $pageTitle     string   heading + <title>
 *   $pageSubtitle  ?string  one line under the heading
 *   $pageActions   ?string  trusted HTML for the right side of the heading (buttons)
 *   $pageBack      ?array   ['href' => …, 'label' => …] back link above the heading (detail pages)
 *   $useGrid       bool     load AG Grid + assets/js/grid.js
 *   $pageCap       ?string  capability required to open the page (require_can)
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/permissions.php';
checkLogin();
if (!empty($pageCap)) {
    require_can($pageCap);
}

$tzAdmin = current_admin() ?? ['id' => (int) $_SESSION['admin_id'], 'name' => getAdminName(), 'email' => '', 'role' => 'staff'];
$adminName = $tzAdmin['name'];
$adminRole = admin_role() ?? 'staff';
$adminRoleLabel = role_label($adminRole);
$adminInitial = strtoupper(substr($adminName, 0, 1));
$adminCsrfToken = csrf_token();
$tzE = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$tzAsset = static fn(string $rel): string => htmlspecialchars(admin_url($rel), ENT_QUOTES, 'UTF-8') . '?v=' . (int) @filemtime(__DIR__ . '/../' . $rel);

// Seller accounts (core/permissions.php): their shop name in the topbar, their own counts only
$tzSellerId = current_seller_id();
$tzSellerName = $tzAdmin['seller_name'] ?? null;

// Counts for the sidebar badges and the notification bell (cheap aggregate queries).
$tzCounts = ['unfulfilled' => 0, 'unpaid' => 0, 'pending_reviews' => 0, 'new_messages' => 0, 'low_stock' => 0, 'today_orders' => 0, 'pending_sellers' => 0];
try {
    if ($tzSellerId !== null) {
        require_once __DIR__ . '/../../core/marketplace.php';
        $tzCounts['unfulfilled'] = count(seller_orders_to_fulfil($conn, $tzSellerId));
        $st = $conn->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status <> 'archived' AND inventory <= low_stock_threshold");
        $st->execute([$tzSellerId]);
        $tzCounts['low_stock'] = (int) $st->fetchColumn();
    } else {
    $tzCounts = array_map('intval', $conn->query("SELECT
        (SELECT COUNT(*) FROM orders WHERE status <> 'Cancelled' AND fulfillment_status <> 'fulfilled') AS unfulfilled,
        (SELECT COUNT(*) FROM orders WHERE status <> 'Cancelled' AND payment_status = 'pending') AS unpaid,
        (SELECT COUNT(*) FROM reviews WHERE status = 'Pending') AS pending_reviews,
        (SELECT COUNT(*) FROM contact_messages WHERE status = 'new') AS new_messages,
        (SELECT COUNT(*) FROM products WHERE status <> 'archived' AND inventory <= low_stock_threshold) AS low_stock,
        (SELECT COUNT(*) FROM orders WHERE order_date >= CURDATE()) AS today_orders,
        (SELECT COUNT(*) FROM seller_applications WHERE status = 'pending') AS pending_sellers")->fetch(PDO::FETCH_ASSOC));
    }
} catch (PDOException $e) {
    log_exception($e, 'admin-shell-counts');
}
// Bell badge = notifications not yet seen this session (core/notifications.php; the dropdown loads api/notifications.php)
require_once __DIR__ . '/../../core/notifications.php';
$tzBellCount = admin_notifications($conn)['unseen'];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <title><?php if (isset($pageTitle)) echo $tzE($pageTitle) . ' · '; ?>TechZone Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="<?= $tzE($adminCsrfToken) ?>">
    <link rel="preload" href="<?= $tzE(admin_url('assets/fonts/inter-latin-400-normal.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= $tzAsset('assets/css/admin-style.css') ?>">
    <script>
        window.TZ_CAPS = <?= json_encode(admin_capabilities()) ?>;
        window.TZ_ROLE = <?= json_encode($adminRole) ?>;
        window.TZ_SELLER = <?= json_encode($tzSellerId !== null ? ['id' => $tzSellerId, 'name' => $tzSellerName] : null) ?>;
    </script>
<?php if (!empty($useGrid)): ?>
    <!-- AG Grid Community 36.2.0 (MIT, vendored: assets/vendor/ag-grid/LICENSE.txt) + our wrapper -->
    <script src="<?= $tzE(admin_url('assets/vendor/ag-grid/ag-grid-community.min.js')) ?>"></script>
    <script src="<?= $tzAsset('assets/js/grid.js') ?>"></script>
<?php endif; ?>
</head>
<body class="admin-body<?= $tzSellerId !== null ? ' is-seller' : '' ?>">
<?php include __DIR__ . '/url-script.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="sidebar-backdrop" data-sidebar-close></div>
<div class="admin-content">
    <header class="admin-header">
        <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-search">
            <button type="button" class="search-trigger" data-search-open aria-haspopup="dialog" aria-label="Search (Ctrl K)">
                <i class="fas fa-magnifying-glass"></i>
                <span class="search-trigger-text"><?= $tzSellerId !== null ? 'Search your orders and products' : 'Search orders, products, customers' ?></span>
                <kbd class="search-kbd" data-kbd-mod>⌘K</kbd>
            </button>
        </div>
        <div class="admin-header-right">
            <?php if ($tzSellerId !== null): ?>
            <a class="seller-chip" href="<?= $tzE(admin_url('manage/store.php')) ?>" title="Your shop on TechZone">
                <i class="fas fa-store" aria-hidden="true"></i>
                <span class="seller-chip-name"><?= $tzE($tzSellerName ?? 'Your shop') ?></span>
                <span class="role-badge role-seller">Seller</span>
            </a>
            <?php endif; ?>
            <div class="dropdown ntf" id="ntf">
                <button type="button" class="topbar-icon" id="ntfBell" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-haspopup="true"
                        aria-label="Notifications<?= $tzBellCount ? ' (' . $tzBellCount . ' new)' : '' ?>">
                    <i class="fas fa-bell"></i>
                    <span class="ntf-badge" id="ntfBadge"<?= $tzBellCount ? '' : ' hidden' ?> aria-hidden="true"><?= $tzBellCount > 99 ? '99+' : (int) $tzBellCount ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-menu ntf-menu" aria-labelledby="ntfBell">
                    <div class="notif-head"><strong>Notifications</strong><button type="button" class="ntf-seen" id="ntfSeen" hidden>Mark all as seen</button></div>
                    <div class="notif-list" id="ntfList" aria-live="polite"><div class="notif-empty">Loading…</div></div>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="user-menu-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
                    <span class="avatar" aria-hidden="true"><?= $tzE($adminInitial) ?></span>
                    <span class="who">
                        <strong><?= $tzE($adminName) ?></strong>
                        <?php if ($tzSellerId === null): ?><span class="role-badge role-<?= $tzE($adminRole) ?>"><?= $tzE($adminRoleLabel) ?></span><?php endif; ?>
                    </span>
                    <i class="fas fa-chevron-down caret"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end account-menu">
                    <li class="account-head">
                        <strong><?= $tzE($adminName) ?></strong>
                        <span><?= $tzE($tzAdmin['email'] ?? '') ?></span>
                        <span class="role-badge role-<?= $tzE($adminRole) ?>"><?= $tzE($adminRoleLabel) ?><?= $tzSellerName ? ' · ' . $tzE($tzSellerName) : '' ?></span>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= $tzE(admin_url('manage/profile.php')) ?>"><i class="fas fa-user"></i> Profile</a></li>
                    <?php if ($tzSellerId !== null): ?><li><a class="dropdown-item" href="<?= $tzE(admin_url('manage/store.php')) ?>"><i class="fas fa-store"></i> Store profile</a></li><?php endif; ?>
                    <li><a class="dropdown-item" href="<?= $tzE(rtrim(dirname(admin_portal_base_path() ?: '/'), '/') . '/MarketingSite/index.php') ?>" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> View storefront</a></li>
                    <li><button type="button" class="dropdown-item" data-shortcuts-open><i class="fas fa-keyboard"></i> Keyboard shortcuts <kbd class="ms-auto">?</kbd></button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= $tzE(admin_url('api/logout.php')) ?>"><i class="fas fa-arrow-right-from-bracket"></i> Log out</a></li>
                </ul>
            </div>
        </div>
    </header>
    <div class="save-bar" id="saveBar" hidden role="region" aria-label="Unsaved changes">
        <span class="save-bar-text"><i class="fas fa-circle-exclamation"></i> Unsaved changes</span>
        <span class="save-bar-actions">
            <button type="button" class="btn btn-sm btn-ghost-light" data-save-discard>Discard</button>
            <button type="button" class="btn btn-sm btn-brand" data-save-submit>Save</button>
        </span>
    </div>
    <main class="admin-page-content" id="main">
        <?php if (!empty($pageBack)): ?>
            <a class="page-back" href="<?= $tzE($pageBack['href']) ?>"><i class="fas fa-arrow-left"></i> <?= $tzE($pageBack['label']) ?></a>
        <?php endif; ?>
        <div class="page-head">
            <div class="page-head-text">
                <h1 class="page-title"><?= $tzE($pageTitle ?? 'Home') ?></h1>
                <?php if (!empty($pageSubtitle)): ?><p class="page-sub"><?= $tzE($pageSubtitle) ?></p><?php endif; ?>
            </div>
            <?php if (!empty($pageActions)): ?><div class="page-actions"><?= $pageActions ?></div><?php endif; ?>
        </div>
