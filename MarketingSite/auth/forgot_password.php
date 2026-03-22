<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['customer_id'])) {
    header('Location: ' . site_url('index.php'));
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../core/security.php';

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

    if ($error === '') {
        $email = trim($_POST['email'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($email === '' || $name === '' || $phone === '') {
            $error = 'Please enter your email, full name, and phone number to verify your account.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $phoneNorm = preg_replace('/[\s\-]+/', '', $phone);
            $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND name = ? AND (phone = ? OR REPLACE(REPLACE(COALESCE(phone,''), ' ', ''), '-', '') = ?)");
            $stmt->execute([$email, $name, $phone, $phoneNorm]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                $token = bin2hex(random_bytes(32));
                $hash  = hash('sha256', $token);
                $expires = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

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

                $stmt = $conn->prepare("INSERT INTO customer_password_resets (customer_id, token_hash, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$customer['id'], $hash, $expires]);

                $resetLink = site_url('auth/reset_password.php?token=' . urlencode($token));
                $message = 'Identity verified. Use the link below to set a new password (valid for 1 hour).';
                $message .= '<br><br><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '" class="btn btn-dark contact-submit-btn">Reset password</a>';
            } else {
                $error = 'No account found with that combination of email, name, and phone. Please check and try again.';
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
