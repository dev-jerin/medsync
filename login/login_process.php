<?php
/**
 * Processes the login form submission.
 * Handles user authentication, session creation, and redirection.
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
    header("Location: ../login.php");
    exit();
}

// --- Form Data Retrieval ---
$login_identifier = trim($_POST['username']); // Can be username, email, or user_id
$password = $_POST['password'];

// --- Basic Validation ---
if (empty($login_identifier) || empty($password)) {
    $_SESSION['login_error'] = "All fields are required.";
    header("Location: ../login.php");
    exit();
}

// --- Database Authentication ---
$conn = getDbConnection();

// Prepare a statement to fetch user data by joining the users and roles tables.
// This query is updated for the new schema.
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
                'text' => 'Your account is currently inactive. Please contact support for assistance.'
            ];
            header("Location: ../login.php");
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

        // Redirect to the appropriate dashboard in the parent directory
        switch ($user['role']) {
            case 'admin':
                header("Location: ../admin/dashboard.php");
                break;
            case 'doctor':
                header("Location: ../doctor/dashboard.php");
                break;
            case 'staff':
                header("Location: ../staff/dashboard.php");
                break;
            case 'user':
                header("Location: ../user/dashboard.php");
                break;
            default:
                header("Location: ../index.php"); // Fallback
                break;
        }
        exit();

    } else {
        // Invalid Password
        $_SESSION['login_error'] = "Invalid credentials. Please check your details and try again.";
        header("Location: ../login.php");
        exit();
    }
} else {
    // User Not Found
    $_SESSION['login_error'] = "Invalid credentials. Please check your details and try again.";
    header("Location: ../login.php");
    exit();
}

$stmt->close();
$conn->close();