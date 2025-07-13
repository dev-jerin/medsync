<?php
// --- CONFIG & SESSION START ---
require_once '../config.php'; 

// --- SESSION SECURITY & ROLE CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    session_destroy();
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// --- API LOGIC ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $conn = getDbConnection();
    $admin_id = $_SESSION['user_id'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'updateProfile') {
            if (empty($_POST['name']) || empty($_POST['email'])) {
                throw new Exception('Name and Email are required.');
            }
            $name = $_POST['name'];
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            if (!$email) throw new Exception('Invalid email format.');
            $phone = $_POST['phone'];

            $sql_parts = ["name = ?", "email = ?", "phone = ?"];
            $params = [$name, $email, $phone];
            $types = "sss";

            if (!empty($_POST['password'])) {
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql_parts[] = "password = ?";
                $params[] = $hashed_password;
                $types .= "s";
            }

            $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
            $params[] = $admin_id;
            $types .= "i";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $_SESSION['username'] = $name; 
                $response = ['success' => true, 'message' => 'Your profile has been updated.'];
            } else {
                throw new Exception('Failed to update your profile.');
            }
        } else {
            throw new Exception('Invalid profile action.');
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $fetch_target = $_GET['fetch'] ?? '';
        if ($fetch_target === 'my_profile') {
            $stmt = $conn->prepare("SELECT name, email, phone, username FROM users WHERE id = ?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();
            $response = ['success' => true, 'data' => $data];
        } else {
            throw new Exception('Invalid profile fetch request.');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
