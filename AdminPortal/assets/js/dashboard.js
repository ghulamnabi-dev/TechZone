/* Home: today vs yesterday, to-do cards, 30-day sales with a 7-day moving average, payment mix,
 * top products, recent orders and recent activity (api/dashboard_stats.php). Light tokens only. */
(function () {
    'use strict';
    var T = { ink: '#1a1d23', ink2: '#5c6370', ink3: '#8a909c', line: '#eef0f3', bar: '#d3d6dc', barHover: '#b9bdc5', brand: '#ffd400', surface: '#ffffff' };
    var ROLE = { owner: 'Owner', manager: 'Manager', staff: 'Staff' };
    function $(id) { return document.getElementById(id); }

    document.addEventListener('DOMContentLoaded', function () {
        fetch(adminUrl('api/dashboard_stats.php'), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) { showNotification(d.message || 'Could not load Home', 'error'); return; }
                today(d.today || {});
                todo(d.todo || {});
                mix(d.payment_mix || []);
                top(d.top_products || []);
                orders(d.recent_orders || []);
                if ($('recentActivity')) activity(d.recent_activity || []);
                if (window.Chart) sales(d.sales || []);
            })
            .catch(function () { showNotification('Could not load Home', 'error'); });
    });

    /** "▲ 25% vs yesterday by now" — or a plain comparison when yesterday was zero */
    function delta(cur, prev, fmt) {
        if (!prev && !cur) return 'Nothing yet, none by now yesterday either';
        if (!prev) return fmt(prev) + ' by now yesterday';
        var pct = (cur - prev) / prev * 100;
        var up = pct >= 0;
        return '<span class="delta ' + (up ? 'up' : 'down') + '">' + (up ? '▲ ' : '▼ ') + Math.abs(pct).toFixed(0) + '%</span> vs ' + fmt(prev) + ' by now yesterday';
    }
    function today(t) {
        var y = t.yesterday || {};
        $('kOrders').textContent = formatNumber(t.orders);
        $('kOrdersSub').innerHTML = delta(+t.orders || 0, +y.orders || 0, function (n) { return formatNumber(n); });
        $('kRevenue').textContent = formatPKR(t.revenue);
        $('kRevenueSub').innerHTML = delta(+t.revenue || 0, +y.revenue || 0, formatPKR);
        $('kAov').textContent = t.orders ? formatPKR(t.aov) : '—';
        $('kAovSub').textContent = t.orders ? 'Across ' + t.orders + ' order' + (t.orders === 1 ? '' : 's') + ' today' : 'No orders yet today';
        $('kCollected').textContent = formatPKR(t.collected);
    }
    function todo(c) {
        document.querySelectorAll('[data-todo]').forEach(function (a) {
            var n = +c[a.getAttribute('data-todo')] || 0;
            a.querySelector('[data-n]').textContent = formatNumber(n);
            var lbl = a.querySelector('.home-card-label');
            lbl.textContent = n === 1 ? lbl.getAttribute('data-one') : lbl.getAttribute('data-many');
            a.classList.toggle('is-clear', n === 0);
            a.setAttribute('aria-label', n + ' ' + lbl.textContent);
        });
    }
    function mix(rows) {
        var box = $('paymentMix');
        var now = new Date();
        $('mixMonth').textContent = MONTHS_SHORT[now.getMonth()] + ' ' + now.getFullYear();
        var total = rows.reduce(function (a, r) { return a + (+r.revenue || 0); }, 0);
        var orders = rows.reduce(function (a, r) { return a + (+r.orders || 0); }, 0);
        if (!total) { box.innerHTML = '<div class="empty-state home-empty"><div class="empty-state-text">No orders this month yet. The split between cash on delivery and JazzCash shows here.</div></div>'; return; }
        var tone = { cash_on_delivery: 'home-mix-cod', jazzcash: 'home-mix-jazz' };
        box.innerHTML = '<div class="home-mix-total"><strong class="tabular">' + formatPKR(total) + '</strong><span>' + formatNumber(orders) + ' order' + (orders === 1 ? '' : 's') + ' · cancelled excluded</span></div>' +
            '<div class="home-mix-bar" role="img" aria-label="' + escapeHtml(rows.map(function (r) { return r.label + ' ' + Math.round(r.revenue / total * 100) + '%'; }).join(', ')) + '">' +
            rows.map(function (r) { return '<span class="' + (tone[r.method] || 'home-mix-other') + '" style="width:' + (r.revenue / total * 100).toFixed(2) + '%"></span>'; }).join('') + '</div>' +
            '<ul class="home-mix-list">' + rows.map(function (r) {
                return '<li><span class="home-sw ' + (tone[r.method] || 'home-mix-other') + '"></span><span class="lbl">' + escapeHtml(r.label) + '</span>' +
                    '<span class="pct tabular">' + Math.round(r.revenue / total * 100) + '%</span>' +
                    '<span class="val tabular">' + formatPKR(r.revenue) + '<small>' + formatNumber(r.orders) + ' order' + (r.orders === 1 ? '' : 's') + '</small></span></li>';
            }).join('') + '</ul>';
    }
    function top(list) {
        var el = $('topProducts');
        if (!list.length) { el.innerHTML = '<li class="muted">No sales in the last 30 days.</li>'; return; }
        el.innerHTML = list.map(function (p, i) {
            return '<li><span class="rank-n">' + (i + 1) + '</span>' +
                '<div class="rank-main"><a class="home-link" href="' + escapeHtml(adminUrl('manage/product.php?id=' + p.id)) + '"><strong title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</strong></a>' +
                '<small>' + formatNumber(p.units) + ' sold' + (p.sku ? ' · ' + escapeHtml(p.sku) : '') + '</small></div>' +
                '<span class="rank-val">' + formatPKR(p.revenue) + '</span></li>';
        }).join('');
    }
    function orders(list) {
        var tb = $('recentOrders');
        if (!list.length) { tb.innerHTML = emptyRow(5, 'No orders yet. New orders appear here the moment they\'re placed.'); return; }
        tb.innerHTML = list.map(function (o) {
            var href = escapeHtml(adminUrl('manage/order.php?id=' + o.id));
            var pay = o.status === 'Cancelled' ? statusPill('cancelled') : statusPill(o.payment_status === 'pending' ? 'unpaid' : o.payment_status);
            var ful = o.status === 'Cancelled' ? '<span class="muted">—</span>' : statusPill(o.fulfillment_status);
            return '<tr data-href="' + href + '">' +
                '<td class="id-cell"><a class="home-link" href="' + href + '">#' + escapeHtml(o.id) + '</a><div class="cell-sub">' + escapeHtml(formatDateTime(o.order_date)) + '</div></td>' +
                '<td><div class="cell-main w500">' + escapeHtml(o.customer_name) + '</div><div class="cell-sub">' + escapeHtml(o.city || '') + ' · ' + (o.payment_method === 'jazzcash' ? 'JazzCash' : 'COD') + '</div></td>' +
                '<td>' + pay + '</td><td>' + ful + '</td>' +
                '<td class="num tabular">' + formatPKR(o.total) + '</td></tr>';
        }).join('');
        tb.onclick = function (e) {
            if (e.target.closest('a')) return;
            var tr = e.target.closest('tr[data-href]');
            if (tr) location.href = tr.getAttribute('data-href');
        };
    }
    function ago(v) {
        var d = parseDate(v);
        if (!d) return '';
        var s = (Date.now() - d.getTime()) / 1000;
        if (s < 3600) return Math.max(1, Math.floor(s / 60)) + ' min ago';
        if (s < 86400) return Math.floor(s / 3600) + ' h ago';
        if (s < 86400 * 7) return Math.floor(s / 86400) + ' d ago';
        return formatDate(d);
    }
    function activity(list) {
        var el = $('recentActivity');
        if (!list.length) { el.innerHTML = '<li class="muted">No activity yet. Every change staff make shows up here.</li>'; return; }
        el.innerHTML = list.map(function (a) {
            var who = a.actor_name || 'System';
            return '<li><span class="grid-avatar" aria-hidden="true">' + escapeHtml(who.charAt(0).toUpperCase()) + '</span>' +
                '<div class="home-act-main"><span class="home-act-sum">' + escapeHtml(a.summary) + '</span>' +
                '<small>' + escapeHtml(who) + (a.actor_role ? ' · ' + escapeHtml(ROLE[a.actor_role] || a.actor_role) : '') + ' · <time datetime="' + escapeHtml(a.created_at) + '" title="' + escapeHtml(formatDateTime(a.created_at)) + '">' + escapeHtml(ago(a.created_at)) + '</time></small></div></li>';
        }).join('');
    }

    function compact(v) {
        if (v >= 1e6) return 'Rs ' + (v / 1e6).toFixed(1).replace(/\.0$/, '') + 'M';
        if (v >= 1e3) return 'Rs ' + Math.round(v / 1e3) + 'k';
        return 'Rs ' + v;
    }
    function sales(rows) {
        var values = rows.map(function (r) { return +r.revenue || 0; });
        var ma = values.map(function (_, i) {
            var from = Math.max(0, i - 6), s = 0;
            for (var j = from; j <= i; j++) s += values[j];
            return Math.round(s / (i - from + 1));
        });
        var labels = rows.map(function (r) { var d = parseDate(r.date); return d.getDate() + ' ' + MONTHS_SHORT[d.getMonth()]; });
        var total = values.reduce(function (a, b) { return a + b; }, 0);
        var n = rows.reduce(function (a, r) { return a + (+r.orders || 0); }, 0);
        $('salesTotal').textContent = formatPKR(total) + ' · ' + formatNumber(n) + ' orders';

        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.font.size = 11;
        Chart.defaults.color = T.ink3;
        var tt = Chart.defaults.plugins.tooltip;
        tt.backgroundColor = T.ink; tt.titleColor = '#fff'; tt.bodyColor = '#e1e3e8'; tt.padding = 10; tt.cornerRadius = 6; tt.boxPadding = 4;

        new Chart($('salesChart'), {
            data: {
                labels: labels,
                datasets: [
                    { type: 'line', label: '7-day average', data: ma, borderColor: T.ink, backgroundColor: T.ink, borderWidth: 2, tension: .35, cubicInterpolationMode: 'monotone',
                      pointRadius: 0, pointHoverRadius: 4, pointHoverBackgroundColor: T.ink, pointHoverBorderColor: T.brand, pointHoverBorderWidth: 2, order: 1 },
                    { type: 'bar', label: 'Daily', data: values, backgroundColor: T.bar, hoverBackgroundColor: T.barHover, borderRadius: 3, maxBarThickness: 18, order: 2 }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: {
                        title: function (items) { return formatDate(rows[items[0].dataIndex].date); },
                        label: function (c) { return c.dataset.label + ': ' + formatPKR(c.parsed.y) + (c.dataset.type === 'bar' ? ' (' + rows[c.dataIndex].orders + ' orders)' : ''); }
                    } }
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
                    y: { beginAtZero: true, grid: { color: T.line, drawTicks: false }, border: { display: false }, ticks: { padding: 8, maxTicksLimit: 5, callback: compact } }
                }
            }
        });
    }
})();
