var categories = [];
var products = [];
var productFormVariations = []; // Store variations for product being added/edited

// Make productFormVariations globally accessible
window.productFormVariations = productFormVariations;

window.onload = function() {
    loadCategories();
    loadProducts();
    
    // Setup image preview when file is selected
    var imageInput = document.getElementById('productImage');
    if (imageInput) {
        imageInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            var preview = document.getElementById('currentImagePreview');
            var previewContainer = document.getElementById('currentImage');
            
            if (file) {
                // Show preview of newly selected file
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                // If file input cleared, show original image if editing
                if (window.currentProductImage) {
                    preview.src = productImageDisplayUrl(window.currentProductImage);
                    previewContainer.style.display = 'block';
                } else {
                    previewContainer.style.display = 'none';
                }
            }
        });
    }
    
    initProductsGrid();

    var addProductBtn = document.getElementById('addProductBtn');   // absent for roles without products.write
    if (addProductBtn) addProductBtn.onclick = function() {
        openAddProductModal();
    };
    
    document.getElementById('productForm').onsubmit = function(e) {
        e.preventDefault();
        handleProductSubmit();
    };
    
    document.getElementById('productsTable').addEventListener('click', function(e) {
        if (e.target.closest('[data-edit-product]')) {
            var id = e.target.closest('[data-edit-product]').getAttribute('data-edit-product');
            openEditProductModal(id);
        }
        if (e.target.closest('[data-delete-product]')) {
            var id = e.target.closest('[data-delete-product]').getAttribute('data-delete-product');
            deleteProduct(id);
        }
        if (e.target.closest('[data-manage-variations]')) {
            var id = e.target.closest('[data-manage-variations]').getAttribute('data-manage-variations');
            openVariationsModal(id);
        }
        if (e.target.closest('[data-toggle-status]')) {
            var btn = e.target.closest('[data-toggle-status]');
            var id = btn.getAttribute('data-toggle-status');
            var currentStatus = parseInt(btn.getAttribute('data-current-status')) || 0;
            toggleProductStatus(id, currentStatus);
        }
    });
    
    // Variations modal handlers
    document.getElementById('addVariationBtn').onclick = function() {
        openAddVariationModal();
    };
    
    document.getElementById('variationForm').onsubmit = function(e) {
        e.preventDefault();
        handleVariationSubmit();
    };
    
    // Use event delegation for buttons inside modals (they may not exist when page loads)
    document.addEventListener('click', function(e) {
        // Handle Add Variation button in product form
        var btn = e.target.closest('#addVariationInProductForm');
        if (btn) {
            e.preventDefault();
            e.stopPropagation();
            openAddVariationFromProductForm();
            return false;
        }
    });
};

function loadCategories() {
    fetch(adminUrl('api/categories.php'))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            categories = data.categories;
            populateCategorySelect();
        }
    });
}

function populateCategorySelect() {
    var filter = document.getElementById('productCategoryFilter');
    if (filter) {
        var keep = filter.value;
        filter.innerHTML = '<option value="">All categories</option>';
        categories.slice().sort(function(a, b) { return a.name.localeCompare(b.name); }).forEach(function(c) {
            var o = document.createElement('option');
            o.value = c.name;
            o.text = c.name;
            filter.appendChild(o);
        });
        filter.value = keep;
    }
    var select = document.getElementById('productCategory');
    select.innerHTML = '<option value="">Select category</option>';
    for (var i = 0; i < categories.length; i++) {
        var option = document.createElement('option');
        option.value = categories[i].id;
        option.text = categories[i].name;
        select.appendChild(option);
    }
}

function loadProducts() {
    return fetch(adminUrl('api/products.php'))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            products = data.products || [];
            if (productsGrid) productsGrid.setRows(products);
        } else if (productsGrid) {
            productsGrid.setRows([]);
        }
    });
}

var PLACEHOLDER_IMG = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
var productsGrid = null;

function isProductActive(p) {
    return p.is_active == 1 || p.is_active === true || p.is_active === '1';
}
function productSku(p) { return 'TZ-' + String(p.id).padStart(4, '0'); }
function productThumbUrl(p) {
    var imageUrl = p.imagelink || '';
    // cache-bust on updated_at so replaced images show without busting on every render
    if (imageUrl) imageUrl += (imageUrl.indexOf('?') > -1 ? '&' : '?') + 'v=' + encodeURIComponent(p.updated_at || '');
    return imageUrl || PLACEHOLDER_IMG;
}

/** PATCH is_active for one product (existing toggle endpoint). Resolves true/false. */
function patchProductActive(id, active) {
    return fetch(adminUrl('api/products.php'), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, is_active: active ? 1 : 0 })
    }).then(function(r) { return r.json(); }).then(function(d) { return !!d.success; });
}
/** DELETE one product (existing endpoint). Resolves true/false; refusals (product has orders) count as failed. */
function deleteProductRequest(id) {
    return fetch(adminUrl('api/products.php?id=' + encodeURIComponent(id)), { method: 'DELETE', body: new FormData() })
        .then(function(r) { return r.json(); }).then(function(d) { return !!d.success; });
}

