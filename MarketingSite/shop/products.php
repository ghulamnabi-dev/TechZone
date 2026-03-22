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
?>

<div class="container mt-5">
    <div class="section-header mb-4">
        <h2>Our Products</h2>
        <p>Browse our complete catalog</p>
    </div>
    
    <!-- Search and Filter -->
    <div class="mb-4">
        <form method="GET" action="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" id="productSearchForm" class="search-form-wrapper">
            <div class="input-group input-group-lg shadow-sm flex-nowrap">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-secondary"></i></span>
                <input type="text" class="form-control border-start-0 border-end-0" name="search" id="searchInput" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>" style="border-left: none; border-right: none;">
                
                <!-- Desktop Category Selector -->
                <select name="category" id="categorySelect" class="form-select border-start-0 border-end-0 d-none d-md-block" style="border-left: none; border-right: none; max-width: 200px; font-size: 0.9rem; padding: 0.5rem 0.75rem;">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button class="btn btn-primary border-start-0" type="submit" style="border-left: none;"><span class="d-none d-sm-inline">Search</span><span class="d-sm-none"><i class="fas fa-search"></i></span></button>
                <?php if (!empty($search) || $category_id > 0): ?>
                    <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary border-start-0" style="border-left: none;" title="Clear filters"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </div>
            <!-- Mobile Category Selector (separate for mobile view) -->
            <div class="d-md-none mt-2">
                <select id="categorySelectMobile" class="form-select" onchange="document.getElementById('categorySelect').value = this.value; document.getElementById('productSearchForm').submit();">
                    <option value="0">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($category_id == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
            <script>
            // Ensure category is preserved when searching, and search is preserved when changing category
            document.addEventListener('DOMContentLoaded', function() {
                const form = document.getElementById('productSearchForm');
                const categorySelect = document.getElementById('categorySelect');
                const categorySelectMobile = document.getElementById('categorySelectMobile');
                const searchInput = document.getElementById('searchInput');
                
                if (form && categorySelect) {
                    // When desktop category changes, submit form (preserving search)
                    categorySelect.addEventListener('change', function() {
                        // Sync mobile select if it exists
                        if (categorySelectMobile) {
                            categorySelectMobile.value = this.value;
                        }
                        form.submit();
                    });
                    
                    // Sync mobile and desktop selects (mobile has no name, so it won't submit)
                    if (categorySelectMobile) {
                        // Mobile select already updates desktop select in onchange, just sync on load
                        categorySelectMobile.value = categorySelect.value;
                    }
                }
            });
            </script>
            <?php if (!empty($search) || $category_id > 0): ?>
                <div class="mt-2">
                    <?php if (!empty($search)): ?>
                        <span class="badge bg-primary me-2"><i class="fas fa-search me-1"></i>Search: "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                    <?php if ($category_id > 0): ?>
                        <?php 
                        $selectedCategory = null;
                        foreach ($categories as $cat) {
                            if ($cat['id'] == $category_id) {
                                $selectedCategory = $cat;
                                break;
                            }
                        }
                        ?>
                        <span class="badge bg-info"><i class="fas fa-filter me-1"></i>Category: <?php echo htmlspecialchars($selectedCategory['name']); ?></span>
                    <?php endif; ?>
                    <span class="badge bg-secondary ms-2"><?php echo $totalProducts; ?> product(s) found</span>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Items per page selector and pagination info -->
    <?php if (count($products) > 0): ?>
        <div class="row align-items-center mb-3">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <label for="perPageSelect" class="me-2 mb-0 text-muted">Items per page:</label>
                    <select id="perPageSelect" class="form-select form-select-sm" style="width: auto;" onchange="changePerPage(this.value)">
                        <option value="6" <?php echo $perPage == 6 ? 'selected' : ''; ?>>6</option>
                        <option value="12" <?php echo $perPage == 12 ? 'selected' : ''; ?>>12</option>
                        <option value="24" <?php echo $perPage == 24 ? 'selected' : ''; ?>>24</option>
                        <option value="48" <?php echo $perPage == 48 ? 'selected' : ''; ?>>48</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted mb-1">
                    Showing <strong><?php echo $offset + 1; ?></strong> to <strong><?php echo min($offset + count($products), $totalProducts); ?></strong> 
                    of <strong><?php echo $totalProducts; ?></strong> products
                </p>
                <div>
                    <select class="form-select form-select-sm" style="width: auto; display: inline-block;" onchange="changeSort(this.value)">
                        <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    </select>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Products Grid -->
    <?php if (count($products) > 0): ?>
        <div class="row" id="productList">
            <?php foreach ($products as $product): ?>
                <div class="col-12 col-sm-6 col-md-4 mb-4">
                    <div class="card product-card-enhanced h-100 d-flex flex-column">
                        <div class="product-image-wrapper">
                            <img
                                src="<?php echo htmlspecialchars(getProductImagePath($product['imagelink'])); ?>"
                                class="product-image"
                                alt="<?php echo htmlspecialchars($product['name']); ?>"
                            >
                            <div class="product-overlay">
                                <div class="btn-group-overlay">
                                    <a href="<?php echo htmlspecialchars(site_url('shop/product-details.php?id=' . (int)$product['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light btn-sm">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                    <?php if ($product['inventory'] > 0): ?>
                                        <a href="<?php echo htmlspecialchars(site_url('shop/cart.php?add=' . (int)$product['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-cart-plus"></i> Add
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>
                                            <i class="fas fa-times-circle"></i> Out
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if ($product['inventory'] > 0): ?>
                                <span class="badge bg-success position-absolute top-0 end-0 m-2">
                                    <i class="fas fa-check-circle me-1"></i>In Stock
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger position-absolute top-0 end-0 m-2">
                                    <i class="fas fa-exclamation-circle me-1"></i>Out of Stock
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-1">
                                <a href="<?php echo htmlspecialchars(site_url('shop/product-details.php?id=' . (int)$product['id']), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none text-dark">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </h5>
                            <p class="card-text text-muted mb-1"><?php echo htmlspecialchars($product['category_name']); ?></p>
                            <p class="card-text fw-bold mb-2">Rs. <?php echo number_format($product['price'], 2); ?></p>
                            <?php if ($product['inventory'] <= 0): ?>
                                <small class="text-danger mb-2"><i class="fas fa-exclamation-circle me-1"></i>Currently unavailable</small>
                            <?php elseif ($product['inventory'] <= 5): ?>
                                <small class="stock-low mb-2"><i class="fas fa-fire me-1"></i>Only <?php echo $product['inventory']; ?> left!</small>
                            <?php else: ?>
                                <small class="text-success mb-2"><i class="fas fa-check-circle me-1"></i>In Stock</small>
                            <?php endif; ?>
                            <div class="mt-auto d-flex justify-content-between pt-2 d-none d-md-flex">
                                <a href="<?php echo htmlspecialchars(site_url('shop/product-details.php?id=' . (int)$product['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($product['inventory'] > 0): ?>
                                    <a href="<?php echo htmlspecialchars(site_url('shop/cart.php?add=' . (int)$product['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-success btn-sm">
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>Out of Stock</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center flex-wrap">
                    <!-- First Page -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildPaginationUrl(1, $search, $category_id, $perPage, $sort); ?>" aria-label="First">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-double-left"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildPaginationUrl($page - 1, $search, $category_id, $perPage, $sort); ?>" aria-label="Previous">
                                <i class="fas fa-angle-left"></i> Previous
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-left"></i> Previous</span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildPaginationUrl(1, $search, $category_id, $perPage, $sort); ?>">1</a>
                        </li>
                        <?php if ($startPage > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo buildPaginationUrl($i, $search, $category_id, $perPage, $sort); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildPaginationUrl($totalPages, $search, $category_id, $perPage, $sort); ?>"><?php echo $totalPages; ?></a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Next Page -->
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildPaginationUrl($page + 1, $search, $category_id, $perPage, $sort); ?>" aria-label="Next">
                                Next <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Next <i class="fas fa-angle-right"></i></span>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Last Page -->
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo buildPaginationUrl($totalPages, $search, $category_id, $perPage, $sort); ?>" aria-label="Last">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="fas fa-angle-double-right"></i></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info text-center">
            <h4>No products found</h4>
            <p>Try adjusting your search or filter criteria.</p>
            <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">View All Products</a>
        </div>
    <?php endif; ?>
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
