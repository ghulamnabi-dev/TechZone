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

$pageTitle = 'Product Details';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-5">
    
    <div class="row">
        <div class="col-md-6">
            <img src="<?php echo htmlspecialchars(getProductImagePath($product['imagelink'])); ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($product['name']); ?>">
        </div>
        <div class="col-md-6">
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            <p class="text-muted">Category: <a href="<?php echo htmlspecialchars(site_url('shop/products.php?category=' . (int)$product['category_id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($product['category_name']); ?></a></p>
            
            <!-- Rating -->
            <?php if (count($reviews) > 0): ?>
                <div class="mb-3">
                    <div class="d-flex align-items-center">
                        <span class="text-warning">
                            <?php 
                            $fullStars = floor($avgRating);
                            $halfStar = ($avgRating - $fullStars) >= 0.5;
                            for ($i = 0; $i < $fullStars; $i++) echo '★';
                            if ($halfStar) echo '☆';
                            for ($i = $fullStars + ($halfStar ? 1 : 0); $i < 5; $i++) echo '☆';
                            ?>
                        </span>
                        <span class="ms-2"><?php echo $avgRating; ?> (<?php echo count($reviews); ?> reviews)</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <h3 class="text-primary mb-2">
                <span id="displayPrice"><?php echo format_pkr($basePrice); ?></span>
                <?php if ($hasVariations): ?>
                    <small class="text-muted d-block" style="font-size: 0.6em;">Base price</small>
                <?php endif; ?>
            </h3>
            
            <div class="mb-3">
                <?php if ($product['inventory'] > 0): ?>
                    <span class="badge bg-success" id="stockBadge">In Stock (<?php echo $product['inventory']; ?> available)</span>
                <?php else: ?>
                    <span class="badge bg-danger">Out of Stock</span>
                <?php endif; ?>
            </div>
            
            <p class="mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            
            <!-- Product Variations Form -->
            <form id="addToCartForm" method="POST" action="<?php echo htmlspecialchars(site_url('api/add_to_cart.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                
                <!-- Size Selection -->
                <?php if (count($sizes) > 0): ?>
                    <?php 
                    // Only show size selection if there are multiple sizes
                    // If there's only one size (the default), hide the selection but still include it as a hidden field
                    $showSizeSelection = count($sizes) > 1;
                    $defaultSizeIndex = -1;
                    foreach ($sizes as $index => $size) {
                        if (isset($size['is_default']) && $size['is_default'] == 1) {
                            $defaultSizeIndex = $index;
                            break;
                        }
                    }
                    if ($defaultSizeIndex == -1) $defaultSizeIndex = 0; // Use first if no default
                    ?>
                    <?php if ($showSizeSelection): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Size <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2" role="group" aria-label="Size selection">
                                <?php foreach ($sizes as $index => $size): ?>
                                    <input type="radio" class="btn-check" name="size" id="size_<?php echo $size['id']; ?>" 
                                           value="<?php echo htmlspecialchars($size['variation_value']); ?>" 
                                           data-price-modifier="<?php echo $size['price_modifier']; ?>" 
                                           data-inventory="<?php echo isset($size['inventory']) ? $size['inventory'] : $product['inventory']; ?>"
                                           <?php echo $index === $defaultSizeIndex ? 'checked' : ''; ?>
                                           required>
                                    <label class="btn btn-outline-primary btn-sm" for="size_<?php echo $size['id']; ?>" style="min-width: 100px;">
                                        <?php echo htmlspecialchars($size['variation_value']); ?>
                                        <?php if (isset($size['is_default']) && $size['is_default'] == 1): ?>
                                            <small class="d-block text-dark"><i class="fas fa-star"></i> Default</small>
                                        <?php endif; ?>
                                        <?php if ($size['price_modifier'] != 0): ?>
                                            <small class="d-block"><?php echo $size['price_modifier'] > 0 ? '+' : ''; ?><?php echo format_pkr($size['price_modifier']); ?></small>
                                        <?php endif; ?>
                                        <small class="d-block text-muted">Stock: <?php echo isset($size['inventory']) ? $size['inventory'] : $product['inventory']; ?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Hidden field for single default size variation -->
                        <input type="hidden" name="size" value="<?php echo htmlspecialchars($sizes[$defaultSizeIndex]['variation_value']); ?>" 
                               data-price-modifier="<?php echo $sizes[$defaultSizeIndex]['price_modifier']; ?>" 
                               data-inventory="<?php echo isset($sizes[$defaultSizeIndex]['inventory']) ? $sizes[$defaultSizeIndex]['inventory'] : $product['inventory']; ?>">
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Color Selection -->
                <?php if (count($colors) > 0): ?>
                    <?php 
                    // Only show color selection if there are multiple colors
                    // If there's only one color (the default), hide the selection but still include it as a hidden field
                    $showColorSelection = count($colors) > 1;
                    $defaultColorIndex = -1;
                    foreach ($colors as $index => $color) {
                        if (isset($color['is_default']) && $color['is_default'] == 1) {
                            $defaultColorIndex = $index;
                            break;
                        }
                    }
                    if ($defaultColorIndex == -1) $defaultColorIndex = 0; // Use first if no default
                    ?>
                    <?php if ($showColorSelection): ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Color <span class="text-danger">*</span></label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($colors as $index => $color): ?>
                                    <input type="radio" class="btn-check" name="color" id="color_<?php echo $color['id']; ?>" 
                                           value="<?php echo htmlspecialchars($color['variation_value']); ?>" 
                                           data-price-modifier="<?php echo $color['price_modifier']; ?>" 
                                           data-inventory="<?php echo isset($color['inventory']) && $color['inventory'] > 0 ? $color['inventory'] : $product['inventory']; ?>"
                                           <?php echo $index === $defaultColorIndex ? 'checked' : ''; ?>
                                           required>
                                    <label class="btn btn-outline-primary" for="color_<?php echo $color['id']; ?>">
                                        <?php echo htmlspecialchars($color['variation_value']); ?>
                                        <?php if (isset($color['is_default']) && $color['is_default'] == 1): ?>
                                            <small class="d-block text-dark"><i class="fas fa-star"></i> Default</small>
                                        <?php endif; ?>
                                        <?php if ($color['price_modifier'] != 0): ?>
                                            <small class="d-block"><?php echo $color['price_modifier'] > 0 ? '+' : ''; ?><?php echo format_pkr($color['price_modifier']); ?></small>
                                        <?php else: ?>
                                            <small class="d-block text-muted">Base price</small>
                                        <?php endif; ?>
                                        <small class="d-block text-muted">Stock: <?php echo isset($color['inventory']) && $color['inventory'] > 0 ? $color['inventory'] : $product['inventory']; ?></small>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Hidden field for single default color variation -->
                        <input type="hidden" name="color" value="<?php echo htmlspecialchars($colors[$defaultColorIndex]['variation_value']); ?>" 
                               data-price-modifier="<?php echo $colors[$defaultColorIndex]['price_modifier']; ?>" 
                               data-inventory="<?php echo isset($colors[$defaultColorIndex]['inventory']) && $colors[$defaultColorIndex]['inventory'] > 0 ? $colors[$defaultColorIndex]['inventory'] : $product['inventory']; ?>">
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Quantity Selection -->
                <div class="mb-3">
                    <label for="quantity" class="form-label fw-bold">Quantity</label>
                    <div class="input-group" style="max-width: 150px;">
                        <button type="button" class="btn btn-outline-secondary" onclick="decreaseQuantity()">-</button>
                        <input type="number" class="form-control text-center" name="quantity" id="quantity" value="1" min="1" max="<?php echo $product['inventory']; ?>" required>
                        <button type="button" class="btn btn-outline-secondary" onclick="increaseQuantity()">+</button>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <?php 
                    // Check if product is in stock (base inventory or any variation has stock)
                    $isInStock = false;
                    if ($product['inventory'] > 0) {
                        $isInStock = true;
                    } else {
                        // Check if any variation has inventory
                        foreach ($sizes as $size) {
                            if (isset($size['inventory']) && $size['inventory'] > 0) {
                                $isInStock = true;
                                break;
                            }
                        }
                        if (!$isInStock) {
                            foreach ($colors as $color) {
                                if (isset($color['inventory']) && $color['inventory'] > 0) {
                                    $isInStock = true;
                                    break;
                                }
                            }
                        }
                    }
                    ?>
                    <?php if ($isInStock): ?>
                        <button type="submit" class="btn btn-success" id="addToCartBtn">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary" id="addToCartBtn" disabled>
                            <i class="fas fa-ban"></i> Out of Stock
                        </button>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">Continue Shopping</a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Reviews Section -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="section-header mb-4">
                <h3>Customer Reviews</h3>
            </div>
            
            <?php if (count($reviews) > 0): ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="mb-0"><?php echo htmlspecialchars($review['customer_name']); ?></h6>
                                    <small class="text-muted"><?php echo date('F j, Y', strtotime($review['created_at'])); ?></small>
                                </div>
                                <div class="text-warning">
                                    <?php for ($i = 0; $i < $review['rating']; $i++) echo '★'; ?>
                                    <?php for ($i = $review['rating']; $i < 5; $i++) echo '☆'; ?>
                                </div>
                            </div>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <?php if (!empty($review['admin_response'])): ?>
                                <div class="mt-2 p-2 bg-light rounded">
                                    <small><strong>Techzone PK:</strong></small>
                                    <p class="mb-0 small"><?php echo nl2br(htmlspecialchars($review['admin_response'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-muted">No reviews yet. Be the first to review this product!</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (count($relatedProducts) > 0): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="section-header mb-4">
                    <h3>Related Products</h3>
                    <p>You might also like these products</p>
                </div>
                <div class="row">
                    <?php foreach ($relatedProducts as $related): ?>
                        <div class="col-6 col-md-3 mb-4">
                            <div class="card h-100">
                                <img src="<?php echo htmlspecialchars(getProductImagePath($related['imagelink'])); ?>" class="card-img-top related-img" alt="<?php echo htmlspecialchars($related['name']); ?>" loading="lazy">
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title related-title"><?php echo htmlspecialchars($related['name']); ?></h6>
                                    <p class="card-text fw-bold price-tag"><?php echo format_pkr($related['price']); ?></p>
                                    <div class="mt-auto">
                                        <a href="<?php echo htmlspecialchars(site_url('shop/product-details.php?id=' . (int)$related['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-primary w-100">View Details</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const basePrice = <?php echo $basePrice; ?>;
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
            stockBadge.textContent = 'In Stock (' + inventory + ' available)';
            stockBadge.className = 'badge bg-success';
            if (quantityInput) {
                quantityInput.max = inventory;
            }
            if (addToCartBtn) {
                addToCartBtn.disabled = false;
                addToCartBtn.className = 'btn btn-success';
                addToCartBtn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
            }
        } else {
            stockBadge.textContent = 'Out of Stock';
            stockBadge.className = 'badge bg-danger';
            if (addToCartBtn) {
                addToCartBtn.disabled = true;
                addToCartBtn.className = 'btn btn-secondary';
                addToCartBtn.innerHTML = '<i class="fas fa-ban"></i> Out of Stock';
            }
            if (quantityInput) {
                quantityInput.max = 0;
            }
        }
    } else {
        // If no stock badge, still disable button if out of stock
        if (addToCartBtn && inventory <= 0) {
            addToCartBtn.disabled = true;
            addToCartBtn.className = 'btn btn-secondary';
            addToCartBtn.innerHTML = '<i class="fas fa-ban"></i> Out of Stock';
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
        addToCartBtn.className = 'btn btn-secondary';
        addToCartBtn.innerHTML = '<i class="fas fa-ban"></i> Out of Stock';
    }
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
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    
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
            alert.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message + 
                '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
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
