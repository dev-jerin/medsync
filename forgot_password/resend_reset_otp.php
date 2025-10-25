<?php
// forgot_password/resend_reset_otp.php

// These are no longer needed here as they are handled in the send_mail.php file
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
// UPDATED: Include the centralized email templates file.
require_once __DIR__ . '/../mail/templates.php';

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

// --- Email OTP using Centralized Function ---
require_once __DIR__ . '/../mail/send_mail.php'; // Include the centralized mail function

try {
    // Fetch user's name for a personalized email
    $stmt_get_name = $conn->prepare("SELECT name FROM users WHERE email = ?");
    $stmt_get_name->bind_param("s", $session_data['email']);
    $stmt_get_name->execute();
    $user_name = $stmt_get_name->get_result()->fetch_assoc()['name'] ?? 'User';
    $stmt_get_name->close();

    $subject = 'Your New Password Reset Code for MedSync';
    $body    = getPasswordResetEmailTemplate($user_name, $new_otp); 
    
    // Call the centralized mail function
    if (send_mail('MedSync Support', $session_data['email'], $subject, $body)) {
        $conn->close();
        echo json_encode(['status' => 'success', 'message' => 'A new OTP has been sent to your email.']);
        exit();
    } else {
        // This will trigger if the send_mail function returns false
        throw new Exception("Mail service failed.");
    }

} catch (Exception $e) {
    error_log("Mailer Error on Resend Reset OTP: " . $e->getMessage());
    $conn->close();

    echo json_encode(['status' => 'error', 'message' => 'Could not send OTP. Please try again later.']);
    exit();
}
?>