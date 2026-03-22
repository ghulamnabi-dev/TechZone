<?php
$pageTitle = 'Customer Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Customers (<span id="customerCount">0</span>)</h5>
        <div>
            <input type="text" class="form-control d-inline-block" id="customerSearch" placeholder="Search customers..." style="width: 200px;">
            <select class="form-select d-inline-block" id="cityFilter" style="width: 150px;">
                <option value="">All Cities</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>City</th>
                    <th>Address</th>
                    <th>Join Date</th>
                    <th>Orders</th>
                    <th>Total Spent</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="customersTable">
                <tr><td colspan="10" class="text-center">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Customer Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetailsContent">
                Loading...
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <div id="paginationInfo">Showing 0 to 0 of 0 customers</div>
    <nav id="paginationNav" aria-label="Customer pagination">
        <ul class="pagination pagination-sm mb-0" id="paginationControls">
            <!-- Pagination buttons will be added here -->
        </ul>
    </nav>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/customers.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

