<?php
$pageTitle = 'Today\'s PC part rates in Lahore';
$pageDescription = 'Live prices on PC parts from Hafeez Centre and Hall Road, Lahore. Build a PC at today\'s rates and pay cash on delivery anywhere in Pakistan.';
require_once 'includes/header.php';
require_once 'config/database.php';
require_once __DIR__ . '/../core/inventory.php';
require_once __DIR__ . '/../core/catalog.php';
require_once __DIR__ . '/../core/builder.php';

$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$fig = static fn($n) => number_format(abs(round((float) $n)), 0, '.', ',');
$promotions = storefront_active_promotions($conn);

/* ---------- hero: the rate board ---------- */
$board = [];
try {
    $board = rate_board($conn, 8);
} catch (PDOException $e) {
    log_exception($e, 'home rate board');
}
$updatedAt = null;
try {
    $updatedAt = $conn->query("SELECT GREATEST(COALESCE((SELECT MAX(recorded_at) FROM price_history), '2000-01-01'),
        COALESCE((SELECT MAX(updated_at) FROM products p WHERE " . catalog_visible_sql('p') . "), '2000-01-01'))")->fetchColumn();
} catch (PDOException $e) {
    $updatedAt = null;
}
$updatedTs = $updatedAt ? strtotime((string) $updatedAt) : false;
$updatedLabel = '';
if ($updatedTs && $updatedTs > strtotime('2001-01-01')) {
    $updatedLabel = date('Y-m-d', $updatedTs) === date('Y-m-d') && date('H:i', $updatedTs) !== '00:00'
        ? date('H:i', $updatedTs) : date('j M', $updatedTs);
}
$visibleCount = (int) $conn->query('SELECT COUNT(*) FROM products p WHERE ' . catalog_visible_sql('p'))->fetchColumn();
$marketSellers = [];
try {
    $marketSellers = $conn->query("SELECT s.id, s.slug, s.name, s.market, s.city, s.rating, COUNT(p.id) AS products
        FROM sellers s LEFT JOIN products p ON p.seller_id = s.id AND " . catalog_visible_sql('p') . "
        WHERE s.id <> 1 AND s.status = 'active'
        GROUP BY s.id, s.slug, s.name, s.market, s.city, s.rating
        ORDER BY s.rating DESC, products DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $marketSellers = [];
}

/* ---------- builder teaser: a sensible build for one processor (mirrors assets/js/home.js) ---------- */
/**
 * Default build for the home teaser. Same choices as home.js makes from api/builder.php:
 * cheapest in-stock board that fits; graphics nearest 1.8 × the processor's price; cheapest in-stock
 * memory kit, internal drive, power supply at or above the recommendation, case; a cooler only when
 * the processor has none in the box.
 *
 * @return array{parts: array<string, array>, boards:int, kits:int, total:float, b:string}
 */
function home_default_build(PDO $conn, int $cpuId): array
{
    $inStock = static fn(array $rows) => array_values(array_filter($rows, static fn($r) => $r['in_stock']));
    $first = static fn(array $rows) => $rows[0] ?? null;
    $build = ['cpu' => $cpuId];
    $loaded = builder_load($conn, $build)['parts'];
    if (!isset($loaded['cpu'])) {
        return ['parts' => [], 'boards' => 0, 'kits' => 0, 'total' => 0.0, 'b' => ''];
    }
    $cpu = $loaded['cpu'];
    $boards = builder_candidates($conn, $build, 'mb');
    $mb = $first($inStock($boards));
    $gpus = $inStock(builder_candidates($conn, $build, 'gpu'));
    $target = (float) $cpu['price'] * 1.8;
    usort($gpus, static fn($a, $b) => [abs((float) $a['price'] - $target), (int) $a['id']] <=> [abs((float) $b['price'] - $target), (int) $b['id']]);
    $gpu = $first($gpus);
    $storage = $first($inStock(builder_candidates($conn, $build, 'storage')));
    if ($mb) $build['mb'] = (int) $mb['id'];
    if ($gpu) $build['gpu'] = (int) $gpu['id'];
    $kits = $mb ? builder_candidates($conn, $build, 'ram') : [];
    $ram = $first($inStock($kits));
    $psu = $first(array_values(array_filter($inStock(builder_candidates($conn, $build, 'psu')), static fn($r) => !$r['warnings'])));
    $case = $first($inStock(builder_candidates($conn, $build, 'case')));
    $cooler = null;
    if (($cpu['attrs']['bundled_cooler'] ?? '0') !== '1') {
        $maxH = $case ? (int) ($case['attrs']['max_cooler_mm'] ?? 0) : 0;
        $cooler = $first(array_values(array_filter($inStock(builder_candidates($conn, $build, 'cooler')),
            static fn($r) => !$maxH || ($r['attrs']['type'] ?? '') !== 'air' || (int) ($r['attrs']['height_mm'] ?? 0) <= $maxH)));
    }
    $parts = array_filter(['cpu' => $cpu, 'mb' => $mb, 'ram' => $ram, 'gpu' => $gpu, 'storage' => $storage, 'psu' => $psu, 'case' => $case, 'cooler' => $cooler]);
    $total = array_sum(array_map(static fn($p) => (float) $p['price'], $parts));
    return ['parts' => $parts, 'boards' => count($boards), 'kits' => count($kits), 'total' => $total,
            'b' => builder_encode(array_map(static fn($p) => (int) $p['id'], $parts))];
}

$cpus = [];
$teaser = ['parts' => [], 'boards' => 0, 'kits' => 0, 'total' => 0.0, 'b' => ''];
try {
    $cpus = builder_candidates($conn, [], 'cpu');
    usort($cpus, static fn($a, $b) => [(float) $a['price'], (int) $a['id']] <=> [(float) $b['price'], (int) $b['id']]);
    $teaserCpu = 0;
    foreach ($cpus as $c) {
        if (stripos($c['name'], 'Ryzen 5 7600') !== false && $c['in_stock']) $teaserCpu = (int) $c['id'];
    }
    if (!$teaserCpu) {
        foreach ($cpus as $c) if ($c['in_stock']) { $teaserCpu = (int) $c['id']; break; }
    }
    if ($teaserCpu) $teaser = home_default_build($conn, $teaserCpu);
} catch (PDOException $e) {
    log_exception($e, 'home builder teaser');
}
$slotLabels = array_map(static fn($s) => $s['label'], builder_slots());

/* ---------- product rows ---------- */
/**
 * Keep the given order but alternate products with a photo and products shown as spec tiles,
 * starting with whichever comes first, so a row is never four tiles (or four photos) in a row
 * when both kinds are available.
 */
function home_interleave(array $rows, int $limit): array
{
    $photo = $tile = [];
    foreach ($rows as $r) {
        if (product_image_url($r) !== null) $photo[] = $r; else $tile[] = $r;
    }
    $out = [];
    $nextPhoto = $rows && product_image_url($rows[0]) !== null;
    while (count($out) < $limit && ($photo || $tile)) {
        $take = ($nextPhoto && $photo) || !$tile ? array_shift($photo) : array_shift($tile);
        $out[] = $take;
        $nextPhoto = !$nextPhoto;
    }
    return $out;
}
$drops = [];
try {
    $drops = home_interleave(price_drops($conn, 100), 4);
} catch (PDOException $e) {
    $drops = [];
}
$newest = home_interleave($conn->query("SELECT p.*, c.name AS category_name, c.part_type FROM products p LEFT JOIN categories c ON c.id = p.category_id
    WHERE " . catalog_visible_sql('p') . " ORDER BY p.created_at DESC, p.id DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC), 4);
storefront_card_prime($conn, array_merge($drops, $newest));

/* ---------- shop by part: the builder's eight part types ---------- */
$partOrder = ['cpu', 'gpu', 'motherboard', 'memory', 'storage', 'psu', 'case', 'cooler'];
$parts = [];
foreach ($GLOBALS['tz_category_tree'] ?? [] as $root) {
    foreach (array_merge([$root], $root['children']) as $c) {
        $t = (string) ($c['part_type'] ?? 'other');
        if (in_array($t, $partOrder, true) && !isset($parts[$t]) && $c['product_count'] > 0) $parts[$t] = $c;
    }
}
uksort($parts, static fn($a, $b) => array_search($a, $partOrder, true) <=> array_search($b, $partOrder, true));

/* ---------- visit us ---------- */
$set = static function (string $k, string $d = '') {
    $v = setting($k, $d);
    return is_scalar($v) ? trim((string) $v) : $d;
};
$storeLine = $set('store_address_line', 'Hafeez Centre, Shop 214, 2nd floor');
$storeAddress = $set('store_address', '');
$storeHours = $set('store_hours', '');
$storePhone = $set('store_phone', '');
$storeWa = $set('whatsapp_number', '');
$todayHours = store_hours_today($storeHours);
?>

<section class="hero" aria-labelledby="hero-title">
    <div class="container hero__grid">
        <h1 class="hero__title" id="hero-title"><span class="hero__line">Aaj ka rate.</span> <span class="hero__line">Lahore se.</span></h1>
        <p class="hero__lead">Today's prices on <?php echo number_format($visibleCount); ?> PC parts from Hafeez Centre and Hall Road. Order with cash on delivery, anywhere in Pakistan.</p>
        <form class="hero-search" role="search" method="GET" action="<?php echo $h(site_url('shop/products.php')); ?>">
            <label class="visually-hidden" for="heroSearch">Search a part</label>
            <?php echo tz_icon('search', 22); ?>
            <input type="search" id="heroSearch" name="search" placeholder="Search a part, like RTX 4060 or Ryzen 5" data-placeholder-short="Search parts, e.g. RTX 4060" autocomplete="off" enterkeyhint="search">
            <button type="submit" class="btn--primary hero-search__btn">Search</button>
        </form>
        <div class="hero__actions">
            <a class="btn--primary" href="<?php echo $h(site_url('builder.php')); ?>">Build a PC</a>
            <a class="btn--secondary" href="<?php echo $h(site_url('deals.php')); ?>">Price drops this week</a>
        </div>

        <div class="board" aria-labelledby="board-title">
            <div class="board__head">
                <h2 class="board__title" id="board-title">Today's rates · Hafeez Centre</h2>
                <?php if ($updatedLabel !== ''): ?>
                <p class="board__time">Updated <time datetime="<?php echo $h(date('c', $updatedTs)); ?>"><?php echo $h($updatedLabel); ?></time></p>
                <?php endif; ?>
            </div>
            <?php if ($board): ?>
            <ol class="board__rows" id="rateBoard">
                <?php foreach ($board as $r): $chg = $r['change']; $dir = $chg['direction']; ?>
                <li>
                    <a class="rate-row rate-row--on-black rate-row--board" href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $r['id'])); ?>">
                        <span class="rate-row__name"><?php echo $h($r['name']); ?>
                            <?php if ($r['key_specs']): ?><span class="rate-row__spec specs"><?php foreach ($r['key_specs'] as $ks): ?><span><?php echo $h($ks); ?></span><?php endforeach; ?></span><?php endif; ?>
                        </span>
                        <span class="rate-row__price" data-from="<?php echo (int) round($chg['previous']); ?>" data-to="<?php echo (int) round($chg['price']); ?>"><span class="visually-hidden">Rs </span><span class="js-fig"><?php echo $fig($chg['price']); ?></span></span>
                        <span class="delta delta--<?php echo $h($dir); ?>"><span class="visually-hidden"><?php echo $dir === 'drop' ? 'Down Rs ' : ($dir === 'rise' ? 'Up Rs ' : 'No change this week'); ?></span><?php echo $dir === 'hold' ? '' : $fig($chg['delta']); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php else: ?>
            <p class="board__empty">Today's rates are being updated. Please check back in a few minutes.</p>
            <?php endif; ?>
            <div class="board__foot">
                <a class="board__all" href="<?php echo $h(site_url('rates.php')); ?>">See all rates</a>
                <span class="board__unit">Prices in PKR, change since last week</span>
            </div>
        </div>
        <script>
        /* The one orchestrated moment (docs/STOREFRONT.md "Motion"): figures tick from last week's
           rate to today's, 600 ms, 40 ms stagger, ease-out. Skipped for reduced motion. */
        (function () {
            var cells = document.querySelectorAll('#rateBoard .rate-row__price');
            if (!cells.length || !window.requestAnimationFrame || !window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            var fmt = function (n) { return Math.round(n).toLocaleString('en-US'); };
            var items = [];
            cells.forEach(function (c, i) {
                var from = +c.getAttribute('data-from'), to = +c.getAttribute('data-to'), el = c.querySelector('.js-fig');
                if (!el || !isFinite(from) || !isFinite(to) || from === to) return;
                el.textContent = fmt(from);
                items.push({ el: el, from: from, to: to, delay: i * 40 });
            });
            if (!items.length) return;
            var start = null, D = 600;
            var ease = function (t) { return 1 - Math.pow(1 - t, 3); };
            function frame(now) {
                if (start === null) start = now;
                var done = true;
                items.forEach(function (it) {
                    var t = Math.min(1, Math.max(0, (now - start - it.delay) / D));
                    if (t < 1) done = false;
                    it.el.textContent = fmt(it.from + (it.to - it.from) * ease(t));
                });
                if (!done) requestAnimationFrame(frame);
            }
            var go = function () { requestAnimationFrame(frame); };
            if (document.fonts && document.fonts.ready) document.fonts.ready.then(go, go); else go();
        })();
        </script>
    </div>
</section>

<div class="trust-row">
    <ul class="container trust-row__list">
        <li><?php echo tz_icon('cash', 20); ?>Cash on delivery nationwide</li>
        <li><?php echo tz_icon('rotate', 20); ?>7-day returns</li>
        <li><?php echo tz_icon('shield', 20); ?>Official warranty</li>
        <li><?php echo tz_icon('store', 20); ?>Pickup at Hafeez Centre</li>
    </ul>
</div>

<?php if ($cpus && $teaser['parts']): ?>
<section class="section teaser" aria-labelledby="teaser-title">
    <div class="container teaser__grid">
        <div class="teaser__copy">
            <h2 class="section-head__title" id="teaser-title">Build a PC at today's rates</h2>
            <p class="teaser__lead">Pick a processor and we only show parts that fit: socket, memory, power and case size. Every price is today's rate.</p>
            <div class="teaser__pick">
                <label class="field__label" for="teaserCpu">Processor</label>
                <select class="form-select teaser__select" id="teaserCpu" data-builder-api="<?php echo $h(site_url('api/builder.php')); ?>">
                    <?php foreach ($cpus as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"<?php echo isset($teaser['parts']['cpu']) && (int) $teaser['parts']['cpu']['id'] === (int) $c['id'] ? ' selected' : ''; ?><?php echo $c['in_stock'] ? '' : ' disabled'; ?>><?php echo $h($c['name']); ?><?php echo $c['in_stock'] ? '' : ' (out of stock)'; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <p class="teaser__fit" aria-live="polite">
                <span class="teaser__stat"><strong class="js-boards"><?php echo (int) $teaser['boards']; ?></strong> <span class="js-boards-label"><?php echo $teaser['boards'] === 1 ? 'board fits' : 'boards fit'; ?></span></span>
                <span class="teaser__stat"><strong class="js-kits"><?php echo (int) $teaser['kits']; ?></strong> <span class="js-kits-label"><?php echo $teaser['kits'] === 1 ? 'memory kit' : 'memory kits'; ?></span></span>
            </p>
            <a class="btn--primary js-teaser-open" href="<?php echo $h(site_url('builder.php?b=' . $teaser['b'])); ?>" data-href="<?php echo $h(site_url('builder.php')); ?>">Open the builder</a>
            <p class="teaser__presets">Or start from a preset build:
                <a href="<?php echo $h(site_url('builder.php?preset=budget')); ?>">Budget 1080p</a>
                <a href="<?php echo $h(site_url('builder.php?preset=1440p')); ?>">1440p gaming</a>
                <a href="<?php echo $h(site_url('builder.php?preset=creator')); ?>">Creator</a>
            </p>
        </div>
        <div class="build-card" aria-live="polite" aria-busy="false">
            <div class="build-card__head">
                <p class="build-card__title">A sensible build</p>
                <p class="build-card__note">In stock today</p>
            </div>
            <dl class="build-card__rows js-build-rows">
                <?php foreach ($teaser['parts'] as $slot => $p): ?>
                <div class="build-card__row">
                    <dt><?php echo $h($slotLabels[$slot] ?? $slot); ?></dt>
                    <dd class="build-card__name"><a href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $p['id'])); ?>"><?php echo $h($p['name']); ?></a></dd>
                    <dd class="build-card__price"><?php echo $fig($p['price']); ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <div class="build-card__total">
                <span>Total at today's rates</span>
                <strong class="js-build-total"><?php echo format_pkr($teaser['total']); ?></strong>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($drops): ?>
