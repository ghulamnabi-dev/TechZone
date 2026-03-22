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
    
    document.getElementById('orderSearch').oninput = function() {
        loadOrders();
    };
    
    document.getElementById('statusFilter').onchange = function() {
        loadOrders();
    };
    
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

function renderOrders() {
    var tbody = document.getElementById('ordersTable');
    document.getElementById('orderCount').innerHTML = orders.length;
    
    if (orders.length == 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">No orders found</td></tr>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < orders.length; i++) {
        var order = orders[i];
        var statusKey = (order.status || '').toLowerCase();
        var statusClass = 'badge-status-pending';
        if (statusKey === 'delivered') statusClass = 'badge-status-delivered';
        else if (statusKey === 'shipped') statusClass = 'badge-status-shipped';
        else if (statusKey === 'processing') statusClass = 'badge-status-processing';
        else if (statusKey === 'cancelled') statusClass = 'badge-status-cancelled';
        else if (statusKey === 'pending') statusClass = 'badge-status-pending';
        
        html += '<tr>';
        html += '<td>#' + order.id + '</td>';
        html += '<td>' + order.customer_name + '</td>';
        html += '<td>' + order.customer_email + '</td>';
        html += '<td>' + order.customer_phone + '</td>';
        html += '<td>' + order.city + '</td>';
        html += '<td>Rs. ' + order.total + '</td>';
        html += '<td>' + formatDate(order.order_date) + '</td>';
        html += '<td><span class="badge badge-status ' + statusClass + '">' + order.status + '</span></td>';
        html += '<td>';
        html += '<button class="btn btn-sm btn-outline-primary me-1" data-view-order="' + order.id + '">View</button>';
        html += '<button class="btn btn-sm btn-outline-secondary btn-update-status" data-update-order="' + order.id + '">Update</button>';
        html += '</td>';
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
                itemsHtml += '<td>' + items[i].product_name + '</td>';
                itemsHtml += '<td>' + items[i].quantity + '</td>';
                itemsHtml += '<td>Rs. ' + items[i].price + '</td>';
                itemsHtml += '<td>Rs. ' + items[i].subtotal + '</td>';
                itemsHtml += '</tr>';
            }
            
            var statusKey = (order.status || '').toLowerCase();
            var statusClass = 'badge-status-pending';
            if (statusKey === 'delivered') statusClass = 'badge-status-delivered';
            else if (statusKey === 'shipped') statusClass = 'badge-status-shipped';
            else if (statusKey === 'processing') statusClass = 'badge-status-processing';
            else if (statusKey === 'cancelled') statusClass = 'badge-status-cancelled';
            else if (statusKey === 'pending') statusClass = 'badge-status-pending';
            
            var html = '<div class="row"><div class="col-md-6">';
            html += '<h6>Order Information</h6>';
            html += '<p><strong>Order ID:</strong> #' + order.id + '</p>';
            html += '<p><strong>Date:</strong> ' + formatDate(order.order_date) + '</p>';
            html += '<p><strong>Status:</strong> <span class="badge badge-status ' + statusClass + '">' + order.status + '</span></p>';
            
            // Display payment method
            var paymentMethod = order.payment_method || 'cash_on_delivery';
            var paymentMethodText = paymentMethod.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            html += '<p><strong>Payment Method:</strong> ' + paymentMethodText + '</p>';
            
            html += '</div><div class="col-md-6">';
            html += '<h6>Customer Information</h6>';
            html += '<p><strong>Name:</strong> ' + order.customer_name + '</p>';
            html += '<p><strong>Email:</strong> ' + order.customer_email + '</p>';
            html += '<p><strong>Phone:</strong> ' + order.customer_phone + '</p>';
            html += '<p><strong>City:</strong> ' + order.city + '</p>';
            html += '</div></div><hr>';
            html += '<h6>Products Ordered</h6>';
            html += '<table class="table"><thead><tr><th>Product</th><th>Quantity</th><th>Price</th><th>Subtotal</th></tr></thead>';
            html += '<tbody>' + itemsHtml + '</tbody></table>';
            html += '<div class="text-end"><h5>Total: Rs. ' + order.total + '</h5></div>';
            
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
