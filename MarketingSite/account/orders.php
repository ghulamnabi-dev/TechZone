<?php
require_once __DIR__ . '/../../core/bootstrap.php';
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

<?php $h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); ?>
<div class="container page">
    <div class="page-title">
        <h1>Orders</h1>
        <?php if (count($orders) > 0): ?><span class="page-title__count"><?php echo count($orders); ?> <?php echo count($orders) === 1 ? 'order' : 'orders'; ?></span><?php endif; ?>
    </div>

    <?php if (count($orders) > 0): ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Date</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="num">Total</th>
                        <th scope="col" class="hide-sm"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order):
                        $statusKey = strtolower((string) $order['status']);
                    ?>
                        <tr>
                            <td><a class="strong-link" href="<?php echo $h(site_url('account/order-details.php?id=' . (int) $order['id'])); ?>">#<?php echo (int) $order['id']; ?></a></td>
                            <td class="nowrap"><?php echo date('j M Y', strtotime($order['order_date'])); ?></td>
                            <td><span class="status status--<?php echo $h($statusKey); ?>"><span class="status__dot" aria-hidden="true"></span><?php echo $h($order['status']); ?></span></td>
                            <td class="num price"><?php echo format_pkr($order['total']); ?></td>
                            <td class="num hide-sm"><a class="text-link" href="<?php echo $h(site_url('account/order-details.php?id=' . (int) $order['id'])); ?>">View details</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>No orders yet</h2>
            <p>Orders you place will appear here with their delivery status.</p>
            <a href="<?php echo $h(site_url('shop/products.php')); ?>" class="btn btn-ink">Shop all products</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
