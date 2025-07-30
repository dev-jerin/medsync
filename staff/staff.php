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

/**
 * Logs a specific action to the activity_logs table.
 */
function log_activity($conn, $user_id, $action, $target_user_id = null, $details = '') {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, target_user_id, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $target_user_id, $details);
    return $stmt->execute();
}

/**
 * Generates a unique, sequential display ID for a new user.
 */
function generateDisplayId($role, $conn) {
    $prefix_map = ['admin' => 'A', 'doctor' => 'D', 'staff' => 'S', 'user' => 'U'];
    if (!isset($prefix_map[$role])) {
        throw new Exception("Invalid role for ID generation.");
    }
    $prefix = $prefix_map[$role];

    $conn->begin_transaction();
    try {
        // Ensure the counter exists for the role
        $init_stmt = $conn->prepare("INSERT INTO role_counters (role_prefix, last_id) VALUES (?, 0) ON DUPLICATE KEY UPDATE role_prefix = role_prefix");
        $init_stmt->bind_param("s", $prefix);
        $init_stmt->execute();
        $init_stmt->close();
        
        // Atomically fetch and update the counter
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;

        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();
        
        $conn->commit();
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

// --- AJAX API Endpoint Logic ---
// This block handles all AJAX requests from the staff dashboard script.
if (isset($_GET['fetch']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    // Ensure a valid staff session exists for any API action
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
        http_response_code(401); // Unauthorized
        $response['message'] = 'Unauthorized access. Please log in again.';
        echo json_encode($response);
        exit();
    }


    $conn = getDbConnection();
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
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
                            r.role_name AS other_user_role,
                            (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message,
                            (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message_time,
                            (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND receiver_id = ? AND is_read = 0) AS unread_count
                        FROM conversations c
                        JOIN users u ON u.id = IF(c.user_one_id = ?, c.user_two_id, c.user_one_id)
                        JOIN roles r ON u.role_id = r.id
                        WHERE c.user_one_id = ? OR c.user_two_id = ?
                        ORDER BY last_message_time DESC
                    ");
                    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
                    $stmt->execute();
                    $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    foreach ($conversations as &$conv) {
                        $default_avatar = '../uploads/profile_pictures/default.png';
                        $picture_filename = $conv['other_user_profile_picture'];
                        $potential_path_local = dirname(__DIR__) . '/uploads/profile_pictures/' . $picture_filename;
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
                    $conversation_id = (int) $_GET['conversation_id'];

                    $auth_stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?)");
                    $auth_stmt->bind_param("iii", $conversation_id, $user_id, $user_id);
                    $auth_stmt->execute();
                    if ($auth_stmt->get_result()->num_rows === 0) {
                        http_response_code(403);
                        throw new Exception('You are not authorized to view this conversation.');
                    }
                    $auth_stmt->close();

                    $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?");
                    $update_stmt->bind_param("ii", $conversation_id, $user_id);
                    $update_stmt->execute();
                    $update_stmt->close();

                    $msg_stmt = $conn->prepare("SELECT id, sender_id, message_text, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
                    $msg_stmt->bind_param("i", $conversation_id);
                    $msg_stmt->execute();
                    $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $msg_stmt->close();

                    $response = ['success' => true, 'data' => $messages];
                    break;

                case 'audit_log':
                    $stmt = $conn->prepare("
                        SELECT action, details, created_at 
                        FROM activity_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 50
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $logs];
                    break;

                case 'get_users':
                    $role_filter = $_GET['role'] ?? 'all';
                    $search_query = $_GET['search'] ?? '';
                    $allowed_roles = ['user', 'doctor'];

                    $sql = "SELECT u.id, u.display_user_id, u.name, u.username, r.role_name as role, u.email, u.phone, u.date_of_birth, u.is_active as active, u.created_at, d.specialty 
                            FROM users u
                            JOIN roles r ON u.role_id = r.id
                            LEFT JOIN doctors d ON u.id = d.user_id
                            WHERE r.role_name IN ('user', 'doctor')";
                    $params = [];
                    $types = "";

                    if ($role_filter !== 'all' && in_array($role_filter, $allowed_roles)) {
                        $sql .= " AND r.role_name = ?";
                        $params[] = $role_filter;
                        $types .= "s";
                    }

                    if (!empty($search_query)) {
                        $sql .= " AND (u.name LIKE ? OR u.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $types .= "ss";
                    }

                    $sql .= " ORDER BY u.created_at DESC";
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $users];
                    break;
                case 'notifications':
                    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                    $stmt = $conn->prepare(
                        "SELECT 
                            n.id, n.message, n.is_read, n.created_at, 
                            u.name as sender_name, r.role_name as sender_role
                         FROM notifications n
                         LEFT JOIN users u ON n.sender_id = u.id
                         LEFT JOIN roles r ON u.role_id = r.id
                         WHERE (n.recipient_user_id = ? OR n.recipient_role = ? OR n.recipient_role = 'all')
                         ORDER BY n.created_at DESC
                         LIMIT ?"
                    );
                    $stmt->bind_param("isi", $user_id, $user_role, $limit);
                    $stmt->execute();
                    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $notifications];
                    break;
                
                case 'unread_notification_count':
                    $stmt = $conn->prepare(
                        "SELECT COUNT(id) as unread_count 
                         FROM notifications 
                         WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')"
                    );
                    $stmt->bind_param("is", $user_id, $user_role);
                    $stmt->execute();
                    $count = $stmt->get_result()->fetch_assoc()['unread_count'];
                    $stmt->close();
                    $response = ['success' => true, 'data' => ['count' => $count]];
                    break;
                case 'medicines':
                    $search_query = $_GET['search'] ?? '';
                    $sql = "SELECT id, name, description, quantity, unit_price, low_stock_threshold, updated_at FROM medicines";
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " WHERE name LIKE ?";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $types .= "s";
                    }

                    $sql .= " ORDER BY name ASC";
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    
                    $stmt->execute();
                    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'blood_inventory':
                    $stmt = $conn->prepare("SELECT id, blood_group, quantity_ml, low_stock_threshold_ml, last_updated FROM blood_inventory ORDER BY id ASC");
                    $stmt->execute();
                    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'bed_management_data':
                    // Fetch all active wards for the filter dropdown
                    $wards_stmt = $conn->prepare("SELECT id, name FROM wards WHERE is_active = 1 ORDER BY name ASC");
                    $wards_stmt->execute();
                    $wards = $wards_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $wards_stmt->close();
                
                    // Fetch all accommodations with details
                    $accommodations_stmt = $conn->prepare("
                        SELECT 
                            a.id, a.type, a.ward_id, w.name AS ward_name, a.number, a.status, a.price_per_day,
                            p.id as patient_id, p.display_user_id as patient_display_id, p.name as patient_name,
                            d.id as doctor_id, doc_user.name as doctor_name
                        FROM accommodations a
                        LEFT JOIN wards w ON a.ward_id = w.id
                        LEFT JOIN users p ON a.patient_id = p.id
                        LEFT JOIN doctors d ON a.doctor_id = d.user_id
                        LEFT JOIN users doc_user ON d.user_id = doc_user.id
                        ORDER BY a.type, w.name, a.number
                    ");
                    $accommodations_stmt->execute();
                    $accommodations_data = $accommodations_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $accommodations_stmt->close();
                
                    // Separate beds and rooms for frontend logic if needed
                    $beds = array_filter($accommodations_data, function($item) { return $item['type'] === 'bed'; });
                    $rooms = array_filter($accommodations_data, function($item) { return $item['type'] === 'room'; });

                    // Fetch available patients (users with role 'user' not currently admitted)
                    $patients_stmt = $conn->prepare("
                        SELECT u.id, u.display_user_id, u.name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.id
                        WHERE r.role_name = 'user' AND u.is_active = 1 AND u.id NOT IN (
                            SELECT patient_id FROM accommodations WHERE patient_id IS NOT NULL
                        )
                        ORDER BY u.name ASC
                    ");
                    $patients_stmt->execute();
                    $available_patients = $patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $patients_stmt->close();
                    
                    // Fetch available doctors
                    $doctors_stmt = $conn->prepare("
                        SELECT u.id, u.name
                        FROM users u
                        JOIN doctors d ON u.id = d.user_id
                        JOIN roles r ON u.role_id = r.id
                        WHERE r.role_name = 'doctor' AND u.is_active = 1
                        ORDER BY u.name ASC
                    ");
                    $doctors_stmt->execute();
                    $available_doctors = $doctors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $doctors_stmt->close();
                
                    $response = [
                        'success' => true, 
                        'data' => [
                            'wards' => $wards, 
                            'beds' => array_values($beds), // Re-index arrays
                            'rooms' => array_values($rooms),
                            'available_patients' => $available_patients,
                            'available_doctors' => $available_doctors
                        ]
                    ];
                    break;
                
                case 'admissions':
                    $search_query = $_GET['search'] ?? '';
                    
                    $sql = "
                        SELECT 
                            a.id,
                            p.display_user_id AS patient_display_id,
                            p.name AS patient_name,
                            doc_user.name AS doctor_name,
                            a.admission_date,
                            a.discharge_date,
                            acc.number AS location,
                            CASE 
                                WHEN acc.type = 'bed' THEN w.name 
                                WHEN acc.type = 'room' THEN 'Private Room' 
                                ELSE 'N/A' 
                            END AS location_type
                        FROM admissions a
                        JOIN users p ON a.patient_id = p.id
                        LEFT JOIN users doc_user ON a.doctor_id = doc_user.id
                        LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
                        LEFT JOIN wards w ON acc.ward_id = w.id
                    ";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " WHERE (p.name LIKE ? OR p.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $types .= "ss";
                    }

                    $sql .= " ORDER BY a.admission_date DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    
                    $stmt->execute();
                    $admissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $admissions];
                    break;

                case 'lab_results':
                    $search_query = $_GET['search'] ?? '';
                    
                    $sql = "
                        SELECT 
                            lr.id, lr.patient_id, lr.doctor_id,
                            p.display_user_id AS patient_display_id,
                            p.name AS patient_name,
                            doc.name AS doctor_name,
                            lr.test_name,
                            lr.test_date,
                            lr.result_details,
                            lr.attachment_path
                        FROM lab_results lr
                        JOIN users p ON lr.patient_id = p.id
                        LEFT JOIN users doc ON lr.doctor_id = doc.id
                    ";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " WHERE (p.name LIKE ? OR p.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $types .= "ss";
                    }

                    $sql .= " ORDER BY lr.test_date DESC, lr.id DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    
                    $stmt->execute();
                    $lab_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $lab_results];
                    break;

                case 'lab_form_data':
                    // Fetch only doctors, as patients will be handled by search
                    $doctors_stmt = $conn->prepare("SELECT u.id, u.name FROM users u JOIN doctors d ON u.id = d.user_id JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'doctor' AND u.is_active = 1 ORDER BY u.name ASC");
                    $doctors_stmt->execute();
                    $doctors = $doctors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $doctors_stmt->close();

                    $response = ['success' => true, 'data' => ['doctors' => $doctors]];
                    break;

                case 'search_patients':
                    $query = $_GET['query'] ?? '';
                    if (empty($query)) {
                         $response = ['success' => true, 'data' => []];
                         break;
                    }

                    $search_term = "%{$query}%";
                    $stmt = $conn->prepare("SELECT u.id, u.display_user_id, u.name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'user' AND u.is_active = 1 AND (u.name LIKE ? OR u.display_user_id LIKE ?) LIMIT 10");
                    $stmt->bind_param("ss", $search_term, $search_term);
                    $stmt->execute();
                    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $response = ['success' => true, 'data' => $patients];
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
                    $department_name = trim($_POST['department']);
                    $date_of_birth = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;

                    if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($department_name)) {
                        throw new Exception('Invalid input. Please check all fields.');
                    }
                    
                    // Get department ID from name
                    $dept_stmt = $conn->prepare("SELECT id FROM departments WHERE name = ?");
                    $dept_stmt->bind_param("s", $department_name);
                    $dept_stmt->execute();
                    $department_id = $dept_stmt->get_result()->fetch_assoc()['id'];
                    $dept_stmt->close();
                    if (!$department_id) {
                        throw new Exception('Invalid department selected.');
                    }

                    $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt_check->bind_param("si", $email, $user_id);
                    $stmt_check->execute();
                    $email_exists = $stmt_check->get_result()->num_rows > 0;
                    $stmt_check->close();

                    if ($email_exists) {
                        throw new Exception('This email address is already in use by another account.');
                    }

                    $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, date_of_birth = ? WHERE id = ?");
                    $stmt_user->bind_param("ssssi", $name, $email, $phone, $date_of_birth, $user_id);
                    $user_updated = $stmt_user->execute();
                    $stmt_user->close();

                    $stmt_staff = $conn->prepare("UPDATE staff SET assigned_department_id = ? WHERE user_id = ?");
                    $stmt_staff->bind_param("ii", $department_id, $user_id);
                    $staff_updated = $stmt_staff->execute();
                    $stmt_staff->close();

                    if ($user_updated && $staff_updated) {
                        $conn->commit();
                        $transaction_active = false;
                        $_SESSION['username'] = $name;
                        $response = ['success' => true, 'message' => 'Personal information updated successfully.'];
                    } else {
                        throw new Exception('Database update failed. Please try again.');
                    }
                    break;

                case 'updatePassword':
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
                    if (empty($_POST['id'])) {
                        throw new Exception('Callback request ID is required.');
                    }
                    $callback_id = (int) $_POST['id'];
                    $stmt = $conn->prepare("UPDATE callback_requests SET is_contacted = 1 WHERE id = ?");
                    $stmt->bind_param("i", $callback_id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Request marked as contacted.'];
                    } else {
                        throw new Exception('Database update failed.');
                    }
                    $stmt->close();
                    break;
                case 'markNotificationsRead':
                    $stmt = $conn->prepare(
                        "UPDATE notifications 
                         SET is_read = 1 
                         WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')"
                    );
                    $stmt->bind_param("is", $user_id, $user_role);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Notifications marked as read.'];
                    } else {
                        throw new Exception('Failed to update notifications.');
                    }
                    $stmt->close();
                    break;
                case 'searchUsers':
                    if (!isset($_POST['query'])) {
                        throw new Exception("Search query is required.");
                    }
                    $query = trim($_POST['query']);
                    $search_term = "%{$query}%";

                    // Staff can search for admins, doctors, and other staff to message them
                    $stmt = $conn->prepare("
                        SELECT u.id, u.display_user_id, u.name, r.role_name as role, u.profile_picture 
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        WHERE (u.name LIKE ? OR u.display_user_id LIKE ?) 
                        AND r.role_name IN ('admin', 'doctor', 'staff') 
                        AND u.id != ?
                    ");
                    $stmt->bind_param("ssi", $search_term, $search_term, $user_id);
                    $stmt->execute();
                    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

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
                    $receiver_id = (int) $_POST['receiver_id'];
                    $message_text = trim($_POST['message_text']);
                    $sender_id = $user_id;

                    $user_one_id = min($sender_id, $receiver_id);
                    $user_two_id = max($sender_id, $receiver_id);

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

                    $stmt_msg = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
                    $stmt_msg->bind_param("iiis", $conversation_id, $sender_id, $receiver_id, $message_text);
                    $stmt_msg->execute();
                    $new_message_id = $conn->insert_id;
                    $stmt_msg->close();

                    $conn->commit();
                    $transaction_active = false;

                    $stmt_get_msg = $conn->prepare("SELECT id, conversation_id, sender_id, receiver_id, message_text, created_at FROM messages WHERE id = ?");
                    $stmt_get_msg->bind_param("i", $new_message_id);
                    $stmt_get_msg->execute();
                    $sent_message = $stmt_get_msg->get_result()->fetch_assoc();
                    $stmt_get_msg->close();

                    $response = ['success' => true, 'message' => 'Message sent.', 'data' => $sent_message];
                    break;

                case 'addUser':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        $role_name = $_POST['role'];
                        
                        // Get role ID from role name
                        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
                        $role_stmt->bind_param("s", $role_name);
                        $role_stmt->execute();
                        $role_id = $role_stmt->get_result()->fetch_assoc()['id'];
                        $role_stmt->close();
                        if (!$role_id) {
                            throw new Exception("Invalid role specified.");
                        }

                        if ($role_name !== 'user' && $role_name !== 'doctor') {
                             throw new Exception("You are not authorized to create users with the role '{$role_name}'.");
                        }
                        
                        $name = trim($_POST['name']);
                        $username = trim($_POST['username']);
                        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                        
                        if (empty($_POST['password'])) {
                           throw new Exception('Password is required for new users.');
                        }
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $date_of_birth = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
                        $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;

                        if (empty($name) || empty($username) || empty($email)) {
                            throw new Exception("Name, Username, and Email fields are required.");
                        }

                        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt_check->bind_param("ss", $username, $email);
                        $stmt_check->execute();
                        if ($stmt_check->get_result()->num_rows > 0) {
                            throw new Exception('Username or email already exists.');
                        }
                        $stmt_check->close();

                        // Generate Display ID before inserting
                        $display_user_id = generateDisplayId($role_name, $conn);
                        
                        $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role_id, phone, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssiss", $display_user_id, $name, $username, $email, $password, $role_id, $phone, $date_of_birth);
                        $stmt->execute();
                        
                        // Get the newly created user's ID
                        $new_user_id = $conn->insert_id;
                        if (!$new_user_id) {
                            throw new Exception('Failed to create the base user account.');
                        }
                        $stmt->close();

                        if ($role_name === 'doctor') {
                            $specialty = trim($_POST['specialty']);
                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty) VALUES (?, ?)");
                            $stmt_doctor->bind_param("is", $new_user_id, $specialty);
                            $stmt_doctor->execute();
                            $stmt_doctor->close();
                        }
                        
                        log_activity($conn, $user_id, 'create_user', $new_user_id, "Staff member created a new {$role_name}: {$username}");

                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => ucfirst($role_name) . ' added successfully.'];
                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'updateUser':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        $target_user_id = (int)$_POST['id'];
                        
                        $stmt_role_check = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                        $stmt_role_check->bind_param("i", $target_user_id);
                        $stmt_role_check->execute();
                        $target_role = $stmt_role_check->get_result()->fetch_assoc()['role_name'];
                        $stmt_role_check->close();

                        if ($target_role !== 'user' && $target_role !== 'doctor') {
                            throw new Exception("You are not authorized to edit users with the role '{$target_role}'.");
                        }

                        $name = trim($_POST['name']);
                        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
                        $phone = trim($_POST['phone']);
                        $date_of_birth = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
                        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

                        if (empty($name) || empty($email)) {
                            throw new Exception("Name and Email fields are required.");
                        }

                        $stmt_update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, date_of_birth = ?, is_active = ? WHERE id = ?");
                        $stmt_update->bind_param("ssssii", $name, $email, $phone, $date_of_birth, $active, $target_user_id);
                        $stmt_update->execute();
                        $stmt_update->close();

                        if ($target_role === 'doctor' && isset($_POST['specialty'])) {
                            $specialty = trim($_POST['specialty']);
                            // This robust query will insert a doctor record if it doesn't exist, or update it if it does.
                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty) VALUES (?, ?) ON DUPLICATE KEY UPDATE specialty = VALUES(specialty)");
                            $stmt_doctor->bind_param("is", $target_user_id, $specialty);
                            $stmt_doctor->execute();
                            $stmt_doctor->close();
                        }

                        log_activity($conn, $user_id, 'update_user', $target_user_id, "Staff updated profile for user ID {$target_user_id}.");

                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => 'User updated successfully.'];
                    } catch (Exception $e) {
                        if($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;
                    
                case 'removeUser':
                    $target_user_id = (int)$_POST['id'];
                    
                    $stmt_role_check = $conn->prepare("SELECT r.role_name, u.username FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                    $stmt_role_check->bind_param("i", $target_user_id);
                    $stmt_role_check->execute();
                    $target_user = $stmt_role_check->get_result()->fetch_assoc();
                    $stmt_role_check->close();
                    
                    if (!$target_user || ($target_user['role_name'] !== 'user' && $target_user['role_name'] !== 'doctor')) {
                        throw new Exception("You are not authorized to remove this user.");
                    }
                    
                    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $target_user_id);
                    $stmt->execute();

                    log_activity($conn, $user_id, 'deactivate_user', $target_user_id, "Staff deactivated user: {$target_user['username']}");

                    $response = ['success' => true, 'message' => 'User has been deactivated.'];
                    break;

                case 'updateMedicineStock':
                    if (!isset($_POST['id'], $_POST['quantity'])) {
                        throw new Exception('Medicine ID and new quantity are required.');
                    }
                    $medicine_id = (int)$_POST['id'];
                    $new_quantity = (int)$_POST['quantity'];

                    $stmt = $conn->prepare("UPDATE medicines SET quantity = ? WHERE id = ?");
                    $stmt->bind_param("ii", $new_quantity, $medicine_id);
                    $stmt->execute();

                    log_activity($conn, $user_id, 'update_inventory', null, "Updated medicine stock for ID {$medicine_id} to {$new_quantity}.");
                    $response = ['success' => true, 'message' => 'Medicine stock updated successfully.'];
                    break;
                
                case 'updateBloodStock':
                    if (!isset($_POST['blood_group'], $_POST['quantity_ml'])) {
                        throw new Exception('Blood group and new quantity are required.');
                    }
                    $blood_group = $_POST['blood_group'];
                    $new_quantity = (int)$_POST['quantity_ml'];

                    $stmt = $conn->prepare("UPDATE blood_inventory SET quantity_ml = ? WHERE blood_group = ?");
                    $stmt->bind_param("is", $new_quantity, $blood_group);
                    $stmt->execute();

                    log_activity($conn, $user_id, 'update_inventory', null, "Updated blood stock for group {$blood_group} to {$new_quantity} ml.");
                    $response = ['success' => true, 'message' => 'Blood stock updated successfully.'];
                    break;

                case 'addBedOrRoom':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        $type = $_POST['type'];
                        $number = trim($_POST['number']);
                        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
                        $ward_id = ($type === 'bed' && !empty($_POST['ward_id'])) ? (int)$_POST['ward_id'] : null;

                        if (empty($number) || $price === false || $price < 0) {
                            throw new Exception("Valid number and non-negative price are required.");
                        }
                        if ($type === 'bed' && empty($ward_id)) {
                            throw new Exception("Ward is required for a new bed.");
                        }

                        $stmt = $conn->prepare("INSERT INTO accommodations (type, number, ward_id, price_per_day) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssid", $type, $number, $ward_id, $price);
                        $stmt->execute();
                        $stmt->close();

                        if ($type === 'bed') {
                            $stmt_ward = $conn->prepare("UPDATE wards SET capacity = capacity + 1 WHERE id = ?");
                            $stmt_ward->bind_param("i", $ward_id);
                            $stmt_ward->execute();
                            $stmt_ward->close();
                        }

                        log_activity($conn, $user_id, "add_{$type}", null, "Added {$type} {$number} with price {$price}");
                        $response = ['success' => true, 'message' => ucfirst($type) . ' added successfully.'];

                        $conn->commit();
                        $transaction_active = false;
                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;


                case 'updateBedOrRoom':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        $id = (int)$_POST['id'];
                        
                        $updates = [];
                        $params = [];
                        $types = "";

                        // Fetch current accommodation details to handle admission/discharge logic
                        $stmt_current = $conn->prepare("SELECT type, patient_id FROM accommodations WHERE id = ? FOR UPDATE");
                        $stmt_current->bind_param("i", $id);
                        $stmt_current->execute();
                        $current_accommodation = $stmt_current->get_result()->fetch_assoc();
                        $stmt_current->close();
                        if (!$current_accommodation) {
                            throw new Exception("Accommodation not found.");
                        }
                        $type = $current_accommodation['type'];
                        $old_patient_id = $current_accommodation['patient_id'];

                        // Handle simple updates
                        if (isset($_POST['price'])) {
                            $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
                            if ($price === false || $price < 0) throw new Exception("Invalid price format.");
                            $updates[] = "price_per_day = ?";
                            $params[] = $price;
                            $types .= "d";
                        }
                        
                        // Handle complex patient assignment/discharge
                        if (isset($_POST['patient_id'])) {
                            $new_patient_id = empty($_POST['patient_id']) ? null : (int)$_POST['patient_id'];
                            $new_doctor_id = empty($_POST['doctor_id']) ? null : (int)$_POST['doctor_id'];

                            if ($new_patient_id !== $old_patient_id) {
                                // Discharging old patient if there was one
                                if ($old_patient_id) {
                                    $dis_stmt = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE accommodation_id = ? AND patient_id = ? AND discharge_date IS NULL");
                                    $dis_stmt->bind_param("ii", $id, $old_patient_id);
                                    $dis_stmt->execute();
                                    $dis_stmt->close();
                                }
                                
                                // Admitting new patient
                                if ($new_patient_id) {
                                    $adm_stmt = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, accommodation_id, admission_date) VALUES (?, ?, ?, NOW())");
                                    $adm_stmt->bind_param("iii", $new_patient_id, $new_doctor_id, $id);
                                    $adm_stmt->execute();
                                    $adm_stmt->close();
                                    
                                    $updates[] = "status = 'occupied'";
                                    $updates[] = "occupied_since = NOW()";
                                    $updates[] = "reserved_since = NULL";
                                } else {
                                    // No new patient, so it's a discharge or status change
                                    $updates[] = "status = 'cleaning'"; // Default status after discharge
                                    $updates[] = "occupied_since = NULL";
                                }
                                $updates[] = "patient_id = ?";
                                $params[] = $new_patient_id;
                                $types .= "i";
                                
                                $updates[] = "doctor_id = ?";
                                $params[] = $new_doctor_id;
                                $types .= "i";
                            }
                        }

                        if (empty($updates)) {
                            throw new Exception("No data provided to update.");
                        }

                        $sql = "UPDATE accommodations SET " . implode(", ", $updates) . " WHERE id = ?";
                        $params[] = $id;
                        $types .= "i";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $stmt->close();
                        
                        log_activity($conn, $user_id, "update_{$type}", null, "Updated details for {$type} ID {$id}");
                        
                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => ucfirst($type) . ' updated successfully.'];
                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;
                
                case 'addLabResult':
                case 'updateLabResult':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        $is_update = ($_POST['action'] === 'updateLabResult');
                        
                        $patient_id = (int)$_POST['patient_id'];
                        $test_name = trim($_POST['test_name']);
                        $test_date = trim($_POST['test_date']);
                        $doctor_id = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
                        $result_details = !empty($_POST['result_details']) ? trim($_POST['result_details']) : null;
                        
                        if (empty($patient_id) || empty($test_name) || empty($test_date)) {
                            throw new Exception("Patient, test name, and test date are required.");
                        }

                        $attachment_filename = null;
                        
                        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = __DIR__ . '/report/';
                            if (!is_dir($upload_dir)) {
                                if (!mkdir($upload_dir, 0755, true)) {
                                    throw new Exception('Failed to create report directory.');
                                }
                            }
                            
                            $file_info = new finfo(FILEINFO_MIME_TYPE);
                            $mime_type = $file_info->file($_FILES['attachment']['tmp_name']);
                            if ($mime_type !== 'application/pdf') {
                                throw new Exception('Invalid file type. Only PDF reports are allowed.');
                            }

                            if ($_FILES['attachment']['size'] > 5242880) { // 5MB limit
                                throw new Exception('File is too large. Maximum size is 5MB.');
                            }
                            
                            $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                            $attachment_filename = 'report_' . $patient_id . '_' . time() . '.' . $file_extension;
                            
                            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment_filename)) {
                                throw new Exception('Failed to save uploaded report.');
                            }
                        }

                        if ($is_update) {
                            $lab_id = (int)$_POST['id'];
                            if (empty($lab_id)) throw new Exception("Lab Result ID is missing for update.");

                            // If a new file was uploaded, delete the old one
                            if ($attachment_filename) {
                                $stmt_old = $conn->prepare("SELECT attachment_path FROM lab_results WHERE id = ?");
                                $stmt_old->bind_param("i", $lab_id);
                                $stmt_old->execute();
                                $old_file = $stmt_old->get_result()->fetch_assoc()['attachment_path'];
                                $stmt_old->close();
                                if ($old_file && file_exists($upload_dir . $old_file)) {
                                    unlink($upload_dir . $old_file);
                                }
                            }

                            $sql = "UPDATE lab_results SET patient_id = ?, doctor_id = ?, test_name = ?, test_date = ?, result_details = ?";
                            $types = "iisss";
                            $params = [$patient_id, $doctor_id, $test_name, $test_date, $result_details];

                            if ($attachment_filename) {
                                $sql .= ", attachment_path = ?";
                                $types .= "s";
                                $params[] = $attachment_filename;
                            }

                            $sql .= " WHERE id = ?";
                            $types .= "i";
                            $params[] = $lab_id;

                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param($types, ...$params);
                            $stmt->execute();
                            $stmt->close();
                            
                            log_activity($conn, $user_id, 'update_lab_result', $patient_id, "Updated lab result #{$lab_id} for test '{$test_name}'");
                            $response = ['success' => true, 'message' => 'Lab result updated successfully.'];

                        } else { // Add new
                            $stmt = $conn->prepare("INSERT INTO lab_results (patient_id, doctor_id, staff_id, test_name, test_date, result_details, attachment_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("iiissss", $patient_id, $doctor_id, $user_id, $test_name, $test_date, $result_details, $attachment_filename);
                            $stmt->execute();
                            $new_lab_id = $conn->insert_id;
                            $stmt->close();
                            
                            log_activity($conn, $user_id, 'add_lab_result', $patient_id, "Added lab result #{$new_lab_id} for test '{$test_name}'");
                            $response = ['success' => true, 'message' => 'Lab result added successfully.'];
                        }

                        $conn->commit();
                        $transaction_active = false;
                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;


                case 'removeLabResult':
                    if (empty($_POST['id'])) {
                        throw new Exception('Lab result ID is required.');
                    }
                    $lab_id = (int)$_POST['id'];

                    $conn->begin_transaction();
                    
                    // First, get the attachment path to delete the file
                    $upload_dir = __DIR__ . '/report/';
                    $stmt_select = $conn->prepare("SELECT attachment_path FROM lab_results WHERE id = ?");
                    $stmt_select->bind_param("i", $lab_id);
                    $stmt_select->execute();
                    $file_to_delete = $stmt_select->get_result()->fetch_assoc()['attachment_path'];
                    $stmt_select->close();

                    // Now, delete the database record
                    $stmt_delete = $conn->prepare("DELETE FROM lab_results WHERE id = ?");
                    $stmt_delete->bind_param("i", $lab_id);
                    $stmt_delete->execute();
                    
                    if ($stmt_delete->affected_rows > 0) {
                        if ($file_to_delete && file_exists($upload_dir . $file_to_delete)) {
                            unlink($upload_dir . $file_to_delete);
                        }
                        log_activity($conn, $user_id, 'delete_lab_result', null, "Deleted lab result #{$lab_id}");
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Lab result deleted successfully.'];
                    } else {
                        $conn->rollback();
                        throw new Exception('Could not find or delete the lab result.');
                    }
                    $stmt_delete->close();
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

    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    echo json_encode($response);
    exit(); // Stop script execution after handling API request
}


