window.onload = function() {
    loadDashboard();
};

function loadDashboard() {
    fetch(adminUrl('api/dashboard_stats.php'))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            document.getElementById('totalSales').innerHTML = 'Rs. ' + data.stats.totalSales;
            document.getElementById('totalOrders').innerHTML = data.stats.totalOrders;
            document.getElementById('totalProducts').innerHTML = data.stats.totalProducts;
            document.getElementById('totalCustomers').innerHTML = data.stats.totalCustomers;
            document.getElementById('totalCategories').innerHTML = data.stats.totalCategories;
            
            var ordersHtml = '';
            for (var i = 0; i < data.recentOrders.length; i++) {
                var order = data.recentOrders[i];
                var statusClass = 'secondary';
                if (order.status == 'Delivered') statusClass = 'success';
                else if (order.status == 'Shipped') statusClass = 'info';
                else if (order.status == 'Processing') statusClass = 'warning';
                
                ordersHtml += '<tr>';
                ordersHtml += '<td>#' + order.id + '</td>';
                ordersHtml += '<td>' + order.customer_name + '</td>';
                ordersHtml += '<td>Rs. ' + order.total + '</td>';
                ordersHtml += '<td><span class="badge bg-' + statusClass + '">' + order.status + '</span></td>';
                ordersHtml += '<td>' + formatDate(order.order_date) + '</td>';
                ordersHtml += '</tr>';
            }
            document.getElementById('recentOrdersTable').innerHTML = ordersHtml;
            
            var productsHtml = '';
            for (var i = 0; i < data.topProducts.length; i++) {
                var product = data.topProducts[i];
                productsHtml += '<div class="d-flex align-items-center mb-3 pb-3 border-bottom">';
                productsHtml += '<div class="badge bg-primary me-3">' + (i+1) + '</div>';
                productsHtml += '<div class="flex-grow-1"><strong>' + product.name + '</strong><br><small>' + product.sold + ' sold</small></div>';
                productsHtml += '<div>Rs. ' + product.price + '</div>';
                productsHtml += '</div>';
            }
            document.getElementById('topProductsList').innerHTML = productsHtml;

            // Low stock products
            var lowStock = data.lowStockProducts || [];
            var lowStockSection = document.getElementById('lowStockSection');
            var lowStockList = document.getElementById('lowStockList');
            if (lowStock.length > 0 && lowStockSection && lowStockList) {
                lowStockSection.style.display = 'block';
                var lowHtml = '<table class="table table-sm table-hover"><thead><tr><th>Product</th><th>Stock</th></tr></thead><tbody>';
                for (var j = 0; j < lowStock.length; j++) {
                    lowHtml += '<tr><td>' + lowStock[j].name + '</td><td><span class="badge bg-warning text-dark">' + lowStock[j].inventory + '</span></td></tr>';
                }
                lowHtml += '</tbody></table>';
                lowStockList.innerHTML = lowHtml;
            } else if (lowStockSection) {
                lowStockSection.style.display = 'none';
            }
            
            // Create sales chart
            createSalesChart(data.monthlySales);
            
            // Create category chart
            createCategoryChart(data.categoryData);
        }
    });
}

function createSalesChart(monthlySales) {
    var ctx = document.getElementById('salesChart').getContext('2d');
    var labels = [];
    var salesData = [];
    
    // Reverse to show oldest first
    monthlySales.reverse();
    
    for (var i = 0; i < monthlySales.length; i++) {
        var month = monthlySales[i];
        labels.push(month.year + '-' + (month.month < 10 ? '0' : '') + month.month);
        salesData.push(month.sales);
    }
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Monthly Sales (Rs.)',
                data: salesData,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function createCategoryChart(categoryData) {
    var ctx = document.getElementById('categoryChart').getContext('2d');
    var labels = [];
    var data = [];
    
    for (var i = 0; i < categoryData.length; i++) {
        labels.push(categoryData[i].name);
        data.push(categoryData[i].product_count);
    }
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)'
                ],
            }]
        },
       options: {
        responsive: false,
        maintainAspectRatio: false, // allows the canvas to fill its container
        plugins: {
            legend: {
                position: 'bottom',
            }
        },
        
    }
    });
}

