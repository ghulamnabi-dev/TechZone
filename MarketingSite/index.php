<?php
$pageTitle = 'Home';
require_once 'includes/header.php';
require_once 'config/database.php';
require_once __DIR__ . '/../core/inventory.php';

$activeWhere = hasIsActiveColumn($conn) ? " WHERE p.is_active = 1" : "";
$baseSelect = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";

// Pick up to $limit products with distinct image files (skip repeats, then top up from the rest)
$pickDistinct = static function (array $rows, int $limit, array $exclude = []): array {
    $picked = [];
    $used = [];
    foreach ($rows as $r) {
        $img = (string) ($r['imagelink'] ?? '');
        if ($img === '' || isset($used[$img]) || in_array((int) $r['id'], $exclude, true)) {
            continue;
        }
        $used[$img] = true;
        $picked[] = $r;
        if (count($picked) >= $limit) {
            return $picked;
        }
    }
    $ids = array_merge($exclude, array_map(static fn($r) => (int) $r['id'], $picked));
    foreach ($rows as $r) {
        if (count($picked) >= $limit) {
            break;
        }
        if (!in_array((int) $r['id'], $ids, true)) {
            $picked[] = $r;
        }
    }
    return $picked;
};

// Featured: best sellers, 8 distinct images
$bySold = $conn->query($baseSelect . $activeWhere . " ORDER BY p.sold DESC, p.id")->fetchAll(PDO::FETCH_ASSOC);
$featuredProducts = $pickDistinct($bySold, 8);

// New arrivals: most recently added, 4 distinct images
$byNewest = $conn->query($baseSelect . $activeWhere . " ORDER BY p.created_at DESC, p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$newProducts = $pickDistinct($byNewest, 4);

// Category tile cutouts: preferred product per category if in stock, else cheapest in-stock product with an image
$tilePreferred = ['Hardware' => 'RTX 4060', 'Peripherals' => 'Razer', 'Monitors' => 'Samsung Odyssey',
                  'Networking' => 'Archer', 'Storage' => '980 PRO', 'Furniture' => 'FlexiSpot'];
$tileProduct = static function (array $c) use ($bySold, $tilePreferred) {
    $pool = array_filter($bySold, static fn($p) => (int) $p['category_id'] === (int) $c['id'] && (int) $p['inventory'] > 0 && !empty($p['imagelink']));
    $needle = $tilePreferred[$c['label']] ?? '';
    foreach ($pool as $p) {
        if ($needle !== '' && stripos($p['name'], $needle) !== false) {
            return $p;
        }
    }
    usort($pool, static fn($x, $y) => [(float) $x['price'], (int) $x['id']] <=> [(float) $y['price'], (int) $y['id']]);
    return $pool ? reset($pool) : null;
};

// Hero product: Samsung Odyssey monitor, else the RTX 4060, else the top seller
$heroProduct = null;
foreach (['Samsung Odyssey%', '%RTX 4060%'] as $pattern) {
    $stmt = $conn->prepare($baseSelect . ($activeWhere ? $activeWhere . " AND" : " WHERE") . " p.name LIKE ? ORDER BY p.id LIMIT 1");
    $stmt->execute([$pattern]);
    if ($heroProduct = $stmt->fetch(PDO::FETCH_ASSOC)) {
        break;
    }
}
if (!$heroProduct && $featuredProducts) {
    $heroProduct = $featuredProducts[0];
}

$categories = $GLOBALS['tz_categories'] ?? [];
$navCategories = storefront_nav_categories($categories);
$promotions = storefront_active_promotions($conn);
$reviewStats = storefront_review_stats($conn);

$buildPcId = 0;
foreach ($categories as $c) {
    if (strcasecmp($c['name'], 'Computer Hardware') === 0) {
        $buildPcId = (int) $c['id'];
    }
}
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$catUrl = static fn(array $c) => site_url('shop/products.php?category=' . (int) $c['id']);
$heroLinks = array_values(array_filter($navCategories, static fn($c) => in_array($c['label'], ['Monitors', 'Peripherals', 'Networking'], true)));
$heroSale = $heroProduct ? promo_price_for_product($heroProduct, $promotions) : null;
?>

<section class="hero">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-lg-6">
                <h1 class="hero__title">Parts, peripherals and complete builds,<br class="d-none d-xl-inline"> in stock in Lahore.</h1>
                <p class="hero__lead">Genuine components with official warranty, delivered across Pakistan. Pay cash on delivery or with JazzCash.</p>
                <div class="hero__actions">
                    <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="btn btn-ink btn-lg-tz">Shop all products</a>
                    <a href="<?php echo $h(site_url('shop/products.php' . ($buildPcId ? '?category=' . $buildPcId : ''))); ?>" class="btn btn-line btn-lg-tz">Build a PC</a>
                </div>
                <?php if ($heroLinks): ?>
                <p class="hero__links">Popular:
                    <?php foreach ($heroLinks as $c): ?>
                        <a href="<?php echo $h($catUrl($c)); ?>"><?php echo $h($c['label']); ?></a>
                    <?php endforeach; ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <?php if ($heroProduct): ?>
                <a class="hero__panel" href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $heroProduct['id'])); ?>">
                    <img src="<?php echo $h(getProductImagePath($heroProduct['imagelink'])); ?>" alt="<?php echo $h($heroProduct['name']); ?>" width="863" height="533" fetchpriority="high">
                    <span class="hero__caption">
                        <?php echo price_tag($heroSale ? $heroSale['price'] : (float) $heroProduct['price'], 'lg'); ?>
                        <span class="hero__name"><?php echo $h($heroProduct['name']); ?></span>
                        <?php if ($heroSale): ?>
                            <span class="hero__note">Was <?php echo format_pkr($heroSale['was']); ?>, <?php echo $h($heroSale['label']); ?> with code <?php echo $h($heroSale['code']); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <ul class="trust-line">
        <li><?php echo tz_icon('truck'); ?>Cash on delivery nationwide</li>
        <li><?php echo tz_icon('rotate'); ?>7-day returns</li>
        <li><?php echo tz_icon('shield'); ?>Official warranty</li>
        <li><?php echo tz_icon('pin'); ?>Pickup in Lahore</li>
    </ul>
