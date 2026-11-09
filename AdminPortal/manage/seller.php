<?php
/**
 * Seller detail (owner / manager, sellers.view): profile (editable with sellers.manage, save bar),
 * their products, their orders, earnings and weekly payout statements (owner marks a week paid,
 * sellers.payouts). Data: api/sellers.php?id=N (assets/js/seller.js).
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/marketplace.php';
checkLogin();
$pageCap = 'sellers.view';
$recordId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$slRow = $recordId > 1 && can('sellers.view') ? marketplace_seller($conn, $recordId) : null;
$navActive = 'sellers.php';
$pageBack = ['href' => admin_url('manage/sellers.php?tab=sellers'), 'label' => 'Sellers'];
$pageTitle = $slRow ? $slRow['name'] : 'Seller not found';
if ($slRow) {
    $pageSubtitle = trim(($slRow['market'] ? $slRow['market'] . ', ' : '') . $slRow['city'] . ' · on TechZone since ' . date('j M Y', strtotime((string) ($slRow['joined_at'] ?: $slRow['created_at']))));
}
$canManage = can('sellers.manage');
if ($slRow && $canManage) {
    $pageActions = '<button type="button" class="btn btn-primary" id="slSave" disabled><i class="fas fa-check"></i> Save</button>';
}
require_once __DIR__ . '/../includes/header.php';
$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/orders.css') ?>">
<link rel="stylesheet" href="<?= $tzAsset('assets/css/sellers.css') ?>">
<?php if (!$slRow): ?>
    <div class="panel"><div class="empty-state">
        <span class="empty-state-icon"><i class="fas fa-store"></i></span>
        <p class="empty-state-title">We couldn't find that seller</p>
        <p class="empty-state-text">The link may have a typo. Pick the seller from the list.</p>
        <a class="btn btn-secondary" href="<?= $e(admin_url('manage/sellers.php?tab=sellers')) ?>">Back to sellers</a>
    </div></div>
<?php require_once __DIR__ . '/../includes/footer.php'; return; endif; ?>

<?php if (!$canManage): ?>
    <div class="prd-readonly-note"><i class="fas fa-eye" aria-hidden="true"></i> You can view this seller. Editing needs a manager or the owner.</div>
<?php endif; ?>

<div class="prd-layout" id="slPage" data-id="<?= (int) $recordId ?>" aria-busy="true">
    <div class="prd-main">
        <form class="panel" id="slForm" novalidate aria-labelledby="slProfileTitle">
            <div class="panel-head"><h2 class="panel-title" id="slProfileTitle">Profile</h2><span id="slStatusPill"></span><span class="spacer"></span><span class="panel-sub" id="slSlug"></span></div>
            <div class="panel-body prd-grid2">
                <div class="prd-field">
                    <label class="form-label" for="slName">Shop name</label>
                    <input type="text" class="form-control" id="slName" name="name" maxlength="120" required>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="slMarket">Market</label>
                    <input type="text" class="form-control" id="slMarket" name="market" maxlength="80" placeholder="Hafeez Centre">
                </div>
                <div class="prd-field prd-span2">
                    <label class="form-label" for="slAddress">Address</label>
                    <input type="text" class="form-control" id="slAddress" name="address" maxlength="255" placeholder="Shop, floor, building, area">
                </div>
                <div class="prd-field">
                    <label class="form-label" for="slCity">City</label>
                    <input type="text" class="form-control" id="slCity" name="city" maxlength="80" required>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="slPhone">Phone</label>
                    <input type="tel" class="form-control tabular" id="slPhone" name="phone" maxlength="30" placeholder="042-35761234">
                </div>
                <div class="prd-field">
                    <label class="form-label" for="slWhatsapp">WhatsApp</label>
                    <input type="tel" class="form-control tabular" id="slWhatsapp" name="whatsapp" maxlength="30" placeholder="923001234567">
                    <div class="form-text">With country code; used for wa.me links on the storefront.</div>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="slCommission">Commission</label>
                    <div class="input-group"><input type="number" class="form-control tabular" id="slCommission" name="commission_pct" min="0" max="50" step="0.5" required><span class="input-group-text">%</span></div>
                    <div class="form-text">Applies to weeks not yet paid.</div>
                </div>
                <div class="prd-field prd-span2">
                    <label class="form-label" for="slAbout">About <span class="prd-count" id="slAboutCount"></span></label>
                    <textarea class="form-control" id="slAbout" name="about" rows="3" maxlength="1000" placeholder="What the shop sells, warranty, delivery times"></textarea>
                </div>
                <div class="prd-field prd-span2">
                    <span class="form-label d-block">Status</span>
                    <div class="prd-radios sl-status-radios" data-field="status">
                        <label class="prd-radio"><input type="radio" class="form-check-input" name="status" value="active"><span><strong>Active</strong><small>Products on sale; the seller can sign in</small></span></label>
                        <label class="prd-radio"><input type="radio" class="form-check-input" name="status" value="suspended"><span><strong>Suspended</strong><small>Seller account blocked from signing in</small></span></label>
                    </div>
                </div>
            </div>
        </form>

        <section class="panel" aria-labelledby="slPayTitle">
            <div class="panel-head"><h2 class="panel-title" id="slPayTitle">Payout statements</h2><span class="panel-sub">Monday to Sunday, paid the following Monday</span></div>
            <div class="table-scroll">
                <table class="table mini-table sl-statements">
                    <thead><tr><th>Week</th><th class="num sl-hide-sm">Items</th><th class="num sl-hide-sm">Gross</th><th class="num sl-hide-sm">Commission</th><th class="num">Net</th><th>Status</th><th class="num"><span class="visually-hidden">Action</span></th></tr></thead>
                    <tbody id="slStatements"><tr><td colspan="7" class="table-empty">Loading…</td></tr></tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="slProductsTitle">
            <div class="panel-head"><h2 class="panel-title" id="slProductsTitle">Products</h2><span class="panel-sub" id="slProductsSub"></span></div>
            <div class="table-scroll" style="max-height:440px">
                <table class="table table-hover mini-table">
                    <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Stock</th><th class="num sl-hide-sm">Sold</th><th>Status</th></tr></thead>
                    <tbody id="slProducts"><tr><td colspan="5" class="table-empty">Loading…</td></tr></tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="slOrdersTitle">
            <div class="panel-head"><h2 class="panel-title" id="slOrdersTitle">Orders</h2><span class="panel-sub">Their lines only, newest first</span></div>
            <div class="table-scroll" style="max-height:440px">
                <table class="table table-hover mini-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Their items</th><th>Status</th><th class="num">Their subtotal</th></tr></thead>
                    <tbody id="slOrders"><tr><td colspan="5" class="table-empty">Loading…</td></tr></tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="prd-side">
        <section class="panel" aria-labelledby="slEarnTitle">
            <div class="panel-head"><h2 class="panel-title" id="slEarnTitle">Earnings</h2></div>
            <div class="panel-body"><dl class="sl-pay" id="slEarnings"><div class="muted">Loading…</div></dl></div>
        </section>
        <section class="panel" aria-labelledby="slAccTitle">
            <div class="panel-head"><h2 class="panel-title" id="slAccTitle">Seller account</h2></div>
            <div class="panel-body" id="slAccounts"><div class="muted">Loading…</div></div>
        </section>
    </aside>
</div>

<?php if (can('sellers.payouts')): ?>
<div class="modal fade" id="slPaidModal" tabindex="-1" aria-labelledby="slPaidTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="slPaidForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="slPaidTitle">Mark payout paid</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="ord-consequence" id="slPaidText"></p>
                <dl class="sl-pay mb-3" id="slPaidFigures"></dl>
                <label class="form-label" for="slPaidRef">Transfer reference</label>
                <input type="text" class="form-control tabular" id="slPaidRef" name="reference" maxlength="80" required placeholder="IBFT or cheque number">
                <div class="form-text">Recording a payout does not move money. Send the transfer first, then record it here.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="slPaidSubmit">Mark paid</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/seller.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
