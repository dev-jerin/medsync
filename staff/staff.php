<?php
/**
 * MedSync Staff Logic (staff.php)
 *
 * This script handles the backend logic for the staff dashboard.
 * - It enforces session security, ensuring only authenticated users with the 'staff' role can access it.
 * - It initializes session variables required by the dashboard's frontend.
 * - It should be included by the main staff_dashboard.php file.
 */

// It's recommended to have a central configuration file for session_start() and other settings.
// For now, we'll start the session here.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Development Only: Simulate a logged-in user ---
// This block should be removed or commented out in a production environment.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'S001';
    $_SESSION['role'] = 'staff';
    $_SESSION['username'] = 'Alice Johnson';
    $_SESSION['display_user_id'] = 'MED-STF-001';
    $_SESSION['loggedin_time'] = time();
}
// --- End of Development Only Block ---


// 1. Check if a user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    // The path is relative to the file that includes this script (staff_dashboard.php)
    header("Location: ../login.php");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('staff').
if ($_SESSION['role'] !== 'staff') {
    // If the role is incorrect, destroy the session as a security measure
    // and redirect to the login page with an error.
    session_destroy();
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// 3. Implement a session timeout to automatically log out inactive users.
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    header("Location: ../login.php?session_expired=true");
    exit();
}
// If the session is active, update the 'loggedin_time' to reset the timeout timer.
$_SESSION['loggedin_time'] = time();

// --- Prepare Variables for Frontend ---
// Fetch user details from the session. Use htmlspecialchars to prevent XSS attacks.
$username = htmlspecialchars($_SESSION['username']);
$display_user_id = htmlspecialchars($_SESSION['display_user_id']);

?>