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
    
    document.getElementById('addProductBtn').onclick = function() {
        openAddProductModal();
    };
    
    document.getElementById('productForm').onsubmit = function(e) {
        e.preventDefault();
        handleProductSubmit();
    };
    
    document.getElementById('productsTable').onclick = function(e) {
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
    };
    
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
            console.log('Add Variation button clicked from product form');
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
    var select = document.getElementById('productCategory');
    select.innerHTML = '<option value="">Select Category</option>';
    for (var i = 0; i < categories.length; i++) {
        var option = document.createElement('option');
        option.value = categories[i].id;
        option.text = categories[i].name;
        select.appendChild(option);
    }
}

function loadProducts() {
    fetch(adminUrl('api/products.php'))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            products = data.products;
            renderProducts();
        }
    });
}

function renderProducts() {
    var tbody = document.getElementById('productsTable');
    document.getElementById('productCount').innerHTML = products.length;
    
    if (products.length == 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No products found</td></tr>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < products.length; i++) {
        var product = products[i];
        var stockStatus = '<span class="badge bg-success">In Stock</span>';
        if (product.inventory < 5) {
            stockStatus = '<span class="badge bg-danger">Low Stock</span>';
        } else if (product.inventory < 10) {
            stockStatus = '<span class="badge bg-warning">Medium</span>';
        }
        
        html += '<tr>';
        // Add cache buster to image URL to ensure updated images show
        var imageUrl = product.imagelink || '';
        if (imageUrl) {
            imageUrl += (imageUrl.indexOf('?') > -1 ? '&' : '?') + 't=' + Date.now();
        }
        // Use base64 GIF to avoid quote/syntax issues in onerror attribute
        var placeholderImg = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        html += '<td><img src="' + imageUrl + '" class="product-img-small" style="width:50px;height:50px;object-fit:cover;" onerror="this.onerror=null;this.src=\'' + placeholderImg + '\'"></td>';
        html += '<td>' + product.id + '</td>';
        html += '<td><strong>' + product.name + '</strong></td>';
        html += '<td><span class="badge bg-secondary">' + product.category_name + '</span></td>';
        html += '<td><strong>Rs. ' + parseFloat(product.price).toFixed(2) + '</strong></td>';
        html += '<td>' + product.inventory + '</td>';
        html += '<td>' + product.sold + '</td>';
        var statusBadge = '';
        if (product.is_active == 1 || product.is_active === true || product.is_active === '1') {
            statusBadge = '<span class="badge bg-success">Active</span>';
        } else {
            statusBadge = '<span class="badge bg-secondary">Inactive</span>';
        }
        html += '<td>' + statusBadge + '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-info me-1" data-manage-variations="' + product.id + '" title="Manage Variations" style="min-width: 110px;"><i class="fas fa-palette me-1"></i>Variations</button> ';
        html += '<button class="btn btn-sm btn-warning me-1" data-edit-product="' + product.id + '" title="Edit Product"><i class="fas fa-edit"></i></button> ';
        var toggleBtnClass = (product.is_active == 1 || product.is_active === true || product.is_active === '1') ? 'btn-secondary' : 'btn-success';
        var toggleBtnText = (product.is_active == 1 || product.is_active === true || product.is_active === '1') ? 'Deactivate' : 'Activate';
        var toggleBtnIcon = (product.is_active == 1 || product.is_active === true || product.is_active === '1') ? 'fa-toggle-off' : 'fa-toggle-on';
        html += '<button class="btn btn-sm ' + toggleBtnClass + ' me-1" data-toggle-status="' + product.id + '" data-current-status="' + (product.is_active || 0) + '" title="Toggle Status"><i class="fas ' + toggleBtnIcon + '"></i> ' + toggleBtnText + '</button> ';
        html += '<button class="btn btn-sm btn-danger" data-delete-product="' + product.id + '" title="Delete Product"><i class="fas fa-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

function openAddProductModal() {
    document.getElementById('productModalTitle').innerHTML = 'Add New Product';
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
        btn.innerHTML = '<i class="fas fa-plus"></i> Add Variation';
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
            document.getElementById('productModalTitle').innerHTML = 'Edit Product';
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
            console.log('Loaded variations for form:', productFormVariations.length, productFormVariations);
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
    var container = document.getElementById('productVariationsList');
    if (!container) {
        console.log('productVariationsList container not found');
        return;
    }
    
    // Use global reference
    var variations = window.productFormVariations || productFormVariations || [];
    
    console.log('Rendering variations:', variations.length);
    
    if (variations.length == 0) {
        container.innerHTML = '<p class="text-muted text-center mb-0 small">No variations added yet. Click "Add Variation" to add size or color options.</p>';
        return;
    }
    
    var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
    html += '<thead><tr><th>Type</th><th>Value</th><th>Price Modifier</th><th>Inventory</th><th>Actions</th></tr></thead><tbody>';
    
    for (var i = 0; i < variations.length; i++) {
        var v = variations[i];
        var typeBadge = v.variation_type == 'size' ? '<span class="badge bg-primary">Size</span>' : '<span class="badge bg-info">Color</span>';
        var priceMod = parseFloat(v.price_modifier) || 0;
        var priceText = priceMod == 0 ? 'Rs. 0.00' : (priceMod > 0 ? '+Rs. ' + priceMod.toFixed(2) : 'Rs. ' + priceMod.toFixed(2));
        
        html += '<tr>';
        html += '<td>' + typeBadge + '</td>';
        html += '<td><strong>' + v.variation_value + '</strong></td>';
        html += '<td>' + priceText + '</td>';
        html += '<td>' + (v.inventory || 'Product inventory') + '</td>';
        html += '<td><button type="button" class="btn btn-sm btn-danger" onclick="window.removeVariationFromProductForm(' + i + ')"><i class="fas fa-trash"></i></button></td>';
        html += '</tr>';
    }
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
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
    console.log('openAddVariationFromProductForm called');
    
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
        if (titleEl) titleEl.textContent = 'Add Variation';
        
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
            
            console.log('Modal shown successfully');
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
    console.log('=== PRODUCT SAVE DEBUG ===');
    console.log('Product ID:', productId);
    console.log('window.productFormVariations:', window.productFormVariations);
    console.log('productFormVariations (local):', typeof productFormVariations !== 'undefined' ? productFormVariations : 'undefined');
    var variationsCheck = window.productFormVariations || (typeof productFormVariations !== 'undefined' ? productFormVariations : []);
    console.log('Variations to save:', variationsCheck.length, variationsCheck);
    
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
        console.log('Image file attached:', imageFile.name, 'Size:', imageFile.size, 'Type:', imageFile.type);
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
                console.log('[CSRF DEBUG] Add product response: success', newProductId);
                console.log('Product saved successfully, ID:', newProductId);
                
                // Save variations if any (check both local and global array)
                var variations = window.productFormVariations || (typeof productFormVariations !== 'undefined' ? productFormVariations : []);
                console.log('Checking variations before save:', variations.length, variations);
                
                if (variations.length > 0 && newProductId) {
                    console.log('Calling saveProductVariations with product ID:', newProductId);
                    saveProductVariations(newProductId, function() {
                        console.log('Variations save completed');
                        showNotification('Product and variations added successfully', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('productModal')).hide();
                        window.productFormVariations = [];
                        if (typeof productFormVariations !== 'undefined') productFormVariations = [];
                        loadProducts();
                    });
                } else {
                    console.log('No variations provided - ensuring default variation exists');
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
    
    console.log('saveProductVariations called for product ID:', productId);
    console.log('Variations to save:', variationsToSave.length, variationsToSave);
    
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
            
            console.log('Saving variation:', v.variation_type, v.variation_value, 'ID:', v.id);
            
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
                            console.log('Variation updated:', data);
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
                            console.log('Variation added:', data);
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
        
        console.log('Total promises to execute:', promises.length);
        
        Promise.all(promises).then(function(results) {
            console.log('All variations saved successfully. Results:', results);
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
                    console.log('Default variation created successfully');
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
            console.log('Product already has variations, skipping default creation');
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
    document.getElementById('variationsList').innerHTML = '<p class="text-muted text-center">Loading variations...</p>';
    
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
            document.getElementById('variationsList').innerHTML = '<p class="text-danger text-center">Error loading variations: ' + (data.message || 'Unknown error') + '</p>';
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        document.getElementById('variationsList').innerHTML = '<p class="text-danger text-center">Error loading variations. Please check console for details.</p>';
    });
}

function renderVariations() {
    var container = document.getElementById('variationsList');
    
    if (currentVariations.length == 0) {
        container.innerHTML = '<p class="text-muted text-center">No variations added yet. Click "Add Variation" to get started.</p>';
        return;
    }
    
    var html = '<div class="table-responsive"><table class="table table-sm table-hover">';
    html += '<thead><tr><th>Type</th><th>Value</th><th>Price Modifier</th><th>Inventory</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
    
    for (var i = 0; i < currentVariations.length; i++) {
        var v = currentVariations[i];
        var typeBadge = v.variation_type == 'size' ? '<span class="badge bg-primary">Size</span>' : '<span class="badge bg-info">Color</span>';
        var statusBadge = v.is_active == 1 ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>';
        var priceMod = parseFloat(v.price_modifier) || 0;
        var priceText = priceMod == 0 ? 'Rs. 0.00' : (priceMod > 0 ? '+Rs. ' + priceMod.toFixed(2) : 'Rs. ' + priceMod.toFixed(2));
        
        html += '<tr>';
        html += '<td>' + typeBadge + '</td>';
        html += '<td><strong>' + v.variation_value + '</strong></td>';
        html += '<td>' + priceText + '</td>';
        html += '<td>' + v.inventory + '</td>';
        html += '<td>' + v.display_order + '</td>';
        html += '<td>' + statusBadge + '</td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-warning me-1" onclick="openEditVariationModal(' + v.id + ')" title="Edit"><i class="fas fa-edit"></i></button> ';
        html += '<button class="btn btn-sm btn-danger" onclick="deleteVariation(' + v.id + ')" title="Delete"><i class="fas fa-trash"></i></button>';
        html += '</td>';
        html += '</tr>';
    }
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
}

function openAddVariationModal() {
    document.getElementById('variationFormTitle').textContent = 'Add Variation';
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
    
    document.getElementById('variationFormTitle').textContent = 'Edit Variation';
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
                    
                    console.log('Adding variation to array:', newVariation);
                    
                    if (!window.productFormVariations) {
                        window.productFormVariations = [];
                    }
                    window.productFormVariations.push(newVariation);
                    productFormVariations = window.productFormVariations;
                    
                    console.log('Variations array now has', window.productFormVariations.length, 'items');
                    
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
