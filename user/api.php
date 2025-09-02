<?php
/**
 * MedSync User/Patient Logic (user/api.php)
 *
 * Handles backend logic for the patient dashboard shell.
 * - Enforces session security and role-based access control.
 * - Initializes session variables for the frontend header and profile.
 * - Manages session timeout for security.
 * - Fetches complete user data for the profile page.
 * - Fetches the count of unread notifications for the header badge.
 */

require_once '../config.php'; // Includes session_start() and database connection ($conn)

// 1. Verify that the user has the correct role ('user').
// Based on the project abstract, the role for patients is 'user'.
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    session_unset();
    session_destroy();
    header("Location: ../login/index.php?error=unauthorized");
    exit();
}

// 2. Implement session timeout.
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: ../login/index.php?error=session_expired");
    exit();
}
// Update the 'loggedin_time' to reset the timeout counter.
$_SESSION['loggedin_time'] = time();


// --- Fetch Complete User Details for Profile & Dashboard ---

$user_details = []; // Initialize an empty array to hold user data.
$user_id = null;
// We assume 'user_id' is stored in the session upon login.
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Prepare a statement to prevent SQL injection.
    // Fetching data from the `users` table as defined in `medsync.sql`.
    $stmt = $conn->prepare("SELECT name, email, phone, date_of_birth, gender, profile_picture FROM users WHERE id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_details = $result->fetch_assoc();
        } else {
            // If the user_id in session doesn't exist in DB, force logout.
            session_unset();
            session_destroy();
            header("Location: ../login/index.php?error=user_not_found");
            exit();
        }
        $stmt->close();
    } else {
        // Handle potential DB errors
        error_log("Database statement preparation failed: " . $conn->error);
        die("An internal error occurred. Please try again later.");
    }
} else {
    // If user_id is not in session, something is wrong. Force logout.
    session_unset();
    session_destroy();
    header("Location: ../login/index.php?error=invalid_session");
    exit();
}

// --- Fetch Unread Notification Count ---

$unread_notification_count = 0; // Initialize count
if ($user_id) {
    // This query counts unread notifications for the current user from the `notifications` table.
    $stmt_count = $conn->prepare("SELECT COUNT(id) as unread_count FROM notifications WHERE recipient_user_id = ? AND is_read = 0");
    if ($stmt_count) {
        $stmt_count->bind_param("i", $user_id);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        if($row = $result_count->fetch_assoc()) {
            $unread_notification_count = $row['unread_count'];
        }
        $stmt_count->close();
    } else {
         error_log("Database statement preparation failed for notification count: " . $conn->error);
    }
}


// --- Prepare Basic Variables for Frontend ---

// Sanitize user details for direct display to prevent XSS.
$username = isset($user_details['name']) && !empty($user_details['name']) ? htmlspecialchars($user_details['name']) : 'User';
$display_user_id = isset($_SESSION['display_user_id']) ? htmlspecialchars($_SESSION['display_user_id']) : 'N/A';
$profile_picture = isset($user_details['profile_picture']) ? htmlspecialchars($user_details['profile_picture']) : 'default.png';

// Dynamic Greeting based on time of day.
// Set timezone to India Standard Time.
date_default_timezone_set('Asia/Kolkata'); 
$current_hour = date('G');

if ($current_hour < 12) {
    $greeting = "Good Morning";
} elseif ($current_hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

?>