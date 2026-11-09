<?php
/**
 * Inventory (docs/ADMIN.md): stock level, threshold, 30-day sales and days of cover for every product,
 * tabs All · Low stock · Out of stock, inline Adjust (inventory.adjust) and the latest ledger entries.
 */
$pageTitle = 'Inventory';
$pageSubtitle = 'Stock levels, low stock and every adjustment';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
?>
<?php if (!can('inventory.adjust')): ?>
    <div class="prd-readonly-note"><i class="fas fa-eye" aria-hidden="true"></i> You can see stock levels. Adjusting stock needs a manager or the owner.</div>
<?php endif; ?>

<div class="panel grid-panel">
    <div class="prd-inv-tabs" role="tablist" aria-label="Stock filter">
        <button type="button" role="tab" class="active" aria-selected="true" data-inv-tab="">All <span class="count-chip" id="invCountAll">0</span></button>
        <button type="button" role="tab" aria-selected="false" data-inv-tab="low">Low stock <span class="count-chip" id="invCountLow">0</span></button>
        <button type="button" role="tab" aria-selected="false" data-inv-tab="out">Out of stock <span class="count-chip" id="invCountOut">0</span></button>
    </div>
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="invSearch" placeholder="Search by name or SKU…" aria-label="Search stock">
        </label>
        <div class="end">
            <span class="grid-count" id="invCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="invExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="invReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="inventoryTable" aria-label="Stock levels"></div>
</div>

<section class="panel" aria-labelledby="invRecentTitle">
    <div class="panel-head"><h2 class="panel-title" id="invRecentTitle">Recent adjustments</h2><span class="panel-sub">Last 50 stock movements: sales, cancellations, deliveries and counts</span></div>
    <div class="table-scroll prd-ledger-wrap" style="max-height:520px">
        <table class="table mini-table prd-table">
            <thead><tr><th>Date</th><th>Product</th><th class="num">Change</th><th>Reason</th><th class="prd-hide-sm">Reference</th><th class="prd-hide-sm">By</th></tr></thead>
            <tbody id="invRecent"><tr><td colspan="6" class="table-empty">Loading…</td></tr></tbody>
        </table>
    </div>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/stock-adjust.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/inventory.js') ?>"></script>
