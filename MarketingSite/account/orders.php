<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/customer_auth.php';
require_once __DIR__ . '/../config/database.php';

checkCustomerLogin();
$pageTitle = 'Order History';
require_once __DIR__ . '/../includes/header.php';

$customer_id = $_SESSION['customer_id'];

// Get all orders for this customer
$stmt = $conn->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY order_date DESC");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="section-header mb-4">
        <h2>My Orders</h2>
        <p>View and track your order history</p>
    </div>
    
    <?php if (count($orders) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $statusColors = [
                            'Pending' => 'warning',
                            'Processing' => 'info',
                            'Shipped' => 'primary',
                            'Delivered' => 'success'
                        ];
                        $statusColor = $statusColors[$order['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $order['id']; ?></strong></td>
                            <td><?php echo date('F j, Y', strtotime($order['order_date'])); ?></td>
                            <td><span class="badge bg-<?php echo $statusColor; ?>"><?php echo $order['status']; ?></span></td>
                            <td><strong>Rs. <?php echo number_format($order['total'], 2); ?></strong></td>
                            <td>
                                <a href="<?php echo htmlspecialchars(site_url('account/order-details.php?id=' . (int)$order['id']), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-sm btn-primary">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-shopping-bag"></i>
            </div>
            <h4>No orders yet</h4>
            <p>You haven't placed any orders yet. Browse our products and start shopping!</p>
            <a href="<?php echo htmlspecialchars(site_url('shop/products.php'), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg"><i class="fas fa-store me-2"></i>Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
