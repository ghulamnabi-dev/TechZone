<?php
$pageTitle = 'Contact Us';
require_once 'includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        require_csrf_token();
        $csrfOk = true;
    } catch (RuntimeException $e) {
        $csrfOk = false;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrfOk) {
    $error = 'Your session has expired. Please refresh the page and try again.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($subject) || empty($message_text)) {
        $error = 'Please fill in all fields';
    } else {
        $message = 'Thank you for contacting us! We will get back to you soon.';
        // In a real application, you would send an email or save to database here
    }
}
?>

<?php require_once __DIR__ . '/includes/pages.php'; $store = pg_store(); $week = pg_week_hours($store['hours_text']); ?>
<div class="container pg-page pg-contact">
    <div class="pg-contact__grid">
        <div>
            <header class="pg-head">
                <h1 class="pg-title">Contact us</h1>
                <p class="pg-lead">Questions about a part, a build or an order? Write to us and we reply within one working day. For an order, include its number.</p>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-success pg-alert pg-alert--ok"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger pg-alert pg-alert--error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div id="errorMessage" class="alert alert-danger pg-alert pg-alert--error d-none" role="alert"></div>
            <form method="POST" id="contactForm" class="pg-form" novalidate>
                <?php echo csrf_field(); ?>
                <div class="pg-form__row">
                    <div class="field"><label for="contactName" class="field__label">Name</label><input type="text" class="field__input" id="contactName" name="name" autocomplete="name"></div>
                    <div class="field"><label for="contactEmail" class="field__label">Email</label><input type="email" class="field__input" id="contactEmail" name="email" autocomplete="email"></div>
                </div>
                <div class="field"><label for="contactSubject" class="field__label">Subject</label><input type="text" class="field__input" id="contactSubject" name="subject" autocomplete="off" placeholder="For example: order #1042, or RTX 4060 availability"></div>
                <div class="field"><label for="contactMessage" class="field__label">Message</label><textarea class="field__input" id="contactMessage" name="message" rows="6" autocomplete="off"></textarea></div>
                <div><button type="submit" class="btn--primary contact-send" id="submitBtn">Send message</button></div>
            </form>
            <p class="pg-contact__more">A faulty part? Use the <a class="pg-link" href="<?php echo htmlspecialchars(site_url('help/warranty.php'), ENT_QUOTES, 'UTF-8'); ?>">warranty claim form</a>. Most other answers are in the <a class="pg-link" href="<?php echo htmlspecialchars(site_url('help/index.php'), ENT_QUOTES, 'UTF-8'); ?>">help centre</a>.</p>
        </div>

        <aside class="pg-storebox pg-band-signal" aria-labelledby="storeboxTitle">
            <h2 class="pg-storebox__title" id="storeboxTitle">The shop</h2>
            <p class="pg-storebox__addr"><?php echo pg_e($store['address']); ?></p>
            <p class="pg-storebox__today"><?php echo pg_e(pg_today_line($week)); ?></p>
            <dl class="pg-storebox__dl">
                <div><dt>Phone</dt><dd><a href="tel:<?php echo pg_e(preg_replace('/[^\d+]/', '', $store['phone'])); ?>"><?php echo pg_e($store['phone']); ?></a></dd></div>
                <div><dt>WhatsApp</dt><dd><a href="<?php echo pg_e(pg_whatsapp_url($store['whatsapp'])); ?>" target="_blank" rel="noopener"><?php echo pg_e($store['whatsapp_text']); ?></a></dd></div>
                <div><dt>Email</dt><dd><a href="mailto:<?php echo pg_e($store['email']); ?>"><?php echo pg_e($store['email']); ?></a></dd></div>
                <div><dt>Hours</dt><dd><?php echo pg_e($store['hours_text']); ?></dd></div>
            </dl>
            <a class="btn--primary" href="<?php echo pg_e(site_url('store.php')); ?>">Directions and parking</a>
        </aside>
    </div>
</div>

<script src="<?php echo htmlspecialchars(marketing_script_url('form-validation.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script>
// Clear validation state on input (remove red border when user types)
document.addEventListener('DOMContentLoaded', function() {
    const fields = ['contactName', 'contactEmail', 'contactSubject', 'contactMessage'];
    
    fields.forEach(function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        }
    });
});

