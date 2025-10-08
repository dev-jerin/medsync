<?php

// --- Database Configuration ---
$dbhost = 'localhost'; 
$dbuser = 'root';      
$dbpass = '';         
$db = 'medsync';       

// --- Establish Database Connection using mysqli ---
$conn = new mysqli($dbhost, $dbuser, $dbpass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set the character set to utf8mb4 to support a wide range of characters.
$conn->set_charset("utf8mb4");

// --- IP Block Check ---
$user_ip = $_SERVER['REMOTE_ADDR'];
$conn_check = new mysqli($dbhost, $dbuser, $dbpass, $db); 
if (!$conn_check->connect_error) {
    $stmt_block = $conn_check->prepare("SELECT id FROM ip_blocks WHERE ip_address = ?");
    if ($stmt_block) {
        $stmt_block->bind_param("s", $user_ip);
        $stmt_block->execute();
        $stmt_block->store_result();
        if ($stmt_block->num_rows > 0) {
            http_response_code(403);
            header("Location: /error/403.php");
            exit("Access Denied.");
        }
        $stmt_block->close();
    }
    $conn_check->close();
}
// --- End IP Block Check ---

// Function to get the database connection
function getDbConnection() {
    global $conn;
    return $conn;
}


// --- Session and Security Initialization ---

// Start the session if it's not already started.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generate a CSRF (Cross-Site Request Forgery) token if one doesn't already exist in the session.
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
    $stmt = $conn->prepare("SELECT setting_value  FROM system_settings WHERE setting_key = ?");
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

// --- Global Error Logging Setup ---
function customErrorHandler($severity, $message, $file, $line) {
    $log_file = __DIR__ . '/log.txt';
    $datetime = date("Y-m-d H:i:s");
    $error_message = "[$datetime] " .
                     "Severity: $severity | " .
                     "Message: $message | " .
                     "File: $file | " .
                     "Line: $line" . PHP_EOL;

    error_log($error_message, 3, $log_file);
}

// Register our custom function as the default error handler for the application.
set_error_handler("customErrorHandler");

// Include reCAPTCHA configuration
require_once __DIR__ . '/_private/recaptcha_config.php';
?>