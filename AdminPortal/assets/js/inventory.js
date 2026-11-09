/* TechZone admin — inventory page (manage/inventory.php). */
(function () {
    'use strict';
    var REASON_LABEL = { received: 'Received', sold: 'Sold', returned: 'Returned', damaged: 'Damaged', correction: 'Correction', cancelled_order: 'Order cancelled' };
    var STATUS_LABEL = { draft: 'Draft', active: 'Active', archived: 'Archived' };
    var rows = [], grid = null, tab = '';
    var $ = function (s) { return document.getElementById(s); };

    function productUrl(id) { return adminUrl('manage/product.php?id=' + encodeURIComponent(id)); }
    function stateOf(r) { return r.stock_state; }

    function coverCell(p) {
        var s = document.createElement('span');
        var v = p.data.days_cover;
        if (v === null || v === undefined) { s.className = 'muted'; s.textContent = '—'; s.title = 'No sales in the last 30 days'; return s; }
        s.className = 'prd-cover' + (v < 7 ? ' short' : (v < 21 ? ' mid' : ''));
        s.textContent = v >= 365 ? '365+ days' : formatNumber(v) + (v === 1 ? ' day' : ' days');
        return s;
    }

    function renderRecent(list) {
        var tb = $('invRecent');
        if (!list.length) { tb.innerHTML = emptyRow(6, 'No stock movements yet.'); return; }
        tb.innerHTML = list.map(function (a) {
            var d = parseInt(a.delta, 10);
            var ref = a.reference || '';
            var m = /^order #(\d+)$/i.exec(ref);
            var refHtml = m ? '<a href="' + escapeHtml(adminUrl('manage/order.php?id=' + m[1])) + '">Order #' + escapeHtml(m[1]) + '</a>' : escapeHtml(ref || '—');
            return '<tr data-href="' + escapeHtml(productUrl(a.product_id)) + '"><td class="nowrap ink-2">' + escapeHtml(formatDateTime(a.created_at)) + '</td>' +
                '<td><a class="cell-main" href="' + escapeHtml(productUrl(a.product_id)) + '">' + escapeHtml(a.product_name) + '</a>' +
                '<div class="cell-sub">' + escapeHtml(a.sku || '') + (a.variation_value ? ' · ' + escapeHtml(a.variation_value) : '') + '</div></td>' +
                '<td class="num"><span class="prd-delta ' + (d > 0 ? 'up' : 'down') + '">' + (d > 0 ? '+' : '−') + formatNumber(Math.abs(d)) + '</span></td>' +
                '<td><span class="prd-reason r-' + escapeHtml(a.reason) + '">' + escapeHtml(REASON_LABEL[a.reason] || a.reason) + '</span></td>' +
                '<td class="ink-2 prd-hide-sm cell-clip" title="' + escapeHtml(ref) + '">' + refHtml + '</td>' +
                '<td class="ink-2 prd-hide-sm nowrap">' + escapeHtml(a.admin_name || (a.reason === 'sold' || a.reason === 'cancelled_order' ? 'Storefront' : 'System')) + '</td></tr>';
        }).join('');
    }

    function counts() {
        $('invCountAll').textContent = formatNumber(rows.length);
        $('invCountLow').textContent = formatNumber(rows.filter(function (r) { return stateOf(r) === 'low'; }).length);
        $('invCountOut').textContent = formatNumber(rows.filter(function (r) { return stateOf(r) === 'out'; }).length);
    }

    function load() {
        return tzJson('GET', adminUrl('api/inventory.php')).then(function (res) {
            if (!res.ok) { grid.setRows([]); showNotification(res.data.message || 'Could not load stock levels', 'error'); return; }
            rows = res.data.products || [];
            grid.setRows(rows);
            counts();
            renderRecent(res.data.adjustments || []);
        });
    }

    function setTab(t) {
        tab = t;
        document.querySelectorAll('[data-inv-tab]').forEach(function (b) {
            var on = b.getAttribute('data-inv-tab') === t;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        grid.api.onFilterChanged();
        try { history.replaceState(null, '', location.pathname + (t ? '?tab=' + t : '')); } catch (e) { /* ignore */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var canAdjust = tzCan('inventory.adjust');
        grid = createAdminGrid($('inventoryTable'), {
            storageKey: 'tz.grid.inventory.v1',
            rowHeight: 52,
            noun: ['product', 'products'],
            emptyText: 'Nothing here. Products appear in this tab when their stock reaches the threshold.',
            exportName: 'inventory',
            toolbar: { search: '#invSearch', count: '#invCount', exportBtn: '#invExport', resetBtn: '#invReset' },
            columns: [
                { field: 'name', headerName: 'Product', kind: 'thumb', flex: 1, minWidth: 250, mobileWidth: 160,
                  cellRendererParams: { wrap: true, icon: 'fa-tag', image: function (r) { return r.imagelink || null; }, sub: function (r) { return (r.sku || '') + (r.category_name ? ' · ' + r.category_name : ''); } },
                  getQuickFilterText: function (p) { return p.data.name + ' ' + (p.data.sku || ''); } },
                { field: 'inventory', headerName: 'Stock', kind: 'stock', width: 110,
                  cellRenderer: function (p) { var t = document.createElement('template'); t.innerHTML = stockCell(p.value, p.data.low_stock_threshold); return t.content.firstChild; } },
                { field: 'low_stock_threshold', headerName: 'Alert at', kind: 'number', width: 104 },
                { colId: 'state', headerName: 'Level', kind: 'status', width: 128,
                  valueGetter: function (p) { return { ok: 'In stock', low: 'Low stock', out: 'Out of stock' }[stateOf(p.data)]; },
                  cellRenderer: function (p) {
                      var tone = { ok: 'ok', low: 'warn', out: 'danger' }[stateOf(p.data)];
                      var w = document.createElement('span'); w.className = 'cell-pill';
                      w.innerHTML = '<span class="pill pill-' + tone + '">' + escapeHtml(p.value) + '</span>';
                      return w;
                  } },
                { field: 'sold_30d', headerName: 'Sold 30 days', kind: 'number', width: 124 },
                { field: 'days_cover', headerName: 'Days of cover', kind: 'number', width: 136, cellRenderer: coverCell,
                  comparator: function (a, b) { return (a === null ? 1e9 : +a) - (b === null ? 1e9 : +b); },
                  csvValue: function (r) { return r.days_cover === null ? '' : r.days_cover; } },
                { colId: 'status', headerName: 'Status', kind: 'status', width: 112, hide: true,
                  valueGetter: function (p) { return STATUS_LABEL[p.data.status] || p.data.status; } }
            ],
            rowActions: function (r) {
                var id = parseInt(r.id, 10);
                return [
                    canAdjust && { icon: 'fa-plus-minus', label: 'Adjust stock', attrs: 'data-adjust="' + id + '"' },
                    { icon: 'fa-arrow-right', label: 'Open product', href: productUrl(id), attrs: 'data-open="' + id + '"' }
                ];
            },
            onRowOpen: function (r) { location.href = productUrl(r.id); }
        });
        grid.api.setGridOption('isExternalFilterPresent', function () { return tab !== ''; });
        grid.api.setGridOption('doesExternalFilterPass', function (n) { return stateOf(n.data) === tab; });

        document.querySelector('.prd-inv-tabs').addEventListener('click', function (e) {
            var b = e.target.closest('[data-inv-tab]');
            if (b) setTab(b.getAttribute('data-inv-tab'));
        });
        $('inventoryTable').addEventListener('click', function (e) {
            var a = e.target.closest('[data-adjust]');
            if (a) {
                var r = rows.filter(function (x) { return x.id == a.getAttribute('data-adjust'); })[0];
                if (r) openStockAdjust(r, function () { load(); });
                return;
            }
            var o = e.target.closest('button[data-open]');
            if (o) location.href = productUrl(o.getAttribute('data-open'));
        });
        $('invRecent').addEventListener('click', function (e) {
            if (e.target.closest('a')) return;
            var tr = e.target.closest('tr[data-href]');
            if (tr) location.href = tr.getAttribute('data-href');
        });
        var t = new URLSearchParams(location.search).get('tab');
        load().then(function () { if (t === 'low' || t === 'out') setTab(t); });
    });
})();
