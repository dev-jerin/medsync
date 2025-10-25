<?php
// resend_otp.php

// These are no longer needed here as they are handled in send_mail.php
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
require_once __DIR__ . '/../mail/templates.php'; // Path to your centralized templates

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

// --- Email OTP using Centralized Function ---
require_once __DIR__ . '/../mail/send_mail.php'; // Include the centralized mail function

try {
    $subject = 'Your New Verification Code for MedSync';
    $body = getOtpEmailTemplate($session_data['name'], $new_otp);

    // Call the centralized mail function
    if (send_mail('MedSync', $session_data['email'], $subject, $body)) {
        $conn->close();
        // Send a success response back to the JavaScript
        echo json_encode(['status' => 'success', 'message' => 'A new OTP has been sent to your email.']);
        exit();
    } else {
        // This will trigger if send_mail returns false
        throw new Exception('Could not send OTP. Please try again later.');
    }

} catch (Exception $e) {
    error_log("Mailer Error on Resend: " . $e->getMessage());
    $conn->close();
    // Send an error response back to the JavaScript
    echo json_encode(['status' => 'error', 'message' => 'Could not send OTP. Please try again later.']);
    exit();
}
?>