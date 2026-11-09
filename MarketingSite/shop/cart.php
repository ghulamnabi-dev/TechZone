<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
$pageTitle = 'Shopping Cart';
require_once __DIR__ . '/../config/database.php';

require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/discounts.php';
require_once __DIR__ . '/../../core/shipping.php';

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

// Delivery city: the signed-in customer's (guests see shipping at checkout)
$shipCity = null;
if (isset($_SESSION['customer_id'])) {
    $cityStmt = $conn->prepare("SELECT city FROM customers WHERE id = ?");
    $cityStmt->execute([(int) $_SESSION['customer_id']]);
    $shipCity = trim((string) ($cityStmt->fetchColumn() ?: '')) ?: null;
}

// Discount: re-evaluated against the current cart (core/discounts.php)
$discount = 0;
$applied_promo = null;
$promo_notice = null;
$free_shipping_code = false;
if (isset($_SESSION['applied_promo_code']) && $cart) {
    $promoLines = array_map(static fn($i) => ['product_id' => (int) $i['id'], 'price' => (float) $i['price'], 'quantity' => (int) $i['quantity']], $cart);
    $promoResult = evaluate_promo($conn, (string) $_SESSION['applied_promo_code'], array_values($promoLines), (float) $total, (string) $shipCity);
    if ($promoResult['ok']) {
        $applied_promo = $promoResult['promo'];
        $discount = $promoResult['discount'];
        $free_shipping_code = $promoResult['free_shipping'];
    } else {
        $promo_notice = $promoResult['message'];
    }
}

$quote = $shipCity !== null ? shipping_quote($conn, $shipCity, (float) $total) : null;
$shipping = $quote === null || $free_shipping_code ? 0 : $quote['fee'];
$final_total = $total - $discount + $shipping;
if ($final_total < 0) $final_total = 0;
?>

