<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

checkLogin(true);
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$adminId = $_SESSION['admin_id'];

// GET - Get all contact messages
if ($method == 'GET') {
    $status = $_GET['status'] ?? '';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;
    
    try {
        // Ensure limit and offset are integers
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        if (!empty($status) && in_array($status, ['new', 'read', 'replied', 'archived'])) {
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contact_messages WHERE status = ?");
            $stmt->execute([$status]);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Use integers directly in SQL, not as bound parameters
            $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE status = ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute([$status]);
        } else {
            $stmt = $conn->query("SELECT COUNT(*) as total FROM contact_messages");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Use integers directly in SQL, not as bound parameters
            $stmt = $conn->prepare("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
            $stmt->execute();
        }
        
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'limit' => $limit
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error fetching messages: ' . $e->getMessage()]);
    }
}

// PUT - Update message status
else if ($method == 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = $data['id'] ?? 0;
    $status = $data['status'] ?? '';
    
    if (empty($messageId) || empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Message ID and status are required']);
        exit();
    }
    
    if (!in_array($status, ['new', 'read', 'replied', 'archived'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
        $stmt->execute([$status, $messageId]);
        
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $e->getMessage()]);
    }
}

// DELETE - Delete message
else if ($method == 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = $data['id'] ?? 0;
    
    if (empty($messageId)) {
        echo json_encode(['success' => false, 'message' => 'Message ID is required']);
        exit();
    }
    
    try {
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        
        echo json_encode(['success' => true, 'message' => 'Message deleted successfully']);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error deleting message: ' . $e->getMessage()]);
    }
}
?>

