<?php
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/js-assets.php';

/**
 * Fix image path for marketing site
 */
/**
 * Resolve product/promotion image src for Marketing Site.
 * Uses absolute web path to Admin Portal/uploads so it works from index.php, shop/*, account/*, etc.
 */
function getProductImagePath($imagelink, $addCacheBuster = true) {
    if (empty($imagelink)) return '';
    $trim = trim($imagelink);
    if (strpos($trim, 'http://') === 0 || strpos($trim, 'https://') === 0) {
        return $trim;
    }
    $rel = ltrim(str_replace('\\', '/', $trim), '/');
    if (strpos($rel, 'uploads/') === 0) {
        $path = admin_portal_asset_url($rel);
        if ($addCacheBuster) {
            $path .= (strpos($path, '?') !== false ? '&' : '?') . 't=' . time();
        }
        return $path;
    }
    if (strpos($rel, 'uploads/') !== false) {
        $parts = explode('uploads/', $rel, 2);
        if (isset($parts[1])) {
            $path = admin_portal_asset_url('uploads/' . $parts[1]);
            if ($addCacheBuster) {
                $path .= (strpos($path, '?') !== false ? '&' : '?') . 't=' . time();
            }
            return $path;
        }
    }
    return $trim;
}

function getPromotionImagePath($imageUrl, $addCacheBuster = true) {
    return getProductImagePath($imageUrl, $addCacheBuster);
}

/**
 * Path to static assets (images, etc.). Use for hero, banners, and other site images.
 * Example: asset_url('images/hero/slide1.png') => 'assets/images/hero/slide1.png'
 */
function asset_url($path) {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if (strpos($path, 'assets/') === 0) {
        return site_url($path);
    }
    return site_url('assets/' . $path);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>TechZone.pk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(site_url('assets/css/style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    <script>
    window.MARKETING_SITE_BASE = <?php echo json_encode(marketing_site_base_path()); ?>;
    window.marketingUrl = function (rel) {
        rel = String(rel || '').replace(/^\/+/, '');
        var b = window.MARKETING_SITE_BASE || '';
        return (b ? b + '/' : '') + rel;
    };
    </script>
    <script src="<?php echo htmlspecialchars(marketing_script_url('common.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(marketing_script_url('session.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body class="<?php echo (basename($_SERVER['PHP_SELF']) ?? '') === 'index.php' ? 'page-home' : ''; ?>">
<main>

<nav class="navbar navbar-expand-lg site-navbar">
  <div class="container">

    <!-- Logo -->
    <a class="navbar-brand site-brand" href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">
      <span class="brand-icon-wrap"><i class="fas fa-microchip"></i></span>
      <span class="brand-name">TechZone</span>
    </a>

    <!-- Mobile Toggle -->
    <button class="navbar-toggler border-0 shadow-none" type="button"
            data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">

      <!-- Center Nav Links -->
      <ul class="navbar-nav mx-auto">
        <li class="nav-item">
          <a class="nav-link site-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link site-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'products.php') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>">Products</a>
        </li>
        <li class="nav-item">
          <a class="nav-link site-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'about.php') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('about.php'), ENT_QUOTES, 'UTF-8'); ?>">About Us</a>
        </li>
        <li class="nav-item">
          <a class="nav-link site-nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'contact.php') ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(site_url('contact.php'), ENT_QUOTES, 'UTF-8'); ?>">Contact</a>
        </li>
      </ul>

      <!-- Right Actions -->
      <ul class="navbar-nav align-items-center gap-1">

        <!-- Search -->
        <li class="nav-item">
          <a class="nav-icon-btn" href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" title="Search Products">
            <i class="fas fa-search"></i>
          </a>
        </li>

        <!-- Cart -->
        <li class="nav-item">
          <a class="nav-icon-btn" href="<?php echo htmlspecialchars(site_url('shop/cart.php'), ENT_QUOTES, 'UTF-8'); ?>" title="Cart">
            <i class="fas fa-shopping-cart"></i>
            <?php
            if (isset($_SESSION['cart']) && count($_SESSION['cart']) > 0):
              $cart_count = count($_SESSION['cart']);
            ?>
            <span class="nav-cart-badge"><?php echo $cart_count; ?></span>
            <?php endif; ?>
          </a>
        </li>

        <!-- User -->
        <?php if (isset($_SESSION['customer_id'])): ?>
        <li class="nav-item dropdown">
          <a class="nav-icon-btn dropdown-toggle-custom" href="#"
             id="navUserDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
            <i class="fas fa-user-circle"></i>
            <span class="nav-username d-none d-xl-inline"><?php echo htmlspecialchars(getCustomerName()); ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navUserDropdown">
            <li><a class="dropdown-item" href="<?php echo htmlspecialchars(site_url('account/profile.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-user-circle me-2 text-muted"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="<?php echo htmlspecialchars(site_url('account/orders.php'), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-shopping-bag me-2 text-muted"></i>Order History</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#"
                   onclick="if(typeof SessionManager !== 'undefined') { SessionManager.logout(); return false; } else { window.location.href=<?php echo json_encode(site_url('auth/logout.php')); ?>; return false; }">
                <i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
          </ul>
        </li>
        <?php else: ?>
        <li class="nav-item">
          <a class="btn-login" href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-sign-in-alt"></i>
            <span>Login</span>
          </a>
        </li>
        <li class="nav-item">
          <a class="btn-signup" href="<?php echo htmlspecialchars(site_url('auth/signup.php'), ENT_QUOTES, 'UTF-8'); ?>">Sign Up</a>
        </li>
        <?php endif; ?>

      </ul>
    </div>
  </div>
</nav>
