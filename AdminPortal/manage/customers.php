<?php
$pageTitle = 'Customers';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel grid-panel">
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="customerSearch" placeholder="Search customers…" aria-label="Search customers">
        </label>
        <select class="form-select" id="cityFilter" aria-label="Filter by city">
            <option value="">All cities</option>
        </select>
        <div class="end">
            <span class="grid-count" id="customerCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="customersExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="customersReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="customersTable" aria-label="Customers"></div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/customers.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/customers.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
