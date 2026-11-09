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

<div class="auth-shell">
<div class="auth-wrap">
    <a class="auth-mark t-wordmark t-wordmark--mark" href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">TechZone</a>
    <div class="auth-panel">
        <h1 class="auth-title">Reset your password</h1>
        <p class="auth-lead">Enter the email, full name and phone number on your account so we can verify it.</p>
                        <?php if ($error): ?>
                            <div class="alert alert-danger pg-alert pg-alert--error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($message): ?>
                            <div class="alert alert-success pg-alert pg-alert--ok"><?php echo $message; ?></div>
                        <?php endif; ?>

                        <?php if (!$message): ?>
                        <form method="post" novalidate>
                            <?= csrf_field() ?>
                            <div class="field">
                                <label class="field__label" for="fo_email">Email</label>
                                <input id="fo_email" type="email" name="email" class="field__input" placeholder="Your email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="fo_name">Full name</label>
                                <input id="fo_name" type="text" name="name" class="field__input" placeholder="Name as registered" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                            </div>
                            <div class="field">
                                <label class="field__label" for="fo_phone">Phone</label>
                                <input id="fo_phone" type="text" name="phone" class="field__input" placeholder="e.g. 03xx-xxxxxxx" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            </div>
                            <button type="submit" class="btn--primary btn--block">Verify & get reset link</button>
                        </form>
                        <?php endif; ?>

                        <div class="auth-foot">
                            <a href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="pg-link">Back to login</a>
                        </div>
    </div>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
