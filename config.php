<?php
/**
 * MedSync Configuration File
 *
 * Handles database connections, session security, environment variable loading,
 * global error handling, and system-wide constants.
 */

// Prevent direct access to scripts checking for this constant
define('CONFIG_LOADED', true);

// Load Composer autoloader and environment variables
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// -------------------------------------------------------------------------
// Database Configuration
// -------------------------------------------------------------------------

$dbhost = $_ENV['DB_HOST'] ?? 'localhost';
$dbuser = $_ENV['DB_USER'] ?? 'root';
$dbpass = $_ENV['DB_PASS'] ?? '';
$db     = $_ENV['DB_NAME'] ?? 'medsync';

// Initialize primary database connection
$conn = new mysqli($dbhost, $dbuser, $dbpass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for full Unicode support
$conn->set_charset("utf8mb4");

// -------------------------------------------------------------------------
// Security: IP Blocking
// -------------------------------------------------------------------------

// Check if the current user's IP is in the blocklist
$user_ip = $_SERVER['REMOTE_ADDR'];
$conn_check = new mysqli($dbhost, $dbuser, $dbpass, $db);

if (!$conn_check->connect_error) {
    $stmt_block = $conn_check->prepare("SELECT id FROM ip_blocks WHERE ip_address = ?");
    if ($stmt_block) {
        $stmt_block->bind_param("s", $user_ip);
        $stmt_block->execute();
        $stmt_block->store_result();

        // If IP is found in blocklist, deny access immediately
        if ($stmt_block->num_rows > 0) {
            http_response_code(403);
            // Ensure this path exists relative to where this file is included
            header("Location: /error/403.php");
            exit("Access Denied.");
        }
        $stmt_block->close();
    }
    $conn_check->close();
}

/**
 * Retrieves the global database connection.
 *
 * @return mysqli The active database connection instance.
 */
function getDbConnection() {
    global $conn;
    return $conn;
}

// -------------------------------------------------------------------------
// Session & Security Configuration
// -------------------------------------------------------------------------

if (session_status() == PHP_SESSION_NONE) {
    // Enforce secure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,             // Expire on browser close
        'path'     => '/',
        'domain'   => '',
        'secure'   => false,         // Set to true in production (HTTPS)
        'httponly' => true,          // Prevent JS access to session cookie
        'samesite' => 'Strict'       // mitigate CSRF
    ]);

    // Configure session settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_secure', '0'); // Update for production
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', '1');

    session_start();
}

// Initialize CSRF token if not present
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Retrieves a system setting value from the database.
 *
 * @param mysqli $conn The database connection object.
 * @param string $setting_key The key of the setting to retrieve.
 * @return string|null The setting value or null if not found/error.
 */
function get_system_setting($conn, $setting_key) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    if (!$stmt) {
        error_log("Database error in get_system_setting: " . $conn->error);
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

// -------------------------------------------------------------------------
// Error Handling
// -------------------------------------------------------------------------

/**
 * Custom error handler to log errors to a file.
 *
 * @param int    $severity The error level.
 * @param string $message  The error message.
 * @param string $file     The filename where the error occurred.
 * @param int    $line     The line number where the error occurred.
 */
function customErrorHandler($severity, $message, $file, $line) {
    $log_file = __DIR__ . '/log.txt';
    $datetime = date("Y-m-d H:i:s");
    $error_log_entry = "[$datetime] Severity: $severity | Message: $message | File: $file | Line: $line" . PHP_EOL;

    error_log($error_log_entry, 3, $log_file);
}

set_error_handler("customErrorHandler");

// -------------------------------------------------------------------------
// Global Constants
// -------------------------------------------------------------------------

define('RECAPTCHA_SITE_KEY', $_ENV['RECAPTCHA_SITE_KEY'] ?? '');
define('RECAPTCHA_SECRET_KEY', $_ENV['RECAPTCHA_SECRET_KEY'] ?? '');
define('CHATBOT_ID', $_ENV['CHATBOT_ID'] ?? '');

?>