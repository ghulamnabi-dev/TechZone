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
require_once __DIR__ . '/../../core/validation.php';

$pageTitle = 'Forgot Password';
require_once __DIR__ . '/../includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf_token();
    } catch (RuntimeException $e) {
        $error = 'Your session has expired. Please refresh the page and try again.';
    }

    // Throttle reset requests per IP + session (5 per 15 minutes)
    if ($error === '' && ($locked = login_lock_remaining($conn, 'customer_reset')) > 0) {
        $error = 'Too many reset attempts. Please try again in ' . max(1, (int) ceil($locked / 60)) . ' minutes.';
    }

    if ($error === '') {
        login_register_failure($conn, 'customer_reset');
        $email = v_str($_POST['email'] ?? '', 150) ?? '';
        $name  = v_str($_POST['name'] ?? '', 100) ?? '';
        $phone = v_str($_POST['phone'] ?? '', 30) ?? '';

        if ($email === '' || $name === '' || $phone === '') {
            $error = 'Please enter your email, full name, and phone number to verify your account.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $phoneNorm = preg_replace('/[\s\-]+/', '', $phone);
            $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND name = ? AND (phone = ? OR REPLACE(REPLACE(COALESCE(phone,''), ' ', ''), '-', '') = ?)");
            $stmt->execute([$email, $name, $phone, $phoneNorm]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            // Same response whether or not the details matched (no account enumeration).
            $message = 'If those details match an account, a password reset link has been sent to its email address.';

            if ($customer) {
                // Only the sha256 of the token is stored; 1 hour expiry; single use.
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);

                $conn->prepare("DELETE FROM customer_password_resets WHERE customer_id = ? OR expires_at < NOW()")->execute([$customer['id']]);
                $stmt = $conn->prepare("INSERT INTO customer_password_resets (customer_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)");
                $stmt->execute([$customer['id'], $hash]);

                $resetLink = site_url('auth/reset_password.php?token=' . urlencode($token));
                $emailSent = false;
                if (env_flag('PASSWORD_RESET_EMAIL')) {
                    $fullResetUrl = absolute_url($resetLink);
                    $emailSent = @mail($email, 'TechZone.pk - Reset your password',
                        "You requested a password reset. Open this link (valid for 1 hour):\n\n" . $fullResetUrl . "\n\nIf you did not request this, ignore this email.",
                        "From: noreply@techzone.pk\r\nContent-Type: text/plain; charset=UTF-8");
                }
                // Demo / local only: show the link on screen (off by default; name+phone are not secrets).
                if (!$emailSent && env_flag('PASSWORD_RESET_SHOW_LINK', app_debug())) {
                    $message = 'Identity verified. Use the link below to set a new password (valid for 1 hour).';
                    $message .= '<br><br><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" class="btn btn-dark contact-submit-btn">Reset password</a>';
                }
            }
        }
    }
}
?>

<div class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card auth-card">
                    <div class="card-header">
                        <h2><i class="fas fa-key me-2"></i>Forgot Password</h2>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">Enter your email, full name, and phone so we can verify your account.</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if (!$message): ?>
                        <form method="post" novalidate>
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-envelope me-2"></i>Email</label>
                                <input type="email" name="email" class="form-control" placeholder="Your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-user me-2"></i>Full name</label>
                                <input type="text" name="name" class="form-control" placeholder="Name as registered" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-phone me-2"></i>Phone</label>
                                <input type="text" name="phone" class="form-control" placeholder="e.g. 03xx-xxxxxxx" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 btn-lg contact-submit-btn"><i class="fas fa-check me-2"></i>Verify & get reset link</button>
                        </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fw-bold auth-link">Back to login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
