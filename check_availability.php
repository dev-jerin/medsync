<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_POST['username'])) {
    $username = trim($_POST['username']);
    $response = ['available' => false, 'message' => ''];

    // Rule: no symbols except underscores and dots
    if (preg_match('/[^\w.]/', $username)) {
        $response['message'] = 'Username can only contain letters, numbers, underscores, and dots.';
        echo json_encode($response);
        exit();
    }

    // Rule: not matching Uxxxx, Axxxx, Sxxxx, Dxxxx
    if (preg_match('/^(u|a|s|d)\d{4}$/i', $username)) {
        $response['message'] = 'This username format is reserved.';
        echo json_encode($response);
        exit();
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response['message'] = 'Username is already taken.';
    } else {
        $response['available'] = true;
        $response['message'] = 'Username is available.';
    }
    $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit();
}

if (isset($_POST['email'])) {
    $email = trim($_POST['email']);
    $response = ['available' => false, 'message' => ''];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email format.';
        echo json_encode($response);
        exit();
    }

    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $response['message'] = 'Email is already registered.';
    } else {
        $response['available'] = true;
        $response['message'] = 'Email is available.';
    }
    $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit();
}
?>