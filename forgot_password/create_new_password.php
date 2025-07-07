<?php
/**
 * Displays the form to create a new password after successful OTP verification.
 */

require_once '../config.php';

// --- Security Check: Ensure user has verified their OTP ---
if (!isset($_SESSION['reset_otp_verified']) || $_SESSION['reset_otp_verified'] !== true) {
    // If the flag is not set, redirect them to the beginning of the flow.
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Please verify your OTP first.'];
    header("Location: ../forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Password - MedSync</title>
    <!-- Styles and Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    <style>
        :root { --primary-color: #007BFF; --secondary-color: #17a2b8; --text-dark: #343a40; --background-grey: #f1f5f9; --background-light: #ffffff; --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1); --border-radius: 12px; --error-color: #dc3545; --success-color: #28a745; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-grey); color: var(--text-dark); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 2rem; }
        .container { width: 100%; max-width: 450px; }
        .form-card { background: var(--background-light); padding: 2.5rem 3rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); }
        .form-header { text-align: center; margin-bottom: 2rem; }
        .form-header h1 { font-size: 1.8rem; font-weight: 600; }
        .form-header p { color: #6c757d; margin-top: 0.5rem; }
        .form-group { margin-bottom: 1.5rem; position: relative; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ced4da; border-radius: 8px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2); }
        .btn-primary { padding: 0.8rem 1.5rem; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; border: none; cursor: pointer; width: 100%; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); color: #fff; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2); }
        .message-box { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; text-align: center; border: 1px solid transparent; font-size: 0.95rem; }
        .error-message { background-color: rgba(220, 53, 69, 0.1); color: var(--error-color); border-color: rgba(220, 53, 69, 0.2); }
        /* Password Strength Meter */
        #password-strength-meter { height: 5px; width: 100%; background: #e0e0e0; border-radius: 5px; margin-top: 5px; }
        #password-strength-bar { height: 100%; width: 0; background: var(--error-color); border-radius: 5px; transition: width 0.3s, background-color 0.3s; }
        #password-strength-text { font-size: 0.85rem; margin-top: 5px; text-align: right; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>Create New Password</h1>
                <p>Please enter your new password below. Make sure it's secure.</p>
            </div>

            <?php
            if (isset($_SESSION['status'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['status']['text']) . '</div>';
                unset($_SESSION['status']);
            }
            ?>

            <form method="POST" action="../forgot_password/update_password_process.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                    <div id="password-strength-meter">
                        <div id="password-strength-bar"></div>
                    </div>
                    <div id="password-strength-text"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn-primary">Reset Password</button>
            </form>
        </div>
    </div>
    <script>
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let text = 'Weak';
            let color = 'var(--error-color)';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch(strength) {
                case 0:
                case 1:
                case 2:
                    text = 'Weak';
                    color = '#dc3545'; // red
                    break;
                case 3:
                    text = 'Medium';
                    color = '#ffc107'; // yellow
                    break;
                case 4:
                    text = 'Strong';
                    color = '#28a745'; // green
                    break;
                case 5:
                    text = 'Very Strong';
                    color = '#007BFF'; // blue
                    break;
            }

            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
        });
    </script>
</body>
</html>
