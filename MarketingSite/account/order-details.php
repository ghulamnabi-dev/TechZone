<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';

checkCustomerLogin();
$pageTitle = 'Order Details';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer_id = $_SESSION['customer_id'];

if ($order_id <= 0) {
    header('Location: ' . site_url('account/orders.php'));
    exit();
}

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ?");
$stmt->execute([$order_id, $customer_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: ' . site_url('account/orders.php'));
    exit();
}

// Output starts here (after all redirects above).
require_once __DIR__ . '/../includes/header.php';

    // Get customer address
$stmt = $conn->prepare("SELECT address FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer_data = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_address = $customer_data['address'] ?? '';

        // Get order items with product images
        $stmt = $conn->prepare("SELECT oi.*, p.imagelink, p.category_id FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tracking: latest shipment, customer-facing timeline, shipping zone
require_once __DIR__ . '/../../core/events.php';
$stmt = $conn->prepare("SELECT courier, tracking_number, shipped_at, delivered_at FROM order_shipments WHERE order_id = ? ORDER BY shipped_at DESC, id DESC LIMIT 1");
$stmt->execute([$order_id]);
$trkShipment = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
$trkTimeline = order_customer_timeline($conn, $order_id);
$trkOrder = $order;
$zoneName = null;
if (!empty($order['shipping_zone_id'])) {
    $stmt = $conn->prepare("SELECT name FROM shipping_zones WHERE id = ?");
    $stmt->execute([(int) $order['shipping_zone_id']]);
    $zoneName = $stmt->fetchColumn() ?: null;
}

// Check which products have been reviewed by this customer
$reviewed_products = [];
if (count($order_items) > 0) {
    $product_ids = array_column($order_items, 'product_id');
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $stmt = $conn->prepare("SELECT product_id FROM reviews WHERE customer_id = ? AND product_id IN ($placeholders)");
    $stmt->execute(array_merge([$customer_id], $product_ids));
    $reviewed_products = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'product_id');
}
?>

<?php
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$orderDate = new DateTime($order['order_date']);
$estimatedDelivery = clone $orderDate;
if ($order['status'] == 'Pending' || $order['status'] == 'Processing') {
    $estimatedDelivery->modify('+10 days');
} elseif ($order['status'] == 'Shipped') {
    $estimatedDelivery->modify('+5 days');
} else {
    $estimatedDelivery = null; // Already delivered
}
$paymentMethods = ['cash_on_delivery' => 'Cash on delivery', 'jazzcash' => 'JazzCash'];
?>
<?php require_once __DIR__ . '/../includes/pages.php'; ?>
<div class="container pg-page acct acct-order-page">
    <ol class="pg-crumbs">
        <li><a href="<?php echo $h(site_url('account/orders.php')); ?>">Orders</a></li>
        <li aria-current="page">#<?php echo (int) $order['id']; ?></li>
    </ol>
    <header class="acct-ohead">
        <h1 class="pg-title">Order #<?php echo (int) $order['id']; ?></h1>
        <?php echo pg_status_pill((string) $order['status']); ?>
        <p class="acct-ohead__date">Placed <?php echo date('j F Y, g:i a', strtotime($order['order_date'])); ?></p>
    </header>

    <div class="acct-ogrid">
        <div class="acct-omain">
            <?php include __DIR__ . '/../includes/order-track.php'; ?>

            <h2 class="acct-panel__title acct-items-title">Items</h2>
            <ul class="acct-items">
                <?php foreach ($order_items as $item): ?>
                    <li class="acct-item">
                        <span class="acct-item__media"><?php echo render_product_media(['id' => $item['product_id'], 'imagelink' => $item['imagelink'] ?? null, 'name' => $item['product_name'], 'category_id' => $item['category_id'] ?? null], ['width' => 64, 'tile' => 'sm']); ?></span>
                        <span class="acct-item__info">
                            <a class="acct-item__name" href="<?php echo $h(site_url('shop/product-details.php?id=' . (int) $item['product_id'])); ?>"><?php echo $h($item['product_name']); ?></a>
                            <span class="acct-item__meta"><?php echo (int) $item['quantity']; ?> &times; <?php echo format_pkr($item['price']); ?><?php if (!empty($item['size'])): ?>, size <?php echo $h($item['size']); ?><?php endif; ?><?php if (!empty($item['color'])): ?>, colour <?php echo $h($item['color']); ?><?php endif; ?></span>
                        </span>
                        <span class="acct-item__total"><?php echo format_pkr($item['subtotal']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="acct-totals">
                <div><dt>Subtotal</dt><dd><?php echo format_pkr($order['subtotal'] ?? $order['total']); ?></dd></div>
                <div><dt>Shipping<?php echo $zoneName ? ', ' . $h($zoneName) : ''; ?></dt><dd><?php echo (float) ($order['shipping_fee'] ?? 0) > 0 ? format_pkr($order['shipping_fee']) : 'Free'; ?></dd></div>
                <?php if ((float) ($order['discount_amount'] ?? 0) > 0): ?>
                    <div class="is-saving"><dt>Discount<?php echo !empty($order['promo_code']) ? ' (' . $h($order['promo_code']) . ')' : ''; ?></dt><dd>&minus;<?php echo format_pkr($order['discount_amount']); ?></dd></div>
                <?php endif; ?>
                <div class="acct-totals__total"><dt>Total</dt><dd><?php echo format_pkr($order['total']); ?></dd></div>
            </dl>

            <section class="acct-reviews" aria-labelledby="rv-title">
                <h2 class="acct-panel__title" id="rv-title">Review what you bought</h2>
                <p class="acct-panel__sub">Reviews appear on the product page after a quick check.</p>
                <ul class="acct-review-list">
                    <?php foreach ($order_items as $item):
                        $is_reviewed = in_array($item['product_id'], $reviewed_products);
                    ?>
                        <li>
                            <span><?php echo $h($item['product_name']); ?><?php if ($is_reviewed): ?> <small class="pg-muted">Reviewed</small><?php endif; ?></span>
                            <button type="button" class="btn--secondary acct-btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#reviewModal"
                                    onclick="openReviewModal(<?php echo (int) $item['product_id']; ?>, <?php echo htmlspecialchars(json_encode((string) $item['product_name'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $is_reviewed ? 'true' : 'false'; ?>)">
                                <?php echo $is_reviewed ? 'Update review' : 'Write a review'; ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <aside class="acct-oside">
            <div class="acct-panel">
                <h2 class="acct-panel__title">Delivery</h2>
                <?php if ($estimatedDelivery): ?>
                    <p class="acct-oside__big">Expected by <?php echo $estimatedDelivery->format('j F'); ?></p>
                <?php elseif ($order['status'] == 'Delivered'): ?>
                    <p class="acct-oside__big">Delivered</p>
                <?php endif; ?>
                <dl class="acct-dl">
                    <?php if (isset($order['payment_method'])): ?>
                        <div><dt>Payment</dt><dd><?php echo $h($paymentMethods[$order['payment_method']] ?? 'Other'); ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Shipping</dt><dd><?php echo $h(($zoneName ?: 'Standard') . ', ' . ((float) ($order['shipping_fee'] ?? 0) > 0 ? format_pkr($order['shipping_fee']) : 'free')); ?></dd></div>
                    <?php if ($trkShipment && $trkShipment['courier'] !== ''): ?>
                        <div><dt>Courier</dt><dd><?php echo $h($trkShipment['courier']); ?><?php if (!empty($trkShipment['tracking_number'])): ?><br><span class="pg-num"><?php echo $h($trkShipment['tracking_number']); ?></span><?php endif; ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Name</dt><dd><?php echo $h($order['customer_name']); ?></dd></div>
                    <div><dt>Email</dt><dd><?php echo $h($order['customer_email']); ?></dd></div>
                    <div><dt>Phone</dt><dd><?php echo $h($order['customer_phone']); ?></dd></div>
                    <div><dt>City</dt><dd><?php echo $h($order['city']); ?></dd></div>
                    <?php if (!empty($customer_address)): ?>
                        <div><dt>Address</dt><dd><?php echo nl2br($h($customer_address)); ?></dd></div>
                    <?php endif; ?>
                </dl>
                <form method="POST" action="<?php echo $h(site_url('shop/cart.php')); ?>">
                    <?= csrf_field() ?>
                    <?php foreach ($order_items as $item): ?>
                        <input type="hidden" name="reorder[<?php echo (int) $item['product_id']; ?>]" value="<?php echo (int) $item['quantity']; ?>">
                    <?php endforeach; ?>
                    <button type="submit" name="reorder_submit" value="1" class="btn--primary btn--block">Reorder these items</button>
                </form>
                <p class="acct-oside__help">A problem with a part? <a href="<?php echo $h(site_url('help/warranty.php')); ?>">Start a warranty claim</a> or see <a href="<?php echo $h(site_url('help/returns.php')); ?>">returns</a>.</p>
            </div>
        </aside>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Write a review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="reviewForm">
                <div class="modal-body">
                    <input type="hidden" id="review_product_id" name="product_id">
                    <h6 id="review_product_name" class="mb-3"></h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Rating <span class="text-danger">*</span></label>
                        <div class="rating-input">
                            <input type="hidden" id="review_rating" name="rating" required>
                            <div class="star-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star-icon" data-rating="<?php echo $i; ?>" style="font-size: 2rem; color: #d7dae0; cursor: pointer; margin-right: 0.25rem;"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted d-block mt-1">Click on a star to rate</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="review_comment" class="form-label">Your review</label>
                        <textarea class="form-control field__input" id="review_comment" name="comment" rows="4" placeholder="Share your experience with this product..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn--secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn--primary">Submit review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentRating = 0;

function openReviewModal(productId, productName, isReviewed) {
    document.getElementById('review_product_id').value = productId;
    document.getElementById('review_product_name').textContent = productName;
    document.getElementById('review_comment').value = '';
    document.getElementById('review_rating').value = '';
    currentRating = 0;
    
    // Reset stars
    document.querySelectorAll('.star-icon').forEach(star => {
        star.style.color = '#d7dae0';
    });
    
    // Update modal title
    document.getElementById('reviewModalLabel').textContent = isReviewed ? 'Update review' : 'Write a review';
}

// Star rating interaction
document.querySelectorAll('.star-icon').forEach(star => {
    star.addEventListener('click', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        currentRating = rating;
        document.getElementById('review_rating').value = rating;
        
        // Update star colors
        document.querySelectorAll('.star-icon').forEach((s, index) => {
            if (index < rating) {
                s.style.color = '#15181e';
            } else {
                s.style.color = '#d7dae0';
            }
        });
    });
    
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        document.querySelectorAll('.star-icon').forEach((s, index) => {
            if (index < rating) {
                s.style.color = '#15181e';
            }
        });
    });
});

