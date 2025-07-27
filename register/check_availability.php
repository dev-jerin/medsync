<?php
// Handles real-time checking for username and email availability.

require_once 'config.php';

// Set the content type to JSON for the response
header('Content-Type: application/json');

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['available' => false, 'message' => 'Invalid request method.']);
    exit();
}

$conn = getDbConnection();
$response = ['available' => false, 'message' => 'An error occurred.'];

// Check for username availability
if (isset($_POST['username'])) {
    $username = strtolower(trim($_POST['username']));
    
    // Rule: Username must be at least 3 characters
    if (strlen($username) < 3) {
        $response['message'] = 'Username must be at least 3 characters.';
    } 
    // Rule: no symbols except underscores and dots
    elseif (preg_match('/[^\w.]/', $username)) {
        $response['message'] = 'Username can only contain letters, numbers, underscores, and dots.';
    }
    // Rule: not matching Uxxxx, Axxxx, Sxxxx, Dxxxx
    elseif (preg_match('/^(u|a|s|d)\d{4}$/i', $username)) {
        $response['message'] = 'This username format is reserved.';
    }
    // If all validation rules pass, check the database for uniqueness
    else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $response['available'] = true;
            $response['message'] = 'Username is available.';
        } else {
            $response['message'] = 'Username is already taken.';
        }
        $stmt->close();
    }
}
// Check for email availability
elseif (isset($_POST['email'])) {
    $email = trim($_POST['email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 0) {
            $response['available'] = true;
            $response['message'] = 'Email is available.';
        } else {
            $response['message'] = 'Email is already registered.';
        }
        $stmt->close();
    }
} else {
    $response['message'] = 'No input provided.';
}

$conn->close();
echo json_encode($response);
?>