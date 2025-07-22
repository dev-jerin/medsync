<?php
/**
 * Processes the new password form, validates, updates the database,
 * and sends a confirmation email.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';
require_once '../config.php';

/**
 * Returns the HTML content for the password reset confirmation email.
 *
 * @param string $name The user's full name.
 * @param string $datetime The date and time of the password change.
 * @return string The complete HTML email body.
 */
function getPasswordResetConfirmationTemplate($name, $datetime) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert: Your Password Was Changed - MedSync</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: 'Poppins', Arial, sans-serif; }
        .container { width: 100%; padding: 20px; background-color: #f1f5f9; }
        .main-content { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin: 0 auto; width: 100%; max-width: 600px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #ffc107, #dc3545); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 700; }
        .content-body { padding: 30px 35px; color: #343a40; line-height: 1.7; text-align: left; }
        .content-body p { font-size: 16px; margin: 0 0 15px 0; }
        .alert-details { background-color: #f8f9fa; border-left: 4px solid #ffc107; margin: 25px 0; padding: 20px; border-radius: 8px; }
        .alert-details p { margin: 5px 0; font-size: 14px; color: #495057; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #6c757d; background-color: #f8f9fa; }
        .footer p { margin: 5px 0; }
        @media screen and (max-width: 600px) {
            .content-body { padding: 25px 20px; }
            .header h1 { font-size: 22px; }
            .content-body p { font-size: 15px; }
        }
    </style>
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #f1f5f9;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Security Alert</h1>
                    </div>
                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>This email is to confirm that your password for your MedSync account was successfully changed.</p>
                        <div class="alert-details">
                            <p><strong>Date & Time:</strong> {$datetime}</p>
                        </div>
                        <p>If you made this change, you can safely ignore this email. Your account is secure.</p>
                        <p><strong>If you did NOT make this change,</strong> please secure your account immediately by resetting your password again and contacting our support team.</p>
                    </div>
                    <div class="footer">
                        <p>&copy; 2025 Calysta Health Institute. All Rights Reserved.</p>
                        <p>Calysta Health Institute, Kerala, India</p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

// --- Security Checks ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../forgot_password/create_new_password.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid session. Please try again.'];
    header("Location: ../forgot_password/create_new_password.php");
    exit();
}
if (!isset($_SESSION['reset_otp_verified']) || $_SESSION['reset_otp_verified'] !== true) {
    header("Location: ../forgot_password.php");
    exit();
}

// --- Form Data Validation ---
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

if (empty($password) || empty($confirm_password)) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Both password fields are required.'];
    header("Location: ../forgot_password/create_new_password.php");
    exit();
}
if ($password !== $confirm_password) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Passwords do not match.'];
    header("Location: ../forgot_password/create_new_password.php");
    exit();
}

// --- Update Password in Database ---
$conn = getDbConnection();
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$email = $_SESSION['password_reset']['email'];

$sql_update = "UPDATE users SET password = ? WHERE email = ?";
$stmt_update = $conn->prepare($sql_update);
$stmt_update->bind_param("ss", $hashed_password, $email);

if ($stmt_update->execute()) {
    // --- Success: Fetch user details for email ---
    $sql_get_user = "SELECT name FROM users WHERE email = ? LIMIT 1";
    $stmt_get_user = $conn->prepare($sql_get_user);
    $stmt_get_user->bind_param("s", $email);
    $stmt_get_user->execute();
    $result = $stmt_get_user->get_result();
    $user = $result->fetch_assoc();
    $user_name = $user ? $user['name'] : 'Valued User';
    $stmt_get_user->close();

    // --- Send Confirmation Email ---
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'medsync.calysta@gmail.com';
$gmail_app_password = get_system_setting($conn, 'gmail_app_password');
if (empty($gmail_app_password)) {
    // For this file, we don't want to stop the user flow if the email fails.
    // Just log the error and proceed.
    error_log("Could not send password change confirmation. Gmail App Password is not set in system_settings.");
    // Skip the rest of the mail sending logic
    throw new Exception("Email could not be sent due to configuration issues.");
}
$mail->Password   = $gmail_app_password; // Your App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('medsync.calysta@gmail.com', 'MedSync Security');
        $mail->addAddress($email, $user_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Security Alert: Your MedSync Password Has Been Changed';
        
        // Set timezone to Indian Standard Time and get current datetime
        date_default_timezone_set('Asia/Kolkata');
        $current_datetime = date('Y-m-d h:i:s A T');
        
        $mail->Body    = getPasswordResetConfirmationTemplate($user_name, $current_datetime);
        $mail->AltBody = 'This is a confirmation that the password for your MedSync account has been changed successfully. If you did not make this change, please contact our support team immediately.';

        $mail->send();
    } catch (Exception $e) {
        // Email sending failed, but the password reset was successful.
        // Log the error for debugging but do not block the user's flow.
        error_log("Password reset confirmation email failed to send to {$email}: {$mail->ErrorInfo}");
    }

    // --- Clean up session and redirect ---
    unset($_SESSION['password_reset']);
    unset($_SESSION['reset_otp_verified']);
    
    $_SESSION['login_message'] = ['type' => 'success', 'text' => 'Your password has been reset successfully. You can now log in.'];
    header("Location: ../login.php");
    exit();
} else {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Failed to update password. Please try again.'];
    header("Location: ../forgot_password/create_new_password.php");
    exit();
}

$stmt_update->close();
$conn->close();
?>
