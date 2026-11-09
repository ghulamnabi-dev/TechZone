<?php
require_once __DIR__ . '/../../core/bootstrap.php';
require_once __DIR__ . '/../config/paths.php';
// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: ' . site_url('index.php'));
    exit();
}
$pageTitle = 'Login';
$redirectTarget = site_url('index.php');
$requestedRedirect = is_string($_GET['redirect'] ?? null) ? $_GET['redirect'] : '';
if ($requestedRedirect === 'checkout') {
    $redirectTarget = site_url('shop/cart.php');
} elseif ($requestedRedirect !== '') {
    // Only same-site relative paths ("/MarketingSite/..."); never "//evil.com" or "https://..."
    $redirectTarget = safe_redirect_path($requestedRedirect, $redirectTarget);
}
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-shell">
<div class="auth-wrap">
    <a class="auth-mark t-wordmark t-wordmark--mark" href="<?php echo htmlspecialchars(site_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">TechZone</a>
    <div class="auth-panel">
        <h1 class="auth-title">Sign in</h1>
        <p class="auth-lead">Use the email and password for your TechZone account.</p>
                    <div id="errorMessage" class="alert alert-danger pg-alert pg-alert--error d-none"></div>
                    <div id="successMessage" class="alert alert-success pg-alert pg-alert--ok d-none"></div>
                    
                        <form id="loginForm" novalidate>
                            <div class="field">
                                <label class="field__label" for="email">Email</label>
                                <input type="text" class="field__input" id="email" name="email" placeholder="Enter your email">
                            </div>
                            <div class="field">
                                <label class="field__label" for="password">Password</label>
                                <input type="password" class="field__input" id="password" name="password" placeholder="Enter your password">
                            </div>
                            <button type="submit" class="btn--primary btn--block">Sign in</button>
                        </form>
                        
                        <div class="auth-foot">
                            <p class="mb-0">New to TechZone? <a href="<?php echo htmlspecialchars(site_url('auth/signup.php'), ENT_QUOTES, 'UTF-8'); ?>" class="pg-link">Create one</a></p>
                            <p class="mb-0 mt-2"><a href="<?php echo htmlspecialchars(site_url('auth/forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>" class="pg-link">Forgot password?</a></p>
                        </div>
    </div>
</div>
</div>
<script src="<?php echo htmlspecialchars(marketing_script_url('form-validation.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
// Show notification if redirected from signup
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
        showNotification('Registration successful! Please login to continue.', 'success');
    <?php endif; ?>
    
    // Clear validation state on input (remove red border when user types)
    const fields = ['email', 'password'];
    fields.forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        }
    });
});

document.getElementById('loginForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Clear previous errors
    FormValidator.clearAllErrors();
    FormValidator.hideGeneralError('errorMessage');
    FormValidator.hideGeneralError('successMessage');
    
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const errorMsg = document.getElementById('errorMessage');
    const successMsg = document.getElementById('successMessage');
    
    let isValid = true;
    
    // Validate email
    const emailValidation = FormValidator.validateEmail(email);
    if (!emailValidation.valid) {
        document.getElementById('email').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('email').classList.remove('is-invalid');
    }
    
    // Validate password
    const passwordValidation = FormValidator.validatePassword(password, 1);
    if (!passwordValidation.valid) {
        document.getElementById('password').classList.add('is-invalid');
        isValid = false;
    } else {
        document.getElementById('password').classList.remove('is-invalid');
    }
    
    // If validation fails, show specific error message for first invalid field
    if (!isValid) {
        let errorMessage = 'Please fill all required fields';
        
        // Find the first invalid field and show its specific error
        if (!emailValidation.valid) {
            errorMessage = emailValidation.message;
        } else if (!passwordValidation.valid) {
            errorMessage = passwordValidation.message;
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
    
    const formData = new FormData();
    formData.append('email', email);
    formData.append('password', password);
    
    fetch(marketingUrl('api/login.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Store session in frontend
            if (typeof SessionManager !== 'undefined' && data.customer) {
                SessionManager.setSession(data.customer);
            }
            
            // Show success notification
            showNotification('Login successful!', 'success');
            
            setTimeout(() => {
                window.location.href = <?php echo json_encode($redirectTarget); ?>;
            }, 1000);
        } else {
            // Show error notification
            showNotification(data.message || 'Login failed. Please try again.', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred. Please try again.', 'error');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>