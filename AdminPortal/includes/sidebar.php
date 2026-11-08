<?php
/**
 * Sidebar navigation (docs/ADMIN.md "Information architecture").
 * Each item: [file, label, icon, badge count|null, capability|null, children].
 * A page marks itself active by file name; detail pages set $navActive (e.g. 'orders.php').
 */
if (!function_exists('admin_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
$currentPage = $navActive ?? basename($_SERVER['PHP_SELF']);
$tzCounts = $tzCounts ?? [];
$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$tzNav = [
    [
        ['dashboard.php', 'Home', 'fa-house', null, null, []],
        ['orders.php', 'Orders', 'fa-inbox', $tzCounts['unfulfilled'] ?? null, null, ['order.php']],
        ['products.php', 'Products', 'fa-tag', null, null, ['product.php', 'categories.php']],
        ['inventory.php', 'Inventory', 'fa-boxes-stacked', null, null, []],
        ['customers.php', 'Customers', 'fa-user-group', null, null, ['customer.php']],
        ['promotions.php', 'Discounts', 'fa-percent', null, null, []],
    ],
    [
        ['reviews.php', 'Reviews', 'fa-star', $tzCounts['pending_reviews'] ?? null, null, []],
        ['contact_messages.php', 'Messages', 'fa-envelope', $tzCounts['new_messages'] ?? null, null, []],
        ['analytics.php', 'Analytics', 'fa-chart-simple', null, null, []],
    ],
];
$tzSub = [
    'products.php' => [['products.php', 'All products'], ['categories.php', 'Categories']],
];
$settingsPages = ['settings.php'];
?>
<aside class="admin-sidebar" id="adminSidebar" aria-label="Main navigation">
    <a href="<?= $e(admin_url('manage/dashboard.php')) ?>" class="sidebar-brand">
        <span class="brand-mark" aria-hidden="true"><i class="fas fa-microchip"></i></span>
        <span class="brand-name">TechZone</span>
        <span class="brand-env">Admin</span>
    </a>

    <nav class="sidebar-nav">
        <?php foreach ($tzNav as $gi => $group): ?>
            <?php if ($gi > 0): ?><div class="nav-sep" role="separator"></div><?php endif; ?>
            <ul class="nav-list">
                <?php foreach ($group as [$file, $label, $icon, $badge, $cap, $children]):
                    if ($cap !== null && !can($cap)) continue;
                    $active = $currentPage === $file || in_array($currentPage, $children, true);
                ?>
                    <li>
                        <a class="nav-link<?= $active ? ' active' : '' ?>" href="<?= $e(admin_url('manage/' . $file)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                            <i class="fas <?= $icon ?>" aria-hidden="true"></i>
                            <span class="nav-label"><?= $e($label) ?></span>
                            <?php if (!empty($badge)): ?><span class="nav-badge"><?= (int) $badge ?></span><?php endif; ?>
                        </a>
                        <?php if ($active && isset($tzSub[$file])): ?>
                            <ul class="nav-sub">
                                <?php foreach ($tzSub[$file] as [$sf, $sl]): ?>
                                    <li><a class="nav-sublink<?= $currentPage === $sf || ($sf === $file && $currentPage === 'product.php') ? ' active' : '' ?>" href="<?= $e(admin_url('manage/' . $sf)) ?>"><?= $e($sl) ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </nav>

    <?php if (can('settings.write') || can('activity.view')): ?>
    <div class="sidebar-foot">
        <a class="nav-link<?= in_array($currentPage, $settingsPages, true) ? ' active' : '' ?>" href="<?= $e(admin_url('manage/settings.php')) ?>">
            <i class="fas fa-gear" aria-hidden="true"></i>
            <span class="nav-label">Settings</span>
        </a>
    </div>
    <?php endif; ?>
</aside>
