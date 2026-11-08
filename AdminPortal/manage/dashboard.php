<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="kpi-grid" aria-label="Key figures">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-wallet"></i> Revenue</div>
        <div class="kpi-value" id="totalSales">—</div>
        <div class="kpi-sub" id="salesSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-bag-shopping"></i> Orders</div>
        <div class="kpi-value" id="totalOrders">—</div>
        <div class="kpi-sub" id="ordersSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-users"></i> Customers</div>
        <div class="kpi-value" id="totalCustomers">—</div>
        <div class="kpi-sub" id="customersSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-box-open"></i> Products</div>
        <div class="kpi-value" id="totalProducts">—</div>
        <div class="kpi-sub" id="productsSub">&nbsp;</div>
    </div>
</section>

<section class="chart-grid">
    <div class="panel mb-0">
        <div class="panel-head">
            <h2 class="panel-title">Sales</h2>
            <span class="panel-sub">Last 12 months</span>
            <span class="spacer"></span>
            <span class="panel-sub tabular" id="salesRangeTotal"></span>
        </div>
        <div class="panel-body">
            <div class="chart-box"><canvas id="salesChart" aria-label="Monthly sales line chart" role="img"></canvas></div>
        </div>
    </div>
    <div class="panel mb-0">
        <div class="panel-head">
            <h2 class="panel-title">Products by category</h2>
        </div>
        <div class="panel-body">
            <div class="donut-wrap">
                <div class="donut-canvas">
                    <canvas id="categoryChart" aria-label="Products by category donut chart" role="img"></canvas>
                    <div class="donut-center"><strong id="donutTotal">—</strong><span>products</span></div>
                </div>
                <ul class="legend-list" id="categoryLegend"></ul>
            </div>
        </div>
    </div>
</section>

<section class="dash-grid">
    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Recent orders</h2>
            <span class="spacer"></span>
            <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars(admin_url('manage/orders.php'), ENT_QUOTES, 'UTF-8'); ?>">View all</a>
        </div>
        <div class="table-scroll">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody id="recentOrdersTable">
                    <tr class="loading-row"><td colspan="5">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="panel">
            <div class="panel-head"><h2 class="panel-title">Top sellers</h2></div>
            <ul class="rank-list" id="topProductsList"><li class="muted">Loading…</li></ul>
        </div>
        <div class="panel" id="lowStockSection" hidden>
            <div class="panel-head">
                <h2 class="panel-title">Low stock</h2>
                <span class="count-chip" id="lowStockCount">0</span>
                <span class="spacer"></span>
                <a class="btn btn-sm btn-secondary" href="<?php echo htmlspecialchars(admin_url('manage/products.php'), ENT_QUOTES, 'UTF-8'); ?>">Manage</a>
            </div>
            <div class="table-scroll">
                <table class="table table-hover mini-table">
                    <thead><tr><th>Product</th><th class="num">In stock</th></tr></thead>
                    <tbody id="lowStockList"></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/dashboard.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
