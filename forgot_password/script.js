/**
 * MedSync - Forgot Password Scripts
 *
 * This script handles client-side interactivity for the password reset flow.
 * - Manages smart OTP input fields for a better user experience.
 * - Provides a password strength meter and visibility toggle for the new password form.
 */
document.addEventListener("DOMContentLoaded", function() {
    // Attach event handlers based on the current page's content
    if (document.querySelector('.otp-input-container')) {
        handleOtpInputs();
    }
    if (document.getElementById('newPasswordForm')) {
        handleNewPasswordForm();
    }
});

/**
 * Handles the logic for smart OTP input fields.
 * - Auto-focuses the next input on entry.
 * - Handles backspace to move to the previous input.
 * - Allows pasting the full OTP code.
 */
function handleOtpInputs() {
    const inputs = document.querySelectorAll('.otp-input');
    const hiddenOtpInput = document.getElementById('otp_full'); // The hidden input to store the full OTP
    const form = inputs[0].closest('form');

    inputs.forEach((input, index) => {
        input.addEventListener('keyup', (e) => {
            // If the key is a number, move to the next input
            if (e.key >= 0 && e.key <= 9) {
                if (index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            } 
            // If the key is Backspace, move to the previous input
            else if (e.key === "Backspace") {
                if (index > 0) {
                    inputs[index - 1].focus();
                }
            }
            updateHiddenOtp();
        });

        // Handle pasting an OTP code
        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text').trim();
            // Check if the pasted data is a valid OTP
            if (/^\d{6}$/.test(paste)) {
                for (let i = 0; i < paste.length; i++) {
                    if (inputs[i]) {
                        inputs[i].value = paste[i];
                    }
                }
                inputs[5].focus(); // Focus the last input
                updateHiddenOtp();
            }
        });
    });

    // Function to combine individual inputs into the hidden field
    function updateHiddenOtp() {
        let otp = "";
        inputs.forEach(input => {
            otp += input.value;
        });
        hiddenOtpInput.value = otp;
    }
}


/**
 * Encapsulates all scripts for the 'Create New Password' form.
 * - Manages the password visibility toggle.
 * - Implements the password strength meter.
 */
function handleNewPasswordForm() {
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('togglePassword');
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');

    // Password Visibility Toggle
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            // Toggle the icon class
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Password Strength Meter
    if (passwordInput && strengthBar && strengthText) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            if (password.length >= 8) strength++;      // Length
            if (password.match(/[a-z]/)) strength++;   // Lowercase
            if (password.match(/[A-Z]/)) strength++;   // Uppercase
            if (password.match(/[0-9]/)) strength++;   // Numbers
            if (password.match(/[^a-zA-Z0-9]/)) strength++; // Symbols

            let text = '';
            let color = '#e2e8f0'; // Default to grey

            if (password.length > 0) {
                switch (strength) {
                    case 1:
                    case 2:
                        text = 'Weak';
                        color = 'var(--error-color)';
                        break;
                    case 3:
                        text = 'Medium';
                        color = 'var(--warning-color)';
                        break;
                    case 4:
                        text = 'Strong';
                        color = 'var(--success-color)';
                        break;
                    case 5:
                        text = 'Very Strong';
                        color = 'var(--primary-color)';
                        break;
                    default:
                        text = 'Weak';
                        color = 'var(--error-color)';
                }
            }

            // Update the UI
            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
        });
    }
}
