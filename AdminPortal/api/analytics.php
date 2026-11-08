<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);
json_headers();

// Get total revenue
$stmt = $conn->query("SELECT SUM(total) as total_revenue FROM orders");
$revenue = $stmt->fetch(PDO::FETCH_ASSOC);
$totalRevenue = $revenue['total_revenue'] ? $revenue['total_revenue'] : 0;

// Get total orders
$stmt = $conn->query("SELECT COUNT(*) as total_orders FROM orders");
$orders = $stmt->fetch(PDO::FETCH_ASSOC);
$totalOrders = $orders['total_orders'];

// Get total units sold
$stmt = $conn->query("SELECT SUM(sold) as total_units_sold FROM products");
$units = $stmt->fetch(PDO::FETCH_ASSOC);
$totalUnitsSold = $units['total_units_sold'] ? $units['total_units_sold'] : 0;

// Calculate average order value
$avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Get top products
$stmt = $conn->query("SELECT id, name, price, sold, (sold * price) as revenue FROM products ORDER BY sold DESC LIMIT 5");
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get category sales
$stmt = $conn->query("SELECT c.name as category_name, COUNT(p.id) as product_count, SUM(p.sold) as units_sold, SUM(p.sold * p.price) as revenue FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.id ORDER BY revenue DESC");
$categorySales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get other stats
$stmt = $conn->query("SELECT COUNT(*) as total_products FROM products");
$products = $stmt->fetch(PDO::FETCH_ASSOC);
$totalProducts = $products['total_products'];

$stmt = $conn->query("SELECT COUNT(*) as total_customers FROM customers");
$customers = $stmt->fetch(PDO::FETCH_ASSOC);
$totalCustomers = $customers['total_customers'];

$result = array(
    'success' => true,
    'analytics' => array(
        'totalRevenue' => $totalRevenue,
        'totalOrders' => $totalOrders,
        'totalUnitsSold' => $totalUnitsSold,
        'avgOrderValue' => $avgOrderValue,
        'totalProducts' => $totalProducts,
        'totalCustomers' => $totalCustomers
    ),
    'topProducts' => $topProducts,
    'categorySales' => $categorySales
);

echo json_encode($result);
?>
