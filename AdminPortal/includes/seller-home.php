<?php
/**
 * Seller portal Home (manage/dashboard.php for role seller). Filled by assets/js/seller-home.js from
 * api/dashboard_stats.php (seller_home: their lines only).
 */
require_once __DIR__ . '/header.php';
$hu = static fn(string $p): string => htmlspecialchars(admin_url($p), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/sellers.css') ?>">

<section class="kpi-grid" aria-label="Your sales">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-bag-shopping"></i> Sales today</div>
        <div class="kpi-value tabular" id="sToday">—</div>
        <div class="kpi-sub" id="sTodaySub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-calendar-week"></i> Last 7 days</div>
        <div class="kpi-value tabular" id="s7">—</div>
        <div class="kpi-sub" id="s7Sub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-calendar"></i> Last 30 days</div>
        <div class="kpi-value tabular" id="s30">—</div>
        <div class="kpi-sub" id="s30Sub">&nbsp;</div>
    </div>
    <a class="kpi kpi-link" href="<?= $hu('manage/payouts.php') ?>">
        <div class="kpi-label"><i class="fas fa-money-check-dollar"></i> <span id="sDueLabel">Payout due</span></div>
        <div class="kpi-value tabular" id="sDue">—</div>
        <div class="kpi-sub" id="sDueSub">&nbsp;</div>
    </a>
</section>

<section class="home-todo" aria-labelledby="todoTitle">
    <h2 class="home-section-title" id="todoTitle">To do</h2>
    <div class="home-todo-grid sl-todo">
        <a class="home-card" data-todo="to_fulfil" href="<?= $hu('manage/orders.php?tab=unfulfilled') ?>">
            <span class="home-card-icon tone-info"><i class="fas fa-box"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="order to fulfil and ship" data-many="orders to fulfil and ship">orders to fulfil and ship</span>
            <span class="home-card-sub">Orders with your items that you haven’t shipped yet</span>
        </a>
        <a class="home-card" data-todo="low_stock" href="<?= $hu('manage/inventory.php') ?>">
            <span class="home-card-icon tone-danger"><i class="fas fa-triangle-exclamation"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="product low on stock" data-many="products low on stock">products low on stock</span>
            <span class="home-card-sub">At or below their low-stock threshold</span>
        </a>
    </div>
</section>

<section class="dash-grid">
    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Recent orders</h2>
            <span class="panel-sub">Your items only</span>
            <span class="spacer"></span>
            <a class="btn btn-sm btn-secondary" href="<?= $hu('manage/orders.php') ?>">View all</a>
        </div>
        <div class="table-scroll">
            <table class="table table-hover home-orders">
                <thead><tr><th>Order</th><th>Customer</th><th>Fulfilment</th><th class="num">Your items</th></tr></thead>
                <tbody id="slRecent"><tr class="loading-row"><td colspan="4">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>
    <div>
        <div class="panel">
            <div class="panel-head"><h2 class="panel-title">Next payout</h2><span class="spacer"></span><a class="btn btn-sm btn-secondary" href="<?= $hu('manage/payouts.php') ?>">Statements</a></div>
            <div class="panel-body" id="slPayout"><div class="muted">Loading…</div></div>
        </div>
        <div class="panel">
            <div class="panel-head"><h2 class="panel-title">Low stock</h2><span class="spacer"></span><a class="btn btn-sm btn-secondary" href="<?= $hu('manage/inventory.php') ?>">Inventory</a></div>
            <ul class="rank-list" id="slLow"><li class="muted">Loading…</li></ul>
        </div>
    </div>
</section>

<script src="<?= $tzAsset('assets/js/seller-home.js') ?>"></script>
<?php require_once __DIR__ . '/footer.php'; ?>
