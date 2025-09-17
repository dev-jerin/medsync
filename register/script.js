document.addEventListener("DOMContentLoaded", function() {
    // --- Page-Specific Scripts ---
    if (document.getElementById('registerForm')) {
        handleRegistrationForm();
    }
    if (document.querySelector('.otp-input-container')) {
        handleOtpInputs();
    }
});

/**
 * Encapsulates all scripts for the registration form.
 */
function handleRegistrationForm() {
    // --- Element Selections ---
    const picInput = document.getElementById('profile_picture_input');
    const picPreview = document.getElementById('profile_picture_preview');
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const confirmPasswordInput = document.getElementById('confirm_password');

    // --- Message Div Selections ---
    const usernameMessage = document.getElementById('username-availability');
    const emailMessage = document.getElementById('email-availability');
    const phoneMessage = document.getElementById('phone-message');
    const passwordMessage = document.getElementById('password-message');
    // Select the message div for the confirm password field
    const confirmPasswordMessage = confirmPasswordInput.parentElement.querySelector('.availability-message');

    // --- Profile Picture Preview ---
    picInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) { picPreview.src = e.target.result; };
            reader.readAsDataURL(file);
        }
    });

    // --- Password Visibility Toggle ---
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // --- Password Strength Meter ---
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;

        let text = '';
        let color = '#dc3545';
        if (password.length > 0) {
            text = 'Weak';
            switch (strength) {
                case 3: text = 'Medium'; color = '#ffc107'; break;
                case 4: text = 'Strong'; color = '#28a745'; break;
                case 5: text = 'Very Strong'; color = '#0067FF'; break;
            }
        }
        strengthBar.style.width = (strength * 20) + '%';
        strengthBar.style.backgroundColor = color;
        strengthText.textContent = text;
        strengthText.style.color = color;
    });

    // --- Main Validation Function (Client-Side) ---
    const validateField = (input, messageDiv) => {
        let isValid = true;
        let message = '';
        const value = input.value.trim();

        switch (input.id) {
            case 'username':
                if (value.length < 3) {
                    message = 'Username must be at least 3 characters.';
                    isValid = false;
                } else if (/[^a-zA-Z0-9_.]/.test(value)) {
                    message = 'Only letters, numbers, underscores, and dots are allowed.';
                    isValid = false;
                } else if (/^(u|s|a|d)\d{4}$/i.test(value)) {
                    message = 'This username format is reserved.';
                    isValid = false;
                }
                break;
            case 'email':
                if (value.length > 0 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    message = 'Please enter a valid email address.';
                    isValid = false;
                }
                break;
            case 'phone':
                if (value.length > 0 && !/^\+91\d{10}$/.test(value)) {
                    message = 'Format must be +91 followed by 10 digits.';
                    isValid = false;
                }
                break;
            case 'password':
                if (value.length > 0 && value.length < 6) {
                    message = 'Password must be at least 6 characters long.';
                    isValid = false;
                }
                break;
            case 'confirm_password':
                if (value.length > 0 && value !== passwordInput.value) {
                    message = 'Passwords do not match.';
                    isValid = false;
                }
                break;
        }
        
        if (messageDiv) {
             if (message) {
                messageDiv.textContent = message;
                messageDiv.className = 'availability-message error';
            } else {
                messageDiv.textContent = '';
                messageDiv.className = 'availability-message';
            }
        }
        return isValid;
    };

    // --- Server-Side Availability Check Function ---
    const checkAvailability = (input, messageDiv, type) => {
        // Don't check the server if the client-side format is invalid
        if (!validateField(input, messageDiv)) {
            return;
        }

        // Don't check availability for empty fields
        if(input.value.trim() === '') {
            return;
        }

        fetch('check_availability.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `${type}=` + encodeURIComponent(input.value)
        })
        .then(response => response.json())
        .then(data => {
            messageDiv.textContent = data.message;
            messageDiv.className = data.available ? 'availability-message success' : 'availability-message error';
        })
        .catch(error => console.error('Error:', error));
    };

    // --- Event Listeners ---
    // Check format as user types
    usernameInput.addEventListener('keyup', () => validateField(usernameInput, usernameMessage));
    emailInput.addEventListener('keyup', () => validateField(emailInput, emailMessage));
    phoneInput.addEventListener('keyup', () => validateField(phoneInput, phoneMessage));
    passwordInput.addEventListener('keyup', () => {
        validateField(passwordInput, passwordMessage);
        validateField(confirmPasswordInput, confirmPasswordMessage); // Re-validate confirm password
    });
    confirmPasswordInput.addEventListener('keyup', () => validateField(confirmPasswordInput, confirmPasswordMessage));

    // Check availability when user leaves the field (blur event)
    usernameInput.addEventListener('blur', () => checkAvailability(usernameInput, usernameMessage, 'username'));
    emailInput.addEventListener('blur', () => checkAvailability(emailInput, emailMessage, 'email'));
}

/**
 * Handles the logic for smart OTP input fields.
 */
function handleOtpInputs() {
    const inputs = document.querySelectorAll('.otp-input');
    const hiddenOtpInput = document.getElementById('otp');
    
    inputs.forEach((input, index) => {
        input.addEventListener('keyup', (e) => {
            // If the key is a number, move to the next input
            if (e.key >= 0 && e.key <= 9 && input.nextElementSibling) {
                input.nextElementSibling.focus();
            } else if (e.key === "Backspace" && input.previousElementSibling) {
                // On backspace, clear current and move to the previous input
                input.previousElementSibling.focus();
            }
            updateHiddenOtp();
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text').trim();
            // Check if the pasted text is exactly 6 digits
            if (/^\d{6}$/.test(paste)) {
                for (let i = 0; i < paste.length; i++) {
                    if (inputs[i]) {
                        inputs[i].value = paste[i];
                    }
                }
                inputs[5]?.focus(); // Focus the last input
                updateHiddenOtp();
            }
        });
    });

    function updateHiddenOtp() {
        let otp = "";
        inputs.forEach(input => {
            otp += input.value;
        });
        hiddenOtpInput.value = otp;
    }
}