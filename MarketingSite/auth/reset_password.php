<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['customer_id'])) {
    header('Location: ' . site_url('index.php'));
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';

function find_customer_reset_record(PDO $conn, string $token): ?array {
    if ($token === '') return null;
    $hash = hash('sha256', $token);
    // Expiry is compared in the database clock (same clock that set it)
    $stmt = $conn->prepare("SELECT id, customer_id, expires_at FROM customer_password_resets WHERE token_hash = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
$token = (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) ? $token : '';
$error = '';
$message = '';
$showForm = true;

if ($token !== '') {
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

    if ($error === '') {
        $resetRecord = find_customer_reset_record($conn, $token);
        if (!$resetRecord) {
            $error = 'This reset link is invalid or has expired.';
            $showForm = false;
        } else {
            $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
            $confirm  = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';
            if ($policyError = password_policy_error($password)) {
                $error = $policyError;
            } elseif (!hash_equals($password, $confirm)) {
                $error = 'Passwords do not match.';
            } else {
                // Single use: consume the token (and any other outstanding ones) atomically
                $conn->beginTransaction();
                $stmt = $conn->prepare("DELETE FROM customer_password_resets WHERE id = ?");
                $stmt->execute([$resetRecord['id']]);
                if ($stmt->rowCount() !== 1) {
                    $conn->rollBack();
                    $error = 'This reset link is invalid or has expired.';
                    $showForm = false;
                } else {
                    $conn->prepare("UPDATE customers SET password = ? WHERE id = ?")
                         ->execute([password_hash($password, PASSWORD_DEFAULT), $resetRecord['customer_id']]);
                    $conn->prepare("DELETE FROM customer_password_resets WHERE customer_id = ?")->execute([$resetRecord['customer_id']]);
                    $conn->commit();
                    $message = 'Your password has been updated. You can now log in.';
                    $showForm = false;
                }
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
                                <input type="password" name="password" class="form-control" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm password</label>
                                <input type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" placeholder="Confirm new password" required>
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
