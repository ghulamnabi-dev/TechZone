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
    storefront_card_prime($conn, $items);
    $h = '<section class="section section--rv" aria-labelledby="rv-title"><div class="container">'
       . '<div class="section-head"><h2 class="section-head__title section-head__title--sm" id="rv-title">Recently viewed</h2></div>'
       . '<div class="pgrid pgrid--scroll">';
    foreach ($items as $p) $h .= render_product_card($p, $promos);
    return $h . '</div></div></section>';
}

/** The six header categories, matched against the categories table by name. */
function storefront_nav_categories(array $categories): array {
    $wanted = ['Components' => 'Components', 'Peripherals' => 'Gaming Peripherals', 'Monitors' => 'Monitors',
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
require_once __DIR__ . '/../../core/catalog.php';
require_once __DIR__ . '/../../core/wishlist.php';
require_once __DIR__ . '/../../core/settings.php';
$tzSelf = basename($_SERVER['PHP_SELF'] ?? '');
$tzPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
$tzIsHome = $tzSelf === 'index.php' && strpos($tzPath, '/shop/') === false && strpos($tzPath, '/sellers/') === false && strpos($tzPath, '/sell/') === false && strpos($tzPath, '/help/') === false;
try {
    $tzAllCategories = $conn->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tzAllCategories = [];
}
$GLOBALS['tz_categories'] = $tzAllCategories;
try {
    $tzTree = category_tree($conn);
} catch (PDOException $e) {
    $tzTree = [];
}
$GLOBALS['tz_category_tree'] = $tzTree;
$tzNavCategories = storefront_nav_categories($tzAllCategories);
$tzComponents = null;
foreach ($tzTree as $c) {
    if (($c['slug'] ?? '') === 'components' || strcasecmp($c['name'], 'Components') === 0) $tzComponents = $c;
}
if ($tzComponents) {   // builder order: processor first, cooling last
    $tzPartOrder = ['cpu', 'motherboard', 'memory', 'gpu', 'storage', 'psu', 'case', 'cooler'];
    usort($tzComponents['children'], static function ($a, $b) use ($tzPartOrder) {
        $x = array_search($a['part_type'], $tzPartOrder, true); $y = array_search($b['part_type'], $tzPartOrder, true);
        return [$x === false ? 99 : $x, $a['name']] <=> [$y === false ? 99 : $y, $b['name']];
    });
}
$tzCurrentCategory = ($tzSelf === 'products.php' && isset($_GET['category']) && is_scalar($_GET['category'])) ? (int) $_GET['category'] : 0;
$tzSearch = ($tzSelf === 'products.php' && isset($_GET['search']) && is_string($_GET['search'])) ? $_GET['search'] : '';
$tzCartCount = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
try {
    $tzWishCount = count(wishlist_ids($conn));
} catch (Throwable $e) {
    $tzWishCount = 0;
}
$tzLoggedIn = isset($_SESSION['customer_id']);
$tzFirstName = $tzLoggedIn ? (explode(' ', trim((string) getCustomerName()))[0] ?: 'Account') : '';
$tzE = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$tzNotice = trim((string) setting('store_notice', store_setting_defaults()['store_notice']));
$tzAsset = static fn(string $rel) => site_url('assets/' . $rel) . '?v=' . (int) @filemtime(__DIR__ . '/../assets/' . $rel);
$tzPagesCss = __DIR__ . '/../assets/css/pages.css';
$tzNavCurrent = static function (array $c) use ($tzCurrentCategory, $tzAllCategories): bool {
    if ($tzCurrentCategory === (int) $c['id']) return true;
    foreach ($tzAllCategories as $x) {
        if ((int) $x['id'] === $tzCurrentCategory && (int) ($x['parent_id'] ?? 0) === (int) $c['id']) return true;
    }
    return false;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="theme-color" content="#FFD400">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' - ' : ''; ?>TechZone.pk</title>
    <?php if (!empty($pageDescription)): ?><meta name="description" content="<?php echo $tzE($pageDescription); ?>"><?php endif; ?>
    <link rel="icon" href="<?php echo $tzE(site_url('assets/images/favicon.svg')); ?>" type="image/svg+xml">
    <link rel="icon" href="<?php echo $tzE(site_url('assets/images/favicon-32.png')); ?>" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="<?php echo $tzE(site_url('assets/images/apple-touch-icon.png')); ?>">
    <link rel="preload" href="<?php echo $tzE(site_url('assets/fonts/archivo/archivo-latin-wdth-normal.woff2')); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $tzE(site_url('assets/fonts/manrope-latin-400-normal.woff2')); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo $tzE(site_url('assets/fonts/manrope-latin-700-normal.woff2')); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $tzE($tzAsset('css/tokens.css')); ?>">
    <link rel="stylesheet" href="<?php echo $tzE($tzAsset('css/style.css')); ?>">
    <?php if (is_file($tzPagesCss)): ?>
    <link rel="stylesheet" href="<?php echo $tzE($tzAsset('css/pages.css')); ?>">
    <?php endif; ?>
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
    <script src="<?php echo htmlspecialchars(marketing_script_url('storefront.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</head>
<body class="<?php echo $tzE(trim(($tzIsHome ? 'page-home ' : '') . ($pageBodyClass ?? ''))); ?>">
<a class="skip-link" href="#main">Skip to content</a>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs><linearGradient id="tzHalf"><stop offset="50%" stop-color="#1B1D22"/><stop offset="50%" stop-color="#D9DCE1"/></linearGradient></defs></svg>
<?php if ($tzNotice !== ''): ?>
<div class="promo-bar"><div class="container"><p class="promo-bar__text"><?php echo $tzE($tzNotice); ?></p></div></div>
<?php endif; ?>

<header class="site-header">
  <div class="container site-header__row">
    <button class="icon-btn site-header__menu d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteMenu" aria-controls="siteMenu" aria-label="Open menu"><?php echo tz_icon('menu', 24); ?></button>

    <a class="wordmark" href="<?php echo $tzE(site_url('index.php')); ?>" aria-label="TechZone home"><span class="wordmark__mark" aria-hidden="true"></span><span class="wordmark__text">TechZone</span></a>

    <form class="site-search d-none d-lg-flex" role="search" method="GET" action="<?php echo $tzE(site_url('shop/products.php')); ?>">
      <label class="visually-hidden" for="siteSearch">Search parts</label>
      <?php echo tz_icon('search', 18); ?>
      <input type="search" id="siteSearch" name="search" placeholder="Search parts, brands, models" value="<?php echo $tzE($tzSearch); ?>" autocomplete="off" enterkeyhint="search">
      <kbd class="site-search__kbd" aria-hidden="true" title="Press / to search">/</kbd>
    </form>

    <div class="site-header__actions">
      <a class="btn--secondary site-header__build d-none d-lg-inline-flex" href="<?php echo $tzE(site_url('builder.php')); ?>">Build a PC</a>
      <a class="head-link d-none d-lg-inline-flex" href="<?php echo $tzE(site_url('track.php')); ?>">Track</a>
      <?php if ($tzLoggedIn): ?>
      <div class="dropdown">
        <a class="head-link head-link--account" href="#" id="accountMenu" data-bs-toggle="dropdown" aria-expanded="false"><?php echo tz_icon('user', 20); ?><span class="d-none d-sm-inline"><?php echo $tzE($tzFirstName); ?></span><span class="visually-hidden d-sm-none">Account</span></a>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="accountMenu">
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('account/orders.php')); ?>">Orders</a></li>
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('account/profile.php')); ?>">Profile</a></li>
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('wishlist.php')); ?>">Wishlist</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?php echo $tzE(site_url('auth/logout.php')); ?>"
                 onclick="if(typeof SessionManager !== 'undefined') { SessionManager.logout(); return false; }">Sign out</a></li>
        </ul>
      </div>
      <?php else: ?>
      <a class="head-link head-link--account" href="<?php echo $tzE(site_url('auth/login.php')); ?>"><?php echo tz_icon('user', 20); ?><span class="d-none d-sm-inline">Sign in</span><span class="visually-hidden d-sm-none">Sign in</span></a>
      <?php endif; ?>
      <a class="nav-icon-btn wish-link" href="<?php echo $tzE(site_url('wishlist.php')); ?>" aria-label="Wishlist<?php echo $tzWishCount ? ', ' . $tzWishCount . ' saved' : ''; ?>">
        <?php echo tz_icon('heart', 22); ?>
        <span class="wish-count"<?php echo $tzWishCount ? '' : ' hidden'; ?>><?php echo (int) $tzWishCount; ?></span>
      </a>
      <a class="nav-icon-btn cart-link" href="<?php echo $tzE(site_url('shop/cart.php')); ?>" title="Cart" aria-label="Cart<?php echo $tzCartCount ? ', ' . $tzCartCount . ' items' : ''; ?>">
        <?php echo tz_icon('cart', 22); ?>
        <?php if ($tzCartCount > 0): ?><span class="nav-cart-badge"><?php echo $tzCartCount; ?></span><?php endif; ?>
      </a>
    </div>
  </div>
  <form class="site-search site-search--mobile d-lg-none" role="search" method="GET" action="<?php echo $tzE(site_url('shop/products.php')); ?>">
    <label class="visually-hidden" for="siteSearchBar">Search parts</label>
    <?php echo tz_icon('search', 18); ?>
    <input type="search" id="siteSearchBar" name="search" placeholder="Search parts, brands, models" value="<?php echo $tzE($tzSearch); ?>" autocomplete="off" enterkeyhint="search">
  </form>
