<?php
/**
 * MedSync Doctor Logic (api.php)
 *
 * This script handles the backend logic for the doctor's dashboard.
 * - It enforces session security.
 * - It fetches doctor profile data for the frontend.
 * - It includes AJAX handlers for updating personal info, managing bed occupancy, and fetching audit logs.
 * - NEW: Includes handlers for managing Lab Results.
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
    
    // ==========================================================
    // --- START: LAB RESULTS ACTIONS ---
    // NOTE: This assumes an ENUM 'status' column ('pending', 'processing', 'completed')
    // has been added to the `lab_results` table for full functionality.
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