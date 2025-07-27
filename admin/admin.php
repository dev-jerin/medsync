<?php
// --- CONFIG & SESSION START ---
// Note: This file is included by dashboard.php, so session_start() is already called in config.php.
require_once '../config.php';
require_once '../vendor/autoload.php'; // Autoload Composer dependencies

use Dompdf\Dompdf;
use Dompdf\Options;

// The session security and role check is now in dashboard.php before this file is included.

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
                            $target_dir = "../uploads/profile_pictures/";
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
                                $old_pfp_path = "../uploads/profile_pictures/" . $old_user_data['profile_picture'];
                                if (file_exists($old_pfp_path)) {
                                    unlink($old_pfp_path);
                                }
                            }
                            // --- End delete old picture ---

                            $changes[] = "profile picture";

                            $target_dir = "../uploads/profile_pictures/";

                            $target_dir = "../uploads/profile_pictures/";
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
                        $pfp_path = "../uploads/profile_pictures/" . $user_data['profile_picture'];
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
    $medsync_logo_path = '../images/logo.png';
    $hospital_logo_path = '../images/hospital.png'; // Make sure you have this image
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