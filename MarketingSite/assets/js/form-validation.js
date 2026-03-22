/**
 * Form Validation Utility
 * Provides client-side validation for forms with inline error messages
 */
const FormValidator = {
    /**
     * Validate email format
     */
    validateEmail: function(email) {
        if (!email || email.trim() === '') {
            return { valid: false, message: 'Email is required' };
        }
        
        const emailValue = email.trim();
        
        // Check for @ symbol
        if (!emailValue.includes('@')) {
            return { valid: false, message: `Please include an '@' in the email address. '${emailValue}' is missing an '@'.` };
        }
        
        // Check for . after @
        const parts = emailValue.split('@');
        if (parts.length !== 2) {
            return { valid: false, message: 'Please enter a valid email address' };
        }
        
        if (!parts[1].includes('.')) {
            return { valid: false, message: `Please include a domain in the email address. '${emailValue}' is missing a domain.` };
        }
        
        // More comprehensive email regex
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(emailValue)) {
            return { valid: false, message: 'Please enter a valid email address' };
        }
        
        return { valid: true, message: '' };
    },
    
    /**
     * Validate password
     */
    validatePassword: function(password, minLength = 6) {
        if (!password || password.trim() === '') {
            return { valid: false, message: 'Password is required' };
        }
        
        if (password.length < minLength) {
            return { valid: false, message: `Password must be at least ${minLength} characters long` };
        }
        
        return { valid: true, message: '' };
    },
    
    /**
     * Validate confirm password matches password
     */
    validateConfirmPassword: function(password, confirmPassword) {
        if (!confirmPassword || confirmPassword.trim() === '') {
            return { valid: false, message: 'Please confirm your password' };
        }
        
        if (password !== confirmPassword) {
            return { valid: false, message: 'Passwords do not match' };
        }
        
        return { valid: true, message: '' };
    },
    
    /**
     * Validate required field
     */
    validateRequired: function(value, fieldName) {
        if (!value || value.trim() === '') {
            return { valid: false, message: `${fieldName} is required` };
        }
        return { valid: true, message: '' };
    },
    
    /**
     * Validate phone number (Pakistani format: 03xx-xxxxxxx)
     */
    validatePhone: function(phone) {
        if (!phone || phone.trim() === '') {
            return { valid: true, message: '' }; // Phone is optional
        }
        
        const phoneValue = phone.trim();
        // Pakistani phone format: 03xx-xxxxxxx
        const phoneRegex = /^03\d{2}-\d{7}$/;
        if (!phoneRegex.test(phoneValue)) {
            return { valid: false, message: 'Phone number must be in format 03xx-xxxxxxx' };
        }
        
        return { valid: true, message: '' };
    },
    
    /**
     * Show inline error message for a field
     */
    showFieldError: function(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        // Remove existing error
        this.removeFieldError(fieldId);
        
        // Add error class to field
        field.classList.add('is-invalid');
        field.classList.remove('is-valid');
        
        // Create error message element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.id = fieldId + '_error';
        errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-1"></i>${message}`;
        
        // Insert error message after the field
        field.parentNode.appendChild(errorDiv);
        
        // Focus on the field
        field.focus();
    },
    
    /**
     * Remove inline error message for a field
     */
    removeFieldError: function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.classList.remove('is-invalid');
            // Don't automatically add is-valid - only add it when validation actually passes
        }
        
        const errorDiv = document.getElementById(fieldId + '_error');
        if (errorDiv) {
            errorDiv.remove();
        }
    },
    
    /**
     * Show valid state for a field (only when validation passes)
     */
    showFieldValid: function(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        // Remove error state
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
        
        // Remove any error messages
        const errorDiv = document.getElementById(fieldId + '_error');
        if (errorDiv) {
            errorDiv.remove();
        }
    },
    
    /**
     * Clear validation state (remove both valid and invalid classes)
     */
    clearFieldValidation: function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.classList.remove('is-invalid');
            field.classList.remove('is-valid');
        }
        
        const errorDiv = document.getElementById(fieldId + '_error');
        if (errorDiv) {
            errorDiv.remove();
        }
    },
    
    /**
     * Clear all field errors
     */
    clearAllErrors: function() {
        const errorMessages = document.querySelectorAll('.invalid-feedback');
        errorMessages.forEach(error => error.remove());
        
        // Remove all validation states
        const validatedFields = document.querySelectorAll('.is-invalid, .is-valid');
        validatedFields.forEach(field => {
            field.classList.remove('is-invalid');
            field.classList.remove('is-valid');
        });
    },
    
    /**
     * Show general error message
     */
    showGeneralError: function(errorElementId, message) {
        const errorElement = document.getElementById(errorElementId);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('d-none');
        }
    },
    
    /**
     * Hide general error message
     */
    hideGeneralError: function(errorElementId) {
        const errorElement = document.getElementById(errorElementId);
        if (errorElement) {
            errorElement.classList.add('d-none');
        }
    },
    
    /**
     * Setup real-time validation for email field
     */
    setupEmailValidation: function(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        // Track if field has been touched
        let touched = false;
        
        field.addEventListener('focus', function() {
            touched = true;
        });
        
        // Validate on blur (only if field has been touched)
        field.addEventListener('blur', function() {
            // Only validate if field has been touched
            if (!touched) return;
            
            const email = this.value.trim();
            if (email === '') {
                // Empty field - clear validation state
                FormValidator.clearFieldValidation(fieldId);
                return;
            }
            
            const validation = FormValidator.validateEmail(email);
            if (!validation.valid) {
                FormValidator.showFieldError(fieldId, validation.message);
            } else {
                // Only show valid state when email is actually valid
                FormValidator.showFieldValid(fieldId);
            }
        });
        
        // Clear validation state on input (don't show valid until blur)
        field.addEventListener('input', function() {
            FormValidator.clearFieldValidation(fieldId);
        });
    },
    
    /**
     * Setup real-time validation for password field
     */
    setupPasswordValidation: function(fieldId, minLength = 6) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        field.addEventListener('blur', function() {
            const password = this.value;
            if (password === '') {
                // Empty field - clear validation state
                FormValidator.clearFieldValidation(fieldId);
                return;
            }
            
            const validation = FormValidator.validatePassword(password, minLength);
            if (!validation.valid) {
                FormValidator.showFieldError(fieldId, validation.message);
            } else {
                // Only show valid state when password is actually valid
                FormValidator.showFieldValid(fieldId);
            }
        });
        
        // Clear validation state on input
        field.addEventListener('input', function() {
            FormValidator.clearFieldValidation(fieldId);
        });
    },
    
    /**
     * Setup real-time validation for required field
     */
    setupRequiredValidation: function(fieldId, fieldName) {
        const field = document.getElementById(fieldId);
        if (!field) return;
        
        // Track if field has been touched
        let touched = false;
        
        field.addEventListener('focus', function() {
            touched = true;
        });
        
        field.addEventListener('blur', function() {
            // Only validate if field has been touched
            if (!touched) return;
            
            const value = this.value.trim();
            if (value === '') {
                // Empty field - clear validation state
                FormValidator.clearFieldValidation(fieldId);
                return;
            }
            
            const validation = FormValidator.validateRequired(value, fieldName);
            if (!validation.valid) {
                FormValidator.showFieldError(fieldId, validation.message);
            } else {
                // Only show valid state when field is actually valid
                FormValidator.showFieldValid(fieldId);
            }
        });
        
        // Clear validation state on input
        field.addEventListener('input', function() {
            FormValidator.clearFieldValidation(fieldId);
        });
    }
};

