<?php
$pageTitle = 'Discounts';
$pageSubtitle = 'Promo codes shoppers enter at checkout; each can also show as a homepage offer';
require_once __DIR__ . '/../config/paths.php';
$pageActions = '<a class="btn btn-primary" id="addPromotionBtn" href="' . htmlspecialchars(admin_url('manage/discount.php?new=1'), ENT_QUOTES, 'UTF-8') . '" data-cap="discounts.write"><i class="fas fa-plus"></i> Create discount</a>';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel grid-panel">
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="promotionSearch" placeholder="Search discounts…" aria-label="Search discounts">
        </label>
        <select class="form-select" id="promotionStateFilter" aria-label="Filter by status">
            <option value="">Any status</option>
            <option value="Live">Live</option>
            <option value="Scheduled">Scheduled</option>
            <option value="Ended">Ended</option>
            <option value="Inactive">Inactive</option>
        </select>
        <div class="end">
            <span class="grid-count" id="promotionCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="promotionsExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="promotionsReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="promotionsList" aria-label="Discounts"></div>
</div>



<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo htmlspecialchars(admin_url('assets/js/promotions.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/promotions.js'); ?>"></script>
