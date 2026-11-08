<?php
$pageTitle = 'Promotions';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Promotions &amp; offers <span class="count-chip" id="promotionCount">0</span></h2>
        <span class="spacer"></span>
        <button class="btn btn-primary" type="button" onclick="showAddPromotionModal()">
            <i class="fas fa-plus"></i> Add promotion
        </button>
    </div>
    <div class="table-scroll">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Promotion</th>
                    <th>Discount</th>
                    <th>Code</th>
                    <th>Runs</th>
                    <th>Status</th>
                    <th class="num">Order</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="promotionsList">
                <tr class="loading-row"><td colspan="7">Loading…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Promotion Modal -->
<div class="modal fade" id="promotionModal" tabindex="-1" aria-labelledby="promotionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="promotionModalTitle">Add promotion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="promotionForm" onsubmit="savePromotion(); return false;">
                    <input type="hidden" id="promotion_id">
                    <div class="row g-4">
                        <div class="col-md-7">
                            <div class="form-section">
                                <h6 class="form-section-title">Details</h6>
                                <div class="mb-3">
                                    <label class="form-label" for="promotion_title">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="promotion_title" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="promotion_description">Description</label>
                                    <textarea class="form-control" id="promotion_description" rows="3"></textarea>
                                </div>
                                <div>
                                    <label class="form-label" for="promotion_link_url">Link URL</label>
                                    <input type="text" class="form-control" id="promotion_link_url" placeholder="/MarketingSite/shop/products.php?category=4">
                                    <div class="form-text">Where shoppers land when they click the offer.</div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h6 class="form-section-title">Discount</h6>
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <label class="form-label" for="promotion_discount_percentage">Percent off</label>
                                        <input type="number" class="form-control" id="promotion_discount_percentage" min="0" max="100" step="0.01" value="0">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="promotion_discount_amount">Amount off (Rs)</label>
                                        <input type="number" class="form-control" id="promotion_discount_amount" min="0" step="0.01" value="0">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="promotion_promo_code">Promo code</label>
                                        <input type="text" class="form-control" id="promotion_promo_code" placeholder="SUMMER2026" style="text-transform:uppercase">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="form-section">
                                <h6 class="form-section-title">Schedule</h6>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label" for="promotion_start_date">Starts</label>
                                        <input type="date" class="form-control" id="promotion_start_date">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="promotion_end_date">Ends</label>
                                        <input type="date" class="form-control" id="promotion_end_date">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="promotion_is_active">Status</label>
                                        <select class="form-select" id="promotion_is_active">
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label" for="promotion_display_order">Display order</label>
                                        <input type="number" class="form-control" id="promotion_display_order" value="0" min="0">
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h6 class="form-section-title">Image</h6>
                                <div class="d-flex gap-3 align-items-start">
                                    <div id="currentPromotionImage" style="display: none;">
                                        <img id="currentPromotionImagePreview" src="" alt="Current promotion image" class="img-preview">
                                    </div>
                                    <div class="flex-grow-1">
                                        <input type="file" class="form-control" id="promotion_image" accept="image/*" aria-label="Promotion image">
                                        <div class="form-text">Optional. Shown on the home page offer card.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="promotionForm" class="btn btn-primary">Save promotion</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/promotions.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/promotions.js'); ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
