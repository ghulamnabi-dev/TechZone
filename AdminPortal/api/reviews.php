<?php
require_once '../config/database.php';
require_once '../config/auth.php';

checkLogin(true);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET - Get all reviews
if ($method == 'GET') {
    $query = "SELECT r.*, p.name as product_name FROM reviews r LEFT JOIN products p ON r.product_id = p.id WHERE 1=1";
    $params = array();
    
    if (isset($_GET['status']) && $_GET['status'] != '') {
        $query .= " AND r.status = ?";
        $params[] = $_GET['status'];
    }
    
    $query .= " ORDER BY r.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get stats (COALESCE so avg_rating is 0 when no reviews instead of NULL)
    $stmt2 = $conn->query("SELECT COUNT(*) as total_reviews, SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_reviews, SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved_reviews, COALESCE(ROUND(AVG(rating), 1), 0) as avg_rating FROM reviews");
    $stats = $stmt2->fetch(PDO::FETCH_ASSOC);
    $stats['total_reviews'] = (int) $stats['total_reviews'];
    $stats['pending_reviews'] = (int) $stats['pending_reviews'];
    $stats['approved_reviews'] = (int) $stats['approved_reviews'];
    $stats['avg_rating'] = (float) $stats['avg_rating'];
    
    echo json_encode(array('success' => true, 'reviews' => $reviews, 'stats' => $stats));
}

// PUT - Update review
else if ($method == 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'];
    
    if (isset($data['status'])) {
        $status = $data['status'];
        $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
        $stmt->execute(array($status, $id));
    }
    
    if (isset($data['admin_response'])) {
        $response = $data['admin_response'];
        $stmt = $conn->prepare("UPDATE reviews SET admin_response = ? WHERE id = ?");
        $stmt->execute(array($response, $id));
    }
    
    echo json_encode(array('success' => true, 'message' => 'Review updated'));
}

// DELETE - Delete review
else if ($method == 'DELETE') {
    $id = $_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->execute(array($id));
    
    echo json_encode(array('success' => true, 'message' => 'Review deleted'));
}
?>
