<?php
// Include the database configuration and start the session
require_once 'config.php';

// --- 1. Security & Validation ---

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Invalid request method.'];
    header("Location: index.php#contact");
    exit();
}

// Validate the CSRF token
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
    header("Location: index.php#contact");
    exit();
}

// Check for empty fields
if (empty(trim($_POST["name"])) || empty(trim($_POST["phone"]))) {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Please fill in both your name and phone number.'];
    header("Location: index.php#contact");
    exit();
}


// --- 2. Sanitize and Process Data ---

$name = htmlspecialchars(strip_tags(trim($_POST["name"])));
$phone = preg_replace('/[^-0-9+]/', '', trim($_POST["phone"]));

// Optional: Further validate the phone number format
if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 10) {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Please enter a valid phone number.'];
    header("Location: index.php#contact");
    exit();
}


// --- 3. Handle the Callback Request (Database Insertion) ---

$isSuccessful = false;
$conn = getDbConnection(); // Get the database connection from config.php

// Prepare the SQL statement to prevent SQL injection
$stmt = $conn->prepare("INSERT INTO callback_requests (name, phone) VALUES (?, ?)");
if ($stmt) {
    // Bind the variables to the prepared statement as parameters
    $stmt->bind_param("ss", $name, $phone);

    // Execute the statement
    if ($stmt->execute()) {
        $isSuccessful = true;
    }
    // Close the statement
    $stmt->close();
}

// Close the database connection
$conn->close();


if ($isSuccessful) {
    $_SESSION['callback_message'] = ['type' => 'success', 'text' => 'Thank you! We have received your request and will call you back shortly.'];
    // Regenerate CSRF token after successful submission
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} else {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Something went wrong. Please try again later.'];
}


// --- 4. Redirect Back ---
header("Location: index.php#contact");
exit();

?>