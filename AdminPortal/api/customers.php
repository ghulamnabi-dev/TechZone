<?php
require_once '../config/database.php';
require_once '../config/auth.php';

checkLogin();
header('Content-Type: application/json');

// GET - Get all customers or one customer
if (isset($_GET['id'])) {
    // Get one customer
    $id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute(array($id));
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get orders
    $stmt2 = $conn->prepare("SELECT id, order_date, status, total FROM orders WHERE customer_id = ? ORDER BY order_date DESC");
    $stmt2->execute(array($id));
    $orders = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate stats
    $totalOrders = count($orders);
    $totalSpent = 0;
    foreach ($orders as $order) {
        $totalSpent += $order['total'];
    }
    $avgOrderValue = $totalOrders > 0 ? $totalSpent / $totalOrders : 0;
    
    $customer['orders'] = $orders;
    $customer['total_orders'] = $totalOrders;
    $customer['total_spent'] = $totalSpent;
    $customer['avg_order_value'] = $avgOrderValue;
    
    echo json_encode(array('success' => true, 'customer' => $customer));
} else {
    // Get all customers with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT * FROM customers WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM customers WHERE 1=1";
    $params = array();
    
    if (isset($_GET['search']) && $_GET['search'] != '') {
        $query .= " AND (name LIKE ? OR email LIKE ?)";
        $countQuery .= " AND (name LIKE ? OR email LIKE ?)";
        $search = '%' . $_GET['search'] . '%';
        $params[] = $search;
        $params[] = $search;
    }
    
    if (isset($_GET['city']) && $_GET['city'] != '') {
        $query .= " AND city = ?";
        $countQuery .= " AND city = ?";
        $params[] = $_GET['city'];
    }
    
    $query .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt2 = $conn->prepare($countQuery);
    $stmt2->execute($params);
    $total = $stmt2->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Add order stats
    for ($i = 0; $i < count($customers); $i++) {
        $stmt3 = $conn->prepare("SELECT COUNT(*) as total_orders, SUM(total) as total_spent FROM orders WHERE customer_id = ?");
        $stmt3->execute(array($customers[$i]['id']));
        $stats = $stmt3->fetch(PDO::FETCH_ASSOC);
        $customers[$i]['total_orders'] = $stats['total_orders'];
        $customers[$i]['total_spent'] = $stats['total_spent'] ? $stats['total_spent'] : 0;
    }
    
    echo json_encode(array('success' => true, 'customers' => $customers, 'total' => $total, 'page' => $page, 'limit' => $limit));
}
?>
