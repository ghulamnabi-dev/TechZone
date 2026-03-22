window.onload = function() {
    loadAnalytics();
};

function loadAnalytics() {
    fetch(adminUrl('api/analytics.php'))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            var analytics = data.analytics;
            
            document.getElementById('statRevenue').innerHTML = 'Rs. ' + analytics.totalRevenue;
            document.getElementById('statOrders').innerHTML = analytics.totalOrders;
            document.getElementById('statUnitsSold').innerHTML = analytics.totalUnitsSold;
            document.getElementById('statAvgOrder').innerHTML = 'Rs. ' + analytics.avgOrderValue.toFixed(2);
            
            document.getElementById('totalProducts').innerHTML = analytics.totalProducts;
            document.getElementById('totalOrders').innerHTML = analytics.totalOrders;
            document.getElementById('totalCustomers').innerHTML = analytics.totalCustomers;
            document.getElementById('totalRevenue').innerHTML = 'Rs. ' + analytics.totalRevenue;
            document.getElementById('totalUnitsSold').innerHTML = analytics.totalUnitsSold;
            document.getElementById('avgOrderValue').innerHTML = 'Rs. ' + analytics.avgOrderValue.toFixed(2);
            
            renderTopProducts(data.topProducts);
            
            renderCategorySales(data.categorySales);
        }
    });
}

function renderTopProducts(products) {
    var tbody = document.getElementById('topProductsTable');
    
    if (products.length == 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No products found</td></tr>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < products.length; i++) {
        html += '<tr>';
        html += '<td>' + products[i].name + '</td>';
        html += '<td>' + products[i].sold + '</td>';
        html += '<td>Rs. ' + products[i].revenue + '</td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}

function renderCategorySales(categorySales) {
    var tbody = document.getElementById('categorySalesTable');
    
    if (categorySales.length == 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center">No data found</td></tr>';
        return;
    }
    
    var html = '';
    for (var i = 0; i < categorySales.length; i++) {
        html += '<tr>';
        html += '<td>' + categorySales[i].category_name + '</td>';
        html += '<td>' + (categorySales[i].units_sold || 0) + '</td>';
        html += '<td>Rs. ' + (categorySales[i].revenue || 0) + '</td>';
        html += '</tr>';
    }
    tbody.innerHTML = html;
}
