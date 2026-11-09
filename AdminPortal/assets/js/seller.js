/* TechZone admin — seller detail (manage/seller.php): profile with save bar + inline errors, products,
 * orders, earnings and weekly payout statements (owner marks a week paid). api/sellers.php?id=N. */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var root = document.getElementById('slPage');
    if (!root) return;
    var ID = parseInt(root.getAttribute('data-id'), 10);
    var e = escapeHtml;
    var $ = function (id) { return document.getElementById(id); };
    var form = $('slForm');
    var D = null;
    var saveBar = null;
    var saving = false;
    var canManage = tzCan('sellers.manage');
    var canPay = tzCan('sellers.payouts');
    var payWeek = null;
    var SELLER_TONE = { active: 'ok', paused: 'warn', suspended: 'danger' };
    var FIELDS = ['name', 'market', 'address', 'city', 'phone', 'whatsapp', 'commission_pct', 'about'];
    var INPUT = { name: 'slName', market: 'slMarket', address: 'slAddress', city: 'slCity', phone: 'slPhone', whatsapp: 'slWhatsapp', commission_pct: 'slCommission', about: 'slAbout' };

    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function sellerPill(s) { return '<span class="pill pill-' + (SELLER_TONE[s] || 'neutral') + '">' + e(s ? s.charAt(0).toUpperCase() + s.slice(1) : '—') + '</span>'; }
    function status() { var r = form.querySelector('input[name="status"]:checked'); return r ? r.value : null; }
    function pct(v) { v = +v; return (v % 1 ? v.toFixed(1) : v.toFixed(0)) + '%'; }

    function fill(s) {
        FIELDS.forEach(function (f) {
            var v = s[f];
            if (f === 'commission_pct') v = v != null ? String(+v) : '';
            $(INPUT[f]).value = v == null ? '' : v;
        });
        form.querySelectorAll('input[name="status"]').forEach(function (r) { r.checked = r.value === s.status; });
        counter();
    }
    function counter() { $('slAboutCount').textContent = $('slAbout').value.length + ' / 1000'; }

    function renderHead() {
        var s = D.seller;
        $('slStatusPill').innerHTML = sellerPill(s.status);
        $('slSlug').textContent = 'techzone.pk/sellers/' + s.slug;
        var h1 = document.querySelector('.page-title');
        if (h1) h1.textContent = s.name;
    }

    function renderStatements() {
        var rows = D.statements || [];
        $('slStatements').innerHTML = rows.length ? rows.map(function (r) {
            var pill = r.status === 'paid' ? statusPill('paid') : (r.current ? statusPill('scheduled', 'This week') : statusPill('pending', 'Upcoming'));
            var act = '';
            if (r.status === 'paid') act = '<span class="ord-hint" title="' + e(r.paid_by ? 'Recorded by ' + r.paid_by : '') + '">' + e(r.reference || '') + '</span>';
            else if (canPay && r.closed && r.gross > 0) act = '<button type="button" class="btn btn-sm btn-secondary" data-pay="' + e(r.week_start) + '">Mark paid</button>';
            return '<tr' + (r.current ? ' class="sl-current"' : '') + '><td><div class="cell-main">' + e(r.label) + '</div><div class="cell-sub">' +
                (r.status === 'paid' ? 'Paid ' + e(formatDate(r.paid_at)) : (r.current ? 'Pays ' : 'Due ') + e(formatDate(r.due_date))) + '</div></td>' +
                '<td class="num tabular sl-hide-sm">' + formatNumber(r.units) + '</td>' +
                '<td class="num tabular sl-hide-sm">' + formatPKR(r.gross) + '</td>' +
                '<td class="num tabular sl-hide-sm">−' + formatPKR(r.commission) + ' <span class="cell-sub">' + e(pct(r.commission_pct)) + '</span></td>' +
                '<td class="num tabular"><strong>' + formatPKR(r.net) + '</strong></td>' +
                '<td>' + pill + '</td><td class="num' + (r.status === 'paid' ? ' sl-hide-sm' : '') + '">' + act + '</td></tr>';
        }).join('') : '<tr><td colspan="7" class="table-empty">No deliveries yet. Statements appear once the seller’s items are delivered.</td></tr>';
    }

    function renderEarnings() {
        var ea = D.earnings, s = ea.summary;
        var h = '';
        h += '<div><dt>Due ' + e(s.due.date === s.today ? 'today' : formatDate(s.due.date)) + '</dt><dd class="tabular sl-pay-big">' + formatPKR(s.due.net) + '</dd></div>';
        if (s.due.weeks) h += '<div><dt>' + e(plural(s.due.weeks, 'closed week')) + '</dt><dd class="tabular">' + formatPKR(s.due.gross) + ' − ' + formatPKR(s.due.commission) + '</dd></div>';
        h += '<div><dt>This week so far</dt><dd class="tabular">' + formatPKR(s.this_week.net) + '</dd></div>';
        h += '<div><dt>Paid to date</dt><dd class="tabular">' + formatPKR(s.paid_total) + '</dd></div>';
        h += '<div><dt>Sales, 30 days</dt><dd class="tabular">' + formatPKR(ea.d30.revenue) + ' <span class="cell-sub">' + e(plural(ea.d30.orders, 'order')) + '</span></dd></div>';
        h += '<div><dt>Sales, all time</dt><dd class="tabular">' + formatPKR(ea.lifetime.revenue) + '</dd></div>';
        h += '<div><dt>Commission</dt><dd class="tabular">' + e(pct(D.seller.commission_pct)) + '</dd></div>';
        $('slEarnings').innerHTML = h;
    }

    function renderAccounts() {
        var acc = D.accounts || [];
        var h = acc.length ? '<ul class="sl-accounts">' + acc.map(function (a) {
            return '<li><span class="avatar" aria-hidden="true">' + e(String(a.name || '?').charAt(0).toUpperCase()) + '</span><div><strong>' + e(a.name) + '</strong>' +
                '<div class="ord-hint">' + e(a.email) + '</div><div class="ord-hint">' + (a.last_login_at ? 'Last signed in ' + e(formatDateTime(a.last_login_at)) : 'Has not signed in yet') + '</div></div></li>';
        }).join('') + '</ul>' : '<p class="muted mb-0">No seller account.</p>';
        if (D.application) {
            h += '<p class="ord-hint mt-3 mb-0">Applied ' + e(formatDate(D.application.created_at)) + ' · <a href="' + e(adminUrl('manage/sellers.php?application=' + parseInt(D.application.id, 10))) + '">View application</a></p>';
        }
        $('slAccounts').innerHTML = h;
    }

    function renderProducts() {
        var rows = D.products || [];
        var active = rows.filter(function (p) { return p.status === 'active'; }).length;
        $('slProductsSub').textContent = plural(rows.length, 'product') + ' · ' + active + ' active';
        $('slProducts').innerHTML = rows.length ? rows.map(function (p) {
            var href = adminUrl('manage/product.php?id=' + parseInt(p.id, 10));
            return '<tr data-href="' + e(href) + '"><td><a class="cell-main" href="' + e(href) + '">' + e(p.name) + '</a><div class="cell-sub">' + e(p.sku || '') + (p.category_name ? ' · ' + e(p.category_name) : '') + '</div></td>' +
                '<td class="num tabular">' + formatPKR(p.price) + '</td><td class="num">' + stockCell(p.inventory, p.low_stock_threshold) + '</td>' +
                '<td class="num tabular sl-hide-sm">' + formatNumber(p.sold) + '</td><td>' + statusPill(p.status) + '</td></tr>';
        }).join('') : '<tr><td colspan="5" class="table-empty">No products yet. The seller adds them from their portal, or set the seller on a product page.</td></tr>';
    }

    function renderOrders() {
        var rows = D.orders || [];
        $('slOrders').innerHTML = rows.length ? rows.map(function (o) {
            var href = adminUrl('manage/order.php?id=' + parseInt(o.id, 10));
            return '<tr data-href="' + e(href) + '"><td><a class="id-cell" href="' + e(href) + '">#' + parseInt(o.id, 10) + '</a><div class="cell-sub">' + e(formatDate(o.order_date)) + '</div></td>' +
                '<td><div class="cell-main">' + e(o.customer_name) + '</div><div class="cell-sub">' + e(o.city || '') + '</div></td>' +
                '<td>' + statusPill(o.fulfillment_status) + '</td><td>' + statusPill(o.status) + '</td>' +
                '<td class="num tabular">' + formatPKR(o.total) + '<div class="cell-sub">' + e(plural(o.items_count, 'item')) + '</div></td></tr>';
        }).join('') : '<tr><td colspan="5" class="table-empty">No orders with this seller’s products yet.</td></tr>';
    }

    function load(keepForm) {
        return tzJson('GET', adminUrl('api/sellers.php?id=' + ID)).then(function (res) {
            if (!res.ok) { showNotification(res.data.message || 'Could not load the seller', 'error'); return; }
            D = res.data;
            if (!keepForm) fill(D.seller);
            renderHead(); renderStatements(); renderEarnings(); renderAccounts(); renderProducts(); renderOrders();
            if (saveBar && !keepForm) saveBar.markClean();
            setSaveState();
            root.setAttribute('aria-busy', 'false');
        });
    }

    function setSaveState() {
        var b = $('slSave');
        if (b) b.disabled = saving || !(saveBar && saveBar.isDirty());
    }
    function payload() {
        var b = { id: ID };
        FIELDS.forEach(function (f) { b[f] = $(INPUT[f]).value.trim(); });
        if (status()) b.status = status();
        return b;
    }
    function save() {
        if (saving || !canManage) return;
        var b = payload();
        var ce = {};
        if (!b.name) ce.name = 'Give the shop a name.';
        if (!b.city) ce.city = 'Enter the city.';
        var c = parseFloat(b.commission_pct);
        if (b.commission_pct === '' || isNaN(c) || c < 0 || c > 50) ce.commission_pct = 'Commission must be between 0 and 50%.';
        if (Object.keys(ce).length) { tzShowErrors(form, ce); return; }
        var go = function () {
            saving = true; setSaveState();
            tzJson('PUT', adminUrl('api/sellers.php'), b).then(function (res) {
                saving = false;
                if (!res.ok) {
                    setSaveState();
                    var loose = res.data.errors ? tzShowErrors(form, res.data.errors) : [];
                    showNotification(loose[0] || res.data.message || 'Could not save the seller', 'error');
                    return;
                }
                tzClearErrors(form);
                saveBar.markClean();
                showNotification(res.data.changed && res.data.changed.length ? 'Seller saved' : 'No changes to save', 'success');
                load(false);
            });
        };
        if (b.status === 'suspended' && D.seller.status !== 'suspended') {
            showConfirm('Suspend ' + D.seller.name + '? Their seller account is signed out and cannot sign in until you reactivate the shop. Orders already placed stay on the order pages.', go, 'Suspend seller');
        } else go();
    }

    /* ---------- mark paid ---------- */
    function openPaid(week) {
        var r = (D.statements || []).filter(function (x) { return x.week_start === week; })[0];
        if (!r) return;
        payWeek = r;
        $('slPaidTitle').textContent = 'Mark ' + r.label + ' paid';
        $('slPaidText').textContent = 'Records that ' + D.seller.name + ' was paid for items delivered ' + r.label + '. The seller sees it as Paid on their statement.';
        $('slPaidFigures').innerHTML = '<div><dt>Gross delivered</dt><dd class="tabular">' + formatPKR(r.gross) + '</dd></div>' +
            '<div><dt>Commission ' + e(pct(r.commission_pct)) + '</dt><dd class="tabular">−' + formatPKR(r.commission) + '</dd></div>' +
            '<div><dt>Net payable</dt><dd class="tabular sl-pay-big">' + formatPKR(r.net) + '</dd></div>';
        tzClearErrors($('slPaidForm'));
        $('slPaidRef').value = '';
        bootstrap.Modal.getOrCreateInstance($('slPaidModal')).show();
    }
    if ($('slPaidForm')) {
        $('slPaidForm').addEventListener('submit', function (ev) {
            ev.preventDefault();
            var ref = $('slPaidRef').value.trim();
            if (!ref) { tzShowErrors($('slPaidForm'), { reference: 'Enter the bank or transfer reference.' }); return; }
            $('slPaidSubmit').disabled = true;
            tzJson('POST', adminUrl('api/sellers.php'), { action: 'mark_paid', seller_id: ID, week_start: payWeek.week_start, reference: ref }).then(function (res) {
                $('slPaidSubmit').disabled = false;
                if (!res.ok) {
                    var loose = res.data.errors ? tzShowErrors($('slPaidForm'), res.data.errors) : [];
                    showNotification(loose[0] || res.data.message || 'Could not mark the payout paid', 'error');
                    return;
                }
                bootstrap.Modal.getOrCreateInstance($('slPaidModal')).hide();
                showNotification('Payout marked paid', 'success');
                load(true);
            });
        });
    }

    /* ---------- wire up ---------- */
    saveBar = initSaveBar(form, { onSave: save, onDiscard: function () { tzClearErrors(form); counter(); setSaveState(); } });
    form.addEventListener('input', function () { counter(); setSaveState(); });
    form.addEventListener('change', setSaveState);
    form.addEventListener('submit', function (ev) { ev.preventDefault(); save(); });
    if ($('slSave')) $('slSave').addEventListener('click', save);
    if (!canManage) form.querySelectorAll('input, select, textarea').forEach(function (el) { el.disabled = true; });
    $('slStatements').addEventListener('click', function (ev) { var b = ev.target.closest('[data-pay]'); if (b) openPaid(b.getAttribute('data-pay')); });
    ['slProducts', 'slOrders'].forEach(function (id) {
        $(id).addEventListener('click', function (ev) {
            var tr = ev.target.closest('tr[data-href]');
            if (tr && !ev.target.closest('a')) location.href = tr.getAttribute('data-href');
        });
    });
    load(false);
});