function initProductsGrid() {
    productsGrid = createAdminGrid(document.getElementById('productsTable'), {
        storageKey: 'tz.grid.products.v2',
        rowHeight: 52,
        noun: ['product', 'products'],
        emptyText: 'No products match these filters.',
        exportName: 'products',
        toolbar: { search: '#productSearch', count: '#productCount', exportBtn: '#productsExport', resetBtn: '#productsReset' },
        selectable: tzCan('products.write'),
        columns: [
            { field: 'name', headerName: 'Product', kind: 'thumb', flex: 1, minWidth: 260, mobileWidth: 170,
              cellRendererParams: { wrap: true, image: productThumbUrl, sub: function(p) { return 'SKU ' + productSku(p); } },
              getQuickFilterText: function(p) { return p.data.name + ' ' + productSku(p.data) + ' #' + p.data.id; } },
            { colId: 'sku', headerName: 'SKU', kind: 'muted', width: 110, hide: true, valueGetter: function(p) { return productSku(p.data); } },
            { field: 'category_name', headerName: 'Category', kind: 'chip', width: 156,
              valueGetter: function(p) { return p.data.category_name || 'Uncategorised'; } },
            { field: 'price', headerName: 'Price', kind: 'money', width: 130 },
            { field: 'inventory', headerName: 'Inventory', kind: 'stock', width: 124 },
            { field: 'sold', headerName: 'Sold', kind: 'number', width: 76 },
            { colId: 'status', headerName: 'Status', kind: 'status', width: 116,
              valueGetter: function(p) { return isProductActive(p.data) ? 'Active' : 'Inactive'; } }
        ],
        rowActions: function(product) {
            var id = parseInt(product.id, 10);
            var active = isProductActive(product);
            // role gating (the API enforces the same capabilities)
            var write = tzCan('products.write');
            return [
                write && { icon: 'fa-layer-group', label: 'Variations', attrs: 'data-manage-variations="' + id + '"' },
                { icon: write ? 'fa-pen' : 'fa-eye', label: write ? 'Edit' : 'View', attrs: 'data-edit-product="' + id + '"' },
                write && { icon: active ? 'fa-eye-slash' : 'fa-eye', label: active ? 'Deactivate' : 'Activate', attrs: 'data-toggle-status="' + id + '" data-current-status="' + (active ? 1 : 0) + '"', tone: active ? '' : 'ok' },
                tzCan('products.delete') && { icon: 'fa-trash', label: 'Delete', attrs: 'data-delete-product="' + id + '"', tone: 'danger' }
            ];
        },
        onRowOpen: function(p) { openEditProductModal(p.id); },
        bulkActions: [
            tzCan('products.write') && { label: 'Activate', icon: 'fa-eye', progress: 'Activating',
              perRow: function(p) { return isProductActive(p) ? true : patchProductActive(p.id, true); },
              done: function(ok, failed) { return ok + ' ' + (ok === 1 ? 'product' : 'products') + ' activated' + (failed ? ', ' + failed + ' failed' : ''); } },
            tzCan('products.write') && { label: 'Deactivate', icon: 'fa-eye-slash', progress: 'Deactivating',
              perRow: function(p) { return !isProductActive(p) ? true : patchProductActive(p.id, false); },
              done: function(ok, failed) { return ok + ' ' + (ok === 1 ? 'product' : 'products') + ' deactivated' + (failed ? ', ' + failed + ' failed' : ''); } },
            tzCan('products.delete') && { label: 'Delete', icon: 'fa-trash', tone: 'danger', progress: 'Deleting',
              confirm: function(rows) { return 'Delete ' + rows.length + ' ' + (rows.length === 1 ? 'product' : 'products') + '? Products that already have orders are kept.'; },
              confirmLabel: 'Delete',
              perRow: function(p) { return deleteProductRequest(p.id); },
              done: function(ok, failed) {
                  return ok + ' ' + (ok === 1 ? 'product' : 'products') + ' deleted' + (failed ? ', ' + failed + ' kept (they have orders)' : '');
              } }
        ].filter(Boolean),
        onBulkDone: function() { loadProducts(); }
    });
    productsGrid.bindSelect('#productCategoryFilter', 'category_name', function(v) { return { filterType: 'text', type: 'equals', filter: v }; });

    // one select drives two columns: Active/Inactive -> status, stock levels -> inventory
    var statusSel = document.getElementById('productStatusFilter');
    statusSel.addEventListener('change', function() {
        var v = statusSel.value;
        var api = productsGrid.api;
        var status = v === 'active' ? { filterType: 'text', type: 'equals', filter: 'Active' }
            : v === 'inactive' ? { filterType: 'text', type: 'equals', filter: 'Inactive' } : null;
        var inv = v === 'low' ? { filterType: 'number', operator: 'AND', conditions: [
                    { filterType: 'number', type: 'greaterThan', filter: 0 },
                    { filterType: 'number', type: 'lessThanOrEqual', filter: 5 }] }
            : v === 'out' ? { filterType: 'number', type: 'lessThanOrEqual', filter: 0 } : null;
        Promise.all([api.setColumnFilterModel('status', status), api.setColumnFilterModel('inventory', inv)])
            .then(function() { api.onFilterChanged(); });
    });
}

