var reviews = [];
var reviewsGrid = null;

/** PUT a review's status (existing endpoint). Resolves true/false. */
function putReviewStatus(id, status) {
    return fetch(adminUrl('api/reviews.php'), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id, 10), status: status })
    }).then(function(r) { return r.json(); }).then(function(d) { return !!d.success; });
}

window.onload = function() {
    reviewsGrid = createAdminGrid(document.getElementById('reviewsList'), {
        storageKey: 'tz.grid.reviews.v1',
        noun: ['review', 'reviews'],
        emptyText: 'No reviews match these filters.',
        exportName: 'reviews',
        toolbar: { search: '#reviewSearch', count: '#reviewCount', exportBtn: '#reviewsExport', resetBtn: '#reviewsReset' },
        selectable: true,
        columns: [
            { field: 'customer_name', headerName: 'Customer', kind: 'stack', width: 180, mobileWidth: 130 },
            { field: 'product_name', headerName: 'Product', kind: 'muted', width: 220 },
            { field: 'rating', headerName: 'Rating', kind: 'stars', width: 120 },
            { field: 'comment', headerName: 'Review', kind: 'stack', flex: 1, minWidth: 260,
              cellRendererParams: { sub: function(r) { return r.admin_response ? '↩ ' + r.admin_response : ''; } },
              getQuickFilterText: function(p) { return (p.data.comment || '') + ' ' + (p.data.admin_response || ''); } },
            { field: 'created_at', headerName: 'Date', kind: 'date', width: 130, sort: 'desc' },
            { field: 'status', headerName: 'Status', kind: 'status', width: 128 }
        ],
        rowActions: function(r) {
            var id = parseInt(r.id, 10);
            var a = [];
            if (r.status !== 'Approved') a.push({ icon: 'fa-check', label: 'Approve', attrs: 'data-review-action="approve" data-review-id="' + id + '"', tone: 'ok' });
            if (r.status !== 'Rejected') a.push({ icon: 'fa-ban', label: 'Reject', attrs: 'data-review-action="reject" data-review-id="' + id + '"', tone: 'danger' });
            a.push({ icon: 'fa-reply', label: r.admin_response ? 'Edit response' : 'Respond', attrs: 'data-review-action="respond" data-review-id="' + id + '"' });
            a.push({ icon: 'fa-trash', label: 'Delete', attrs: 'data-review-action="delete" data-review-id="' + id + '"', tone: 'danger' });
            return a;
        },
        onRowOpen: function(r) { openRespondModal(r.id); },
        bulkActions: [
            { label: 'Approve', icon: 'fa-check', progress: 'Approving',
              perRow: function(r) { return r.status === 'Approved' ? true : putReviewStatus(r.id, 'Approved'); },
              done: function(ok, failed) { return ok + ' ' + (ok === 1 ? 'review' : 'reviews') + ' approved' + (failed ? ', ' + failed + ' failed' : ''); } },
            { label: 'Reject', icon: 'fa-ban', progress: 'Rejecting',
              perRow: function(r) { return r.status === 'Rejected' ? true : putReviewStatus(r.id, 'Rejected'); },
              done: function(ok, failed) { return ok + ' ' + (ok === 1 ? 'review' : 'reviews') + ' rejected' + (failed ? ', ' + failed + ' failed' : ''); } }
        ],
        onBulkDone: function() { loadReviews(); }
    });
    reviewsGrid.bindSelect('#statusFilter', 'status', function(v) { return { filterType: 'text', type: 'equals', filter: v }; });

    loadReviews();

    document.getElementById('reviewsList').addEventListener('click', function(e) {
        var b = e.target.closest('[data-review-action]');
        if (!b) return;
        var action = b.getAttribute('data-review-action');
        var id = b.getAttribute('data-review-id');
        if (action == 'approve') updateReviewStatus(id, 'Approved');
        else if (action == 'reject') updateReviewStatus(id, 'Rejected');
        else if (action == 'respond') openRespondModal(id);
        else if (action == 'delete') deleteReview(id);
    });

    document.getElementById('respondForm').onsubmit = function(e) {
        e.preventDefault();
        handleReviewResponse();
    };
};

function loadReviews() {
    // all statuses once; the status select drives the grid's filter model
    return fetch(adminUrl('api/reviews.php'))
    .then(function(response) { return response.json(); })
    .then(function(data) {
        reviews = data.success ? (data.reviews || []) : [];
        reviewsGrid.setRows(reviews);
        if (data.success) updateStats(data.stats);
    });
}

function updateStats(stats) {
    stats = stats || {};
    document.getElementById('totalReviews').textContent = formatNumber(stats.total_reviews || 0);
    document.getElementById('pendingReviews').textContent = formatNumber(stats.pending_reviews || 0);
    document.getElementById('approvedReviews').textContent = formatNumber(stats.approved_reviews || 0);
    var total = parseInt(stats.total_reviews, 10) || 0;
    var avg = stats.avg_rating != null ? Number(stats.avg_rating) : 0;
    document.getElementById('avgRating').textContent = total === 0 ? '—' : avg.toFixed(1);
}

function updateReviewStatus(reviewId, status) {
    var data = {
        id: parseInt(reviewId),
        status: status
    };
    
    fetch(adminUrl('api/reviews.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            showNotification('Review ' + status.toLowerCase() + ' successfully', 'success');
            loadReviews();
        } else {
            showNotification(data.message || 'Failed to update review', 'error');
        }
    });
}

function openRespondModal(reviewId) {
    for (var i = 0; i < reviews.length; i++) {
        if (reviews[i].id == reviewId) {
            var review = reviews[i];
            document.getElementById('reviewId').value = reviewId;
            document.getElementById('modalCustomerName').textContent = review.customer_name;
            document.getElementById('modalProductName').textContent = review.product_name;
            document.getElementById('modalReviewText').textContent = review.comment;
            document.getElementById('adminResponse').value = review.admin_response || '';
            
            var modal = new bootstrap.Modal(document.getElementById('respondModal'));
            modal.show();
            break;
        }
    }
}

function handleReviewResponse() {
    var reviewId = document.getElementById('reviewId').value;
    var response = document.getElementById('adminResponse').value;
    
    var data = {
        id: parseInt(reviewId),
        admin_response: response
    };
    
    fetch(adminUrl('api/reviews.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(function(result) {
        return result.json();
    })
    .then(function(data) {
        if (data.success) {
            showNotification('Response sent successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('respondModal')).hide();
            loadReviews();
        } else {
            showNotification(data.message || 'Failed to send response', 'error');
        }
    });
}

function deleteReview(reviewId) {
    showConfirm('Are you sure you want to delete this review?', function(confirmed) {
        if (!confirmed) return;
    
        fetch(adminUrl('api/reviews.php?id=' + reviewId), {
            method: 'DELETE'
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                showNotification('Review deleted successfully', 'success');
                loadReviews();
            } else {
                showNotification(data.message || 'Failed to delete review', 'error');
            }
        });
    });
}
