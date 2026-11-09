<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/inventory.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';
require_once __DIR__ . '/../../core/orders.php';   // + discounts.php, shipping.php
require_once __DIR__ . '/../../core/settings.php';  // payment_options(): Settings → Payments

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

// Summary preview; place_order() recomputes all of it server-side from the posted city.
$shipCity = (string) ($_POST['city'] ?? ($customer['city'] ?? ''));
$discount = 0;
$applied_promo_code = null;
$free_shipping_code = false;
if (isset($_SESSION['applied_promo_code'])) {
    $promoLines = array_map(static fn($i) => ['product_id' => (int) $i['id'], 'price' => (float) $i['price'], 'quantity' => (int) $i['quantity']], $cart);
    $promoResult = evaluate_promo($conn, (string) $_SESSION['applied_promo_code'], array_values($promoLines), (float) $subtotal, $shipCity);
    if ($promoResult['ok']) {
        $applied_promo_code = $promoResult['code'];
        $discount = $promoResult['discount'];
        $free_shipping_code = $promoResult['free_shipping'];
    }
}

$quote = shipping_quote($conn, $shipCity, (float) $subtotal);
$shipping = $free_shipping_code ? 0 : $quote['fee'];
$total = $subtotal - $discount + $shipping;
if ($total < 0) $total = 0;
// Payment methods switched on in Settings → Payments; COD can be capped by order total
$payOpts = payment_options((float) $total);
$payAvailable = array_keys(array_filter($payOpts, static fn($o) => $o['available']));

