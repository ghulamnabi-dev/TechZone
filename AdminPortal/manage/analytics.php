<?php
$pageTitle = 'Analytics';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="kpi-grid" aria-label="Sales figures">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-wallet"></i> Revenue</div>
        <div class="kpi-value" id="statRevenue">—</div>
        <div class="kpi-sub">All-time gross sales</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-bag-shopping"></i> Orders</div>
        <div class="kpi-value" id="statOrders">—</div>
        <div class="kpi-sub" id="statOrdersSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-cubes"></i> Units sold</div>
        <div class="kpi-value" id="statUnitsSold">—</div>
        <div class="kpi-sub" id="statUnitsSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-receipt"></i> Avg order value</div>
        <div class="kpi-value" id="statAvgOrder">—</div>
        <div class="kpi-sub">Revenue ÷ orders</div>
    </div>
</section>

<section class="chart-grid" style="grid-template-columns: minmax(0,1fr) minmax(0,1fr)">
    <div class="panel mb-0">
        <div class="panel-head"><h2 class="panel-title">Top selling products</h2></div>
        <div class="table-scroll">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th class="num">Units</th>
                        <th class="num">Revenue</th>
                    </tr>
                </thead>
                <tbody id="topProductsTable">
                    <tr class="loading-row"><td colspan="3">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel mb-0">
        <div class="panel-head"><h2 class="panel-title">Revenue by category</h2></div>
        <div class="table-scroll">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th class="num">Units</th>
                        <th>Share of revenue</th>
                        <th class="num">Revenue</th>
                    </tr>
                </thead>
                <tbody id="categorySalesTable">
                    <tr class="loading-row"><td colspan="4">Loading…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<div class="panel">
    <div class="panel-head"><h2 class="panel-title">Performance summary</h2></div>
    <div class="summary-grid">
        <div><span>Products</span><strong id="totalProducts">—</strong></div>
        <div><span>Orders</span><strong id="totalOrders">—</strong></div>
        <div><span>Customers</span><strong id="totalCustomers">—</strong></div>
        <div><span>Revenue</span><strong id="totalRevenue">—</strong></div>
        <div><span>Units sold</span><strong id="totalUnitsSold">—</strong></div>
        <div><span>Average order value</span><strong id="avgOrderValue">—</strong></div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/analytics.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/analytics.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