// --- Standard Page Load Security & Session Management ---

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php?error=not_loggedin");
    exit();
}

$role_check_stmt = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$role_check_stmt->bind_param("i", $_SESSION['user_id']);
$role_check_stmt->execute();
$current_user_role = $role_check_stmt->get_result()->fetch_assoc()['role_name'];
$role_check_stmt->close();

if (!in_array($current_user_role, ['staff', 'admin'])) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=unauthorized");
    exit();
}
$_SESSION['role'] = $current_user_role;


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

$stmt = $conn->prepare("
    SELECT 
        u.username, u.display_user_id, u.email, u.phone, u.profile_picture, u.name, u.date_of_birth,
        s.shift, d.name as assigned_department
    FROM users u
    LEFT JOIN staff s ON u.id = s.user_id
    LEFT JOIN departments d ON s.assigned_department_id = d.id
    WHERE u.id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

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
$username = $display_name;
$raw_username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
$display_user_id = htmlspecialchars($user['display_user_id'], ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
$phone = htmlspecialchars($user['phone'] ?? '', ENT_QUOTES, 'UTF-8');
$date_of_birth = htmlspecialchars($user['date_of_birth'] ?? '', ENT_QUOTES, 'UTF-8');
$shift = htmlspecialchars($user['shift'] ?? 'N/A', ENT_QUOTES, 'UTF-8');
$assigned_department = htmlspecialchars($user['assigned_department'] ?? '', ENT_QUOTES, 'UTF-8');
$profile_picture_filename = htmlspecialchars($user['profile_picture'] ?? 'default.png', ENT_QUOTES, 'UTF-8');

$profile_picture_path = '../uploads/profile_pictures/' . $profile_picture_filename;
if (!file_exists(dirname(__DIR__) . '/uploads/profile_pictures/' . $profile_picture_filename) || empty($user['profile_picture'])) {
    $profile_picture_path = '../uploads/default.png'; // A default placeholder
}

// Generate a CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>