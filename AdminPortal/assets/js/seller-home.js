/* Seller portal Home: sales today / 7 / 30 days, orders to fulfil, low stock of their products and the next
 * payout (api/dashboard_stats.php -> seller_home). Every value reaches the DOM through escapeHtml()/textContent. */
(function () {
    'use strict';
    function e(v) { return escapeHtml(v); }
    function $(id) { return document.getElementById(id); }
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : (many || one + 's')); }
    function day(d) {
        var t = parseDate(d);
        return t ? t.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }) : '';
    }

    function kpi(id, v) {
        $(id).textContent = formatPKR(v.revenue || 0);
        $(id + 'Sub').textContent = v.orders ? plural(v.orders, 'order') + ' · ' + plural(v.units, 'item') : 'No orders yet';
    }

    function render(d) {
        kpi('sToday', d.sales.today);
        kpi('s7', d.sales.d7);
        kpi('s30', d.sales.d30);

        var p = d.payout || {}, due = p.due || {}, wk = p.this_week || {};
        var isToday = !!due.date && due.date === p.today;
        $('sDueLabel').textContent = 'Payout due ' + (isToday ? 'today' : day(due.date));
        $('sDue').textContent = formatPKR(due.net || 0);
        $('sDueSub').textContent = due.weeks ? plural(due.weeks, 'closed week') + ' after 8% commission' : 'Nothing due; this week so far ' + formatPKR(wk.net || 0);

        document.querySelectorAll('[data-todo]').forEach(function (card) {
            var n = card.getAttribute('data-todo') === 'to_fulfil' ? (d.to_fulfil || {}).count || 0 : (d.low_stock || []).length;
            card.querySelector('[data-n]').textContent = formatNumber(n);
            var lbl = card.querySelector('.home-card-label');
            lbl.textContent = n === 1 ? lbl.getAttribute('data-one') : lbl.getAttribute('data-many');
            card.classList.toggle('is-zero', n === 0);
        });

        var rows = d.recent_orders || [];
        $('slRecent').innerHTML = rows.length ? rows.map(function (o) {
            return '<tr data-href="' + e(adminUrl('manage/order.php?id=' + parseInt(o.id, 10))) + '">' +
                '<td><a class="id-cell" href="' + e(adminUrl('manage/order.php?id=' + parseInt(o.id, 10))) + '">#' + parseInt(o.id, 10) + '</a><div class="cell-sub">' + e(formatDate(o.order_date)) + '</div></td>' +
                '<td><div class="cell-main">' + e(o.customer_name) + '</div><div class="cell-sub">' + e(o.city || '') + '</div></td>' +
                '<td>' + statusPill(o.fulfillment_status) + (o.status === 'Cancelled' ? ' ' + statusPill('cancelled') : '') + '</td>' +
                '<td class="num tabular">' + formatPKR(o.total) + '<div class="cell-sub">' + e(plural(o.items_count, 'item')) + '</div></td></tr>';
        }).join('') : '<tr><td colspan="4" class="table-empty">No orders with your products yet. They appear here the moment a customer orders.</td></tr>';

        var h = '<dl class="sl-pay">';
        h += '<div><dt>Due ' + e(isToday ? 'today' : day(due.date)) + '</dt><dd class="tabular sl-pay-big">' + formatPKR(due.net || 0) + '</dd></div>';
        if (due.weeks) {
            h += '<div><dt>Gross delivered</dt><dd class="tabular">' + formatPKR(due.gross) + '</dd></div>';
            h += '<div><dt>Commission</dt><dd class="tabular">−' + formatPKR(due.commission) + '</dd></div>';
        }
        h += '<div><dt>This week so far</dt><dd class="tabular">' + formatPKR(wk.net || 0) + (wk.date ? ' <span class="cell-sub">paid ' + e(day(wk.date)) + '</span>' : '') + '</dd></div>';
        if (p.paid_last) h += '<div><dt>Last paid</dt><dd class="tabular">' + formatPKR(p.paid_last.net) + ' <span class="cell-sub">' + e(formatDate(p.paid_last.date)) + '</span></dd></div>';
        h += '</dl><p class="sl-hint">Payouts cover items delivered Monday to Sunday and are paid the following Monday, after TechZone’s commission.</p>';
        $('slPayout').innerHTML = h;

        var low = d.low_stock || [];
        $('slLow').innerHTML = low.length ? low.map(function (r) {
            return '<li><a class="cell-main" href="' + e(adminUrl('manage/product.php?id=' + parseInt(r.id, 10))) + '">' + e(r.name) + '</a>' +
                '<span class="cell-sub ms-auto">' + e(r.sku || '') + '</span>' + stockCell(r.inventory, r.threshold) + '</li>';
        }).join('') : '<li class="muted">All your products are above their low-stock threshold.</li>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        fetch(adminUrl('api/dashboard_stats.php'), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success || !d.seller_home) { showNotification(d.message || 'Could not load Home', 'error'); return; }
                render(d);
            })
            .catch(function () { showNotification('Could not load Home', 'error'); });
        $('slRecent').addEventListener('click', function (ev) {
            var tr = ev.target.closest('tr[data-href]');
            if (tr && !ev.target.closest('a')) location.href = tr.getAttribute('data-href');
        });
    });
})();
