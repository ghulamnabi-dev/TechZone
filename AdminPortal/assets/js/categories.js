var categories = [];
var categoriesGrid = null;

// Safer DOM-ready hook so events are always attached,
// even if other scripts touch window.onload
document.addEventListener('DOMContentLoaded', function () {
    categoriesGrid = createAdminGrid(document.getElementById('categoriesGrid'), {
        storageKey: 'tz.grid.categories.v1',
        noun: ['category', 'categories'],
        emptyText: 'No categories match these filters.',
        exportName: 'categories',
        toolbar: { search: '#categorySearch', count: '#categoryCount', exportBtn: '#categoriesExport', resetBtn: '#categoriesReset' },
        columns: [
            { field: 'name', headerName: 'Category', kind: 'thumb', width: 280, mobileWidth: 170,
              cellRendererParams: { image: function (c) { return getCategoryImageUrl(c.image); }, icon: 'fa-tags', sub: function (c) { return '#' + c.id; } } },
            { field: 'description', headerName: 'Description', kind: 'muted', flex: 1, minWidth: 240, tooltipField: 'description' },
            { field: 'product_count', headerName: 'Products', kind: 'number', width: 120 },
            { colId: 'updated', headerName: 'Updated', kind: 'date', width: 140,
              valueGetter: function (p) { return p.data.updated_at || p.data.created_at; } }
        ],
        rowActions: function (cat) {
            var id = parseInt(cat.id, 10);
            return [
                { icon: 'fa-pen', label: 'Edit', attrs: 'data-edit-category="' + id + '"' },
                { icon: 'fa-trash', label: 'Delete', attrs: 'data-delete-category="' + id + '"', tone: 'danger' }
            ];
        },
        onRowOpen: function (cat) { openEditCategoryModal(cat.id); }
    });
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
        preview.innerHTML = '<img src="' + escapeHtml(currentImageUrl) + '" alt="Current image">';
        document.getElementById('clearImageBtn').classList.remove('d-none');
    } else {
        preview.innerHTML = '<i class="fas fa-image fa-2x"></i><span class="small d-block mt-2">Click to choose image</span>';
    }
}

function loadCategories() {
    fetch(adminUrl('api/categories.php'))
    .then(function(response) { return response.json(); })
    .then(function(data) {
        categories = data.success ? (data.categories || []) : [];
        renderCategories();
    });
}

function getCategoryImageUrl(image) {
    if (!image) return null;
    return adminUrl('uploads/' + encodeURIComponent(image));
}

function renderCategories() {
    if (categoriesGrid) categoriesGrid.setRows(categories);
}

function openAddCategoryModal() {
    document.getElementById('categoryModalTitle').textContent = 'Add category';
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
            document.getElementById('categoryModalTitle').textContent = 'Edit category';
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
