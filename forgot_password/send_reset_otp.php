<?php
/**
 * Handles the "Forgot Password" form submission.
 * Generates a 6-digit OTP, stores it in the session, and sends it to the user's email
 * using a mobile-responsive HTML template.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';
require_once '../config.php';

/**
 * Returns the HTML content for the password reset OTP email.
 * This template is mobile-responsive and mirrors the registration email style.
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @return string The complete HTML email body.
 */
function getPasswordResetEmailTemplate($name, $otp) {
    // Using a HEREDOC for clean HTML structure
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MedSync Password Reset OTP</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: 'Poppins', Arial, sans-serif; }
        .container { width: 100%; padding: 20px; background-color: #f1f5f9; }
        .main-content { background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin: 0 auto; width: 100%; max-width: 600px; overflow: hidden; border: 1px solid #e2e8f0; }
        .header { background: linear-gradient(135deg, #007BFF, #17a2b8); color: #ffffff; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 26px; font-weight: 700; }
        .content-body { padding: 30px 35px; color: #343a40; line-height: 1.7; text-align: left; }
        .content-body p { font-size: 16px; margin: 0 0 15px 0; }
        .otp-box { background-color: #f8f9fa; border: 2px dashed #007BFF; margin: 25px 0; padding: 20px; text-align: center; border-radius: 8px; }
        .otp-code { font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #0056b3; margin-bottom: 15px; display: block; user-select: all; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #6c757d; background-color: #f8f9fa; }
        .footer p { margin: 5px 0; }
        @media screen and (max-width: 600px) {
            .content-body { padding: 25px 20px; }
            .header h1 { font-size: 22px; }
            .content-body p { font-size: 15px; }
            .otp-code { font-size: 28px; letter-spacing: 5px; }
        }
    </style>
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #f1f5f9;">
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
                            <p style="margin-bottom:10px; font-size:14px; color:#6c757d;">Your OTP is:</p>
                            <span class="otp-code">{$otp}</span>
                            <p style="margin-bottom:15px; font-size:12px; color:#6c757d;">This code is valid for 10 minutes.</p>
                        </div>
                        <p>If you did not request a password reset, please ignore this email. Your account is still secure.</p>
                    </div>
                    <div class="footer">
                        <p>&copy; 2025 Calysta Health Institute. All Rights Reserved.</p>
                        <p>Calysta Health Institute, Kerala, India</p>
                    </div>
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
    header("Location: ../forgot_password.php");
    exit();
}

// --- Form Data Retrieval & Validation ---
$email = trim($_POST['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['status'] = ['type' => 'error', 'text' => 'Invalid email format.'];
    header("Location: ../forgot_password.php");
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
    // SECURITY NOTE: We show a generic success message even if the email doesn't exist
    // to prevent attackers from guessing registered email addresses.
    $_SESSION['status'] = ['type' => 'success', 'text' => 'If an account with that email exists, a password reset OTP has been sent.'];
    header("Location: ../forgot_password.php");
    exit();
}
// Fetch the user's details
$user = $result->fetch_assoc();
$user_name = $user['name'];
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
    $mail->addAddress($email, $user_name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your Password Reset OTP for MedSync';
    // Use the new email template
    $mail->Body    = getPasswordResetEmailTemplate($user_name, $otp);
    $mail->AltBody = "Your password reset OTP is: {$otp}. It is valid for 10 minutes.";

    $mail->send();

    // Redirect to the new OTP verification page
    header("Location: ../forgot_password/verify_reset_otp.php");
    exit();

} catch (Exception $e) {
    $_SESSION['status'] = ['type' => 'error', 'text' => "Message could not be sent. Please try again later."];
    // For debugging, you might want to log the detailed error: error_log("Mailer Error: {$mail->ErrorInfo}");
    header("Location: forgot_password.php");
    exit();
} finally {
    $conn->close();
}
