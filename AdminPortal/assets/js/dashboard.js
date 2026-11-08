/* Dashboard: KPIs, sales area chart, category donut, recent orders, top sellers, low stock. */
var TOKENS = {
    accent: '#7c6cf6', line: 'rgba(255,255,255,.08)', ink: '#e7eaf3', ink2: '#a7afc4', ink3: '#6f7891',
    surface: '#171d2f', surface2: '#1e2538', gray: '#4a5168'
};
// sequential tint ramp of the accent (light → deep); the last slice is always neutral gray
var DONUT_RAMP = ['#c9c2fd', '#b0a6fb', '#968af8', '#7c6cf6', '#6758dc', '#5546bd', '#43379a'];

window.addEventListener('load', loadDashboard);

function loadDashboard() {
    fetch(adminUrl('api/dashboard_stats.php'))
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { showNotification(data.message || 'Could not load dashboard', 'error'); return; }
            renderKpis(data);
            renderRecentOrders(data.recentOrders || []);
            renderTopProducts(data.topProducts || []);
            renderLowStock(data.lowStockProducts || []);
            if (window.Chart) {
                applyChartDefaults();
                createSalesChart((data.monthlySales || []).slice());
                createCategoryChart(data.categoryData || []);
            }
        })
        .catch(function () { showNotification('Could not load dashboard data', 'error'); });
}

function monthlyAsc(monthlySales) {
    return monthlySales.slice().sort(function (a, b) { return (a.year - b.year) || (a.month - b.month); });
}

function renderKpis(data) {
    var s = data.stats || {};
    var months = monthlyAsc(data.monthlySales || []);
    var orders = Number(s.totalOrders) || 0;
    var revenue = Number(s.totalSales) || 0;

    document.getElementById('totalSales').textContent = formatPKR(revenue);
    document.getElementById('totalOrders').textContent = formatNumber(orders);
    document.getElementById('totalCustomers').textContent = formatNumber(s.totalCustomers);
    document.getElementById('totalProducts').textContent = formatNumber(s.totalProducts);

    var salesSub = document.getElementById('salesSub');
    if (months.length >= 2) {
        var cur = months[months.length - 1], prev = months[months.length - 2];
        var c = Number(cur.sales) || 0, p = Number(prev.sales) || 0;
        var html = formatPKR(c) + ' in ' + MONTHS_SHORT[cur.month - 1];
        if (p > 0) {
            var pct = (c - p) / p * 100;
            html += ' <span class="delta ' + (pct >= 0 ? 'up' : 'down') + '">' + (pct >= 0 ? '▲ ' : '▼ ') + Math.abs(pct).toFixed(0) + '%</span> vs ' + MONTHS_SHORT[prev.month - 1];
        }
        salesSub.innerHTML = html;
    } else {
        salesSub.textContent = 'All-time gross sales';
    }

    document.getElementById('ordersSub').textContent = orders > 0 ? 'Avg ' + formatPKR(revenue / orders) + ' per order' : 'No orders yet';
    var cust = Number(s.totalCustomers) || 0;
    document.getElementById('customersSub').textContent = cust > 0 ? (orders / cust).toFixed(1) + ' orders per customer' : 'Registered accounts';
    var low = (data.lowStockProducts || []).length;
    document.getElementById('productsSub').innerHTML = formatNumber(s.totalCategories) + ' categories · ' +
        (low > 0 ? '<span class="text-warning">' + low + ' low stock</span>' : 'stock healthy');
}

function renderRecentOrders(list) {
    var tbody = document.getElementById('recentOrdersTable');
    if (!list.length) { tbody.innerHTML = emptyRow(5, 'No orders yet'); return; }
    tbody.innerHTML = list.map(function (o) {
        return '<tr>' +
            '<td class="id-cell">#' + escapeHtml(o.id) + '</td>' +
            '<td><div class="cell-main" style="font-weight:500">' + escapeHtml(o.customer_name) + '</div><div class="cell-sub">' + escapeHtml(o.city || '') + '</div></td>' +
            '<td class="nowrap ink-2">' + formatDate(o.order_date) + '</td>' +
            '<td>' + statusPill(o.status) + '</td>' +
            '<td class="num">' + formatPKR(o.total) + '</td>' +
            '</tr>';
    }).join('');
}

function renderTopProducts(list) {
    var el = document.getElementById('topProductsList');
    if (!list.length) { el.innerHTML = '<li class="muted">No sales yet</li>'; return; }
    el.innerHTML = list.map(function (p, i) {
        return '<li><span class="rank-n">' + (i + 1) + '</span>' +
            '<div class="rank-main"><strong title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</strong><small>' + formatNumber(p.sold) + ' sold · ' + formatPKR(p.price) + ' each</small></div>' +
            '<span class="rank-val">' + formatPKR((Number(p.price) || 0) * (Number(p.sold) || 0)) + '</span></li>';
    }).join('');
}

