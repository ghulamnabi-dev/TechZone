<?php
$pageTitle = 'Orders';
$useGrid = true;
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
$ordSeller = is_seller();
if ($ordSeller) {
    $pageSubtitle = 'Orders that contain your products. You see and fulfil only your own items.';
}
require_once __DIR__ . '/../includes/header.php';
$ordTabs = [
    'open' => 'Open',
    'unfulfilled' => $ordSeller ? 'To fulfil' : 'Unfulfilled',
    'unpaid' => 'Unpaid',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'all' => 'All',
];
if ($ordSeller) {
    unset($ordTabs['unpaid']);   // payment is TechZone's side; sellers never see it
}
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/orders.css') ?>">

<div class="panel grid-panel">
    <div class="ord-tabs" role="tablist" aria-label="Order views" id="orderTabs">
        <?php foreach ($ordTabs as $key => $label): ?>
            <button type="button" class="ord-tab" role="tab" data-tab="<?= $tzE($key) ?>" aria-selected="false">
                <?= $tzE($label) ?> <span class="ord-tab-count" data-count="<?= $tzE($key) ?>">–</span>
            </button>
        <?php endforeach; ?>
    </div>
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="orderSearch" placeholder="<?= $ordSeller ? 'Search order # or customer…' : 'Search order #, customer, phone, city…' ?>" aria-label="Search orders">
        </label>
        <div class="end">
            <span class="grid-count" id="orderCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="ordersExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="ordersReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="ordersTable" aria-label="Orders"></div>
</div>

<!-- Bulk cancel: one reason for every selected order -->
<div class="modal fade" id="bulkCancelModal" tabindex="-1" aria-labelledby="bulkCancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="bulkCancelForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="bulkCancelTitle">Cancel orders</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="ord-consequence" id="bulkCancelText"></p>
                <label class="form-label" for="bulkCancelReason">Reason</label>
                <select class="form-select" id="bulkCancelReason" required>
                    <option value="Customer request">Customer request</option>
                    <option value="Out of stock">Out of stock</option>
                    <option value="Payment failed">Payment failed</option>
                    <option value="Fraud suspected">Fraud suspected</option>
                    <option value="Other">Other</option>
                </select>
                <div class="mt-2" id="bulkCancelOtherWrap" hidden>
                    <label class="form-label" for="bulkCancelOther">Describe the reason</label>
                    <input type="text" class="form-control" id="bulkCancelOther" maxlength="200">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep orders</button>
                <button type="submit" class="btn btn-danger">Cancel orders</button>
            </div>
        </form>
    </div>
</div>

<script src="<?= $tzAsset('assets/js/orders.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
