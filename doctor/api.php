<?php
/**
 * MedSync Doctor Logic (api.php)
 *
 * This script handles the backend logic for the doctor's dashboard.
 * - It enforces session security.
 * - It fetches doctor profile data for the frontend.
 * - It includes AJAX handlers for updating personal info, managing bed occupancy, and fetching audit logs.
 * - NEW: Includes handlers for managing Lab Results and Messenger.
 */
require_once '../config.php'; // Contains the database connection ($conn)

// --- Helper function for activity logging ---
function log_activity($conn, $user_id, $action, $target_user_id = null, $details = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, target_user_id, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $target_user_id, $details);
    $stmt->execute();
    $stmt->close();
}


// --- Security & Session Management ---
// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If it's an AJAX request, send a JSON error instead of redirecting
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }
    header("Location: ../login");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('doctor').
if ($_SESSION['role'] !== 'doctor') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        exit();
    }
    session_destroy();
    header("Location: ../login/index.php?error=unauthorized");
    exit();
}

// 3. Implement a session timeout.
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }
    session_unset();
    session_destroy();
    header("Location: ../login/index.php?session_expired=true");
    exit();
}
$_SESSION['loggedin_time'] = time();


// --- Prepare Variables for Frontend (for non-AJAX page loads) ---
if (!isset($_REQUEST['action'])) {
    $user_id = $_SESSION['user_id'];
    $username = '';
    $full_name = '';
    $email = '';
    $phone = '';
    $gender = '';
    $date_of_birth = '';
    $specialty = '';
    $profile_picture = 'default.png'; // Fallback default
    $display_user_id = htmlspecialchars($_SESSION['display_user_id']);

    // Updated query to fetch profile_picture
    $stmt = $conn->prepare("SELECT u.username, u.name, u.email, u.phone, u.gender, u.date_of_birth, u.profile_picture, d.specialty FROM users u LEFT JOIN doctors d ON u.id = d.user_id WHERE u.id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = htmlspecialchars($row['username']);
        $full_name = htmlspecialchars($row['name'] ?: $row['username']); 
        $email = htmlspecialchars($row['email']);
        $phone = htmlspecialchars($row['phone']);
        $gender = htmlspecialchars($row['gender']);
        $date_of_birth = htmlspecialchars($row['date_of_birth']);
        $specialty = htmlspecialchars($row['specialty']);
        // Set profile picture, ensuring it's not null or empty
        $profile_picture = !empty($row['profile_picture']) ? htmlspecialchars($row['profile_picture']) : 'default.png';
    }
    $stmt->close();
    $_SESSION['name'] = $full_name;
}


