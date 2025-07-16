<?php
// --- CONFIG & SESSION START ---
require_once 'config.php'; 

// --- SESSION SECURITY & ROLE CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    session_destroy();
    header("Location: login.php?error=unauthorized");
    exit();
}

// --- SESSION TIMEOUT ---
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_destroy();
    header("Location: login.php?session_expired=true");
    exit();
}
$_SESSION['loggedin_time'] = time();

/**
 * Generates a unique, sequential display ID for a new user based on their role.
 * Uses a dedicated counter table with row locking to prevent race conditions.
 * e.g., A0001, D0001, S0001, U0001
 *
 * @param string $role The role of the user ('admin', 'doctor', 'staff', 'user').
 * @param mysqli $conn The database connection object.
 * @return string The formatted display ID.
 * @throws Exception If the role is invalid or a database error occurs.
 */
function generateDisplayId($role, $conn) {
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


// ===================================================================================
// --- API ENDPOINT LOGIC (Handles all AJAX requests) ---
// ===================================================================================
if (isset($_GET['fetch']) || (isset($_POST['action']) && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    set_error_handler(function($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) { return; }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        $conn = getDbConnection(); 

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
                throw new Exception('Invalid CSRF token. Please refresh and try again.');
            }
        }

        if (isset($_POST['action'])) {
            $action = $_POST['action'];
            switch ($action) {
                case 'addUser':
                    // --- Start Transaction ---
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email']) || empty($_POST['role']) || empty($_POST['password']) || empty($_POST['phone'])) {
                            throw new Exception('Please fill all required fields.');
                        }
                        $name = $_POST['name'];
                        $username = $_POST['username'];
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                        if (!$email) throw new Exception('Invalid email format.');
                        
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $role = $_POST['role'];
                        $phone = $_POST['phone'];
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;

                        $display_user_id = generateDisplayId($role, $conn);

                        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->bind_param("ss", $username, $email);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            throw new Exception('Username or email already exists.');
                        }
                        
                        $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role, gender, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("ssssssss", $display_user_id, $name, $username, $email, $password, $role, $gender, $phone);
                        $stmt->execute();
                        $user_id = $conn->insert_id;

                        if ($role === 'doctor') {
                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty, qualifications, department_id, availability) VALUES (?, ?, ?, ?, ?)");
                            $stmt_doctor->bind_param("issii", $user_id, $_POST['specialty'], $_POST['qualifications'], $_POST['department_id'], $_POST['availability']);
                            $stmt_doctor->execute();
                        } elseif ($role === 'staff') {
                            $stmt_staff = $conn->prepare("INSERT INTO staff (user_id, shift, assigned_department) VALUES (?, ?, ?)");
                            $stmt_staff->bind_param("iss", $user_id, $_POST['shift'], $_POST['assigned_department']);
                            $stmt_staff->execute();
                        } elseif ($role === 'admin') {
                            $stmt_admin = $conn->prepare("INSERT INTO admins (user_id) VALUES (?)");
                            $stmt_admin->bind_param("i", $user_id);
                            $stmt_admin->execute();
                        }

                        $conn->commit();
                        $response = ['success' => true, 'message' => ucfirst($role) . ' added successfully.'];

                    } catch (Exception $e) {
                        $conn->rollback();
                        throw new Exception('Database error on user creation: ' . $e->getMessage());
                    }
                    break;

                case 'updateUser':
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email'])) {
                            throw new Exception('Invalid data provided.');
                        }
                        $id = (int)$_POST['id'];
                        $name = $_POST['name'];
                        $username = $_POST['username'];
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                        if (!$email) throw new Exception('Invalid email format.');
                        $phone = $_POST['phone'];
                        $active = isset($_POST['active']) ? (int)$_POST['active'] : 1;
                        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;

                        $sql_parts = ["name = ?", "username = ?", "email = ?", "phone = ?", "active = ?", "date_of_birth = ?", "gender = ?"];
                        $params = [$name, $username, $email, $phone, $active, $date_of_birth, $gender];
                        $types = "ssssiss";

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

                        // Fetch user's role to update role-specific tables
                        $role_stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
                        $role_stmt->bind_param("i", $id);
                        $role_stmt->execute();
                        $user_role = $role_stmt->get_result()->fetch_assoc()['role'];

                        if ($user_role === 'doctor') {
                             $stmt_doctor = $conn->prepare("
                                INSERT INTO doctors (user_id, specialty, qualifications, department_id, availability) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                specialty = VALUES(specialty), 
                                qualifications = VALUES(qualifications), 
                                department_id = VALUES(department_id), 
                                availability = VALUES(availability)
                            ");
                            $stmt_doctor->bind_param("issii", $id, $_POST['specialty'], $_POST['qualifications'], $_POST['department_id'], $_POST['availability']);
                            $stmt_doctor->execute();
                        } elseif ($user_role === 'staff') {
                            $stmt_staff = $conn->prepare("
                                INSERT INTO staff (user_id, shift, assigned_department) 
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                shift = VALUES(shift), 
                                assigned_department = VALUES(assigned_department)
                            ");
                            $stmt_staff->bind_param("iss", $id, $_POST['shift'], $_POST['assigned_department']);
                            $stmt_staff->execute();
                        }

                        $conn->commit();
                        $response = ['success' => true, 'message' => 'User updated successfully.'];

                    } catch (Exception $e) {
                         $conn->rollback();
                        throw new Exception('Failed to update user: ' . $e->getMessage());
                    }
                    break;
                
                case 'updateProfile': 
                    if (empty($_POST['name']) || empty($_POST['email'])) {
                        throw new Exception('Name and Email are required.');
                    }
                    $id = $_SESSION['user_id'];
                    $name = $_POST['name'];
                    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                    if (!$email) throw new Exception('Invalid email format.');
                    $phone = $_POST['phone'];

                    $sql_parts = ["name = ?", "email = ?", "phone = ?"];
                    $params = [$name, $email, $phone];
                    $types = "sss";

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

                    if ($stmt->execute()) {
                        $_SESSION['username'] = $name; 
                        $response = ['success' => true, 'message' => 'Your profile has been updated successfully.'];
                    } else {
                        throw new Exception('Failed to update your profile.');
                    }
                    break;

                case 'deleteUser':
                    if (empty($_POST['id'])) {
                        throw new Exception('Invalid user ID.');
                    }
                    $id = (int)$_POST['id'];
                    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'User deactivated successfully.'];
                    } else {
                        throw new Exception('Failed to deactivate user.');
                    }
                    break;
                
                // --- INVENTORY MANAGEMENT ACTIONS ---
                case 'addMedicine':
                    if (empty($_POST['name']) || empty($_POST['quantity']) || empty($_POST['unit_price'])) {
                        throw new Exception('Medicine name, quantity, and unit price are required.');
                    }
                    $name = $_POST['name'];
                    $description = $_POST['description'] ?? null;
                    $quantity = (int)$_POST['quantity'];
                    $unit_price = (float)$_POST['unit_price'];
                    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 10);

                    $stmt = $conn->prepare("INSERT INTO medicines (name, description, quantity, unit_price, low_stock_threshold) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssidi", $name, $description, $quantity, $unit_price, $low_stock_threshold);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Medicine added successfully.'];
                    } else {
                        throw new Exception('Failed to add medicine. It might already exist.');
                    }
                    break;

                case 'updateMedicine':
                    if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['quantity']) || empty($_POST['unit_price'])) {
                        throw new Exception('Medicine ID, name, quantity, and unit price are required.');
                    }
                    $id = (int)$_POST['id'];
                    $name = $_POST['name'];
                    $description = $_POST['description'] ?? null;
                    $quantity = (int)$_POST['quantity'];
                    $unit_price = (float)$_POST['unit_price'];
                    $low_stock_threshold = (int)($_POST['low_stock_threshold'] ?? 10);

                    $stmt = $conn->prepare("UPDATE medicines SET name = ?, description = ?, quantity = ?, unit_price = ?, low_stock_threshold = ? WHERE id = ?");
                    $stmt->bind_param("ssidii", $name, $description, $quantity, $unit_price, $low_stock_threshold, $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Medicine updated successfully.'];
                    } else {
                        throw new Exception('Failed to update medicine.');
                    }
                    break;

                case 'deleteMedicine':
                    if (empty($_POST['id'])) {
                        throw new Exception('Medicine ID is required.');
                    }
                    $id = (int)$_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
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
                    $quantity_ml = (int)$_POST['quantity_ml'];
                    $low_stock_threshold_ml = (int)($_POST['low_stock_threshold_ml'] ?? 5000);

                    $stmt = $conn->prepare("INSERT INTO blood_inventory (blood_group, quantity_ml, low_stock_threshold_ml) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity_ml = ?, low_stock_threshold_ml = ?");
                    $stmt->bind_param("siiii", $blood_group, $quantity_ml, $low_stock_threshold_ml, $quantity_ml, $low_stock_threshold_ml);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Blood inventory updated successfully.'];
                    } else {
                        throw new Exception('Failed to update blood inventory.');
                    }
                    break;
                
                case 'addWard':
                    if (empty($_POST['name']) || empty($_POST['capacity'])) {
                        throw new Exception('Ward name and capacity are required.');
                    }
                    $name = $_POST['name'];
                    $capacity = (int)$_POST['capacity'];
                    $description = $_POST['description'] ?? null;
                    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

                    $stmt = $conn->prepare("INSERT INTO wards (name, capacity, description, is_active) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sisi", $name, $capacity, $description, $is_active);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Ward added successfully.'];
                    } else {
                        throw new Exception('Failed to add ward. It might already exist.');
                    }
                    break;
                
                case 'updateWard':
                    if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['capacity'])) {
                        throw new Exception('Ward ID, name, and capacity are required.');
                    }
                    $id = (int)$_POST['id'];
                    $name = $_POST['name'];
                    $capacity = (int)$_POST['capacity'];
                    $description = $_POST['description'] ?? null;
                    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

                    $stmt = $conn->prepare("UPDATE wards SET name = ?, capacity = ?, description = ?, is_active = ? WHERE id = ?");
                    $stmt->bind_param("sisii", $name, $capacity, $description, $is_active, $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Ward updated successfully.'];
                    } else {
                        throw new Exception('Failed to update ward.');
                    }
                    break;

                case 'deleteWard':
                    if (empty($_POST['id'])) {
                        throw new Exception('Ward ID is required.');
                    }
                    $id = (int)$_POST['id'];
                    // Consider soft delete or checking if beds are occupied
                    $stmt = $conn->prepare("DELETE FROM wards WHERE id = ?"); // Or UPDATE wards SET is_active = 0
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Ward deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete ward. Ensure no beds are assigned to it.');
                    }
                    break;

                case 'addBed':
                    if (empty($_POST['ward_id']) || empty($_POST['bed_number'])) {
                        throw new Exception('Ward and bed number are required.');
                    }
                    $ward_id = (int)$_POST['ward_id'];
                    $bed_number = $_POST['bed_number'];
                    $status = $_POST['status'] ?? 'available';
                    $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
                    $occupied_since = ($status === 'occupied' && $patient_id) ? date('Y-m-d H:i:s') : null;
                    $reserved_since = ($status === 'reserved' && $patient_id) ? date('Y-m-d H:i:s') : null;

                    $stmt = $conn->prepare("INSERT INTO beds (ward_id, bed_number, status, patient_id, occupied_since, reserved_since) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ississ", $ward_id, $bed_number, $status, $patient_id, $occupied_since, $reserved_since);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Bed added successfully.'];
                    } else {
                        throw new Exception('Failed to add bed. Bed number might already exist in this ward.');
                    }
                    break;

                case 'updateBed':
                    if (empty($_POST['id']) || empty($_POST['ward_id']) || empty($_POST['bed_number']) || empty($_POST['status'])) {
                        throw new Exception('Bed ID, ward, bed number, and status are required.');
                    }
                    $id = (int)$_POST['id'];
                    $ward_id = (int)$_POST['ward_id'];
                    $bed_number = $_POST['bed_number'];
                    $new_status = $_POST['status'];
                    $new_patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;

                    // --- Start Transaction for safe update ---
                    $conn->begin_transaction();
                    try {
                        // Fetch the current state of the bed to make intelligent decisions about date updates
                        $stmt_current = $conn->prepare("SELECT status, occupied_since, reserved_since FROM beds WHERE id = ? FOR UPDATE");
                        $stmt_current->bind_param("i", $id);
                        $stmt_current->execute();
                        $current_bed = $stmt_current->get_result()->fetch_assoc();
                        if (!$current_bed) {
                            throw new Exception("Bed not found.");
                        }

                        $occupied_since = $current_bed['occupied_since'];
                        $reserved_since = $current_bed['reserved_since'];
                        $patient_id = $new_patient_id;

                        // Only update "since" dates if the status is actually changing
                        if ($new_status !== $current_bed['status']) {
                            if ($new_status === 'occupied') {
                                $occupied_since = date('Y-m-d H:i:s');
                                $reserved_since = null; // A bed cannot be simultaneously occupied and reserved
                            } elseif ($new_status === 'reserved') {
                                $reserved_since = date('Y-m-d H:i:s');
                                $occupied_since = null;
                            } else { // Status is changing to 'available' or 'cleaning'
                                $occupied_since = null;
                                $reserved_since = null;
                            }
                        }

                        // Regardless of status change, if the new status isn't one that holds a patient, nullify the patient ID.
                        if ($new_status !== 'occupied' && $new_status !== 'reserved') {
                            $patient_id = null;
                        }

                        $stmt = $conn->prepare("UPDATE beds SET ward_id = ?, bed_number = ?, status = ?, patient_id = ?, occupied_since = ?, reserved_since = ? WHERE id = ?");
                        // Corrected bind_param string: s for status, i for patient_id, s for dates
                        $stmt->bind_param("ississi", $ward_id, $bed_number, $new_status, $patient_id, $occupied_since, $reserved_since, $id);

                        if ($stmt->execute()) {
                            $conn->commit();
                            $response = ['success' => true, 'message' => 'Bed updated successfully.'];
                        } else {
                            throw new Exception('Failed to execute the bed update query.');
                        }
                    } catch (Exception $e) {
                        $conn->rollback();
                        // Re-throw the exception to be caught by the main handler
                        throw $e;
                    }
                    break;

                case 'deleteBed':
                    if (empty($_POST['id'])) {
                        throw new Exception('Bed ID is required.');
                    }
                    $id = (int)$_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM beds WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Bed deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete bed.');
                    }
                    break;
                
                case 'addRoom':
                    if (empty($_POST['room_number']) || !isset($_POST['price_per_day'])) {
                        throw new Exception('Room number and price are required.');
                    }
                    $room_number = $_POST['room_number'];
                    $status = $_POST['status'] ?? 'available';
                    $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
                    $price_per_day = (float)$_POST['price_per_day'];
                    $occupied_since = ($status === 'occupied' && $patient_id) ? date('Y-m-d H:i:s') : null;
                    $reserved_since = ($status === 'reserved' && $patient_id) ? date('Y-m-d H:i:s') : null;

                    $stmt = $conn->prepare("INSERT INTO rooms (room_number, status, patient_id, occupied_since, reserved_since, price_per_day) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssissd", $room_number, $status, $patient_id, $occupied_since, $reserved_since, $price_per_day);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Room added successfully.'];
                    } else {
                        throw new Exception('Failed to add room. Room number might already exist.');
                    }
                    break;

                case 'updateRoom':
                    if (empty($_POST['id']) || empty($_POST['room_number']) || !isset($_POST['price_per_day']) || empty($_POST['status'])) {
                        throw new Exception('Room ID, number, price, and status are required.');
                    }
                    $id = (int)$_POST['id'];
                    $room_number = $_POST['room_number'];
                    $status = $_POST['status'];
                    $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
                    $price_per_day = (float)$_POST['price_per_day'];
                    
                    $occupied_since = null;
                    $reserved_since = null;
                    
                    if ($status === 'occupied' && $patient_id) {
                        $occupied_since = date('Y-m-d H:i:s');
                    } elseif ($status === 'reserved' && $patient_id) {
                        $reserved_since = date('Y-m-d H:i:s');
                    }

                    if ($status === 'available' || $status === 'cleaning') {
                        $patient_id = null;
                    }
                    
                    $stmt = $conn->prepare("UPDATE rooms SET room_number = ?, status = ?, patient_id = ?, occupied_since = ?, reserved_since = ?, price_per_day = ? WHERE id = ?");
                    $stmt->bind_param("ssissdi", $room_number, $status, $patient_id, $occupied_since, $reserved_since, $price_per_day, $id);

                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Room updated successfully.'];
                    } else {
                        throw new Exception('Failed to update room.');
                    }
                    break;

                case 'deleteRoom':
                    if (empty($_POST['id'])) {
                        throw new Exception('Room ID is required.');
                    }
                    $id = (int)$_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Room deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete room.');
                    }
                    break;

            }
        }
        elseif (isset($_GET['fetch'])) {
             $fetch_target = $_GET['fetch'];
             switch ($fetch_target) {
                case 'users':
                    if (!isset($_GET['role'])) throw new Exception('User role not specified.');
                    $role = $_GET['role'];
                    
                    $sql = "SELECT u.id, u.display_user_id, u.name, u.username, u.email, u.phone, u.role, u.active, u.created_at, u.date_of_birth, u.gender";
                    
                    if ($role === 'doctor') {
                        $sql .= ", d.specialty, d.qualifications, d.department_id, d.availability 
                                 FROM users u 
                                 LEFT JOIN doctors d ON u.id = d.user_id 
                                 WHERE u.role = ?";
                    } elseif ($role === 'staff') {
                        $sql .= ", s.shift, s.assigned_department 
                                 FROM users u 
                                 LEFT JOIN staff s ON u.id = s.user_id 
                                 WHERE u.role = ?";
                    } else {
                        $sql .= " FROM users u WHERE u.role = ?";
                    }
                    $sql .= " ORDER BY u.created_at DESC";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $role);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;
                
                case 'departments':
                    $result = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'dashboard_stats':
                    $stats = [];
                    $stats['total_users'] = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
                    $stats['active_doctors'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='doctor' AND active=1")->fetch_assoc()['c'];
                    
                    $role_counts_sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
                    $result = $conn->query($role_counts_sql);
                    $counts = ['user' => 0, 'doctor' => 0, 'staff' => 0, 'admin' => 0];
                     while($row = $result->fetch_assoc()){
                        if(array_key_exists($row['role'], $counts)){
                            $counts[$row['role']] = (int)$row['count'];
                        }
                    }
                    $stats['role_counts'] = $counts;

                    // Fetch low stock alerts
                    $low_medicines_stmt = $conn->query("SELECT COUNT(*) as c FROM medicines WHERE quantity <= low_stock_threshold");
                    $stats['low_medicines_count'] = $low_medicines_stmt->fetch_assoc()['c'];

                    $low_blood_stmt = $conn->query("SELECT COUNT(*) as c FROM blood_inventory WHERE quantity_ml <= low_stock_threshold_ml");
                    $stats['low_blood_count'] = $low_blood_stmt->fetch_assoc()['c'];

                    $response = ['success' => true, 'data' => $stats];
                    break;
                
                case 'my_profile': 
                    $admin_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT name, email, phone, username FROM users WHERE id = ?");
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_assoc();
                    $response = ['success' => true, 'data' => $data];
                    break;
                
                // --- INVENTORY FETCH ENDPOINTS ---
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
                    $result = $conn->query("SELECT id, name, capacity, description, is_active FROM wards ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'beds':
                    $sql = "SELECT b.id, b.ward_id, w.name as ward_name, b.bed_number, b.status, b.patient_id, u.name as patient_name, b.occupied_since, b.reserved_since, b.price_per_day 
                            FROM beds b 
                            JOIN wards w ON b.ward_id = w.id 
                            LEFT JOIN users u ON b.patient_id = u.id
                            ORDER BY w.name, b.bed_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;
                
                case 'rooms':
                    $sql = "SELECT r.id, r.room_number, r.status, r.patient_id, u.name as patient_name, r.occupied_since, r.reserved_since, r.price_per_day 
                            FROM rooms r
                            LEFT JOIN users u ON r.patient_id = u.id
                            ORDER BY r.room_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;
                
                case 'patients_for_beds': // Re-used for rooms as well
                    $result = $conn->query("SELECT id, name, display_user_id FROM users WHERE role = 'user' AND active = 1 ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;
             }
        }

    } catch (Throwable $e) { 
        http_response_code(400); 
        $response['message'] = $e->getMessage();
    }
    
    restore_error_handler();
    echo json_encode($response);
    exit();
}

