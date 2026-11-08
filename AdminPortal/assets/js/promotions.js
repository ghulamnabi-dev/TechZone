let promotions = [];

// image_url is stored relative to the Admin Portal root ("uploads/x.png"); resolve it so it
// also works from manage/*.php pages.
function promoImageSrc(url) {
    if (!url || /^(https?:)?\/\//i.test(url) || url.charAt(0) === '/') return url;
    return adminUrl(url);
}
let editingPromotionId = null;

let promotionsGrid = null;

function loadPromotions() {
    if (promotionsGrid) promotionsGrid.setLoading(true);
    fetch(adminUrl('api/promotions.php'))
        .then(response => response.json())
        .then(data => {
            promotions = data.success ? (data.promotions || []) : [];
            displayPromotions();
        })
        .catch(error => {
            console.error('Error:', error);
            promotions = [];
            displayPromotions();
            showNotification('Could not load promotions. Refresh to try again.', 'error');
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
function promoType(promo) {
    const pct = Number(promo.discount_percentage) || 0, amt = Number(promo.discount_amount) || 0;
    return pct > 0 && amt > 0 ? 'Percent + amount' : pct > 0 ? 'Percent' : amt > 0 ? 'Amount' : '—';
}
function promoValue(promo) {
    const pct = Number(promo.discount_percentage) || 0, amt = Number(promo.discount_amount) || 0;
    return [pct > 0 ? (pct % 1 === 0 ? pct.toFixed(0) : pct) + '% off' : '', amt > 0 ? formatPKR(amt) + ' off' : ''].filter(Boolean).join(' + ') || '—';
}
const capitalise = s => s.charAt(0).toUpperCase() + s.slice(1);

function initPromotionsGrid() {
    promotionsGrid = createAdminGrid(document.getElementById('promotionsList'), {
        storageKey: 'tz.grid.promotions.v1',
        noun: ['promotion', 'promotions'],
        emptyText: 'No promotions match these filters.',
        exportName: 'promotions',
        toolbar: { search: '#promotionSearch', count: '#promotionCount', exportBtn: '#promotionsExport', resetBtn: '#promotionsReset' },
        columns: [
            { field: 'title', headerName: 'Promotion', kind: 'thumb', flex: 1, minWidth: 230, mobileWidth: 170,
              cellRendererParams: { image: p => (p.image_url ? promoImageSrc(p.image_url) : null), icon: 'fa-tag', sub: p => p.description || '' } },
            { field: 'promo_code', headerName: 'Code', kind: 'code', width: 140 },
            { colId: 'value', headerName: 'Discount', kind: 'stack', width: 150, valueGetter: p => promoValue(p.data),
              cellRendererParams: { sub: p => promoType(p) },
              comparator: (a, b, na, nb) => ((Number(na.data.discount_percentage) || 0) * 1e6 + (Number(na.data.discount_amount) || 0)) - ((Number(nb.data.discount_percentage) || 0) * 1e6 + (Number(nb.data.discount_amount) || 0)) },
            { colId: 'type', headerName: 'Type', kind: 'muted', width: 140, hide: true, valueGetter: p => promoType(p.data) },
            { field: 'start_date', headerName: 'Starts', kind: 'date', width: 124 },
            { field: 'end_date', headerName: 'Ends', kind: 'date', width: 124 },
            { colId: 'state', headerName: 'Status', kind: 'status', width: 128, valueGetter: p => capitalise(promoState(p.data)) },
            { field: 'display_order', headerName: 'Order', kind: 'number', width: 92 }
        ],
        rowActions: promo => {
            const id = parseInt(promo.id, 10);
            return [
                { icon: 'fa-pen', label: 'Edit', attrs: `data-edit-promotion="${id}"` },
                { icon: 'fa-trash', label: 'Delete', attrs: `data-delete-promotion="${id}"`, tone: 'danger' }
            ];
        },
        onRowOpen: promo => editPromotion(parseInt(promo.id, 10))
    });
    promotionsGrid.bindSelect('#promotionStateFilter', 'state', v => ({ filterType: 'text', type: 'equals', filter: v }));
    document.getElementById('promotionsList').addEventListener('click', e => {
        const ed = e.target.closest('[data-edit-promotion]');
        if (ed) { editPromotion(parseInt(ed.getAttribute('data-edit-promotion'), 10)); return; }
        const del = e.target.closest('[data-delete-promotion]');
        if (del) deletePromotion(parseInt(del.getAttribute('data-delete-promotion'), 10));
    });
}

function displayPromotions() {
    promotionsGrid.setRows(promotions);
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
// (common.js loads after this script, so wait for the DOM before building the grid)
document.addEventListener('DOMContentLoaded', function () {
    initPromotionsGrid();
    loadPromotions();
});

