<?php
$pageTitle = 'Product Management';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../core/security.php';
$csrfToken = csrf_token();
?>
<!-- CSRF token for JS (product form submit) -->
<div id="csrf-meta" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true" style="display:none"></div>
<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">All Products (<span id="productCount">0</span>)</h5>
        <button class="btn btn-primary" id="addProductBtn">
            <i class="fas fa-plus"></i> Add New Product
        </button>
    </div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Inventory</th>
                    <th>Sold</th>
                    <th>Active Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="productsTable">
                <tr><td colspan="9" class="text-center">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Variations Modal -->
<div class="modal fade" id="variationsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="variationsModalTitle">Manage Product Variations</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="variationProductId">
                <div class="mb-3">
                    <h6>Product: <span id="variationProductName"></span></h6>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Variations</h6>
                    <button type="button" class="btn btn-sm btn-primary" id="addVariationBtn">
                        <i class="fas fa-plus"></i> Add Variation
                    </button>
                </div>
                
                <div id="variationsList" class="mb-3">
                    <p class="text-muted text-center">Loading variations...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Variation Modal -->
<div class="modal fade" id="variationFormModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="variationFormTitle">Add Variation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="variationForm">
                    <input type="hidden" id="variationId">
                    <input type="hidden" id="variationProductIdForm">
                    
                    <div class="mb-3">
                        <label class="form-label">Variation Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="variationType" required>
                            <option value="">Select Type</option>
                            <option value="size">Size</option>
                            <option value="color">Color</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Variation Value <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="variationValue" placeholder="e.g., Large, Red, 32GB" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Price Modifier (Rs.)</label>
                            <input type="number" class="form-control" id="variationPriceModifier" value="0" step="0.01">
                            <small class="text-muted">Additional cost (can be negative for discount)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Inventory</label>
                            <input type="number" class="form-control" id="variationInventory" value="0" min="0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="variationDisplayOrder" value="0" min="0">
                            <small class="text-muted">Controls the order variations appear (0 = first, 1 = second, etc.)</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="variationIsActive">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Save Variation</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="productForm" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" id="productId" name="id" value="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="productName" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="productCategory" name="category_id" required>
                                <option value="">Select Category</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="productPrice" name="price" required min="0" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Inventory Stock <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="productInventory" name="inventory" required min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sold Count</label>
                            <input type="number" class="form-control" id="productSold" name="sold" value="0" min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="productIsActive" name="is_active" value="1" checked>
                            <label class="form-check-label" for="productIsActive">
                                <strong>Active</strong> (Product will be visible on marketing site)
                            </label>
                        </div>
                        <small class="text-muted">Inactive products will not appear on the marketing site</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="productDescription" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Image <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                        <small class="text-muted">Upload a product image (required for new products)</small>
                        <div id="currentImage" class="mt-2" style="display: none;">
                            <img id="currentImagePreview" src="" alt="Current Image" style="max-width: 100px; max-height: 100px;">
                        </div>
                    </div>
                    
                    <!-- Product Variations Section -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Product Variations</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleVariationFormBtn" onclick="toggleVariationForm(); return false;">
                                <i class="fas fa-plus"></i> Add Variation
                            </button>
                        </div>
                        
                        <!-- Inline Variation Form (Hidden by default) -->
                        <div id="inlineVariationForm" class="border rounded p-3 mb-3" style="display: none; background-color: #f8f9fa;">
                            <h6 class="mb-3">Add New Variation</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Variation Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="inlineVariationType">
                                        <option value="">Select Type</option>
                                        <option value="size">Size</option>
                                        <option value="color">Color</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Variation Value <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="inlineVariationValue" placeholder="e.g., Large, Red, 32GB">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Price Modifier (Rs.)</label>
                                    <input type="number" class="form-control" id="inlineVariationPriceModifier" value="0" step="0.01">
                                    <small class="text-muted">Additional cost (can be negative)</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Inventory</label>
                                    <input type="number" class="form-control" id="inlineVariationInventory" value="0" min="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Display Order</label>
                                    <input type="number" class="form-control" id="inlineVariationDisplayOrder" value="0" min="0">
                                    <small class="text-muted">Lower = appears first</small>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="inlineVariationIsDefault">
                                    <label class="form-check-label" for="inlineVariationIsDefault">
                                        Set as Base/Default Variant
                                    </label>
                                    <small class="text-muted d-block">This will be the default selection for customers</small>
                                </div>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-primary" onclick="addVariationInline(); return false;">
                                    <i class="fas fa-save"></i> Add to List
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="toggleVariationForm(); return false;">
                                    Cancel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Variations List -->
                        <div id="productVariationsList" class="border rounded p-3" style="min-height: 100px; background-color: #f8f9fa;">
                            <p class="text-muted text-center mb-0 small">No variations added yet. Click "Add Variation" to add size or color options.</p>
                        </div>
                        <small class="text-muted">Add size and color options for this product. Variations will be shown to customers on the product page.</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">Save Product</button>
                </form>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?php echo htmlspecialchars(admin_url('assets/js/products.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/products.js'); ?>"></script>
<script>
// Inline Variation Form Functions
function toggleVariationForm() {
    var form = document.getElementById('inlineVariationForm');
    var btn = document.getElementById('toggleVariationFormBtn');
    
    if (form.style.display === 'none' || !form.style.display) {
        form.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-times"></i> Cancel';
        btn.classList.remove('btn-outline-primary');
        btn.classList.add('btn-secondary');
        
        // Reset form
        document.getElementById('inlineVariationType').value = '';
        document.getElementById('inlineVariationValue').value = '';
        document.getElementById('inlineVariationPriceModifier').value = '0';
        document.getElementById('inlineVariationInventory').value = '0';
        document.getElementById('inlineVariationIsDefault').checked = false;
        var variationsCount = window.productFormVariations ? window.productFormVariations.length : 0;
        document.getElementById('inlineVariationDisplayOrder').value = variationsCount.toString();
    } else {
        form.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-plus"></i> Add Variation';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-outline-primary');
    }
}

function addVariationInline() {
    var type = document.getElementById('inlineVariationType').value;
    var value = document.getElementById('inlineVariationValue').value.trim();
    
    // Validate required fields manually
    if (!type || type == '') {
        if (typeof showNotification === 'function') {
            showNotification('Please select variation type', 'error');
        } else {
            alert('Please select variation type');
        }
        return;
    }
    
    if (!value || value == '') {
        if (typeof showNotification === 'function') {
            showNotification('Please enter variation value', 'error');
        } else {
            alert('Please enter variation value');
        }
        return;
    }
    var priceModifier = parseFloat(document.getElementById('inlineVariationPriceModifier').value) || 0;
    var inventory = parseInt(document.getElementById('inlineVariationInventory').value) || 0;
    var displayOrder = parseInt(document.getElementById('inlineVariationDisplayOrder').value) || 0;
    var isDefault = document.getElementById('inlineVariationIsDefault').checked ? 1 : 0;
    var productId = document.getElementById('productId').value;
    
    if (!type || !value) {
        if (typeof showNotification === 'function') {
            showNotification('Please fill Variation Type and Value', 'error');
        } else {
            alert('Please fill Variation Type and Value');
        }
        return;
    }
    
    // If product ID exists (editing existing product), save directly to database
    if (productId && productId != '') {
        var formData = new FormData();
        formData.append('product_id', productId);
        formData.append('variation_type', type);
        formData.append('variation_value', value);
        formData.append('price_modifier', priceModifier);
        formData.append('inventory', inventory);
        formData.append('display_order', displayOrder);
        formData.append('is_active', 1);
        formData.append('is_default', isDefault);
        
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
                // Reload variations from database
                if (typeof loadProductVariationsForForm === 'function') {
                    loadProductVariationsForForm(productId);
                }
                toggleVariationForm();
                if (typeof showNotification === 'function') {
                    showNotification('Variation saved successfully', 'success');
                }
            } else {
                if (typeof showNotification === 'function') {
                    showNotification(data.message || 'Failed to save variation', 'error');
                } else {
                    alert(data.message || 'Failed to save variation');
                }
            }
        })
        .catch(function(error) {
            console.error('Error saving variation:', error);
            if (typeof showNotification === 'function') {
                showNotification('Error saving variation: ' + error.message, 'error');
            } else {
                alert('Error saving variation: ' + error.message);
            }
        });
    } else {
        // New product - add to array for later
        if (!window.productFormVariations) {
            window.productFormVariations = [];
        }
        
        var variation = {
            id: null,
            variation_type: type,
            variation_value: value,
            price_modifier: priceModifier,
            inventory: inventory,
            display_order: displayOrder,
            is_active: 1,
            is_default: isDefault
        };
        
        window.productFormVariations.push(variation);
        if (typeof productFormVariations !== 'undefined') {
            productFormVariations = window.productFormVariations;
        }
        
        renderProductFormVariationsInline();
        toggleVariationForm();
        
        if (typeof showNotification === 'function') {
            showNotification('Variation added to list', 'success');
        }
    }
}

