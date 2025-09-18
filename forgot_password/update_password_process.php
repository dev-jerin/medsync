<?php
/**
 * Processes the new password form, validates, updates the database,
 * and sends a confirmation email.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
// UPDATED: Include the new file with only the email template functions.
require_once 'email_templates.php';


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