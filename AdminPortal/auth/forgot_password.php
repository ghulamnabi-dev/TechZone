<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: ' . admin_url('manage/dashboard.php'));
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/validation.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf_token();
    } catch (RuntimeException $e) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    }

    // Throttle reset requests per IP + session (5 per 15 minutes)
    if ($error === '' && ($locked = login_lock_remaining($conn, 'admin_reset')) > 0) {
        $error = 'Too many reset attempts. Please try again in ' . max(1, (int) ceil($locked / 60)) . ' minutes.';
    }

    if ($error === '') {
        login_register_failure($conn, 'admin_reset');
        $email = v_str($_POST['email'] ?? '', 150) ?? '';
        $username = v_str($_POST['username'] ?? '', 50) ?? '';
        $phone = v_str($_POST['phone'] ?? '', 30) ?? '';

        if ($email === '' || $username === '' || $phone === '') {
            $error = 'Please enter email, username, and phone to verify your account.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Validate: all three must match one admin (normalize phone: strip spaces and dashes so 0301-1427871 = 03011427871)
            $phoneNorm = preg_replace('/[\s\-]+/', '', $phone);
            $stmt = $conn->prepare("SELECT id FROM admins WHERE email = ? AND username = ? AND (phone = ? OR REPLACE(REPLACE(COALESCE(phone,''), ' ', ''), '-', '') = ?)");
            $stmt->execute([$email, $username, $phone, $phoneNorm]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // Same response whether or not the details matched (no account enumeration).
            $message = 'If those details match an admin account, a password reset link has been sent to its email address.';

            if ($admin) {
                // Only the sha256 of the token is stored; 1 hour expiry; single use.
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);

                // One live link per account: drop older / expired tokens
                $conn->prepare("DELETE FROM admin_password_resets WHERE admin_id = ? OR expires_at < NOW()")->execute([$admin['id']]);
                $stmt = $conn->prepare("INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
                $stmt->execute([$admin['id'], $hash]);

                $resetLink = 'reset_password.php?token=' . urlencode($token);
                $fullResetUrl = absolute_url(admin_url('auth/' . $resetLink));

                $emailSent = false;
                if (env_flag('PASSWORD_RESET_EMAIL')) {
                    $subject = 'TechZone.pk Admin – Reset your password';
                    $body = "You requested a password reset. Click the link below (valid for 1 hour):\n\n" . $fullResetUrl . "\n\nIf you did not request this, ignore this email.";
                    $headers = "From: noreply@techzone.pk\r\nContent-Type: text/plain; charset=UTF-8";
                    $emailSent = @mail($email, $subject, $body, $headers);
                }

                // Demo / local only: show the link on screen instead of emailing it.
                // Off by default because email+username+phone are not secrets.
                if (!$emailSent && env_flag('PASSWORD_RESET_SHOW_LINK', app_debug())) {
                    $message = 'Identity verified. Use the link below to set a new password (valid for 1 hour).';
                    $message .= '<br><br><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Reset password</a>';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <title>Forgot Password - TechZone.pk Admin</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php require_once __DIR__ . '/../../core/boot_ui.php'; tz_boot_ui('admin'); ?>
    <link rel="stylesheet" data-tz-critical href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" data-tz-critical href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body class="admin-login-body">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <div class="auth-brand mb-3">
                        <span class="brand-mark"><i class="fas fa-microchip"></i></span>
                        <div><h1>TechZone</h1><p>Reset your password</p></div>
                    </div>
                    <p class="text-muted small mb-3">Enter your <strong>email</strong>, <strong>username</strong>, and <strong>phone</strong> so we can verify your account and let you reset your password.</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php elseif ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>

                    <?php if (!$message): ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="Admin email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" placeholder="Admin username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" placeholder="e.g. 03xx-xxxxxxx or +92..." value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2">Verify & get reset link</button>
                    </form>
                    <?php endif; ?>

                    <div class="text-center mt-3 small">
                        <a href="<?php echo htmlspecialchars(admin_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Back to login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
