<?php
require_once __DIR__ . '/../config/paths.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - TechZone.pk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body class="admin-login-body">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow-lg">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <h2 class="fw-bold text-dark mb-1">TechZone.pk</h2>
                        <p class="text-muted mb-0">Admin Panel Login</p>
                    </div>

                    <form id="adminLoginForm" novalidate>
                        <div id="errorMessage" class="alert alert-danger d-none mb-3"></div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="adminUsername" placeholder="Enter email address" autocomplete="email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" id="adminPassword" placeholder="Enter password" autocomplete="current-password">
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2">Login to Dashboard</button>
                    </form>

                    <div class="text-center mt-4 pt-3 admin-login-helper">
                        <small class="text-muted d-block mb-2">Default: admin@techzone.pk / admin1234</small>
                        <a href="<?php echo htmlspecialchars(admin_url('auth/signup.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none fw-medium d-block mb-1">Create new account</a>
                        <a href="<?php echo htmlspecialchars(admin_url('auth/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none small">Forgot password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
