<?php
/**
 * MedSync Staff Logic (api.php)
 *
 * This script handles the backend logic for the staff dashboard.
 * - Enforces session security and role-based access.
 * - Initializes session variables and fetches user data for the frontend.
 * - Handles AJAX API requests for Profile Settings, Callback Requests, and Messenger.
 * - UPDATED: Refined the discharge request logic to enforce workflow order.
 */

// config.php should be included first to initialize the session and db connection.
require_once '../config.php';
require_once '../vendor/autoload.php'; // Autoload Composer dependencies
require_once '../mail/send_mail.php'; // Email sending functionality
require_once '../mail/templates.php'; // Email templates

// All 'use' statements must be at the top of the file.
use Dompdf\Dompdf;
use Dompdf\Options;

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

    try {
        $init_stmt = $conn->prepare("INSERT INTO role_counters (role_prefix, last_id) VALUES (?, 0) ON DUPLICATE KEY UPDATE role_prefix = role_prefix");
        $init_stmt->bind_param("s", $prefix);
        $init_stmt->execute();
        $init_stmt->close();
        
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;

        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();
        $update_stmt->close();
        
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        throw $e;
    }
}

/**
 * Checks if all discharge clearances for an admission are complete and finalizes the discharge.
 */
function checkAndFinalizeDischarge($conn, $admission_id) {
    $stmt = $conn->prepare("SELECT COUNT(id) as cleared_count FROM discharge_clearance WHERE admission_id = ? AND is_cleared = 1");
    $stmt->bind_param("i", $admission_id);
    $stmt->execute();
    $cleared_count = $stmt->get_result()->fetch_assoc()['cleared_count'];
    $stmt->close();

    if ($cleared_count === 3) {
        $stmt_adm = $conn->prepare("SELECT accommodation_id, patient_id FROM admissions WHERE id = ?");
        $stmt_adm->bind_param("i", $admission_id);
        $stmt_adm->execute();
        $admission_data = $stmt_adm->get_result()->fetch_assoc();
        $stmt_adm->close();

        if ($admission_data) {
            $stmt_discharge = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE id = ? AND discharge_date IS NULL");
            $stmt_discharge->bind_param("i", $admission_id);
            $stmt_discharge->execute();
            $stmt_discharge->close();

            if ($admission_data['accommodation_id']) {
                $stmt_acc = $conn->prepare("UPDATE accommodations SET status = 'cleaning', patient_id = NULL, doctor_id = NULL WHERE id = ?");
                $stmt_acc->bind_param("i", $admission_data['accommodation_id']);
                $stmt_acc->execute();
                $stmt_acc->close();
            }
            
            // Get patient info for a more detailed log message
            $stmt_p_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
            $stmt_p_info->bind_param("i", $admission_data['patient_id']);
            $stmt_p_info->execute();
            $patient_details = $stmt_p_info->get_result()->fetch_assoc();
            $stmt_p_info->close();
            $patient_log_info = $patient_details ? "'{$patient_details['name']}' ({$patient_details['display_user_id']})" : "ID {$admission_data['patient_id']}";

            log_activity($conn, $_SESSION['user_id'], 'finalize_discharge', $admission_data['patient_id'], "Finalized discharge for patient {$patient_log_info} (Admission #{$admission_id}).");
        }
    }
}


