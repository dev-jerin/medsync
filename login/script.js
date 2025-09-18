/**
 * MedSync - Login Page Scripts
 *
 * This script is to let you see the password you're typing when you click the eye icon, and then hide it again when you click the icon a second time.
 * 
 */
document.addEventListener("DOMContentLoaded", function() {
    const togglePassword = document.getElementById('togglePassword'); //finds the eye icon
    const passwordInput = document.getElementById('password'); //finds password field

    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute -- if the eye icon is clicked
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
});
