<?php
$pageTitle = 'Contact Messages';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Contact Messages</h5>
        <div class="btn-group" role="group">
            <button type="button" class="btn btn-sm btn-outline-primary filter-btn active" data-status="">All</button>
            <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-status="new">New</button>
            <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-status="read">Read</button>
            <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-status="replied">Replied</button>
            <button type="button" class="btn btn-sm btn-outline-primary filter-btn" data-status="archived">Archived</button>
        </div>
    </div>

    <div id="messagesContainer">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
    
    <nav aria-label="Page navigation">
        <ul class="pagination justify-content-center" id="pagination">
        </ul>
    </nav>
</div>

<!-- Message Detail Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageDetails">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="markRepliedBtn">Mark as Replied</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/contact_messages.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>