// Handle form submission (similar to signup/login)
document.getElementById('contactForm').addEventListener('submit', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Clear previous errors
    FormValidator.clearAllErrors();
    FormValidator.hideGeneralError('errorMessage');
    
    const submitBtn = document.getElementById('submitBtn');
    const errorMsg = document.getElementById('errorMessage');
    const originalText = submitBtn.innerHTML;
    let isValid = true;
    
    // Validate name
    const name = document.getElementById('contactName').value.trim();
    const nameValidation = FormValidator.validateRequired(name, 'Your Name');
    let nameError = null;
    if (!nameValidation.valid) {
        document.getElementById('contactName').classList.add('is-invalid');
        isValid = false;
        nameError = nameValidation.message;
    } else {
        document.getElementById('contactName').classList.remove('is-invalid');
    }
    
    // Validate email
    const email = document.getElementById('contactEmail').value.trim();
    const emailValidation = FormValidator.validateEmail(email);
    let emailError = null;
    if (!emailValidation.valid) {
        document.getElementById('contactEmail').classList.add('is-invalid');
        isValid = false;
        emailError = emailValidation.message;
    } else {
        document.getElementById('contactEmail').classList.remove('is-invalid');
    }
    
    // Validate subject
    const subject = document.getElementById('contactSubject').value.trim();
    const subjectValidation = FormValidator.validateRequired(subject, 'Subject');
    let subjectError = null;
    if (!subjectValidation.valid) {
        document.getElementById('contactSubject').classList.add('is-invalid');
        isValid = false;
        subjectError = subjectValidation.message;
    } else if (subject.length < 3) {
        document.getElementById('contactSubject').classList.add('is-invalid');
        isValid = false;
        subjectError = 'Subject must be at least 3 characters long';
    } else {
        document.getElementById('contactSubject').classList.remove('is-invalid');
    }
    
    // Validate message
    const message = document.getElementById('contactMessage').value.trim();
    const messageValidation = FormValidator.validateRequired(message, 'Message');
    let messageError = null;
    if (!messageValidation.valid) {
        document.getElementById('contactMessage').classList.add('is-invalid');
        isValid = false;
        messageError = messageValidation.message;
    } else if (message.length < 10) {
        document.getElementById('contactMessage').classList.add('is-invalid');
        isValid = false;
        messageError = 'Message must be at least 10 characters long';
    } else {
        document.getElementById('contactMessage').classList.remove('is-invalid');
    }
    
    // If validation fails, show specific error message for first invalid field
    if (!isValid) {
        let errorMessage = 'Please fill all required fields';
        
        // Find the first invalid field and show its specific error
        if (nameError) {
            errorMessage = nameError;
        } else if (emailError) {
            errorMessage = emailError;
        } else if (subjectError) {
            errorMessage = subjectError;
        } else if (messageError) {
            errorMessage = messageError;
        }
        
        FormValidator.showGeneralError('errorMessage', errorMessage);
        const firstError = this.querySelector('.is-invalid');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return false;
    }
    
    // Hide error message if all valid
    FormValidator.hideGeneralError('errorMessage');
    
    // If all validations pass, submit via AJAX
    submitBtn.disabled = true;
    submitBtn.textContent = 'Sending…';
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    formData.append('subject', subject);
    formData.append('message', message);
    
    fetch(marketingUrl('api/contact.php'), {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            FormValidator.showGeneralError('errorMessage', data.message);
            document.getElementById('errorMessage').classList.remove('alert-danger');
            document.getElementById('errorMessage').classList.add('alert-success');
            document.getElementById('contactForm').reset();
            setTimeout(() => {
                document.getElementById('errorMessage').classList.add('d-none');
            }, 5000);
        } else {
            FormValidator.showGeneralError('errorMessage', data.message);
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send message';
    })
    .catch(error => {
        FormValidator.showGeneralError('errorMessage', 'Failed to send message. Please try again.');
        submitBtn.disabled = false;
        submitBtn.textContent = 'Send message';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