function renderProductFormVariationsInline() {
    var container = document.getElementById('productVariationsList');
    if (!container) {
        console.error('productVariationsList container not found!');
        return;
    }
    
    // Use global reference
    var variations = window.productFormVariations || [];
    
    console.log('Rendering variations inline:', variations.length);
    
    if (variations.length == 0) {
        container.innerHTML = '<p class="text-muted text-center mb-0 small">No variations added yet. Click "Add Variation" to add size or color options.</p>';
        return;
    }
    
    var html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">';
    html += '<thead><tr><th>Type</th><th>Value</th><th>Price Modifier</th><th>Inventory</th><th>Default</th><th>Actions</th></tr></thead><tbody>';
    
    for (var i = 0; i < variations.length; i++) {
        var v = variations[i];
        var typeBadge = v.variation_type == 'size' ? '<span class="badge bg-primary">Size</span>' : '<span class="badge bg-info">Color</span>';
        var priceMod = parseFloat(v.price_modifier) || 0;
        var priceText = priceMod == 0 ? 'Rs. 0.00' : (priceMod > 0 ? '+Rs. ' + priceMod.toFixed(2) : 'Rs. ' + priceMod.toFixed(2));
        var defaultBadge = (v.is_default == 1 || v.is_default == true || v.is_default == '1') ? '<span class="badge bg-success"><i class="fas fa-star"></i> Default</span>' : '<span class="text-muted">-</span>';
        
        html += '<tr>';
        html += '<td>' + typeBadge + '</td>';
        html += '<td><strong>' + (v.variation_value || '') + '</strong></td>';
        html += '<td>' + priceText + '</td>';
        html += '<td>' + (v.inventory || 0) + '</td>';
        html += '<td>' + defaultBadge + '</td>';
        html += '<td><button type="button" class="btn btn-sm btn-danger" onclick="removeVariationInline(' + i + ', ' + (v.id ? v.id : 'null') + ')"><i class="fas fa-trash"></i></button></td>';
        html += '</tr>';
    }
    
    html += '</tbody></table></div>';
    container.innerHTML = html;
    console.log('Variations rendered successfully. Count:', variations.length);
}

