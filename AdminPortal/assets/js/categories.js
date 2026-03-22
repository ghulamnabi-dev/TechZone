var categories = [];

// Safer DOM-ready hook so events are always attached,
// even if other scripts touch window.onload
document.addEventListener('DOMContentLoaded', function () {
    loadCategories();

    var addBtn = document.getElementById('addCategoryBtn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            openAddCategoryModal();
        });
    }

    var form = document.getElementById('categoryForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            handleCategorySubmit();
        });
    }

    var grid = document.getElementById('categoriesGrid');
    if (grid) {
        grid.addEventListener('click', function (e) {
            if (e.target.closest('[data-edit-category]')) {
                var id = e.target.closest('[data-edit-category]').getAttribute('data-edit-category');
                openEditCategoryModal(id);
            }
            if (e.target.closest('[data-delete-category]')) {
                var idDel = e.target.closest('[data-delete-category]').getAttribute('data-delete-category');
                deleteCategory(idDel);
            }
        });
    }

    var fileInput = document.getElementById('categoryImage');
    var clearBtn = document.getElementById('clearImageBtn');

    // Image preview — use URL.createObjectURL for instant, reliable preview
    if (fileInput) {
        fileInput.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            var url = URL.createObjectURL(file);
            var preview = document.getElementById('categoryImgPreview');
            if (preview) {
                preview.innerHTML = '<img src=\"' + url + '\" alt=\"Preview\" style=\"width:100%;height:100%;object-fit:cover;\">';
            }
            if (clearBtn) {
                clearBtn.classList.remove('d-none');
            }
        });
    }

    // Clear image button
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            resetImagePreview();
        });
    }
});

function resetImagePreview(currentImageUrl) {
    var preview = document.getElementById('categoryImgPreview');
    document.getElementById('categoryImage').value = '';
    document.getElementById('clearImageBtn').classList.add('d-none');
    if (currentImageUrl) {
        preview.innerHTML = '<img src="' + currentImageUrl + '" alt="Current image">';
        document.getElementById('clearImageBtn').classList.remove('d-none');
    } else {
        preview.innerHTML = '<i class="fas fa-image fa-2x text-muted"></i><span class="text-muted small d-block mt-1">No image selected</span>';
    }
}

function loadCategories() {
    fetch(adminUrl('api/categories.php'))
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            categories = data.categories;
            renderCategories();
        }
    });
}

function getCategoryImageUrl(image) {
    if (!image) return null;
    return adminUrl('uploads/' + encodeURIComponent(image));
}

function renderCategories() {
    var grid = document.getElementById('categoriesGrid');

    if (categories.length == 0) {
        grid.innerHTML = '<div class="col-12"><div class="alert alert-info">No categories found.</div></div>';
        return;
    }

    var html = '';
    for (var i = 0; i < categories.length; i++) {
        var cat = categories[i];
        var imgUrl = getCategoryImageUrl(cat.image);
        var imgHtml = imgUrl
            ? '<img src="' + imgUrl + '" class="category-card-thumb" alt="' + cat.name + '">'
            : '<div class="category-card-thumb category-card-img-placeholder"><i class="fas fa-tags"></i></div>';

        html += '<div class="col-md-6 col-lg-4 mb-3">';
        html += '<div class="category-card d-flex align-items-stretch">';
        html += '<div class="category-card-body flex-grow-1">';
        html += '<div class="category-card-name">' + cat.name + '</div>';
        html += '<div class="category-card-desc">' + (cat.description || 'No description') + '</div>';
        html += '<div class="category-card-footer">';
        html += '<span class="badge bg-secondary">' + cat.product_count + ' products</span>';
        html += '<div class="d-flex gap-1">';
        html += '<button class="btn btn-sm btn-outline-primary" data-edit-category="' + cat.id + '"><i class="fas fa-pencil"></i></button>';
        html += '<button class="btn btn-sm btn-outline-danger" data-delete-category="' + cat.id + '"><i class="fas fa-trash"></i></button>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        html += imgHtml;
        html += '</div>';
        html += '</div>';
    }
    grid.innerHTML = html;
}

function openAddCategoryModal() {
    document.getElementById('categoryModalTitle').textContent = 'Add New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    resetImagePreview();
    var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    modal.show();
}

function openEditCategoryModal(categoryId) {
    for (var i = 0; i < categories.length; i++) {
        if (categories[i].id == categoryId) {
            var cat = categories[i];
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            document.getElementById('categoryId').value = cat.id;
            document.getElementById('categoryName').value = cat.name;
            document.getElementById('categoryDescription').value = cat.description || '';

            var imgUrl = getCategoryImageUrl(cat.image);
            resetImagePreview(imgUrl);

            var modal = new bootstrap.Modal(document.getElementById('categoryModal'));
            modal.show();
            break;
        }
    }
}

function deleteCategory(categoryId) {
    showConfirm('Are you sure you want to delete this category?', function(confirmed) {
        if (!confirmed) return;

        fetch(adminUrl('api/categories.php?id=' + categoryId), { method: 'DELETE' })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                showNotification('Category deleted successfully', 'success');
                loadCategories();
            } else {
                showNotification(data.message || 'Failed to delete category', 'error');
            }
        });
    });
}

function handleCategorySubmit() {
    var categoryId = document.getElementById('categoryId').value;
    var name = document.getElementById('categoryName').value.trim();
    var description = document.getElementById('categoryDescription').value.trim();
    var imageFile = document.getElementById('categoryImage').files[0];

    if (!name) {
        showNotification('Category name is required', 'error');
        return;
    }

    var formData = new FormData();
    formData.append('name', name);
    formData.append('description', description);
    if (imageFile) {
        formData.append('image', imageFile);
    }

    var url = adminUrl('api/categories.php');
    var method = 'POST';

    if (categoryId) {
        formData.append('_method', 'PUT');
        formData.append('id', categoryId);
    }

    // Immediate feedback that the click worked
    showNotification('Saving category…', 'success');

    fetch(url, { method: method, body: formData })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            if (data.success) {
                showNotification(data.message || 'Category saved successfully', 'success');
                var modalEl = document.getElementById('categoryModal');
                if (modalEl && window.bootstrap && bootstrap.Modal.getInstance(modalEl)) {
                    bootstrap.Modal.getInstance(modalEl).hide();
                }
                loadCategories();
            } else {
                showNotification(data.message || 'Failed to save category', 'error');
            }
        })
        .catch(function (err) {
            showNotification('An error occurred while saving the category.', 'error');
        });
}
