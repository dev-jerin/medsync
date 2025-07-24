<?php
require_once 'config.php';

// --- Session Security ---
// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has the correct role ('staff')
if ($_SESSION['role'] !== 'staff') {
    // If the role is incorrect, destroy the session and redirect to login
    session_destroy();
    header("Location: login.php?error=unauthorized");
    exit();
}

// 3. Check for session timeout (e.g., 30 minutes)
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?session_expired=true");
    exit();
}
// Update the session time
$_SESSION['loggedin_time'] = time();

// --- Fetch Staff's Full Name from Database ---
// Note: This part requires a valid database connection from 'config.php' to work.
// For demonstration, we'll have a fallback if the connection fails or returns no data.


/**
 * Generates a unique, sequential display ID for a new user based on their role.
 * Uses a dedicated counter table with row locking to prevent race conditions.
 *
 * @param string $role The role of the user ('admin', 'doctor', 'staff', 'user').
 * @param mysqli $conn The database connection object.
 * @return string The formatted display ID.
 * @throws Exception If the role is invalid or a database error occurs.
 */

/**
 * Logs a specific action to the activity_logs table.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user performing the action (the staff member).
 * @param string $action A description of the action (e.g., 'admit_patient', 'update_medicine').
 * @param int|null $target_user_id The ID of the user being affected. Can be null.
 * @param string $details A detailed description of the change.
 * @return bool True on success, false on failure.
 */
function log_activity($conn, $user_id, $action, $target_user_id = null, $details = '')
{
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, target_user_id, details) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        // Handle error, e.g., log to a file, but don't stop the main execution
        error_log("Failed to prepare statement for activity log: " . $conn->error);
        return false;
    }
    $stmt->bind_param("isis", $user_id, $action, $target_user_id, $details);
    return $stmt->execute();
}

function generateDisplayId($role, $conn)
{
    $prefix_map = [
        'admin' => 'A',
        'doctor' => 'D',
        'staff' => 'S',
        'user' => 'U'
    ];

    if (!isset($prefix_map[$role])) {
        throw new Exception("Invalid role specified for ID generation.");
    }
    $prefix = $prefix_map[$role];

    // Start transaction for safe counter update
    $conn->begin_transaction();
    try {
        // Lock the row for the specific role to prevent race conditions
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            // If the prefix doesn't exist, you might want to create it, but for now we'll throw an error.
            throw new Exception("Role prefix '$prefix' not found in counters table.");
        }
        $row = $result->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;

        // Update the counter
        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();

        // Commit the transaction
        $conn->commit();

        // Format the new ID with leading zeros
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);

    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        // Re-throw the exception to be caught by the main handler
        throw $e;
    }
}

