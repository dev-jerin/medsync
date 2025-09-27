<?php
require_once '../config.php';

// If user is not coming from the Google Auth flow, redirect them.
if (!isset($_SESSION['google_new_user'])) {
    header("Location: ../register/index.php");
    exit();
}

$google_user = $_SESSION['google_new_user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - MedSync</title>
    <!-- We can reuse the styles from the register page for consistency -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../main/styles.css">
    <link rel="stylesheet" href="../register/styles.css">
</head>
<body>
    <header class="header">
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
                <h1>Just One More Step...</h1>
                <p>Welcome, <?php echo htmlspecialchars($google_user['name']); ?>! Please provide the following details to complete your registration.</p>
            </div>

            <?php
            if (isset($_SESSION['profile_error'])) {
                echo '<div class="message-box error-message">' . htmlspecialchars($_SESSION['profile_error']) . '</div>';
                unset($_SESSION['profile_error']);
            }
            ?>

            <form action="save_profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($google_user['email']); ?>" disabled>
                    <label class="form-label" style="top: -0.6rem; left: 0.8rem; font-size: 0.8rem; color: var(--primary-color);">Email Address</label>
                </div>

                <div class="form-group">
                    <input type="tel" id="phone" name="phone" class="form-control" placeholder=" " pattern="^\+91[0-9]{10}$" title="Format: +911234567890" required>
                    <label for="phone" class="form-label">Phone Number (e.g., +91...)</label>
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
                
                <button type="submit" class="btn btn-primary btn-full-width">Complete Registration</button>
            </form>
        </div>
    </main>
</body>
</html>
