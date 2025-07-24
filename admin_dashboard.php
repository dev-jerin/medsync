<?php
// --- CONFIG & SESSION START ---
require_once 'config.php';
require_once 'vendor/autoload.php'; // Autoload Composer dependencies

use Dompdf\Dompdf;
use Dompdf\Options;

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
 * Logs a specific action to the activity_logs table.
 *
 * @param mysqli $conn The database connection object.
 * @param int $user_id The ID of the user performing the action (the admin).
 * @param string $action A description of the action (e.g., 'create_user', 'update_user').
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
    set_error_handler(function ($severity, $message, $file, $line) {
        if (!(error_reporting() & $severity)) {
            return;
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];
    $admin_user_id_for_log = $_SESSION['user_id'];

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
                        if (!$email)
                            throw new Exception('Invalid email format.');

                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $role = $_POST['role'];
                        $phone = $_POST['phone'];
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;

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

                        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->bind_param("ss", $username, $email);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            throw new Exception('Username or email already exists.');
                        }

                        $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role, gender, phone, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssssss", $display_user_id, $name, $username, $email, $password, $role, $gender, $phone, $profile_picture);
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
                        }

                        // --- Audit Log ---
                        $log_details = "Created a new user '{$username}' (ID: {$display_user_id}) with the role '{$role}'.";
                        if ($profile_picture !== 'default.png') {
                            $log_details .= " Profile picture was added.";
                        }
                        log_activity($conn, $admin_user_id_for_log, 'create_user', $user_id, $log_details);
                        // --- End Audit Log ---

                        $conn->commit();
                        $response = ['success' => true, 'message' => ucfirst($role) . ' added successfully.'];

                    } catch (Exception $e) {
                        $conn->rollback();
                        throw new Exception('Database error on user creation: ' . $e->getMessage());
                    }
                    break;

                case 'updateSystemSettings':
                    $conn->begin_transaction();
                    try {
                        $changes_logged = [];
                        // Handle System Email
                        if (isset($_POST['system_email']) && !empty($_POST['system_email'])) {
                            $new_email = filter_var($_POST['system_email'], FILTER_VALIDATE_EMAIL);
                            if (!$new_email) {
                                throw new Exception('Invalid email format provided.');
                            }
                            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('system_email', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                            $stmt->bind_param("ss", $new_email, $new_email);
                            $stmt->execute();
                            $changes_logged[] = "System Email";
                        }

                        // Handle Gmail App Password
                        if (isset($_POST['gmail_app_password']) && !empty($_POST['gmail_app_password'])) {
                            $new_password = $_POST['gmail_app_password'];
                            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('gmail_app_password', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                            $stmt->bind_param("ss", $new_password, $new_password);
                            $stmt->execute();
                            $changes_logged[] = "Gmail App Password";
                        }

                        if (!empty($changes_logged)) {
                            log_activity($conn, $admin_user_id_for_log, 'update_system_settings', null, 'Updated system settings: ' . implode(', ', $changes_logged) . '.');
                            $response = ['success' => true, 'message' => 'System settings updated successfully.'];
                        } else {
                            $response = ['success' => true, 'message' => 'No changes were made.'];
                        }
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e; // Rethrow the exception to be caught by the main handler
                    }
                    break;

                case 'updateUser':
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email'])) {
                            throw new Exception('Invalid data provided.');
                        }
                        $id = (int) $_POST['id'];
                        $name = $_POST['name'];
                        $username = $_POST['username'];
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                        if (!$email)
                            throw new Exception('Invalid email format.');
                        $phone = $_POST['phone'];
                        $active = isset($_POST['active']) ? (int) $_POST['active'] : 1;
                        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;

                        // --- Audit Log: Fetch current state ---
                        $stmt_old = $conn->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt_old->bind_param("i", $id);
                        $stmt_old->execute();
                        $old_user_data = $stmt_old->get_result()->fetch_assoc();
                        // --- End Audit Log Fetch ---

                        $sql_parts = ["name = ?", "username = ?", "email = ?", "phone = ?", "active = ?", "date_of_birth = ?", "gender = ?"];
                        $params = [$name, $username, $email, $phone, $active, $date_of_birth, $gender];
                        $types = "ssssiss";

                        $changes = [];

                        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
                            // --- Delete old profile picture ---
                            if ($old_user_data && $old_user_data['profile_picture'] !== 'default.png') {
                                $old_pfp_path = "uploads/profile_pictures/" . $old_user_data['profile_picture'];
                                if (file_exists($old_pfp_path)) {
                                    unlink($old_pfp_path);
                                }
                            }
                            // --- End delete old picture ---

                            $changes[] = "profile picture";

                            $target_dir = "uploads/profile_pictures/";

                            $target_dir = "uploads/profile_pictures/";
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            $image_name = uniqid() . '_' . basename($_FILES["profile_picture"]["name"]);
                            $target_file = $target_dir . $image_name;
                            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                                $sql_parts[] = "profile_picture = ?";
                                $params[] = $image_name;
                                $types .= "s";
                            }
                        }

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

                        if ($old_user_data['role'] === 'doctor') {
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
                        } elseif ($old_user_data['role'] === 'staff') {
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

                        // --- Audit Log: Compare and log changes ---
                        if ($old_user_data['name'] !== $name)
                            $changes[] = "name from '{$old_user_data['name']}' to '{$name}'";
                        if ($old_user_data['username'] !== $username)
                            $changes[] = "username from '{$old_user_data['username']}' to '{$username}'";
                        if ($old_user_data['email'] !== $email)
                            $changes[] = "email from '{$old_user_data['email']}' to '{$email}'";
                        if ($old_user_data['phone'] !== $phone)
                            $changes[] = "phone number";
                        if ($old_user_data['active'] != $active)
                            $changes[] = "status from " . ($old_user_data['active'] ? "'Active'" : "'Inactive'") . " to " . ($active ? "'Active'" : "'Inactive'");
                        if (!empty($_POST['password']))
                            $changes[] = "password";

                        if (!empty($changes)) {
                            $log_details = "Updated user '{$username}': changed " . implode(', ', $changes) . ".";
                            log_activity($conn, $admin_user_id_for_log, 'update_user', $id, $log_details);
                        }
                        // --- End Audit Log ---

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
                    if (!$email)
                        throw new Exception('Invalid email format.');
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
                        log_activity($conn, $admin_user_id_for_log, 'update_own_profile', $id, 'Admin updated their own profile details.');
                        $response = ['success' => true, 'message' => 'Your profile has been updated successfully.'];
                    } else {
                        throw new Exception('Failed to update your profile.');
                    }
                    break;

                case 'deleteUser':
                    if (empty($_POST['id'])) {
                        throw new Exception('Invalid user ID.');
                    }
                    $id = (int) $_POST['id'];
                    // --- Audit Log: Fetch user info before deactivating ---
                    $stmt_old = $conn->prepare("SELECT username, display_user_id FROM users WHERE id = ?");
                    $stmt_old->bind_param("i", $id);
                    $stmt_old->execute();
                    $old_user_data = $stmt_old->get_result()->fetch_assoc();
                    // --- End Audit Log Fetch ---

                    $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $log_details = "Deactivated user '{$old_user_data['username']}' (ID: {$old_user_data['display_user_id']}).";
                        log_activity($conn, $admin_user_id_for_log, 'deactivate_user', $id, $log_details);
                        $response = ['success' => true, 'message' => 'User deactivated successfully.'];
                    } else {
                        throw new Exception('Failed to deactivate user.');
                    }
                    break;

                case 'removeProfilePicture':
                    if (empty($_POST['id'])) {
                        throw new Exception('Invalid user ID.');
                    }
                    $id = (int) $_POST['id'];

                    // Fetch user info before updating
                    $stmt_old = $conn->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
                    $stmt_old->bind_param("i", $id);
                    $stmt_old->execute();
                    $user_data = $stmt_old->get_result()->fetch_assoc();

                    if ($user_data && $user_data['profile_picture'] !== 'default.png') {
                        $pfp_path = "uploads/profile_pictures/" . $user_data['profile_picture'];
                        if (file_exists($pfp_path)) {
                            unlink($pfp_path);
                        }
                    }

                    $stmt = $conn->prepare("UPDATE users SET profile_picture = 'default.png' WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $log_details = "Removed profile picture for user '{$user_data['username']}'.";
                        log_activity($conn, $admin_user_id_for_log, 'update_user', $id, $log_details);
                        $response = ['success' => true, 'message' => 'Profile picture removed successfully.'];
                    } else {
                        throw new Exception('Failed to remove profile picture.');
                    }
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
                        $log_details = "Added new medicine '{$name}' with quantity {$quantity}.";
                        log_activity($conn, $admin_user_id_for_log, 'add_medicine', null, $log_details);
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
                        $log_details = "Updated medicine '{$name}' (ID: {$id}). New quantity: {$quantity}.";
                        log_activity($conn, $admin_user_id_for_log, 'update_medicine', null, $log_details);
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

                    // --- Audit Log: Fetch medicine name before delete ---
                    $stmt_med = $conn->prepare("SELECT name FROM medicines WHERE id = ?");
                    $stmt_med->bind_param("i", $id);
                    $stmt_med->execute();
                    $med_data = $stmt_med->get_result()->fetch_assoc();
                    $med_name = $med_data ? $med_data['name'] : 'Unknown';
                    // --- End Fetch ---

                    $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $log_details = "Deleted medicine '{$med_name}' (ID: {$id}).";
                        log_activity($conn, $admin_user_id_for_log, 'delete_medicine', null, $log_details);
                        $response = ['success' => true, 'message' => 'Medicine deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete medicine.');
                    }
                    break;

                case 'addDepartment':
                    if (empty($_POST['name'])) {
                        throw new Exception('Department name is required.');
                    }
                    $name = $_POST['name'];
                    $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
                    $stmt->bind_param("s", $name);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'create_department', null, "Created new department '{$name}'.");
                        $response = ['success' => true, 'message' => 'Department added successfully.'];
                    } else {
                        throw new Exception('Failed to add department. It might already exist.');
                    }
                    break;

                case 'updateDepartment':
                    if (empty($_POST['id']) || empty($_POST['name'])) {
                        throw new Exception('Department ID and name are required.');
                    }
                    $id = (int) $_POST['id'];
                    $name = $_POST['name'];
                    $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

                    $stmt = $conn->prepare("UPDATE departments SET name = ?, is_active = ? WHERE id = ?");
                    $stmt->bind_param("sii", $name, $is_active, $id);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'update_department', null, "Updated department ID {$id} to name '{$name}' and status " . ($is_active ? 'Active' : 'Inactive'));
                        $response = ['success' => true, 'message' => 'Department updated successfully.'];
                    } else {
                        throw new Exception('Failed to update department.');
                    }
                    break;

                case 'deleteDepartment': // This will be a soft delete by setting is_active to 0
                    if (empty($_POST['id'])) {
                        throw new Exception('Department ID is required.');
                    }
                    $id = (int) $_POST['id'];
                    $stmt = $conn->prepare("UPDATE departments SET is_active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'deactivate_department', null, "Deactivated department ID {$id}.");
                        $response = ['success' => true, 'message' => 'Department disabled successfully.'];
                    } else {
                        throw new Exception('Failed to disable department.');
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
                        $log_details = "Updated blood inventory for group '{$blood_group}' to {$quantity_ml} ml.";
                        log_activity($conn, $admin_user_id_for_log, 'update_blood_inventory', null, $log_details);
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
                    $capacity = (int) $_POST['capacity'];
                    $description = $_POST['description'] ?? null;
                    $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

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
                    $id = (int) $_POST['id'];
                    $name = $_POST['name'];
                    $capacity = (int) $_POST['capacity'];
                    $description = $_POST['description'] ?? null;
                    $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

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
                    $id = (int) $_POST['id'];
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
                    $ward_id = (int) $_POST['ward_id'];
                    $bed_number = $_POST['bed_number'];
                    $status = $_POST['status'] ?? 'available';
                    $patient_id = !empty($_POST['patient_id']) ? (int) $_POST['patient_id'] : null;
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
                    $id = (int) $_POST['id'];
                    $ward_id = (int) $_POST['ward_id'];
                    $bed_number = $_POST['bed_number'];
                    $new_status = $_POST['status'];
                    $new_patient_id = !empty($_POST['patient_id']) ? (int) $_POST['patient_id'] : null;
                    $new_doctor_id = !empty($_POST['doctor_id']) ? (int) $_POST['doctor_id'] : null;

                    $conn->begin_transaction();
                    try {
                        // ... (keep the existing admission/discharge logic here) ...
                        $stmt_current = $conn->prepare("SELECT status, patient_id FROM beds WHERE id = ? FOR UPDATE");
                        $stmt_current->bind_param("i", $id);
                        $stmt_current->execute();
                        $current_bed = $stmt_current->get_result()->fetch_assoc();

                        if ($current_bed) {
                            $old_status = $current_bed['status'];
                            $old_patient_id = $current_bed['patient_id'];

                            if ($old_status === 'occupied' && $new_status !== 'occupied' && $old_patient_id) {
                                $stmt_discharge = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE patient_id = ? AND bed_id = ? AND discharge_date IS NULL");
                                $stmt_discharge->bind_param("ii", $old_patient_id, $id);
                                $stmt_discharge->execute();
                            }

                            if ($new_status === 'occupied' && $old_status !== 'occupied' && $new_patient_id) {
                                $stmt_admit = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, ward_id, bed_id, admission_date) VALUES (?, ?, ?, ?, NOW())");
                                $stmt_admit->bind_param("iiii", $new_patient_id, $new_doctor_id, $ward_id, $id);
                                $stmt_admit->execute();
                            }
                        }

                        $occupied_since = ($new_status === 'occupied') ? date('Y-m-d H:i:s') : null;
                        $reserved_since = ($new_status === 'reserved') ? date('Y-m-d H:i:s') : null;
                        $patient_id_to_set = ($new_status === 'occupied' || $new_status === 'reserved') ? $new_patient_id : null;
                        $doctor_id_to_set = ($new_status === 'occupied') ? $new_doctor_id : null; // Only set doctor if occupied

                        $stmt = $conn->prepare("UPDATE beds SET ward_id = ?, bed_number = ?, status = ?, patient_id = ?, doctor_id = ?, occupied_since = ?, reserved_since = ? WHERE id = ?");
                        $stmt->bind_param("ississsi", $ward_id, $bed_number, $new_status, $patient_id_to_set, $doctor_id_to_set, $occupied_since, $reserved_since, $id);
                        $stmt->execute();

                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Bed updated successfully.'];
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'deleteBed':
                    if (empty($_POST['id'])) {
                        throw new Exception('Bed ID is required.');
                    }
                    $id = (int) $_POST['id'];
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
                    $patient_id = !empty($_POST['patient_id']) ? (int) $_POST['patient_id'] : null;
                    $price_per_day = (float) $_POST['price_per_day'];
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
                    $id = (int) $_POST['id'];
                    $room_number = $_POST['room_number'];
                    $new_status = $_POST['status'];
                    $new_patient_id = !empty($_POST['patient_id']) ? (int) $_POST['patient_id'] : null;
                    $new_doctor_id = !empty($_POST['doctor_id']) ? (int) $_POST['doctor_id'] : null;
                    $price_per_day = (float) $_POST['price_per_day'];

                    $conn->begin_transaction();
                    try {
                        // ... (keep the existing admission/discharge logic here) ...
                        $stmt_current = $conn->prepare("SELECT status, patient_id FROM rooms WHERE id = ? FOR UPDATE");
                        $stmt_current->bind_param("i", $id);
                        $stmt_current->execute();
                        $current_room = $stmt_current->get_result()->fetch_assoc();

                        if ($current_room) {
                            $old_status = $current_room['status'];
                            $old_patient_id = $current_room['patient_id'];

                            if ($old_status === 'occupied' && $new_status !== 'occupied' && $old_patient_id) {
                                $stmt_discharge = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE patient_id = ? AND room_id = ? AND discharge_date IS NULL");
                                $stmt_discharge->bind_param("ii", $old_patient_id, $id);
                                $stmt_discharge->execute();
                            }

                            if ($new_status === 'occupied' && $old_status !== 'occupied' && $new_patient_id) {
                                $stmt_admit = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, room_id, admission_date) VALUES (?, ?, ?, NOW())");
                                $stmt_admit->bind_param("iii", $new_patient_id, $new_doctor_id, $id);
                                $stmt_admit->execute();
                            }
                        }

                        $occupied_since = ($new_status === 'occupied') ? date('Y-m-d H:i:s') : null;
                        $reserved_since = ($new_status === 'reserved') ? date('Y-m-d H:i:s') : null;
                        $patient_id_to_set = ($new_status === 'occupied' || $new_status === 'reserved') ? $new_patient_id : null;
                        $doctor_id_to_set = ($new_status === 'occupied') ? $new_doctor_id : null;

                        $stmt = $conn->prepare("UPDATE rooms SET room_number = ?, status = ?, patient_id = ?, doctor_id = ?, occupied_since = ?, reserved_since = ?, price_per_day = ? WHERE id = ?");
                        $stmt->bind_param("ssisssdi", $room_number, $new_status, $patient_id_to_set, $doctor_id_to_set, $occupied_since, $reserved_since, $price_per_day, $id);
                        $stmt->execute();

                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Room updated successfully.'];
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'deleteRoom':
                    if (empty($_POST['id'])) {
                        throw new Exception('Room ID is required.');
                    }
                    $id = (int) $_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Room deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete room.');
                    }
                    break;

                case 'update_doctor_schedule':
                    if (empty($_POST['doctor_id']) || !isset($_POST['slots'])) {
                        throw new Exception('Doctor ID and slots data are required.');
                    }
                    $doctor_id = (int) $_POST['doctor_id'];
                    $slots_json = $_POST['slots']; // This will be a JSON string from the frontend

                    $stmt = $conn->prepare("UPDATE doctors SET slots = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $slots_json, $doctor_id);

                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'update_doctor_schedule', $doctor_id, "Updated schedule for doctor ID {$doctor_id}.");
                        $response = ['success' => true, 'message' => 'Doctor schedule updated successfully.'];
                    } else {
                        throw new Exception('Failed to update doctor schedule.');
                    }
                    break;
                case 'sendIndividualNotification':
                    if (empty($_POST['recipient_user_id']) || empty($_POST['message'])) {
                        throw new Exception('Recipient and message are required.');
                    }
                    $recipient_user_id = (int) $_POST['recipient_user_id'];
                    $message = $_POST['message'];
                    $admin_user_id = $_SESSION['user_id'];

                    $sql = "INSERT INTO notifications (sender_id, message, recipient_user_id) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isi", $admin_user_id, $message, $recipient_user_id);

                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'send_notification', $recipient_user_id, "Sent an individual message.");
                        $response = ['success' => true, 'message' => 'Message sent successfully.'];
                    } else {
                        throw new Exception('Failed to send message.');
                    }
                    break;

                case 'sendNotification':
                    if (empty($_POST['role']) || empty($_POST['message'])) {
                        throw new Exception('Role and message are required.');
                    }
                    $role = $_POST['role'];
                    $message = $_POST['message'];
                    $admin_user_id = $_SESSION['user_id'];

                    $sql = "INSERT INTO notifications (sender_id, message, recipient_role) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $admin_user_id, $message, $role);

                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'send_notification', null, "Sent a broadcast message to '{$role}'.");
                        $response = ['success' => true, 'message' => 'Notification sent successfully.'];
                    } else {
                        throw new Exception('Failed to send notification.');
                    }
                    break;

                case 'update_staff_shift':
                    if (empty($_POST['staff_id']) || empty($_POST['shift'])) {
                        throw new Exception('Staff ID and shift are required.');
                    }
                    $staff_id = (int) $_POST['staff_id'];
                    $shift = $_POST['shift'];
                    if (!in_array($shift, ['day', 'night', 'off'])) {
                        throw new Exception('Invalid shift value.');
                    }

                    $stmt = $conn->prepare("UPDATE staff SET shift = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $shift, $staff_id);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'update_staff_shift', $staff_id, "Updated shift to '{$shift}' for staff ID {$staff_id}.");
                        $response = ['success' => true, 'message' => 'Staff shift updated successfully.'];
                    } else {
                        throw new Exception('Failed to update staff shift.');
                    }
                    break;

                case 'mark_notifications_read':
                    $admin_id = $_SESSION['user_id'];
                    // This query updates the is_read flag to 1 for all unread (is_read = 0) notifications for this admin.
                    $sql = "UPDATE notifications SET is_read = 1 WHERE (recipient_user_id = ? OR recipient_role = 'admin' OR recipient_role = 'all') AND is_read = 0";
                    $stmt = $conn->prepare($sql);

                    if ($stmt) {
                        $stmt->bind_param("i", $admin_id);
                        if ($stmt->execute()) {
                            $response = ['success' => true, 'message' => 'Notifications marked as read.'];
                        } else {
                            $response = ['success' => false, 'message' => 'Database update failed during execution.'];
                        }
                        $stmt->close();
                    } else {
                        $response = ['success' => false, 'message' => 'Database statement could not be prepared.'];
                    }
                    break;

                case 'dismiss_all_notifications':
                    $admin_user_id = $_SESSION['user_id'];

                    // First, find all notification IDs that are currently unread/undismissed for this admin
                    $sql_get_ids = "SELECT n.id 
                                    FROM notifications n
                                    LEFT JOIN notification_dismissals nd ON n.id = nd.notification_id AND nd.user_id = ?
                                    WHERE (n.recipient_user_id = ? OR n.recipient_role = 'admin' OR n.recipient_role = 'all')
                                    AND nd.user_id IS NULL";
                    $stmt_get_ids = $conn->prepare($sql_get_ids);
                    $stmt_get_ids->bind_param("ii", $admin_user_id, $admin_user_id);
                    $stmt_get_ids->execute();
                    $result = $stmt_get_ids->get_result();
                    $notification_ids = [];
                    while ($row = $result->fetch_assoc()) {
                        $notification_ids[] = $row['id'];
                    }
                    $stmt_get_ids->close();

                    if (!empty($notification_ids)) {
                        // Now, insert a dismissal record for each of these notifications
                        $sql_dismiss = "INSERT INTO notification_dismissals (user_id, notification_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id=user_id";
                        $stmt_dismiss = $conn->prepare($sql_dismiss);
                        foreach ($notification_ids as $notif_id) {
                            $stmt_dismiss->bind_param("ii", $admin_user_id, $notif_id);
                            $stmt_dismiss->execute();
                        }
                        $stmt_dismiss->close();
                    }

                    log_activity($conn, $admin_user_id_for_log, 'dismiss_notifications', null, "Dismissed all unread notifications.");
                    $response = ['success' => true, 'message' => 'Notifications dismissed.'];
                    break;

            }
        } elseif (isset($_GET['fetch'])) {
            $fetch_target = $_GET['fetch'];
            switch ($fetch_target) {

                // Add these cases inside the switch for $_GET['fetch']
                case 'active_doctors':
                    $sql = "SELECT u.id, u.name, d.specialty 
                            FROM users u 
                            JOIN doctors d ON u.id = d.user_id 
                            WHERE u.active = 1 AND u.role = 'doctor' 
                            ORDER BY u.name ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'available_beds':
                    $sql = "SELECT b.id, b.bed_number, w.name as ward_name 
                            FROM beds b
                            JOIN wards w ON b.ward_id = w.id
                            WHERE b.status = 'available'
                            ORDER BY w.name, b.bed_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'unassigned_patients':
                    // Fetches users who are not currently occupying a bed or a room
                    $sql = "SELECT u.id, u.name, u.display_user_id 
                            FROM users u
                            LEFT JOIN beds b ON u.id = b.patient_id AND b.status = 'occupied'
                            LEFT JOIN rooms r ON u.id = r.patient_id AND r.status = 'occupied'
                            WHERE u.role = 'user' AND u.active = 1 AND b.id IS NULL AND r.id IS NULL
                            ORDER BY u.name ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'available_rooms':
                    $sql = "SELECT id, room_number 
                            FROM rooms
                            WHERE status = 'available'
                            ORDER BY room_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;


                case 'appointments':
                    $doctor_id_filter = $_GET['doctor_id'] ?? 'all';

                    $sql = "SELECT a.id, p.name as patient_name, p.display_user_id as patient_display_id, d.name as doctor_name, a.appointment_date, a.status
            FROM appointments a
            JOIN users p ON a.user_id = p.id
            JOIN users d ON a.doctor_id = d.id";

                    $params = [];
                    $types = "";

                    if ($doctor_id_filter !== 'all') {
                        $sql .= " WHERE a.doctor_id = ?";
                        $params[] = (int) $doctor_id_filter;
                        $types .= "i";
                    }

                    $sql .= " ORDER BY a.appointment_date DESC";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
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
                    $search = $_GET['search'] ?? '';

                    $sql = "SELECT u.id, u.display_user_id, u.name, u.username, u.email, u.phone, u.role, u.active, u.created_at, u.date_of_birth, u.gender, u.profile_picture";
                    $params = [];
                    $types = "";

                    $base_from = " FROM users u ";
                    if ($role === 'doctor') {
                        $sql .= ", d.specialty, d.qualifications, d.department_id, d.availability";
                        $base_from = " FROM users u LEFT JOIN doctors d ON u.id = d.user_id ";
                    } elseif ($role === 'staff') {
                        $sql .= ", s.shift, s.assigned_department";
                        $base_from = " FROM users u LEFT JOIN staff s ON u.id = s.user_id ";
                    }
                    $sql .= $base_from;

                    $where_clauses = [];
                    // If the role is 'all_users', don't filter by role
                    if ($role !== 'all_users') {
                        $where_clauses[] = "u.role = ?";
                        $params[] = $role;
                        $types .= "s";
                    }

                    if (!empty($search)) {
                        // Add conditions to search across multiple fields
                        $where_clauses[] = "(u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.display_user_id LIKE ?)";
                        $search_term = "%{$search}%";
                        array_push($params, $search_term, $search_term, $search_term, $search_term);
                        $types .= "ssss";
                    }

                    if (!empty($where_clauses)) {
                        $sql .= " WHERE " . implode(' AND ', $where_clauses);
                    }

                    $sql .= " ORDER BY u.created_at DESC";

                    $stmt = $conn->prepare($sql);
                    // Bind parameters dynamically
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
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
                    $stmt = $conn->prepare("SELECT u.*, d.specialty, d.qualifications, s.shift 
                                            FROM users u 
                                            LEFT JOIN doctors d ON u.id = d.user_id AND u.role = 'doctor'
                                            LEFT JOIN staff s ON u.id = s.user_id AND u.role = 'staff'
                                            WHERE u.id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $data['user'] = $stmt->get_result()->fetch_assoc();

                    // Fetch activity logs for this user
                    $stmt = $conn->prepare("SELECT * FROM activity_logs WHERE target_user_id = ? ORDER BY created_at DESC LIMIT 20");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $data['activity'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                    // Fetch assigned patients (if doctor)
                    if ($data['user']['role'] === 'doctor') {
                        $stmt = $conn->prepare("SELECT u.name, u.display_user_id, a.appointment_date, a.status 
                                                FROM appointments a 
                                                JOIN users u ON a.user_id = u.id 
                                                WHERE a.doctor_id = ? 
                                                ORDER BY a.appointment_date DESC LIMIT 20");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $data['assigned_patients'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    }

                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'doctors_for_scheduling':
                    $result = $conn->query("SELECT u.id, u.name, u.display_user_id FROM users u JOIN doctors d ON u.id = d.user_id WHERE u.active = 1 AND u.role = 'doctor' ORDER BY u.name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'fetch_doctor_schedule':
                    if (empty($_GET['doctor_id']))
                        throw new Exception('Doctor ID is required.');
                    $doctor_id = (int) $_GET['doctor_id'];
                    $stmt = $conn->prepare("SELECT slots FROM doctors WHERE user_id = ?");
                    $stmt->bind_param("i", $doctor_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_assoc();
                    // Provide a default structure if slots are null
                    $slots = $data['slots'] ? json_decode($data['slots'], true) : [
                        'Monday' => [],
                        'Tuesday' => [],
                        'Wednesday' => [],
                        'Thursday' => [],
                        'Friday' => [],
                        'Saturday' => [],
                        'Sunday' => []
                    ];
                    $response = ['success' => true, 'data' => $slots];
                    break;

                case 'staff_for_shifting':
                    $result = $conn->query("SELECT u.id, u.name, u.display_user_id, s.shift FROM users u JOIN staff s ON u.id = s.user_id WHERE u.active = 1 AND u.role = 'staff' ORDER BY u.name ASC");
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

                    $stats['pending_appointments'] = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='scheduled'")->fetch_assoc()['c'];


                    $role_counts_sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
                    $result = $conn->query($role_counts_sql);
                    $counts = ['user' => 0, 'doctor' => 0, 'staff' => 0, 'admin' => 0];
                    while ($row = $result->fetch_assoc()) {
                        if (array_key_exists($row['role'], $counts)) {
                            $counts[$row['role']] = (int) $row['count'];
                        }
                    }
                    $stats['role_counts'] = $counts;

                    // Fetch low stock alerts
                    $low_medicines_stmt = $conn->query("SELECT COUNT(*) as c FROM medicines WHERE quantity < low_stock_threshold");
                    $stats['low_medicines_count'] = $low_medicines_stmt->fetch_assoc()['c'];

                    $low_blood_stmt = $conn->query("SELECT COUNT(*) as c FROM blood_inventory WHERE quantity_ml < low_stock_threshold_ml");
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

                case 'departments_management':
                    $result = $conn->query("SELECT * FROM departments ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
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
                    $sql = "SELECT b.id, b.ward_id, w.name as ward_name, b.bed_number, b.status, 
                                   b.patient_id, p.name as patient_name, 
                                   b.doctor_id, d.name as doctor_name,
                                   b.occupied_since, b.reserved_since, b.price_per_day 
                            FROM beds b 
                            JOIN wards w ON b.ward_id = w.id 
                            LEFT JOIN users p ON b.patient_id = p.id
                            LEFT JOIN users d ON b.doctor_id = d.id
                            ORDER BY w.name, b.bed_number ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'rooms':
                    $sql = "SELECT r.id, r.room_number, r.status, 
                                   r.patient_id, p.name as patient_name, 
                                   r.doctor_id, d.name as doctor_name,
                                   r.occupied_since, r.reserved_since, r.price_per_day 
                            FROM rooms r
                            LEFT JOIN users p ON r.patient_id = p.id
                            LEFT JOIN users d ON r.doctor_id = d.id
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

                case 'report':
                    if (empty($_GET['type']) || empty($_GET['period'])) {
                        throw new Exception('Report type and period are required.');
                    }
                    $reportType = $_GET['type'];
                    $period = $_GET['period'];

                    $data = ['summary' => [], 'chartData' => [], 'tableData' => []];
                    $date_format_chart = '%Y-%m-%d';
                    $date_format_table = '%Y-%m-%d';
                    $interval = '1 YEAR';
                    $group_by_chart = "DATE_FORMAT(created_at, '$date_format_chart')";
                    $group_by_table = "DATE_FORMAT(t.created_at, '$date_format_table')";

                    switch ($period) {
                        case 'daily':
                            $interval = '30 DAY';
                            $date_format_chart = '%Y-%m-%d';
                            $date_format_table = '%Y-%m-%d';
                            break;
                        case 'weekly':
                            $interval = '3 MONTH';
                            $date_format_chart = '%Y-W%U';
                            $date_format_table = '%Y-W%U';
                            break;
                        case 'monthly':
                            $interval = '1 YEAR';
                            $date_format_chart = '%Y-%m';
                            $date_format_table = '%%Y-%%m';
                            break;
                        case 'yearly':
                            $interval = '5 YEAR';
                            $date_format_chart = '%Y';
                            $date_format_table = '%%Y';
                            break;
                    }

                    if ($reportType === 'financial') {
                        $summary_sql = "SELECT SUM(IF(type='payment', amount, 0)) as total_revenue, SUM(IF(type='refund', amount, 0)) as total_refunds, COUNT(*) as total_transactions FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)";
                        $chart_sql = "SELECT DATE_FORMAT(created_at, '$date_format_chart') as label, SUM(IF(type='payment', amount, -amount)) as value FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval) GROUP BY label ORDER BY label";
                        $table_sql = "SELECT t.id, u.name as user_name, t.description, t.amount, t.type, DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as date FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL $interval) ORDER BY t.created_at DESC";
                    } elseif ($reportType === 'patient') {
                        $summary_sql = "SELECT COUNT(*) as total_appointments, SUM(IF(status='completed', 1, 0)) as completed, SUM(IF(status='cancelled', 1, 0)) as cancelled FROM appointments WHERE appointment_date >= DATE_SUB(NOW(), INTERVAL $interval)";
                        $chart_sql = "SELECT DATE_FORMAT(appointment_date, '$date_format_chart') as label, COUNT(*) as value FROM appointments WHERE appointment_date >= DATE_SUB(NOW(), INTERVAL $interval) GROUP BY label ORDER BY label";
                        $table_sql = "SELECT a.id, p.name as patient_name, d.name as doctor_name, a.status, DATE_FORMAT(a.appointment_date, '%Y-%m-%d %H:%i') as date FROM appointments a JOIN users p ON a.user_id = p.id JOIN users d ON a.doctor_id = d.id WHERE a.appointment_date >= DATE_SUB(NOW(), INTERVAL $interval) ORDER BY a.appointment_date DESC";
                    } else { // resource
                        $summary_sql = "SELECT 
        (SELECT COUNT(*) FROM beds) as total_beds,
        (SELECT COUNT(*) FROM rooms) as total_rooms,
        (SELECT COUNT(*) FROM beds WHERE status = 'occupied') as occupied_beds,
        (SELECT COUNT(*) FROM rooms WHERE status = 'occupied') as occupied_rooms";

                        $chart_sql = "SELECT DATE_FORMAT(admission_date, '$date_format_chart') as label, COUNT(*) as value FROM admissions WHERE admission_date >= DATE_SUB(NOW(), INTERVAL $interval) GROUP BY label ORDER BY label";

                        $table_sql = "SELECT 
                    a.id, 
                    p.name as patient_name, 
                    CASE 
                        WHEN a.bed_id IS NOT NULL THEN CONCAT('Bed ', b.bed_number, ' (', w.name, ')')
                        WHEN a.room_id IS NOT NULL THEN CONCAT('Room ', r.room_number)
                        ELSE 'N/A' 
                    END as location,
                    DATE_FORMAT(a.admission_date, '%Y-%m-%d %H:%i') as admission_date,
                    IF(a.discharge_date IS NOT NULL, DATE_FORMAT(a.discharge_date, '%Y-%m-%d %H:%i'), 'Admitted') as discharge_date
                FROM admissions a
                JOIN users p ON a.patient_id = p.id
                LEFT JOIN beds b ON a.bed_id = b.id
                LEFT JOIN wards w ON b.ward_id = w.id
                LEFT JOIN rooms r ON a.room_id = r.id
                WHERE a.admission_date >= DATE_SUB(NOW(), INTERVAL $interval)
                ORDER BY a.admission_date DESC";
                    }

                    $summary_result = $conn->query($summary_sql);
                    $data['summary'] = $summary_result->fetch_assoc();

                    $chart_result = $conn->query($chart_sql);
                    $data['chartData'] = $chart_result->fetch_all(MYSQLI_ASSOC);

                    $table_result = $conn->query($table_sql);
                    $data['tableData'] = $table_result->fetch_all(MYSQLI_ASSOC);


                    $response = ['success' => true, 'data' => $data];
                    break;



                case 'search_users':
                    $term = $_GET['term'] ?? '';
                    if (empty($term)) {
                        $response = ['success' => true, 'data' => []];
                        break;
                    }
                    $searchTerm = "%{$term}%";
                    $sql = "SELECT id, name, display_user_id, role FROM users WHERE name LIKE ? OR username LIKE ? OR email LIKE ? OR display_user_id LIKE ? LIMIT 10";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'all_notifications':
                    $admin_id = $_SESSION['user_id'];
                    // CORRECTED: Fetches ALL notifications for the admin, regardless of read status.
                    $sql = "SELECT n.id, n.message, n.created_at, n.is_read, u.name as sender_name 
                            FROM notifications n
                            JOIN users u ON n.sender_id = u.id
                            WHERE (n.recipient_user_id = ? OR n.recipient_role = 'admin' OR n.recipient_role = 'all')
                            ORDER BY n.created_at DESC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'unread_notification_count':
                    $admin_id = $_SESSION['user_id'];
                    // CORRECTED: Counts only rows where is_read = 0 (unread).
                    $sql = "SELECT COUNT(*) as unread_count 
                            FROM notifications
                            WHERE 
                                (recipient_user_id = ? OR recipient_role = 'admin' OR recipient_role = 'all') 
                                AND is_read = 0";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $response = ['success' => true, 'count' => $result['unread_count']];
                    break;

                case 'mark_notifications_read':
                    $admin_id = $_SESSION['user_id'];
                    $sql = "UPDATE notifications SET is_read = 1 WHERE (recipient_user_id = ? OR recipient_role = 'admin' OR recipient_role = 'all') AND is_read = 0";
                    $stmt = $conn->prepare($sql);

                    if ($stmt) {
                        $stmt->bind_param("i", $admin_id);
                        if ($stmt->execute()) {
                            $response = ['success' => true, 'message' => 'Notifications marked as read.'];
                        } else {
                            // If execution fails, send a specific error
                            $response = ['success' => false, 'message' => 'Database update failed.'];
                        }
                        $stmt->close();
                    } else {
                        // If preparing the statement fails
                        $response = ['success' => false, 'message' => 'Database statement could not be prepared.'];
                    }
                    break;



                case 'activity':
                    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
                    $sql = "SELECT a.id, a.action, a.details, a.created_at, u.username as admin_username, t.username as target_username
                            FROM activity_logs a
                            JOIN users u ON a.user_id = u.id
                            LEFT JOIN users t ON a.target_user_id = t.id
                            ORDER BY a.created_at DESC
                            LIMIT ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $limit);
                    $stmt->execute();
                    $result = $stmt->get_result();
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
// --- PDF GENERATION LOGIC ---
// ===================================================================================
if (isset($_GET['action']) && $_GET['action'] === 'download_pdf') {
    $reportType = $_GET['report_type'] ?? 'Unknown';
    $period = $_GET['period'] ?? 'All Time';
    $conn = getDbConnection();

    // --- Data Fetching (same as the report API endpoint) ---
    $table_sql = '';
    $table_headers = [];

    $interval_map = ['daily' => '30 DAY', 'weekly' => '3 MONTH', 'monthly' => '1 YEAR', 'yearly' => '5 YEAR'];
    $interval = $interval_map[$period] ?? '1 YEAR';

    if ($reportType === 'financial') {
        $table_headers = ['ID', 'User', 'Description', 'Amount', 'Type', 'Date'];
        $table_sql = "SELECT t.id, u.name as user_name, t.description, t.amount, t.type, DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as date FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL $interval) ORDER BY t.created_at DESC";
    } elseif ($reportType === 'patient') {
        $table_headers = ['ID', 'Patient', 'Doctor', 'Status', 'Date'];
        $table_sql = "SELECT a.id, p.name as patient_name, d.name as doctor_name, a.status, DATE_FORMAT(a.appointment_date, '%Y-%m-%d %H:%i') as date FROM appointments a JOIN users p ON a.user_id = p.id JOIN users d ON a.doctor_id = d.id WHERE a.appointment_date >= DATE_SUB(NOW(), INTERVAL $interval) ORDER BY a.appointment_date DESC";
    } elseif ($reportType === 'resource') {
        $table_headers = ['Admission ID', 'Patient Name', 'Location', 'Admission Date', 'Discharge Date'];
        $table_sql = "SELECT 
                    a.id, 
                    p.name as patient_name, 
                    CASE 
                        WHEN a.bed_id IS NOT NULL THEN CONCAT('Bed ', b.bed_number, ' (', w.name, ')')
                        WHEN a.room_id IS NOT NULL THEN CONCAT('Room ', r.room_number)
                        ELSE 'N/A' 
                    END as location,
                    DATE_FORMAT(a.admission_date, '%Y-%m-%d %H:%i') as admission_date,
                    IF(a.discharge_date IS NOT NULL, DATE_FORMAT(a.discharge_date, '%Y-%m-%d %H:%i'), 'Admitted') as discharge_date
                FROM admissions a
                JOIN users p ON a.patient_id = p.id
                LEFT JOIN beds b ON a.bed_id = b.id
                LEFT JOIN wards w ON b.ward_id = w.id
                LEFT JOIN rooms r ON a.room_id = r.id
                WHERE a.admission_date >= DATE_SUB(NOW(), INTERVAL $interval)
                ORDER BY a.admission_date DESC";
    }

    $result = $conn->query($table_sql);
    $tableData = $result->fetch_all(MYSQLI_ASSOC);
    $conn->close();

    // --- HTML Template for PDF ---
    $medsync_logo_path = 'images/logo.png';
    $hospital_logo_path = 'images/hospital.png'; // Make sure you have this image
    $medsync_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($medsync_logo_path));
    $hospital_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($hospital_logo_path));

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Report</title>
<style>
            @page { margin: 20px; }
            body { font-family: "Poppins", sans-serif; color: #333; }
            .header { position: fixed; top: 0; left: 0; right: 0; width: 100%; height: 120px; }
            .medsync-logo { position: absolute; top: 10px; left: 20px; }
            .medsync-logo img { width: 80px; } /* <-- Reduced size */
            .hospital-logo { position: absolute; top: 10px; right: 20px; }
            .hospital-logo img { width: 70px; } /* <-- Reduced size */
            .hospital-details { text-align: center; margin-top: 0; }
            .hospital-details h2 { margin: 0; font-size: 1.5em; color: #007BFF; }
            .hospital-details p { margin: 2px 0; font-size: 0.85em; }
            .report-title { text-align: center; margin-top: 130px; margin-bottom: 20px; }
            .report-title h1 { margin: 0; font-size: 1.8em; }
            .report-title p { margin: 5px 0 0 0; font-size: 1em; color: #666; }
            .data-table { width: 100%; border-collapse: collapse; font-size: 0.9em; }
            .data-table th, .data-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .data-table th { background-color: #f2f2f2; font-weight: bold; }
            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 0.8em; color: #aaa; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="medsync-logo">
                <img src="' . $medsync_logo_base64 . '" alt="MedSync Logo">
            </div>
            <div class="hospital-details">
                <h2>Calysta Health Institute</h2>
                <p>Kerala, India</p>
                <p>+91 45235 31245 | medsync.calysta@gmail.com</p>
            </div>
            <div class="hospital-logo">
                <img src="' . $hospital_logo_base64 . '" alt="Hospital Logo">
            </div>
        </div>

        <div class="report-title">
            <h1>' . htmlspecialchars(ucfirst($reportType)) . ' Report</h1>
            <p>Period: ' . htmlspecialchars(ucfirst($period)) . ' | Generated on: ' . date('Y-m-d H:i:s') . '</p>
        </div>
        <table class="data-table">
            <thead>
                <tr>';
    foreach ($table_headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '
                </tr>
            </thead>
            <tbody>';
    if (count($tableData) > 0) {
        foreach ($tableData as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>' . htmlspecialchars($cell) . '</td>';
            }
            $html .= '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="' . count($table_headers) . '" style="text-align: center;">No data available for this period.</td></tr>';
    }
    $html .= '
            </tbody>
        </table>
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
    $dompdf->stream(strtolower(str_replace(' ', '_', $reportType)) . '_report.pdf', ["Attachment" => 1]);
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

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        /* --- Schedules Panel --- */
        /* (Keep all existing .schedule-* CSS rules) */

        .time-slot {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            /* Add gap for spacing */
            background-color: var(--bg-grey);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
        }

        .time-slot label {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .time-slot input[type="time"] {
            border: none;
            background: transparent;
            outline: none;
            flex-grow: 1;
            /* This is the key change */
            width: 100%;
            /* Fallback for some browsers */
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
        }

        /* Style for the time input's picker indicator to match the theme */
        input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            /* A simple trick to make it visible in both light/dark modes */
        }

        body.dark-mode input[type="time"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        .time-slot .remove-slot-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            font-size: 1.1rem;
        }

        /* (Keep the rest of the existing schedule CSS rules) */

        /* --- Schedules Panel --- */
        .schedule-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 2rem;
        }

        .schedule-tab-button {
            padding: 0.75rem 1.5rem;
            cursor: pointer;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
            /* Overlap border */
            transition: all 0.3s ease;
        }

        .schedule-tab-button.active,
        .schedule-tab-button:hover {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .schedule-tab-content {
            display: none;
        }

        .schedule-tab-content.active {
            display: block;
        }

        .schedule-controls {
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background-color: var(--bg-grey);
            border-radius: var(--border-radius);
        }

        .schedule-editor-container .placeholder-text {
            text-align: center;
            color: var(--text-muted);
            padding: 3rem;
            background-color: var(--bg-grey);
            border-radius: var(--border-radius);
        }

        .day-schedule-card {
            background-color: var(--bg-light);
            border: 1px solid var(--border-light);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .day-schedule-card h4 {
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            /* Increased min-width */
            gap: 1rem;
        }

        .time-slot {
            display: flex;
            align-items: center;
            background-color: var(--bg-grey);
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
        }

        .time-slot input {
            border: none;
            background: transparent;
            outline: none;
            width: 100%;
        }

        .time-slot .remove-slot-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            font-size: 1.1rem;
            margin-left: 0.5rem;
        }

        .add-slot-btn {
            margin-top: 1rem;
            background: none;
            border: 1px dashed var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-slot-btn:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .schedule-actions {
            margin-top: 2rem;
            text-align: right;
        }

        .shift-select {
            padding: 0.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            background-color: var(--bg-grey);
            color: var(--text-dark);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }

        /* --- Enhanced Search Bar --- */
        .search-container {
            position: relative;
            background-color: var(--bg-grey);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .search-container:focus-within {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
            background-color: var(--bg-light);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            color: var(--text-muted);
            font-size: 1rem;
            transition: color 0.3s ease;
        }

        .search-container:focus-within .search-icon {
            color: var(--primary-color);
        }

        #user-search-input {
            width: 100%;
            border: 1px solid var(--border-light);
            background-color: transparent;
            border-radius: var(--border-radius);
            padding: 1.5rem 1rem 0.5rem 3rem;
            /* Top padding for label */
            font-size: 1rem;
            color: var(--text-dark);
            outline: none;
        }

        #user-search-input::placeholder {
            color: transparent;
            /* Hide placeholder initially */
        }

        #user-search-label {
            position: absolute;
            left: 3rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: var(--text-muted);
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        /* Floating label effect */
        #user-search-input:focus+#user-search-label,
        #user-search-input:not(:placeholder-shown)+#user-search-label {
            top: 0.5rem;
            transform: translateY(0);
            font-size: 0.75rem;
            color: var(--primary-color);
        }

        /* --- THEMES AND MODERN ADMIN COLOR PALETTE --- */
        :root {
            --primary-color: #3B82F6;
            /* A modern, vibrant blue */
            --primary-color-dark: #2563EB;
            --danger-color: #EF4444;
            --success-color: #22C55E;
            --warning-color: #F97316;

            --text-dark: #1F2937;
            /* Dark Gray */
            --text-light: #F9FAFB;
            /* Almost White */
            --text-muted: #6B7280;
            /* Medium Gray */

            --bg-light: #FFFFFF;
            /* White */
            --bg-grey: #F3F4F6;
            /* Lightest Gray */
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
            font-size: 16px;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

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

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
            padding-left: 0.5rem;
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

        .sidebar-nav a,
        .nav-dropdown-toggle {
            display: flex;
            align-items: center;
            padding: 0.9rem 1rem;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 8px;

            transition: background-color var(--transition-speed), color var(--transition-speed);
            font-weight: 500;
            cursor: pointer;
        }

        /* ADD THESE NEW RULES FOR CORRECT SPACING */
        .sidebar-nav>ul>li {
            margin-bottom: 0.5rem;
        }

        .nav-dropdown li {
            margin-bottom: 0.25rem;
        }

        .nav-dropdown li:last-child {
            margin-bottom: 0;
        }

        .sidebar-nav a i,
        .nav-dropdown-toggle i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
            text-align: center;
        }

        .sidebar-nav a:hover,
        .nav-dropdown-toggle:hover {
            background-color: var(--bg-grey);
            color: var(--primary-color);
        }

        .sidebar-nav a.active,
        .nav-dropdown-toggle.active {
            background-color: var(--primary-color);
            color: white;
        }

        body.dark-mode .sidebar-nav a.active,
        body.dark-mode .nav-dropdown-toggle.active {
            background-color: var(--primary-color-dark);
        }

        .nav-dropdown-toggle .arrow {
            margin-left: auto;
            transition: transform var(--transition-speed);
        }

        .nav-dropdown-toggle.active .arrow {
            transform: rotate(90deg);
        }

        .nav-dropdown {
            list-style: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-in-out;
            padding-left: 1.5rem;
        }

        .nav-dropdown a {
            font-size: 0.95rem;
            padding: 0.7rem 1rem 0.7rem 0.5rem;
            background-color: rgba(100, 100, 100, 0.05);
            padding-bottom: -3.5rem;
        }

        body.dark-mode .nav-dropdown a {
            background-color: rgba(255, 255, 255, 0.05);
        }

        /* ADD THIS RULE TO FIX THE SIDEBAR SPACING */
        .nav-dropdown li:last-child a {
            margin-bottom: 0;
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
            margin-top: 1rem;
        }

        .logout-btn:hover {
            background-color: var(--danger-color);
            color: white;
        }

        /* --- MAIN CONTENT --- */
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            margin-left: 280px;
            transition: margin-left var(--transition-speed);
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .main-header .title-group {
            flex-grow: 1;
        }

        .main-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            margin: 0;
        }

        .main-header h2 {
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
            background-color: var(--bg-light);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .content-panel.active {
            display: block;
        }

        /* --- DASHBOARD HOME --- */
        .stat-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
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
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card .icon {
            font-size: 2rem;
            padding: 1rem;
            border-radius: 50%;
            color: var(--primary-color);
            background-color: var(--bg-grey);
        }

        .stat-card.blue {
            border-left-color: #3B82F6;
        }

        .stat-card.blue .icon {
            color: #3B82F6;
        }

        .stat-card.green {
            border-left-color: var(--success-color);
        }

        .stat-card.green .icon {
            color: var(--success-color);
        }

        .stat-card.orange {
            border-left-color: var(--warning-color);
        }

        .stat-card.orange .icon {
            color: var(--warning-color);
        }

        .stat-card.red {
            border-left-color: var(--danger-color);
        }

        .stat-card.red .icon {
            color: var(--danger-color);
        }

        /* Added for low stock */
        .stat-card .info .value {
            font-size: 1.75rem;
            font-weight: 600;
        }

        .stat-card .info .label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .grid-card {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .grid-card h3 {
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        /* --- QUICK ACTIONS --- */
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

        /* --- USER MANAGEMENT & GENERIC TABLE STYLES --- */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-light);
            white-space: nowrap;
        }

        .data-table th {
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
        }

        .data-table tbody tr {
            transition: background-color var(--transition-speed);
        }

        .data-table tbody tr:hover {
            background-color: var(--bg-grey);
        }

        .data-table tbody tr.clickable-row {
            cursor: pointer;
        }

        .user-list-pfp {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }

        .status-badge {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.active,
        .status-badge.in-stock {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-badge.inactive,
        .status-badge.low-stock {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        .status-badge.scheduled {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .status-badge.completed {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .status-badge.cancelled {
            background-color: #FEE2E2;
            color: #991B1B;
        }

        body.dark-mode .status-badge.scheduled {
            background-color: #78350F;
            color: #FDE68A;
        }

        body.dark-mode .status-badge.completed {
            background-color: #064E3B;
            color: #A7F3D0;
        }

        body.dark-mode .status-badge.cancelled {
            background-color: #7F1D1D;
            color: #FECACA;
        }

        body.dark-mode .status-badge.active,
        body.dark-mode .status-badge.in-stock {
            background-color: #064E3B;
            color: #A7F3D0;
        }

        body.dark-mode .status-badge.inactive,
        body.dark-mode .status-badge.low-stock {
            background-color: #7F1D1D;
            color: #FECACA;
        }

        .action-buttons button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            margin: 0 5px;
            transition: color var(--transition-speed);
        }

        .action-buttons .btn-edit {
            color: var(--primary-color);
        }

        .action-buttons .btn-delete {
            color: var(--danger-color);
        }

        .quantity-good {
            color: var(--success-color);
            font-weight: 600;
        }

        .quantity-low {
            color: var(--danger-color);
            font-weight: 600;
        }

        /* --- BUTTONS & FORMS --- */
        .btn {
            padding: 0.7rem 1.4rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all var(--transition-speed);
            border: 1px solid transparent;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-color-dark);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            background-color: var(--bg-grey);
            color: var(--text-dark);
            transition: all var(--transition-speed);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .role-specific-fields {
            border-top: 1px solid var(--border-light);
            margin-top: 1.5rem;
            padding-top: 1.5rem;
        }

        /* --- MODAL, NOTIFICATION, CONFIRMATION STYLES --- */
        .modal,
        .notification-container,
        .confirm-dialog {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1050;
            display: none;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal.show,
        .notification-container.show,
        .confirm-dialog.show {
            display: flex;
        }

        .modal-content,
        .confirm-content {
            background-color: var(--bg-light);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 500px;
            animation: slideIn 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
        }

        #user-detail-modal #user-detail-content {
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 1rem;
            /* Adds some space for the scrollbar */
            margin-right: -1rem;
            /* Compensates for the padding */
        }

        #user-detail-modal .modal-content {
            max-width: 800px;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-light);
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
        }

        @keyframes slideIn {
            from {
                transform: translateY(-30px) scale(0.95);
                opacity: 0;
            }

            to {
                transform: translateY(0) scale(1);
                opacity: 1;
            }
        }

        .notification {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            color: white;
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s, fadeOut 0.5s 4.5s forwards;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1100;
        }

        .notification.success {
            background-color: var(--success-color);
        }

        .notification.error {
            background-color: var(--danger-color);
        }

        .notification.warning {
            background-color: var(--warning-color);
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        .confirm-content {
            text-align: center;
        }

        .confirm-content h4 {
            margin-bottom: 1rem;
        }

        .confirm-content p {
            margin-bottom: 1.5rem;
            color: var(--text-muted);
        }

        .confirm-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .btn-secondary {
            background-color: var(--bg-grey);
            color: var(--text-dark);
            border-color: var(--border-light);
        }

        body.dark-mode .btn-secondary {
            background-color: #374151;
            color: var(--text-light);
            border-color: #4B5563;
        }

        .btn-secondary:hover {
            background-color: #E5E7EB;
        }

        body.dark-mode .btn-secondary:hover {
            background-color: #4B5563;
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        /* --- DARK/LIGHT THEME TOGGLE --- */
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

        /* --- INVENTORY: BEDS & ROOMS --- */
        .resource-grid-container,
        .ward-beds-container {
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

        .bed-card,
        .room-card {
            background-color: var(--bg-light);
            padding: 1.25rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            text-align: center;
            border-left: 5px solid;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .bed-card:hover,
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .bed-card.available,
        .room-card.available {
            border-color: var(--success-color);
        }

        .bed-card.occupied,
        .room-card.occupied {
            border-color: var(--danger-color);
        }

        .bed-card.reserved,
        .room-card.reserved {
            border-color: var(--primary-color);
        }

        .bed-card.cleaning,
        .room-card.cleaning {
            border-color: var(--warning-color);
        }

        .bed-card .bed-icon,
        .room-card .room-icon {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        .bed-card.available .bed-icon,
        .room-card.available .room-icon {
            color: var(--success-color);
        }

        .bed-card.occupied .bed-icon,
        .room-card.occupied .room-icon {
            color: var(--danger-color);
        }

        .bed-card.reserved .bed-icon,
        .room-card.reserved .room-icon {
            color: var(--primary-color);
        }

        .bed-card.cleaning .bed-icon,
        .room-card.cleaning .room-icon {
            color: var(--warning-color);
        }

        .bed-card .bed-number,
        .room-card .room-number {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .bed-card .bed-status,
        .room-card .room-status {
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: capitalize;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
        }

        .bed-card .patient-info,
        .room-card .patient-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .bed-card .action-buttons,
        .room-card .action-buttons {
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            gap: 0.5rem;
        }

        .bed-card .action-buttons button,
        .room-card .action-buttons button {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
                /* Stack on medium screens */
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
            }

            .sidebar.active {
                left: 0;
                box-shadow: var(--shadow-lg);
            }

            .main-content {
                margin-left: 0;
            }

            .hamburger-btn {
                display: block;
            }

            .main-header {
                flex-wrap: wrap;
                /* Allow header items to wrap */
                gap: 1rem;
            }

            .main-header .title-group {
                order: 2;
                width: 100%;
                /* Take full width on a new line */
                text-align: center;
                margin-top: 1rem;
            }

            .header-actions {
                margin-left: auto;
                order: 1;
            }

            #hamburger-btn {
                order: 0;
            }

            .overlay.active {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }

            .content-panel {
                padding: 1.5rem;
            }

            .main-header h1 {
                font-size: 1.4rem;
            }

            .main-header h2 {
                font-size: 1rem;
            }

            .stat-cards-container {
                grid-template-columns: 1fr;
            }

            .header-actions {
                gap: 0.5rem;
            }

            .user-profile-widget {
                padding: 0.5rem;
            }

            .user-profile-widget .user-info {
                display: none;
            }

            .modal-content {
                padding: 1.5rem;
            }
        }

        /* --- REPORTS PANEL --- */
        #reports-panel .report-controls {
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            margin-bottom: 2.5rem;
            flex-wrap: wrap;
            padding: 1.5rem;
            background-color: var(--bg-grey);
            border-radius: var(--border-radius);
        }

        #reports-panel .report-controls .form-group {
            margin-bottom: 0;
            flex-grow: 1;
            min-width: 150px;
            /* Ensure inputs don't get too squished */
        }

        #reports-panel .report-summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .summary-card {
            background-color: var(--bg-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary-color);
        }

        .summary-card .label {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            display: block;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        #reports-panel #report-chart-container {
            margin-top: 2rem;
            padding: 2rem;
            background-color: var(--bg-light);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        @media (max-width: 576px) {
            #reports-panel #report-chart-container {
                padding: 1rem;
            }
        }


        /* --- ACTIVITY LOGS (AUDIT TRAIL) --- */
        #activity-panel .log-item,
        #user-detail-activity-log .log-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
        }

        #activity-panel .log-item:last-child,
        #user-detail-activity-log .log-item:last-child {
            border-bottom: none;
        }

        .log-icon {
            font-size: 1.2rem;
            color: var(--text-light);
            background-color: var(--primary-color);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            flex-shrink: 0;
        }

        .log-icon.update {
            background-color: var(--warning-color);
        }

        .log-icon.delete {
            background-color: var(--danger-color);
        }

        .log-details p {
            margin: 0;
            font-weight: 500;
        }

        .log-details .log-meta {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* --- USER DETAIL MODAL --- */
        .user-detail-header {
            display: flex;
            flex-direction: column;
            /* Stack on mobile */
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        @media (min-width: 576px) {
            .user-detail-header {
                flex-direction: row;
                /* Row on larger screens */
                text-align: left;
                gap: 1.5rem;
            }
        }

        .user-detail-pfp {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--bg-grey);
        }

        .user-detail-info h4 {
            font-size: 1.5rem;
            margin: 0;
        }

        .user-detail-info p {
            color: var(--text-muted);
            margin: 0.25rem 0;
        }

        .detail-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1.5rem;
            /* Allow scrolling tabs on small screens */
        }

        .detail-tab-button {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--text-muted);
            border-bottom: 3px solid transparent;
            margin-bottom: -1px;
            white-space: nowrap;
            /* Overlap border */
        }

        .detail-tab-button.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .detail-tab-content {
            display: none;
        }

        .detail-tab-content.active {
            display: block;
        }

        .search-result-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border-light);
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .search-result-item:hover {
            background-color: var(--bg-grey);
        }

        .search-result-item.none {
            cursor: default;
            color: var(--text-muted);
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
                    <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-home"></i>
                            Dashboard</a></li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-users"></i> Users <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <li><a href="#" class="nav-link" data-target="users-user"><i
                                        class="fas fa-user-injured"></i> Regular Users</a></li>
                            <li><a href="#" class="nav-link" data-target="users-doctor"><i class="fas fa-user-md"></i>
                                    Doctors</a></li>
                            <li><a href="#" class="nav-link" data-target="users-staff"><i
                                        class="fas fa-user-shield"></i> Staff</a></li>
                            <li><a href="#" class="nav-link" data-target="users-admin"><i class="fas fa-user-cog"></i>
                                    Admins</a></li>
                        </ul>
                    </li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-warehouse"></i> Inventory <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <li><a href="#" class="nav-link" data-target="inventory-blood"><i class="fas fa-tint"></i>
                                    Blood Inventory</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-medicine"><i
                                        class="fas fa-pills"></i> Medicine Inventory</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-departments"><i
                                        class="fas fa-building"></i> Departments</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-wards"><i
                                        class="fas fa-hospital"></i> Wards</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-beds"><i class="fas fa-bed"></i>
                                    Beds</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-rooms"><i
                                        class="fas fa-door-closed"></i> Rooms</a></li><br>
                        </ul>
                    </li>
                    <li><a href="#" class="nav-link" data-target="appointments"><i class="fas fa-calendar-check"></i>
                            Appointments</a></li>
                    <li><a href="#" class="nav-link" data-target="schedules"><i class="fas fa-calendar-alt"></i>
                            Schedules</a></li>
                    <li><a href="#" class="nav-link" data-target="reports"><i class="fas fa-chart-line"></i> Reports</a>
                    </li>
                    <li><a href="#" class="nav-link" data-target="activity"><i class="fas fa-history"></i> Activity
                            Logs</a></li>
                    <li><a href="#" class="nav-link" data-target="settings"><i class="fas fa-user-edit"></i> My
                            Account</a></li>
                    <li><a href="#" class="nav-link" data-target="system-settings"><i class="fas fa-cog"></i> System
                            Settings</a></li>
                    <li><a href="#" class="nav-link" data-target="notifications"><i class="fas fa-bullhorn"></i>
                            Notifications</a></li>
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

                    <div class="notification-bell-wrapper nav-link" id="notification-bell-wrapper"
                        data-target="all-notifications" style="position: relative; cursor: pointer; padding: 0.5rem;">
                        <i class="fas fa-bell" style="font-size: 1.2rem;"></i>
                        <span id="notification-count"
                            style="position: absolute; top: -5px; right: -8px; background-color: var(--danger-color); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.7rem; display: none; place-items: center;"></span>
                    </div>

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
                            <span style="color: var(--text-muted); font-size: 0.8rem;">ID:
                                <?php echo $display_user_id; ?></span>
                        </div>
                    </div>

                </div>
            </header>
            <div id="dashboard-panel" class="content-panel active">
                <div class="stat-cards-container">
                    <div class="stat-card blue">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="info">
                            <div class="value" id="total-users-stat"><?php echo $total_users; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="icon"><i class="fas fa-user-md"></i></div>
                        <div class="info">
                            <div class="value" id="active-doctors-stat"><?php echo $active_doctors; ?></div>
                            <div class="label">Active Doctors</div>
                        </div>
                    </div>
                    <div class="stat-card orange">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="info">
                            <div class="value" id="pending-appointments-stat">0</div>
                            <div class="label">Pending Appointments</div>
                        </div>
                    </div>
                    <div class="stat-card red" id="low-medicine-stat" style="display: none;">
                        <div class="icon"><i class="fas fa-pills"></i></div>
                        <div class="info">
                            <div class="value" id="low-medicine-count">0</div>
                            <div class="label">Low Medicines</div>
                        </div>
                    </div>
                    <div class="stat-card red" id="low-blood-stat" style="display: none;">
                        <div class="icon"><i class="fas fa-tint"></i></div>
                        <div class="info">
                            <div class="value" id="low-blood-count">0</div>
                            <div class="label">Low Blood Units</div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-grid">
                    <div class="grid-card">
                        <h3>User Roles Distribution</h3>
                        <div style="position: relative; height: auto; max-width: 480px; margin: auto;">
                            <canvas id="userRolesChart"></canvas>
                        </div>
                    </div>
                    <div class="grid-card quick-actions">
                        <h3>Quick Actions</h3>
                        <div class="actions-grid">
                            <a href="#" class="action-btn nav-link" data-target="users-user" id="quick-add-user-btn"><i
                                    class="fas fa-user-plus"></i> Add User</a>
                            <a href="#" class="action-btn nav-link" data-target="activity"><i
                                    class="fas fa-history"></i> Activity Log</a>
                            <a href="#" class="action-btn nav-link" data-target="inventory-departments"><i
                                    class="fas fa-building"></i> Departments</a>
                            <a href="#" class="action-btn nav-link" data-target="notifications"><i
                                    class="fas fa-bullhorn"></i> Send Notifications</a>
                            <a href="#" class="action-btn nav-link" data-target="system-settings"><i
                                    class="fas fa-cog"></i>
                                System Settings</a>
                            <a href="#" class="action-btn nav-link" data-target="settings"><i
                                    class="fas fa-user-edit"></i> My Account</a>
                        </div>
                    </div>
                </div>

            </div>
            <div id="appointments-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 id="appointments-table-title">Patient Appointments</h2>
                    <div class="form-group" style="flex-grow: 1; max-width: 400px; margin-bottom: 0;">
                        <label for="appointment-doctor-filter" style="margin-bottom: 0.25rem; font-weight: 500;">Filter
                            by Doctor</label>
                        <select id="appointment-doctor-filter">
                            <option value="all">All Doctors</option>
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Appt. ID</th>
                                <th>Patient Details</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="appointments-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="users-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 id="user-table-title">Users</h2>
                    <div class="search-container" style="flex-grow: 1; max-width: 400px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="user-search-input" placeholder="Search...">
                        <label for="user-search-input" id="user-search-label">Search users...</label>
                    </div>
                    <button id="add-user-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New User</button>
                </div>
                <div class="table-container">
                    <table class="data-table user-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>User ID</th>
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
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Blood Inventory</h2>
                    <button id="add-blood-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Update Blood
                        Unit</button>
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
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Medicine Inventory</h2>
                    <button id="add-medicine-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New
                        Medicine</button>
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
            <div id="inventory-departments-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Department Management</h2>
                    <button id="add-department-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New
                        Department</button>
                </div>
                <div class="table-container">
                    <table class="data-table" id="department-table">
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="department-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-wards-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
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
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Bed Management</h2>
                    <button id="add-bed-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Bed</button>
                </div>
                <div id="beds-container">
                </div>
            </div>

            <div id="inventory-rooms-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Room Management</h2>
                    <button id="add-room-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Room</button>
                </div>
                <div id="rooms-container" class="resource-grid-container">
                </div>
            </div>
            <div id="reports-panel" class="content-panel">
                <div class="report-controls">
                    <div class="form-group">
                        <label for="report-type">Report Type</label>
                        <select id="report-type" name="report_type">
                            <option value="financial">Financial</option>
                            <option value="patient">Patient Statistics</option>
                            <option value="resource">Resource Utilization</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="report-period">Period</label>
                        <select id="report-period" name="period">
                            <option value="yearly">Year</option>
                            <option value="monthly" selected>Month</option>
                            <option value="daily">Day</option>
                        </select>
                    </div>
                    <div class="form-group" id="report-year-container">
                        <label for="report-year">Year</label>
                        <input type="number" id="report-year" name="year" value="<?php echo date('Y'); ?>">
                    </div>
                    <div class="form-group" id="report-month-container">
                        <label for="report-month">Month</label>
                        <input type="month" id="report-month" name="month" value="<?php echo date('Y-m'); ?>">
                    </div>
                    <div class="form-group" id="report-day-container" style="display: none;">
                        <label for="report-day">Day</label>
                        <input type="date" id="report-day" name="day" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <button id="generate-report-btn" class="btn btn-primary"><i class="fas fa-sync"></i> Generate
                        Report</button>
                    <form id="download-pdf-form" method="GET" action="admin_dashboard.php" target="_blank">
                        <input type="hidden" name="action" value="download_pdf">
                        <input type="hidden" id="pdf-report-type" name="report_type">
                        <input type="hidden" id="pdf-period" name="period">
                        <input type="hidden" id="pdf-year" name="year">
                        <input type="hidden" id="pdf-month" name="month">
                        <input type="hidden" id="pdf-day" name="day">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-file-pdf"></i> Download
                            PDF</button>
                    </form>
                </div>

                <div class="report-summary-cards" id="report-summary-cards">
                </div>

                <div id="report-chart-container">
                    <canvas id="report-chart"></canvas>
                </div>
                <div id="report-table-container" style="margin-top: 2rem;">
                </div>
            </div>

            <div id="activity-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Recent Activity Logs</h2>
                    <button id="refresh-logs-btn" class="btn btn-secondary"><i class="fas fa-sync-alt"></i>
                        Refresh</button>
                </div>
                <div id="activity-log-container">
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
                        <input type="tel" id="profile-phone" name="phone" pattern="\+[0-9]{10,15}"
                            title="Enter in format +CountryCodeNumber">
                    </div>
                    <div class="form-group">
                        <label for="profile-username">Username</label>
                        <input type="text" id="profile-username" name="username" disabled>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Username cannot be changed.</small>
                    </div>
                    <div class="form-group">
                        <label for="profile-password">New Password</label>
                        <input type="password" id="profile-password" name="password">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep your current
                            password.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <div id="system-settings-panel" class="content-panel">
                <h3>System Settings</h3>
                <p>Configure system-wide settings here. Changes will take effect immediately.</p>
                <form id="system-settings-form" style="margin-top: 2rem; max-width: 600px;">
                    <input type="hidden" name="action" value="updateSystemSettings">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label for="system_email">System Email Address</label>
                        <input type="email" id="system_email" name="system_email"
                            placeholder="e.g., your_email@gmail.com">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">This email will be used to send OTPs
                            and all other system notifications.</small>
                    </div>

                    <div class="form-group">
                        <label for="gmail_app_password">Gmail App Password</label>
                        <input type="password" id="gmail_app_password" name="gmail_app_password">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">This is used for sending system
                            emails (e.g., OTPs, notifications). <a
                                href="https://support.google.com/accounts/answer/185833" target="_blank">How to get an
                                App Password</a>.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
            <div id="schedules-panel" class="content-panel">
                <div class="schedule-tabs">
                    <button class="schedule-tab-button active" data-tab="doctor-availability">Doctor
                        Availability</button>
                    <button class="schedule-tab-button" data-tab="staff-shifts">Staff Shifts</button>
                </div>

                <div id="doctor-availability-content" class="schedule-tab-content active">
                    <div class="schedule-controls">
                        <div class="form-group" style="flex-grow: 1;">
                            <label for="doctor-select">Select Doctor</label>
                            <select id="doctor-select" name="doctor_select"></select>
                        </div>
                    </div>
                    <div id="doctor-schedule-editor" class="schedule-editor-container">
                        <p class="placeholder-text">Please select a doctor to view or edit their schedule.</p>
                    </div>
                    <div class="schedule-actions" style="display:none;">
                        <button id="save-schedule-btn" class="btn btn-primary"><i class="fas fa-save"></i> Save
                            Schedule</button>
                    </div>
                </div>

                <div id="staff-shifts-content" class="schedule-tab-content">
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>User ID</th>
                                    <th>Current Shift</th>
                                    <th>Assign New Shift</th>
                                </tr>
                            </thead>
                            <tbody id="staff-shifts-table-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="all-notifications-panel" class="content-panel">
            </div>
            <div id="notifications-panel" class="content-panel">
                <div class="schedule-tabs">
                    <button class="schedule-tab-button active" data-tab="broadcast">Broadcast</button>
                    <button class="schedule-tab-button" data-tab="individual">Individual</button>
                </div>

                <div id="broadcast-content" class="schedule-tab-content active">
                    <h3>Send Broadcast Notification</h3>
                    <form id="notification-form">
                        <input type="hidden" name="action" value="sendNotification">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="form-group">
                            <label for="notification-role">Select Role</label>
                            <select id="notification-role" name="role" required>
                                <option value="all">All Users</option>
                                <option value="user">Regular Users</option>
                                <option value="doctor">Doctors</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admins</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notification-message">Message</label>
                            <textarea id="notification-message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Broadcast</button>
                    </form>
                </div>

                <div id="individual-content" class="schedule-tab-content">
                    <h3>Send Individual Notification</h3>
                    <form id="individual-notification-form">
                        <input type="hidden" name="action" value="sendIndividualNotification">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" id="recipient-user-id" name="recipient_user_id" required>

                        <div class="form-group">
                            <label for="user-search">Search for User (Recipient)</label>
                            <input type="text" id="user-search" autocomplete="off"
                                placeholder="Search by name, username, email, or ID..." class="form-control">
                            <div id="user-search-results"
                                style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 8px; margin-top: 5px; display: none;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="individual-notification-message">Message</label>
                            <textarea id="individual-notification-message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div class="overlay" id="overlay"></div>

    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add New User</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="user-form" enctype="multipart/form-data">
                <input type="hidden" name="id" id="user-id">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                            style="flex-grow: 1;">
                        <button type="button" id="remove-pfp-btn" class="btn btn-secondary"
                            style="display: none;">Remove Photo</button>
                    </div>
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
                        title="Enter in format +CountryCodeNumber" required>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>">
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
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep current password
                        when editing.</small>
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

    <div id="user-detail-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Profile</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div id="user-detail-content">
            </div>
        </div>
    </div>
    <div id="department-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="department-modal-title">Add New Department</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="department-form">
                <input type="hidden" name="id" id="department-id">
                <input type="hidden" name="action" id="department-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="department-name">Department Name</label>
                    <input type="text" id="department-name" name="name" required>
                </div>
                <div class="form-group" id="department-active-group" style="display: none;">
                    <label for="department-is-active">Status</label>
                    <select id="department-is-active" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Department</button>
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
                    <input type="number" id="blood-low-stock-threshold-ml" name="low_stock_threshold_ml" min="0"
                        required>
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
                <div class="form-group" id="bed-doctor-group" style="display: none;">
                    <label for="bed-doctor-id">Assign Doctor</label>
                    <select id="bed-doctor-id" name="doctor_id">
                        <option value="">Select Doctor</option>
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
                <div class="form-group" id="room-doctor-group" style="display: none;">
                    <label for="room-doctor-id">Assign Doctor</label>
                    <select id="room-doctor-id" name="doctor_id">
                        <option value="">Select Doctor</option>
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
        document.addEventListener("DOMContentLoaded", function () {

            const userSearchInput = document.getElementById('user-search-input');
            userSearchInput.addEventListener('keyup', () => {
                // A small delay to avoid sending too many requests while typing
                setTimeout(() => {
                    fetchUsers(currentRole, userSearchInput.value.trim());
                }, 300);
            });
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
            let reportChart = null;

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
                toggle.addEventListener('click', function () {
                    this.classList.toggle('active');
                    const dropdown = this.nextElementSibling;
                    dropdown.style.maxHeight = dropdown.style.maxHeight ? null : dropdown.scrollHeight + "px";
                });
            });

            // --- PANEL SWITCHING LOGIC ---
            const handlePanelSwitch = (clickedLink) => {
                if (!clickedLink) return;

                const targetId = clickedLink.dataset.target;
                if (!targetId) return;

                // Update active link styling in the sidebar
                document.querySelectorAll('.sidebar-nav a.active, .sidebar-nav .nav-dropdown-toggle.active').forEach(a => a.classList.remove('active'));

                // Find the corresponding sidebar link and activate it
                const sidebarLink = document.querySelector(`.sidebar .nav-link[data-target="${targetId}"]`);
                if (sidebarLink) {
                    sidebarLink.classList.add('active');
                    let parentDropdown = sidebarLink.closest('.nav-dropdown');
                    if (parentDropdown) {
                        let parentDropdownToggle = parentDropdown.previousElementSibling;
                        if (parentDropdownToggle) {
                            parentDropdownToggle.classList.add('active');
                        }
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
                    title = sidebarLink ? sidebarLink.innerText : 'Inventory';
                    welcomeMessage.style.display = 'none';
                    const inventoryType = targetId.split('-')[1];
                    if (inventoryType === 'blood') fetchBloodInventory();
                    else if (inventoryType === 'medicine') fetchMedicineInventory();
                    else if (inventoryType === 'departments') fetchDepartmentsManagement();
                    else if (inventoryType === 'wards') fetchWards();
                    else if (inventoryType === 'beds') fetchWardsAndBeds();
                    else if (inventoryType === 'rooms') fetchRooms();
                } else if (document.getElementById(targetId + '-panel')) {
                    panelToShowId = targetId + '-panel';
                    title = sidebarLink ? sidebarLink.innerText : 'Admin Panel';
                    welcomeMessage.style.display = (targetId === 'dashboard') ? 'block' : 'none';

                    if (targetId === 'settings') fetchMyProfile();

                    if (targetId === 'appointments') {
                        fetchDoctorsForAppointmentFilter();
                        fetchAppointments(); // Load all appointments initially
                    }
                    if (targetId === 'reports') generateReport();
                    if (targetId === 'activity') fetchActivityLogs();
                    if (targetId === 'schedules' && doctorSelect.options.length <= 1) fetchDoctorsForScheduling();
                }

                document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
                document.getElementById(panelToShowId).classList.add('active');
                panelTitle.textContent = title;

                if (window.innerWidth <= 992 && sidebar.classList.contains('active')) toggleMenu();
            };

            // Use event delegation on the body to handle all clicks on '.nav-link'
            document.body.addEventListener('click', function (e) {
                const link = e.target.closest('.nav-link');
                if (link) {
                    e.preventDefault(); // Prevent default link behavior for all nav-links

                    // The special logic for the bell is handled by its own listener now,
                    // so we just need to call the generic panel switcher.
                    if (link.id !== 'notification-bell-wrapper') {
                        handlePanelSwitch(link);
                    }
                }
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
                    document.getElementById('pending-appointments-stat').textContent = stats.pending_appointments || 0;

                    const lowMedicineStat = document.getElementById('low-medicine-stat');
                    const lowBloodStat = document.getElementById('low-blood-stat');

                    // FIX: Reset visibility before updating
                    lowMedicineStat.style.display = 'none';
                    lowBloodStat.style.display = 'none';

                    if (stats.low_medicines_count > 0) {
                        document.getElementById('low-medicine-count').textContent = stats.low_medicines_count;
                        lowMedicineStat.style.display = 'flex';
                    }

                    if (stats.low_blood_count > 0) {
                        document.getElementById('low-blood-count').textContent = stats.low_blood_count;
                        lowBloodStat.style.display = 'flex';
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

            const fetchDoctorsForAppointmentFilter = async () => {
                const doctorFilterSelect = document.getElementById('appointment-doctor-filter');
                // Prevent re-populating if already filled
                if (doctorFilterSelect.options.length > 1) return;

                try {
                    const response = await fetch('?fetch=doctors_for_scheduling'); // Reusing existing API endpoint
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    result.data.forEach(doctor => {
                        doctorFilterSelect.innerHTML += `<option value="${doctor.id}">${doctor.name} (${doctor.display_user_id})</option>`;
                    });
                } catch (error) {
                    console.error("Failed to fetch doctors for filter:", error);
                }
            };

            const fetchAppointments = async (doctorId = 'all') => {
                const tableBody = document.getElementById('appointments-table-body');
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Loading appointments...</td></tr>`;
                try {
                    const response = await fetch(`?fetch=appointments&doctor_id=${doctorId}`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    if (result.data.length > 0) {
                        tableBody.innerHTML = result.data.map(appt => {
                            const status = appt.status.charAt(0).toUpperCase() + appt.status.slice(1);
                            return `
                <tr>
                    <td>${appt.id}</td>
                    <td>${appt.patient_name} (${appt.patient_display_id})</td>
                    <td>${appt.doctor_name}</td>
                    <td>${new Date(appt.appointment_date).toLocaleString()}</td>
                    <td><span class="status-badge ${appt.status.toLowerCase()}">${status}</span></td>
                </tr>
            `}).join('');
                    } else {
                        tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No appointments found.</td></tr>`;
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Failed to load appointments: ${error.message}</td></tr>`;
                }
            };

            // --- USER MANAGEMENT (CRUD & Detail View) ---
            const userModal = document.getElementById('user-modal');
            const userForm = document.getElementById('user-form');
            const userDetailModal = document.getElementById('user-detail-modal');
            const addUserBtn = document.getElementById('add-user-btn');
            const quickAddUserBtn = document.getElementById('quick-add-user-btn');
            const quickSendNotificationBtn = document.querySelector('.quick-actions .action-btn[href="#"] i.fa-bullhorn').parentElement;

            quickSendNotificationBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // Find and click the sidebar link for notifications
                document.querySelector('.nav-link[data-target="notifications"]').click();
            });
            // Restrict year in Date of Birth to 4 digits
            const dobInput = document.getElementById('date_of_birth');
            dobInput.addEventListener('input', function () {
                // The value is in 'YYYY-MM-DD' format. We check the year part.
                if (this.value.length > 0) {
                    const year = this.value.split('-')[0];
                    if (year.length > 4) {
                        this.value = year.slice(0, 4) + this.value.substring(year.length);
                    }
                }
            });
            const modalTitle = document.getElementById('modal-title');
            const passwordGroup = document.getElementById('password-group');
            const activeGroup = document.getElementById('active-group');
            const roleSelect = document.getElementById('role');
            const doctorFields = document.getElementById('doctor-fields');
            const staffFields = document.getElementById('staff-fields');

            const openDetailedProfileModal = async (userId) => {
                const contentDiv = document.getElementById('user-detail-content');
                contentDiv.innerHTML = '<p>Loading profile...</p>';
                userDetailModal.classList.add('show');
                try {
                    const response = await fetch(`?fetch=user_details&id=${userId}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    const { user, activity, assigned_patients } = result.data;
                    const pfpPath = `uploads/profile_pictures/${user.profile_picture || 'default.png'}`;

                    let roleSpecificTabs = '';
                    let roleSpecificContent = '';

                    if (user.role === 'doctor') {
                        roleSpecificTabs = `<button class="detail-tab-button" data-tab="patients">Assigned Patients</button>`;
                        roleSpecificContent = `<div id="patients-tab" class="detail-tab-content">
                        <h3>Assigned Patients</h3>
                        ${assigned_patients.length > 0 ? assigned_patients.map(p => `<p>${p.name} (${p.display_user_id}) - Last Appointment: ${new Date(p.appointment_date).toLocaleDateString()}</p>`).join('') : '<p>No patients assigned.</p>'}
                    </div>`;
                    }

                    contentDiv.innerHTML = `
                    <div class="user-detail-header">
                        <img src="${pfpPath}" alt="Profile Picture" class="user-detail-pfp" onerror="this.src='uploads/profile_pictures/default.png'">
                        <div class="user-detail-info">
                            <h4>${user.name}</h4>
                            <p>${user.username} (${user.display_user_id})</p>
                            <p>${user.email}</p>
                        </div>
                    </div>
                    <div class="detail-tabs">
                        <button class="detail-tab-button active" data-tab="activity">Activity Log</button>
                        ${roleSpecificTabs}
                    </div>
                    <div id="activity-tab" class="detail-tab-content active">
                        <h3>Recent Activity</h3>
                        <div id="user-detail-activity-log">
                        ${activity.length > 0 ? activity.map(log => {
                        let iconClass = 'fa-plus';
                        if (log.action.includes('update')) iconClass = 'fa-pencil-alt';
                        if (log.action.includes('delete') || log.action.includes('deactivate')) iconClass = 'fa-trash-alt';
                        return `<div class="log-item">
                                <div class="log-icon"><i class="fas ${iconClass}"></i></div>
                                <div class="log-details">
                                    <p>${log.details}</p>
                                    <div class="log-meta">${new Date(log.created_at).toLocaleString()}</div>
                                </div>
                            </div>`
                    }).join('') : '<p>No activity recorded for this user.</p>'}
                        </div>
                    </div>
                    ${roleSpecificContent}
                `;

                    // Add event listeners for the new tabs
                    contentDiv.querySelectorAll('.detail-tab-button').forEach(button => {
                        button.addEventListener('click', () => {
                            const tabId = button.dataset.tab;
                            contentDiv.querySelectorAll('.detail-tab-button').forEach(btn => btn.classList.remove('active'));
                            contentDiv.querySelectorAll('.detail-tab-content').forEach(content => content.classList.remove('active'));
                            button.classList.add('active');
                            document.getElementById(`${tabId}-tab`).classList.add('active');
                        });
                    });
                } catch (error) {
                    contentDiv.innerHTML = `<p style="color:var(--danger-color);">Failed to load profile: ${error.message}</p>`;
                }
            };

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

                    // At the top of the edit mode block
                    const removePfpBtn = document.getElementById('remove-pfp-btn');
                    removePfpBtn.style.display = 'none'; // Hide by default

                    // ... inside the edit block ...
                    if (user.profile_picture && user.profile_picture !== 'default.png') {
                        removePfpBtn.style.display = 'block';
                        removePfpBtn.onclick = async () => {
                            const confirmed = await showConfirmation('Remove Picture', `Are you sure you want to remove the profile picture for ${user.username}?`);
                            if (confirmed) {
                                const formData = new FormData();
                                formData.append('action', 'removeProfilePicture');
                                formData.append('id', user.id);
                                formData.append('csrf_token', csrfToken);
                                handleFormSubmit(formData, `users-${currentRole}`);
                                closeModal(userModal); // Close the modal after action
                            }
                        };
                    }
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
            userDetailModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(userDetailModal));
            userDetailModal.addEventListener('click', (e) => { if (e.target === userDetailModal) closeModal(userDetailModal); });

            const fetchUsers = async (role, searchTerm = '') => {
                currentRole = role;
                document.getElementById('user-table-title').textContent = `${role.charAt(0).toUpperCase() + role.slice(1)}s`;
                const tableBody = document.getElementById('user-table-body');
                tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Loading...</td></tr>`;

                try {
                    let fetchUrl = `?fetch=users&role=${role}`;
                    if (searchTerm) {
                        fetchUrl += `&search=${encodeURIComponent(searchTerm)}`;
                    }
                    const response = await fetch(fetchUrl);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    if (result.data.length > 0) {
                        tableBody.innerHTML = result.data.map(user => `
                        <tr class="clickable-row" data-user-id="${user.id}">
                            <td>
                                <div style="display: flex; align-items: center;">
                                    <img src="uploads/profile_pictures/${user.profile_picture || 'default.png'}" alt="pfp" class="user-list-pfp" onerror="this.onerror=null;this.src='uploads/profile_pictures/default.png';">
                                    ${user.name || 'N/A'}
                                </div>
                            </td>
                            <td>${user.display_user_id || 'N/A'}</td>
                            <td>${user.username}</td>
                            <td>${user.email}</td>
                            <td>${user.phone || 'N/A'}</td>
                            <td><span class="status-badge ${user.active == 1 ? 'active' : 'inactive'}">${user.active == 1 ? 'Active' : 'Inactive'}</span></td>
                            <td>${new Date(user.created_at).toLocaleDateString()}</td>
                            <td class="action-buttons">
                                <button class="btn-edit" data-user='${JSON.stringify(user)}' title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete" data-user='${JSON.stringify(user)}' title="Deactivate"><i class="fas fa-trash-alt"></i></button>
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
                const row = e.target.closest('tr');
                if (!row) return;

                const editBtn = e.target.closest('.btn-edit');
                const deleteBtn = e.target.closest('.btn-delete');

                if (editBtn) {
                    e.stopPropagation(); // Prevent row click from triggering
                    const user = JSON.parse(editBtn.dataset.user);
                    openUserModal('edit', user);
                    return;
                }

                if (deleteBtn) {
                    e.stopPropagation(); // Prevent row click from triggering
                    const user = JSON.parse(deleteBtn.dataset.user);
                    const confirmed = await showConfirmation('Deactivate User', `Are you sure you want to deactivate ${user.username}?`);
                    if (confirmed) {
                        const formData = new FormData();
                        formData.append('action', 'deleteUser');
                        formData.append('id', user.id);
                        formData.append('csrf_token', csrfToken);
                        handleFormSubmit(formData, `users-${currentRole}`);
                    }
                    return;
                }

                // If no button was clicked, it's a row click
                if (row.classList.contains('clickable-row')) {
                    const userId = row.dataset.userId;
                    openDetailedProfileModal(userId);
                }
            });

            const handleFormSubmit = async (formData, refreshTarget = null) => {
                try {
                    const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (result.success) {
                        showNotification(result.message, 'success');

                        // Check if a notification was sent and update the count immediately
                        const action = formData.get('action');
                        if (action === 'sendNotification' || action === 'sendIndividualNotification') {
                            updateNotificationCount();
                        }

                        if (formData.get('action') === 'addUser' || formData.get('action') === 'updateUser') closeModal(userModal);
                        else if (formData.get('action').toLowerCase().includes('medicine')) closeModal(medicineModal);
                        else if (formData.get('action').toLowerCase().includes('blood')) closeModal(bloodModal);
                        else if (formData.get('action').toLowerCase().includes('ward')) closeModal(wardFormModal);
                        else if (formData.get('action').toLowerCase().includes('bed')) closeModal(bedModal);
                        else if (formData.get('action').toLowerCase().includes('room')) closeModal(document.getElementById('room-modal'));

                        if (refreshTarget) {
                            if (refreshTarget.startsWith('users-')) fetchUsers(refreshTarget.split('-')[1]);
                            else if (refreshTarget === 'blood') fetchBloodInventory();
                            else if (refreshTarget === 'departments_management') { closeModal(departmentModal); fetchDepartmentsManagement(); }
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

            const systemSettingsForm = document.getElementById('system-settings-form');
            if (systemSettingsForm) {
                systemSettingsForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const confirmed = await showConfirmation('Update Settings', 'Are you sure you want to save these system settings? This may affect system functionality like sending emails.');
                    if (confirmed) {
                        const formData = new FormData(systemSettingsForm);
                        handleFormSubmit(formData);
                        // Clear the password field for security after submission
                        document.getElementById('gmail_app_password').value = '';
                    }
                });
            }

            // --- INVENTORY MANAGEMENT ---

            // Medicine Inventory
            const medicineModal = document.getElementById('medicine-modal');
            const medicineForm = document.getElementById('medicine-form');
            const addMedicineBtn = document.getElementById('add-medicine-btn');
            const medicineTableBody = document.getElementById('medicine-table-body');


            const departmentModal = document.getElementById('department-modal');
            const departmentForm = document.getElementById('department-form');
            const addDepartmentBtn = document.getElementById('add-department-btn');
            const departmentTableBody = document.getElementById('department-table-body');

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

            // --- Department Management ---
            const openDepartmentModal = (mode, department = {}) => {
                departmentForm.reset();
                if (mode === 'add') {
                    document.getElementById('department-modal-title').textContent = 'Add New Department';
                    document.getElementById('department-form-action').value = 'addDepartment';
                    document.getElementById('department-active-group').style.display = 'none';
                } else {
                    document.getElementById('department-modal-title').textContent = `Edit ${department.name}`;
                    document.getElementById('department-form-action').value = 'updateDepartment';
                    document.getElementById('department-id').value = department.id;
                    document.getElementById('department-name').value = department.name;
                    document.getElementById('department-is-active').value = department.is_active;
                    document.getElementById('department-active-group').style.display = 'block';
                }
                departmentModal.classList.add('show');
            };

            addDepartmentBtn.addEventListener('click', () => openDepartmentModal('add'));
            departmentModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(departmentModal));
            departmentModal.addEventListener('click', (e) => { if (e.target === departmentModal) closeModal(departmentModal); });

            departmentForm.addEventListener('submit', (e) => {
                e.preventDefault();
                handleFormSubmit(new FormData(departmentForm), 'departments_management');
            });

            const fetchDepartmentsManagement = async () => {
                departmentTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center;">Loading...</td></tr>`;
                try {
                    const response = await fetch('?fetch=departments_management');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    if (result.data.length > 0) {
                        departmentTableBody.innerHTML = result.data.map(dept => `
                        <tr data-department='${JSON.stringify(dept)}'>
                            <td>${dept.name}</td>
                            <td><span class="status-badge ${dept.is_active == 1 ? 'active' : 'inactive'}">${dept.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                            <td class="action-buttons">
                                <button class="btn-edit-department btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-department btn-delete" title="Disable"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    `).join('');
                    } else {
                        departmentTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center;">No departments found.</td></tr>`;
                    }
                } catch (error) {
                    departmentTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; color: var(--danger-color);">Failed to load departments.</td></tr>`;
                }
            };

            departmentTableBody.addEventListener('click', async (e) => {
                const row = e.target.closest('tr');
                if (!row) return;
                const department = JSON.parse(row.dataset.department);
                if (e.target.closest('.btn-edit-department')) {
                    openDepartmentModal('edit', department);
                }
                if (e.target.closest('.btn-delete-department')) {
                    const confirmed = await showConfirmation('Disable Department', `Are you sure you want to disable the "${department.name}" department?`);
                    if (confirmed) {
                        const formData = new FormData();
                        formData.append('action', 'deleteDepartment');
                        formData.append('id', department.id);
                        formData.append('csrf_token', csrfToken);
                        handleFormSubmit(formData, 'departments_management');
                    }
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
                    if (wardsResult.success) {
                        wardsResult.data.forEach(ward => wardSelect.innerHTML += `<option value="${ward.id}">${ward.name}</option>`);
                    }

                    bedPatientSelect.innerHTML = '<option value="">Select Patient</option>';
                    if (patientsResult.success) {
                        patientsResult.data.forEach(patient => bedPatientSelect.innerHTML += `<option value="${patient.id}">${patient.name} (${patient.display_user_id})</option>`);
                    }
                } catch (error) {
                    console.error('Failed to populate dropdowns:', error);
                }
            };

            const populateDoctorDropdowns = async (selectElement) => {
                try {
                    const response = await fetch('?fetch=doctors_for_scheduling');
                    const result = await response.json();

                    selectElement.innerHTML = '<option value="">Select Doctor</option>';
                    if (result.success) {
                        result.data.forEach(doctor => {
                            selectElement.innerHTML += `<option value="${doctor.id}">${doctor.name} (${doctor.display_user_id})</option>`;
                        });
                    }
                } catch (error) {
                    console.error('Failed to populate doctor dropdown:', error);
                }
            };

            bedStatusSelect.addEventListener('change', () => {
                const showPatient = bedStatusSelect.value === 'occupied' || bedStatusSelect.value === 'reserved';
                bedPatientGroup.style.display = showPatient ? 'block' : 'none';
                bedPatientSelect.required = showPatient;
            });

            const bedDoctorGroup = document.getElementById('bed-doctor-group');
            const bedDoctorSelect = document.getElementById('bed-doctor-id');

            bedStatusSelect.addEventListener('change', () => {
                const showPatient = bedStatusSelect.value === 'occupied' || bedStatusSelect.value === 'reserved';
                bedPatientGroup.style.display = showPatient ? 'block' : 'none';
                bedPatientSelect.required = showPatient;
                // Show doctor dropdown only when occupied
                bedDoctorGroup.style.display = bedStatusSelect.value === 'occupied' ? 'block' : 'none';
                bedDoctorSelect.required = bedStatusSelect.value === 'occupied';
            });

            const openBedModal = async (mode, bed = {}) => {
                bedForm.reset();
                await Promise.all([populateBedDropdowns(), populateDoctorDropdowns(bedDoctorSelect)]); // Fetch doctors
                document.getElementById('bed-modal-title').textContent = mode === 'add' ? 'Add New Bed' : `Edit Bed ${bed.bed_number}`;
                document.getElementById('bed-form-action').value = mode === 'add' ? 'addBed' : 'updateBed';

                bedPatientGroup.style.display = 'none';
                bedDoctorGroup.style.display = 'none';
                bedPatientSelect.required = false;
                bedDoctorSelect.required = false;

                if (mode === 'edit') {
                    document.getElementById('bed-id').value = bed.id;
                    setTimeout(() => { // Use timeout to ensure dropdowns are populated
                        document.getElementById('bed-ward-id').value = bed.ward_id;
                        document.getElementById('bed-number').value = bed.bed_number;
                        document.getElementById('bed-status').value = bed.status;

                        const showPatient = bed.status === 'occupied' || bed.status === 'reserved';
                        if (showPatient) {
                            bedPatientGroup.style.display = 'block';
                            bedPatientSelect.required = true;
                            document.getElementById('bed-patient-id').value = bed.patient_id || '';
                        }
                        if (bed.status === 'occupied') {
                            bedDoctorGroup.style.display = 'block';
                            bedDoctorSelect.required = true;
                            document.getElementById('bed-doctor-id').value = bed.doctor_id || '';
                        }
                    }, 150);
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
                            // PASTE YOUR SNIPPET HERE, REPLACING THE OLD ONE
                            let patientInfo = '';
                            if (bed.status === 'occupied' && bed.patient_name) {
                                let doctorInfo = bed.doctor_name ? `<br><small>Doctor: ${bed.doctor_name}</small>` : '';
                                patientInfo = `<div class="patient-info">Occupied by: ${bed.patient_name}${doctorInfo}</div>`;
                            } else if (bed.status === 'reserved' && bed.patient_name) {
                                patientInfo = `<div class="patient-info">Reserved for: ${bed.patient_name}</div>`;
                            }

                            // THIS IS THE CODE THAT COMES AFTER YOUR SNIPPET
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
                    if (result.success) {
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

            const roomDoctorGroup = document.getElementById('room-doctor-group');
            const roomDoctorSelect = document.getElementById('room-doctor-id');

            roomStatusSelect.addEventListener('change', () => {
                const showPatient = roomStatusSelect.value === 'occupied' || roomStatusSelect.value === 'reserved';
                roomPatientGroup.style.display = showPatient ? 'block' : 'none';
                roomPatientSelect.required = showPatient;
                // Show doctor dropdown only when occupied
                roomDoctorGroup.style.display = roomStatusSelect.value === 'occupied' ? 'block' : 'none';
                roomDoctorSelect.required = roomStatusSelect.value === 'occupied';
            });

            const openRoomModal = async (mode, room = {}) => {
                roomForm.reset();
                await Promise.all([populateRoomDropdowns(), populateDoctorDropdowns(roomDoctorSelect)]); // Fetch doctors
                document.getElementById('room-modal-title').textContent = mode === 'add' ? 'Add New Room' : `Edit Room ${room.room_number}`;
                document.getElementById('room-form-action').value = mode === 'add' ? 'addRoom' : 'updateRoom';

                roomPatientGroup.style.display = 'none';
                roomDoctorGroup.style.display = 'none';
                roomPatientSelect.required = false;
                roomDoctorSelect.required = false;

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
                    if (room.status === 'occupied') {
                        roomDoctorGroup.style.display = 'block';
                        roomDoctorSelect.required = true;
                        document.getElementById('room-doctor-id').value = room.doctor_id || '';
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
                            // PASTE YOUR SNIPPET HERE (adapted for rooms)
                            let patientInfo = '';
                            if (room.status === 'occupied' && room.patient_name) {
                                let doctorInfo = room.doctor_name ? `<br><small>Doctor: ${room.doctor_name}</small>` : '';
                                patientInfo = `<div class="patient-info">Occupied by: ${room.patient_name}${doctorInfo}</div>`;
                            } else if (room.status === 'reserved' && room.patient_name) {
                                patientInfo = `<div class="patient-info">Reserved for: ${room.patient_name}</div>`;
                            }

                            // THIS IS THE CODE THAT COMES AFTER YOUR SNIPPET
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

            // --- REPORTING ---
            const generateReportBtn = document.getElementById('generate-report-btn');
            const downloadPdfForm = document.getElementById('download-pdf-form');
            const summaryCardsContainer = document.getElementById('report-summary-cards');

            const generateReport = async () => {
                const reportType = document.getElementById('report-type').value;
                const period = document.getElementById('report-period').value;

                // Update PDF download form
                document.getElementById('pdf-report-type').value = reportType;
                document.getElementById('pdf-period').value = period;
                summaryCardsContainer.innerHTML = '<p>Loading summary...</p>';
                document.getElementById('report-table-container').innerHTML = ''; // Clear old table

                try {
                    const response = await fetch(`?fetch=report&type=${reportType}&period=${period}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    const { summary, chartData, tableData } = result.data;

                    // Update Summary Cards
                    summaryCardsContainer.innerHTML = ''; // Clear previous cards
                    if (reportType === 'financial') {
                        summaryCardsContainer.innerHTML = `
                        <div class="summary-card"><span class="label">Total Revenue</span><span class="value">₹${parseFloat(summary.total_revenue || 0).toLocaleString('en-IN')}</span></div>
                        <div class="summary-card"><span class="label">Total Refunds</span><span class="value">₹${parseFloat(summary.total_refunds || 0).toLocaleString('en-IN')}</span></div>
                        <div class="summary-card"><span class="label">Net Revenue</span><span class="value">₹${(parseFloat(summary.total_revenue || 0) - parseFloat(summary.total_refunds || 0)).toLocaleString('en-IN')}</span></div>
                        <div class="summary-card"><span class="label">Transactions</span><span class="value">${summary.total_transactions || 0}</span></div>
                    `;
                    } else if (reportType === 'patient') {
                        summaryCardsContainer.innerHTML = `
                        <div class="summary-card"><span class="label">Total Appointments</span><span class="value">${summary.total_appointments || 0}</span></div>
                        <div class="summary-card"><span class="label">Completed</span><span class="value">${summary.completed || 0}</span></div>
                        <div class="summary-card"><span class="label">Cancelled</span><span class="value">${summary.cancelled || 0}</span></div>
                    `;
                    } else if (reportType === 'resource') {
                        const occupancy_rate = summary.total_beds > 0 ? ((summary.occupied_beds / summary.total_beds) * 100).toFixed(1) : 0;
                        summaryCardsContainer.innerHTML = `
                        <div class="summary-card"><span class="label">Occupied Beds</span><span class="value">${summary.occupied_beds || 0} / ${summary.total_beds || 0}</span></div>
                        <div class="summary-card"><span class="label">Bed Occupancy Rate</span><span class="value">${occupancy_rate}%</span></div>
                        <div class="summary-card"><span class="label">Occupied Rooms</span><span class="value">${summary.occupied_rooms || 0} / ${summary.total_rooms || 0}</span></div>
                    `;
                    }

                    // Render Chart
                    const chartCtx = document.getElementById('report-chart').getContext('2d');
                    if (reportChart) {
                        reportChart.destroy();
                    }

                    const labels = chartData.map(item => item.label);
                    const data = chartData.map(item => item.value);
                    const chartLabel = reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Trend';

                    reportChart = new Chart(chartCtx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: chartLabel,
                                data: data,
                                borderColor: 'var(--primary-color)',
                                backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                fill: true,
                                tension: 0.4
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { beginAtZero: true },
                            }
                        }
                    });

                    // Render Table
                    const tableContainer = document.getElementById('report-table-container');
                    if (tableData.length > 0) {
                        const headers = Object.keys(tableData[0]);
                        const tableHTML = `
                            <h3 style="margin-top: 2.5rem; margin-bottom: 1.5rem;">Detailed Report Data</h3>
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            ${headers.map(h => `<th>${h.replace(/_/g, ' ').toUpperCase()}</th>`).join('')}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${tableData.map(row => `
                                            <tr>
                                                ${headers.map(h => `<td>${row[h]}</td>`).join('')}
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `;
                        tableContainer.innerHTML = tableHTML;
                    }


                } catch (error) {
                    showNotification('Failed to generate report: ' + error.message, 'error');
                    summaryCardsContainer.innerHTML = `<p style="color: var(--danger-color);">Could not load report summary.</p>`;
                }
            };

            const reportPeriodSelect = document.getElementById('report-period');
            const yearContainer = document.getElementById('report-year-container');
            const monthContainer = document.getElementById('report-month-container');
            const dayContainer = document.getElementById('report-day-container');

            reportPeriodSelect.addEventListener('change', () => {
                const period = reportPeriodSelect.value;
                yearContainer.style.display = 'none';
                monthContainer.style.display = 'none';
                dayContainer.style.display = 'none';

                if (period === 'yearly') {
                    yearContainer.style.display = 'block';
                } else if (period === 'monthly') {
                    monthContainer.style.display = 'block';
                } else if (period === 'daily') {
                    dayContainer.style.display = 'block';
                }
            });

            // Trigger change event on load to set the initial correct view
            reportPeriodSelect.dispatchEvent(new Event('change'));

            generateReportBtn.addEventListener('click', generateReport);

            // --- ACTIVITY LOG (AUDIT TRAIL) ---
            const activityLogContainer = document.getElementById('activity-log-container');
            const refreshLogsBtn = document.getElementById('refresh-logs-btn');

            const fetchActivityLogs = async () => {
                activityLogContainer.innerHTML = '<p style="text-align: center;">Loading logs...</p>';
                try {
                    const response = await fetch(`?fetch=activity&limit=50`);
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    if (result.data.length > 0) {
                        activityLogContainer.innerHTML = result.data.map(log => {
                            let iconClass = 'fa-plus';
                            let iconBgClass = 'create';
                            if (log.action.includes('update')) {
                                iconClass = 'fa-pencil-alt';
                                iconBgClass = 'update';
                            } else if (log.action.includes('delete') || log.action.includes('deactivate')) {
                                iconClass = 'fa-trash-alt';
                                iconBgClass = 'delete';
                            }

                            const time = new Date(log.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });

                            return `
                        <div class="log-item">
                            <div class="log-icon ${iconBgClass}"><i class="fas ${iconClass}"></i></div>
                            <div class="log-details">
                                <p>${log.details}</p>
                                <div class="log-meta">
                                    By: <strong>${log.admin_username}</strong> on ${time}
                                </div>
                            </div>
                        </div>
                        `;
                        }).join('');
                    } else {
                        activityLogContainer.innerHTML = `<p style="text-align: center;">No recent activity found.</p>`;
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    activityLogContainer.innerHTML = `<p style="text-align: center; color: var(--danger-color);">Failed to load activity logs.</p>`;
                }
            };

            refreshLogsBtn.addEventListener('click', fetchActivityLogs);

            // --- SCHEDULES PANEL LOGIC ---
            const schedulesPanel = document.getElementById('schedules-panel');
            const doctorSelect = document.getElementById('doctor-select');
            const scheduleEditorContainer = document.getElementById('doctor-schedule-editor');
            const saveScheduleBtn = document.getElementById('save-schedule-btn');

            const fetchDoctorsForScheduling = async () => {
                try {
                    const response = await fetch('?fetch=doctors_for_scheduling');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    doctorSelect.innerHTML = '<option value="">Select a Doctor...</option>';
                    result.data.forEach(doctor => {
                        doctorSelect.innerHTML += `<option value="${doctor.id}">${doctor.name} (${doctor.display_user_id})</option>`;
                    });
                } catch (error) {
                    console.error("Failed to fetch doctors:", error);
                    doctorSelect.innerHTML = '<option value="">Could not load doctors</option>';
                }
            };

            const renderScheduleEditor = (slots) => {
                const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                scheduleEditorContainer.innerHTML = days.map(day => `
    <div class="day-schedule-card" data-day="${day}">
        <h4>${day}</h4>
        <div class="time-slots-grid">
            ${(slots[day] || []).map(slot => `
                <div class="time-slot">
                    <label>From:</label>
                    <input type="time" class="slot-from" value="${slot.from}" />
                    <label>To:</label>
                    <input type="time" class="slot-to" value="${slot.to}" />
                    <button class="remove-slot-btn" title="Remove slot"><i class="fas fa-times"></i></button>
                </div>
            `).join('')}
        </div>
        <button class="add-slot-btn"><i class="fas fa-plus"></i> Add Slot</button>
    </div>
`).join('');
                document.querySelector('.schedule-actions').style.display = 'block';
            };

            const fetchDoctorSchedule = async (doctorId) => {
                if (!doctorId) {
                    scheduleEditorContainer.innerHTML = '<p class="placeholder-text">Please select a doctor to view or edit their schedule.</p>';
                    document.querySelector('.schedule-actions').style.display = 'none';
                    return;
                }
                scheduleEditorContainer.innerHTML = '<p class="placeholder-text">Loading schedule...</p>';
                try {
                    const response = await fetch(`?fetch=fetch_doctor_schedule&doctor_id=${doctorId}`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);
                    renderScheduleEditor(result.data);
                } catch (error) {
                    scheduleEditorContainer.innerHTML = `<p class="placeholder-text" style="color:var(--danger-color)">Failed to load schedule: ${error.message}</p>`;
                }
            };

            doctorSelect.addEventListener('change', () => {
                fetchDoctorSchedule(doctorSelect.value);
            });

            scheduleEditorContainer.addEventListener('click', (e) => {
                if (e.target.closest('.add-slot-btn')) {
                    const grid = e.target.closest('.day-schedule-card').querySelector('.time-slots-grid');
                    const slotDiv = document.createElement('div');
                    slotDiv.className = 'time-slot';
                    slotDiv.innerHTML = `
        <label>From:</label>
        <input type="time" class="slot-from" value="09:00" />
        <label>To:</label>
        <input type="time" class="slot-to" value="13:00" />
        <button class="remove-slot-btn" title="Remove slot"><i class="fas fa-times"></i></button>
    `;
                    grid.appendChild(slotDiv);
                }
                if (e.target.closest('.remove-slot-btn')) {
                    e.target.closest('.time-slot').remove();
                }
            });

            saveScheduleBtn.addEventListener('click', async () => {
                const doctorId = doctorSelect.value;
                if (!doctorId) {
                    showNotification('Please select a doctor first.', 'error');
                    return;
                }

                const scheduleData = {};
                let isValid = true;
                document.querySelectorAll('.day-schedule-card').forEach(dayCard => {
                    const day = dayCard.dataset.day;
                    const slots = [];
                    dayCard.querySelectorAll('.time-slot').forEach(slotElement => {
                        const from = slotElement.querySelector('.slot-from').value;
                        const to = slotElement.querySelector('.slot-to').value;
                        if (from && to) {
                            if (to <= from) {
                                showNotification(`'To' time must be after 'From' time for a slot on ${day}.`, 'error');
                                isValid = false;
                            }
                            slots.push({ from, to });
                        }
                    });
                    scheduleData[day] = slots;
                });

                if (!isValid) return; // Stop if there's a time validation error

                const formData = new FormData();
                formData.append('action', 'update_doctor_schedule');
                formData.append('doctor_id', doctorId);
                formData.append('slots', JSON.stringify(scheduleData));
                formData.append('csrf_token', csrfToken);

                try {
                    const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (result.success) {
                        showNotification(result.message, 'success');
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    showNotification(`Error saving schedule: ${error.message}`, 'error');
                }
            });

            const fetchStaffShifts = async () => {
                const staffTableBody = document.getElementById('staff-shifts-table-body');
                staffTableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading staff shifts...</td></tr>';
                try {
                    const response = await fetch('?fetch=staff_for_shifting');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    if (result.data.length > 0) {
                        staffTableBody.innerHTML = result.data.map(staff => `
                    <tr data-staff-id="${staff.id}">
                        <td>${staff.name}</td>
                        <td>${staff.display_user_id}</td>
                        <td id="shift-status-${staff.id}">${staff.shift}</td>
                        <td>
                            <select class="shift-select" data-id="${staff.id}">
                                <option value="day" ${staff.shift === 'day' ? 'selected' : ''}>Day</option>
                                <option value="night" ${staff.shift === 'night' ? 'selected' : ''}>Night</option>
                                <option value="off" ${staff.shift === 'off' ? 'selected' : ''}>Off</option>
                            </select>
                        </td>
                    </tr>
                `).join('');
                    } else {
                        staffTableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No active staff found.</td></tr>';
                    }
                } catch (error) {
                    staffTableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color: var(--danger-color);">Failed to load shifts: ${error.message}</td></tr>`;
                }
            };

            // Tab switching logic for the Schedules panel
            schedulesPanel.querySelectorAll('.schedule-tab-button').forEach(button => {
                button.addEventListener('click', function () {
                    const tabId = this.dataset.tab;

                    schedulesPanel.querySelectorAll('.schedule-tab-button').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    schedulesPanel.querySelectorAll('.schedule-tab-content').forEach(content => content.classList.remove('active'));
                    document.getElementById(`${tabId}-content`).classList.add('active');

                    // Fetch data if the tab is being opened for the first time or needs refresh
                    if (tabId === 'doctor-availability' && doctorSelect.options.length <= 1) {
                        fetchDoctorsForScheduling();
                    } else if (tabId === 'staff-shifts') {
                        // Future implementation: fetchStaffShifts();
                        fetchStaffShifts();
                    }
                });
            });

            document.getElementById('staff-shifts-table-body').addEventListener('change', async (e) => {
                if (e.target.classList.contains('shift-select')) {
                    const staffId = e.target.dataset.id;
                    const newShift = e.target.value;

                    const formData = new FormData();
                    formData.append('action', 'update_staff_shift');
                    formData.append('staff_id', staffId);
                    formData.append('shift', newShift);
                    formData.append('csrf_token', csrfToken);

                    try {
                        const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        if (result.success) {
                            showNotification(result.message, 'success');
                            document.getElementById(`shift-status-${staffId}`).textContent = newShift;
                        } else {
                            throw new Error(result.message);
                        }
                    } catch (error) {
                        showNotification(`Error: ${error.message}`, 'error');
                        fetchStaffShifts();
                    }
                }
            });


            // --- NOTIFICATIONS PANEL LOGIC ---
            const notificationsPanel = document.getElementById('notifications-panel');
            const notificationForm = document.getElementById('notification-form');
            const individualNotificationForm = document.getElementById('individual-notification-form');
            const recipientSelect = document.getElementById('recipient-user-id');

            const fetchAllUsersForNotifications = async () => {
                try {
                    const response = await fetch(`?fetch=users&role=all_users`);
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    recipientSelect.innerHTML = '<option value="">Select a user...</option>';
                    result.data.forEach(user => {
                        recipientSelect.innerHTML += `<option value="${user.id}">${user.name} (${user.display_user_id}) - ${user.role}</option>`;
                    });

                } catch (error) {
                    recipientSelect.innerHTML = '<option value="">Failed to load users</option>';
                    console.error("Failed to fetch users for notifications:", error);
                }
            };

            notificationsPanel.querySelectorAll('.schedule-tab-button').forEach(button => {
                button.addEventListener('click', function () {
                    const tabId = this.dataset.tab;

                    notificationsPanel.querySelectorAll('.schedule-tab-button').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    notificationsPanel.querySelectorAll('.schedule-tab-content').forEach(content => content.classList.remove('active'));
                    document.getElementById(`${tabId}-content`).classList.add('active');

                    if (tabId === 'individual' && recipientSelect.options.length <= 1) {
                        fetchAllUsersForNotifications();
                    }
                });
            });

            notificationForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(notificationForm);
                const role = formData.get('role');
                const confirmed = await showConfirmation('Send Notification', `Are you sure you want to send this broadcast message to all ${role}s?`);
                if (confirmed) {
                    handleFormSubmit(formData);
                    notificationForm.reset();
                }
            });

            individualNotificationForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(individualNotificationForm);
                const recipientName = document.getElementById('user-search').value;
                if (!formData.get('recipient_user_id')) {
                    showNotification('Please select a valid user from the search results.', 'error');
                    return;
                }
                const confirmed = await showConfirmation('Send Message', `Are you sure you want to send this message to ${recipientName}?`);
                if (confirmed) {
                    handleFormSubmit(formData);
                    individualNotificationForm.reset();
                }
            });
            // --- NOTIFICATION CENTER LOGIC ---
            const notificationBell = document.getElementById('notification-bell-wrapper');
            const notificationCountBadge = document.getElementById('notification-count');
            const allNotificationsPanel = document.getElementById('all-notifications-panel');

            const updateNotificationCount = async () => {
                try {
                    const response = await fetch('?fetch=unread_notification_count');
                    const result = await response.json();
                    if (result.success && result.count > 0) {
                        notificationCountBadge.textContent = result.count;
                        notificationCountBadge.style.display = 'grid';
                    } else {
                        notificationCountBadge.style.display = 'none';
                    }
                } catch (error) {
                    console.error('Failed to fetch notification count:', error);
                }
            };

            const loadAllNotifications = async () => {
                allNotificationsPanel.innerHTML = '<p style="text-align: center; padding: 2rem;">Loading messages...</p>';
                try {
                    const response = await fetch('?fetch=all_notifications');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message);

                    let content = `
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1rem;">
                            <h2 style="margin: 0;">All Notifications</h2>
                        </div>
                    `;

                    if (result.data.length > 0) {
                        result.data.forEach(notif => {
                            const isUnread = notif.is_read == 0;
                            const itemStyle = isUnread ? 'background-color: var(--bg-grey);' : '';

                            content += `
                                <div class="notification-item" style="display: flex; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid var(--border-light); ${itemStyle}">
                                    <div style="font-size: 1.5rem; color: var(--primary-color); padding-top: 5px;"><i class="fas fa-envelope-open-text"></i></div>
                                    <div style="flex-grow: 1;">
                                        <p style="margin: 0 0 0.25rem 0; font-weight: ${isUnread ? '600' : '500'};">${notif.message}</p>
                                        <small style="color: var(--text-muted);">From: ${notif.sender_name} on ${new Date(notif.created_at).toLocaleString()}</small>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        content += '<p style="text-align: center; padding: 2rem;">You have no notifications.</p>';
                    }
                    allNotificationsPanel.innerHTML = content;
                } catch (error) {
                    allNotificationsPanel.innerHTML = '<p style="text-align: center; color: var(--danger-color);">Could not load notifications.</p>';
                }
            };

            notificationBell.addEventListener('click', async (e) => {
                e.stopPropagation();

                // Send the request to mark notifications as READ in the background
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_notifications_read');
                    formData.append('csrf_token', csrfToken);

                    const response = await fetch('admin_dashboard.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (result.success) {
                        // If the database is successfully updated, THEN update the UI
                        notificationCountBadge.textContent = '0';
                        notificationCountBadge.style.display = 'none';

                        // Switch to the panel and reload the list to show the new "read" styles
                        handlePanelSwitch(notificationBell);
                        loadAllNotifications();
                    } else {
                        showNotification(result.message || 'Could not mark notifications as read.', 'error');
                        console.error('Server failed to mark notifications as read:', result.message);
                    }
                } catch (error) {
                    showNotification('A network error occurred. Please try again.', 'error');
                    console.error('Error marking notifications as read:', error);
                }
            });

            // Add this event listener to handle dismissing individual notifications
            allNotificationsPanel.addEventListener('click', async (e) => {
                const deleteButton = e.target.closest('.btn-delete-notification');
                if (deleteButton) {
                    const notificationId = deleteButton.dataset.id;
                    const confirmed = await showConfirmation('Dismiss Notification', 'Are you sure you want to permanently dismiss this message?');
                    if (confirmed) {
                        const formData = new FormData();
                        formData.append('action', 'delete_notification');
                        formData.append('notification_id', notificationId);
                        formData.append('csrf_token', csrfToken);

                        // Optimistically remove from UI
                        deleteButton.closest('.notification-item').remove();
                        showNotification('Notification dismissed.', 'success');

                        // Send request to server
                        fetch('admin_dashboard.php', { method: 'POST', body: formData })
                            .then(res => res.json())
                            .then(result => {
                                if (!result.success) {
                                    showNotification('Failed to dismiss on server.', 'error');
                                    // If server fails, reload the list to be accurate
                                    loadAllNotifications();
                                }
                            });
                    }
                }
            });
            // --- INDIVIDUAL NOTIFICATION SEARCH LOGIC ---
            const userSearch = document.getElementById('user-search');
            const userSearchResults = document.getElementById('user-search-results');
            const recipientUserIdInput = document.getElementById('recipient-user-id');

            let searchTimeout;
            userSearch.addEventListener('keyup', () => {
                clearTimeout(searchTimeout);
                const searchTerm = userSearch.value.trim();

                if (searchTerm.length < 2) {
                    userSearchResults.style.display = 'none';
                    return;
                }

                searchTimeout = setTimeout(async () => {
                    try {
                        const response = await fetch(`?fetch=search_users&term=${encodeURIComponent(searchTerm)}`);
                        const result = await response.json();
                        if (!result.success) throw new Error(result.message);

                        if (result.data.length > 0) {
                            userSearchResults.innerHTML = result.data.map(user => `
                                <div class="search-result-item" data-id="${user.id}" data-name="${user.name} (${user.display_user_id})">
                                    <strong>${user.name}</strong> (${user.display_user_id}) - <small>${user.role}</small>
                                </div>
                            `).join('');
                            userSearchResults.style.display = 'block';
                        } else {
                            userSearchResults.innerHTML = '<div class="search-result-item none">No users found.</div>';
                            userSearchResults.style.display = 'block';
                        }
                    } catch (error) {
                        console.error('User search failed:', error);
                        userSearchResults.innerHTML = '<div class="search-result-item none">Search error.</div>';
                        userSearchResults.style.display = 'block';
                    }
                }, 300);
            });

            userSearchResults.addEventListener('click', (e) => {
                const item = e.target.closest('.search-result-item');
                if (item && item.dataset.id) {
                    recipientUserIdInput.value = item.dataset.id;
                    userSearch.value = item.dataset.name;
                    userSearchResults.style.display = 'none';
                }
            });

            // Hide search results if clicking elsewhere
            document.addEventListener('click', (e) => {
                if (!userSearch.contains(e.target) && !userSearchResults.contains(e.target)) {
                    userSearchResults.style.display = 'none';
                }
            });

            document.getElementById('appointment-doctor-filter').addEventListener('change', (e) => {
                fetchAppointments(e.target.value);
            });

            // --- INITIAL LOAD ---
            updateDashboardStats();
            updateNotificationCount();
            fetchDepartments();
            generateReport(); // Generate default report on load
        });
    </script>
</body>

</html>