// --- API ENDPOINT LOGIC (Handles AJAX requests) ---
if (isset($_GET['fetch']) || (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];
     $staff_user_id_for_log = $_SESSION['user_id']; // Get the staff's ID for logging
    $conn = getDbConnection();

    

    try {
        // CSRF token validation for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                throw new Exception('Invalid CSRF token.');
            }
        }

        // Handle POST actions (form submissions)
        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            switch ($action) {
                case 'admitPatient':
                    if (empty($_POST['patient_id']) || empty($_POST['admission_date']) || empty($_POST['doctor_id']) || empty($_POST['resource_id'])) {
                        throw new Exception('Patient, date, doctor, and bed/room are required.');
                    }

                    $patient_id = (int) $_POST['patient_id'];
                    $admission_date = $_POST['admission_date'];
                    $doctor_id = (int) $_POST['doctor_id'];
                    $resource_parts = explode('-', $_POST['resource_id']);
                    $resource_type = $resource_parts[0];
                    $resource_id = (int) $resource_parts[1];

                    $conn->begin_transaction();
                    $now_timestamp = date('Y-m-d H:i:s');

                    if ($resource_type === 'bed') {
                        $stmt_admit = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, bed_id, admission_date) VALUES (?, ?, ?, ?)");
                        $stmt_admit->bind_param("iiis", $patient_id, $doctor_id, $resource_id, $admission_date);
                        $stmt_resource = $conn->prepare("UPDATE beds SET status = 'occupied', patient_id = ?, occupied_since = ? WHERE id = ? AND status = 'available'");
                        $stmt_resource->bind_param("isi", $patient_id, $now_timestamp, $resource_id);
                    } elseif ($resource_type === 'room') {
                        $stmt_admit = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, room_id, admission_date) VALUES (?, ?, ?, ?)");
                        $stmt_admit->bind_param("iiis", $patient_id, $doctor_id, $resource_id, $admission_date);
                        $stmt_resource = $conn->prepare("UPDATE rooms SET status = 'occupied', patient_id = ?, occupied_since = ? WHERE id = ? AND status = 'available'");
                        $stmt_resource->bind_param("isi", $patient_id, $now_timestamp, $resource_id);
                    } else {
                        throw new Exception('Invalid resource type provided.');
                    }

                    $stmt_admit->execute();
                    $stmt_resource->execute();

                    if ($stmt_resource->affected_rows === 0) {
                        throw new Exception('The selected bed or room is no longer available. Please refresh and try again.');
                    }

                        // --- Audit Log ---
                    $log_details = "Admitted patient (ID: {$patient_id}) to {$resource_type} (ID: {$resource_id}) under Dr. (ID: {$doctor_id}).";
                    log_activity($conn, $staff_user_id_for_log, 'admit_patient', $patient_id, $log_details);

                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Patient admitted successfully.'];
                    break;

                // --- INVENTORY MANAGEMENT ACTIONS ---
                case 'addMedicine':
                    if (empty($_POST['name']) || empty($_POST['quantity']) || empty($_POST['unit_price'])) {
                        throw new Exception('Medicine name, quantity, and unit price are required.');
                    }
                    $name = $_POST['name'];
                    $description = $_POST['description'] ?? null;
                    $quantity = (int) $_POST['quantity'];
                    $unit_price = (float) $_POST['unit_price'];
                    $low_stock_threshold = (int) ($_POST['low_stock_threshold'] ?? 10);

                    $stmt = $conn->prepare("INSERT INTO medicines (name, description, quantity, unit_price, low_stock_threshold) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssidi", $name, $description, $quantity, $unit_price, $low_stock_threshold);
                    if ($stmt->execute()) {
                        // --- Audit Log ---
                        $log_details = "Added new medicine '{$name}' with initial quantity {$quantity}.";
                        log_activity($conn, $staff_user_id_for_log, 'add_medicine', null, $log_details);
                        $response = ['success' => true, 'message' => 'Medicine added successfully.'];
                    } else {
                        throw new Exception('Failed to add medicine. It might already exist.');
                    }
                    break;

                case 'updateMedicine':
                    if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['quantity']) || empty($_POST['unit_price'])) {
                        throw new Exception('Medicine ID, name, quantity, and unit price are required.');
                    }
                    $id = (int) $_POST['id'];
                    $name = $_POST['name'];
                    $description = $_POST['description'] ?? null;
                    $quantity = (int) $_POST['quantity'];
                    $unit_price = (float) $_POST['unit_price'];
                    $low_stock_threshold = (int) ($_POST['low_stock_threshold'] ?? 10);

                    $stmt = $conn->prepare("UPDATE medicines SET name = ?, description = ?, quantity = ?, unit_price = ?, low_stock_threshold = ? WHERE id = ?");
                    $stmt->bind_param("ssidii", $name, $description, $quantity, $unit_price, $low_stock_threshold, $id);
                    if ($stmt->execute()) {
                          // --- Audit Log ---
                        $log_details = "Updated medicine '{$name}' (ID: {$id}). New quantity: {$quantity}.";
                        log_activity($conn, $staff_user_id_for_log, 'update_medicine', null, $log_details);
                        $response = ['success' => true, 'message' => 'Medicine updated successfully.'];
                    } else {
                        throw new Exception('Failed to update medicine.');
                    }
                    break;

                case 'deleteMedicine':
                    if (empty($_POST['id'])) {
                        throw new Exception('Medicine ID is required.');
                    }
                    $id = (int) $_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                           // --- Audit Log ---
                        // To make the log more descriptive, you can fetch the medicine name before deleting
                        $log_details = "Deleted medicine with ID: {$id}.";
                        log_activity($conn, $staff_user_id_for_log, 'delete_medicine', null, $log_details);
                        $response = ['success' => true, 'message' => 'Medicine deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete medicine.');
                    }
                    break;

                case 'updateBlood':
                    if (empty($_POST['blood_group']) || !isset($_POST['quantity_ml'])) {
                        throw new Exception('Blood group and quantity are required.');
                    }
                    $blood_group = $_POST['blood_group'];
                    $quantity_ml = (int) $_POST['quantity_ml'];
                    $low_stock_threshold_ml = (int) ($_POST['low_stock_threshold_ml'] ?? 5000);

                    $stmt = $conn->prepare("INSERT INTO blood_inventory (blood_group, quantity_ml, low_stock_threshold_ml) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity_ml = ?, low_stock_threshold_ml = ?");
                    $stmt->bind_param("siiii", $blood_group, $quantity_ml, $low_stock_threshold_ml, $quantity_ml, $low_stock_threshold_ml);
                    if ($stmt->execute()) {
                                   // --- Audit Log ---
                        $log_details = "Updated blood inventory for group '{$blood_group}' to {$quantity_ml} ml.";
                        log_activity($conn, $staff_user_id_for_log, 'update_blood_inventory', null, $log_details);
                        $response = ['success' => true, 'message' => 'Blood inventory updated successfully.'];
                    } else {
                        throw new Exception('Failed to update blood inventory.');
                    }
                    break;

                case 'addUser':
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email']) || empty($_POST['role']) || empty($_POST['password'])) {
                            throw new Exception('Please fill all required fields.');
                        }
                        $role = $_POST['role'];
                        if (!in_array($role, ['user', 'doctor'])) {
                            throw new Exception('Permission denied to create this role.');
                        }

                        $name = $_POST['name'];
                        $username = $_POST['username'];
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $phone = $_POST['phone'];
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
                        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;


                        $profile_picture = 'default.png';
                        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                            $target_dir = "uploads/profile_pictures/";
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            $image_name = uniqid() . '_' . basename($_FILES["profile_picture"]["name"]);
                            $target_file = $target_dir . $image_name;
                            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                                $profile_picture = $image_name;
                            }
                        }

                        $display_user_id = generateDisplayId($role, $conn);

                        $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role, gender, phone, profile_picture, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssssss", $display_user_id, $name, $username, $email, $password, $role, $gender, $phone, $profile_picture, $date_of_birth);
                        $stmt->execute();
                        $user_id = $conn->insert_id;

                        if ($role === 'doctor') {
                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty) VALUES (?, ?)");
                            $stmt_doctor->bind_param("is", $user_id, $_POST['specialty']);
                            $stmt_doctor->execute();
                        }
                        // --- Audit Log ---
                        $log_details = "Created a new user '{$username}' (ID: {$display_user_id}) with the role '{$role}'.";
                        log_activity($conn, $staff_user_id_for_log, 'create_user', $user_id, $log_details);

                        $conn->commit();
                        $response = ['success' => true, 'message' => ucfirst($role) . ' added successfully.'];
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;

             case 'updateUser':
    $conn->begin_transaction();
    try {
        if (empty($_POST['id'])) {
            throw new Exception('User ID is missing.');
        }
        $id = (int)$_POST['id'];

        $stmt_check_role = $conn->prepare("SELECT role, profile_picture FROM users WHERE id = ?");
        $stmt_check_role->bind_param("i", $id);
        $stmt_check_role->execute();
        $user_to_update = $stmt_check_role->get_result()->fetch_assoc();
        if (!$user_to_update || !in_array($user_to_update['role'], ['user', 'doctor'])) {
            throw new Exception('Permission denied to modify this user.');
        }

        // Correctly retrieve all fields from the form
        $name = $_POST['name'];
        $username = $_POST['username'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;

        $sql_parts = ["name = ?", "username = ?", "email = ?", "phone = ?", "date_of_birth = ?", "gender = ?", "active = ?"];
        $params = [$name, $username, $email, $phone, $date_of_birth, $gender, $active];
        $types = "ssssssi";

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            if ($user_to_update['profile_picture'] && $user_to_update['profile_picture'] !== 'default.png') {
                $old_pfp_path = "uploads/profile_pictures/" . $user_to_update['profile_picture'];
                if (file_exists($old_pfp_path)) {
                    unlink($old_pfp_path);
                }
            }
            $target_dir = "uploads/profile_pictures/";
            $image_name = uniqid() . '_' . basename($_FILES["profile_picture"]["name"]);
            $target_file = $target_dir . $image_name;
            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                $sql_parts[] = "profile_picture = ?";
                $params[] = $image_name;
                $types .= "s";
            }
        }

        // Add password update logic if a new password is provided
        if (!empty($_POST['password'])) {
            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_parts[] = "password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }

        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();

        if ($user_to_update['role'] === 'doctor') {
            $stmt_doctor = $conn->prepare("UPDATE doctors SET specialty = ?, qualifications = ? WHERE user_id = ?");
            $stmt_doctor->bind_param("ssi", $_POST['specialty'], $_POST['qualifications'], $id);
            $stmt_doctor->execute();
        }
    // --- Audit Log ---
                        $log_details = "Updated user '{$username}' (ID: {$id}).";
                        log_activity($conn, $staff_user_id_for_log, 'update_user', $id, $log_details);
        $conn->commit();
        $response = ['success' => true, 'message' => 'User updated successfully.'];
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    break;

                case 'deleteUser':
                    if (empty($_POST['id'])) {
                        throw new Exception('User ID is missing.');
                    }
                    $id = (int) $_POST['id'];

                    $stmt_check_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt_check_role->bind_param("i", $id);
                    $stmt_check_role->execute();
                    $user_to_update = $stmt_check_role->get_result()->fetch_assoc();
                    if (!$user_to_update || !in_array($user_to_update['role'], ['user', 'doctor'])) {
                        throw new Exception('Permission denied to deactivate this user.');
                    }

                    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                        // --- Audit Log ---
                    $log_details = "Deactivated user with ID: {$id}.";
                    log_activity($conn, $staff_user_id_for_log, 'deactivate_user', $id, $log_details);
                    $response = ['success' => true, 'message' => 'User deactivated successfully.'];
                    break;

                           case 'removeProfilePicture':
                    if (empty($_POST['id'])) {
                        throw new Exception('Invalid user ID.');
                    }
                    $id = (int) $_POST['id'];

                    $stmt_check = $conn->prepare("SELECT role, profile_picture FROM users WHERE id = ?");
                    $stmt_check->bind_param("i", $id);
                    $stmt_check->execute();
                    $user_data = $stmt_check->get_result()->fetch_assoc();

                    if (!$user_data || !in_array($user_data['role'], ['user', 'doctor'])) {
                        throw new Exception('Permission denied to modify this user.');
                    }

                    if ($user_data && $user_data['profile_picture'] !== 'default.png') {
                        $pfp_path = "uploads/profile_pictures/" . $user_data['profile_picture'];
                        if (file_exists($pfp_path)) {
                            unlink($pfp_path); // This correctly removes the file from the server
                        }
                    }

                    $stmt_update = $conn->prepare("UPDATE users SET profile_picture = 'default.png' WHERE id = ?");
                    $stmt_update->bind_param("i", $id);
                    if ($stmt_update->execute()) {
                         // --- Audit Log ---
                        $log_details = "Removed profile picture for user ID: {$id}.";
                        log_activity($conn, $staff_user_id_for_log, 'update_user', $id, $log_details);
                        $response = ['success' => true, 'message' => 'Profile picture removed successfully.'];
                    } else {
                        throw new Exception('Failed to remove profile picture.');
                    }
                    break;
            }
        }


        // Handle GET requests (data fetching)
        elseif (isset($_GET['fetch'])) {
            $fetch_target = $_GET['fetch'];
            switch ($fetch_target) {

                case 'search_unassigned_users':
                    $term = $_GET['term'] ?? '';
                    $searchTerm = "%{$term}%";
                    $sql = "SELECT u.id, u.name, u.display_user_id
            FROM users u
            LEFT JOIN beds b ON u.id = b.patient_id AND b.status = 'occupied'
            LEFT JOIN rooms r ON u.id = r.patient_id AND r.status = 'occupied'
            WHERE u.role = 'user' AND u.active = 1 AND b.id IS NULL AND r.id IS NULL
            AND (u.name LIKE ? OR u.display_user_id LIKE ?)
            ORDER BY u.name ASC
            LIMIT 10";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ss", $searchTerm, $searchTerm);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'users':
                    if (!isset($_GET['role'])) {
                        throw new Exception('User role not specified.');
                    }
                    $role = $_GET['role'];
                    if (!in_array($role, ['user', 'doctor'])) {
                        throw new Exception('Invalid role specified.');
                    }
                    $search = $_GET['search'] ?? '';

                          $sql = "SELECT u.id, u.display_user_id, u.name, u.username, u.email, u.phone, u.date_of_birth, u.gender, u.role, u.active, u.created_at, u.profile_picture, d.specialty, d.qualifications
            FROM users u
            LEFT JOIN doctors d ON u.id = d.user_id
            WHERE u.role = ?";

                    $params = [$role];
                    $types = "s";

                    if (!empty($search)) {
                        $sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.display_user_id LIKE ?)";
                        $search_term = "%{$search}%";
                        array_push($params, $search_term, $search_term, $search_term);
                        $types .= "sss";
                    }
                    $sql .= " ORDER BY u.created_at DESC";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'user_details':
                    if (empty($_GET['id']))
                        throw new Exception('User ID not specified.');
                    $user_id = (int) $_GET['id'];
                    $data = [];

                    // Fetch basic user info
                    $stmt = $conn->prepare("SELECT u.*, d.specialty, d.qualifications FROM users u LEFT JOIN doctors d ON u.id = d.user_id WHERE u.id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user_data = $stmt->get_result()->fetch_assoc();

                    // Security check: Staff can only view 'user' or 'doctor'
                    if (!$user_data || !in_array($user_data['role'], ['user', 'doctor'])) {
                        throw new Exception('Permission denied to view this user.');
                    }
                    $data['user'] = $user_data;

                    // Fetch assigned patients (if doctor)
                    if ($data['user']['role'] === 'doctor') {
                        $stmt_patients = $conn->prepare("SELECT u.name, u.display_user_id, a.appointment_date, a.status
                                        FROM appointments a JOIN users u ON a.user_id = u.id
                                        WHERE a.doctor_id = ? ORDER BY a.appointment_date DESC LIMIT 10");
                        $stmt_patients->bind_param("i", $user_id);
                        $stmt_patients->execute();
                        $data['assigned_patients'] = $stmt_patients->get_result()->fetch_all(MYSQLI_ASSOC);
                    }

                    $response = ['success' => true, 'data' => $data];
                    break;



                case 'departments':
                    $result = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'active_doctors':
                    $sql = "SELECT u.id, u.name, d.specialty FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.active = 1 AND u.role = 'doctor' ORDER BY u.name ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'available_beds':
                    $sql = "SELECT b.id, b.bed_number, w.name as ward_name FROM beds b JOIN wards w ON b.ward_id = w.id WHERE b.status = 'available' ORDER BY w.name, b.bed_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'available_rooms':
                    $sql = "SELECT id, room_number FROM rooms WHERE status = 'available' ORDER BY room_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'unassigned_patients':
                    $sql = "SELECT u.id, u.name, u.display_user_id FROM users u LEFT JOIN beds b ON u.id = b.patient_id AND b.status = 'occupied' LEFT JOIN rooms r ON u.id = r.patient_id AND r.status = 'occupied' WHERE u.role = 'user' AND u.active = 1 AND b.id IS NULL AND r.id IS NULL ORDER BY u.name ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'bed_room_status':
                    // Fetches the current status of all beds and rooms for real-time updates
                    $beds_sql = "SELECT id, status, 'bed' as type FROM beds";
                    $rooms_sql = "SELECT id, status, 'room' as type FROM rooms";

                    $beds_result = $conn->query($beds_sql);
                    $rooms_result = $conn->query($rooms_sql);

                    $data = array_merge($beds_result->fetch_all(MYSQLI_ASSOC), $rooms_result->fetch_all(MYSQLI_ASSOC));
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'medicines':
                    $result = $conn->query("SELECT * FROM medicines ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'blood_inventory':
                    $result = $conn->query("SELECT * FROM blood_inventory ORDER BY blood_group ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'wards':
                    $result = $conn->query("SELECT id, name FROM wards WHERE is_active = 1 ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'beds':
                    $sql = "SELECT b.id, b.ward_id, w.name as ward_name, b.bed_number, b.status, b.patient_id, u.name as patient_name
            FROM beds b
            JOIN wards w ON b.ward_id = w.id
            LEFT JOIN users u ON b.patient_id = u.id
            ORDER BY w.name, b.bed_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'rooms':
                    $sql = "SELECT r.id, r.room_number, r.status, r.patient_id, u.name as patient_name
            FROM rooms r
            LEFT JOIN users u ON r.patient_id = u.id
            ORDER BY r.room_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'patients_for_beds': // Needed for modals
                    $result = $conn->query("SELECT id, name, display_user_id FROM users WHERE role = 'user' AND active = 1 ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                         case 'my_activity':
                    $staff_id = $_SESSION['user_id'];
                    $sql = "SELECT a.id, a.action, a.details, a.created_at, t.name as target_user_name
                            FROM activity_logs a
                            LEFT JOIN users t ON a.target_user_id = t.id
                            WHERE a.user_id = ?
                            ORDER BY a.created_at DESC
                            LIMIT 50";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $staff_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

            }
        }
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(400);
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}
// --- END API LOGIC ---

$staff_name = "Staff Member"; // Default fallback name
$display_user_id = isset($_SESSION['display_user_id']) ? htmlspecialchars($_SESSION['display_user_id']) : 'STF-000'; // Fallback ID

if (function_exists('getDbConnection')) {
    $conn = getDbConnection();
    if ($conn && !isset($conn->connect_error)) {
        $staff_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
            $staff_name = htmlspecialchars($user_data['name']);
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - MedSync</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        /* --- Admission Search & Scroll --- */
        #patient-search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: var(--bg-light);
            border: 1px solid var(--border-light);
            border-top: none;
            border-radius: 0 0 8px 8px;
            z-index: 100;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            /* Hidden by default */
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
        }

        .search-result-item:hover {
            background-color: var(--bg-grey);
        }

        .search-result-item.no-results {
            color: var(--text-muted);
            cursor: default;
        }

        /* --- Fix for scrollable dropdown --- */
        #admit-resource {
            max-height: 200px;
            /* You can adjust this value */
            overflow-y: auto;
        }

        /* --- STAFF DASHBOARD THEME --- */
        :root {
            --primary-color: #3B82F6;
            /* Blue */
            --primary-color-dark: #2563EB;
            --danger-color: #EF4444;
            /* Red */
            --danger-color-dark: #DC2626;
            --success-color: #22C55E;
            /* Green */
            --warning-color: #F97316;
            /* Orange */
            --info-color: #38BDF8;
            /* Sky Blue */
            --text-dark: #1F2937;
            --text-light: #F9FAFB;
            --text-muted: #6B7280;
            --bg-light: #FFFFFF;
            --bg-grey: #F3F4F6;
            --border-light: #E5E7EB;
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --border-radius: 12px;
            --transition-speed: 0.3s;
        }

        body.dark-mode {
            --primary-color: #60A5FA;
            --primary-color-dark: #3B82F6;
            --text-dark: #F9FAFB;
            --text-light: #1F2937;
            --text-muted: #9CA3AF;
            --bg-light: #1F2937;
            /* Card Background */
            --bg-grey: #111827;
            /* Main Background */
            --border-light: #374151;
        }

        /* --- BASE STYLES --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-grey);
            color: var(--text-dark);
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 260px;
            background-color: var(--bg-light);
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            transition: all var(--transition-speed) ease-in-out;
            z-index: 1000;
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
            border-right: 1px solid var(--border-light);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        .sidebar-header .logo-img {
            height: 40px;
            margin-right: 10px;
        }

        .sidebar-header .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .sidebar-nav {
            flex-grow: 1;
            overflow-y: auto;
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav>ul>li {
            margin-bottom: 0.5rem;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.9rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        .sidebar-nav a i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
            text-align: center;
        }

        .sidebar-nav a:hover {
            background-color: var(--bg-grey);
            color: var(--primary-color);
        }

        .sidebar-nav a.active {
            background-color: var(--primary-color);
            color: white;
        }

        body.dark-mode .sidebar-nav a.active {
            background-color: var(--primary-color-dark);
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 1rem;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.9rem 1rem;
            background-color: transparent;
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-speed);
        }

        .logout-btn:hover {
            background-color: var(--danger-color);
            color: white;
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            margin-left: 260px;
            transition: margin-left var(--transition-speed);
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .main-header .title-group h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .main-header .title-group h2 {
            font-size: 1.2rem;
            font-weight: 400;
            color: var(--text-muted);
            margin: 0.25rem 0 0 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-profile-widget {
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: var(--bg-light);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .user-profile-widget i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .content-panel {
            display: none;
            animation: fadeIn 0.5s ease-in-out;
        }

        .content-panel.active {
            display: block;
        }

        .panel-container {
            background-color: var(--bg-light);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* --- GENERIC & REUSABLE COMPONENTS --- */
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .panel-header h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin: 0;
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-bar input {
            padding: 0.6rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            background-color: var(--bg-grey);
            min-width: 250px;
        }

        body.dark-mode .search-bar input {
            color: var(--text-dark);
            /* In dark mode, --text-dark is light */
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all var(--transition-speed);
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-color-dark);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--danger-color-dark);
        }

        .btn-icon {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
        }

        .btn-icon:hover {
            background-color: var(--bg-grey);
            color: var(--primary-color);
        }

        .custom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .custom-table th,
        .custom-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .custom-table th {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .custom-table tbody tr {
            transition: background-color var(--transition-speed);
        }

        .custom-table tbody tr:hover {
            background-color: var(--bg-grey);
        }

        .badge {
            padding: 0.25em 0.6em;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .badge-danger {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .badge-info {
            background-color: #CFFAFE;
            color: #0E7490;
        }

        .tabs-container {
            display: flex;
            border-bottom: 2px solid var(--border-light);
            margin-bottom: 1.5rem;
        }

        .tab-button {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 500;
            color: var(--text-muted);
            position: relative;
        }

        .tab-button.active {
            color: var(--primary-color);
            font-weight: 600;
        }

        .tab-button.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .small-note {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 1rem;
            text-align: center;
        }

        .small-note i {
            margin-right: 0.5rem;
        }

        /* Form & Input Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* Bed Management Grid */
        .bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
        }

        .bed-card {
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius);
            padding: 1.2rem;
            text-align: center;
            transition: all var(--transition-speed);
        }

        .bed-card h4 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .bed-card .bed-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .bed-card.available {
            border-left: 5px solid var(--success-color);
        }

        .bed-card.available .bed-icon {
            color: var(--success-color);
        }

        .bed-card.occupied {
            border-left: 5px solid var(--danger-color);
        }

        .bed-card.occupied .bed-icon {
            color: var(--danger-color);
        }

        .bed-card.cleaning {
            border-left: 5px solid var(--warning-color);
        }

        .bed-card.cleaning .bed-icon {
            color: var(--warning-color);
        }

        /* Discharge Checklist */
        .checklist {
            list-style: none;
            padding: 0;
        }

        .checklist-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            margin-bottom: 0.8rem;
        }

        .checklist-item .status {
            font-weight: 600;
        }

        .checklist-item.pending .status {
            color: var(--warning-color);
        }

        .checklist-item.cleared .status {
            color: var(--success-color);
        }

        .checklist-item .action button:disabled {
            background-color: var(--text-muted);
            cursor: not-allowed;
        }

        /* --- DASHBOARD ELEMENTS --- */
        .stat-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            border-left: 5px solid var(--primary-color);
        }

        .stat-card .icon {
            font-size: 2rem;
            padding: 1rem;
            border-radius: 50%;
            color: var(--primary-color);
            background-color: var(--bg-grey);
        }

        .stat-card .info .value {
            font-size: 1.75rem;
            font-weight: 600;
        }

        .stat-card .info .label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .quick-actions .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 1rem;
        }

        .quick-actions .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.2rem 1rem;
            border-radius: var(--border-radius);
            background-color: var(--bg-grey);
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s, color 0.2s;
        }

        .quick-actions .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            background-color: var(--primary-color);
            color: white;
        }

        .quick-actions .action-btn i {
            font-size: 1.8rem;
            margin-bottom: 0.75rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .dashboard-grid>div {
            background-color: var(--bg-light);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .chart-container {
            height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }

        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        /* --- THEME TOGGLE --- */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
        }

        .theme-switch {
            display: inline-block;
            height: 24px;
            position: relative;
            width: 48px;
        }

        .theme-switch input {
            display: none;
        }

        .slider {
            background-color: #ccc;
            bottom: 0;
            cursor: pointer;
            left: 0;
            position: absolute;
            right: 0;
            top: 0;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            background-color: #fff;
            content: "";
            height: 18px;
            left: 3px;
            position: absolute;
            bottom: 3px;
            transition: .4s;
            width: 18px;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: var(--primary-color-dark);
        }

        input:checked+.slider:before {
            transform: translateX(24px);
        }

        .theme-switch-wrapper .fa-sun,
        .theme-switch-wrapper .fa-moon {
            margin: 0 8px;
            color: var(--text-muted);
        }

        /* --- MODAL STYLES --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.95);
            background: var(--bg-light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 2000;
            width: 90%;
            max-width: 600px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            max-height: 85vh;
        }

        .modal-overlay.active .modal-container {
            transform: translate(-50%, -50%) scale(1);
            opacity: 1;
            visibility: visible;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-light);
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.4rem;
        }

        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            padding: 1rem 1.5rem;
            background-color: var(--bg-grey);
            border-top: 1px solid var(--border-light);
            border-bottom-left-radius: var(--border-radius);
            border-bottom-right-radius: var(--border-radius);
            gap: 0.8rem;
        }

        /* NEW: Messenger Styles */
        .messenger-layout {
            display: flex;
            height: 70vh;
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius);
        }

        .contact-list {
            width: 35%;
            border-right: 1px solid var(--border-light);
            display: flex;
            flex-direction: column;
        }

        .contact-list-header,
        .chat-header {
            padding: 1rem;
            font-weight: 600;
            border-bottom: 1px solid var(--border-light);
        }

        .contacts {
            flex-grow: 1;
            overflow-y: auto;
        }

        .contact {
            display: flex;
            align-items: center;
            padding: 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border-light);
            gap: 1rem;
        }

        .contact:hover,
        .contact.active {
            background-color: var(--bg-grey);
        }

        .contact .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .contact .name {
            font-weight: 500;
        }

        .chat-window {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .chat-messages {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            padding: 0.75rem 1rem;
            border-radius: 18px;
            max-width: 70%;
        }

        .message.sent {
            background-color: var(--primary-color);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .message.received {
            background-color: var(--bg-grey);
            color: var(--text-dark);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        .chat-input {
            display: flex;
            padding: 1rem;
            border-top: 1px solid var(--border-light);
            gap: 1rem;
        }

        .chat-input input {
            flex-grow: 1;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-light);
            border-radius: 20px;
        }

        /* NEW: Activity Log Styles */
        .activity-timeline {
            list-style: none;
            padding-left: 1.5rem;
            position: relative;
        }

        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: var(--border-light);
        }

        .activity-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .activity-item::before {
            content: '';
            position: absolute;
            left: -1.5rem;
            top: 4px;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: var(--primary-color);
            border: 2px solid var(--bg-light);
        }

        .activity-content {
            padding-left: 1rem;
        }

        .activity-content .description {
            font-weight: 500;
        }

        .activity-content .time {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* --- MOBILE & RESPONSIVE --- */
        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-dark);
            cursor: pointer;
            z-index: 1001;
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        @media (max-width: 992px) {
            .sidebar {
                left: -260px;
            }

            .sidebar.active {
                left: 0;
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.1);
            }

            .main-content {
                margin-left: 0;
            }

            .hamburger-btn {
                display: block;
            }

            .main-header {
                justify-content: flex-start;
                gap: 1rem;
            }

            .user-profile-widget {
                margin-left: auto;
            }

            .sidebar-overlay.active {
                display: block;
            }

            .messenger-layout {
                flex-direction: column;
                height: auto;
            }

            .contact-list {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-light);
                max-height: 200px;
            }
        }
    </style>
