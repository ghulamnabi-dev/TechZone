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

<div class="container page">
    <div class="page-intro">
        <h1>Contact</h1>
        <p class="lead-text">Questions about a part, a build or an order? Send us a message and we will reply within one working day. For order questions, include your order number.</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4 g-lg-5">
        <div class="col-lg-7">
            <div id="errorMessage" class="alert alert-danger d-none" role="alert"></div>
            <form method="POST" id="contactForm" novalidate>
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="contactName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="contactName" name="name" autocomplete="name">
                    </div>
                    <div class="col-sm-6">
                        <label for="contactEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="contactEmail" name="email" autocomplete="email">
                    </div>
                    <div class="col-12">
                        <label for="contactSubject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="contactSubject" name="subject" autocomplete="off" placeholder="For example: order #1042, or RTX 4060 availability">
                    </div>
                    <div class="col-12">
                        <label for="contactMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="contactMessage" name="message" rows="6" autocomplete="off"></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-ink contact-send" id="submitBtn">Send message</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <aside class="info-box">
                <h2 class="info-box__title">TechZone, Lahore</h2>
                <dl class="info-list">
                    <div><dt>Address</dt><dd>Hafeez Centre, Gulberg III, Lahore</dd></div>
                    <div><dt>Phone</dt><dd><a href="tel:+923011427871">+92 301 1427871</a></dd></div>
                    <div><dt>Email</dt><dd><a href="mailto:info@techzone.pk">info@techzone.pk</a></dd></div>
                    <div><dt>Hours</dt><dd>Monday to Saturday, 10am to 8pm<br>Closed on Sunday</dd></div>
                </dl>
                <p class="muted mb-0">Order pickup is available during shop hours once we confirm your order is ready.</p>
            </aside>
        </div>
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