// ===================================================================================
// --- STANDARD PAGE LOAD LOGIC ---
// ===================================================================================
$conn = getDbConnection();
$admin_id = $_SESSION['user_id'];

// Fetch admin's full name for the welcome message
$stmt = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_user = $result->fetch_assoc();
$admin_name = $admin_user ? htmlspecialchars($admin_user['name']) : 'Admin';
$display_user_id = $admin_user ? htmlspecialchars($admin_user['display_user_id']) : 'N/A';
$stmt->close();


$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$active_doctors = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='doctor' AND active=1")->fetch_assoc()['c'];
$pending_appointments = 0; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MedSync</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        /* --- THEMES AND MODERN ADMIN COLOR PALETTE --- */
        :root {
            --primary-color: #3B82F6; /* A modern, vibrant blue */
            --primary-color-dark: #2563EB;
            --danger-color: #EF4444;
            --success-color: #22C55E;
            --warning-color: #F97316;
            
            --text-dark: #1F2937; /* Dark Gray */
            --text-light: #F9FAFB; /* Almost White */
            --text-muted: #6B7280; /* Medium Gray */
            
            --bg-light: #FFFFFF; /* White */
            --bg-grey: #F3F4F6; /* Lightest Gray */
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
            --bg-light: #1F2937; /* Card Background */
            --bg-grey: #111827; /* Main Background */
            --border-light: #374151;
        }

        /* --- BASE STYLES --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-grey);
            color: var(--text-dark);
            transition: background-color var(--transition-speed), color var(--transition-speed);
            font-size: 16px;
        }
        .dashboard-layout { display: flex; min-height: 100vh; }

        /* --- SIDEBAR --- */
        .sidebar {
            width: 280px;
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
        .sidebar-header { display: flex; align-items: center; margin-bottom: 2.5rem; padding-left: 0.5rem; }
        .sidebar-header .logo-img { height: 40px; margin-right: 10px; }
        .sidebar-header .logo-text { font-size: 1.5rem; font-weight: 600; color: var(--text-dark); }
        .sidebar-nav { flex-grow: 1; overflow-y: auto; }
        .sidebar-nav ul { list-style: none; }
        .sidebar-nav a, .nav-dropdown-toggle {
            display: flex; align-items: center; padding: 0.9rem 1rem; color: var(--text-muted);
            text-decoration: none; border-radius: 8px; margin-bottom: 0.5rem;
            transition: background-color var(--transition-speed), color var(--transition-speed);
            font-weight: 500; cursor: pointer;
        }
        .sidebar-nav a i, .nav-dropdown-toggle i { width: 20px; margin-right: 1rem; font-size: 1.1rem; text-align: center; }
        .sidebar-nav a:hover, .nav-dropdown-toggle:hover { background-color: var(--bg-grey); color: var(--primary-color); }
        .sidebar-nav a.active, .nav-dropdown-toggle.active { background-color: var(--primary-color); color: white; }
        body.dark-mode .sidebar-nav a.active, body.dark-mode .nav-dropdown-toggle.active { background-color: var(--primary-color-dark); }
        .nav-dropdown-toggle .arrow { margin-left: auto; transition: transform var(--transition-speed); }
        .nav-dropdown-toggle.active .arrow { transform: rotate(90deg); }
        .nav-dropdown { list-style: none; max-height: 0; overflow: hidden; transition: max-height 0.4s ease-in-out; padding-left: 1.5rem; }
        .nav-dropdown a { font-size: 0.95rem; padding: 0.7rem 1rem 0.7rem 0.5rem; background-color: rgba(100,100,100,0.05); }
        body.dark-mode .nav-dropdown a { background-color: rgba(255,255,255,0.05); }
        .logout-btn { display: flex; align-items: center; justify-content: center; width: 100%; padding: 0.9rem 1rem; background-color: transparent; color: var(--danger-color); border: 1px solid var(--danger-color); border-radius: 8px; font-size: 1rem; font-family: 'Poppins', sans-serif; font-weight: 500; cursor: pointer; transition: all var(--transition-speed); margin-top: 1rem; }
        .logout-btn:hover { background-color: var(--danger-color); color: white; }

        /* --- MAIN CONTENT --- */
        .main-content { flex-grow: 1; padding: 2rem; overflow-y: auto; margin-left: 280px; transition: margin-left var(--transition-speed); }
        .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .main-header .title-group { flex-grow: 1; }
        .main-header h1 { font-size: 1.8rem; font-weight: 600; margin: 0; }
        .main-header h2 { font-size: 1.2rem; font-weight: 400; color: var(--text-muted); margin: 0.25rem 0 0 0; }
        .header-actions { display: flex; align-items: center; gap: 1rem; }
        .user-profile-widget { display: flex; align-items: center; gap: 1rem; background-color: var(--bg-light); padding: 0.5rem 1rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); }
        .user-profile-widget i { font-size: 1.5rem; color: var(--primary-color); }
        .content-panel { display: none; background-color: var(--bg-light); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); animation: fadeIn 0.5s ease-in-out; }
        .content-panel.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        /* --- DASHBOARD HOME --- */
        .stat-cards-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-top: 2rem; }
        .stat-card { background: var(--bg-light); padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); display: flex; align-items: center; gap: 1.5rem; border-left: 5px solid var(--primary-color); transition: transform 0.2s, box-shadow 0.2s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .stat-card .icon { font-size: 2rem; padding: 1rem; border-radius: 50%; color: var(--primary-color); background-color: var(--bg-grey); }
        .stat-card.blue { border-left-color: #3B82F6; } .stat-card.blue .icon { color: #3B82F6; }
        .stat-card.green { border-left-color: var(--success-color); } .stat-card.green .icon { color: var(--success-color); }
        .stat-card.orange { border-left-color: var(--warning-color); } .stat-card.orange .icon { color: var(--warning-color); }
        .stat-card.red { border-left-color: var(--danger-color); } .stat-card.red .icon { color: var(--danger-color); } /* Added for low stock */
        .stat-card .info .value { font-size: 1.75rem; font-weight: 600; }
        .stat-card .info .label { color: var(--text-muted); font-size: 0.9rem; }
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem; }
        .grid-card { background-color: var(--bg-light); padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--shadow-md); }
        .grid-card h3 { margin-bottom: 1.5rem; font-weight: 600; }

        /* --- QUICK ACTIONS --- */
        .quick-actions .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; }
        .quick-actions .action-btn { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.2rem 1rem; border-radius: var(--border-radius); background-color: var(--bg-grey); color: var(--text-dark); text-decoration: none; font-weight: 500; text-align: center; transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s, color 0.2s; }
        .quick-actions .action-btn:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); background-color: var(--primary-color); color: white; }
        .quick-actions .action-btn i { font-size: 1.8rem; margin-bottom: 0.75rem; }

        /* --- USER MANAGEMENT TABLE & GENERIC TABLE STYLES --- */
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-light); white-space: nowrap; }
        .data-table th { font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .data-table tbody tr { transition: background-color var(--transition-speed); }
        .data-table tbody tr:hover { background-color: var(--bg-grey); }
        .status-badge { padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .status-badge.active, .status-badge.in-stock { background-color: #D1FAE5; color: #065F46; }
        .status-badge.inactive, .status-badge.low-stock { background-color: #FEE2E2; color: #991B1B; }
        body.dark-mode .status-badge.active, body.dark-mode .status-badge.in-stock { background-color: #064E3B; color: #A7F3D0; }
        body.dark-mode .status-badge.inactive, body.dark-mode .status-badge.low-stock { background-color: #7F1D1D; color: #FECACA; }
        .action-buttons button { background: none; border: none; cursor: pointer; font-size: 1.1rem; margin: 0 5px; transition: color var(--transition-speed); }
        .action-buttons .btn-edit { color: var(--primary-color); }
        .action-buttons .btn-delete { color: var(--danger-color); }
        .quantity-good { color: var(--success-color); font-weight: 600; }
        .quantity-low { color: var(--danger-color); font-weight: 600; }

        /* --- BUTTONS & FORMS --- */
        .btn { padding: 0.7rem 1.4rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all var(--transition-speed); border: 1px solid transparent; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: var(--primary-color-dark); }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border: 1px solid var(--border-light); border-radius: 8px; background-color: var(--bg-grey); color: var(--text-dark); transition: all var(--transition-speed); }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .role-specific-fields {
            border-top: 1px solid var(--border-light);
            margin-top: 1.5rem;
            padding-top: 1.5rem;
        }
        
        /* --- MODAL, NOTIFICATION, CONFIRMATION STYLES --- */
        .modal, .notification-container, .confirm-dialog { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; display: none; align-items: center; justify-content: center; backdrop-filter: blur(4px); background-color: rgba(0,0,0,0.5); }
        .modal.show, .notification-container.show, .confirm-dialog.show { display: flex; }
        .modal-content, .confirm-content { background-color: var(--bg-light); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow-lg); width: 90%; max-width: 500px; animation: slideIn 0.3s ease-out; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .modal-header h3 { margin: 0; }
        .modal-close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        @keyframes slideIn { from { transform: translateY(-30px) scale(0.95); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
        .notification { padding: 1rem 1.5rem; border-radius: 8px; color: white; box-shadow: var(--shadow-lg); animation: slideIn 0.3s, fadeOut 0.5s 4.5s forwards; position: fixed; top: 20px; right: 20px; z-index: 1100; }
        .notification.success { background-color: var(--success-color); }
        .notification.error { background-color: var(--danger-color); }
        .notification.warning { background-color: var(--warning-color); }
        @keyframes fadeOut { to { opacity: 0; transform: translateY(-20px); } }
        .confirm-content { text-align: center; }
        .confirm-content h4 { margin-bottom: 1rem; } .confirm-content p { margin-bottom: 1.5rem; color: var(--text-muted); }
        .confirm-buttons { display: flex; justify-content: center; gap: 1rem; }
        .btn-secondary { background-color: var(--bg-grey); color: var(--text-dark); border-color: var(--border-light); }
        body.dark-mode .btn-secondary { background-color: #374151; color: var(--text-light); border-color: #4B5563; }
        .btn-secondary:hover { background-color: #E5E7EB; }
        body.dark-mode .btn-secondary:hover { background-color: #4B5563; }
        .btn-danger { background-color: var(--danger-color); color: white; }

        /* --- DARK/LIGHT THEME TOGGLE --- */
        .theme-switch-wrapper { display: flex; align-items: center; }
        .theme-switch { display: inline-block; height: 24px; position: relative; width: 48px; }
        .theme-switch input { display: none; }
        .slider { background-color: #ccc; bottom: 0; cursor: pointer; left: 0; position: absolute; right: 0; top: 0; transition: .4s; border-radius: 24px; }
        .slider:before { background-color: #fff; content: ""; height: 18px; left: 3px; position: absolute; bottom: 3px; transition: .4s; width: 18px; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-color-dark); }
        input:checked + .slider:before { transform: translateX(24px); }
        .theme-switch-wrapper .fa-sun, .theme-switch-wrapper .fa-moon { margin: 0 8px; color: var(--text-muted); }
        
        /* --- INVENTORY: BEDS & ROOMS --- */
        .resource-grid-container, .ward-beds-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 1.5rem;
        }
        .ward-section {
            margin-bottom: 2rem;
        }
        .ward-header {
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ward-header h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .bed-card, .room-card {
            background-color: var(--bg-light);
            padding: 1.25rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            text-align: center;
            border-left: 5px solid;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .bed-card:hover, .room-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        .bed-card.available, .room-card.available { border-color: var(--success-color); }
        .bed-card.occupied, .room-card.occupied { border-color: var(--danger-color); }
        .bed-card.reserved, .room-card.reserved { border-color: var(--primary-color); }
        .bed-card.cleaning, .room-card.cleaning { border-color: var(--warning-color); }

        .bed-card .bed-icon, .room-card .room-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }
        .bed-card.available .bed-icon, .room-card.available .room-icon { color: var(--success-color); }
        .bed-card.occupied .bed-icon, .room-card.occupied .room-icon { color: var(--danger-color); }
        .bed-card.reserved .bed-icon, .room-card.reserved .room-icon { color: var(--primary-color); }
        .bed-card.cleaning .bed-icon, .room-card.cleaning .room-icon { color: var(--warning-color); }

        .bed-card .bed-number, .room-card .room-number {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .bed-card .bed-status, .room-card .room-status {
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }
        .bed-card .patient-info, .room-card .patient-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
        .bed-card .action-buttons, .room-card .action-buttons {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }
        .bed-card .action-buttons button, .room-card .action-buttons button {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        /* --- MOBILE & RESPONSIVE --- */
        .hamburger-btn { display: none; background: none; border: none; font-size: 1.5rem; color: var(--text-dark); cursor: pointer; z-index: 1001; }
        .overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background-color: rgba(0, 0, 0, 0.5); z-index: 998; }

        @media (max-width: 992px) {
            .sidebar { left: -280px; }
            .sidebar.active { left: 0; box-shadow: var(--shadow-lg); }
            .main-content { margin-left: 0; }
            .hamburger-btn { display: block; }
            .main-header { justify-content: flex-start; gap: 1rem; }
            .main-header .title-group { order: 2; }
            .header-actions { margin-left: auto; order: 3; }
            .overlay.active { display: block; }
            .dashboard-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .main-content { padding: 1rem; }
            .main-header h1 { font-size: 1.4rem; }
            .main-header h2 { font-size: 1rem; }
            .stat-cards-container { grid-template-columns: 1fr; }
            .header-actions { gap: 0.5rem; }
            .user-profile-widget { padding: 0.5rem; }
            .user-profile-widget .user-info { display: none; }
        }
    </style>
</head>
<body class="light-mode">
    <div class="dashboard-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-users"></i> Users <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <li><a href="#" class="nav-link" data-target="users-user"><i class="fas fa-user-injured"></i> Regular Users</a></li>
                            <li><a href="#" class="nav-link" data-target="users-doctor"><i class="fas fa-user-md"></i> Doctors</a></li>
                            <li><a href="#" class="nav-link" data-target="users-staff"><i class="fas fa-user-shield"></i> Staff</a></li>
                            <li><a href="#" class="nav-link" data-target="users-admin"><i class="fas fa-user-cog"></i> Admins</a></li>
                        </ul>
                    </li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-warehouse"></i> Inventory <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <li><a href="#" class="nav-link" data-target="inventory-blood"><i class="fas fa-tint"></i> Blood Inventory</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-medicine"><i class="fas fa-pills"></i> Medicine Inventory</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-wards"><i class="fas fa-hospital"></i> Wards</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-beds"><i class="fas fa-bed"></i> Beds</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-rooms"><i class="fas fa-door-closed"></i> Rooms</a></li>
                        </ul>
                    </li>
                    <li><a href="#" class="nav-link" data-target="shifts"><i class="fas fa-calendar-alt"></i> Staff Shifts</a></li>
                    <li><a href="#" class="nav-link" data-target="reports"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="#" class="nav-link" data-target="activity"><i class="fas fa-history"></i> Activity Logs</a></li>
                    <li><a href="#" class="nav-link" data-target="settings"><i class="fas fa-user-edit"></i> My Account</a></li>
                    <li><a href="#" class="nav-link" data-target="backup"><i class="fas fa-database"></i> Backup</a></li>
                    <li><a href="#" class="nav-link" data-target="notifications"><i class="fas fa-bullhorn"></i> Notifications</a></li>
                </ul>
            </nav>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="title-group">
                    <h1 id="panel-title">Dashboard</h1>
                    <h2 id="welcome-message">Hello, <?php echo $admin_name; ?>!</h2>
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
                    <div class="user-profile-widget">
                        <i class="fas fa-user-crown"></i>
                        <div class="user-info">
                            <strong><?php echo $admin_name; ?></strong><br>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">ID: <?php echo $display_user_id; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div id="dashboard-panel" class="content-panel active">
                <div class="stat-cards-container">
                    <div class="stat-card blue"><div class="icon"><i class="fas fa-users"></i></div><div class="info"><div class="value" id="total-users-stat"><?php echo $total_users; ?></div><div class="label">Total Users</div></div></div>
                    <div class="stat-card green"><div class="icon"><i class="fas fa-user-md"></i></div><div class="info"><div class="value" id="active-doctors-stat"><?php echo $active_doctors; ?></div><div class="label">Active Doctors</div></div></div>
                    <div class="stat-card orange"><div class="icon"><i class="fas fa-calendar-check"></i></div><div class="info"><div class="value"><?php echo $pending_appointments; ?></div><div class="label">Pending Appointments</div></div></div>
                    <div class="stat-card red" id="low-medicine-stat" style="display: none;"><div class="icon"><i class="fas fa-pills"></i></div><div class="info"><div class="value" id="low-medicine-count">0</div><div class="label">Low Medicines</div></div></div>
                    <div class="stat-card red" id="low-blood-stat" style="display: none;"><div class="icon"><i class="fas fa-tint"></i></div><div class="info"><div class="value" id="low-blood-count">0</div><div class="label">Low Blood Units</div></div></div>
                </div>
                <div class="dashboard-grid">
                    <div class="grid-card">
                        <h3>User Roles Distribution</h3>
                        <div style="position: relative; height: auto; max-width: 450px; margin: auto;">
                            <canvas id="userRolesChart"></canvas>
                        </div>
                    </div>
                    <div class="grid-card quick-actions">
                        <h3>Quick Actions</h3>
                        <div class="actions-grid">
                            <a href="#" class="action-btn" id="quick-add-user-btn"><i class="fas fa-user-plus"></i> Add User</a>
                            <a href="#" class="action-btn"><i class="fas fa-file-alt"></i> Generate Report</a>
                            <a href="#" class="action-btn"><i class="fas fa-database"></i> Backup Data</a>
                            <a href="#" class="action-btn"><i class="fas fa-bullhorn"></i> Send Notification</a>
                        </div>
                    </div>
                </div>
            </div>

            <div id="users-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 id="user-table-title">Users</h2>
                    <button id="add-user-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New User</button>
                </div>
                <div class="table-container">
                    <table class="data-table user-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-body">
                            </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-blood-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Blood Inventory</h2>
                    <button id="add-blood-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Update Blood Unit</button>
                </div>
                <div class="table-container">
                    <table class="data-table blood-table">
                        <thead>
                            <tr>
                                <th>Blood Group</th>
                                <th>Quantity (ml)</th>
                                <th>Status</th>
                                <th>Low Stock Threshold (ml)</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="blood-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-medicine-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Medicine Inventory</h2>
                    <button id="add-medicine-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Medicine</button>
                </div>
                <div class="table-container">
                    <table class="data-table medicine-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Unit Price (₹)</th>
                                <th>Low Stock Threshold</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="medicine-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-wards-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Ward Management</h2>
                    <button id="add-ward-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Ward</button>
                </div>
                <div class="table-container">
                    <table class="data-table ward-table">
                        <thead>
                            <tr>
                                <th>Ward Name</th>
                                <th>Capacity</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ward-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-beds-panel" class="content-panel">
                 <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Bed Management</h2>
                    <button id="add-bed-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Bed</button>
                </div>
                <div id="beds-container">
                    </div>
            </div>

            <div id="inventory-rooms-panel" class="content-panel">
                 <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Room Management</h2>
                    <button id="add-room-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Room</button>
                </div>
                <div id="rooms-container" class="resource-grid-container">
                    </div>
            </div>
            <div id="settings-panel" class="content-panel">
                <h3>My Account Details</h3>
                <p>Edit your personal information and password here.</p>
                <form id="profile-form" style="margin-top: 2rem; max-width: 600px;">
                    <input type="hidden" name="action" value="updateProfile">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="form-group">
                        <label for="profile-name">Full Name</label>
                        <input type="text" id="profile-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email</label>
                        <input type="email" id="profile-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-phone">Phone Number</label>
                        <input type="tel" id="profile-phone" name="phone" pattern="\+[0-9]{10,15}" title="Enter in format +CountryCodeNumber">
                    </div>
                    <div class="form-group">
                        <label for="profile-username">Username</label>
                        <input type="text" id="profile-username" name="username" disabled>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Username cannot be changed.</small>
                    </div>
                    <div class="form-group">
                        <label for="profile-password">New Password</label>
                        <input type="password" id="profile-password" name="password">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep your current password.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
            
            <div id="shifts-panel" class="content-panel"><p>Staff Shifts Management coming soon.</p></div>
            <div id="reports-panel" class="content-panel"><p>Reports and Analytics coming soon.</p></div>
            <div id="activity-panel" class="content-panel"><p>Activity Logs coming soon.</p></div>
            <div id="backup-panel" class="content-panel"><p>Database Backup utility coming soon.</p></div>
            <div id="notifications-panel" class="content-panel"><p>Notification management coming soon.</p></div>
        </main>
    </div>
    
    <div class="overlay" id="overlay"></div>

    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add New User</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="user-form">
                <input type="hidden" name="id" id="user-id">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
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
                    <input type="tel" id="phone" name="phone" pattern="\+[0-9]{10,15}" title="Enter in format +CountryCodeNumber" required>
                </div>
                 <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group" id="password-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password">
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep current password when editing.</small>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="user">Regular User</option>
                        <option value="doctor">Doctor</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div id="doctor-fields" class="role-specific-fields" style="display: none;">
                    <h4>Doctor Details</h4>
                    <div class="form-group">
                        <label for="specialty">Specialty</label>
                        <input type="text" id="specialty" name="specialty">
                    </div>
                    <div class="form-group">
                        <label for="qualifications">Qualifications (e.g., MBBS, MD)</label>
                        <input type="text" id="qualifications" name="qualifications">
                    </div>
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="availability">Availability</label>
                        <select id="availability" name="availability">
                            <option value="1">Available</option>
                            <option value="0">On Leave</option>
                        </select>
                    </div>
                </div>

                <div id="staff-fields" class="role-specific-fields" style="display: none;">
                    <h4>Staff Details</h4>
                    <div class="form-group">
                        <label for="shift">Shift</label>
                        <select id="shift" name="shift">
                            <option value="day">Day</option>
                            <option value="night">Night</option>
                            <option value="off">Off</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assigned_department">Assigned Department</label>
                        <select id="assigned_department" name="assigned_department">
                             <option value="">Select Department</option>
                        </select>
                    </div>
                </div>

                 <div class="form-group" id="active-group" style="display: none;">
                    <label for="active">Status</label>
                    <select id="active" name="active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save User</button>
            </form>
        </div>
    </div>
    
    <div id="medicine-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="medicine-modal-title">Add New Medicine</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="medicine-form">
                <input type="hidden" name="id" id="medicine-id">
                <input type="hidden" name="action" id="medicine-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="medicine-name">Medicine Name</label>
                    <input type="text" id="medicine-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="medicine-description">Description</label>
                    <textarea id="medicine-description" name="description" rows="3"></textarea>
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
                    <input type="number" id="medicine-low-stock-threshold" name="low_stock_threshold" min="0" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Medicine</button>
            </form>
        </div>
    </div>

    <div id="blood-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="blood-modal-title">Update Blood Inventory</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="blood-form">
                <input type="hidden" name="action" value="updateBlood">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
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
                    <input type="number" id="blood-low-stock-threshold-ml" name="low_stock_threshold_ml" min="0" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Blood</button>
            </form>
        </div>
    </div>

    <div id="ward-form-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="ward-form-modal-title">Add New Ward</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="ward-form">
                <input type="hidden" name="id" id="ward-id-input">
                <input type="hidden" name="action" id="ward-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label for="ward-name-input">Ward Name</label>
                    <input type="text" id="ward-name-input" name="name" required>
                </div>
                <div class="form-group">
                    <label for="ward-capacity-input">Capacity</label>
                    <input type="number" id="ward-capacity-input" name="capacity" min="0" required>
                </div>
                <div class="form-group">
                    <label for="ward-description-input">Description</label>
                    <textarea id="ward-description-input" name="description" rows="3"></textarea>
                </div>
                <div class="form-group" id="ward-active-group" style="display: none;">
                    <label for="ward-is-active-input">Status</label>
                    <select id="ward-is-active-input" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Ward</button>
            </form>
        </div>
    </div>

    <div id="bed-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="bed-modal-title">Add New Bed</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="bed-form">
                <input type="hidden" name="id" id="bed-id">
                <input type="hidden" name="action" id="bed-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="bed-ward-id">Ward</label>
                    <select id="bed-ward-id" name="ward_id" required>
                        </select>
                </div>
                <div class="form-group">
                    <label for="bed-number">Bed Number</label>
                    <input type="text" id="bed-number" name="bed_number" required>
                </div>
                <div class="form-group">
                    <label for="bed-status">Status</label>
                    <select id="bed-status" name="status" required>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="cleaning">Cleaning</option>
                    </select>
                </div>
                <div class="form-group" id="bed-patient-group" style="display: none;">
                    <label for="bed-patient-id">Patient</label>
                    <select id="bed-patient-id" name="patient_id">
                        <option value="">Select Patient</option>
                        </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Bed</button>
            </form>
        </div>
    </div>

    <div id="room-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="room-modal-title">Add New Room</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="room-form">
                <input type="hidden" name="id" id="room-id">
                <input type="hidden" name="action" id="room-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="room-number">Room Number</label>
                    <input type="text" id="room-number" name="room_number" required>
                </div>
                 <div class="form-group">
                    <label for="room-price-per-day">Price Per Day (₹)</label>
                    <input type="number" id="room-price-per-day" name="price_per_day" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="room-status">Status</label>
                    <select id="room-status" name="status" required>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="cleaning">Cleaning</option>
                    </select>
                </div>
                <div class="form-group" id="room-patient-group" style="display: none;">
                    <label for="room-patient-id">Patient</label>
                    <select id="room-patient-id" name="patient_id">
                        <option value="">Select Patient</option>
                        </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Room</button>
            </form>
        </div>
    </div>


    <div id="notification-container"></div>
    
    <div id="confirm-dialog" class="confirm-dialog">
        <div class="confirm-content">
            <h4 id="confirm-title">Are you sure?</h4>
            <p id="confirm-message">This action cannot be undone.</p>
            <div class="confirm-buttons">
                <button id="confirm-btn-cancel" class="btn btn-secondary">Cancel</button>
                <button id="confirm-btn-ok" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    </div>


    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // --- CORE UI ELEMENTS & STATE ---
        const csrfToken = '<?php echo $csrf_token; ?>';
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const navLinks = document.querySelectorAll('.nav-link');
        const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
        const panelTitle = document.getElementById('panel-title');
        const welcomeMessage = document.getElementById('welcome-message');
        let currentRole = 'user'; 
        let userRolesChart = null;

        // --- HELPER FUNCTIONS ---
        const showNotification = (message, type = 'success') => {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);
            setTimeout(() => {
                notification.remove();
            }, 5000);
        };
        
        const showConfirmation = (title, message) => {
            return new Promise((resolve) => {
                const dialog = document.getElementById('confirm-dialog');
                document.getElementById('confirm-title').textContent = title;
                document.getElementById('confirm-message').textContent = message;
                dialog.classList.add('show');

                const cancelBtn = document.getElementById('confirm-btn-cancel');
                const okBtn = document.getElementById('confirm-btn-ok');

                const cleanup = (result) => {
                    dialog.classList.remove('show');
                    resolve(result);
                    okBtn.removeEventListener('click', handleOk);
                    cancelBtn.removeEventListener('click', handleCancel);
                };

                const handleOk = () => cleanup(true);
                const handleCancel = () => cleanup(false);

                okBtn.addEventListener('click', handleOk, { once: true });
                cancelBtn.addEventListener('click', handleCancel, { once: true });
            });
        };

        // --- THEME TOGGLE ---
        const themeToggle = document.getElementById('theme-toggle');
        const applyTheme = (theme) => {
            document.body.className = theme;
            themeToggle.checked = theme === 'dark-mode';
            if (userRolesChart) {
                updateChartAppearance();
            }
        };

        themeToggle.addEventListener('change', () => {
            const newTheme = themeToggle.checked ? 'dark-mode' : 'light-mode';
            localStorage.setItem('theme', newTheme);
            applyTheme(newTheme);
        });
        applyTheme(localStorage.getItem('theme') || 'light-mode');


        // --- SIDEBAR & NAVIGATION ---
        const toggleMenu = () => {
            const isActive = sidebar.classList.contains('active');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            hamburgerBtn.querySelector('i').className = `fas ${isActive ? 'fa-bars' : 'fa-times'}`;
        };

        hamburgerBtn.addEventListener('click', e => { e.stopPropagation(); toggleMenu(); });
        overlay.addEventListener('click', toggleMenu);

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function() {
                this.classList.toggle('active');
                const dropdown = this.nextElementSibling;
                dropdown.style.maxHeight = dropdown.style.maxHeight ? null : dropdown.scrollHeight + "px";
            });
        });

        // --- PANEL SWITCHING LOGIC ---
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.dataset.target;

                document.querySelectorAll('.sidebar-nav a.active').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
                
                let parentDropdown = this.closest('.nav-dropdown');
                if (parentDropdown) {
                    let parentDropdownToggle = parentDropdown.previousElementSibling;
                    if (parentDropdownToggle) {
                        parentDropdownToggle.classList.add('active');
                        parentDropdown.style.maxHeight = parentDropdown.scrollHeight + "px";
                    }
                }

                let panelToShowId = 'dashboard-panel';
                let title = 'Dashboard';
                welcomeMessage.style.display = 'block';
                
                if (targetId.startsWith('users-')) {
                    panelToShowId = 'users-panel';
                    const role = targetId.split('-')[1];
                    title = `${role.charAt(0).toUpperCase() + role.slice(1)} Management`;
                    welcomeMessage.style.display = 'none';
                    fetchUsers(role);
                } else if (targetId.startsWith('inventory-')) {
                    panelToShowId = targetId + '-panel';
                    title = this.innerText;
                    welcomeMessage.style.display = 'none';
                    const inventoryType = targetId.split('-')[1];
                    if (inventoryType === 'blood') fetchBloodInventory();
                    else if (inventoryType === 'medicine') fetchMedicineInventory();
                    else if (inventoryType === 'wards') fetchWards();
                    else if (inventoryType === 'beds') fetchWardsAndBeds();
                    else if (inventoryType === 'rooms') fetchRooms();
                }
                else if (document.getElementById(targetId + '-panel')) {
                    panelToShowId = targetId + '-panel';
                    title = this.innerText;
                    welcomeMessage.style.display = (targetId === 'dashboard') ? 'block' : 'none';
                    if (targetId === 'settings') fetchMyProfile();
                }
                
                document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
                document.getElementById(panelToShowId).classList.add('active');
                panelTitle.textContent = title;

                if (window.innerWidth <= 992 && sidebar.classList.contains('active')) toggleMenu();
            });
        });

        // --- CHART.JS & DASHBOARD STATS ---
        const updateChartAppearance = () => {
            if (!userRolesChart) return;
            const isDarkMode = document.body.classList.contains('dark-mode');
            const textColor = isDarkMode ? '#F9FAFB' : '#1F2937';
            const borderColor = isDarkMode ? '#111827' : '#FFFFFF';

            userRolesChart.options.plugins.legend.labels.color = textColor;
            userRolesChart.data.datasets[0].borderColor = borderColor;
            userRolesChart.update();
        };

        const updateDashboardStats = async () => {
            try {
                const response = await fetch('?fetch=dashboard_stats');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                const stats = result.data;
                document.getElementById('total-users-stat').textContent = stats.total_users;
                document.getElementById('active-doctors-stat').textContent = stats.active_doctors;
                
                const lowMedicineStat = document.getElementById('low-medicine-stat');
                const lowBloodStat = document.getElementById('low-blood-stat');

                if (stats.low_medicines_count > 0) {
                    document.getElementById('low-medicine-count').textContent = stats.low_medicines_count;
                    lowMedicineStat.style.display = 'flex';
                } else {
                    lowMedicineStat.style.display = 'none';
                }

                if (stats.low_blood_count > 0) {
                    document.getElementById('low-blood-count').textContent = stats.low_blood_count;
                    lowBloodStat.style.display = 'flex';
                } else {
                    lowBloodStat.style.display = 'none';
                }

                const chartData = [
                    stats.role_counts.user || 0, 
                    stats.role_counts.doctor || 0, 
                    stats.role_counts.staff || 0, 
                    stats.role_counts.admin || 0
                ];
                
                const ctx = document.getElementById('userRolesChart').getContext('2d');
                if (userRolesChart) {
                    userRolesChart.data.datasets[0].data = chartData;
                    userRolesChart.update();
                } else {
                    userRolesChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: ['Users', 'Doctors', 'Staff', 'Admins'],
                            datasets: [{
                                label: 'User Roles',
                                data: chartData,
                                backgroundColor: ['#3B82F6', '#22C55E', '#F97316', '#8B5CF6'],
                                borderWidth: 4
                            }]
                        },
                        options: {
                            responsive: true, maintainAspectRatio: true, 
                            plugins: { legend: { position: 'bottom' } },
                            cutout: '70%'
                        }
                    });
                }
                updateChartAppearance();
            } catch (error) {
                console.error('Failed to update dashboard stats:', error);
                showNotification('Could not refresh dashboard data.', 'error');
            }
        };

        // --- USER MANAGEMENT (CRUD) ---
        const userModal = document.getElementById('user-modal');
        const userForm = document.getElementById('user-form');
        const addUserBtn = document.getElementById('add-user-btn');
        const quickAddUserBtn = document.getElementById('quick-add-user-btn');
        const modalTitle = document.getElementById('modal-title');
        const passwordGroup = document.getElementById('password-group');
        const activeGroup = document.getElementById('active-group');
        const roleSelect = document.getElementById('role');
        const doctorFields = document.getElementById('doctor-fields');
        const staffFields = document.getElementById('staff-fields');

        const toggleRoleFields = () => {
            const selectedRole = roleSelect.value;
            doctorFields.style.display = selectedRole === 'doctor' ? 'block' : 'none';
            staffFields.style.display = selectedRole === 'staff' ? 'block' : 'none';
        };

        roleSelect.addEventListener('change', toggleRoleFields);
        
        const fetchDepartments = async () => {
            try {
                const response = await fetch('?fetch=departments');
                const result = await response.json();
                if (result.success) {
                    const departmentSelect = document.getElementById('department_id');
                    const staffDepartmentSelect = document.getElementById('assigned_department');
                    departmentSelect.innerHTML = '<option value="">Select Department</option>'; // Reset
                    staffDepartmentSelect.innerHTML = '<option value="">Select Department</option>'; // Reset
                    result.data.forEach(dept => {
                        const option = `<option value="${dept.id}">${dept.name}</option>`;
                        departmentSelect.innerHTML += option;
                        staffDepartmentSelect.innerHTML += `<option value="${dept.name}">${dept.name}</option>`;
                    });
                }
            } catch (error) {
                console.error('Failed to fetch departments:', error);
            }
        };

        const openUserModal = (mode, user = {}) => {
            userForm.reset();
            roleSelect.value = currentRole;
            roleSelect.disabled = (mode === 'edit');
            
            if (mode === 'add') {
                modalTitle.textContent = `Add New ${currentRole.charAt(0).toUpperCase() + currentRole.slice(1)}`;
                document.getElementById('form-action').value = 'addUser';
                document.getElementById('password').required = true;
                passwordGroup.style.display = 'block';
                activeGroup.style.display = 'none';
            } else { // edit mode
                modalTitle.textContent = `Edit ${user.username}`;
                document.getElementById('form-action').value = 'updateUser';
                document.getElementById('user-id').value = user.id;
                document.getElementById('name').value = user.name || '';
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('phone').value = user.phone || '';
                document.getElementById('date_of_birth').value = user.date_of_birth || '';
                document.getElementById('gender').value = user.gender || '';
                document.getElementById('password').required = false;
                passwordGroup.style.display = 'block';
                activeGroup.style.display = 'block';
                document.getElementById('active').value = user.active;

                if (user.role === 'doctor') {
                    document.getElementById('specialty').value = user.specialty || '';
                    document.getElementById('qualifications').value = user.qualifications || '';
                    document.getElementById('department_id').value = user.department_id || '';
                    document.getElementById('availability').value = user.availability !== null ? user.availability : 1;
                } else if (user.role === 'staff') {
                    document.getElementById('shift').value = user.shift || 'day';
                    document.getElementById('assigned_department').value = user.assigned_department || '';
                }
            }
            toggleRoleFields();
            userModal.classList.add('show');
        };

        const closeModal = (modalElement) => modalElement.classList.remove('show');
        
        addUserBtn.addEventListener('click', () => openUserModal('add'));
        quickAddUserBtn.addEventListener('click', (e) => {
             e.preventDefault();
             document.querySelector('.nav-link[data-target="users-user"]').click();
             setTimeout(() => openUserModal('add'), 100);
        });
        userModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(userModal));
        userModal.addEventListener('click', (e) => { if (e.target === userModal) closeModal(userModal); });

        const fetchUsers = async (role) => {
            currentRole = role;
            document.getElementById('user-table-title').textContent = `${role.charAt(0).toUpperCase() + role.slice(1)}s`;
            const tableBody = document.getElementById('user-table-body');
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Loading...</td></tr>`;

            try {
                const response = await fetch(`?fetch=users&role=${role}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                if (result.data.length > 0) {
                    tableBody.innerHTML = result.data.map(user => `
                        <tr data-user='${JSON.stringify(user)}'>
                            <td>${user.display_user_id || 'N/A'}</td>
                            <td>${user.name || 'N/A'}</td>
                            <td>${user.username}</td>
                            <td>${user.email}</td>
                            <td>${user.phone || 'N/A'}</td>
                            <td><span class="status-badge ${user.active == 1 ? 'active' : 'inactive'}">${user.active == 1 ? 'Active' : 'Inactive'}</span></td>
                            <td>${new Date(user.created_at).toLocaleDateString()}</td>
                            <td class="action-buttons">
                                <button class="btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete" title="Deactivate"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No users found for this role.</td></tr>`;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Failed to load users: ${error.message}</td></tr>`;
                showNotification(error.message, 'error');
            }
        };
        
        document.getElementById('user-table-body').addEventListener('click', async (e) => {
            const editBtn = e.target.closest('.btn-edit');
            const deleteBtn = e.target.closest('.btn-delete');
            
            if (editBtn) {
                const user = JSON.parse(editBtn.closest('tr').dataset.user);
                openUserModal('edit', user);
            }
            
            if (deleteBtn) {
                const user = JSON.parse(deleteBtn.closest('tr').dataset.user);
                const confirmed = await showConfirmation('Deactivate User', `Are you sure you want to deactivate ${user.username}?`);
                if (confirmed) {
                    const formData = new FormData();
                    formData.append('action', 'deleteUser');
                    formData.append('id', user.id);
                    formData.append('csrf_token', csrfToken);
                    handleFormSubmit(formData, `users-${currentRole}`);
                }
            }
        });

        const handleFormSubmit = async (formData, refreshTarget = null) => {
            try {
                const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    if (formData.get('action') === 'addUser' || formData.get('action') === 'updateUser') closeModal(userModal);
                    else if (formData.get('action').toLowerCase().includes('medicine')) closeModal(medicineModal);
                    else if (formData.get('action').toLowerCase().includes('blood')) closeModal(bloodModal);
                    else if (formData.get('action').toLowerCase().includes('ward')) closeModal(wardFormModal);
                    else if (formData.get('action').toLowerCase().includes('bed')) closeModal(bedModal);
                    else if (formData.get('action').toLowerCase().includes('room')) closeModal(document.getElementById('room-modal'));

                    if (refreshTarget) {
                        if (refreshTarget.startsWith('users-')) fetchUsers(refreshTarget.split('-')[1]);
                        else if (refreshTarget === 'blood') fetchBloodInventory();
                        else if (refreshTarget === 'medicine') fetchMedicineInventory();
                        else if (refreshTarget === 'wards') { fetchWards(); }
                        else if (refreshTarget === 'beds') fetchWardsAndBeds();
                        else if (refreshTarget === 'rooms') fetchRooms();
                    }
                    updateDashboardStats();
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                console.error('Submit error:', error);
                showNotification(error.message, 'error');
            }
        };

        userForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(userForm);
            handleFormSubmit(formData, `users-${currentRole}`);
        });
        
        // --- ADMIN PROFILE EDIT ---
        const profileForm = document.getElementById('profile-form');

        const fetchMyProfile = async () => {
             try {
                const response = await fetch(`?fetch=my_profile`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                const profile = result.data;
                document.getElementById('profile-name').value = profile.name || '';
                document.getElementById('profile-email').value = profile.email || '';
                document.getElementById('profile-phone').value = profile.phone || '';
                document.getElementById('profile-username').value = profile.username || '';
            } catch (error) {
                showNotification('Could not load your profile data.', 'error');
            }
        };

        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(profileForm);
            try {
                const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    document.getElementById('welcome-message').textContent = `Hello, ${formData.get('name')}!`;
                    document.querySelector('.user-profile-widget .user-info strong').textContent = formData.get('name');
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                console.error('Profile update error:', error);
                showNotification(error.message, 'error');
            }
        });
        
        // --- INVENTORY MANAGEMENT ---

        // Medicine Inventory
        const medicineModal = document.getElementById('medicine-modal');
        const medicineForm = document.getElementById('medicine-form');
        const addMedicineBtn = document.getElementById('add-medicine-btn');
        const medicineTableBody = document.getElementById('medicine-table-body');

        const openMedicineModal = (mode, medicine = {}) => {
            medicineForm.reset();
            if (mode === 'add') {
                document.getElementById('medicine-modal-title').textContent = 'Add New Medicine';
                document.getElementById('medicine-form-action').value = 'addMedicine';
                document.getElementById('medicine-low-stock-threshold').value = 10;
            } else {
                document.getElementById('medicine-modal-title').textContent = `Edit ${medicine.name}`;
                document.getElementById('medicine-form-action').value = 'updateMedicine';
                document.getElementById('medicine-id').value = medicine.id;
                document.getElementById('medicine-name').value = medicine.name;
                document.getElementById('medicine-description').value = medicine.description || '';
                document.getElementById('medicine-quantity').value = medicine.quantity;
                document.getElementById('medicine-unit-price').value = medicine.unit_price;
                document.getElementById('medicine-low-stock-threshold').value = medicine.low_stock_threshold;
            }
            medicineModal.classList.add('show');
        };

        addMedicineBtn.addEventListener('click', () => openMedicineModal('add'));
        medicineModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(medicineModal));
        medicineModal.addEventListener('click', (e) => { if (e.target === medicineModal) closeModal(medicineModal); });

        medicineForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmit(new FormData(medicineForm), 'medicine');
        });

        const fetchMedicineInventory = async () => {
            medicineTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Loading...</td></tr>`;
            try {
                const response = await fetch('?fetch=medicines');
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                if (result.data.length > 0) {
                    medicineTableBody.innerHTML = result.data.map(med => {
                        const isLowStock = parseInt(med.quantity) <= parseInt(med.low_stock_threshold);
                        const statusClass = isLowStock ? 'low-stock' : 'in-stock';
                        const quantityClass = isLowStock ? 'quantity-low' : 'quantity-good';
                        return `
                        <tr data-medicine='${JSON.stringify(med)}'>
                            <td>${med.name}</td>
                            <td>${med.description || 'N/A'}</td>
                            <td><span class="${quantityClass}">${med.quantity}</span></td>
                            <td><span class="status-badge ${statusClass}">${isLowStock ? 'Low Stock' : 'In Stock'}</span></td>
                            <td>₹ ${parseFloat(med.unit_price).toFixed(2)}</td>
                            <td>${med.low_stock_threshold}</td>
                            <td>${new Date(med.updated_at).toLocaleString()}</td>
                            <td class="action-buttons">
                                <button class="btn-edit-medicine btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-medicine btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    `}).join('');
                } else {
                    medicineTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No medicines found.</td></tr>`;
                }
            } catch (error) {
                medicineTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Failed to load medicines: ${error.message}</td></tr>`;
            }
        };

        medicineTableBody.addEventListener('click', async (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const medicine = JSON.parse(row.dataset.medicine);
            if (e.target.closest('.btn-edit-medicine')) {
                openMedicineModal('edit', medicine);
            }
            if (e.target.closest('.btn-delete-medicine')) {
                const confirmed = await showConfirmation('Delete Medicine', `Are you sure you want to delete ${medicine.name}?`);
                if (confirmed) {
                    const formData = new FormData();
                    formData.append('action', 'deleteMedicine');
                    formData.append('id', medicine.id);
                    formData.append('csrf_token', csrfToken);
                    handleFormSubmit(formData, 'medicine');
                }
            }
        });

        // Blood Inventory
        const bloodModal = document.getElementById('blood-modal');
        const bloodForm = document.getElementById('blood-form');
        const addBloodBtn = document.getElementById('add-blood-btn');
        const bloodTableBody = document.getElementById('blood-table-body');

        const openBloodModal = (blood = {}) => {
            bloodForm.reset();
            document.getElementById('blood-modal-title').textContent = `Update Blood Unit`;
            document.getElementById('blood-group').value = blood.blood_group || 'A+';
            document.getElementById('blood-group').disabled = !!blood.blood_group;
            document.getElementById('blood-quantity-ml').value = blood.quantity_ml || 0;
            document.getElementById('blood-low-stock-threshold-ml').value = blood.low_stock_threshold_ml || 5000;
            bloodModal.classList.add('show');
        };

        addBloodBtn.addEventListener('click', () => openBloodModal());
        bloodModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(bloodModal));
        bloodModal.addEventListener('click', (e) => { if (e.target === bloodModal) closeModal(bloodModal); });

        bloodForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(bloodForm);
            if (document.getElementById('blood-group').disabled) {
                formData.set('blood_group', document.getElementById('blood-group').value);
            }
            handleFormSubmit(formData, 'blood');
        });

        const fetchBloodInventory = async () => {
            bloodTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Loading...</td></tr>`;
            try {
                const response = await fetch('?fetch=blood_inventory');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                if (result.data.length > 0) {
                    bloodTableBody.innerHTML = result.data.map(blood => {
                        const isLowStock = parseInt(blood.quantity_ml) < parseInt(blood.low_stock_threshold_ml);
                        const statusClass = isLowStock ? 'low-stock' : 'in-stock';
                        const quantityClass = isLowStock ? 'quantity-low' : 'quantity-good';
                        return `
                        <tr data-blood='${JSON.stringify(blood)}'>
                            <td>${blood.blood_group}</td>
                            <td><span class="${quantityClass}">${blood.quantity_ml}</span> ml</td>
                            <td><span class="status-badge ${statusClass}">${isLowStock ? 'Low Stock' : 'In Stock'}</span></td>
                            <td>${blood.low_stock_threshold_ml} ml</td>
                            <td>${new Date(blood.last_updated).toLocaleString()}</td>
                            <td class="action-buttons">
                                <button class="btn-edit-blood btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                    `}).join('');
                } else {
                    bloodTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No blood inventory records found.</td></tr>`;
                }
            } catch (error) {
                bloodTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Failed to load blood inventory.</td></tr>`;
            }
        };

        bloodTableBody.addEventListener('click', async (e) => {
            if (e.target.closest('.btn-edit-blood')) {
                const blood = JSON.parse(e.target.closest('tr').dataset.blood);
                openBloodModal(blood);
            }
        });

        // --- Ward Management ---
        const addWardBtn = document.getElementById('add-ward-btn');
        const wardFormModal = document.getElementById('ward-form-modal');
        const wardForm = document.getElementById('ward-form');
        const wardTableBody = document.getElementById('ward-table-body');
        
        const openWardForm = (mode, ward = {}) => {
            wardForm.reset();
            wardFormModal.querySelector('#ward-form-modal-title').textContent = mode === 'add' ? 'Add New Ward' : `Edit ${ward.name}`;
            wardForm.querySelector('#ward-form-action').value = mode === 'add' ? 'addWard' : 'updateWard';
            const activeGroup = wardForm.querySelector('#ward-active-group');
            
            if (mode === 'edit') {
                wardForm.querySelector('#ward-id-input').value = ward.id;
                wardForm.querySelector('#ward-name-input').value = ward.name;
                wardForm.querySelector('#ward-capacity-input').value = ward.capacity;
                wardForm.querySelector('#ward-description-input').value = ward.description || '';
                wardForm.querySelector('#ward-is-active-input').value = ward.is_active;
                activeGroup.style.display = 'block';
            } else {
                activeGroup.style.display = 'none';
            }
            wardFormModal.classList.add('show');
        };

        addWardBtn.addEventListener('click', () => openWardForm('add'));
        wardFormModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(wardFormModal));
        
        wardForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmit(new FormData(wardForm), 'wards');
        });

        const fetchWards = async () => {
            wardTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Loading...</td></tr>`;
            try {
                const response = await fetch('?fetch=wards');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                if (result.data.length > 0) {
                    wardTableBody.innerHTML = result.data.map(ward => `
                        <tr data-ward='${JSON.stringify(ward)}'>
                            <td>${ward.name}</td>
                            <td>${ward.capacity}</td>
                            <td>${ward.description || 'N/A'}</td>
                            <td><span class="status-badge ${ward.is_active == 1 ? 'active' : 'inactive'}">${ward.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                            <td class="action-buttons">
                                <button class="btn-edit-ward btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-ward btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    wardTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No wards found.</td></tr>`;
                }
            } catch (error) {
                wardTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Failed to load wards.</td></tr>`;
            }
        };

        wardTableBody.addEventListener('click', async (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const ward = JSON.parse(row.dataset.ward);
            if (e.target.closest('.btn-edit-ward')) {
                openWardForm('edit', ward);
            }
            if (e.target.closest('.btn-delete-ward')) {
                const confirmed = await showConfirmation('Delete Ward', `Are you sure you want to delete ward "${ward.name}"?`);
                if (confirmed) {
                    const formData = new FormData();
                    formData.append('action', 'deleteWard');
                    formData.append('id', ward.id);
                    formData.append('csrf_token', csrfToken);
                    handleFormSubmit(formData, 'wards');
                }
            }
        });

        // --- Bed Management ---
        const bedModal = document.getElementById('bed-modal');
        const bedForm = document.getElementById('bed-form');
        const addBedBtn = document.getElementById('add-bed-btn');
        const bedsContainer = document.getElementById('beds-container');
        const bedPatientGroup = document.getElementById('bed-patient-group');
        const bedStatusSelect = document.getElementById('bed-status');
        const bedPatientSelect = document.getElementById('bed-patient-id');

        const populateBedDropdowns = async () => {
            try {
                const [wardsRes, patientsRes] = await Promise.all([fetch('?fetch=wards'), fetch('?fetch=patients_for_beds')]);
                const wardsResult = await wardsRes.json();
                const patientsResult = await patientsRes.json();
                const wardSelect = document.getElementById('bed-ward-id');
                
                wardSelect.innerHTML = '<option value="">Select Ward</option>';
                if(wardsResult.success) {
                    wardsResult.data.forEach(ward => wardSelect.innerHTML += `<option value="${ward.id}">${ward.name}</option>`);
                }

                bedPatientSelect.innerHTML = '<option value="">Select Patient</option>';
                if(patientsResult.success) {
                    patientsResult.data.forEach(patient => bedPatientSelect.innerHTML += `<option value="${patient.id}">${patient.name} (${patient.display_user_id})</option>`);
                }
            } catch (error) {
                console.error('Failed to populate dropdowns:', error);
            }
        };

        bedStatusSelect.addEventListener('change', () => {
            const showPatient = bedStatusSelect.value === 'occupied' || bedStatusSelect.value === 'reserved';
            bedPatientGroup.style.display = showPatient ? 'block' : 'none';
            bedPatientSelect.required = showPatient;
        });

        const openBedModal = async (mode, bed = {}) => {
            bedForm.reset();
            await populateBedDropdowns();
            document.getElementById('bed-modal-title').textContent = mode === 'add' ? 'Add New Bed' : `Edit Bed ${bed.bed_number}`;
            document.getElementById('bed-form-action').value = mode === 'add' ? 'addBed' : 'updateBed';
            bedPatientGroup.style.display = 'none';
            bedPatientSelect.required = false;

            document.getElementById('bed-number').readOnly = false;
            document.getElementById('bed-ward-id').disabled = false;

            if (mode === 'edit') {
                document.getElementById('bed-id').value = bed.id;
                setTimeout(() => { 
                    document.getElementById('bed-ward-id').value = bed.ward_id;
                    document.getElementById('bed-number').value = bed.bed_number;
                    document.getElementById('bed-status').value = bed.status;
                    const showPatient = bed.status === 'occupied' || bed.status === 'reserved';
                    if (showPatient) {
                        bedPatientGroup.style.display = 'block';
                        bedPatientSelect.required = true;
                        document.getElementById('bed-patient-id').value = bed.patient_id || '';
                    }
                }, 100);
            }
            bedModal.classList.add('show');
        };

        addBedBtn.addEventListener('click', () => openBedModal('add'));
        bedModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(bedModal));
        bedModal.addEventListener('click', (e) => { if (e.target === bedModal) closeModal(bedModal); });

        bedForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmit(new FormData(bedForm), 'beds');
        });

        const fetchWardsAndBeds = async () => {
            bedsContainer.innerHTML = `<p style="text-align:center;">Loading beds...</p>`;
            try {
                const response = await fetch('?fetch=beds');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                const bedsByWard = result.data.reduce((acc, bed) => {
                    (acc[bed.ward_name] = acc[bed.ward_name] || []).push(bed);
                    return acc;
                }, {});

                if (Object.keys(bedsByWard).length > 0) {
                    bedsContainer.innerHTML = Object.entries(bedsByWard).map(([wardName, beds]) => `
                        <div class="ward-section">
                            <div class="ward-header">
                                <h3>${wardName}</h3>
                            </div>
                            <div class="ward-beds-container">
                                ${beds.map(bed => {
                                    let patientInfo = '';
                                    if (bed.status === 'occupied' && bed.patient_name) {
                                        patientInfo = `<div class="patient-info">Occupied by: ${bed.patient_name}<br><small>Since: ${new Date(bed.occupied_since).toLocaleDateString()}</small></div>`;
                                    } else if (bed.status === 'reserved' && bed.patient_name) {
                                        patientInfo = `<div class="patient-info">Reserved for: ${bed.patient_name}<br><small>Since: ${new Date(bed.reserved_since).toLocaleDateString()}</small></div>`;
                                    }
                                    return `
                                    <div class="bed-card ${bed.status}" data-bed='${JSON.stringify(bed)}'>
                                        <div class="bed-icon"><i class="fas fa-bed"></i></div>
                                        <div class="bed-number">Bed ${bed.bed_number}</div>
                                        <div class="bed-status">${bed.status}</div>
                                        ${patientInfo}
                                        <div class="action-buttons">
                                            <button class="btn-edit-bed btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                            <button class="btn-delete-bed btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                        </div>
                                    </div>
                                `}).join('')}
                            </div>
                        </div>
                    `).join('');
                } else {
                    bedsContainer.innerHTML = `<p style="text-align:center;">No beds found. Add wards and beds to get started.</p>`;
                }
            } catch (error) {
                bedsContainer.innerHTML = `<p style="text-align:center;">Failed to load beds: ${error.message}</p>`;
            }
        };

        bedsContainer.addEventListener('click', async (e) => {
            const bedCard = e.target.closest('.bed-card');
            if (!bedCard) return;

            const bed = JSON.parse(bedCard.dataset.bed);
            if (e.target.closest('.btn-edit-bed')) {
                openBedModal('edit', bed);
            }
            if (e.target.closest('.btn-delete-bed')) {
                const confirmed = await showConfirmation('Delete Bed', `Are you sure you want to delete Bed ${bed.bed_number} in ${bed.ward_name}?`);
                if (confirmed) {
                    const formData = new FormData();
                    formData.append('action', 'deleteBed');
                    formData.append('id', bed.id);
                    formData.append('csrf_token', csrfToken);
                    handleFormSubmit(formData, 'beds');
                }
            }
        });

        // --- Room Management ---
        const roomModal = document.getElementById('room-modal');
        const roomForm = document.getElementById('room-form');
        const addRoomBtn = document.getElementById('add-room-btn');
        const roomsContainer = document.getElementById('rooms-container');
        const roomPatientGroup = document.getElementById('room-patient-group');
        const roomStatusSelect = document.getElementById('room-status');
        const roomPatientSelect = document.getElementById('room-patient-id');

        const populateRoomDropdowns = async () => {
             try {
                const response = await fetch('?fetch=patients_for_beds'); // Reusing the same patient fetcher
                const result = await response.json();
                
                roomPatientSelect.innerHTML = '<option value="">Select Patient</option>';
                if(result.success) {
                    result.data.forEach(patient => roomPatientSelect.innerHTML += `<option value="${patient.id}">${patient.name} (${patient.display_user_id})</option>`);
                }
            } catch (error) {
                console.error('Failed to populate patient dropdown for rooms:', error);
            }
        };

        roomStatusSelect.addEventListener('change', () => {
            const showPatient = roomStatusSelect.value === 'occupied' || roomStatusSelect.value === 'reserved';
            roomPatientGroup.style.display = showPatient ? 'block' : 'none';
            roomPatientSelect.required = showPatient;
        });

        const openRoomModal = async (mode, room = {}) => {
            roomForm.reset();
            await populateRoomDropdowns();
            document.getElementById('room-modal-title').textContent = mode === 'add' ? 'Add New Room' : `Edit Room ${room.room_number}`;
            document.getElementById('room-form-action').value = mode === 'add' ? 'addRoom' : 'updateRoom';
            roomPatientGroup.style.display = 'none';
            roomPatientSelect.required = false;
            
            document.getElementById('room-number').readOnly = false;

            if (mode === 'edit') {
                document.getElementById('room-id').value = room.id;
                document.getElementById('room-number').value = room.room_number;
                document.getElementById('room-price-per-day').value = room.price_per_day;
                document.getElementById('room-status').value = room.status;
                const showPatient = room.status === 'occupied' || room.status === 'reserved';
                if (showPatient) {
                    roomPatientGroup.style.display = 'block';
                    roomPatientSelect.required = true;
                    document.getElementById('room-patient-id').value = room.patient_id || '';
                }
            } else {
                 document.getElementById('room-price-per-day').value = '0.00';
            }
            roomModal.classList.add('show');
        };

        addRoomBtn.addEventListener('click', () => openRoomModal('add'));
        roomModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(roomModal));
        roomModal.addEventListener('click', (e) => { if (e.target === roomModal) closeModal(roomModal); });

        roomForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmit(new FormData(roomForm), 'rooms');
        });

        const fetchRooms = async () => {
            roomsContainer.innerHTML = `<p style="text-align:center;">Loading rooms...</p>`;
            try {
                const response = await fetch('?fetch=rooms');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                if (result.data.length > 0) {
                    roomsContainer.innerHTML = result.data.map(room => {
                        let patientInfo = '';
                        if (room.status === 'occupied' && room.patient_name) {
                            patientInfo = `<div class="patient-info">Occupied by: ${room.patient_name}<br><small>Since: ${new Date(room.occupied_since).toLocaleDateString()}</small></div>`;
                        } else if (room.status === 'reserved' && room.patient_name) {
                            patientInfo = `<div class="patient-info">Reserved for: ${room.patient_name}<br><small>Since: ${new Date(room.reserved_since).toLocaleDateString()}</small></div>`;
                        }
                        return `
                        <div class="room-card ${room.status}" data-room='${JSON.stringify(room)}'>
                            <div class="room-icon"><i class="fas fa-door-closed"></i></div>
                            <div class="room-number">Room ${room.room_number}</div>
                            <div class="room-status">${room.status}</div>
                            ${patientInfo}
                            <div class="action-buttons">
                                <button class="btn-edit-room btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-room btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    `}).join('');
                } else {
                    roomsContainer.innerHTML = `<p style="text-align:center;">No rooms found. Add some to get started.</p>`;
                }
            } catch (error) {
                roomsContainer.innerHTML = `<p style="text-align:center;">Failed to load rooms: ${error.message}</p>`;
            }
        };

        roomsContainer.addEventListener('click', async (e) => {
            const roomCard = e.target.closest('.room-card');
            if (!roomCard) return;

            const room = JSON.parse(roomCard.dataset.room);
            if (e.target.closest('.btn-edit-room')) {
                openRoomModal('edit', room);
            }
            if (e.target.closest('.btn-delete-room')) {
                const confirmed = await showConfirmation('Delete Room', `Are you sure you want to delete Room ${room.room_number}?`);
                if (confirmed) {
                    const formData = new FormData();
                    formData.append('action', 'deleteRoom');
                    formData.append('id', room.id);
                    formData.append('csrf_token', csrfToken);
                    handleFormSubmit(formData, 'rooms');
                }
            }
        });


        // --- INITIAL LOAD ---
        updateDashboardStats();
        fetchDepartments();
    });
    </script>
</body>
</html>