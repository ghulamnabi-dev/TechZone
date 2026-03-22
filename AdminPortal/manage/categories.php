<?php
$pageTitle = 'Category Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="table-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">All Categories</h5>
        <button class="btn btn-primary" id="addCategoryBtn">
            <i class="fas fa-plus me-1"></i> Add New Category
        </button>
    </div>
    <div class="row g-3" id="categoriesGrid">
        <div class="col-12 text-center py-4 text-muted">Loading...</div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="categoryForm" enctype="multipart/form-data">
                    <input type="hidden" id="categoryId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" placeholder="e.g. Monitors" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea class="form-control" id="categoryDescription" rows="3" placeholder="Short description…"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Image</label>
                        <!-- Hidden real file input -->
                        <input type="file" id="categoryImage" name="image" accept="image/*" style="position:absolute;opacity:0;pointer-events:none;width:0;height:0;">
                        <!-- Clickable preview area -->
                        <div class="category-img-preview" id="categoryImgPreview" onclick="document.getElementById('categoryImage').click()" title="Click to choose image">
                            <i class="fas fa-image fa-2x text-muted"></i>
                            <span class="text-muted small d-block mt-1">Click to choose image</span>
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('categoryImage').click()">
                                <i class="fas fa-upload me-1"></i> Choose Image
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm d-none" id="clearImageBtn">
                                <i class="fas fa-times me-1"></i> Remove
                            </button>
                        </div>
                        <div class="form-text">JPG, PNG, GIF or WebP. Recommended: 400×300px.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-1"></i> Save Category
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.category-card {
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 12px;
    overflow: hidden;
    transition: box-shadow 0.2s, transform 0.2s;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    border-left: 4px solid #6366f1;
}
.category-card:hover {
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}
.category-card-thumb {
    width: 120px;
    height: 100%;
    max-height: 110px;
    object-fit: cover;
    background: #f1f5f9;
    border-left: 1px solid #e2e8f0;
}
.category-card-img-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #94a3b8;
}
.category-card-body {
    padding: 12px 16px;
}
.category-card-name {
    font-weight: 600;
    font-size: 0.95rem;
    color: #1e293b;
    margin-bottom: 4px;
}
.category-card-desc {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 8px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.category-card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.category-img-upload-wrap {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}
.category-img-preview {
    width: 100%;
    height: 150px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    overflow: hidden;
    cursor: pointer;
    transition: border-color 0.2s;
}
.category-img-preview:hover {
    border-color: #764ba2;
}
.category-img-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 6px;
}
</style>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/categories.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/categories.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
