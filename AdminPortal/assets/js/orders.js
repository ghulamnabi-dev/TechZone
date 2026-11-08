var orders = [];
var orderDetailsModalInstance = null;
var updateStatusModalInstance = null;

var ordersGrid = null;
var ORDER_STATUS_LIST = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

function paymentLabel(method) {
    var m = String(method || 'cash_on_delivery');
    if (m === 'cash_on_delivery') return 'Cash on delivery';
    if (m === 'jazzcash') return 'JazzCash';
    if (m === 'easypaisa') return 'Easypaisa';
    return m.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
}

/** PUT one order's status through the wrapped fetch (adds X-CSRF-Token). Resolves true/false. */
function putOrderStatus(id, status) {
    return fetch(adminUrl('api/orders.php'), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: parseInt(id, 10), status: status })
    }).then(function(r) { return r.json(); }).then(function(d) { return !!d.success; });
}

window.onload = function() {
    var orderDetailsModalElement = document.getElementById('orderDetailsModal');
    var updateStatusModalElement = document.getElementById('updateStatusModal');
    if (orderDetailsModalElement && typeof bootstrap !== 'undefined') {
        orderDetailsModalInstance = bootstrap.Modal.getOrCreateInstance(orderDetailsModalElement);
    }
    if (updateStatusModalElement && typeof bootstrap !== 'undefined') {
        updateStatusModalInstance = bootstrap.Modal.getOrCreateInstance(updateStatusModalElement);
    }

    ordersGrid = createAdminGrid(document.getElementById('ordersTable'), {
        storageKey: 'tz.grid.orders.v1',
        noun: ['order', 'orders'],
        emptyText: 'No orders match these filters.',
        exportName: 'orders',
        toolbar: { search: '#orderSearch', count: '#orderCount', exportBtn: '#ordersExport', resetBtn: '#ordersReset' },
        selectable: true,
        columns: [
            { field: 'id', headerName: 'Order', kind: 'id', width: 104, mobileWidth: 96 },
            { field: 'customer_name', headerName: 'Customer', kind: 'stack', flex: 1, minWidth: 210,
              cellRendererParams: { sub: function(o) { return [o.city, o.customer_email].filter(Boolean).join(' · '); } },
              getQuickFilterText: function(p) { var o = p.data; return [o.customer_name, o.customer_email, o.customer_phone, o.city, '#' + o.id].join(' '); },
              csvValue: function(o) { return o.customer_name; } },
            { field: 'city', headerName: 'City', kind: 'muted', width: 130, hide: true },
            { field: 'customer_email', headerName: 'Email', kind: 'muted', width: 200, hide: true },
            { field: 'order_date', headerName: 'Date', kind: 'date', width: 136, sort: 'desc' },
            { field: 'payment_method', headerName: 'Payment', kind: 'muted', width: 170,
              valueGetter: function(p) { return paymentLabel(p.data.payment_method); } },
            { field: 'status', headerName: 'Status', kind: 'status', width: 140 },
            { field: 'total', headerName: 'Total', kind: 'money', width: 140 }
        ],
        rowActions: function(o) {
            return [
                { icon: 'fa-eye', label: 'View order', attrs: 'data-view-order="' + parseInt(o.id, 10) + '"' },
                { icon: 'fa-arrows-rotate', label: 'Update status', attrs: 'data-update-order="' + parseInt(o.id, 10) + '"' }
            ];
        },
        onRowOpen: function(o) { showOrderDetails(o.id); },
        bulkActions: [{
            label: 'Mark as', icon: 'fa-arrows-rotate',
            menu: ORDER_STATUS_LIST.filter(function(s) { return s !== 'Cancelled' || tzCan('orders.cancel'); }).map(function(s) { return { label: s, value: s }; }),
            progress: 'Updating',
            perRow: function(o, status) { return o.status === status ? true : putOrderStatus(o.id, status); },
            done: function(ok, failed) {
                return ok + ' ' + (ok === 1 ? 'order' : 'orders') + ' updated' + (failed ? ', ' + failed + ' failed' : '');
            }
        }],
        onBulkDone: function() { loadOrders(); }
    });
    ordersGrid.bindSelect('#statusFilter', 'status', function(v) { return { filterType: 'text', type: 'equals', filter: v }; });

    loadOrders();

    var detailsUpdateBtn = document.getElementById('orderDetailsUpdateBtn');
    if (detailsUpdateBtn) {
        detailsUpdateBtn.onclick = function() {
            var id = this.getAttribute('data-order-id');
            if (!id) return;
            if (orderDetailsModalInstance) orderDetailsModalInstance.hide();
            openStatusModal(id);
        };
    }

    document.getElementById('ordersTable').addEventListener('click', function(e) {
        var v = e.target.closest('[data-view-order]');
        if (v) { showOrderDetails(v.getAttribute('data-view-order')); return; }
        var u = e.target.closest('[data-update-order]');
        if (u) openStatusModal(u.getAttribute('data-update-order'));
    });

    document.getElementById('updateStatusForm').onsubmit = function(e) {
        e.preventDefault();
        handleStatusUpdate();
    };
};

function loadOrders() {
    // all orders once; search / status filtering happens in the grid (quick filter + filter model)
    return fetch(adminUrl('api/orders.php'))
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            orders = data.orders || [];
            ordersGrid.setRows(orders);
        } else {
            ordersGrid.setRows([]);
            showNotification(data.message || 'Could not load orders', 'error');
        }
    })
    .catch(function() {
        ordersGrid.setRows([]);
        showNotification('Could not load orders', 'error');
    });
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
