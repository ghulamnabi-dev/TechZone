<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <!-- Stats Cards -->
    <?php 
        $stats = [
            ['id' => 'totalSales', 'title' => 'Total Revenue', 'icon' => 'fa-dollar-sign', 'class' => 'sales', 'prefix' => 'Rs. '],
            ['id' => 'totalOrders', 'title' => 'Total Orders', 'icon' => 'fa-shopping-cart', 'class' => 'orders'],
            ['id' => 'totalProducts', 'title' => 'Total Products', 'icon' => 'fa-box', 'class' => 'products'],
            ['id' => 'totalCustomers', 'title' => 'Total Customers', 'icon' => 'fa-users', 'class' => 'customers']
        ];
        
        foreach ($stats as $item):
    ?>
    <div class="col-md-3 mb-3">
        <div class="card stats-card <?= $item['class']; ?>">
            <div class="card-body text-center">
                <i class="fas <?= $item['icon']; ?> fa-2x mb-2"></i>
                <h6><?= $item['title']; ?></h6>
                <h3 id="<?= $item['id']; ?>"><?= $item['prefix'] ?? ''; ?>0</h3>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Categories -->
    <div class="col-md-3 mb-3">
        <div class="card stats-card categories">
            <div class="card-body text-center">
                <i class="fas fa-tags fa-2x mb-2"></i>
                <h6>Total Categories</h6>
                <h3 id="totalCategories">0</h3>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4 chart-row">
    <div class="col-md-7">
        <div class="chart-container">
            <span class="chart-label">Sales Overview (Last 12 Months)</span>
            <canvas id="salesChart"></canvas>
        </div>
    </div>

    <div class="col-md-5" >
        <div class="chart-container">
            <span class="chart-label">Products by Category</span>
            <canvas id="categoryChart"></canvas>
        </div>
    </div>
</div>

<!-- Recent Orders -->
<div class="table-container mb-4">
    <h5 class="mb-3">Recent Orders</h5>
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody id="recentOrdersTable">
            <tr><td colspan="5" class="text-center">Loading...</td></tr>
        </tbody>
    </table>
</div>

<!-- Top Selling Products -->
<div class="table-container mb-4">
    <h5 class="mb-3">Top Selling Products</h5>
    <div id="topProductsList">
        <div class="text-center">Loading...</div>
    </div>
</div>

<!-- Low Stock Alert -->
<div class="table-container mb-4" id="lowStockSection" style="display: none;">
    <h5 class="mb-3"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Low Stock Products</h5>
    <div id="lowStockList"></div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
