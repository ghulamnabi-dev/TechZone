var customers = [];
var customersGrid = null;

function initials(name) {
    return String(name || '?').trim().split(/\s+/).slice(0, 2).map(function(w) { return w.charAt(0).toUpperCase(); }).join('');
}

window.onload = function() {
    customersGrid = createAdminGrid(document.getElementById('customersTable'), {
        storageKey: 'tz.grid.customers.v2',
        noun: ['customer', 'customers'],
        emptyText: 'No customers match these filters.',
        exportName: 'customers',
        toolbar: { search: '#customerSearch', count: '#customerCount', exportBtn: '#customersExport', resetBtn: '#customersReset' },
        columns: [
            { field: 'name', headerName: 'Customer', kind: 'thumb', flex: 1, minWidth: 240, mobileWidth: 170,
              cellRendererParams: { avatar: function(c) { return initials(c.name); }, sub: function(c) { return c.email; }, weight: 500 },
              getQuickFilterText: function(p) { return [p.data.name, p.data.email, p.data.phone, p.data.city, p.data.tags].join(' '); } },
            { field: 'email', headerName: 'Email', kind: 'muted', width: 220, hide: true },
            { field: 'phone', headerName: 'Phone', kind: 'muted', width: 150 },
            { field: 'city', headerName: 'City', kind: 'muted', width: 140 },
            { field: 'created_at', headerName: 'Joined', kind: 'date', width: 136, sort: 'desc' },
            { field: 'tags', headerName: 'Tags', width: 180, filter: 'agTextColumnFilter', sortable: false,
              cellRenderer: function(p) {
                  var w = document.createElement('span'); w.className = 'cell-pill';
                  var tags = String(p.value || '').split(',').filter(Boolean);
                  if (!tags.length) { w.className = 'muted'; w.textContent = '—'; return w; }
                  tags.forEach(function(t) { var c = document.createElement('span'); c.className = 'chip me-1'; c.textContent = t; w.appendChild(c); });
                  return w;
              } },
            { field: 'total_orders', headerName: 'Orders', kind: 'number', width: 110 },
            { field: 'total_spent', headerName: 'Total spent', kind: 'money', width: 150 }
        ],
        rowActions: function(c) {
            return [{ icon: 'fa-arrow-right', label: 'Open customer', href: customerUrl(c.id), attrs: 'data-view-customer="' + parseInt(c.id, 10) + '"' }];
        },
        onRowOpen: function(c) { location.href = customerUrl(c.id); }
    });
    customersGrid.bindSelect('#cityFilter', 'city', function(v) { return { filterType: 'text', type: 'equals', filter: v }; });

    document.getElementById('customersTable').addEventListener('click', function(e) {
        var btn = e.target.closest('[data-view-customer]');
        if (btn && btn.tagName === 'BUTTON') location.href = customerUrl(btn.getAttribute('data-view-customer'));
    });
    var q = new URLSearchParams(location.search).get('q');
    if (q) { var s = document.getElementById('customerSearch'); s.value = q; s.dispatchEvent(new Event('input')); }
    if (/[?&]deleted=1/.test(location.search)) { showNotification('Customer deleted', 'success'); history.replaceState(null, '', location.pathname); }
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

function customerUrl(id) {
    return adminUrl('manage/customer.php?id=' + encodeURIComponent(id));
}
