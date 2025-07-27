<?php
/**
 * MedSync Staff Logic (staff.php)
 *
 * This script handles the backend logic for the staff dashboard.
 * - Enforces session security and role-based access.
 * - Initializes session variables for the frontend.
 * - Handles AJAX API requests for staff functionalities.
 */

// config.php should be included first to initialize the session and db connection.
require_once '../config.php';

// --- AJAX API Endpoint Logic ---
// This block handles all AJAX requests from the staff dashboard script.
if (isset($_GET['fetch']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];
    
    // Ensure a valid staff session exists for any API action
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Unauthorized access.';
        echo json_encode($response);
        exit();
    }
    
    $conn = getDbConnection();

    try {
        if (isset($_GET['fetch'])) {
            if ($_GET['fetch'] === 'callbacks') {
                // CORRECTED SQL QUERY
                $sql = "SELECT id, name, phone, created_at, is_contacted FROM callback_requests ORDER BY created_at DESC";
                $result = $conn->query($sql);
                if (!$result) {
                    throw new Exception("Database query failed: " . $conn->error);
                }
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $response = ['success' => true, 'data' => $data];
            }
        } elseif (isset($_POST['action'])) {
            // CSRF Token validation for all POST actions
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                throw new Exception('Invalid security token. Please refresh the page.');
            }

            if ($_POST['action'] === 'markCallbackContacted') {
                if (empty($_POST['id'])) {
                    throw new Exception('Callback request ID is required.');
                }
                $callback_id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE callback_requests SET is_contacted = 1 WHERE id = ?");
                $stmt->bind_param("i", $callback_id);
                if ($stmt->execute()) {
                    $response = ['success' => true, 'message' => 'Request marked as contacted.'];
                } else {
                    throw new Exception('Database update failed.');
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        $response['message'] = $e->getMessage();
    }
    
    $conn->close();
    echo json_encode($response);
    exit(); // Stop script execution after handling API request
}


// --- Standard Page Load Security & Session Management ---

// 1. Check if a user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?error=not_loggedin");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('staff').
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    // Destroy the session as a security measure for incorrect role access.
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=unauthorized");
    exit();
}

// 3. Implement session timeout to automatically log out inactive users.
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}
// If the session is active, update the 'loggedin_time' to reset the timeout timer.
$_SESSION['loggedin_time'] = time();

// --- Prepare Variables for Frontend ---
// Fetch user details from the session. Use htmlspecialchars to prevent XSS attacks.
$username = htmlspecialchars($_SESSION['username']);
$display_user_id = htmlspecialchars($_SESSION['display_user_id']);

?>