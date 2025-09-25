<?php
/**
 * MedSync User/Patient Logic (user/api.php)
 *
 * Handles backend logic for the patient dashboard shell and AJAX requests.
 * - Enforces session security and role-based access control.
 * - Initializes session variables for the frontend header and profile.
 * - Manages session timeout for security.
 * - Provides API endpoints for dashboard data, notifications, profile management, and live tokens.
 */

require_once '../config.php'; // Includes session_start() and database connection ($conn)
require_once '../vendor/autoload.php'; // Assuming Composer autoload for Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

function log_user_activity($conn, $user_id, $action, $details = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

// --- Security & Session Management ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
        exit();
    }
    header("Location: ../login/index.php?error=unauthorized");
    exit();
}

// 2. Implement session timeout.
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
    header("Location: ../login/index.php?error=session_expired");
    exit();
}
$_SESSION['loggedin_time'] = time(); // Reset timeout counter

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$conn = getDbConnection();

// --- AJAX API Endpoint Logic ---
if (isset($_GET['action']) || isset($_POST['action'])) {

    // --- Special Case for PDF Download ---
    if (isset($_GET['action']) && $_GET['action'] == 'download_discharge_summary' && isset($_GET['id'])) {
        $summary_id = (int)$_GET['id'];
        
        // UPDATED QUERY: Fetch more details for the PDF template
        $stmt = $conn->prepare("
            SELECT 
                dc.*, 
                p.name as patient_name,
                p.display_user_id,
                a.admission_date,
                COALESCE(a.discharge_date, dc.discharge_date) as discharge_date,
                doc.name as doctor_name
            FROM discharge_clearance dc
            JOIN admissions a ON dc.admission_id = a.id
            JOIN users p ON a.patient_id = p.id
            LEFT JOIN users doc ON dc.doctor_id = doc.id
            WHERE dc.id = ? AND p.id = ?
        ");
        $stmt->bind_param("ii", $summary_id, $user_id);
        $stmt->execute();
        $summary_data = $stmt->get_result()->fetch_assoc();
        
        if ($summary_data) {
            log_user_activity($conn, $user_id, 'downloaded_summary', "Downloaded discharge summary for admission on " . date('M j, Y', strtotime($summary_data['admission_date'])));
            
            // Check if summary_template.php exists before including
            if (file_exists('summary_template.php')) {
                ob_start();
                include 'summary_template.php';
                $html = ob_get_clean();

                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true); // Good for images if you add them later
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                // Use a more descriptive filename
                $filename = "Discharge_Summary_" . htmlspecialchars($summary_data['patient_name']) . "_" . $summary_data['id'] . ".pdf";
                $dompdf->stream($filename, array("Attachment" => 1)); // 1 = force download
                exit();
            } else {
                 die('Error: PDF template file is missing.');
            }
        } else {
            die('Summary not found or you do not have permission to access it.');
        }
    }

    // --- Special Case for Lab Report PDF Download ---
    if (isset($_GET['action']) && $_GET['action'] == 'download_lab_report' && isset($_GET['id'])) {
        $lab_result_id = (int)$_GET['id'];

        // FIXED: Use the correct table name 'lab_orders' instead of 'lab_results'
        $stmt = $conn->prepare("
            SELECT 
                lo.*,
                p.name AS patient_name,
                p.display_user_id,
                doc.name AS doctor_name
            FROM lab_orders lo
            JOIN users p ON lo.patient_id = p.id
            LEFT JOIN users doc ON lo.doctor_id = doc.id
            WHERE lo.id = ? AND p.id = ?
        ");
        $stmt->bind_param("ii", $lab_result_id, $user_id);
        $stmt->execute();
        $lab_data = $stmt->get_result()->fetch_assoc();

        if ($lab_data) {
            log_user_activity($conn, $user_id, 'downloaded_lab_report', "Downloaded lab report for '{$lab_data['test_name']}'.");

            // Ensure the template file exists
            if (file_exists('lab_result_template.php')) {
                // Capture the template output into a variable
                ob_start();
                include 'lab_result_template.php';
                $html = ob_get_clean();

                // Setup Dompdf
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true); 
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                
                // Generate a filename and stream the PDF to the browser
                $filename = "Lab_Report_" . str_replace(' ', '_', $lab_data['test_name']) . "_" . $lab_data['id'] . ".pdf";
                $dompdf->stream($filename, array("Attachment" => 1)); // 1 = force download
                exit();
            } else {
                 die('Error: Lab report PDF template file is missing.');
            }
        } else {
            die('Lab result not found or you do not have permission to access it.');
        }
    }


    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        // Handle GET requests for fetching data
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                
                case 'get_billing_data':
                    $summary = [
                        'outstanding_balance' => 0.00,
                        'last_payment_amount' => 0.00,
                        'last_payment_date' => 'N/A'
                    ];

                    // Calculate outstanding balance
                    $stmt_due = $conn->prepare("SELECT COALESCE(SUM(amount), 0.00) as total_due FROM transactions WHERE user_id = ? AND status = 'pending'");
                    $stmt_due->bind_param("i", $user_id);
                    $stmt_due->execute();
                    $summary['outstanding_balance'] = $stmt_due->get_result()->fetch_assoc()['total_due'];
                    $stmt_due->close();

                    // Get last payment details
                    $stmt_last_paid = $conn->prepare("SELECT amount, paid_at FROM transactions WHERE user_id = ? AND status = 'paid' ORDER BY paid_at DESC LIMIT 1");
                    $stmt_last_paid->bind_param("i", $user_id);
                    $stmt_last_paid->execute();
                    $last_payment = $stmt_last_paid->get_result()->fetch_assoc();
                    if ($last_payment) {
                        $summary['last_payment_amount'] = $last_payment['amount'];
                        $summary['last_payment_date'] = date('M j, Y', strtotime($last_payment['paid_at']));
                    }
                    $stmt_last_paid->close();

                    // Get billing history with filtering
                    $sql_history = "SELECT id, created_at, description, amount, status, paid_at FROM transactions WHERE user_id = ?";
                    $params = [$user_id];
                    $types = "i";

                    // Apply status filter
                    if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'paid', 'due'])) {
                        // The frontend uses 'due', but the DB uses 'pending'
                        $db_status = ($_GET['status'] === 'due') ? 'pending' : $_GET['status'];
                        $sql_history .= " AND status = ?";
                        $params[] = $db_status;
                        $types .= "s";
                    }

                    // Apply date filter (by month)
                    if (!empty($_GET['date'])) {
                        $date_filter = $_GET['date'] . '-%'; // e.g., '2025-09-%'
                        $sql_history .= " AND created_at LIKE ?";
                        $params[] = $date_filter;
                        $types .= "s";
                    }
                    
                    $sql_history .= " ORDER BY created_at DESC";

                    $stmt_history = $conn->prepare($sql_history);
                    $stmt_history->bind_param($types, ...$params);
                    $stmt_history->execute();
                    $history = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_history->close();

                    $response = ['success' => true, 'data' => ['summary' => $summary, 'history' => $history]];
                    break;
                
                case 'get_discharge_summaries':
                    $stmt = $conn->prepare("
                        SELECT 
                            dc.id,
                            a.admission_date,
                            COALESCE(a.discharge_date, dc.discharge_date) as discharge_date,
                            doc.name as doctor_name,
                            dept.name as department_name,
                            dc.summary_text,
                            dc.notes
                        FROM discharge_clearance dc
                        JOIN admissions a ON dc.admission_id = a.id
                        LEFT JOIN users doc ON dc.doctor_id = doc.id
                        LEFT JOIN doctors dr ON doc.id = dr.user_id
                        LEFT JOIN departments dept ON dr.department_id = dept.id
                        WHERE a.patient_id = ? AND (a.discharge_date IS NOT NULL OR dc.discharge_date IS NOT NULL)
                        ORDER BY discharge_date DESC
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $summaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $summaries];
                    break;
                
                case 'get_dashboard_data':
                    $dashboard_data = [];
                    // Fetch upcoming appointments (limit 2) - UPDATED QUERY
                    $stmt_app = $conn->prepare("
                        SELECT 
                            a.appointment_date, 
                            u.name as doctorName, 
                            u.profile_picture as avatar, 
                            sp.name as specialty
                        FROM appointments a
                        JOIN users u ON a.doctor_id = u.id
                        JOIN doctors d ON u.id = d.user_id
                        LEFT JOIN specialities sp ON d.specialty_id = sp.id
                        WHERE a.user_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
                        ORDER BY a.appointment_date ASC LIMIT 2
                    ");
                    $stmt_app->bind_param("i", $user_id);
                    $stmt_app->execute();
                    $dashboard_data['appointments'] = $stmt_app->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_app->close();

                    // Fetch recent activity (from the user's own activity log, limit 5)
                    $stmt_act = $conn->prepare("
                        SELECT action, details, created_at as time 
                        FROM activity_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $stmt_act->bind_param("i", $user_id);
                    $stmt_act->execute();
                    $dashboard_data['activity'] = $stmt_act->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_act->close();

                    // Fetch live token for today, if any
                    $stmt_token = $conn->prepare("
                        SELECT a.token_number AS yours, u.name AS doctorName,
                            (SELECT COALESCE(MAX(token_number), 0) FROM appointments WHERE doctor_id = a.doctor_id AND DATE(appointment_date) = CURDATE() AND token_status IN ('in_consultation', 'skipped', 'completed')) AS current
                        FROM appointments a JOIN users u ON a.doctor_id = u.id
                        WHERE a.user_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled' LIMIT 1
                    ");
                    $stmt_token->bind_param("i", $user_id);
                    $stmt_token->execute();
                    $token_result = $stmt_token->get_result();
                    $dashboard_data['token'] = $token_result->num_rows > 0 ? $token_result->fetch_assoc() : null;
                    $stmt_token->close();
                    
                    $response = ['success' => true, 'data' => $dashboard_data];
                    break;

                case 'get_lab_results':
                    // FIXED: Use the correct table name 'lab_orders' instead of 'lab_results'
                    $sql = "
                        SELECT 
                            lo.id,
                            lo.test_date,
                            lo.test_name,
                            lo.status,
                            lo.result_details,
                            doc.name AS doctor_name
                        FROM lab_orders lo
                        LEFT JOIN users doc ON lo.doctor_id = doc.id
                        WHERE lo.patient_id = ?
                    ";
                
                    $params = [$user_id];
                    $types = "i";
                
                    if (!empty($_GET['search'])) {
                        $search_term = '%' . $_GET['search'] . '%';
                        $sql .= " AND lo.test_name LIKE ?";
                        $params[] = $search_term;
                        $types .= "s";
                    }
                
                    if (!empty($_GET['date'])) {
                        $date_filter = $_GET['date'] . '-%';
                        $sql .= " AND lo.test_date LIKE ?";
                        $params[] = $date_filter;
                        $types .= "s";
                    }
                
                    $sql .= " ORDER BY lo.test_date DESC";
                
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params); 
                    $stmt->execute();
                    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $results];
                    break;

                case 'get_medical_records':
                    $records = [];
                    // FIXED: Use the correct table name 'lab_orders' in the UNION and corrected admissions join
                    $sql = "
                        -- Admissions
                        SELECT
                            a.id,
                            a.admission_date AS record_date,
                            'admission' AS record_type,
                            d.name AS title,
                            CONCAT('Admitted under Dr. ', doc.name) AS details,
                            CASE WHEN a.discharge_date IS NULL THEN 'Admitted' ELSE 'Discharged' END AS status
                        FROM admissions a
                        JOIN users doc ON a.doctor_id = doc.id
                        JOIN doctors dr ON doc.id = dr.user_id
                        JOIN departments d ON dr.department_id = d.id
                        WHERE a.patient_id = ?

                        UNION ALL

                        -- Lab Results
                        SELECT
                            lo.id,
                            lo.test_date AS record_date,
                            'lab_result' AS record_type,
                            lo.test_name AS title,
                            CONCAT('Ordered by Dr. ', doc.name) AS details,
                            lo.status AS status
                        FROM lab_orders lo
                        LEFT JOIN users doc ON lo.doctor_id = doc.id
                        WHERE lo.patient_id = ? AND lo.status = 'completed'

                        UNION ALL

                        -- Prescriptions
                        SELECT
                            p.id,
                            p.prescription_date AS record_date,
                            'prescription' AS record_type,
                            'New Prescription Issued' AS title,
                            CONCAT('Prescribed by Dr. ', doc.name) AS details,
                            p.status AS status
                        FROM prescriptions p
                        JOIN users doc ON p.doctor_id = doc.id
                        WHERE p.patient_id = ?

                        ORDER BY record_date DESC;
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $records = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $records];
                    break;

                case 'get_notifications':
                    $filter = $_GET['filter'] ?? 'all';
                    $sql = "SELECT id, message, is_read, created_at as timestamp FROM notifications 
                            WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')";
                    if ($filter === 'unread') $sql .= " AND is_read = 0";
                    $sql .= " ORDER BY created_at DESC";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $user_id, $user_role);
                    $stmt->execute();
                    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $stmt_count = $conn->prepare("SELECT COUNT(id) as unread_count FROM notifications WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all') AND is_read = 0");
                    $stmt_count->bind_param("is", $user_id, $user_role);
                    $stmt_count->execute();
                    $unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'];
                    $stmt_count->close();

                    foreach ($notifications as &$notification) {
                        if (strpos(strtolower($notification['message']), 'lab') !== false) $notification['type'] = 'labs';
                        elseif (strpos(strtolower($notification['message']), 'bill') !== false || strpos(strtolower($notification['message']), 'payment') !== false) $notification['type'] = 'billing';
                        elseif (strpos(strtolower($notification['message']), 'prescription') !== false) $notification['type'] = 'prescriptions';
                        else $notification['type'] = 'appointments';
                    }
                    $response = ['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count];
                    break;
                
                case 'get_live_tokens':
                    $sql_tokens = "SELECT 
                                       a.token_number, 
                                       a.token_status, 
                                       a.doctor_id, 
                                       u.name as doctor_name, 
                                       sp.name as specialty,
                                       d.office_floor,
                                       d.office_room_number
                                   FROM appointments a
                                   JOIN users u ON a.doctor_id = u.id
                                   LEFT JOIN doctors d ON u.id = d.user_id
                                   LEFT JOIN specialities sp ON d.specialty_id = sp.id
                                   WHERE a.user_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled'";
                    
                    $stmt_tokens = $conn->prepare($sql_tokens);
                    $stmt_tokens->bind_param("i", $user_id);
                    $stmt_tokens->execute();
                    $todays_appointments = $stmt_tokens->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_tokens->close();

                    if (empty($todays_appointments)) {
                        $response = ['success' => true, 'tokens' => [], 'message' => "No active tokens for today."];
                        break;
                    }

                    $tokens = [];
                    $current_token_sql = "SELECT COALESCE(MAX(token_number), 0) as current_token 
                                          FROM appointments 
                                          WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() 
                                          AND token_status IN ('in_consultation', 'completed', 'skipped')";
                    
                    $total_patients_sql = "SELECT COUNT(id) as total_patients 
                                           FROM appointments 
                                           WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'scheduled'";

                    $current_token_stmt = $conn->prepare($current_token_sql);
                    $total_patients_stmt = $conn->prepare($total_patients_sql);
                    
                    foreach ($todays_appointments as $appointment) {
                        $current_token_stmt->bind_param("i", $appointment['doctor_id']);
                        $current_token_stmt->execute();
                        $current_token_result = $current_token_stmt->get_result()->fetch_assoc();

                        $total_patients_stmt->bind_param("i", $appointment['doctor_id']);
                        $total_patients_stmt->execute();
                        $total_patients_result = $total_patients_stmt->get_result()->fetch_assoc();
                        
                        $patients_left = $total_patients_result['total_patients'] - $current_token_result['current_token'];

                        $tokens[] = [
                            'your_token' => $appointment['token_number'],
                            'current_token' => $current_token_result['current_token'],
                            'doctor_name' => $appointment['doctor_name'],
                            'specialty' => $appointment['specialty'],
                            'token_status' => $appointment['token_status'],
                            'office_floor' => $appointment['office_floor'],
                            'office_room_number' => $appointment['office_room_number'],
                            'total_patients' => $total_patients_result['total_patients'],
                            'patients_left' => max(0, $patients_left)
                        ];
                    }
                    $current_token_stmt->close();
                    $total_patients_stmt->close();
                    
                    $response = ['success' => true, 'tokens' => $tokens, 'message' => "Live token status fetched successfully."];
                    break;
                
                case 'get_appointments':
                    $appointments = [
                        'upcoming' => [],
                        'past' => []
                    ];
                
                    $sql = "SELECT 
                                a.id, 
                                a.appointment_date, 
                                a.token_number, 
                                a.status,
                                doc_user.name as doctor_name,
                                sp.name AS specialty
                            FROM appointments a
                            JOIN users doc_user ON a.doctor_id = doc_user.id
                            JOIN doctors d ON doc_user.id = d.user_id
                            LEFT JOIN specialities sp ON d.specialty_id = sp.id
                            WHERE a.user_id = ?
                            ORDER BY a.appointment_date DESC";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                
                    while ($row = $result->fetch_assoc()) {
                        $is_upcoming = (new DateTime($row['appointment_date']) >= new DateTime('today')) && ($row['status'] == 'scheduled');
                        if ($is_upcoming) {
                            $appointments['upcoming'][] = $row;
                        } else {
                            $appointments['past'][] = $row;
                        }
                    }
                    $stmt->close();
                    usort($appointments['upcoming'], function($a, $b) {
                        return strtotime($a['appointment_date']) - strtotime($b['appointment_date']);
                    });
                
                    $response = ['success' => true, 'data' => $appointments];
                    break;
                
                case 'get_doctors':
                    $specialty_filter = $_GET['specialty'] ?? '';
                    $name_search = $_GET['name_search'] ?? '';

                    $sql = "SELECT
                                d.user_id as id,
                                u.name,
                                sp.name AS specialty,
                                u.profile_picture
                            FROM doctors d
                            JOIN users u ON d.user_id = u.id
                            LEFT JOIN specialities sp ON d.specialty_id = sp.id
                            WHERE d.is_available = 1 AND u.is_active = 1";

                    $params = [];
                    $types = "";

                    if (!empty($name_search)) {
                        $sql .= " AND u.name LIKE ?";
                        $params[] = '%' . $name_search . '%';
                        $types .= "s";
                    }

                    if (!empty($specialty_filter)) {
                        $sql .= " AND sp.name = ?";
                        $params[] = $specialty_filter;
                        $types .= "s";
                    }

                    $sql .= " ORDER BY u.name ASC";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $response = ['success' => true, 'data' => $doctors];
                    break;
                
                case 'get_doctor_slots':
                    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
                    $date = $_GET['date'] ?? '';

                    if (empty($doctor_id) || empty($date)) {
                        throw new Exception("Doctor ID and date are required.");
                    }

                    // Define all possible slots for a doctor's schedule
                    $all_possible_slots = [
                        "09:00 AM - 10:00 AM", 
                        "10:00 AM - 11:00 AM", 
                        "11:00 AM - 12:00 PM", 
                        "02:00 PM - 03:00 PM",
                        "03:00 PM - 04:00 PM"
                    ];

                    // Find which slots are already booked for that doctor on that date
                    $stmt = $conn->prepare("
                        SELECT appointment_date 
                        FROM appointments 
                        WHERE doctor_id = ? 
                        AND DATE(appointment_date) = ? 
                        AND status = 'scheduled'
                    ");
                    $stmt->bind_param("is", $doctor_id, $date);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    $booked_slots = [];
                    while ($row = $result->fetch_assoc()) {
                        $booked_hour = (int)date('H', strtotime($row['appointment_date']));
                        // Determine which slot the booked hour falls into
                        if ($booked_hour >= 9 && $booked_hour < 10) $booked_slots[] = "09:00 AM - 10:00 AM";
                        else if ($booked_hour >= 10 && $booked_hour < 11) $booked_slots[] = "10:00 AM - 11:00 AM";
                        else if ($booked_hour >= 11 && $booked_hour < 12) $booked_slots[] = "11:00 AM - 12:00 PM";
                        else if ($booked_hour >= 14 && $booked_hour < 15) $booked_slots[] = "02:00 PM - 03:00 PM";
                        else if ($booked_hour >= 15 && $booked_hour < 16) $booked_slots[] = "03:00 PM - 04:00 PM";
                    }
                    $stmt->close();
                    
                    // Return only the slots that are not in the booked list
                    $available_slots = array_diff($all_possible_slots, $booked_slots);

                    $response = ['success' => true, 'data' => array_values($available_slots)]; // Re-index array
                    break;
                
                case 'get_available_tokens':
                    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
                    $date = $_GET['date'] ?? '';
                    
                    if (empty($doctor_id) || empty($date)) {
                        throw new Exception("Doctor ID and date are required.");
                    }
                    
                    // ===== THIS IS THE FIX =====
                    // Only select tokens from appointments that are currently scheduled.
                    $stmt = $conn->prepare("SELECT token_number FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status = 'scheduled'");
                    $stmt->bind_param("is", $doctor_id, $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $booked_tokens = [];
                    while ($row = $result->fetch_assoc()) {
                        $booked_tokens[] = $row['token_number'];
                    }
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => ['total' => 20, 'booked' => $booked_tokens]];
                    break;
            }
        }
        elseif (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'mark_read':
                    if (!isset($_POST['id'])) throw new Exception('Notification ID is required.');
                    $notification_id = (int)$_POST['id'];
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (recipient_user_id = ? OR recipient_role = ?)");
                    $stmt->bind_param("iis", $notification_id, $user_id, $user_role);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Notification marked as read.'];
                    } else {
                        throw new Exception('Failed to update notification status.');
                    }
                    $stmt->close();
                    break;

                case 'mark_all_read':
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all') AND is_read = 0");
                    $stmt->bind_param("is", $user_id, $user_role);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'All notifications marked as read.'];
                    } else {
                        throw new Exception('Failed to update notifications.');
                    }
                    $stmt->close();
                    break;

                case 'update_personal_info':
                    $name = trim($_POST['name'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $dob = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
                    $gender = trim($_POST['gender'] ?? '');

                    if (empty($name) || empty($email)) throw new Exception('Full name and email are required.');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email format.');

                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->bind_param("si", $email, $user_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) throw new Exception('This email address is already in use.');
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $dob, $gender, $user_id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Personal information updated successfully.'];
                    } else {
                        throw new Exception('Failed to update information.');
                    }
                    $stmt->close();
                    break;
                
                case 'change_password':
                    $current_password = $_POST['current_password'] ?? '';
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if (empty($current_password) || empty($new_password) || empty($confirm_password)) throw new Exception('All password fields are required.');
                    if ($new_password !== $confirm_password) throw new Exception('New password and confirmation do not match.');
                    if (strlen($new_password) < 8) throw new Exception('New password must be at least 8 characters long.');

                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$user || !password_verify($current_password, $user['password'])) throw new Exception('The current password you entered is incorrect.');
                    
                    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_password_hashed, $user_id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Password changed successfully.'];
                    } else {
                        throw new Exception('Failed to update password.');
                    }
                    $stmt->close();
                    break;
                
                case 'update_profile_picture':
                    if (!isset($_FILES['profile_picture'])) throw new Exception('No file was uploaded.');

                    $file = $_FILES['profile_picture'];
                    $upload_dir = '../uploads/profile_pictures/';
                    
                    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('File upload error. Code: ' . $file['error']);
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array(mime_content_type($file['tmp_name']), $allowed_types)) throw new Exception('Invalid file type. Please upload a JPG, PNG, or GIF.');
                    if ($file['size'] > 2097152) throw new Exception('File size exceeds the 2MB limit.');
                    
                    $stmt_old = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt_old->bind_param("i", $user_id);
                    $stmt_old->execute();
                    $old_pic = $stmt_old->get_result()->fetch_assoc()['profile_picture'];
                    $stmt_old->close();

                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_filename, $user_id);
                        if ($stmt->execute()) {
                            if ($old_pic && $old_pic !== 'default.png' && file_exists($upload_dir . $old_pic)) {
                                @unlink($upload_dir . $old_pic);
                            }
                            $response = ['success' => true, 'message' => 'Profile picture updated!', 'filepath' => $new_filename];
                        } else {
                            @unlink($upload_path);
                            throw new Exception('Database update failed.');
                        }
                        $stmt->close();
                    } else {
                        throw new Exception('Failed to move uploaded file.');
                    }
                    break;
                
                case 'book_appointment':
                    $doctor_id = (int)($_POST['doctorId'] ?? 0);
                    $date = $_POST['date'] ?? '';
                    $token = (int)($_POST['token'] ?? 0);
                
                    // Validate that the essential details are present
                    if (empty($doctor_id) || empty($date) || empty($token)) {
                        throw new Exception("Doctor, date, and token are required.");
                    }
                    
                    // --- NEW VALIDATION: Check if user already has a booking with this doctor on this day ---
                    $stmt_user_check = $conn->prepare("
                        SELECT id FROM appointments 
                        WHERE user_id = ? AND doctor_id = ? AND DATE(appointment_date) = ? AND status = 'scheduled'
                    ");
                    $stmt_user_check->bind_param("iis", $user_id, $doctor_id, $date);
                    $stmt_user_check->execute();
                    if ($stmt_user_check->get_result()->num_rows > 0) {
                        $stmt_user_check->close();
                        throw new Exception("You already have an appointment scheduled with this doctor on this day.");
                    }
                    $stmt_user_check->close();
                    // --- END OF NEW VALIDATION ---
                    
                    // Set a fixed time for the appointment datetime
                    $appointment_datetime = date('Y-m-d H:i:s', strtotime("$date 09:00:00"));
                
                    // Check if the token is already booked for that doctor on that date
                    $stmt_check = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND token_number = ? AND status = 'scheduled'");
                    $stmt_check->bind_param("isi", $doctor_id, $date, $token);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        throw new Exception("This token has just been booked by someone else. Please select another token.");
                    }
                    $stmt_check->close();
                
                    // Insert the new appointment with the fixed time
                    $stmt_insert = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, appointment_date, token_number, status) VALUES (?, ?, ?, ?, 'scheduled')");
                    $stmt_insert->bind_param("iisi", $user_id, $doctor_id, $appointment_datetime, $token);
                
                    if ($stmt_insert->execute()) {
                        $stmt_doctor = $conn->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt_doctor->bind_param("i", $doctor_id);
                        $stmt_doctor->execute();
                        $doctor_name = $stmt_doctor->get_result()->fetch_assoc()['name'];
                        $stmt_doctor->close();
                        log_user_activity($conn, $user_id, 'booked_appointment', "Booked an appointment with Dr. {$doctor_name} for {$date}.");
                        
                        $response = ['success' => true, 'message' => 'Appointment booked successfully!'];
                    } else {
                        throw new Exception("Failed to book the appointment. Please try again.");
                    }
                    $stmt_insert->close();
                    break;
                    
                case 'cancel_appointment':
                    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
                
                    if (empty($appointment_id)) {
                        throw new Exception("Appointment ID is required.");
                    }
                
                    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'scheduled'");
                    $stmt->bind_param("ii", $appointment_id, $user_id);
                    
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            log_user_activity($conn, $user_id, 'cancelled_appointment', "Cancelled appointment ID: {$appointment_id}.");
                            $response = ['success' => true, 'message' => 'Appointment cancelled successfully.'];
                        } else {
                            throw new Exception("Could not cancel this appointment. It may have already started or does not exist.");
                        }
                    } else {
                        throw new Exception("Failed to cancel the appointment.");
                    }
                    $stmt->close();
                    break;
            }
        }

    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// --- Standard Page Load Data Preparation ---
