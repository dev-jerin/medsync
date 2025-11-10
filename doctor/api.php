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
 * - UPDATED: Added full handlers for the automated Discharge Process.
 */
require_once '../config.php'; // Contains the database connection ($conn)
require_once '../vendor/autoload.php'; // Add this line for Dompdf
require_once '../mail/templates.php'; // Email templates
require_once '../mail/send_mail.php'; // Email sending functionality

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

// --- DISCHARGE SUMMARY PDF DOWNLOAD HANDLER ---
if (isset($_GET['action']) && $_GET['action'] == 'download_discharge_summary') {
    if (empty($_GET['admission_id'])) {
        die('Admission ID is required.');
    }
    $admission_id = (int)$_GET['admission_id'];
    $current_user_id = $_SESSION['user_id'];
    $conn = getDbConnection();
    
    // Fetch all data needed for the PDF
    $stmt = $conn->prepare("
        SELECT 
            p.name AS patient_name, p.display_user_id AS patient_display_id, p.gender, p.date_of_birth,
            a.admission_date,
            dc.discharge_date, dc.summary_text,
            d.name AS doctor_name, d.display_user_id AS doctor_display_id,
            s.name as specialty
        FROM admissions a
        JOIN users p ON a.patient_id = p.id
        LEFT JOIN discharge_clearance dc ON a.id = dc.admission_id
        LEFT JOIN users d ON dc.doctor_id = d.id
        LEFT JOIN doctors doc ON d.id = doc.user_id
        LEFT JOIN specialities s ON doc.specialty_id = s.id
        WHERE a.id = ? AND a.doctor_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $admission_id, $current_user_id);
    $stmt->execute();
    $summary_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$summary_data) {
        die('Summary not found or you do not have permission to view it.');
    }

    generateDischargeSummaryPdf($summary_data);
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
        $stmt = $conn->prepare("SELECT specialty_id, department_id, qualifications, office_floor, office_room_number FROM doctors WHERE user_id = ?");
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
                (SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'scheduled') AS today_appointments,
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
            SELECT a.id, a.token_number, p.name AS patient_name, a.appointment_date, 
                   a.slot_start_time, a.slot_end_time, a.status, a.token_status, p.id as user_id
            FROM appointments a
            JOIN users p ON a.user_id = p.id
            WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled'
            ORDER BY a.slot_start_time ASC, a.token_number ASC LIMIT 5
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
    
    // --- START: ADDED CODE FOR DISCHARGE PROCESS ---
    
    // Action: Get all active and completed discharge requests for the 'Discharge' page
    if ($action == 'get_discharge_requests') {
        $requests = [];
        // This query fetches all admissions that have a corresponding entry in discharge_clearance,
        // which means the discharge process has been started for them.
        $sql = "
            SELECT 
                a.id AS admission_id,
                a.admission_date,
                p.name AS patient_name,
                p.display_user_id,
                dc_summary.initiated_at, -- Use the earliest creation time as the initiation time
                CASE
                    WHEN a.discharge_date IS NOT NULL THEN 'Completed'
                    WHEN (SELECT COUNT(*) FROM discharge_clearance WHERE admission_id = a.id AND is_cleared = 0) = 0 THEN 'Ready for Discharge'
                    ELSE 'Pending'
                END AS status,
                CASE 
                    WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                    ELSE CONCAT(w.name, ' - Bed ', acc.number)
                END AS room_bed
            FROM admissions a
            JOIN users p ON a.patient_id = p.id
            LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
            LEFT JOIN wards w ON acc.ward_id = w.id
            JOIN (
                SELECT admission_id, MIN(created_at) as initiated_at 
                FROM discharge_clearance 
                GROUP BY admission_id
            ) dc_summary ON a.id = dc_summary.admission_id
            WHERE a.doctor_id = ?
            ORDER BY 
                CASE 
                    WHEN a.discharge_date IS NULL THEN 0 ELSE 1 
                END, -- Show active requests first
                dc_summary.initiated_at DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $requests = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $requests]);
        exit();
    }

    // Action: Initiate the discharge process for an admitted patient
    if ($action == 'initiate_discharge' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $admission_id = (int)($_POST['admission_id'] ?? 0);

        if (empty($admission_id)) {
            echo json_encode(['success' => false, 'message' => 'Admission ID is required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            // Check if discharge has already been initiated
            $stmt_check = $conn->prepare("SELECT id FROM discharge_clearance WHERE admission_id = ?");
            $stmt_check->bind_param("i", $admission_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                throw new Exception('Discharge process has already been initiated for this admission.');
            }
            $stmt_check->close();

            // Insert the three required clearance steps
            $steps = ['nursing', 'pharmacy', 'billing'];
            $stmt_insert = $conn->prepare("INSERT INTO discharge_clearance (admission_id, clearance_step) VALUES (?, ?)");
            foreach ($steps as $step) {
                $stmt_insert->bind_param("is", $admission_id, $step);
                $stmt_insert->execute();
            }
            $stmt_insert->close();
            
            log_activity($conn, $current_user_id, 'Discharge Initiated', null, "For Admission ID: {$admission_id}");

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Discharge process initiated. Departments have been notified.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // Action: Get the status of each clearance step for a specific request
    if ($action == 'get_discharge_status' && isset($_GET['admission_id'])) {
        $admission_id = (int)$_GET['admission_id'];
        $response = ['success' => false];

        $sql = "
            SELECT 
                dc.clearance_step, dc.is_cleared, dc.cleared_at, u.name AS cleared_by
            FROM discharge_clearance dc
            LEFT JOIN users u ON dc.cleared_by_user_id = u.id
            WHERE dc.admission_id = ?
            ORDER BY FIELD(dc.clearance_step, 'nursing', 'pharmacy', 'billing')
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $admission_id);
        $stmt->execute();
        $status_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if ($status_data) {
            $response['success'] = true;
            $response['data'] = $status_data;
        } else {
            $response['message'] = 'No clearance details found. The process may not have been initiated.';
        }
        echo json_encode($response);
        exit();
    }
    
    // --- END: ADDED CODE FOR DISCHARGE PROCESS ---


    // ==========================================================
    // --- PATIENT ENCOUNTER ACTIONS ---
    // ==========================================================
    if ($action == 'get_encounter_details' && isset($_GET['appointment_id'])) {
        $appointment_id = (int)$_GET['appointment_id'];
        $response = ['success' => false, 'data' => null];

        // Fetch patient details from appointment
        $stmt_patient = $conn->prepare("SELECT user_id as patient_id, name FROM appointments a JOIN users u ON a.user_id = u.id WHERE a.id = ?");
        $stmt_patient->bind_param("i", $appointment_id);
        $stmt_patient->execute();
        $patient_data = $stmt_patient->get_result()->fetch_assoc();
        $stmt_patient->close();

        if ($patient_data) {
            $response['patient_data'] = $patient_data;
            // Now check for an existing encounter
            $stmt_encounter = $conn->prepare("SELECT * FROM patient_encounters WHERE appointment_id = ?");
            $stmt_encounter->bind_param("i", $appointment_id);
            $stmt_encounter->execute();
            $encounter_data = $stmt_encounter->get_result()->fetch_assoc();
            $stmt_encounter->close();

            // Decode vitals JSON if it exists
            if ($encounter_data && !empty($encounter_data['vitals'])) {
                $encounter_data['vitals'] = json_decode($encounter_data['vitals'], true);
            }

            $response['success'] = true;
            $response['encounter_data'] = $encounter_data; // Will be null if no encounter exists yet
        } else {
            $response['message'] = 'Appointment not found or unauthorized.';
        }
        
        echo json_encode($response);
        exit();
    }

    if ($action == 'save_encounter' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $encounter_id = (int)($_POST['encounter_id'] ?? 0);
        $appointment_id = (int)($_POST['appointment_id'] ?? 0);
        $patient_id = (int)($_POST['patient_id'] ?? 0);
        $chief_complaint = trim($_POST['chief_complaint'] ?? '');
        $vitals = json_encode($_POST['vitals'] ?? []);
        $soap_subjective = trim($_POST['soap_subjective'] ?? '');
        $soap_objective = trim($_POST['soap_objective'] ?? '');
        $soap_assessment = trim($_POST['soap_assessment'] ?? '');
        $soap_plan = trim($_POST['soap_plan'] ?? '');
        $diagnosis = trim($_POST['diagnosis_icd10'] ?? '');

        if (empty($appointment_id) || empty($patient_id)) {
            echo json_encode(['success' => false, 'message' => 'Missing required appointment data.']);
            exit();
        }
        
        // --- Start Transaction ---
        $conn->begin_transaction();
        
        try {
            if ($encounter_id > 0) {
                // UPDATE existing encounter
                $stmt = $conn->prepare("UPDATE patient_encounters SET chief_complaint=?, vitals=?, soap_subjective=?, soap_objective=?, soap_assessment=?, soap_plan=?, diagnosis_icd10=? WHERE id=? AND doctor_id=?");
                $stmt->bind_param("sssssssii", $chief_complaint, $vitals, $soap_subjective, $soap_objective, $soap_assessment, $soap_plan, $diagnosis, $encounter_id, $current_user_id);
            } else {
                // INSERT new encounter
                $stmt = $conn->prepare("INSERT INTO patient_encounters (appointment_id, patient_id, doctor_id, chief_complaint, vitals, soap_subjective, soap_objective, soap_assessment, soap_plan, diagnosis_icd10) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisssssss", $appointment_id, $patient_id, $current_user_id, $chief_complaint, $vitals, $soap_subjective, $soap_objective, $soap_assessment, $soap_plan, $diagnosis);
            }

            if (!$stmt->execute()) {
                throw new Exception('Failed to save encounter details.');
            }
            $new_id = ($encounter_id > 0) ? $encounter_id : $conn->insert_id;
            $stmt->close();

            // --- Update the appointment status to 'completed' and token_status to 'completed' ---
            $stmt_appt = $conn->prepare("UPDATE appointments SET status = 'completed', token_status = 'completed' WHERE id = ? AND doctor_id = ?");
            $stmt_appt->bind_param("ii", $appointment_id, $current_user_id);
            if (!$stmt_appt->execute()) {
                throw new Exception('Failed to update appointment status.');
            }
            $stmt_appt->close();
            
            // --- Commit Transaction ---
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Consultation saved and appointment completed.', 'encounter_id' => $new_id]);

        } catch (Exception $e) {
            // --- Rollback Transaction on Error ---
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        
        exit();
    }


    // ==========================================================
    // --- TOKEN STATUS UPDATE ACTION (MODIFIED) ---
    // ==========================================================
    if ($action == 'update_token_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $appointment_id = (int)($_POST['appointment_id'] ?? 0);
        $new_status = $_POST['token_status'] ?? '';
        
        $allowed_statuses = ['waiting', 'in_consultation', 'completed', 'skipped'];
        
        if (empty($appointment_id) || !in_array($new_status, $allowed_statuses)) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            exit();
        }
        
        try {
            // --- MODIFIED LOGIC ---
            $sql = "UPDATE appointments SET token_status = ?";
            
            // Only set the consultation_start_time when the status is changing to 'in_consultation'
            if ($new_status == 'in_consultation') {
                // Also set it to NULL if status is changing *away* from in_consultation?
                // No, let's keep it simple. Only set it when it becomes 'in_consultation'
                // We'll also update the main appointment status to 'completed' if the token status is 'completed'
                $sql .= ", consultation_start_time = NOW()";
            }
            
            $sql .= " WHERE id = ? AND doctor_id = ?";
            // --- END MODIFIED LOGIC ---
            
            $stmt = $conn->prepare($sql); // Use the new dynamic SQL
            $stmt->bind_param("sii", $new_status, $appointment_id, $current_user_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                log_activity($conn, $current_user_id, 'Token Status Updated', null, "Appointment ID: {$appointment_id} to {$new_status}");
                echo json_encode(['success' => true, 'message' => 'Token status updated successfully']);
            } else {
                // If no rows were affected, it might be a re-click. Check if status is already set.
                $check_stmt = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND token_status = ?");
                $check_stmt->bind_param("is", $appointment_id, $new_status);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                     echo json_encode(['success' => true, 'message' => 'Token status is already set.']);
                } else {
                     echo json_encode(['success' => false, 'message' => 'No changes made or appointment not found']);
                }
                $check_stmt->close();
            }
            $stmt->close();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }

    // ==========================================================
    // --- ADMISSIONS & DISCHARGE ACTIONS ---
    // ==========================================================
    if ($action == 'get_admissions') {
        $admissions = [];
        $sql = "
            SELECT 
                adm.id, p.id as patient_id, p.name AS patient_name, p.display_user_id, adm.admission_date,
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

    // --- Get details for discharge summary modal ---
    if ($action == 'get_discharge_summary_details' && isset($_GET['admission_id'])) {
        $admission_id = (int)$_GET['admission_id'];
        $response = ['success' => false];
        
        $stmt = $conn->prepare("
            SELECT 
                p.name AS patient_name, a.admission_date,
                dc.summary_text, dc.discharge_date
            FROM admissions a
            JOIN users p ON a.patient_id = p.id
            LEFT JOIN discharge_clearance dc ON a.id = dc.admission_id
            WHERE a.id = ? AND a.doctor_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $admission_id, $current_user_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($data) {
            $response['success'] = true;
            $response['data'] = $data;
        } else {
            $response['message'] = 'Admission record not found or unauthorized.';
        }
        echo json_encode($response);
        exit();
    }
    
    // --- Save the discharge summary ---
    if ($action == 'save_discharge_summary' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $admission_id = (int)($_POST['admission_id'] ?? 0);
        $discharge_date = trim($_POST['discharge_date'] ?? '');
        $summary_text = trim($_POST['summary_text'] ?? '');

        if (empty($admission_id) || empty($discharge_date) || empty($summary_text)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }

        // This query updates the summary across all clearance steps for the admission.
        $stmt = $conn->prepare("
            UPDATE discharge_clearance 
            SET summary_text = ?, discharge_date = ?, doctor_id = ? 
            WHERE admission_id = ?
        ");
        $stmt->bind_param("ssii", $summary_text, $discharge_date, $current_user_id, $admission_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                 log_activity($conn, $current_user_id, 'Discharge Summary Created', null, "For Admission ID: {$admission_id}");
                echo json_encode(['success' => true, 'message' => 'Discharge summary saved successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No matching admission record found to update. Please ensure clearance has been initiated.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: Could not save the summary.']);
        }
        $stmt->close();
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
            SELECT a.id, a.user_id, a.appointment_date, a.slot_start_time, a.slot_end_time, 
                   a.status, a.token_number, a.token_status, p.name AS patient_name, p.display_user_id AS patient_display_id
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

        $sql .= " ORDER BY a.appointment_date ASC, a.slot_start_time ASC, a.token_number ASC";

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
                // Fetch identifier for better logging
                $sql_ident = "SELECT CASE WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number) ELSE CONCAT(w.name, ' - Bed ', acc.number) END AS identifier FROM accommodations acc LEFT JOIN wards w ON acc.ward_id = w.id WHERE acc.id = ?";
                $stmt_ident = $conn->prepare($sql_ident);
                $stmt_ident->bind_param("i", $id);
                $stmt_ident->execute();
                $identifier = $stmt_ident->get_result()->fetch_assoc()['identifier'] ?? "ID {$id}";
                $stmt_ident->close();

                log_activity($conn, $current_user_id, 'Bed Status Updated', null, "Set {$identifier} to {$new_status}");
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
        $encounter_id = isset($input['encounter_id']) && !empty($input['encounter_id']) ? (int)$input['encounter_id'] : null;

        if (empty($patient_id) || empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Patient and at least one medication are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            $stmt_pr = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, notes, status, encounter_id) VALUES (?, ?, CURDATE(), ?, 'pending', ?)");
            $stmt_pr->bind_param("iisi", $patient_id, $current_user_id, $notes, $encounter_id);
            $stmt_pr->execute();
            $prescription_id = $conn->insert_id;
            $stmt_pr->close();

            $stmt_item = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_id, dosage, frequency, quantity_prescribed) VALUES (?, ?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt_item->bind_param("iissi", $prescription_id, $item['medicine_id'], $item['dosage'], $item['frequency'], $item['quantity']);
                $stmt_item->execute();
            }
            $stmt_item->close();

            // Get patient and medicine names for detailed logging
            $stmt_patient = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $stmt_patient->bind_param("i", $patient_id);
            $stmt_patient->execute();
            $patient_name = $stmt_patient->get_result()->fetch_assoc()['name'] ?? 'N/A';
            $stmt_patient->close();

            $medicine_names = [];
            $medicine_ids = array_column($items, 'medicine_id');
            if (!empty($medicine_ids)) {
                $sql_meds = "SELECT name FROM medicines WHERE id IN (" . implode(',', array_fill(0, count($medicine_ids), '?')) . ")";
                $stmt_meds = $conn->prepare($sql_meds);
                $stmt_meds->bind_param(str_repeat('i', count($medicine_ids)), ...$medicine_ids);
                $stmt_meds->execute();
                $result_meds = $stmt_meds->get_result();
                while ($row = $result_meds->fetch_assoc()) {
                    $medicine_names[] = $row['name'];
                }
                $stmt_meds->close();
            }

            $log_details = "Issued Rx ID: {$prescription_id} to {$patient_name}. Medications: " . implode(', ', $medicine_names);
            
            $conn->commit();
            log_activity($conn, $current_user_id, 'Prescription Issued', $patient_id, $log_details);
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
            WHERE pr.id = ?
        ");
        $stmt_main->bind_param("i", $prescription_id);
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
        $office_floor = trim($_POST['office_floor'] ?? '');
        $office_room_number = trim($_POST['office_room_number'] ?? '');
    
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
        
        // Fetch old user data for comparison (before update)
        $stmt_old = $conn->prepare("
            SELECT u.name, u.email, u.phone, u.date_of_birth, u.gender, u.username,
                   d.qualifications, s.name as specialty_name, dep.name as department_name,
                   d.office_floor, d.office_room_number
            FROM users u
            LEFT JOIN doctors d ON u.id = d.user_id
            LEFT JOIN specialities s ON d.specialty_id = s.id
            LEFT JOIN departments dep ON d.department_id = dep.id
            WHERE u.id = ?
        ");
        $stmt_old->bind_param("i", $current_user_id);
        $stmt_old->execute();
        $old_user_data = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();
        
        // Get specialty and department names for the new values
        $new_specialty_name = null;
        $new_department_name = null;
        if ($specialty_id) {
            $stmt_spec = $conn->prepare("SELECT name FROM specialities WHERE id = ?");
            $stmt_spec->bind_param("i", $specialty_id);
            $stmt_spec->execute();
            $new_specialty_name = $stmt_spec->get_result()->fetch_assoc()['name'] ?? null;
            $stmt_spec->close();
        }
        if ($department_id) {
            $stmt_dept = $conn->prepare("SELECT name FROM departments WHERE id = ?");
            $stmt_dept->bind_param("i", $department_id);
            $stmt_dept->execute();
            $new_department_name = $stmt_dept->get_result()->fetch_assoc()['name'] ?? null;
            $stmt_dept->close();
        }
    
        // 3. Database Transaction
        $conn->begin_transaction();
        try {
            // Update users table
            $stmt_user = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, gender = ?, date_of_birth = ? WHERE id = ?");
            $stmt_user->bind_param("sssssi", $name, $email, $phone, $gender, $dob, $current_user_id);
            $stmt_user->execute();
            
            // Update doctors table - handle NULL values properly and add office info
            $sql_doctor = "
                INSERT INTO doctors (user_id, specialty_id, department_id, qualifications, office_floor, office_room_number) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    specialty_id = VALUES(specialty_id), 
                    department_id = VALUES(department_id), 
                    qualifications = VALUES(qualifications),
                    office_floor = VALUES(office_floor),
                    office_room_number = VALUES(office_room_number)
            ";
            $stmt_doctor = $conn->prepare($sql_doctor);
            $stmt_doctor->bind_param("iiisss", $current_user_id, $specialty_id, $department_id, $qualifications, $office_floor, $office_room_number);
            $stmt_doctor->execute();
            
            $log_details = "Updated profile details. Name: {$name}, Email: {$email}, Phone: {$phone}, Qualifications: {$qualifications}.";
            log_activity($conn, $current_user_id, 'Profile Updated', $current_user_id, $log_details);
            $conn->commit();
            $_SESSION['name'] = $name; // Update session name
            
            // Track changes for email notification
            $email_changes = [];
            if ($old_user_data['name'] !== $name) {
                $email_changes['Name'] = ['old' => $old_user_data['name'], 'new' => $name];
            }
            if ($old_user_data['email'] !== $email) {
                $email_changes['Email'] = ['old' => $old_user_data['email'], 'new' => $email];
            }
            if ($old_user_data['phone'] !== $phone) {
                $email_changes['Phone Number'] = ['old' => ($old_user_data['phone'] ?: 'Not set'), 'new' => $phone];
            }
            if (($old_user_data['date_of_birth'] ?? '') !== ($dob ?? '')) {
                $email_changes['Date of Birth'] = ['old' => ($old_user_data['date_of_birth'] ?: 'Not set'), 'new' => ($dob ?: 'Not set')];
            }
            if (($old_user_data['gender'] ?? '') !== ($gender ?? '')) {
                $email_changes['Gender'] = ['old' => ($old_user_data['gender'] ?: 'Not set'), 'new' => ($gender ?: 'Not set')];
            }
            if (($old_user_data['qualifications'] ?? '') !== ($qualifications ?? '')) {
                $email_changes['Qualifications'] = ['old' => ($old_user_data['qualifications'] ?: 'Not set'), 'new' => ($qualifications ?: 'Not set')];
            }
            if (($old_user_data['specialty_name'] ?? '') !== ($new_specialty_name ?? '')) {
                $email_changes['Specialty'] = ['old' => ($old_user_data['specialty_name'] ?: 'Not set'), 'new' => ($new_specialty_name ?: 'Not set')];
            }
            if (($old_user_data['department_name'] ?? '') !== ($new_department_name ?? '')) {
                $email_changes['Department'] = ['old' => ($old_user_data['department_name'] ?: 'Not set'), 'new' => ($new_department_name ?: 'Not set')];
            }
            if (($old_user_data['office_floor'] ?? '') !== ($office_floor ?? '')) {
                $email_changes['Office Floor'] = ['old' => ($old_user_data['office_floor'] ?: 'Not set'), 'new' => ($office_floor ?: 'Not set')];
            }
            if (($old_user_data['office_room_number'] ?? '') !== ($office_room_number ?? '')) {
                $email_changes['Office Room'] = ['old' => ($old_user_data['office_room_number'] ?: 'Not set'), 'new' => ($office_room_number ?: 'Not set')];
            }
            
            // Send email notification if there are changes
            if (!empty($email_changes)) {
                try {
                    date_default_timezone_set('Asia/Kolkata');
                    $current_datetime = date('d M Y, h:i A');
                    $email_body = getAccountModificationTemplate($name, $old_user_data['username'], $email_changes, $current_datetime, 'You (Self-Updated)');
                    
                    // Send to the updated email address
                    send_mail('MedSync', $email, 'Your MedSync Account Has Been Updated', $email_body);
                    
                    // If email was changed, also notify the old email
                    if (isset($email_changes['Email']) && !empty($old_user_data['email'])) {
                        $old_email_body = getAccountModificationTemplate(
                            $old_user_data['name'], 
                            $old_user_data['username'], 
                            $email_changes, 
                            $current_datetime, 
                            'You (Self-Updated)'
                        );
                        send_mail('MedSync', $old_user_data['email'], 'Your MedSync Account Has Been Updated', $old_email_body);
                    }
                } catch (Exception $email_error) {
                    // Email sending failed, but don't block the update
                    error_log("Failed to send profile update email: " . $email_error->getMessage());
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            // Log the actual error for debugging
            error_log("Profile update error: " . $exception->getMessage());
            error_log("Error code: " . $conn->errno);
            
            // Check for duplicate email error
            if ($conn->errno === 1062) {
                 echo json_encode(['success' => false, 'message' => 'Error: This email address is already in use by another account.']);
            } else {
                 echo json_encode(['success' => false, 'message' => 'A database error occurred: ' . $exception->getMessage()]);
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
        try {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];

            if ($new_password !== $_POST['confirm_password']) {
                throw new Exception('New password and confirmation do not match.');
            }
            if (strlen($new_password) < 8) {
                throw new Exception('New password must be at least 8 characters long.');
            }

            // Fetch user data for email notification
            $stmt = $conn->prepare("SELECT name, email, username, password FROM users WHERE id = ?");
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user_data && password_verify($current_password, $user_data['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->bind_param("si", $hashed_password, $current_user_id);
                
                if ($stmt_update->execute()) {
                    log_activity($conn, $current_user_id, 'Password Changed', $current_user_id, 'User changed their own password.');
                    
                    // Send password change confirmation email
                    try {
                        date_default_timezone_set('Asia/Kolkata');
                        $current_datetime = date('d M Y, h:i A');
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $email_body = getPasswordResetConfirmationTemplate($user_data['name'], $current_datetime, $ip_address);
                        
                        send_mail('MedSync Security Alert', $user_data['email'], 'Your MedSync Password Was Changed', $email_body);
                    } catch (Exception $email_error) {
                        // Email sending failed, but don't block the password change
                        error_log("Failed to send password change email: " . $email_error->getMessage());
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Password changed successfully. A confirmation email has been sent.']);
                } else {
                    throw new Exception('Failed to update password in the database.');
                }
                $stmt_update->close();
            } else {
                throw new Exception('Incorrect current password.');
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($action == 'updateProfilePicture') {
        try {
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
                if ($_FILES['profile_picture']['size'] > 5242880) { // 5MB limit
                    throw new Exception('File is too large. Maximum size is 5MB.');
                }

                $file_extension = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $new_filename = 'doctor_' . $current_user_id . '_' . time() . '.' . $file_extension;

                $stmt_select = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                $stmt_select->bind_param("i", $current_user_id);
                $stmt_select->execute();
                $old_picture_filename = $stmt_select->get_result()->fetch_assoc()['profile_picture'];
                $stmt_select->close();

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_dir . $new_filename)) {
                    $stmt_update = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                    $stmt_update->bind_param("si", $new_filename, $current_user_id);
                    if ($stmt_update->execute()) {
                        if ($old_picture_filename && $old_picture_filename !== 'default.png' && file_exists($upload_dir . $old_picture_filename)) {
                            unlink($upload_dir . $old_picture_filename);
                        }
                        log_activity($conn, $current_user_id, 'Profile Picture Updated', $current_user_id, 'Doctor updated their profile picture.');
                        echo json_encode(['success' => true, 'message' => 'Profile picture updated.', 'new_image_url' => '../uploads/profile_pictures/' . $new_filename]);
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
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    if ($action == 'removeProfilePicture') {
        try {
            $stmt_select = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt_select->bind_param("i", $current_user_id);
            $stmt_select->execute();
            $current_picture = $stmt_select->get_result()->fetch_assoc()['profile_picture'];
            $stmt_select->close();

            if ($current_picture && $current_picture !== 'default.png') {
                $upload_dir = '../uploads/profile_pictures/';
                $old_file_path = $upload_dir . $current_picture;
                if (file_exists($old_file_path)) {
                    unlink($old_file_path);
                }
            }

            $stmt_update = $conn->prepare("UPDATE users SET profile_picture = 'default.png' WHERE id = ?");
            $stmt_update->bind_param("i", $current_user_id);
            if ($stmt_update->execute()) {
                log_activity($conn, $current_user_id, 'Profile Picture Removed', $current_user_id, 'Doctor removed their profile picture.');
                echo json_encode(['success' => true, 'message' => 'Profile picture removed successfully.', 'new_image_url' => '../uploads/profile_pictures/default.png']);
            } else {
                throw new Exception('Failed to remove profile picture.');
            }
            $stmt_update->close();
        } catch (Exception $e) {
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
        $after_id = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
        
        $update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND receiver_id = ?");
        $update_stmt->bind_param("ii", $conversation_id, $current_user_id);
        $update_stmt->execute();
        
        if ($after_id > 0) {
            // Get only new messages after the specified ID
            $msg_stmt = $conn->prepare("SELECT id, sender_id, message_text, created_at FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at ASC");
            $msg_stmt->bind_param("ii", $conversation_id, $after_id);
        } else {
            // Get all messages
            $msg_stmt = $conn->prepare("SELECT id, sender_id, message_text, created_at FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $msg_stmt->bind_param("i", $conversation_id);
        }
        
        $msg_stmt->execute();
        $messages = $msg_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $messages]);
        exit();
    }

    if ($action == 'delete_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $message_id = (int)$_POST['message_id'];
        
        // Verify the message belongs to the current user
        $check_stmt = $conn->prepare("SELECT sender_id FROM messages WHERE id = ?");
        $check_stmt->bind_param("i", $message_id);
        $check_stmt->execute();
        $result = $check_stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Message not found.']);
            exit();
        }
        
        $message = $result->fetch_assoc();
        if ($message['sender_id'] != $current_user_id) {
            echo json_encode(['success' => false, 'message' => 'You can only delete your own messages.']);
            exit();
        }
        
        // Delete the message
        $delete_stmt = $conn->prepare("DELETE FROM messages WHERE id = ?");
        $delete_stmt->bind_param("i", $message_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Message deleted successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete message.']);
        }
        exit();
    }

    if ($action == 'get_unread_count') {
        $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $current_user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => true, 'count' => (int)$result['unread_count']]);
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
                    lo.*, 
                    p.name AS patient_name, 
                    p.display_user_id AS patient_display_id,
                    p.gender AS patient_gender,
                    p.date_of_birth AS patient_dob,
                    s.name AS staff_name
                FROM lab_orders lo
                JOIN users p ON lo.patient_id = p.id
                LEFT JOIN users s ON lo.staff_id = s.id
                WHERE lo.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        if ($data) {
            // Calculate patient age
            if ($data['patient_dob']) {
                $birthDate = new DateTime($data['patient_dob']);
                $today = new DateTime('today');
                $data['patient_age'] = $birthDate->diff($today)->y;
            } else {
                $data['patient_age'] = 'N/A';
            }

            // Decode JSON result details
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
        $patient_id = (int)($input['patient_id'] ?? 0);
        $test_names = $input['test_names'] ?? [];
        $encounter_id = isset($input['encounter_id']) && !empty($input['encounter_id']) ? (int)$input['encounter_id'] : null;

        if (empty($patient_id) || empty($test_names)) {
            echo json_encode(['success' => false, 'message' => 'Patient and at least one test name are required.']);
            exit();
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO lab_orders (patient_id, doctor_id, test_name, status, ordered_at, encounter_id) 
                 VALUES (?, ?, ?, 'ordered', NOW(), ?)"
            );
            
            foreach ($test_names as $test_name) {
                $trimmed_test_name = trim($test_name);
                if (!empty($trimmed_test_name)) {
                    $stmt->bind_param("iisi", $patient_id, $current_user_id, $trimmed_test_name, $encounter_id);
                    $stmt->execute();
                }
            }
            $stmt->close();
            
            $stmt_patient = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $stmt_patient->bind_param("i", $patient_id);
            $stmt_patient->execute();
            $patient_name = $stmt_patient->get_result()->fetch_assoc()['name'] ?? 'N/A';
            $stmt_patient->close();
            
            $log_details = "Ordered for {$patient_name}: " . implode(', ', $test_names);
            log_activity($conn, $current_user_id, 'Lab Order Placed', $patient_id, $log_details);
            
            $conn->commit();
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
    // ==========================================================
    // --- MEDICAL RECORD ACTIONS ---
    // ==========================================================
    if ($action == 'get_patient_medical_record' && isset($_GET['patient_id'])) {
        $patient_id = (int)$_GET['patient_id'];
        $response = ['success' => false, 'data' => null];

        try {
            // 1. Get Patient Details
            $stmt_details = $conn->prepare("SELECT name, display_user_id, gender, date_of_birth FROM users WHERE id = ?");
            $stmt_details->bind_param("i", $patient_id);
            $stmt_details->execute();
            $details = $stmt_details->get_result()->fetch_assoc();
            $stmt_details->close();

            if (!$details) {
                throw new Exception('Patient not found.');
            }

            // Calculate age
            if ($details['date_of_birth']) {
                $birthDate = new DateTime($details['date_of_birth']);
                $today = new DateTime('today');
                $details['age'] = $birthDate->diff($today)->y;
            } else {
                $details['age'] = 'N/A';
            }

            // 2. Get Admission History
            $stmt_admissions = $conn->prepare("
                SELECT adm.id, adm.admission_date, IF(adm.discharge_date IS NULL, 'Active', 'Discharged') as status,
                CASE WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number) ELSE CONCAT(w.name, ' - Bed ', acc.number) END AS room_bed
                FROM admissions adm
                LEFT JOIN accommodations acc ON adm.accommodation_id = acc.id
                LEFT JOIN wards w ON acc.ward_id = w.id
                WHERE adm.patient_id = ? ORDER BY adm.admission_date DESC
            ");
            $stmt_admissions->bind_param("i", $patient_id);
            $stmt_admissions->execute();
            $admissions = $stmt_admissions->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_admissions->close();

            // 3. Get Prescription History
            $stmt_prescriptions = $conn->prepare("SELECT id, prescription_date, status FROM prescriptions WHERE patient_id = ? ORDER BY prescription_date DESC");
            $stmt_prescriptions->bind_param("i", $patient_id);
            $stmt_prescriptions->execute();
            $prescriptions = $stmt_prescriptions->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_prescriptions->close();

            // 4. Get Lab Order History
            $stmt_labs = $conn->prepare("SELECT id, test_name, ordered_at, status FROM lab_orders WHERE patient_id = ? ORDER BY ordered_at DESC");
            $stmt_labs->bind_param("i", $patient_id);
            $stmt_labs->execute();
            $labs = $stmt_labs->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_labs->close();

            // 5. Get Encounter History (UPDATED)
            $stmt_encounters = $conn->prepare("
                SELECT 
                    pe.id, pe.encounter_date, pe.chief_complaint, 
                    a.id as appointment_id, d.name as doctor_name
                FROM patient_encounters pe
                JOIN users d ON pe.doctor_id = d.id
                LEFT JOIN appointments a ON pe.appointment_id = a.id
                WHERE pe.patient_id = ? 
                ORDER BY pe.encounter_date DESC
            ");
            $stmt_encounters->bind_param("i", $patient_id);
            $stmt_encounters->execute();
            $encounters = $stmt_encounters->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_encounters->close();

            $response = [
                'success' => true,
                'data' => [
                    'details' => $details,
                    'admissions' => $admissions,
                    'prescriptions' => $prescriptions,
                    'labs' => $labs,
                    'encounters' => $encounters // (UPDATED) Add encounters to the response
                ]
            ];

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        echo json_encode($response);
        exit();
    }

    // ==========================================================
    // --- NOTIFICATIONS ---
    // ==========================================================
    if ($action == 'get_notifications') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        
        // Get user role
        $stmt_role = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt_role->bind_param("i", $current_user_id);
        $stmt_role->execute();
        $user_role = $stmt_role->get_result()->fetch_assoc()['role_name'] ?? '';
        $stmt_role->close();
        
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
        $stmt->bind_param("isi", $current_user_id, $user_role, $limit);
        $stmt->execute();
        $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        echo json_encode(['success' => true, 'data' => $notifications]);
        exit();
    }

    if ($action == 'get_unread_notification_count') {
        // Get user role
        $stmt_role = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt_role->bind_param("i", $current_user_id);
        $stmt_role->execute();
        $user_role = $stmt_role->get_result()->fetch_assoc()['role_name'] ?? '';
        $stmt_role->close();
        
        $stmt = $conn->prepare(
            "SELECT COUNT(id) as unread_count FROM notifications WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')"
        );
        $stmt->bind_param("is", $current_user_id, $user_role);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['unread_count'];
        $stmt->close();
        echo json_encode(['success' => true, 'data' => ['count' => $count]]);
        exit();
    }

    if ($action == 'mark_all_notifications_read') {
        // Get user role
        $stmt_role = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
        $stmt_role->bind_param("i", $current_user_id);
        $stmt_role->execute();
        $user_role = $stmt_role->get_result()->fetch_assoc()['role_name'] ?? '';
        $stmt_role->close();
        
        $stmt = $conn->prepare(
            "UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')"
        );
        $stmt->bind_param("is", $current_user_id, $user_role);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        echo json_encode(['success' => true, 'message' => "$affected notifications marked as read"]);
        exit();
    }


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
        <div classs="rx-signature">
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

/**
 * NEW: Generates a Discharge Summary PDF and streams it to the browser.
 * @param array $data The discharge summary data.
 */
function generateDischargeSummaryPdf($data) {
    // --- Get image paths and convert to base64 ---
    $medsync_logo_path = '../images/logo.png';
    $hospital_logo_path = '../images/hospital.png';
    $medsync_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($medsync_logo_path));
    $hospital_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($hospital_logo_path));

    // Calculate age
    $age = 'N/A';
    if (!empty($data['date_of_birth'])) {
        $birthDate = new DateTime($data['date_of_birth']);
        $today = new DateTime('today');
        $age = $birthDate->diff($today)->y;
    }

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Discharge Summary</title>
        <style>
            @page { margin: 40px 50px; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; line-height: 1.6; }
            .header-table { width: 100%; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; }
            .header-table .logo { width: 60px; vertical-align: middle; }
            .hospital-details { text-align: center; }
            .patient-details-box { border: 1px solid #ccc; border-radius: 8px; padding: 15px; margin-bottom: 20px; }
            .patient-details-table { width: 100%; }
            .section { margin-bottom: 25px; }
            .section h3 { font-size: 1.2em; color: #16a085; border-bottom: 1px solid #1abc9c; padding-bottom: 5px; margin-bottom: 10px; }
            .summary-text { text-align: justify; white-space: pre-wrap; }
            .footer { position: fixed; bottom: -20px; left: 0px; right: 0px; height: 100px; }
            .signature-area { margin-top: 50px; padding-top: 10px; border-top: 1px solid #333; text-align: right; }
        </style>
    </head>
    <body>
        <table class="header-table">
            <tr>
                <td style="width:25%;"><img src="' . $medsync_logo_base64 . '" alt="MedSync Logo" class="logo"></td>
                <td style="width:50%;" class="hospital-details">
                    <h1 style="margin:0;">Discharge Summary</h1>
                    <h3 style="margin:0;">Calysta Health Institute</h3>
                </td>
                <td style="width:25%; text-align:right;"><img src="' . $hospital_logo_base64 . '" alt="Hospital Logo" class="logo"></td>
            </tr>
        </table>

        <div class="patient-details-box">
            <table class="patient-details-table">
                <tr>
                    <td style="width:50%;"><strong>Patient Name:</strong> ' . htmlspecialchars($data['patient_name']) . '</td>
                    <td style.width:50%;"><strong>Patient ID:</strong> ' . htmlspecialchars($data['patient_display_id']) . '</td>
                </tr>
                <tr>
                    <td><strong>Age / Gender:</strong> ' . $age . ' / ' . htmlspecialchars($data['gender']) . '</td>
                    <td></td>
                </tr>
                <tr>
                    <td><strong>Admission Date:</strong> ' . date("F j, Y, g:i a", strtotime($data['admission_date'])) . '</td>
                    <td><strong>Discharge Date:</strong> ' . date("F j, Y", strtotime($data['discharge_date'])) . '</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <h3>Doctor\'s Summary</h3>
            <p class="summary-text">' . nl2br(htmlspecialchars($data['summary_text'])) . '</p>
        </div>

        <div class="footer">
            <div class="signature-area">
                <p><strong>Dr. ' . htmlspecialchars($data['doctor_name']) . '</strong><br>
                ' . htmlspecialchars($data['specialty']) . '<br>
                Reg. No: ' . htmlspecialchars($data['doctor_display_id']) . '</p>
                <p style="font-size: 0.8em;">(Digitally Signed)</p>
            </div>
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
    $dompdf->stream("Discharge-Summary-".htmlspecialchars($data['patient_display_id']).".pdf", ["Attachment" => 0]);
}
?>