<section class="section" aria-labelledby="drops-title">
    <div class="container">
        <div class="section-head">
            <h2 class="section-head__title" id="drops-title">Price drops this week</h2>
            <a class="section-head__link" href="<?php echo $h(site_url('deals.php')); ?>">See all</a>
        </div>
        <div class="pgrid pgrid--4">
            <?php foreach ($drops as $p) echo render_product_card($p, $promotions); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($parts): ?>
<section class="section" aria-labelledby="parts-title">
    <div class="container">
        <div class="section-head">
            <h2 class="section-head__title" id="parts-title">Shop by part</h2>
            <a class="section-head__link" href="<?php echo $h(site_url('shop/products.php')); ?>">All products</a>
        </div>
        <ul class="part-list">
            <?php foreach ($parts as $type => $c): ?>
            <li>
                <a class="part-row" href="<?php echo $h(site_url('shop/products.php?category=' . (int) $c['id'])); ?>">
                    <span class="part-row__glyph"><?php echo spec_glyph($type, 44); ?></span>
                    <span class="part-row__name"><?php echo $h($c['name']); ?></span>
                    <span class="part-row__count"><?php echo (int) $c['product_count']; ?> <?php echo (int) $c['product_count'] === 1 ? 'product' : 'products'; ?>, <?php echo (int) $c['in_stock_count']; ?> in stock</span>
                    <span class="part-row__from"><?php if ($c['from_price'] !== null): ?><span class="part-row__from-label">from</span> <?php echo format_pkr($c['from_price']); ?><?php endif; ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if ($marketSellers): ?>
