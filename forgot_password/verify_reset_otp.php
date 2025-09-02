<?php
require_once '../config.php';

// If the user hasn't started the password reset process, redirect them.
if (!isset($_SESSION['password_reset'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - MedSync</title>
    
    <!-- Fonts, Favicon, and Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../main/styles.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="auth-page-centered">
        <div class="auth-form-container">
            <div class="auth-header">
                <h1>Check Your Email</h1>
                <p>We've sent a 6-digit code to <strong><?php echo htmlspecialchars($_SESSION['password_reset']['email']); ?></strong>. Please enter it below to verify your identity.</p>
            </div>

            <?php
            // Display any verification error messages
            if (isset($_SESSION['status'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['status']['text']) . '</div>';
                unset($_SESSION['status']);
            }
            ?>

            <form action="verify_reset_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <!-- This hidden input will be populated by script.js -->
                <input type="hidden" id="otp_full" name="otp">
                
                <div class="form-group">
                    <!-- FIXED: Changed class from "form-label" to "otp-group-label" to prevent floating -->
                    <label for="otp-1" class="otp-group-label">Enter 6-Digit OTP</label>
                    <div class="otp-input-container">
                        <input type="text" id="otp-1" class="otp-input" maxlength="1" inputmode="numeric" required>
                        <input type="text" id="otp-2" class="otp-input" maxlength="1" inputmode="numeric" required>
                        <input type="text" id="otp-3" class="otp-input" maxlength="1" inputmode="numeric" required>
                        <input type="text" id="otp-4" class="otp-input" maxlength="1" inputmode="numeric" required>
                        <input type="text" id="otp-5" class="otp-input" maxlength="1" inputmode="numeric" required>
                        <input type="text" id="otp-6" class="otp-input" maxlength="1" inputmode="numeric" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full-width">Verify Code</button>
            </form>

            <div class="extra-links">
                <p>Didn't get a code? <a href="index.php">Request a new one</a></p>
            </div>
        </div>
    </main>
    
    <script src="script.js"></script>
</body>
</html>
