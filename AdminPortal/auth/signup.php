<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . admin_url('manage/dashboard.php'));
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Signup - TechZone.pk</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(admin_url('assets/css/admin-style.css'), ENT_QUOTES, 'UTF-8'); ?>?v=<?php echo (int) filemtime(__DIR__ . '/../assets/css/admin-style.css'); ?>">
</head>
<body class="admin-login-body">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-lg admin-login-card--signup">
                    <div class="card-body p-4">
                        <div class="text-center mb-3">
                            <h2 class="fw-bold text-dark mb-0" style="font-size: 1.5rem;">TechZone.pk</h2>
                            <p class="text-muted small">Admin Signup</p>
                        </div>

                        <form id="adminSignupForm" novalidate class="admin-signup-form">
                            <div id="errorMessage" class="alert alert-danger d-none mb-2 py-2"></div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small">Full Name</label>
                                    <input type="text" class="form-control form-control-sm" id="adminName" placeholder="Full name" autocomplete="name">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">User Name</label>
                                    <input type="text" class="form-control form-control-sm" id="adminUsername" placeholder="User name" autocomplete="username">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Email</label>
                                <input type="email" class="form-control form-control-sm" id="adminEmail" placeholder="Email address" autocomplete="email">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small">Phone</label>
                                <input type="text" class="form-control form-control-sm" id="adminPhone" placeholder="03xx-xxxxxxx" autocomplete="tel" maxlength="12">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="form-label small">Password</label>
                                    <input type="password" class="form-control form-control-sm" id="adminPassword" placeholder="Password" autocomplete="new-password">
                                </div>
                                <div class="col-6">
                                    <label class="form-label small">Confirm</label>
                                    <input type="password" class="form-control form-control-sm" id="adminConfirmPassword" placeholder="Confirm" autocomplete="new-password">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 btn-sm mt-2 py-2">
                                <i class="fas fa-user-plus"></i> Create Account
                            </button>
                        </form>

                        <div class="text-center mt-2 pt-2 small">
                            <p class="mb-0">Already have an account? <a href="<?php echo htmlspecialchars(admin_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Login here</a></p>
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
            var signupForm = document.getElementById('adminSignupForm');
            if (!signupForm) return;
            
            var nameField = document.getElementById('adminName');
            var usernameField = document.getElementById('adminUsername');
            var emailField = document.getElementById('adminEmail');
            var phoneField = document.getElementById('adminPhone');
            var passwordField = document.getElementById('adminPassword');
            var confirmPasswordField = document.getElementById('adminConfirmPassword');
            var errorMsg = document.getElementById('errorMessage');
            
            var fields = [nameField, usernameField, emailField, phoneField, passwordField, confirmPasswordField];
            
            // Clear validation state on input
            fields.forEach(function(field) {
                if (field) {
                    field.addEventListener('input', function() {
                        this.classList.remove('is-invalid');
                        if (errorMsg) errorMsg.classList.add('d-none');
                    });
                }
            });
            
            // Auto-format phone number: 03xx-xxxxxxx
            if (phoneField) {
                phoneField.addEventListener('input', function() {
                    var value = this.value.replace(/\D/g, ''); // Remove non-digits
                    if (value.length > 0 && value.startsWith('03')) {
                        if (value.length <= 4) {
                            this.value = value;
                        } else if (value.length <= 11) {
                            this.value = value.substring(0, 4) + '-' + value.substring(4);
                        } else {
                            this.value = value.substring(0, 4) + '-' + value.substring(4, 11);
                        }
                    }
                });
            }
            
            // Form submit handler
            signupForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Clear previous errors
                if (errorMsg) {
                    errorMsg.classList.add('d-none');
                }
                fields.forEach(function(field) {
                    if (field) field.classList.remove('is-invalid');
                });
                
                var name = nameField ? nameField.value.trim() : '';
                var username = usernameField ? usernameField.value.trim() : '';
                var email = emailField ? emailField.value.trim() : '';
                var phone = phoneField ? phoneField.value.trim() : '';
                var password = passwordField ? passwordField.value : '';
                var confirmPassword = confirmPasswordField ? confirmPasswordField.value : '';
                
                var isValid = true;
                var errorMessage = '';
                
                // Validate name
                if (name === '') {
                    if (nameField) nameField.classList.add('is-invalid');
                    isValid = false;
                    errorMessage = 'Full Name is required';
                }
                
                // Validate username
                if (username === '') {
                    if (usernameField) usernameField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'User Name is required';
                    }
                } else if (username.length < 3) {
                    if (usernameField) usernameField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Username must be at least 3 characters long';
                    }
                } else {
                    // Username can only contain letters, numbers, and underscores
                    var usernameRegex = /^[a-zA-Z0-9_]+$/;
                    if (!usernameRegex.test(username)) {
                        if (usernameField) usernameField.classList.add('is-invalid');
                        isValid = false;
                        if (!errorMessage) {
                            errorMessage = 'Username can only contain letters, numbers, and underscores';
                        }
                    }
                }
                
                // Validate email
                if (email === '') {
                    if (emailField) emailField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Email is required';
                    }
                } else {
                    // Basic email validation
                    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        if (emailField) emailField.classList.add('is-invalid');
                        isValid = false;
                        if (!errorMessage) {
                            errorMessage = 'Please enter a valid email address';
                        }
                    }
                }
                
                // Validate phone (if provided)
                if (phone !== '') {
                    // Pakistani phone format: 03xx-xxxxxxx
                    var phoneRegex = /^03\d{2}-\d{7}$/;
                    if (!phoneRegex.test(phone)) {
                        if (phoneField) phoneField.classList.add('is-invalid');
                        isValid = false;
                        if (!errorMessage) {
                            errorMessage = 'Phone number must be in format 03xx-xxxxxxx';
                        }
                    }
                }
                
                // Validate password
                if (password === '') {
                    if (passwordField) passwordField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Password is required';
                    }
                } else if (password.length < 6) {
                    if (passwordField) passwordField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Password must be at least 6 characters long';
                    }
                }
                
                // Validate confirm password
                if (confirmPassword === '') {
                    if (confirmPasswordField) confirmPasswordField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Please confirm your password';
                    }
                } else if (password !== confirmPassword) {
                    if (confirmPasswordField) confirmPasswordField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Passwords do not match';
                    }
                }
                
                // If validation fails, show error message
                if (!isValid) {
                    if (errorMsg) {
                        errorMsg.textContent = errorMessage;
                        errorMsg.classList.remove('d-none');
                    }
                    var firstError = signupForm.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.focus();
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                
                // Submit via AJAX
                fetch(adminUrl('api/signup.php'), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        name: name,
                        username: username,
                        email: email,
                        phone: phone,
                        password: password
                    })
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        showNotification(data.message + '! Please login', 'success');
                        setTimeout(function() {
                            window.location.href = <?php echo json_encode(admin_url('index.php')); ?>;
                        }, 1000);
                    } else {
                        if (errorMsg) {
                            errorMsg.textContent = data.message || 'Signup failed';
                            errorMsg.classList.remove('d-none');
                        } else {
                            alert(data.message || 'Signup failed');
                        }
                    }
                })
                .catch(function(error) {
                    if (errorMsg) {
                        errorMsg.textContent = 'Network error occurred';
                        errorMsg.classList.remove('d-none');
                    } else {
                        alert('Network error occurred');
                    }
                });
            });
        });
    </script>
</body>
</html>