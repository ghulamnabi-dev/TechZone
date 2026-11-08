<?php
$pageTitle = 'Categories';
$navActive = 'categories.php';
$pageActions = '<button class="btn btn-primary" id="addCategoryBtn" type="button" data-cap="products.write"><i class="fas fa-plus"></i> Add category</button>';
$useGrid = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="panel grid-panel">
    <div class="toolbar grid-toolbar">
        <label class="search mb-0">
            <i class="fas fa-magnifying-glass"></i>
            <input type="search" class="form-control" id="categorySearch" placeholder="Search categories…" aria-label="Search categories">
        </label>
        <div class="end">
            <span class="grid-count" id="categoryCount" aria-live="polite">Loading…</span>
            <button type="button" class="btn btn-secondary" id="categoriesExport"><i class="fas fa-file-csv"></i> Export CSV</button>
            <button type="button" class="btn btn-secondary btn-square" id="categoriesReset" data-bs-toggle="tooltip" data-bs-title="Reset columns" aria-label="Reset columns"><i class="fas fa-table-columns"></i></button>
        </div>
    </div>
    <div id="categoriesGrid" aria-label="Categories"></div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="categoryModalTitle">Add category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="categoryForm" enctype="multipart/form-data">
                    <input type="hidden" id="categoryId">
                    <div class="row g-4">
                        <div class="col-md-7">
                            <h6 class="form-section-title">Details</h6>
                            <div class="mb-3">
                                <label class="form-label" for="categoryName">Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="categoryName" placeholder="e.g. Monitors" required>
                            </div>
                            <div>
                                <label class="form-label" for="categoryDescription">Description</label>
                                <textarea class="form-control" id="categoryDescription" rows="5" placeholder="Short description shown on the storefront"></textarea>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <h6 class="form-section-title">Image</h6>
                            <!-- Hidden real file input -->
                            <input type="file" id="categoryImage" name="image" accept="image/*" style="position:absolute;opacity:0;pointer-events:none;width:0;height:0;">
                            <div class="category-img-preview" id="categoryImgPreview" onclick="document.getElementById('categoryImage').click()" title="Click to choose image">
                                <i class="fas fa-image fa-2x"></i>
                                <span class="small d-block mt-2">Click to choose image</span>
                            </div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('categoryImage').click()">
                                    <i class="fas fa-upload"></i> Choose
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="clearImageBtn">
                                    <i class="fas fa-xmark"></i> Remove
                                </button>
                            </div>
                            <div class="form-text">JPG, PNG, GIF or WebP · 400×300 recommended.</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="categoryForm" class="btn btn-primary">Save category</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/categories.js'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/js/categories.js'); ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
