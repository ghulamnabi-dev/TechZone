var customers = [];
var customersGrid = null;

function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function(w) { return w.charAt(0).toUpperCase(); }).join('');
}

window.onload = function() {
    customersGrid = createAdminGrid(document.getElementById('customersTable'), {
        storageKey: 'tz.grid.customers.v1',
        noun: ['customer', 'customers'],
        emptyText: 'No customers match these filters.',
        exportName: 'customers',
        toolbar: { search: '#customerSearch', count: '#customerCount', exportBtn: '#customersExport', resetBtn: '#customersReset' },
        columns: [
            { field: 'name', headerName: 'Customer', kind: 'thumb', flex: 1, minWidth: 240, mobileWidth: 170,
              cellRendererParams: { avatar: function(c) { return initials(c.name); }, sub: function(c) { return c.email; }, weight: 500 },
              getQuickFilterText: function(p) { return [p.data.name, p.data.email, p.data.phone, p.data.city].join(' '); } },
            { field: 'email', headerName: 'Email', kind: 'muted', width: 220, hide: true },
            { field: 'phone', headerName: 'Phone', kind: 'muted', width: 150 },
            { field: 'city', headerName: 'City', kind: 'muted', width: 140 },
            { field: 'created_at', headerName: 'Joined', kind: 'date', width: 136, sort: 'desc' },
            { field: 'total_orders', headerName: 'Orders', kind: 'number', width: 110 },
            { field: 'total_spent', headerName: 'Total spent', kind: 'money', width: 150 }
        ],
        rowActions: function(c) {
            return [{ icon: 'fa-eye', label: 'View customer', attrs: 'data-view-customer="' + parseInt(c.id, 10) + '"' }];
        },
        onRowOpen: function(c) { showCustomerDetails(c.id); }
    });
    customersGrid.bindSelect('#cityFilter', 'city', function(v) { return { filterType: 'text', type: 'equals', filter: v }; });

    document.getElementById('customersTable').addEventListener('click', function(e) {
        var btn = e.target.closest('[data-view-customer]');
        if (btn) showCustomerDetails(btn.getAttribute('data-view-customer'));
    });
    loadCustomers();
};

function loadCustomers() {
    // the API pages (limit up to 1000); the grid pages, sorts and filters client-side
    return fetch(adminUrl('api/customers.php') + '?page=1&limit=1000')
        .then(function(response) { return response.json(); })
        .then(function(data) {
            customers = data.success ? (data.customers || []) : [];
            customersGrid.setRows(customers);
            updateCityFilter();
            if (!data.success) showNotification(data.message || 'Could not load customers', 'error');
        })
        .catch(function() {
            customersGrid.setRows([]);
            showNotification('Could not load customers', 'error');
        });
}

function updateCityFilter() {
    var cities = [];
    customers.forEach(function(c) { if (c.city && cities.indexOf(c.city) === -1) cities.push(c.city); });
    cities.sort();
    var select = document.getElementById('cityFilter');
    var currentValue = select.value;
    select.innerHTML = '<option value="">All cities</option>';
    cities.forEach(function(city) {
        var option = document.createElement('option');
        option.value = city;
        option.text = city;
        select.appendChild(option);
    });
    select.value = currentValue;
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
