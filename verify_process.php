<?php
/**
 * Processes the OTP submitted by the user.
 * If the OTP is correct and not expired, it creates the user account in the database.
 * Generates a display_user_id in the format 'Uxxxx'.
 */

require_once 'config.php';

// --- Security & Session Checks ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['verify_error'] = "Invalid request method.";
    header("Location: verify_otp.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['verify_error'] = "CSRF validation failed. Please try again.";
    header("Location: verify_otp.php");
    exit();
}
if (!isset($_SESSION['registration_data'])) {
    header("Location: register.php");
    exit();
}

// --- OTP Validation ---
$submitted_otp = trim($_POST['otp']);
$session_data = $_SESSION['registration_data'];

// 1. Check if the submitted OTP matches the one in the session.
if ($submitted_otp != $session_data['otp']) {
    $_SESSION['verify_error'] = "Invalid OTP. Please try again.";
    header("Location: verify_otp.php");
    exit();
}

// 2. Check if the OTP has expired (10 minutes / 600 seconds).
$otp_expiry_time = 600; 
if (time() - $session_data['timestamp'] > $otp_expiry_time) {
    $_SESSION['verify_error'] = "OTP has expired. Please start the registration process again.";
    unset($_SESSION['registration_data']);
    header("Location: register.php");
    exit();
}


// --- Database Insertion (Final Step) ---
// **IMPORTANT**: Assumes your `users` table has columns: `name`, `date_of_birth`, `gender`.
// You must add these columns to your database table.
$conn = getDbConnection();

$sql_insert = "INSERT INTO users (username, name, email, password, role, date_of_birth, gender, display_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);

$display_user_id_placeholder = 'TEMP'; // Temporary placeholder

// Bind parameters from the session data
$stmt_insert->bind_param(
    "ssssssss",
    $session_data['username'],
    $session_data['name'],
    $session_data['email'],
    $session_data['password'],
    $session_data['role'],
    $session_data['date_of_birth'],
    $session_data['gender'],
    $display_user_id_placeholder
);

// Execute the statement
if ($stmt_insert->execute()) {
    // Get the auto-incremented `id` of the new user.
    $last_id = $stmt_insert->insert_id;
    
    // Generate the display_user_id (e.g., U0001, U0002).
    $display_user_id = 'U' . str_pad($last_id, 4, '0', STR_PAD_LEFT);
    
    // Update the record with the correct display_user_id.
    $sql_update_id = "UPDATE users SET display_user_id = ? WHERE id = ?";
    $stmt_update_id = $conn->prepare($sql_update_id);
    $stmt_update_id->bind_param("si", $display_user_id, $last_id);
    $stmt_update_id->execute();
    $stmt_update_id->close();

    // Clean up the session
    unset($_SESSION['registration_data']);
    
    // Set success message and redirect to the login page
    $_SESSION['register_success'] = "Registration successful! Your User ID is " . $display_user_id . ". You can now log in.";
    header("Location: login.php");
    exit();
} else {
    // If insertion fails, redirect with an error.
    $_SESSION['verify_error'] = "Database error: Could not create account. Please try again later.";
    header("Location: verify_otp.php");
    exit();
}

$stmt_insert->close();
$conn->close();

?>