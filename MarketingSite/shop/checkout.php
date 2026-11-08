<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/inventory.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/orders.php';

// Check if customer is logged in
checkCustomerLogin();

// Check if cart is not empty
if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
    header('Location: ' . site_url('shop/cart.php'));
    exit();
}

$pageTitle = 'Checkout';
// NOTE: includes/header.php is required after the POST handler below, so the
// post-order redirect can still send its Location header.

// Get customer details
$customer_id = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT id, name, email, phone, city, address FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

$cart = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Calculate discount if promo code is applied
$discount = 0;
$applied_promo_code = null;
if (isset($_SESSION['applied_promo_code']) && isset($_SESSION['applied_promo_data'])) {
    $applied_promo_code = $_SESSION['applied_promo_code'];
    $applied_promo = $_SESSION['applied_promo_data'];
    if ($applied_promo['discount_percentage'] > 0) {
        $discount = ($subtotal * $applied_promo['discount_percentage']) / 100;
    } elseif ($applied_promo['discount_amount'] > 0) {
        $discount = $applied_promo['discount_amount'];
    }
}

$shipping = $subtotal > 5000 ? 0 : 500;
$total = $subtotal - $discount + $shipping;
if ($total < 0) $total = 0;

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF protection for checkout form
        require_csrf_token();
    } catch (RuntimeException $e) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    }

    if (empty($error)) {
        $customer_name = v_str($_POST['customer_name'] ?? '', 100);
        $customer_email_raw = v_str($_POST['customer_email'] ?? '', 150);
        $customer_phone = v_str($_POST['customer_phone'] ?? '', 30);
        $city = v_str($_POST['city'] ?? '', 100);
        $address = v_str($_POST['address'] ?? '', 500);
        $payment_method = v_enum($_POST['payment_method'] ?? 'cash_on_delivery', ['cash_on_delivery', 'jazzcash']);
        $jazzcash_number = v_str($_POST['jazzcash_number'] ?? '', 30);

        if (!$customer_name || !$customer_email_raw || !$customer_phone || !$city || !$address) {
            $error = 'Please fill in all required fields (name 100, city 100, address 500 characters max)';
        } elseif (($customer_email = v_email($customer_email_raw)) === null) {
            $error = 'Please enter a valid email address';
        } elseif ($payment_method === null) {
            $error = 'Please choose a valid payment method';
        } elseif ($payment_method === 'jazzcash' && !$jazzcash_number) {
            $error = 'Please enter your JazzCash account number';
        } else {
            try {
                // Stock is locked, re-checked, priced and decremented inside one
                // transaction (see core/orders.php) - no overselling under concurrency.
                $placed = place_order($conn, (int) $customer_id, $cart, [
                    'customer_name'  => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => $customer_phone,
                    'city'           => $city,
                    'payment_method' => $payment_method,
                ], $applied_promo_code);
                $order_id = $placed['order_id'];

                // Remember the delivery details on the customer profile (best effort, outside the order transaction)
                if ($customer['name'] != $customer_name || $customer['email'] != $customer_email || $customer['phone'] != $customer_phone || $customer['city'] != $city || $customer['address'] != $address) {
                    try {
                        $updateCustomerStmt = $conn->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, city = ?, address = ? WHERE id = ?");
                        $updateCustomerStmt->execute([$customer_name, $customer_email, $customer_phone, $city, $address, $customer_id]);
                    } catch (PDOException $e) {
                        log_exception($e, 'checkout-profile-update');   // e.g. email already used by another account
                    }
                }

                unset($_SESSION['cart'], $_SESSION['applied_promo_code'], $_SESSION['applied_promo_data']);

                header('Location: ' . site_url('account/order-details.php?id=' . (int) $order_id));
                exit();
            } catch (CheckoutException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $error = safe_error_message($e, 'We could not process your order. Please try again.');
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<div class="container page">
    <nav aria-label="Checkout steps">
        <ol class="crumbs crumbs--steps">
            <li><a href="<?php echo $h(site_url('shop/cart.php')); ?>">Cart</a></li>
            <li aria-current="step"><strong>Delivery and payment</strong></li>
            <li>Confirmation</li>
        </ol>
    </nav>
    <div class="page-title"><h1>Checkout</h1></div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?php echo $h($error); ?></div>
    <?php endif; ?>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <form method="POST" id="checkoutForm" class="form-stack">
                <?php echo csrf_field(); ?>
                <fieldset class="form-section">
                    <legend>Delivery details</legend>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="co_name">Full name</label>
                            <input type="text" class="form-control" id="co_name" name="customer_name" autocomplete="name" value="<?php echo $h($customer['name']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="co_email">Email</label>
                            <input type="email" class="form-control" id="co_email" name="customer_email" autocomplete="email" value="<?php echo $h($customer['email']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="co_phone">Phone</label>
                            <input type="tel" class="form-control" id="co_phone" name="customer_phone" autocomplete="tel" value="<?php echo $h($customer['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="co_city">City</label>
                            <input type="text" class="form-control" id="co_city" name="city" autocomplete="address-level2" value="<?php echo $h($customer['city'] ?? ''); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="co_address">Address</label>
                            <textarea class="form-control" id="co_address" name="address" rows="3" autocomplete="street-address" required><?php echo $h($customer['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form-section">
                    <legend>Payment</legend>
                    <div class="pay-options">
                        <label class="payment-method-card pay-option" for="payment_cod">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_cod" value="cash_on_delivery" checked onchange="toggleJazzCash()">
                            <span class="pay-option__text"><strong>Cash on delivery</strong><span>Pay the courier when your order arrives</span></span>
                        </label>
                        <label class="payment-method-card pay-option" for="payment_jazzcash">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_jazzcash" value="jazzcash" onchange="toggleJazzCash()">
                            <span class="pay-option__text"><strong>JazzCash</strong><span>Pay from your JazzCash mobile account</span></span>
                        </label>
                    </div>
                    <div id="jazzcash_fields" class="mt-3" style="display: none;">
                        <label class="form-label" for="jazzcash_number">JazzCash account number</label>
                        <input type="text" class="form-control" name="jazzcash_number" id="jazzcash_number" placeholder="03XX-XXXXXXX" pattern="[0-9]{4}-[0-9]{7}" maxlength="12" inputmode="numeric">
                        <p class="form-hint">Format: 03XX-XXXXXXX</p>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-ink w-100">Place order</button>
                <p class="form-hint text-center mt-2">You will see your order number on the next page.</p>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="summary summary--sticky">
                <h2 class="summary__title">Order summary</h2>
                <ul class="summary__items">
                    <?php foreach ($cart as $item): ?>
                        <li>
                            <span class="summary__item-name"><?php echo $h($item['name']); ?>
                                <?php if (!empty($item['size']) || !empty($item['color'])): ?>
                                    <small><?php echo $h(trim(($item['size'] ?? '') . ' ' . ($item['color'] ?? ''))); ?></small>
                                <?php endif; ?>
                                <small>Qty <?php echo (int) $item['quantity']; ?> &times; <?php echo format_pkr($item['price']); ?></small>
                            </span>
                            <span class="summary__item-price"><?php echo format_pkr($item['price'] * $item['quantity']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="summary__rows">
                    <div><dt>Subtotal (<?php echo array_sum(array_column($cart, 'quantity')); ?> items)</dt><dd><?php echo format_pkr($subtotal); ?></dd></div>
                    <?php if ($discount > 0): ?>
                        <div class="is-discount"><dt>Discount (<?php echo $h($applied_promo_code); ?>)</dt><dd>&minus;<?php echo format_pkr($discount); ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Shipping</dt><dd><?php echo $shipping > 0 ? format_pkr($shipping) : 'Free'; ?></dd></div>
                    <div class="summary__total"><dt>Total</dt><dd><?php echo format_pkr($total); ?></dd></div>
                </dl>
                <?php if ($subtotal < 5000): ?>
                    <p class="summary__note">Add <?php echo format_pkr(5000 - $subtotal); ?> more for free shipping.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function toggleJazzCash() {
    const jazzcashRadio = document.getElementById('payment_jazzcash');
    const jazzcashFields = document.getElementById('jazzcash_fields');
    const jazzcashNumber = document.getElementById('jazzcash_number');
    
    if (jazzcashRadio.checked) {
        jazzcashFields.style.display = 'block';
        jazzcashNumber.required = true;
        
        // Update card styles
        document.querySelectorAll('.payment-method-card').forEach(card => {
            card.classList.remove('active');
        });
        jazzcashRadio.closest('.payment-method-card').classList.add('active');
    } else {
        jazzcashFields.style.display = 'none';
        jazzcashNumber.required = false;
        
        // Update card styles
        document.querySelectorAll('.payment-method-card').forEach(card => {
            card.classList.remove('active');
        });
        document.getElementById('payment_cod').closest('.payment-method-card').classList.add('active');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleJazzCash();
    
    // Add active class styling
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('active');
            });
            if (this.checked) {
                this.closest('.payment-method-card').classList.add('active');
            }
        });
    });
    
    // Format JazzCash number input
    const jazzcashInput = document.getElementById('jazzcash_number');
    if (jazzcashInput) {
        jazzcashInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) {
                value = value.slice(0, 4) + '-' + value.slice(4, 11);
            }
            e.target.value = value;
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>