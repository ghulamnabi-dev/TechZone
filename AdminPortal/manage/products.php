<?php
$pageTitle = 'Products';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../../core/security.php';
$csrfToken = csrf_token();
?>
<!-- CSRF token for JS (product form submit) -->
<div id="csrf-meta" data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true" style="display:none"></div>

<div class="panel">
    <div class="panel-head">
        <h2 class="panel-title">All products <span class="count-chip" id="productCount">0</span></h2>
    </div>
    <div class="toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="productSearch" placeholder="Search name or SKU…" aria-label="Search products">
        </label>
        <select class="form-select" id="productCategoryFilter" aria-label="Filter by category">
            <option value="">All categories</option>
        </select>
        <select class="form-select" id="productStatusFilter" aria-label="Filter by status">
            <option value="">Any status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
            <option value="low">Low stock (≤ 5)</option>
            <option value="out">Out of stock</option>
        </select>
        <div class="end">
            <button class="btn btn-primary" id="addProductBtn" type="button">
                <i class="fas fa-plus"></i> Add product
            </button>
        </div>
    </div>
    <div class="table-scroll">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="num">Price</th>
                    <th>Inventory</th>
                    <th class="num">Sold</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="productsTable">
                <tr class="loading-row"><td colspan="7">Loading…</td></tr>
            </tbody>
        </table>
    </div>
    <div class="table-foot" id="productsPager"></div>
</div>

<!-- Variations Modal -->
<div class="modal fade" id="variationsModal" tabindex="-1" aria-labelledby="variationsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="variationsModalTitle">Variations</h5>
                    <div class="panel-sub" id="variationProductName"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <input type="hidden" id="variationProductId">
                <div id="variationsList">
                    <p class="text-muted text-center py-4 mb-0">Loading variations…</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="addVariationBtn"><i class="fas fa-plus"></i> Add variation</button>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Variation Modal -->
