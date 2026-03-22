var reviews = [];

window.onload = function() {
    loadReviews();
    
    document.getElementById('statusFilter').onchange = function() {
        loadReviews();
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
    document.getElementById('totalReviews').innerHTML = stats.total_reviews || 0;
    document.getElementById('pendingReviews').innerHTML = stats.pending_reviews || 0;
    document.getElementById('approvedReviews').innerHTML = stats.approved_reviews || 0;
    var total = parseInt(stats.total_reviews, 10) || 0;
    var avg = stats.avg_rating != null ? Number(stats.avg_rating) : 0;
    document.getElementById('avgRating').innerHTML = total === 0 ? '—' : avg.toFixed(1);
}

function renderReviews() {
    var container = document.getElementById('reviewsList');
    
    if (reviews.length == 0) {
        container.innerHTML = '<div class="col-12"><div class="alert alert-info">No reviews found.</div></div>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < reviews.length; i++) {
        var review = reviews[i];
        var statusBadge = 'warning';
        if (review.status == 'Approved') statusBadge = 'success';
        else if (review.status == 'Rejected') statusBadge = 'danger';
        
        var stars = '';
        for (var j = 0; j < review.rating; j++) {
            stars += '★';
        }
        for (var j = review.rating; j < 5; j++) {
            stars += '☆';
        }
        
        html += '<div class="col-md-6 mb-4">';
        html += '<div class="card h-100">';
        html += '<div class="card-body">';
        html += '<h6>' + review.customer_name + ' <span class="badge bg-' + statusBadge + '">' + review.status + '</span></h6>';
        html += '<small>' + review.product_name + '</small><br>';
        html += '<div class="mb-2"><span style="color:#ffc107;font-size:1.2rem;">' + stars + '</span> ' + review.rating + '/5</div>';
        html += '<p>' + review.comment + '</p>';
        html += '<small>Posted on ' + formatDate(review.created_at) + '</small>';
        
        if (review.admin_response) {
            html += '<div class="mt-3 p-2 bg-light rounded">';
            html += '<small><strong>Admin Response:</strong></small>';
            html += '<p class="mb-0 small">' + review.admin_response + '</p>';
            html += '</div>';
        }
        
        html += '<div class="mt-3">';
        if (review.status == 'Pending') {
            html += '<button class="btn btn-sm btn-success me-2" data-review-action="approve" data-review-id="' + review.id + '">Approve</button>';
            html += '<button class="btn btn-sm btn-danger me-2" data-review-action="reject" data-review-id="' + review.id + '">Reject</button>';
        }
        html += '<button class="btn btn-sm btn-primary me-2" data-review-action="respond" data-review-id="' + review.id + '">Respond</button>';
        html += '<button class="btn btn-sm btn-danger" data-review-action="delete" data-review-id="' + review.id + '">Delete</button>';
        html += '</div></div></div></div>';
    }
    container.innerHTML = html;
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
            document.getElementById('modalCustomerName').innerHTML = review.customer_name;
            document.getElementById('modalProductName').innerHTML = review.product_name;
            document.getElementById('modalReviewText').innerHTML = review.comment;
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
