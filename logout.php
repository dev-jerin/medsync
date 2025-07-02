<?php
/**
 * Handles user logout.
 * Destroys the session securely and redirects to the login page.
 */

// Start the session to access session variables.
session_start();

// 1. Unset all of the session variables.
$_SESSION = array();

// 2. If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. Finally, destroy the session.
session_destroy();

// 4. Redirect the user to the login page.
// We can add a parameter to show a "logged out" message if desired.
header("Location: index.php?logged_out=true");
exit();
?>
