<?php
// config.php should initialize the session and generate the CSRF token.
require_once '../config.php';

// If a user is already logged in, redirect them to their dashboard.
if (isset($_SESSION['user_id'])) {
    header("Location: ../" . $_SESSION['role'] . "/dashboard");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MedSync</title>
    
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
    <link rel="stylesheet" href="../main/styles.css"> <!-- Main styles for button consistency -->
    <link rel="stylesheet" href="styles.css"> <!-- Page-specific styles -->
</head>
<body>
    <main class="auth-page-centered">
        <div class="auth-form-container">
            <div class="auth-header">
                <a href="../index.php" class="logo">
                    <img src="../images/logo.png" alt="MedSync Logo" class="logo-img">
                    <span>MedSync</span>
                </a>
                <h1>Forgot Your Password?</h1>
                <p>No problem. Enter your email address below and we'll send you an OTP to reset it.</p>
            </div>

            <?php
            // Display status messages from the session
            if (isset($_SESSION['status'])) {
                $status = $_SESSION['status'];
                $message_type = $status['type'] === 'success' ? 'success-message' : 'error-message';
                echo '<div class="message-box ' . $message_type . '">' . htmlspecialchars($status['text']) . '</div>';
                unset($_SESSION['status']);
            }
            ?>

            <form action="send_reset_otp" method="POST">
                <!-- CSRF Token for security -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <input type="email" id="email" name="email" class="form-control" placeholder=" " required>
                    <label for="email" class="form-label">Email Address</label>
                </div>

                <button type="submit" class="btn btn-primary btn-full-width">Send Reset OTP</button>
            </form>

            <div class="extra-links">
                <p>Remembered your password? <a href="../login">Back to Login</a></p>
            </div>
        </div>
    </main>
</body>
</html>
