<?php
if (!function_exists('admin_url')) {
    require_once __DIR__ . '/../config/paths.php';
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="admin-sidebar">
    <a href="<?php echo htmlspecialchars(admin_url('manage/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>" class="sidebar-brand text-decoration-none">
        <div class="sidebar-brand-icon">
      <i class="fas fa-microchip"></i>
        </div>
        <div class="sidebar-brand-text">
            TechZone.pk
            <span>Admin Panel</span>
        </div>
    </a>

    <div class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <nav class="nav flex-column">
            <a class="nav-link <?php if($currentPage == 'dashboard.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-gauge-high"></i> Dashboard
            </a>
            <a class="nav-link <?php if($currentPage == 'orders.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/orders.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-shopping-bag"></i> Orders
            </a>
            <a class="nav-link <?php if($currentPage == 'products.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/products.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-box-open"></i> Products
            </a>
            <a class="nav-link <?php if($currentPage == 'categories.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/categories.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-tags"></i> Categories
            </a>
        </nav>

        <div class="nav-section-label">People</div>
        <nav class="nav flex-column">
            <a class="nav-link <?php if($currentPage == 'customers.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/customers.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-users"></i> Customers
            </a>
            <a class="nav-link <?php if($currentPage == 'reviews.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/reviews.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-star"></i> Reviews
            </a>
            <a class="nav-link <?php if($currentPage == 'contact_messages.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/contact_messages.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-envelope"></i> Messages
            </a>
        </nav>

        <div class="nav-section-label">Marketing</div>
        <nav class="nav flex-column">
            <a class="nav-link <?php if($currentPage == 'promotions.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/promotions.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-percent"></i> Promotions
            </a>
            <a class="nav-link <?php if($currentPage == 'analytics.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/analytics.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
        </nav>

        <div class="sidebar-divider"></div>
        <nav class="nav flex-column">
            <a class="nav-link <?php if($currentPage == 'profile.php') echo 'active'; ?>" href="<?php echo htmlspecialchars(admin_url('manage/profile.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-user-circle"></i> Profile
            </a>
            <a class="nav-link logout-link" href="<?php echo htmlspecialchars(admin_url('api/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
</div>
