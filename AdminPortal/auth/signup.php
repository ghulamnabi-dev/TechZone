<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
require_once __DIR__ . '/../../core/security.php';
if (isset($_SESSION['admin_id'])) {
    header('Location: ' . admin_url('manage/dashboard.php'));
    exit();
}
$signupEnabled = env('ADMIN_SIGNUP_CODE') !== null;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
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
                        <div class="auth-brand mb-3">
                            <span class="brand-mark"><i class="fas fa-microchip"></i></span>
                            <div><h1>TechZone</h1><p>Create a staff account</p></div>
                        </div>

                        <form id="adminSignupForm" novalidate class="admin-signup-form">
                            <?php if (!$signupEnabled): ?>
                            <div class="alert alert-warning mb-2 py-2 small" role="status">
                                <i class="fas fa-lock me-1"></i> Admin sign-up is currently closed. Ask an existing administrator for an invite code.
                            </div>
                            <?php endif; ?>
                            <div id="errorMessage" class="alert alert-danger d-none mb-2 py-2" role="alert"></div>
                            <div class="mb-2">
                                <label class="form-label small" for="adminInviteCode">Invite code</label>
                                <input type="text" class="form-control form-control-sm" id="adminInviteCode" placeholder="Invite code from an administrator" autocomplete="off" spellcheck="false" maxlength="200">
                            </div>
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
            var inviteField = document.getElementById('adminInviteCode');
            var submitBtn = signupForm.querySelector('button[type="submit"]');
            var errorMsg = document.getElementById('errorMessage');
            
            var fields = [inviteField, nameField, usernameField, emailField, phoneField, passwordField, confirmPasswordField];
            
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
                var inviteCode = inviteField ? inviteField.value.trim() : '';
                
                var isValid = true;
                var errorMessage = '';

                // Validate invite code
                if (inviteCode === '') {
                    if (inviteField) inviteField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'An invite code is required to create an admin account';
                    }
                }
                
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
                } else if (password.length < 8) {
                    if (passwordField) passwordField.classList.add('is-invalid');
                    isValid = false;
                    if (!errorMessage) {
                        errorMessage = 'Password must be at least 8 characters long';
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
                if (submitBtn) submitBtn.disabled = true;
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
                        password: password,
                        invite_code: inviteCode
                    })
                })
                .then(function(response) {
                    return response.json().catch(function () {
                        return { success: false, message: 'Signup failed (HTTP ' + response.status + '). Please try again.' };
                    }).then(function (data) {
                        data.status = response.status;
                        return data;
                    });
                })
                .then(function(data) {
                    if (submitBtn) submitBtn.disabled = false;
                    if (!data.success && (data.status === 403 || data.status === 429) && inviteField && /invite|sign-up is disabled/i.test(data.message || '')) {
                        inviteField.classList.add('is-invalid');
                        inviteField.focus();
                    }
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
                    if (submitBtn) submitBtn.disabled = false;
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