/* TechZone admin — Sellers (manage/sellers.php): applications queue with a detail drawer, approve (temp
 * password shown once) / reject with a reason, the sellers grid and payable totals. api/sellers.php.
 * Data reaches the DOM only through escapeHtml(), statusPill() and textContent. */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    var e = escapeHtml;
    var $ = function (id) { return document.getElementById(id); };
    var D = null;
    var tab = 'applications';
    var appStatus = 'pending';
    var current = null;     // application in the drawer
    var grid = null;
    var canManage = tzCan('sellers.manage');
    var SELLER_TONE = { active: 'ok', paused: 'warn', suspended: 'danger' };

    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function sellerPill(s) { return '<span class="pill pill-' + (SELLER_TONE[s] || 'neutral') + '">' + e(s ? s.charAt(0).toUpperCase() + s.slice(1) : '—') + '</span>'; }
    function chips(csv) {
        return String(csv || '').split(',').map(function (c) { return c.trim(); }).filter(Boolean)
            .map(function (c) { return '<span class="chip">' + e(c) + '</span>'; }).join(' ');
    }
    function slugify(s) { return String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 70); }
    function modal(id) { return bootstrap.Modal.getOrCreateInstance($(id)); }

    /* ---------------- load ---------------- */
    function load() {
        return tzJson('GET', adminUrl('api/sellers.php')).then(function (res) {
            if (!res.ok) { showNotification(res.data.message || 'Could not load sellers', 'error'); return; }
            D = res.data;
            renderKpis();
            renderApps();
            if (grid) grid.setRows(D.sellers || []);
            document.querySelector('[data-count="applications"]').textContent = formatNumber(D.counts.pending);
            document.querySelector('[data-count="sellers"]').textContent = formatNumber((D.sellers || []).length);
        });
    }

    function renderKpis() {
        var active = (D.sellers || []).filter(function (s) { return s.status === 'active'; });
        $('kPending').textContent = formatNumber(D.counts.pending);
        $('kActive').textContent = formatNumber(active.length);
        var products = active.reduce(function (n, s) { return n + s.products; }, 0);
        $('kActiveSub').textContent = plural(products, 'product') + ' listed';
        $('kPayable').textContent = formatPKR(D.payable_total);
        var owed = (D.sellers || []).filter(function (s) { return s.payable > 0; }).length;
        $('kPayableSub').textContent = owed ? plural(owed, 'seller') + ' · closed weeks after commission' : 'Nothing owed for closed weeks';
        $('kAccruing').textContent = formatPKR(D.accruing_total);
    }

    /* ---------------- applications ---------------- */
    var APP_EMPTY = {
        pending: 'No applications waiting. New ones from the Sell on TechZone page appear here.',
        approved: 'No approved applications yet.',
        rejected: 'No rejected applications.'
    };
    function renderApps() {
        Object.keys(D.counts).forEach(function (k) {
            var c = document.querySelector('[data-app-count="' + k + '"]');
            if (c) c.textContent = formatNumber(D.counts[k]);
        });
        var rows = (D.applications || []).filter(function (a) { return a.status === appStatus; });
        $('slAppRows').innerHTML = rows.length ? rows.map(function (a) {
            var id = parseInt(a.id, 10);
            return '<tr data-app="' + id + '" tabindex="0">' +
                '<td><div class="cell-main">' + e(a.shop_name) + '</div><div class="cell-sub">' + e(a.owner_name) + ' · ' + e(a.city || '') + '</div></td>' +
                '<td class="sl-hide-sm">' + e(a.market || '—') + '</td>' +
                '<td class="sl-hide-md sl-chips">' + (chips(a.categories) || '<span class="muted">—</span>') + '</td>' +
                '<td><span class="ink-2">' + e(formatDate(a.created_at)) + '</span></td>' +
                '<td>' + statusPill(a.status) + '</td>' +
                '<td class="num"><button type="button" class="btn btn-sm btn-secondary" data-app-open="' + id + '">' + (a.status === 'pending' && canManage ? 'Review' : 'View') + '</button></td></tr>';
        }).join('') : '<tr><td colspan="6" class="table-empty">' + e(APP_EMPTY[appStatus]) + '</td></tr>';
    }

    function openApp(id) {
        var a = (D.applications || []).filter(function (x) { return parseInt(x.id, 10) === id; })[0];
        if (!a) return;
        current = a;
        $('slDrawerTitle').textContent = a.shop_name;
        $('slDrawerSub').innerHTML = statusPill(a.status) + ' <span class="ord-hint">Applied ' + e(formatDateTime(a.created_at)) + '</span>';
        var h = '<dl class="kv sl-kv">';
        h += '<dt>Owner</dt><dd>' + e(a.owner_name) + '</dd>';
        h += '<dt>Phone</dt><dd><a class="tabular" href="tel:' + e(String(a.phone || '').replace(/[^\d+]/g, '')) + '">' + e(a.phone) + '</a></dd>';
        h += '<dt>Email</dt><dd><a href="mailto:' + e(a.email) + '">' + e(a.email) + '</a></dd>';
        h += '<dt>Market</dt><dd>' + e(a.market || '—') + '</dd>';
        h += '<dt>City</dt><dd>' + e(a.city || '—') + '</dd>';
        h += '<dt>Sells</dt><dd class="sl-chips">' + (chips(a.categories) || '—') + '</dd>';
        h += '<dt>NTN</dt><dd class="tabular">' + (a.ntn ? e(a.ntn) : '<span class="muted">Not given</span>') + '</dd>';
        h += '</dl>';
        h += '<h3 class="ord-sub-title">Message</h3>' + (a.message ? '<div class="quote">' + e(a.message) + '</div>' : '<p class="muted">No message.</p>');
        if (a.status !== 'pending') {
            h += '<h3 class="ord-sub-title">Review</h3><dl class="kv sl-kv">';
            h += '<dt>' + (a.status === 'approved' ? 'Approved' : 'Rejected') + '</dt><dd>' + e(formatDateTime(a.reviewed_at)) + (a.reviewed_by_name ? ' by ' + e(a.reviewed_by_name) : '') + '</dd>';
            if (a.review_note) h += '<dt>Reason</dt><dd>' + e(a.review_note) + '</dd>';
            if (a.seller_id) h += '<dt>Seller</dt><dd><a href="' + e(adminUrl('manage/seller.php?id=' + parseInt(a.seller_id, 10))) + '">Open seller <i class="fas fa-arrow-right" aria-hidden="true"></i></a></dd>';
            h += '</dl>';
        }
        $('slDrawerBody').innerHTML = h;
        var foot = '';
        if (a.status === 'pending' && canManage) {
            foot = '<button type="button" class="btn btn-secondary ord-btn-danger" data-app-reject><i class="fas fa-xmark" aria-hidden="true"></i> Reject…</button>' +
                '<button type="button" class="btn btn-primary" data-app-approve><i class="fas fa-check" aria-hidden="true"></i> Approve…</button>';
        } else if (a.status === 'pending') {
            foot = '<span class="ord-hint">Only the owner or a manager can approve applications.</span>';
        }
        $('slDrawerFoot').innerHTML = foot;
        $('slDrawerFoot').hidden = !foot;
        bootstrap.Offcanvas.getOrCreateInstance($('slDrawer')).show();
    }

    /* ---------------- approve ---------------- */
    function openApprove() {
        var a = current;
        bootstrap.Offcanvas.getOrCreateInstance($('slDrawer')).hide();
        var f = $('slApproveForm');
        tzClearErrors(f);
        $('slApproveStep1').hidden = false; $('slApproveFoot1').hidden = false;
        $('slApproveStep2').hidden = true; $('slApproveFoot2').hidden = true;
        $('slApproveTitle').textContent = 'Approve ' + a.shop_name;
        $('slApName').value = a.shop_name;
        $('slApMarket').value = a.market || '';
        $('slApCommission').value = '8';
        $('slApSlug').value = slugify(a.shop_name);
        $('slApSlug').setAttribute('data-auto', '1');
        $('slApproveText').textContent = 'Creates the shop and a seller account for ' + a.owner_name + ' (' + a.email + '). ' +
            'They can list their own products and fulfil their own orders; they never see customers, settings or other sellers.';
        $('slApproveSubmit').disabled = false;
        modal('slApproveModal').show();
    }
    function submitApprove(ev) {
        ev.preventDefault();
        var f = $('slApproveForm');
        var body = {
            action: 'approve', application_id: parseInt(current.id, 10),
            name: $('slApName').value.trim(), market: $('slApMarket').value.trim(),
            commission_pct: $('slApCommission').value.trim(), slug: $('slApSlug').value.trim()
        };
        var ce = {};
        if (!body.name) ce.name = 'Give the shop a name.';
        var pct = parseFloat(body.commission_pct);
        if (body.commission_pct === '' || isNaN(pct) || pct < 0 || pct > 50) ce.commission_pct = 'Commission must be between 0 and 50%.';
        if (body.slug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(body.slug)) ce.slug = 'Use lower-case letters, numbers and dashes.';
        if (Object.keys(ce).length) { tzShowErrors(f, ce); return; }
        $('slApproveSubmit').disabled = true;
        tzJson('POST', adminUrl('api/sellers.php'), body).then(function (res) {
            $('slApproveSubmit').disabled = false;
            if (!res.ok) {
                var loose = res.data.errors ? tzShowErrors(f, res.data.errors) : [];
                showNotification(loose[0] || res.data.message || 'Could not approve the application', 'error');
                return;
            }
            var d = res.data;
            $('slApproveStep1').hidden = true; $('slApproveFoot1').hidden = true;
            $('slApproveStep2').hidden = false; $('slApproveFoot2').hidden = false;
            $('slApproveTitle').textContent = 'Seller account created';
            $('slDoneTitle').textContent = d.seller.name + ' is now a TechZone seller';
            $('slDoneSub').textContent = 'Commission ' + body.commission_pct + '% · techzone.pk/sellers/' + d.seller.slug;
            $('slTempPassword').textContent = d.temp_password;
            $('slTempUrl').textContent = d.login_url;
            $('slTempEmail').textContent = d.email;
            $('slOpenSeller').href = adminUrl('manage/seller.php?id=' + parseInt(d.seller_id, 10));
            showNotification('Application approved', 'success');
            load();
        });
    }

    /* ---------------- reject ---------------- */
    function openReject() {
        bootstrap.Offcanvas.getOrCreateInstance($('slDrawer')).hide();
        tzClearErrors($('slRejectForm'));
        $('slRejectReason').value = '';
        $('slRejectTitle').textContent = 'Reject ' + current.shop_name;
        $('slRejectText').textContent = current.owner_name + '’s application moves to Rejected with your reason. No account is created.';
        modal('slRejectModal').show();
    }
    function submitReject(ev) {
        ev.preventDefault();
        var reason = $('slRejectReason').value.trim();
        if (!reason) { tzShowErrors($('slRejectForm'), { reason: 'Give the reason. It stays on the application.' }); return; }
        tzJson('POST', adminUrl('api/sellers.php'), { action: 'reject', application_id: parseInt(current.id, 10), reason: reason }).then(function (res) {
            if (!res.ok) {
                var loose = res.data.errors ? tzShowErrors($('slRejectForm'), res.data.errors) : [];
                showNotification(loose[0] || res.data.message || 'Could not reject the application', 'error');
                return;
            }
            modal('slRejectModal').hide();
            showNotification('Application rejected', 'success');
            load();
        });
    }

    /* ---------------- sellers grid ---------------- */
    function initGrid() {
        grid = createAdminGrid($('slGrid'), {
            storageKey: 'tz.grid.sellers.v1',
            noun: ['seller', 'sellers'],
            emptyText: 'No sellers yet. Approve an application to add the first one.',
            exportName: 'sellers',
            toolbar: { search: '#slSearch', count: '#slCount', exportBtn: '#slExport', resetBtn: '#slReset' },
            columns: [
                { field: 'name', headerName: 'Seller', kind: 'stack', flex: 1, minWidth: 200,
                  cellRendererParams: { sub: function (s) { return [s.market, s.city].filter(Boolean).join(' · '); } },
                  getQuickFilterText: function (p) { return [p.data.name, p.data.market, p.data.city, p.data.account_email].join(' '); } },
                { field: 'products', headerName: 'Products', kind: 'number', width: 104 },
                { field: 'sales_30d', headerName: '30-day sales', kind: 'money', width: 140 },
                { field: 'rating', headerName: 'Rating', width: 96,
                  cellRenderer: function (p) { var s = document.createElement('span'); s.className = 'tabular'; s.textContent = p.value ? (+p.value).toFixed(1) + ' ★' : '—'; return s; } },
                { field: 'status', headerName: 'Status', width: 118,
                  cellRenderer: function (p) { var t = document.createElement('template'); t.innerHTML = sellerPill(p.value); return t.content.firstChild; } },
                { field: 'commission_pct', headerName: 'Commission', width: 116,
                  cellRenderer: function (p) { var s = document.createElement('span'); s.className = 'tabular'; s.textContent = (+p.value).toFixed(p.value % 1 ? 1 : 0) + '%'; return s; } },
                { field: 'payable', headerName: 'Payable Monday', kind: 'money', width: 150 }
            ],
            rowActions: function (s) { return [{ icon: 'fa-arrow-right', label: 'Open ' + s.name, href: adminUrl('manage/seller.php?id=' + parseInt(s.id, 10)) }]; },
            onRowOpen: function (s) { location.href = adminUrl('manage/seller.php?id=' + parseInt(s.id, 10)); }
        });
    }

    function setTab(t, push) {
        tab = t === 'sellers' ? 'sellers' : 'applications';
        document.querySelectorAll('#slTabs [data-tab]').forEach(function (b) {
            var on = b.getAttribute('data-tab') === tab;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $('slApps').hidden = tab !== 'applications';
        $('slSellers').hidden = tab !== 'sellers';
        if (tab === 'sellers' && !grid) { initGrid(); if (D) grid.setRows(D.sellers || []); }
        if (push) { try { var u = new URL(location.href); u.searchParams.set('tab', tab); history.replaceState(null, '', u.toString()); } catch (x) { /* ignore */ } }
    }

    /* ---------------- wire up ---------------- */
    $('slTabs').addEventListener('click', function (ev) { var b = ev.target.closest('[data-tab]'); if (b) setTab(b.getAttribute('data-tab'), true); });
    $('slAppSeg').addEventListener('click', function (ev) {
        var b = ev.target.closest('[data-status]');
        if (!b) return;
        appStatus = b.getAttribute('data-status');
        $('slAppSeg').querySelectorAll('[data-status]').forEach(function (x) { var on = x === b; x.classList.toggle('active', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        if (D) renderApps();
    });
    $('slAppRows').addEventListener('click', function (ev) {
        var tr = ev.target.closest('[data-app]');
        if (tr) openApp(parseInt(tr.getAttribute('data-app'), 10));
    });
    $('slAppRows').addEventListener('keydown', function (ev) {
        if (ev.key !== 'Enter') return;
        var tr = ev.target.closest('[data-app]');
        if (tr) openApp(parseInt(tr.getAttribute('data-app'), 10));
    });
    $('slDrawerFoot').addEventListener('click', function (ev) {
        if (ev.target.closest('[data-app-approve]')) openApprove();
        else if (ev.target.closest('[data-app-reject]')) openReject();
    });
    if ($('slApproveForm')) {
        $('slApproveForm').addEventListener('submit', submitApprove);
        $('slApName').addEventListener('input', function () { if ($('slApSlug').getAttribute('data-auto') === '1') $('slApSlug').value = slugify(this.value); });
        $('slApSlug').addEventListener('input', function () { this.setAttribute('data-auto', '0'); });
        $('slApproveModal').addEventListener('hidden.bs.modal', function () { $('slTempPassword').textContent = ''; });
        $('slTempCopy').addEventListener('click', function () {
            var pw = $('slTempPassword').textContent;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(pw).then(function () { showNotification('Password copied', 'success'); });
            } else {
                var r = document.createRange(); r.selectNodeContents($('slTempPassword'));
                var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
                showNotification('Press Ctrl+C to copy', 'info');
            }
        });
        $('slRejectForm').addEventListener('submit', submitReject);
    }

    var params = new URLSearchParams(location.search);
    setTab(params.get('tab') || 'applications', false);
    load().then(function () {
        var a = parseInt(params.get('application'), 10);
        if (a) {
            var app = (D.applications || []).filter(function (x) { return parseInt(x.id, 10) === a; })[0];
            if (app) { appStatus = app.status; $('slAppSeg').querySelector('[data-status="' + app.status + '"]').click(); openApp(a); }
        }
    });
});
