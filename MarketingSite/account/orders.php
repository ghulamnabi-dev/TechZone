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

<?php
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
require_once __DIR__ . '/../includes/pages.php';
$itemCounts = [];
if ($orders) {
    $ids = array_map(static fn($o) => (int) $o['id'], $orders);
    $st = $conn->prepare('SELECT order_id, SUM(quantity) AS n FROM order_items WHERE order_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') GROUP BY order_id');
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $itemCounts[(int) $r['order_id']] = (int) $r['n'];
    }
}
$open = count(array_filter($orders, static fn($o) => in_array($o['status'], ['Pending', 'Processing', 'Shipped'], true)));
?>
<div class="container pg-page acct">
    <?php echo pg_account_head('orders', 'Orders', $orders ? count($orders) . ' ' . (count($orders) === 1 ? 'order' : 'orders') . ($open ? ', ' . $open . ' on the way' : '') : ''); ?>

    <?php if (count($orders) > 0): ?>
        <div class="acct-table-wrap">
            <table class="acct-table">
                <caption class="visually-hidden">Your orders, newest first</caption>
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Placed</th>
                        <th scope="col" class="acct-hide-sm">Items</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="acct-r">Total</th>
                        <th scope="col" class="acct-hide-sm"><span class="visually-hidden">Details</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): $url = site_url('account/order-details.php?id=' . (int) $order['id']); ?>
                        <tr>
                            <th scope="row"><a class="acct-order" href="<?php echo $h($url); ?>">#<?php echo (int) $order['id']; ?></a></th>
                            <td class="acct-nowrap"><?php echo date('j M Y', strtotime($order['order_date'])); ?></td>
                            <td class="acct-hide-sm"><?php echo (int) ($itemCounts[(int) $order['id']] ?? 0); ?></td>
                            <td><?php echo pg_status_pill((string) $order['status']); ?></td>
                            <td class="acct-r acct-money"><?php echo format_pkr($order['total']); ?></td>
                            <td class="acct-r acct-hide-sm"><a class="pg-link" href="<?php echo $h($url); ?>">Details</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="pg-empty">
            <h2>No orders yet</h2>
            <p>Orders you place appear here with their delivery status and tracking number.</p>
            <a href="<?php echo $h(site_url('rates.php')); ?>" class="btn--primary">See today's rates</a>
            <a href="<?php echo $h(site_url('builder.php')); ?>" class="btn--secondary">Build a PC</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
