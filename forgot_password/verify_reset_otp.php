<?php
// Include the configuration file to initialize session and CSRF token
require_once '../config.php';

// If the user hasn't started the password reset process, redirect them.
if (!isset($_SESSION['password_reset'])) {
    header("Location: ../forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - MedSync</title>
    <!-- Styles and Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    <style>
        :root {
            --primary-color: #007BFF;
            --secondary-color: #17a2b8;
            --text-dark: #343a40;
            --background-grey: #f1f5f9;
            --background-light: #ffffff;
            --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --error-color: #dc3545;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-grey);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 2rem;
        }
        .container { width: 100%; max-width: 450px; }
        .form-card { background: var(--background-light); padding: 2.5rem 3rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); text-align: center; }
        .form-header h1 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .form-header p { margin-bottom: 2rem; color: #6c757d; }
        .form-group { margin-bottom: 1.5rem; text-align: left; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; padding: 0.8rem 1rem; border: 1px solid #ced4da; border-radius: 8px; font-size: 1.5rem; text-align: center; letter-spacing: 5px; }
        .form-group input:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2); }
        .btn-primary { padding: 0.8rem 1.5rem; border-radius: var(--border-radius); text-decoration: none; font-weight: 600; border: none; cursor: pointer; width: 100%; background: linear-gradient(90deg, var(--primary-color), var(--secondary-color)); color: #fff; box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2); }
        .message-box { padding: 1rem; border-radius: var(--border-radius); margin-bottom: 1.5rem; border: 1px solid transparent; }
        .error-message { background-color: rgba(220, 53, 69, 0.1); color: var(--error-color); border-color: rgba(220, 53, 69, 0.2); }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="form-header">
                <h1>Verify Your Identity</h1>
                <p>An OTP has been sent to <strong><?php echo htmlspecialchars($_SESSION['password_reset']['email']); ?></strong>. Please enter it below.</p>
            </div>

            <?php
            // Display any verification error messages
            if (isset($_SESSION['status'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['status']['text']) . '</div>';
                unset($_SESSION['status']);
            }
            ?>

            <form action="../forgot_password/verify_reset_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="otp">Enter 6-Digit OTP</label>
                    <input type="text" id="otp" name="otp" maxlength="6" pattern="\d{6}" inputmode="numeric" required>
                </div>
                <button type="submit" class="btn-primary">Verify OTP</button>
            </form>
        </div>
    </div>
</body>
</html>
