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

        // Prepare a query to get lab data, including patient and doctor names.
        $stmt = $conn->prepare("
            SELECT 
                lr.*,
                p.name AS patient_name,
                p.display_user_id,
                doc.name AS doctor_name
            FROM lab_results lr
            JOIN users p ON lr.patient_id = p.id
            LEFT JOIN users doc ON lr.doctor_id = doc.id
            WHERE lr.id = ? AND p.id = ?
        ");
        $stmt->bind_param("ii", $lab_result_id, $user_id);
        $stmt->execute();
        $lab_data = $stmt->get_result()->fetch_assoc();

        if ($lab_data) {
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
                        LEFT JOIN departments dept ON a.department_id = dept.id
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
                    // Fetch upcoming appointments (limit 2)
                    $stmt_app = $conn->prepare("
                        SELECT a.appointment_date, u.name as doctorName, u.profile_picture as avatar, d.specialty
                        FROM appointments a
                        JOIN users u ON a.doctor_id = u.id
                        JOIN doctors d ON u.id = d.user_id
                        WHERE a.user_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
                        ORDER BY a.appointment_date ASC LIMIT 2
                    ");
                    $stmt_app->bind_param("i", $user_id);
                    $stmt_app->execute();
                    $dashboard_data['appointments'] = $stmt_app->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_app->close();

                    // Fetch recent activity (from notifications, limit 3)
                    $stmt_act = $conn->prepare("
                        SELECT message, created_at as time FROM notifications 
                        WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')
                        ORDER BY created_at DESC LIMIT 3
                    ");
                    $stmt_act->bind_param("is", $user_id, $user_role);
                    $stmt_act->execute();
                    $activity = $stmt_act->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_act->close();
                    foreach ($activity as &$act) {
                        if (strpos(strtolower($act['message']), 'lab') !== false) $act['type'] = 'labs';
                        elseif (strpos(strtolower($act['message']), 'bill') !== false) $act['type'] = 'billing';
                        elseif (strpos(strtolower($act['message']), 'prescription') !== false) $act['type'] = 'prescriptions';
                        else $act['type'] = 'appointments';
                    }
                    $dashboard_data['activity'] = $activity;

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
                    // Base SQL query to get lab results for the logged-in user
                    // We JOIN with the 'users' table to get the doctor's name
                    $sql = "
                        SELECT 
                            lr.id,
                            lr.test_date,
                            lr.test_name,
                            lr.status,
                            lr.result_details,
                            doc.name AS doctor_name
                        FROM lab_results lr
                        LEFT JOIN users doc ON lr.doctor_id = doc.id
                        WHERE lr.patient_id = ?
                    ";
                
                    // Prepare for filtering
                    $params = [$user_id];
                    $types = "i";
                
                    // Handle search by test name
                    if (!empty($_GET['search'])) {
                        $search_term = '%' . $_GET['search'] . '%';
                        $sql .= " AND lr.test_name LIKE ?";
                        $params[] = $search_term;
                        $types .= "s";
                    }
                
                    // Handle filter by date (month and year)
                    if (!empty($_GET['date'])) {
                        $date_filter = $_GET['date'] . '-%'; // Matches YYYY-MM format
                        $sql .= " AND lr.test_date LIKE ?";
                        $params[] = $date_filter;
                        $types .= "s";
                    }
                
                    $sql .= " ORDER BY lr.test_date DESC";
                
                    $stmt = $conn->prepare($sql);
                    // Dynamically bind parameters based on the filters applied
                    $stmt->bind_param($types, ...$params); 
                    $stmt->execute();
                    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $results];
                    break;

                case 'get_medical_records':
                    $records = [];
                    $sql = "
                        -- Admissions
                        SELECT
                            a.id,
                            a.admission_date AS record_date,
                            'admission' AS record_type,
                            d.name AS title,
                            CONCAT('Admitted to ', w.name) AS details,
                            a.status AS status
                        FROM admissions a
                        JOIN departments d ON a.department_id = d.id
                        LEFT JOIN wards w ON a.ward_id = w.id
                        WHERE a.patient_id = ?

                        UNION ALL

                        -- Lab Results
                        SELECT
                            lr.id,
                            lr.test_date AS record_date,
                            'lab_result' AS record_type,
                            lr.test_name AS title,
                            CONCAT('Ordered by Dr. ', doc.name) AS details,
                            lr.status AS status
                        FROM lab_results lr
                        LEFT JOIN users doc ON lr.doctor_id = doc.id
                        WHERE lr.patient_id = ? AND lr.status = 'completed'

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
                    // --- Main query to get the user's appointments for today ---
                    $sql_tokens = "SELECT 
                                       a.token_number, 
                                       a.token_status, 
                                       a.doctor_id, 
                                       u.name as doctor_name, 
                                       d.specialty,
                                       d.office_floor,      -- NEWLY ADDED
                                       d.office_room_number -- NEWLY ADDED
                                   FROM appointments a
                                   JOIN users u ON a.doctor_id = u.id
                                   JOIN doctors d ON u.id = d.user_id
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
                    // --- Prepare statements for calculations (more efficient) ---
                    $current_token_sql = "SELECT COALESCE(MAX(token_number), 0) as current_token 
                                          FROM appointments 
                                          WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() 
                                          AND token_status IN ('in_consultation', 'completed', 'skipped')";
                    
                    $total_patients_sql = "SELECT COUNT(id) as total_patients 
                                           FROM appointments 
                                           WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'scheduled'";

                    $current_token_stmt = $conn->prepare($current_token_sql);
                    $total_patients_stmt = $conn->prepare($total_patients_sql);
                    
                    // --- Loop through each appointment and gather all required data ---
                    foreach ($todays_appointments as $appointment) {
                        // Get current serving token
                        $current_token_stmt->bind_param("i", $appointment['doctor_id']);
                        $current_token_stmt->execute();
                        $current_token_result = $current_token_stmt->get_result()->fetch_assoc();

                        // Get total patients for the doctor today
                        $total_patients_stmt->bind_param("i", $appointment['doctor_id']);
                        $total_patients_stmt->execute();
                        $total_patients_result = $total_patients_stmt->get_result()->fetch_assoc();
                        
                        // Calculate patients left
                        $patients_left = $total_patients_result['total_patients'] - $current_token_result['current_token'];

                        $tokens[] = [
                            'your_token' => $appointment['token_number'],
                            'current_token' => $current_token_result['current_token'],
                            'doctor_name' => $appointment['doctor_name'],
                            'specialty' => $appointment['specialty'],
                            'token_status' => $appointment['token_status'],
                            'office_floor' => $appointment['office_floor'],       // NEW
                            'office_room_number' => $appointment['office_room_number'], // NEW
                            'total_patients' => $total_patients_result['total_patients'], // NEW
                            'patients_left' => max(0, $patients_left) // NEW (ensure it's not negative)
                        ];
                    }
                    $current_token_stmt->close();
                    $total_patients_stmt->close();
                    
                    $response = ['success' => true, 'tokens' => $tokens, 'message' => "Live token status fetched successfully."];
                    break;
                
                // =======================================================
                // === NEWLY ADDED APPOINTMENT ENDPOINTS (GET)         ===
                // =======================================================
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
                                d.specialty
                            FROM appointments a
                            JOIN users doc_user ON a.doctor_id = doc_user.id
                            JOIN doctors d ON doc_user.id = d.user_id
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
                    // Sort upcoming appointments in ascending order
                    usort($appointments['upcoming'], function($a, $b) {
                        return strtotime($a['appointment_date']) - strtotime($b['appointment_date']);
                    });
                
                    $response = ['success' => true, 'data' => $appointments];
                    break;
                
                // START: UPDATED CODE BLOCK
                case 'get_doctors':
                    $specialty_filter = $_GET['specialty'] ?? '';
                    $name_search = $_GET['name_search'] ?? ''; // Get the name search parameter
                    
                    $sql = "SELECT 
                                d.user_id as id, 
                                u.name, 
                                d.specialty,
                                u.profile_picture
                            FROM doctors d
                            JOIN users u ON d.user_id = u.id
                            WHERE d.is_available = 1 AND u.is_active = 1";
                
                    $params = [];
                    $types = "";

                    // Add condition for name search
                    if (!empty($name_search)) {
                        $sql .= " AND u.name LIKE ?";
                        $params[] = '%' . $name_search . '%';
                        $types .= "s";
                    }
                
                    // Add condition for specialty filter
                    if (!empty($specialty_filter)) {
                        $sql .= " AND d.specialty = ?";
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
                // END: UPDATED CODE BLOCK
                
                case 'get_doctor_slots':
                    // For this example, we will return static slots. 
                    // You could expand this to read from the `doctors.slots` JSON column.
                    $mock_slots = ["09:00 AM - 10:00 AM", "10:00 AM - 11:00 AM", "11:00 AM - 12:00 PM", "02:00 PM - 03:00 PM"];
                    $response = ['success' => true, 'data' => $mock_slots];
                    break;
                
                case 'get_available_tokens':
                    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
                    $date = $_GET['date'] ?? '';
                    
                    if (empty($doctor_id) || empty($date)) {
                        throw new Exception("Doctor ID and date are required.");
                    }
                    
                    $stmt = $conn->prepare("SELECT token_number FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ?");
                    $stmt->bind_param("is", $doctor_id, $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $booked_tokens = [];
                    while ($row = $result->fetch_assoc()) {
                        $booked_tokens[] = $row['token_number'];
                    }
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => ['total' => 20, 'booked' => $booked_tokens]]; // Assuming 20 tokens per slot
                    break;
            }
        }
        // Handle POST requests for performing actions
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
                
                // =======================================================
                // === NEWLY ADDED APPOINTMENT ENDPOINTS (POST)        ===
                // =======================================================
                case 'book_appointment':
                    $doctor_id = (int)($_POST['doctorId'] ?? 0);
                    $date = $_POST['date'] ?? '';
                    $slot = $_POST['slot'] ?? ''; // e.g., "09:00 AM - 10:00 AM"
                    $token = (int)($_POST['token'] ?? 0);
                
                    if (empty($doctor_id) || empty($date) || empty($slot) || empty($token)) {
                        throw new Exception("All appointment details are required.");
                    }
                    
                    // Combine date and the start of the slot to create a DATETIME
                    $time_start = explode(' - ', $slot)[0];
                    $appointment_datetime_str = "$date $time_start";
                    $appointment_datetime = date('Y-m-d H:i:s', strtotime($appointment_datetime_str));
                
                    // Check if this token is already booked for this doctor on this day
                    $stmt_check = $conn->prepare("SELECT id FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND token_number = ?");
                    $stmt_check->bind_param("isi", $doctor_id, $date, $token);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        throw new Exception("This token has just been booked by someone else. Please select another token.");
                    }
                    $stmt_check->close();
                
                    $stmt_insert = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, appointment_date, token_number, status) VALUES (?, ?, ?, ?, 'scheduled')");
                    $stmt_insert->bind_param("iisi", $user_id, $doctor_id, $appointment_datetime, $token);
                
                    if ($stmt_insert->execute()) {
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
                
                    // Check if the appointment belongs to the current user and is in a cancellable state
                    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ? AND status = 'scheduled'");
                    $stmt->bind_param("ii", $appointment_id, $user_id);
                    
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
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
// THIS IS THE UPDATED LINE
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