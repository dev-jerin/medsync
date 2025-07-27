<?php
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
    
    <!-- Fonts, Favicon, and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="../main/styles.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="auth-page-centered">
        <div class="auth-form-container">
            <div class="auth-header">
                <h1>Set New Password</h1>
                <p>Your identity has been verified. Please create a new, secure password for your account.</p>
            </div>

            <?php
            // Display any error messages
            if (isset($_SESSION['status'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['status']['text']) . '</div>';
                unset($_SESSION['status']);
            }
            ?>

            <form id="newPasswordForm" method="POST" action="update_password_process.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group password-group">
                    <input type="password" id="password" name="password" class="form-control" placeholder=" " required>
                    <label for="password" class="form-label">New Password</label>
                    <i class="fas fa-eye-slash password-toggle-icon" id="togglePassword"></i>
                </div>

                <!-- Password Strength Meter -->
                <div class="password-strength-container">
                    <div id="password-strength-meter"><div id="password-strength-bar"></div></div>
                    <span id="password-strength-text"></span>
                </div>

                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder=" " required>
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                </div>

                <button type="submit" class="btn btn-primary btn-full-width">Update Password</button>
            </form>
        </div>
    </main>
    
    <script src="script.js"></script>
</body>
</html>
