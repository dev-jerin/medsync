<?php
// forgot_password/resend_reset_otp.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
// UPDATED: Include the new file with only the email template functions.
require_once 'email_templates.php'; 

header('Content-Type: application/json');

// --- Security & Session Checks ---
// 1. Ensure it's a POST request
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}
// 2. CSRF Token Validation
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid security token.']);
    exit();
}
// 3. Check if password reset data exists in the session
if (!isset($_SESSION['password_reset'])) {
    echo json_encode(['status' => 'error', 'message' => 'Your session has expired. Please start over.']);
    exit();
}

// --- Generate and Send New OTP ---
$conn = getDbConnection();
$session_data = $_SESSION['password_reset'];

// Generate a new OTP
$new_otp = random_int(100000, 999999);

// Update the session with the new OTP and a new timestamp
$_SESSION['password_reset']['otp'] = $new_otp;
$_SESSION['password_reset']['timestamp'] = time();

// --- Email OTP using PHPMailer ---
$mail = new PHPMailer(true);
try {
    // Fetch user's name for a personalized email
    $stmt_get_name = $conn->prepare("SELECT name FROM users WHERE email = ?");
    $stmt_get_name->bind_param("s", $session_data['email']);
    $stmt_get_name->execute();
    $user_name = $stmt_get_name->get_result()->fetch_assoc()['name'] ?? 'User';
    $stmt_get_name->close();

    $system_email = get_system_setting($conn, 'system_email');
    $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

    if (empty($system_email) || empty($gmail_app_password)) {
        throw new Exception("Mail service is not configured.");
    }

    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $system_email;
    $mail->Password = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Recipients
    $mail->setFrom($system_email, 'MedSync Support');
    $mail->addAddress($session_data['email'], $user_name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your New Password Reset Code for MedSync';
    // Use the correct email template function
    $mail->Body    = getPasswordResetEmailTemplate($user_name, $new_otp); 
    $mail->AltBody = "Your new password reset code for MedSync is: $new_otp. It's valid for 10 minutes.";

    $mail->send();
    $conn->close();
    
    echo json_encode(['status' => 'success', 'message' => 'A new OTP has been sent to your email.']);
    exit();

} catch (Exception $e) {
    error_log("Mailer Error on Resend Reset OTP: " . $mail->ErrorInfo);
    $conn->close();

    echo json_encode(['status' => 'error', 'message' => 'Could not send OTP. Please try again later.']);
    exit();
}
?>