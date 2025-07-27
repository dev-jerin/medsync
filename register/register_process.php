<?php
/**
 * Processes registration, handles file upload, and sends OTP.
 */

// --- PHPMailer Inclusion ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/Exception.php';
require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';

// Include the config file and the new OTP email template
require_once '../config.php';
require_once 'otp_email_template.php';

// --- Security Checks ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['register_error'] = "Invalid request method.";
    header("Location: ../register.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['register_error'] = "CSRF validation failed. Please try again.";
    header("Location: ../register.php");
    exit();
}

// --- Form Data Retrieval & Formatting ---
$name = trim($_POST['name']);
$username = strtolower(str_replace(' ', '', trim($_POST['username'])));
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$date_of_birth = trim($_POST['date_of_birth']);
$gender = trim($_POST['gender']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$role = 'user';

// --- Server-Side Validation ---
// (Error checking for empty fields, password match, etc.)
if (empty($name) || empty($username) || empty($email) || empty($phone) || empty($date_of_birth) || empty($gender) || empty($password)) {
    $_SESSION['register_error'] = "All fields are required.";
    header("Location: ../register.php");
    exit();
}
if ($password !== $confirm_password) {
    $_SESSION['register_error'] = "Passwords do not match.";
    header("Location: ../register.php");
    exit();
}
// ... (other validations remain the same) ...

// --- Profile Picture Handling ---
$profile_picture_filename = 'default.png'; // Default value

if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
    $upload_dir = '../uploads/profile_pictures/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2 MB

    $file_type = $_FILES['profile_picture']['type'];
    $file_size = $_FILES['profile_picture']['size'];

    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
        $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('user_', true) . '.' . $file_extension;
        
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
            $profile_picture_filename = $new_filename;
        } else {
            $_SESSION['register_error'] = "Could not upload profile picture. Please try again.";
            header("Location: ../register.php");
            exit();
        }
    } else {
        $_SESSION['register_error'] = "Invalid file type or size. Please upload a JPG, PNG, or GIF under 2MB.";
        header("Location: ../register.php");
        exit();
    }
}

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
$otp = random_int(100000, 999999);
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

$_SESSION['registration_data'] = [
    'name' => $name,
    'username' => $username,
    'email' => $email,
    'phone' => $phone,
    'date_of_birth' => $date_of_birth,
    'gender' => $gender,
    'password' => $hashed_password,
    'role' => $role,
    'profile_picture' => $profile_picture_filename, // Store filename in session
    'otp' => $otp,
    'timestamp' => time()
];

// --- Email OTP using PHPMailer ---
$mail = new PHPMailer(true);

try {
    // ... (PHPMailer setup remains the same) ...
    $system_email = get_system_setting($conn, 'system_email');
    $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

    if (empty($system_email) || empty($gmail_app_password)) {
        throw new Exception("Mail service is not configured.");
    }

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $system_email;
    $mail->Password = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom($system_email, 'MedSync');
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Your Verification Code for MedSync';
    $mail->Body    = getOtpEmailTemplate($name, $otp); // Use the new template function
    $mail->AltBody = "Your OTP for MedSync is: $otp. It's valid for 10 minutes.";

    $mail->send();
    header("Location: ../register/verify_otp.php");
    exit();

} catch (Exception $e) {
    $_SESSION['register_error'] = "Could not send OTP. Mailer Error: {$mail->ErrorInfo}";
    header("Location: ../register.php");
    exit();
}
?>