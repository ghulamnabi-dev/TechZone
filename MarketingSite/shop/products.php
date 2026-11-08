<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/inventory.php';

// Get search, filter and sort parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
// Handle category - if multiple values exist (from both desktop and mobile selects), take the last non-zero one
$category_id = 0;
if (isset($_GET['category'])) {
    if (is_array($_GET['category'])) {
        // If it's an array, get the last non-zero value
        $category_id = 0;
        foreach ($_GET['category'] as $cat) {
            $cat = (int)$cat;
            if ($cat > 0) {
                $category_id = $cat;
            }
        }
    } else {
        $category_id = (int)$_GET['category'];
    }
}
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(6, min(48, (int)$_GET['per_page'])) : 12; // Allow 6, 12, 24, 48 items per page
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';
$offset = ($page - 1) * $perPage;

// Helper function to build pagination URL
function buildPaginationUrl($page, $search, $category_id, $perPage, $sort) {
    $params = [];
    if ($page > 1) $params['page'] = $page;
    if (!empty($search)) $params['search'] = $search;
    if ($category_id > 0) $params['category'] = $category_id;
    if ($perPage != 12) $params['per_page'] = $perPage;
    if (!empty($sort) && $sort !== 'popular') $params['sort'] = $sort;
    return '?' . http_build_query($params);
}

// Build query
$where = "1=1";
$params = [];

// Only show active products if is_active column exists
if (hasIsActiveColumn($conn)) {
    $where .= " AND p.is_active = 1";
}

if (!empty($search)) {
    // Search only in product name (title) with partial word matching (case-insensitive)
    $where .= " AND LOWER(p.name) LIKE LOWER(?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
}

if ($category_id > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
}

// Sorting
$allowedSorts = ['popular', 'price_asc', 'price_desc', 'newest'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'popular';
}

switch ($sort) {
    case 'price_asc':
        $orderBy = 'p.price ASC';
        break;
    case 'price_desc':
        $orderBy = 'p.price DESC';
        break;
    case 'newest':
        $orderBy = 'p.created_at DESC';
        break;
    case 'popular':
    default:
        $orderBy = 'p.sold DESC, p.created_at DESC';
        break;
}

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM products p WHERE $where";
$stmt = $conn->prepare($countQuery);
$stmt->execute($params);
$totalProducts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalProducts / $perPage);

// Get products
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE $where ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Presentation data: per-category counts, promotions (sale tags), review averages
$categoryCounts = storefront_category_counts($conn);
$allCount = array_sum($categoryCounts);
$promotions = storefront_active_promotions($conn);
$reviewStats = storefront_review_stats($conn);
$selectedCategory = null;
foreach ($categories as $cat) {
    if ((int) $cat['id'] === $category_id) {
        $selectedCategory = $cat;
    }
}
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$pageHeading = $selectedCategory ? $selectedCategory['name'] : ($search !== '' ? 'Results for "' . $search . '"' : 'All products');
?>

