<?php
//database connection
require_once '../config.php';

// If a user is already logged in, redirect them to their dashboard
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
    <title>Login - MedSync</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
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
            <div class="nav-actions">
                <a href="../register" class="btn btn-primary">Register</a>
            </div>
        </nav>
    </header>

    <main class="auth-page">
        <div class="auth-container">
            <!-- Left Panel: Branding & Illustration (visible only to desktop or supported device) -->
            <div class="auth-panel">
                <img src="../images/doctor.png" alt="Medical professionals collaborating" class="auth-image">
                <div class="auth-panel-overlay">
                    <h2>Welcome Back to MedSync</h2>
                    <p>Log in to access your dashboard and manage your healthcare seamlessly.</p>
                </div>
            </div>

            <!-- Right Panel: Login Form -->
            <div class="auth-form-wrapper">
                <div class="auth-form-container">
                    <div class="auth-header">
                        <h1>Member Login</h1>
                        <p>Enter your credentials to access your account.</p>
                    </div>

                    <?php
                    // Display messages from other pages - register
                    if (isset($_SESSION['register_success'])) {
                        echo '<div class="message-box success-message">' . htmlspecialchars($_SESSION['register_success']) . '</div>';
                        unset($_SESSION['register_success']);
                    }
                    if (isset($_SESSION['login_message'])) {
                        $message = $_SESSION['login_message'];
                        $message_type = $message['type'] === 'success' ? 'success-message' : 'error-message';
                        echo '<div class="message-box ' . $message_type . '">' . $message['text'] . '</div>';
                        unset($_SESSION['login_message']);
                    }
                    // Display login-specific errors
                    if (isset($_SESSION['login_error'])) {
                        echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['login_error']) . '</div>';
                        unset($_SESSION['login_error']);
                    }
                    ?>

                    <!-- FORM SUBMITTED TO login_process.php -->
                    <form action="login_process.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                        
                        <div class="form-group">
                            <input type="text" id="username" name="username" class="form-control" placeholder=" " required>
                            <label for="username" class="form-label">Username, Email, or User ID</label>
                        </div>

                        <div class="form-group password-group">
                            <input type="password" id="password" name="password" class="form-control" placeholder=" " required>
                            <label for="password" class="form-label">Password</label>
                            <i class="fas fa-eye-slash password-toggle-icon" id="togglePassword"></i>
                        </div>

                        <div class="forgot-password-link">
                            <a href="../forgot_password">Forgot Password?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full-width">Login</button>
                    </form>

                    <div class="extra-links">
                        <p>Don't have an account? <a href="../register">Register Now</a></p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="script.js"></script>
</body>
</html>