function removeVariationInline(index, variationId) {
    var productId = document.getElementById('productId').value;
    
    // If variation has ID (from database) and product exists, delete from database
    if (variationId && productId && productId != '') {
        if (typeof showConfirm === 'function') {
            showConfirm('Remove this variation?', function(confirmed) {
                if (confirmed) {
                    var formData = new FormData();
                    formData.append('id', variationId);
                    
                    // Send DELETE request with ID in URL for compatibility
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
                            if (typeof loadProductVariationsForForm === 'function') {
                                loadProductVariationsForForm(productId);
                            }
                            if (typeof showNotification === 'function') {
                                showNotification('Variation removed successfully', 'success');
                            }
                        } else {
                            if (typeof showNotification === 'function') {
                                showNotification(data.message || 'Failed to remove variation', 'error');
                            }
                        }
                    })
                    .catch(function(error) {
                        console.error('Error removing variation:', error);
                        if (typeof showNotification === 'function') {
                            showNotification('Error removing variation', 'error');
                        }
                    });
                }
            });
        } else {
            if (confirm('Remove this variation?')) {
                var formData = new FormData();
                formData.append('id', variationId);
                
                // Send DELETE request with ID in URL for compatibility
                fetch(adminUrl('api/variations.php?id=' + variationId), {
                    method: 'DELETE',
                    body: formData
                })
                .then(function(response) {
                    return response.text().then(function(text) {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            return { success: false, message: 'Invalid response' };
                        }
                    });
                })
                .then(function(data) {
                    if (data.success && typeof loadProductVariationsForForm === 'function') {
                        loadProductVariationsForForm(productId);
                    }
                });
            }
        }
    } else {
        // New product - remove from array
        if (!window.productFormVariations) {
            return;
        }
        
        if (typeof showConfirm === 'function') {
            showConfirm('Remove this variation from the list?', function(confirmed) {
                if (confirmed) {
                    window.productFormVariations.splice(index, 1);
                    if (typeof productFormVariations !== 'undefined') {
                        productFormVariations = window.productFormVariations;
                    }
                    renderProductFormVariationsInline();
                }
            });
        } else {
            if (confirm('Remove this variation from the list?')) {
                window.productFormVariations.splice(index, 1);
                if (typeof productFormVariations !== 'undefined') {
                    productFormVariations = window.productFormVariations;
                }
                renderProductFormVariationsInline();
            }
        }
    }
}

// Make functions globally accessible
window.renderProductFormVariationsInline = renderProductFormVariationsInline;
window.removeVariationInline = removeVariationInline;

// Make functions globally accessible
window.toggleVariationForm = toggleVariationForm;
window.addVariationInline = addVariationInline;

// Old modal function (removed - keeping for compatibility but not used)
window.openAddVariationFromProductForm = function() {
    console.log('openAddVariationFromProductForm called from window');
    
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
        if (displayOrderEl) displayOrderEl.value = (window.productFormVariations ? window.productFormVariations.length : 0).toString();
        
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
            alert('Bootstrap is not loaded. Please refresh the page.');
        }
    } catch (error) {
        console.error('Error in openAddVariationFromProductForm:', error);
        alert('Error opening variation form: ' + error.message);
    }
};

</script>

