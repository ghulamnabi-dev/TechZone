<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: ' . site_url('index.php'));
    exit();
}
$pageTitle = 'Sign Up';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-shell">
<div class="auth-wrap">
    <a class="auth-mark t-wordmark t-wordmark--mark" href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">TechZone</a>
    <div class="auth-panel">
        <h1 class="auth-title">Create an account</h1>
        <p class="auth-lead">Track orders, reorder parts and review what you bought.</p>
                    <div id="errorMessage" class="alert alert-danger pg-alert pg-alert--error d-none"></div>
                    <div id="successMessage" class="alert alert-success pg-alert pg-alert--ok d-none"></div>
                    
                        <form id="signupForm" novalidate>
                            <div class="auth-stack">
                                <div class="field">
                                    <label class="field__label" for="name">Full name</label>
                                    <input type="text" class="field__input" id="name" name="name" placeholder="Enter your full name">
                                </div>
                                <div class="field">
                                    <label class="field__label" for="email">Email</label>
                                    <input type="text" class="field__input" id="email" name="email" placeholder="Enter your email">
                                </div>
                            </div>
                            <div class="auth-stack">
                                <div class="field">
                                    <label class="field__label" for="password">Password</label>
                                    <input type="password" class="field__input" id="password" name="password" placeholder="Create a password">
                                    <p class="field__hint">At least 8 characters</p>
                                </div>
                                <div class="field">
                                    <label class="field__label" for="confirmPassword">Confirm password</label>
                                    <input type="password" class="field__input" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password">
                                </div>
                            </div>
                            <div class="auth-stack">
                                <div class="field">
                                    <label class="field__label" for="phone">Phone</label>
                                    <input type="text" class="field__input" id="phone" name="phone" placeholder="03xx-xxxxxxx" maxlength="12">
                                </div>
                            </div>
                            <div class="field">
                                <label class="field__label" for="city">City</label>
                                <input type="text" class="field__input" id="city" name="city" placeholder="Enter your city">
                            </div>
                            <div class="field">
                                <label class="field__label" for="address">Address</label>
                                <textarea class="field__input" id="address" name="address" rows="2" placeholder="Enter your address"></textarea>
                            </div>
                            <button type="submit" class="btn--primary btn--block">Create account</button>
                        </form>
                        
                        <div class="auth-foot">
                            <p class="mb-0">Already have an account? <a href="<?php echo htmlspecialchars(site_url('auth/login.php'), ENT_QUOTES, 'UTF-8'); ?>" class="pg-link">Sign in</a></p>
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
    const passwordValidation = FormValidator.validatePassword(password, 8);
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