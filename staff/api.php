<?php
/**
 * MedSync Staff Logic (api.php)
 *
 * This script handles the backend logic for the staff dashboard.
 * - Enforces session security and role-based access.
 * - Initializes session variables and fetches user data for the frontend.
 * - Handles AJAX API requests for Profile Settings, Callback Requests, and Messenger.
 */

// config.php should be included first to initialize the session and db connection.
require_once '../config.php';
require_once '../vendor/autoload.php'; // Autoload Composer dependencies

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
/**
 * Generates a unique, sequential display ID for a new user.
 */
function generateDisplayId($role, $conn) {
    $prefix_map = ['admin' => 'A', 'doctor' => 'D', 'staff' => 'S', 'user' => 'U'];
    if (!isset($prefix_map[$role])) {
        throw new Exception("Invalid role for ID generation.");
    }
    $prefix = $prefix_map[$role];

    // This function will now run inside the caller's transaction.
    // DO NOT begin, commit, or rollback transactions here.
    try {
        // Ensure the counter exists for the role
        $init_stmt = $conn->prepare("INSERT INTO role_counters (role_prefix, last_id) VALUES (?, 0) ON DUPLICATE KEY UPDATE role_prefix = role_prefix");
        $init_stmt->bind_param("s", $prefix);
        $init_stmt->execute();
        $init_stmt->close();
        
        // Atomically fetch and update the counter (FOR UPDATE works within an active transaction)
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
        // Let the calling function handle any exceptions and rollbacks
        throw $e;
    }
}

/**
 * Checks if all discharge clearances for an admission are complete and finalizes the discharge.
 */