<div class="container page">
    <nav aria-label="Breadcrumb">
        <ol class="crumbs">
            <li><a href="<?php echo $h(site_url('index.php')); ?>">Home</a></li>
            <li><a href="<?php echo $h(site_url('shop/products.php')); ?>"<?php echo $selectedCategory ? '' : ' aria-current="page"'; ?>>Products</a></li>
            <?php if ($selectedCategory): ?>
                <li aria-current="page"><?php echo $h($selectedCategory['name']); ?></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="page-title">
        <h1><?php echo $h($pageHeading); ?></h1>
        <span class="page-title__count"><?php echo (int) $totalProducts; ?> <?php echo (int) $totalProducts === 1 ? 'product' : 'products'; ?></span>
    </div>

    <div class="shop-layout">
        <aside class="offcanvas-lg offcanvas-start shop-filters" tabindex="-1" id="shopFilters" aria-labelledby="shopFiltersLabel">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title" id="shopFiltersLabel">Filters</h2>
                <button type="button" class="icon-btn" data-bs-dismiss="offcanvas" data-bs-target="#shopFilters" aria-label="Close filters"><?php echo tz_icon('close', 22); ?></button>
            </div>
            <div class="offcanvas-body">
                <form method="GET" action="<?php echo $h(site_url('shop/products.php')); ?>" id="productSearchForm" class="filters">
                    <?php if ($search !== ''): ?>
                        <input type="hidden" name="search" value="<?php echo $h($search); ?>">
                    <?php endif; ?>
                    <?php if ($sort !== 'popular'): ?>
                        <input type="hidden" name="sort" value="<?php echo $h($sort); ?>">
                    <?php endif; ?>
                    <input type="hidden" name="per_page" value="<?php echo (int) $perPage; ?>">
                    <fieldset class="filters__group">
                        <legend>Category</legend>
                        <label class="radio-row">
                            <input type="radio" name="category" value="0" <?php echo $category_id === 0 ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <span>All products</span><span class="radio-row__count"><?php echo (int) $allCount; ?></span>
                        </label>
                        <?php foreach ($categories as $cat): ?>
                        <label class="radio-row">
                            <input type="radio" name="category" value="<?php echo (int) $cat['id']; ?>" <?php echo $category_id === (int) $cat['id'] ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <span><?php echo $h($cat['name']); ?></span><span class="radio-row__count"><?php echo (int) ($categoryCounts[(int) $cat['id']] ?? 0); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </fieldset>
                    <?php if ($search !== ''): ?>
                    <div class="filters__group">
                        <p class="filters__legend">Search</p>
                        <p class="filters__active">"<?php echo $h($search); ?>" <a href="<?php echo $h(site_url('shop/products.php' . ($category_id ? '?category=' . $category_id : ''))); ?>">Clear</a></p>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-ink w-100 d-lg-none">Show results</button>
                </form>
            </div>
        </aside>

        <div class="shop-main">
            <div class="shop-toolbar">
                <button class="btn btn-line btn-sm-tz d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#shopFilters" aria-controls="shopFilters"><?php echo tz_icon('filter'); ?>Filters</button>
                <p class="shop-toolbar__info">
                    <?php if (count($products) > 0): ?>
                        Showing <?php echo $offset + 1; ?>&ndash;<?php echo min($offset + count($products), $totalProducts); ?> of <?php echo (int) $totalProducts; ?>
                    <?php endif; ?>
                </p>
                <div class="shop-toolbar__controls">
                    <label for="sortSelect" class="visually-hidden">Sort by</label>
                    <select id="sortSelect" class="form-select form-select-tz" onchange="changeSort(this.value)">
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Sort: Relevance</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: low to high</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: high to low</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                    </select>
                    <label for="perPageSelect" class="visually-hidden">Items per page</label>
                    <select id="perPageSelect" class="form-select form-select-tz d-none d-sm-block" onchange="changePerPage(this.value)">
                        <?php foreach ([6, 12, 24, 48] as $n): ?>
                            <option value="<?php echo $n; ?>" <?php echo $perPage == $n ? 'selected' : ''; ?>><?php echo $n; ?> per page</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if (count($products) > 0): ?>
                <div class="pgrid pgrid--3" id="productList">
                    <?php foreach ($products as $product) {
                        echo render_product_card($product, $promotions, $reviewStats[(int) $product['id']] ?? null);
                    } ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Pages" class="pager">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h(buildPaginationUrl($page - 1, $search, $category_id, $perPage, $sort)); ?>">Previous</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Previous</span></li>
                            <?php endif; ?>
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h(buildPaginationUrl(1, $search, $category_id, $perPage, $sort)); ?>">1</a></li>
                                <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <?php endif; ?>
                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $h(buildPaginationUrl($i, $search, $category_id, $perPage, $sort)); ?>"<?php echo $i == $page ? ' aria-current="page"' : ''; ?>><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h(buildPaginationUrl($totalPages, $search, $category_id, $perPage, $sort)); ?>"><?php echo $totalPages; ?></a></li>
                            <?php endif; ?>
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item"><a class="page-link" href="<?php echo $h(buildPaginationUrl($page + 1, $search, $category_id, $perPage, $sort)); ?>">Next</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Next</span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty">
                    <h2>No products found</h2>
                    <p>Try another category or a shorter search term.</p>
                    <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="btn btn-ink">View all products</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function changePerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', value);
    url.searchParams.set('page', '1'); // Reset to first page when changing items per page
    window.location.href = url.toString();
}

function changeSort(sortValue) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', sortValue);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