<section class="seller-band" aria-labelledby="sellers-title">
    <div class="container seller-band__grid">
        <div class="seller-band__intro">
            <h2 class="seller-band__title" id="sellers-title">Sold on TechZone</h2>
            <p class="seller-band__text">Vetted shops from Lahore's markets sell here too. We collect the cash and handle returns.</p>
        </div>
        <ul class="seller-band__list">
            <?php foreach ($marketSellers as $s): ?>
            <li>
                <a class="seller-tile" href="<?php echo $h(site_url('sellers/view.php?slug=' . rawurlencode((string) $s['slug']))); ?>">
                    <span class="seller-tile__name"><?php echo $h($s['name']); ?></span>
                    <span class="seller-tile__market"><?php echo $h($s['market']); ?>, <?php echo $h($s['city']); ?></span>
                    <span class="seller-tile__facts">
                        <span class="seller-tile__rating"><?php echo rating_stars((float) $s['rating'], 13); ?><span><?php echo number_format((float) $s['rating'], 1); ?></span></span>
                        <span class="seller-tile__count"><?php echo (int) $s['products']; ?> <?php echo (int) $s['products'] === 1 ? 'product' : 'products'; ?></span>
                    </span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="seller-band__cta">
            <a class="btn--on-black" href="<?php echo $h(site_url('sell/')); ?>">Sell on TechZone</a>
            <p class="seller-band__fee">8% per sale, no listing fee</p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($newest): ?>