</head>

<body class="light-mode">
    <div class="dashboard-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img" onerror="this.style.display='none'">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-home"></i>
                            Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-target="admissions"><i class="fas fa-user-plus"></i>
                            Admissions</a></li>
                    <li><a href="#" class="nav-link" data-target="dispensing"><i class="fas fa-pills"></i> Medication
                            Dispensing</a></li>
                    <li><a href="#" class="nav-link" data-target="discharges"><i class="fas fa-hospital-user"></i>
                            Discharges</a></li>
                    <li><a href="#" class="nav-link" data-target="inventory"><i class="fas fa-boxes-stacked"></i>
                            Inventory</a></li>
                    <li><a href="#" class="nav-link" data-target="lab-results"><i class="fas fa-vials"></i> Lab
                            Results</a></li>
                    <li><a href="#" class="nav-link" data-target="manage-users"><i class="fas fa-users-cog"></i> Manage
                            Users</a></li>
                    <li><a href="#" class="nav-link" data-target="messenger"><i class="fas fa-comments"></i>
                            Messenger</a></li>
                    <li><a href="#" class="nav-link" data-target="activity-log"><i class="fas fa-history"></i> Activity
                            Log</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="title-group">
                    <h1 id="panel-title">Dashboard</h1>
                    <h2 id="welcome-message">Hello, <?php echo $staff_name; ?>!</h2>
                </div>
                <div class="header-actions">
                    <div class="theme-switch-wrapper">
                        <i class="fas fa-sun"></i>
                        <label class="theme-switch" for="theme-toggle">
                            <input type="checkbox" id="theme-toggle" />
                            <span class="slider"></span>
                        </label>
                        <i class="fas fa-moon"></i>
                    </div>
                    <button class="btn-icon" id="settings-btn" title="Dashboard Settings"><i
                            class="fas fa-cog"></i></button>
                    <div class="user-profile-widget">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <strong><?php echo $staff_name; ?></strong><br>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">ID:
                                <?php echo $display_user_id; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div id="dashboard-panel" class="content-panel active">
                <div class="stat-cards-container">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-bed"></i></div>
                        <div class="info">
                            <div class="value">15</div>
                            <div class="label">Pending Admissions</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-file-export"></i></div>
                        <div class="info">
                            <div class="value">8</div>
                            <div class="label">Pending Discharges</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-pills"></i></div>
                        <div class="info">
                            <div class="value">23</div>
                            <div class="label">Prescriptions to Dispense</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-box-open"></i></div>
                        <div class="info">
                            <div class="value">5</div>
                            <div class="label">Low Stock Items</div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-grid" style="margin-top: 2rem; grid-template-columns: 2fr 1fr; gap: 2rem;">
                    <div class="quick-actions">
                        <h3>Quick Actions</h3>
                        <div class="actions-grid">
                            <a href="#" class="action-btn nav-link" data-target="admissions"><i
                                    class="fas fa-user-plus"></i> Admit Patient</a>
                            <a href="#" class="action-btn nav-link" data-target="discharges"><i
                                    class="fas fa-file-export"></i> Process Discharge</a>
                            <a href="#" class="action-btn nav-link" data-target="inventory"><i class="fas fa-dolly"></i>
                                Update Inventory</a>
                            <a href="#" class="action-btn nav-link" data-target="manage-users"><i
                                    class="fas fa-users-cog"></i> Manage Users</a>
                        </div>
                    </div>
                    <div class="panel-container">
                        <h3>Visual Analytics</h3>
                        <div class="chart-container" id="bed-occupancy-chart">
                            <i class="fas fa-chart-pie" style="font-size: 2rem; margin-right: 1rem;"></i>
                            <p>Bed Occupancy Chart</p>
                        </div>
                        <small class="small-note" style="text-align: center; margin-top: 1rem;">Chart library (e.g.,
                            Chart.js) required to render.</small>
                    </div>
                </div>
            </div>



            <div id="admissions-panel" class="content-panel">
                <div class="panel-container">
                    <div class="panel-header">
                        <h3>New Patient Admission</h3>
                    </div>
                    <form id="admission-form" class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1; position: relative;">
                            <label for="admit-patient-search">Search User (by Name or ID)</label>
                            <input type="text" id="admit-patient-search"
                                placeholder="Start typing to search for a user..." autocomplete="off">
                            <div id="patient-search-results"></div>
                            <input type="hidden" id="admit-patient-id" name="patient_id" required>
                        </div>
                        <div class="form-group">
                            <label for="admit-date">Admission Date & Time</label>
                            <input type="datetime-local" id="admit-date" name="admission_date"
                                value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="admit-doctor">Assign Doctor</label>
                            <select id="admit-doctor" name="doctor_id" required>
                                <option value="">Loading doctors...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="admit-resource">Assign Bed or Room</label>
                            <select id="admit-resource" name="resource_id" required>
                                <option value="">Loading resources...</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1; align-items: flex-end;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Register
                                Admission</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="dispensing-panel" class="content-panel">
                <div class="panel-container">
                    <div class="panel-header">
                        <h3>Pending Prescriptions</h3>
                        <div class="search-bar">
                            <input type="text" class="table-search" data-table="dispensing-table"
                                placeholder="Search by patient name...">
                        </div>
                    </div>
                    <table class="custom-table" id="dispensing-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Prescribing Doctor</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Mark Johnson</td>
                                <td>Dr. Alice Williams</td>
                                <td>2025-07-23</td>
                                <td><span class="badge badge-warning">Pending</span></td>
                                <td><button class="btn btn-primary btn-sm" onclick="openModal('dispenseModal')"><i
                                            class="fas fa-eye"></i> View & Dispense</button></td>
                            </tr>
                            <tr>
                                <td>Laura Davis</td>
                                <td>Dr. Ben Carter</td>
                                <td>2025-07-22</td>
                                <td><span class="badge badge-warning">Pending</span></td>
                                <td><button class="btn btn-primary btn-sm" onclick="openModal('dispenseModal')"><i
                                            class="fas fa-eye"></i> View & Dispense</button></td>
                            </tr>
                            <tr>
                                <td>Chris Lee</td>
                                <td>Dr. Alice Williams</td>
                                <td>2025-07-22</td>
                                <td><span class="badge badge-success">Dispensed</span></td>
                                <td><button class="btn btn-primary btn-sm" disabled><i class="fas fa-check"></i>
                                        Completed</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

           <div id="discharges-panel" class="content-panel">
                <div class="panel-container">
                    <div class="panel-header">
                        <h3>Pending Discharges</h3>
                    </div>
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Room</th>
                                <th>Doctor</th>
                                <th>Discharge Initiated</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Sarah Miller</td>
                                <td>101-A</td>
                                <td>Dr. Ben Carter</td>
                                <td>2025-07-23</td>
                                <td><button class="btn btn-primary btn-sm" onclick="openModal('dischargeModal')"><i class="fas fa-tasks"></i> Process Clearance</button></td>
                            </tr>
                            <tr>
                                <td>Robert Brown</td>
                                <td>203-B</td>
                                <td>Dr. Alice Williams</td>
                                <td>2025-07-22</td>
                                <td><button class="btn btn-primary btn-sm" onclick="openModal('dischargeModal')"><i class="fas fa-tasks"></i> Process Clearance</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-panel" class="content-panel">
                <div class="panel-container">
                    <div class="tabs-container">
                        <button class="tab-button active" data-tab="bed-management">Bed Management</button>
                        <button class="tab-button" data-tab="medicine-stock">Medicine Stock</button>
                        <button class="tab-button" data-tab="blood-stock">Blood Stock</button>
                    </div>

                    <div id="bed-management-content" class="tab-content active">
                        <div class="panel-header">
                            <h3>Room & Bed Status</h3>
                        </div>
                        <div class="bed-grid">
                            <div class="bed-card occupied">
                                <i class="fas fa-bed bed-icon"></i>
                                <h4>Room 101-A</h4><span class="badge badge-danger">Occupied</span>
                            </div>
                            <div class="bed-card available">
                                <i class="fas fa-bed bed-icon"></i>
                                <h4>Room 101-B</h4><span class="badge badge-success">Available</span>
                            </div>
                            <div class="bed-card cleaning">
                                <i class="fas fa-bed bed-icon"></i>
                                <h4>Room 102-A</h4><span class="badge badge-warning">Cleaning</span>
                            </div>
                            <div class="bed-card available">
                                <i class="fas fa-bed bed-icon"></i>
                                <h4>Room 102-B</h4><span class="badge badge-success">Available</span>
                            </div>
                            <div class="bed-card occupied">
                                <i class="fas fa-bed bed-icon"></i>
                                <h4>Room 203-B</h4><span class="badge badge-danger">Occupied</span>
                            </div>
                        </div>
                    </div>

                    <div id="medicine-stock-content" class="tab-content">
                        <div class="panel-header">
                            <h3>Medicine Inventory</h3> <button class="btn btn-primary"><i class="fas fa-plus"></i>
                                Update Stock</button>
                        </div>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Current Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Medicine data will be loaded here by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div id="blood-stock-content" class="tab-content">
                        <div class="panel-header">
                            <h3>Blood Bank Inventory</h3><button class="btn btn-primary"><i class="fas fa-plus"></i>
                                Update Stock</button>
                        </div>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Blood Type</th>
                                    <th>Units Available</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Blood data will be loaded here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="lab-results-panel" class="content-panel">
                <div class="panel-container">
                    <div class="tabs-container">
                        <button class="tab-button active" data-tab="enter-results">Enter New Result</button>
                        <button class="tab-button" data-tab="view-results">View Past Results</button>
                    </div>
                    <div id="enter-results-content" class="tab-content active">
                        <div class="panel-header">
                            <h3>Enter Lab Result</h3>
                        </div>
                        <form class="form-grid">
                            <div class="form-group"><label>Search Patient</label><input type="text"
                                    placeholder="Name or ID"></div>
                            <div class="form-group"><label>Test Name</label><input type="text"
                                    placeholder="e.g., Complete Blood Count"></div>
                            <div class="form-group"><label>Test Date</label><input type="date"
                                    value="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="form-group" style="grid-column: 1 / -1;"><label>Result Details /
                                    Notes</label><textarea></textarea></div>
                            <div class="form-group"><button type="submit" class="btn btn-primary"><i
                                        class="fas fa-save"></i> Save Result</button></div>
                        </form>
                    </div>
                    <div id="view-results-content" class="tab-content">
                        <div class="panel-header">
                            <h3>View Patient Lab History</h3>
                        </div>
                        <div class="search-bar" style="margin-bottom: 1.5rem;"><input type="text"
                                placeholder="Search for a patient..."></div>
                        <p><i>Search for a patient to view their lab result history.</i></p>
                    </div>
                </div>
            </div>

            <div id="manage-users-panel" class="content-panel">
                <div class="panel-header">
                    <h3>Manage Users</h3>
                    <div class="search-bar" style="display:flex; gap: 1rem;">
                        <input type="text" id="user-search-input" placeholder="Search..." style="min-width: 300px;">
                        <button id="add-user-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New
                            User</button>
                    </div>
                </div>
                <div class="tabs-container" style="margin-top: 1.5rem;">
                    <button class="tab-button active" data-role="user">Users</button>
                    <button class="tab-button" data-role="doctor">Doctors</button>
                </div>
                <div class="table-container">
                    <table class="custom-table" id="manage-users-table">
                        <thead>
                            <tr id="users-table-header">
                            </tr>
                        </thead>
                        <tbody id="manage-users-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="messenger-panel" class="content-panel">
                <div class="panel-container">
                    <div class="panel-header">
                        <h3>Secure Messenger</h3>
                    </div>
                    <div class="messenger-layout">
                        <div class="contact-list">
                            <div class="contact-list-header">Contacts</div>
                            <div class="contacts">
                                <div class="contact active">
                                    <div class="avatar">AW</div>
                                    <div class="name">Dr. Alice Williams</div>
                                </div>
                                <div class="contact">
                                    <div class="avatar">BC</div>
                                    <div class="name">Dr. Ben Carter</div>
                                </div>
                                <div class="contact">
                                    <div class="avatar"><i class="fas fa-pills"></i></div>
                                    <div class="name">Pharmacy Desk</div>
                                </div>
                            </div>
                        </div>
                        <div class="chat-window">
                            <div class="chat-header">Dr. Alice Williams</div>
                            <div class="chat-messages">
                                <div class="message received">Good morning. Please check on the updated lab results for
                                    Sarah Miller in Room 101-A.</div>
                                <div class="message sent">Good morning, Dr. Williams. I have the results here and will
                                    upload them shortly.</div>
                                <div class="message received">Thank you.</div>
                            </div>
                            <div class="chat-input">
                                <input type="text" placeholder="Type a message...">
                                <button class="btn btn-primary">Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="activity-log-panel" class="content-panel">
                <div class="panel-container">
                    <div class="panel-header">
                        <h3>My Recent Activity</h3>
                    </div>
                    <div id="activity-timeline-container">
                        </div>
                </div>
            </div>

         <div id="activity-timeline-container">
    </div>
                </div>
            </div>

        </main>
    </div>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="modal-overlay" id="modal-overlay">
        <div class="modal-container" id="user-modal">
            <div class="modal-header">
                <h3 id="user-modal-title">Add New User</h3>
                <button class="btn-icon" onclick="closeAllModals()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="user-form" enctype="multipart/form-data" class="form-grid">
                    <input type="hidden" name="id" id="user-id">
                    <input type="hidden" name="action" id="form-action">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="profile_picture">Profile Picture</label>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                                style="flex-grow: 1;">
                            <button type="button" id="remove-pfp-btn" class="btn btn-danger"
                                style="display: none;">Remove</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" pattern="\+[0-9]{10,15}"
                            title="Format: +CountryCodeNumber" required>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="" disabled selected>Select gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" id="password-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password">
                        <small style="font-size: 0.8rem; color: var(--text-muted);">Leave blank when editing to keep the
                            current password.</small>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="user">User</option>
                            <option value="doctor">Doctor</option>
                        </select>
                    </div>

                    <div id="doctor-fields"
                        style="display: none; grid-column: 1 / -1; border-top: 1px solid var(--border-light); margin-top: 1rem; padding-top: 1.5rem;">
                        <h4 style="margin-bottom: 1rem;">Doctor Details</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="specialty">Specialty</label>
                                <input type="text" id="specialty" name="specialty">
                            </div>
                            <div class="form-group">
                                <label for="qualifications">Qualifications (e.g., MBBS, MD)</label>
                                <input type="text" id="qualifications" name="qualifications">
                            </div>
                        </div>
                    </div>

                    <div class="form-group" id="active-group" style="display: none;">
                        <label for="active">Status</label>
                        <select id="active" name="active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAllModals()">Cancel</button>
                <button class="btn btn-primary" id="save-user-btn">Save User</button>
            </div>
        </div>

        <div class="modal-container" id="user-detail-modal">
            <div class="modal-header">
                <h3>User Profile</h3>
                <button class="btn-icon" onclick="closeAllModals()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body" id="user-detail-content" style="max-height: 70vh; overflow-y: auto;">
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAllModals()">Close</button>
            </div>
        </div>

        <div class="modal-container" id="dischargeModal">
            <div class="modal-header">
                <h3>Process Discharge: Sarah Miller</h3><button class="btn-icon" onclick="closeAllModals()"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p>Complete the following clearance steps to finalize the patient discharge.</p>
                <ul class="checklist">
                    <li class="checklist-item cleared">
                        <div class="item-name"><i class="fas fa-check-circle"
                                style="color:var(--success-color); margin-right: 8px;"></i> Nursing Clearance</div>
                        <div class="status">Cleared</div>
                    </li>
                    <li class="checklist-item pending">
                        <div class="item-name"><i class="fas fa-hourglass-half"
                                style="color:var(--warning-color); margin-right: 8px;"></i> Pharmacy Clearance</div>
                        <div class="action"><button class="btn btn-primary btn-sm">Mark as Cleared</button></div>
                    </li>
                    <li class="checklist-item pending">
                        <div class="item-name"><i class="fas fa-hourglass-half"
                                style="color:var(--warning-color); margin-right: 8px;"></i> Bill Settlement</div>
                        <div class="action"><button class="btn btn-primary btn-sm" disabled>Awaiting Pharmacy</button>
                        </div>
                    </li>
                    <li class="checklist-item pending">
                        <div class="item-name"><i class="fas fa-hourglass-half"
                                style="color:var(--warning-color); margin-right: 8px;"></i> Final Discharge</div>
                        <div class="action"><button class="btn btn-primary btn-sm" disabled>Awaiting Bills</button>
                        </div>
                    </li>
                </ul>
                <p class="small-note"><i class="fas fa-info-circle"></i>Finalizing the discharge will automatically
                    update the bed status to 'Cleaning' and notify housekeeping.</p>
            </div>
            <div class="modal-footer"><button class="btn" onclick="closeAllModals()">Close</button></div>
        </div>

        <div class="modal-container" id="dispenseModal">
            <div class="modal-header">
                <h3>Prescription Details: Mark Johnson</h3><button class="btn-icon" onclick="closeAllModals()"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <p><strong>Prescribing Doctor:</strong> Dr. Alice Williams</p>
                <p><strong>Date:</strong> 2025-07-23</p>
                <hr style="margin: 1rem 0; border-color: var(--border-light);">
                <h5>Medications:</h5>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Atorvastatin 20mg</td>
                            <td>1 tablet</td>
                            <td>Once daily</td>
                        </tr>
                        <tr>
                            <td>Aspirin 81mg</td>
                            <td>1 tablet</td>
                            <td>Once daily</td>
                        </tr>
                    </tbody>
                </table>
                <p class="small-note"><i class="fas fa-info-circle"></i>Marking as 'Dispensed' will automatically update
                    medicine inventory levels.</p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAllModals()">Cancel</button>
                <button class="btn btn-warning">Mark Not Available</button>
                <button class="btn btn-success">Mark as Dispensed</button>
            </div>
        </div>

        <div class="modal-container" id="settingsModal">
            <div class="modal-header">
                <h3>Dashboard Settings</h3><button class="btn-icon" onclick="closeAllModals()"><i
                        class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form>
                    <div class="form-group">
                        <label>Customize Dashboard Widgets</label>
                        <div style="padding: 0.5rem; border: 1px solid var(--border-light); border-radius: 8px;">
                            <div class="form-group"
                                style="flex-direction: row; align-items: center; margin-bottom: 0.5rem;"><input
                                    type="checkbox" id="widget1" checked style="margin-right: 10px;"><label
                                    for="widget1" style="margin-bottom:0;">Show Quick Actions</label></div>
                            <div class="form-group"
                                style="flex-direction: row; align-items: center; margin-bottom: 0.5rem;"><input
                                    type="checkbox" id="widget2" checked style="margin-right: 10px;"><label
                                    for="widget2" style="margin-bottom:0;">Show Visual Analytics</label></div>
                            <div class="form-group" style="flex-direction: row; align-items: center; margin-bottom: 0;">
                                <input type="checkbox" id="widget3" style="margin-right: 10px;"><label for="widget3"
                                    style="margin-bottom:0;">Show Pending Admissions List</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAllModals()">Cancel</button>
                <button class="btn btn-primary">Save Preferences</button>
            </div>
        </div>

        <div class="modal-container" id="medicine-modal">
            <div class="modal-header">
                <h3 id="medicine-modal-title">Add New Medicine</h3>
                <button class="btn-icon" onclick="closeAllModals()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="medicine-form" class="form-grid">
                    <input type="hidden" name="id" id="medicine-id">
                    <input type="hidden" name="action" id="medicine-form-action">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label for="medicine-name">Medicine Name</label>
                        <input type="text" id="medicine-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="medicine-quantity">Quantity</label>
                        <input type="number" id="medicine-quantity" name="quantity" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="medicine-unit-price">Unit Price (₹)</label>
                        <input type="number" id="medicine-unit-price" name="unit_price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="medicine-low-stock-threshold">Low Stock Threshold</label>
                        <input type="number" id="medicine-low-stock-threshold" name="low_stock_threshold" min="0"
                            required>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label for="medicine-description">Description</label>
                        <textarea id="medicine-description" name="description" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAllModals()">Cancel</button>
                <button class="btn btn-primary" form="medicine-form" type="submit">Save Medicine</button>
            </div>
        </div>

        <div class="modal-container" id="blood-modal">
            <div class="modal-header">
                <h3 id="blood-modal-title">Update Blood Inventory</h3>
                <button class="btn-icon" onclick="closeAllModals()"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="blood-form" class="form-grid">
                    <input type="hidden" name="action" value="updateBlood">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-group">
                        <label for="blood-group">Blood Group</label>
                        <select id="blood-group" name="blood_group" required>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="blood-quantity-ml">Quantity (ml)</label>
                        <input type="number" id="blood-quantity-ml" name="quantity_ml" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="blood-low-stock-threshold-ml">Low Stock Threshold (ml)</label>
                        <input type="number" id="blood-low-stock-threshold-ml" name="low_stock_threshold_ml" min="0"
                            required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeAllModals()">Cancel</button>
                <button class="btn btn-primary" form="blood-form" type="submit">Update Blood Unit</button>
            </div>
        </div>

    </div>

    <script>
        /**
 * A generic handler for submitting forms via AJAX.
 * It automatically includes the CSRF token and provides notifications.
 * @param {FormData} formData - The data from the form to be submitted.
 * @param {Function} [callbackOnSuccess] - A function to run after a successful submission (e.g., to refresh a table).
 * @param {string} [modalIdToClose] - The ID of a modal to close on success.
 */
        const handleFormSubmit = async (formData, callbackOnSuccess, modalIdToClose) => {
            // CSRF token is now added directly in the form HTML for user-related forms
            // and explicitly added for other forms where needed.
            try {
                const response = await fetch('staff_dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    alert('Success: ' + result.message); // You can replace this with a prettier notification
                    if (modalIdToClose) closeAllModals();
                    if (callbackOnSuccess) callbackOnSuccess();
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                alert('Error: ' + error.message); // Or a prettier notification
            }
        };

        // --- MANAGE USERS PANEL ---
        // Moved these functions to global scope or passed them correctly
        let currentRole = 'user'; // Global variable for current active role in manage users panel

        const renderTableHeaders = (role) => {
            const headerRow = document.getElementById('users-table-header');
            let headers = `<th>Name</th><th>User ID</th><th>Username</th><th>Email</th><th>Phone</th>`;
            if (role === 'doctor') {
                headers += `<th>Specialty</th>`;
            }
            headers += `<th>Status</th><th>Joined</th><th>Actions</th>`;
            headerRow.innerHTML = headers;
        };

        const fetchAndRenderUsers = async (role, searchTerm = '') => {
            currentRole = role;
            renderTableHeaders(role);
            const tableBody = document.getElementById('manage-users-table-body');
            tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center;">Loading...</td></tr>`;

            try {
                const response = await fetch(`staff_dashboard.php?fetch=users&role=${role}&search=${encodeURIComponent(searchTerm)}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                if (result.data.length > 0) {
                    tableBody.innerHTML = result.data.map(user => {
                        const statusClass = user.active == 1 ? 'badge-success' : 'badge-danger';
                        const statusText = user.active == 1 ? 'Active' : 'Inactive';
                        const pfpPath = `uploads/profile_pictures/${user.profile_picture || 'default.png'}`;
                        const specialtyCell = role === 'doctor' ? `<td>${user.specialty || 'N/A'}</td>` : '';

                        return `
                        <tr class="clickable-row" data-user-id="${user.id}">
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="${pfpPath}" alt="pfp" style="width:40px; height:40px; border-radius:50%; object-fit:cover;" onerror="this.src='uploads/profile_pictures/default.png'">
                                    ${user.name}
                                </div>
                            </td>
                            <td>${user.display_user_id}</td>
                            <td>${user.username}</td>
                            <td>${user.email}</td>
                            <td>${user.phone}</td>
                            ${specialtyCell}
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td>${new Date(user.created_at).toLocaleDateString()}</td>
                            <td>
                                <button class="btn-icon btn-edit-user" title="Edit" data-user='${JSON.stringify(user)}'><i class="fas fa-pencil-alt"></i></button>
                                ${user.active == 1 ? `<button class="btn-icon btn-delete-user" title="Deactivate" data-user-id="${user.id}" data-user-name="${user.name}"><i class="fas fa-trash-alt" style="color:var(--danger-color)"></i></button>` : ''}
                            </td>
                        </tr>
                    `;
                    }).join('');
                } else {
                    tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center;">No ${role}s found.</td></tr>`;
                }
            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="9" style="text-align:center; color: var(--danger-color);">Error loading users.</td></tr>`;
            }
        };

        const openUserModal = (mode, user = {}) => {
            const form = document.getElementById('user-form');
            form.reset();
            document.getElementById('user-modal-title').textContent = mode === 'add' ? `Add New ${user.role.charAt(0).toUpperCase() + user.role.slice(1)}` : `Edit ${user.name}`;
            document.getElementById('form-action').value = mode === 'add' ? 'addUser' : 'updateUser';

            const roleSelect = document.getElementById('role');
            roleSelect.value = user.role || 'user';
            roleSelect.disabled = (mode === 'edit');

            document.getElementById('active-group').style.display = (mode === 'edit') ? 'block' : 'none';

            const removePfpBtn = document.getElementById('remove-pfp-btn');
            removePfpBtn.style.display = 'none';

            if (mode === 'edit') {
                document.getElementById('user-id').value = user.id;
                document.getElementById('name').value = user.name;
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('phone').value = user.phone;
                document.getElementById('date_of_birth').value = user.date_of_birth || '';
                document.getElementById('gender').value = user.gender || '';
                document.getElementById('active').value = user.active;
                document.getElementById('specialty').value = user.specialty || '';
                document.getElementById('qualifications').value = user.qualifications || '';
                document.getElementById('password').required = false;

                if (user.profile_picture && user.profile_picture !== 'default.png') {
                    removePfpBtn.style.display = 'block';
                    removePfpBtn.onclick = async () => {
                        if (confirm(`Remove profile picture for ${user.name}?`)) {
                            const formData = new FormData();
                            formData.append('action', 'removeProfilePicture');
                            formData.append('id', user.id);
                            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>'); // Ensure CSRF for this action
                            await handleFormSubmit(formData, () => {
                                // Re-fetch users for the current role after successful removal
                                fetchAndRenderUsers(currentRole);
                                closeAllModals();
                            });
                        }
                    };
                }
            } else {
                document.getElementById('password').required = true;
            }

            toggleDoctorFields();
            openModal('user-modal');
        };

        const toggleDoctorFields = () => {
            const role = document.getElementById('role').value;
            const doctorFields = document.getElementById('doctor-fields');
            doctorFields.style.display = (role === 'doctor') ? 'block' : 'none';
            document.getElementById('specialty').required = (role === 'doctor');
            document.getElementById('qualifications').required = (role === 'doctor');
        };

        const openDetailedProfileModal = async (userId) => {
            const contentDiv = document.getElementById('user-detail-content');
            contentDiv.innerHTML = '<p>Loading profile...</p>';
            openModal('user-detail-modal');
            try {
                const response = await fetch(`staff_dashboard.php?fetch=user_details&id=${userId}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                const { user, assigned_patients } = result.data;
                const pfpPath = `uploads/profile_pictures/${user.profile_picture || 'default.png'}`;

                let assignedPatientsHtml = '';
                if (user.role === 'doctor') {
                    assignedPatientsHtml = `<h4>Assigned Patients (Recent 10)</h4>`;
                    if (assigned_patients && assigned_patients.length > 0) {
                        assignedPatientsHtml += `<ul style="list-style:none; padding:0;">${assigned_patients.map(p => `<li style="padding: 5px 0;">${p.name} (${p.display_user_id}) - ${p.status}</li>`).join('')}</ul>`;
                    } else {
                        assignedPatientsHtml += '<p>No patients assigned recently.</p>';
                    }
                }

                contentDiv.innerHTML = `
            <div style="display: flex; align-items: center; gap: 1.5rem; margin-bottom: 1.5rem;">
                <img src="${pfpPath}" alt="Profile" style="width:100px; height:100px; border-radius:50%; object-fit:cover;">
                <div>
                    <h3 style="margin:0;">${user.name} <span class="badge ${user.active == 1 ? 'badge-success' : 'badge-danger'}">${user.active == 1 ? 'Active' : 'Inactive'}</span></h3>
                    <p style="color:var(--text-muted); margin:0;">${user.username} (${user.display_user_id})</p>
                    <p style="color:var(--text-muted); margin:0;">${user.email} | ${user.phone}</p>
                    <p style="color:var(--text-muted); margin:0;">DOB: ${user.date_of_birth || 'N/A'} | Gender: ${user.gender || 'N/A'}</p>
                    ${user.role === 'doctor' ? `<p style="color:var(--text-muted); margin:0;">Specialty: ${user.specialty || 'N/A'} | Qualifications: ${user.qualifications || 'N/A'}</p>` : ''}
                </div>
            </div>
            ${assignedPatientsHtml}
        `;
            } catch (error) {
                contentDiv.innerHTML = `<p style="color:var(--danger-color);">Failed to load profile: ${error.message}</p>`;
            }
        };


        document.addEventListener("DOMContentLoaded", function () {

             const fetchMyActivityLogs = async () => {
        const container = document.getElementById('activity-timeline-container');
        container.innerHTML = '<p style="text-align:center;">Loading your activity...</p>';
        try {
            const response = await fetch('staff_dashboard.php?fetch=my_activity');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                const timelineHTML = result.data.map(log => {
                    const time = new Date(log.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
                    return `
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="description">${log.details}</div>
                                <div class="time"><i class="far fa-clock"></i> ${time}</div>
                            </div>
                        </li>`;
                }).join('');
                container.innerHTML = `<ul class="activity-timeline">${timelineHTML}</ul>`;
            } else {
                container.innerHTML = '<p style="text-align:center;">No recent activity found for your account.</p>';
            }
        } catch (error) {
            container.innerHTML = `<p style="text-align:center; color:var(--danger-color)">Error loading activity: ${error.message}</p>`;
        }
    };
            // --- CORE UI ELEMENTS & STATE ---
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            const navLinks = document.querySelectorAll('.nav-link');
            const panelTitle = document.getElementById('panel-title');
            const welcomeMessage = document.getElementById('welcome-message');
            const themeToggle = document.getElementById('theme-toggle');
            const modalOverlay = document.getElementById('modal-overlay');
            const settingsBtn = document.getElementById('settings-btn');

            // --- THEME TOGGLE ---
            const applyTheme = (theme) => {
                document.body.className = theme;
                themeToggle.checked = theme === 'dark-mode';
            };
            themeToggle.addEventListener('change', () => {
                const newTheme = themeToggle.checked ? 'dark-mode' : 'light-mode';
                localStorage.setItem('theme', newTheme);
                applyTheme(newTheme);
            });
            applyTheme(localStorage.getItem('theme') || 'light-mode');

            // --- REAL-TIME POLLING FOR INVENTORY ---
            const updateResourceStatusUI = (resources) => {
                resources.forEach(resource => {
                    const card = document.querySelector(`.bed-card[data-resource-id="${resource.type}-${resource.id}"]`);
                    if (card) {
                        card.classList.remove('available', 'occupied', 'cleaning', 'reserved');
                        card.classList.add(resource.status);
                        const statusEl = card.querySelector('.bed-status');
                        if (statusEl) {
                            statusEl.textContent = resource.status.charAt(0).toUpperCase() + resource.status.slice(1);
                        }
                    }
                });
            };

            const pollResourceStatus = async () => {
                const inventoryPanel = document.getElementById('inventory-panel');
                if (!inventoryPanel || !inventoryPanel.classList.contains('active')) {
                    return;
                }
                try {
                    const response = await fetch('staff_dashboard.php?fetch=bed_room_status');
                    const result = await response.json();
                    if (result.success) {
                        updateResourceStatusUI(result.data);
                    }
                } catch (error) {
                    console.error("Polling error:", error);
                }
            };

            setInterval(pollResourceStatus, 5000); // Poll every 5 seconds

            // --- SIDEBAR & NAVIGATION ---
            function closeMenu() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
            hamburgerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.add('active');
                sidebarOverlay.classList.add('active');
            });
            sidebarOverlay.addEventListener('click', closeMenu);

            navLinks.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetId = link.dataset.target;

                    if (targetId === 'admissions') {
                        populateAdmissionDropdowns();
                    }

                          if (targetId === 'activity-log') {
                        fetchMyActivityLogs();
                    }

                    // NEW: Trigger inventory data load when switching to inventory panel
                    if (targetId === 'inventory') {
                        setupInventoryPanel();
                    }

                    if (targetId === 'manage-users') {
                        // Call setupManageUsersPanel only if it hasn't been set up or needs re-initialization
                        // This prevents re-attaching event listeners multiple times
                        if (!document.getElementById('manage-users-panel').dataset.initialized) {
                            setupManageUsersPanel();
                            document.getElementById('manage-users-panel').dataset.initialized = 'true';
                        }
                    }

                    document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
                    document.getElementById(targetId + '-panel').classList.add('active');
                    navLinks.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                    panelTitle.textContent = link.innerText.trim();
                    welcomeMessage.style.display = (targetId === 'dashboard') ? 'block' : 'none';

                    if (window.innerWidth <= 992) closeMenu();
                });
            });

            // --- ADMISSIONS DATA FETCHING & FORM LOGIC ---
            const populateAdmissionDropdowns = async () => {
                const doctorSelect = document.getElementById('admit-doctor');
                const resourceSelect = document.getElementById('admit-resource');

                doctorSelect.innerHTML = '<option value="">Loading doctors...</option>';
                resourceSelect.innerHTML = '<option value="">Loading resources...</option>';

                try {
                    const [docRes, bedRes, roomRes] = await Promise.all([
                        fetch('staff_dashboard.php?fetch=active_doctors'),
                        fetch('staff_dashboard.php?fetch=available_beds'),
                        fetch('staff_dashboard.php?fetch=available_rooms')
                    ]);
                    const doctors = await docRes.json();
                    const beds = await bedRes.json();
                    const rooms = await roomRes.json();

                    doctorSelect.innerHTML = '<option value="">Select a doctor...</option>';
                    if (doctors.success) {
                        doctors.data.forEach(doc => {
                            doctorSelect.innerHTML += `<option value="${doc.id}">Dr. ${doc.name} (${doc.specialty})</option>`;
                        });
                    }

                    resourceSelect.innerHTML = '<option value="">Select a bed or room...</option>';
                    if (beds.success && beds.data.length > 0) {
                        const bedGroup = document.createElement('optgroup');
                        bedGroup.label = 'Available Beds';
                        beds.data.forEach(bed => {
                            bedGroup.innerHTML += `<option value="bed-${bed.id}">${bed.ward_name} - Bed ${bed.bed_number}</option>`;
                        });
                        resourceSelect.appendChild(bedGroup);
                    }
                    if (rooms.success && rooms.data.length > 0) {
                        const roomGroup = document.createElement('optgroup');
                        roomGroup.label = 'Available Rooms';
                        rooms.data.forEach(room => {
                            roomGroup.innerHTML += `<option value="room-${room.id}">Room ${room.room_number}</option>`;
                        });
                        resourceSelect.appendChild(roomGroup);
                    }
                } catch (error) {
                    console.error("Failed to populate admission forms:", error);
                    doctorSelect.innerHTML = resourceSelect.innerHTML = '<option value="">Error loading data</option>';
                }
            };

            const admissionForm = document.getElementById('admission-form');
            const patientSearchInput = document.getElementById('admit-patient-search');
            const patientSearchIdInput = document.getElementById('admit-patient-id');
            const patientSearchResults = document.getElementById('patient-search-results');
            let searchTimeout;

            patientSearchInput.addEventListener('keyup', () => {
                clearTimeout(searchTimeout);
                const searchTerm = patientSearchInput.value.trim();
                patientSearchIdInput.value = '';

                if (searchTerm.length < 2) {
                    patientSearchResults.style.display = 'none';
                    return;
                }

                searchTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch(`staff_dashboard.php?fetch=search_unassigned_users&term=${encodeURIComponent(searchTerm)}`);
                        const result = await response.json();
                        if (result.success) {
                            if (result.data.length > 0) {
                                patientSearchResults.innerHTML = result.data.map(p =>
                                    `<div class="search-result-item" data-id="${p.id}" data-name="${p.name} (${p.display_user_id})">
                                    ${p.name} (${p.display_user_id})
                                </div>`
                                ).join('');
                            } else {
                                patientSearchResults.innerHTML = '<div class="search-result-item no-results">No unassigned users found.</div>';
                            }
                            patientSearchResults.style.display = 'block';
                        }
                    } catch (error) {
                        console.error('Patient search failed:', error);
                    }
                }, 300);
            });

            patientSearchResults.addEventListener('click', (e) => {
                const item = e.target.closest('.search-result-item');
                if (item && item.dataset.id) {
                    patientSearchIdInput.value = item.dataset.id;
                    patientSearchInput.value = item.dataset.name;
                    patientSearchResults.style.display = 'none';
                }
            });

            document.addEventListener('click', (e) => {
                if (!patientSearchInput.contains(e.target)) {
                    patientSearchResults.style.display = 'none';
                }
            });

            if (admissionForm) {
                admissionForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    const formData = new FormData(admissionForm);
                    formData.append('action', 'admitPatient');
                    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

                    try {
                        const response = await fetch('staff_dashboard.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        if (response.ok && result.success) {
                            alert('Success: ' + result.message);
                            admissionForm.reset();
                            populateAdmissionDropdowns();
                        } else {
                            throw new Error(result.message || 'An unknown error occurred.');
                        }
                    } catch (error) {
                        alert('Error: ' + error.message);
                    }
                });
            }
            // --- MANAGE USERS PANEL ---

            const setupManageUsersPanel = () => {
                const searchInput = document.getElementById('user-search-input');
                const addUserBtn = document.getElementById('add-user-btn');
                const userTableBody = document.getElementById('manage-users-table-body');
                const userForm = document.getElementById('user-form');
                const saveUserBtn = document.getElementById('save-user-btn');
                const tabs = document.querySelectorAll('#manage-users-panel .tab-button');

                tabs.forEach(tab => {
                    tab.addEventListener('click', () => {
                        tabs.forEach(t => t.classList.remove('active'));
                        tab.classList.add('active');
                        const role = tab.dataset.role;
                        searchInput.value = '';
                        searchInput.placeholder = `Search ${role}s by name, ID...`;
                        fetchAndRenderUsers(role);
                    });
                });

                let searchTimeout;
                searchInput.addEventListener('keyup', () => {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        fetchAndRenderUsers(currentRole, searchInput.value);
                    }, 300);
                });

                addUserBtn.addEventListener('click', () => openUserModal('add', { role: currentRole }));

                saveUserBtn.addEventListener('click', () => userForm.requestSubmit());
                userForm.addEventListener('submit', async (e) => { e.preventDefault(); await handleFormSubmit(new FormData(userForm), () => fetchAndRenderUsers(currentRole), 'user-modal'); });

                userTableBody.addEventListener('click', async (e) => {
                    const row = e.target.closest('tr');
                    if (!row) return;

                    const editBtn = e.target.closest('.btn-edit-user');
                    const deleteBtn = e.target.closest('.btn-delete-user');

                    if (editBtn) {
                        e.stopPropagation();
                        const user = JSON.parse(editBtn.dataset.user);
                        openUserModal('edit', user);
                        return;
                    }

                    if (deleteBtn) {
                        e.stopPropagation();
                        const userId = deleteBtn.dataset.userId;
                        const userName = deleteBtn.dataset.userName;
                        if (confirm(`Are you sure you want to deactivate ${userName}?`)) {
                            const formData = new FormData();
                            formData.append('action', 'deleteUser');
                            formData.append('id', userId);
                            formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                            await handleFormSubmit(formData, () => fetchAndRenderUsers(currentRole));
                        }
                        return;
                    }

                    if (row.classList.contains('clickable-row')) {
                        openDetailedProfileModal(row.dataset.userId);
                    }
                });

                // Initial load for the manage users panel when it's first accessed
                fetchAndRenderUsers(currentRole);
            };


            document.getElementById('role').addEventListener('change', toggleDoctorFields);


            // --- MODAL CONTROLS ---
            window.openModal = (modalId) => {
                // First, hide any other modals to prevent them from stacking.
                document.querySelectorAll('.modal-container').forEach(m => {
                    m.style.display = 'none';
                });

                const modal = document.getElementById(modalId);
                if (modal) {
                    modalOverlay.classList.add('active');
                    // Now, display only the target modal.
                    modal.style.display = 'flex';
                    modal.style.opacity = 1;
                    modal.style.visibility = 'visible';
                    modal.style.transform = 'translate(-50%, -50%) scale(1)';
                }
            };


            window.closeAllModals = () => {
                modalOverlay.classList.remove('active');
                document.querySelectorAll('.modal-container').forEach(modal => {
                    // Hiding the modal-container itself
                    modal.style.opacity = 0;
                    modal.style.visibility = 'hidden';
                    modal.style.transform = 'translate(-50%, -50%) scale(0.95)';
                    modal.style.display = 'none';
                });
            };

            if (settingsBtn) {
                settingsBtn.addEventListener('click', () => openModal('settingsModal'));
            }
            modalOverlay.addEventListener('click', (e) => { if (e.target === e.currentTarget) closeAllModals(); });
            document.addEventListener('keydown', (e) => { if (e.key === "Escape") closeAllModals(); });

            // --- INVENTORY MANAGEMENT (NEW CODE) ---
            const setupInventoryPanel = async () => {
                fetchAndRenderMedicines();
                fetchAndRenderBlood();
                fetchAndRenderBedsAndRooms();
            };

            // --- Medicine Logic ---
            const medicineModal = document.getElementById('medicine-modal');
            const medicineForm = document.getElementById('medicine-form');

            const openMedicineModal = (mode, medicine = {}) => {
                medicineForm.reset();
                document.getElementById('medicine-modal-title').textContent = mode === 'add' ? 'Add New Medicine' : `Edit ${medicine.name}`;
                document.getElementById('medicine-form-action').value = mode === 'add' ? 'addMedicine' : 'updateMedicine';
                if (mode === 'edit') {
                    document.getElementById('medicine-id').value = medicine.id;
                    document.getElementById('medicine-name').value = medicine.name;
                    document.getElementById('medicine-quantity').value = medicine.quantity;
                    document.getElementById('medicine-unit-price').value = medicine.unit_price;
                    document.getElementById('medicine-low-stock-threshold').value = medicine.low_stock_threshold;
                    document.getElementById('medicine-description').value = medicine.description || '';
                }
                openModal('medicine-modal');
            };

            const fetchAndRenderMedicines = async () => {
                const tableBody = document.querySelector('#medicine-stock-content tbody');
                tableBody.innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;
                try {
                    const response = await fetch('staff_dashboard.php?fetch=medicines');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);
                    if (result.data.length === 0) {
                        tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center;">No medicines found.</td></tr>`;
                        return;
                    }
                    tableBody.innerHTML = result.data.map(med => {
                        const isLow = parseInt(med.quantity) <= parseInt(med.low_stock_threshold);
                        const statusClass = isLow ? 'badge-warning' : 'badge-success';
                        const statusText = isLow ? 'Low Stock' : 'In Stock';
                        return `
                        <tr data-medicine='${JSON.stringify(med)}'>
                            <td>${med.name}</td>
                            <td>${med.quantity} units</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                            <td>
                                <button class="btn-icon btn-edit-medicine" title="Edit"><i class="fas fa-pencil-alt"></i></button>
                                <button class="btn-icon btn-delete-medicine" title="Delete"><i class="fas fa-trash-alt" style="color:var(--danger-color)"></i></button>
                            </td>
                        </tr>
                    `;
                    }).join('');
                } catch (error) {
                    tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color:var(--danger-color)">Error loading medicines.</td></tr>`;
                }
            };

            document.querySelector('#medicine-stock-content').addEventListener('click', async (e) => {
                const editBtn = e.target.closest('.btn-edit-medicine');
                const deleteBtn = e.target.closest('.btn-delete-medicine');
                if (editBtn) {
                    const medicineData = JSON.parse(editBtn.closest('tr').dataset.medicine);
                    openMedicineModal('edit', medicineData);
                }
                if (deleteBtn) {
                    const medicineData = JSON.parse(deleteBtn.closest('tr').dataset.medicine);
                    if (confirm(`Are you sure you want to delete ${medicineData.name}?`)) {
                        const formData = new FormData();
                        formData.append('action', 'deleteMedicine');
                        formData.append('id', medicineData.id);
                        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
                        await handleFormSubmit(formData, fetchAndRenderMedicines);
                    }
                }
            });

            document.querySelector('#medicine-stock-content .btn-primary').addEventListener('click', () => openMedicineModal('add'));
            medicineForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(medicineForm);
                // CSRF token is already in the form HTML for medicine-form
                await handleFormSubmit(formData, fetchAndRenderMedicines, 'medicine-modal');
            });

            // --- Blood Logic ---
            const bloodModal = document.getElementById('blood-modal');
            const bloodForm = document.getElementById('blood-form');

            const openBloodModal = (blood = {}) => {
                bloodForm.reset();
                document.getElementById('blood-group').value = blood.blood_group || 'A+';
                document.getElementById('blood-group').disabled = !!blood.blood_group;
                document.getElementById('blood-quantity-ml').value = blood.quantity_ml || '';
                document.getElementById('blood-low-stock-threshold-ml').value = blood.low_stock_threshold_ml || 5000;
                openModal('blood-modal');
            };

            const fetchAndRenderBlood = async () => {
                const tableBody = document.querySelector('#blood-stock-content tbody');
                tableBody.innerHTML = `<tr><td colspan="3">Loading...</td></tr>`;
                try {
                    const response = await fetch('staff_dashboard.php?fetch=blood_inventory');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);
                    if (result.data.length === 0) {
                        tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center;">No blood stock data. Update to add.</td></tr>`;
                        return;
                    }
                    tableBody.innerHTML = result.data.map(blood => {
                        const isLow = parseInt(blood.quantity_ml) < parseInt(blood.low_stock_threshold_ml);
                        const statusClass = isLow ? 'badge-danger' : 'badge-success';
                        const statusText = isLow ? 'Critical Low' : 'Available';
                        return `
                        <tr data-blood='${JSON.stringify(blood)}'>
                            <td>${blood.blood_group}</td>
                            <td>${blood.quantity_ml} ml</td>
                            <td><span class="badge ${statusClass}">${statusText}</span></td>
                        </tr>
                     `;
                    }).join('');
                } catch (error) {
                    tableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; color:var(--danger-color)">Error loading blood stock.</td></tr>`;
                }
            };

            document.querySelector('#blood-stock-content .btn-primary').addEventListener('click', () => openBloodModal());
            bloodForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(bloodForm);
                // CSRF token is already in the form HTML for blood-form
                if (document.getElementById('blood-group').disabled) {
                    formData.set('blood_group', document.getElementById('blood-group').value);
                }
                await handleFormSubmit(formData, fetchAndRenderBlood, 'blood-modal');
            });


            // --- Beds and Rooms Rendering ---
            const fetchAndRenderBedsAndRooms = async () => {
                const bedGrid = document.querySelector('#bed-management-content .bed-grid');
                bedGrid.innerHTML = `<p>Loading statuses...</p>`;
                try {
                    const [bedsRes, roomsRes] = await Promise.all([
                        fetch('staff_dashboard.php?fetch=beds'),
                        fetch('staff_dashboard.php?fetch=rooms')
                    ]);
                    const beds = await bedsRes.json();
                    const rooms = await roomsRes.json();
                    let content = '';
                    if (beds.success) {
                        content += beds.data.map(bed => {
                            const statusClass = bed.status === 'available' ? 'badge-success' : 'badge-danger';
                            const cardClass = bed.status;
                            return `
                            <div class="bed-card ${cardClass}">
                                <i class="fas fa-bed bed-icon"></i><h4>${bed.ward_name} - ${bed.bed_number}</h4>
                                <span class="badge ${statusClass}">${bed.status}</span>
                                ${bed.patient_name ? `<p style="font-size:0.8rem; margin-top:5px;">${bed.patient_name}</p>` : ''}
                            </div>
                         `;
                        }).join('');
                    }
                    if (rooms.success) {
                        content += rooms.data.map(room => {
                            const statusClass = room.status === 'available' ? 'badge-success' : 'badge-danger';
                            const cardClass = room.status;
                            return `
                            <div class="bed-card ${cardClass}">
                                <i class="fas fa-door-closed bed-icon"></i><h4>Room ${room.room_number}</h4>
                                <span class="badge ${statusClass}">${room.status}</span>
                                ${room.patient_name ? `<p style="font-size:0.8rem; margin-top:5px;">${room.patient_name}</p>` : ''}
                            </div>
                         `;
                        }).join('');
                    }
                    bedGrid.innerHTML = content || '<p>No beds or rooms found.</p>';
                } catch (error) {
                    bedGrid.innerHTML = `<p style="color:var(--danger-color)">Error loading statuses.</p>`
                }
            }

            // --- Navigation and Tab Logic ---
            const inventoryNavLink = document.querySelector('.nav-link[data-target="inventory"]');
            if (inventoryNavLink) {
                inventoryNavLink.addEventListener('click', setupInventoryPanel);
            }

            document.querySelectorAll('.tabs-container .tab-button').forEach(button => {
                button.addEventListener('click', function () {
                    const targetTab = this.dataset.tab;
                    document.querySelectorAll('.tabs-container .tab-button').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                    document.getElementById(targetTab + '-content').classList.add('active');
                });
            });
        });
    </script>
</body>
</html>
