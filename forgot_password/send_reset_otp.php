<?php
/**
 * Handles the "Forgot Password" form submission.
 * Generates a 6-digit OTP, stores it in the session, and sends it to the user's email.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';

/**
 * Returns the HTML content for the professional password reset OTP email.
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @return string The complete HTML email body.
 */
function getPasswordResetEmailTemplate($name, $otp) {
    $currentYear = date('Y');
    
    // Using a HEREDOC for clean, modern HTML structure
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MedSync Password Reset Code</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .container { padding: 20px; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 0 auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #0067FF; color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #00D9E9; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .content-body { padding: 40px 35px; line-height: 1.6; text-align: left; }
        .content-body p { font-size: 16px; margin: 0 0 20px 0; }
        .otp-box { text-align: center; margin: 30px 0; }
        .otp-label { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
        .otp-code { display: inline-block; background-color: #e6f0ff; color: #0058d6; font-size: 36px; font-weight: 700; letter-spacing: 10px; padding: 15px 30px; border-radius: 8px; user-select: all; border: 1px dashed #0067FF; }
        .validity-text { font-size: 14px; color: #6c757d; text-align: center; margin-top: 15px; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Password Reset Request</h1>
                    </div>
                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>We received a request to reset the password for your MedSync account. Please use the following One-Time Password (OTP) to complete the process.</p>
                        <div class="otp-box">
                            <p class="otp-label">Your Password Reset Code:</p>
                            <span class="otp-code">{$otp}</span>
                        </div>
                        <p class="validity-text">This code is valid for the next 10 minutes.</p>
                        <p>If you did not request a password reset, please ignore this email. Your account is still secure and no action is needed.</p>
                        <p>Sincerely,<br>The MedSync Support Team</p>
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

// --- Send Email with PHPMailer ---
$mail = new PHPMailer(true);
try {
    $system_email = get_system_setting($conn, 'system_email');
    $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

    if (empty($system_email) || empty($gmail_app_password)) {
        $_SESSION['status'] = ['type' => 'error', 'text' => "The mail service is not configured. Please contact support."];
        header("Location: index.php");
        exit();
    }

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $system_email;
    $mail->Password   = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($system_email, 'MedSync Support');
    $mail->addAddress($email, $user_name);
    
    $mail->isHTML(true);
    $mail->Subject = 'Your Password Reset Code for MedSync';
    $mail->Body    = getPasswordResetEmailTemplate($user_name, $otp);
    $mail->AltBody = "Your password reset code for MedSync is: {$otp}. It is valid for 10 minutes.";

    $mail->send();

    // Redirect to the OTP verification page
    header("Location: ../forgot_password/verify_reset_otp");
    exit();

} catch (Exception $e) {
    // Log the detailed error for the admin, but show a generic message to the user.
    error_log("Mailer Error on password reset for {$email}: {$mail->ErrorInfo}");
    $_SESSION['status'] = ['type' => 'error', 'text' => "Message could not be sent. Please try again later."];
    header("Location: index.php");
    exit();
} finally {
    $conn->close();
}
