<?php
/**
 * Sellers (docs/STOREFRONT.md "Marketplace", Customers group). Owner + manager (sellers.view).
 * Tabs: Applications (pending / approved / rejected, detail drawer, approve / reject) · Sellers (grid).
 * Payable totals for next Monday at the top. Data: api/sellers.php (assets/js/sellers.js).
 */
$pageTitle = 'Sellers';
$pageSubtitle = 'Marketplace shops selling on TechZone: applications, sellers and what we owe them';
$pageCap = 'sellers.view';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
$slCanManage = can('sellers.manage');
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/orders.css') ?>">
<link rel="stylesheet" href="<?= $tzAsset('assets/css/sellers.css') ?>">

<section class="kpi-grid" aria-label="Marketplace">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-inbox"></i> Applications to review</div>
        <div class="kpi-value tabular" id="kPending">—</div>
        <div class="kpi-sub">From the Sell on TechZone form</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-store"></i> Active sellers</div>
        <div class="kpi-value tabular" id="kActive">—</div>
        <div class="kpi-sub" id="kActiveSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-money-check-dollar"></i> Payable now</div>
        <div class="kpi-value tabular" id="kPayable">—</div>
        <div class="kpi-sub" id="kPayableSub">Closed weeks, after commission</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-hourglass-half"></i> Accruing this week</div>
        <div class="kpi-value tabular" id="kAccruing">—</div>
        <div class="kpi-sub">Delivered since Monday, after commission</div>
    </div>
</section>

<div class="panel grid-panel">
    <div class="ord-tabs" role="tablist" aria-label="Sellers views" id="slTabs">
        <button type="button" class="ord-tab" role="tab" data-tab="applications" aria-selected="false" aria-controls="slApps">Applications <span class="ord-tab-count" data-count="applications">–</span></button>
        <button type="button" class="ord-tab" role="tab" data-tab="sellers" aria-selected="false" aria-controls="slSellers">Sellers <span class="ord-tab-count" data-count="sellers">–</span></button>
    </div>

    <div id="slApps" role="tabpanel">
        <div class="toolbar">
            <div class="seg" role="group" aria-label="Application status" id="slAppSeg">
                <button type="button" class="active" data-status="pending" aria-pressed="true">Pending <span class="count-chip" data-app-count="pending">0</span></button>
                <button type="button" data-status="approved" aria-pressed="false">Approved <span class="count-chip" data-app-count="approved">0</span></button>
                <button type="button" data-status="rejected" aria-pressed="false">Rejected <span class="count-chip" data-app-count="rejected">0</span></button>
            </div>
        </div>
        <div class="table-scroll">
            <table class="table table-hover sl-apps">
                <thead><tr><th>Shop</th><th class="sl-hide-sm">Market</th><th class="sl-hide-md">Sells</th><th>Applied</th><th>Status</th><th class="num"><span class="visually-hidden">Action</span></th></tr></thead>
                <tbody id="slAppRows"><tr><td colspan="6" class="table-empty">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div id="slSellers" role="tabpanel" hidden>
        <div class="toolbar grid-toolbar">
            <label class="search mb-0">
                <i class="fas fa-magnifying-glass"></i>
                <input type="search" class="form-control" id="slSearch" placeholder="Search sellers or markets…" aria-label="Search sellers">
            </label>
            <div class="end">
                <span class="grid-count" id="slCount" aria-live="polite">Loading…</span>
                <button type="button" class="btn btn-secondary" id="slExport"><i class="fas fa-file-csv"></i> Export CSV</button>
                <button type="button" class="btn btn-secondary btn-square" id="slReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
            </div>
        </div>
        <div id="slGrid" aria-label="Sellers"></div>
    </div>
</div>

<!-- Application detail -->
<div class="offcanvas offcanvas-end sl-drawer" tabindex="-1" id="slDrawer" aria-labelledby="slDrawerTitle">
    <div class="offcanvas-header">
        <div>
            <h2 class="offcanvas-title" id="slDrawerTitle">Application</h2>
            <div class="sl-drawer-sub" id="slDrawerSub"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body" id="slDrawerBody"></div>
    <div class="sl-drawer-foot" id="slDrawerFoot"></div>
</div>

<?php if ($slCanManage): ?>
<!-- Approve -->
<div class="modal fade" id="slApproveModal" tabindex="-1" aria-labelledby="slApproveTitle" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="slApproveForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="slApproveTitle">Approve application</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="slApproveStep1">
                <p class="ord-consequence" id="slApproveText"></p>
                <div class="prd-grid2">
                    <div class="prd-field prd-span2">
                        <label class="form-label" for="slApName">Shop name</label>
                        <input type="text" class="form-control" id="slApName" name="name" maxlength="120" required>
                    </div>
                    <div class="prd-field">
                        <label class="form-label" for="slApMarket">Market</label>
                        <input type="text" class="form-control" id="slApMarket" name="market" maxlength="80" placeholder="Hafeez Centre">
                    </div>
                    <div class="prd-field">
                        <label class="form-label" for="slApCommission">Commission</label>
                        <div class="input-group"><input type="number" class="form-control tabular" id="slApCommission" name="commission_pct" min="0" max="50" step="0.5" value="8" required><span class="input-group-text">%</span></div>
                    </div>
                    <div class="prd-field prd-span2">
                        <label class="form-label" for="slApSlug">Shop address</label>
                        <div class="input-group"><span class="input-group-text">techzone.pk/sellers/</span><input type="text" class="form-control" id="slApSlug" name="slug" maxlength="80" autocomplete="off" spellcheck="false"></div>
                        <div class="form-text">Lower-case letters, numbers and dashes. Leave as suggested if unsure.</div>
                    </div>
                </div>
            </div>
            <div class="modal-body" id="slApproveStep2" hidden>
                <div class="sl-done"><span class="sl-done-icon"><i class="fas fa-check" aria-hidden="true"></i></span><div><strong id="slDoneTitle">Seller approved</strong><div class="ord-hint" id="slDoneSub"></div></div></div>
                <p class="mb-2 mt-3">Share this temporary password with the seller. <strong>It is shown only once.</strong> They should change it on their Profile after signing in.</p>
                <div class="set-temp"><code id="slTempPassword"></code><button type="button" class="btn btn-sm btn-secondary" id="slTempCopy"><i class="fas fa-copy"></i> Copy</button></div>
                <dl class="kv mt-3 mb-0"><dt>Sign in at</dt><dd id="slTempUrl" class="tabular"></dd><dt>Email</dt><dd id="slTempEmail"></dd></dl>
            </div>
            <div class="modal-footer" id="slApproveFoot1">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="slApproveSubmit">Approve and create account</button>
            </div>
            <div class="modal-footer" id="slApproveFoot2" hidden>
                <a class="btn btn-secondary" id="slOpenSeller" href="#">Open seller</a>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject -->
<div class="modal fade" id="slRejectModal" tabindex="-1" aria-labelledby="slRejectTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="slRejectForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="slRejectTitle">Reject application</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="ord-consequence" id="slRejectText"></p>
                <label class="form-label" for="slRejectReason">Reason</label>
                <textarea class="form-control" id="slRejectReason" name="reason" rows="3" maxlength="255" required placeholder="e.g. We only list new, boxed parts with warranty."></textarea>
                <div class="form-text">Kept on the application so the team knows why.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep pending</button>
                <button type="submit" class="btn btn-danger">Reject application</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/sellers.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
