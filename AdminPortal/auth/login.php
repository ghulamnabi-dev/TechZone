<?php
require_once __DIR__ . '/../../core/bootstrap.php';   // security headers + hardened session
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../../core/security.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php require_once __DIR__ . '/../../core/boot_ui.php'; tz_boot_ui('admin'); ?>
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Sign in · TechZone Admin</title>
    <link rel="stylesheet" data-tz-critical href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" data-tz-critical href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body class="admin-login-body">
<main class="auth-card">
    <div class="auth-brand">
        <span class="brand-mark"><i class="fas fa-microchip"></i></span>
        <div>
            <h1>TechZone</h1>
            <p>Admin</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-4">
            <h2 class="auth-title">Sign in</h2>
            <p class="auth-sub">Use your staff email and password.</p>
            <form id="adminLoginForm" novalidate>
                <div id="errorMessage" class="alert alert-danger d-none mb-3" role="alert"></div>

                <div class="mb-3">
                    <label class="form-label" for="adminUsername">Email</label>
                    <input type="email" class="form-control" id="adminUsername" placeholder="you@techzone.pk" autocomplete="email" autofocus>
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-baseline">
                        <label class="form-label" for="adminPassword">Password</label>
                        <a href="<?php echo htmlspecialchars(admin_url('auth/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none small">Forgot?</a>
                    </div>
                    <input type="password" class="form-control" id="adminPassword" placeholder="••••••••" autocomplete="current-password">
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-1">Sign in</button>
            </form>
            <div class="auth-demo">
                Demo accounts
                <dl>
                    <dt>Owner</dt><dd><code>admin@techzone.pk</code> / <code>Admin@1234</code></dd>
                    <dt>Manager</dt><dd><code>sana.manager@techzone.pk</code> / <code>Manager@1234</code></dd>
                    <dt>Staff</dt><dd><code>bilal.staff@techzone.pk</code> / <code>Staff@1234</code></dd>
                </dl>
            </div>
        </div>
    </div>
    <p class="text-center mt-3 mb-0 muted">Have an invite code? <a href="<?php echo htmlspecialchars(admin_url('auth/signup.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">Create your account</a></p>
</main>

<script src="<?php echo htmlspecialchars(admin_url('assets/js/common.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php include __DIR__ . '/../includes/url-script.php'; ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var loginForm = document.getElementById('adminLoginForm');
        if (!loginForm) return;
        
        var usernameField = document.getElementById('adminUsername');
        var passwordField = document.getElementById('adminPassword');
        var errorMsg = document.getElementById('errorMessage');
        
        if (usernameField) {
            usernameField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (errorMsg) errorMsg.classList.add('d-none');
            });
        }
        
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (errorMsg) errorMsg.classList.add('d-none');
            });
        }
        
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (errorMsg) {
                errorMsg.classList.add('d-none');
            }
            if (usernameField) usernameField.classList.remove('is-invalid');
            if (passwordField) passwordField.classList.remove('is-invalid');
            
            var username = usernameField ? usernameField.value.trim() : '';
            var password = passwordField ? passwordField.value : '';
            
            var isValid = true;
            var errorMessage = '';
            
            if (username === '') {
                if (usernameField) usernameField.classList.add('is-invalid');
                isValid = false;
                errorMessage = 'Email is required';
            } else {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(username)) {
                    if (usernameField) usernameField.classList.add('is-invalid');
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
            }
            
            if (password === '') {
                if (passwordField) passwordField.classList.add('is-invalid');
                isValid = false;
                if (!errorMessage) {
                    errorMessage = 'Password is required';
                }
            }
            
            if (!isValid) {
                if (errorMsg) {
                    errorMsg.textContent = errorMessage;
                    errorMsg.classList.remove('d-none');
                }
                var firstError = loginForm.querySelector('.is-invalid');
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }
            
            var formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            
            fetch(adminUrl('api/login.php'), {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    showNotification('Login successful', 'success');
                    setTimeout(function() {
                        window.location.href = <?php echo json_encode(admin_url('manage/dashboard.php')); ?>;
                    }, 500);
                } else {
                    if (errorMsg) {
                        errorMsg.textContent = data.message || 'Invalid credentials';
                        errorMsg.classList.remove('d-none');
                    }
                }
            })
            .catch(function(error) {
                console.error('Login error:', error);
                if (errorMsg) {
                    errorMsg.textContent = 'Login failed. Please try again.';
                    errorMsg.classList.remove('d-none');
                }
            });
        });
    });
</script>
</body>
</html>