document.querySelector('.star-rating').addEventListener('mouseleave', function() {
    document.querySelectorAll('.star-icon').forEach((s, index) => {
        if (index < currentRating) {
            s.style.color = '#15181e';
        } else {
            s.style.color = '#d7dae0';
        }
    });
});

// Submit review form
document.getElementById('reviewForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const productId = formData.get('product_id');
    const rating = formData.get('rating');
    const comment = formData.get('comment');
    
    if (!rating) {
        alert('Please select a rating');
        return;
    }
    
    // Submit via AJAX
    fetch(marketingUrl('api/submit_review.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Close the review modal
            const reviewModal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
            reviewModal.hide();
            
            // Show success message
            showThankYouMessage();
        } else {
            alert(data.message || 'Error submitting review');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error submitting review. Please try again.');
    });
});

function showThankYouMessage() {
    // Create and show thank you message
    const thankYouHTML = `
        <div class="modal fade" id="thankYouModal" tabindex="-1" aria-labelledby="thankYouModalLabel" aria-hidden="true" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body text-center p-5">
                        <div class="mb-4">
                            
                        </div>
                        <h2 class="h4 mb-3">Thanks for your review</h2>
                        <p class="text-muted mb-4">It will appear on the product page once it has been checked.</p>
                        <button type="button" class="btn--primary" data-bs-dismiss="modal" onclick="location.reload()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('thankYouModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', thankYouHTML);
    
    // Show modal
    const thankYouModal = new bootstrap.Modal(document.getElementById('thankYouModal'));
    thankYouModal.show();
    
    // Reload page when modal is hidden
    document.getElementById('thankYouModal').addEventListener('hidden.bs.modal', function() {
        location.reload();
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
