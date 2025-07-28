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

// --- NEW: Global Error Logging Setup ---

/**
 * Custom error handler to log detailed errors to a file.
 * This function will capture PHP errors, format them, and save them.
 *
 * @param int $severity The level of the error raised.
 * @param string $message The error message.
 * @param string $file The filename that the error was raised in.
 * @param int $line The line number the error was raised at.
 * @return void
 */
function customErrorHandler($severity, $message, $file, $line) {
    // Path to your log file.
    $log_file = __DIR__ . '/log.txt';

    // Get the current date and time.
    $datetime = date("Y-m-d H:i:s");

    // Format the error message.
    $error_message = "[$datetime] " .
                     "Severity: $severity | " .
                     "Message: $message | " .
                     "File: $file | " .
                     "Line: $line" . PHP_EOL; // PHP_EOL adds a newline character.

    // Use error_log() to append the message to the specified file.
    // The '3' means the message is appended to the destination file.
    error_log($error_message, 3, $log_file);

    // Optional: If you want to stop the script on certain errors, you can add logic here.
    // For a production environment, you generally don't want to display errors to the user.
}

// Register our custom function as the default error handler for the application.
set_error_handler("customErrorHandler");

// --- IMPORTANT: Production Server Recommendations ---
// On a live server, you should disable the display of errors to the user for security.
// The errors will still be logged to your log.txt file.
// To do this, you would add these lines:
// ini_set('display_errors', 0);
// ini_set('log_errors', 1);
// error_reporting(E_ALL);

?>