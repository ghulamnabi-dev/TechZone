<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../config/paths.php';
$pageActions = '<a class="btn btn-primary" id="addProductBtn" href="' . htmlspecialchars(admin_url('manage/product.php?new=1'), ENT_QUOTES, 'UTF-8') . '" data-cap="products.write"><i class="fas fa-plus"></i> Add product</a>';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel grid-panel">
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="productSearch" placeholder="Search products…" aria-label="Search products">
        </label>
        <select class="form-select" id="productCategoryFilter" aria-label="Filter by category">
            <option value="">All categories</option>
        </select>
        <select class="form-select" id="productStatusFilter" aria-label="Filter by status">
            <option value="">Any status</option>
            <option value="active">Active</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
            <option value="low">Low stock</option>
            <option value="out">Out of stock</option>
        </select>
        <div class="end">
            <span class="grid-count" id="productCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="productsExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="productsReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="productsTable" aria-label="Products"></div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo htmlspecialchars(admin_url('assets/js/products.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/products.js'); ?>"></script>
