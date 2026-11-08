var customers = [];
var currentPage = 1;
var limit = 25;
var knownCities = [];

window.onload = function() {
    loadCustomers();

    var searchTimer = null;
    document.getElementById('customerSearch').oninput = function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { currentPage = 1; loadCustomers(); }, 200);
    };

    document.getElementById('cityFilter').onchange = function() {
        currentPage = 1;
        loadCustomers();
    };

    document.getElementById('customersTable').onclick = function(e) {
        var btn = e.target.closest('[data-view-customer]');
        if (btn) showCustomerDetails(btn.getAttribute('data-view-customer'));
    };
};

function loadCustomers() {
    var search = document.getElementById('customerSearch').value;
    var city = document.getElementById('cityFilter').value;

    var url = adminUrl('api/customers.php') + '?page=' + currentPage + '&limit=' + limit;
    if (search) url += '&search=' + encodeURIComponent(search);
    if (city) url += '&city=' + encodeURIComponent(city);

    fetch(url)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                customers = data.customers || [];
                renderCustomers();
                updateCityFilter();
                var total = data.total != null ? Number(data.total) : customers.length;
                document.getElementById('customerCount').textContent = total;
                renderPager(document.getElementById('customersPager'), total, Number(data.page) || currentPage, Number(data.limit) || limit, function(pg) {
                    currentPage = pg;
                    loadCustomers();
                }, 'customers');
            } else {
                document.getElementById('customersTable').innerHTML = emptyRow(7, data.message || 'Could not load customers');
            }
        })
        .catch(function() {
            document.getElementById('customersTable').innerHTML = emptyRow(7, 'Could not load customers');
        });
}

function updateCityFilter() {
    // the API pages results, so remember every city we have seen rather than only this page's
    customers.forEach(function(c) {
        if (c.city && knownCities.indexOf(c.city) === -1) knownCities.push(c.city);
    });
    knownCities.sort();
    var select = document.getElementById('cityFilter');
    var currentValue = select.value;
    select.innerHTML = '<option value="">All cities</option>';
    knownCities.forEach(function(city) {
        var option = document.createElement('option');
        option.value = city;
        option.text = city;
        select.appendChild(option);
    });
    select.value = currentValue;
}

function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function(w) { return w.charAt(0).toUpperCase(); }).join('');
}

function renderCustomers() {
    var tbody = document.getElementById('customersTable');
    if (customers.length == 0) {
        tbody.innerHTML = emptyRow(7, 'No customers match these filters.');
        return;
    }
    var html = '';
    for (var i = 0; i < customers.length; i++) {
        var c = customers[i];
        html += '<tr>';
        html += '<td><div class="prod-cell" style="min-width:200px"><span class="admin-header-avatar" style="background:var(--surface-2);color:var(--ink-2);flex-shrink:0">' + escapeHtml(initials(c.name)) + '</span>' +
            '<div class="txt"><div class="cell-main" style="font-weight:500">' + escapeHtml(c.name) + '</div><div class="cell-sub">' + escapeHtml(c.email) + '</div></div></div></td>';
        html += '<td class="nowrap ink-2 tabular">' + escapeHtml(c.phone || '—') + '</td>';
        html += '<td class="ink-2">' + escapeHtml(c.city || '—') + '</td>';
        html += '<td class="nowrap ink-2">' + formatDate(c.created_at) + '</td>';
        html += '<td class="num">' + formatNumber(c.total_orders || 0) + '</td>';
        html += '<td class="num">' + formatPKR(c.total_spent || 0) + '</td>';
        html += '<td class="text-end"><div class="actions">' + iconButton('fa-eye', 'View customer', 'data-view-customer="' + c.id + '"') + '</div></td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

function showCustomerDetails(customerId) {
    fetch(adminUrl('api/customers.php?id=' + encodeURIComponent(customerId)))
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!(data.success && data.customer)) {
                showNotification(data.message || 'Customer not found', 'error');
                return;
            }
            var c = data.customer;
            var orders = c.orders || [];
            var ordersHtml = orders.length ? orders.map(function(o) {
                return '<tr><td class="id-cell">#' + escapeHtml(o.id) + '</td><td class="nowrap ink-2">' + formatDate(o.order_date) + '</td><td>' + statusPill(o.status) + '</td><td class="num">' + formatPKR(o.total) + '</td></tr>';
            }).join('') : emptyRow(4, 'No orders yet');

            var html = '<div class="detail-grid mb-4">';
            html += '<div class="detail-card"><h6 class="form-section-title">Contact</h6><dl class="kv">';
            html += '<dt>Name</dt><dd>' + escapeHtml(c.name) + '</dd>';
            html += '<dt>Email</dt><dd>' + escapeHtml(c.email) + '</dd>';
            html += '<dt>Phone</dt><dd class="tabular">' + escapeHtml(c.phone || '—') + '</dd>';
            html += '<dt>City</dt><dd>' + escapeHtml(c.city || '—') + '</dd>';
            html += '<dt>Address</dt><dd>' + escapeHtml(c.address || '—') + '</dd>';
            html += '</dl></div>';
            html += '<div class="detail-card"><h6 class="form-section-title">Activity</h6><dl class="kv">';
            html += '<dt>Joined</dt><dd>' + formatDate(c.created_at) + '</dd>';
            html += '<dt>Orders</dt><dd class="tabular">' + formatNumber(c.total_orders || 0) + '</dd>';
            html += '<dt>Total spent</dt><dd class="tabular">' + formatPKR(c.total_spent || 0) + '</dd>';
            html += '<dt>Avg order</dt><dd class="tabular">' + formatPKR(c.avg_order_value || 0) + '</dd>';
            html += '</dl></div></div>';
            html += '<h6 class="form-section-title">Order history</h6>';
            html += '<div class="detail-card p-0" style="overflow:hidden"><div class="table-scroll"><table class="table mini-table mb-0"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="num">Total</th></tr></thead><tbody>' + ordersHtml + '</tbody></table></div></div>';

            document.getElementById('customerDetailsTitle').textContent = c.name;
            document.getElementById('customerDetailsContent').innerHTML = html;
            bootstrap.Modal.getOrCreateInstance(document.getElementById('customerDetailsModal')).show();
        });
}