function openAddProductModal() {
    document.getElementById('productModalTitle').textContent = 'Add product';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = '';
    document.getElementById('productSold').value = '0';
    document.getElementById('productIsActive').checked = true; // Default to active
    document.getElementById('currentImage').style.display = 'none';
    window.currentProductImage = ''; // Clear stored image
    productFormVariations = []; // Reset variations
    window.productFormVariations = []; // Reset global reference
    
    // Hide inline form
    var inlineForm = document.getElementById('inlineVariationForm');
    if (inlineForm) inlineForm.style.display = 'none';
    
    // Reset button
    var btn = document.getElementById('toggleVariationFormBtn');
    if (btn) {
        btn.innerHTML = '<i class="fas fa-plus"></i> Add variation';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-outline-primary');
    }
    
    // Render variations (should show empty message)
    if (typeof renderProductFormVariations === 'function') {
        renderProductFormVariations();
    } else if (typeof renderProductFormVariationsInline === 'function') {
        renderProductFormVariationsInline();
    }
    
    var modal = new bootstrap.Modal(document.getElementById('productModal'));
    modal.show();
}

function openEditProductModal(productId) {
    for (var i = 0; i < products.length; i++) {
        if (products[i].id == productId) {
            var product = products[i];
            document.getElementById('productModalTitle').textContent = 'Edit product';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategory').value = product.category_id;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productInventory').value = product.inventory;
            document.getElementById('productSold').value = product.sold;
            document.getElementById('productIsActive').checked = (product.is_active == 1 || product.is_active === true || product.is_active === '1');
            document.getElementById('productDescription').value = product.description || '';
            
            // Handle image - Reset file input and show current image
            var imageInput = document.getElementById('productImage');
            if (imageInput) {
                imageInput.value = ''; // Clear file input so new selection is detected
            }
            if (product.imagelink) {
                document.getElementById('currentImagePreview').src = product.imagelink;
                document.getElementById('currentImage').style.display = 'block';
            } else {
                document.getElementById('currentImage').style.display = 'none';
            }
            
            // Store original image for reference
            window.currentProductImage = product.imagelink || '';
            
            // Load existing variations
            productFormVariations = [];
            window.productFormVariations = productFormVariations;
            loadProductVariationsForForm(productId);
            
            var modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
            break;
        }
    }
}

function loadProductVariationsForForm(productId) {
    fetch(adminUrl('api/variations.php?product_id=' + productId))
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                return { success: true, variations: [] };
            }
        });
    })
    .then(function(data) {
        if (data.success) {
            productFormVariations = data.variations || [];
            window.productFormVariations = productFormVariations;
            // Use inline render function if available, otherwise fall back
            if (typeof renderProductFormVariationsInline === 'function') {
                renderProductFormVariationsInline();
            } else if (typeof renderProductFormVariations === 'function') {
                renderProductFormVariations();
            }
        } else {
            productFormVariations = [];
            window.productFormVariations = [];
            if (typeof renderProductFormVariationsInline === 'function') {
                renderProductFormVariationsInline();
            } else if (typeof renderProductFormVariations === 'function') {
                renderProductFormVariations();
            }
        }
    })
    .catch(function(error) {
        console.error('Error loading variations:', error);
        productFormVariations = [];
        window.productFormVariations = [];
        if (typeof renderProductFormVariationsInline === 'function') {
            renderProductFormVariationsInline();
        } else if (typeof renderProductFormVariations === 'function') {
            renderProductFormVariations();
        }
    });
}

function renderProductFormVariations() {
    if (typeof window.renderProductFormVariationsInline === 'function') {
        window.renderProductFormVariationsInline();
    }
}

function removeVariationFromProductForm(index) {
    showConfirm('Remove this variation from the list?', function(confirmed) {
        if (confirmed) {
            var variations = window.productFormVariations || productFormVariations;
            variations.splice(index, 1);
            window.productFormVariations = variations;
            productFormVariations = variations;
            renderProductFormVariations();
        }
    });
}

// Make function globally accessible
window.removeVariationFromProductForm = removeVariationFromProductForm;

