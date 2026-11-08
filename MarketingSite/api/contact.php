<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/validation.php';

json_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request method', 405);
}
enforce_csrf_json();

$name = v_str($_POST['name'] ?? '', 100);
$emailRaw = v_str($_POST['email'] ?? '', 150);
$subject = v_str($_POST['subject'] ?? '', 200);
$message = v_str($_POST['message'] ?? '', 5000);

if ($name === null || $emailRaw === null || $subject === null || $message === null) {
    json_error('One of the fields is too long (name 100, subject 200, message 5000 characters)', 422);
}
if ($name === '' || $emailRaw === '' || $subject === '' || $message === '') {
    json_error('All fields are required', 422);
}
$email = v_email($emailRaw);
if ($email === null) {
    json_error('Please enter a valid email address', 422);
}
if (mb_strlen($subject) < 3) {
    json_error('Subject must be at least 3 characters long', 422);
}
if (mb_strlen($message) < 10) {
    json_error('Message must be at least 10 characters long', 422);
}

// Light flood control: at most 5 messages per session per 10 minutes
$now = time();
$recent = array_filter($_SESSION['contact_sent'] ?? [], static fn($t) => is_int($t) && $t > $now - 600);
if (count($recent) >= 5) {
    json_error('You have sent several messages recently. Please wait a few minutes and try again.', 429);
}

try {
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $subject, $message]);
    $recent[] = $now;
    $_SESSION['contact_sent'] = array_values($recent);
    json_response(['success' => true, 'message' => 'Thank you for contacting us! We will get back to you soon.']);
} catch (PDOException $e) {
    log_exception($e, 'contact');
    json_error('Failed to send message. Please try again.', 500);
}
