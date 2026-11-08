let promotions = [];

// image_url is stored relative to the Admin Portal root ("uploads/x.png"); resolve it so it
// also works from manage/*.php pages.
function promoImageSrc(url) {
    if (!url || /^(https?:)?\/\//i.test(url) || url.charAt(0) === '/') return url;
    return adminUrl(url);
}
let editingPromotionId = null;

function loadPromotions() {
    const container = document.getElementById('promotionsList');
    container.innerHTML = '<tr class="loading-row"><td colspan="7">Loading…</td></tr>';
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
            container.innerHTML = emptyRow(7, 'Could not load promotions. Refresh to try again.');
        });
}

function promoState(promo) {
    if (!(promo.is_active == 1 || promo.is_active === true)) return 'inactive';
    var today = new Date(); today.setHours(0, 0, 0, 0);
    var start = parseDate(promo.start_date), end = parseDate(promo.end_date);
    if (start && start > today) return 'scheduled';
    if (end && end < today) return 'ended';
    return 'live';
}

function displayPromotions() {
    const container = document.getElementById('promotionsList');
    const countEl = document.getElementById('promotionCount');
    if (countEl) countEl.textContent = promotions.length;

    if (promotions.length === 0) {
        container.innerHTML = emptyRow(7, 'No promotions yet. Use "Add promotion" to create one.');
        return;
    }

    container.innerHTML = promotions.map(promo => {
        const id = parseInt(promo.id, 10);
        const pct = Number(promo.discount_percentage) || 0;
        const amt = Number(promo.discount_amount) || 0;
        const discount = [pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct) + '% off' : '', amt > 0 ? formatPKR(amt) + ' off' : ''].filter(Boolean).join(' + ') || '—';
        const img = promo.image_url
            ? `<img src="${escapeHtml(promoImageSrc(promo.image_url))}" class="thumb" alt="" loading="lazy">`
            : '<span class="thumb thumb-ph"><i class="fas fa-tag"></i></span>';
        const runs = (promo.start_date || promo.end_date)
            ? `${promo.start_date ? formatDate(promo.start_date) : 'Now'} – ${promo.end_date ? formatDate(promo.end_date) : 'open'}`
            : 'Always';
        const state = promoState(promo);
        return `
            <tr>
                <td><div class="prod-cell">${img}<div class="txt"><div class="cell-main">${escapeHtml(promo.title)}</div><div class="cell-sub cell-clip" style="max-width:340px">${escapeHtml(promo.description || '')}</div></div></div></td>
                <td class="nowrap tabular">${escapeHtml(discount)}</td>
                <td>${promo.promo_code ? `<span class="code-chip">${escapeHtml(promo.promo_code)}</span>` : '<span class="muted">—</span>'}</td>
                <td class="nowrap ink-2">${runs}</td>
                <td>${statusPill(state)}</td>
                <td class="num">${escapeHtml(promo.display_order)}</td>
                <td class="text-end"><div class="actions">
                    ${iconButton('fa-pen', 'Edit', `onclick="editPromotion(${id})"`)}
                    ${iconButton('fa-trash', 'Delete', `onclick="deletePromotion(${id})"`, 'danger')}
                </div></td>
            </tr>`;
    }).join('');
}

function showAddPromotionModal() {
    editingPromotionId = null;
    document.getElementById('promotionModalTitle').textContent = 'Add promotion';
    document.getElementById('promotionForm').reset();
    document.getElementById('promotion_id').value = '';
    document.getElementById('promotion_discount_percentage').value = '0';
    document.getElementById('promotion_discount_amount').value = '0';
    document.getElementById('promotion_display_order').value = '0';
    document.getElementById('promotion_is_active').value = '1';
    // Hide image preview for new promotion
    document.getElementById('currentPromotionImage').style.display = 'none';
    document.getElementById('currentPromotionImagePreview').src = '';
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('promotionModal'));
    modal.show();
}

function editPromotion(id) {
    const promo = promotions.find(p => p.id == id);
    if (!promo) return;
    
    editingPromotionId = id;
    document.getElementById('promotionModalTitle').textContent = 'Edit promotion';
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
        document.getElementById('currentPromotionImagePreview').src = promoImageSrc(promo.image_url);
        document.getElementById('currentPromotionImage').style.display = 'block';
    } else {
        document.getElementById('currentPromotionImage').style.display = 'none';
        document.getElementById('currentPromotionImagePreview').src = '';
    }
    
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('promotionModal'));
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

