<?php
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/js-assets.php';
require_once __DIR__ . '/format.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/inventory.php';

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

/**
 * "Add to cart" button for product listings: a tiny POST form (with CSRF token)
 * to shop/cart.php instead of a state-changing GET link.
 */
function cart_add_form(int $productId, string $btnClass, string $labelHtml): string {
    return '<form method="post" action="' . htmlspecialchars(site_url('shop/cart.php'), ENT_QUOTES, 'UTF-8') . '" class="d-inline m-0 cart-add-form">'
        . csrf_field()
        . '<input type="hidden" name="add" value="' . $productId . '">'
        . '<button type="submit" class="' . htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8') . '">' . $labelHtml . '</button>'
        . '</form>';
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

/* ---------- storefront read helpers (presentation data only) ---------- */

/** Active promotions (same query the home page used). */
function storefront_active_promotions(PDO $conn, int $limit = 3): array {
    try {
        $stmt = $conn->prepare("SELECT * FROM promotions WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY display_order ASC, created_at DESC LIMIT " . (int) $limit);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/** product_id => ['avg' => float, 'count' => int] from approved reviews. */
function storefront_review_stats(PDO $conn): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [];
    try {
        foreach ($conn->query("SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS n FROM reviews WHERE status = 'Approved' GROUP BY product_id") as $r) {
            $cache[(int) $r['product_id']] = ['avg' => (float) $r['avg_rating'], 'count' => (int) $r['n']];
        }
    } catch (PDOException $e) {
        $cache = [];
    }
    return $cache;
}

/** category_id => number of visible products. */
function storefront_category_counts(PDO $conn): array {
    $out = [];
    try {
        $sql = "SELECT category_id, COUNT(*) AS n FROM products" . (hasIsActiveColumn($conn) ? " WHERE is_active = 1" : "") . " GROUP BY category_id";
        foreach ($conn->query($sql) as $r) {
            $out[(int) $r['category_id']] = (int) $r['n'];
        }
    } catch (PDOException $e) {
    }
    return $out;
}

/** Products by id, returned in the given id order (for "Recently viewed"). */
function storefront_products_by_ids(PDO $conn, array $ids): array {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id IN ($ph)" . (hasIsActiveColumn($conn) ? " AND p.is_active = 1" : "");
    $stmt = $conn->prepare($sql);
    $stmt->execute($ids);
    $byId = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $byId[(int) $row['id']] = $row;
    $out = [];
    foreach ($ids as $id) if (isset($byId[$id])) $out[] = $byId[$id];
    return $out;
}

/** "Recently viewed" strip (hidden when empty). */
function render_recently_viewed(PDO $conn, int $excludeId = 0): string {
    $items = storefront_products_by_ids($conn, recently_viewed_ids($excludeId, 6));
    if (!$items) return '';
    $promos = storefront_active_promotions($conn);
    $stats = storefront_review_stats($conn);
    $h = '<section class="section" aria-labelledby="rv-title"><div class="container">'
       . '<div class="section-head"><h2 id="rv-title">Recently viewed</h2></div>'
       . '<div class="pgrid pgrid--scroll">';
    foreach ($items as $p) $h .= render_product_card($p, $promos, $stats[(int) $p['id']] ?? null);
    return $h . '</div></div></section>';
}

/** The six header categories, matched against the categories table by name. */
function storefront_nav_categories(array $categories): array {
    $wanted = ['Hardware' => 'Computer Hardware', 'Peripherals' => 'Gaming Peripherals', 'Monitors' => 'Monitors',
               'Networking' => 'Networking', 'Storage' => 'Storage Devices', 'Furniture' => 'Furniture'];
    $byName = [];
    foreach ($categories as $c) $byName[strtolower($c['name'])] = $c;
    $out = [];
    foreach ($wanted as $label => $name) {
        if (isset($byName[strtolower($name)])) $out[] = ['label' => $label] + $byName[strtolower($name)];
    }
    return $out;
}
?>
<?php
$tzSelf = basename($_SERVER['PHP_SELF'] ?? '');
$tzIsHome = $tzSelf === 'index.php' && strpos(str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? ''), '/shop/') === false;
try {
    $tzAllCategories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tzAllCategories = [];
}
$GLOBALS['tz_categories'] = $tzAllCategories;
$tzNavCategories = storefront_nav_categories($tzAllCategories);
$tzCurrentCategory = ($tzSelf === 'products.php' && isset($_GET['category']) && is_scalar($_GET['category'])) ? (int) $_GET['category'] : 0;
$tzSearch = ($tzSelf === 'products.php' && isset($_GET['search']) && is_string($_GET['search'])) ? $_GET['search'] : '';
$tzCartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
$tzLoggedIn = isset($_SESSION['customer_id']);
$tzFirstName = $tzLoggedIn ? (explode(' ', trim((string) getCustomerName()))[0] ?: 'Account') : '';
$tzE = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - ' : ''; ?>TechZone.pk</title>
    <link rel="preload" href="<?php echo $tzE(site_url('assets/fonts/manrope-latin-400-normal.woff2')); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $tzE(site_url('assets/fonts/manrope-latin-700-normal.woff2')); ?>" as="font" type="font/woff2" crossorigin>
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
<body class="<?php echo $tzIsHome ? 'page-home' : ''; ?>">
<a class="skip-link" href="#main">Skip to content</a>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs><linearGradient id="tzHalf"><stop offset="50%" stop-color="#15181e"/><stop offset="50%" stop-color="#d7dae0"/></linearGradient></defs></svg>
<?php if ($tzIsHome): ?>
<div class="promo-bar"><div class="container">Cash on delivery across Pakistan. Free delivery on orders over Rs 5,000.</div></div>
<?php endif; ?>

<header class="site-header">
  <div class="container site-header__row">
    <button class="icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteMenu" aria-controls="siteMenu" aria-label="Open menu"><?php echo tz_icon('menu', 22); ?></button>

    <a class="wordmark" href="<?php echo $tzE(site_url('index.php')); ?>"><span class="wordmark__mark" aria-hidden="true"></span>TechZone</a>

    <nav class="cat-nav d-none d-lg-flex" aria-label="Categories">
      <?php foreach ($tzNavCategories as $c): ?>
        <a href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"<?php echo $tzCurrentCategory === (int) $c['id'] ? ' aria-current="page"' : ''; ?>><?php echo $tzE($c['label']); ?></a>
      <?php endforeach; ?>
    </nav>

    <form class="site-search d-none d-lg-flex" role="search" method="GET" action="<?php echo $tzE(site_url('shop/products.php')); ?>">
      <label class="visually-hidden" for="siteSearch">Search products</label>
      <?php echo tz_icon('search', 18); ?>
      <input type="search" id="siteSearch" name="search" placeholder="Search parts and peripherals" value="<?php echo $tzE($tzSearch); ?>" autocomplete="off">
    </form>

    <div class="site-header__actions">
      <?php if ($tzLoggedIn): ?>
      <div class="dropdown">
        <a class="account-link" href="#" id="accountMenu" data-bs-toggle="dropdown" aria-expanded="false"><?php echo tz_icon('user', 20); ?><span class="d-none d-sm-inline"><?php echo $tzE($tzFirstName); ?></span></a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountMenu">
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('account/profile.php')); ?>">Profile</a></li>
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('account/orders.php')); ?>">Orders</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('auth/logout.php')); ?>"
                 onclick="if(typeof SessionManager !== 'undefined') { SessionManager.logout(); return false; }">Sign out</a></li>
        </ul>
      </div>
      <?php else: ?>
      <a class="account-link" href="<?php echo $tzE(site_url('auth/login.php')); ?>"><?php echo tz_icon('user', 20); ?><span class="d-none d-sm-inline">Sign in</span></a>
      <?php endif; ?>
      <a class="nav-icon-btn cart-link" href="<?php echo $tzE(site_url('shop/cart.php')); ?>" title="Cart" aria-label="Cart<?php echo $tzCartCount ? ', ' . $tzCartCount . ' items' : ''; ?>">
        <?php echo tz_icon('cart', 22); ?>
        <?php if ($tzCartCount > 0): ?><span class="nav-cart-badge"><?php echo $tzCartCount; ?></span><?php endif; ?>
      </a>
    </div>
  </div>
