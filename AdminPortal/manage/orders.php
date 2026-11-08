<?php
$pageTitle = 'Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title">All orders <span class="count-chip" id="orderCount">0</span></h2>
    </div>
    <div class="toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="orderSearch" placeholder="Search order #, customer, email…" aria-label="Search orders">
        </label>
        <select class="form-select" id="statusFilter" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="Pending">Pending</option>
            <option value="Processing">Processing</option>
            <option value="Shipped">Shipped</option>
            <option value="Delivered">Delivered</option>
            <option value="Cancelled">Cancelled</option>
        </select>
    </div>
    <div class="table-scroll">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Date</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th class="num">Total</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="ordersTable">
                <tr class="loading-row"><td colspan="9">Loading…</td></tr>
            </tbody>
        </table>
    </div>
    <div class="table-foot" id="ordersPager"></div>
</div>

<!-- Order Details Modal -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsTitle">Order details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                Loading…
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="orderDetailsUpdateBtn"><i class="fas fa-arrows-rotate"></i> Update status</button>
            </div>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateStatusModalLabel">Update status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="updateStatusForm">
                    <input type="hidden" id="updateOrderId">
                    <label class="form-label" for="newStatus">New status for order <span id="updateOrderLabel"></span></label>
                    <select class="form-select" id="newStatus" required>
                        <option value="Pending">Pending</option>
                        <option value="Processing">Processing</option>
                        <option value="Shipped">Shipped</option>
                        <option value="Delivered">Delivered</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="updateStatusForm" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>


<script src="<?php echo htmlspecialchars(admin_url('assets/js/orders.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/orders.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