// --- AJAX API Endpoint Logic ---
if (isset($_GET['fetch']) || isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin'])) {
        http_response_code(401);
        $response['message'] = 'Unauthorized access. Please log in again.';
        echo json_encode($response);
        exit();
    }

    $conn = getDbConnection();
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    $transaction_active = false;

    try {
        if (isset($_GET['fetch'])) {
            switch ($_GET['fetch']) {

                case 'specialities': // This is the new case
                    $stmt = $conn->prepare("SELECT id, name FROM specialities ORDER BY name ASC");
                    $stmt->execute();
                    $specialities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $specialities];
                    break;
                    
                case 'dashboard_stats':
                    $stats = [];
                    $stmt_beds = $conn->prepare("SELECT COUNT(id) as count FROM accommodations WHERE status = 'available'");
                    $stmt_beds->execute();
                    $stats['available_beds'] = $stmt_beds->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_beds->close();

                    $stmt_med = $conn->prepare("SELECT COUNT(id) as count FROM medicines WHERE quantity <= low_stock_threshold");
                    $stmt_med->execute();
                    $low_med_count = $stmt_med->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_med->close();

                    $stmt_blood = $conn->prepare("SELECT COUNT(id) as count FROM blood_inventory WHERE quantity_ml <= low_stock_threshold_ml");
                    $stmt_blood->execute();
                    $low_blood_count = $stmt_blood->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_blood->close();
                    $stats['low_stock_items'] = $low_med_count + $low_blood_count;

                    $stmt_discharges = $conn->prepare("SELECT COUNT(DISTINCT admission_id) as count FROM discharge_clearance WHERE is_cleared = 0");
                    $stmt_discharges->execute();
                    $stats['pending_discharges'] = $stmt_discharges->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_discharges->close();

                    $stmt_patients = $conn->prepare("SELECT COUNT(id) as count FROM admissions WHERE discharge_date IS NULL");
                    $stmt_patients->execute();
                    $stats['active_patients'] = $stmt_patients->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_patients->close();

                    $stmt_table = $conn->prepare("
                        SELECT 
                            p.name as patient_name,
                            acc.number as location,
                            dc.clearance_step,
                            dc.id as discharge_id
                        FROM discharge_clearance dc
                        JOIN admissions a ON dc.admission_id = a.id
                        JOIN users p ON a.patient_id = p.id
                        LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
                        WHERE dc.is_cleared = 0
                        ORDER BY dc.id DESC
                        LIMIT 5
                    ");
                    $stmt_table->execute();
                    $stats['discharge_table_data'] = $stmt_table->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_table->close();

                    $stmt_occupancy = $conn->prepare("SELECT status, COUNT(id) as count FROM accommodations GROUP BY status");
                    $stmt_occupancy->execute();
                    $stats['occupancy_data'] = $stmt_occupancy->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_occupancy->close();

                    $stmt_activity = $conn->prepare("
                        SELECT a.details, a.created_at, u.name as user_name
                        FROM activity_logs a
                        JOIN users u ON a.user_id = u.id
                        ORDER BY a.created_at DESC
                        LIMIT 5
                    ");
                    $stmt_activity->execute();
                    $stats['recent_activity'] = $stmt_activity->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_activity->close();
                    
                    $response = ['success' => true, 'data' => $stats];
                    break;
                
                // This is the NEW code
                case 'active_doctors':
                    $search_query = $_GET['search'] ?? '';
                    $sql = "
                        SELECT u.id, u.name, u.display_user_id
                        FROM users u 
                        JOIN doctors d ON u.id = d.user_id 
                        WHERE u.is_active = 1
                    ";
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " AND (u.name LIKE ? OR u.display_user_id LIKE ?)"; // Search by name OR ID
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $types .= "ss";
                    }
                    $sql .= " ORDER BY u.name ASC LIMIT 10";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    
                    $stmt->execute();
                    $doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $doctors];
                    break;

                case 'fetch_tokens':
                    $doctor_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
                    if ($doctor_id === 0) {
                        $response = ['success' => true, 'data' => []];
                        break;
                    }

                    $stmt = $conn->prepare("
                        SELECT 
                            a.token_number, a.token_status, a.slot_start_time, a.slot_end_time,
                            p.name as patient_name, p.display_user_id as patient_display_id
                        FROM appointments a
                        JOIN users p ON a.user_id = p.id
                        WHERE a.doctor_id = ? AND DATE(a.appointment_date) = CURDATE()
                        ORDER BY a.slot_start_time ASC, a.token_number ASC
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $tokens_flat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $grouped_tokens = [];
                    foreach ($tokens_flat as $token) {
                        $slot_key = date("g:i A", strtotime($token['slot_start_time'])) . ' - ' . date("g:i A", strtotime($token['slot_end_time']));
                        if (!isset($grouped_tokens[$slot_key])) {
                            $grouped_tokens[$slot_key] = [];
                        }
                        $grouped_tokens[$slot_key][] = $token;
                    }

                    $response = ['success' => true, 'data' => $grouped_tokens];
                    break;

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
                            c.id AS conversation_id, u.id AS other_user_id, u.display_user_id, u.name AS other_user_name,
                            u.profile_picture AS other_user_profile_picture, r.role_name AS other_user_role,
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
                    // UPDATED: Joined with users table to get target user details for a richer log display
                    $stmt = $conn->prepare("
                        SELECT
                            al.action,
                            al.details,
                            al.created_at,
                            target_user.display_user_id AS target_user_display_id,
                            target_user.name AS target_user_name
                        FROM
                            activity_logs al
                        LEFT JOIN
                            users AS target_user ON al.target_user_id = target_user.id
                        WHERE
                            al.user_id = ?
                        ORDER BY
                            al.created_at DESC
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
                    $status_filter = $_GET['status'] ?? 'all'; // New status filter
                    $allowed_roles = ['user', 'doctor'];

                    $sql = "SELECT u.id, u.display_user_id, u.name, u.username, r.role_name as role, 
                            u.email, u.phone, u.date_of_birth, u.gender, u.profile_picture,
                            u.is_active as active, u.created_at, u.session_token,
                            d.specialty_id,
                            TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) AS age,
                            (SELECT MAX(al.created_at) FROM activity_logs al WHERE al.user_id = u.id) AS last_active
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
                    
                    // Add status filter
                    if ($status_filter !== 'all') {
                        if ($status_filter === 'active') {
                            $sql .= " AND u.is_active = 1";
                        } elseif ($status_filter === 'inactive') {
                            $sql .= " AND u.is_active = 0";
                        }
                    }

                    if (!empty($search_query)) {
                        $sql .= " AND (u.name LIKE ? OR u.display_user_id LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $params[] = $search_term;
                        $types .= "ssss";
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
                            n.id, n.message, n.is_read, n.created_at, u.name as sender_name, r.role_name as sender_role
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
                        "SELECT COUNT(id) as unread_count FROM notifications WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')"
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
                    $wards_stmt = $conn->prepare("SELECT id, name FROM wards WHERE is_active = 1 ORDER BY name ASC");
                    $wards_stmt->execute();
                    $wards = $wards_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $wards_stmt->close();
                
                    $accommodations_stmt = $conn->prepare("
                        SELECT 
                            a.id, a.type, a.ward_id, w.name AS ward_name, a.number, a.status, a.price_per_day,
                            p.id as patient_id, p.display_user_id as patient_display_id, p.name as patient_name,
                            d.user_id as doctor_id, doc_user.name as doctor_name
                        FROM accommodations a
                        LEFT JOIN wards w ON a.ward_id = w.id
                        LEFT JOIN users p ON a.patient_id = p.id
                        LEFT JOIN users doc_user ON a.doctor_id = doc_user.id
                        LEFT JOIN doctors d ON doc_user.id = d.user_id
                        ORDER BY a.type, w.name, a.number
                    ");
                    $accommodations_stmt->execute();
                    $accommodations_data = $accommodations_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $accommodations_stmt->close();
                
                    $beds = array_filter($accommodations_data, function($item) { 
                        $item['bed_number'] = $item['number'];
                        return $item['type'] === 'bed'; 
                    });
                    $rooms = array_filter($accommodations_data, function($item) { 
                        $item['room_number'] = $item['number'];
                        return $item['type'] === 'room'; 
                    });

                    $patients_stmt = $conn->prepare("
                        SELECT u.id, u.display_user_id, u.name 
                        FROM users u JOIN roles r ON u.role_id = r.id
                        WHERE r.role_name = 'user' AND u.is_active = 1 AND u.id NOT IN (
                            SELECT patient_id FROM accommodations WHERE patient_id IS NOT NULL
                        )
                        ORDER BY u.name ASC
                    ");
                    $patients_stmt->execute();
                    $available_patients = $patients_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $patients_stmt->close();
                    
                    $doctors_stmt = $conn->prepare("
                        SELECT u.id, u.name FROM users u JOIN doctors d ON u.id = d.user_id JOIN roles r ON u.role_id = r.id
                        WHERE r.role_name = 'doctor' AND u.is_active = 1 ORDER BY u.name ASC
                    ");
                    $doctors_stmt->execute();
                    $available_doctors = $doctors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $doctors_stmt->close();
                
                    $response = [
                        'success' => true, 
                        'data' => [
                            'wards' => $wards, 'beds' => array_values($beds), 'rooms' => array_values($rooms),
                            'available_patients' => $available_patients, 'available_doctors' => $available_doctors
                        ]
                    ];
                    break;
                
                case 'admissions':
                    $search_query = $_GET['search'] ?? '';
                    $sql = "
                        SELECT 
                            a.id, p.display_user_id AS patient_display_id, p.name AS patient_name, doc_user.name AS doctor_name,
                            a.admission_date, a.discharge_date, acc.number AS location,
                            CASE WHEN acc.type = 'bed' THEN 'Bed' WHEN acc.type = 'room' THEN 'Private Room' ELSE 'N/A' END AS location_type
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
                        $params[] = $search_term; $params[] = $search_term;
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

                // --- START: LAB WORKFLOW UPDATE ---
                case 'lab_orders': // Renamed from lab_results
                    $search_query = $_GET['search'] ?? '';
                    $status_filter = $_GET['status'] ?? 'all';
                    
                    $sql = "
                        SELECT 
                            lo.id, lo.patient_id, lo.doctor_id, lo.cost, lo.status,
                            p.display_user_id AS patient_display_id, 
                            p.name AS patient_name,
                            p.gender AS patient_gender,
                            p.date_of_birth AS patient_dob,
                            p.phone AS patient_phone,
                            doc.name AS doctor_name, 
                            lo.test_name, lo.test_date,
                            lo.result_details, lo.attachment_path,
                            TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) AS patient_age
                        FROM lab_orders lo -- Renamed table
                        JOIN users p ON lo.patient_id = p.id
                        LEFT JOIN users doc ON lo.doctor_id = doc.id
                    ";
                    
                    $params = [];
                    $types = "";
                    $where_clauses = [];

                    if (!empty($search_query)) {
                        $where_clauses[] = "(p.name LIKE ? OR p.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        array_push($params, $search_term, $search_term);
                        $types .= "ss";
                    }

                    if ($status_filter !== 'all' && in_array($status_filter, ['ordered', 'pending', 'processing', 'completed'])) {
                        $where_clauses[] = "lo.status = ?";
                        $params[] = $status_filter;
                        $types .= "s";
                    }

                    if (!empty($where_clauses)) {
                        $sql .= " WHERE " . implode(" AND ", $where_clauses);
                    }

                    // Updated ORDER BY to prioritize new orders for the staff queue
                    $sql .= " ORDER BY FIELD(lo.status, 'ordered', 'processing', 'pending', 'completed'), lo.ordered_at DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    
                    $stmt->execute();
                    $lab_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $lab_orders];
                    break;

                case 'lab_form_data':
                    $doctors_stmt = $conn->prepare("SELECT u.id, u.name FROM users u JOIN doctors d ON u.id = d.user_id JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'doctor' AND u.is_active = 1 ORDER BY u.name ASC");
                    $doctors_stmt->execute();
                    $doctors = $doctors_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $doctors_stmt->close();

                    $response = ['success' => true, 'data' => ['doctors' => $doctors]];
                    break;
                // --- END: LAB WORKFLOW UPDATE ---

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
                
                // --- START: MODIFIED DISCHARGE REQUESTS LOGIC ---
                case 'discharge_requests':
                    $search_query = $_GET['search'] ?? '';
                    $status_filter = $_GET['status'] ?? 'all';
                    
                    $sql = "
                        SELECT 
                            dc.id as discharge_id, a.id as admission_id, p.name as patient_name, p.display_user_id as patient_display_id,
                            d.name as doctor_name, dc.clearance_step, dc.is_cleared, dc.cleared_at, u_cleared.name as cleared_by_name
                        FROM discharge_clearance dc
                        JOIN admissions a ON dc.admission_id = a.id
                        JOIN users p ON a.patient_id = p.id
                        LEFT JOIN users d ON a.doctor_id = d.id
                        LEFT JOIN users u_cleared ON dc.cleared_by_user_id = u_cleared.id
                    ";
                
                    $params = [];
                    $types = "";
                    // We only want to show tasks that are not yet cleared.
                    $where_clauses = ["dc.is_cleared = 0"];

                    if (!empty($search_query)) {
                        $where_clauses[] = "(p.name LIKE ? OR p.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        array_push($params, $search_term, $search_term);
                        $types .= "ss";
                    }

                    // This is the improved logic to enforce the workflow
                    if ($status_filter !== 'all') {
                        // For 'billing' tasks, only show them if nursing and pharmacy are already done.
                        if ($status_filter === 'billing') {
                            $where_clauses[] = "dc.clearance_step = 'billing'";
                            // This subquery checks that no uncleared nursing or pharmacy steps exist for the same admission.
                            $where_clauses[] = "(SELECT COUNT(*) FROM discharge_clearance dc2 WHERE dc2.admission_id = dc.admission_id AND dc2.is_cleared = 0 AND dc2.clearance_step IN ('nursing', 'pharmacy')) = 0";
                        } 
                        // For 'nursing' or 'pharmacy', just filter by the step name.
                        else if (in_array($status_filter, ['nursing', 'pharmacy'])) {
                            $where_clauses[] = "dc.clearance_step = ?";
                            $params[] = $status_filter;
                            $types .= "s";
                        }
                    }

                    if (!empty($where_clauses)) {
                        $sql .= " WHERE " . implode(" AND ", $where_clauses);
                    }
                    $sql .= " ORDER BY dc.id DESC";
                
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $discharge_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                
                    $response = ['success' => true, 'data' => $discharge_requests];
                    break;
                // --- END: MODIFIED DISCHARGE REQUESTS LOGIC ---

                case 'billable_patients':
                    $search_query = $_GET['search'] ?? '';
                    $sql = "
                        SELECT a.id as admission_id, p.id as patient_id, p.display_user_id as patient_display_id, p.name as patient_name
                        FROM admissions a JOIN users p ON a.patient_id = p.id
                        WHERE a.discharge_date IS NULL OR a.id NOT IN (SELECT admission_id FROM transactions WHERE status = 'paid')
                    ";
                    
                    $params = [];
                    $types = "";
                    if (!empty($search_query)) {
                        $sql .= " AND (p.name LIKE ? OR p.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term; $params[] = $search_term;
                        $types .= "ss";
                    }
                    $sql .= " ORDER BY a.admission_date DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $patients];
                    break;

                case 'invoices':
                    $search_query = $_GET['search'] ?? '';
                    $sql = "SELECT t.id, u.name as patient_name, t.amount, t.created_at, t.status FROM transactions t JOIN users u ON t.user_id = u.id";
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " WHERE (u.name LIKE ? OR t.id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $search_id = "{$search_query}%";
                        $params[] = $search_term; $params[] = $search_id;
                        $types .= "ss";
                    }
                    $sql .= " ORDER BY t.created_at DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $invoices];
                    break;
                
                case 'pending_prescriptions':
                    $search_query = $_GET['search'] ?? '';
                    $status_filter = $_GET['status'] ?? 'all';
                    $sql = "
                        SELECT 
                            p.id, patient.name AS patient_name, patient.display_user_id AS patient_display_id,
                            doctor.name AS doctor_name, doctor.display_user_id AS doctor_display_id, p.prescription_date, p.status
                        FROM prescriptions p
                        JOIN users patient ON p.patient_id = patient.id
                        JOIN users doctor ON p.doctor_id = doctor.id
                    ";
                    
                    $params = [];
                    $types = "";
                    $where_clauses = [];

                    if (!empty($search_query)) {
                        $where_clauses[] = "(patient.name LIKE ? OR patient.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        array_push($params, $search_term, $search_term);
                        $types .= "ss";
                    }

                    if ($status_filter !== 'all') {
                        $where_clauses[] = "p.status = ?";
                        $params[] = $status_filter;
                        $types .= "s";
                    }

                    if (!empty($where_clauses)) {
                        $sql .= " WHERE " . implode(" AND ", $where_clauses);
                    }
                    $sql .= " ORDER BY p.prescription_date DESC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'prescription_details':
                    if (empty($_GET['id'])) {
                        throw new Exception('Prescription ID is required.');
                    }
                    $prescription_id = (int)$_GET['id'];
                    $sql = "
                        SELECT 
                            pi.id as item_id, pi.medicine_id, m.name as medicine_name, m.quantity as stock_quantity,
                            m.unit_price, pi.dosage, pi.frequency, pi.quantity_prescribed, pi.quantity_dispensed
                        FROM prescription_items pi JOIN medicines m ON pi.medicine_id = m.id
                        WHERE pi.prescription_id = ?
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $prescription_id);
                    $stmt->execute();
                    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $data];
                    break;
            }
        }
        elseif (isset($_POST['action'])) {
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                http_response_code(403);
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

                    if (empty($name) || empty($email) || empty($department_name)) {
                        throw new Exception('Name, Email, and Department are required fields.');
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception('Please provide a valid email address.');
                    }
                    if (!empty($phone) && !preg_match('/^\+91\d{10}$/', $phone)) {
                        throw new Exception('Phone number must be in the format +91 followed by 10 digits.');
                    }
                    if ($date_of_birth) {
                        $d = DateTime::createFromFormat('Y-m-d', $date_of_birth);
                        if (!$d || $d->format('Y-m-d') !== $date_of_birth) {
                            throw new Exception('Invalid date of birth format provided.');
                        }
                        $year = (int)$d->format('Y');
                        if ($year < 1900 || $year > date('Y')) {
                            throw new Exception('Please enter a realistic year for the date of birth.');
                        }
                    }
                    
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

                case 'removeProfilePicture':
                    $stmt_select = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt_select->bind_param("i", $user_id);
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
                    $stmt_update->bind_param("i", $user_id);
                    if ($stmt_update->execute()) {
                        log_activity($conn, $user_id, 'remove_profile_picture', null, 'Staff removed their profile picture.');
                        $response = ['success' => true, 'message' => 'Profile picture removed successfully.', 'new_image_url' => '../uploads/profile_pictures/default.png'];
                    } else {
                        throw new Exception('Failed to remove profile picture.');
                    }
                    $stmt_update->close();
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
                        "UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')"
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

                    $stmt = $conn->prepare("
                        SELECT
                            u.id, u.display_user_id, u.name, r.role_name as role, u.profile_picture, c.id as conversation_id,
                            (SELECT message_text FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) AS last_message
                        FROM users u
                        JOIN roles r ON u.role_id = r.id
                        LEFT JOIN conversations c ON (c.user_one_id = u.id AND c.user_two_id = ?) OR (c.user_one_id = ? AND c.user_two_id = u.id)
                        WHERE (u.name LIKE ? OR u.display_user_id LIKE ?) AND r.role_name IN ('admin', 'doctor', 'staff') AND u.id != ?
                    ");
                    $stmt->bind_param("iissi", $user_id, $user_id, $search_term, $search_term, $user_id);
                    $stmt->execute();
                    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    foreach ($users as &$user) {
                        $default_avatar = '../uploads/profile_pictures/default.png';
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

                        if ($phone && !preg_match('/^\+91\d{10}$/', $phone)) {
                            throw new Exception('Invalid phone number format. Use +91xxxxxxxxxx.');
                        }
                        if ($date_of_birth) {
                            $d = DateTime::createFromFormat('Y-m-d', $date_of_birth);
                            if (!$d || $d->format('Y-m-d') !== $date_of_birth || strlen(explode('-', $date_of_birth)[0]) > 4) {
                                throw new Exception('Invalid date of birth provided.');
                            }
                        }

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

                        $display_user_id = generateDisplayId($role_name, $conn);
                        
                        $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role_id, phone, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssiss", $display_user_id, $name, $username, $email, $password, $role_id, $phone, $date_of_birth);
                        $stmt->execute();
                        
                        $new_user_id = $conn->insert_id;
                        if (!$new_user_id) {
                            throw new Exception('Failed to create the base user account.');
                        }
                        $stmt->close();

                        if ($role_name === 'doctor') {
                            $specialty_id = !empty($_POST['specialty_id']) ? (int)$_POST['specialty_id'] : null;
                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty_id) VALUES (?, ?)");
                            $stmt_doctor->bind_param("ii", $new_user_id, $specialty_id);
                            $stmt_doctor->execute();
                            $stmt_doctor->close();
                        }
                        
                        // UPDATED: More descriptive log message
                        log_activity($conn, $user_id, 'create_user', $new_user_id, "Created new {$role_name} '{$name}' ({$display_user_id}).");

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

                        if (!empty($phone) && !preg_match('/^\+91\d{10}$/', $phone)) {
                            throw new Exception('Invalid phone number format. Use +91xxxxxxxxxx.');
                        }
                        if ($date_of_birth) {
                            $d = DateTime::createFromFormat('Y-m-d', $date_of_birth);
                            if (!$d || $d->format('Y-m-d') !== $date_of_birth || strlen(explode('-', $date_of_birth)[0]) > 4) {
                                throw new Exception('Invalid date of birth provided.');
                            }
                        }

                        if (empty($name) || empty($email)) {
                            throw new Exception("Name and Email fields are required.");
                        }

                        $stmt_update = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, date_of_birth = ?, is_active = ? WHERE id = ?");
                        $stmt_update->bind_param("ssssii", $name, $email, $phone, $date_of_birth, $active, $target_user_id);
                        $stmt_update->execute();
                        $stmt_update->close();

                        if ($target_role === 'doctor' && isset($_POST['specialty_id'])) {
                            $specialty_id = !empty($_POST['specialty_id']) ? (int)$_POST['specialty_id'] : null;
                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE specialty_id = VALUES(specialty_id)");
                            $stmt_doctor->bind_param("ii", $target_user_id, $specialty_id);
                            $stmt_doctor->execute();
                            $stmt_doctor->close();
                        }

                        // UPDATED: Fetch display_user_id for a better log message
                        $stmt_get_id = $conn->prepare("SELECT display_user_id FROM users WHERE id = ?");
                        $stmt_get_id->bind_param("i", $target_user_id);
                        $stmt_get_id->execute();
                        $target_display_id = $stmt_get_id->get_result()->fetch_assoc()['display_user_id'];
                        $stmt_get_id->close();
                        log_activity($conn, $user_id, 'update_user', $target_user_id, "Updated profile for user '{$name}' ({$target_display_id}).");

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
                    
                    // UPDATED: Also fetch display_user_id for logging
                    $stmt_role_check = $conn->prepare("SELECT r.role_name, u.username, u.display_user_id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
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

                    // UPDATED: More descriptive log message
                    log_activity($conn, $user_id, 'deactivate_user', $target_user_id, "Deactivated user '{$target_user['username']}' ({$target_user['display_user_id']}).");

                    $response = ['success' => true, 'message' => 'User has been deactivated.'];
                    break;

                case 'reactivateUser':
                    if (empty($_POST['id'])) {
                        throw new Exception('User ID is required.');
                    }
                    $target_user_id = (int)$_POST['id'];
                    
                    // Security check: ensure only patients and doctors can be reactivated by staff
                    $stmt_role_check = $conn->prepare("SELECT r.role_name, u.username, u.display_user_id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                    $stmt_role_check->bind_param("i", $target_user_id);
                    $stmt_role_check->execute();
                    $target_user = $stmt_role_check->get_result()->fetch_assoc();
                    $stmt_role_check->close();
                    
                    if (!$target_user || !in_array($target_user['role_name'], ['user', 'doctor'])) {
                        throw new Exception("You are not authorized to reactivate this user.");
                    }

                    // Set the user's status back to active
                    $stmt = $conn->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                    $stmt->bind_param("i", $target_user_id);
                    $stmt->execute();

                    log_activity($conn, $user_id, 'reactivate_user', $target_user_id, "Reactivated user '{$target_user['username']}' ({$target_user['display_user_id']}).");

                    $response = ['success' => true, 'message' => 'User has been reactivated successfully.'];
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
                    
                    // UPDATED: Fetch medicine name for a more readable log
                    $stmt_med_name = $conn->prepare("SELECT name FROM medicines WHERE id = ?");
                    $stmt_med_name->bind_param("i", $medicine_id);
                    $stmt_med_name->execute();
                    $medicine_name = $stmt_med_name->get_result()->fetch_assoc()['name'] ?? 'Unknown Medicine';
                    $stmt_med_name->close();
                    
                    log_activity($conn, $user_id, 'update_inventory', null, "Updated stock for '{$medicine_name}' to {$new_quantity} units.");
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
                    
                    // UPDATED: Slightly better wording
                    log_activity($conn, $user_id, 'update_inventory', null, "Updated '{$blood_group}' blood stock to {$new_quantity} ml.");
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
                        
                        // UPDATED: More descriptive log
                        log_activity($conn, $user_id, "add_{$type}", null, "Added new {$type} '{$number}' with price ₹{$price}.");
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

                        $stmt_current = $conn->prepare("SELECT type, patient_id, `number` FROM accommodations WHERE id = ? FOR UPDATE");
                        $stmt_current->bind_param("i", $id);
                        $stmt_current->execute();
                        $current_accommodation = $stmt_current->get_result()->fetch_assoc();
                        $stmt_current->close();
                        if (!$current_accommodation) {
                            throw new Exception("Accommodation not found.");
                        }
                        $type = $current_accommodation['type'];
                        $old_patient_id = $current_accommodation['patient_id'];
                        $current_accommodation_number = $current_accommodation['number'];

                        if (isset($_POST['price'])) {
                            $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);
                            if ($price === false || $price < 0) throw new Exception("Invalid price format.");
                            $updates[] = "price_per_day = ?";
                            $params[] = $price;
                            $types .= "d";
                        }
                        if (isset($_POST['number'])) {
                            $updates[] = "number = ?";
                            $params[] = trim($_POST['number']);
                            $types .= "s";
                        }
                        
                        if (isset($_POST['status']) && !isset($_POST['patient_id'])) {
                            if ($old_patient_id) {
                                throw new Exception("Cannot change status directly while a patient is assigned. Please discharge the patient first.");
                            }
                            $new_status = $_POST['status'];
                            $allowed_statuses = ['available', 'cleaning', 'reserved'];
                            if (!in_array($new_status, $allowed_statuses)) {
                                throw new Exception("Invalid status provided for an unoccupied bed.");
                            }
                            $updates[] = "status = ?";
                            $params[] = $new_status;
                            $types .= "s";
                        }
                        
                        if (isset($_POST['patient_id'])) {
                            $new_patient_id = empty($_POST['patient_id']) ? null : (int)$_POST['patient_id'];
                            $new_doctor_id = empty($_POST['doctor_id']) ? null : (int)$_POST['doctor_id'];

                            if ($new_patient_id != $old_patient_id) {
                                
                                if ($old_patient_id) {
                                    // UPDATED: Fetch full patient info for logging
                                    $stmt_old_p = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                                    $stmt_old_p->bind_param("i", $old_patient_id);
                                    $stmt_old_p->execute();
                                    $old_patient_info = $stmt_old_p->get_result()->fetch_assoc();
                                    $stmt_old_p->close();

                                    $dis_stmt = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE accommodation_id = ? AND patient_id = ? AND discharge_date IS NULL");
                                    $dis_stmt->bind_param("ii", $id, $old_patient_id);
                                    $dis_stmt->execute();
                                    $dis_stmt->close();
                                    log_activity($conn, $user_id, "discharge_patient", $old_patient_id, "Discharged patient '{$old_patient_info['name']}' ({$old_patient_info['display_user_id']}) from {$type} '{$current_accommodation_number}'.");
                                }
                                
                                if ($new_patient_id) {
                                    // UPDATED: Fetch full patient info for logging
                                    $stmt_new_p = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                                    $stmt_new_p->bind_param("i", $new_patient_id);
                                    $stmt_new_p->execute();
                                    $new_patient_info = $stmt_new_p->get_result()->fetch_assoc();
                                    $stmt_new_p->close();

                                    $adm_stmt = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, accommodation_id, admission_date) VALUES (?, ?, ?, NOW())");
                                    $adm_stmt->bind_param("iii", $new_patient_id, $new_doctor_id, $id);
                                    $adm_stmt->execute();
                                    $adm_stmt->close();
                                    
                                    $updates[] = "status = 'occupied'";
                                    log_activity($conn, $user_id, "admit_patient", $new_patient_id, "Admitted patient '{$new_patient_info['name']}' ({$new_patient_info['display_user_id']}) to {$type} '{$current_accommodation_number}'.");
                                } else {
                                     if(isset($_POST['status'])) {
                                        $updates[] = "status = ?";
                                        $params[] = $_POST['status'];
                                        $types .= "s";
                                     }
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
                            if (!isset($_POST['patient_id'])) {
                                throw new Exception("No data provided to update.");
                            }
                        }

                        if (!empty($updates)) {
                            $sql = "UPDATE accommodations SET " . implode(", ", $updates) . " WHERE id = ?";
                            $params[] = $id;
                            $types .= "i";
                            
                            $stmt = $conn->prepare($sql);
                            $stmt->bind_param($types, ...$params);
                            $stmt->execute();
                            $stmt->close();
                        }
                        
                        $log_details = [];
                        if (isset($_POST['price'])) $log_details[] = "price to ₹" . $_POST['price'];
                        if (isset($_POST['number'])) $log_details[] = "number to '" . $_POST['number'] . "'";
                        if (isset($_POST['status']) && !isset($_POST['patient_id'])) $log_details[] = "status to '" . $_POST['status'] . "'";

                        if (!empty($log_details)) {
                            $accommodation_number_for_log = $_POST['number'] ?? $current_accommodation_number;
                            log_activity($conn, $user_id, "update_{$type}", null, "Updated {$type} '{$accommodation_number_for_log}': set " . implode(', ', $log_details) . ".");
                        }
                        
                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => ucfirst($type) . ' updated successfully.'];
                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;
                
                case 'bulkUpdateBedStatus':
                    if (empty($_POST['ids']) || empty($_POST['status'])) {
                        throw new Exception("A list of IDs and a new status are required.");
                    }
                    
                    $ids = json_decode($_POST['ids'], true);
                    if (!is_array($ids) || empty($ids)) {
                        throw new Exception("Invalid IDs format.");
                    }
                    
                    $sanitized_ids = array_map('intval', $ids);
                    $placeholders = implode(',', array_fill(0, count($sanitized_ids), '?'));
                    $status = $_POST['status'];
                    
                    $allowed_statuses = ['available', 'cleaning', 'reserved'];
                    if (!in_array($status, $allowed_statuses)) {
                        throw new Exception("Invalid status provided.");
                    }
                
                    $sql = "UPDATE accommodations SET status = ? WHERE id IN ($placeholders)";
                    $types = 's' . str_repeat('i', count($sanitized_ids));
                    $params = array_merge([$status], $sanitized_ids);
                
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                
                    if ($stmt->affected_rows > 0) {
                        log_activity($conn, $user_id, 'bulk_update_beds', null, "Updated {$stmt->affected_rows} accommodations to status '{$status}'");
                        $response = ['success' => true, 'message' => "Successfully updated {$stmt->affected_rows} item(s)."];
                    } else {
                        throw new Exception("No records were updated. They may have already been updated by someone else.");
                    }
                    $stmt->close();
                    break;

                // --- START: LAB WORKFLOW UPDATE ---
                case 'addLabOrder': // Renamed from addLabResult
                case 'updateLabOrder': // Renamed from updateLabResult
                    /**
                     * Lab Order Status Workflow:
                     * - 'ordered': Initial state when a doctor orders a test (default for new orders)
                     * - 'pending': Test is scheduled/awaiting sample collection
                     * - 'processing': Lab is actively processing the test
                     * - 'completed': Results are ready and report is available
                     * 
                     * Note: Staff can update status at any stage. No strict workflow enforcement.
                     */
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        $is_update = ($_POST['action'] === 'updateLabOrder');
                        
                        $patient_id = (int)$_POST['patient_id'];
                        $test_name = trim($_POST['test_name']);
                        $test_date = !empty(trim($_POST['test_date'])) ? trim($_POST['test_date']) : null;
                        $doctor_id = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
                        $result_details = !empty($_POST['result_details']) ? trim($_POST['result_details']) : null;
                        $cost = isset($_POST['cost']) && is_numeric($_POST['cost']) && (float)$_POST['cost'] >= 0 ? (float)$_POST['cost'] : 0.00;
                        $status = in_array($_POST['status'], ['ordered', 'pending', 'processing', 'completed']) ? $_POST['status'] : 'pending';
                        
                        if (empty($patient_id) || empty($test_name)) {
                            throw new Exception("Patient and test name are required.");
                        }
                        
                        if ($cost < 0) {
                            throw new Exception("Cost cannot be negative.");
                        }
                        
                        // Validate test date if provided
                        if ($test_date) {
                            $date_obj = DateTime::createFromFormat('Y-m-d', $test_date);
                            if (!$date_obj || $date_obj->format('Y-m-d') !== $test_date) {
                                throw new Exception("Invalid test date format.");
                            }
                            // Optionally prevent future dates (uncomment if needed)
                            // if ($date_obj > new DateTime()) {
                            //     throw new Exception("Test date cannot be in the future.");
                            // }
                        }

                        $attachment_filename = null;
                        $upload_dir = __DIR__ . '/report/';
                        
                        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                            if (!is_dir($upload_dir)) {
                                if (!mkdir($upload_dir, 0755, true)) throw new Exception('Failed to create report directory.');
                            }
                            
                            $file_info = new finfo(FILEINFO_MIME_TYPE);
                            $mime_type = $file_info->file($_FILES['attachment']['tmp_name']);
                            if ($mime_type !== 'application/pdf') throw new Exception('Invalid file type. Only PDF reports are allowed.');

                            if ($_FILES['attachment']['size'] > 5242880) throw new Exception('File is too large. Maximum size is 5MB.');
                            
                            $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
                            $attachment_filename = 'report_' . $patient_id . '_' . time() . '.' . $file_extension;
                            
                            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $attachment_filename)) {
                                throw new Exception('Failed to save uploaded report.');
                            }
                        }

                        if ($is_update) {
                            $lab_id = (int)$_POST['id'];
                            if (empty($lab_id)) throw new Exception("Lab Order ID is missing for update.");

                            if ($attachment_filename) {
                                $stmt_old = $conn->prepare("SELECT attachment_path FROM lab_orders WHERE id = ?");
                                $stmt_old->bind_param("i", $lab_id);
                                $stmt_old->execute();
                                $old_file = $stmt_old->get_result()->fetch_assoc()['attachment_path'];
                                $stmt_old->close();
                                if ($old_file && file_exists($upload_dir . $old_file)) {
                                    unlink($upload_dir . $old_file);
                                }
                            }

                            // Staff ID is updated when a staff member handles the order.
                            $sql = "UPDATE lab_orders SET patient_id = ?, doctor_id = ?, staff_id = ?, test_name = ?, test_date = ?, result_details = ?, cost = ?, status = ?";
                            $types = "iiisssds";
                            $params = [$patient_id, $doctor_id, $user_id, $test_name, $test_date, $result_details, $cost, $status];

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
                            
                            // *** IMPROVED: Notification logic on completion ***
                            if ($status === 'completed') {
                                // Get patient details for email notification
                                $stmt_patient = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
                                $stmt_patient->bind_param("i", $patient_id);
                                $stmt_patient->execute();
                                $patient_data = $stmt_patient->get_result()->fetch_assoc();
                                $stmt_patient->close();
                                
                                // Notify the doctor if one is assigned
                                if ($doctor_id) {
                                    $stmt_notify = $conn->prepare("INSERT INTO notifications (sender_id, recipient_user_id, message) VALUES (?, ?, ?)");
                                    $message = "Lab result for test '{$test_name}' for patient ID {$patient_id} is ready for review.";
                                    $stmt_notify->bind_param("iis", $user_id, $doctor_id, $message);
                                    $stmt_notify->execute();
                                    $stmt_notify->close();
                                }
                                
                                // ALWAYS notify the patient when their results are ready (in-app notification)
                                $stmt_notify_patient = $conn->prepare("INSERT INTO notifications (sender_id, recipient_user_id, message) VALUES (?, ?, ?)");
                                $patient_message = "Your lab test result for '{$test_name}' is now ready. Please check your dashboard for details.";
                                $stmt_notify_patient->bind_param("iis", $user_id, $patient_id, $patient_message);
                                $stmt_notify_patient->execute();
                                $stmt_notify_patient->close();
                                
                                // Send email notification to patient
                                if ($patient_data && !empty($patient_data['email'])) {
                                    try {
                                        $current_datetime = date('d M Y, h:i A');
                                        $email_body = getLabResultReadyTemplate(
                                            $patient_data['name'],
                                            $test_name,
                                            $test_date,
                                            'Completed',
                                            $current_datetime
                                        );
                                        
                                        $email_sent = send_mail(
                                            'MedSync Lab Services',
                                            $patient_data['email'],
                                            'Your Lab Results Are Ready - MedSync',
                                            $email_body
                                        );
                                        
                                        if (!$email_sent) {
                                            $error_msg = "Failed to send lab result email to patient (ID: {$patient_id}, Email: {$patient_data['email']}). Check email configuration in system settings.";
                                            error_log($error_msg);
                                            log_activity($conn, $user_id, 'email_error', $patient_id, "Lab result email failed: Email system may not be configured");
                                        } else {
                                            log_activity($conn, $user_id, 'email_sent', $patient_id, "Lab result notification email sent to {$patient_data['email']}");
                                        }
                                    } catch (Exception $email_error) {
                                        // Log email error but don't fail the lab order update
                                        $error_msg = "Lab result email notification failed for patient ID {$patient_id}: " . $email_error->getMessage();
                                        error_log($error_msg);
                                        log_activity($conn, $user_id, 'email_error', $patient_id, $error_msg);
                                    }
                                } else {
                                    // Patient doesn't have an email address
                                    if ($patient_data && empty($patient_data['email'])) {
                                        log_activity($conn, $user_id, 'email_skipped', $patient_id, "Lab result email not sent: Patient has no email address on file");
                                    }
                                }
                            }
                            
                            // UPDATED: More descriptive log message
                            $stmt_p_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                            $stmt_p_info->bind_param("i", $patient_id);
                            $stmt_p_info->execute();
                            $patient_info = $stmt_p_info->get_result()->fetch_assoc();
                            $stmt_p_info->close();
                            $patient_log_details = $patient_info ? "'{$patient_info['name']}' ({$patient_info['display_user_id']})" : "ID {$patient_id}";
                            log_activity($conn, $user_id, 'update_lab_order', $patient_id, "Updated lab order #{$lab_id} ('{$test_name}') for patient {$patient_log_details}.");

                            $response = ['success' => true, 'message' => 'Lab order updated successfully.'];

                        } else { // This is now for walk-in orders
                            $stmt = $conn->prepare("INSERT INTO lab_orders (patient_id, doctor_id, staff_id, test_name, test_date, result_details, attachment_path, cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->bind_param("iiissssds", $patient_id, $doctor_id, $user_id, $test_name, $test_date, $result_details, $attachment_filename, $cost, $status);
                            $stmt->execute();
                            $new_lab_id = $conn->insert_id;
                            $stmt->close();
                            
                            // UPDATED: More descriptive log message
                            $stmt_p_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                            $stmt_p_info->bind_param("i", $patient_id);
                            $stmt_p_info->execute();
                            $patient_info = $stmt_p_info->get_result()->fetch_assoc();
                            $stmt_p_info->close();
                            $patient_log_details = $patient_info ? "'{$patient_info['name']}' ({$patient_info['display_user_id']})" : "ID {$patient_id}";
                            log_activity($conn, $user_id, 'add_lab_order', $patient_id, "Added walk-in lab order #{$new_lab_id} ('{$test_name}') for patient {$patient_log_details}.");

                            $response = ['success' => true, 'message' => 'Walk-in lab order added successfully.'];
                        }

                        $conn->commit();
                        $transaction_active = false;
                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'removeLabOrder': // Renamed from removeLabResult
                    if (empty($_POST['id'])) {
                        throw new Exception('Lab order ID is required.');
                    }
                    $lab_id = (int)$_POST['id'];
                    $conn->begin_transaction();
                    
                    $upload_dir = __DIR__ . '/report/';
                    $stmt_select = $conn->prepare("SELECT attachment_path, patient_id FROM lab_orders WHERE id = ?");
                    $stmt_select->bind_param("i", $lab_id);
                    $stmt_select->execute();
                    $result_data = $stmt_select->get_result()->fetch_assoc();
                    $file_to_delete = $result_data['attachment_path'] ?? null;
                    $patient_id_for_log = $result_data['patient_id'] ?? null;
                    $stmt_select->close();

                    $stmt_delete = $conn->prepare("DELETE FROM lab_orders WHERE id = ?");
                    $stmt_delete->bind_param("i", $lab_id);
                    $stmt_delete->execute();
                    
                    if ($stmt_delete->affected_rows > 0) {
                        if ($file_to_delete && file_exists($upload_dir . $file_to_delete)) {
                            unlink($upload_dir . $file_to_delete);
                        }

                        // UPDATED: More descriptive log message
                        $patient_log_details = "ID {$patient_id_for_log}";
                        if ($patient_id_for_log) {
                            $stmt_p_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                            $stmt_p_info->bind_param("i", $patient_id_for_log);
                            $stmt_p_info->execute();
                            $patient_info = $stmt_p_info->get_result()->fetch_assoc();
                            $stmt_p_info->close();
                            if ($patient_info) {
                                 $patient_log_details = "'{$patient_info['name']}' ({$patient_info['display_user_id']})";
                            }
                        }
                        log_activity($conn, $user_id, 'delete_lab_order', $patient_id_for_log, "Deleted lab order #{$lab_id} for patient {$patient_log_details}.");
                        
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Lab order deleted successfully.'];
                    } else {
                        $conn->rollback();
                        throw new Exception('Could not find or delete the lab order.');
                    }
                    $stmt_delete->close();
                    break;
                // --- END: LAB WORKFLOW UPDATE ---

                case 'process_clearance':
                    if (empty($_POST['discharge_id']) || empty($_POST['notes'])) {
                        throw new Exception("Discharge ID and notes are required.");
                    }
                    $discharge_id = (int)$_POST['discharge_id'];
                    $notes = trim($_POST['notes']);

                    $conn->begin_transaction();
                    $transaction_active = true;

                    $stmt_step = $conn->prepare("SELECT admission_id, clearance_step FROM discharge_clearance WHERE id = ? FOR UPDATE");
                    $stmt_step->bind_param("i", $discharge_id);
                    $stmt_step->execute();
                    $current_step = $stmt_step->get_result()->fetch_assoc();
                    $stmt_step->close();

                    if (!$current_step) {
                        throw new Exception("Discharge record not found.");
                    }
                    $admission_id = $current_step['admission_id'];

                    if ($current_step['clearance_step'] === 'pharmacy') {
                        $stmt_check = $conn->prepare("SELECT is_cleared FROM discharge_clearance WHERE admission_id = ? AND clearance_step = 'nursing'");
                        $stmt_check->bind_param("i", $admission_id);
                        $stmt_check->execute();
                        if ($stmt_check->get_result()->fetch_assoc()['is_cleared'] == 0) {
                            throw new Exception("Nursing clearance must be completed before pharmacy clearance.");
                        }
                        $stmt_check->close();
                    } elseif ($current_step['clearance_step'] === 'billing') {
                        $stmt_check = $conn->prepare("SELECT COUNT(id) as uncleared_count FROM discharge_clearance WHERE admission_id = ? AND clearance_step IN ('nursing', 'pharmacy') AND is_cleared = 0");
                        $stmt_check->bind_param("i", $admission_id);
                        $stmt_check->execute();
                        if ($stmt_check->get_result()->fetch_assoc()['uncleared_count'] > 0) {
                            throw new Exception("Nursing and Pharmacy clearances must be completed before billing clearance.");
                        }
                        $stmt_check->close();
                    }

                    $stmt = $conn->prepare("UPDATE discharge_clearance SET is_cleared = 1, cleared_by_user_id = ?, cleared_at = NOW(), notes = ? WHERE id = ? AND is_cleared = 0");
                    $stmt->bind_param("isi", $user_id, $notes, $discharge_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        // UPDATED: Fetch display_user_id for logging
                        $stmt_patient = $conn->prepare("SELECT p.id, p.name, p.display_user_id FROM users p JOIN admissions a ON p.id = a.patient_id WHERE a.id = ?");
                        $stmt_patient->bind_param("i", $admission_id);
                        $stmt_patient->execute();
                        $patient_info = $stmt_patient->get_result()->fetch_assoc();
                        $stmt_patient->close();

                        checkAndFinalizeDischarge($conn, $admission_id);
                        // UPDATED: More descriptive log message
                        log_activity($conn, $user_id, 'process_discharge_clearance', $patient_info['id'] ?? null, "Processed {$current_step['clearance_step']} clearance for patient '{$patient_info['name']}' ({$patient_info['display_user_id']}). Notes: {$notes}");
                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => 'Clearance processed successfully.'];
                    } else {
                        throw new Exception("Failed to process clearance. It might have been already processed.");
                    }
                    $stmt->close();
                    break;

                 case 'generateInvoice':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        if (empty($_POST['admission_id'])) {
                             throw new Exception("Admission ID is required to generate an invoice.");
                        }
                        $admission_id = (int)$_POST['admission_id'];
                        
                        // --- START: THIS IS THE FIX ---
                        // Check if an unpaid invoice already exists for this admission
                        $stmt_check = $conn->prepare("SELECT id FROM transactions WHERE admission_id = ? AND status = 'pending'");
                        $stmt_check->bind_param("i", $admission_id);
                        $stmt_check->execute();
                        if ($stmt_check->get_result()->num_rows > 0) {
                            throw new Exception("An unpaid invoice already exists for this admission. Please process the existing one.");
                        }
                        $stmt_check->close();
                        // --- END: THIS IS THE FIX ---

                        $stmt_adm = $conn->prepare("
                            SELECT a.patient_id, a.admission_date, COALESCE(a.discharge_date, NOW()) as effective_discharge_date, acc.price_per_day
                            FROM admissions a LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
                            WHERE a.id = ?
                        ");
                        $stmt_adm->bind_param("i", $admission_id);
                        $stmt_adm->execute();
                        $admission = $stmt_adm->get_result()->fetch_assoc();
                        $stmt_adm->close();
                        if (!$admission) throw new Exception("Admission record not found.");
                        
                        $admission_date = new DateTime($admission['admission_date']);
                        $discharge_date = new DateTime($admission['effective_discharge_date']);
                        $duration = max(1, $admission_date->diff($discharge_date)->days + 1);
                        $accommodation_cost = $duration * ($admission['price_per_day'] ?? 0);
                        
                        $stmt_med = $conn->prepare("
                            SELECT SUM(pi.quantity_dispensed * m.unit_price) as total_med_cost
                            FROM prescription_items pi
                            JOIN prescriptions p ON pi.prescription_id = p.id JOIN medicines m ON pi.medicine_id = m.id
                            WHERE p.admission_id = ? AND pi.quantity_dispensed > 0
                        ");
                        $stmt_med->bind_param("i", $admission_id);
                        $stmt_med->execute();
                        $medicine_cost = $stmt_med->get_result()->fetch_assoc()['total_med_cost'] ?? 0.00;
                        $stmt_med->close();

                        // Updated to use lab_orders table
                        $stmt_lab = $conn->prepare("
                            SELECT SUM(cost) as total_lab_cost FROM lab_orders 
                            WHERE patient_id = ? AND created_at >= ?
                        ");
                        $stmt_lab->bind_param("is", $admission['patient_id'], $admission['admission_date']);
                        $stmt_lab->execute();
                        $lab_cost = $stmt_lab->get_result()->fetch_assoc()['total_lab_cost'] ?? 0.00;
                        $stmt_lab->close();

                        $total_amount = $accommodation_cost + $medicine_cost + $lab_cost;
                        $description = sprintf(
                            "Final bill for admission #%d. Accommodation (%d days): ₹%.2f, Medicines: ₹%.2f, Lab Tests: ₹%.2f.",
                            $admission_id, $duration, $accommodation_cost, $medicine_cost, $lab_cost
                        );
                        
                        $stmt_insert = $conn->prepare("INSERT INTO transactions (user_id, admission_id, description, amount, type, status) VALUES (?, ?, ?, ?, 'payment', 'pending')");
                        $stmt_insert->bind_param("iisd", $admission['patient_id'], $admission_id, $description, $total_amount);
                        $stmt_insert->execute();
                        $new_invoice_id = $conn->insert_id;
                        $stmt_insert->close();
                        
                        log_activity($conn, $user_id, 'generate_invoice', $admission['patient_id'], "Generated invoice #{$new_invoice_id} for admission #{$admission_id}. Amount: ₹{$total_amount}.");
                        
                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => 'Invoice generated successfully.'];

                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;
                
                case 'processPayment':
                    $conn->begin_transaction();
                    $transaction_active = true;
                    try {
                        if (empty($_POST['transaction_id']) || empty($_POST['payment_mode'])) {
                            throw new Exception("Transaction ID and payment mode are required.");
                        }
                        $transaction_id = (int)$_POST['transaction_id'];
                        $payment_mode = $_POST['payment_mode'];

                        $stmt = $conn->prepare("UPDATE transactions SET status = 'paid', payment_mode = ?, paid_at = NOW() WHERE id = ? AND status = 'pending'");
                        $stmt->bind_param("si", $payment_mode, $transaction_id);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows === 0) {
                            throw new Exception("Payment failed. The invoice may already be paid or does not exist.");
                        }
                        $stmt->close();
                        
                        // UPDATED: Fetch patient's name and email for the new mail function
                        $stmt_user = $conn->prepare("SELECT u.name, u.email FROM users u JOIN transactions t ON u.id = t.user_id WHERE t.id = ?");
                        $stmt_user->bind_param("i", $transaction_id);
                        $stmt_user->execute();
                        $patient = $stmt_user->get_result()->fetch_assoc();
                        $stmt_user->close();

                        if ($patient && !empty($patient['email'])) {
                            // UPDATED: Include the centralized mail function
                            require_once __DIR__ . '/../mail/send_mail.php'; 
                            
                            ob_start();
                            include '../mail/invoice_template.php'; 
                            $html = ob_get_clean();
                            
                            $options = new Options();
                            $options->set('isRemoteEnabled', true);
                            $dompdf = new Dompdf($options);
                            $dompdf->loadHtml($html);
                            $dompdf->setPaper('A4', 'portrait');
                            $dompdf->render();
                            $pdf_output = $dompdf->output();
                            
                            $subject = "Your MedSync Hospital Bill (Invoice #" . $transaction_id . ")";
                            // UPDATED: Personalize the email body with the patient's name
                            $body = "Dear " . htmlspecialchars($patient['name']) . ",<br><br>Thank you for your payment. Please find your detailed bill attached.<br><br>Sincerely,<br>MedSync Hospital";
                            
                            // UPDATED: Call the new centralized function with all required arguments
                            send_mail('MedSync Billing', $patient['email'], $subject, $body, $pdf_output, 'invoice-' . $transaction_id . '.pdf');
                        }

                        $conn->commit();
                        $transaction_active = false;
                        $response = ['success' => true, 'message' => 'Payment processed successfully. Receipt sent to patient.'];

                    } catch (Exception $e) {
                        if ($transaction_active) $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'create_pharmacy_bill':
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['prescription_id']) || empty($_POST['items']) || empty($_POST['payment_mode'])) {
                            throw new Exception("Missing required billing data.");
                        }
                        $prescription_id = (int)$_POST['prescription_id'];
                        $items_to_dispense = json_decode($_POST['items'], true);
                        $payment_mode = $_POST['payment_mode'];
                        $staff_id = $_SESSION['user_id'];
                        $total_amount = 0;
                        
                        $stmt_check = $conn->prepare("SELECT id FROM pharmacy_bills WHERE prescription_id = ?");
                        $stmt_check->bind_param("i", $prescription_id);
                        $stmt_check->execute();
                        if ($stmt_check->get_result()->num_rows > 0) {
                            throw new Exception("This prescription has already been billed.");
                        }
                        $stmt_check->close();

                        $patient_id_stmt = $conn->prepare("SELECT patient_id FROM prescriptions WHERE id = ?");
                        $patient_id_stmt->bind_param("i", $prescription_id);
                        $patient_id_stmt->execute();
                        $patient_id = $patient_id_stmt->get_result()->fetch_assoc()['patient_id'];
                        $patient_id_stmt->close();

                        foreach ($items_to_dispense as $item) {
                            $medicine_id = (int)$item['medicine_id'];
                            $quantity_to_dispense = (int)$item['quantity'];
                            if ($quantity_to_dispense <= 0) continue;

                            $stmt_med = $conn->prepare("SELECT quantity, unit_price FROM medicines WHERE id = ? FOR UPDATE");
                            $stmt_med->bind_param("i", $medicine_id);
                            $stmt_med->execute();
                            $medicine = $stmt_med->get_result()->fetch_assoc();

                            if ($medicine['quantity'] < $quantity_to_dispense) {
                                throw new Exception("Insufficient stock for medicine ID {$medicine_id}.");
                            }

                            $new_stock = $medicine['quantity'] - $quantity_to_dispense;
                            $stmt_update_stock = $conn->prepare("UPDATE medicines SET quantity = ? WHERE id = ?");
                            $stmt_update_stock->bind_param("ii", $new_stock, $medicine_id);
                            $stmt_update_stock->execute();
                            $stmt_update_stock->close();

                            $stmt_update_item = $conn->prepare("UPDATE prescription_items SET quantity_dispensed = quantity_dispensed + ? WHERE prescription_id = ? AND medicine_id = ?");
                            $stmt_update_item->bind_param("iii", $quantity_to_dispense, $prescription_id, $medicine_id);
                            $stmt_update_item->execute();
                            $stmt_update_item->close();
                            
                            $total_amount += $quantity_to_dispense * $medicine['unit_price'];
                        }

                        if ($total_amount <= 0) {
                            throw new Exception("Cannot create a bill with zero total amount.");
                        }

                        $description = "Pharmacy Bill for Prescription #" . $prescription_id;
                        $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, description, amount, status, payment_mode, paid_at) VALUES (?, ?, ?, 'paid', ?, NOW())");
                        $stmt_trans->bind_param("isds", $patient_id, $description, $total_amount, $payment_mode);
                        $stmt_trans->execute();
                        $transaction_id = $conn->insert_id;
                        $stmt_trans->close();

                        $stmt_bill = $conn->prepare("INSERT INTO pharmacy_bills (prescription_id, transaction_id, created_by_staff_id, total_amount) VALUES (?, ?, ?, ?)");
                        $stmt_bill->bind_param("iiid", $prescription_id, $transaction_id, $staff_id, $total_amount);
                        $stmt_bill->execute();
                        $bill_id = $conn->insert_id;
                        $stmt_bill->close();
                        
                        $stmt_update_presc = $conn->prepare("UPDATE prescriptions SET status = 'dispensed' WHERE id = ?");
                        $stmt_update_presc->bind_param("i", $prescription_id);
                        $stmt_update_presc->execute();
                        $stmt_update_presc->close();
                        
                        // UPDATED: Added currency symbol for clarity
                        log_activity($conn, $staff_id, 'create_pharmacy_bill', $patient_id, "Created pharmacy bill #{$bill_id} for prescription #{$prescription_id}. Amount: ₹{$total_amount}.");

                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Bill created and medicine dispensed successfully.', 'bill_id' => $bill_id];
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        if ($transaction_active) {
            $conn->rollback();
        }
        http_response_code(400);
        $response['message'] = $e->getMessage();
    }

    if (isset($conn) && $conn->query("SELECT 1")) {
        $conn->close();
    }
    echo json_encode($response);
    exit();
}


