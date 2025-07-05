<?php
/**
 * Displays the form to verify OTP and reset the password.
 * Processes the submission to validate OTP and update the password.
 */

require_once './config.php';

// --- Security Check: Ensure user has started the reset process ---
if (!isset($_SESSION['password_reset'])) {
    // Redirect if the session data isn't set, forcing them to start over.
    header("Location: forgot_password.php");
    exit();
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid session. Please try again.'];
    } else {
        $submitted_otp = trim($_POST['otp']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $session_data = $_SESSION['password_reset'];

        // 1. Check if OTP has expired (10 minutes / 600 seconds)
        if (time() - $session_data['timestamp'] > 600) {
            $_SESSION['status'] = ['type' => 'error', 'text' => 'OTP has expired. Please request a new one.'];
            unset($_SESSION['password_reset']); // Clear expired data
            header("Location: forgot_password.php");
            exit();
        }
        // 2. Check if the submitted OTP is correct
        elseif ($submitted_otp != $session_data['otp']) {
            $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid OTP. Please check and try again.'];
        }
        // 3. Validate passwords
        elseif (empty($password) || empty($confirm_password)) {
            $_SESSION['status'] = ['type' => 'error', 'text' => 'Both password fields are required.'];
        } elseif ($password !== $confirm_password) {
            $_SESSION['status'] = ['type' => 'error', 'text' => 'Passwords do not match.'];
        } else {
            // --- All checks passed, update the password ---
            $conn = getDbConnection();
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $email = $session_data['email'];
            
            $sql_update = "UPDATE users SET password = ? WHERE email = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("ss", $hashed_password, $email);
            
            if ($stmt_update->execute()) {
                // --- Success ---
                unset($_SESSION['password_reset']); // Clean up the session
                $_SESSION['login_message'] = ['type' => 'success', 'text' => 'Your password has been reset successfully. You can now log in.'];
                header("Location: login.php");
                exit();
            } else {
                $_SESSION['status'] = ['type' => 'error', 'text' => 'Failed to update password. Please try again.'];
            }
            $stmt_update->close();
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MedSync</title>
    <!-- Styles are identical to forgot_password.php for consistency -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    <style>
        :root { --primary-color: #007BFF; --secondary-color: #17a2b8; --text-dark: #343a40; --background-grey: #f1f5f9; --background-light: #ffffff; --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1); --border-radius: 12px; --error-color: #dc3545; --success-color: #28a745; }
        body { font-family: 'Poppins', sans-serif; background-color: var(--background-grey); color: var(--text-dark); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 2rem; }
        .container { width: 100%; max-width: 450px; }
        .form-card { background: var(--background-light); padding: 2.5rem 3rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); width: 100%; }
        .form-header { text-align: center; margin-bottom: 2rem; }
        .form-header .logo { font-size: 2rem; font-weight: 700; color: var(--primary-color); text-decoration: none; margin-bottom: 0.5rem; display: inline-flex; align-items: center; }
        .form-header .logo-img { height: 40px; margin-right: 8px; }
        .form-header h1 { font-size: 1.8rem; font-weight: 600; }
        .form-header p { color: #6c757d; margin-top: 0.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ced4da; border-radius: 8px; font-size: 1rem; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2); }
        .btn-primary { padding: 0.8rem 1.5rem; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; border: none; cursor: pointer; width: 100%; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); color: #fff; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2); }
        .message-box { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; text-align: center; border: 1px solid transparent; font-size: 0.95rem; }
        .error-message { background-color: rgba(220, 53, 69, 0.1); color: var(--error-color); border-color: rgba(220, 53, 69, 0.2); }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <a href="index.php" class="logo">
                    <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                    MedSync
                </a>
                <h1>Set New Password</h1>
                <p>An OTP has been sent to your email. Please enter it below to set a new password.</p>
            </div>

            <?php
            if (isset($_SESSION['status'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['status']['text']) . '</div>';
                unset($_SESSION['status']);
            }
            ?>

            <form method="POST" action="verify_and_reset_password.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <label for="otp">6-Digit OTP</label>
                    <input type="text" id="otp" name="otp" maxlength="6" pattern="\d{6}" inputmode="numeric" required>
                </div>

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn-primary">Verify & Reset Password</button>
            </form>
        </div>
    </div>
</body>
</html>
