window.onload = function() {
    loadProfile();
    
    var updateProfileForm = document.getElementById('updateProfileForm');
    var changePasswordForm = document.getElementById('changePasswordForm');
    
    // Setup profile update form validation
    if (updateProfileForm) {
        var nameField = document.getElementById('adminName');
        var emailField = document.getElementById('adminEmail');
        var phoneField = document.getElementById('adminPhone');
        var profileErrorMsg = document.getElementById('profileErrorMessage');
        
        // Clear validation state on input
        if (nameField) {
            nameField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (profileErrorMsg) profileErrorMsg.classList.add('d-none');
            });
        }
        if (emailField) {
            emailField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (profileErrorMsg) profileErrorMsg.classList.add('d-none');
            });
        }
        if (phoneField) {
            phoneField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (profileErrorMsg) profileErrorMsg.classList.add('d-none');
                
                // Auto-format phone number: 03xx-xxxxxxx
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
        
        updateProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleProfileUpdate();
        });
    }
    
    // Setup password change form validation
    if (changePasswordForm) {
        var currentPasswordField = document.getElementById('currentPassword');
        var newPasswordField = document.getElementById('newPassword');
        var confirmPasswordField = document.getElementById('confirmPassword');
        var passwordErrorMsg = document.getElementById('passwordErrorMessage');
        
        // Clear validation state on input
        if (currentPasswordField) {
            currentPasswordField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (passwordErrorMsg) passwordErrorMsg.classList.add('d-none');
            });
        }
        if (newPasswordField) {
            newPasswordField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (passwordErrorMsg) passwordErrorMsg.classList.add('d-none');
            });
        }
        if (confirmPasswordField) {
            confirmPasswordField.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (passwordErrorMsg) passwordErrorMsg.classList.add('d-none');
            });
        }
        
        changePasswordForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handlePasswordChange();
        });
    }
};

function loadProfile() {
    fetch(adminUrl('api/profile.php'))
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success && data.admin) {
            var admin = data.admin;
            
            document.getElementById('displayName').textContent = admin.name;
            document.getElementById('displayEmail').textContent = admin.email;
            document.getElementById('displayPhone').textContent = admin.phone || '—';
            
            if (admin.created_at) {
                document.getElementById('memberSince').textContent = formatDate(admin.created_at);
            }
            
            document.getElementById('adminName').value = admin.name;
            document.getElementById('adminEmail').value = admin.email;
            document.getElementById('adminPhone').value = admin.phone || '';
        }
    });
}

function handleProfileUpdate() {
    var nameField = document.getElementById('adminName');
    var emailField = document.getElementById('adminEmail');
    var phoneField = document.getElementById('adminPhone');
    var profileErrorMsg = document.getElementById('profileErrorMessage');
    
    // Clear previous errors
    if (profileErrorMsg) {
        profileErrorMsg.classList.add('d-none');
    }
    if (nameField) nameField.classList.remove('is-invalid');
    if (emailField) emailField.classList.remove('is-invalid');
    if (phoneField) phoneField.classList.remove('is-invalid');
    
    var name = nameField ? nameField.value.trim() : '';
    var email = emailField ? emailField.value.trim() : '';
    var phone = phoneField ? phoneField.value.trim() : '';
    
    var isValid = true;
    var errorMessage = '';
    
    // Validate name
    if (name === '') {
        if (nameField) nameField.classList.add('is-invalid');
        isValid = false;
        errorMessage = 'Name is required';
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
    
    // If validation fails, show error message
    if (!isValid) {
        if (profileErrorMsg) {
            profileErrorMsg.textContent = errorMessage;
            profileErrorMsg.classList.remove('d-none');
        }
        var form = document.getElementById('updateProfileForm');
        var firstError = form ? form.querySelector('.is-invalid') : null;
        if (firstError) {
            firstError.focus();
        }
        return;
    }
    
    // Submit via AJAX
    var data = {
        name: name,
        email: email,
        phone: phone
    };
    
    fetch(adminUrl('api/profile.php'), {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            showNotification('Profile updated successfully', 'success');
            loadProfile();
        } else {
            if (profileErrorMsg) {
                profileErrorMsg.textContent = data.message || 'Failed to update profile';
                profileErrorMsg.classList.remove('d-none');
            } else {
                showNotification(data.message || 'Failed to update profile', 'error');
            }
        }
    })
    .catch(function(error) {
        console.error('Profile update error:', error);
        if (profileErrorMsg) {
            profileErrorMsg.textContent = 'Failed to update profile. Please try again.';
            profileErrorMsg.classList.remove('d-none');
        } else {
            showNotification('Failed to update profile. Please try again.', 'error');
        }
    });
}

function handlePasswordChange() {
    var currentPasswordField = document.getElementById('currentPassword');
    var newPasswordField = document.getElementById('newPassword');
    var confirmPasswordField = document.getElementById('confirmPassword');
    var passwordErrorMsg = document.getElementById('passwordErrorMessage');
    
    // Clear previous errors
    if (passwordErrorMsg) {
        passwordErrorMsg.classList.add('d-none');
    }
    if (currentPasswordField) currentPasswordField.classList.remove('is-invalid');
    if (newPasswordField) newPasswordField.classList.remove('is-invalid');
    if (confirmPasswordField) confirmPasswordField.classList.remove('is-invalid');
    
    var currentPassword = currentPasswordField ? currentPasswordField.value : '';
    var newPassword = newPasswordField ? newPasswordField.value : '';
    var confirmPassword = confirmPasswordField ? confirmPasswordField.value : '';
    
    var isValid = true;
    var errorMessage = '';
    
    // Validate current password
    if (currentPassword === '') {
        if (currentPasswordField) currentPasswordField.classList.add('is-invalid');
        isValid = false;
        errorMessage = 'Current password is required';
    }
    
    // Validate new password
    if (newPassword === '') {
        if (newPasswordField) newPasswordField.classList.add('is-invalid');
        isValid = false;
        if (!errorMessage) {
            errorMessage = 'New password is required';
        }
    } else if (newPassword.length < 8) {
        if (newPasswordField) newPasswordField.classList.add('is-invalid');
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
            errorMessage = 'Please confirm your new password';
        }
    } else if (newPassword !== confirmPassword) {
        if (confirmPasswordField) confirmPasswordField.classList.add('is-invalid');
        isValid = false;
        if (!errorMessage) {
            errorMessage = 'New passwords do not match';
        }
    }
    
    // If validation fails, show error message
    if (!isValid) {
        if (passwordErrorMsg) {
            passwordErrorMsg.textContent = errorMessage;
            passwordErrorMsg.classList.remove('d-none');
        }
        var form = document.getElementById('changePasswordForm');
        var firstError = form ? form.querySelector('.is-invalid') : null;
        if (firstError) {
            firstError.focus();
        }
        return;
    }
    
    // Submit via AJAX
    var data = {
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
    };
    
    fetch(adminUrl('api/profile.php'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            showNotification('Password changed successfully', 'success');
            document.getElementById('changePasswordForm').reset();
        } else {
            if (passwordErrorMsg) {
                passwordErrorMsg.textContent = data.message || 'Failed to change password';
                passwordErrorMsg.classList.remove('d-none');
            } else {
                showNotification(data.message || 'Failed to change password', 'error');
            }
        }
    })
    .catch(function(error) {
        console.error('Password change error:', error);
        if (passwordErrorMsg) {
            passwordErrorMsg.textContent = 'Failed to change password. Please try again.';
            passwordErrorMsg.classList.remove('d-none');
        } else {
            showNotification('Failed to change password. Please try again.', 'error');
        }
    });
}
