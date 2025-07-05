<?php
/**
 * Processes the login form submission.
 * Handles user authentication, session creation, and redirection.
 */

// Include the database configuration file.
require_once 'config.php';

// --- Security Check: Ensure the request is a POST request ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

// --- Security Check: Validate CSRF Token ---
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['login_error'] = "Invalid session. Please try logging in again.";
    header("Location: login.php");
    exit();
}

// --- Form Data Retrieval ---
$login_identifier = trim($_POST['username']); // Can be username, email, or user_id
$password = $_POST['password'];

// --- Basic Validation ---
if (empty($login_identifier) || empty($password)) {
    $_SESSION['login_error'] = "Username/Email/User ID and password are required.";
    header("Location: login.php");
    exit();
}


// --- Database Authentication ---
$conn = getDbConnection();

// Prepare a statement to fetch user data based on username, email, OR display_user_id.
// This prevents SQL injection.
$sql = "SELECT id, username, password, role, display_user_id FROM users WHERE username = ? OR display_user_id = ? OR email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
// Bind the same identifier to all three placeholders
$stmt->bind_param("sss", $login_identifier, $login_identifier, $login_identifier);
$stmt->execute();
$result = $stmt->get_result();

// Check if a user exists.
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    // --- Verify Password ---
    if (password_verify($password, $user['password'])) {
        
        // --- Login Successful ---

        // Regenerate session ID to prevent session fixation.
        session_regenerate_id(true);

        // Store user information in the session.
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['display_user_id'] = $user['display_user_id'];
        $_SESSION['loggedin_time'] = time();

        // --- Redirect to the appropriate dashboard ---
        switch ($user['role']) {
            case 'admin':
                header("Location: admin_dashboard.php");
                break;
            case 'doctor':
                header("Location: doctor_dashboard.php");
                break;
            case 'staff':
                header("Location: staff_dashboard.php");
                break;
            case 'user':
                header("Location: user_dashboard.php");
                break;
            default:
                header("Location: index.php");
                break;
        }
        exit();

    } else {
        // --- Invalid Password ---
        $_SESSION['login_error'] = "Invalid credentials. Please try again.";
        header("Location: login.php");
        exit();
    }
} else {
    // --- User Not Found ---
    $_SESSION['login_error'] = "Invalid credentials. Please try again.";
    header("Location: login.php");
    exit();
}

// Close the statement and connection.
$stmt->close();
$conn->close();

?>
