<?php
// Start the session to access session variables like the CSRF token
session_start();

// --- 1. Security & Validation ---

// Check if the request method is POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // If not, set an error message and redirect
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Invalid request method.'];
    header("Location: index.php#contact");
    exit();
}

// Validate the CSRF token to prevent cross-site request forgery
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    // Token is invalid or missing
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Invalid security token. Please try again.'];
    header("Location: index.php#contact");
    exit();
}

// Check if the required fields are filled
if (empty(trim($_POST["name"])) || empty(trim($_POST["phone"]))) {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Please fill in both your name and phone number.'];
    header("Location: index.php#contact");
    exit();
}


// --- 2. Sanitize and Process Data ---

// Sanitize user input to prevent XSS attacks
$name = htmlspecialchars(strip_tags(trim($_POST["name"])));

// Sanitize phone number: remove all characters except digits and the '+' sign
$phone = preg_replace('/[^-0-9+]/', '', trim($_POST["phone"]));

// Optional: Further validate the phone number format (e.g., must be at least 10 digits)
if (strlen(preg_replace('/[^0-9]/', '', $phone)) < 10) {
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Please enter a valid phone number.'];
    header("Location: index.php#contact");
    exit();
}


// --- 3. Handle the Callback Request ---

/*
 * In a real-world application, you would now:
 * 1. Store the request in a database.
 * - Example: $stmt = $pdo->prepare("INSERT INTO callback_requests (name, phone, requested_at) VALUES (?, ?, NOW())");
 * - $stmt->execute([$name, $phone]);
 * 2. Send an email or SMS notification to the administrative staff.
 * - Example: mail("admin@calystahealth.com", "New Callback Request", "Name: $name\nPhone: $phone");
 * 3. Maybe send a confirmation to the user.
 */

// For this example, we'll simulate success and set a success message.
$isSuccessful = true; // Assume the database insertion and notification worked.

if ($isSuccessful) {
    $_SESSION['callback_message'] = ['type' => 'success', 'text' => 'Thank you! We have received your request and will call you back shortly.'];
} else {
    // This would be triggered if the database insertion or email failed
    $_SESSION['callback_message'] = ['type' => 'error', 'text' => 'Something went wrong. Please try again later or contact us directly.'];
}

// Regenerate CSRF token after successful submission to prevent reuse
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));


// --- 4. Redirect Back ---

// Redirect the user back to the contact section on the main page
header("Location: index.php#contact");
exit();

?>