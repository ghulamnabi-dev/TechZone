let promotions = [];
let editingPromotionId = null;

function loadPromotions() {
    const container = document.getElementById('promotionsList');
    container.innerHTML = '<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    fetch(adminUrl('api/promotions.php'))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                promotions = data.promotions;
                displayPromotions();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = '<div class="col-12"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-exclamation-triangle text-warning"></i></div><h5 class="empty-state-title">Error loading promotions</h5><p class="empty-state-text mb-0">Please refresh the page or try again later.</p></div></div>';
        });
}

function displayPromotions() {
    const container = document.getElementById('promotionsList');
    
    if (promotions.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-tag"></i>
                    </div>
                    <h5 class="empty-state-title mb-1">No promotions yet</h5>
                    <p class="empty-state-text mb-0">Create your first promotion to show on the homepage and product pages. Click "Add Promotion" to get started.</p>
                </div>
            </div>
        `;
        return;
    }
    
    container.innerHTML = promotions.map(promo => `
        <div class="col-md-4">
            <div class="card h-100 promotion-card">
                ${promo.image_url ? `<img src="${promo.image_url}" class="card-img-top" alt="${promo.title}" style="height: 200px; object-fit: cover;">` : 
                    `<div class="card-img-top bg-primary d-flex align-items-center justify-content-center" style="height: 200px;"><i class="fas fa-tag fa-3x text-white"></i></div>`}
                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">${promo.title}</h5>
                    ${promo.description ? `<p class="card-text text-muted small flex-grow-1">${promo.description}</p>` : ''}
                    ${promo.discount_percentage > 0 ? `<span class="badge badge-status badge-status-delivered mb-2">${promo.discount_percentage}% OFF</span>` : ''}
                    ${promo.discount_amount > 0 ? `<span class="badge badge-status badge-status-delivered mb-2">Rs. ${promo.discount_amount} OFF</span>` : ''}
                    ${promo.promo_code ? `<small class="text-muted mb-2">Code: <strong>${promo.promo_code}</strong></small>` : ''}
                    <div class="mt-auto">
                        <small class="text-muted d-block mb-2">
                            ${promo.is_active ? '<span class="badge badge-status badge-status-delivered">Active</span>' : '<span class="badge badge-status badge-status-archived">Inactive</span>'}
                            &nbsp;Order: ${promo.display_order}
                        </small>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" onclick="editPromotion(${promo.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deletePromotion(${promo.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function showAddPromotionModal() {
    editingPromotionId = null;
    document.getElementById('promotionModalTitle').textContent = 'Add Promotion';
    document.getElementById('promotionForm').reset();
    document.getElementById('promotion_id').value = '';
    document.getElementById('promotion_discount_percentage').value = '0';
    document.getElementById('promotion_discount_amount').value = '0';
    document.getElementById('promotion_display_order').value = '0';
    document.getElementById('promotion_is_active').value = '1';
    // Hide image preview for new promotion
    document.getElementById('currentPromotionImage').style.display = 'none';
    document.getElementById('currentPromotionImagePreview').src = '';
    const modal = new bootstrap.Modal(document.getElementById('promotionModal'));
    modal.show();
}

function editPromotion(id) {
    const promo = promotions.find(p => p.id == id);
    if (!promo) return;
    
    editingPromotionId = id;
    document.getElementById('promotionModalTitle').textContent = 'Edit Promotion';
    document.getElementById('promotion_id').value = promo.id;
    document.getElementById('promotion_title').value = promo.title || '';
    document.getElementById('promotion_description').value = promo.description || '';
    document.getElementById('promotion_discount_percentage').value = promo.discount_percentage || '0';
    document.getElementById('promotion_discount_amount').value = promo.discount_amount || '0';
    document.getElementById('promotion_promo_code').value = promo.promo_code || '';
    document.getElementById('promotion_start_date').value = promo.start_date || '';
    document.getElementById('promotion_end_date').value = promo.end_date || '';
    document.getElementById('promotion_display_order').value = promo.display_order || '0';
    document.getElementById('promotion_is_active').value = promo.is_active;
    document.getElementById('promotion_link_url').value = promo.link_url || '';
    
    // Handle image preview
    if (promo.image_url) {
        document.getElementById('currentPromotionImagePreview').src = promo.image_url;
        document.getElementById('currentPromotionImage').style.display = 'block';
    } else {
        document.getElementById('currentPromotionImage').style.display = 'none';
        document.getElementById('currentPromotionImagePreview').src = '';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('promotionModal'));
    modal.show();
}

function savePromotion() {
    const id = document.getElementById('promotion_id').value;
    const title = document.getElementById('promotion_title').value;
    
    if (!title.trim()) {
        showNotification('Title is required', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('description', document.getElementById('promotion_description').value);
    formData.append('discount_percentage', parseFloat(document.getElementById('promotion_discount_percentage').value) || 0);
    formData.append('discount_amount', parseFloat(document.getElementById('promotion_discount_amount').value) || 0);
    formData.append('promo_code', document.getElementById('promotion_promo_code').value);
    formData.append('start_date', document.getElementById('promotion_start_date').value || '');
    formData.append('end_date', document.getElementById('promotion_end_date').value || '');
    formData.append('is_active', parseInt(document.getElementById('promotion_is_active').value));
    formData.append('display_order', parseInt(document.getElementById('promotion_display_order').value) || 0);
    formData.append('link_url', document.getElementById('promotion_link_url').value);
    
    // Add image file if selected
    const imageFile = document.getElementById('promotion_image').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }
    
    // Add ID for update
    if (id) {
        formData.append('id', parseInt(id));
    }
    
    const url = adminUrl('api/promotions.php');
    const method = id ? 'POST' : 'POST'; // Use POST for both, API will handle based on ID
    
    fetch(url, {
        method: method,
        body: formData
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('promotionModal')).hide();
            showNotification(id ? 'Promotion updated successfully' : 'Promotion created successfully', 'success');
            loadPromotions();
        } else {
            showNotification(result.message || 'Error saving promotion', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error saving promotion', 'error');
    });
}

function deletePromotion(id) {
    showConfirm('Are you sure you want to delete this promotion? This action cannot be undone.', function(confirmed) {
        if (!confirmed) return;
        
        fetch(adminUrl('api/promotions.php'), {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Promotion deleted successfully', 'success');
                loadPromotions();
            } else {
                showNotification(result.message || 'Error deleting promotion', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Error deleting promotion', 'error');
        });
    });
}

// Load promotions on page load
loadPromotions();

