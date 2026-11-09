<?php
/**
 * Product page (docs/ADMIN.md "Product"): details · media · pricing · inventory + ledger · variants · SEO,
 * with status, organization and insights on the side. ?id=N edits, ?new=1 creates.
 * Data loads from api/products.php, api/inventory.php, api/variations.php (assets/js/product.js).
 */
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/permissions.php';
checkLogin();

$isNew = isset($_GET['new']);
$recordId = $isNew ? 0 : (isset($_GET['id']) ? max(0, (int) $_GET['id']) : 0);
$navActive = 'products.php';
$pageBack = ['href' => admin_url('manage/products.php'), 'label' => 'Products'];
$product = null;
$prdSeller = is_seller();
if ($recordId > 0) {
    // sellers only ever open their own products (seller_scope_products_sql)
    $st = $conn->prepare('SELECT p.id, p.name, p.status, p.sku, p.seller_id, sl.name AS seller_name FROM products p LEFT JOIN sellers sl ON sl.id = p.seller_id
        WHERE p.id = ? AND ' . seller_scope_products_sql('p'));
    $st->execute([$recordId]);
    $product = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$product && $prdSeller) {
        http_response_code(404);
    }
}
// owner / manager choose which marketplace seller sells the product (side card)
$prdSellers = [];
if (can('sellers.manage')) {
    $prdSellers = $conn->query("SELECT id, name, status FROM sellers ORDER BY id = 1 DESC, name")->fetchAll(PDO::FETCH_ASSOC);
}
if ($isNew) {
    $pageCap = 'products.write';
}
$storeBase = rtrim(dirname(admin_portal_base_path() ?: '/'), '/') . '/MarketingSite/shop/product-details.php?id=';
$canWrite = can('products.write');

if (!$isNew && !$product) {
    $pageTitle = 'Product not found';
    require_once __DIR__ . '/../includes/header.php';
    echo '<div class="panel"><div class="empty-state"><div class="empty-state-icon"><i class="fas fa-tag"></i></div>'
        . '<h2 class="empty-state-title">This product doesn’t exist</h2><p class="empty-state-text">It may have been deleted. Search the product list instead.</p>'
        . '<a class="btn btn-secondary" href="' . htmlspecialchars(admin_url('manage/products.php'), ENT_QUOTES, 'UTF-8') . '">Back to products</a></div></div>';
    require_once __DIR__ . '/../includes/footer.php';
    return;
}

$e = static fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$pageTitle = $isNew ? 'Add product' : $product['name'];
$pageSubtitle = $isNew ? 'Start as a draft; set it Active when it is ready for the store.' : null;
$pageActions = ($isNew ? '' : '<a class="btn btn-secondary" id="prdViewStore" href="' . $e($storeBase . $recordId) . '" target="_blank" rel="noopener"' . ($product['status'] === 'active' ? '' : ' hidden') . '><i class="fas fa-arrow-up-right-from-square"></i> View in store</a>')
    . '<button type="button" class="btn btn-primary" id="prdSave" disabled data-cap="products.write"><i class="fas fa-check"></i> ' . ($isNew ? 'Create product' : 'Save') . '</button>';
require_once __DIR__ . '/../includes/header.php';
?>
<?php if (!$canWrite): ?>
    <div class="prd-readonly-note"><i class="fas fa-eye" aria-hidden="true"></i> You can view this product. Editing products and stock needs a manager or the owner.</div>
<?php elseif ($prdSeller && $isNew): ?>
    <div class="prd-readonly-note"><i class="fas fa-store" aria-hidden="true"></i> New products are listed under your shop. TechZone may review them before they go live.</div>
<?php endif; ?>

