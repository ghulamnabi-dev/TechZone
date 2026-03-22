<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Handle add to cart from URL
if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
        // Check if is_active column exists
        $hasIsActiveColumn = false;
        try {
            // Try to select the column - if it fails, it doesn't exist
            $testQuery = $conn->query("SELECT is_active FROM products LIMIT 1");
            $hasIsActiveColumn = true;
        } catch(PDOException $e) {
            $hasIsActiveColumn = false;
        }
        
        // Get product with inventory (for products without variations, this is fine) - only active products if column exists
        $query = "SELECT id, name, price, imagelink, inventory FROM products WHERE id = ?";
        if ($hasIsActiveColumn) {
            $query .= " AND is_active = 1";
        }
        $stmt = $conn->prepare($query);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $availableInventory = (int)$product['inventory'];
            
            // Note: For products added via GET (without variations), we use base inventory
            // Products with variations should use add_to_cart.php API
            
            if ($availableInventory <= 0) {
                $_SESSION['cart_error'] = 'Product is out of stock';
                header('Location: ' . site_url('shop/cart.php'));
                exit();
            }
            
            $currentQuantity = isset($_SESSION['cart'][$product_id]) ? (int)$_SESSION['cart'][$product_id]['quantity'] : 0;
            
            if ($currentQuantity + 1 > $availableInventory) {
                $_SESSION['cart_error'] = 'Maximum available quantity already in cart';
                header('Location: ' . site_url('shop/cart.php'));
                exit();
            }
            
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity']++;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'imagelink' => $product['imagelink'],
                    'quantity' => 1,
                    'inventory' => $availableInventory
                ];
            }
        }
    header('Location: ' . site_url('shop/cart.php'));
    exit();
}

// Handle remove from cart (now uses cart key)
if (isset($_GET['remove'])) {
    $cart_key = $_GET['remove'];
    if (isset($_SESSION['cart'][$cart_key])) {
        unset($_SESSION['cart'][$cart_key]);
    }
    header('Location: ' . site_url('shop/cart.php'));
    exit();
}

// Handle update quantity (now uses cart key)
if (isset($_POST['update_quantity'])) {
    $cart_key = $_POST['cart_key'];
    $quantity = max(1, (int)$_POST['quantity']);
    
    if (isset($_SESSION['cart'][$cart_key])) {
        $product_id = (int)$_SESSION['cart'][$cart_key]['id'];
        $size = isset($_SESSION['cart'][$cart_key]['size']) ? $_SESSION['cart'][$cart_key]['size'] : null;
        $color = isset($_SESSION['cart'][$cart_key]['color']) ? $_SESSION['cart'][$cart_key]['color'] : null;
        
        // Check if is_active column exists
        $hasIsActiveColumn = false;
        try {
            // Try to select the column - if it fails, it doesn't exist
            $testQuery = $conn->query("SELECT is_active FROM products LIMIT 1");
            $hasIsActiveColumn = true;
        } catch(PDOException $e) {
            $hasIsActiveColumn = false;
        }
        
        // Get base product inventory - only active products if column exists
        $query = "SELECT inventory FROM products WHERE id = ?";
        if ($hasIsActiveColumn) {
            $query .= " AND is_active = 1";
        }
        $stmt = $conn->prepare($query);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $availableInventory = (int)$product['inventory'];
            
            // Check size variation inventory if size is selected
            if ($size) {
                try {
                    $stmt = $conn->prepare("SELECT inventory FROM product_variations WHERE product_id = ? AND variation_type = 'size' AND variation_value = ? AND is_active = 1");
                    $stmt->execute([$product_id, $size]);
                    $sizeVariation = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($sizeVariation && isset($sizeVariation['inventory'])) {
                        $variationInventory = (int)$sizeVariation['inventory'];
                        $availableInventory = min($availableInventory, $variationInventory);
                    }
                } catch (PDOException $e) {
                    // Variations table might not exist
                }
            }
            
            // Check color variation inventory if color is selected
            if ($color) {
                try {
                    $stmt = $conn->prepare("SELECT inventory FROM product_variations WHERE product_id = ? AND variation_type = 'color' AND variation_value = ? AND is_active = 1");
                    $stmt->execute([$product_id, $color]);
                    $colorVariation = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($colorVariation && isset($colorVariation['inventory'])) {
                        $variationInventory = (int)$colorVariation['inventory'];
                        $availableInventory = min($availableInventory, $variationInventory);
                    }
                } catch (PDOException $e) {
                    // Variations table might not exist
                }
            }
            
            if ($quantity > $availableInventory) {
                $_SESSION['cart_error'] = 'Only ' . $availableInventory . ' available in stock';
                $_SESSION['cart'][$cart_key]['quantity'] = min($quantity, $availableInventory);
            } else {
                $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
            }
            
            // Update stored inventory
            $_SESSION['cart'][$cart_key]['inventory'] = $availableInventory;
        }
    }
    header('Location: ' . site_url('shop/cart.php'));
    exit();
}

