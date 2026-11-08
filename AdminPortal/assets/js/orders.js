var orders = [];
var orderDetailsModalInstance = null;
var updateStatusModalInstance = null;

window.onload = function() {
    loadOrders();
    
    // Initialize modal instances once
    var orderDetailsModalElement = document.getElementById('orderDetailsModal');
    var updateStatusModalElement = document.getElementById('updateStatusModal');
    
    if (orderDetailsModalElement && typeof bootstrap !== 'undefined') {
        orderDetailsModalInstance = bootstrap.Modal.getInstance(orderDetailsModalElement) || 
                                   new bootstrap.Modal(orderDetailsModalElement);
    }
    
    if (updateStatusModalElement && typeof bootstrap !== 'undefined') {
        updateStatusModalInstance = bootstrap.Modal.getInstance(updateStatusModalElement) || 
                                   new bootstrap.Modal(updateStatusModalElement);
    }
    
    var searchTimer = null;
    document.getElementById('orderSearch').oninput = function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { orderPage = 1; loadOrders(); }, 200);
    };
    
    document.getElementById('statusFilter').onchange = function() {
        orderPage = 1;
        loadOrders();
    };

    var detailsUpdateBtn = document.getElementById('orderDetailsUpdateBtn');
    if (detailsUpdateBtn) {
        detailsUpdateBtn.onclick = function() {
            var id = this.getAttribute('data-order-id');
            if (!id) return;
            if (orderDetailsModalInstance) orderDetailsModalInstance.hide();
            openStatusModal(id);
        };
    }
    
    document.getElementById('ordersTable').onclick = function(e) {
        if (e.target.closest('[data-view-order]')) {
            var id = e.target.closest('[data-view-order]').getAttribute('data-view-order');
            showOrderDetails(id);
        }
        if (e.target.closest('[data-update-order]')) {
            var id = e.target.closest('[data-update-order]').getAttribute('data-update-order');
            openStatusModal(id);
        }
    };
    
    document.getElementById('updateStatusForm').onsubmit = function(e) {
        e.preventDefault();
        handleStatusUpdate();
    };
};

function loadOrders() {
    var search = document.getElementById('orderSearch').value;
    var status = document.getElementById('statusFilter').value;
    
    var url = adminUrl('api/orders.php') + '?';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    if (status) url += 'status=' + encodeURIComponent(status) + '&';
    
    fetch(url)
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            orders = data.orders;
            renderOrders();
        }
    });
}

var ORDERS_PAGE_SIZE = 25;
var orderPage = 1;

function paymentLabel(method) {
    var m = String(method || 'cash_on_delivery');
    if (m === 'cash_on_delivery') return 'Cash on delivery';
    if (m === 'jazzcash') return 'JazzCash';
    if (m === 'easypaisa') return 'Easypaisa';
    return m.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
}

