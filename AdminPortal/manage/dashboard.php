<?php
$pageTitle = 'Home';
$pageSubtitle = 'Today at TechZone and what needs doing';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
if (is_seller()) {
    // Seller portal home: their sales, orders to fulfil, low stock and the next payout
    $pageSubtitle = 'Your sales on TechZone and what needs doing';
    require __DIR__ . '/../includes/seller-home.php';
    return;
}
require_once __DIR__ . '/../includes/header.php';
$hu = static fn(string $p): string => htmlspecialchars(admin_url($p), ENT_QUOTES, 'UTF-8');
?>

<section class="kpi-grid home-today" aria-label="Today">
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-bag-shopping"></i> Orders today</div>
        <div class="kpi-value" id="kOrders">—</div>
        <div class="kpi-sub" id="kOrdersSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-wallet"></i> Revenue today</div>
        <div class="kpi-value" id="kRevenue">—</div>
        <div class="kpi-sub" id="kRevenueSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-receipt"></i> Average order</div>
        <div class="kpi-value" id="kAov">—</div>
        <div class="kpi-sub" id="kAovSub">&nbsp;</div>
    </div>
    <div class="kpi">
        <div class="kpi-label"><i class="fas fa-hand-holding-dollar"></i> Collected today</div>
        <div class="kpi-value" id="kCollected">—</div>
        <div class="kpi-sub" id="kCollectedSub">Payments recorded on orders</div>
    </div>
</section>

<section class="home-todo" aria-labelledby="todoTitle">
    <h2 class="home-section-title" id="todoTitle">To do</h2>
    <div class="home-todo-grid">
        <a class="home-card" data-todo="to_fulfil" href="<?= $hu('manage/orders.php?tab=unfulfilled') ?>">
            <span class="home-card-icon tone-info"><i class="fas fa-box"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="order to fulfil" data-many="orders to fulfil">orders to fulfil</span>
            <span class="home-card-sub">Confirmed or new, not packed yet</span>
        </a>
        <a class="home-card" data-todo="to_collect" href="<?= $hu('manage/orders.php?tab=unpaid') ?>">
            <span class="home-card-icon tone-warn"><i class="fas fa-money-bill-wave"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="payment to collect" data-many="payments to collect">payments to collect</span>
            <span class="home-card-sub">Cash on delivery, shipped or delivered, unpaid</span>
        </a>
        <a class="home-card" data-todo="low_stock" href="<?= $hu('manage/inventory.php') ?>">
            <span class="home-card-icon tone-danger"><i class="fas fa-triangle-exclamation"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="low-stock product" data-many="low-stock products">low-stock products</span>
            <span class="home-card-sub">At or below their threshold</span>
        </a>
        <a class="home-card" data-todo="reviews" href="<?= $hu('manage/reviews.php') ?>">
            <span class="home-card-icon tone-neutral"><i class="fas fa-star"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="review to moderate" data-many="reviews to moderate">reviews to moderate</span>
            <span class="home-card-sub">Hidden until approved</span>
        </a>
        <a class="home-card" data-todo="messages" href="<?= $hu('manage/contact_messages.php') ?>">
            <span class="home-card-icon tone-neutral"><i class="fas fa-envelope"></i></span>
            <span class="home-card-n tabular" data-n>—</span>
            <span class="home-card-label" data-one="message unread" data-many="messages unread">messages unread</span>
            <span class="home-card-sub">From the contact form</span>
        </a>
    </div>
</section>

<section class="chart-grid">
    <div class="panel mb-0">
        <div class="panel-head">
            <h2 class="panel-title">Sales</h2>
            <span class="panel-sub">Last 30 days</span>
            <span class="spacer"></span>
            <span class="home-legend" aria-hidden="true"><span class="home-sw home-sw-bar"></span>Daily <span class="home-sw home-sw-line"></span>7-day average</span>
            <span class="panel-sub tabular" id="salesTotal"></span>
        </div>
        <div class="panel-body">
            <div class="chart-box"><canvas id="salesChart" role="img" aria-label="Daily sales for the last 30 days with a 7-day moving average"></canvas></div>
        </div>
    </div>
    <div class="panel mb-0">
        <div class="panel-head">
            <h2 class="panel-title">Payment mix</h2>
            <span class="panel-sub" id="mixMonth">This month</span>
        </div>
        <div class="panel-body" id="paymentMix"><div class="muted">Loading…</div></div>
    </div>
</section>

<section class="dash-grid">
    <div class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Recent orders</h2>
            <span class="spacer"></span>
            <a class="btn btn-sm btn-secondary" href="<?= $hu('manage/orders.php') ?>">View all</a>
        </div>
        <div class="table-scroll">
            <table class="table table-hover home-orders">
                <thead>
                    <tr><th>Order</th><th>Customer</th><th>Payment</th><th>Fulfilment</th><th class="num">Total</th></tr>
                </thead>
                <tbody id="recentOrders"><tr class="loading-row"><td colspan="5">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div>
        <div class="panel">
            <div class="panel-head"><h2 class="panel-title">Top products</h2><span class="panel-sub">Last 30 days, by units</span></div>
            <ul class="rank-list" id="topProducts"><li class="muted">Loading…</li></ul>
        </div>
        <?php if (can('activity.view')): ?>
        <div class="panel">
            <div class="panel-head">
                <h2 class="panel-title">Recent activity</h2>
                <span class="spacer"></span>
                <a class="btn btn-sm btn-secondary" href="<?= $hu('manage/settings.php?tab=activity') ?>">Activity log</a>
            </div>
            <ul class="home-activity" id="recentActivity"><li class="muted">Loading…</li></ul>
        </div>
        <?php endif; ?>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?= $tzAsset('assets/js/dashboard.js') ?>"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
