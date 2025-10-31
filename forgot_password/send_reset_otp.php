<?php
/**
 * Handles the "Forgot Password" form submission.
 * Generates a 6-digit OTP, stores it in the session, and sends it to the user's email.
 */

// We no longer need the PHPMailer 'use' statements here
// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
// UPDATED: Include the centralized email templates file.
require_once __DIR__ . '/../mail/templates.php';

// --- Security Check: POST request and CSRF Token ---
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid request. Please try again.'];
    header("Location: index.php");
    exit();
}

// --- Form Data Retrieval & Validation ---
$email = trim($_POST['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid email format.'];
    header("Location: index.php");
    exit();
}

$conn = getDbConnection();

// --- Check if Email Exists and Fetch User's Name ---
$sql_check = "SELECT id, name FROM users WHERE email = ? LIMIT 1";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows === 0) {
    // SECURITY NOTE: Show a generic success message even if the email doesn't exist
    // to prevent attackers from guessing registered email addresses.
    $_SESSION['status'] = ['type' => 'success', 'text' => 'If an account with that email exists, a password reset OTP has been sent.'];
    header("Location: index.php");
    exit();
}
$user = $result->fetch_assoc();
$user_name = $user['name'];
$stmt_check->close();

// --- OTP Generation and Session Storage ---
$otp = random_int(100000, 999999);
$_SESSION['password_reset'] = [
    'email' => $email,
    'otp' => $otp,
    'timestamp' => time() // To check for expiry
];

// --- Send Email with Centralized Function ---
require_once __DIR__ . '/../mail/send_mail.php';

try {
    $subject = 'Your Password Reset Code for MedSync';
    $body = getPasswordResetEmailTemplate($user_name, $otp, $_SERVER['REMOTE_ADDR']);

    if (send_mail('MedSync Support', $email, $subject, $body)) {
        // Redirect to the OTP verification page on success
        header("Location: ../forgot_password/verify_reset_otp.php");
        exit();
    } else {
        throw new Exception("Mail service failed. Please try again later.");
    }

} catch (Exception $e) {
    // Log the detailed error for the admin, but show a generic message to the user.
    error_log("Mailer Error on password reset for {$email}: " . $e->getMessage());
    $_SESSION['status'] = ['type' => 'error', 'text' => "Message could not be sent. Please try again later."];
    header("Location: index.php");
    exit();
} finally {
    $conn->close();
}
?>