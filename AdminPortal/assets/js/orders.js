/* TechZone admin — orders list: tabs with counts, AG Grid, bulk "Mark as …". Row click opens the order page. */
var orders = [];
var ordersGrid = null;
var ordTab = 'open';
var ordLoaded = false;

var ORD_TABS = {
    open: function (o) { return o.status !== 'Delivered' && o.status !== 'Cancelled'; },
    unfulfilled: function (o) { return o.status !== 'Cancelled' && o.fulfillment_status !== 'fulfilled'; },
    unpaid: function (o) { return o.status !== 'Cancelled' && o.payment_status === 'pending'; },
    completed: function (o) { return o.status === 'Delivered'; },
    cancelled: function (o) { return o.status === 'Cancelled'; },
    all: function () { return true; }
};
var ORD_EMPTY = {
    open: 'No open orders. New orders appear here the moment they’re placed.',
    unfulfilled: 'No unfulfilled orders. New orders appear here the moment they’re placed.',
    unpaid: 'No payments to collect.',
    completed: 'No delivered orders yet.',
    cancelled: 'No cancelled orders.',
    all: 'No orders yet.'
};

function paymentLabel(method) {
    var m = String(method || 'cash_on_delivery');
    if (m === 'cash_on_delivery') return 'Cash on delivery';
    if (m === 'jazzcash') return 'JazzCash';
    if (m === 'easypaisa') return 'Easypaisa';
    if (m === 'bank_transfer') return 'Bank transfer';
    return m.replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
}

