<?php
// config.php initializes the session and CSRF token
require_once '../config.php';
require_once '../_private/firebase_config.php';

// If a user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: ../{$role}/dashboard");
    exit();
}

// Retrieve persisted form data if it exists after a validation failure
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$name_value = isset($form_data['name']) ? htmlspecialchars($form_data['name']) : '';
$username_value = isset($form_data['username']) ? htmlspecialchars($form_data['username']) : '';
$email_value = isset($form_data['email']) ? htmlspecialchars($form_data['email']) : '';
$phone_value = isset($form_data['phone']) ? htmlspecialchars($form_data['phone']) : '';
$dob_value = isset($form_data['date_of_birth']) ? htmlspecialchars($form_data['date_of_birth']) : '';
$gender_value = isset($form_data['gender']) ? htmlspecialchars($form_data['gender']) : '';
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
    <link rel="stylesheet" href="../main/styles.css"> 
    <link rel="stylesheet" href="styles.css">
    <style>
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-muted);
            margin: 1.5rem 0;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }
        .divider:not(:empty)::before {
            margin-right: .5em;
        }
        .divider:not(:empty)::after {
            margin-left: .5em;
        }
        .btn-google {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            color: #3c4043;
            background-color: #fff;
            border: 1px solid #dadce0;
            border-radius: var(--border-radius-md);
            cursor: pointer;
            transition: background-color 0.3s, box-shadow 0.3s;
        }
        .btn-google:hover {
            background-color: #f8f9fa;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
    </style>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
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
                <img src="../images/register-img.jpg" alt="A friendly male doctor smiling in a modern clinic" class="auth-image">
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
                    
                    <button type="button" id="google-signin-btn" class="btn-google">
                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" style="width: 20px; height: 20px; margin-right: 12px;"><g><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.42-4.55H24v9.02h12.94c-.58 2.92-2.26 5.47-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path><path fill="none" d="M0 0h48v48H0z"></path></g></svg>
                        Sign in with Google
                    </button>

                    <div class="divider">OR</div>

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
                            <input type="text" id="name" name="name" class="form-control" placeholder=" " required value="<?php echo $name_value; ?>">
                            <label for="name" class="form-label">Full Name</label>
                        </div>
                        
                        <div class="form-group">
                            <input type="text" id="username" name="username" class="form-control" placeholder=" " required value="<?php echo $username_value; ?>">
                            <label for="username" class="form-label">Username</label>
                            <div id="username-availability" class="availability-message"></div>
                        </div>

                        <div class="form-group">
                            <input type="email" id="email" name="email" class="form-control" placeholder=" " required value="<?php echo $email_value; ?>">
                            <label for="email" class="form-label">Email Address</label>
                            <div id="email-availability" class="availability-message"></div>
                        </div>

                        <div class="form-group">
                            <input type="tel" id="phone" name="phone" class="form-control" placeholder=" " pattern="^\+91[0-9]{10}$" title="Format: +911234567890" required value="<?php echo $phone_value; ?>">
                            <label for="phone" class="form-label">Phone Number (e.g., +91...)</label>
                            <div id="phone-message" class="availability-message"></div>
                        </div>

                        <div class="form-group-row">
                            <div class="form-group">
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" placeholder=" " max="<?php echo date('Y-m-d'); ?>" required value="<?php echo $dob_value; ?>">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                            </div>
                            <div class="form-group">
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="" disabled selected></option>
                                    <option value="Male" <?php if ($gender_value === 'Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if ($gender_value === 'Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if ($gender_value === 'Other') echo 'selected'; ?>>Other</option>
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
                        
                        <div class="form-group">
                            <center><div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_SITE_KEY; ?>"></div></center>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full-width">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-app.js"></script>
    <script src="https://www.gstatic.com/firebasejs/8.10.0/firebase-auth.js"></script>

    <script>
      // Your web app's Firebase configuration
        const firebaseConfig = <?php echo json_encode($firebaseConfig); ?>;

      // Initialize Firebase
      firebase.initializeApp(firebaseConfig);
    </script>
    
    <script src="../auth/google-auth.js"></script>
    <script src="script.js"></script>
</body>
</html>