function openAddVariationFromProductForm() {
    
    try {
        var productIdEl = document.getElementById('productId');
        var productId = productIdEl ? productIdEl.value : '';
        
        var variationFormModal = document.getElementById('variationFormModal');
        if (!variationFormModal) {
            console.error('ERROR: variationFormModal element not found!');
            alert('Error: Variation form modal not found. Please refresh the page.');
            return;
        }
        
        // Reset form
        var formEl = document.getElementById('variationForm');
        if (formEl) formEl.reset();
        
        // Set form values
        var titleEl = document.getElementById('variationFormTitle');
        if (titleEl) titleEl.textContent = 'Add variation';
        
        var variationIdEl = document.getElementById('variationId');
        if (variationIdEl) variationIdEl.value = '';
        
        var variationProductIdFormEl = document.getElementById('variationProductIdForm');
        if (variationProductIdFormEl) variationProductIdFormEl.value = productId || 'new';
        
        var priceModEl = document.getElementById('variationPriceModifier');
        if (priceModEl) priceModEl.value = '0';
        
        var inventoryEl = document.getElementById('variationInventory');
        if (inventoryEl) inventoryEl.value = '0';
        
        var displayOrderEl = document.getElementById('variationDisplayOrder');
        if (displayOrderEl) displayOrderEl.value = productFormVariations.length.toString();
        
        var isActiveEl = document.getElementById('variationIsActive');
        if (isActiveEl) isActiveEl.value = '1';
        
        var typeEl = document.getElementById('variationType');
        if (typeEl) {
            typeEl.disabled = false;
            typeEl.value = '';
        }
        
        // Mark that this is for product form
        variationFormModal.setAttribute('data-for-product-form', 'true');
        
        // Show modal using Bootstrap
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            var modal = new bootstrap.Modal(variationFormModal, {
                backdrop: true,
                keyboard: true
            });
            
            // Prevent closing product modal when variation modal closes
            variationFormModal.addEventListener('hidden.bs.modal', function() {
                // Ensure product modal stays open
                var productModal = document.getElementById('productModal');
                if (productModal && !productModal.classList.contains('show')) {
                    // Product modal was closed, reopen it
                    var productModalInstance = bootstrap.Modal.getInstance(productModal);
                    if (!productModalInstance) {
                        productModalInstance = new bootstrap.Modal(productModal);
                    }
                    productModalInstance.show();
                }
            }, { once: true });
            
            modal.show();
            
        } else {
            console.error('Bootstrap is not loaded!');
            // Fallback: show modal manually
            variationFormModal.style.display = 'block';
            variationFormModal.classList.add('show');
            document.body.classList.add('modal-open');
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            backdrop.id = 'modalBackdrop';
            document.body.appendChild(backdrop);
        }
    } catch (error) {
        console.error('Error in openAddVariationFromProductForm:', error);
        alert('Error opening variation form: ' + error.message);
    }
}

function toggleProductStatus(productId, currentStatus) {
    var newStatus = currentStatus == 1 ? 0 : 1;
    var statusText = newStatus == 1 ? 'activated' : 'deactivated';
    
    fetch(adminUrl('api/products.php'), {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            id: productId,
            is_active: newStatus
        })
    })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                showNotification('Product ' + statusText + ' successfully', 'success');
                loadProducts();
            } else {
                showNotification(data.message || 'Failed to update product status', 'error');
            }
        })
        .catch(function(error) {
            console.error('Error toggling product status:', error);
            showNotification('Error updating product status: ' + error.message, 'error');
        });
}

function deleteProduct(productId) {
    showConfirm('Are you sure you want to delete this product?', function(confirmed) {
        if (!confirmed) return;
        var metaEl = document.querySelector('meta[name="csrf-token"]');
        var csrfMeta = document.getElementById('csrf-meta');
        var form = document.getElementById('productForm');
        var csrfToken = (metaEl && metaEl.getAttribute('content')) || (csrfMeta && csrfMeta.getAttribute('data-csrf-token')) || (form && form.querySelector('input[name="csrf_token"]') && form.querySelector('input[name="csrf_token"]').value) || '';
        if (!csrfToken) {
            showNotification('Security token missing. Refresh the page and try again.', 'error');
            return;
        }
        var formData = new FormData();
        formData.append('csrf_token', csrfToken);
        fetch(adminUrl('api/products.php?id=' + productId), {
            method: 'DELETE',
            headers: { 'X-CSRF-Token': csrfToken },
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.success) {
                showNotification('Product deleted successfully', 'success');
                loadProducts();
            } else {
                showNotification(data.message || 'Failed to delete product', 'error');
            }
        });
    });
}

