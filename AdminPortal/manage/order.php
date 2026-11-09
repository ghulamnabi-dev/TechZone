<?php
/**
 * Order page (docs/ADMIN.md: "An order is a page, not a row").
 * The shell is rendered here; assets/js/order.js fills every card from GET api/orders.php?id=N
 * and re-renders after each staff action (POST api/orders.php {id, action, …}).
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/orders.php';
checkLogin();
$recordId = isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0;
$ordRow = null;
$ordSeller = is_seller();
try {
    $ordRow = $recordId > 0 ? order_get($conn, $recordId) : null;
    if ($ordRow && $ordSeller) {
        // a seller only ever opens orders containing their products (same rule as api/orders.php)
        $st = $conn->prepare('SELECT 1 FROM order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = ? AND p.seller_id = ? LIMIT 1');
        $st->execute([$recordId, (int) current_seller_id()]);
        if (!$st->fetchColumn()) {
            $ordRow = null;
        }
    }
} catch (PDOException $e) {
    log_exception($e, 'order-page');
}
if (!$ordRow && $ordSeller) {
    http_response_code(404);
}
$navActive = 'orders.php';
$pageTitle = $ordRow ? 'Order #' . $recordId : 'Order not found';
$pageBack = ['href' => admin_url('manage/orders.php'), 'label' => 'Orders'];
if ($ordRow) {
    $pageSubtitle = 'Placed ' . date('j M Y, H:i', strtotime((string) $ordRow['order_date'])) . ($ordSeller ? ' · Showing your items only' : ' · Storefront');
    $pageActions = '<div class="ord-actions" id="ordActions" aria-label="Order actions"></div>';
}
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="<?= $tzAsset('assets/css/orders.css') ?>">
<?php if (!$ordRow): ?>
    <div class="panel">
        <div class="empty-state">
            <span class="empty-state-icon"><i class="fas fa-receipt"></i></span>
            <p class="empty-state-title">We couldn't find that order</p>
            <p class="empty-state-text">It may have been removed, or the link has a typo. Search for it from the orders list.</p>
            <a class="btn btn-secondary" href="<?= $tzE(admin_url('manage/orders.php')) ?>">Back to orders</a>
        </div>
    </div>
<?php require_once __DIR__ . '/../includes/footer.php'; return; endif; ?>

<div class="ord-layout" id="ordPage" data-order-id="<?= (int) $recordId ?>" aria-busy="true">
    <div class="ord-main">
        <section class="panel ord-card" aria-labelledby="ordItemsTitle">
            <div class="panel-head">
                <h2 class="panel-title" id="ordItemsTitle">Items</h2>
                <span id="ordItemsPill"></span>
                <span class="spacer"></span>
                <span id="ordItemsAction"></span>
            </div>
            <div id="ordItems" class="ord-items"><div class="ord-loading">Loading…</div></div>
        </section>

        <?php if (!$ordSeller): ?>
        <section class="panel ord-card" aria-labelledby="ordPayTitle">
            <div class="panel-head">
                <h2 class="panel-title" id="ordPayTitle">Payment</h2>
                <span id="ordPayPill"></span>
                <span class="spacer"></span>
                <span id="ordPayAction"></span>
            </div>
            <div class="panel-body" id="ordPayment"></div>
        </section>
        <?php endif; ?>

        <section class="panel ord-card" aria-labelledby="ordShipTitle">
            <div class="panel-head">
                <h2 class="panel-title" id="ordShipTitle">Shipping</h2>
                <span id="ordShipPill"></span>
                <span class="spacer"></span>
                <span id="ordShipAction"></span>
            </div>
            <div class="panel-body" id="ordShipping"></div>
        </section>

        <section class="panel ord-card" aria-labelledby="ordTimelineTitle">
            <div class="panel-head">
                <h2 class="panel-title" id="ordTimelineTitle">Timeline</h2>
                <span class="panel-sub">Newest first</span>
            </div>
            <div class="panel-body"><ol class="ord-timeline" id="ordTimeline"></ol></div>
        </section>
    </div>

    <aside class="ord-side" aria-label="Customer and notes">
        <section class="panel ord-card" aria-labelledby="ordCustTitle">
            <div class="panel-head"><h2 class="panel-title" id="ordCustTitle">Customer</h2></div>
            <div class="panel-body" id="ordCustomer"></div>
        </section>

        <section class="panel ord-card" aria-labelledby="ordNotesTitle">
            <div class="panel-head"><h2 class="panel-title" id="ordNotesTitle">Notes</h2><span class="panel-sub"><?= $ordSeller ? 'Your shop and TechZone' : 'Staff only' ?></span></div>
            <div class="panel-body">
                <form id="ordNoteForm" class="ord-note-form" data-cap="orders.note" novalidate>
                    <label class="visually-hidden" for="ordNoteBody">Add a note</label>
                    <textarea class="form-control" id="ordNoteBody" rows="3" maxlength="5000" placeholder="<?= $ordSeller ? 'Add a note for TechZone…' : 'Add a note for the team…' ?>"></textarea>
                    <div class="ord-note-foot"><span class="ord-meta">Customers never see notes.</span><button type="submit" class="btn btn-secondary btn-sm">Add note</button></div>
                </form>
                <ul class="ord-notes" id="ordNotes"></ul>
            </div>
        </section>

        <section class="panel ord-card" aria-labelledby="ordSumTitle">
            <div class="panel-head"><h2 class="panel-title" id="ordSumTitle">Summary</h2></div>
            <div class="panel-body" id="ordSummary"></div>
        </section>
    </aside>
</div>

<?php if (!$ordSeller): ?>
<!-- Record payment -->
<div class="modal fade" id="ordPayModal" tabindex="-1" aria-labelledby="ordPayModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="ordPayForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="ordPayModalTitle">Record payment</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="ordPayAmount">Amount received (Rs)</label>
                    <input type="number" class="form-control tabular" id="ordPayAmount" min="1" step="1" required>
                    <div class="invalid-feedback">Enter the amount received.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="ordPayMethod">Method</label>
                    <select class="form-select" id="ordPayMethod"></select>
                </div>
                <div>
                    <label class="form-label" for="ordPayRef">Reference <span class="ord-hint">(optional)</span></label>
                    <input type="text" class="form-control" id="ordPayRef" maxlength="80" placeholder="Transaction ID or receipt number">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Record payment</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>
<!-- Fulfil (partial allowed) -->
<div class="modal fade" id="ordFulfilModal" tabindex="-1" aria-labelledby="ordFulfilTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form class="modal-content" id="ordFulfilForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="ordFulfilTitle">Fulfil items</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="ord-consequence">Mark what has been picked and packed. Set a line to 0 to leave it for later.</p>
                <div class="ord-fulfil-list" id="ordFulfilList"></div>
            </div>
            <div class="modal-footer">
                <span class="me-auto ord-meta" id="ordFulfilTotal"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary">Fulfil items</button>
            </div>
        </form>
    </div>
</div>

<!-- Ship / edit tracking -->
<div class="modal fade" id="ordShipModal" tabindex="-1" aria-labelledby="ordShipModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="ordShipForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="ordShipModalTitle">Ship order</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="ord-consequence" id="ordShipText"></p>
                <div class="row g-3">
                    <div class="col-sm-5">
                        <label class="form-label" for="ordShipCourier">Courier</label>
                        <select class="form-select" id="ordShipCourier"></select>
                    </div>
                    <div class="col-sm-7">
                        <label class="form-label" for="ordShipTracking">Tracking number</label>
                        <input type="text" class="form-control tabular" id="ordShipTracking" maxlength="80" autocomplete="off" required>
                        <div class="invalid-feedback">Enter the tracking number from the courier receipt.</div>
                    </div>
                    <div class="col-12" id="ordShipOtherWrap" hidden>
                        <label class="form-label" for="ordShipOther">Courier name</label>
                        <input type="text" class="form-control" id="ordShipOther" maxlength="60">
                        <div class="invalid-feedback">Name the courier.</div>
                    </div>
                    <div class="col-12" id="ordShipNoteWrap" data-cap="orders.note">
                        <label class="form-label" for="ordShipNote">Note <span class="ord-hint">(optional, staff only)</span></label>
                        <textarea class="form-control" id="ordShipNote" rows="2" maxlength="1000" placeholder="e.g. Handed over at the Gulberg TCS counter"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-primary" id="ordShipSubmit">Ship order</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$ordSeller): ?>
<!-- Cancel -->
<div class="modal fade" id="ordCancelModal" tabindex="-1" aria-labelledby="ordCancelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="ordCancelForm" novalidate>
            <div class="modal-header">
                <h2 class="modal-title" id="ordCancelTitle">Cancel order</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="ord-warn-box" id="ordCancelText"></div>
                <label class="form-label" for="ordCancelReason">Reason</label>
                <select class="form-select" id="ordCancelReason">
                    <option value="Customer request">Customer request</option>
                    <option value="Out of stock">Out of stock</option>
                    <option value="Payment failed">Payment failed</option>
                    <option value="Fraud suspected">Fraud suspected</option>
                    <option value="Other">Other</option>
                </select>
                <div class="mt-2" id="ordCancelOtherWrap" hidden>
                    <label class="form-label" for="ordCancelOther">Describe the reason</label>
                    <input type="text" class="form-control" id="ordCancelOther" maxlength="200">
                    <div class="invalid-feedback">Describe why the order is being cancelled.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Keep order</button>
                <button type="submit" class="btn btn-danger">Cancel order</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>
<script src="<?= $tzAsset('assets/js/order.js') ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