</header>

<nav class="cat-bar d-none d-lg-block" aria-label="Categories">
  <div class="container cat-bar__row">
    <ul class="cat-nav">
      <?php foreach ($tzNavCategories as $c): ?>
        <?php if ($tzComponents && (int) $c['id'] === (int) $tzComponents['id'] && $tzComponents['children']): ?>
        <li class="cat-nav__item dropdown mega" data-mega>
          <button class="cat-nav__link cat-nav__btn" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"<?php echo $tzNavCurrent($c) ? ' aria-current="true"' : ''; ?>><?php echo $tzE($c['label']); ?><?php echo tz_icon('down', 14); ?></button>
          <div class="dropdown-menu mega__panel">
            <div class="mega__grid">
              <ul class="mega__list">
                <?php foreach ($tzComponents['children'] as $ch): ?>
                <li><a class="mega__row" href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $ch['id'])); ?>">
                  <span class="mega__glyph"><?php echo spec_glyph((string) $ch['part_type'], 28); ?></span>
                  <span class="mega__name"><?php echo $tzE($ch['name']); ?></span>
                  <span class="mega__count"><?php echo (int) $ch['product_count']; ?> <?php echo (int) $ch['product_count'] === 1 ? 'product' : 'products'; ?></span>
                  <span class="mega__from"><?php echo $ch['from_price'] !== null ? 'from ' . format_pkr($ch['from_price']) : ''; ?></span>
                </a></li>
                <?php endforeach; ?>
              </ul>
              <div class="mega__side">
                <p class="mega__side-title">Build a PC at today's rates</p>
                <p class="mega__side-text">Pick a processor and the builder only shows parts that fit.</p>
                <a class="btn--on-black" href="<?php echo $tzE(site_url('builder.php')); ?>">Open the builder</a>
                <a class="mega__all" href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $tzComponents['id'])); ?>">All components (<?php echo (int) $tzComponents['product_count']; ?>)</a>
              </div>
            </div>
          </div>
        </li>
        <?php else: ?>
        <li class="cat-nav__item"><a class="cat-nav__link" href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"<?php echo $tzNavCurrent($c) ? ' aria-current="page"' : ''; ?>><?php echo $tzE($c['label']); ?></a></li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ul>
    <ul class="cat-nav cat-nav--aside">
      <li class="cat-nav__item"><a class="cat-nav__link" href="<?php echo $tzE(site_url('rates.php')); ?>">Today's rates</a></li>
      <li class="cat-nav__item"><a class="cat-nav__link" href="<?php echo $tzE(site_url('deals.php')); ?>">Price drops</a></li>
      <li class="cat-nav__item"><a class="cat-nav__link" href="<?php echo $tzE(site_url('sellers/')); ?>">Sellers</a></li>
    </ul>
  </div>
