<?php
session_start();
require_once __DIR__ . '/../config/paths.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: ' . admin_url('manage/dashboard.php'));
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf_token();
    } catch (RuntimeException $e) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    }

    if ($error === '') {
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

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

            if ($admin) {
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);
                $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

                $conn->exec("CREATE TABLE IF NOT EXISTS admin_password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    admin_id INT NOT NULL,
                    token_hash CHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
                    INDEX idx_token (token_hash),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                $stmt = $conn->prepare("INSERT INTO admin_password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$admin['id'], $hash, $expires]);

                $resetLink = 'reset_password.php?token=' . urlencode($token);
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base = dirname($_SERVER['SCRIPT_NAME'] ?? '');
                if (strpos($base, '\\') !== false) $base = str_replace('\\', '/', $base);
                $fullResetUrl = $scheme . '://' . $host . rtrim($base, '/') . '/' . $resetLink;

                $emailSent = false;
                if (defined('SEND_PASSWORD_RESET_EMAIL') && SEND_PASSWORD_RESET_EMAIL) {
                    $subject = 'TechZone.pk Admin – Reset your password';
                    $body = "You requested a password reset. Click the link below (valid for 1 hour):\n\n" . $fullResetUrl . "\n\nIf you did not request this, ignore this email.";
                    $headers = "From: noreply@techzone.pk\r\nContent-Type: text/plain; charset=UTF-8";
                    $emailSent = @mail($email, $subject, $body, $headers);
                }

                if ($emailSent) {
                    $message = 'We have sent a reset link to your email. Check your inbox and spam folder.';
                } else {
                    $message = 'Identity verified. Use the link below to set a new password (valid for 1 hour).';
                    $message .= '<br><br><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">Reset password</a>';
                }
            } else {
                $error = 'No account found with that combination of email, username, and phone. Please check and try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password - TechZone.pk Admin</title>
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
                        <p class="text-muted small mb-0">Admin Password Reset</p>
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
