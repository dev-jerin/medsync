<?php
// resend_otp.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
require_once 'otp_email_template.php';

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
// 3. Check if registration data exists in the session
if (!isset($_SESSION['registration_data'])) {
    echo json_encode(['status' => 'error', 'message' => 'Your session has expired. Please start over.']);
    exit();
}

// --- Generate and Send New OTP ---
$conn = getDbConnection();
$session_data = $_SESSION['registration_data'];

// Generate a new OTP
$new_otp = random_int(100000, 999999);

// Update the session with the new OTP and a new timestamp
$_SESSION['registration_data']['otp'] = $new_otp;
$_SESSION['registration_data']['timestamp'] = time();

// --- Email OTP using PHPMailer ---
$mail = new PHPMailer(true);
try {
    $system_email = get_system_setting($conn, 'system_email');
    $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

    if (empty($system_email) || empty($gmail_app_password)) {
        throw new Exception("Mail service is not configured.");
    }

    // Server settings (same as in register_process.php)
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $system_email;
    $mail->Password = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Recipients
    $mail->setFrom($system_email, 'MedSync');
    $mail->addAddress($session_data['email'], $session_data['name']);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your New Verification Code for MedSync';
    $mail->Body    = getOtpEmailTemplate($session_data['name'], $new_otp);
    $mail->AltBody = "Your new OTP for MedSync is: $new_otp. It's valid for 10 minutes.";

    $mail->send();
    $conn->close();
    // Send a success response back to the JavaScript
    echo json_encode(['status' => 'success', 'message' => 'A new OTP has been sent to your email.']);
    exit();

} catch (Exception $e) {
    error_log("Mailer Error on Resend: " . $mail->ErrorInfo);
    $conn->close();
    // Send an error response back to the JavaScript
    echo json_encode(['status' => 'error', 'message' => 'Could not send OTP. Please try again later.']);
    exit();
}
?>