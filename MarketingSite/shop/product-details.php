<?php
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: ' . site_url('shop/products.php'));
    exit();
}

// Check if is_active column exists - use a more reliable method
$hasIsActiveColumn = false;
try {
    // Try to select the column - if it fails, it doesn't exist
    $testQuery = $conn->query("SELECT is_active FROM products LIMIT 1");
    $hasIsActiveColumn = true;
} catch(PDOException $e) {
    // Column doesn't exist if we get an error
    $hasIsActiveColumn = false;
}

// Get product details - only if active (if column exists)
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?";
if ($hasIsActiveColumn) {
    $query .= " AND p.is_active = 1";
}
$stmt = $conn->prepare($query);
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    header('Location: ' . site_url('shop/products.php'));
    exit();
}

// Get reviews for this product (only approved)
$stmt = $conn->prepare("SELECT r.* FROM reviews r WHERE r.product_id = ? AND r.status = 'Approved' ORDER BY r.created_at DESC");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate average rating
$avgRating = 0;
if (count($reviews) > 0) {
    $totalRating = array_sum(array_column($reviews, 'rating'));
    $avgRating = round($totalRating / count($reviews), 1);
}

// Get related products (same category, limit 4) - only active products if column exists
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.category_id = ? AND p.id != ?";
if ($hasIsActiveColumn) {
    $query .= " AND p.is_active = 1";
}
$query .= " LIMIT 4";
$stmt = $conn->prepare($query);
$stmt->execute([$product['category_id'], $product_id]);
$relatedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product variations (size and color)
// Auto-create table if it doesn't exist
try {
    // First check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'product_variations'");
    if ($tableCheck->rowCount() == 0) {
        // Create table without foreign key first (to avoid constraint issues)
        $conn->exec("CREATE TABLE product_variations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            variation_type ENUM('size', 'color') NOT NULL,
            variation_value VARCHAR(100) NOT NULL,
            price_modifier DECIMAL(10, 2) DEFAULT 0,
            inventory INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_variation (product_id, variation_type, variation_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Try to add foreign key constraint if possible
        try {
            $conn->exec("ALTER TABLE product_variations 
                ADD CONSTRAINT fk_product_variations_product 
                FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE");
        } catch (PDOException $e) {
            // Foreign key might fail if products table structure is different, that's okay
        }
    }
} catch (PDOException $e) {
    // Table might already exist, ignore
}

// Check if size and color columns exist in order_items table
try {
    $checkCols = $conn->query("SHOW COLUMNS FROM order_items LIKE 'size'");
    if ($checkCols->rowCount() == 0) {
        $conn->exec("ALTER TABLE order_items 
            ADD COLUMN size VARCHAR(100) DEFAULT NULL,
            ADD COLUMN color VARCHAR(100) DEFAULT NULL");
    }
} catch (PDOException $e) {
    // Columns might already exist, ignore
}

// Get variations for this product
try {
    $stmt = $conn->prepare("SELECT * FROM product_variations WHERE product_id = ? AND is_active = 1 ORDER BY variation_type, is_default DESC, display_order");
    $stmt->execute([$product_id]);
    $allVariations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet or query failed
    $allVariations = [];
}

// Organize variations by type
$sizes = [];
$colors = [];
foreach ($allVariations as $variation) {
    if ($variation['variation_type'] == 'size') {
        $sizes[] = $variation;
    } elseif ($variation['variation_type'] == 'color') {
        $colors[] = $variation;
    }
}

// Debug: Check if we have variations (remove this after testing)
// echo "<!-- Debug: Total variations: " . count($allVariations) . ", Colors: " . count($colors) . ", Sizes: " . count($sizes) . " -->";

// If product has no variations, treat it as having a default variation (but don't show it)
// This allows the product to work normally without variation selection
$hasVariations = count($sizes) > 0 || count($colors) > 0;

// Calculate base price
$basePrice = $product['price'];

$pageTitle = $product['name'] ?? 'Product Details';
require_once __DIR__ . '/../includes/header.php';

// Remember this product for the "Recently viewed" strip (session only, newest first, max 8)
remember_recently_viewed((int) $product['id']);

// Presentation data: active promotions (sale tag) and review averages for cards
$promotions = storefront_active_promotions($conn);
$reviewStats = storefront_review_stats($conn);
$sale = promo_price_for_product($product, $promotions);
$salePromo = null;
if ($sale) {
    foreach ($promotions as $pr) {
        if ((string) ($pr['promo_code'] ?? '') === $sale['code']) { $salePromo = $pr; break; }
    }
}

// Top up "Related products" to 4 with best sellers from other categories when the category is small
if (count($relatedProducts) < 4) {
    $exclude = array_merge([$product_id], array_map('intval', array_column($relatedProducts, 'id')));
    $ph = implode(',', array_fill(0, count($exclude), '?'));
    $q = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id NOT IN ($ph)" . ($hasIsActiveColumn ? " AND p.is_active = 1" : "") . " ORDER BY p.sold DESC LIMIT " . (4 - count($relatedProducts));
    $stmt = $conn->prepare($q);
    $stmt->execute($exclude);
    $relatedProducts = array_merge($relatedProducts, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

// Check if product is in stock (base inventory or any variation has stock)
$isInStock = false;
if ($product['inventory'] > 0) {
    $isInStock = true;
} else {
    foreach (array_merge($sizes, $colors) as $v) {
        if (isset($v['inventory']) && $v['inventory'] > 0) { $isInStock = true; break; }
    }
}
$inv = (int) $product['inventory'];
$stockState = $inv <= 0 ? 'out' : ($inv <= 5 ? 'low' : 'ok');
$stockText = $inv <= 0 ? 'Out of stock' : ($inv <= 5 ? 'Only ' . $inv . ' left' : 'In stock, ' . $inv . ' available');

/* ---------- storefront v2 data: specs, rate history, seller, installments, delivery, compatibility ---------- */
require_once __DIR__ . '/../../core/builder.php';
require_once __DIR__ . '/../../core/shipping.php';
$pid = (int) $product['id'];
$attrs = product_attributes($conn, $pid);
$keySpecs = key_specs($attrs);
$specRows = spec_rows($attrs);
$chg = price_change($conn, [$pid], 7)[$pid] ?? null;
$series = price_series($conn, $pid, 12);
$range = price_range($series);
$seller = seller_for($conn, $pid);
$partType = product_part_type($product);
$fig = static fn($n) => number_format(abs(round((float) $n)), 0, '.', ',');

$plans = setting('installment_plans', null);
$installments = null;
if (is_array($plans) && !empty($plans['banks']) && !empty($plans['months'])) {
    $markup = (float) ($plans['markup_pct'] ?? 0);
    $months = array_values(array_filter(array_map('intval', (array) $plans['months']), static fn($m) => $m > 1));
    sort($months);
    $payable = (float) $basePrice * (1 + $markup / 100);
    if ($months && (float) $basePrice >= (float) ($plans['min_amount'] ?? 0)) {
        $installments = ['banks' => array_values(array_map('strval', (array) $plans['banks'])), 'months' => $months, 'markup' => $markup,
                         'payable' => $payable, 'lowest' => $payable / max($months), 'longest' => max($months), 'min' => (float) ($plans['min_amount'] ?? 0)];
    }
}
$zones = [];
try {
    $zones = shipping_zones($conn);
} catch (Throwable $e) {
    $zones = [];
}

// WhatsApp: the seller's number, else the store's
$waDigits = whatsapp_digits((string) (($seller['whatsapp'] ?? '') ?: setting('whatsapp_number', '')));
$host = preg_replace('/[^A-Za-z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$productAbsUrl = $scheme . '://' . $host . site_url('shop/product-details.php?id=' . $pid);
$waUrl = $waDigits !== '' ? 'https://wa.me/' . $waDigits . '?text=' . rawurlencode('Is ' . $product['name'] . ' available? ' . $productAbsUrl) : '';

// Compatibility (builder rules), components only
$slotFor = ['cpu' => 'cpu', 'motherboard' => 'mb', 'memory' => 'ram', 'gpu' => 'gpu', 'psu' => 'psu', 'case' => 'case', 'cooler' => 'cooler', 'storage' => 'storage'];
$buildSlot = $slotFor[$partType] ?? null;
if ($buildSlot === 'storage' && ($attrs['internal'] ?? '0') !== '1') $buildSlot = null;
$compat = [];
if ($buildSlot) {
    $mine = [$buildSlot => $pid];
    $fit = static function (string $slot, ?callable $keep = null) use ($conn, $mine): array {
        $rows = array_values(array_filter(builder_candidates($conn, $mine, $slot), static fn($r) => $r['in_stock']));
        return $keep ? array_values(array_filter($rows, $keep)) : $rows;
    };
    $noWarn = static fn($r) => !$r['warnings'];
    $a = static fn(string $k) => (string) ($attrs[$k] ?? '');
    switch ($partType) {
        case 'cpu':
            $compat[] = ['Fits ' . $a('socket') . ' motherboards', $fit('mb'), 'mb'];
            if ($a('bundled_cooler') !== '1') $compat[] = ['Coolers that handle ' . $a('tdp_w') . ' W', $fit('cooler'), 'cooler'];
            break;
        case 'motherboard':
            $compat[] = ['Takes ' . $a('socket') . ' processors', $fit('cpu'), 'cpu'];
            $compat[] = ['Takes ' . $a('memory_type') . ' memory', $fit('ram'), 'ram'];
            break;
        case 'memory':
            $compat[] = ['Fits ' . $a('memory_type') . ' motherboards', $fit('mb'), 'mb'];
            break;
        case 'gpu':
            $compat[] = ['Power supplies of ' . builder_power(['gpu' => ['attrs' => $attrs]])[1] . ' W or more', $fit('psu', $noWarn), 'psu'];
            $compat[] = ['Cases with room for ' . (int) $a('length_mm') . ' mm', $fit('case'), 'case'];
            break;
        case 'psu':
            $compat[] = ['Graphics cards it can power', $fit('gpu', $noWarn), 'gpu'];
            break;
        case 'case':
            $compat[] = ['Graphics cards up to ' . (int) $a('max_gpu_mm') . ' mm', $fit('gpu'), 'gpu'];
            $compat[] = ['Motherboards: ' . str_replace(',', ', ', $a('form_factors')), $fit('mb'), 'mb'];
            break;
        case 'cooler':
            $compat[] = ['Fits ' . str_replace(',', ', ', $a('sockets')) . ' processors', $fit('cpu'), 'cpu'];
            break;
        case 'storage':
            $compat[] = ['Motherboards with an M.2 slot', $fit('mb'), 'mb'];
            break;
    }
}
$dropped = $chg && $chg['direction'] === 'drop';
?>

<div class="container page">
    <nav aria-label="Breadcrumb">
        <ol class="crumbs">
            <li><a href="<?php echo $h(site_url('index.php')); ?>">Home</a></li>
            <li><a href="<?php echo $h(site_url('shop/products.php')); ?>">Products</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li><a href="<?php echo $h(site_url('shop/products.php?category=' . (int) $product['category_id'])); ?>"><?php echo $h($product['category_name']); ?></a></li>
            <?php endif; ?>
            <li aria-current="page"><?php echo $h($product['name']); ?></li>
        </ol>
    </nav>

    <div class="pdp">
        <div class="pdp__media">
            <div class="pdp__gallery">
                <?php echo render_product_media($product, ['width' => 900, 'alt' => $product['name'], 'lazy' => false, 'attrs' => 'fetchpriority="high"']); ?>
            </div>
        </div>

        <div class="buybox">
            <p class="buybox__cat"><a href="<?php echo $h(site_url('shop/products.php?category=' . (int) $product['category_id'])); ?>"><?php echo $h($product['category_name']); ?></a></p>
            <h1 class="buybox__title"><?php echo $h($product['name']); ?></h1>
            <div class="buybox__meta">
                <?php echo render_key_specs($keySpecs, ''); ?>
                <p class="buybox__rating">
                    <?php if (count($reviews) > 0): ?>
                        <?php echo rating_stars((float) $avgRating, 16); ?>
                        <span><?php echo number_format((float) $avgRating, 1); ?></span>
                        <a href="#details" class="js-show-reviews"><?php echo count($reviews); ?> <?php echo count($reviews) === 1 ? 'review' : 'reviews'; ?></a>
                    <?php else: ?>
                        <a href="#details" class="js-show-reviews">No reviews yet</a>
                    <?php endif; ?>
                </p>
            </div>

            <div class="buybox__price">
                <?php if ($sale): ?>
                    <?php echo price_tag($sale['price'], 'lg', 'price-tag--figure js-sale-tag'); ?>
                    <p class="buybox__was">Regular price <s><span id="displayPrice"><?php echo format_pkr($basePrice); ?></span></s>. <?php echo $h($sale['label']); ?> with code <strong><?php echo $h($sale['code']); ?></strong> in your cart.</p>
                <?php elseif ($dropped): ?>
                    <span class="price-tag price-tag--lg price-tag--figure"><span id="displayPrice"><?php echo format_pkr($basePrice); ?></span></span>
                <?php else: ?>
                    <p class="buybox__amount"><span id="displayPrice"><?php echo format_pkr($basePrice); ?></span></p>
                <?php endif; ?>
                <?php if ($chg && $chg['direction'] !== 'hold'): ?>
                    <p class="buybox__change"><?php echo render_delta($chg); ?> since last week</p>
                <?php elseif ($series): ?>
                    <p class="buybox__change buybox__change--hold">Same rate as last week</p>
                <?php endif; ?>
            </div>

            <?php if (count($series) >= 2): $first = reset($series); $last = end($series); ?>
            <div class="rate-card">
                <div class="rate-card__head">
                    <p class="rate-card__title">Rate history</p>
                    <p class="rate-card__span"><?php echo count($series); ?> weeks</p>
                </div>
                <div class="rate-card__body">
                    <div class="rate-card__chart"><?php echo render_sparkline($series, 280, 64); ?>
                        <div class="rate-card__dates" aria-hidden="true"><span><?php echo $h(date('j M', strtotime($first['date']))); ?></span><span>Today</span></div>
                    </div>
                    <dl class="rate-card__range">
                        <div><dt>12-week low</dt><dd><?php echo format_pkr($range['low']); ?></dd></div>
                        <div><dt>High</dt><dd><?php echo format_pkr($range['high']); ?></dd></div>
                    </dl>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($installments): $bankList = $installments['banks']; $lastBank = array_pop($bankList); ?>
            <div class="installments">
                <div class="installments__line">or <strong><?php echo format_pkr($installments['lowest']); ?></strong> a month for <?php echo (int) $installments['longest']; ?> months with <?php echo $h($bankList ? implode(', ', $bankList) . ' or ' . $lastBank : $lastBank); ?><?php echo $installments['markup'] <= 0 ? ', 0% markup' : ''; ?>.
                    <details class="installments__details">
                        <summary>See plans</summary>
                        <div class="installments__pop">
                            <table class="installments__table">
                                <caption class="visually-hidden">Monthly installment by bank and plan length</caption>
                                <thead><tr><th scope="col">Bank</th><?php foreach ($installments['months'] as $m): ?><th scope="col"><?php echo (int) $m; ?> months</th><?php endforeach; ?></tr></thead>
                                <tbody>
                                <?php foreach ($installments['banks'] as $bank): ?>
                                    <tr><th scope="row"><?php echo $h($bank); ?></th><?php foreach ($installments['months'] as $m): ?><td><?php echo format_pkr($installments['payable'] / $m); ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="installments__note">On orders over <?php echo format_pkr($installments['min']); ?> with a credit card from these banks<?php echo $installments['markup'] > 0 ? ', ' . rtrim(rtrim(number_format($installments['markup'], 2), '0'), '.') . '% markup included' : ', no markup'; ?>. Choose the plan at checkout.</p>
                        </div>
                    </details>
                </div>
            </div>
            <?php endif; ?>

            <form id="addToCartForm" class="<?php echo $isInStock ? '' : 'is-out'; ?>" method="POST" action="<?php echo $h(site_url('api/add_to_cart.php')); ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                    <?php if (count($sizes) > 0): ?>
                        <?php
                        $showSizeSelection = count($sizes) > 1;
                        $defaultSizeIndex = -1;
                        foreach ($sizes as $index => $size) {
                            if (isset($size['is_default']) && $size['is_default'] == 1) { $defaultSizeIndex = $index; break; }
                        }
                        if ($defaultSizeIndex == -1) $defaultSizeIndex = 0;
                        ?>
                        <?php if ($showSizeSelection): ?>
                            <fieldset class="chips">
                                <legend>Size</legend>
                                <div class="chips__row">
                                <?php foreach ($sizes as $index => $size): ?>
                                    <input type="radio" class="btn-check" name="size" id="size_<?php echo (int) $size['id']; ?>"
                                           value="<?php echo $h($size['variation_value']); ?>"
                                           data-price-modifier="<?php echo $h($size['price_modifier']); ?>"
                                           data-inventory="<?php echo isset($size['inventory']) ? (int) $size['inventory'] : $inv; ?>"
                                           <?php echo $index === $defaultSizeIndex ? 'checked' : ''; ?> required>
                                    <label class="chip" for="size_<?php echo (int) $size['id']; ?>">
                                        <?php echo $h($size['variation_value']); ?>
                                        <?php if ($size['price_modifier'] != 0): ?><span class="chip__mod"><?php echo format_pkr_delta($size['price_modifier']); ?></span><?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php else: ?>
                            <input type="hidden" name="size" value="<?php echo $h($sizes[$defaultSizeIndex]['variation_value']); ?>"
                                   data-price-modifier="<?php echo $h($sizes[$defaultSizeIndex]['price_modifier']); ?>"
                                   data-inventory="<?php echo isset($sizes[$defaultSizeIndex]['inventory']) ? (int) $sizes[$defaultSizeIndex]['inventory'] : $inv; ?>">
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (count($colors) > 0): ?>
                        <?php
                        $showColorSelection = count($colors) > 1;
                        $defaultColorIndex = -1;
                        foreach ($colors as $index => $color) {
                            if (isset($color['is_default']) && $color['is_default'] == 1) { $defaultColorIndex = $index; break; }
                        }
                        if ($defaultColorIndex == -1) $defaultColorIndex = 0;
                        ?>
                        <?php if ($showColorSelection): ?>
                            <fieldset class="chips">
                                <legend>Colour</legend>
                                <div class="chips__row">
                                <?php foreach ($colors as $index => $color): ?>
                                    <input type="radio" class="btn-check" name="color" id="color_<?php echo (int) $color['id']; ?>"
                                           value="<?php echo $h($color['variation_value']); ?>"
                                           data-price-modifier="<?php echo $h($color['price_modifier']); ?>"
                                           data-inventory="<?php echo isset($color['inventory']) && $color['inventory'] > 0 ? (int) $color['inventory'] : $inv; ?>"
                                           <?php echo $index === $defaultColorIndex ? 'checked' : ''; ?> required>
                                    <label class="chip" for="color_<?php echo (int) $color['id']; ?>">
                                        <?php echo $h($color['variation_value']); ?>
                                        <?php if ($color['price_modifier'] != 0): ?><span class="chip__mod"><?php echo format_pkr_delta($color['price_modifier']); ?></span><?php endif; ?>
                                    </label>
                                <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php else: ?>
                            <input type="hidden" name="color" value="<?php echo $h($colors[$defaultColorIndex]['variation_value']); ?>"
                                   data-price-modifier="<?php echo $h($colors[$defaultColorIndex]['price_modifier']); ?>"
                                   data-inventory="<?php echo isset($colors[$defaultColorIndex]['inventory']) && $colors[$defaultColorIndex]['inventory'] > 0 ? (int) $colors[$defaultColorIndex]['inventory'] : $inv; ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                <p class="stock stock--<?php echo $stockState; ?> buybox__stock" id="stockBadge"><span class="stock__dot" aria-hidden="true"></span><span class="js-stock-text"><?php echo $h($stockText); ?></span></p>

                <?php if ($isInStock): ?>
                <div class="buybox__buy">
                    <div class="qty">
                        <label for="quantity" class="visually-hidden">Quantity</label>
                        <button type="button" class="qty__btn" onclick="decreaseQuantity()" aria-label="Decrease quantity">&minus;</button>
                        <input type="number" class="qty__input" name="quantity" id="quantity" value="1" min="1" max="<?php echo $inv; ?>" required>
                        <button type="button" class="qty__btn" onclick="increaseQuantity()" aria-label="Increase quantity">+</button>
                    </div>
                    <button type="submit" class="btn--primary btn-buy" id="addToCartBtn">Add to cart</button>
                </div>
                <?php else: ?>
                <input type="hidden" name="quantity" id="quantity" value="1">
                <button type="button" class="d-none" id="addToCartBtn" disabled>Out of stock</button>
                <?php endif; ?>
            </form>

            <?php if (!$isInStock): ?>
            <form class="notify" data-stock-alert novalidate>
                <p class="notify__title">Notify me when it is back</p>
                <p class="notify__text">We will send one email when this is in stock again. Nothing else.</p>
                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                <div class="notify__row">
                    <label class="visually-hidden" for="alertEmail">Email address</label>
                    <input class="field__input" type="email" id="alertEmail" name="email" required autocomplete="email" placeholder="you@example.com" value="<?php echo $h($_SESSION['customer_email'] ?? ''); ?>">
                    <button type="submit" class="btn--primary">Notify me</button>
                </div>
                <p class="js-alert-msg notify__msg" role="status" aria-live="polite"></p>
            </form>
            <?php endif; ?>

            <div class="buybox__secondary">
                <?php echo render_wish_button($product, '', true); ?>
                <button type="button" class="btn--secondary compare-toggle" data-compare="<?php echo $pid; ?>" aria-pressed="false">Compare</button>
            </div>

            <?php if ($seller): ?>
            <div class="seller-box">
                <p class="seller-box__label">Sold by</p>
                <p class="seller-box__name"><?php if ((int) $seller['id'] !== 1 && !empty($seller['slug'])): ?><a href="<?php echo $h(site_url('sellers/view.php?slug=' . rawurlencode((string) $seller['slug']))); ?>"><?php echo $h($seller['name']); ?></a><?php else: ?><?php echo $h($seller['name']); ?><?php endif; ?></p>
                <p class="seller-box__where"><span><?php echo $h(trim(($seller['market'] ?? '') . ', ' . ($seller['city'] ?? ''), ', ')); ?></span><?php if ((float) ($seller['rating'] ?? 0) > 0): ?><span><?php echo rating_stars((float) $seller['rating'], 12); ?> <?php echo number_format((float) $seller['rating'], 1); ?></span><?php endif; ?></p>
                <?php if ($waUrl !== ''): ?>
                <a class="seller-box__wa" href="<?php echo $h($waUrl); ?>" target="_blank" rel="noopener"><?php echo tz_icon('whatsapp', 18); ?>Ask on WhatsApp</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <ul class="delivery-box">
                <?php if ($zones): ?>
                <li><?php echo tz_icon('truck'); ?><span><strong>Delivery</strong>
                    <ul class="zone-list">
                    <?php foreach ($zones as $z): if (!$z['rate']) continue; ?>
                        <li><?php echo $h($z['name']); ?> <?php echo $h($z['rate']['eta'] ?: ''); ?>, <?php echo $z['rate']['free_over'] !== null ? 'free over ' . format_pkr($z['rate']['free_over']) : format_pkr($z['rate']['amount']); ?></li>
                    <?php endforeach; ?>
                    </ul></span></li>
                <?php endif; ?>
                <li><?php echo tz_icon('cash'); ?><span><strong>Cash on delivery</strong> or JazzCash at checkout</span></li>
                <li><?php echo tz_icon('rotate'); ?><span><strong>7-day returns</strong> on unopened items, official warranty</span></li>
            </ul>
        </div>
    </div>

    <div class="pdp-lower<?php echo $compat ? '' : ' pdp-lower--solo'; ?>">
        <div class="pdp-tabs" id="details">
            <ul class="nav tabs" role="tablist">
                <?php if ($specRows): ?>
                <li class="nav-item" role="presentation"><button class="nav-link active" id="tab-specs" data-bs-toggle="tab" data-bs-target="#pane-specs" type="button" role="tab" aria-controls="pane-specs" aria-selected="true">Specifications</button></li>
                <?php endif; ?>
                <li class="nav-item" role="presentation"><button class="nav-link<?php echo $specRows ? '' : ' active'; ?>" id="tab-desc" data-bs-toggle="tab" data-bs-target="#pane-desc" type="button" role="tab" aria-controls="pane-desc" aria-selected="<?php echo $specRows ? 'false' : 'true'; ?>">Description</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="tab-reviews" data-bs-toggle="tab" data-bs-target="#pane-reviews" type="button" role="tab" aria-controls="pane-reviews" aria-selected="false">Reviews <span class="tabs__count"><?php echo count($reviews); ?></span></button></li>
            </ul>
            <div class="tab-content">
                <?php if ($specRows): ?>
                <div class="tab-pane fade show active" id="pane-specs" role="tabpanel" aria-labelledby="tab-specs" tabindex="0">
                    <table class="spec-table">
                        <caption class="visually-hidden">Specifications of <?php echo $h($product['name']); ?></caption>
                        <tbody>
                        <?php foreach ($specRows as $row): ?>
                            <tr><th scope="row"><?php echo $h($row['label']); ?></th><td><?php echo $h($row['value']); ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="tab-pane fade<?php echo $specRows ? '' : ' show active'; ?>" id="pane-desc" role="tabpanel" aria-labelledby="tab-desc" tabindex="0">
                    <div class="prose"><?php echo $product['description'] !== null && $product['description'] !== '' ? nl2br($h($product['description'])) : '<p>No description yet. The specifications tab lists what we know about this part.</p>'; ?></div>
                </div>
                <div class="tab-pane fade" id="pane-reviews" role="tabpanel" aria-labelledby="tab-reviews" tabindex="0">
                <?php if (count($reviews) > 0): ?>
                    <ul class="review-list">
                    <?php foreach ($reviews as $review): ?>
                        <li class="review">
                            <div class="review__head">
                                <?php echo rating_stars((float) $review['rating'], 14); ?>
                                <strong><?php echo $h($review['customer_name']); ?></strong>
                                <span class="review__date"><?php echo date('j F Y', strtotime($review['created_at'])); ?></span>
                            </div>
                            <?php if (trim((string) $review['comment']) !== ''): ?>
                                <p class="review__body"><?php echo nl2br($h($review['comment'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($review['admin_response'])): ?>
                                <div class="review__reply">
                                    <strong>TechZone replied</strong>
                                    <p><?php echo nl2br($h($review['admin_response'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="muted">No reviews yet. Customers can review a product from their order page after it is delivered.</p>
                <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($compat): ?>
        <aside class="compat" aria-labelledby="compat-title">
            <h2 class="compat__title" id="compat-title">Compatibility</h2>
            <p class="compat__lead">Checked with the builder's rules against parts in stock today.</p>
            <?php foreach ($compat as [$label, $rows, $slot]): ?>
            <p class="compat__fact"><span class="compat__n"><?php echo count($rows); ?></span> <?php echo $h($label); ?></p>
            <?php if ($rows): ?>
            <ul class="compat__list">
                <?php foreach (array_slice($rows, 0, 4) as $r): ?>
                <li><a href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $r['id'])); ?>"><span><?php echo $h($r['name']); ?></span><span class="compat__price"><?php echo $fig($r['price']); ?></span></a></li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="compat__lead">None in stock right now.</p>
            <?php endif; ?>
            <?php endforeach; ?>
            <a class="btn--primary btn--block compat__use" href="<?php echo $h(site_url('builder.php?b=' . $buildSlot . ':' . $pid)); ?>">Use in a build</a>
        </aside>
        <?php endif; ?>
    </div>
</div>

<?php if (count($relatedProducts) > 0): storefront_card_prime($conn, $relatedProducts); ?>
<section class="section" aria-labelledby="related-title">
    <div class="container">
        <div class="section-head">
            <h2 class="section-head__title section-head__title--sm" id="related-title">Related parts</h2>
            <a class="section-head__link" href="<?php echo $h(site_url('shop/products.php?category=' . (int) $product['category_id'])); ?>">See all <?php echo $h($product['category_name']); ?></a>
        </div>
        <div class="pgrid pgrid--4">
            <?php foreach ($relatedProducts as $related) echo render_product_card($related, $promotions); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php echo render_recently_viewed($conn, (int) $product['id']); ?>

<script>
const basePrice = <?php echo (float) $basePrice; ?>;
const SALE = <?php echo $salePromo ? json_encode(['pct' => (float) $salePromo['discount_percentage'], 'amt' => (float) $salePromo['discount_amount']]) : 'null'; ?>;
let selectedSize = null;
let selectedColor = null;

// Update price when variation is selected and ensure only selected option is highlighted
document.querySelectorAll('input[name="size"], input[name="color"]').forEach(input => {
    input.addEventListener('change', function() {
        // Remove active class from all labels in the same group
        const groupName = this.name;
        document.querySelectorAll(`input[name="${groupName}"]`).forEach(radio => {
            const label = document.querySelector(`label[for="${radio.id}"]`);
            if (label) {
                label.classList.remove('active');
            }
        });
        
        // Add active class to selected label
        const selectedLabel = document.querySelector(`label[for="${this.id}"]`);
        if (selectedLabel) {
            selectedLabel.classList.add('active');
        }
        
        if (this.name === 'size') {
            selectedSize = this;
        } else if (this.name === 'color') {
            selectedColor = this;
        }
        updatePrice();
        updateStock();
    });
    
    // Initialize selected variations for hidden fields (single default variation)
    if (input.type === 'hidden') {
        if (input.name === 'size') {
            selectedSize = input;
        } else if (input.name === 'color') {
            selectedColor = input;
        }
    }
    
    // Initialize highlighting for checked radio buttons on page load
    if (input.checked && input.type === 'radio') {
        const label = document.querySelector(`label[for="${input.id}"]`);
        if (label) {
            label.classList.add('active');
        }
    }
});

function formatPkr(n) {
    n = Number(n) || 0;
    return (n < 0 ? '-' : '') + 'Rs ' + Math.round(Math.abs(n)).toLocaleString('en-US');
}
function formatPkrDelta(n) {
    return (n > 0 ? '+' : '') + formatPkr(n);
}

function updatePrice() {
    let totalPrice = basePrice;
    let priceModifiers = [];
    
    if (selectedSize) {
        const modifier = parseFloat(selectedSize.dataset.priceModifier) || 0;
        totalPrice += modifier;
        if (modifier != 0) {
            priceModifiers.push(selectedSize.value + ' (' + formatPkrDelta(modifier) + ')');
        }
    }
    
    if (selectedColor) {
        const modifier = parseFloat(selectedColor.dataset.priceModifier) || 0;
        totalPrice += modifier;
        if (modifier != 0) {
            priceModifiers.push(selectedColor.value + ' (' + formatPkrDelta(modifier) + ')');
        }
    }
    
    document.getElementById('displayPrice').textContent = formatPkr(totalPrice);
    const tag = document.querySelector('.js-sale-tag');
    if (tag && SALE) {
        tag.textContent = formatPkr(SALE.pct > 0 ? totalPrice * (1 - SALE.pct / 100) : Math.max(0, totalPrice - SALE.amt));
    }
}

function setStock(state, text) {
    const el = document.getElementById('stockBadge');
    if (!el) return;
    el.className = 'stock stock--' + state + ' buybox__stock';
    el.querySelector('.js-stock-text').textContent = text;
}

function updateStock() {
    // Get inventory from selected variations or base product
    let inventory = <?php echo $product['inventory']; ?>;
    
    if (selectedSize && selectedSize.dataset.inventory) {
        inventory = Math.min(inventory, parseInt(selectedSize.dataset.inventory) || inventory);
    }
    
    if (selectedColor && selectedColor.dataset.inventory) {
        inventory = Math.min(inventory, parseInt(selectedColor.dataset.inventory) || inventory);
    }
    
    const stockBadge = document.getElementById('stockBadge');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const quantityInput = document.getElementById('quantity');
    
    if (stockBadge) {
        if (inventory > 0) {
            setStock(inventory <= 5 ? 'low' : 'ok', inventory <= 5 ? 'Only ' + inventory + ' left' : 'In stock, ' + inventory + ' available');
            if (quantityInput) {
                quantityInput.max = inventory;
            }
            if (addToCartBtn) {
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = 'Add to cart';
            }
        } else {
            setStock('out', 'Out of stock');
            if (addToCartBtn) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = 'Out of stock';
            }
            if (quantityInput) {
                quantityInput.max = 0;
            }
        }
    } else {
        // If no stock badge, still disable button if out of stock
        if (addToCartBtn && inventory <= 0) {
            addToCartBtn.disabled = true;
            addToCartBtn.textContent = 'Out of stock';
        }
    }
}

function increaseQuantity() {
    const qtyInput = document.getElementById('quantity');
    const max = parseInt(qtyInput.max) || <?php echo $product['inventory']; ?>;
    const current = parseInt(qtyInput.value) || 1;
    if (current < max) {
        qtyInput.value = current + 1;
    }
}

function decreaseQuantity() {
    const qtyInput = document.getElementById('quantity');
    const current = parseInt(qtyInput.value) || 1;
    if (current > 1) {
        qtyInput.value = current - 1;
    }
}

// Initialize selected variations and check stock on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check for checked radio buttons first
    let sizeInput = document.querySelector('input[name="size"]:checked');
    let colorInput = document.querySelector('input[name="color"]:checked');
    
    // If no checked radio, check for hidden fields (single default variation)
    if (!sizeInput) {
        sizeInput = document.querySelector('input[name="size"][type="hidden"]');
    }
    if (!colorInput) {
        colorInput = document.querySelector('input[name="color"][type="hidden"]');
    }
    
    // If product has variations, initialize them
    if (sizeInput) {
        selectedSize = sizeInput;
    }
    if (colorInput) {
        selectedColor = colorInput;
    }
    
    // Only update price/stock if variations exist, otherwise use base product
    const hasVariations = <?php echo $hasVariations ? 'true' : 'false'; ?>;
    if (hasVariations) {
        updatePrice();
        updateStock();
    }
    
    // Also check stock initially if product has no variations
    const addToCartBtn = document.getElementById('addToCartBtn');
    const baseInventory = <?php echo $product['inventory']; ?>;
    if (addToCartBtn && baseInventory <= 0 && !hasVariations) {
        addToCartBtn.disabled = true;
        addToCartBtn.textContent = 'Out of stock';
    }

    // "12 reviews" link opens the Reviews tab
    document.querySelectorAll('.js-show-reviews').forEach(function (a) {
        a.addEventListener('click', function (e) {
            const tabBtn = document.getElementById('tab-reviews');
            if (tabBtn && window.bootstrap) {
                e.preventDefault();
                bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                document.getElementById('details').scrollIntoView({ block: 'start' });
            }
        });
    });
});

// Handle form submission with AJAX
document.getElementById('addToCartForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('addToCartBtn');
    
    // Prevent submission if button is disabled (out of stock)
    if (btn.disabled) {
        return false;
    }
    
    const formData = new FormData(this);
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.textContent = 'Adding…';
    
    fetch(marketingUrl('api/add_to_cart.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show mt-3';
            alert.setAttribute('role', 'status');
            alert.textContent = data.message + ' ';
            const viewCart = document.createElement('a');
            viewCart.href = marketingUrl('shop/cart.php');
            viewCart.textContent = 'View cart';
            alert.appendChild(viewCart);
            alert.insertAdjacentHTML('beforeend', '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>');
            document.getElementById('addToCartForm').after(alert);
            
            // Update cart badge if it exists
            if (data.cart_count !== undefined) {
                let cartBadge = document.querySelector('.nav-cart-badge');
                const cartLink = document.querySelector('.cart-link');
                if (!cartBadge && cartLink) {
                    cartBadge = document.createElement('span');
                    cartBadge.className = 'nav-cart-badge';
                    cartLink.appendChild(cartBadge);
                }
                if (cartBadge) cartBadge.textContent = data.cart_count;
            }
            
            // Reset form after 2 seconds
            setTimeout(() => {
                alert.remove();
            }, 3000);
            
            // Re-enable button and restore text
            btn.disabled = false;
            btn.innerHTML = originalText;
        } else {
            alert(data.message || 'Error adding product to cart');
            // Re-enable button and restore text
            btn.disabled = false;
            btn.innerHTML = originalText;
            // Re-check stock status
            updateStock();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding product to cart. Please try again.');
        // Re-enable button and restore text
        btn.disabled = false;
        btn.innerHTML = originalText;
        // Re-check stock status
        updateStock();
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
