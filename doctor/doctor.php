<?php
/**
 * MedSync Doctor Logic (doctor.php)
 *
 * This script handles the backend logic for the doctor's dashboard.
 * - It enforces session security, ensuring only authenticated users with the 'doctor' role can access it.
 * - It initializes session variables required by the dashboard's frontend.
 * - It should be included by the main dashboard file.
 */
require_once 'config.php';
// It's recommended to have a central configuration file for session_start() and other settings.
// For now, we'll start the session here.

//checks if user is logedin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('doctor').
if ($_SESSION['role'] !== 'doctor') {
    // If the role is incorrect, destroy the session as a security measure
    // and redirect to the login page with an error.
    session_destroy();
    // The path is relative to the file that includes this script (doctor_dashboard.php)
    header("Location: login.php?error=unauthorized");
    exit();
}

// 3. Implement a session timeout to automatically log out inactive users.
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    // The path is relative to the file that includes this script (doctor_dashboard.php)
    header("Location: login.php?session_expired=true");
    exit();
}
// If the session is active, update the 'loggedin_time' to reset the timeout timer.
$_SESSION['loggedin_time'] = time();

// --- Prepare Variables for Frontend ---
// Fetch user details from the session. Use htmlspecialchars to prevent XSS attacks.
$username = htmlspecialchars($_SESSION['username']);
$display_user_id = htmlspecialchars($_SESSION['display_user_id']);

?>
