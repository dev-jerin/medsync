<?php
/**
 * MedSync User/Patient Logic (user/user.php)
 *
 * Handles backend logic for the patient dashboard.
 * - Enforces session security and role-based access control.
 * - Initializes session variables for the frontend.
 * - Manages session timeout for security.
 */

require_once 'config.php';

// 2. Verify that the user has the correct role ('user').
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    session_unset();
    session_destroy();
    header("Location: login.php?error=unauthorized");
    exit();
}

// 3. Implement session timeout.
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?error=session_expired");
    exit();
}

// Update the 'loggedin_time' to reset the timeout counter.
$_SESSION['loggedin_time'] = time();


// --- Prepare Variables for Frontend ---

// Sanitize user details to prevent XSS.
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';
$display_user_id = isset($_SESSION['display_user_id']) ? htmlspecialchars($_SESSION['display_user_id']) : 'N/A';

// Dynamic Greeting based on time of day.
date_default_timezone_set('Asia/Kolkata'); // Set timezone
$current_hour = date('G');
if ($current_hour < 12) {
    $greeting = "Good Morning";
} elseif ($current_hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

?>