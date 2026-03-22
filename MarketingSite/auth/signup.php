<?php
session_start();
require_once __DIR__ . '/../config/paths.php';
// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: ' . site_url('index.php'));
    exit();
}
$pageTitle = 'Sign Up';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card auth-card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-plus me-2"></i>Create Account</h2>
                        <p class="mb-0 mt-2" style="opacity: 0.9;">Join TechZone.pk and start shopping today!</p>
                    </div>
                    <div class="card-body">
                    <div id="errorMessage" class="alert alert-danger d-none"></div>
                    <div id="successMessage" class="alert alert-success d-none"></div>
                    
                        <form id="signupForm" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-user me-2"></i>Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" placeholder="Enter your full name">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-envelope me-2"></i>Email Address *</label>
                                    <input type="text" class="form-control" id="email" name="email" placeholder="Enter your email">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Create a password">
                                    <small class="text-muted">Minimum 6 characters</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-lock me-2"></i>Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-phone me-2"></i>Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone" placeholder="03xx-xxxxxxx" maxlength="12">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-city me-2"></i>City</label>
                                <input type="text" class="form-control" id="city" name="city" placeholder="Enter your city">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-map-marker-alt me-2"></i>Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter your address"></textarea>
                            </div>
                            <button type="submit" class="btn btn-dark w-100 btn-lg contact-submit-btn"><i class="fas fa-user-plus me-2"></i>Create Account</button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <p class="mb-0">Already have an account? <a href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fw-bold auth-link">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(marketing_script_url('form-validation.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
// Clear validation state on input (remove red border when user types)
document.addEventListener('DOMContentLoaded', function() {
    const fields = ['name', 'email', 'password', 'confirmPassword', 'phone', 'city', 'address'];
    fields.forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                // If confirm password field, also clear password field validation if passwords match
                if (fieldId === 'confirmPassword') {
                    const passwordField = document.getElementById('password');
                    const confirmPasswordField = document.getElementById('confirmPassword');
                    if (passwordField && confirmPasswordField && passwordField.value === confirmPasswordField.value) {
                        passwordField.classList.remove('is-invalid');
                        confirmPasswordField.classList.remove('is-invalid');
                    }
                }
                // If password field changes, also check confirm password
                if (fieldId === 'password') {
                    const confirmPasswordField = document.getElementById('confirmPassword');
                    if (confirmPasswordField && confirmPasswordField.value && this.value === confirmPasswordField.value) {
                        confirmPasswordField.classList.remove('is-invalid');
                    }
                }
            });
        }
    });
    
    // Auto-format phone number: 03xx-xxxxxxx
    const phoneField = document.getElementById('phone');
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
            } else if (value.length > 0 && !value.startsWith('03')) {
                // If user starts typing something that doesn't start with 03, clear it
                if (value.length === 1 && value !== '0') {
                    this.value = '';
                } else if (value.length === 2 && value !== '03') {
                    this.value = '';
                }
            }
        });
    }
});

document.getElementById('signupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Clear previous errors
    FormValidator.clearAllErrors();
    FormValidator.hideGeneralError('errorMessage');
    FormValidator.hideGeneralError('successMessage');
    
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const phone = document.getElementById('phone').value.trim();
    const errorMsg = document.getElementById('errorMessage');
    const successMsg = document.getElementById('successMessage');
    
    let isValid = true;
    
    // Validate name
    const nameValidation = FormValidator.validateRequired(name, 'Full Name');
    if (!nameValidation.valid) {
        document.getElementById('name').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('name').classList.remove('is-invalid');
    }
    
    // Validate email
    const emailValidation = FormValidator.validateEmail(email);
    if (!emailValidation.valid) {
        document.getElementById('email').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }
    
    // Validate password
    const passwordValidation = FormValidator.validatePassword(password, 6);
    if (!passwordValidation.valid) {
        document.getElementById('password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('password').classList.remove('is-invalid');
    }
    
    // Validate confirm password
    const confirmPasswordValidation = FormValidator.validateConfirmPassword(password, confirmPassword);
    if (!confirmPasswordValidation.valid) {
        document.getElementById('confirmPassword').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('confirmPassword').classList.remove('is-invalid');
    }
    
    // Validate phone (optional but if provided, must be valid)
    let phoneValidation = { valid: true };
    if (phone) {
        phoneValidation = FormValidator.validatePhone(phone);
        if (!phoneValidation.valid) {
            document.getElementById('phone').classList.add('is-invalid');
            isValid = false;
        } else {
            document.getElementById('phone').classList.remove('is-invalid');
        }
    }
    
    // If validation fails, show specific error message for first invalid field
    if (!isValid) {
        let errorMessage = 'Please fill all required fields';
        
        // Find the first invalid field and show its specific error
        if (!nameValidation.valid) {
            errorMessage = nameValidation.message;
        } else if (!emailValidation.valid) {
            errorMessage = emailValidation.message;
        } else if (!passwordValidation.valid) {
            errorMessage = passwordValidation.message;
        } else if (!confirmPasswordValidation.valid) {
            errorMessage = confirmPasswordValidation.message;
        } else if (phone && !phoneValidation.valid) {
            errorMessage = phoneValidation.message;
        }
        
        FormValidator.showGeneralError('errorMessage', errorMessage);
        const firstError = this.querySelector('.is-invalid');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    
    // Hide general error messages
    errorMsg.classList.add('d-none');
    successMsg.classList.add('d-none');
    
    const formData = new FormData(this);
    
    fetch(marketingUrl('api/signup.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success notification
            showNotification('Signup successful! Redirecting to login...', 'success');
            
            setTimeout(() => {
                window.location.href = <?php echo json_encode(site_url('auth/login.php?registered=1')); ?>;
            }, 1500);
        } else {
            // Show error notification
            showNotification(data.message || 'Signup failed. Please try again.', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred. Please try again.', 'error');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>