function handleProductSubmit() {
    var productId = document.getElementById('productId').value;
    var name = document.getElementById('productName').value;
    var description = document.getElementById('productDescription').value;
    var price = document.getElementById('productPrice').value;
    var category_id = document.getElementById('productCategory').value;
    var inventory = document.getElementById('productInventory').value;
    var sold = document.getElementById('productSold').value;
    var imageFile = document.getElementById('productImage').files[0];
    
    if (name === '' || price === '' || category_id === '' || description.trim() === '') {
        showNotification('Please fill all required fields', 'error');
        return;
    }
    if (productId === '' && !imageFile) {
        showNotification('Product image is required for new products', 'error');
        return;
    }

    // DEBUG: Check variations array before saving
    var variationsCheck = window.productFormVariations || (typeof productFormVariations !== 'undefined' ? productFormVariations : []);
    
    var form = document.getElementById('productForm');
    var formData = new FormData(form);
    // CSRF: head meta (all admin pages) > #csrf-meta div (products) > form hidden input
    var metaEl = document.querySelector('meta[name="csrf-token"]');
    var csrfMeta = document.getElementById('csrf-meta');
    var csrfVal = (metaEl && metaEl.getAttribute('content')) || (csrfMeta && csrfMeta.getAttribute('data-csrf-token')) || (form && form.querySelector('input[name="csrf_token"]') && form.querySelector('input[name="csrf_token"]').value) || '';
    if (!csrfVal) {
        showNotification('Security token missing. Refresh the page and try again.', 'error');
        return;
    }
    formData.set('csrf_token', csrfVal);
    formData.set('is_active', document.getElementById('productIsActive').checked ? '1' : '0');
    var csrfHeaders = { 'X-CSRF-Token': csrfVal };
    if (imageFile) {
    }

    if (productId == '') {
        // Add product (token in body + header so API always receives it)
        fetch(adminUrl('api/products.php'), {
            method: 'POST',
            headers: csrfHeaders,
            body: formData
        })
        .then(function(response) {
            return response.text().then(function(text) {
                if (!response.ok) {
                    console.error('[CSRF DEBUG] Add product HTTP', response.status, 'Response:', text);
                    throw new Error('HTTP ' + response.status + ': ' + (text ? text.substring(0, 200) : 'No body'));
                }
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('[CSRF DEBUG] Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                var newProductId = data.product_id || data.id;
                
                // Save variations if any (check both local and global array)
                var variations = window.productFormVariations || (typeof productFormVariations !== 'undefined' ? productFormVariations : []);
                
                if (variations.length > 0 && newProductId) {
                    saveProductVariations(newProductId, function() {
                        showNotification('Product and variations added successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                        window.productFormVariations = [];
                        if (typeof productFormVariations !== 'undefined') productFormVariations = [];
                        loadProducts();
                    });
                } else {
                    // If no variations were provided, ensure a default variation exists
                    ensureDefaultVariation(newProductId, function() {
                        showNotification('Product added successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                        window.productFormVariations = [];
                        if (typeof productFormVariations !== 'undefined') productFormVariations = [];
                        loadProducts();
                    });
                }
            } else {
                console.error('[CSRF DEBUG] Add product failed:', data.message);
                showNotification(data.message || 'Failed to add product', 'error');
            }
        })
        .catch(function(err) {
            console.error('[CSRF DEBUG] Add product fetch error:', err);
            showNotification('Request failed: ' + (err.message || 'Unknown error'), 'error');
        });
    } else {
        formData.set('id', productId);
        formData.set('_method', 'PUT');
        fetch(adminUrl('api/products.php'), {
            method: 'POST',
            headers: csrfHeaders,
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                // Save variations if any (check both local and global array)
                var variations = window.productFormVariations || (typeof productFormVariations !== 'undefined' ? productFormVariations : []);
                
                if (variations.length > 0) {
                    saveProductVariations(productId, function() {
                        // After saving variations, ensure at least one default variation exists
                        ensureDefaultVariation(productId, function() {
                            showNotification('Product and variations updated successfully', 'success');
                            bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                            window.productFormVariations = [];
                            if (typeof productFormVariations !== 'undefined') productFormVariations = [];
                            loadProducts();
                        });
                    });
                } else {
                    // No variations provided - ensure default variation exists
                    ensureDefaultVariation(productId, function() {
                        showNotification('Product updated successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                        window.productFormVariations = [];
                        if (typeof productFormVariations !== 'undefined') productFormVariations = [];
                        loadProducts();
                    });
                }
            } else {
                showNotification(data.message || 'Failed to update product', 'error');
            }
        })
        .catch(function(error) {
            console.error('Error updating product:', error);
            showNotification('Error saving product: ' + error.message, 'error');
        });
    }
}

function saveProductVariations(productId, callback) {
    // Use global array if available
    var variationsToSave = window.productFormVariations || productFormVariations || [];
    
    
    // Note: Even if variationsToSave is empty, we still proceed to check existing variations
    // This allows us to delete all variations if needed, but ensureDefaultVariation will create a default
    
    // Get existing variations
    fetch(adminUrl('api/variations.php?product_id=' + productId))
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                return { success: true, variations: [] };
            }
        });
    })
    .then(function(data) {
        var existingVariations = data.success ? data.variations : [];
        var promises = [];
        
        // Save new variations from productFormVariations
        for (var i = 0; i < variationsToSave.length; i++) {
            var v = variationsToSave[i];
            var formData = new FormData();
            formData.append('product_id', productId);
            formData.append('variation_type', v.variation_type);
            formData.append('variation_value', v.variation_value);
            formData.append('price_modifier', v.price_modifier || 0);
            formData.append('inventory', v.inventory || 0);
            formData.append('display_order', v.display_order || i);
            formData.append('is_active', v.is_active || 1);
            
            
            if (v.id) {
                // Update existing - Use POST with _method=PUT for FormData compatibility
                formData.append('id', v.id);
                formData.append('_method', 'PUT');
                promises.push(fetch(adminUrl('api/variations.php'), { method: 'POST', body: formData }).then(function(res) {
                    if (!res.ok) {
                        throw new Error('Failed to update variation');
                    }
                    return res.text().then(function(text) {
                        try {
                            var data = JSON.parse(text);
                            return data;
                        } catch (e) {
                            console.error('Invalid JSON in update response:', text);
                            throw new Error('Invalid JSON response');
                        }
                    });
                }));
            } else {
                // Add new
                promises.push(fetch(adminUrl('api/variations.php'), { method: 'POST', body: formData }).then(function(res) {
                    if (!res.ok) {
                        throw new Error('Failed to add variation');
                    }
                    return res.text().then(function(text) {
                        try {
                            var data = JSON.parse(text);
                            return data;
                        } catch (e) {
                            console.error('Invalid JSON in add response:', text);
                            throw new Error('Invalid JSON response');
                        }
                    });
                }));
            }
        }
        
        // Delete variations that were removed
        for (var j = 0; j < existingVariations.length; j++) {
            var exists = false;
            for (var k = 0; k < variationsToSave.length; k++) {
                if (variationsToSave[k].id == existingVariations[j].id) {
                    exists = true;
                    break;
                }
            }
            if (!exists) {
                var deleteData = new FormData();
                deleteData.append('id', existingVariations[j].id);
                promises.push(fetch(adminUrl('api/variations.php'), { method: 'DELETE', body: deleteData }));
            }
        }
        
        
        Promise.all(promises).then(function(results) {
            if (callback) callback();
        }).catch(function(error) {
            console.error('Error saving variations:', error);
            console.error('Error details:', error.message, error.stack);
            if (callback) callback();
        });
    })
    .catch(function(error) {
        console.error('Error loading existing variations:', error);
        if (callback) callback();
    });
}

