<?php
// Dashboard stats
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);

json_headers();

// Get total sales
$stmt = $conn->query("SELECT SUM(total) as total_sales FROM orders");
$sales = $stmt->fetch(PDO::FETCH_ASSOC);
$totalSales = $sales['total_sales'] ? $sales['total_sales'] : 0;

// Get total orders
$stmt = $conn->query("SELECT COUNT(*) as total_orders FROM orders");
$orders = $stmt->fetch(PDO::FETCH_ASSOC);
$totalOrders = $orders['total_orders'];

// Get total products
$stmt = $conn->query("SELECT COUNT(*) as total_products FROM products");
$products = $stmt->fetch(PDO::FETCH_ASSOC);
$totalProducts = $products['total_products'];

// Get total customers
$stmt = $conn->query("SELECT COUNT(*) as total_customers FROM customers");
$customers = $stmt->fetch(PDO::FETCH_ASSOC);
$totalCustomers = $customers['total_customers'];

// Get total categories
$stmt = $conn->query("SELECT COUNT(*) as total_categories FROM categories");
$cats = $stmt->fetch(PDO::FETCH_ASSOC);
$totalCategories = $cats['total_categories'];

// Get recent orders
$stmt = $conn->query("SELECT * FROM orders ORDER BY order_date DESC LIMIT 5");
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top products
$stmt = $conn->query("SELECT * FROM products ORDER BY sold DESC LIMIT 5");
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly sales
$stmt = $conn->query("SELECT MONTH(order_date) as month, YEAR(order_date) as year, SUM(total) as sales FROM orders GROUP BY YEAR(order_date), MONTH(order_date) ORDER BY year DESC, month DESC LIMIT 12");
$monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category distribution
$stmt = $conn->query("SELECT c.name, COUNT(p.id) as product_count FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id");
$categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products (inventory <= 5); skip if column missing
$lowStockProducts = [];
try {
    // same rule as the sidebar / bell: at or below each product's threshold, archived excluded
    $stmt = $conn->query("SELECT id, name, inventory, low_stock_threshold FROM products WHERE status <> 'archived' AND inventory <= low_stock_threshold AND inventory >= 0 ORDER BY inventory ASC LIMIT 10");
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // products.inventory may not exist in older schema
}

// Return data
$result = array(
    'success' => true,
    'stats' => array(
        'totalSales' => $totalSales,
        'totalOrders' => $totalOrders,
        'totalProducts' => $totalProducts,
        'totalCustomers' => $totalCustomers,
        'totalCategories' => $totalCategories
    ),
    'recentOrders' => $recentOrders,
    'topProducts' => $topProducts,
    'monthlySales' => $monthlySales,
    'categoryData' => $categoryData,
    'lowStockProducts' => $lowStockProducts
);

echo json_encode($result);

