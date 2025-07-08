<?php
/**
 * Processes the OTP, creates the user account, and sends a welcome email.
 */

// --- PHPMailer Inclusion ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';

// --- Other Required Files ---
require_once '../config.php';
require_once 'welcome_email_template.php'; // Include the new email template file

// --- Security & Session Checks ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['verify_error'] = "Invalid request method.";
    header("Location: ../register/verify_otp.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['verify_error'] = "CSRF validation failed. Please try again.";
    header("Location: ../register/verify_otp.php");
    exit();
}
if (!isset($_SESSION['registration_data'])) {
    header("Location: ../register.php");
    exit();
}

// --- OTP Validation ---
$submitted_otp = trim($_POST['otp']);
$session_data = $_SESSION['registration_data'];

// 1. Check if the submitted OTP is correct
if ($submitted_otp != $session_data['otp']) {
    $_SESSION['verify_error'] = "Invalid OTP. Please try again.";
    header("Location: ../register/verify_otp.php");
    exit();
}

// 2. Check for OTP expiry (10 minutes)
$otp_expiry_time = 600; 
if (time() - $session_data['timestamp'] > $otp_expiry_time) {
    $_SESSION['verify_error'] = "OTP has expired. Please start the registration process again.";
    unset($_SESSION['registration_data']);
    header("Location: ../register.php");
    exit();
}

// --- Database Insertion ---
$conn = getDbConnection();
$sql_insert = "INSERT INTO users (username, name, email, password, role, date_of_birth, gender, display_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);

$display_user_id_placeholder = 'TEMP'; // Temporary placeholder

$stmt_insert->bind_param(
    "ssssssss",
    $session_data['username'],
    $session_data['name'],
    $session_data['email'],
    $session_data['password'],
    $session_data['role'],
    $session_data['date_of_birth'],
    $session_data['gender'],
    $display_user_id_placeholder
);

if ($stmt_insert->execute()) {
    $last_id = $stmt_insert->insert_id;
    $display_user_id = 'U' . str_pad($last_id, 4, '0', STR_PAD_LEFT);
    
    // Update the record with the final display_user_id
    $sql_update_id = "UPDATE users SET display_user_id = ? WHERE id = ?";
    $stmt_update_id = $conn->prepare($sql_update_id);
    $stmt_update_id->bind_param("si", $display_user_id, $last_id);
    $stmt_update_id->execute();
    $stmt_update_id->close();

    // --- Send Welcome Email ---
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
        $mail->setFrom('medsync.calysta@gmail.com', 'MedSync');
        $mail->addAddress($session_data['email'], $session_data['name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to MedSync, ' . $session_data['name'] . '!';
        // Get the HTML body from our template function
        $mail->Body    = getWelcomeEmailTemplate(
            $session_data['name'],
            $session_data['username'],
            $display_user_id,
            $session_data['email']
        );
        $mail->AltBody = "Welcome to MedSync! Your User ID is {$display_user_id}.";

        $mail->send();
    } catch (Exception $e) {
        // Optional: Log the email error, but don't block the user's registration
        // error_log("Welcome email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }

    // Clean up the session and redirect
    unset($_SESSION['registration_data']);
    $_SESSION['register_success'] = "Registration successful! Your User ID is " . $display_user_id . ". You can now log in.";
    header("Location: ../login.php");
    exit();

} else {
    $_SESSION['verify_error'] = "Database error: Could not create account.";
    header("Location: ../register/verify_otp.php");
    exit();
}

$stmt_insert->close();
$conn->close();
?>