</header>

<div class="offcanvas offcanvas-start site-menu" tabindex="-1" id="siteMenu" aria-labelledby="siteMenuLabel">
  <div class="offcanvas-header">
    <a class="wordmark" id="siteMenuLabel" href="<?php echo $tzE(site_url('index.php')); ?>"><span class="wordmark__mark" aria-hidden="true"></span>TechZone</a>
    <button type="button" class="icon-btn" data-bs-dismiss="offcanvas" aria-label="Close menu"><?php echo tz_icon('close', 22); ?></button>
  </div>
  <div class="offcanvas-body">
    <form class="site-search site-search--block" role="search" method="GET" action="<?php echo $tzE(site_url('shop/products.php')); ?>">
      <label class="visually-hidden" for="siteSearchMobile">Search products</label>
      <?php echo tz_icon('search', 18); ?>
      <input type="search" id="siteSearchMobile" name="search" placeholder="Search parts and peripherals" value="<?php echo $tzE($tzSearch); ?>" autocomplete="off">
    </form>
    <p class="site-menu__label">Shop</p>
    <ul class="site-menu__list">
      <li><a href="<?php echo $tzE(site_url('shop/products.php')); ?>">All products</a></li>
      <?php foreach ($tzAllCategories as $c): ?>
        <li><a href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"><?php echo $tzE($c['name']); ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="site-menu__label">TechZone</p>
    <ul class="site-menu__list">
      <li><a href="<?php echo $tzE(site_url('about.php')); ?>">About</a></li>
      <li><a href="<?php echo $tzE(site_url('contact.php')); ?>">Contact</a></li>
      <?php if ($tzLoggedIn): ?>
        <li><a href="<?php echo $tzE(site_url('account/orders.php')); ?>">Orders</a></li>
        <li><a href="<?php echo $tzE(site_url('account/profile.php')); ?>">Profile</a></li>
      <?php else: ?>
        <li><a href="<?php echo $tzE(site_url('auth/login.php')); ?>">Sign in</a></li>
        <li><a href="<?php echo $tzE(site_url('auth/signup.php')); ?>">Create account</a></li>
      <?php endif; ?>
    </ul>
  </div>
</div>

<main id="main">
