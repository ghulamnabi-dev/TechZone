/* TechZone admin — product page (manage/product.php). */
(function () {
    'use strict';
    var form = document.getElementById('productForm');
    if (!form) return;
    var id = parseInt(form.getAttribute('data-id'), 10) || 0;
    var isNew = form.getAttribute('data-new') === '1';
    var storeBase = form.getAttribute('data-store');
    var canWrite = tzCan('products.write');
    var product = null;
    var categories = [];
    var variants = [];
    var pendingImage = null;      // new product: picked before the first save
    var saveBar = null;
    var saving = false;
    var $ = function (s) { return document.getElementById(s); };
    var saveBtn = $('prdSave');

    var REASON_LABEL = { received: 'Received', sold: 'Sold', returned: 'Returned', damaged: 'Damaged', correction: 'Correction', cancelled_order: 'Order cancelled' };
    var STATUS_LABEL = { draft: 'Draft', active: 'Active', archived: 'Archived' };

    function slugify(s) {
        return String(s || '').toLowerCase().normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/["'″]/g, '')
            .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70) || 'product';
    }
    function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }
    function status() { var r = form.querySelector('input[name="status"]:checked'); return r ? r.value : 'draft'; }

    /* ---------- title + pill ---------- */
    function renderHead() {
        var h1 = document.querySelector('.page-title');
        if (!h1) return;
        var name = $('prdName').value.trim();
        h1.textContent = name || (isNew ? 'Add product' : 'Untitled product');
        var st = status();
        var pill = document.createElement('span');
        pill.className = 'prd-title-pill';
        pill.innerHTML = statusPill(st, STATUS_LABEL[st]);
        h1.appendChild(pill);
        var view = $('prdViewStore');
        if (view && product) view.hidden = product.status !== 'active';
    }

    /* ---------- pricing ---------- */
    function renderMargin() {
        var price = num($('prdPrice').value), cost = num($('prdCost').value), cmp = num($('prdCompare').value);
        var out = [];
        if (price !== null && cost !== null && price > 0) {
            var profit = price - cost;
            var pct = profit / price * 100;
            out.push('<span>Margin <strong class="' + (profit >= 0 ? 'ok' : 'bad') + '" id="prdMarginPct">' + pct.toFixed(1) + '%</strong></span>');
            out.push('<span class="ink-2">Profit ' + escapeHtml(formatPKR(profit)) + ' per item</span>');
        } else {
            out.push('<span class="ink-2">Add a cost per item to see the margin.</span>');
        }
        if (cmp !== null && price !== null && cmp > price) {
            out.push('<span class="ink-2">Shoppers see <span class="prd-was">' + escapeHtml(formatPKR(cmp)) + '</span> ' + escapeHtml(formatPKR(price)) + ' · ' + Math.round((cmp - price) / cmp * 100) + '% off</span>');
        }
        $('prdMargin').innerHTML = out.join('<span class="ink-2" aria-hidden="true">·</span>');
    }

    /* ---------- SEO ---------- */
    function renderSeo() {
        var name = $('prdName').value.trim() || 'Product name';
        var t = $('prdSeoTitleInput').value.trim() || name;
        var d = $('prdSeoDesc').value.trim() || $('prdDescription').value.trim() || 'Add a description so search engines and shoppers know what this is.';
        $('prdSnipTitle').textContent = t.length > 60 ? t.slice(0, 59) + '…' : t;
        $('prdSnipDesc').textContent = d.length > 160 ? d.slice(0, 159) + '…' : d;
        $('prdSnipUrl').textContent = 'techzone.pk › shop › ' + slugify(name);
        form.querySelectorAll('[data-count-for]').forEach(function (c) {
            var el = $(c.getAttribute('data-count-for'));
            var max = parseInt(c.getAttribute('data-max'), 10);
            var n = el.value.length;
            c.textContent = n + ' / ' + max;
            c.classList.toggle('over', n > max);
        });
    }

    /* ---------- organization / status side ---------- */
    function renderSide() {
        var sel = $('prdCategory');
        var opt = sel.options[sel.selectedIndex];
        $('prdOrgCategory').textContent = opt && opt.value ? opt.text : 'Uncategorised';
        $('prdOrgSku').textContent = $('prdSku').value.trim().toUpperCase() || (product && product.sku) || 'Assigned on save';
        $('prdOrgCreated').textContent = product ? formatDate(product.created_at) : 'Not yet';
    }

    /* ---------- media ---------- */
    function showImage(src) {
        var box = $('prdImageBox');
        box.innerHTML = '';
        if (src) {
            var img = document.createElement('img');
            img.alt = 'Product image';
            img.src = src;
            img.onerror = function () { box.innerHTML = '<i class="fas fa-image" aria-hidden="true"></i>'; };
            box.appendChild(img);
            $('prdDropVerb').textContent = 'replace it';
        } else {
            box.innerHTML = '<i class="fas fa-image" aria-hidden="true"></i>';
            $('prdDropVerb').textContent = 'upload';
        }
    }
    function uploadImage(file) {
        var fd = new FormData();
        fd.append('action', 'image');
        fd.append('id', String(id));
        fd.append('image', file);
        var drop = $('prdDrop');
        drop.classList.add('is-disabled');
        return tzJson('POST', adminUrl('api/products.php'), fd).then(function (res) {
            drop.classList.remove('is-disabled');
            if (!res.ok) {
                showNotification((res.data.errors && res.data.errors.image) || res.data.message || 'Could not upload the image', 'error');
                return false;
            }
            showImage(res.data.imagelink + '?v=' + Date.now());
            showNotification('Image replaced', 'success');
            return true;
        });
    }
    function takeFile(file) {
        if (!file || !canWrite) return;
        if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { showNotification('Use a JPG, PNG or WebP image', 'error'); return; }
        if (file.size > 2 * 1024 * 1024) { showNotification('Image is too large (max 2 MB)', 'error'); return; }
        if (isNew) {
            pendingImage = file;
            var r = new FileReader();
            r.onload = function () { showImage(r.result); };
            r.readAsDataURL(file);
            showNotification('Image will upload when you create the product', 'info');
            setSaveState();
            return;
        }
        uploadImage(file);
    }
    function initMedia() {
        var drop = $('prdDrop'), input = $('prdImageInput');
        if (!canWrite) return;
        drop.addEventListener('click', function () { input.click(); });
        drop.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
        input.addEventListener('change', function () { takeFile(input.files[0]); input.value = ''; });
        ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
        drop.addEventListener('drop', function (e) { takeFile(e.dataTransfer && e.dataTransfer.files[0]); });
    }

    /* ---------- stock + ledger ---------- */
    function renderStock() {
        if (isNew || !product) return;
        var n = parseInt(product.inventory, 10) || 0;
        var t = parseInt($('prdThreshold').value, 10);
        if (isNaN(t)) t = parseInt(product.low_stock_threshold, 10) || 0;
        $('prdStockNum').innerHTML = stockCell(n, t);
        $('prdStockLbl').textContent = n <= 0 ? 'Out of stock' : (n <= t ? 'Low stock · alert at ' + t : 'in stock');
    }
    function loadLedger() {
        if (isNew) return;
        tzJson('GET', adminUrl('api/inventory.php?product_id=' + id)).then(function (res) {
            var rows = res.ok ? res.data.adjustments || [] : [];
            var tb = $('prdLedger');
            if (!rows.length) { tb.innerHTML = emptyRow(5, 'No stock movements yet. Adjust stock to record a delivery or a count.'); return; }
            tb.innerHTML = rows.map(function (a) {
                var d = parseInt(a.delta, 10);
                return '<tr><td class="nowrap ink-2">' + escapeHtml(formatDateTime(a.created_at)) + '</td>' +
                    '<td class="num"><span class="prd-delta ' + (d > 0 ? 'up' : 'down') + '">' + (d > 0 ? '+' : '−') + formatNumber(Math.abs(d)) + '</span></td>' +
                    '<td><span class="prd-reason r-' + escapeHtml(a.reason) + '">' + escapeHtml(REASON_LABEL[a.reason] || a.reason) + '</span>' +
                    (a.variation_value ? ' <span class="ink-2">· ' + escapeHtml(a.variation_value) + '</span>' : '') + '</td>' +
                    '<td class="cell-clip ink-2 prd-hide-sm" title="' + escapeHtml(a.reference || '') + '">' + linkRef(a.reference) + '</td>' +
                    '<td class="ink-2 prd-hide-sm nowrap">' + escapeHtml(a.admin_name || (a.reason === 'sold' || a.reason === 'cancelled_order' ? 'Storefront' : 'System')) + '</td></tr>';
            }).join('');
        });
    }
    /** "order #12" becomes a link to the order page; everything else is text. */
    function linkRef(ref) {
        if (!ref) return '—';
        var m = /^order #(\d+)$/i.exec(ref);
        if (m) return '<a href="' + escapeHtml(adminUrl('manage/order.php?id=' + m[1])) + '">Order #' + escapeHtml(m[1]) + '</a>';
        return escapeHtml(ref);
    }

    /* ---------- variants ---------- */
    function renderVariants() {
        var host = $('prdVariants');
        if (isNew) return;
        if (!variants.length) {
            host.innerHTML = '<div class="prd-empty">No options. Customers buy the product as it is' + (canWrite ? '; add sizes or colours if it comes in more than one.' : '.') + '</div>';
            return;
        }
        var h = '<div class="table-scroll"><table class="table mini-table"><thead><tr><th>Type</th><th>Value</th><th class="num">Price change</th><th>Stock</th><th>Availability</th>' + (canWrite ? '<th class="text-end"><span class="visually-hidden">Actions</span></th>' : '') + '</tr></thead><tbody>';
        variants.forEach(function (v) {
            h += '<tr><td><span class="chip">' + (v.variation_type === 'size' ? 'Size' : 'Colour') + '</span></td>' +
                '<td class="cell-main">' + escapeHtml(v.variation_value) + (v.is_default == 1 ? ' <span class="ink-2" style="font-weight:400">· default</span>' : '') + '</td>' +
                '<td class="num">' + escapeHtml(formatPKRDelta(v.price_modifier)) + '</td>' +
                '<td>' + stockCell(v.inventory, product ? product.low_stock_threshold : 5) + '</td>' +
                '<td>' + statusPill(v.is_active == 1 ? 'active' : 'inactive', v.is_active == 1 ? 'Available' : 'Hidden') + '</td>' +
                (canWrite ? '<td class="text-end"><div class="actions">' + iconButton('fa-pen', 'Edit option', 'data-variant-edit="' + parseInt(v.id, 10) + '"') +
                    iconButton('fa-trash', 'Delete option', 'data-variant-delete="' + parseInt(v.id, 10) + '"', 'danger') + '</div></td>' : '') + '</tr>';
        });
        host.innerHTML = h + '</tbody></table></div>';
    }
    function loadVariants() {
        if (isNew) return Promise.resolve();
        return tzJson('GET', adminUrl('api/variations.php?product_id=' + id)).then(function (res) {
            variants = res.ok ? res.data.variations || [] : [];
            renderVariants();
        });
    }
    var vModalEl = $('variantModal'), vForm = $('variantForm');
    function openVariant(v) {
        vForm.reset();
        tzClearErrors(vForm);
        $('variantModalTitle').textContent = v ? 'Edit option' : 'Add option';
        $('variantId').value = v ? v.id : '';
        $('variantType').value = v ? v.variation_type : 'size';
        $('variantType').disabled = !!v;
        $('variantValue').value = v ? v.variation_value : '';
        $('variantPrice').value = v ? Math.round(+v.price_modifier) : '0';
        $('variantStock').value = v ? v.inventory : '0';
        $('variantOrder').value = v ? v.display_order : String(variants.length);
        $('variantActive').value = v ? String(v.is_active) : '1';
        $('variantDefault').checked = v ? v.is_default == 1 : !variants.length;
        bootstrap.Modal.getOrCreateInstance(vModalEl).show();
    }
    function saveVariant() {
        var vid = $('variantId').value;
        var value = $('variantValue').value.trim();
        if (!value) { tzShowErrors(vForm, { variation_value: 'Enter the option, e.g. 32GB or White.' }); return; }
        var fd = new FormData();
        fd.append('product_id', String(id));
        fd.append('variation_type', $('variantType').value);
        fd.append('variation_value', value);
        fd.append('price_modifier', $('variantPrice').value || '0');
        fd.append('inventory', $('variantStock').value || '0');
        fd.append('display_order', $('variantOrder').value || '0');
        fd.append('is_active', $('variantActive').value);
        fd.append('is_default', $('variantDefault').checked ? '1' : '0');
        if (vid) { fd.append('id', vid); fd.append('_method', 'PUT'); }
        $('variantSave').disabled = true;
        tzJson('POST', adminUrl('api/variations.php'), fd).then(function (res) {
            $('variantSave').disabled = false;
            if (!res.ok) { tzShowErrors(vForm, { variation_value: res.data.message || 'Could not save the option' }); return; }
            bootstrap.Modal.getOrCreateInstance(vModalEl).hide();
            showNotification(vid ? 'Option saved' : 'Option added', 'success');
            loadVariants();
        });
    }
    function deleteVariant(vid) {
        var v = variants.filter(function (x) { return x.id == vid; })[0];
        if (!v) return;
        showConfirm('Delete the option “' + v.variation_value + '”? Past orders keep their option name.', function () {
            tzJson('DELETE', adminUrl('api/variations.php?id=' + encodeURIComponent(vid)), new FormData()).then(function (res) {
                if (!res.ok) { showNotification(res.data.message || 'Could not delete the option', 'error'); return; }
                showNotification('Option deleted', 'success');
                loadVariants();
            });
        }, 'Delete option');
    }

    /* ---------- insights ---------- */
    function renderInsights(ins) {
        if (!ins || !$('insUnits')) return;
        $('insUnits').textContent = formatNumber(Math.max(ins.units, parseInt(product.sold, 10) || 0));
        $('insOrders').textContent = ins.orders ? 'in ' + formatNumber(ins.orders) + ' order' + (ins.orders === 1 ? '' : 's') : 'No orders yet';
        $('insRevenue').textContent = formatPKR(ins.revenue);
        $('ins30').textContent = formatNumber(ins.units_30d) + ' sold';
        $('insLast').textContent = ins.last_sold_at ? 'Last sale ' + formatDate(ins.last_sold_at) : '';
        $('insRating').textContent = ins.avg_rating !== null ? ins.avg_rating.toFixed(1) + ' ★' : '—';
        $('insReviews').textContent = ins.reviews ? formatNumber(ins.reviews) + ' approved review' + (ins.reviews === 1 ? '' : 's') : 'No reviews yet';
    }

    /* ---------- fill + save ---------- */
    function fill(p) {
        $('prdName').value = p.name || '';
        $('prdCategory').value = p.category_id || '';
        $('prdSku').value = p.sku || '';
        $('prdDescription').value = p.description || '';
        $('prdPrice').value = p.price != null ? Math.round(+p.price * 100) / 100 : '';
        $('prdCompare').value = p.compare_at_price != null ? Math.round(+p.compare_at_price * 100) / 100 : '';
        $('prdCost').value = p.cost_price != null ? Math.round(+p.cost_price * 100) / 100 : '';
        $('prdThreshold').value = p.low_stock_threshold != null ? p.low_stock_threshold : 5;
        $('prdSeoTitleInput').value = p.seo_title || '';
        $('prdSeoDesc').value = p.seo_description || '';
        if ($('prdSeller') && p.seller_id) $('prdSeller').value = String(p.seller_id);
        var r = form.querySelector('input[name="status"][value="' + (p.status || 'draft') + '"]');
        if (r) r.checked = true;
        showImage(p.imagelink ? p.imagelink + (p.imagelink.indexOf('?') > -1 ? '&' : '?') + 'v=' + encodeURIComponent(p.updated_at || '') : '');
    }
    function payload() {
        var b = {
            name: $('prdName').value.trim(),
            category_id: $('prdCategory').value,
            sku: $('prdSku').value.trim(),
            description: $('prdDescription').value.trim(),
            price: $('prdPrice').value.trim(),
            compare_at_price: $('prdCompare').value.trim(),
            cost_price: $('prdCost').value.trim(),
            low_stock_threshold: $('prdThreshold').value.trim(),
            status: status(),
            seo_title: $('prdSeoTitleInput').value.trim(),
            seo_description: $('prdSeoDesc').value.trim()
        };
        if ($('prdSeller')) b.seller_id = $('prdSeller').value;
        if (isNew) b.inventory = $('prdOpening').value.trim();
        else b.id = id;
        return b;
    }
    function clientErrors(b) {
        var e = {};
        if (!b.name) e.name = 'Give the product a name.';
        if (b.price === '' || isNaN(+b.price) || +b.price < 0) e.price = 'Enter a price in rupees (0 or more).';
        if (b.compare_at_price !== '' && b.price !== '' && +b.compare_at_price <= +b.price) e.compare_at_price = 'Compare-at price must be higher than the price, or empty.';
        return e;
    }
    function setSaveState() {
        if (!saveBtn) return;
        var dirty = (saveBar && saveBar.isDirty()) || (isNew && !!pendingImage);
        saveBtn.disabled = saving || !dirty;
    }
    function save() {
        if (saving || !canWrite) return;
        var b = payload();
        var ce = clientErrors(b);
        if (Object.keys(ce).length) { tzShowErrors(form, ce); return; }
        saving = true;
        setSaveState();
        tzJson(isNew ? 'POST' : 'PUT', adminUrl('api/products.php'), b).then(function (res) {
            saving = false;
            if (!res.ok) {
                setSaveState();
                var loose = res.data.errors ? tzShowErrors(form, res.data.errors) : [];
                showNotification(loose[0] || res.data.message || 'Could not save the product', 'error');
                return;
            }
            tzClearErrors(form);
            if (isNew) {
                id = res.data.id;
                saveBar.markClean();
                var go = function () { location.href = adminUrl('manage/product.php?id=' + id + '&created=1'); };
                if (pendingImage) uploadImage(pendingImage).then(go); else go();
                return;
            }
            saveBar.markClean();
            setSaveState();
            showNotification(res.data.changed && res.data.changed.length ? 'Product saved' : 'No changes to save', 'success');
            load(true);
        });
    }

    function load(keepForm) {
        return tzJson('GET', adminUrl('api/products.php?id=' + id)).then(function (res) {
            if (!res.ok) { showNotification(res.data.message || 'Could not load the product', 'error'); return; }
            product = res.data.product;
            if (!keepForm) fill(product);
            renderAll();
            renderInsights(product.insights);
            if (saveBar && !keepForm) saveBar.markClean();
            setSaveState();
        });
    }
    function renderAll() { renderHead(); renderMargin(); renderSeo(); renderSide(); renderStock(); }

    function loadCategories() {
        return tzJson('GET', adminUrl('api/categories.php')).then(function (res) {
            categories = res.ok ? res.data.categories || [] : [];
            var sel = $('prdCategory');
            categories.slice().sort(function (a, b) { return a.name.localeCompare(b.name); }).forEach(function (c) {
                var o = document.createElement('option');
                o.value = c.id;
                o.textContent = c.name;
                sel.appendChild(o);
            });
        });
    }

    /* ---------- wire up ---------- */
    document.addEventListener('DOMContentLoaded', function () {
        saveBar = initSaveBar(form, { onSave: save, onDiscard: function () { tzClearErrors(form); renderAll(); setSaveState(); } });
        form.addEventListener('input', function () { renderAll(); setSaveState(); });
        form.addEventListener('change', function () { renderAll(); setSaveState(); });
        form.addEventListener('submit', function (e) { e.preventDefault(); save(); });
        if (saveBtn) saveBtn.addEventListener('click', save);
        if (!canWrite) form.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = true; });
        initMedia();

        var adjust = $('prdAdjustBtn');
        if (adjust) adjust.addEventListener('click', function () {
            if (!product) return;
            openStockAdjust({ id: id, name: product.name, sku: product.sku, inventory: product.inventory }, function (r) {
                product.inventory = r.inventory;
                renderStock();
                loadLedger();
            });
        });
        var add = $('prdAddVariant');
        if (add) add.addEventListener('click', function () { openVariant(null); });
        $('prdVariants').addEventListener('click', function (e) {
            var ed = e.target.closest('[data-variant-edit]');
            if (ed) { openVariant(variants.filter(function (v) { return v.id == ed.getAttribute('data-variant-edit'); })[0]); return; }
            var del = e.target.closest('[data-variant-delete]');
            if (del) deleteVariant(del.getAttribute('data-variant-delete'));
        });
        vForm.addEventListener('submit', function (e) { e.preventDefault(); saveVariant(); });
        var delBtn = $('prdDelete');
        if (delBtn) delBtn.addEventListener('click', function () {
            showConfirm('Delete “' + (product ? product.name : 'this product') + '”? This removes it, its options and reviews for good. Products that already have orders cannot be deleted; archive them instead.', function () {
                tzJson('DELETE', adminUrl('api/products.php?id=' + id), new FormData()).then(function (res) {
                    if (!res.ok) { showNotification(res.data.message || 'Could not delete the product', 'error'); return; }
                    saveBar.markClean();
                    location.href = adminUrl('manage/products.php?deleted=1');
                });
            }, 'Delete product');
        });

        loadCategories().then(function () {
            if (isNew) {
                var draft = form.querySelector('input[name="status"][value="draft"]');
                draft.checked = true;
                renderAll();
                saveBar.markClean();
                setSaveState();
                $('prdName').focus();
                return;
            }
            return load(false).then(function () { loadLedger(); loadVariants(); });
        });
        if (/[?&]created=1/.test(location.search)) {
            showNotification('Product created', 'success');
            history.replaceState(null, '', location.pathname + '?id=' + id);
        }
    });
})();
