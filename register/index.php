<?php
// config.php initializes the session and CSRF token
require_once '../config.php';

// If a user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    // A robust solution would be to redirect based on the role stored in the session
    $role = $_SESSION['role'];
    header("Location: ../{$role}/dashboard");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Your Account - MedSync</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="../main/styles.css"> <link rel="stylesheet" href="styles.css"> </head>
<body>

    <header class="header" id="header">
        <nav class="container navbar">
            <a href="../index.php" class="logo">
                <img src="../images/logo.png" alt="MedSync Logo" class="logo-img">
                <span>MedSync</span>
            </a>
            <div class="nav-actions">
                <a href="../login" class="btn btn-secondary">Login</a>
            </div>
        </nav>
    </header>

    <main class="auth-page">
        <div class="auth-container">
            <div class="auth-panel">
                <img src="https://images.unsplash.com/photo-1551601651-2a8555f1a136?q=80&w=2147&auto=format&fit=crop" alt="A friendly male doctor smiling in a modern clinic" class="auth-image">
                <div class="auth-panel-overlay">
                    <h2>Join a Healthier Future</h2>
                    <p>Register with MedSync to manage your health with ease and efficiency.</p>
                </div>
            </div>

            <div class="auth-form-wrapper">
                <div class="auth-form-container">
                    <div class="auth-header">
                        <h1>Create Your Account</h1>
                        <p>Let's get you started. Already have an account? <a href="../login">Log in</a>.</p>
                    </div>

                    <?php
                    if (isset($_SESSION['register_error'])) {
                        echo '<div class="message-box error-message">' . $_SESSION['register_error'] . '</div>';
                        unset($_SESSION['register_error']);
                    }
                    ?>

                    <form id="registerForm" action="register_process.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <div class="form-group profile-picture-uploader">
                            <label for="profile_picture_input">Profile Picture</label>
                            <div class="profile-picture-container">
                                <img src="../uploads/profile_pictures/default.png" alt="Profile Preview" id="profile_picture_preview" class="profile-picture-preview">
                                <input type="file" id="profile_picture_input" name="profile_picture" accept="image/jpeg, image/png, image/gif" style="display: none;">
                                <button type="button" class="btn-change-pic" onclick="document.getElementById('profile_picture_input').click();"></button>
                            </div>
                        </div>

                        <div class="form-group">
                            <input type="text" id="name" name="name" class="form-control" placeholder=" " required>
                            <label for="name" class="form-label">Full Name</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" id="username" name="username" class="form-control" placeholder=" " required>
                            <label for="username" class="form-label">Username</label>
                            <div id="username-availability" class="availability-message"></div>
                        </div>

                        <div class="form-group">
                            <input type="email" id="email" name="email" class="form-control" placeholder=" " required>
                            <label for="email" class="form-label">Email Address</label>
                            <div id="email-availability" class="availability-message"></div>
                        </div>

                        <div class="form-group">
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder=" " pattern="\+91[0-9]{10}" title="Format: +911234567890" required>
                            <label for="phone" class="form-label">Phone Number (e.g., +91...)</label>
                            <div id="phone-message" class="availability-message"></div>
                        </div>

                        <div class="form-group-row">
                            <div class="form-group">
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" placeholder=" " max="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                            </div>
                            <div class="form-group">
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="" disabled selected></option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                                <label for="gender" class="form-label">Gender</label>
                            </div>
                        </div>

                        <div class="form-group password-group">
                            <input type="password" id="password" name="password" class="form-control" placeholder=" " required>
                            <label for="password" class="form-label">Password</label>
                            <i class="fa fa-eye-slash password-toggle-icon" id="togglePassword"></i>
                            <div id="password-message" class="availability-message"></div>
                        </div>
                        
                        <div class="password-strength-container">
                            <div id="password-strength-meter">
                                <div id="password-strength-bar"></div>
                            </div>
                            <div id="password-strength-text"></div>
                        </div>

                        <div class="form-group password-group">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder=" " required>
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <div class="availability-message"></div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-full-width">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <script src="script.js"></script>
</body>
</html>