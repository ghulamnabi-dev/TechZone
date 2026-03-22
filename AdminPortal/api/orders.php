<?php
require_once '../config/database.php';
require_once '../config/auth.php';

checkLogin();
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get all orders or one order
if ($method == 'GET') {
    if (isset($_GET['id'])) {
        // Get one order
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute(array($id));
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get order items
        $stmt2 = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt2->execute(array($id));
        $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        $order['items'] = $items;
        
        echo json_encode(array('success' => true, 'order' => $order));
    } else {
        // Get all orders
        $query = "SELECT * FROM orders WHERE 1=1";
        $params = array();
        
        if (isset($_GET['status']) && $_GET['status'] != '') {
            $query .= " AND status = ?";
            $params[] = $_GET['status'];
        }
        
        if (isset($_GET['search']) && $_GET['search'] != '') {
            $query .= " AND (customer_name LIKE ? OR customer_email LIKE ?)";
            $search = '%' . $_GET['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $query .= " ORDER BY order_date DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(array('success' => true, 'orders' => $orders));
    }
}

// PUT - Update order status
else if ($method == 'PUT') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['id']) || !isset($data['status'])) {
        echo json_encode(array('success' => false, 'message' => 'Invalid data'));
        exit();
    }
    
    $id = intval($data['id']);
    $status = $data['status'];
    
    // Validate status
    $validStatuses = array('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled');
    if (!in_array($status, $validStatuses)) {
        echo json_encode(array('success' => false, 'message' => 'Invalid status'));
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $result = $stmt->execute(array($status, $id));
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode(array('success' => true, 'message' => 'Order status updated'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Order not found or status unchanged'));
        }
    } catch(PDOException $e) {
        echo json_encode(array('success' => false, 'message' => 'Database error: ' . $e->getMessage()));
    }
}
?>
