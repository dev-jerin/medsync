<?php
/**
 * MedSync Staff Logic (staff.php)
 *
 * This script handles the backend logic for the staff dashboard.
 * - Enforces session security and role-based access.
 * - Initializes session variables and fetches user data for the frontend.
 * - Handles AJAX API requests for Profile Settings, Callback Requests, and Messenger.
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
        $response['message'] = 'Unauthorized access. Please log in again.';
        echo json_encode($response);
        exit();
    }
    
    $conn = getDbConnection();
    $user_id = $_SESSION['user_id'];
    $transaction_active = false; // Flag to track transaction state for safe rollback

    try {
        // Handle GET requests for fetching data
        if (isset($_GET['fetch'])) {
            switch ($_GET['fetch']) {
                case 'callbacks':
                    $stmt = $conn->prepare("SELECT id, name, phone, created_at, is_contacted FROM callback_requests ORDER BY created_at DESC");
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $data];
                    break;
                
                case 'conversations':
                    $stmt = $conn->prepare("
                        SELECT
                            c.id AS conversation_id,
                            u.id AS other_user_id,
                            u.display_user_id,
                            u.name AS other_user_name,
                            u.profile_picture AS other_user_profile_picture,
                            u.role AS other_user_role,
                            (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_time,
                            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND receiver_id = ? AND is_read = 0) AS unread_count
                        FROM conversations c
                        JOIN users u ON u.id = IF(c.user_one_id = ?, c.user_two_id, c.user_one_id)
                        WHERE c.user_one_id = ? OR c.user_two_id = ?
                        ORDER BY last_message_time DESC
                    ");
                    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
                    $stmt->execute();
                    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    // Process profile pictures to return a full URL
                    foreach ($conversations as &$conv) {
                        $default_avatar = '../uploads/profile_pictures/default.png';
                        $picture_filename = $conv['other_user_profile_picture'];
                        $potential_path_local = dirname(__DIR__) . '../uploads/profile_pictures/' . $picture_filename;
                        $potential_path_web = '../uploads/profile_pictures/' . $picture_filename;

                        if (!empty($picture_filename) && $picture_filename !== 'default.png' && file_exists($potential_path_local)) {
                            $conv['other_user_avatar_url'] = $potential_path_web;
                        } else {
                            $conv['other_user_avatar_url'] = $default_avatar;
                        }
                    }
                    unset($conv);

                    $response = ['success' => true, 'data' => $conversations];
                    break;

                case 'messages':
                    if (!isset($_GET['conversation_id'])) {
                        throw new Exception('Conversation ID is required.');
                    }
                    $conversation_id = (int)$_GET['conversation_id'];

                    // Authorize: check if user is part of the conversation
                    $auth_stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)");
                    $auth_stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
                    $auth_stmt->execute();
                    if ($auth_stmt->get_result()->num_rows === 0) {
                        http_response_code(403);
                        throw new Exception('You are not authorized to view this conversation.');
                    }
                    $auth_stmt->close();
                    
                    // Mark messages as read
                    $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?");
                    $update_stmt->bind_param("ii", $conversation_id, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    // Fetch messages
                    $msg_stmt = $conn->prepare("SELECT id, sender_id, message_text, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
                    $msg_stmt->bind_param("i", $conversation_id);
                    $msg_stmt->execute();
                    $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $msg_stmt->close();

                    $response = ['success' => true, 'data' => $messages];
                    break;
            }
        } 
        // Handle POST requests for performing actions
        elseif (isset($_POST['action'])) {
            // CSRF Token validation for all POST actions
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                http_response_code(403); // Forbidden
                throw new Exception('Invalid security token. Please refresh the page and try again.');
            }

            switch ($_POST['action']) {
                case 'updatePersonalInfo':
                    $conn->begin_transaction();
                    $transaction_active = true;

                    $name = trim($_POST['name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    $department = trim($_POST['department']);
                    $date_of_birth = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;


                    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($department)) {
                        throw new Exception('Invalid input. Please check all fields.');
                    }

                    // Check if email is already in use by another user
                    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt_check->bind_param("si", $email, $user_id);
                    $stmt_check->execute();
                    $email_exists = $stmt_check->get_result()->num_rows > 0;
                    $stmt_check->close();

                    if ($email_exists) {
                        throw new Exception('This email address is already in use by another account.');
                    }

                    // Update users table
                    $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, date_of_birth = ? WHERE id = ?");
                    $stmt_user->bind_param("ssssi", $name, $email, $phone, $date_of_birth, $user_id);
                    $user_updated = $stmt_user->execute();
                    $stmt_user->close();

                    // Update staff table
                    $stmt_staff = $conn->prepare("UPDATE staff SET assigned_department = ? WHERE user_id = ?");
                    $stmt_staff->bind_param("si", $department, $user_id);
                    $staff_updated = $stmt_staff->execute();
                    $stmt_staff->close();

                    if ($user_updated && $staff_updated) {
                        $conn->commit();
                        $transaction_active = false;
                        $_SESSION['username'] = $name; // Update session variable
                        $response = ['success' => true, 'message' => 'Personal information updated successfully.'];
                    } else {
                        throw new Exception('Database update failed. Please try again.');
                    }
                    break;

                case 'updatePassword':
                    // This logic remains unchanged
                    $current_password = $_POST['current_password'];
                    $new_password = $_POST['new_password'];
                    if ($new_password !== $_POST['confirm_password']) {
                        throw new Exception('New password and confirmation do not match.');
                    }
                    if (strlen($new_password) < 8) {
                        throw new Exception('New password must be at least 8 characters long.');
                    }

                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($result && password_verify($current_password, $result['password'])) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt_update->bind_param("si", $hashed_password, $user_id);
                        if ($stmt_update->execute()) {
                            $response = ['success' => true, 'message' => 'Password changed successfully.'];
                        } else {
                            throw new Exception('Failed to update password.');
                        }
                        $stmt_update->close();
                    } else {
                        throw new Exception('Incorrect current password.');
                    }
                    break;

                case 'updateProfilePicture':
                    // This logic remains unchanged
                    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/profile_pictures/';
                        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                             throw new Exception('Failed to create upload directory.');
                        }
                        
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        $file_mime_type = mime_content_type($_FILES['profile_picture']['tmp_name']);
                        if (!in_array($file_mime_type, $allowed_types)) {
                            throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
                        }
                        if ($_FILES['profile_picture']['size'] > 2097152) { // 2MB limit
                            throw new Exception('File is too large. Maximum size is 2MB.');
                        }

                        $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                        $new_filename = 'staff_' . $user_id . '_' . time() . '.' . $file_extension;
                        
                        $stmt_select = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                        $stmt_select->bind_param("i", $user_id);
                        $stmt_select->execute();
                        $old_picture_filename = $stmt_select->get_result()->fetch_assoc()['profile_picture'];
                        $stmt_select->close();

                        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                            $stmt_update = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                            $stmt_update->bind_param("si", $new_filename, $user_id);
                            if ($stmt_update->execute()) {
                                if ($old_picture_filename && $old_picture_filename !== 'default.png' && file_exists($upload_dir . $old_picture_filename)) {
                                    unlink($upload_dir . $old_picture_filename);
                                }
                                $response = ['success' => true, 'message' => 'Profile picture updated.', 'new_image_url' => '../uploads/profile_pictures/' . $new_filename];
                            } else {
                                unlink($upload_dir . $new_filename);
                                throw new Exception('Database update failed.');
                            }
                            $stmt_update->close();
                        } else {
                            throw new Exception('Failed to move uploaded file.');
                        }
                    } else {
                        $error_code = $_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE;
                        throw new Exception('File upload error code: ' . $error_code);
                    }
                    break;
                
                case 'markCallbackContacted':
                     // This logic remains unchanged
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
                    break;
                
                case 'searchUsers':
                    if (!isset($_POST['query'])) {
                        throw new Exception("Search query is required.");
                    }
                    $query = trim($_POST['query']);
                    $search_term = "%{$query}%";

                    // Staff can search for admins, doctors, and other staff
                    $stmt = $conn->prepare("
                        SELECT id, display_user_id, name, role, profile_picture 
                        FROM users 
                        WHERE (name LIKE ? OR display_user_id LIKE ?) 
                        AND role IN ('admin', 'doctor', 'staff') 
                        AND id != ?
                    ");
                    $stmt->bind_param("ssi", $search_term, $search_term, $user_id);
                    $stmt->execute();
                    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    // Process profile pictures to return a full URL
                    foreach ($users as &$user) {
                        $default_avatar = '../images/staff-avatar.jpg';
                        $picture_filename = $user['profile_picture'];
                        $potential_path_local = dirname(__DIR__) . '/uploads/profile_pictures/' . $picture_filename;
                        $potential_path_web = '../uploads/profile_pictures/' . $picture_filename;

                        if (!empty($picture_filename) && $picture_filename !== 'default.png' && file_exists($potential_path_local)) {
                            $user['avatar_url'] = $potential_path_web;
                        } else {
                            $user['avatar_url'] = $default_avatar;
                        }
                    }
                    unset($user);

                    $response = ['success' => true, 'data' => $users];
                    break;

                case 'sendMessage':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    
                    if (empty($_POST['receiver_id']) || empty(trim($_POST['message_text']))) {
                        throw new Exception("Receiver and message text cannot be empty.");
                    }
                    $receiver_id = (int)$_POST['receiver_id'];
                    $message_text = trim($_POST['message_text']);
                    $sender_id = $user_id;

                    // Determine canonical user order for the conversation
                    $user_one_id = min($sender_id, $receiver_id);
                    $user_two_id = max($sender_id, $receiver_id);

                    // Find or create the conversation
                    $stmt_conv = $conn->prepare("SELECT id FROM conversations WHERE user_one_id = ? AND user_two_id = ?");
                    $stmt_conv->bind_param("ii", $user_one_id, $user_two_id);
                    $stmt_conv->execute();
                    $conv_result = $stmt_conv->get_result();
                    
                    if ($conv_result->num_rows > 0) {
                        $conversation_id = $conv_result->fetch_assoc()['id'];
                    } else {
                        $stmt_insert_conv = $conn->prepare("INSERT INTO conversations (user_one_id, user_two_id) VALUES (?, ?)");
                        $stmt_insert_conv->bind_param("ii", $user_one_id, $user_two_id);
                        $stmt_insert_conv->execute();
                        $conversation_id = $conn->insert_id;
                        $stmt_insert_conv->close();
                    }
                    $stmt_conv->close();

                    // Insert the message
                    $stmt_msg = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
                    $stmt_msg->bind_param("iiis", $conversation_id, $sender_id, $receiver_id, $message_text);
                    $stmt_msg->execute();
                    $new_message_id = $conn->insert_id;
                    $stmt_msg->close();

                    $conn->commit();
                    $transaction_active = false;
                    
                    // Fetch the sent message to return to the client
                    $stmt_get_msg = $conn->prepare("SELECT id, conversation_id, sender_id, message_text, created_at FROM messages WHERE id = ?");
                    $stmt_get_msg->bind_param("i", $new_message_id);
                    $stmt_get_msg->execute();
                    $sent_message = $stmt_get_msg->get_result()->fetch_assoc();
                    $stmt_get_msg->close();

                    $response = ['success' => true, 'message' => 'Message sent.', 'data' => $sent_message];
                    break;
            }
        }
    } catch (Exception $e) {
        if ($transaction_active) {
            $conn->rollback();
        }
        http_response_code(400); // Bad Request
        $response['message'] = $e->getMessage();
    }
    
    $conn->close();
    echo json_encode($response);
    exit(); // Stop script execution after handling API request
}


// --- Standard Page Load Security & Session Management ---

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?error=not_loggedin");
    exit();
}

if ($_SESSION['role'] !== 'staff') {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=unauthorized");
    exit();
}

$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}
$_SESSION['loggedin_time'] = time();

// --- Prepare Variables for Frontend ---
$conn = getDbConnection();

// Fetch user details along with staff-specific info
$stmt = $conn->prepare("
    SELECT 
        u.username, u.display_user_id, u.email, u.phone, u.profile_picture, u.name, u.date_of_birth,
        s.shift, s.assigned_department
    FROM users u
    LEFT JOIN staff s ON u.id = s.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch all available departments for the dropdown
$departments_result = $conn->query("SELECT name FROM departments WHERE is_active = 1 ORDER BY name ASC");
$departments = $departments_result->fetch_all(MYSQLI_ASSOC);

$conn->close();

if (!$user) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=user_not_found");
    exit();
}

$display_name = !empty($user['name']) ? htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');

// Sanitize all outputs to prevent XSS
$username = $display_name; // This is the display name
$raw_username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8'); // This is the login username
$display_user_id = htmlspecialchars($user['display_user_id'], ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$date_of_birth = htmlspecialchars($user['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8');
$shift = htmlspecialchars($user['shift'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$assigned_department = htmlspecialchars($user['assigned_department'] ?? '', ENT_QUOTES, 'UTF-8');
$profile_picture_filename = htmlspecialchars($user['profile_picture'] ?? 'default.png', ENT_QUOTES, 'UTF-8');

$profile_picture_path = '../uploads/profile_pictures/' . $profile_picture_filename;
if (!file_exists(dirname(__DIR__) . '/uploads/profile_pictures/' . $profile_picture_filename) || empty($user['profile_picture'])) {
    $profile_picture_path = '../images/staff-avatar.jpg'; // A default placeholder
}

// Generate a CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>