// Handle reorder
if (isset($_POST['reorder_submit']) && isset($_POST['reorder']) && is_array($_POST['reorder'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    foreach ($_POST['reorder'] as $product_id => $quantity) {
        $product_id = (int)$product_id;
        $quantity = max(1, (int)$quantity);
        
        // Check if is_active column exists (reuse from above if already checked, or check again)
        if (!isset($hasIsActiveColumn)) {
            $hasIsActiveColumn = false;
            try {
                // Try to select the column - if it fails, it doesn't exist
                $testQuery = $conn->query("SELECT is_active FROM products LIMIT 1");
                $hasIsActiveColumn = true;
            } catch(PDOException $e) {
                // Column doesn't exist if we get an error
                $hasIsActiveColumn = false;
            }
        }
        
        // Get product with inventory (for reorder, we use base inventory since variations aren't stored in order history) - only active products if column exists
        $query = "SELECT id, name, price, imagelink, inventory FROM products WHERE id = ?";
        if ($hasIsActiveColumn) {
            $query .= " AND is_active = 1";
        }
        $stmt = $conn->prepare($query);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $availableInventory = (int)$product['inventory'];
            
            // Note: Reorder uses base product inventory
            // If the product has variations, the user should add them manually from product page
            
            if ($availableInventory <= 0) {
                continue; // Skip out of stock products
            }
            
            $currentQuantity = isset($_SESSION['cart'][$product_id]) ? (int)$_SESSION['cart'][$product_id]['quantity'] : 0;
            $newQuantity = $currentQuantity + $quantity;
            
            if ($newQuantity > $availableInventory) {
                $quantity = $availableInventory - $currentQuantity;
                if ($quantity <= 0) {
                    continue; // Already at max
                }
            }
            
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'imagelink' => $product['imagelink'],
                    'quantity' => $quantity,
                    'inventory' => $availableInventory
                ];
            }
        }
    }
    header('Location: ' . site_url('shop/cart.php'));
    exit();
}

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

// Update inventory for each cart item from database (check variation inventory if applicable)
foreach ($cart as $cart_key => $item) {
    $product_id = (int)$item['id'];
    $size = isset($item['size']) ? $item['size'] : null;
    $color = isset($item['color']) ? $item['color'] : null;
    
    // Get base product inventory
    $stmt = $conn->prepare("SELECT inventory FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $availableInventory = (int)$product['inventory'];
        
        // Check size variation inventory if size is selected
        if ($size) {
            try {
                $stmt = $conn->prepare("SELECT inventory FROM product_variations WHERE product_id = ? AND variation_type = 'size' AND variation_value = ? AND is_active = 1");
                $stmt->execute([$product_id, $size]);
                $sizeVariation = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($sizeVariation && isset($sizeVariation['inventory'])) {
                    $variationInventory = (int)$sizeVariation['inventory'];
                    $availableInventory = min($availableInventory, $variationInventory);
                }
            } catch (PDOException $e) {
                // Variations table might not exist
            }
        }
        
        // Check color variation inventory if color is selected
        if ($color) {
            try {
                $stmt = $conn->prepare("SELECT inventory FROM product_variations WHERE product_id = ? AND variation_type = 'color' AND variation_value = ? AND is_active = 1");
                $stmt->execute([$product_id, $color]);
                $colorVariation = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($colorVariation && isset($colorVariation['inventory'])) {
                    $variationInventory = (int)$colorVariation['inventory'];
                    $availableInventory = min($availableInventory, $variationInventory);
                }
            } catch (PDOException $e) {
                // Variations table might not exist
            }
        }
        
        $cart[$cart_key]['inventory'] = $availableInventory;
        // If quantity exceeds inventory, adjust it
        if ($cart[$cart_key]['quantity'] > $cart[$cart_key]['inventory']) {
            $cart[$cart_key]['quantity'] = $cart[$cart_key]['inventory'];
        }
    } else {
        // Product no longer exists, remove from cart
        unset($cart[$cart_key]);
    }
}
$_SESSION['cart'] = $cart;

