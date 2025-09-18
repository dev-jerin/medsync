<?php
/**
 * Processes registration, validates data, handles file upload, and sends OTP.
 */

// --- PHPMailer Inclusion ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';
require_once '../config.php';
require_once 'otp_email_template.php';

// --- Security Checks ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['register_error'] = "Invalid request method.";
    header("Location: index.php");
    exit();
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['register_error'] = "CSRF validation failed. Please try again.";
    header("Location: index.php");
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
$role_id = 1; // 'user' role has an ID of 1 in the schema.

// --- Final Server-Side Validation ---
$errors = [];
// Required fields check
if (empty($name) || empty($username) || empty($email) || empty($phone) || empty($date_of_birth) || empty($gender) || empty($password)) {
    $errors[] = "All fields are required.";
}

// Username validation
if (strlen($username) < 3) {
    $errors[] = "Username must be at least 3 characters.";
} elseif (preg_match('/[^\w.]/', $username)) {
    $errors[] = "Username can only contain letters, numbers, underscores, and dots.";
} elseif (preg_match('/^(u|a|s|d)\d{4}$/i', $username)) {
    $errors[] = "This username format is reserved.";
}

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Phone validation
if (!preg_match('/^\+91\d{10}$/', $phone)) {
    $errors[] = "Phone number must be in the format +91 followed by 10 digits.";
}

// Password validation
if (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters long.";
} elseif ($password !== $confirm_password) {
    $errors[] = "Passwords do not match.";
}

if (!empty($errors)) {
    $_SESSION['register_error'] = implode('<br>', $errors); //implode acts like a glue to show more than 1 error
    header("Location: index.php");
    exit();
}

// --- Profile Picture Handling ---
$profile_picture_filename = 'default.png';
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/profile_pictures/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2 MB

    $file_info = new finfo(FILEINFO_MIME_TYPE);
    $file_type = $file_info->file($_FILES['profile_picture']['tmp_name']);
    $file_size = $_FILES['profile_picture']['size'];

    if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $new_filename = 'user_' . uniqid('', true) . '.' . $file_extension;
        
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
            $_SESSION['register_error'] = "Could not upload profile picture. Please try again.";
            header("Location: index.php");
            exit();
        }
        $profile_picture_filename = $new_filename;
    } else {
        $_SESSION['register_error'] = "Invalid file type or size. Please upload a JPG, PNG, or GIF under 2MB.";
        header("Location: index.php");
        exit();
    }
}

// --- Check for Existing User in Database ---
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
    header("Location: index.php");
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
    'role_id' => $role_id,
    'profile_picture' => $profile_picture_filename,
    'otp' => $otp,
    'timestamp' => time()
];

// --- Email OTP using PHPMailer ---
$mail = new PHPMailer(true);
try {
    $system_email = get_system_setting($conn, 'system_email');
    $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

    if (empty($system_email) || empty($gmail_app_password)) {
        throw new Exception("Mail service is not configured in system settings.");
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
    $mail->Body    = getOtpEmailTemplate($name, $otp);
    $mail->AltBody = "Your OTP for MedSync is: $otp. It's valid for 10 minutes.";

    $mail->send();
    $conn->close();
    header("Location: verify_otp.php");
    exit();

} catch (Exception $e) {
    // Clean up uploaded file if email fails
    if ($profile_picture_filename !== 'default.png') {
        unlink('../uploads/profile_pictures/' . $profile_picture_filename);
    }
    error_log("Mailer Error: " . $mail->ErrorInfo); // Log the detailed error for debugging
    $_SESSION['register_error'] = "Could not send OTP email. Please check the email address and try again.";
    $conn->close();
    header("Location: index.php");
    exit();
}
?>