function checkAndFinalizeDischarge($conn, $admission_id) {
    // Check if all 3 steps are cleared
    $stmt = $conn->prepare("SELECT COUNT(id) as cleared_count FROM discharge_clearance WHERE admission_id = ? AND is_cleared = 1");
    $stmt->bind_param("i", $admission_id);
    $stmt->execute();
    $cleared_count = $stmt->get_result()->fetch_assoc()['cleared_count'];
    $stmt->close();

    if ($cleared_count === 3) {
        // Fetch accommodation ID before updating admission
        $stmt_adm = $conn->prepare("SELECT accommodation_id, patient_id FROM admissions WHERE id = ?");
        $stmt_adm->bind_param("i", $admission_id);
        $stmt_adm->execute();
        $admission_data = $stmt_adm->get_result()->fetch_assoc();
        $stmt_adm->close();

        if ($admission_data) {
            // 1. Finalize admission record
            $stmt_discharge = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE id = ? AND discharge_date IS NULL");
            $stmt_discharge->bind_param("i", $admission_id);
            $stmt_discharge->execute();
            $stmt_discharge->close();

            // 2. Update accommodation status to 'cleaning'
            if ($admission_data['accommodation_id']) {
                $stmt_acc = $conn->prepare("UPDATE accommodations SET status = 'cleaning', patient_id = NULL, doctor_id = NULL WHERE id = ?");
                $stmt_acc->bind_param("i", $admission_data['accommodation_id']);
                $stmt_acc->execute();
                $stmt_acc->close();
            }
             log_activity($conn, $_SESSION['user_id'], 'finalize_discharge', $admission_data['patient_id'], "All clearances met. Patient from admission #{$admission_id} discharged.");
        }
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
                case 'dashboard_stats':
                    $stats = [];

                    // 1. Count Available Accommodations (Beds & Rooms)
                    $stmt_beds = $conn->prepare("SELECT COUNT(id) as count FROM accommodations WHERE status = 'available'");
                    $stmt_beds->execute();
                    $stats['available_beds'] = $stmt_beds->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_beds->close();

                    // 2. Count Low Stock Items (Medicines + Blood)
                    $stmt_med = $conn->prepare("SELECT COUNT(id) as count FROM medicines WHERE quantity <= low_stock_threshold");
                    $stmt_med->execute();
                    $low_med_count = $stmt_med->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_med->close();

                    $stmt_blood = $conn->prepare("SELECT COUNT(id) as count FROM blood_inventory WHERE quantity_ml <= low_stock_threshold_ml");
                    $stmt_blood->execute();
                    $low_blood_count = $stmt_blood->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_blood->close();
                    $stats['low_stock_items'] = $low_med_count + $low_blood_count;

                    // 3. Count Unique Pending Discharges
                    $stmt_discharges = $conn->prepare("SELECT COUNT(DISTINCT admission_id) as count FROM discharge_clearance WHERE is_cleared = 0");
                    $stmt_discharges->execute();
                    $stats['pending_discharges'] = $stmt_discharges->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_discharges->close();

                    // 4. Count Active In-Patients
                    $stmt_patients = $conn->prepare("SELECT COUNT(id) as count FROM admissions WHERE discharge_date IS NULL");
                    $stmt_patients->execute();
                    $stats['active_patients'] = $stmt_patients->get_result()->fetch_assoc()['count'] ?? 0;
                    $stmt_patients->close();

                    // 5. Fetch details for the pending discharge table (limit to 5 for the dashboard)
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

                    // 6. Fetch data for Bed Occupancy chart
                    $stmt_occupancy = $conn->prepare("SELECT status, COUNT(id) as count FROM accommodations GROUP BY status");
                    $stmt_occupancy->execute();
                    $stats['occupancy_data'] = $stmt_occupancy->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_occupancy->close();

                    // 7. Fetch Recent Activity Feed
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
                
                case 'active_doctors':
                    $stmt = $conn->prepare("
                        SELECT u.id, u.name 
                        FROM users u 
                        JOIN doctors d ON u.id = d.user_id 
                        WHERE u.is_active = 1 
                        ORDER BY u.name ASC
                    ");
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
                            a.token_number,
                            a.token_status,
                            a.slot_start_time,
                            a.slot_end_time,
                            p.name as patient_name,
                            p.display_user_id as patient_display_id
                        FROM appointments a
                        JOIN users p ON a.user_id = p.id
                        WHERE a.doctor_id = ? 
                          AND DATE(a.appointment_date) = CURDATE()
                        ORDER BY a.slot_start_time ASC, a.token_number ASC
                    ");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $tokens_flat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    // Group the tokens by their time slot for the frontend
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
                
                    // Separate beds and rooms for frontend logic if needed
                    $beds = array_filter($accommodations_data, function($item) { 
                        $item['bed_number'] = $item['number'];
                        return $item['type'] === 'bed'; 
                    });
                    $rooms = array_filter($accommodations_data, function($item) { 
                        $item['room_number'] = $item['number'];
                        return $item['type'] === 'room'; 
                    });

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
                                WHEN acc.type = 'bed' THEN 'Bed' 
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
                
                case 'discharge_requests':
                    $search_query = $_GET['search'] ?? '';
                    $status_filter = $_GET['status'] ?? 'all';
                
                    $sql = "
                        SELECT 
                            dc.id as discharge_id,
                            a.id as admission_id,
                            p.name as patient_name,
                            p.display_user_id as patient_display_id,
                            d.name as doctor_name,
                            dc.clearance_step,
                            dc.is_cleared,
                            dc.cleared_at,
                            u_cleared.name as cleared_by_name
                        FROM discharge_clearance dc
                        JOIN admissions a ON dc.admission_id = a.id
                        JOIN users p ON a.patient_id = p.id
                        LEFT JOIN users d ON a.doctor_id = d.id
                        LEFT JOIN users u_cleared ON dc.cleared_by_user_id = u_cleared.id
                    ";
                
                    $params = [];
                    $types = "";
                
                    // Add WHERE clauses based on filters
                    $where_clauses = [];
                    if (!empty($search_query)) {
                        $where_clauses[] = "(p.name LIKE ? OR p.display_user_id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        array_push($params, $search_term, $search_term);
                        $types .= "ss";
                    }
                
                    if ($status_filter !== 'all') {
                        $where_clauses[] = "dc.clearance_step = ? AND dc.is_cleared = 0";
                        $params[] = $status_filter;
                        $types .= "s";
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

                case 'billable_patients':
                    $search_query = $_GET['search'] ?? '';
                    $sql = "
                        SELECT 
                            a.id as admission_id,
                            p.id as patient_id,
                            p.display_user_id as patient_display_id,
                            p.name as patient_name
                        FROM admissions a
                        JOIN users p ON a.patient_id = p.id
                        WHERE a.discharge_date IS NULL OR a.id NOT IN (
                            SELECT admission_id FROM transactions WHERE status = 'paid'
                        )
                    ";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " AND (p.name LIKE ? OR p.display_user_id LIKE ?)";
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
                    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $patients];
                    break;

                case 'invoices':
                    $search_query = $_GET['search'] ?? '';
                    
                    $sql = "
                        SELECT 
                            t.id,
                            u.name as patient_name,
                            t.amount,
                            t.created_at,
                            t.status
                        FROM transactions t
                        JOIN users u ON t.user_id = u.id
                    ";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " WHERE (u.name LIKE ? OR t.id LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $search_id = "{$search_query}%";
                        $params[] = $search_term;
                        $params[] = $search_id;
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
                    $sql = "
                        SELECT 
                            p.id,
                            patient.name AS patient_name,
                            doctor.name AS doctor_name,
                            p.prescription_date,
                            p.status
                        FROM prescriptions p
                        JOIN users patient ON p.patient_id = patient.id
                        JOIN users doctor ON p.doctor_id = doctor.id
                        WHERE (p.status = 'pending' OR p.status = 'partial')
                        AND p.id NOT IN (SELECT prescription_id FROM pharmacy_bills WHERE 1)
                    ";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search_query)) {
                        $sql .= " AND (patient.name LIKE ?)";
                        $search_term = "%{$search_query}%";
                        $params[] = $search_term;
                        $types .= "s";
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
                            pi.id as item_id,
                            pi.medicine_id,
                            m.name as medicine_name,
                            m.quantity as stock_quantity,
                            m.unit_price,
                            pi.quantity_prescribed,
                            pi.quantity_dispensed
                        FROM prescription_items pi
                        JOIN medicines m ON pi.medicine_id = m.id
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
                        
                        // Handle direct status change for UNOCCUPIED beds
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
                        
                        // Handle complex patient assignment/discharge
                        if (isset($_POST['patient_id'])) {
                            $new_patient_id = empty($_POST['patient_id']) ? null : (int)$_POST['patient_id'];
                            $new_doctor_id = empty($_POST['doctor_id']) ? null : (int)$_POST['doctor_id'];

                            if ($new_patient_id != $old_patient_id) {
                                if ($old_patient_id) {
                                    $dis_stmt = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE accommodation_id = ? AND patient_id = ? AND discharge_date IS NULL");
                                    $dis_stmt->bind_param("ii", $id, $old_patient_id);
                                    $dis_stmt->execute();
                                    $dis_stmt->close();
                                    log_activity($conn, $user_id, "discharge_patient", $old_patient_id, "Discharged patient from {$type} ID {$id}.");
                                }
                                
                                if ($new_patient_id) {
                                    $adm_stmt = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, accommodation_id, admission_date) VALUES (?, ?, ?, NOW())");
                                    $adm_stmt->bind_param("iii", $new_patient_id, $new_doctor_id, $id);
                                    $adm_stmt->execute();
                                    $adm_stmt->close();
                                    
                                    $updates[] = "status = 'occupied'";
                                    log_activity($conn, $user_id, "admit_patient", $new_patient_id, "Admitted patient to {$type} ID {$id}.");
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

                case 'process_clearance':
                    if (empty($_POST['discharge_id']) || empty($_POST['notes'])) {
                        throw new Exception("Discharge ID and notes are required.");
                    }
                    $discharge_id = (int)$_POST['discharge_id'];
                    $notes = trim($_POST['notes']);

                    $conn->begin_transaction();
                    $transaction_active = true;

                    // Get the current step details
                    $stmt_step = $conn->prepare("SELECT admission_id, clearance_step FROM discharge_clearance WHERE id = ? FOR UPDATE");
                    $stmt_step->bind_param("i", $discharge_id);
                    $stmt_step->execute();
                    $current_step = $stmt_step->get_result()->fetch_assoc();
                    $stmt_step->close();

                    if (!$current_step) {
                        throw new Exception("Discharge record not found.");
                    }
                    
                    $admission_id = $current_step['admission_id'];

                    // Enforce sequential clearing
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
                        checkAndFinalizeDischarge($conn, $admission_id);
                        log_activity($conn, $user_id, 'process_discharge_clearance', null, "Processed discharge clearance #{$discharge_id}. Notes: {$notes}");
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
                        
                        // Fetch admission details
                        $stmt_adm = $conn->prepare("
                            SELECT 
                                a.patient_id, a.admission_date,
                                COALESCE(a.discharge_date, NOW()) as effective_discharge_date,
                                acc.price_per_day
                            FROM admissions a
                            LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
                            WHERE a.id = ?
                        ");
                        $stmt_adm->bind_param("i", $admission_id);
                        $stmt_adm->execute();
                        $admission = $stmt_adm->get_result()->fetch_assoc();
                        $stmt_adm->close();
                        
                        if (!$admission) throw new Exception("Admission record not found.");
                        
                        // 1. Calculate Accommodation Cost
                        $admission_date = new DateTime($admission['admission_date']);
                        $discharge_date = new DateTime($admission['effective_discharge_date']);
                        $duration = max(1, $admission_date->diff($discharge_date)->days + 1);
                        $accommodation_cost = $duration * ($admission['price_per_day'] ?? 0);
                        
                        // 2. Calculate Medicine Cost for this admission
                        $stmt_med = $conn->prepare("
                            SELECT SUM(pi.quantity_dispensed * m.unit_price) as total_med_cost
                            FROM prescription_items pi
                            JOIN prescriptions p ON pi.prescription_id = p.id
                            JOIN medicines m ON pi.medicine_id = m.id
                            WHERE p.admission_id = ? AND pi.quantity_dispensed > 0
                        ");
                        $stmt_med->bind_param("i", $admission_id);
                        $stmt_med->execute();
                        $medicine_cost = $stmt_med->get_result()->fetch_assoc()['total_med_cost'] ?? 0.00;
                        $stmt_med->close();

                        // 3. Calculate Lab Test Cost for this admission
                        $stmt_lab = $conn->prepare("
                            SELECT SUM(cost) as total_lab_cost FROM lab_results 
                            WHERE patient_id = ? AND test_date BETWEEN ? AND ?
                        ");
                        $stmt_lab->bind_param("iss", $admission['patient_id'], $admission['admission_date'], $admission['effective_discharge_date']);
                        $stmt_lab->execute();
                        $lab_cost = $stmt_lab->get_result()->fetch_assoc()['total_lab_cost'] ?? 0.00;
                        $stmt_lab->close();

                        // 4. Calculate Total Amount and Description
                        $total_amount = $accommodation_cost + $medicine_cost + $lab_cost;
                        $description = sprintf(
                            "Final bill for admission #%d. Accommodation (%d days): ₹%.2f, Medicines: ₹%.2f, Lab Tests: ₹%.2f.",
                            $admission_id, $duration, $accommodation_cost, $medicine_cost, $lab_cost
                        );
                        
                        // 5. Insert the transaction
                        $stmt_insert = $conn->prepare("
                            INSERT INTO transactions (user_id, admission_id, description, amount, type, status) 
                            VALUES (?, ?, ?, ?, 'payment', 'pending')
                        ");
                        $stmt_insert->bind_param("iisd", $admission['patient_id'], $admission_id, $description, $total_amount);
                        $stmt_insert->execute();
                        $new_invoice_id = $conn->insert_id;
                        $stmt_insert->close();

                        log_activity($conn, $user_id, 'generate_invoice', $admission['patient_id'], "Generated invoice #{$new_invoice_id}. Amount: {$total_amount}");
                        
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

                        // Update transaction status
                        $stmt = $conn->prepare("UPDATE transactions SET status = 'paid', payment_mode = ?, paid_at = NOW() WHERE id = ? AND status = 'pending'");
                        $stmt->bind_param("si", $payment_mode, $transaction_id);
                        $stmt->execute();
                        
                        if ($stmt->affected_rows === 0) {
                            throw new Exception("Payment failed. The invoice may already be paid or does not exist.");
                        }
                        $stmt->close();
                        
                        // --- PDF Generation and Emailing ---
                        // Fetch patient email
                        $stmt_user = $conn->prepare("SELECT u.email FROM users u JOIN transactions t ON u.id = t.user_id WHERE t.id = ?");
                        $stmt_user->bind_param("i", $transaction_id);
                        $stmt_user->execute();
                        $patient_email = $stmt_user->get_result()->fetch_assoc()['email'];
                        $stmt_user->close();

                        if ($patient_email) {
                            // Assumes send_mail.php is in the parent directory and configured
                            require_once '../send_mail.php'; 
                            
                            // Generate the PDF content by including a template
                            ob_start();
                            // The included file will have access to $conn and $transaction_id
                            include 'invoice_template.php'; 
                            $html = ob_get_clean();

                            $options = new Options();
                            $options->set('isRemoteEnabled', true);
                            $dompdf = new Dompdf($options);
                            $dompdf->loadHtml($html);
                            $dompdf->setPaper('A4', 'portrait');
                            $dompdf->render();
                            $pdf_output = $dompdf->output();
                            
                            // Send email with PDF attachment
                            $subject = "Your MedSync Hospital Bill (Invoice #" . $transaction_id . ")";
                            $body = "Dear Patient,<br><br>Thank you for your payment. Please find your detailed bill attached.<br><br>Sincerely,<br>MedSync Hospital";
                            send_mail($patient_email, $subject, $body, $pdf_output, 'invoice-' . $transaction_id . '.pdf');
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
                        
                        // 1. Check if prescription is already fully billed
                        $stmt_check = $conn->prepare("SELECT id FROM pharmacy_bills WHERE prescription_id = ?");
                        $stmt_check->bind_param("i", $prescription_id);
                        $stmt_check->execute();
                        if ($stmt_check->get_result()->num_rows > 0) {
                            throw new Exception("This prescription has already been billed.");
                        }
                        $stmt_check->close();

                        // 2. Process each item, update stock, and calculate total
                        $patient_id_stmt = $conn->prepare("SELECT patient_id FROM prescriptions WHERE id = ?");
                        $patient_id_stmt->bind_param("i", $prescription_id);
                        $patient_id_stmt->execute();
                        $patient_id = $patient_id_stmt->get_result()->fetch_assoc()['patient_id'];
                        $patient_id_stmt->close();

                        foreach ($items_to_dispense as $item) {
                            $medicine_id = (int)$item['medicine_id'];
                            $quantity_to_dispense = (int)$item['quantity'];

                            if ($quantity_to_dispense <= 0) continue; // Skip items not being dispensed

                            // Lock medicine row for safe stock update
                            $stmt_med = $conn->prepare("SELECT quantity, unit_price FROM medicines WHERE id = ? FOR UPDATE");
                            $stmt_med->bind_param("i", $medicine_id);
                            $stmt_med->execute();
                            $medicine = $stmt_med->get_result()->fetch_assoc();

                            if ($medicine['quantity'] < $quantity_to_dispense) {
                                throw new Exception("Insufficient stock for medicine ID {$medicine_id}.");
                            }

                            // Update medicine stock
                            $new_stock = $medicine['quantity'] - $quantity_to_dispense;
                            $stmt_update_stock = $conn->prepare("UPDATE medicines SET quantity = ? WHERE id = ?");
                            $stmt_update_stock->bind_param("ii", $new_stock, $medicine_id);
                            $stmt_update_stock->execute();
                            $stmt_update_stock->close();

                            // Update prescription item dispensed quantity
                            $stmt_update_item = $conn->prepare("UPDATE prescription_items SET quantity_dispensed = quantity_dispensed + ? WHERE prescription_id = ? AND medicine_id = ?");
                            $stmt_update_item->bind_param("iii", $quantity_to_dispense, $prescription_id, $medicine_id);
                            $stmt_update_item->execute();
                            $stmt_update_item->close();
                            
                            // Add to total amount
                            $total_amount += $quantity_to_dispense * $medicine['unit_price'];
                        }

                        if ($total_amount <= 0) {
                            throw new Exception("Cannot create a bill with zero total amount.");
                        }

                        // 3. Create transaction record
                        $description = "Pharmacy Bill for Prescription #" . $prescription_id;
                        $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, description, amount, status, payment_mode, paid_at) VALUES (?, ?, ?, 'paid', ?, NOW())");
                        $stmt_trans->bind_param("isds", $patient_id, $description, $total_amount, $payment_mode);
                        $stmt_trans->execute();
                        $transaction_id = $conn->insert_id;
                        $stmt_trans->close();

                        // 4. Create pharmacy bill record
                        $stmt_bill = $conn->prepare("INSERT INTO pharmacy_bills (prescription_id, transaction_id, created_by_staff_id, total_amount) VALUES (?, ?, ?, ?)");
                        $stmt_bill->bind_param("iiid", $prescription_id, $transaction_id, $staff_id, $total_amount);
                        $stmt_bill->execute();
                        $bill_id = $conn->insert_id;
                        $stmt_bill->close();
                        
                        // 5. Update overall prescription status
                        // (You can add more complex logic here for partial vs fully dispensed)
                        $stmt_update_presc = $conn->prepare("UPDATE prescriptions SET status = 'dispensed' WHERE id = ?");
                        $stmt_update_presc->bind_param("i", $prescription_id);
                        $stmt_update_presc->execute();
                        $stmt_update_presc->close();

                        log_activity($conn, $staff_id, 'create_pharmacy_bill', $patient_id, "Created pharmacy bill #{$bill_id} for prescription #{$prescription_id}. Amount: {$total_amount}");

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
        http_response_code(400); // Bad Request
        $response['message'] = $e->getMessage();
    }

    if (isset($conn) && $conn->query("SELECT 1")) {
        $conn->close();
    }
    echo json_encode($response);
    exit(); // Stop script execution after handling API request
}


// --- Standard Page Load Security & Session Management ---

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


$session_timeout = 1800; // 30 minutes
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
    $profile_picture_path = '../uploads/profile_pictures/default.png'; // A default placeholder
}

// Generate a CSRF token if one doesn't exist
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// --- PDF GENERATION LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'download_pharmacy_bill') {
    if (empty($_GET['id'])) {
        die('Bill ID is required to generate a PDF.');
    }
    $bill_id = (int)$_GET['id'];
    $conn = getDbConnection();

    // --- Data Fetching for the Bill ---
    $bill_sql = "
        SELECT 
            pb.*, 
            p.prescription_date,
            patient.name as patient_name, patient.display_user_id as patient_display_id,
            doctor.name as doctor_name,
            staff.name as staff_name,
            t.payment_mode
        FROM pharmacy_bills pb
        JOIN prescriptions p ON pb.prescription_id = p.id
        JOIN transactions t ON pb.transaction_id = t.id
        JOIN users patient ON p.patient_id = patient.id
        JOIN users doctor ON p.doctor_id = doctor.id
        JOIN users staff ON pb.created_by_staff_id = staff.id
        WHERE pb.id = ?
    ";
    $stmt_bill = $conn->prepare($bill_sql);
    $stmt_bill->bind_param("i", $bill_id);
    $stmt_bill->execute();
    $bill_data = $stmt_bill->get_result()->fetch_assoc();

    $items_sql = "
        SELECT 
            m.name,
            pi.quantity_dispensed,
            m.unit_price,
            (pi.quantity_dispensed * m.unit_price) as subtotal
        FROM prescription_items pi
        JOIN medicines m ON pi.medicine_id = m.id
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

    // --- HTML Template for PDF (Adapted from api.php) ---
    $medsync_logo_path = '../images/logo.png';
    $hospital_logo_path = '../images/hospital.png';
    $medsync_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($medsync_logo_path));
    $hospital_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($hospital_logo_path));

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Pharmacy Bill</title>
        <style>
            @page { margin: 130px 20px 20px 20px; }
            body { font-family: "Poppins", sans-serif; color: #333; font-size: 14px; }
            .header { position: fixed; top: -110px; left: 0; right: 0; width: 100%; height: 120px; }
            .medsync-logo { position: absolute; top: 10px; left: 20px; }
            .medsync-logo img { width: 80px; }
            .hospital-logo { position: absolute; top: 10px; right: 20px; }
            .hospital-logo img { width: 70px; }
            .hospital-details { text-align: center; margin-top: 0; }
            .hospital-details h2 { margin: 0; font-size: 1.5em; color: #007BFF; }
            .hospital-details p { margin: 2px 0; font-size: 0.85em; }
            .report-title { text-align: center; margin-top: 0; margin-bottom: 20px; }
            .report-title h1 { margin: 0; font-size: 1.8em; }
            .bill-details { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; }
            .bill-details p { margin: 5px 0; }
            .data-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
            .data-table th, .data-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .data-table th { background-color: #f2f2f2; font-weight: bold; }
            .total-section { text-align: right; margin-top: 20px; font-size: 1.2em; font-weight: bold; }
            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 0.8em; color: #aaa; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="medsync-logo"><img src="' . $medsync_logo_base64 . '"></div>
            <div class="hospital-details">
                <h2>Calysta Health Institute</h2>
                <p>Kerala, India</p>
                <p>+91 45235 31245 | medsync.calysta@gmail.com</p>
            </div>
            <div class="hospital-logo"><img src="' . $hospital_logo_base64 . '"></div>
        </div>

        <div class="report-title">
            <h1>Pharmacy Bill / Receipt</h1>
        </div>

        <div class="bill-details">
            <p><strong>Bill ID:</strong> PHARM-' . htmlspecialchars(str_pad($bill_data['id'], 5, '0', STR_PAD_LEFT)) . '</p>
            <p><strong>Patient:</strong> ' . htmlspecialchars($bill_data['patient_name']) . ' (' . htmlspecialchars($bill_data['patient_display_id']) . ')</p>
            <p><strong>Prescribing Doctor:</strong> ' . htmlspecialchars($bill_data['doctor_name']) . '</p>
            <p><strong>Date Issued:</strong> ' . htmlspecialchars(date("Y-m-d H:i", strtotime($bill_data['created_at']))) . '</p>
            <p><strong>Issued By:</strong> ' . htmlspecialchars($bill_data['staff_name']) . '</p>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>';
    foreach ($items_data as $item) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($item['name']) . '</td>
                    <td>' . htmlspecialchars($item['quantity_dispensed']) . '</td>
                    <td>₹' . htmlspecialchars(number_format($item['unit_price'], 2)) . '</td>
                    <td>₹' . htmlspecialchars(number_format($item['subtotal'], 2)) . '</td>
                  </tr>';
    }
    $html .= '</tbody></table>
        <div class="total-section">
            Total: ₹' . htmlspecialchars(number_format($bill_data['total_amount'], 2)) . '
        </div>
        <div class="footer">
            MedSync Healthcare Platform | &copy; ' . date('Y') . ' Calysta Health Institute
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
    $dompdf->stream('pharmacy_bill_' . $bill_id . '.pdf', ["Attachment" => 1]);
    exit();
}
?>