$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Calculate discount if promo code is applied
$discount = 0;
$applied_promo = null;
if (isset($_SESSION['applied_promo_code']) && isset($_SESSION['applied_promo_data'])) {
    $applied_promo = $_SESSION['applied_promo_data'];
    if ($applied_promo['discount_percentage'] > 0) {
        $discount = ($total * $applied_promo['discount_percentage']) / 100;
    } elseif ($applied_promo['discount_amount'] > 0) {
        $discount = $applied_promo['discount_amount'];
    }
}

$shipping = $total > 5000 ? 0 : 500;
$final_total = $total - $discount + $shipping;
if ($final_total < 0) $final_total = 0;
?>

<div class="container mt-5 mb-5">
    <h2 class="mb-4">Shopping Cart</h2>
    
    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['cart_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>
    
    <?php if (count($cart) > 0): ?>
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price</th>
                                    <th>Quantity</th>
                                    <th>Subtotal</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cart as $cart_key => $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo htmlspecialchars(getProductImagePath($item['imagelink'])); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     style="width: 80px; height: 80px; object-fit: cover; margin-right: 15px; border-radius: 5px;">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                                        <div class="mt-1">
                                                            <?php if (!empty($item['size'])): ?>
                                                                <span class="badge bg-secondary me-1">Size: <?php echo htmlspecialchars($item['size']); ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['color'])): ?>
                                                                <span class="badge bg-info">Color: <?php echo htmlspecialchars($item['color']); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $availableInventory = isset($item['inventory']) ? (int)$item['inventory'] : 0;
                                            $maxQuantity = max(1, $availableInventory);
                                            ?>
                                            <input type="number" 
                                                   class="form-control form-control-sm quantity-input" 
                                                   style="width: 80px; display: inline-block;" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   max="<?php echo $maxQuantity; ?>"
                                                   data-cart-key="<?php echo htmlspecialchars($cart_key); ?>"
                                                   data-inventory="<?php echo $availableInventory; ?>"
                                                   data-product-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                   onchange="updateQuantity('<?php echo htmlspecialchars($cart_key); ?>', this.value)">
                                            <?php if ($availableInventory > 0): ?>
                                                <small class="text-muted d-block mt-1">Max: <?php echo $availableInventory; ?></small>
                                            <?php else: ?>
                                                <small class="text-danger d-block mt-1">Out of stock</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmRemoveItem('<?php echo urlencode($cart_key); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Order Summary</h5>
                        <hr>
                        
                        <!-- Promo Code Section -->
                        <div class="mb-3">
                            <?php if (!isset($_SESSION['applied_promo_code'])): ?>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control" 
                                           id="promoCodeInput" 
                                           placeholder="Enter promo code">
                                    <button class="btn btn-primary" type="button" onclick="applyPromoCode()">
                                        Apply
                                    </button>
                                </div>
                                <small class="text-muted">Have a promo code? Enter it above</small>
                            <?php else: ?>
                                <div class="alert alert-success p-2 mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($_SESSION['applied_promo_code']); ?></strong>
                                            <?php if (isset($_SESSION['applied_promo_data']['title'])): ?>
                                                <br><small><?php echo htmlspecialchars($_SESSION['applied_promo_data']['title']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removePromoCode()" title="Remove">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div id="promoCodeMessage" class="mt-2"></div>
                        </div>
                        <hr>
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>Rs. <?php echo number_format($total, 2); ?></span>
                        </div>
                        <?php if ($discount > 0): ?>
                            <div class="d-flex justify-content-between mb-2 text-success">
                                <span>Discount (<?php echo htmlspecialchars($_SESSION['applied_promo_code']); ?>):</span>
                                <span>- Rs. <?php echo number_format($discount, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Shipping:</span>
                            <span>Rs. <?php echo number_format($shipping, 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong class="fs-5" style="background: linear-gradient(135deg,#667eea,#764ba2); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">Rs. <?php echo number_format($final_total, 2); ?></strong>
                        </div>
                        <?php if (isset($_SESSION['customer_id'])): ?>
                            <a href="<?php echo htmlspecialchars(site_url('shop/checkout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary w-100 checkout-button">Proceed to Checkout</a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars(site_url('auth/login.php?redirect=checkout'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary w-100"><i class="fas fa-sign-in-alt me-2"></i>Login to Checkout</a>
                            <p class="text-muted text-center mt-2 mb-0" style="font-size:0.8rem;">You'll be brought back to your cart after login.</p>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary w-100 mt-2">Continue Shopping</a>
                        <!-- Trust badges -->
                        <div class="cart-trust-badges">
                            <div class="cart-trust-item"><i class="fas fa-lock"></i> Secure Checkout</div>
                            <div class="cart-trust-item"><i class="fas fa-undo"></i> Easy Returns</div>
                            <div class="cart-trust-item"><i class="fas fa-shipping-fast"></i> Fast Delivery</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h4>Your cart is empty</h4>
            <p>Looks like you haven't added any items to your cart yet. Start shopping to fill it up!</p>
            <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><i class="fas fa-shopping-bag me-2"></i>Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Show notification if there was a cart error
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['cart_error'])): ?>
        showNotification('<?php echo addslashes($_SESSION['cart_error']); ?>', 'error');
    <?php endif; ?>
});

function applyPromoCode() {
    const promoCode = document.getElementById('promoCodeInput').value.trim().toUpperCase();
    const messageDiv = document.getElementById('promoCodeMessage');
    
    if (!promoCode) {
        messageDiv.innerHTML = '<div class="alert alert-warning alert-dismissible fade show" role="alert">Please enter a promo code<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        return;
    }
    
    const formData = new FormData();
    formData.append('promo_code', promoCode);
    
    fetch(marketingUrl('api/apply_promo_code.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<div class="alert alert-success alert-dismissible fade show" role="alert">' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            messageDiv.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">' + data.message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        messageDiv.innerHTML = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error applying promo code. Please try again.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    });
}

function removePromoCode() {
    fetch(marketingUrl('api/remove_promo_code.php'), {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing promo code. Please try again.');
    });
}

// Allow Enter key to apply promo code
document.addEventListener('DOMContentLoaded', function() {
    const promoInput = document.getElementById('promoCodeInput');
    if (promoInput) {
        promoInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyPromoCode();
            }
        });
    }
});

function updateQuantity(cartKey, quantity) {
    quantity = parseInt(quantity);
    
    // Get the input element to check inventory
    const input = document.querySelector(`input[data-cart-key="${cartKey}"]`);
    if (!input) return;
    
    const maxInventory = parseInt(input.dataset.inventory) || 0;
    const productName = input.dataset.productName || 'Product';
    
    if (quantity < 1) {
        quantity = 1;
        input.value = 1;
    }
    
    // Validate inventory before submitting
    if (quantity > maxInventory) {
        showNotification(`Only ${maxInventory} available in stock for ${productName}`, 'error');
        input.value = maxInventory;
        quantity = maxInventory;
    }
    
    // Update via AJAX
    const formData = new FormData();
    formData.append('cart_key', cartKey);
    formData.append('quantity', quantity);
    formData.append('update_quantity', '1');
    
    fetch(marketingUrl('shop/cart.php'), {
        method: 'POST',
        body: formData
    })
    .then(() => {
        // Reload page to show updated totals
        window.location.reload();
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error updating quantity. Please try again.', 'error');
    });
}

function showConfirm(message, callback) {
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';
    overlay.innerHTML = `
        <div class="confirm-dialog">
            <div class="confirm-message">
                <i class="fas fa-exclamation-triangle text-warning me-2" style="font-size: 1.5rem;"></i>
                ${message}
            </div>
            <div class="confirm-buttons">
                <button class="btn btn-secondary" id="confirm-cancel">Cancel</button>
                <button class="btn btn-danger" id="confirm-ok">Remove</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    setTimeout(() => {
        overlay.classList.add('show');
    }, 10);
    
    document.getElementById('confirm-cancel').onclick = function() {
        overlay.classList.remove('show');
        setTimeout(() => {
            overlay.remove();
            if (callback) callback(false);
        }, 300);
    };
    
    document.getElementById('confirm-ok').onclick = function() {
        overlay.classList.remove('show');
        setTimeout(() => {
            overlay.remove();
            if (callback) callback(true);
        }, 300);
    };
    
    overlay.onclick = function(e) {
        if (e.target == overlay) {
            overlay.classList.remove('show');
            setTimeout(() => {
                overlay.remove();
                if (callback) callback(false);
            }, 300);
        }
    };
}

function confirmRemoveItem(cartKey) {
    showConfirm('Are you sure you want to remove this item from your cart?', function(confirmed) {
        if (confirmed) {
            window.location.href = marketingUrl('shop/cart.php?remove=' + encodeURIComponent(cartKey));
        }
    });
}
</script>

<style>
.confirm-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.confirm-overlay.show {
    opacity: 1;
}

.confirm-dialog {
    background: white;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 90%;
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.confirm-message {
    font-size: 16px;
    margin-bottom: 20px;
    color: #333;
    display: flex;
    align-items: center;
}

.confirm-buttons {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.confirm-buttons button {
    padding: 8px 20px;
    min-width: 80px;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
