<?php
/**
 * Seller portal: payout statements (seller.portal). Weekly Monday..Sunday statements of the seller's
 * delivered items: gross, commission, net, Upcoming / Paid. Computed by core/marketplace.php;
 * data from api/seller_portal.php (assets/js/payouts.js).
 */
$pageTitle = 'Payouts';
$pageSubtitle = 'What TechZone pays you for delivered items, week by week';
$pageCap = 'seller.portal';
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/sellers.css') ?>">

<section class="kpi-grid" aria-label="Payout summary">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-money-check-dollar"></i> <span id="pDueLabel">Due next Monday</span></div>
        <div class="kpi-value tabular" id="pDue">—</div>
        <div class="kpi-sub" id="pDueSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-hourglass-half"></i> This week so far</div>
        <div class="kpi-value tabular" id="pWeek">—</div>
        <div class="kpi-sub" id="pWeekSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-circle-check"></i> Paid to date</div>
        <div class="kpi-value tabular" id="pPaid">—</div>
        <div class="kpi-sub" id="pPaidSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-percent"></i> Commission</div>
        <div class="kpi-value tabular" id="pPct">—</div>
        <div class="kpi-sub">Per delivered sale, no listing fee</div>
    </div>
</section>

<section class="panel" aria-labelledby="pStTitle">
    <div class="panel-head"><h2 class="panel-title" id="pStTitle">Statements</h2><span class="panel-sub">Items count in the week they are delivered</span></div>
    <div class="table-scroll">
        <table class="table mini-table sl-statements">
            <thead><tr><th>Week</th><th class="num sl-hide-sm">Items</th><th class="num sl-hide-sm">Gross</th><th class="num sl-hide-sm">Commission</th><th class="num">Net payable</th><th>Status</th><th class="sl-hide-sm">Reference</th></tr></thead>
            <tbody id="pRows"><tr><td colspan="7" class="table-empty">Loading…</td></tr></tbody>
        </table>
    </div>
    <div class="panel-body sl-explain">
        <p class="mb-1"><strong>How payouts work.</strong> Every Monday TechZone pays you for your items delivered the week before (Monday to Sunday), minus the commission.</p>
        <p class="mb-0 ord-hint">Cancelled and refunded orders never count. Cash collected by couriers stays with TechZone until your payout. Questions about a statement? Add a note on the order or message TechZone.</p>
    </div>
</section>

<script src="<?= $tzAsset('assets/js/payouts.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
