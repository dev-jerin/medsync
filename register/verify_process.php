<?php
/**
 * Processes OTP, creates user, saves profile picture, and sends welcome email.
 */

// --- Includes and Usings ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Correctly include the Composer autoloader to load PHPMailer classes
require '../vendor/autoload.php'; 

// Include your existing configuration and email template files
require_once '../config.php';
require_once __DIR__ . '/../mail/templates.php';

/**
 * Generates a unique, sequential display ID for a new user.
 *
 * @param string $role The role of the user.
 * @param mysqli $conn The database connection object.
 * @return string The generated display ID.
 * @throws Exception If an invalid role is provided or a database error occurs.
 */
function generateDisplayId($role, $conn) {
    $prefix_map = ['admin' => 'A', 'doctor' => 'D', 'staff' => 'S', 'user' => 'U'];
    if (!isset($prefix_map[$role])) {
        throw new Exception("Invalid role specified for ID generation.");
    }
    $prefix = $prefix_map[$role];

    $conn->begin_transaction();
    try {
        // Ensure the counter exists before trying to lock it
        $init_stmt = $conn->prepare("INSERT INTO role_counters (role_prefix, last_id) VALUES (?, 0) ON DUPLICATE KEY UPDATE role_prefix = role_prefix");
        $init_stmt->bind_param("s", $prefix);
        $init_stmt->execute();
        $init_stmt->close();
        
        // Lock the row for the specific role to prevent race conditions
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $row = $result->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;
        
        // Update the counter
        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();
        
        $conn->commit();
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $conn->rollback();
        // Log the actual database error for debugging, but don't expose it to the user.
        error_log("ID Generation Failed: " . $e->getMessage());
        throw new Exception("Could not generate a new user ID. Please try again later.");
    }
}


// --- Security & Session Checks ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") { 
    // Silently exit if not a POST request
    exit(); 
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Silently exit on CSRF failure
    exit(); 
}
if (!isset($_SESSION['registration_data'])) {
    // Redirect if the registration process hasn't been started
    header("Location: index.php");
    exit();
}


// --- OTP Validation ---
$submitted_otp = trim($_POST['otp']);
$session_data = $_SESSION['registration_data'];

// Check for OTP expiry (10 minutes)
if (time() - $session_data['timestamp'] > 600) {
    $_SESSION['verify_error'] = "Your OTP has expired. Please register again.";
    // Clear expired data
    unset($_SESSION['registration_data']);
    header("Location: index.php");
    exit();
}

if ($submitted_otp != $session_data['otp']) {
    $_SESSION['verify_error'] = "Invalid OTP. Please try again.";
    header("Location: ../register/verify_otp.php");
    exit();
}

// --- Database Insertion ---
$conn = getDbConnection();

try {
    $role_name = $session_data['role'] ?? 'user';
    $role_id = 1; 

    $display_user_id = generateDisplayId($role_name, $conn);

    $sql_insert = "INSERT INTO users (display_user_id, username, name, email, phone, password, role_id, date_of_birth, gender, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);
    
    $stmt_insert->bind_param(
        "ssssssisss",
        $display_user_id,
        $session_data['username'],
        $session_data['name'],
        $session_data['email'],
        $session_data['phone'],
        $session_data['password'],
        $role_id,
        $session_data['date_of_birth'],
        $session_data['gender'],
        $session_data['profile_picture']
    );

    if ($stmt_insert->execute()) {
        // --- Send Welcome Email using Centralized Function ---
        require_once __DIR__ . '/../mail/send_mail.php';
        try {
            $subject = 'Welcome to MedSync, ' . $session_data['name'] . '!';
            $body = getWelcomeEmailTemplate(
                $session_data['name'],
                $session_data['username'],
                $display_user_id
            );

            // The 'from' name is "MedSync Welcome Team"
            send_mail('MedSync Welcome Team', $session_data['email'], $subject, $body);

        } catch (Exception $e) {
            // Log the email error but don't interrupt the user's successful registration
            error_log("Welcome email could not be sent to {$session_data['email']}. Error: {$e->getMessage()}");
        }

        // Clean up session and redirect to login with a success message
        unset($_SESSION['registration_data']);
        $_SESSION['register_success'] = "Registration successful! Your User ID is " . $display_user_id . ". You can now log in.";
        header("Location: ../login");
        exit();

    } else {
        // Handle specific database errors, like duplicate entry
        if ($conn->errno == 1062) { // Duplicate entry error code
             throw new Exception("An account with this username or email already exists.");
        }
        throw new Exception("Database error: Could not create your account at this time.");
    }
} catch (Exception $e) {
    // If user creation fails, delete the uploaded profile picture to clean up
    if (isset($session_data['profile_picture']) && $session_data['profile_picture'] !== 'default.png') {
        $file_to_delete = '../uploads/profile_pictures/' . $session_data['profile_picture'];
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
    }
    // Set a user-friendly error message and redirect
    $_SESSION['verify_error'] = "An error occurred during registration: " . $e->getMessage();
    header("Location: ../register/verify_otp.php");
    exit();
} finally {
    if (isset($stmt_insert)) $stmt_insert->close();
    $conn->close();
}
?>