<form id="productForm" class="prd-layout" novalidate data-id="<?= (int) $recordId ?>" data-new="<?= $isNew ? '1' : '0' ?>" data-store="<?= $e($storeBase) ?>">
    <div class="prd-main">
        <section class="panel" aria-labelledby="prdDetailsTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdDetailsTitle">Details</h2></div>
            <div class="panel-body prd-grid2">
                <div class="prd-field prd-span2">
                    <label class="form-label" for="prdName">Name</label>
                    <input type="text" class="form-control" id="prdName" name="name" maxlength="200" required autocomplete="off" placeholder="e.g. Logitech G502 HERO Gaming Mouse">
                </div>
                <div class="prd-field">
                    <label class="form-label" for="prdCategory">Category</label>
                    <select class="form-select" id="prdCategory" name="category_id"><option value="">Uncategorised</option></select>
                </div>
                <div class="prd-field">
                    <label class="form-label" for="prdSku">SKU</label>
                    <input type="text" class="form-control tabular" id="prdSku" name="sku" maxlength="40" autocomplete="off" placeholder="<?= $isNew ? 'Generated (TZ-0033) if empty' : '' ?>">
                </div>
                <div class="prd-field prd-span2">
                    <label class="form-label" for="prdDescription">Short description <span class="prd-count" data-count-for="prdDescription" data-max="600"></span></label>
                    <textarea class="form-control" id="prdDescription" name="description" rows="3" placeholder="One or two lines shown on the product card and page"></textarea>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="prdMediaTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdMediaTitle">Media</h2><span class="spacer"></span><span class="panel-sub">One image per product</span></div>
            <div class="panel-body">
                <div class="prd-media">
                    <div class="prd-media-img" id="prdImageBox"><i class="fas fa-image" aria-hidden="true"></i></div>
                    <div class="prd-drop<?= $canWrite ? '' : ' is-disabled' ?>" id="prdDrop" tabindex="<?= $canWrite ? '0' : '-1' ?>" role="button" aria-label="Upload or replace the product image" data-field="image">
                        <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                        <span><strong>Drop an image here</strong> or click to <span id="prdDropVerb">upload</span></span>
                        <span class="prd-hint">JPG, PNG or WebP up to 2 MB. The store shows one image per product; a new one replaces it.</span>
                    </div>
                    <input type="file" id="prdImageInput" accept="image/jpeg,image/png,image/webp" hidden data-save-ignore>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="prdPricingTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdPricingTitle">Pricing</h2></div>
            <div class="panel-body">
                <div class="prd-grid3">
                    <div class="prd-field">
                        <label class="form-label" for="prdPrice">Price</label>
                        <div class="input-group prd-money"><span class="input-group-text">Rs</span><input type="number" class="form-control" id="prdPrice" name="price" min="0" step="1" inputmode="decimal" required></div>
                    </div>
                    <div class="prd-field">
                        <label class="form-label" for="prdCompare">Compare-at price</label>
                        <div class="input-group prd-money"><span class="input-group-text">Rs</span><input type="number" class="form-control" id="prdCompare" name="compare_at_price" min="0" step="1" inputmode="decimal" placeholder="—"></div>
                        <div class="form-text">Shown struck through when higher than the price.</div>
                    </div>
                    <div class="prd-field">
                        <label class="form-label" for="prdCost">Cost per item</label>
                        <div class="input-group prd-money"><span class="input-group-text">Rs</span><input type="number" class="form-control" id="prdCost" name="cost_price" min="0" step="1" inputmode="decimal" placeholder="—"></div>
                        <div class="form-text">Never shown to customers.</div>
                    </div>
                </div>
                <div class="prd-margin mt-3" id="prdMargin" aria-live="polite"></div>
            </div>
        </section>

        <section class="panel" aria-labelledby="prdInvTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdInvTitle">Inventory</h2><span class="spacer"></span>
                <?php if (!$isNew): ?><a class="panel-sub" href="<?= $e(admin_url('manage/inventory.php')) ?>">All stock levels</a><?php endif; ?></div>
            <div class="panel-body">
                <?php if ($isNew): ?>
                    <div class="prd-grid2">
                        <div class="prd-field">
                            <label class="form-label" for="prdOpening">Opening stock</label>
                            <input type="number" class="form-control tabular" id="prdOpening" name="inventory" min="0" step="1" value="0" inputmode="numeric">
                            <div class="form-text">Recorded in the stock ledger as “received · Opening stock”.</div>
                        </div>
                        <div class="prd-field">
                            <label class="form-label" for="prdThreshold">Low-stock alert at</label>
                            <input type="number" class="form-control tabular" id="prdThreshold" name="low_stock_threshold" min="0" step="1" value="5" inputmode="numeric">
                            <div class="form-text">Flagged on Home and Inventory at or below this.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="prd-stock">
                        <div>
                            <div class="prd-stock-num" id="prdStockNum">—</div>
                            <div class="prd-stock-lbl" id="prdStockLbl">in stock</div>
                        </div>
                        <button type="button" class="btn btn-secondary" id="prdAdjustBtn" data-cap="inventory.adjust"><i class="fas fa-plus-minus"></i> Adjust</button>
                        <span class="spacer"></span>
                        <div class="prd-field" style="width:180px">
                            <label class="form-label" for="prdThreshold">Low-stock alert at</label>
                            <input type="number" class="form-control tabular" id="prdThreshold" name="low_stock_threshold" min="0" step="1" inputmode="numeric">
                        </div>
                    </div>
                    <div class="prd-ledger">
                        <div class="prd-ledger-wrap table-scroll">
                            <table class="table mini-table">
                                <thead><tr><th>Date</th><th class="num">Change</th><th>Reason</th><th class="prd-hide-sm">Reference</th><th class="prd-hide-sm">By</th></tr></thead>
                                <tbody id="prdLedger"><tr><td colspan="5" class="table-empty">Loading…</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel prd-variants" aria-labelledby="prdVarTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdVarTitle">Variants</h2><span class="panel-sub">Sizes or colours; saved as soon as you add them</span><span class="spacer"></span>
                <?php if (!$isNew): ?><button type="button" class="btn btn-secondary btn-sm" id="prdAddVariant" data-cap="products.write"><i class="fas fa-plus"></i> Add option</button><?php endif; ?></div>
            <div id="prdVariants"><div class="prd-empty"><?= $isNew ? 'Create the product first, then add sizes or colours.' : 'Loading…' ?></div></div>
        </section>

        <section class="panel" aria-labelledby="prdSeoTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdSeoTitle">Search engine listing</h2></div>
            <div class="panel-body">
                <div class="prd-snippet mb-3" aria-label="Search result preview">
                    <div class="prd-snippet-site"><span class="prd-snippet-fav" aria-hidden="true">TZ</span><span><span style="display:block">TechZone</span><span class="prd-snippet-url" id="prdSnipUrl"></span></span></div>
                    <div class="prd-snippet-title" id="prdSnipTitle"></div>
                    <div class="prd-snippet-desc" id="prdSnipDesc"></div>
                </div>
                <div class="prd-grid2">
                    <div class="prd-field prd-span2">
                        <label class="form-label" for="prdSeoTitleInput">Page title <span class="prd-count" data-count-for="prdSeoTitleInput" data-max="60"></span></label>
                        <input type="text" class="form-control" id="prdSeoTitleInput" name="seo_title" maxlength="200" placeholder="Defaults to the product name">
                    </div>
                    <div class="prd-field prd-span2">
                        <label class="form-label" for="prdSeoDesc">Meta description <span class="prd-count" data-count-for="prdSeoDesc" data-max="160"></span></label>
                        <textarea class="form-control" id="prdSeoDesc" name="seo_description" rows="2" maxlength="320" placeholder="Defaults to the short description"></textarea>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <aside class="prd-side">
        <section class="panel" aria-labelledby="prdStatusTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdStatusTitle">Status</h2></div>
            <div class="panel-body">
                <div class="prd-radios" data-field="status">
                    <label class="prd-radio"><input type="radio" class="form-check-input" name="status" value="draft"><span><strong>Draft</strong><small>Hidden from the store while you finish it</small></span></label>
                    <label class="prd-radio"><input type="radio" class="form-check-input" name="status" value="active"><span><strong>Active</strong><small>Active products appear in the store</small></span></label>
                    <label class="prd-radio"><input type="radio" class="form-check-input" name="status" value="archived"><span><strong>Archived</strong><small>No longer sold; kept for order history</small></span></label>
                </div>
            </div>
        </section>

        <section class="panel" aria-labelledby="prdOrgTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdOrgTitle">Organization</h2></div>
            <div class="panel-body">
                <dl class="kv">
                    <dt>Category</dt><dd id="prdOrgCategory">—</dd>
                    <dt>SKU</dt><dd class="tabular" id="prdOrgSku">—</dd>
                    <dt>Created</dt><dd id="prdOrgCreated">—</dd>
                </dl>
                <?php if (!$prdSeller): ?><p class="prd-hint mt-3 mb-0">Products are grouped by category; there are no product tags. <a href="<?= $e(admin_url('manage/categories.php')) ?>">Manage categories</a></p><?php endif; ?>
            </div>
        </section>

        <?php if ($prdSellers): ?>
        <section class="panel" aria-labelledby="prdSellerTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdSellerTitle">Seller</h2></div>
            <div class="panel-body">
                <label class="form-label" for="prdSeller">Sold by</label>
                <select class="form-select" id="prdSeller" name="seller_id">
                    <?php foreach ($prdSellers as $sl): ?>
                        <option value="<?= (int) $sl['id'] ?>"<?= (int) ($product['seller_id'] ?? 1) === (int) $sl['id'] ? ' selected' : '' ?>><?= $e($sl['name']) ?><?= (int) $sl['id'] === 1 ? ' (our own stock)' : ($sl['status'] !== 'active' ? ' · ' . $e($sl['status']) : '') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">A marketplace seller sees and fulfils this product's orders, and it counts towards their payouts.</div>
            </div>
        </section>
        <?php elseif (!$isNew && !$prdSeller && (int) ($product['seller_id'] ?? 1) !== 1): ?>
        <section class="panel" aria-labelledby="prdSellerTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdSellerTitle">Seller</h2></div>
            <div class="panel-body"><dl class="kv"><dt>Sold by</dt><dd><?= $e($product['seller_name'] ?? '') ?></dd></dl></div>
        </section>
        <?php endif; ?>

        <?php if (!$isNew): ?>
        <section class="panel" aria-labelledby="prdInsTitle">
            <div class="panel-head"><h2 class="panel-title" id="prdInsTitle">Insights</h2><span class="spacer"></span><span class="panel-sub">Excludes cancelled orders</span></div>
            <div class="panel-body">
                <div class="prd-stats">
                    <div><span>Units sold</span><strong id="insUnits">—</strong><small id="insOrders"></small></div>
                    <div><span>Revenue</span><strong id="insRevenue">—</strong></div>
                    <div><span>Last 30 days</span><strong id="ins30">—</strong><small id="insLast"></small></div>
                    <div><span>Rating</span><strong id="insRating">—</strong><small id="insReviews"></small></div>
                </div>
            </div>
        </section>
        <?php if (can('products.delete')): ?>
        <section class="panel" aria-labelledby="prdDangerTitle">
            <div class="panel-body prd-danger-zone">
                <div><strong id="prdDangerTitle">Delete product</strong><div class="prd-hint">Only possible before it has orders. Archive it instead to stop selling.</div></div>
                <button type="button" class="btn btn-outline-danger btn-sm" id="prdDelete"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </section>
        <?php endif; ?>
        <?php endif; ?>
    </aside>
