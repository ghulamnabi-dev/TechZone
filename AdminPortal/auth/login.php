<?php
require_once __DIR__ . '/../../core/bootstrap.php';   // security headers + hardened session
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../../core/security.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>Sign in · TechZone Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body class="admin-login-body">
<main class="auth-card">
    <div class="auth-brand">
        <span class="sidebar-brand-icon"><i class="fas fa-microchip"></i></span>
        <div>
            <h1>TechZone.pk</h1>
            <p>Admin portal</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body p-4">
            <h2 class="h6 mb-3" style="font-weight:600">Sign in to your account</h2>
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

                <button type="submit" class="btn btn-primary w-100 py-2 mt-1">Sign in</button>
            </form>
            <div class="auth-demo">Demo: <code>admin@techzone.pk</code> / <code>Admin@1234</code></div>
        </div>
    </div>
    <p class="text-center mt-3 mb-0 small" style="color:var(--ink-3)">No account? <a href="<?php echo htmlspecialchars(admin_url('auth/signup.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none">Create one</a></p>
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