/** POST one staff action ({id, action, …}) through the wrapped fetch (adds X-CSRF-Token). Resolves true/false. */
function postOrderAction(id, action, extra) {
    var body = Object.assign({ id: parseInt(id, 10), action: action }, extra || {});
    return fetch(adminUrl('api/orders.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    }).then(function (r) { return r.json(); }).then(function (d) { return !!d.success; }, function () { return false; });
}

/* ---- cell renderers (DOM + textContent only) ---- */
function ordEl(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = String(text);
    return n;
}
function ordOrderCell(p) {
    var o = p.data || {};
    var w = ordEl('div', 'txt grid-stack');
    w.appendChild(ordEl('div', 'cell-main id-cell', '#' + o.id));
    var n = parseInt(o.items_count, 10) || 0;
    w.appendChild(ordEl('div', 'cell-sub', n + (n === 1 ? ' item' : ' items')));
    return w;
}
function ordPaymentCell(p) {
    var o = p.data || {};
    var w = ordEl('div', 'ord-paycell');
    var pill = document.createElement('span');
    pill.innerHTML = statusPill(o.payment_status);
    w.appendChild(pill.firstChild);
    w.appendChild(ordEl('span', 'cell-sub', paymentLabel(o.payment_method)));
    return w;
}

function ordSetTab(tab, push) {
    if (!ORD_TABS[tab]) tab = 'open';
    ordTab = tab;
    document.querySelectorAll('#orderTabs [data-tab]').forEach(function (b) {
        var on = b.getAttribute('data-tab') === tab;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (push) {
        try {
            var u = new URL(location.href);
            u.searchParams.set('tab', tab);
            history.replaceState(null, '', u.toString());
        } catch (e) { /* ignore */ }
    }
    ordRender();
}

function ordRender() {
    if (!ordersGrid || !ordLoaded) return;
    Object.keys(ORD_TABS).forEach(function (k) {
        var c = document.querySelector('[data-count="' + k + '"]');
        if (c) c.textContent = formatNumber(orders.filter(ORD_TABS[k]).length);
    });
    var rows = orders.filter(ORD_TABS[ordTab]);
    ordersGrid.api.setGridOption('localeText', { noRowsToShow: ORD_EMPTY[ordTab], noMatchingRows: 'Nothing matches this search.' });
    ordersGrid.api.setGridOption('overlayNoRowsTemplate', '<div class="grid-empty"><span class="empty-state-icon"><i class="fas fa-inbox"></i></span><div>' + escapeHtml(ORD_EMPTY[ordTab]) + '</div></div>');
    ordersGrid.setRows(rows);
}

/** Bulk cancel: ask once for the reason. Resolves the reason text, or null when dismissed. */
function ordAskCancelReason(rows) {
    return new Promise(function (resolve) {
        var m = document.getElementById('bulkCancelModal');
        var form = document.getElementById('bulkCancelForm');
        var sel = document.getElementById('bulkCancelReason');
        var other = document.getElementById('bulkCancelOther');
        var wrap = document.getElementById('bulkCancelOtherWrap');
        var units = rows.reduce(function (s, o) { return s + (parseInt(o.items_count, 10) || 0); }, 0);
        document.getElementById('bulkCancelText').textContent = 'Cancelling ' + rows.length + (rows.length === 1 ? ' order' : ' orders') +
            ' restocks ' + units + (units === 1 ? ' item' : ' items') + '. Shipped or delivered orders are skipped.';
        sel.value = 'Customer request';
        other.value = '';
        wrap.hidden = true;
        other.classList.remove('is-invalid');
        sel.onchange = function () { wrap.hidden = sel.value !== 'Other'; if (!wrap.hidden) other.focus(); };
        var done = false;
        var modal = bootstrap.Modal.getOrCreateInstance(m);
        form.onsubmit = function (e) {
            e.preventDefault();
            var reason = sel.value;
            if (reason === 'Other') {
                if (!other.value.trim()) { other.classList.add('is-invalid'); other.focus(); return; }
                reason = 'Other: ' + other.value.trim();
            }
            done = true;
            modal.hide();
            resolve(reason);
        };
        m.addEventListener('hidden.bs.modal', function h() {
            m.removeEventListener('hidden.bs.modal', h);
            if (!done) resolve(null);
        });
        modal.show();
    });
}

window.onload = function () {
    var params = new URLSearchParams(location.search);
    ordTab = ORD_TABS[params.get('tab')] ? params.get('tab') : 'open';

    var BULK = [
        { label: 'Confirmed', value: 'confirm', skip: function (o) { return o.status !== 'Pending'; } },
        { label: 'Fulfilled', value: 'fulfil', skip: function (o) { return o.fulfillment_status === 'fulfilled'; } },
        { label: 'Delivered', value: 'deliver', skip: function (o) { return o.status === 'Delivered'; } }
    ];
    var DONE = { confirm: 'confirmed', fulfil: 'fulfilled', deliver: 'marked delivered' };

    var bulkActions = !tzCan('orders.fulfil') ? [] : [{
        label: 'Mark as', icon: 'fa-check-double',
        menu: BULK.map(function (b) { return { label: b.label, value: b.value }; }),
        progress: 'Updating',
        perRow: function (o, action) {
            var b = BULK.filter(function (x) { return x.value === action; })[0];
            if (o.status === 'Cancelled') return false;
            return b && b.skip(o) ? true : postOrderAction(o.id, action);
        },
        done: function (ok, failed, action) {
            return ok + ' ' + (ok === 1 ? 'order' : 'orders') + ' ' + (DONE[action] || 'updated') + (failed ? ', ' + failed + ' could not be changed' : '');
        }
    }];
    if (tzCan('orders.cancel')) {
        bulkActions.push({
            label: 'Cancel…', icon: 'fa-ban', tone: 'danger',
            before: function (rows) { return ordAskCancelReason(rows); },
            progress: 'Cancelling',
            perRow: function (o, reason) {
                if (o.status === 'Cancelled') return true;
                if (o.status === 'Shipped' || o.status === 'Delivered') return false;
                return postOrderAction(o.id, 'cancel', { reason: reason });
            },
            done: function (ok, failed) {
                return ok + ' ' + (ok === 1 ? 'order' : 'orders') + ' cancelled' + (failed ? ', ' + failed + ' skipped (already shipped)' : '');
            }
        });
    }

    ordersGrid = createAdminGrid(document.getElementById('ordersTable'), {
        storageKey: 'tz.grid.orders.v2',
        noun: ['order', 'orders'],
        emptyText: ORD_EMPTY[ordTab],
        exportName: 'orders',
        toolbar: { search: '#orderSearch', count: '#orderCount', exportBtn: '#ordersExport', resetBtn: '#ordersReset' },
        selectable: true,
        columns: [
            { field: 'id', headerName: 'Order', kind: 'id', width: 112, mobileWidth: 100, cellRenderer: ordOrderCell,
              getQuickFilterText: function (p) { return '#' + p.data.id + ' ' + p.data.id; } },
            { field: 'customer_name', headerName: 'Customer', kind: 'stack', flex: 1, minWidth: 200,
              cellRendererParams: { sub: function (o) { return o.city || ''; } },
              getQuickFilterText: function (p) { var o = p.data; return [o.customer_name, o.customer_email, o.customer_phone, o.city].join(' '); },
              csvValue: function (o) { return o.customer_name; } },
            { field: 'city', headerName: 'City', kind: 'muted', width: 130, hide: true },
            { field: 'customer_phone', headerName: 'Phone', kind: 'muted', width: 140, hide: true },
            { field: 'order_date', headerName: 'Date', kind: 'date', width: 130, sort: 'desc' },
            { field: 'payment_status', headerName: 'Payment', kind: 'status', width: 176, cellRenderer: ordPaymentCell,
              getQuickFilterText: function (p) { return p.data.payment_status + ' ' + paymentLabel(p.data.payment_method); },
              csvValue: function (o) { return o.payment_status + ' (' + paymentLabel(o.payment_method) + ')'; } },
            { field: 'fulfillment_status', headerName: 'Fulfilment', kind: 'status', width: 132 },
            { field: 'status', headerName: 'Status', kind: 'status', width: 128 },
            { field: 'total', headerName: 'Total', kind: 'money', width: 130 }
        ],
        rowActions: function (o) {
            return [{ icon: 'fa-arrow-right', label: 'Open order #' + o.id, attrs: 'data-open-order="' + parseInt(o.id, 10) + '"' }];
        },
        onRowOpen: function (o) { location.href = adminUrl('manage/order.php?id=' + parseInt(o.id, 10)); },
        bulkActions: bulkActions,
        onBulkDone: function () { loadOrders(); }
    });

    document.getElementById('ordersTable').addEventListener('click', function (e) {
        var v = e.target.closest('[data-open-order]');
        if (v) location.href = adminUrl('manage/order.php?id=' + parseInt(v.getAttribute('data-open-order'), 10));
    });
    document.getElementById('orderTabs').addEventListener('click', function (e) {
        var b = e.target.closest('[data-tab]');
        if (b) ordSetTab(b.getAttribute('data-tab'), true);
    });

    var q = params.get('q');
    if (q) {
        var s = document.getElementById('orderSearch');
        s.value = q;
        ordersGrid.api.setGridOption('quickFilterText', q);
        if (!params.get('tab')) ordTab = 'all';
    }
    ordSetTab(ordTab, false);
    loadOrders();
};

function loadOrders() {
    return fetch(adminUrl('api/orders.php'))
        .then(function (response) { return response.json(); })
        .then(function (data) {
            ordLoaded = true;
            if (data.success) {
                orders = data.orders || [];
            } else {
                orders = [];
                showNotification(data.message || 'Could not load orders', 'error');
            }
            ordRender();
        })
        .catch(function () {
            ordLoaded = true;
            orders = [];
            ordRender();
            showNotification('Could not load orders', 'error');
        });
}
