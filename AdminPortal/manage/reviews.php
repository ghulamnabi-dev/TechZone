<?php
$pageTitle = 'Reviews Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card stats-card sales">
            <div class="card-body text-center">
                <i class="fas fa-star fa-2x mb-2"></i>
                <h6>Total Reviews</h6>
                <h3 id="totalReviews">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stats-card orders">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h6>Pending</h6>
                <h3 id="pendingReviews">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stats-card products">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h6>Approved</h6>
                <h3 id="approvedReviews">0</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card stats-card customers">
            <div class="card-body text-center">
                <i class="fas fa-star-half-alt fa-2x mb-2"></i>
                <h6>Avg Rating</h6>
                <h3 id="avgRating">0.0</h3>
            </div>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Reviews</h5>
        <select class="form-select d-inline-block" id="statusFilter" style="width: 200px;">
            <option value="">All Status</option>
            <option value="Pending">Pending</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
        </select>
    </div>
    <div class="row" id="reviewsList">
        <div class="col-12 text-center">Loading...</div>
    </div>
</div>

<!-- Respond Modal -->
<div class="modal fade" id="respondModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Respond to Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="respondForm">
                    <input type="hidden" id="reviewId">
                    <div class="mb-3">
                        <p><strong>Customer:</strong> <span id="modalCustomerName"></span></p>
                        <p><strong>Product:</strong> <span id="modalProductName"></span></p>
                        <p><strong>Review:</strong> <span id="modalReviewText"></span></p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Response</label>
                        <textarea class="form-control" id="adminResponse" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Send Response</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/reviews.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