function ensureDefaultVariation(productId, callback) {
    // Check if product already has variations
    fetch(adminUrl('api/variations.php?product_id=' + productId))
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                return { success: true, variations: [] };
            }
        });
    })
    .then(function(data) {
        var existingVariations = data.success ? (data.variations || []) : [];
        
        // If no variations exist, create a default one
        if (existingVariations.length === 0) {
            // Get product inventory from the form or use 0 as default
            var productInventory = 0;
            var inventoryInput = document.getElementById('productInventory');
            if (inventoryInput) {
                productInventory = parseInt(inventoryInput.value) || 0;
            }
            
            var formData = new FormData();
            formData.append('product_id', productId);
            formData.append('variation_type', 'size');
            formData.append('variation_value', 'Default');
            formData.append('price_modifier', '0');
            formData.append('inventory', productInventory.toString());
            formData.append('display_order', '0');
            formData.append('is_active', '1');
            formData.append('is_default', '1');
            
            return fetch(adminUrl('api/variations.php'), {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Failed to create default variation');
                }
                return response.text().then(function(text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text);
                        return { success: false };
                    }
                });
            })
            .then(function(result) {
                if (result.success) {
                } else {
                    console.warn('Failed to create default variation:', result.message);
                }
                if (callback) callback();
            })
            .catch(function(error) {
                console.error('Error creating default variation:', error);
                if (callback) callback();
            });
        } else {
            // Variations already exist, nothing to do
            if (callback) callback();
        }
    })
    .catch(function(error) {
        console.error('Error checking variations:', error);
        if (callback) callback();
    });
}

// Variations Management
var currentVariations = [];
var currentProductId = null;

function openVariationsModal(productId) {
    currentProductId = productId;
    var product = products.find(function(p) { return p.id == productId; });
    if (product) {
        document.getElementById('variationProductId').value = productId;
        document.getElementById('variationProductName').textContent = product.name;
    }
    loadVariations(productId);
    var modal = new bootstrap.Modal(document.getElementById('variationsModal'));
    modal.show();
}

