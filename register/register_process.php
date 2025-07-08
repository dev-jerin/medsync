<?php
/**
 * Processes the initial user registration form data.
 * Validates user input, generates a 6-digit OTP, and sends it via email using PHPMailer.
 * Temporarily stores registration data in the session pending OTP verification.
 */

// --- PHPMailer Inclusion ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';

// Include the database configuration file
require_once '../config.php';

/**
 * Returns the HTML content for the OTP verification email.
 * This template is mobile-responsive and designed for easy OTP copying.
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @return string The complete HTML email body.
 */
function getOtpEmailTemplate($name, $otp) {
    // Using a HEREDOC for clean HTML structure
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MedSync OTP</title>
    <style>
        /* Import Google Font */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        /* Basic Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: 'Poppins', Arial, sans-serif; }

        /* Main Styles */
        .container {
            width: 100%;
            padding: 20px;
            background-color: #f1f5f9;
        }
        .main-content {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #007BFF, #17a2b8);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }
        .content-body {
            padding: 30px 35px;
            color: #343a40;
            line-height: 1.7;
            text-align: left;
        }
        .content-body p {
            font-size: 16px;
            margin: 0 0 15px 0;
        }
        .otp-box {
            background-color: #f8f9fa;
            border: 2px dashed #007BFF;
            margin: 25px 0;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
        }
        .otp-code {
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 8px;
            color: #0056b3;
            margin-bottom: 15px;
            display: block;
            -webkit-user-select: all; /* Allows for easy selection on Webkit browsers */
            -moz-user-select: all;    /* Allows for easy selection on Firefox */
            -ms-user-select: all;     /* Allows for easy selection on IE */
            user-select: all;         /* Allows for easy selection on modern browsers */
        }
        .footer {
            text-align: center;
            padding: 25px;
            font-size: 13px;
            color: #6c757d;
            background-color: #f8f9fa;
        }
        .footer p {
            margin: 5px 0;
        }

        /* Responsive Styles */
        @media screen and (max-width: 600px) {
            .content-body {
                padding: 25px 20px;
            }
            .header h1 {
                font-size: 22px;
            }
            .content-body p {
                font-size: 15px;
            }
            .otp-code {
                font-size: 28px;
                letter-spacing: 5px;
            }
        }
    </style>
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #f1f5f9;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Your Verification Code</h1>
                    </div>
                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>Thank you for starting your registration with MedSync. Please use the following One-Time Password (OTP) to verify your email address and complete the process.</p>
                        <div class="otp-box">
                            <p style="margin-bottom:10px; font-size:14px; color:#6c757d;">Your OTP is:</p>
                            <span class="otp-code">{$otp}</span>
                            <p style="margin-bottom:15px; font-size:12px; color:#6c757d;">This code is valid for 10 minutes.</p>
                        </div>
                        <p>If you did not request this code, please ignore this email. No account will be created without this verification step.</p>
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

// --- Security Check: Ensure the request is a POST request ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['register_error'] = "Invalid request method.";
    header("Location: ../register.php");
    exit();
}

// --- Security Check: Validate CSRF Token ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['register_error'] = "CSRF validation failed. Please try again.";
    header("Location: ../register.php");
    exit();
}

// --- Form Data Retrieval & Formatting ---
$name = trim($_POST['name']);
// Username: trim, remove spaces, and convert to lowercase
$username = strtolower(str_replace(' ', '', trim($_POST['username'])));
$email = trim($_POST['email']);
$date_of_birth = trim($_POST['date_of_birth']);
$gender = trim($_POST['gender']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$role = 'user'; // Default role for new registrations

// --- Server-Side Validation ---
if (empty($name) || empty($username) || empty($email) || empty($date_of_birth) || empty($gender) || empty($password)) {
    $_SESSION['register_error'] = "All fields are required.";
    header("Location: ../register.php");
    exit();
}
if ($password !== $confirm_password) {
    $_SESSION['register_error'] = "Passwords do not match.";
    header("Location: ../register.php");
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = "Invalid email format.";
    header("Location: ../register.php");
    exit();
}
// Optional: More specific validation for username, name, etc., can be added here.

// --- Check for Existing User ---
$conn = getDbConnection();
$sql_check = "SELECT id FROM users WHERE username = ? OR email = ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ss", $username, $email);
$stmt_check->execute();
$stmt_check->store_result();

if ($stmt_check->num_rows > 0) {
    $_SESSION['register_error'] = "Username or email is already taken.";
    $stmt_check->close();
    $conn->close();
    header("Location: ../register.php");
    exit();
}
$stmt_check->close();

// --- OTP Generation and Session Storage ---

// Generate a 6-digit random OTP
$otp = random_int(100000, 999999);

// Hash the password for secure storage
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Store all registration data and the OTP in the session
$_SESSION['registration_data'] = [
    'name' => $name,
    'username' => $username,
    'email' => $email,
    'date_of_birth' => $date_of_birth,
    'gender' => $gender,
    'password' => $hashed_password,
    'role' => $role,
    'otp' => $otp,
    'timestamp' => time() // Store the current time to check for OTP expiry
];

// --- Email OTP using PHPMailer ---
$mail = new PHPMailer(true);

try {
    // Server settings for Gmail
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'medsync.calysta@gmail.com';
    $mail->Password   = 'sswyqzegdpyixbyw'; // Use a 16-digit App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom('medsync.calysta@gmail.com', 'MedSync');
    $mail->addAddress($email, $name); // Use the full name here

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP for MedSync Registration';
    $mail->Body    = getOtpEmailTemplate($name, $otp);
    $mail->AltBody = "Your OTP for MedSync Registration is: $otp. This OTP is valid for 10 minutes.";

    $mail->send();

    // Redirect to the OTP verification page
    header("Location: ../register/verify_otp.php");
    exit();

} catch (Exception $e) {
    // If email sending fails, show an error.
    $_SESSION['register_error'] = "Could not send OTP. Please check your email and try again. Mailer Error: {$mail->ErrorInfo}";
    header("Location: ../register.php");
    exit();
}

?>
