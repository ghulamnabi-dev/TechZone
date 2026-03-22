<?php
$pageTitle = 'Analytics & Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card sales">
            <div class="card-body text-center">
                <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                <h6>Total Revenue</h6>
                <h3 id="statRevenue">Rs. 0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stats-card orders">
            <div class="card-body text-center">
                <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                <h6>Total Orders</h6>
                <h3 id="statOrders">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stats-card products">
            <div class="card-body text-center">
                <i class="fas fa-box fa-2x mb-2"></i>
                <h6>Units Sold</h6>
                <h3 id="statUnitsSold">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stats-card customers">
            <div class="card-body text-center">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h6>Avg Order Value</h6>
                <h3 id="statAvgOrder">Rs. 0</h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="table-container">
            <h5 class="mb-3">Top Selling Products</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody id="topProductsTable">
                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="col-md-6 mb-4">
        <div class="table-container">
            <h5 class="mb-3">Category Sales Breakdown</h5>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Units Sold</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody id="categorySalesTable">
                    <tr><td colspan="3" class="text-center">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="table-container">
    <h5 class="mb-3">Performance Summary</h5>
    <div class="row">
        <div class="col-md-3">
            <p><strong>Total Products:</strong> <span id="totalProducts">0</span></p>
        </div>
        <div class="col-md-3">
            <p><strong>Total Orders:</strong> <span id="totalOrders">0</span></p>
        </div>
        <div class="col-md-3">
            <p><strong>Total Customers:</strong> <span id="totalCustomers">0</span></p>
        </div>
        <div class="col-md-3">
            <p><strong>Total Revenue:</strong> <span id="totalRevenue"><small>Rs.</small> 0</span></p>
        </div>
    </div>
    <div class="row mt-3">
        <div class="col-md-3">
            <p><strong>Total Units Sold:</strong> <span id="totalUnitsSold">0</span></p>
        </div>
        <div class="col-md-3">
            <p><strong>Average Order Value:</strong> <span id="avgOrderValue">Rs. 0</span></p>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/analytics.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