$user_details = [];
$stmt = $conn->prepare("SELECT username, name, email, phone, date_of_birth, gender, profile_picture FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user_details = $result->fetch_assoc();
    } else {
        session_unset();
        session_destroy();
        header("Location: ../login/index.php?error=user_not_found");
        exit();
    }
    $stmt->close();
} else {
    error_log("Database statement preparation failed: " . $conn->error);
    die("An internal error occurred. Please try again later.");
}

$unread_notification_count = 0;
$stmt_count = $conn->prepare("SELECT COUNT(id) as unread_count FROM notifications WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all') AND is_read = 0");
if ($stmt_count) {
    $stmt_count->bind_param("is", $user_id, $user_role);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    if($row = $result_count->fetch_assoc()) {
        $unread_notification_count = $row['unread_count'];
    }
    $stmt_count->close();
} else {
     error_log("Database statement preparation failed for notification count: " . $conn->error);
}

$username = isset($user_details['name']) && !empty($user_details['name']) ? htmlspecialchars($user_details['name']) : 'User';
$display_user_id = isset($_SESSION['display_user_id']) ? htmlspecialchars($_SESSION['display_user_id']) : 'N/A';
$profile_picture = isset($user_details['profile_picture']) ? htmlspecialchars($user_details['profile_picture']) : 'default.png';

date_default_timezone_set('Asia/Kolkata'); 
$current_hour = date('G');
if ($current_hour < 12) {
    $greeting = "Good Morning";
} elseif ($current_hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>