<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';

checkCustomerLogin();
$pageTitle = 'Order Details';
require_once __DIR__ . '/../includes/header.php';

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

    // Get customer address
$stmt = $conn->prepare("SELECT address FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer_data = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_address = $customer_data['address'] ?? '';

        // Get order items with product images
        $stmt = $conn->prepare("SELECT oi.*, p.imagelink FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2>Order #<?php echo $order['id']; ?></h2>
            <p class="text-muted">Placed on <?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Order Items</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity</th>
                                <th>Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php if (!empty($item['imagelink'])): ?>
                                                        <img src="<?php echo htmlspecialchars(getProductImagePath($item['imagelink'])); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                                             class="me-3" 
                                                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                                    <?php endif; ?>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">Product ID: #<?php echo $item['product_id']; ?></small>
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
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?php echo $item['quantity']; ?></span>
                                            </td>
                                            <td>Rs. <?php echo number_format($item['price'], 2); ?></td>
                                            <td><strong>Rs. <?php echo number_format($item['subtotal'], 2); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                <td><strong>Rs. <?php echo number_format($order['total'], 2); ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            <!-- Review Products Section -->
            <div class="card mt-4">
                <div class="card-header text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h5 class="mb-0"><i class="fas fa-star me-2"></i>Review Our Products</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Help other customers by sharing your experience.</p>
                    
                    <div class="row">
                        <?php foreach ($order_items as $item): ?>
                            <?php 
                            $is_reviewed = in_array($item['product_id'], $reviewed_products);
                            ?>
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-2 <?php echo $is_reviewed ? 'bg-light' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0 small"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                            <?php if ($is_reviewed): ?>
                                                <span class="badge bg-success small"><i class="fas fa-check me-1"></i>Reviewed</span>
                                            <?php endif; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#reviewModal"
                                                onclick="openReviewModal(<?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>', <?php echo $is_reviewed ? 'true' : 'false'; ?>)">
                                            <i class="fas fa-star me-1"></i><?php echo $is_reviewed ? 'Update' : 'Review'; ?>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Order Status & Tracking</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $statusColors = [
                                'Pending' => 'warning',
                                'Processing' => 'info',
                                'Shipped' => 'secondary',
                                'Delivered' => 'success'
                            ];
                            $statusColor = $statusColors[$order['status']] ?? 'secondary';
                            
                            // Calculate estimated delivery date
                            $orderDate = new DateTime($order['order_date']);
                            $estimatedDelivery = clone $orderDate;
                            
                            if ($order['status'] == 'Pending' || $order['status'] == 'Processing') {
                                $estimatedDelivery->modify('+10 days');
                            } elseif ($order['status'] == 'Shipped') {
                                $estimatedDelivery->modify('+5 days');
                            } else {
                                $estimatedDelivery = null; // Already delivered
                            }
                            
                            // Determine which timeline steps are completed
                            $statusSteps = [
                                'Pending' => 1,      // Only "Order Placed" completed
                                'Processing' => 2,   // "Order Placed" and "Processing" completed
                                'Shipped' => 3,      // Up to "Shipped" completed
                                'Delivered' => 4     // All steps completed
                            ];
                            $completedSteps = $statusSteps[$order['status']] ?? 1;
                            ?>
                            
                            <div class="mb-4 text-center">
                                <span class="badge bg-<?php echo $statusColor; ?> fs-6 p-3"><?php echo $order['status']; ?></span>
                                <p class="text-muted small mt-2 mb-0">Order placed on <?php echo date('M j, Y', strtotime($order['order_date'])); ?></p>
                            </div>
                            
                            <!-- Order Timeline -->
                            <div class="order-timeline-horizontal mb-4" style="display: flex !important; flex-direction: row !important; align-items: flex-start !important;">
                                <div class="timeline-step <?php echo $completedSteps >= 1 ? 'active' : ''; ?>">
                                    <div class="timeline-marker" style="<?php echo $completedSteps >= 1 ? 'background-color: #764ba2 !important; border-color: #764ba2 !important; color: white !important;' : 'background-color: #fff; border: 3px solid #dee2e6; color: #6c757d;'; ?> width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title"></div>
                                    </div>
                                </div>
                                <div class="timeline-line" style="flex: 1 1 auto; height: 4px; background-color: <?php echo $completedSteps >= 2 ? '#764ba2' : '#dee2e6'; ?>; margin: 0 10px; margin-top: 18px; min-width: 40px;"></div>
                                <div class="timeline-step <?php echo $completedSteps >= 2 ? 'active' : ''; ?>">
                                    <div class="timeline-marker" style="<?php echo $completedSteps >= 2 ? 'background-color: #764ba2 !important; border-color: #764ba2 !important; color: white !important;' : 'background-color: #fff; border: 3px solid #dee2e6; color: #6c757d;'; ?> width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                        <i class="fas fa-cog"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title"></div>
                                    </div>
                                </div>
                                <div class="timeline-line" style="flex: 1 1 auto; height: 4px; background-color: <?php echo $completedSteps >= 3 ? '#764ba2' : '#dee2e6'; ?>; margin: 0 10px; margin-top: 18px; min-width: 40px;"></div>
                                <div class="timeline-step <?php echo $completedSteps >= 3 ? 'active' : ''; ?>">
                                    <div class="timeline-marker" style="<?php echo $completedSteps >= 3 ? 'background-color: #764ba2 !important; border-color: #764ba2 !important; color: white !important;' : 'background-color: #fff; border: 3px solid #dee2e6; color: #6c757d;'; ?> width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title"></div>
                                    </div>
                                </div>
                                <div class="timeline-line" style="flex: 1 1 auto; height: 4px; background-color: <?php echo $completedSteps >= 4 ? '#764ba2' : '#dee2e6'; ?>; margin: 0 10px; margin-top: 18px; min-width: 40px;"></div>
                                <div class="timeline-step <?php echo $completedSteps >= 4 ? 'active' : ''; ?>">
                                    <div class="timeline-marker" style="<?php echo $completedSteps >= 4 ? 'background-color: #764ba2 !important; border-color: #764ba2 !important; color: white !important;' : 'background-color: #fff; border: 3px solid #dee2e6; color: #6c757d;'; ?> width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-title"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($estimatedDelivery): ?>
                            <div class="alert mb-0" style="background: rgba(118,75,162,0.1); border-color: #764ba2; color: #4a2a7a;">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <strong>Estimated Delivery:</strong><br>
                                <span><?php echo $estimatedDelivery->format('F j, Y'); ?></span>
                            </div>
                            <?php elseif ($order['status'] == 'Delivered'): ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle me-2"></i>
                                <strong>Delivered on:</strong><br>
                                <span><?php echo date('F j, Y', strtotime($order['order_date'] . ' +7 days')); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
            
            <?php if (isset($order['payment_method'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Payment Method</h5>
                </div>
                <div class="card-body">
                    <?php
                    $paymentMethods = [
                        'cash_on_delivery' => ['Cash on Delivery', 'fas fa-money-bill-wave', 'success'],
                        'jazzcash' => ['JazzCash Wallet', 'fas fa-wallet', 'warning']
                    ];
                    $method = $paymentMethods[$order['payment_method']] ?? ['Other', 'fas fa-credit-card', 'secondary'];
                    ?>
                    <p class="mb-0">
                        <i class="<?php echo $method[1]; ?> text-<?php echo $method[2]; ?> me-2"></i>
                        <strong><?php echo $method[0]; ?></strong>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Shipping Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                    <p><strong>City:</strong> <?php echo htmlspecialchars($order['city']); ?></p>
                    <?php if (!empty($customer_address)): ?>
                        <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($customer_address)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <a href="<?php echo htmlspecialchars(site_url('account/orders.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-secondary w-100 mb-2">Back to Orders</a>
                    <form method="POST" action="cart.php">
                        <?php foreach ($order_items as $item): ?>
                            <input type="hidden" name="reorder[<?php echo $item['product_id']; ?>]" value="<?php echo $item['quantity']; ?>">
                        <?php endforeach; ?>
                        <button type="submit" name="reorder_submit" value="1" class="btn btn-primary w-100">Reorder Items</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reviewModalLabel">Write a Review</h5>
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
                                    <i class="fas fa-star star-icon" data-rating="<?php echo $i; ?>" style="font-size: 2rem; color: #ddd; cursor: pointer; margin-right: 0.25rem;"></i>
                                <?php endfor; ?>
                            </div>
                            <small class="text-muted d-block mt-1">Click on a star to rate</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="review_comment" class="form-label">Your Review</label>
                        <textarea class="form-control" id="review_comment" name="comment" rows="4" placeholder="Share your experience with this product..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
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
        star.style.color = '#ddd';
    });
    
    // Update modal title
    document.getElementById('reviewModalLabel').textContent = isReviewed ? 'Update Review' : 'Write a Review';
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
                s.style.color = '#ffc107';
            } else {
                s.style.color = '#ddd';
            }
        });
    });
    
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.getAttribute('data-rating'));
        document.querySelectorAll('.star-icon').forEach((s, index) => {
            if (index < rating) {
                s.style.color = '#ffc107';
            }
        });
    });
});

document.querySelector('.star-rating').addEventListener('mouseleave', function() {
    document.querySelectorAll('.star-icon').forEach((s, index) => {
        if (index < currentRating) {
            s.style.color = '#ffc107';
        } else {
            s.style.color = '#ddd';
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
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="mb-3">Thank You!</h4>
                        <p class="text-muted mb-4">Your review has been submitted successfully. We appreciate your feedback and it helps other customers make better decisions.</p>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" onclick="location.reload()">Close</button>
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
