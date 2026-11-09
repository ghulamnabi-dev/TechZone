/* TechZone admin — discount builder (manage/discount.php). */
(function () {
    'use strict';
    var form = document.getElementById('discountForm');
    if (!form) return;
    var id = parseInt(form.getAttribute('data-id'), 10) || 0;
    var isNew = id === 0;
    var canWrite = tzCan('discounts.write');
    var promo = null, categories = [], products = [], saveBar = null, saving = false, imageChanged = false;
    var codeSeq = 0, codeTimer = null;
    var $ = function (s) { return document.getElementById(s); };
    var saveBtn = $('dscSave');
    var STATE_LABEL = { live: 'Live', scheduled: 'Scheduled', ended: 'Ended', inactive: 'Inactive' };

    function val(name) { var r = form.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
    function setRadio(name, v) { var r = form.querySelector('input[name="' + name + '"][value="' + v + '"]'); if (r) r.checked = true; }
    function toLocal(dt) { return dt ? String(dt).replace(' ', 'T').slice(0, 16) : ''; }
    function nowLocal() { var d = new Date(); d.setSeconds(0, 0); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }
    function fmtDT(v) {
        var d = parseDate(v); if (!d) return '';
        var t = d.getHours() || d.getMinutes() ? (d.getHours() === 23 && d.getMinutes() === 59 ? '' : ', ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0')) : '';
        return formatDate(d) + t;
    }
    function productImg(p) { return p.imagelink || ''; }
    function productById(pid) { return products.filter(function (p) { return p.id == pid; })[0]; }
    function categoryById(cid) { return categories.filter(function (c) { return c.id == cid; })[0]; }

    /* ---------- type / applies UI ---------- */
    function renderType() {
        var k = val('kind') || 'percent';
        var free = k === 'free_shipping';
        $('dscValueRow').hidden = free;
        $('dscValuePre').hidden = k !== 'fixed';
        $('dscValueSuf').hidden = k !== 'percent';
        $('dscValueLabel').textContent = k === 'fixed' ? 'Amount off' : 'Percentage off';
        $('dscValue').max = k === 'percent' ? '90' : '';
        $('dscValueHint').textContent = k === 'fixed' ? 'Can’t be more than the minimum order, so no order ends up free.' : 'Between 1% and 90%.';
        $('dscAppliesSeg').hidden = free;
        $('dscFreeShipNote').hidden = !free;
        renderApplies();
    }
    function renderApplies() {
        var free = val('kind') === 'free_shipping';
        var a = free ? 'all' : (val('applies_to') || 'all');
        $('dscCategoryRow').hidden = a !== 'category';
        $('dscProductRow').hidden = a !== 'product';
        // the visible picker carries the target_id error
        $('dscCategory').toggleAttribute('data-field', a === 'category');
        $('dscProductSearch').toggleAttribute('data-field', a === 'product');
        if (a === 'category') $('dscCategory').setAttribute('data-field', 'target_id');
        if (a === 'product') $('dscProductSearch').setAttribute('data-field', 'target_id');
        var t = $('dscTarget');
        if (a === 'category') t.value = $('dscCategory').value;
        renderPicked();
    }
    function renderPicked() {
        var host = $('dscPicked');
        var p = val('applies_to') === 'product' ? productById($('dscTarget').value) : null;
        if (!p) { host.innerHTML = ''; return; }
        host.innerHTML = '<div class="dsc-picked">' + (productImg(p) ? '<img src="' + escapeHtml(productImg(p)) + '" alt="">' : '') +
            '<div class="grow"><div class="cell-main">' + escapeHtml(p.name) + '</div><div class="cell-sub">' + escapeHtml((p.sku || '') + ' · ' + formatPKR(p.price)) + '</div></div>' +
            (canWrite ? '<button type="button" class="icon-btn" data-unpick aria-label="Remove product"><i class="fas fa-xmark"></i></button>' : '') + '</div>';
    }

    /* ---------- product picker ---------- */
    var pickSel = -1, pickItems = [];
    function renderPickList() {
        var q = $('dscProductSearch').value.trim().toLowerCase();
        var list = $('dscProductList');
        if (!q) { list.hidden = true; $('dscProductSearch').setAttribute('aria-expanded', 'false'); return; }
        pickItems = products.filter(function (p) { return (p.name + ' ' + (p.sku || '')).toLowerCase().indexOf(q) !== -1; }).slice(0, 12);
        pickSel = pickItems.length ? 0 : -1;
        list.innerHTML = pickItems.length ? pickItems.map(function (p, i) {
            return '<button type="button" role="option" data-pick="' + parseInt(p.id, 10) + '" class="' + (i === 0 ? 'is-active' : '') + '">' +
                (productImg(p) ? '<img src="' + escapeHtml(productImg(p)) + '" alt="">' : '') + '<span>' + escapeHtml(p.name) + '</span><small>' + escapeHtml(formatPKR(p.price)) + '</small></button>';
        }).join('') : '<div class="prd-empty">No products match “' + escapeHtml(q) + '”.</div>';
        list.hidden = false;
        $('dscProductSearch').setAttribute('aria-expanded', 'true');
    }
    function pick(pid) {
        $('dscTarget').value = String(pid);
        $('dscProductSearch').value = '';
        $('dscProductList').hidden = true;
        $('dscTarget').dispatchEvent(new Event('change', { bubbles: true }));
        tzClearErrors($('dscProductRow'));
    }

    /* ---------- code check ---------- */
    function checkCode() {
        var el = $('dscCode'), st = $('dscCodeState');
        var code = el.value.trim().toUpperCase();
        clearTimeout(codeTimer);
        if (!code) { st.className = 'dsc-code-state'; st.innerHTML = '<span class="prd-hint">Shoppers type this at the cart. Letters, numbers and dashes.</span>'; return; }
        if (!/^[A-Z0-9][A-Z0-9_-]{2,29}$/.test(code)) { st.className = 'dsc-code-state bad'; st.innerHTML = '<i class="fas fa-circle-exclamation"></i> 3–30 letters, numbers, dashes or underscores'; return; }
        if (promo && promo.promo_code && promo.promo_code.toUpperCase() === code) { st.className = 'dsc-code-state'; st.innerHTML = '<span class="prd-hint">Current code</span>'; return; }
        var my = ++codeSeq;
        st.className = 'dsc-code-state'; st.innerHTML = '<span class="prd-hint">Checking…</span>';
        codeTimer = setTimeout(function () {
            tzJson('GET', adminUrl('api/promotions.php?check_code=' + encodeURIComponent(code) + '&id=' + id)).then(function (res) {
                if (my !== codeSeq || !res.ok) return;
                st.className = 'dsc-code-state ' + (res.data.available ? 'ok' : 'bad');
                st.innerHTML = res.data.available ? '<i class="fas fa-circle-check"></i> ' + escapeHtml(code) + ' is available'
                    : '<i class="fas fa-circle-exclamation"></i> ' + escapeHtml(code) + ' is already used by another discount';
            });
        }, 250);
    }

    /* ---------- summary sentence ---------- */
    function renderSummary() {
        var k = val('kind') || 'percent';
        var v = parseFloat($('dscValue').value);
        var a = k === 'free_shipping' ? 'all' : (val('applies_to') || 'all');
        var min = parseFloat($('dscMin').value) || 0;
        var limit = parseInt($('dscLimit').value, 10);
        var used = promo ? parseInt(promo.used_count, 10) || 0 : 0;
        var start = $('dscStart').value, end = $('dscNoEnd').checked ? '' : $('dscEnd').value;
        var active = $('dscActive').checked;
        var code = $('dscCode').value.trim().toUpperCase();

        var what;
        if (k === 'free_shipping') what = '<b>Free shipping</b>';
        else if (k === 'fixed') what = '<b>' + (isFinite(v) && v > 0 ? escapeHtml(formatPKR(v)) : 'Rs —') + ' off</b>';
        else what = '<b>' + (isFinite(v) && v > 0 ? escapeHtml(String(Math.round(v * 100) / 100)) : '—') + '% off</b>';
        var target = '';
        if (k !== 'free_shipping') {
            if (a === 'all') target = ' the entire order';
            else if (a === 'category') { var c = categoryById($('dscTarget').value); target = ' <b>' + escapeHtml(c ? c.name : 'a category (choose one)') + '</b>'; }
            else { var p = productById($('dscTarget').value); target = ' <b>' + escapeHtml(p ? p.name : 'a product (choose one)') + '</b>'; }
        }
        var parts = [what + target + (min > 0 ? ' on orders over <b>' + escapeHtml(formatPKR(min)) + '</b>' : '')];
        var now = nowLocal();
        if (start && start > now) parts.push('from ' + escapeHtml(fmtDT(start)));
        if (end) parts.push((end < now ? 'ended ' : 'until ') + escapeHtml(fmtDT(end)));
        parts.push(isFinite(limit) && limit > 0 ? formatNumber(used) + ' of ' + formatNumber(limit) + ' used' : (used ? formatNumber(used) + ' used, no limit' : 'unlimited uses'));
        $('dscSentence').innerHTML = (active ? '' : '<span class="ink-2">Paused: </span>') + parts.join(', ');

        var facts = [];
        facts.push('<li><i class="fas fa-ticket" aria-hidden="true"></i><span>Code <span class="code-chip">' + escapeHtml(code || '—') + '</span></span></li>');
        facts.push('<li><i class="fas fa-calendar" aria-hidden="true"></i><span>' + (start ? 'Starts ' + escapeHtml(fmtDT(start)) : 'Starts immediately') + (end ? '; ends ' + escapeHtml(fmtDT(end)) : '; no end date') + '</span></li>');
        if (isFinite(limit) && limit > 0) {
            var pct = Math.min(100, Math.round(used / limit * 100));
            facts.push('<li><i class="fas fa-chart-simple" aria-hidden="true"></i><span style="flex:1">' + formatNumber(used) + ' of ' + formatNumber(limit) + ' uses · ' + formatNumber(Math.max(0, limit - used)) + ' left<div class="dsc-usage-bar"><i style="width:' + pct + '%"></i></div></span></li>');
        }
        if (k === 'free_shipping') facts.push('<li><i class="fas fa-truck" aria-hidden="true"></i><span>The shipping line shows “Free” at the cart and checkout.</span></li>');
        else if (a !== 'all') facts.push('<li><i class="fas fa-filter" aria-hidden="true"></i><span>Other items in the cart are charged in full.</span></li>');
        $('dscFacts').innerHTML = facts.join('');

        var state = !active ? 'inactive' : (start && start > now ? 'scheduled' : (end && end < now ? 'ended' : (isFinite(limit) && used >= limit ? 'ended' : 'live')));
        $('dscStatePill').innerHTML = statusPill(state, STATE_LABEL[state]);
        $('dscEnd').disabled = $('dscNoEnd').checked || !canWrite;
        var h1 = document.querySelector('.page-title');
        if (h1) { h1.textContent = code || (isNew ? 'Create discount' : 'Discount'); var s = document.createElement('span'); s.className = 'prd-title-pill'; s.innerHTML = statusPill(state, STATE_LABEL[state]); h1.appendChild(s); }
        if (promo) $('dscUsedNote').textContent = 'Used ' + formatNumber(used) + ' time' + (used === 1 ? '' : 's') + ' so far.';
    }

    /* ---------- usage card ---------- */
    function renderUsage() {
        if (!promo || !$('usRows')) return;
        var u = promo.usage || { orders: [] };
        $('usOrders').textContent = formatNumber(u.count);
        $('usSales').textContent = formatPKR(u.revenue);
        $('usGiven').textContent = formatPKR(u.discount);
        $('usRows').innerHTML = u.orders.length ? u.orders.map(function (o) {
            var href = adminUrl('manage/order.php?id=' + o.id);
            return '<tr data-href="' + escapeHtml(href) + '"><td><a class="cell-main id-cell" href="' + escapeHtml(href) + '">#' + escapeHtml(o.id) + '</a><div class="cell-sub">' + escapeHtml(formatDate(o.order_date)) + '</div></td>' +
                '<td>' + escapeHtml(o.customer_name) + (o.status === 'Cancelled' ? ' ' + statusPill('cancelled') : '') + '</td>' +
                '<td class="num">' + (o.status === 'Cancelled' ? '<span class="ink-2">—</span>' : escapeHtml(formatPKR(o.discount_amount))) + '</td></tr>';
        }).join('') : emptyRow(3, 'No orders have used this code yet.');
    }

    /* ---------- fill / save ---------- */
    function fill(p) {
        $('dscCode').value = p.promo_code || '';
        setRadio('kind', p.kind || 'percent');
        $('dscValue').value = p.kind === 'fixed' ? Math.round(+p.discount_amount) : (p.kind === 'percent' ? +p.discount_percentage : '');
        setRadio('applies_to', p.applies_to || 'all');
        $('dscTarget').value = p.target_id || '';
        if (p.applies_to === 'category') $('dscCategory').value = p.target_id || '';
        $('dscMin').value = +p.min_order ? Math.round(+p.min_order) : '';
        $('dscLimit').value = p.usage_limit || '';
        $('dscStart').value = toLocal(p.starts_at || p.start_date);
        var end = p.ends_at || (p.end_date ? p.end_date + ' 23:59:59' : '');
        $('dscEnd').value = toLocal(end);
        $('dscNoEnd').checked = !end;
        $('dscActive').checked = p.is_active == 1;
        $('dscTitle').value = p.title && p.title !== p.promo_code ? p.title : '';
        $('dscDesc').value = p.description || '';
        $('dscLink').value = p.link_url || '';
        $('dscOrder').value = p.display_order || 0;
        $('dscImageNote').textContent = p.image_url ? 'Has an image; choose a file to replace it.' : 'JPG, PNG or WebP up to 2 MB.';
    }
    function setSaveState() { if (saveBtn) saveBtn.disabled = saving || !((saveBar && saveBar.isDirty()) || imageChanged); }
    function save() {
        if (saving || !canWrite) return;
        var fd = new FormData();
        if (!isNew) fd.append('id', String(id));
        fd.append('promo_code', $('dscCode').value.trim().toUpperCase());
        var k = val('kind') || 'percent';
        fd.append('kind', k);
        fd.append('value', k === 'free_shipping' ? '0' : $('dscValue').value.trim());
        fd.append('applies_to', k === 'free_shipping' ? 'all' : (val('applies_to') || 'all'));
        fd.append('target_id', $('dscTarget').value);
        fd.append('min_order', $('dscMin').value.trim());
        fd.append('usage_limit', $('dscLimit').value.trim());
        fd.append('starts_at', $('dscStart').value);
        fd.append('ends_at', $('dscEnd').value);
        fd.append('no_end', $('dscNoEnd').checked ? '1' : '0');
        fd.append('is_active', $('dscActive').checked ? '1' : '0');
        fd.append('title', $('dscTitle').value.trim());
        fd.append('description', $('dscDesc').value.trim());
        fd.append('link_url', $('dscLink').value.trim());
        fd.append('display_order', $('dscOrder').value.trim());
        var f = $('dscImage').files[0];
        if (f) fd.append('image', f);
        saving = true; setSaveState();
        tzJson('POST', adminUrl('api/promotions.php'), fd).then(function (res) {
            saving = false;
            if (!res.ok) {
                setSaveState();
                var loose = res.data.errors ? tzShowErrors(form, res.data.errors) : [];
                showNotification(loose[0] || res.data.message || 'Could not save the discount', 'error');
                return;
            }
            tzClearErrors(form);
            saveBar.markClean(); imageChanged = false;
            if (isNew) { location.href = adminUrl('manage/discount.php?id=' + res.data.id + '&created=1'); return; }
            $('dscImage').value = '';
            showNotification('Discount saved', 'success');
            load(true);
        });
    }
    function load(keep) {
        return tzJson('GET', adminUrl('api/promotions.php?id=' + id)).then(function (res) {
            if (!res.ok) { showNotification(res.data.message || 'Could not load the discount', 'error'); return; }
            promo = res.data.promotion;
            if (!keep) fill(promo);
            renderType(); renderSummary(); renderUsage();
            if (!keep) saveBar.markClean();
            setSaveState();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        saveBar = initSaveBar(form, { onSave: save, onDiscard: function () { tzClearErrors(form); imageChanged = false; $('dscImage').value = ''; renderType(); renderSummary(); setSaveState(); checkCode(); } });
        var refresh = function () { renderSummary(); setSaveState(); };
        form.addEventListener('input', refresh);
        form.addEventListener('change', function (e) {
            if (e.target.name === 'kind') renderType();
            if (e.target.name === 'applies_to') { $('dscTarget').value = ''; if (e.target.value === 'category') $('dscTarget').value = $('dscCategory').value; renderApplies(); }
            if (e.target.id === 'dscCategory') $('dscTarget').value = e.target.value;
            if (e.target.id === 'dscImage') imageChanged = !!e.target.files.length;
            if (e.target.id === 'dscNoEnd' && !e.target.checked && !$('dscEnd').value) {
                var d = new Date(); d.setDate(d.getDate() + 30);
                $('dscEnd').value = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0') + 'T23:59';
            }
            renderPicked(); refresh(); saveBar.refresh(); setSaveState();
        });
        form.addEventListener('submit', function (e) { e.preventDefault(); save(); });
        if (saveBtn) saveBtn.addEventListener('click', save);
        $('dscCode').addEventListener('input', function () {
            var el = this, pos = el.selectionStart;
            el.value = el.value.toUpperCase().replace(/\s+/g, '');
            try { el.setSelectionRange(pos, pos); } catch (e) { /* ignore */ }
            checkCode();
        });
        $('dscGenerate').addEventListener('click', function () {
            var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', s = 'TZ';
            for (var i = 0; i < 6; i++) s += chars.charAt(Math.floor(Math.random() * chars.length));
            $('dscCode').value = s;
            $('dscCode').dispatchEvent(new Event('input', { bubbles: true }));
        });
        var ps = $('dscProductSearch');
        ps.addEventListener('input', renderPickList);
        ps.addEventListener('keydown', function (e) {
            var list = $('dscProductList');
            if (list.hidden || !pickItems.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                pickSel = (pickSel + (e.key === 'ArrowDown' ? 1 : -1) + pickItems.length) % pickItems.length;
                list.querySelectorAll('[data-pick]').forEach(function (b, i) { b.classList.toggle('is-active', i === pickSel); });
            } else if (e.key === 'Enter') { e.preventDefault(); if (pickSel >= 0) pick(pickItems[pickSel].id); }
            else if (e.key === 'Escape') { list.hidden = true; }
        });
        $('dscProductList').addEventListener('click', function (e) { var b = e.target.closest('[data-pick]'); if (b) pick(b.getAttribute('data-pick')); });
        $('dscPicked').addEventListener('click', function (e) { if (e.target.closest('[data-unpick]')) { $('dscTarget').value = ''; $('dscTarget').dispatchEvent(new Event('change', { bubbles: true })); } });
        document.addEventListener('click', function (e) { if (!e.target.closest('.dsc-picker')) $('dscProductList').hidden = true; });
        var usRows = $('usRows');
        if (usRows) usRows.addEventListener('click', function (e) { if (e.target.closest('a')) return; var tr = e.target.closest('tr[data-href]'); if (tr) location.href = tr.getAttribute('data-href'); });
        var del = $('dscDelete');
        if (del) del.addEventListener('click', function () {
            showConfirm('Delete ' + (promo ? promo.promo_code : 'this discount') + '? Shoppers can no longer use the code; orders that used it keep their discount.', function () {
                tzJson('DELETE', adminUrl('api/promotions.php'), { id: id }).then(function (res) {
                    if (!res.ok) { showNotification(res.data.message || 'Could not delete the discount', 'error'); return; }
                    saveBar.markClean();
                    location.href = adminUrl('manage/promotions.php?deleted=1');
                });
            }, 'Delete discount');
        });
        if (!canWrite) form.querySelectorAll('input, select, textarea, button').forEach(function (el) { el.disabled = true; });

        Promise.all([
            tzJson('GET', adminUrl('api/categories.php')).then(function (r) { categories = r.ok ? r.data.categories || [] : []; }),
            tzJson('GET', adminUrl('api/products.php')).then(function (r) { products = r.ok ? r.data.products || [] : []; })
        ]).then(function () {
            var sel = $('dscCategory');
            categories.slice().sort(function (a, b) { return a.name.localeCompare(b.name); }).forEach(function (c) {
                var o = document.createElement('option'); o.value = c.id; o.textContent = c.name; sel.appendChild(o);
            });
            if (isNew) {
                setRadio('kind', 'percent'); setRadio('applies_to', 'all');
                $('dscNoEnd').checked = true; $('dscActive').checked = true;
                renderType(); renderSummary(); saveBar.markClean(); setSaveState();
                $('dscCode').focus();
                return;
            }
            return load(false).then(checkCode);
        });
        if (/[?&]created=1/.test(location.search)) { showNotification('Discount created', 'success'); history.replaceState(null, '', location.pathname + '?id=' + id); }
    });
})();
