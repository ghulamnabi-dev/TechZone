<?php
/**
 * Customer page (docs/ADMIN.md "Customers"): contact + tags, orders, customer-facing timeline,
 * overview (orders, lifetime value, average order, first/last order), internal notes, address.
 * Data: api/customers.php?id=N (assets/js/customer.js). Tags/notes need customers.write.
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
checkLogin();
$recordId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$navActive = 'customers.php';
$pageBack = ['href' => admin_url('manage/customers.php'), 'label' => 'Customers'];
$st = $conn->prepare('SELECT id, name, created_at FROM customers WHERE id = ?');
$st->execute([$recordId]);
$cust = $st->fetch(PDO::FETCH_ASSOC) ?: null;
$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

if (!$cust) {
    $pageTitle = 'Customer not found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="panel"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-user"></i></div>'
        . '<h2 class="empty-state-title">This customer doesn’t exist</h2><p class="empty-state-text">They may have been deleted. Their orders keep a copy of their name and email.</p>'
        . '<a class="btn btn-secondary" href="' . $e(admin_url('manage/customers.php')) . '">Back to customers</a></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    return;
}
$pageTitle = $cust['name'];
$pageSubtitle = 'Customer since ' . date('j M Y', strtotime($cust['created_at']));
$pageActions = '<button type="button" class="btn btn-outline-danger" id="cusDelete" data-cap="customers.delete"><i class="fas fa-trash"></i> Delete customer</button>';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="prd-layout" id="customerPage" data-id="<?= (int) $recordId ?>">
    <div class="prd-main">
        <section class="panel" aria-label="Contact and tags">
            <div class="cus-head">
                <span class="cus-avatar" id="cusAvatar" aria-hidden="true"></span>
                <div class="cus-head-main">
                    <h2 id="cusName"><?= $e($cust['name']) ?></h2>
                    <div class="cus-contact" id="cusContact"></div>
                </div>
            </div>
            <div class="cus-tags" id="cusTags" aria-label="Tags"></div>
        </section>

        <section class="panel cus-orders" aria-labelledby="cusOrdersTitle">
            <div class="panel-head"><h2 class="panel-title" id="cusOrdersTitle">Orders</h2><span class="count-chip" id="cusOrderCount">0</span></div>
            <div class="table-scroll">
                <table class="table mini-table prd-table">
                    <thead><tr><th>Order</th><th class="prd-hide-sm">Date</th><th>Status</th><th class="prd-hide-sm">Payment · Fulfilment</th><th class="num prd-hide-sm">Items</th><th class="num">Total</th></tr></thead>
                    <tbody id="cusOrders"><tr><td colspan="6" class="table-empty">Loading…</td></tr></tbody>
                </table>
            </div>
        </section>

        <section class="panel" aria-labelledby="cusTimelineTitle">
            <div class="panel-head"><h2 class="panel-title" id="cusTimelineTitle">Timeline</h2><span class="panel-sub">What the customer saw happen, newest first</span></div>
            <div class="panel-body cus-timeline-wrap"><ol class="cus-timeline" id="cusTimeline"></ol></div>
        </section>
    </div>

    <aside class="prd-side">
        <section class="panel" aria-labelledby="cusOverviewTitle">
            <div class="panel-head"><h2 class="panel-title" id="cusOverviewTitle">Overview</h2><span class="spacer"></span><span class="panel-sub">Excludes cancelled orders</span></div>
            <div class="panel-body">
                <div class="cus-overview">
                    <div><span>Orders</span><strong id="ovOrders">—</strong><small id="ovCancelled"></small></div>
                    <div><span>Lifetime value</span><strong id="ovLtv">—</strong></div>
                    <div><span>Average order</span><strong id="ovAvg">—</strong></div>
                    <div><span>Customer since</span><strong id="ovSince">—</strong></div>
                    <div><span>First order</span><strong id="ovFirst">—</strong></div>
                    <div><span>Last order</span><strong id="ovLast">—</strong><small id="ovLastAgo"></small></div>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="cusNotesTitle">
            <div class="panel-head"><h2 class="panel-title" id="cusNotesTitle">Notes</h2><span class="panel-sub">Internal; the customer never sees these</span></div>
            <div class="panel-body">
                <form class="cus-note-form" id="cusNoteForm" data-cap="customers.write" novalidate>
                    <label class="visually-hidden" for="cusNoteBody">New note</label>
                    <textarea class="form-control" id="cusNoteBody" name="body" rows="3" maxlength="2000" placeholder="Prefers evening delivery, wants a GST invoice…"></textarea>
                    <div class="d-flex justify-content-end mt-2"><button type="submit" class="btn btn-primary btn-sm" id="cusNoteSave">Add note</button></div>
                </form>
                <ul class="cus-notes" id="cusNotes"></ul>
            </div>
        </section>

        <section class="panel" aria-labelledby="cusAddrTitle">
            <div class="panel-head"><h2 class="panel-title" id="cusAddrTitle">Address</h2></div>
            <div class="panel-body"><p class="cus-address" id="cusAddress">—</p></div>
        </section>
    </aside>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/customer.js') ?>"></script>