<?php
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../includes/pages.php';
$itemCount = array_sum(array_column($cart, 'quantity'));
// free-delivery progress: the customer's city, or Lahore (where most orders go) for guests
$barCity = $shipCity ?? 'Lahore';
$barQuote = $quote ?? ($cart ? shipping_quote($conn, 'Lahore', (float) $total) : null);
$plan = pg_installments();
$longest = $plan['months'] ? max($plan['months']) : 0;
$cartMeta = [];
if ($cart) {
    $cartIds = array_values(array_unique(array_map(static fn($i) => (int) $i['id'], $cart)));
    $cartAttrs = products_attributes($conn, $cartIds);
    $cartTypes = [];
    $ph = implode(',', array_fill(0, count($cartIds), '?'));
    $stT = $conn->prepare("SELECT p.id, p.category_id, p.seller_id, c.part_type, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id IN ($ph)");
    $stT->execute($cartIds);
    foreach ($stT->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cartMeta[(int) $r['id']] = $r + ['key_specs' => key_specs($cartAttrs[(int) $r['id']] ?? [])];
    }
    $cartSellers = sellers_by_id($conn);
}
?>
<div class="container pg-page cart-page">
    <header class="cart-head">
        <h1 class="pg-title">Cart</h1>
        <?php if ($cart): ?><p class="cart-head__count"><?php echo $itemCount; ?> <?php echo $itemCount === 1 ? 'item' : 'items'; ?></p><?php endif; ?>
    </header>

    <?php if (isset($_SESSION['cart_error'])): ?>
        <div class="pg-alert pg-alert--warn alert-warning" role="alert"><?php echo $h($_SESSION['cart_error']); ?></div>
        <?php unset($_SESSION['cart_error']); ?>
    <?php endif; ?>

    <?php if (count($cart) > 0): ?>
        <div class="cart-grid">
            <div class="cart-main">
                <ul class="cart-items">
                    <?php foreach ($cart as $cart_key => $item):
                        $availableInventory = isset($item['inventory']) ? (int) $item['inventory'] : 0;
                        $maxQuantity = max(1, $availableInventory);
                        $meta = $cartMeta[(int) $item['id']] ?? [];
                        $media = $item + ['category_id' => $meta['category_id'] ?? null, 'part_type' => $meta['part_type'] ?? null];
                        $url = site_url('shop/product-details.php?id=' . (int) $item['id']);
                        $qid = 'qty-' . md5((string) $cart_key);
                        $sellerName = $cartSellers[(int) ($meta['seller_id'] ?? 1)]['name'] ?? 'TechZone';
                    ?>
                    <li class="cart-item">
                        <a class="cart-item__media" href="<?php echo $h($url); ?>" tabindex="-1" aria-hidden="true"><?php echo render_product_media($media, ['width' => 112, 'tile' => 'sm']); ?></a>
                        <div class="cart-item__info">
                            <a class="cart-item__name" href="<?php echo $h($url); ?>"><?php echo $h($item['name']); ?></a>
                            <?php $specs = $meta['key_specs'] ?? []; if (!empty($item['size'])) $specs[] = 'Size ' . $item['size']; if (!empty($item['color'])) $specs[] = 'Colour ' . $item['color']; ?>
                            <?php if ($specs): ?><p class="cart-item__specs"><?php echo $h(implode(', ', $specs)); ?></p><?php endif; ?>
                            <p class="cart-item__meta"><?php echo format_pkr($item['price']); ?> each<span aria-hidden="true">, </span>sold by <?php echo $h($sellerName); ?></p>
                            <?php if ($availableInventory <= 0): ?>
                                <p class="cart-item__stock is-out">Out of stock: remove it to check out</p>
                            <?php elseif ($availableInventory <= 5): ?>
                                <p class="cart-item__stock is-low">Only <?php echo $availableInventory; ?> left</p>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item__qty">
                            <label class="visually-hidden" for="<?php echo $h($qid); ?>">Quantity for <?php echo $h($item['name']); ?></label>
                            <div class="cart-stepper">
                                <button type="button" class="cart-stepper__btn" data-step="-1" data-for="<?php echo $h($qid); ?>" aria-label="One fewer"<?php echo (int) $item['quantity'] <= 1 ? ' disabled' : ''; ?>>&minus;</button>
                                <input type="number" id="<?php echo $h($qid); ?>"
                                       class="cart-stepper__input quantity-input"
                                       value="<?php echo (int) $item['quantity']; ?>"
                                       min="1"
                                       max="<?php echo $maxQuantity; ?>"
                                       inputmode="numeric"
                                       data-cart-key="<?php echo $h((string) $cart_key); ?>"
                                       data-inventory="<?php echo $availableInventory; ?>"
                                       data-product-name="<?php echo $h($item['name']); ?>"
                                       onchange="updateQuantity(this.dataset.cartKey, this.value)">
                                <button type="button" class="cart-stepper__btn" data-step="1" data-for="<?php echo $h($qid); ?>" aria-label="One more"<?php echo (int) $item['quantity'] >= $maxQuantity ? ' disabled' : ''; ?>>+</button>
                            </div>
                            <button type="button" class="cart-item__remove" data-cart-key="<?php echo $h((string) $cart_key); ?>" onclick="confirmRemoveItem(this.dataset.cartKey)">Remove</button>
                        </div>
                        <p class="cart-item__total"><?php echo format_pkr($item['price'] * $item['quantity']); ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <div class="cart-after">
                    <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="pg-link">Continue shopping</a>
                    <a href="<?php echo $h(site_url('builder.php')); ?>" class="pg-link">Build a PC around these parts</a>
                </div>
            </div>

            <aside class="cart-sum" aria-labelledby="cartSumTitle">
                <h2 class="cart-sum__title" id="cartSumTitle">Order summary</h2>

                <div class="cart-promo">
                    <?php if (!isset($_SESSION['applied_promo_code'])): ?>
                        <label for="promoCodeInput" class="field__label">Promo code</label>
                        <div class="cart-promo__row">
                            <input type="text" class="field__input cart-promo__input" id="promoCodeInput" placeholder="For example TECH10" autocomplete="off" autocapitalize="characters">
                            <button class="btn--secondary cart-promo__btn" type="button" onclick="applyPromoCode()">Apply</button>
                        </div>
                    <?php else: ?>
                        <div class="cart-promo__applied">
                            <span class="cart-promo__code"><?php echo $h($_SESSION['applied_promo_code']); ?></span>
                            <?php if (isset($_SESSION['applied_promo_data']['title'])): ?>
                                <span class="cart-promo__title"><?php echo $h($_SESSION['applied_promo_data']['title']); ?></span>
                            <?php endif; ?>
                            <button type="button" class="cart-promo__remove" onclick="removePromoCode()">Remove</button>
                        </div>
                    <?php endif; ?>
                    <div id="promoCodeMessage" class="cart-promo__msg"><?php if ($promo_notice): ?><div class="alert alert-warning" role="status"><?php echo $h($promo_notice); ?></div><?php endif; ?></div>
                </div>

                <dl class="cart-sum__rows">
                    <div><dt>Subtotal</dt><dd><?php echo format_pkr($total); ?></dd></div>
                    <?php if ($discount > 0): ?>
                        <div class="is-saving"><dt>Discount (<?php echo $h($_SESSION['applied_promo_code']); ?>)</dt><dd>&minus;<?php echo format_pkr($discount); ?></dd></div>
                    <?php endif; ?>
                    <?php if ($free_shipping_code): ?>
                        <div class="is-saving"><dt>Delivery (<?php echo $h($_SESSION['applied_promo_code']); ?>)</dt><dd>Free</dd></div>
                    <?php elseif ($quote === null): ?>
                        <div><dt>Delivery</dt><dd class="cart-sum__soft">At checkout</dd></div>
                    <?php else: ?>
                        <div><dt>Delivery<?php if ($quote['zone']): ?>, <?php echo $h($quote['zone']['name']); ?><?php endif; ?><?php if ($quote['eta']): ?><span class="cart-sum__eta"><?php echo $h($quote['eta']); ?></span><?php endif; ?></dt><dd><?php echo $shipping > 0 ? format_pkr($shipping) : 'Free'; ?></dd></div>
                    <?php endif; ?>
                    <div class="cart-sum__total"><dt>Total</dt><dd><?php echo format_pkr($final_total); ?></dd></div>
                </dl>

                <?php if ($barQuote && !$free_shipping_code && $barQuote['free_over'] !== null):
                    $pct = max(0, min(100, $total / max(1, $barQuote['free_over']) * 100)); ?>
                    <div class="cart-free">
                        <div class="cart-free__bar" role="progressbar" aria-label="Towards free delivery" aria-valuemin="0" aria-valuemax="<?php echo (int) $barQuote['free_over']; ?>" aria-valuenow="<?php echo (int) min($total, $barQuote['free_over']); ?>"><span style="width: <?php echo number_format($pct, 1, '.', ''); ?>%"></span></div>
                        <p class="cart-free__text"><?php if ($barQuote['remaining_for_free'] !== null): ?>Add <?php echo format_pkr($barQuote['remaining_for_free']); ?> more for free delivery to <?php echo $h($barCity); ?>.<?php else: ?>Free delivery to <?php echo $h($barCity); ?> on this order.<?php endif; ?><?php if ($quote === null): ?> Other cities are shown at checkout.<?php endif; ?></p>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['customer_id'])): ?>
                    <a href="<?php echo $h(site_url('shop/checkout.php')); ?>" class="btn--primary btn--block checkout-button">Check out</a>
                <?php else: ?>
                    <a href="<?php echo $h(site_url('auth/login.php?redirect=checkout')); ?>" class="btn--primary btn--block">Sign in to check out</a>
                    <p class="cart-sum__note">You come back to this cart after signing in.</p>
                <?php endif; ?>

                <?php if ($longest && $final_total >= $plan['min_amount']): ?>
                    <p class="cart-sum__plan">Or <strong><?php echo format_pkr(ceil($final_total * (1 + $plan['markup_pct'] / 100) / $longest)); ?> a month</strong> for <?php echo (int) $longest; ?> months at <?php echo $plan['markup_pct'] > 0 ? $h($plan['markup_pct'] . '%') : '0%'; ?> markup with <?php echo $h(pg_list_and($plan['banks'])); ?> credit cards. <a href="<?php echo $h(site_url('help/payments.php')); ?>">How installments work</a></p>
                <?php endif; ?>
                <ul class="cart-sum__facts">
                    <li>Cash on delivery or JazzCash</li>
                    <li>7-day returns, official warranty</li>
                </ul>
            </aside>
        </div>
    <?php else: ?>
        <div class="pg-empty">
            <h2>Your cart is empty</h2>
            <p>Add parts from the catalogue, or let the builder put together a whole PC that fits. Nothing is reserved until you place the order.</p>
            <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="btn--primary">Shop all products</a>
            <a href="<?php echo $h(site_url('builder.php')); ?>" class="btn--secondary">Build a PC</a>
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
    overlay.className = 'cart-confirm';
    overlay.innerHTML = `
        <div class="cart-confirm__dialog" role="alertdialog" aria-modal="true" aria-labelledby="cartConfirmMsg">
            <p class="cart-confirm__msg" id="cartConfirmMsg">${message}</p>
            <div class="cart-confirm__btns">
                <button class="btn--secondary" id="confirm-cancel">Keep it</button>
                <button class="btn--primary" id="confirm-ok">Remove</button>
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

// quantity stepper: -/+ set the number field and fire its change handler
document.addEventListener('click', function (e) {
    const b = e.target.closest('.cart-stepper__btn');
    if (!b) return;
    const input = document.getElementById(b.dataset.for);
    const max = parseInt(input.max) || 1;
    const next = Math.min(max, Math.max(1, (parseInt(input.value) || 1) + parseInt(b.dataset.step)));
    if (next === parseInt(input.value)) return;
    input.value = next;
    input.dispatchEvent(new Event('change'));
});

function confirmRemoveItem(cartKey) {
    showConfirm('Remove this item from your cart?', function(confirmed) {
        if (confirmed) {
            postCartAction({ remove: cartKey });
        }
    });
}
</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
