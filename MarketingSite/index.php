<?php
$pageTitle = 'Home';
require_once 'includes/header.php';
require_once 'config/database.php';
require_once __DIR__ . '/../core/inventory.php';

// Get featured products (limit to 6) - only active products if column exists
$query = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
if (hasIsActiveColumn($conn)) {
    $query .= " WHERE p.is_active = 1";
}
$query .= " ORDER BY p.sold DESC LIMIT 6";
$stmt = $conn->query($query);
$featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$stmt = $conn->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Hero Banner -->
<section class="hero-banner">
    <div class="hero-banner-overlay">
        <div class="container">
            <div class="hero-banner-content">
                <p class="hero-eyebrow">Premium PC Accessories &middot; Pakistan</p>
                <h1>Everything you need for your next PC upgrade</h1>
                <p class="hero-sub">From gaming peripherals to high-performance components — all in one place.</p>
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-light btn-lg hero-cta-primary">
                        <i class="fas fa-shopping-cart me-2"></i>Shop Now
                    </a>
                    <a href="<?php echo htmlspecialchars(site_url('about.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-light btn-lg">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Promotions and Special Offers Section -->
<?php
// Get active promotions
$stmt = $conn->prepare("SELECT * FROM promotions WHERE is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY display_order ASC, created_at DESC LIMIT 3");
try {
    $stmt->execute();
    $promotions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $promotions = [];
}
?>

