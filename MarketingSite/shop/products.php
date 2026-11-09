<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/catalog.php';

/* ---------- input (GET only; every value reaches SQL as a bound parameter) ---------- */
$str = static fn($k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
$search = mb_substr($str('search'), 0, 100);
$category_id = 0;
if (isset($_GET['category'])) {   // a repeated param (old desktop + mobile selects): last non-zero wins
    foreach ((array) $_GET['category'] as $cat) {
        if (is_scalar($cat) && (int) $cat > 0) $category_id = (int) $cat;
    }
}
$page = max(1, (int) ($str('page') ?: 1));
$perPage = (int) ($str('per_page') ?: 12);
$perPage = in_array($perPage, [6, 12, 24, 48], true) ? $perPage : 12;
$sort = $str('sort') ?: 'popular';
$sorts = ['popular' => 'Relevance', 'price_asc' => 'Price, low to high', 'price_desc' => 'Price, high to low', 'newest' => 'Newest', 'drop' => 'Biggest drop this week'];
if (!isset($sorts[$sort])) $sort = 'popular';
$inStockOnly = $str('in_stock') === '1';
$minPrice = $str('min') !== '' && is_numeric($str('min')) ? max(0, (int) $str('min')) : null;
$maxPrice = $str('max') !== '' && is_numeric($str('max')) ? max(0, (int) $str('max')) : null;
$sellerIds = [];
foreach ((array) ($_GET['seller'] ?? []) as $s) {
    if (is_scalar($s) && (int) $s > 0) $sellerIds[] = (int) $s;
}
$sellerIds = array_values(array_unique($sellerIds));

/* ---------- category, part type and its attribute facets ---------- */
$categories = $conn->query('SELECT * FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$selectedCategory = null;
foreach ($categories as $cat) if ((int) $cat['id'] === $category_id) $selectedCategory = $cat;
if (!$selectedCategory) $category_id = 0;
$partType = $selectedCategory ? (string) $selectedCategory['part_type'] : '';
if ($selectedCategory && $partType === 'other' && (($selectedCategory['slug'] ?? '') === 'monitors')) $partType = 'monitor';
if ($selectedCategory && $partType === 'other' && (($selectedCategory['slug'] ?? '') === 'networking')) $partType = 'network';
const CATALOG_FACETS = [
    'cpu' => ['socket', 'cores'], 'gpu' => ['chip', 'vram_gb'], 'memory' => ['memory_type', 'capacity_gb'],
    'monitor' => ['size_in', 'refresh_hz'], 'motherboard' => ['socket', 'memory_type', 'form_factor'],
    'psu' => ['wattage', 'efficiency'], 'cooler' => ['type'], 'storage' => ['interface', 'capacity_gb'],
    'network' => ['wifi_standard'], 'case' => [],
];
$facetKeys = CATALOG_FACETS[$partType] ?? [];
$facetSel = [];   // key => [values]
$rawF = isset($_GET['f']) && is_array($_GET['f']) ? $_GET['f'] : [];
foreach ($facetKeys as $k) {
    $vals = [];
    foreach ((array) ($rawF[$k] ?? []) as $v) {
        if (is_scalar($v) && trim((string) $v) !== '') $vals[] = mb_substr(trim((string) $v), 0, 60);
    }
    if ($vals) $facetSel[$k] = array_values(array_unique($vals));
}
$facetLabel = static function (string $k, string $v): string {
    if ($k === 'cores') return $v . ' cores';
    if ($k === 'type' && in_array($v, ['air', 'aio'], true)) return $v === 'aio' ? 'Liquid (AIO)' : 'Air';
    $rows = spec_rows([$k => $v]);
    return $rows[0]['value'] ?? $v;
};
$facetTitle = static fn(string $k): string => ['vram_gb' => 'Video memory', 'capacity_gb' => 'Capacity', 'size_in' => 'Screen size', 'chip' => 'Graphics chip'][$k] ?? (CATALOG_ATTR_LABELS[$k] ?? ucfirst(str_replace('_', ' ', $k)));

/* ---------- WHERE builder: base (no facets), facets optionally minus one key ---------- */
$base = [catalog_visible_sql('p')];
$baseP = [];
if ($search !== '') {
    $base[] = 'LOWER(p.name) LIKE LOWER(?)';
    $baseP[] = '%' . $search . '%';
}
if ($category_id > 0) {
    $catIds = category_with_children($conn, $category_id);
    $base[] = 'p.category_id IN (' . implode(', ', array_fill(0, count($catIds), '?')) . ')';
    array_push($baseP, ...$catIds);
}
if ($inStockOnly) $base[] = 'p.inventory > 0';
if ($minPrice !== null) { $base[] = 'p.price >= ?'; $baseP[] = $minPrice; }
if ($maxPrice !== null) { $base[] = 'p.price <= ?'; $baseP[] = $maxPrice; }
$sellerWhere = '';
$sellerP = [];
if ($sellerIds) {
    $sellerWhere = 'p.seller_id IN (' . implode(', ', array_fill(0, count($sellerIds), '?')) . ')';
    $sellerP = $sellerIds;
}
$facetWhere = static function (?string $except) use ($facetSel): array {
    $w = [];
    $params = [];
    foreach ($facetSel as $k => $vals) {
        if ($k === $except) continue;
        $w[] = 'EXISTS (SELECT 1 FROM product_attributes fa WHERE fa.product_id = p.id AND fa.attr_key = ? AND fa.attr_value IN ('
            . implode(', ', array_fill(0, count($vals), '?')) . '))';
        array_push($params, $k, ...$vals);
    }
    return [$w, $params];
};
$whereFor = static function (?string $exceptFacet, bool $withSeller = true) use ($base, $baseP, $sellerWhere, $sellerP, $facetWhere): array {
    [$fw, $fp] = $facetWhere($exceptFacet);
    $w = array_merge($base, $fw);
    $p = array_merge($baseP, $fp);
    if ($withSeller && $sellerWhere !== '') {
        $w[] = $sellerWhere;
        $p = array_merge($p, $sellerP);
    }
    return [implode(' AND ', $w), $p];
};

[$where, $params] = $whereFor(null);
$prevSql = '(SELECT h.price FROM price_history h WHERE h.product_id = p.id AND h.recorded_at <= NOW() - INTERVAL 7 DAY ORDER BY h.recorded_at DESC, h.id DESC LIMIT 1)';
$orderBy = [
    'price_asc' => 'p.price ASC, p.id',
    'price_desc' => 'p.price DESC, p.id',
    'newest' => 'p.created_at DESC, p.id DESC',
    'drop' => 'drop_pct DESC, p.sold DESC, p.id',
    'popular' => '(p.inventory > 0) DESC, p.sold DESC, p.created_at DESC, p.id',
][$sort];

$stmt = $conn->prepare("SELECT COUNT(*) FROM products p WHERE $where");
$stmt->execute($params);
$totalProducts = (int) $stmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare("SELECT p.*, c.name AS category_name, c.part_type,
        COALESCE(($prevSql - p.price) / NULLIF($prevSql, 0), 0) AS drop_pct
    FROM products p LEFT JOIN categories c ON p.category_id = c.id
    WHERE $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* facet counts (each facet ignores its own selection, so options stay visible) */
$facets = [];
foreach ($facetKeys as $k) {
    [$w, $p] = $whereFor($k);
    $st = $conn->prepare("SELECT a.attr_value AS v, COUNT(DISTINCT p.id) AS n FROM products p
        JOIN product_attributes a ON a.product_id = p.id AND a.attr_key = ? WHERE $w GROUP BY a.attr_value");
    $st->execute(array_merge([$k], $p));
    $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($facetSel[$k] ?? [] as $v) if (!isset($rows[$v])) $rows[$v] = 0;
    uksort($rows, static fn($a, $b) => is_numeric($a) && is_numeric($b) ? (float) $a <=> (float) $b : strnatcasecmp((string) $a, (string) $b));
    if ($rows) $facets[$k] = $rows;
}
/* seller counts (ignoring the seller filter) */
[$w, $p] = $whereFor(null, false);
$st = $conn->prepare("SELECT s.id, s.name, COUNT(p.id) AS n FROM sellers s JOIN products p ON p.seller_id = s.id WHERE $w GROUP BY s.id, s.name ORDER BY s.id");
$st->execute($p);
$sellerCounts = $st->fetchAll(PDO::FETCH_ASSOC);
/* price bounds and stock count in the current scope */
$st = $conn->prepare("SELECT MIN(p.price), MAX(p.price), SUM(p.inventory > 0) FROM products p WHERE $where");
$st->execute($params);
[$scopeMin, $scopeMax, $scopeInStock] = $st->fetch(PDO::FETCH_NUM);

/* ---------- URLs: current query with changes ---------- */
$current = array_filter([
    'search' => $search, 'category' => $category_id ?: null, 'in_stock' => $inStockOnly ? '1' : null,
    'min' => $minPrice, 'max' => $maxPrice, 'seller' => $sellerIds ?: null, 'f' => $facetSel ?: null,
    'sort' => $sort !== 'popular' ? $sort : null, 'per_page' => $perPage !== 12 ? $perPage : null,
], static fn($v) => $v !== null && $v !== '' && $v !== []);
$urlWith = static function (array $changes) use ($current): string {
    $q = $current;
    unset($q['page']);
    foreach ($changes as $k => $v) {
        if ($v === null) unset($q[$k]); else $q[$k] = $v;
    }
    $qs = http_build_query($q);
    return site_url('shop/products.php') . ($qs !== '' ? '?' . $qs : '');
};
$withoutFacet = static function (string $k, string $v) use ($facetSel, $urlWith): string {
    $sel = $facetSel;
    $sel[$k] = array_values(array_diff($sel[$k] ?? [], [$v]));
    if (!$sel[$k]) unset($sel[$k]);
    return $urlWith(['f' => $sel ?: null]);
};

$tree = category_tree($conn);
$pageTitle = $selectedCategory ? $selectedCategory['name'] : ($search !== '' ? 'Search: ' . $search : 'All products');
require_once __DIR__ . '/../includes/header.php';
$promotions = storefront_active_promotions($conn);
storefront_card_prime($conn, $products);
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$parentOfSelected = $selectedCategory && $selectedCategory['parent_id'] ? (int) $selectedCategory['parent_id'] : 0;
$pageHeading = $selectedCategory ? $selectedCategory['name'] : ($search !== '' ? 'Results for "' . $search . '"' : 'All products');

$active = [];   // [label, remove-url]
if ($search !== '') $active[] = ['"' . $search . '"', $urlWith(['search' => null])];
if ($inStockOnly) $active[] = ['In stock', $urlWith(['in_stock' => null])];
if ($minPrice !== null) $active[] = ['From ' . format_pkr($minPrice), $urlWith(['min' => null])];
if ($maxPrice !== null) $active[] = ['Up to ' . format_pkr($maxPrice), $urlWith(['max' => null])];
foreach ($sellerCounts as $s) {
    if (in_array((int) $s['id'], $sellerIds, true)) $active[] = [$s['name'], $urlWith(['seller' => array_values(array_diff($sellerIds, [(int) $s['id']])) ?: null])];
}
foreach ($facetSel as $k => $vals) foreach ($vals as $v) $active[] = [$facetLabel($k, $v), $withoutFacet($k, $v)];
?>

<div class="container page">
    <nav aria-label="Breadcrumb">
        <ol class="crumbs">
            <li><a href="<?php echo $h(site_url('index.php')); ?>">Home</a></li>
            <li><a href="<?php echo $h(site_url('shop/products.php')); ?>"<?php echo $selectedCategory ? '' : ' aria-current="page"'; ?>>Products</a></li>
            <?php if ($parentOfSelected): foreach ($categories as $c) if ((int) $c['id'] === $parentOfSelected): ?>
                <li><a href="<?php echo $h(site_url('shop/products.php?category=' . (int) $c['id'])); ?>"><?php echo $h($c['name']); ?></a></li>
            <?php endif; endif; ?>
            <?php if ($selectedCategory): ?>
                <li aria-current="page"><?php echo $h($selectedCategory['name']); ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="cat-head">
        <div>
            <h1><?php echo $h($pageHeading); ?></h1>
            <p class="cat-head__count"><?php echo $totalProducts; ?> <?php echo $totalProducts === 1 ? 'product' : 'products'; ?><?php echo $totalProducts ? ', ' . (int) $scopeInStock . ' in stock' : ''; ?></p>
        </div>
        <?php if ($totalProducts && $scopeMin !== null): ?>
        <div class="cat-head__aside">
            <p class="cat-head__from">Today's rates from<strong><?php echo format_pkr($scopeMin); ?></strong></p>
        </div>
        <?php endif; ?>
    </div>

    <div class="shop-layout">
        <aside class="offcanvas-lg offcanvas-start shop-filters" tabindex="-1" id="shopFilters" aria-labelledby="shopFiltersLabel">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title" id="shopFiltersLabel">Filters</h2>
                <button type="button" class="icon-btn" data-bs-dismiss="offcanvas" data-bs-target="#shopFilters" aria-label="Close filters"><?php echo tz_icon('close', 22); ?></button>
            </div>
            <div class="offcanvas-body">
                <div class="filters__group">
                    <p class="filters__legend" id="catTreeLabel">Category</p>
                    <ul class="cat-tree" aria-labelledby="catTreeLabel">
                        <li><a href="<?php echo $h($urlWith(['category' => null, 'f' => null])); ?>"<?php echo $category_id ? '' : ' aria-current="page"'; ?>><span>All products</span></a></li>
                        <?php foreach ($tree as $root): ?>
                        <li>
                            <a href="<?php echo $h($urlWith(['category' => (int) $root['id'], 'f' => null])); ?>"<?php echo $category_id === (int) $root['id'] ? ' aria-current="page"' : ''; ?>><span><?php echo $h($root['name']); ?></span><span class="cat-tree__count"><?php echo (int) $root['product_count']; ?></span></a>
                            <?php if ($root['children'] && ($category_id === (int) $root['id'] || $parentOfSelected === (int) $root['id'] || !$category_id)): ?>
                            <ul>
                                <?php foreach ($root['children'] as $ch): ?>
                                <li><a href="<?php echo $h($urlWith(['category' => (int) $ch['id'], 'f' => null])); ?>"<?php echo $category_id === (int) $ch['id'] ? ' aria-current="page"' : ''; ?>><span><?php echo $h($ch['name']); ?></span><span class="cat-tree__count"><?php echo (int) $ch['product_count']; ?></span></a></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <form method="GET" action="<?php echo $h(site_url('shop/products.php')); ?>" id="productSearchForm" class="filters" data-autosubmit>
                    <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo $h($search); ?>"><?php endif; ?>
                    <?php if ($category_id): ?><input type="hidden" name="category" value="<?php echo (int) $category_id; ?>"><?php endif; ?>
                    <?php if ($sort !== 'popular'): ?><input type="hidden" name="sort" value="<?php echo $h($sort); ?>"><?php endif; ?>
                    <?php if ($perPage !== 12): ?><input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>"><?php endif; ?>

                    <fieldset class="filters__group">
                        <legend>Availability</legend>
                        <label class="check-row"><input type="checkbox" name="in_stock" value="1"<?php echo $inStockOnly ? ' checked' : ''; ?>><span>In stock only</span></label>
                    </fieldset>

                    <?php foreach ($facets as $k => $rows): ?>
                    <fieldset class="filters__group">
                        <legend><?php echo $h($facetTitle($k)); ?></legend>
                        <?php foreach ($rows as $v => $n): $on = in_array((string) $v, $facetSel[$k] ?? [], true); ?>
                        <label class="check-row<?php echo !$n && !$on ? ' check-row--off' : ''; ?>">
                            <input type="checkbox" name="f[<?php echo $h($k); ?>][]" value="<?php echo $h($v); ?>"<?php echo $on ? ' checked' : ''; ?>>
                            <span><?php echo $h($facetLabel($k, (string) $v)); ?></span><span class="check-row__count"><?php echo (int) $n; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php endforeach; ?>

                    <fieldset class="filters__group">
                        <legend>Price</legend>
                        <div class="price-range">
                            <div class="field"><label class="field__label" for="minPrice">Min (Rs)</label><input class="field__input" type="number" inputmode="numeric" min="0" step="500" id="minPrice" name="min" value="<?php echo $minPrice !== null ? (int) $minPrice : ''; ?>" placeholder="<?php echo $scopeMin !== null ? (int) $scopeMin : ''; ?>"></div>
                            <div class="field"><label class="field__label" for="maxPrice">Max (Rs)</label><input class="field__input" type="number" inputmode="numeric" min="0" step="500" id="maxPrice" name="max" value="<?php echo $maxPrice !== null ? (int) $maxPrice : ''; ?>" placeholder="<?php echo $scopeMax !== null ? (int) $scopeMax : ''; ?>"></div>
                            <button type="submit" class="btn--secondary price-range__go">Go</button>
                        </div>
                    </fieldset>

                    <?php if (count($sellerCounts) > 1 || $sellerIds): ?>
                    <fieldset class="filters__group">
                        <legend>Seller</legend>
                        <?php foreach ($sellerCounts as $s): ?>
                        <label class="check-row"><input type="checkbox" name="seller[]" value="<?php echo (int) $s['id']; ?>"<?php echo in_array((int) $s['id'], $sellerIds, true) ? ' checked' : ''; ?>><span><?php echo $h($s['name']); ?></span><span class="check-row__count"><?php echo (int) $s['n']; ?></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php endif; ?>

                    <div class="filters__submit"><button type="submit" class="btn--primary btn--block">Show results</button></div>
                </form>
            </div>
        </aside>

        <div class="shop-main">
            <div class="shop-toolbar">
                <button class="btn--secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#shopFilters" aria-controls="shopFilters"><?php echo tz_icon('filter'); ?>Filters<?php echo $active ? ' (' . count($active) . ')' : ''; ?></button>
                <p class="shop-toolbar__info">
                    <?php if ($products): ?>Showing <?php echo $offset + 1; ?>&ndash;<?php echo $offset + count($products); ?> of <?php echo $totalProducts; ?><?php endif; ?>
                </p>
                <div class="shop-toolbar__controls">
                    <label for="sortSelect" class="visually-hidden">Sort by</label>
                    <select id="sortSelect" class="form-select form-select-tz" onchange="changeSort(this.value)">
                        <?php foreach ($sorts as $k => $label): ?>
                        <option value="<?php echo $h($k); ?>"<?php echo $sort === $k ? ' selected' : ''; ?>><?php echo $k === 'popular' ? 'Sort: ' : ''; ?><?php echo $h($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="perPageSelect" class="visually-hidden">Items per page</label>
                    <select id="perPageSelect" class="form-select form-select-tz d-none d-sm-block" onchange="changePerPage(this.value)">
                        <?php foreach ([6, 12, 24, 48] as $n): ?>
                            <option value="<?php echo $n; ?>"<?php echo $perPage === $n ? ' selected' : ''; ?>><?php echo $n; ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($active): ?>
            <div class="active-filters" aria-label="Active filters">
                <?php foreach ($active as [$label, $url]): ?>
                <a class="chip" href="<?php echo $h($url); ?>" aria-label="Remove filter <?php echo $h($label); ?>"><?php echo $h($label); ?><?php echo tz_icon('close', 14); ?></a>
                <?php endforeach; ?>
                <a class="active-filters__clear" href="<?php echo $h(site_url('shop/products.php' . ($category_id ? '?category=' . $category_id : ''))); ?>">Clear all</a>
            </div>
            <?php endif; ?>

            <?php if ($products): ?>
                <div class="pgrid pgrid--3" id="productList">
                    <?php foreach ($products as $product) echo render_product_card($product, $promotions); ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Pages" class="pager">
                        <ul class="pagination">
                            <?php $pg = static fn(int $n) => $urlWith(['page' => $n > 1 ? $n : null]); ?>
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h($pg($page - 1)); ?>">Previous</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Previous</span></li>
                            <?php endif; ?>
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h($pg(1)); ?>">1</a></li>
                                <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $h($pg($i)); ?>"<?php echo $i === $page ? ' aria-current="page"' : ''; ?>><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h($pg($totalPages)); ?>"><?php echo $totalPages; ?></a></li>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h($pg($page + 1)); ?>">Next</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Next</span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty">
                    <h2>Nothing matches these filters</h2>
                    <p>Remove a filter or try a shorter search, like "4060" instead of the full model name.</p>
                    <a href="<?php echo $h(site_url('shop/products.php' . ($category_id ? '?category=' . $category_id : ''))); ?>" class="btn--primary">Clear filters</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
function changeSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page');
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
