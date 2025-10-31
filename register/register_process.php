<?php
/**
 * Processes registration, validates data, handles file upload, and sends OTP.
 */

// --- PHPMailer Inclusion ---
// We no longer need the 'use' statements here as they are in send_mail.php
require '../vendor/autoload.php';
require_once '../config.php';
require_once __DIR__ . '/../mail/templates.php'; // Centralized email templates

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

// --- reCAPTCHA Validation ---
if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
    $_SESSION['register_error'] = "Please complete the reCAPTCHA.";
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['confirm_password']);
    header("Location: index.php");
    exit();
}

$recaptcha_secret = RECAPTCHA_SECRET_KEY; // Assuming this is defined in your config
$recaptcha_response = $_POST['g-recaptcha-response'];
$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
$recaptcha_data = [
    'secret' => $recaptcha_secret,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR'],
];
$options = ['http' => ['header' => "Content-type: application/x-www-form-urlencoded\r\n", 'method' => 'POST', 'content' => http_build_query($recaptcha_data)]];
$context = stream_context_create($options);
$result_json = json_decode(file_get_contents($recaptcha_url, false, $context), true);

if ($result_json['success'] !== true) {
    $_SESSION['register_error'] = "reCAPTCHA verification failed. Please try again.";
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password'], $_SESSION['form_data']['confirm_password']);
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
$role_id = 1; // 'user' role

// --- Final Server-Side Validation ---
$errors = [];
if (empty($name) || empty($username) || empty($email) || empty($phone) || empty($date_of_birth) || empty($gender) || empty($password)) {
    $errors[] = "All fields are required.";
}
if (strlen($username) < 3) {
    $errors[] = "Username must be at least 3 characters.";
} elseif (preg_match('/[^\w.]/', $username)) {
    $errors[] = "Username can only contain letters, numbers, underscores, and dots.";
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}
if (!preg_match('/^\+91\d{10}$/', $phone)) {
    $errors[] = "Phone number must be in the format +91 followed by 10 digits.";
}
if (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters long.";
} elseif ($password !== $confirm_password) {
    $errors[] = "Passwords do not match.";
}

if (!empty($errors)) {
    $_SESSION['register_error'] = implode('<br>', $errors);
    header("Location: index.php");
    exit();
}

// --- Profile Picture Handling ---
$profile_picture_filename = 'default.png';
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/profile_pictures/';
    // Basic security checks for the uploaded file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2 MB
    if (in_array(mime_content_type($_FILES['profile_picture']['tmp_name']), $allowed_types) && $_FILES['profile_picture']['size'] <= $max_size) {
        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $new_filename = 'user_' . uniqid('', true) . '.' . $file_extension;
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
            $profile_picture_filename = $new_filename;
        }
    }
}

// --- Check for Existing User in Database ---
$conn = getDbConnection();
$stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$stmt_check->bind_param("ss", $username, $email);
$stmt_check->execute();
if ($stmt_check->get_result()->num_rows > 0) {
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

// --- Email OTP using Centralized Function ---
require_once __DIR__ . '/../mail/send_mail.php';

try {
    $subject = 'Your Verification Code for MedSync';
    $body = getOtpEmailTemplate($name, $otp, $_SERVER['REMOTE_ADDR']);

    // Call the centralized mail function
    if (send_mail('MedSync', $email, $subject, $body)) {
        $conn->close();
        header("Location: verify_otp.php");
        exit();
    } else {
        // This will trigger if send_mail returns false
        throw new Exception("Could not send OTP email. Please check the email address and try again.");
    }

} catch (Exception $e) {
    // Clean up uploaded file if email fails
    if ($profile_picture_filename !== 'default.png') {
        @unlink('../uploads/profile_pictures/' . $profile_picture_filename);
    }
    $_SESSION['register_error'] = $e->getMessage();
    $conn->close();
    header("Location: index.php");
    exit();
}
?>