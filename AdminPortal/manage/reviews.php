<?php
$pageTitle = 'Reviews';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
?>

<section class="kpi-grid" aria-label="Review figures">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-comment-dots"></i> Total reviews</div>
        <div class="kpi-value" id="totalReviews">—</div>
        <div class="kpi-sub">All statuses</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-hourglass-half"></i> Awaiting moderation</div>
        <div class="kpi-value" id="pendingReviews">—</div>
        <div class="kpi-sub">Hidden until approved</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-circle-check"></i> Approved</div>
        <div class="kpi-value" id="approvedReviews">—</div>
        <div class="kpi-sub">Visible on the storefront</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-star"></i> Average rating</div>
        <div class="kpi-value" id="avgRating">—</div>
        <div class="kpi-sub">Out of 5</div>
    </div>
</section>

<div class="panel grid-panel">
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="reviewSearch" placeholder="Search reviews…" aria-label="Search reviews">
        </label>
        <select class="form-select" id="statusFilter" aria-label="Filter by status">
            <option value="">All statuses</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
        </select>
        <div class="end">
            <span class="grid-count" id="reviewCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="reviewsExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="reviewsReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="reviewsList" aria-label="Reviews"></div>
</div>

<!-- Respond Modal -->
<div class="modal fade" id="respondModal" tabindex="-1" aria-labelledby="respondModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="respondModalTitle">Respond to review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="respondForm">
                    <input type="hidden" id="reviewId">
                    <dl class="kv mb-3">
                        <dt>Customer</dt><dd id="modalCustomerName"></dd>
                        <dt>Product</dt><dd id="modalProductName"></dd>
                    </dl>
                    <div class="quote mb-3" id="modalReviewText"></div>
                    <label class="form-label" for="adminResponse">Your response</label>
                    <textarea class="form-control" id="adminResponse" rows="4" placeholder="Shown publicly under the review"></textarea>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="respondForm" class="btn btn-primary">Send response</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/reviews.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/reviews.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
