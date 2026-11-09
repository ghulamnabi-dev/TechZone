<?php
/**
 * Discount builder (docs/ADMIN.md "Discounts"): code, type, value, applies to, minimum, usage limit,
 * schedule and status, with the rule summarised as a sentence and the orders that used the code.
 * ?id=N edits, ?new=1 creates. Saves through api/promotions.php (assets/js/discount.js).
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/permissions.php';
checkLogin();
$isNew = isset($_GET['new']);
$recordId = $isNew ? 0 : (isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0);
$navActive = 'promotions.php';
$pageBack = ['href' => admin_url('manage/promotions.php'), 'label' => 'Discounts'];
$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$promo = null;
if ($recordId > 0) {
    $st = $conn->prepare('SELECT id, promo_code, title FROM promotions WHERE id = ?');
    $st->execute([$recordId]);
    $promo = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if ($isNew) {
    $pageCap = 'discounts.write';
}
if (!$isNew && !$promo) {
    $pageTitle = 'Discount not found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="panel"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-percent"></i></div>'
        . '<h2 class="empty-state-title">This discount doesn’t exist</h2><p class="empty-state-text">It may have been deleted.</p>'
        . '<a class="btn btn-secondary" href="' . $e(admin_url('manage/promotions.php')) . '">Back to discounts</a></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    return;
}
$canWrite = can('discounts.write');
$pageTitle = $isNew ? 'Create discount' : ($promo['promo_code'] ?: $promo['title']);
$pageActions = ($isNew || !$canWrite ? '' : '<button type="button" class="btn btn-outline-danger" id="dscDelete"><i class="fas fa-trash"></i> Delete</button>')
    . '<button type="button" class="btn btn-primary" id="dscSave" disabled data-cap="discounts.write"><i class="fas fa-check"></i> ' . ($isNew ? 'Create discount' : 'Save') . '</button>';
require_once __DIR__ . '/../includes/header.php';
?>
<?php if (!$canWrite): ?>
    <div class="prd-readonly-note"><i class="fas fa-eye" aria-hidden="true"></i> You can view this discount. Creating and editing discounts needs a manager or the owner.</div>
<?php endif; ?>

<form id="discountForm" class="prd-layout" novalidate data-id="<?= (int) $recordId ?>">
    <div class="prd-main">
        <section class="panel" aria-labelledby="dscCodeTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscCodeTitle">Code</h2></div>
            <div class="panel-body">
                <div class="prd-field">
                    <label class="form-label" for="dscCode">Discount code</label>
                    <div class="input-group">
                        <input type="text" class="form-control dsc-code" id="dscCode" name="promo_code" maxlength="30" autocomplete="off" spellcheck="false" placeholder="e.g. LAHORE5" aria-describedby="dscCodeState">
                        <button type="button" class="btn btn-secondary" id="dscGenerate"><i class="fas fa-shuffle"></i> Generate</button>
                    </div>
                    <div class="dsc-code-state" id="dscCodeState" aria-live="polite"><span class="prd-hint">Shoppers type this at the cart. Letters, numbers and dashes.</span></div>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="dscTypeTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscTypeTitle">Type and value</h2></div>
            <div class="panel-body">
                <div class="dsc-types" data-field="kind" role="radiogroup" aria-labelledby="dscTypeTitle">
                    <label class="dsc-type"><input type="radio" name="kind" value="percent"><i class="fas fa-percent" aria-hidden="true"></i><span><strong>Percentage</strong><small>A share off the eligible items</small></span></label>
                    <label class="dsc-type"><input type="radio" name="kind" value="fixed"><i class="fas fa-money-bill-wave" aria-hidden="true"></i><span><strong>Fixed amount</strong><small>Rupees off the eligible items</small></span></label>
                    <label class="dsc-type"><input type="radio" name="kind" value="free_shipping"><i class="fas fa-truck-fast" aria-hidden="true"></i><span><strong>Free shipping</strong><small>Waives the delivery fee</small></span></label>
                </div>
                <div class="prd-grid2 mt-3" id="dscValueRow">
                    <div class="prd-field">
                        <label class="form-label" for="dscValue" id="dscValueLabel">Percentage off</label>
                        <div class="input-group prd-money" id="dscValueGroup">
                            <span class="input-group-text" id="dscValuePre">Rs</span>
                            <input type="number" class="form-control" id="dscValue" name="value" min="0" step="1" inputmode="decimal">
                            <span class="input-group-text" id="dscValueSuf">%</span>
                        </div>
                        <div class="form-text" id="dscValueHint">Between 1% and 90%.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="dscAppliesTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscAppliesTitle">Applies to</h2></div>
            <div class="panel-body">
                <div class="dsc-seg" role="radiogroup" aria-labelledby="dscAppliesTitle" data-field="applies_to" id="dscAppliesSeg">
                    <label><input type="radio" name="applies_to" value="all"><span>Entire order</span></label>
                    <label><input type="radio" name="applies_to" value="category"><span>A category</span></label>
                    <label><input type="radio" name="applies_to" value="product"><span>A product</span></label>
                </div>
                <p class="prd-hint mt-2 mb-0" id="dscFreeShipNote" hidden>Free shipping always applies to the whole order.</p>
                <div class="prd-field mt-3" id="dscCategoryRow" hidden>
                    <label class="form-label" for="dscCategory">Category</label>
                    <select class="form-select" id="dscCategory" data-field="target_id"><option value="">Choose a category</option></select>
                </div>
                <div class="prd-field mt-3" id="dscProductRow" hidden>
                    <label class="form-label" for="dscProductSearch">Product</label>
                    <div class="dsc-picker">
                        <input type="search" class="form-control" id="dscProductSearch" placeholder="Search products by name or SKU" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="dscProductList" data-save-ignore data-field="target_id">
                        <div class="dsc-picker-list" id="dscProductList" role="listbox" hidden></div>
                    </div>
                    <div id="dscPicked"></div>
                </div>
                <input type="hidden" name="target_id" id="dscTarget">
                <p class="prd-hint mt-3 mb-0">Only eligible items are discounted; the minimum order counts the whole cart.</p>
            </div>
        </section>

        <section class="panel" aria-labelledby="dscReqTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscReqTitle">Minimum order and usage</h2></div>
            <div class="panel-body prd-grid2">
                <div class="prd-field">
                    <label class="form-label" for="dscMin">Minimum order</label>
                    <div class="input-group prd-money"><span class="input-group-text">Rs</span><input type="number" class="form-control" id="dscMin" name="min_order" min="0" step="1" inputmode="decimal" placeholder="0"></div>
                    <div class="form-text">Cart subtotal before shipping. 0 for no minimum.</div>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="dscLimit">Total usage limit</label>
                    <input type="number" class="form-control tabular" id="dscLimit" name="usage_limit" min="1" step="1" inputmode="numeric" placeholder="Unlimited">
                    <div class="form-text dsc-usage" id="dscUsedNote">Counts every order that used the code.</div>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="dscSchedTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscSchedTitle">Schedule</h2><span class="spacer"></span><span class="panel-sub">Store time (PKT)</span></div>
            <div class="panel-body prd-grid2">
                <div class="prd-field">
                    <label class="form-label" for="dscStart">Starts</label>
                    <input type="datetime-local" class="form-control" id="dscStart" name="starts_at">
                    <div class="form-text">Leave empty to start as soon as it is saved.</div>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="dscEnd">Ends</label>
                    <input type="datetime-local" class="form-control" id="dscEnd" name="ends_at">
                    <label class="dsc-check"><input type="checkbox" class="form-check-input" id="dscNoEnd" name="no_end" value="1"> No end date</label>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="dscOfferTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscOfferTitle">Homepage offer</h2><span class="panel-sub">Optional card on the storefront home page</span></div>
            <div class="panel-body prd-grid2">
                <div class="prd-field">
                    <label class="form-label" for="dscTitle">Offer title</label>
                    <input type="text" class="form-control" id="dscTitle" name="title" maxlength="200" placeholder="Defaults to the code">
                </div>
                <div class="prd-field">
                    <label class="form-label" for="dscLink">Link</label>
                    <input type="text" class="form-control" id="dscLink" name="link_url" maxlength="500" placeholder="/MarketingSite/shop/products.php?category=5">
                </div>
                <div class="prd-field prd-span2">
                    <label class="form-label" for="dscDesc">Offer text</label>
                    <textarea class="form-control" id="dscDesc" name="description" rows="2" placeholder="5% off routers and switches for Lahore shoppers."></textarea>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="dscImage">Image</label>
                    <input type="file" class="form-control" id="dscImage" name="image" accept="image/jpeg,image/png,image/webp" data-field="image">
                    <div class="form-text" id="dscImageNote">JPG, PNG or WebP up to 2 MB.</div>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="dscOrder">Display order</label>
                    <input type="number" class="form-control tabular" id="dscOrder" name="display_order" step="1" value="0">
                </div>
            </div>
        </section>
    </div>

    <aside class="prd-side">
        <section class="panel dsc-summary-card" aria-labelledby="dscSumTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscSumTitle">Summary</h2><span class="spacer"></span><span id="dscStatePill"></span></div>
            <div class="panel-body">
                <p class="dsc-sentence" id="dscSentence" aria-live="polite"></p>
                <ul class="dsc-facts" id="dscFacts"></ul>
            </div>
        </section>
        <section class="panel" aria-labelledby="dscStatusTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscStatusTitle">Status</h2></div>
            <div class="panel-body">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="dscActive" name="is_active" value="1">
                    <label class="form-check-label" for="dscActive"><strong>Active</strong></label>
                </div>
                <p class="prd-hint mt-2 mb-0">Turn off to pause the code without losing its settings or history.</p>
            </div>
        </section>
        <?php if (!$isNew): ?>
        <section class="panel" aria-labelledby="dscUsageTitle">
            <div class="panel-head"><h2 class="panel-title" id="dscUsageTitle">Usage</h2><span class="spacer"></span><span class="panel-sub">Excludes cancelled orders</span></div>
            <div class="dsc-stats">
                <div><span>Orders</span><strong id="usOrders">—</strong></div>
                <div><span>Sales</span><strong id="usSales">—</strong></div>
                <div><span>Discount given</span><strong id="usGiven">—</strong></div>
            </div>
            <div class="table-scroll">
                <table class="table mini-table prd-table">
                    <thead><tr><th>Order</th><th>Customer</th><th class="num">Discount</th></tr></thead>
                    <tbody id="usRows"><tr><td colspan="3" class="table-empty">Loading…</td></tr></tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </aside>
</form>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/discount.js') ?>"></script>