function renderLowStock(list) {
    var section = document.getElementById('lowStockSection');
    if (!list.length) { section.hidden = true; return; }
    section.hidden = false;
    document.getElementById('lowStockCount').textContent = list.length;
    document.getElementById('lowStockList').innerHTML = list.map(function (p) {
        return '<tr><td class="cell-clip" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</td><td class="num">' + stockCell(p.inventory) + '</td></tr>';
    }).join('');
}

function applyChartDefaults() {
    Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
    Chart.defaults.font.size = 11;
    Chart.defaults.color = TOKENS.ink3;
    Chart.defaults.borderColor = TOKENS.line;
    var tt = Chart.defaults.plugins.tooltip;
    tt.backgroundColor = '#2c3550';
    tt.titleColor = TOKENS.ink;
    tt.bodyColor = TOKENS.ink2;
    tt.borderColor = 'rgba(255,255,255,.12)';
    tt.borderWidth = 1;
    tt.padding = 10;
    tt.cornerRadius = 6;
    tt.displayColors = false;
}

function compactPKR(v) {
    if (v >= 1e6) return 'Rs ' + (v / 1e6).toFixed(v % 1e6 === 0 ? 0 : 1) + 'M';
    if (v >= 1e3) return 'Rs ' + Math.round(v / 1e3) + 'k';
    return 'Rs ' + v;
}

function createSalesChart(monthlySales) {
    var rows = monthlyAsc(monthlySales);
    var labels = rows.map(function (m) { return MONTHS_SHORT[m.month - 1]; });
    var full = rows.map(function (m) { return MONTHS_SHORT[m.month - 1] + ' ' + m.year; });
    var values = rows.map(function (m) { return Number(m.sales) || 0; });
    var sum = values.reduce(function (a, b) { return a + b; }, 0);
    document.getElementById('salesRangeTotal').textContent = formatPKR(sum) + ' total';

    var canvas = document.getElementById('salesChart');
    var ctx = canvas.getContext('2d');
    var grad = ctx.createLinearGradient(0, 0, 0, canvas.parentNode.clientHeight || 290);
    grad.addColorStop(0, 'rgba(124,108,246,.32)');
    grad.addColorStop(1, 'rgba(124,108,246,0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Sales',
                data: values,
                borderColor: TOKENS.accent,
                backgroundColor: grad,
                fill: true,
                tension: .35,
                cubicInterpolationMode: 'monotone',
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 4,
                pointHoverBackgroundColor: TOKENS.accent,
                pointHoverBorderColor: '#fff',
                pointHoverBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { title: function (items) { return full[items[0].dataIndex]; }, label: function (c) { return formatPKR(c.parsed.y); } } }
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { color: TOKENS.ink3, maxRotation: 0, autoSkipPadding: 8 } },
                y: {
                    beginAtZero: true,
                    grid: { color: TOKENS.line, drawTicks: false },
                    border: { display: false },
                    ticks: { color: TOKENS.ink3, padding: 8, maxTicksLimit: 5, callback: function (v) { return compactPKR(v); } }
                }
            }
        }
    });
}

function createCategoryChart(categoryData) {
    var rows = categoryData.map(function (c) { return { name: c.name, value: Number(c.product_count) || 0 }; })
        .filter(function (c) { return c.value > 0; })
        .sort(function (a, b) { return b.value - a.value; });
    // keep the ramp readable: at most 7 tinted slices, everything else folds into a gray "Other"
    if (rows.length > 8) {
        var rest = rows.slice(7).reduce(function (a, b) { return a + b.value; }, 0);
        rows = rows.slice(0, 7).concat([{ name: 'Other', value: rest }]);
    }
    var colors = rows.map(function (r, i) {
        if (i === rows.length - 1 && rows.length > 1) return TOKENS.gray;
        var step = rows.length - 1 <= 1 ? 3 : Math.round(i * (DONUT_RAMP.length - 1) / Math.max(1, rows.length - 2));
        return DONUT_RAMP[Math.min(step, DONUT_RAMP.length - 1)];
    });
    var total = rows.reduce(function (a, b) { return a + b.value; }, 0);
    document.getElementById('donutTotal').textContent = formatNumber(total);
    document.getElementById('categoryLegend').innerHTML = rows.map(function (r, i) {
        return '<li><span class="sw" style="background:' + colors[i] + '"></span><span class="lbl" title="' + escapeHtml(r.name) + '">' + escapeHtml(r.name) + '</span><span class="val">' + r.value + '</span></li>';
    }).join('');

    new Chart(document.getElementById('categoryChart'), {
        type: 'doughnut',
        data: {
            labels: rows.map(function (r) { return r.name; }),
            datasets: [{ data: rows.map(function (r) { return r.value; }), backgroundColor: colors, borderColor: TOKENS.surface, borderWidth: 2, hoverOffset: 4 }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: function (c) { return c.label + ': ' + c.parsed + ' (' + Math.round(c.parsed / total * 100) + '%)'; } } }
            }
        }
    });
}