</form>

<!-- Add / edit variant (saved straight to api/variations.php; outside the product form so it never dirties it) -->
<div class="modal fade" id="variantModal" tabindex="-1" aria-labelledby="variantModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="variantModalTitle">Add option</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="variantForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="id" id="variantId">
                    <div class="prd-grid2">
                        <div class="prd-field">
                            <label class="form-label" for="variantType">Type</label>
                            <select class="form-select" id="variantType" name="variation_type">
                                <option value="size">Size</option>
                                <option value="color">Colour</option>
                            </select>
                        </div>
                        <div class="prd-field">
                            <label class="form-label" for="variantValue">Value</label>
                            <input type="text" class="form-control" id="variantValue" name="variation_value" maxlength="100" placeholder="e.g. 32GB, White, 120cm">
                        </div>
                        <div class="prd-field">
                            <label class="form-label" for="variantPrice">Price change</label>
                            <div class="input-group prd-money"><span class="input-group-text">Rs</span><input type="number" class="form-control" id="variantPrice" name="price_modifier" step="1" value="0"></div>
                            <div class="form-text">Added to the base price; negative for cheaper.</div>
                        </div>
                        <div class="prd-field">
                            <label class="form-label" for="variantStock">Stock</label>
                            <input type="number" class="form-control tabular" id="variantStock" name="inventory" min="0" step="1" value="0">
                            <div class="form-text">Customers can buy at most this many of the option.</div>
                        </div>
                        <div class="prd-field">
                            <label class="form-label" for="variantOrder">Display order</label>
                            <input type="number" class="form-control tabular" id="variantOrder" name="display_order" step="1" value="0">
                        </div>
                        <div class="prd-field">
                            <label class="form-label" for="variantActive">Availability</label>
                            <select class="form-select" id="variantActive" name="is_active"><option value="1">Available</option><option value="0">Hidden</option></select>
                        </div>
                        <div class="prd-field prd-span2">
                            <label class="form-check"><input class="form-check-input" type="checkbox" id="variantDefault" name="is_default" value="1"> <span class="form-check-label">Pre-selected option for its type</span></label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="variantSave">Save option</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="<?= $tzAsset('assets/js/form-helpers.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/stock-adjust.js') ?>"></script>
<script src="<?= $tzAsset('assets/js/product.js') ?>"></script>
