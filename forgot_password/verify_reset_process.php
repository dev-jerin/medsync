<?php
/**
 * Processes the OTP submitted for password reset.
 */

require_once '../config.php';

// --- Security & Session Checks ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid request method.'];
    header("Location: ../forgot_password/verify_reset_otp.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'CSRF validation failed. Please try again.'];
    header("Location: ../forgot_password/verify_reset_otp.php");
    exit();
}
if (!isset($_SESSION['password_reset'])) {
    header("Location: ../forgot_password.php");
    exit();
}

// --- OTP Validation ---
$submitted_otp = trim($_POST['otp']);
$session_data = $_SESSION['password_reset'];

// 1. Check if OTP has expired (10 minutes / 600 seconds)
if (time() - $session_data['timestamp'] > 600) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'OTP has expired. Please request a new one.'];
    unset($_SESSION['password_reset']); // Clear expired data
    header("Location: ../forgot_password.php");
    exit();
}

// 2. Check if the submitted OTP is correct
if ($submitted_otp != $session_data['otp']) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid OTP. Please check and try again.'];
    header("Location: ../forgot_password/verify_reset_otp.php");
    exit();
}

// --- OTP is correct ---
// Set a flag in the session to indicate OTP verification was successful
$_SESSION['reset_otp_verified'] = true;

// Redirect to the page for creating a new password
header("Location: ../forgot_password/create_new_password.php");
exit();

?>
