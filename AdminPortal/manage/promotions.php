<?php
$pageTitle = 'Promotions Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Promotions & Special Offers</h5>
        <button class="btn btn-primary" onclick="showAddPromotionModal()">
            <i class="fas fa-plus me-2"></i>Add Promotion
        </button>
    </div>
    <div class="row g-3" id="promotionsList">
        <div class="col-12 text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promotionModalTitle">Add Promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="promotionForm">
                    <input type="hidden" id="promotion_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Title *</label>
                        <input type="text" class="form-control" id="promotion_title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="promotion_description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Promotion Image</label>
                        <input type="file" class="form-control" id="promotion_image" accept="image/*">
                        <small class="text-muted">Upload a promotion image (optional)</small>
                        <div id="currentPromotionImage" class="mt-2" style="display: none;">
                            <img id="currentPromotionImagePreview" src="" alt="Current Image" style="max-width: 100px; max-height: 100px; border-radius: 5px;">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Percentage (%)</label>
                            <input type="number" class="form-control" id="promotion_discount_percentage" min="0" max="100" step="0.01" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Discount Amount (Rs.)</label>
                            <input type="number" class="form-control" id="promotion_discount_amount" min="0" step="0.01" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Promo Code</label>
                        <input type="text" class="form-control" id="promotion_promo_code" placeholder="SUMMER2025">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="promotion_start_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="promotion_end_date">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="promotion_display_order" value="0" min="0">
                            <small class="text-muted">Lower numbers appear first</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="promotion_is_active">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Link URL</label>
                        <input type="url" class="form-control" id="promotion_link_url" placeholder="products.php or specific product URL">
                        <small class="text-muted">Where users should go when clicking the promotion</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePromotion()">Save Promotion</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/promotions.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