<?php if (count($promotions) > 0): ?>
<section class="promotions-section">
    <div class="container py-5">
        <div class="section-header section-header--promo">
            <h2>Special Offers & Promotions</h2>
            <p>Don't miss out on these amazing deals</p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php foreach ($promotions as $promo): ?>
            <div class="col-md-6 col-lg-4">
                <div class="promo-card">
                    <div class="promo-card__image-wrap">
                        <?php if (!empty($promo['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars(getPromotionImagePath($promo['image_url'])); ?>" class="promo-card__img" alt="<?php echo htmlspecialchars($promo['title']); ?>">
                        <?php else: ?>
                            <div class="promo-card__placeholder">
                                <i class="fas fa-tag"></i>
                            </div>
                        <?php endif; ?>
                        <?php if ($promo['discount_percentage'] > 0 || $promo['discount_amount'] > 0): ?>
                            <span class="promo-card__badge">
                                <?php if ($promo['discount_percentage'] > 0): ?>
                                    <?php echo number_format($promo['discount_percentage'], 0); ?>% OFF
                                <?php else: ?>
                                    Rs. <?php echo number_format($promo['discount_amount'], 2); ?> OFF
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="promo-card__body">
                        <h3 class="promo-card__title"><?php echo htmlspecialchars($promo['title']); ?></h3>
                        <?php if (!empty($promo['description'])): ?>
                            <p class="promo-card__desc"><?php echo htmlspecialchars($promo['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($promo['promo_code'])): ?>
                            <p class="promo-card__code">Code: <strong><?php echo htmlspecialchars($promo['promo_code']); ?></strong></p>
                        <?php endif; ?>
                        <?php if (!empty($promo['link_url'])): ?>
                            <a href="<?php echo htmlspecialchars($promo['link_url']); ?>" class="promo-card__btn">View Offer</a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="promo-card__btn">Shop Now</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="container mt-5">
    <div class="section-header">
        <h2>Featured Products</h2>
        <p>Our most popular items</p>
    </div>
    <!-- Quick category filters (link to full products page) -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="category-pills">
            <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="category-pill">All</a>
            <?php foreach (array_slice($categories, 0, 4) as $cat): ?>
                <a href="<?php echo htmlspecialchars(site_url('shop/products.php?category=' . (int)$cat['id']), ENT_QUOTES, 'UTF-8'); ?>" class="category-pill">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div>
            <select class="form-select form-select-sm" style="width: auto;" onchange="if(this.value) window.location.href=this.value;">
                <option value="">Sort products…</option>
                <option value="<?php echo htmlspecialchars(site_url('shop/products.php?sort=popular'), ENT_QUOTES, 'UTF-8'); ?>">Most Popular</option>
                <option value="<?php echo htmlspecialchars(site_url('shop/products.php?sort=price_asc'), ENT_QUOTES, 'UTF-8'); ?>">Price: Low to High</option>
                <option value="<?php echo htmlspecialchars(site_url('shop/products.php?sort=price_desc'), ENT_QUOTES, 'UTF-8'); ?>">Price: High to Low</option>
                <option value="<?php echo htmlspecialchars(site_url('shop/products.php?sort=newest'), ENT_QUOTES, 'UTF-8'); ?>">Newest First</option>
            </select>
        </div>
    </div>
    <div class="row">
        <?php foreach ($featuredProducts as $product): ?>
        <div class="col-md-4 mb-4">
            <div class="card product-card-enhanced h-100">
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
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <p class="card-text fw-bold mb-0">Rs. <?php echo number_format($product['price'], 2); ?></p>
                        <?php if ($product['sold'] > 10): ?>
                            <span class="badge badge-discount">Hot Deal</span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-2">
                        <span class="rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="far fa-star"></i>
                            <span class="count">(<?php echo max(1, (int)$product['sold']); ?>)</span>
                        </span>
                    </p>
                    <?php if ($product['inventory'] <= 0): ?>
                        <small class="text-danger mb-2"><i class="fas fa-exclamation-circle me-1"></i>Currently unavailable</small>
                    <?php elseif ($product['inventory'] <= 5): ?>
                        <small class="stock-low mb-2"><i class="fas fa-fire me-1"></i>Only <?php echo $product['inventory']; ?> left in stock!</small>
                    <?php else: ?>
                        <small class="text-success mb-2"><i class="fas fa-check-circle me-1"></i>In Stock</small>
                    <?php endif; ?>
                    <div class="mt-auto d-flex justify-content-between pt-2">
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
</div>

<div class="container mt-5 mb-5">
    <div class="section-header">
        <h2>Explore Our Collection</h2>
        <p>Browse our product categories</p>
    </div>
    <?php
    $fallbackGradients = ['#667eea,#764ba2', '#764ba2,#f093fb', '#f093fb,#4facfe', '#4facfe,#43e97b'];
    ?>
    <div class="row g-3">
        <?php foreach ($categories as $index => $category): ?>
        <?php
            $catName = $category['name'];
            $imgPath = !empty($category['image'])
                ? '../Admin Portal/uploads/' . rawurlencode($category['image'])
                : null;
            $grad = $fallbackGradients[$index % count($fallbackGradients)];
            list($c1, $c2) = explode(',', $grad);
        ?>
        <div class="col-md-6 mb-4">
            <a href="<?php echo htmlspecialchars(site_url('shop/products.php?category=' . (int)$category['id']), ENT_QUOTES, 'UTF-8'); ?>" class="category-card-link text-decoration-none">
                <div class="category-card-large d-flex align-items-stretch">
                    <div class="category-card-overlay flex-grow-1">
                        <div class="category-card-title">
                            <span class="category-title-bar"></span>
                            <div>
                                <h3 class="mb-1"><?php echo strtoupper(htmlspecialchars($catName)); ?></h3>
                                <p class="mb-0 category-card-sub">Browse <?php echo htmlspecialchars($catName); ?> products</p>
                            </div>
                        </div>
                    </div>
                    <div class="category-card-image">
                        <?php if ($imgPath): ?>
                            <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($catName); ?>" class="category-bg-img">
                        <?php else: ?>
                            <div class="category-image-placeholder" style="background: linear-gradient(135deg, <?php echo $c1; ?> 0%, <?php echo $c2; ?> 100%);"></div>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php
// Fetch 3 approved reviews with 4+ stars that have a comment
$reviewStmt = $conn->prepare("
    SELECT r.customer_name, r.rating, r.comment, p.name AS product_name
    FROM reviews r
    JOIN products p ON r.product_id = p.id
    WHERE r.status = 'Approved' AND r.rating >= 4 AND r.comment != ''
    ORDER BY r.rating DESC, r.created_at DESC
    LIMIT 3
");
$reviewStmt->execute();
$testimonials = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php if (count($testimonials) > 0): ?>
<!-- Testimonials -->
<div class="testimonials-strip">
    <div class="container">
        <div class="section-header">
            <h2>What Our Customers Say</h2>
        </div>
        <div class="row g-4">
            <?php foreach ($testimonials as $review):
                // Build initials from customer name
                $nameParts = explode(' ', trim($review['customer_name']));
                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
            ?>
            <div class="col-md-4">
                <div class="testimonial-card">
                    <div class="testimonial-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="<?php echo $i <= $review['rating'] ? 'fas' : 'far'; ?> fa-star"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="testimonial-text">"<?php echo htmlspecialchars($review['comment']); ?>"</p>
                    <div class="testimonial-author">
                        <div class="testimonial-avatar"><?php echo $initials; ?></div>
                        <div>
                            <div class="testimonial-name"><?php echo htmlspecialchars($review['customer_name']); ?></div>
                            <div class="testimonial-meta"><?php echo htmlspecialchars($review['product_name']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
