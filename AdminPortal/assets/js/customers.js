var customers = [];
var currentPage = 1;
var totalPages = 1;
var limit = 10;

window.onload = function() {
    loadCustomers();
    
    document.getElementById('customerSearch').oninput = function() {
        currentPage = 1;
        loadCustomers();
    };
    
    document.getElementById('cityFilter').onchange = function() {
        currentPage = 1;
        loadCustomers();
    };
    
    document.getElementById('customersTable').onclick = function(e) {
        if (e.target.closest('[data-view-customer]')) {
            var id = e.target.closest('[data-view-customer]').getAttribute('data-view-customer');
            showCustomerDetails(id);
        }
    };
};

function loadCustomers() {
    var search = document.getElementById('customerSearch').value;
    var city = document.getElementById('cityFilter').value;
    
    var url = adminUrl('api/customers.php') + '?page=' + currentPage + '&limit=' + limit;
    if (search) url += '&search=' + encodeURIComponent(search);
    if (city) url += '&city=' + encodeURIComponent(city);
    
    fetch(url)
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            customers = data.customers;
            renderCustomers();
            updateCityFilter();
            updatePagination(data.total, data.page, data.limit);
        }
    });
}

function updateCityFilter() {
    var cities = [];
    for (var i = 0; i < customers.length; i++) {
        if (customers[i].city && cities.indexOf(customers[i].city) == -1) {
            cities.push(customers[i].city);
        }
    }
    
    var select = document.getElementById('cityFilter');
    var currentValue = select.value;
    select.innerHTML = '<option value="">All Cities</option>';
    for (var i = 0; i < cities.length; i++) {
        var option = document.createElement('option');
        option.value = cities[i];
        option.text = cities[i];
        if (cities[i] == currentValue) option.selected = true;
        select.appendChild(option);
    }
}

function renderCustomers() {
    var tbody = document.getElementById('customersTable');
    document.getElementById('customerCount').innerHTML = customers.length;
    
    if (customers.length == 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">No customers found</td></tr>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < customers.length; i++) {
        var customer = customers[i];
        html += '<tr>';
        html += '<td>' + customer.id + '</td>';
        html += '<td>' + customer.name + '</td>';
        html += '<td>' + customer.email + '</td>';
        html += '<td>' + (customer.phone || 'N/A') + '</td>';
        html += '<td>' + (customer.city || 'N/A') + '</td>';
        html += '<td><small>' + (customer.address || 'N/A') + '</small></td>';
        html += '<td>' + formatDate(customer.created_at) + '</td>';
        html += '<td><span class="badge bg-primary">' + (customer.total_orders || 0) + '</span></td>';
        html += '<td>Rs. ' + (customer.total_spent || 0) + '</td>';
        html += '<td><button class="btn btn-sm btn-info" data-view-customer="' + customer.id + '">View</button></td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

function showCustomerDetails(customerId) {
    fetch(adminUrl('api/customers.php?id=' + customerId))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success && data.customer) {
            var customer = data.customer;
            var orders = customer.orders || [];
            
            var ordersHtml = '';
            if (orders.length > 0) {
                for (var i = 0; i < orders.length; i++) {
                    var statusClass = 'secondary';
                    if (orders[i].status == 'Delivered') statusClass = 'success';
                    else if (orders[i].status == 'Shipped') statusClass = 'info';
                    else if (orders[i].status == 'Processing') statusClass = 'warning';
                    
                    ordersHtml += '<tr>';
                    ordersHtml += '<td>#' + orders[i].id + '</td>';
                    ordersHtml += '<td>' + formatDate(orders[i].order_date) + '</td>';
                    ordersHtml += '<td><span class="badge bg-' + statusClass + '">' + orders[i].status + '</span></td>';
                    ordersHtml += '<td>Rs. ' + orders[i].total + '</td>';
                    ordersHtml += '</tr>';
                }
            } else {
                ordersHtml = '<tr><td colspan="4" class="text-center">No orders found</td></tr>';
            }
            
            var html = '<div class="row mb-4"><div class="col-md-6">';
            html += '<h6>Personal Information</h6>';
            html += '<p><strong>Name:</strong> ' + customer.name + '</p>';
            html += '<p><strong>Email:</strong> ' + customer.email + '</p>';
            html += '<p><strong>Phone:</strong> ' + (customer.phone || 'N/A') + '</p>';
            html += '<p><strong>City:</strong> ' + (customer.city || 'N/A') + '</p>';
            html += '<p><strong>Address:</strong> ' + (customer.address || 'N/A') + '</p>';
            html += '</div><div class="col-md-6">';
            html += '<h6>Statistics</h6>';
            html += '<p><strong>Join Date:</strong> ' + formatDate(customer.created_at) + '</p>';
            html += '<p><strong>Total Orders:</strong> <span class="badge bg-primary">' + customer.total_orders + '</span></p>';
            html += '<p><strong>Total Spent:</strong> <span class="badge bg-success">Rs. ' + customer.total_spent + '</span></p>';
            html += '<p><strong>Average Order Value:</strong> Rs. ' + customer.avg_order_value.toFixed(2) + '</p>';
            html += '</div></div><hr>';
            html += '<h6>Order History</h6>';
            html += '<table class="table table-sm"><thead><tr><th>Order ID</th><th>Date</th><th>Status</th><th>Amount</th></tr></thead>';
            html += '<tbody>' + ordersHtml + '</tbody></table>';
            
            document.getElementById('customerDetailsContent').innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
            modal.show();
        }
    });
}

