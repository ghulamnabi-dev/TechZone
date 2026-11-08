window.addEventListener('load', loadAnalytics);

function loadAnalytics() {
    fetch(adminUrl('api/analytics.php'))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.success) { showNotification(data.message || 'Could not load analytics', 'error'); return; }
            var a = data.analytics || {};
            var orders = Number(a.totalOrders) || 0;
            var units = Number(a.totalUnitsSold) || 0;
            var customers = Number(a.totalCustomers) || 0;

            setText('statRevenue', formatPKR(a.totalRevenue));
            setText('statOrders', formatNumber(orders));
            setText('statOrdersSub', customers ? (orders / customers).toFixed(1) + ' per customer' : '');
            setText('statUnitsSold', formatNumber(units));
            setText('statUnitsSub', orders ? (units / orders).toFixed(1) + ' units per order' : '');
            setText('statAvgOrder', formatPKR(a.avgOrderValue));

            setText('totalProducts', formatNumber(a.totalProducts));
            setText('totalOrders', formatNumber(orders));
            setText('totalCustomers', formatNumber(customers));
            setText('totalRevenue', formatPKR(a.totalRevenue));
            setText('totalUnitsSold', formatNumber(units));
            setText('avgOrderValue', formatPKR(a.avgOrderValue));

            renderTopProducts(data.topProducts || []);
            renderCategorySales(data.categorySales || []);
        })
        .catch(function() { showNotification('Could not load analytics', 'error'); });
}

function setText(id, value) {
    var el = document.getElementById(id);
    if (el) el.textContent = value;
}

function renderTopProducts(products) {
    var tbody = document.getElementById('topProductsTable');
    if (!products.length) { tbody.innerHTML = emptyRow(3, 'No sales yet'); return; }
    tbody.innerHTML = products.map(function(p, i) {
        return '<tr><td><div class="prod-cell" style="min-width:200px"><span class="rank-n">' + (i + 1) + '</span><div class="txt cell-clip" style="max-width:320px" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</div></div></td>' +
            '<td class="num">' + formatNumber(p.sold) + '</td>' +
            '<td class="num">' + formatPKR(p.revenue) + '</td></tr>';
    }).join('');
}

function renderCategorySales(rows) {
    var tbody = document.getElementById('categorySalesTable');
    if (!rows.length) { tbody.innerHTML = emptyRow(4, 'No data yet'); return; }
    var total = rows.reduce(function(s, r) { return s + (Number(r.revenue) || 0); }, 0) || 1;
    tbody.innerHTML = rows.map(function(r) {
        var share = (Number(r.revenue) || 0) / total * 100;
        return '<tr><td class="nowrap">' + escapeHtml(r.category_name) + '</td>' +
            '<td class="num">' + formatNumber(r.units_sold || 0) + '</td>' +
            '<td><div class="bar-cell"><div class="bar"><i style="width:' + share.toFixed(1) + '%"></i></div><span class="tabular ink-2" style="min-width:34px;text-align:right">' + share.toFixed(0) + '%</span></div></td>' +
            '<td class="num">' + formatPKR(r.revenue || 0) + '</td></tr>';
    }).join('');
}