// --- AJAX Request Handler ---
if (isset($_REQUEST['action'])) {
    
    header('Content-Type: application/json');
    $current_user_id = $_SESSION['user_id'];

    // ==========================================================
    // --- START: BED MANAGEMENT ACTIONS ---
    // ==========================================================

    // Action: Fetch Wards and Rooms for the Bed Management filter
    if ($_REQUEST['action'] == 'get_locations') {
        $locations = ['wards' => [], 'rooms' => []];
        
        $ward_result = $conn->query("SELECT id, name FROM wards WHERE is_active = 1 ORDER BY name ASC");
        if($ward_result) {
            $locations['wards'] = $ward_result->fetch_all(MYSQLI_ASSOC);
        }

        $room_result = $conn->query("SELECT id, number FROM accommodations WHERE type = 'room' ORDER BY number ASC");
        if($room_result) {
            while ($row = $room_result->fetch_assoc()) {
                $locations['rooms'][] = ['id' => $row['id'], 'name' => $row['number']];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $locations]);
        exit();
    }

    // Action: Fetch all bed/room occupancy data
    if ($_REQUEST['action'] == 'get_occupancy_data') {
        $occupancy_data = [];

        $sql = "
            SELECT 
                a.id, a.type, a.number AS bed_number, a.status, a.ward_id AS location_parent_id,
                CASE 
                    WHEN a.type = 'bed' THEN w.name
                    ELSE 'Private Room'
                END AS location_name,
                p.name AS patient_name, p.display_user_id AS patient_display_id
            FROM accommodations a
            LEFT JOIN wards w ON a.ward_id = w.id AND a.type = 'bed'
            LEFT JOIN users p ON a.patient_id = p.id
        ";
        $result = $conn->query($sql);
        if ($result) {
            $occupancy_data = $result->fetch_all(MYSQLI_ASSOC);
        }

        echo json_encode(['success' => true, 'data' => $occupancy_data]);
        exit();
    }

    // Action: Update status for a bed or room in 'accommodations' table
    if ($_REQUEST['action'] == 'update_location_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : ''; 
        $new_status = isset($_POST['status']) ? $_POST['status'] : '';
        
        $allowed_statuses = ['available', 'cleaning', 'reserved'];
        $allowed_types = ['bed', 'room'];

        if ($id > 0 && in_array($new_status, $allowed_statuses) && in_array($type, $allowed_types)) {
            $stmt = $conn->prepare("UPDATE accommodations SET status = ?, patient_id = NULL, occupied_since = NULL WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);

            if ($stmt->execute()) {
                log_activity($conn, $current_user_id, 'Accommodation Status Update', null, "Set accommodation ID {$id} to {$new_status}");
                echo json_encode(['success' => true, 'message' => ucfirst($type) . ' status updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: Failed to update status.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data provided for update.']);
        }
        exit();
    }

    // ==========================================================
    // --- END: BED MANAGEMENT ACTIONS ---
    // ==========================================================

    // Action: Update doctor's personal information
    if ($_REQUEST['action'] == 'update_personal_info' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialty = trim($_POST['specialty'] ?? '');
        $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $gender = $_POST['gender'] ?? '';
        
        if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a valid name and email address.']);
            exit();
        }

        $conn->begin_transaction();

        try {
            $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, gender = ?, date_of_birth = ? WHERE id = ?");
            $stmt_user->bind_param("sssssi", $name, $email, $phone, $gender, $dob, $current_user_id);
            $stmt_user->execute();
            
            $stmt_doctor = $conn->prepare("UPDATE doctors SET specialty = ? WHERE user_id = ?");
            $stmt_doctor->bind_param("si", $specialty, $current_user_id);
            $stmt_doctor->execute();
            
            log_activity($conn, $current_user_id, 'Profile Updated', null, 'Personal info changed.');
            
            $conn->commit();
            
            $_SESSION['name'] = $name;

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            if ($conn->errno === 1062) {
                 echo json_encode(['success' => false, 'message' => 'Error: This email address is already in use by another account.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'A database error occurred. Could not update profile.']);
            }
        } finally {
            if (isset($stmt_user)) $stmt_user->close();
            if (isset($stmt_doctor)) $stmt_doctor->close();
        }
        exit();
    }
    
    // Action: Fetch audit log for the current doctor
    if ($_REQUEST['action'] == 'get_audit_log') {
        $audit_logs = [];
        $sql = "
            SELECT 
                al.created_at,
                al.action,
                al.details,
                target_user.name AS target_user_name,
                target_user.display_user_id AS target_display_id
            FROM activity_logs al
            LEFT JOIN users AS target_user ON al.target_user_id = target_user.id
            WHERE al.user_id = ?
            ORDER BY al.created_at DESC
            LIMIT 50
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $audit_logs = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => $audit_logs]);
        exit();
    }

    // Action: Search for users to message
    if ($_REQUEST['action'] == 'searchUsers') {
        if (!isset($_GET['term'])) {
            echo json_encode(['success' => false, 'message' => 'Search term is required.']);
            exit();
        }
        $term = '%' . trim($_GET['term']) . '%';
        // Doctors can message other doctors, staff, and admins
        $sql = "SELECT u.id, u.display_user_id, u.name, r.role_name as role, u.profile_picture 
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE (u.name LIKE ? OR u.display_user_id LIKE ?) 
                AND r.role_name IN ('doctor', 'staff', 'admin') 
                AND u.id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $term, $term, $current_user_id);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

    // Action: Fetch conversations
    if ($_REQUEST['action'] == 'get_conversations') {
        $stmt = $conn->prepare("
            SELECT
                c.id AS conversation_id,
                u.id AS other_user_id,
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
        $stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
        $stmt->execute();
        $conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $conversations]);
        exit();
    }

    // Action: Fetch messages for a conversation
    if ($_REQUEST['action'] == 'get_messages' && isset($_GET['conversation_id'])) {
        $conversation_id = (int)$_GET['conversation_id'];
        // Mark messages as read
        $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?");
        $update_stmt->bind_param("ii", $conversation_id, $current_user_id);
        $update_stmt->execute();

        // Fetch messages
        $msg_stmt = $conn->prepare("SELECT id, sender_id, message_text, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $msg_stmt->bind_param("i", $conversation_id);
        $msg_stmt->execute();
        $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $messages]);
        exit();
    }

    // Action: Send a new message
    if ($_REQUEST['action'] == 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $receiver_id = (int)$_POST['receiver_id'];
        $message_text = trim($_POST['message_text']);

        if (empty($receiver_id) || empty($message_text)) {
            echo json_encode(['success' => false, 'message' => 'Receiver and message are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            $user_one_id = min($current_user_id, $receiver_id);
            $user_two_id = max($current_user_id, $receiver_id);

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
            }

            $stmt_msg = $conn->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message_text) VALUES (?, ?, ?, ?)");
            $stmt_msg->bind_param("iiis", $conversation_id, $current_user_id, $receiver_id, $message_text);
            $stmt_msg->execute();
            $new_message_id = $conn->insert_id;
            
            $conn->commit();
            
            $stmt_get_msg = $conn->prepare("SELECT * FROM messages WHERE id = ?");
            $stmt_get_msg->bind_param("i", $new_message_id);
            $stmt_get_msg->execute();
            $sent_message = $stmt_get_msg->get_result()->fetch_assoc();

            echo json_encode(['success' => true, 'message' => 'Message sent.', 'data' => $sent_message]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Failed to send message.']);
        }
        exit();
    }
    
    // ==========================================================
    // --- START: LAB RESULTS ACTIONS ---
    // ==========================================================

    // Action: Get all lab results for display in the table
    if ($_REQUEST['action'] == 'get_lab_results') {
        $sql = "SELECT
                    lr.id,
                    lr.test_name,
                    lr.test_date,
                    lr.status,
                    p.name AS patient_name
                FROM lab_results lr
                JOIN users p ON lr.patient_id = p.id
                WHERE lr.doctor_id = ?
                ORDER BY lr.test_date DESC, lr.created_at DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    // Action: Get detailed information for a single lab report (for viewing modal)
    if ($_REQUEST['action'] == 'get_lab_report_details' && isset($_GET['id'])) {
        $report_id = (int)$_GET['id'];
        $sql = "SELECT 
                    lr.*, 
                    p.name AS patient_name,
                    p.display_user_id AS patient_display_id
                FROM lab_results lr
                JOIN users p ON lr.patient_id = p.id
                WHERE lr.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data) {
            // Decode the JSON details for easier use on the frontend
            $data['result_details'] = json_decode($data['result_details'], true);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found.']);
        }
        $stmt->close();
        exit();
    }

    // Action: Get a list of patients for dropdowns
    if ($_REQUEST['action'] == 'get_patients_for_dropdown') {
        $sql = "SELECT id, display_user_id, name FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'user') AND is_active = 1 ORDER BY name ASC";
        $result = $conn->query($sql);
        $patients = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['success' => true, 'data' => $patients]);
        exit();
    }

    // Action: Add a new lab result (handles file upload)
    if ($_REQUEST['action'] == 'add_lab_result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)$_POST['patient_id'];
        $test_name = trim($_POST['test_name']);
        $test_date = !empty($_POST['test_date']) ? $_POST['test_date'] : date('Y-m-d');
        $result_details = $_POST['result_details']; // JSON string from frontend
        $status = 'completed'; // When adding a full result, it's completed
        $attachment_path = null;

        if (empty($patient_id) || empty($test_name)) {
            echo json_encode(['success' => false, 'message' => 'Patient and Test Name are required.']);
            exit();
        }

        // --- File Upload Handling ---
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/lab_reports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = new SplFileInfo($_FILES['report_file']['name']);
            $extension = strtolower($file_info->getExtension());

            if ($_FILES['report_file']['size'] > 5000000) { // 5MB limit
                echo json_encode(['success' => false, 'message' => 'File is too large. Maximum size is 5MB.']);
                exit();
            }
            if ($extension !== 'pdf') {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Only PDF files are allowed.']);
                exit();
            }

            $safe_filename = "report_" . uniqid() . "." . $extension;
            if (move_uploaded_file($_FILES['report_file']['tmp_name'], $upload_dir . $safe_filename)) {
                $attachment_path = $safe_filename;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
                exit();
            }
        }
        
        $sql = "INSERT INTO lab_results (patient_id, doctor_id, staff_id, test_name, test_date, status, result_details, attachment_path, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        // Using current doctor's ID for both doctor_id and staff_id to satisfy schema constraints
        $stmt->bind_param("iiisssss", $patient_id, $current_user_id, $current_user_id, $test_name, $test_date, $status, $result_details, $attachment_path);

        if ($stmt->execute()) {
            $report_id = $conn->insert_id;
            log_activity($conn, $current_user_id, 'Lab Result Added', $patient_id, "Report ID: LR{$report_id}");
            echo json_encode(['success' => true, 'message' => 'Lab result added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: Could not save the result.']);
        }
        $stmt->close();
        exit();
    }
    
    // --- END: LAB RESULTS ACTIONS ---

    // Fallback for any unknown action
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit();
}
?>