<section class="section" aria-labelledby="new-title">
    <div class="container">
        <div class="section-head">
            <h2 class="section-head__title" id="new-title">New arrivals</h2>
            <a class="section-head__link" href="<?php echo $h(site_url('shop/products.php?sort=newest')); ?>">See all</a>
        </div>
        <div class="pgrid pgrid--4">
            <?php foreach ($newest as $p) echo render_product_card($p, $promotions); ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section visit" aria-labelledby="visit-title">
    <div class="container visit__grid">
        <div class="visit__card">
            <h2 class="visit__title" id="visit-title">Visit us</h2>
            <p class="visit__line"><?php echo $h($storeLine); ?></p>
            <p class="visit__area">Main Boulevard, Gulberg III, Lahore</p>
            <dl class="visit__facts">
                <?php if ($todayHours !== ''): ?>
                <div><dt>Hours</dt><dd><?php echo $h($todayHours); ?><?php if ($storeHours !== ''): ?><span class="visit__sub"><?php echo $h($storeHours); ?></span><?php endif; ?></dd></div>
                <?php endif; ?>
                <?php if ($storePhone !== ''): ?>
                <div><dt>Phone</dt><dd><a href="tel:<?php echo $h(preg_replace('/[^\d+]/', '', $storePhone)); ?>"><?php echo $h($storePhone); ?></a></dd></div>
                <?php endif; ?>
                <?php if ($storeWa !== ''): ?>
                <div><dt>WhatsApp</dt><dd><a href="https://wa.me/<?php echo $h(whatsapp_digits($storeWa)); ?>" rel="noopener"><?php echo $h(format_phone_pk($storeWa)); ?></a></dd></div>
                <?php endif; ?>
            </dl>
            <a class="btn--primary visit__btn" href="<?php echo $h(site_url('store.php')); ?>">How to get there</a>
        </div>
        <figure class="visit__map">
            <svg viewBox="0 0 640 440" role="img" aria-labelledby="mapTitle" preserveAspectRatio="xMidYMid slice">
                <title id="mapTitle">Sketch of Gulberg III: TechZone is on Main Boulevard between Liberty roundabout and Kalma Chowk</title>
                <rect width="640" height="440" fill="#EEF0F2"/>
                <g fill="none" stroke="#FFFFFF" stroke-linecap="round">
                    <path d="M-20 92 C 160 104, 360 74, 660 46" stroke-width="16"/>
                    <path d="M118 -20 L 150 460" stroke-width="10"/>
                    <path d="M262 -20 L 300 460" stroke-width="10"/>
                    <path d="M-20 310 L 660 270" stroke-width="10"/>
                    <path d="M420 -20 L 452 460" stroke-width="8"/>
                    <path d="M-20 214 L 660 186" stroke-width="7"/>
                </g>
                <path d="M-30 470 L 690 -30" fill="none" stroke="#FFFFFF" stroke-width="26" stroke-linecap="round"/>
                <path d="M556 -20 C 548 120, 560 300, 586 460" fill="none" stroke="#FFFFFF" stroke-width="24" stroke-linecap="round"/>
                <path d="M-30 470 L 690 -30" fill="none" stroke="#D9DCE1" stroke-width="1.5" stroke-dasharray="2 10"/>
                <circle cx="196" cy="331" r="34" fill="#EEF0F2" stroke="#FFFFFF" stroke-width="12"/>
                <circle cx="558" cy="80" r="20" fill="#EEF0F2" stroke="#FFFFFF" stroke-width="11"/>
                <g font-family="Manrope, system-ui, sans-serif" font-size="13" font-weight="600" fill="#5E6672">
                    <text x="214" y="236" transform="rotate(-34.8 214 236)">Main Boulevard Gulberg</text>
                    <text x="574" y="410" transform="rotate(-82 574 410)">Ferozepur Road</text>
                    <text x="122" y="390">Liberty</text>
                    <text x="586" y="124">Kalma Chowk</text>
                    <text x="24" y="84">Jail Road</text>
                </g>
                <g transform="translate(374 202)">
                    <path d="M0 0 C -14 -18, -22 -28, -22 -42 A 22 22 0 0 1 22 -42 C 22 -28, 14 -18, 0 0 Z" fill="#000000"/>
                    <circle cx="0" cy="-42" r="8" fill="#FFD400"/>
                    <rect x="30" y="-62" width="158" height="38" fill="#000000"/>
                    <text x="44" y="-38" font-family="Manrope, system-ui, sans-serif" font-size="14" font-weight="700" fill="#FFFFFF">TechZone, Shop 214</text>
                </g>
            </svg>
            <figcaption class="visually-hidden"><?php echo $h($storeAddress ?: $storeLine); ?></figcaption>
        </figure>
    </div>
</section>

<?php echo render_recently_viewed($conn); ?>

<script src="<?php echo $h(marketing_script_url('home.js')); ?>" defer></script>
<?php require_once 'includes/footer.php'; ?>
