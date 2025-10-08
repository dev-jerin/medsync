<?php
/**
 * Processes the login form submission.
 * Handles user authentication, session creation, IP tracking, and redirection.
 * This file is intended to be in a 'login' subfolder.
 */

// config.php is in the parent directory
require_once '../config.php';

// --- Security Check: Ensure the request is a POST request ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../index.php");
    exit();
}

// --- Security Check: Validate CSRF Token ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['login_error'] = "Invalid session. Please try logging in again.";
    header("Location: index.php");
    exit();
}

// --- reCAPTCHA Validation ---
if (!isset($_POST['g-recaptcha-response']) || empty($_POST['g-recaptcha-response'])) {
    $_SESSION['login_error'] = "Please complete the reCAPTCHA.";
    // Persist form data
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password']);
    header("Location: index.php");
    exit();
}

$recaptcha_response = $_POST['g-recaptcha-response'];
$recaptcha_secret = RECAPTCHA_SECRET_KEY;
$recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';
$recaptcha_data = [
    'secret' => $recaptcha_secret,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR'],
];

$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($recaptcha_data),
    ],
];

$context = stream_context_create($options);
$result = file_get_contents($recaptcha_url, false, $context);
$result_json = json_decode($result, true);

if ($result_json['success'] !== true) {
    $_SESSION['login_error'] = "reCAPTCHA verification failed. Please try again.";
    // Persist form data
    $_SESSION['form_data'] = $_POST;
    unset($_SESSION['form_data']['password']);
    header("Location: index.php");
    exit();
}


// --- Form Data Retrieval ---
$login_identifier = trim($_POST['username']); // Can be username, email, or user_id
$password = $_POST['password'];

// --- Basic Validation ---
if (empty($login_identifier) || empty($password)) {
    $_SESSION['login_error'] = "All fields are required.";
    header("Location: index.php");
    exit();
}

// --- Database Authentication ---
$conn = getDbConnection();

// Prepare a statement to fetch user data by joining the users and roles tables.
$sql = "SELECT 
            u.id, 
            u.username, 
            u.password, 
            r.role_name AS role, 
            u.display_user_id, 
            u.is_active 
        FROM users u 
        JOIN roles r ON u.role_id = r.id 
        WHERE u.username = ? OR u.email = ? OR u.display_user_id = ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $login_identifier, $login_identifier, $login_identifier);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // --- Verify Password ---
    if (password_verify($password, $user['password'])) {

        // --- Check if the account is active ---
        if ($user['is_active'] == 0) {
            $_SESSION['login_message'] = [
                'type' => 'error',
                'text' => 'Your account is currently inactive. Please <a href="../index.php#contact">contact support</a> for assistance.'
            ];
            header("Location: index.php");
            exit();
        }

        // --- Login Successful ---
        session_regenerate_id(true); // Prevent session fixation

        // Store user information in the session.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['display_user_id'] = $user['display_user_id'];
        $_SESSION['loggedin_time'] = time();

        // --- IP Tracking ---
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_id = $user['id'];
        $stmt_ip = $conn->prepare("INSERT INTO ip_tracking (user_id, ip_address) VALUES (?, ?)");
        if($stmt_ip) {
            $stmt_ip->bind_param("is", $user_id, $ip_address);
            $stmt_ip->execute();
            $stmt_ip->close();
        }
        // --- End IP Tracking ---

        // Redirect to the appropriate dashboard in the parent directory
        switch ($user['role']) {
            case 'admin':
                header("Location: ../admin/dashboard");
                break;
            case 'doctor':
                header("Location: ../doctor/dashboard");
                break;
            case 'staff':
                header("Location: ../staff/dashboard");
                break;
            case 'user':
                header("Location: ../user/dashboard");
                break;
            default:
                header("Location: ../index.php"); // Fallback
                break;
        }
        exit();

    } else {
        // Invalid Password
        $_SESSION['login_error'] = "Invalid credentials. Please check your details and try again.";
        header("Location: index.php");
        exit();
    }
} else {
    // User Not Found
    $_SESSION['login_error'] = "Invalid credentials. Please check your details and try again.";
    header("Location: index.php");
    exit();
}

?>