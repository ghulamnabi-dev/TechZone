<?php
$pageTitle = 'Contact Us';
require_once 'includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

<!-- Page Hero -->
<div class="inner-page-hero">
    <div class="container">
        <h1 class="inner-page-hero-title"><i class="fas fa-envelope me-3"></i>Contact Us</h1>
        <p class="inner-page-hero-sub">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
    </div>
</div>

<div class="container my-5">

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">

        <!-- Contact Info sidebar -->
        <div class="col-lg-4 col-md-5">
            <div class="contact-info-panel h-100">
                <h5 class="contact-info-heading">Get in touch</h5>

                <div class="contact-info-item">
                    <div class="contact-info-icon"><i class="fas fa-phone"></i></div>
                    <div>
                        <div class="contact-info-label">Phone</div>
                        <div class="contact-info-value">+92 301 1427871</div>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
                    <div>
                        <div class="contact-info-label">Email</div>
                        <div class="contact-info-value">info@techzone.pk</div>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <div class="contact-info-label">Address</div>
                        <div class="contact-info-value">Lahore, Pakistan</div>
                    </div>
                </div>

                <div class="contact-info-item">
                    <div class="contact-info-icon"><i class="fas fa-clock"></i></div>
                    <div>
                        <div class="contact-info-label">Business Hours</div>
                        <div class="contact-info-value">Mon–Sat, 10am – 8pm</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form -->
        <div class="col-lg-7 col-md-7">
            <div class="contact-form-card">
                <h5 class="contact-form-heading">Send a Message</h5>
                <div id="errorMessage" class="alert alert-danger d-none"></div>
                <form method="POST" id="contactForm" novalidate>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="contactName" class="form-label">Your Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contactName" name="name" autocomplete="name" placeholder="John Doe">
                        </div>
                        <div class="col-sm-6">
                            <label for="contactEmail" class="form-label">Your Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="contactEmail" name="email" autocomplete="email" placeholder="you@example.com">
                        </div>
                        <div class="col-12">
                            <label for="contactSubject" class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="contactSubject" name="subject" autocomplete="off" placeholder="How can we help?">
                        </div>
                        <div class="col-12">
                            <label for="contactMessage" class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="contactMessage" name="message" rows="5" autocomplete="off" placeholder="Tell us more…"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-dark w-100 contact-submit-btn" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </div>
                    </div>
                </form>
            </div>
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
    
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
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Message';
    })
    .catch(error => {
        FormValidator.showGeneralError('errorMessage', 'Failed to send message. Please try again.');
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Send Message';
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
