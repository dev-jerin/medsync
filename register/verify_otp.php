<?php
// config.php initializes the session and CSRF token
require_once '../config.php';

// If the user hasn't started the registration process, redirect them.
if (!isset($_SESSION['registration_data'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Your Account - MedSync</title>
    
    <!-- Fonts, Favicon, Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../main/styles.css"> <!-- Main styles for header/footer -->
    <link rel="stylesheet" href="styles.css"> <!-- Page-specific styles -->
</head>
<body>

    <!-- Header -->
    <header class="header" id="header">
        <nav class="container navbar">
            <a href="../index.php" class="logo">
                <img src="../images/logo.png" alt="MedSync Logo" class="logo-img">
                <span>MedSync</span>
            </a>
        </nav>
    </header>

    <main class="auth-page-centered">
        <div class="auth-form-container" style="max-width: 500px;">
            <div class="auth-header">
                <h1>Email Verification</h1>
                <p>An OTP has been sent to <strong><?php echo htmlspecialchars($_SESSION['registration_data']['email']); ?></strong>. Please enter it below.</p>
            </div>

            <?php
            if (isset($_SESSION['verify_error'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['verify_error']) . '</div>';
                unset($_SESSION['verify_error']);
            }
            ?>

            <form action="verify_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" id="otp" name="otp">
                
                <div class="form-group">
                    <label for="otp-1">Enter 6-Digit OTP</label>
                    <div class="otp-input-container">
                        <input type="text" id="otp-1" class="otp-input" maxlength="1" inputmode="numeric">
                        <input type="text" id="otp-2" class="otp-input" maxlength="1" inputmode="numeric">
                        <input type="text" id="otp-3" class="otp-input" maxlength="1" inputmode="numeric">
                        <input type="text" id="otp-4" class="otp-input" maxlength="1" inputmode="numeric">
                        <input type="text" id="otp-5" class="otp-input" maxlength="1" inputmode="numeric">
                        <input type="text" id="otp-6" class="otp-input" maxlength="1" inputmode="numeric">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full-width">Verify & Register</button>
            </form>
            <div class="resend-otp-link">
                <p>Didn't receive the code? <a href="#" id="resendOtpLink">Resend OTP</a></p>
                <div id="resend-message" class="availability-message"></div>
            </div>
        </div>
    </main>
    
    <script src="script.js"></script>
</body>
</html>