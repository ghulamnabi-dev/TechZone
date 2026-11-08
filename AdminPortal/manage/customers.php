<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title">All customers <span class="count-chip" id="customerCount">0</span></h2>
    </div>
    <div class="toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="customerSearch" placeholder="Search name, email or phone…" aria-label="Search customers">
        </label>
        <select class="form-select" id="cityFilter" aria-label="Filter by city">
            <option value="">All cities</option>
        </select>
    </div>
    <div class="table-scroll">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Joined</th>
                    <th class="num">Orders</th>
                    <th class="num">Total spent</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="customersTable">
                <tr class="loading-row"><td colspan="7">Loading…</td></tr>
            </tbody>
        </table>
    </div>
    <div class="table-foot" id="customersPager"></div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="customerDetailsTitle">Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customerDetailsContent">
                Loading…
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/customers.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/customers.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