<div class="modal fade" id="variationFormModal" tabindex="-1" aria-labelledby="variationFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="variationFormTitle">Add variation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="variationForm">
                    <input type="hidden" id="variationId">
                    <input type="hidden" id="variationProductIdForm">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label" for="variationType">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="variationType" required>
                                <option value="">Select type</option>
                                <option value="size">Size</option>
                                <option value="color">Color</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="variationValue">Value <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="variationValue" placeholder="e.g. Large, Red, 32GB" required>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="variationPriceModifier">Price modifier (Rs)</label>
                            <input type="number" class="form-control" id="variationPriceModifier" value="0" step="0.01">
                            <div class="form-text">Added to the base price; negative for a discount.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="variationInventory">Inventory</label>
                            <input type="number" class="form-control" id="variationInventory" value="0" min="0">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="variationDisplayOrder">Display order</label>
                            <input type="number" class="form-control" id="variationDisplayOrder" value="0" min="0">
                            <div class="form-text">0 shows first.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="variationIsActive">Status</label>
                            <select class="form-select" id="variationIsActive">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="variationForm" class="btn btn-primary">Save variation</button>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalTitle">Add product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="productForm" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" id="productId" name="id" value="">
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="form-section">
                                <h6 class="form-section-title">Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label" for="productName">Product name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="productName" name="name" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" for="productCategory">Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="productCategory" name="category_id" required>
                                            <option value="">Select category</option>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label" for="productDescription">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="productDescription" name="description" rows="4" required></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h6 class="form-section-title">Pricing &amp; stock</h6>
                                <div class="row g-3">
                                    <div class="col-sm-4">
                                        <label class="form-label" for="productPrice">Price (Rs) <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="productPrice" name="price" required min="0" step="0.01">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="productInventory">Inventory <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="productInventory" name="inventory" required min="0">
                                    </div>
                                    <div class="col-sm-4">
                                        <label class="form-label" for="productSold">Units sold</label>
                                        <input type="number" class="form-control" id="productSold" name="sold" value="0" min="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="form-section">
                                <h6 class="form-section-title">Image</h6>
                                <div class="d-flex gap-3 align-items-start">
                                    <div id="currentImage" style="display: none;">
                                        <img id="currentImagePreview" src="" alt="Current product image" class="img-preview">
                                    </div>
                                    <div class="flex-grow-1">
                                        <label class="form-label" for="productImage">Upload <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" id="productImage" name="image" accept="image/*">
                                        <div class="form-text">Required for new products. JPG, PNG or WebP.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-section">
                                <h6 class="form-section-title">Visibility</h6>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="productIsActive" name="is_active" value="1" checked>
                                    <label class="form-check-label" for="productIsActive">Active on storefront</label>
                                </div>
                                <div class="form-text">Inactive products are hidden from the marketing site.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Variations Section -->
                    <div class="form-section mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="form-section-title mb-0">Variations</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleVariationFormBtn" onclick="toggleVariationForm(); return false;">
                                <i class="fas fa-plus"></i> Add variation
                            </button>
                        </div>

                        <!-- Inline Variation Form (Hidden by default) -->
                        <div id="inlineVariationForm" class="subtle-box mb-3" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label" for="inlineVariationType">Type <span class="text-danger">*</span></label>
                                    <select class="form-select" id="inlineVariationType">
                                        <option value="">Select type</option>
                                        <option value="size">Size</option>
                                        <option value="color">Color</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="inlineVariationValue">Value <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="inlineVariationValue" placeholder="e.g. Large, Red, 32GB">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="inlineVariationPriceModifier">Price ± (Rs)</label>
                                    <input type="number" class="form-control" id="inlineVariationPriceModifier" value="0" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="inlineVariationInventory">Inventory</label>
                                    <input type="number" class="form-control" id="inlineVariationInventory" value="0" min="0">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label" for="inlineVariationDisplayOrder">Order</label>
                                    <input type="number" class="form-control" id="inlineVariationDisplayOrder" value="0" min="0">
                                </div>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                                <div class="form-check mb-0 me-auto">
                                    <input class="form-check-input" type="checkbox" id="inlineVariationIsDefault">
                                    <label class="form-check-label" for="inlineVariationIsDefault">Default variant</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleVariationForm(); return false;">Cancel</button>
                                <button type="button" class="btn btn-sm btn-primary" onclick="addVariationInline(); return false;">
                                    <i class="fas fa-check"></i> Add to list
                                </button>
                            </div>
                        </div>

                        <!-- Variations List -->
                        <div id="productVariationsList" class="subtle-box p-0" style="overflow:hidden">
                            <p class="text-muted text-center mb-0 py-3 small">No variations yet. Add size or colour options customers can pick from.</p>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="productForm" class="btn btn-primary">Save product</button>
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
        btn.innerHTML = '<i class="fas fa-xmark"></i> Close';
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
        btn.innerHTML = '<i class="fas fa-plus"></i> Add variation';
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
    
    
    if (variations.length == 0) {
        container.innerHTML = '<p class="text-muted text-center mb-0 py-3 small">No variations yet. Add size or colour options customers can pick from.</p>';
        return;
    }

    var html = '<div class="table-scroll"><table class="table mini-table mb-0">';
    html += '<thead><tr><th>Type</th><th>Value</th><th class="num">Price ±</th><th class="num">Inventory</th><th>Default</th><th class="text-end"></th></tr></thead><tbody>';

    for (var i = 0; i < variations.length; i++) {
        var v = variations[i];
        var isDefault = (v.is_default == 1 || v.is_default === true || v.is_default == '1');
        html += '<tr>';
        html += '<td><span class="chip">' + (v.variation_type == 'size' ? 'Size' : 'Colour') + '</span></td>';
        html += '<td class="cell-main">' + escapeHtml(v.variation_value || '') + '</td>';
        html += '<td class="num">' + formatPKRDelta(v.price_modifier) + '</td>';
        html += '<td class="num">' + formatNumber(v.inventory || 0) + '</td>';
        html += '<td>' + (isDefault ? statusPill('active', 'Default') : '<span class="muted">—</span>') + '</td>';
        html += '<td class="text-end">' + iconButton('fa-trash', 'Remove variation', 'onclick="removeVariationInline(' + i + ', ' + (v.id ? parseInt(v.id, 10) : 'null') + ')"', 'danger') + '</td>';
        html += '</tr>';
    }

    html += '</tbody></table></div>';
    container.innerHTML = html;
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

