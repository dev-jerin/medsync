<?php
/**
 * MedSync Doctor Logic (api.php)
 *
 * This script handles the backend logic for the doctor's dashboard.
 * - It enforces session security.
 * - It fetches doctor profile data for the frontend.
 * - It includes AJAX handlers for updating personal info, managing bed occupancy, and fetching audit logs.
 * - NEW: Includes handlers for managing Lab Results and Messenger.
 * - NEW: Includes handler for My Patients page.
 * - NEW: Includes handlers for Admissions.
 * - NEW: Includes handlers for Prescriptions (get, search, add).
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
// MODIFIED: This block now correctly handles both standard requests and JSON POST requests
if (isset($_REQUEST['action']) || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    
    header('Content-Type: application/json');
    $current_user_id = $_SESSION['user_id'];
    
    $action = $_REQUEST['action'] ?? null;
    $input = [];

    // If it's a JSON request, decode the body and get the action from there
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        // If an action is set in the JSON body, it takes precedence
        $action = $input['action'] ?? $action; 
    }

    // ==========================================================
    // --- START: ADMISSIONS ACTIONS ---
    // ==========================================================
    
    if ($action == 'get_admissions') {
        $admissions = [];
        $sql = "
            SELECT 
                adm.id,
                p.name AS patient_name,
                p.display_user_id,
                adm.admission_date,
                CASE
                    WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                    ELSE CONCAT(w.name, ' - Bed ', acc.number)
                END AS room_bed,
                IF(adm.discharge_date IS NULL, 'Active', 'Discharged') AS status
            FROM admissions adm
            JOIN users p ON adm.patient_id = p.id
            LEFT JOIN accommodations acc ON adm.accommodation_id = acc.id
            LEFT JOIN wards w ON acc.ward_id = w.id
            WHERE adm.doctor_id = ?
            ORDER BY adm.admission_date DESC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $admissions = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => $admissions]);
        exit();
    }

    if ($action == 'get_available_accommodations') {
        $accommodations = [];
        $sql = "
            SELECT 
                acc.id,
                CASE
                    WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                    ELSE CONCAT(w.name, ' - Bed ', acc.number)
                END AS identifier
            FROM accommodations acc
            LEFT JOIN wards w ON acc.ward_id = w.id
            WHERE acc.status = 'available'
            ORDER BY identifier ASC
        ";
        $result = $conn->query($sql);
        if ($result) {
            $accommodations = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $accommodations]);
        exit();
    }

    if ($action == 'admit_patient' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)$_POST['patient_id'];
        $accommodation_id = (int)$_POST['accommodation_id'];
        // Note: admission_notes are not in your DB schema for admissions, but good practice to have.
        // We will log it instead.
        $notes = trim($_POST['notes'] ?? 'No notes provided.');

        if (empty($patient_id) || empty($accommodation_id)) {
            echo json_encode(['success' => false, 'message' => 'Patient and Bed are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            // 1. Create the admission record
            $stmt_adm = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, accommodation_id, admission_date) VALUES (?, ?, ?, NOW())");
            $stmt_adm->bind_param("iii", $patient_id, $current_user_id, $accommodation_id);
            $stmt_adm->execute();
            $admission_id = $conn->insert_id;
            $stmt_adm->close();

            // 2. Update the accommodation status
            $stmt_acc = $conn->prepare("UPDATE accommodations SET status = 'occupied', patient_id = ?, doctor_id = ?, occupied_since = NOW() WHERE id = ? AND status = 'available'");
            $stmt_acc->bind_param("iii", $patient_id, $current_user_id, $accommodation_id);
            $stmt_acc->execute();

            // Check if the bed was actually updated (prevents race conditions)
            if ($stmt_acc->affected_rows === 0) {
                throw new Exception('Selected bed is no longer available. Please choose another one.');
            }
            $stmt_acc->close();

            // 3. Log the activity
            log_activity($conn, $current_user_id, 'Patient Admitted', $patient_id, "Admission ID: {$admission_id}. Notes: {$notes}");
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Patient admitted successfully.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // ==========================================================
    // --- END: ADMISSIONS ACTIONS ---
    // ==========================================================

    // ==========================================================
    // --- START: APPOINTMENTS ACTIONS ---
    // ==========================================================

    if ($action == 'get_appointments') {
        // Default to fetching today's appointments if no period is specified
        $period = $_GET['period'] ?? 'today'; 
        
        $sql = "
            SELECT 
                a.id,
                a.appointment_date,
                a.status,
                a.token_number,
                p.name AS patient_name,
                p.display_user_id AS patient_display_id
            FROM appointments a
            JOIN users p ON a.user_id = p.id
            WHERE a.doctor_id = ? 
        ";

        // Append date filtering based on the 'period' parameter
        switch ($period) {
            case 'upcoming':
                $sql .= " AND DATE(a.appointment_date) > CURDATE() ORDER BY a.appointment_date ASC";
                break;
            case 'past':
                $sql .= " AND DATE(a.appointment_date) < CURDATE() ORDER BY a.appointment_date DESC";
                break;
            case 'today':
            default:
                $sql .= " AND DATE(a.appointment_date) = CURDATE() ORDER BY a.appointment_date ASC";
                break;
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // Handle SQL error
            echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
            exit();
        }
        
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        echo json_encode(['success' => true, 'data' => $appointments]);
        exit();
    }

    // ==========================================================
    // --- END: APPOINTMENTS ACTIONS ---
    // ==========================================================


    // ==========================================================
    // --- START: BED MANAGEMENT ACTIONS ---
    // ==========================================================

    // Action: Fetch Wards and Rooms for the Bed Management filter
    if ($action == 'get_locations') {
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
    if ($action == 'get_occupancy_data') {
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
    if ($action == 'update_location_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // ==========================================================
    // --- START: MY PATIENTS ACTIONS ---
    // ==========================================================

    if ($action == 'get_my_patients') {
        $patients = [];
        // This complex query retrieves all unique patients associated with the current doctor
        // from admissions, appointments, prescriptions, and lab results.
        // It then LEFT JOINs to find the patient's current status (In-Patient/Out-Patient)
        // and their assigned bed/room if they are admitted.
        $sql = "
            SELECT DISTINCT
                u.id,
                u.display_user_id,
                u.name,
                IF(adm.id IS NOT NULL, 'In-Patient', 'Out-Patient') AS status,
                CASE
                    WHEN acc.id IS NOT NULL THEN 
                        IF(acc.type = 'room', acc.number, CONCAT(w.name, ' - ', acc.number))
                    ELSE 'N/A'
                END AS room_bed
            FROM users u
            JOIN (
                SELECT patient_id FROM admissions WHERE doctor_id = ?
                UNION
                SELECT user_id AS patient_id FROM appointments WHERE doctor_id = ?
                UNION
                SELECT patient_id FROM prescriptions WHERE doctor_id = ?
                UNION
                SELECT patient_id FROM lab_results WHERE doctor_id = ?
            ) AS doctor_patients ON u.id = doctor_patients.patient_id
            LEFT JOIN admissions adm ON u.id = adm.patient_id AND adm.discharge_date IS NULL
            LEFT JOIN accommodations acc ON adm.accommodation_id = acc.id
            LEFT JOIN wards w ON acc.ward_id = w.id
            WHERE u.role_id = (SELECT id FROM roles WHERE role_name = 'user')
            ORDER BY u.name ASC
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiii", $current_user_id, $current_user_id, $current_user_id, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $patients = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => $patients]);
        exit();
    }

    // ==========================================================
    // --- END: MY PATIENTS ACTIONS ---
    // ==========================================================

    // ==========================================================
    // --- START: PRESCRIPTIONS ACTIONS ---
    // ==========================================================

    if ($action == 'get_prescriptions') {
        $prescriptions = [];
        $sql = "
            SELECT 
                pr.id,
                p.name AS patient_name,
                pr.prescription_date,
                pr.status
            FROM prescriptions pr
            JOIN users p ON pr.patient_id = p.id
            WHERE pr.doctor_id = ?
            ORDER BY pr.prescription_date DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $prescriptions = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => $prescriptions]);
        exit();
    }

    if ($action == 'search_medicines' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $medicines = [];
        
        // Return only medicines that are in stock
        $stmt = $conn->prepare("SELECT id, name, quantity FROM medicines WHERE name LIKE ? AND quantity > 0 LIMIT 10");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result) {
            $medicines = $result->fetch_all(MYSQLI_ASSOC);
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'data' => $medicines]);
        exit();
    }

    // MODIFIED: Uses the new $action variable and the pre-decoded $input array
    if ($action == 'add_prescription' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)($input['patient_id'] ?? 0);
        $notes = trim($input['notes'] ?? '');
        $items = $input['items'] ?? [];

        if (empty($patient_id) || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Patient and at least one medication are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            // 1. Create the main prescription record
            $stmt_pr = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, notes, status) VALUES (?, ?, CURDATE(), ?, 'pending')");
            $stmt_pr->bind_param("iis", $patient_id, $current_user_id, $notes);
            $stmt_pr->execute();
            $prescription_id = $conn->insert_id;
            $stmt_pr->close();

            // 2. Insert each prescribed item
            $stmt_item = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, quantity_prescribed) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $medicine_id = (int)$item['medicine_id'];
                $dosage = trim($item['dosage']);
                $frequency = trim($item['frequency']);
                $quantity = (int)$item['quantity'];

                $stmt_item->bind_param("iissi", $prescription_id, $medicine_id, $dosage, $frequency, $quantity);
                $stmt_item->execute();
            }
            $stmt_item->close();

            $conn->commit();
            log_activity($conn, $current_user_id, 'Prescription Issued', $patient_id, "Prescription ID: {$prescription_id}");
            echo json_encode(['success' => true, 'message' => 'Prescription created successfully!']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

    // ==========================================================
    // --- END: PRESCRIPTIONS ACTIONS ---
    // ==========================================================
    
    // Action: Update doctor's personal information
    if ($action == 'update_personal_info' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if ($action == 'get_audit_log') {
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
    if ($action == 'searchUsers') {
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
    if ($action == 'get_conversations') {
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
    if ($action == 'get_messages' && isset($_GET['conversation_id'])) {
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
    if ($action == 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
    if ($action == 'get_lab_results') {
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
    if ($action == 'get_lab_report_details' && isset($_GET['id'])) {
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
    if ($action == 'get_patients_for_dropdown') {
        $sql = "SELECT id, display_user_id, name FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'user') AND is_active = 1 ORDER BY name ASC";
        $result = $conn->query($sql);
        $patients = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['success' => true, 'data' => $patients]);
        exit();
    }

    // Action: Add a new lab result (handles file upload)
    if ($action == 'add_lab_result' && $_SERVER['REQUEST_METHOD'] === 'POST') {
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