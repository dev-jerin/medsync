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
    // Profile Picture Preview
    const picInput = document.getElementById('profile_picture_input');
    const picPreview = document.getElementById('profile_picture_preview');
    picInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                picPreview.src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Password Visibility Toggle
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    togglePassword.addEventListener('click', function() {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });

    // Password Strength Meter
    const strengthBar = document.getElementById('password-strength-bar');
    const strengthText = document.getElementById('password-strength-text');
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        if (password.length >= 8) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;

        let text = '';
        let color = '#dc3545'; // Default to weak
        if (password.length > 0) {
            text = 'Weak';
            switch(strength) {
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

    // Real-time Availability Checks
    const usernameInput = document.getElementById('username');
    const emailInput = document.getElementById('email');
    const usernameAvailability = document.getElementById('username-availability');
    const emailAvailability = document.getElementById('email-availability');

    const checkAvailability = (input, messageDiv, type) => {
        const value = input.value;
        if (value.length < 3 && type === 'username') {
            messageDiv.textContent = '';
            return;
        }
        if (value.length === 0) {
            messageDiv.textContent = '';
            return;
        }

        // CORRECTED: The path now correctly points to the validation script
        fetch('register/check_availability.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `${type}=` + encodeURIComponent(value)
        })
        .then(response => response.json())
        .then(data => {
            messageDiv.textContent = data.message;
            messageDiv.className = data.available ? 'availability-message success' : 'availability-message error';
        })
        .catch(error => console.error('Error:', error));
    };

    usernameInput.addEventListener('blur', () => checkAvailability(usernameInput, usernameAvailability, 'username'));
    emailInput.addEventListener('blur', () => checkAvailability(emailInput, emailAvailability, 'email'));
}

/**
 * Handles the logic for smart OTP input fields.
 */
function handleOtpInputs() {
    const inputs = document.querySelectorAll('.otp-input');
    const hiddenOtpInput = document.getElementById('otp');
    
    inputs.forEach((input, index) => {
        input.addEventListener('keyup', (e) => {
            if (e.key >= 0 && e.key <= 9) { // On number input, move to next
                if (input.nextElementSibling) {
                    input.nextElementSibling.focus();
                }
            } else if (e.key === "Backspace") { // On backspace, move to previous
                if (input.previousElementSibling) {
                    input.previousElementSibling.focus();
                }
            }
            updateHiddenOtp();
        });

        input.addEventListener('paste', (e) => {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text').trim();
            if (/^\d{6}$/.test(paste)) {
                for (let i = 0; i < paste.length; i++) {
                    if (inputs[i]) {
                        inputs[i].value = paste[i];
                    }
                }
                inputs[5].focus();
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