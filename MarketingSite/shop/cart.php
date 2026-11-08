<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../../core/security.php';

$cartUrl = site_url('shop/cart.php');

// Old GET links (?add= / ?remove=) no longer change the cart: state changes are POST + CSRF only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && (isset($_GET['add']) || isset($_GET['remove']))) {
    $legacyId = isset($_GET['add']) ? (int) $_GET['add'] : 0;
    header('Location: ' . ($legacyId > 0 ? site_url('shop/product-details.php?id=' . $legacyId) : $cartUrl));
    exit();
}

// Every cart action below is a POST that must carry the session's CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf_token();
    } catch (RuntimeException $e) {
        $_SESSION['cart_error'] = 'Your session has expired. Please refresh the page and try again.';
        header('Location: ' . $cartUrl);
        exit();
    }
}

// Handle add to cart (POST from product listings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $product_id = (int) $_POST['add'];
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

            // Note: listing "Add" buttons have no variation choice, so base inventory is used.
            // Products with variations should use add_to_cart.php API

            if ($availableInventory <= 0) {
                $_SESSION['cart_error'] = 'Product is out of stock';
                header('Location: ' . $cartUrl);
                exit();
            }

            $currentQuantity = isset($_SESSION['cart'][$product_id]) ? (int)$_SESSION['cart'][$product_id]['quantity'] : 0;

            if ($currentQuantity + 1 > $availableInventory) {
                $_SESSION['cart_error'] = 'Maximum available quantity already in cart';
                header('Location: ' . $cartUrl);
                exit();
            }

            if (!isset($_SESSION['cart'][$product_id]) && count($_SESSION['cart']) >= 50) {
                $_SESSION['cart_error'] = 'Your cart is full (50 items maximum)';
                header('Location: ' . $cartUrl);
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
    header('Location: ' . $cartUrl);
    exit();
}

// Handle remove from cart (POST, uses cart key)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove'])) {
    $cart_key = is_scalar($_POST['remove']) ? (string) $_POST['remove'] : '';
    if ($cart_key !== '' && isset($_SESSION['cart'][$cart_key])) {
        unset($_SESSION['cart'][$cart_key]);
    }
    header('Location: ' . $cartUrl);
    exit();
}

