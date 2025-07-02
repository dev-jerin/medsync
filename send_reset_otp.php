<?php
/**
 * Handles the "Forgot Password" form submission.
 * Generates a 6-digit OTP, stores it in the session, and sends it to the user's email.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require_once 'config.php';

// --- Security Check: POST request and CSRF Token ---
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid request. Please try again.'];
    header("Location: forgot_password.php");
    exit();
}

// --- Form Data Retrieval & Validation ---
$email = trim($_POST['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid email format.'];
    header("Location: forgot_password.php");
    exit();
}

$conn = getDbConnection();

// --- Check if Email Exists in the Database ---
$sql_check = "SELECT id FROM users WHERE email = ? LIMIT 1";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    // SECURITY NOTE: We show a generic success message even if the email doesn't exist
    // to prevent attackers from guessing registered email addresses.
    $_SESSION['status'] = ['type' => 'success', 'text' => 'If an account with that email exists, a password reset OTP has been sent.'];
    header("Location: forgot_password.php");
    exit();
}
$stmt_check->close();

// --- OTP Generation and Session Storage ---
$otp = random_int(100000, 999999);

// Store email, OTP, and timestamp in the session for verification
$_SESSION['password_reset'] = [
    'email' => $email,
    'otp' => $otp,
    'timestamp' => time() // To check for expiry
];

// --- Send Email with PHPMailer ---
$mail = new PHPMailer(true);
try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'medsync.calysta@gmail.com';
    $mail->Password   = 'sswyqzegdpyixbyw'; // Your App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('medsync.calysta@gmail.com', 'MedSync Support');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your Password Reset OTP for MedSync';
    $mail->Body    = "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <h2>Password Reset Request</h2>
                        <p>We received a request to reset your password. Use the following One-Time Password (OTP) to complete the process.</p>
                        <p>Your OTP is: <strong style='font-size: 20px; letter-spacing: 2px;'>{$otp}</strong></p>
                        <p>This OTP is valid for 10 minutes. If you did not request a password reset, please ignore this email.</p>
                    </div>";
    $mail->AltBody = "Your password reset OTP is: {$otp}. It is valid for 10 minutes.";

    $mail->send();

    // Redirect to the page where the user will enter the OTP and new password
    header("Location: verify_and_reset_password.php");
    exit();

} catch (Exception $e) {
    $_SESSION['status'] = ['type' => 'error', 'text' => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"];
    header("Location: forgot_password.php");
    exit();
} finally {
    $conn->close();
}
