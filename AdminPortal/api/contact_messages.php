<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();
// Any write needs messages.write (core/permissions.php); reads are open to every role.
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    require_can('messages.write');
}

$method = $_SERVER['REQUEST_METHOD'];
const CONTACT_STATUSES = ['new', 'read', 'replied', 'archived'];

try {
    // GET - list contact messages
    if ($method === 'GET') {
        $status = v_enum($_GET['status'] ?? '', CONTACT_STATUSES);
        $page = v_int($_GET['page'] ?? 1, 1, 100000) ?? 1;
        $limit = v_int($_GET['limit'] ?? 10, 1, 1000) ?? 10;
        $offset = ($page - 1) * $limit;

        $where = $status !== null ? " WHERE status = ?" : "";
        $params = $status !== null ? [$status] : [];

        $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM contact_messages" . $where);
        $stmt->execute($params);
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // limit/offset are validated integers
        $stmt = $conn->prepare("SELECT * FROM contact_messages" . $where . " ORDER BY created_at DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset);
        $stmt->execute($params);

        json_response([
            'success' => true,
            'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    // PUT - update status (JSON body: id, status)
    if ($method === 'PUT') {
        enforce_csrf_json();
        $data = request_json();
        $messageId = v_id($data['id'] ?? 0);
        $status = $data['status'] ?? '';

        if ($messageId === 0 || $status === '') {
            json_error('Message ID and status are required', 422);
        }
        if (v_enum($status, CONTACT_STATUSES) === null) {
            json_error('Invalid status', 422);
        }

        $stmt = $conn->prepare("UPDATE contact_messages SET status = ? WHERE id = ?");
        $stmt->execute([$status, $messageId]);
        json_response(['success' => true, 'message' => 'Status updated successfully']);
    }

    // DELETE - delete message (JSON body: id, or ?id=)
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $data = request_json();
        $messageId = v_id($data['id'] ?? ($_GET['id'] ?? 0));
        if ($messageId === 0) {
            json_error('Message ID is required', 422);
        }

        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
        $stmt->execute([$messageId]);
        json_response(['success' => true, 'message' => 'Message deleted successfully']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not process the request.'), 500);
}
