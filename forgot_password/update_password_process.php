<?php
/**
 * Processes the new password form, validates, updates the database,
 * and sends a confirmation email.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';

/**
 * Returns the HTML content for the password reset confirmation email.
 *
 * @param string $name The user's full name.
 * @param string $datetime The date and time of the password change.
 * @return string The complete HTML email body.
 */
function getPasswordResetConfirmationTemplate($name, $datetime) {
    $currentYear = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert: Your MedSync Password Was Changed</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .container { padding: 20px; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 0 auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #ffc107; color: #1a202c; padding: 40px 20px; text-align: center; border-bottom: 5px solid #e9a900; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .content-body { padding: 40px 35px; line-height: 1.6; text-align: left; }
        .content-body p { font-size: 16px; margin: 0 0 20px 0; }
        .alert-details { background-color: #fffbeb; border-left: 4px solid #ffc107; margin: 25px 0; padding: 20px; border-radius: 8px; }
        .alert-details p { margin: 10px 0; font-size: 15px; color: #5c3f00; }
        .alert-details strong { color: #1a202c; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Security Alert</h1>
                    </div>
                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>This is a confirmation that the password for your MedSync account was successfully changed. Your account security is our top priority.</p>
                        <div class="alert-details">
                            <p><strong>Date & Time of Change:</strong> {$datetime}</p>
                        </div>
                        <p>If you made this change, you can safely ignore this email. Your account is secure.</p>
                        <p><strong>If you did NOT authorize this change,</strong> please contact our support team immediately so we can help you secure your account.</p>
                        <p>Sincerely,<br>The MedSync Security Team</p>
                    </div>
                </div>
                <div class="footer">
                    &copy; {$currentYear} Calysta Health Institute. All Rights Reserved.<br>
                    Kerala, India
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

// --- Security Checks ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']) || !isset($_SESSION['reset_otp_verified']) || $_SESSION['reset_otp_verified'] !== true) {
    header("Location: index.php");
    exit();
}

// --- Form Data Validation ---
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

if (empty($password) || empty($confirm_password)) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Both password fields are required.'];
    header("Location: create_new_password");
    exit();
}
if ($password !== $confirm_password) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Passwords do not match.'];
    header("Location: create_new_password");
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
    $user = $stmt_get_user->get_result()->fetch_assoc();
    $user_name = $user ? $user['name'] : 'Valued User';
    $stmt_get_user->close();

    // --- Send Confirmation Email ---
    $mail = new PHPMailer(true);
    try {
        $system_email = get_system_setting($conn, 'system_email');
        $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

        if (!empty($system_email) && !empty($gmail_app_password)) {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $system_email;
            $mail->Password   = $gmail_app_password;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom($system_email, 'MedSync Security');
            $mail->addAddress($email, $user_name);

            $mail->isHTML(true);
            $mail->Subject = 'Security Alert: Your MedSync Password Has Been Changed';
            
            date_default_timezone_set('Asia/Kolkata');
            $current_datetime = date('F j, Y, g:i A T');
            
            $mail->Body    = getPasswordResetConfirmationTemplate($user_name, $current_datetime);
            $mail->AltBody = 'This is a confirmation that the password for your MedSync account was changed. If you did not make this change, please contact support immediately.';

            $mail->send();
        }
    } catch (Exception $e) {
        error_log("Password reset confirmation email failed for {$email}: {$mail->ErrorInfo}");
    }

    // --- Clean up session and redirect ---
    unset($_SESSION['password_reset']);
    unset($_SESSION['reset_otp_verified']);
    
    $_SESSION['login_message'] = ['type' => 'success', 'text' => 'Your password has been reset successfully. Please log in with your new password.'];
    header("Location: ../login");
    exit();
} else {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Failed to update password. Please try again.'];
    header("Location: create_new_password");
    exit();
}


