<?php
/**
 * MedSync Doctor Logic (api.php)
 *
 * This script handles the backend logic for the doctor's dashboard.
 * - It enforces session security.
 * - It fetches doctor profile data for the frontend.
 * - It includes AJAX handlers for updating personal info, managing bed occupancy, and fetching audit logs.
 * - UPDATED: Includes handlers for managing Lab Orders and Messenger.
 * - UPDATED: Includes handler for My Patients page.
 * - Includes handlers for Admissions.
 * - Includes handlers for Prescriptions (get, search, add).
 */
require_once '../config.php'; // Contains the database connection ($conn)
require_once '../vendor/autoload.php'; // Add this line for Dompdf

// Add these lines for Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;


// --- Helper function for activity logging ---
function log_activity($conn, $user_id, $action, $target_user_id = null, $details = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, target_user_id, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isis", $user_id, $action, $target_user_id, $details);
    $stmt->execute();
    $stmt->close();
}

// --- NEW HELPER FUNCTIONS ---

/**
 * Fetches a list of specialties from the database.
 *
 * @param mysqli $conn The database connection object.
 * @return array An array of specialties.
 */
function get_specialities($conn) {
    $specialities = [];
    $sql = "SELECT id, name FROM specialities ORDER BY name ASC";
    $result = $conn->query($sql);
    if ($result) {
        $specialities = $result->fetch_all(MYSQLI_ASSOC);
    }
    return $specialities;
}

/**
 * Fetches a list of active departments from the database.
 *
 * @param mysqli $conn The database connection object.
 * @return array An array of departments.
 */
