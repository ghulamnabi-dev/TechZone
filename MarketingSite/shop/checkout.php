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

<div class="checkout-progress-bar">
    <div class="container">
        <div class="checkout-steps">
            <div class="checkout-step completed">
                <div class="checkout-step-icon"><i class="fas fa-shopping-cart"></i></div>
                <div class="checkout-step-label">Cart</div>
            </div>
            <div class="checkout-step-line completed"></div>
            <div class="checkout-step active">
                <div class="checkout-step-icon"><i class="fas fa-truck"></i></div>
                <div class="checkout-step-label">Details</div>
            </div>
            <div class="checkout-step-line"></div>
            <div class="checkout-step">
                <div class="checkout-step-icon"><i class="fas fa-check"></i></div>
                <div class="checkout-step-label">Confirm</div>
            </div>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="section-header mb-4">
        <h2>Checkout</h2>
        <p>Complete your order</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-4">Shipping Information</h5>
                    <form method="POST" id="checkoutForm">
                        <?php echo csrf_field(); ?>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="customer_name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="customer_email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" class="form-control" name="customer_phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City *</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($customer['city'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Address *</label>
                            <textarea class="form-control" name="address" rows="3" required><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Payment Method</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="payment-method-card card p-3" for="payment_cod">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payment_cod" value="cash_on_delivery" checked onchange="toggleJazzCash()">
                                        <label class="form-check-label w-100" for="payment_cod">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-money-bill-wave fa-2x text-success me-3"></i>
                                                <div>
                                                    <strong>Cash on Delivery</strong>
                                                    <p class="text-muted mb-0 small">Pay when you receive</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="payment-method-card card p-3" for="payment_jazzcash">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="payment_method" id="payment_jazzcash" value="jazzcash" onchange="toggleJazzCash()">
                                        <label class="form-check-label w-100" for="payment_jazzcash">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-warning rounded p-2 me-3" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                                    <strong style="color: #fff; font-size: 0.9rem;">JC</strong>
                                                </div>
                                                <div>
                                                    <strong>JazzCash Wallet</strong>
                                                    <p class="text-muted mb-0 small">Pay via JazzCash</p>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div id="jazzcash_fields" class="mb-4" style="display: none;">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <label class="form-label fw-bold">JazzCash Account Number *</label>
                                    <input type="text" class="form-control" name="jazzcash_number" id="jazzcash_number" placeholder="03XX-XXXXXXX" pattern="[0-9]{4}-[0-9]{7}" maxlength="12">
                                    <small class="text-muted">Format: 03XX-XXXXXXX</small>
                                </div>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <h5 class="mb-3">Order Summary</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="border-0">Product</th>
                                        <th class="border-0 text-center" style="width: 80px;">Qty</th>
                                        <th class="border-0 text-end" style="width: 120px;">Price</th>
                                        <th class="border-0 text-end" style="width: 120px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart as $item): ?>
                                        <tr>
                                            <td class="border-0">
                                                <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                            </td>
                                            <td class="border-0 text-center">
                                                <span class="badge bg-secondary"><?php echo $item['quantity']; ?></span>
                                            </td>
                                            <td class="border-0 text-end text-muted">
                                                <?php echo format_pkr($item['price']); ?>
                                            </td>
                                            <td class="border-0 text-end">
                                                <strong><?php echo format_pkr($item['price'] * $item['quantity']); ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="border-top">
                                    <tr>
                                        <td colspan="3" class="text-end pt-3"><strong>Subtotal:</strong></td>
                                        <td class="text-end pt-3"><strong><?php echo format_pkr($subtotal); ?></strong></td>
                                    </tr>
                                    <?php if ($discount > 0): ?>
                                    <tr>
                                        <td colspan="3" class="text-end text-success"><strong>Discount (<?php echo htmlspecialchars($applied_promo_code); ?>):</strong></td>
                                        <td class="text-end text-success"><strong>- <?php echo format_pkr($discount); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                                        <td class="text-end"><strong><?php echo format_pkr($shipping); ?></strong></td>
                                    </tr>
                                    <tr style="background-color: #878787ff; border-top: 2px solid #4d4f52ff;">
                                        <td colspan="3" class="text-end py-3">
                                            <strong class="fs-5 text-dark">Total:</strong>
                                        </td>
                                        <td class="text-end py-3">
                                            <strong class="fs-6 text-primary"><?php echo format_pkr($total); ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mt-4">Place Order</button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-body">
                    <h5 class="card-title">Order Summary</h5>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Items (<?php echo array_sum(array_column($cart, 'quantity')); ?>):</span>
                        <span><?php echo format_pkr($subtotal); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span><?php echo format_pkr($shipping); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                        <strong class="fs-5">Total:</strong>
                        <strong class="fs-4 text-primary"><?php echo format_pkr($total); ?></strong>
                    </div>
                    <?php if ($subtotal < 5000): ?>
                        <div class="alert alert-info py-2 px-3 mb-0">
                            <small><i class="fas fa-info-circle"></i> Add <?php echo format_pkr(5000 - $subtotal); ?> more for free shipping!</small>
                        </div>
                    <?php endif; ?>
                </div>
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