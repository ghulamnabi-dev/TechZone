<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

checkLogin(true);
json_headers();

$method = $_SERVER['REQUEST_METHOD'];
const REVIEW_STATUSES = ['Pending', 'Approved', 'Rejected'];

try {
    // GET - all reviews (+ stats)
    if ($method === 'GET') {
        $query = "SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON r.product_id = p.id WHERE 1=1";
        $params = [];

        $status = v_enum($_GET['status'] ?? '', REVIEW_STATUSES);
        if ($status !== null) {
            $query .= " AND r.status = ?";
            $params[] = $status;
        }
        $query .= " ORDER BY r.created_at DESC";

        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // COALESCE so avg_rating is 0 when no reviews instead of NULL
        $stmt2 = $conn->query("SELECT COUNT(*) AS total_reviews, SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_reviews, SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_reviews, COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating FROM reviews");
        $stats = $stmt2->fetch(PDO::FETCH_ASSOC);
        $stats['total_reviews'] = (int) $stats['total_reviews'];
        $stats['pending_reviews'] = (int) $stats['pending_reviews'];
        $stats['approved_reviews'] = (int) $stats['approved_reviews'];
        $stats['avg_rating'] = (float) $stats['avg_rating'];

        json_response(['success' => true, 'reviews' => $reviews, 'stats' => $stats]);
    }

    // PUT - update status and/or admin response (JSON body)
    if ($method === 'PUT') {
        enforce_csrf_json();
        $data = request_json();
        $id = v_id($data['id'] ?? 0);
        if ($id === 0) {
            json_error('Invalid review ID', 422);
        }

        if (array_key_exists('status', $data)) {
            $status = v_enum($data['status'], REVIEW_STATUSES);
            if ($status === null) {
                json_error('Invalid status', 422);
            }
            $stmt = $conn->prepare("UPDATE reviews SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
        }

        if (array_key_exists('admin_response', $data)) {
            $response = v_str($data['admin_response'], 2000);
            if ($response === null) {
                json_error('Response is too long (max 2000 characters)', 422);
            }
            $stmt = $conn->prepare("UPDATE reviews SET admin_response = ? WHERE id = ?");
            $stmt->execute([$response === '' ? null : $response, $id]);
        }

        json_response(['success' => true, 'message' => 'Review updated']);
    }

    // DELETE - ?id=
    if ($method === 'DELETE') {
        enforce_csrf_json();
        $id = v_id($_GET['id'] ?? 0);
        if ($id === 0) {
            json_error('Invalid review ID', 422);
        }
        $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$id]);
        json_response(['success' => true, 'message' => 'Review deleted']);
    }

    json_error('Invalid request method', 405);
} catch (PDOException $e) {
    json_error(safe_error_message($e, 'Could not process the request.'), 500);
}
