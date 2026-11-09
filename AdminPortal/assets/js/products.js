/* TechZone admin — products list (manage/products.php). Editing lives on the product page (product.js). */
var categories = [];
var products = [];
var productsGrid = null;

var PRODUCT_STATUS_LABEL = { draft: 'Draft', active: 'Active', archived: 'Archived' };

function productStatus(p) { return p.status || (p.is_active == 1 ? 'active' : 'draft'); }
function isProductActive(p) { return productStatus(p) === 'active'; }
function productSku(p) { return p.sku || ('TZ-' + String(p.id).padStart(4, '0')); }
function productUrl(id) { return adminUrl('manage/product.php?id=' + encodeURIComponent(id)); }
function productThumbUrl(p) {
    var imageUrl = p.imagelink || '';
    // cache-bust on updated_at so replaced images show without busting on every render
    if (imageUrl) imageUrl += (imageUrl.indexOf('?') > -1 ? '&' : '?') + 'v=' + encodeURIComponent(p.updated_at || '');
    return imageUrl || null;
}

/** PATCH is_active for one product (status follows: active / draft). Resolves true/false. */
function patchProductActive(id, active) {
    return fetch(adminUrl('api/products.php'), {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, is_active: active ? 1 : 0 })
    }).then(function (r) { return r.json(); }).then(function (d) { return !!d.success; });
}
/** DELETE one product. Resolves true/false; refusals (product has orders) count as failed. */
function deleteProductRequest(id) {
    return fetch(adminUrl('api/products.php?id=' + encodeURIComponent(id)), { method: 'DELETE', body: new FormData() })
        .then(function (r) { return r.json(); }).then(function (d) { return !!d.success; });
}

function loadCategories() {
    return fetch(adminUrl('api/categories.php')).then(function (r) { return r.json(); }).then(function (data) {
        if (!data.success) return;
        categories = data.categories || [];
        var filter = document.getElementById('productCategoryFilter');
        var keep = filter.value;
        filter.innerHTML = '<option value="">All categories</option>';
        categories.slice().sort(function (a, b) { return a.name.localeCompare(b.name); }).forEach(function (c) {
            var o = document.createElement('option');
            o.value = c.name;
            o.text = c.name;
            filter.appendChild(o);
        });
        filter.value = keep;
    });
}

function loadProducts() {
    return fetch(adminUrl('api/products.php')).then(function (r) { return r.json(); }).then(function (data) {
        products = data.success ? (data.products || []) : [];
        if (productsGrid) productsGrid.setRows(products);
    }).catch(function () {
        if (productsGrid) productsGrid.setRows([]);
        showNotification('Could not load products. Refresh to try again.', 'error');
    });
}

function toggleProductStatus(id, active) {
    patchProductActive(id, !active).then(function (ok) {
        showNotification(ok ? (active ? 'Product moved to draft' : 'Product activated') : 'Could not change the status', ok ? 'success' : 'error');
        loadProducts();
    });
}
function deleteProduct(id) {
    var p = products.filter(function (x) { return x.id == id; })[0];
    showConfirm('Delete “' + (p ? p.name : 'this product') + '”? Products that already have orders cannot be deleted; archive them instead.', function () {
        fetch(adminUrl('api/products.php?id=' + encodeURIComponent(id)), { method: 'DELETE', body: new FormData() })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                showNotification(d.success ? 'Product deleted' : (d.message || 'Could not delete the product'), d.success ? 'success' : 'error');
                if (d.success) loadProducts();
            });
    }, 'Delete product');
}

