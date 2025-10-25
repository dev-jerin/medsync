<?php
/**
 * Processes the new password form, validates, updates the database,
 * and sends a confirmation email.
 */

// We no longer need the PHPMailer 'use' statements here
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
// UPDATED: Include the new file with only the email template functions.
require_once __DIR__ . '/../mail/templates.php';


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
    header("Location: create_new_password.php");
    exit();
}
if ($password !== $confirm_password) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Passwords do not match.'];
    header("Location: create_new_password.php");
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

    // --- Send Confirmation Email using Centralized Function ---
    require_once __DIR__ . '/../mail/send_mail.php';
    try {
        $subject = 'Security Alert: Your MedSync Password Has Been Changed';
        
        date_default_timezone_set('Asia/Kolkata');
        $current_datetime = date('F j, Y, g:i A T');
        
        $body = getPasswordResetConfirmationTemplate($user_name, $current_datetime);

        // Call the centralized function
        send_mail('MedSync Security', $email, $subject, $body);

    } catch (Exception $e) {
        error_log("Password reset confirmation email failed for {$email}: " . $e->getMessage());
    }

    // --- Clean up session and redirect ---
    unset($_SESSION['password_reset']);
    unset($_SESSION['reset_otp_verified']);
    
    $_SESSION['login_message'] = ['type' => 'success', 'text' => 'Your password has been reset successfully. Please log in with your new password.'];
    header("Location: ../login");
    exit();
} else {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Failed to update password. Please try again.'];
    header("Location: create_new_password.php");
    exit();
}
?>