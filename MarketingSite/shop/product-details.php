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
?>

<div class="container page">
    <nav aria-label="Breadcrumb">
        <ol class="crumbs">
            <li><a href="<?php echo $h(site_url('index.php')); ?>">Home</a></li>
            <li><a href="<?php echo $h(site_url('shop/products.php')); ?>">Products</a></li>
            <?php if (!empty($product['category_name'])): ?>
                <li><a href="<?php echo $h(site_url('shop/products.php?category=' . (int) $product['category_id'])); ?>"><?php echo $h($product['category_name']); ?></a></li>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="row g-4 g-lg-5 pdp">
        <div class="col-lg-7">
            <div class="pdp__gallery">
                <img src="<?php echo $h(getProductImagePath($product['imagelink'])); ?>" alt="<?php echo $h($product['name']); ?>" width="800" height="800">
            </div>
        </div>
        <div class="col-lg-5">
            <div class="buybox">
                <p class="buybox__cat"><a href="<?php echo $h(site_url('shop/products.php?category=' . (int) $product['category_id'])); ?>"><?php echo $h($product['category_name']); ?></a></p>
                <h1 class="buybox__title"><?php echo $h($product['name']); ?></h1>

                <p class="buybox__rating">
                    <?php if (count($reviews) > 0): ?>
                        <?php echo rating_stars((float) $avgRating, 16); ?>
                        <span><?php echo number_format((float) $avgRating, 1); ?></span>
                        <a href="#reviews" class="js-show-reviews"><?php echo count($reviews); ?> <?php echo count($reviews) === 1 ? 'review' : 'reviews'; ?></a>
                    <?php else: ?>
                        <a href="#reviews" class="js-show-reviews">No reviews yet</a>
                    <?php endif; ?>
                </p>

                <div class="buybox__price">
                    <?php if ($sale): ?>
                        <?php echo price_tag($sale['price'], 'md', 'js-sale-tag'); ?>
                        <p class="buybox__was">Regular price <s><span id="displayPrice"><?php echo format_pkr($basePrice); ?></span></s>. <?php echo $h($sale['label']); ?> with code <strong><?php echo $h($sale['code']); ?></strong> in your cart.</p>
                    <?php else: ?>
                        <p class="buybox__amount"><span id="displayPrice"><?php echo format_pkr($basePrice); ?></span></p>
                    <?php endif; ?>
                </div>

                <form id="addToCartForm" method="POST" action="<?php echo $h(site_url('api/add_to_cart.php')); ?>">
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

                    <div class="buybox__buy">
                        <div class="qty">
                            <label for="quantity" class="visually-hidden">Quantity</label>
                            <button type="button" class="qty__btn" onclick="decreaseQuantity()" aria-label="Decrease quantity">&minus;</button>
                            <input type="number" class="qty__input" name="quantity" id="quantity" value="1" min="1" max="<?php echo $inv; ?>" required>
                            <button type="button" class="qty__btn" onclick="increaseQuantity()" aria-label="Increase quantity">+</button>
                        </div>
                        <?php if ($isInStock): ?>
                            <button type="submit" class="btn btn-ink btn-buy" id="addToCartBtn">Add to cart</button>
                        <?php else: ?>
                            <button type="button" class="btn btn-ink btn-buy" id="addToCartBtn" disabled>Out of stock</button>
                        <?php endif; ?>
                    </div>
                </form>

                <ul class="delivery-box">
                    <li><?php echo tz_icon('cash'); ?><span><strong>Cash on delivery</strong> or JazzCash at checkout</span></li>
                    <li><?php echo tz_icon('truck'); ?><span><strong>Delivery 2&ndash;4 days in Lahore</strong>, 3&ndash;6 days nationwide</span></li>
                    <li><?php echo tz_icon('rotate'); ?><span><strong>7-day returns</strong> on unopened items</span></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="pdp-tabs" id="reviews">
        <ul class="nav tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="tab-desc" data-bs-toggle="tab" data-bs-target="#pane-desc" type="button" role="tab" aria-controls="pane-desc" aria-selected="true">Description</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="tab-reviews" data-bs-toggle="tab" data-bs-target="#pane-reviews" type="button" role="tab" aria-controls="pane-reviews" aria-selected="false">Reviews <span class="tabs__count"><?php echo count($reviews); ?></span></button>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="pane-desc" role="tabpanel" aria-labelledby="tab-desc" tabindex="0">
                <div class="prose"><?php echo $product['description'] !== null && $product['description'] !== '' ? nl2br($h($product['description'])) : '<p>No description yet.</p>'; ?></div>
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
</div>

<?php if (count($relatedProducts) > 0): ?>
<section class="section section--tight" aria-labelledby="related-title">
    <div class="container">
        <div class="section-head">
            <h2 id="related-title">Related products</h2>
            <a class="text-link" href="<?php echo $h(site_url('shop/products.php?category=' . (int) $product['category_id'])); ?>">View all</a>
        </div>
        <div class="pgrid pgrid--4">
            <?php foreach ($relatedProducts as $related) {
                echo render_product_card($related, $promotions, $reviewStats[(int) $related['id']] ?? null);
            } ?>
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
                document.getElementById('reviews').scrollIntoView({ block: 'start' });
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
                const cartLink = document.querySelector('.nav-icon-btn[title="Cart"]');
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
