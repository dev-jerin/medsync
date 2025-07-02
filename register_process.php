<?php
/**
 * Processes the initial user registration form data.
 * Validates user input, generates a 6-digit OTP, and sends it via email using PHPMailer.
 * Temporarily stores registration data in the session pending OTP verification.
 */

// --- PHPMailer Inclusion ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Include the database configuration file
require_once 'config.php';

// --- Security Check: Ensure the request is a POST request ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['register_error'] = "Invalid request method.";
    header("Location: register.php");
    exit();
}

// --- Security Check: Validate CSRF Token ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['register_error'] = "CSRF validation failed. Please try again.";
    header("Location: register.php");
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
    header("Location: register.php");
    exit();
}
if ($password !== $confirm_password) {
    $_SESSION['register_error'] = "Passwords do not match.";
    header("Location: register.php");
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['register_error'] = "Invalid email format.";
    header("Location: register.php");
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
    header("Location: register.php");
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
    $mail->Body    = "<div style='font-family: Arial, sans-serif; line-height: 1.6;'>
                        <h2>Welcome to MedSync!</h2>
                        <p>Thank you for registering. Please use the following One-Time Password (OTP) to complete your registration process.</p>
                        <p>Your OTP is: <strong style='font-size: 20px; letter-spacing: 2px;'>$otp</strong></p>
                        <p>This OTP is valid for 10 minutes.</p>
                        <p>If you did not request this, please ignore this email.</p>
                        <br>
                        <p>Best regards,</p>
                        <p>The MedSync Team</p>
                    </div>";
    $mail->AltBody = "Your OTP for MedSync Registration is: $otp. This OTP is valid for 10 minutes.";

    $mail->send();

    // Redirect to the OTP verification page
    header("Location: verify_otp.php");
    exit();

} catch (Exception $e) {
    // If email sending fails, show an error.
    $_SESSION['register_error'] = "Could not send OTP. Please check your email and try again. Mailer Error: {$mail->ErrorInfo}";
    header("Location: register.php");
    exit();
}

?>