var reviews = [];

window.onload = function() {
    loadReviews();
    
    document.getElementById('statusFilter').onchange = function() {
        reviewPage = 1;
        loadReviews();
    };
    document.getElementById('reviewSearch').oninput = function() {
        reviewPage = 1;
        renderReviews();
    };
    
    document.getElementById('reviewsList').onclick = function(e) {
        if (e.target.closest('[data-review-action]')) {
            var action = e.target.closest('[data-review-action]').getAttribute('data-review-action');
            var id = e.target.closest('[data-review-action]').getAttribute('data-review-id');
            
            if (action == 'approve') {
                updateReviewStatus(id, 'Approved');
            } else if (action == 'reject') {
                updateReviewStatus(id, 'Rejected');
            } else if (action == 'respond') {
                openRespondModal(id);
            } else if (action == 'delete') {
                deleteReview(id);
            }
        }
    };
    
    document.getElementById('respondForm').onsubmit = function(e) {
        e.preventDefault();
        handleReviewResponse();
    };
};

function loadReviews() {
    var status = document.getElementById('statusFilter').value;
    var url = adminUrl('api/reviews.php');
    if (status) url += '?status=' + encodeURIComponent(status);
    
    fetch(url)
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            reviews = data.reviews;
            renderReviews();
            updateStats(data.stats);
        }
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

var REVIEWS_PAGE_SIZE = 25;
var reviewPage = 1;

function starRating(n) {
    n = Math.max(0, Math.min(5, parseInt(n, 10) || 0));
    return '<span class="stars" aria-label="' + n + ' out of 5">' + '★★★★★'.slice(0, n) + '<span class="off">' + '★★★★★'.slice(0, 5 - n) + '</span></span>';
}

function renderReviews() {
    var tbody = document.getElementById('reviewsList');
    var q = (document.getElementById('reviewSearch').value || '').trim().toLowerCase();
    var list = reviews.filter(function(r) {
        return !q || (r.customer_name + ' ' + r.product_name + ' ' + (r.comment || '')).toLowerCase().indexOf(q) !== -1;
    });
    document.getElementById('reviewCount').textContent = list.length;

    var pages = Math.max(1, Math.ceil(list.length / REVIEWS_PAGE_SIZE));
    if (reviewPage > pages) reviewPage = pages;
    var pageItems = list.slice((reviewPage - 1) * REVIEWS_PAGE_SIZE, reviewPage * REVIEWS_PAGE_SIZE);
    renderPager(document.getElementById('reviewsPager'), list.length, reviewPage, REVIEWS_PAGE_SIZE, function(pg) {
        reviewPage = pg;
        renderReviews();
    }, 'reviews');

    if (list.length == 0) {
        tbody.innerHTML = emptyRow(7, 'No reviews found.');
        return;
    }

    var html = '';
    for (var i = 0; i < pageItems.length; i++) {
        var r = pageItems[i];
        html += '<tr>';
        html += '<td class="nowrap"><div class="cell-main" style="font-weight:500">' + escapeHtml(r.customer_name) + '</div></td>';
        html += '<td class="ink-2"><div class="cell-clip" style="max-width:220px" title="' + escapeHtml(r.product_name) + '">' + escapeHtml(r.product_name) + '</div></td>';
        html += '<td>' + starRating(r.rating) + '</td>';
        html += '<td style="min-width:260px;max-width:420px"><div>' + escapeHtml(r.comment) + '</div>' +
            (r.admin_response ? '<div class="cell-sub"><i class="fas fa-reply fa-flip-horizontal me-1"></i>' + escapeHtml(r.admin_response) + '</div>' : '') + '</td>';
        html += '<td class="nowrap ink-2">' + formatDate(r.created_at) + '</td>';
        html += '<td>' + statusPill(r.status) + '</td>';
        html += '<td class="text-end"><div class="actions">';
        if (r.status == 'Pending') {
            html += iconButton('fa-check', 'Approve', 'data-review-action="approve" data-review-id="' + r.id + '"', 'ok');
            html += iconButton('fa-ban', 'Reject', 'data-review-action="reject" data-review-id="' + r.id + '"', 'danger');
        }
        html += iconButton('fa-reply', r.admin_response ? 'Edit response' : 'Respond', 'data-review-action="respond" data-review-id="' + r.id + '"');
        html += iconButton('fa-trash', 'Delete', 'data-review-action="delete" data-review-id="' + r.id + '"', 'danger');
        html += '</div></td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
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
