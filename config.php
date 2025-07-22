<?php
/**
 * Configuration file for the MedSync application.
 * Contains database connection settings and initializes the session.
 */

// --- Database Configuration ---
$dbhost = 'localhost'; // Your database host (e.g., 'localhost' or an IP address)
$dbuser = 'root';      // Your database username
$dbpass = '';          // Your database password
$db = 'medsync';       // The name of your database

// --- Establish Database Connection using mysqli ---
$conn = new mysqli($dbhost, $dbuser, $dbpass, $db);

// Check for a connection error. If it exists, kill the script and show the error.
if ($conn->connect_error) {
    // In a production environment, you might want to log this error instead of displaying it.
    die("Connection failed: " . $conn->connect_error);
}

// Set the character set to utf8mb4 to support a wide range of characters.
$conn->set_charset("utf8mb4");

/**
 * A global function to get the database connection object.
 * This can be used throughout the application to access the connection.
 * @return mysqli The database connection object.
 */
function getDbConnection() {
    global $conn;
    return $conn;
}


// --- Session and Security Initialization ---

// Start the session if it's not already started.
// This is necessary for storing user login state and CSRF tokens.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate a CSRF (Cross-Site Request Forgery) token if one doesn't already exist in the session.
// This token should be included in all forms to prevent CSRF attacks.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Fetches a specific setting value from the system_settings table.
 * @param mysqli $conn The database connection object.
 * @param string $setting_key The key of the setting to retrieve.
 * @return string|null The value of the setting or null if not found.
 */
function get_system_setting($conn, $setting_key) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if (!$stmt) {
        error_log("Failed to prepare statement for get_system_setting: " . $conn->error);
        return null;
    }
    $stmt->bind_param("s", $setting_key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return null;
}

?>