function updatePagination(total, page, limit) {
    totalPages = Math.ceil(total / limit);
    currentPage = page;
    
    var start = (page - 1) * limit + 1;
    var end = Math.min(page * limit, total);
    
    document.getElementById('customerCount').textContent = total;
    document.getElementById('paginationInfo').textContent = 'Showing ' + start + ' to ' + end + ' of ' + total + ' customers';
    
    var controls = document.getElementById('paginationControls');
    controls.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Previous button
    var prevLi = document.createElement('li');
    prevLi.className = 'page-item' + (page == 1 ? ' disabled' : '');
    var prevA = document.createElement('a');
    prevA.className = 'page-link';
    prevA.href = '#';
    prevA.textContent = 'Previous';
    prevA.onclick = function(e) { e.preventDefault(); if (page > 1) { currentPage--; loadCustomers(); } };
    prevLi.appendChild(prevA);
    controls.appendChild(prevLi);
    
    // Page numbers
    var startPage = Math.max(1, page - 2);
    var endPage = Math.min(totalPages, page + 2);
    
    if (startPage > 1) {
        var li = document.createElement('li');
        li.className = 'page-item';
        var a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = '1';
        a.onclick = function(e) { e.preventDefault(); currentPage = 1; loadCustomers(); };
        li.appendChild(a);
        controls.appendChild(li);
        
        if (startPage > 2) {
            var dots = document.createElement('li');
            dots.className = 'page-item disabled';
            var span = document.createElement('span');
            span.className = 'page-link';
            span.textContent = '...';
            dots.appendChild(span);
            controls.appendChild(dots);
        }
    }
    
    for (var i = startPage; i <= endPage; i++) {
        var li = document.createElement('li');
        li.className = 'page-item' + (i == page ? ' active' : '');
        var a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = i;
        a.onclick = function(pageNum) { return function(e) { e.preventDefault(); currentPage = pageNum; loadCustomers(); } }(i);
        li.appendChild(a);
        controls.appendChild(li);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            var dots = document.createElement('li');
            dots.className = 'page-item disabled';
            var span = document.createElement('span');
            span.className = 'page-link';
            span.textContent = '...';
            dots.appendChild(span);
            controls.appendChild(dots);
        }
        
        var li = document.createElement('li');
        li.className = 'page-item';
        var a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = totalPages;
        a.onclick = function(e) { e.preventDefault(); currentPage = totalPages; loadCustomers(); };
        li.appendChild(a);
        controls.appendChild(li);
    }
    
    // Next button
    var nextLi = document.createElement('li');
    nextLi.className = 'page-item' + (page == totalPages ? ' disabled' : '');
    var nextA = document.createElement('a');
    nextA.className = 'page-link';
    nextA.href = '#';
    nextA.textContent = 'Next';
    nextA.onclick = function(e) { e.preventDefault(); if (page < totalPages) { currentPage++; loadCustomers(); } };
    nextLi.appendChild(nextA);
    controls.appendChild(nextLi);
}