</nav>

<div class="offcanvas offcanvas-start site-menu" tabindex="-1" id="siteMenu" aria-labelledby="siteMenuLabel">
  <div class="offcanvas-header">
    <a class="wordmark" id="siteMenuLabel" href="<?php echo $tzE(site_url('index.php')); ?>"><span class="wordmark__mark" aria-hidden="true"></span><span class="wordmark__text">TechZone</span></a>
    <button type="button" class="icon-btn" data-bs-dismiss="offcanvas" aria-label="Close menu"><?php echo tz_icon('close', 22); ?></button>
  </div>
  <div class="offcanvas-body">
    <div class="site-menu__actions">
      <a class="btn--primary" href="<?php echo $tzE(site_url('builder.php')); ?>">Build a PC</a>
      <a class="btn--secondary" href="<?php echo $tzE(site_url('rates.php')); ?>">Today's rates</a>
    </div>
    <?php if ($tzComponents && $tzComponents['children']): ?>
    <p class="site-menu__label">Components</p>
    <ul class="site-menu__list">
      <?php foreach ($tzComponents['children'] as $ch): ?>
        <li><a href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $ch['id'])); ?>"><span><?php echo $tzE($ch['name']); ?></span><span class="site-menu__meta"><?php echo $ch['from_price'] !== null ? 'from ' . format_pkr($ch['from_price']) : ''; ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <p class="site-menu__label">Shop</p>
    <ul class="site-menu__list">
      <?php foreach ($tzNavCategories as $c): if ($tzComponents && (int) $c['id'] === (int) $tzComponents['id']) continue; ?>
        <li><a href="<?php echo $tzE(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"><span><?php echo $tzE($c['label']); ?></span></a></li>
      <?php endforeach; ?>
      <li><a href="<?php echo $tzE(site_url('deals.php')); ?>"><span>Price drops this week</span></a></li>
      <li><a href="<?php echo $tzE(site_url('shop/products.php')); ?>"><span>All products</span></a></li>
    </ul>
    <p class="site-menu__label">Your account</p>
    <ul class="site-menu__list">
      <li><a href="<?php echo $tzE(site_url('track.php')); ?>"><span>Track your order</span></a></li>
      <li><a href="<?php echo $tzE(site_url('wishlist.php')); ?>"><span>Wishlist</span><span class="site-menu__meta wish-count-text"><?php echo $tzWishCount ? (int) $tzWishCount . ' saved' : ''; ?></span></a></li>
      <?php if ($tzLoggedIn): ?>
        <li><a href="<?php echo $tzE(site_url('account/orders.php')); ?>"><span>Orders</span></a></li>
        <li><a href="<?php echo $tzE(site_url('account/profile.php')); ?>"><span>Profile</span></a></li>
      <?php else: ?>
        <li><a href="<?php echo $tzE(site_url('auth/login.php')); ?>"><span>Sign in</span></a></li>
        <li><a href="<?php echo $tzE(site_url('auth/signup.php')); ?>"><span>Create account</span></a></li>
      <?php endif; ?>
    </ul>
    <p class="site-menu__label">TechZone</p>
    <ul class="site-menu__list">
      <li><a href="<?php echo $tzE(site_url('store.php')); ?>"><span>Visit our store</span></a></li>
      <li><a href="<?php echo $tzE(site_url('sellers/')); ?>"><span>Sellers</span></a></li>
      <li><a href="<?php echo $tzE(site_url('sell/')); ?>"><span>Sell on TechZone</span></a></li>
      <li><a href="<?php echo $tzE(site_url('help/')); ?>"><span>Help centre</span></a></li>
    </ul>
  </div>
</div>

<main id="main">