function initProductsGrid() {
    var stockMode = '';   // '', 'low', 'out' — external filter, uses each product's own threshold
    productsGrid = createAdminGrid(document.getElementById('productsTable'), {
        storageKey: 'tz.grid.products.v3',
        rowHeight: 52,
        noun: ['product', 'products'],
        emptyText: 'No products match these filters.',
        exportName: 'products',
        toolbar: { search: '#productSearch', count: '#productCount', exportBtn: '#productsExport', resetBtn: '#productsReset' },
        selectable: tzCan('products.write'),
        columns: [
            { field: 'name', headerName: 'Product', kind: 'thumb', flex: 1, minWidth: 260, mobileWidth: 170,
              cellRendererParams: { wrap: true, image: productThumbUrl, icon: 'fa-tag', sub: function (p) { return 'SKU ' + productSku(p); } },
              getQuickFilterText: function (p) { return p.data.name + ' ' + productSku(p.data) + ' #' + p.data.id; } },
            { colId: 'sku', headerName: 'SKU', kind: 'muted', width: 110, hide: true, valueGetter: function (p) { return productSku(p.data); } },
            { field: 'category_name', headerName: 'Category', kind: 'chip', width: 156,
              valueGetter: function (p) { return p.data.category_name || 'Uncategorised'; } },
            { field: 'price', headerName: 'Price', kind: 'money', width: 130 },
            { field: 'inventory', headerName: 'Inventory', kind: 'stock', width: 124,
              cellRenderer: function (p) { var t = document.createElement('template'); t.innerHTML = stockCell(p.value, p.data.low_stock_threshold); return t.content.firstChild; } },
            { field: 'sold', headerName: 'Sold', kind: 'number', width: 76 },
            { colId: 'status', headerName: 'Status', kind: 'status', width: 116,
              valueGetter: function (p) { return PRODUCT_STATUS_LABEL[productStatus(p.data)] || 'Draft'; } }
        ],
        rowActions: function (product) {
            var id = parseInt(product.id, 10);
            var active = isProductActive(product);
            var write = tzCan('products.write');
            return [
                { icon: write ? 'fa-pen' : 'fa-eye', label: write ? 'Edit' : 'View', href: productUrl(id), attrs: 'data-edit-product="' + id + '"' },
                write && { icon: active ? 'fa-eye-slash' : 'fa-eye', label: active ? 'Move to draft' : 'Activate', attrs: 'data-toggle-status="' + id + '" data-current-status="' + (active ? 1 : 0) + '"', tone: active ? '' : 'ok' },
                tzCan('products.delete') && { icon: 'fa-trash', label: 'Delete', attrs: 'data-delete-product="' + id + '"', tone: 'danger' }
            ];
        },
        onRowOpen: function (p) { location.href = productUrl(p.id); },
        bulkActions: [
            tzCan('products.write') && { label: 'Activate', icon: 'fa-eye', progress: 'Activating',
              perRow: function (p) { return isProductActive(p) ? true : patchProductActive(p.id, true); },
              done: function (ok, failed) { return ok + ' ' + (ok === 1 ? 'product' : 'products') + ' activated' + (failed ? ', ' + failed + ' failed' : ''); } },
            tzCan('products.write') && { label: 'Deactivate', icon: 'fa-eye-slash', progress: 'Deactivating',
              perRow: function (p) { return !isProductActive(p) ? true : patchProductActive(p.id, false); },
              done: function (ok, failed) { return ok + ' ' + (ok === 1 ? 'product' : 'products') + ' deactivated' + (failed ? ', ' + failed + ' failed' : ''); } },
            tzCan('products.delete') && { label: 'Delete', icon: 'fa-trash', tone: 'danger', progress: 'Deleting',
              confirm: function (rows) { return 'Delete ' + rows.length + ' ' + (rows.length === 1 ? 'product' : 'products') + '? Products that already have orders are kept.'; },
              confirmLabel: 'Delete',
              perRow: function (p) { return deleteProductRequest(p.id); },
              done: function (ok, failed) {
                  return ok + ' ' + (ok === 1 ? 'product' : 'products') + ' deleted' + (failed ? ', ' + failed + ' kept (they have orders)' : '');
              } }
        ].filter(Boolean),
        onBulkDone: function () { loadProducts(); }
    });
    productsGrid.bindSelect('#productCategoryFilter', 'category_name', function (v) { return { filterType: 'text', type: 'equals', filter: v }; });

    var api = productsGrid.api;
    api.setGridOption('isExternalFilterPresent', function () { return stockMode !== ''; });
    api.setGridOption('doesExternalFilterPass', function (node) {
        var n = parseInt(node.data.inventory, 10) || 0;
        var t = parseInt(node.data.low_stock_threshold, 10);
        if (isNaN(t)) t = 5;
        return stockMode === 'out' ? n <= 0 : (n > 0 && n <= t);
    });
    // one select drives the status column (Draft/Active/Archived) or the stock filter (low/out)
    var statusSel = document.getElementById('productStatusFilter');
    statusSel.addEventListener('change', function () {
        var v = statusSel.value;
        stockMode = v === 'low' || v === 'out' ? v : '';
        var status = PRODUCT_STATUS_LABEL[v] ? { filterType: 'text', type: 'equals', filter: PRODUCT_STATUS_LABEL[v] } : null;
        Promise.resolve(api.setColumnFilterModel('status', status)).then(function () { api.onFilterChanged(); });
    });
    var q = new URLSearchParams(location.search).get('q');
    if (q) { var s = document.getElementById('productSearch'); s.value = q; s.dispatchEvent(new Event('input')); }
    if (/[?&]deleted=1/.test(location.search)) { showNotification('Product deleted', 'success'); history.replaceState(null, '', location.pathname); }

    document.getElementById('productsTable').addEventListener('click', function (e) {
        var ed = e.target.closest('button[data-edit-product]');   // mobile row menu renders links as buttons
        if (ed) { location.href = productUrl(ed.getAttribute('data-edit-product')); return; }
        var t = e.target.closest('[data-toggle-status]');
        if (t) { toggleProductStatus(t.getAttribute('data-toggle-status'), t.getAttribute('data-current-status') === '1'); return; }
        var d = e.target.closest('[data-delete-product]');
        if (d) deleteProduct(d.getAttribute('data-delete-product'));
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initProductsGrid();
    loadCategories();
    loadProducts();
});