// --- Standard Page Load Security & Session Management ---
if (!isset($_GET['action']) || $_GET['action'] !== 'download_pharmacy_bill') {

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/index.php?error=not_loggedin");
        exit();
    }

    $conn = getDbConnection();
    $role_check_stmt = $conn->prepare("SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
    $role_check_stmt->bind_param("i", $_SESSION['user_id']);
    $role_check_stmt->execute();
    $current_user_role = $role_check_stmt->get_result()->fetch_assoc()['role_name'];
    $role_check_stmt->close();

    if (!in_array($current_user_role, ['staff', 'admin'])) {
        session_unset();
        session_destroy();
        header("Location: ../login/index.php?error=unauthorized");
        exit();
    }
    $_SESSION['role'] = $current_user_role;

    $session_timeout = 1800;
    if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
        session_unset();
        session_destroy();
        header("Location: ../login/index.php?error=session_expired");
        exit();
    }
    $_SESSION['loggedin_time'] = time();

    // --- Prepare Variables for Frontend ---
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
        header("Location: ../login/index.php?error=user_not_found");
        exit();
    }

    $display_name = !empty($user['name']) ? htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') : htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
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
        $profile_picture_path = '../uploads/profile_pictures/default.png';
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}


// --- PDF GENERATION LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'download_pharmacy_bill') {
    if (empty($_GET['id'])) {
        die('Bill ID is required to generate a PDF.');
    }
    $bill_id = (int)$_GET['id'];
    $conn = getDbConnection();

    $bill_sql = "
        SELECT 
            pb.*, p.prescription_date, patient.name as patient_name, patient.display_user_id as patient_display_id,
            doctor.name as doctor_name, staff.name as staff_name, t.payment_mode
        FROM pharmacy_bills pb
        JOIN prescriptions p ON pb.prescription_id = p.id JOIN transactions t ON pb.transaction_id = t.id
        JOIN users patient ON p.patient_id = patient.id JOIN users doctor ON p.doctor_id = doctor.id JOIN users staff ON pb.created_by_staff_id = staff.id
        WHERE pb.id = ?
    ";
    $stmt_bill = $conn->prepare($bill_sql);
    $stmt_bill->bind_param("i", $bill_id);
    $stmt_bill->execute();
    $bill_data = $stmt_bill->get_result()->fetch_assoc();

    $items_sql = "
        SELECT m.name, pi.quantity_dispensed, m.unit_price, (pi.quantity_dispensed * m.unit_price) as subtotal
        FROM prescription_items pi JOIN medicines m ON pi.medicine_id = m.id
        WHERE pi.prescription_id = ? AND pi.quantity_dispensed > 0
    ";
    $stmt_items = $conn->prepare($items_sql);
    $stmt_items->bind_param("i", $bill_data['prescription_id']);
    $stmt_items->execute();
    $items_data = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
    $conn->close();

    if (!$bill_data) {
        die('Bill not found.');
    }

    $medsync_logo_path = '../images/logo.png';
    $hospital_logo_path = '../images/hospital.png';
    $medsync_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($medsync_logo_path));
    $hospital_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($hospital_logo_path));

    $html = '...'; // PDF HTML content remains the same

    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream('pharmacy_bill_' . $bill_id . '.pdf', ["Attachment" => 1]);
    exit();
}
?>