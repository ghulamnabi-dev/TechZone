<?php
$pageTitle = 'Messages';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title">Contact messages <span class="count-chip" id="messageCount">0</span></h2>
    </div>
    <div class="toolbar">
        <div class="seg" role="group" aria-label="Filter by status">
            <button type="button" class="filter-btn active" data-status="">All</button>
            <button type="button" class="filter-btn" data-status="new">New</button>
            <button type="button" class="filter-btn" data-status="read">Read</button>
            <button type="button" class="filter-btn" data-status="replied">Replied</button>
            <button type="button" class="filter-btn" data-status="archived">Archived</button>
        </div>
    </div>
    <div id="messagesContainer">
        <div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div></div>
    </div>
    <div class="table-foot" id="pagination"></div>
</div>

<!-- Message Detail Modal -->
<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalTitle">Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="messageDetails"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="markRepliedBtn"><i class="fas fa-check"></i> Mark as replied</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/contact_messages.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/contact_messages.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