// Zones for the live preview when the customer edits the city field
$zonesForJs = array_map(static fn($z) => [
    'name' => $z['name'],
    'cities' => array_map(static fn($c) => mb_strtolower(trim($c), 'UTF-8'), $z['cities']),
    'is_default' => $z['is_default'],
    'amount' => $z['rate']['amount'] ?? 500,
    'free_over' => $z['rate']['free_over'] ?? null,
    'eta' => $z['rate']['eta'] ?? null,
], shipping_zones($conn));

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
        } elseif (!in_array($payment_method, $payAvailable, true)) {
            $error = $payOpts[$payment_method]['reason']
                ? $payOpts[$payment_method]['label'] . ' is ' . lcfirst($payOpts[$payment_method]['reason']) . '. Please choose another payment method.'
                : $payOpts[$payment_method]['label'] . ' is not available right now. Please choose another payment method.';
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
                    <?php $payChecked = in_array((string) ($_POST['payment_method'] ?? ''), $payAvailable, true) ? (string) $_POST['payment_method'] : ($payAvailable[0] ?? ''); ?>
                    <div class="pay-options">
                        <?php if ($payOpts['cash_on_delivery']['enabled']): $codOk = $payOpts['cash_on_delivery']['available']; ?>
                        <label class="payment-method-card pay-option<?php echo $codOk ? '' : ' is-disabled'; ?>" for="payment_cod">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_cod" value="cash_on_delivery"<?php echo $payChecked === 'cash_on_delivery' ? ' checked' : ''; ?><?php echo $codOk ? '' : ' disabled'; ?> onchange="toggleJazzCash()" data-cod-limit="<?php echo (int) setting('cod_limit', 0); ?>">
                            <span class="pay-option__text"><strong>Cash on delivery</strong><span id="codNote"><?php echo $h($payOpts['cash_on_delivery']['reason'] ?? 'Pay the courier when your order arrives'); ?></span></span>
                        </label>
                        <?php endif; ?>
                        <?php if ($payOpts['jazzcash']['enabled']): ?>
                        <label class="payment-method-card pay-option" for="payment_jazzcash">
                            <input class="form-check-input" type="radio" name="payment_method" id="payment_jazzcash" value="jazzcash"<?php echo $payChecked === 'jazzcash' ? ' checked' : ''; ?> onchange="toggleJazzCash()">
                            <span class="pay-option__text"><strong>JazzCash</strong><span>Pay from your JazzCash mobile account</span></span>
                        </label>
                        <?php endif; ?>
                    </div>
                    <?php if (!$payAvailable): ?>
                        <p class="alert alert-warning mt-3 mb-0" role="alert">Online payment options are paused right now. Please contact us to place your order.</p>
                    <?php endif; ?>
                    <div id="jazzcash_fields" class="mt-3" style="display: none;">
                        <label class="form-label" for="jazzcash_number">JazzCash account number</label>
                        <input type="text" class="form-control" name="jazzcash_number" id="jazzcash_number" placeholder="03XX-XXXXXXX" pattern="[0-9]{4}-[0-9]{7}" maxlength="12" inputmode="numeric">
                        <p class="form-hint">Format: 03XX-XXXXXXX</p>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn-ink w-100"<?php echo $payAvailable ? '' : ' disabled'; ?>>Place order</button>
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
                    <div><dt id="shipLabel">Shipping<?php if ($quote['zone']): ?> · <?php echo $h($quote['zone']['name']); ?><?php endif; ?></dt><dd id="shipFee"><?php echo $shipping > 0 ? format_pkr($shipping) : ($free_shipping_code ? 'Free · ' . $h($applied_promo_code) : 'Free'); ?></dd></div>
                    <div class="summary__total"><dt>Total</dt><dd id="orderTotal"><?php echo format_pkr($total); ?></dd></div>
                </dl>
                <p class="summary__note" id="shipNote"<?php if ($free_shipping_code || ($quote['remaining_for_free'] === null && !$quote['eta'])): ?> hidden<?php endif; ?>><?php
                    if (!$free_shipping_code && $quote['remaining_for_free'] !== null) {
                        echo 'Add ' . format_pkr($quote['remaining_for_free']) . ' more for free delivery to ' . $h($shipCity) . '.';
                    } elseif ($quote['eta']) {
                        echo 'Delivery in ' . $h($quote['eta']) . '.';
                    }
                ?></p>
            </div>
        </div>
    </div>
</div>

<script>
// Live shipping preview as the city changes (the server recomputes it when the order is placed).
(function () {
    var zones = <?php echo json_encode($zonesForJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var subtotal = <?php echo json_encode(round((float) $subtotal, 2)); ?>;
    var discount = <?php echo json_encode(round((float) $discount, 2)); ?>;
    var freeCode = <?php echo $free_shipping_code ? 'true' : 'false'; ?>;
    var pkr = function (n) { return 'Rs ' + Math.round(n).toLocaleString('en-US'); };
    function zoneFor(city) {
        var key = String(city || '').trim().toLowerCase().replace(/\s+/g, ' ');
        var def = null;
        for (var i = 0; i < zones.length; i++) {
            if (key && zones[i].cities.indexOf(key) !== -1) return zones[i];
            if (zones[i].is_default && !def) def = zones[i];
        }
        return def;
    }
    function update() {
        var city = document.getElementById('co_city').value;
        var z = zoneFor(city);
        if (!z) return;
        var free = freeCode || (z.free_over !== null && subtotal >= z.free_over);
        var fee = free ? 0 : z.amount;
        document.getElementById('shipLabel').textContent = 'Shipping · ' + z.name;
        document.getElementById('shipFee').textContent = fee > 0 ? pkr(fee) : (freeCode ? 'Free · ' + <?php echo json_encode((string) $applied_promo_code); ?> : 'Free');
        document.getElementById('orderTotal').textContent = pkr(Math.max(0, subtotal - discount + fee));
        // COD limit (Settings → Payments) follows the live total; the server checks it again
        var cod = document.getElementById('payment_cod');
        var limit = cod ? +cod.getAttribute('data-cod-limit') : 0;
        if (cod && limit > 0) {
            var over = Math.max(0, subtotal - discount + fee) > limit;
            cod.disabled = over;
            cod.closest('.pay-option').classList.toggle('is-disabled', over);
            document.getElementById('codNote').textContent = over ? 'Available on orders up to ' + pkr(limit) : 'Pay the courier when your order arrives';
            if (over && cod.checked) { var jz = document.getElementById('payment_jazzcash'); cod.checked = false; if (jz) jz.checked = true; toggleJazzCash(); }
        }
        var note = document.getElementById('shipNote');
        if (!freeCode && z.free_over !== null && subtotal < z.free_over) {
            note.textContent = 'Add ' + pkr(z.free_over - subtotal) + ' more for free delivery to ' + city.trim() + '.';
            note.hidden = false;
        } else if (z.eta) {
            note.textContent = 'Delivery in ' + z.eta + '.';
            note.hidden = false;
        } else {
            note.hidden = true;
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        var input = document.getElementById('co_city');
        if (input) input.addEventListener('input', update);
    });
})();

function toggleJazzCash() {
    const jazzcashRadio = document.getElementById('payment_jazzcash');
    const jazzcashFields = document.getElementById('jazzcash_fields');
    const jazzcashNumber = document.getElementById('jazzcash_number');
    const codRadio = document.getElementById('payment_cod');

    if (jazzcashRadio && jazzcashRadio.checked) {
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
        if (codRadio && codRadio.checked) codRadio.closest('.payment-method-card').classList.add('active');
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