function loadVariations(productId) {
    document.getElementById('variationsList').innerHTML = '<p class="text-muted text-center py-4 mb-0">Loading variations…</p>';
    
    fetch(adminUrl('api/variations.php?product_id=' + productId))
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text().then(function(text) {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON response from server');
            }
        });
    })
    .then(function(data) {
        if (data.success) {
            currentVariations = data.variations || [];
            renderVariations();
        } else {
            document.getElementById('variationsList').innerHTML = '<p class="text-danger text-center py-4 mb-0">Error loading variations: ' + escapeHtml(data.message || 'Unknown error') + '</p>';
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        document.getElementById('variationsList').innerHTML = '<p class="text-danger text-center py-4 mb-0">Could not load variations.</p>';
    });
}

function renderVariations() {
    var container = document.getElementById('variationsList');

    if (currentVariations.length == 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-layer-group"></i></div><div class="empty-state-title">No variations yet</div><p class="empty-state-text mb-0">Add sizes or colours customers can choose from.</p></div>';
        return;
    }

    var html = '<div class="table-scroll"><table class="table table-hover mb-0">';
    html += '<thead><tr><th>Type</th><th>Value</th><th class="num">Price ±</th><th>Inventory</th><th class="num">Order</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>';

    for (var i = 0; i < currentVariations.length; i++) {
        var v = currentVariations[i];
        html += '<tr>';
        html += '<td><span class="chip">' + (v.variation_type == 'size' ? 'Size' : 'Colour') + '</span></td>';
        html += '<td class="cell-main">' + escapeHtml(v.variation_value) + (v.is_default == 1 ? ' <span class="muted" style="font-weight:400">· default</span>' : '') + '</td>';
        html += '<td class="num">' + formatPKRDelta(v.price_modifier) + '</td>';
        html += '<td>' + stockCell(v.inventory) + '</td>';
        html += '<td class="num">' + escapeHtml(v.display_order) + '</td>';
        html += '<td>' + statusPill(v.is_active == 1 ? 'active' : 'inactive') + '</td>';
        html += '<td class="text-end"><div class="actions">';
        html += iconButton('fa-pen', 'Edit', 'onclick="openEditVariationModal(' + parseInt(v.id, 10) + ')"');
        html += iconButton('fa-trash', 'Delete', 'onclick="deleteVariation(' + parseInt(v.id, 10) + ')"', 'danger');
        html += '</div></td>';
        html += '</tr>';
    }

    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function openAddVariationModal() {
    document.getElementById('variationFormTitle').textContent = 'Add variation';
    document.getElementById('variationForm').reset();
    document.getElementById('variationId').value = '';
    document.getElementById('variationProductIdForm').value = currentProductId;
    document.getElementById('variationPriceModifier').value = '0';
    document.getElementById('variationInventory').value = '0';
    document.getElementById('variationDisplayOrder').value = currentVariations.length.toString();
    document.getElementById('variationIsActive').value = '1';
    document.getElementById('variationType').disabled = false;
    var modal = new bootstrap.Modal(document.getElementById('variationFormModal'));
    modal.show();
}

function openEditVariationModal(variationId) {
    var variation = currentVariations.find(function(v) { return v.id == variationId; });
    if (!variation) {
        showNotification('Variation not found', 'error');
        return;
    }
    
    document.getElementById('variationFormTitle').textContent = 'Edit variation';
    document.getElementById('variationId').value = variation.id;
    document.getElementById('variationProductIdForm').value = variation.product_id;
    document.getElementById('variationType').value = variation.variation_type;
    document.getElementById('variationValue').value = variation.variation_value;
    document.getElementById('variationPriceModifier').value = variation.price_modifier || '0';
    document.getElementById('variationInventory').value = variation.inventory || '0';
    document.getElementById('variationDisplayOrder').value = variation.display_order || '0';
    document.getElementById('variationIsActive').value = variation.is_active || '1';
    
    // Disable type selection when editing
    document.getElementById('variationType').disabled = true;
    
    var modal = new bootstrap.Modal(document.getElementById('variationFormModal'));
    modal.show();
}

function deleteVariation(variationId) {
    showConfirm('Are you sure you want to delete this variation?', function(confirmed) {
        if (!confirmed) return;
        
        var formData = new FormData();
        formData.append('id', variationId);
        
        // Send DELETE request with ID in both URL and body for compatibility
        fetch(adminUrl('api/variations.php?id=' + variationId), {
            method: 'DELETE',
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                showNotification('Variation deleted successfully', 'success');
                loadVariations(currentProductId);
            } else {
                showNotification(data.message || 'Failed to delete variation', 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Error deleting variation', 'error');
        });
    });
}

function handleVariationSubmit() {
    var variationId = document.getElementById('variationId').value;
    var productId = document.getElementById('variationProductIdForm').value;
    var variationType = document.getElementById('variationType').value;
    var variationValue = document.getElementById('variationValue').value.trim();
    var priceModifier = document.getElementById('variationPriceModifier').value;
    var inventory = document.getElementById('variationInventory').value;
    var displayOrder = document.getElementById('variationDisplayOrder').value;
    var isActive = document.getElementById('variationIsActive').value;
    
    if (variationType == '' || variationValue == '') {
        showNotification('Please fill all required fields', 'error');
        return;
    }
    
    // Check if this is for product form (new product or editing)
    var variationFormModalEl = document.getElementById('variationFormModal');
    var isForProductForm = variationFormModalEl && variationFormModalEl.getAttribute('data-for-product-form') == 'true';
    
    if (isForProductForm && (productId == 'new' || productId == '')) {
        // Add to product form variations array (for new products)
        var variation = {
            id: null,
            variation_type: variationType,
            variation_value: variationValue,
            price_modifier: parseFloat(priceModifier) || 0,
            inventory: parseInt(inventory) || 0,
            display_order: parseInt(displayOrder) || (window.productFormVariations ? window.productFormVariations.length : 0),
            is_active: parseInt(isActive) || 1
        };
        
        // Add to both local and global arrays
        if (!window.productFormVariations) {
            window.productFormVariations = [];
        }
        window.productFormVariations.push(variation);
        productFormVariations = window.productFormVariations;
        
        // Refresh the display
        renderProductFormVariations();
        
        // Close only the variation modal, keep product modal open
        var variationModal = bootstrap.Modal.getInstance(variationFormModalEl);
        if (variationModal) {
            variationModal.hide();
        }
        
        // Remove the attribute
        variationFormModalEl.removeAttribute('data-for-product-form');
        
        // Show success message
        showNotification('Variation added to product form', 'success');
        return;
    }
    
    var formData = new FormData();
    formData.append('product_id', productId);
    formData.append('variation_type', variationType);
    formData.append('variation_value', variationValue);
    formData.append('price_modifier', priceModifier || '0');
    formData.append('inventory', inventory || '0');
    formData.append('display_order', displayOrder || '0');
    formData.append('is_active', isActive || '1');
    
    if (variationId == '') {
        // Add variation
        fetch(adminUrl('api/variations.php'), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                showNotification('Variation added successfully', 'success');
                
                var variationFormModalEl = document.getElementById('variationFormModal');
                var variationModal = bootstrap.Modal.getInstance(variationFormModalEl);
                
                if (isForProductForm) {
                    // Add the new variation to the productFormVariations array
                    var newVariation = {
                        id: data.variation_id || data.id || null,
                        variation_type: variationType,
                        variation_value: variationValue,
                        price_modifier: parseFloat(priceModifier) || 0,
                        inventory: parseInt(inventory) || 0,
                        display_order: parseInt(displayOrder) || 0,
                        is_active: parseInt(isActive) || 1
                    };
                    
                    
                    if (!window.productFormVariations) {
                        window.productFormVariations = [];
                    }
                    window.productFormVariations.push(newVariation);
                    productFormVariations = window.productFormVariations;
                    
                    
                    // Refresh the display immediately
                    renderProductFormVariations();
                    
                    // Also reload from server to ensure sync (for existing products)
                    if (productId && productId != 'new' && productId != '') {
                        setTimeout(function() {
                            loadProductVariationsForForm(productId);
                        }, 200);
                    }
                }
                
                if (variationModal) {
                    variationModal.hide();
                }
                
                var typeEl = document.getElementById('variationType');
                if (typeEl) typeEl.disabled = false;
                
                if (variationFormModalEl) {
                    variationFormModalEl.removeAttribute('data-for-product-form');
                }
                
                if (!isForProductForm) {
                    // Reload variations for variations modal
                    loadVariations(currentProductId);
                }
            } else {
                showNotification(data.message || 'Failed to add variation', 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Error adding variation', 'error');
        });
    } else {
        // Update variation - Use POST with _method=PUT for FormData compatibility
        formData.append('id', variationId);
        formData.append('_method', 'PUT');
        
        fetch(adminUrl('api/variations.php'), {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(function(text) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(function(data) {
            if (data.success) {
                showNotification('Variation updated successfully', 'success');
                
                var variationFormModalEl = document.getElementById('variationFormModal');
                var variationModal = bootstrap.Modal.getInstance(variationFormModalEl);
                
                if (variationModal) {
                    variationModal.hide();
                }
                
                var typeEl = document.getElementById('variationType');
                if (typeEl) typeEl.disabled = false;
                
                if (variationFormModalEl) {
                    variationFormModalEl.removeAttribute('data-for-product-form');
                }
                
                if (isForProductForm) {
                    // Reload variations for product form and keep product modal open
                    loadProductVariationsForForm(productId);
                } else {
                    loadVariations(currentProductId);
                }
            } else {
                showNotification(data.message || 'Failed to update variation', 'error');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            showNotification('Error updating variation', 'error');
        });
    }
}