</div>

<section class="section" aria-labelledby="featured-title">
    <div class="container">
        <div class="section-head">
            <h2 id="featured-title">Featured</h2>
            <a class="text-link" href="<?php echo $h(site_url('shop/products.php')); ?>">View all</a>
        </div>
        <div class="pgrid pgrid--4">
            <?php foreach ($featuredProducts as $product) {
                echo render_product_card($product, $promotions, $reviewStats[(int) $product['id']] ?? null);
            } ?>
        </div>
    </div>
</section>

<?php if ($navCategories): ?>
<section class="section section--alt" aria-labelledby="cats-title">
    <div class="container">
        <div class="section-head">
            <h2 id="cats-title">Shop by category</h2>
            <a class="text-link" href="<?php echo $h(site_url('shop/products.php')); ?>">View all</a>
        </div>
        <ul class="cat-tiles">
            <?php foreach ($navCategories as $c): ?>
            <li>
                <a class="cat-tile" href="<?php echo $h($catUrl($c)); ?>">
                    <span class="cat-tile__img">
                        <?php $tp = $tileProduct($c); if ($tp): ?>
                            <img src="<?php echo $h(getProductImagePath($tp['imagelink'])); ?>" alt="" width="460" height="340" loading="lazy" decoding="async">
                        <?php endif; ?>
                    </span>
                    <span class="cat-tile__name"><?php echo $h($c['name']); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<section class="section" aria-labelledby="new-title">
    <div class="container">
        <div class="section-head">
            <h2 id="new-title">New arrivals</h2>
            <a class="text-link" href="<?php echo $h(site_url('shop/products.php?sort=newest')); ?>">View all</a>
        </div>
        <div class="pgrid pgrid--4">
            <?php foreach ($newProducts as $product) {
                echo render_product_card($product, $promotions, $reviewStats[(int) $product['id']] ?? null);
            } ?>
        </div>
    </div>
</section>

<?php if (count($promotions) > 0): ?>
<section class="section section--alt" aria-labelledby="offers-title">
    <div class="container">
        <div class="section-head">
            <h2 id="offers-title">Current offers</h2>
        </div>
        <div class="row g-4">
            <?php foreach (array_slice($promotions, 0, 2) as $promo): ?>
            <div class="col-md-6">
                <div class="offer">
                    <div class="offer__text">
                        <h3 class="offer__title"><?php echo $h($promo['title']); ?></h3>
                        <?php if (!empty($promo['description'])): ?>
                            <p class="offer__desc"><?php echo $h($promo['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($promo['end_date'])): ?>
                            <p class="offer__meta">Ends <?php echo $h(date('j F Y', strtotime($promo['end_date']))); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="offer__actions">
                        <?php if (!empty($promo['promo_code'])): ?>
                            <span class="code-pill" title="Enter this code in your cart"><?php echo $h($promo['promo_code']); ?></span>
                        <?php endif; ?>
                        <a href="<?php echo $h(!empty($promo['link_url']) ? $promo['link_url'] : site_url('shop/products.php')); ?>" class="btn btn-ink">Shop the offer</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php echo render_recently_viewed($conn); ?>

<?php require_once 'includes/footer.php'; ?>
