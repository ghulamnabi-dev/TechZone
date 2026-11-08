<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../config/paths.php';

$token = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['token'] ?? '') : ($_GET['token'] ?? '');
$token = (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) ? $token : '';
$error = '';
$message = '';
$showForm = true;

function find_reset_record(PDO $conn, string $token): ?array {
    if ($token === '') return null;
    $hash = hash('sha256', $token);
    // Expiry is compared in the database clock (same clock that set it)
    $stmt = $conn->prepare("SELECT r.id, r.admin_id, r.expires_at FROM admin_password_resets r WHERE r.token_hash = ? AND r.expires_at > NOW() LIMIT 1");
    $stmt->execute([$hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

$resetRecord = null;
if ($token !== '') {
    $resetRecord = find_reset_record($conn, $token);
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
        $resetRecord = find_reset_record($conn, $token);
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
                $stmt = $conn->prepare("DELETE FROM admin_password_resets WHERE id = ?");
                $stmt->execute([$resetRecord['id']]);
                if ($stmt->rowCount() !== 1) {
                    $conn->rollBack();
                    $error = 'This reset link is invalid or has expired.';
                    $showForm = false;
                } else {
                    $conn->prepare("UPDATE admins SET password = ? WHERE id = ?")
                         ->execute([password_hash($password, PASSWORD_DEFAULT), $resetRecord['admin_id']]);
                    $conn->prepare("DELETE FROM admin_password_resets WHERE admin_id = ?")->execute([$resetRecord['admin_id']]);
                    $conn->commit();
                    $message = 'Your password has been updated. You can now log in.';
                    $showForm = false;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - TechZone.pk Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body class="admin-login-body">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <h2 class="fw-bold text-dark mb-0">TechZone.pk</h2>
                        <p class="text-muted small mb-0">Set a new password</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                        <div class="text-center mt-3 small"><a href="<?php echo htmlspecialchars(admin_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to login</a></div>
                    <?php endif; ?>

                    <?php if ($showForm && $resetRecord): ?>
                        <form method="post" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="mb-3">
                                <label class="form-label">New password</label>
                                <input type="password" name="password" class="form-control" minlength="8" maxlength="72" placeholder="At least 8 characters" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm password</label>
                                <input type="password" name="password_confirm" class="form-control" minlength="8" maxlength="72" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Update password</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($error && $showForm === false): ?>
                        <div class="text-center mt-3 small"><a href="<?php echo htmlspecialchars(admin_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to login</a></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
