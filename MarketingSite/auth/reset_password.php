<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['customer_id'])) {
    header('Location: ' . site_url('index.php'));
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';

function find_customer_reset_record(PDO $conn, string $token): ?array {
    if ($token === '') return null;
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("SELECT id, customer_id, expires_at FROM customer_password_resets WHERE token_hash = ? LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if (new DateTime($row['expires_at']) < new DateTime()) return null;
    return $row;
}

$token = $_GET['token'] ?? '';
$error = '';
$message = '';
$showForm = true;

if ($token !== '') {
    $conn->exec("CREATE TABLE IF NOT EXISTS customer_password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
        INDEX idx_token (token_hash),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $resetRecord = find_customer_reset_record($conn, $token);
    if (!$resetRecord) {
        $error = 'This reset link is invalid or has expired.';
        $showForm = false;
    }
} else {
    $error = 'Missing reset token.';
    $showForm = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf_token();
    } catch (RuntimeException $e) {
        $error = 'Your session has expired. Please refresh the page and try again.';
        $showForm = false;
    }

    $token = $_POST['token'] ?? '';
    if ($error === '') {
        $resetRecord = find_customer_reset_record($conn, $token);
        if (!$resetRecord) {
            $error = 'This reset link is invalid or has expired.';
            $showForm = false;
        } else {
            $password = $_POST['password'] ?? '';
            $confirm  = $_POST['password_confirm'] ?? '';
            if (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters.';
            } elseif ($password !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE customers SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $resetRecord['customer_id']]);
                $stmt = $conn->prepare("DELETE FROM customer_password_resets WHERE id = ?");
                $stmt->execute([$resetRecord['id']]);
                $message = 'Your password has been updated. You can now log in.';
                $showForm = false;
            }
        }
    }
}

$pageTitle = 'Reset Password';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card auth-card">
                    <div class="card-header">
                        <h2><i class="fas fa-lock me-2"></i>Set new password</h2>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                            <div class="text-center mt-3"><a href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fw-bold auth-link">Back to login</a></div>
                        <?php endif; ?>

                        <?php if ($showForm && isset($resetRecord) && $resetRecord): ?>
                        <form method="post" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>New password</label>
                                <input type="password" name="password" class="form-control" minlength="6" placeholder="At least 6 characters" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm password</label>
                                <input type="password" name="password_confirm" class="form-control" minlength="6" placeholder="Confirm new password" required>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 btn-lg contact-submit-btn">Update password</button>
                        </form>
                        <?php endif; ?>

                        <?php if ($error && !$showForm): ?>
                            <div class="text-center mt-3"><a href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fw-bold auth-link">Back to login</a></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