// Handle update quantity (now uses cart key)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_quantity'])) {
    $cart_key = is_scalar($_POST['cart_key'] ?? null) ? (string) $_POST['cart_key'] : '';
    $quantity = min(100, max(1, (int) ($_POST['quantity'] ?? 1)));
    
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reorder_submit']) && isset($_POST['reorder']) && is_array($_POST['reorder'])) {
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    foreach ($_POST['reorder'] as $product_id => $quantity) {
        $product_id = (int)$product_id;
        $quantity = min(100, max(1, (int)$quantity));
        if ($product_id <= 0 || (!isset($_SESSION['cart'][$product_id]) && count($_SESSION['cart']) >= 50)) {
            continue;
        }
        
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

// Output starts here (after all redirecting cart actions above).
require_once __DIR__ . '/../includes/header.php';

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

<?php $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<div class="container page">
    <div class="page-title">
        <h1>Cart</h1>
        <?php if (count($cart) > 0): ?><span class="page-title__count"><?php echo array_sum(array_column($cart, 'quantity')); ?> items</span><?php endif; ?>
    </div>

    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <?php echo $h($_SESSION['cart_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>

    <?php if (count($cart) > 0): ?>
        <div class="row g-4 g-lg-5">
            <div class="col-lg-8">
                <ul class="cart-lines">
                    <?php foreach ($cart as $cart_key => $item):
                        $availableInventory = isset($item['inventory']) ? (int) $item['inventory'] : 0;
                        $maxQuantity = max(1, $availableInventory);
                    ?>
                    <li class="cart-line">
                        <a class="cart-line__img" href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $item['id'])); ?>">
                            <img src="<?php echo $h(getProductImagePath($item['imagelink'])); ?>" alt="" width="96" height="96" loading="lazy">
                        </a>
                        <div class="cart-line__info">
                            <a class="cart-line__name" href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $item['id'])); ?>"><?php echo $h($item['name']); ?></a>
                            <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                <p class="cart-line__opts">
                                    <?php if (!empty($item['size'])): ?>Size: <?php echo $h($item['size']); ?><?php endif; ?>
                                    <?php if (!empty($item['size']) && !empty($item['color'])): ?><br><?php endif; ?>
                                    <?php if (!empty($item['color'])): ?>Colour: <?php echo $h($item['color']); ?><?php endif; ?>
                                </p>
                            <?php endif; ?>
                            <p class="cart-line__unit"><?php echo format_pkr($item['price']); ?> each</p>
                            <?php if ($availableInventory <= 0): ?>
                                <p class="stock stock--out"><span class="stock__dot" aria-hidden="true"></span>Out of stock</p>
                            <?php elseif ($availableInventory <= 5): ?>
                                <p class="stock stock--low"><span class="stock__dot" aria-hidden="true"></span>Only <?php echo $availableInventory; ?> left</p>
                            <?php endif; ?>
                        </div>
                        <div class="cart-line__qty">
                            <label class="visually-hidden" for="qty-<?php echo $h(md5((string) $cart_key)); ?>">Quantity for <?php echo $h($item['name']); ?></label>
                            <input type="number" id="qty-<?php echo $h(md5((string) $cart_key)); ?>"
                                   class="form-control quantity-input"
                                   value="<?php echo (int) $item['quantity']; ?>"
                                   min="1"
                                   max="<?php echo $maxQuantity; ?>"
                                   data-cart-key="<?php echo $h((string) $cart_key); ?>"
                                   data-inventory="<?php echo $availableInventory; ?>"
                                   data-product-name="<?php echo $h($item['name']); ?>"
                                   onchange="updateQuantity(this.dataset.cartKey, this.value)">
                            <button type="button" class="link-btn" data-cart-key="<?php echo $h((string) $cart_key); ?>" onclick="confirmRemoveItem(this.dataset.cartKey)">Remove</button>
                        </div>
                        <p class="cart-line__total"><?php echo format_pkr($item['price'] * $item['quantity']); ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="text-link mt-3 d-inline-block">Continue shopping</a>
            </div>

            <div class="col-lg-4">
                <div class="summary">
                    <h2 class="summary__title">Order summary</h2>

                    <div class="summary__promo">
                        <?php if (!isset($_SESSION['applied_promo_code'])): ?>
                            <label for="promoCodeInput" class="form-label">Promo code</label>
                            <div class="input-row">
                                <input type="text" class="form-control" id="promoCodeInput" placeholder="e.g. TECH10" autocomplete="off">
                                <button class="btn btn-line" type="button" onclick="applyPromoCode()">Apply</button>
                            </div>
                        <?php else: ?>
                            <div class="applied-code">
                                <div>
                                    <span class="code-pill"><?php echo $h($_SESSION['applied_promo_code']); ?></span>
                                    <?php if (isset($_SESSION['applied_promo_data']['title'])): ?>
                                        <span class="applied-code__title"><?php echo $h($_SESSION['applied_promo_data']['title']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="link-btn" onclick="removePromoCode()">Remove</button>
                            </div>
                        <?php endif; ?>
                        <div id="promoCodeMessage" class="mt-2"></div>
                    </div>

                    <dl class="summary__rows">
                        <div><dt>Subtotal</dt><dd><?php echo format_pkr($total); ?></dd></div>
                        <?php if ($discount > 0): ?>
                            <div class="is-discount"><dt>Discount (<?php echo $h($_SESSION['applied_promo_code']); ?>)</dt><dd>&minus;<?php echo format_pkr($discount); ?></dd></div>
                        <?php endif; ?>
                        <div><dt>Shipping</dt><dd><?php echo $shipping > 0 ? format_pkr($shipping) : 'Free'; ?></dd></div>
                        <div class="summary__total"><dt>Total</dt><dd><?php echo format_pkr($final_total); ?></dd></div>
                    </dl>

                    <?php if (isset($_SESSION['customer_id'])): ?>
                        <a href="<?php echo $h(site_url('shop/checkout.php')); ?>" class="btn btn-ink w-100 checkout-button">Proceed to Checkout</a>
                    <?php else: ?>
                        <a href="<?php echo $h(site_url('auth/login.php?redirect=checkout')); ?>" class="btn btn-ink w-100">Login to Checkout</a>
                        <p class="summary__note">You will come back to your cart after signing in.</p>
                    <?php endif; ?>
                    <ul class="summary__facts">
                        <li><?php echo tz_icon('cash'); ?>Cash on delivery or JazzCash</li>
                        <li><?php echo tz_icon('rotate'); ?>7-day returns</li>
                    </ul>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>Your cart is empty</h2>
            <p>Browse the catalogue and add parts to your cart. Nothing is reserved until you place the order.</p>
            <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="btn btn-ink">Shop all products</a>
        </div>
    <?php endif; ?>
</div>

<script>
// Show notification if there was a cart error
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['cart_error'])): ?>
        showNotification(<?php echo json_encode((string) $_SESSION['cart_error'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, 'error');
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
    const input = Array.prototype.find.call(document.querySelectorAll('input.quantity-input'), el => el.dataset.cartKey === cartKey);
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
            <div class="confirm-message">${message}</div>
            <div class="confirm-buttons">
                <button class="btn btn-line" id="confirm-cancel">Cancel</button>
                <button class="btn btn-ink" id="confirm-ok">Remove</button>
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

// Submit a cart action as a classic POST form (CSRF token included) so the page reloads as before.
function postCartAction(fields) {
    const form = document.createElement('form');
    form.method = 'post';
    form.action = marketingUrl('shop/cart.php');
    form.style.display = 'none';
    const meta = document.querySelector('meta[name="csrf-token"]');
    fields = Object.assign({ csrf_token: meta ? meta.getAttribute('content') : '' }, fields);
    Object.keys(fields).forEach(function (name) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = fields[name];
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

function confirmRemoveItem(cartKey) {
    showConfirm('Remove this item from your cart?', function(confirmed) {
        if (confirmed) {
            postCartAction({ remove: cartKey });
        }
    });
}
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