function renderOrders() {
    var tbody = document.getElementById('ordersTable');
    document.getElementById('orderCount').textContent = orders.length;

    var pages = Math.max(1, Math.ceil(orders.length / ORDERS_PAGE_SIZE));
    if (orderPage > pages) orderPage = pages;
    var pageItems = orders.slice((orderPage - 1) * ORDERS_PAGE_SIZE, orderPage * ORDERS_PAGE_SIZE);
    renderPager(document.getElementById('ordersPager'), orders.length, orderPage, ORDERS_PAGE_SIZE, function(pg) {
        orderPage = pg;
        renderOrders();
    }, 'orders');

    if (orders.length == 0) {
        tbody.innerHTML = emptyRow(9, 'No orders match these filters.');
        return;
    }

    var html = '';
    for (var i = 0; i < pageItems.length; i++) {
        var order = pageItems[i];
        html += '<tr>';
        html += '<td class="id-cell">#' + escapeHtml(order.id) + '</td>';
        html += '<td><div class="cell-main" style="font-weight:500">' + escapeHtml(order.customer_name) + '</div><div class="cell-sub">' + escapeHtml(order.customer_email) + '</div></td>';
        html += '<td class="nowrap ink-2 tabular">' + escapeHtml(order.customer_phone) + '</td>';
        html += '<td class="ink-2">' + escapeHtml(order.city) + '</td>';
        html += '<td class="nowrap ink-2">' + formatDate(order.order_date) + '</td>';
        html += '<td class="nowrap ink-2">' + escapeHtml(paymentLabel(order.payment_method)) + '</td>';
        html += '<td>' + statusPill(order.status) + '</td>';
        html += '<td class="num">' + formatPKR(order.total) + '</td>';
        html += '<td class="text-end"><div class="actions">';
        html += iconButton('fa-eye', 'View order', 'data-view-order="' + order.id + '"');
        html += iconButton('fa-arrows-rotate', 'Update status', 'data-update-order="' + order.id + '"');
        html += '</div></td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

function showOrderDetails(orderId) {
    fetch(adminUrl('api/orders.php?id=' + orderId))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success && data.order) {
            var order = data.order;
            var items = order.items || [];
            
            var itemsHtml = '';
            for (var i = 0; i < items.length; i++) {
                itemsHtml += '<tr>';
                itemsHtml += '<td><div class="cell-main" style="font-weight:500">' + escapeHtml(items[i].product_name) + '</div>' +
                    ((items[i].size || items[i].color) ? '<div class="cell-sub">' + escapeHtml([items[i].size, items[i].color].filter(Boolean).join(' · ')) + '</div>' : '') + '</td>';
                itemsHtml += '<td class="num">' + formatNumber(items[i].quantity) + '</td>';
                itemsHtml += '<td class="num">' + formatPKR(items[i].price) + '</td>';
                itemsHtml += '<td class="num">' + formatPKR(items[i].subtotal) + '</td>';
                itemsHtml += '</tr>';
            }
            if (!items.length) itemsHtml = emptyRow(4, 'No line items');

            var html = '<div class="detail-grid mb-4">';
            html += '<div class="detail-card"><h6 class="form-section-title">Order</h6><dl class="kv">';
            html += '<dt>Order</dt><dd class="tabular">#' + escapeHtml(order.id) + '</dd>';
            html += '<dt>Placed</dt><dd>' + formatDateTime(order.order_date) + '</dd>';
            html += '<dt>Status</dt><dd>' + statusPill(order.status) + '</dd>';
            html += '<dt>Payment</dt><dd>' + escapeHtml(paymentLabel(order.payment_method)) + '</dd>';
            if (order.promo_code) html += '<dt>Promo code</dt><dd><span class="code-chip">' + escapeHtml(order.promo_code) + '</span></dd>';
            html += '</dl></div>';
            html += '<div class="detail-card"><h6 class="form-section-title">Customer</h6><dl class="kv">';
            html += '<dt>Name</dt><dd>' + escapeHtml(order.customer_name) + '</dd>';
            html += '<dt>Email</dt><dd>' + escapeHtml(order.customer_email) + '</dd>';
            html += '<dt>Phone</dt><dd class="tabular">' + escapeHtml(order.customer_phone) + '</dd>';
            html += '<dt>City</dt><dd>' + escapeHtml(order.city) + '</dd>';
            if (order.address || order.shipping_address) html += '<dt>Address</dt><dd>' + escapeHtml(order.address || order.shipping_address) + '</dd>';
            html += '</dl></div></div>';
            html += '<h6 class="form-section-title">Items</h6>';
            html += '<div class="detail-card p-0" style="overflow:hidden"><div class="table-scroll"><table class="table mini-table mb-0"><thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Subtotal</th></tr></thead>';
            html += '<tbody>' + itemsHtml + '</tbody></table></div></div>';
            html += '<div class="totals"><span>Total</span><strong>' + formatPKR(order.total) + '</strong></div>';

            document.getElementById('orderDetailsTitle').textContent = 'Order #' + order.id;
            var upd = document.getElementById('orderDetailsUpdateBtn');
            if (upd) upd.setAttribute('data-order-id', order.id);
            document.getElementById('orderDetailsContent').innerHTML = html;
            
            // Use existing modal instance or create one
            if (!orderDetailsModalInstance) {
                var modalElement = document.getElementById('orderDetailsModal');
                orderDetailsModalInstance = bootstrap.Modal.getInstance(modalElement) || 
                                           new bootstrap.Modal(modalElement);
            }
            
            // Show modal - z-index is handled by common.js
            orderDetailsModalInstance.show();
        }
    });
}

function openStatusModal(orderId) {
    orderId = parseInt(orderId);
    for (var i = 0; i < orders.length; i++) {
        if (parseInt(orders[i].id) == orderId) {
            document.getElementById('updateOrderId').value = orderId;
            var lbl = document.getElementById('updateOrderLabel');
            if (lbl) lbl.textContent = '#' + orderId;
            document.getElementById('newStatus').value = orders[i].status;
            
            // Use existing modal instance or create one
            if (!updateStatusModalInstance) {
                var modalElement = document.getElementById('updateStatusModal');
                updateStatusModalInstance = bootstrap.Modal.getInstance(modalElement) || 
                                           new bootstrap.Modal(modalElement);
            }
            
            // Show modal - z-index is handled by common.js
            updateStatusModalInstance.show();
            
            return;
        }
    }
    showNotification('Order not found', 'error');
}

function handleStatusUpdate() {
    var orderId = document.getElementById('updateOrderId').value;
    var newStatus = document.getElementById('newStatus').value;
    
    if (!orderId || !newStatus) {
        showNotification('Please select a status', 'error');
        return;
    }
    
    var data = {
        id: parseInt(orderId),
        status: newStatus
    };
    
    fetch(adminUrl('api/orders.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(function(response) {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            if (typeof showNotification === 'function') {
                showNotification('Order status updated successfully', 'success');
            } else {
                alert('Order status updated successfully');
            }
            var modalElement = document.getElementById('updateStatusModal');
            if (updateStatusModalInstance) {
                updateStatusModalInstance.hide();
            } else {
                var modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
            loadOrders();
        } else {
            if (typeof showNotification === 'function') {
                showNotification(data.message || 'Failed to update status', 'error');
            } else {
                alert(data.message || 'Failed to update status');
            }
        }
    })
    .catch(function(error) {
        console.error('Error:', error);
        showNotification('Error updating order status. Please try again.', 'error');
    });
}