function get_departments($conn) {
    $departments = [];
    $sql = "SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC";
    $result = $conn->query($sql);
    if ($result) {
        $departments = $result->fetch_all(MYSQLI_ASSOC);
    }
    return $departments;
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

// ==========================================================
// --- PDF DOWNLOAD HANDLER (NON-JSON RESPONSE) ---
// ==========================================================
if (isset($_GET['action']) && $_GET['action'] == 'download_prescription') {
    if (empty($_GET['id'])) {
        die('Prescription ID is required.');
    }
    $prescription_id = (int)$_GET['id'];
    $current_user_id = $_SESSION['user_id'];
    $conn = getDbConnection();
    
    // Fetch all data needed for the PDF
    $stmt = $conn->prepare("
        SELECT 
            pr.prescription_date, pr.notes,
            p.name AS patient_name, p.display_user_id AS patient_display_id,
            d.name AS doctor_name, d.display_user_id AS doctor_display_id,
            s.name as specialty
        FROM prescriptions pr
        JOIN users p ON pr.patient_id = p.id
        JOIN users d ON pr.doctor_id = d.id
        LEFT JOIN doctors doc ON d.id = doc.user_id
        LEFT JOIN specialities s ON doc.specialty_id = s.id
        WHERE pr.id = ? AND pr.doctor_id = ?
    ");
    $stmt->bind_param("ii", $prescription_id, $current_user_id);
    $stmt->execute();
    $prescription_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prescription_data) {
        die('Prescription not found or you do not have permission to view it.');
    }

    $stmt_items = $conn->prepare("
        SELECT m.name, pi.dosage, pi.frequency, pi.quantity_prescribed
        FROM prescription_items pi
        JOIN medicines m ON pi.medicine_id = m.id
        WHERE pi.prescription_id = ?
    ");
    $stmt_items->bind_param("i", $prescription_id);
    $stmt_items->execute();
    $prescription_data['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_items->close();
    
    generatePrescriptionPdf($prescription_data);
    exit();
}


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
    $qualifications = ''; // Initialize qualifications

    $conn = getDbConnection();
    // Updated query to fetch profile_picture, qualifications, department, and specialty
    $stmt = $conn->prepare("
    SELECT u.username, u.name, u.email, u.phone, u.gender, u.date_of_birth, u.profile_picture, 
           s.name as specialty, d.qualifications, dep.name as department
    FROM users u 
    LEFT JOIN doctors d ON u.id = d.user_id 
    LEFT JOIN specialities s ON d.specialty_id = s.id
    LEFT JOIN departments dep ON d.department_id = dep.id
    WHERE u.id = ?
");
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
        $qualifications = htmlspecialchars($row['qualifications']);
        $profile_picture = !empty($row['profile_picture']) ? htmlspecialchars($row['profile_picture']) : 'default.png';
    }
    $stmt->close();
    $_SESSION['name'] = $full_name;
}


// --- AJAX Request Handler ---
if (isset($_REQUEST['action']) || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    
    header('Content-Type: application/json');
    $current_user_id = $_SESSION['user_id'];
    $conn = getDbConnection();
    
    $action = $_REQUEST['action'] ?? null;
    $input = [];

    // If it's a JSON request, decode the body and get the action from there
    if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $action; 
    }
    
    // --- START: NEW CASES FOR DROPDOWNS & PROFILE DATA ---
    if ($action == 'get_specialities') {
        echo json_encode(['success' => true, 'data' => get_specialities($conn)]);
        exit();
    }

    if ($action == 'get_departments') {
        echo json_encode(['success' => true, 'data' => get_departments($conn)]);
        exit();
    }
    
    if ($action == 'get_doctor_details') {
        $stmt = $conn->prepare("SELECT specialty_id, department_id, qualifications FROM doctors WHERE user_id = ?");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }
    // --- END: NEW CASES ---


    // ==========================================================
    // --- DASHBOARD ACTIONS ---
    // ==========================================================
    if ($action == 'get_dashboard_data') {
        $response = [
            'success' => true,
            'data' => [
                'stats' => [],
                'appointments' => [],
                'inpatients' => []
            ]
        ];

        // 1. Fetch statistics
        $stats_sql = "
            SELECT
                (SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE()) AS today_appointments,
                (SELECT COUNT(*) FROM admissions WHERE doctor_id = ? AND discharge_date IS NULL) AS active_admissions,
                (SELECT COUNT(DISTINCT a.id) 
                 FROM admissions a 
                 JOIN discharge_clearance dc ON a.id = dc.admission_id 
                 WHERE a.doctor_id = ? AND a.discharge_date IS NULL) AS pending_discharges
        ";
        $stmt = $conn->prepare($stats_sql);
        $stmt->bind_param("iii", $current_user_id, $current_user_id, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['data']['stats'] = $result->fetch_assoc();
        $stmt->close();

        // 2. Fetch today's appointment queue (Limit 5 for the dashboard)
        $appt_sql = "
            SELECT a.token_number, p.name AS patient_name, a.appointment_date, a.status
            FROM appointments a
            JOIN users p ON a.user_id = p.id
            WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
            ORDER BY a.appointment_date ASC LIMIT 5
        ";
        $stmt = $conn->prepare($appt_sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['data']['appointments'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // 3. Fetch current in-patients (Limit 5 for the dashboard)
        $inpatients_sql = "
            SELECT p.name AS patient_name,
                   p.id AS patient_id,
                   CASE
                       WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                       ELSE CONCAT(w.name, ' - Bed ', acc.number)
                   END AS room_bed
            FROM admissions adm
            JOIN users p ON adm.patient_id = p.id
            LEFT JOIN accommodations acc ON adm.accommodation_id = acc.id
            LEFT JOIN wards w ON acc.ward_id = w.id
            WHERE adm.doctor_id = ? AND adm.discharge_date IS NULL
            ORDER BY adm.admission_date DESC LIMIT 5
        ";
        $stmt = $conn->prepare($inpatients_sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $response['data']['inpatients'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        echo json_encode($response);
        exit();
    }

    // ==========================================================
    // --- ADMISSIONS ACTIONS ---
    // ==========================================================
    if ($action == 'get_admissions') {
        $admissions = [];
        $sql = "
            SELECT 
                adm.id, p.name AS patient_name, p.display_user_id, adm.admission_date,
                CASE
                    WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                    ELSE CONCAT(w.name, ' - Bed ', acc.number)
                END AS room_bed,
                IF(adm.discharge_date IS NULL, 'Active', 'Discharged') AS status
            FROM admissions adm
            JOIN users p ON adm.patient_id = p.id
            LEFT JOIN accommodations acc ON adm.accommodation_id = acc.id
            LEFT JOIN wards w ON acc.ward_id = w.id
            WHERE adm.doctor_id = ? ORDER BY adm.admission_date DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $admissions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $admissions]);
        exit();
    }

    if ($action == 'get_available_accommodations') {
        $accommodations = [];
        $sql = "
            SELECT acc.id, CASE WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number) ELSE CONCAT(w.name, ' - Bed ', acc.number) END AS identifier
            FROM accommodations acc
            LEFT JOIN wards w ON acc.ward_id = w.id
            WHERE acc.status = 'available' ORDER BY identifier ASC
        ";
        $result = $conn->query($sql);
        if ($result) $accommodations = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $accommodations]);
        exit();
    }

    if ($action == 'admit_patient' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $patient_id = (int)$_POST['patient_id'];
        $accommodation_id = (int)$_POST['accommodation_id'];
        $notes = trim($_POST['notes'] ?? 'No notes provided.');

        if (empty($patient_id) || empty($accommodation_id)) {
            echo json_encode(['success' => false, 'message' => 'Patient and Bed are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            $stmt_adm = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, accommodation_id, admission_date) VALUES (?, ?, ?, NOW())");
            $stmt_adm->bind_param("iii", $patient_id, $current_user_id, $accommodation_id);
            $stmt_adm->execute();
            $admission_id = $conn->insert_id;
            $stmt_adm->close();

            $stmt_acc = $conn->prepare("UPDATE accommodations SET status = 'occupied', patient_id = ?, doctor_id = ?, occupied_since = NOW() WHERE id = ? AND status = 'available'");
            $stmt_acc->bind_param("iii", $patient_id, $current_user_id, $accommodation_id);
            $stmt_acc->execute();

            if ($stmt_acc->affected_rows === 0) throw new Exception('Selected bed is no longer available.');
            $stmt_acc->close();

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
    // --- APPOINTMENTS ACTIONS ---
    // ==========================================================
    if ($action == 'get_appointments') {
        $period = $_GET['period'] ?? 'today';
        $filter_date = $_GET['date'] ?? null;

        $sql = "
            SELECT a.id, a.appointment_date, a.status, a.token_number, p.name AS patient_name, p.display_user_id AS patient_display_id
            FROM appointments a JOIN users p ON a.user_id = p.id
            WHERE a.doctor_id = ? 
        ";
        $params = [$current_user_id];
        $types = "i";

        if (!empty($filter_date)) {
            $sql .= " AND DATE(a.appointment_date) = ?";
            $params[] = $filter_date;
            $types .= "s";
        } else {
            switch ($period) {
                case 'upcoming': $sql .= " AND DATE(a.appointment_date) > CURDATE()"; break;
                case 'past': $sql .= " AND DATE(a.appointment_date) < CURDATE()"; break;
                default: $sql .= " AND DATE(a.appointment_date) = CURDATE()"; break;
            }
        }

        $sql .= " ORDER BY a.appointment_date ASC";

        $stmt = $conn->prepare($sql);
        if (!$stmt) die(json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]));
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointments = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $appointments]);
        exit();
    }

    // ==========================================================
    // --- BED MANAGEMENT ACTIONS ---
    // ==========================================================
    if ($action == 'get_locations') {
        $locations = ['wards' => [], 'rooms' => []];
        $ward_result = $conn->query("SELECT id, name FROM wards WHERE is_active = 1 ORDER BY name ASC");
        if($ward_result) $locations['wards'] = $ward_result->fetch_all(MYSQLI_ASSOC);
        $room_result = $conn->query("SELECT id, number FROM accommodations WHERE type = 'room' ORDER BY number ASC");
        if($room_result) {
            while ($row = $room_result->fetch_assoc()) $locations['rooms'][] = ['id' => $row['id'], 'name' => $row['number']];
        }
        echo json_encode(['success' => true, 'data' => $locations]);
        exit();
    }

    if ($action == 'get_occupancy_data') {
        $occupancy_data = [];
        $sql = "
            SELECT a.id, a.type, a.number AS bed_number, a.status, a.ward_id AS location_parent_id,
            CASE WHEN a.type = 'bed' THEN w.name ELSE 'Private Room' END AS location_name,
            p.name AS patient_name, p.display_user_id AS patient_display_id
            FROM accommodations a
            LEFT JOIN wards w ON a.ward_id = w.id AND a.type = 'bed'
            LEFT JOIN users p ON a.patient_id = p.id
        ";
        $result = $conn->query($sql);
        if ($result) $occupancy_data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $occupancy_data]);
        exit();
    }

    if ($action == 'update_location_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['type'] ?? ''; 
        $new_status = $_POST['status'] ?? '';
        
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
    // --- MY PATIENTS ACTIONS ---
    // ==========================================================
    if ($action == 'get_my_patients') {
        $patients = [];
        $sql = "
            SELECT DISTINCT u.id, u.display_user_id, u.name, IF(adm.id IS NOT NULL, 'In-Patient', 'Out-Patient') AS status,
            CASE WHEN acc.id IS NOT NULL THEN IF(acc.type = 'room', CONCAT('Room ', acc.number), CONCAT(w.name, ' - Bed ', acc.number)) ELSE 'N/A' END AS room_bed
            FROM users u
            JOIN (
                SELECT patient_id FROM admissions WHERE doctor_id = ? UNION
                SELECT user_id AS patient_id FROM appointments WHERE doctor_id = ? UNION
                SELECT patient_id FROM prescriptions WHERE doctor_id = ? UNION
                SELECT patient_id FROM lab_orders WHERE doctor_id = ?
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
        if ($result) $patients = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $patients]);
        exit();
    }

    // ==========================================================
    // --- PRESCRIPTIONS ACTIONS ---
    // ==========================================================
    if ($action == 'get_prescriptions') {
        $prescriptions = [];
        $sql = "
            SELECT pr.id, p.name AS patient_name, pr.prescription_date, pr.status
            FROM prescriptions pr JOIN users p ON pr.patient_id = p.id
            WHERE pr.doctor_id = ? ORDER BY pr.prescription_date DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $prescriptions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $prescriptions]);
        exit();
    }

    if ($action == 'search_medicines' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $medicines = [];
        $stmt = $conn->prepare("SELECT id, name, quantity FROM medicines WHERE name LIKE ? AND quantity > 0 LIMIT 10");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $medicines = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $medicines]);
        exit();
    }
    
    if ($action == 'search_patients' && isset($_GET['term'])) {
        $term = '%' . $_GET['term'] . '%';
        $patients = [];
        // Ensure we only search for users with the 'user' role
        $stmt = $conn->prepare("
            SELECT u.id, u.name, u.display_user_id 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE r.role_name = 'user' AND (u.name LIKE ? OR u.display_user_id LIKE ?) 
            LIMIT 10
        ");
        $stmt->bind_param("ss", $term, $term);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $patients = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $patients]);
        exit();
    }

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
            $stmt_pr = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, notes, status) VALUES (?, ?, CURDATE(), ?, 'pending')");
            $stmt_pr->bind_param("iis", $patient_id, $current_user_id, $notes);
            $stmt_pr->execute();
            $prescription_id = $conn->insert_id;
            $stmt_pr->close();

            $stmt_item = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, quantity_prescribed) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt_item->bind_param("iissi", $prescription_id, $item['medicine_id'], $item['dosage'], $item['frequency'], $item['quantity']);
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

    if ($action == 'get_prescription_details' && isset($_GET['id'])) {
        $prescription_id = (int)$_GET['id'];
        $response = ['success' => false, 'message' => 'Prescription not found or unauthorized.'];

        // Main prescription details
        $stmt_main = $conn->prepare("
            SELECT pr.prescription_date, pr.notes, p.name AS patient_name, p.display_user_id AS patient_display_id
            FROM prescriptions pr
            JOIN users p ON pr.patient_id = p.id
            WHERE pr.id = ? AND pr.doctor_id = ?
        ");
        $stmt_main->bind_param("ii", $prescription_id, $current_user_id);
        $stmt_main->execute();
        $result_main = $stmt_main->get_result();
        
        if ($details = $result_main->fetch_assoc()) {
            // Get medication items for this prescription
            $stmt_items = $conn->prepare("
                SELECT m.name, pi.dosage, pi.frequency, pi.quantity_prescribed
                FROM prescription_items pi
                JOIN medicines m ON pi.medicine_id = m.id
                WHERE pi.prescription_id = ?
            ");
            $stmt_items->bind_param("i", $prescription_id);
            $stmt_items->execute();
            $result_items = $stmt_items->get_result();
            
            $details['items'] = $result_items->fetch_all(MYSQLI_ASSOC);
            $response = ['success' => true, 'data' => $details];
            
            $stmt_items->close();
        }
        $stmt_main->close();
        echo json_encode($response);
        exit();
    }
    
    // ==========================================================
    // --- PROFILE & GENERAL ACTIONS (REVISED) ---
    // ==========================================================
    if ($action == 'update_personal_info' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // 1. Data Retrieval
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $dob = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $gender = $_POST['gender'] ?? '';
        $specialty_id = !empty($_POST['specialty_id']) ? (int)$_POST['specialty_id'] : null;
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        $qualifications = trim($_POST['qualifications'] ?? '');
    
        // 2. Server-Side Validation
        if (empty($name) || empty($email) || empty($phone)) {
            echo json_encode(['success' => false, 'message' => 'Full Name, Email, and Phone Number are required.']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit();
        }
        if (!preg_match('/^\+91\d{10}$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Phone number must be in the format +91xxxxxxxxxx.']);
            exit();
        }
        if ($dob) {
            $d = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$d || $d->format('Y-m-d') !== $dob || strlen(explode('-', $dob)[0]) > 4) {
                echo json_encode(['success' => false, 'message' => 'Invalid Date of Birth format.']);
                exit();
            }
        }
    
        // 3. Database Transaction
        $conn->begin_transaction();
        try {
            // Update users table
            $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, gender = ?, date_of_birth = ? WHERE id = ?");
            $stmt_user->bind_param("sssssi", $name, $email, $phone, $gender, $dob, $current_user_id);
            $stmt_user->execute();
            
            // Update doctors table (Use INSERT...ON DUPLICATE KEY UPDATE to handle both new and existing doctors)
            $stmt_doctor = $conn->prepare("
                INSERT INTO doctors (user_id, specialty_id, department_id, qualifications) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    specialty_id = VALUES(specialty_id), 
                    department_id = VALUES(department_id), 
                    qualifications = VALUES(qualifications)
            ");
            $stmt_doctor->bind_param("iiis", $current_user_id, $specialty_id, $department_id, $qualifications);
            $stmt_doctor->execute();
            
            log_activity($conn, $current_user_id, 'Profile Updated', null, 'Personal info changed.');
            $conn->commit();
            $_SESSION['name'] = $name; // Update session name
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            // Check for duplicate email error
            if ($conn->errno === 1062) {
                 echo json_encode(['success' => false, 'message' => 'Error: This email address is already in use by another account.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
            }
        } finally {
            if (isset($stmt_user)) $stmt_user->close();
            if (isset($stmt_doctor)) $stmt_doctor->close();
        }
        exit();
    }
    
    if ($action == 'get_audit_log') {
        $audit_logs = [];
        $sql = "
            SELECT al.created_at, al.action, al.details, target_user.name AS target_user_name, target_user.display_user_id AS target_display_id
            FROM activity_logs al LEFT JOIN users AS target_user ON al.target_user_id = target_user.id
            WHERE al.user_id = ? ORDER BY al.created_at DESC LIMIT 50
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) $audit_logs = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $audit_logs]);
        exit();
    }

    if ($action == 'updatePassword') {
        // ADDED: A try...catch block to handle errors and send proper JSON responses
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];

            if ($new_password !== $_POST['confirm_password']) {
                throw new Exception('New password and confirmation do not match.');
            }
            if (strlen($new_password) < 8) {
                throw new Exception('New password must be at least 8 characters long.');
            }

            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            // CHANGED: Use the correct session variable '$current_user_id' instead of '$user_id'
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($result && password_verify($current_password, $result['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                // CHANGED: Use the correct session variable here as well
                $stmt_update->bind_param("si", $hashed_password, $current_user_id);
                
                if ($stmt_update->execute()) {
                    log_activity($conn, $current_user_id, 'update_password', $current_user_id, 'User changed their own password.');
                    echo json_encode(['success' => true, 'message' => 'Password changed successfully.']);
                } else {
                    throw new Exception('Failed to update password in the database.');
                }
                $stmt_update->close();
            } else {
                throw new Exception('Incorrect current password.');
            }
        } catch (Exception $e) {
            // This 'catch' block ensures errors are sent back as JSON
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    // ==========================================================
    // --- MESSENGER ACTIONS ---
    // ==========================================================
        if ($action == 'searchUsers') {
        if (!isset($_GET['term'])) die(json_encode(['success' => false, 'message' => 'Search term is required.']));
        $term = '%' . trim($_GET['term']) . '%';
        
        $sql = "SELECT u.id, u.display_user_id, u.name, r.role_name as role, u.profile_picture, 
                    c.id as conversation_id
                FROM users u 
                JOIN roles r ON u.role_id = r.id
                LEFT JOIN conversations c ON 
                    (c.user_one_id = u.id AND c.user_two_id = ?) OR 
                    (c.user_one_id = ? AND c.user_two_id = u.id)
                WHERE (u.name LIKE ? OR u.display_user_id LIKE ?) 
                AND r.role_name IN ('doctor', 'staff', 'admin') 
                AND u.id != ?";
                
        $stmt = $conn->prepare($sql);
        // CORRECTED: The type string now has 5 characters ("iissi") to match the 5 variables.
        $stmt->bind_param("iissi", $current_user_id, $current_user_id, $term, $term, $current_user_id);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $users]);
        exit();
    }

    if ($action == 'get_conversations') {
        $stmt = $conn->prepare("
            SELECT c.id AS conversation_id, u.id AS other_user_id, u.name AS other_user_name, u.profile_picture AS other_user_profile_picture, r.role_name AS other_user_role,
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

    if ($action == 'get_messages' && isset($_GET['conversation_id'])) {
        $conversation_id = (int)$_GET['conversation_id'];
        $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?");
        $update_stmt->bind_param("ii", $conversation_id, $current_user_id);
        $update_stmt->execute();
        $msg_stmt = $conn->prepare("SELECT id, sender_id, message_text, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
        $msg_stmt->bind_param("i", $conversation_id);
        $msg_stmt->execute();
        $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $messages]);
        exit();
    }

    if ($action == 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $receiver_id = (int)$_POST['receiver_id'];
        $message_text = trim($_POST['message_text']);
        if (empty($receiver_id) || empty($message_text)) die(json_encode(['success' => false, 'message' => 'Receiver and message are required.']));

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
    // --- START: LAB WORKFLOW UPDATE ---
    // ==========================================================

    // Action: Get all lab orders for the current doctor
    if ($action == 'get_lab_orders') {
        $sql = "SELECT
                    lo.id, lo.test_name, lo.ordered_at, lo.status, p.name AS patient_name
                FROM lab_orders lo
                JOIN users p ON lo.patient_id = p.id
                WHERE lo.doctor_id = ?
                ORDER BY lo.ordered_at DESC";
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
                    lo.*, p.name AS patient_name, p.display_user_id AS patient_display_id
                FROM lab_orders lo
                JOIN users p ON lo.patient_id = p.id
                WHERE lo.id = ? AND lo.doctor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $report_id, $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data) {
            // Assuming result_details is stored as JSON
            if (!empty($data['result_details'])) {
                $data['result_details'] = json_decode($data['result_details'], true);
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Report not found or unauthorized.']);
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

    // NEW Action: Create new lab order(s)
    if ($action == 'create_lab_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Note: We get data from json_decode because the JS will send a JSON string
        $input = json_decode(file_get_contents('php://input'), true);
        
        $patient_id = (int)($input['patient_id'] ?? 0);
        $test_names = $input['test_names'] ?? [];

        if (empty($patient_id) || empty($test_names)) {
            echo json_encode(['success' => false, 'message' => 'Patient and at least one test name are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO lab_orders (patient_id, doctor_id, test_name, status, ordered_at) 
                 VALUES (?, ?, ?, 'ordered', NOW())"
            );
            
            foreach ($test_names as $test_name) {
                $trimmed_test_name = trim($test_name);
                if (!empty($trimmed_test_name)) {
                    $stmt->bind_param("iis", $patient_id, $current_user_id, $trimmed_test_name);
                    $stmt->execute();
                }
            }
            $stmt->close();
            $conn->commit();
            
            log_activity($conn, $current_user_id, 'Lab Order Placed', $patient_id, count($test_names) . " test(s) ordered.");
            echo json_encode(['success' => true, 'message' => 'Lab order(s) placed successfully.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
    
    // ==========================================================
    // --- END: LAB WORKFLOW UPDATE ---
    // ==========================================================

    // Fallback for any unknown action
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit();
}

/**
 * Generates a PDF from prescription data and streams it to the browser.
 * @param array $data The prescription data.
 */
function generatePrescriptionPdf($data) {
    // --- Get image paths and convert to base64 ---
    $medsync_logo_path = '../images/logo.png';
    $hospital_logo_path = '../images/hospital.png';
    $medsync_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($medsync_logo_path));
    $hospital_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($hospital_logo_path));


    $items_html = '';
    if (!empty($data['items'])) {
        foreach ($data['items'] as $item) {
            $items_html .= '
            <tr>
                <td style="padding: 10px 5px;">
                    <div style="font-weight: bold; font-size: 1.1em;">' . htmlspecialchars($item['name']) . '</div>
                    <div style="color: #555; font-size: 0.9em;">' . htmlspecialchars($item['dosage']) . ' - ' . htmlspecialchars($item['frequency']) . ' (Qty: ' . htmlspecialchars($item['quantity_prescribed']) . ')</div>
                </td>
            </tr>';
        }
    } else {
        $items_html = '<tr><td>No medications listed.</td></tr>';
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Prescription</title>
        <style>
            @page { margin: 20px; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
            .header { width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .header .logo { width: 60px; vertical-align: middle; }
            .hospital-details { text-align: center; }
            .doctor-details { text-align: right; }
            .patient-details { margin-top: 20px; padding: 10px; border: 1px solid #ccc; border-radius: 8px; }
            .rx-body { margin-top: 25px; }
            .rx-symbol { font-size: 4em; font-weight: bold; color: #ccc; float: left; width: 80px; }
            .rx-medication-area { margin-left: 80px; border-left: 1px solid #ccc; padding-left: 15px; }
            .rx-signature { margin-top: 60px; padding-top: 10px; border-top: 1px solid #333; text-align: right; }
            table { width: 100%; }
        </style>
    </head>
    <body>
        <table class="header">
            <tr>
                <td style="width:25%;">
                    <img src="' . $medsync_logo_base64 . '" alt="MedSync Logo" class="logo">
                </td>
                <td style="width:50%; text-align:center;">
                    <div class="hospital-details">
                        <h2 style="margin:0;">Calysta Health Institute</h2>
                        <p style="margin:2px 0;">Kerala, India | +91 45235 31245</p>
                    </div>
                </td>
                <td style="width:25%; text-align:right;">
                    <img src="' . $hospital_logo_base64 . '" alt="Hospital Logo" class="logo">
                </td>
            </tr>
        </table>
        
        <table style="width:100%;">
            <tr>
                <td style="width:60%; vertical-align:top;">
                    <div class="patient-details">
                        <strong>Patient:</strong> ' . htmlspecialchars($data['patient_name']) . ' (' . htmlspecialchars($data['patient_display_id']) . ')<br>
                        <strong>Date:</strong> ' . htmlspecialchars($data['prescription_date']) . '
                    </div>
                </td>
                <td style="width:40%; text-align:right; vertical-align:top;">
                    <div class="doctor-details">
                        <strong>Dr. ' . htmlspecialchars($data['doctor_name']) . '</strong><br>
                        ' . htmlspecialchars($data['specialty']) . '<br>
                        Reg. No: ' . htmlspecialchars($data['doctor_display_id']) . '
                    </div>
                </td>
            </tr>
        </table>

        <div class="rx-body">
            <div class="rx-symbol">R<sub>x</sub></div>
            <div class="rx-medication-area">
                <table>' . $items_html . '</table>
                <p><strong>Notes:</strong><br>' . nl2br(htmlspecialchars($data['notes'] ?: 'No specific notes provided.')) . '</p>
            </div>
        </div>
        <div class="rx-signature">
            Digitally Signed
        </div>
    </body>
    </html>';

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true); 
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("Prescription-".htmlspecialchars($data['patient_display_id']).".pdf", ["Attachment" => 0]);
}
?>