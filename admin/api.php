<?php

// --- CONFIG & SESSION START ---
require_once '../config.php'; //loads database connection
require_once '../vendor/autoload.php'; //vendor files for phpmailer and dompdf
require_once '../mail/send_mail.php'; //email sending functionality
require_once '../mail/templates.php'; //email templates

// ---classes from the Dompdf library---
use Dompdf\Dompdf;
use Dompdf\Options;



// --- LOG ACTIVITY STORING ---
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
        error_log("Failed to prepare statement for activity log: " . $conn->error);
        return false;
    }
    $stmt->bind_param("isis", $user_id, $action, $target_user_id, $details);
    return $stmt->execute();
}

// -- DISPLAY ID GENERATION --
/**
 * Generates a unique, sequential display ID for a new user based on their role.
 * Uses a dedicated counter table with row locking to prevent race conditions.
 * e.g., A0001, D0001, S0001, U0001
 *
 * @param string $role The role name of the user ('admin', 'doctor', 'staff', 'user').
 * @param mysqli $conn The database connection object.
 * @return string The formatted display ID.
 * @throws Exception If the role is invalid or a database error occurs.
 */
function generateDisplayId($role_name, $conn)
{
    $prefix_map = ['admin' => 'A','doctor' => 'D','staff' => 'S','user' => 'U' ];

    if (!isset($prefix_map[$role_name])) {
        throw new Exception("Invalid role specified for ID generation.");
    }
    $prefix = $prefix_map[$role_name];

    // Start transaction for safe counter update
    $conn->begin_transaction();
    try {
        // Lock the row for the specific role to prevent race conditions
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE"); //FETCHED LAST ID
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            // If the prefix doesn't exist, create it ie first user.
            $insert_stmt = $conn->prepare("INSERT INTO role_counters (role_prefix, last_id) VALUES (?, 0)");
            $insert_stmt->bind_param("s", $prefix);
            $insert_stmt->execute();
            $new_id_num = 1;
        } else {
            $row = $result->fetch_assoc();
            $new_id_num = $row['last_id'] + 1;
        }


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
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email']) || empty($_POST['role']) || empty($_POST['password']) || empty($_POST['phone']) || empty($_POST['password'])) {
                            throw new Exception('Please fill all required fields.');
                        }
                        $name = $_POST['name'];
                        $username = strtolower($_POST['username']);
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

                        if (!$email)
                        throw new Exception('Invalid email format.');

                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $role_name = $_POST['role'];
                        $phone = $_POST['phone'];
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
                        
                        // Fetch role_id from role_name
                        $role_stmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
                        $role_stmt->bind_param("s", $role_name);
                        $role_stmt->execute();
                        $role_result = $role_stmt->get_result();

                        if($role_result->num_rows === 0) 
                        throw new Exception('Invalid user role specified.');
                        $role_id = $role_result->fetch_assoc()['id'];

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

                        $display_user_id = generateDisplayId($role_name, $conn);

                        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                        $stmt->bind_param("ss", $username, $email);
                        $stmt->execute();
                        if ($stmt->get_result()->num_rows > 0) {
                            throw new Exception('Username or email already exists.');
                        }

                        $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role_id, gender, phone, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("sssssisss", $display_user_id, $name, $username, $email, $password, $role_id, $gender, $phone, $profile_picture);
                        $stmt->execute();
                        $user_id = $conn->insert_id;

                        if ($role_name === 'doctor') {
                            $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
                            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                            $specialty_id = !empty($_POST['specialty_id']) ? (int)$_POST['specialty_id'] : null; // Get specialty_id

                            $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty_id, qualifications, department_id, is_available) VALUES (?, ?, ?, ?, ?)");
                            $stmt_doctor->bind_param("iisii", $user_id, $specialty_id, $_POST['qualifications'], $department_id, $is_available);
                            $stmt_doctor->execute();
                        }elseif ($role_name === 'staff') {
                            $stmt_staff = $conn->prepare("INSERT INTO staff (user_id, shift, assigned_department_id) VALUES (?, ?, ?)");
                            $stmt_staff->bind_param("isi", $user_id, $_POST['shift'], $_POST['assigned_department_id']);
                            $stmt_staff->execute();
                        }

                        // --- Audit Log ---
                        $log_details = "Created a new user '{$username}' (ID: {$display_user_id}) with the role '{$role_name}'.";
                        if ($profile_picture !== 'default.png') {
                            $log_details .= " Profile picture was added.";
                        }
                        log_activity($conn, $admin_user_id_for_log, 'create_user', $user_id, $log_details);
                        // --- End Audit Log ---

                        $conn->commit();
                        $response = ['success' => true, 'message' => ucfirst($role_name) . ' added successfully.'];

                    } catch (Exception $e) {
                        $conn->rollback();
                        $response = ['success' => false, 'message' => $e->getMessage()];
                    }
                    break;
                
                case 'specialities':
                    $result = $conn->query("SELECT id, name FROM specialities ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
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
                        throw $e; 
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
                        $username = strtolower($_POST['username']);
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                        if (!$email)
                            throw new Exception('Invalid email format.');
                        $phone = $_POST['phone'];
                        $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
                        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
                        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;

                        // --- Audit Log: Fetch current state ---
                        $stmt_old = $conn->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
                        $stmt_old->bind_param("i", $id);
                        $stmt_old->execute();
                        $old_user_data = $stmt_old->get_result()->fetch_assoc();
                        // --- End Audit Log Fetch ---

                        $sql_parts = ["name = ?", "username = ?", "email = ?", "phone = ?", "is_active = ?", "date_of_birth = ?", "gender = ?"];
                        $params = [$name, $username, $email, $phone, $is_active, $date_of_birth, $gender];
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
                            $changes[] = "password";
                        }

                        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
                        $params[] = $id;
                        $types .= "i";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();

                        // In api.php, inside case 'updateUser':

                        if ($old_user_data['role_name'] === 'doctor') {
                            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
                            $is_available = isset($_POST['is_available']) ? (int)$_POST['is_available'] : 1;
                            $specialty_id = !empty($_POST['specialty_id']) ? (int)$_POST['specialty_id'] : null; // Get specialty_id

                            $stmt_doctor = $conn->prepare("
                                INSERT INTO doctors (user_id, specialty_id, qualifications, department_id, is_available) 
                                VALUES (?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                specialty_id = VALUES(specialty_id), 
                                qualifications = VALUES(qualifications), 
                                department_id = VALUES(department_id), 
                                is_available = VALUES(is_available)
                            ");
                            $stmt_doctor->bind_param("iisii", $id, $specialty_id, $_POST['qualifications'], $department_id, $is_available);
                            $stmt_doctor->execute();
                        } elseif ($old_user_data['role_name'] === 'staff') {
                            $stmt_staff = $conn->prepare("
                                INSERT INTO staff (user_id, shift, assigned_department_id) 
                                VALUES (?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                shift = VALUES(shift), 
                                assigned_department_id = VALUES(assigned_department_id)
                            ");
                            $stmt_staff->bind_param("isi", $id, $_POST['shift'], $_POST['assigned_department_id']);
                            $stmt_staff->execute();
                        }

                        // --- Audit Log: Compare and log changes ---
                        $email_changes = []; // For email notification
                        
                        if ($old_user_data['name'] !== $name) {
                            $changes[] = "name from '{$old_user_data['name']}' to '{$name}'";
                            $email_changes['Name'] = ['old' => $old_user_data['name'], 'new' => $name];
                        }
                        if ($old_user_data['username'] !== $username) {
                            $changes[] = "username from '{$old_user_data['username']}' to '{$username}'";
                            $email_changes['Username'] = ['old' => $old_user_data['username'], 'new' => $username];
                        }
                        if ($old_user_data['email'] !== $email) {
                            $changes[] = "email from '{$old_user_data['email']}' to '{$email}'";
                            $email_changes['Email'] = ['old' => $old_user_data['email'], 'new' => $email];
                        }
                        if ($old_user_data['phone'] !== $phone) {
                            $changes[] = "phone number";
                            $email_changes['Phone Number'] = ['old' => $old_user_data['phone'], 'new' => $phone];
                        }
                        if ($old_user_data['is_active'] != $is_active) {
                            $changes[] = "status from " . ($old_user_data['is_active'] ? "'Active'" : "'Inactive'") . " to " . ($is_active ? "'Active'" : "'Inactive'");
                            $email_changes['Account Status'] = ['old' => ($old_user_data['is_active'] ? 'Active' : 'Inactive'), 'new' => ($is_active ? 'Active' : 'Inactive')];
                        }
                        if (($old_user_data['gender'] ?? '') !== ($gender ?? '')) {
                            $email_changes['Gender'] = ['old' => ($old_user_data['gender'] ?? 'Not set'), 'new' => ($gender ?? 'Not set')];
                        }
                        if (($old_user_data['date_of_birth'] ?? '') !== ($date_of_birth ?? '')) {
                            $email_changes['Date of Birth'] = ['old' => ($old_user_data['date_of_birth'] ?? 'Not set'), 'new' => ($date_of_birth ?? 'Not set')];
                        }
                        if (in_array("profile picture", $changes)) {
                            $email_changes['Profile Picture'] = 'Updated';
                        }
                        if (in_array("password", $changes)) {
                            $email_changes['Password'] = 'Changed for security';
                        }
                        
                        if (!empty($changes)) {
                            $log_details = "Updated user '{$username}': changed " . implode(', ', $changes) . ".";
                            log_activity($conn, $admin_user_id_for_log, 'update_user', $id, $log_details);
                            
                            // --- Send Email Notification ---
                            if (!empty($email_changes)) {
                                try {
                                    $current_datetime = date('d M Y, h:i A');
                                    $email_body = getAccountModificationTemplate($name, $username, $email_changes, $current_datetime, 'System Administrator');
                                    
                                    // Send to the updated email address
                                    $email_sent = send_mail('MedSync', $email, 'Your MedSync Account Has Been Updated', $email_body);
                                    
                                    if (!$email_sent) {
                                        error_log("Failed to send account update email to user (ID: $id, Email: $email)");
                                        log_activity($conn, $admin_user_id_for_log, 'email_error', $id, "Failed to send account update notification email to $email");
                                    }
                                    
                                    // If email was changed, also notify the old email
                                    if (isset($email_changes['Email']) && !empty($old_user_data['email'])) {
                                        $old_email_body = getAccountModificationTemplate(
                                            $old_user_data['name'], 
                                            $old_user_data['username'], 
                                            $email_changes, 
                                            $current_datetime, 
                                            'System Administrator'
                                        );
                                        $old_email_sent = send_mail('MedSync', $old_user_data['email'], 'Your MedSync Account Has Been Updated', $old_email_body);
                                        
                                        if (!$old_email_sent) {
                                            error_log("Failed to send account update email to old email (ID: $id, Email: {$old_user_data['email']})");
                                        }
                                    }
                                } catch (Exception $email_error) {
                                    // Log email error but don't fail the update
                                    error_log("Email notification failed for user update (ID: $id): " . $email_error->getMessage());
                                    log_activity($conn, $admin_user_id_for_log, 'email_error', $id, "Failed to send account update notification email: " . $email_error->getMessage());
                                }
                            }
                            // --- End Email Notification ---
                        }
                        // --- End Audit Log ---

                        $conn->commit();
                        $response = ['success' => true, 'message' => 'User updated successfully.'];

                    } catch (Exception $e) {
                        $conn->rollback();
                        $response = ['success' => false, 'message' => $e->getMessage()];
                    }
                    break;

                case 'updateProfile':
                    $conn->begin_transaction();
                    try {
                        if (empty($_POST['name']) || empty($_POST['email'])) {
                            throw new Exception('Name and Email are required.');
                        }
                        $id = $_SESSION['user_id'];
                        $name = $_POST['name'];
                        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
                        if (!$email)
                            throw new Exception('Invalid email format.');
                        $phone = $_POST['phone'];
                        $gender = $_POST['gender'] ?? null;
                        $date_of_birth = $_POST['date_of_birth'] ?? null;

                        // --- Fetch current state for comparison ---
                        $stmt_old = $conn->prepare("SELECT name, email, phone, gender, date_of_birth, profile_picture, username FROM users WHERE id = ?");
                        $stmt_old->bind_param("i", $id);
                        $stmt_old->execute();
                        $old_user_data = $stmt_old->get_result()->fetch_assoc();
                        // --- End Fetch ---

                        $sql_parts = ["name = ?", "email = ?", "phone = ?"];
                        $params = [$name, $email, $phone];
                        $types = "sss";

                        $email_changes = []; // Track changes for email notification

                        // Handle gender
                        if (!empty($gender)) {
                            $sql_parts[] = "gender = ?";
                            $params[] = $gender;
                            $types .= "s";
                        }

                        // Handle date of birth
                        if (!empty($date_of_birth)) {
                            $sql_parts[] = "date_of_birth = ?";
                            $params[] = $date_of_birth;
                            $types .= "s";
                        }

                        // Handle profile picture upload
                        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                            $file = $_FILES['profile_picture'];
                            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
                            $max_size = 2 * 1024 * 1024; // 2MB

                            if (!in_array($file['type'], $allowed_types)) {
                                throw new Exception('Invalid file type. Only JPG and PNG are allowed.');
                            }
                            if ($file['size'] > $max_size) {
                                throw new Exception('File size exceeds 2MB limit.');
                            }

                            // Generate unique filename
                            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                            $new_filename = 'profile_' . $id . '_' . time() . '.' . $extension;
                            $upload_path = '../uploads/profile_pictures/' . $new_filename;

                            // Delete old profile picture if not default
                            $old_pic = $old_user_data['profile_picture'];
                            if ($old_pic && $old_pic !== 'default.png' && file_exists('../uploads/profile_pictures/' . $old_pic)) {
                                unlink('../uploads/profile_pictures/' . $old_pic);
                            }

                            // Upload new file
                            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                                $sql_parts[] = "profile_picture = ?";
                                $params[] = $new_filename;
                                $types .= "s";
                                $email_changes['Profile Picture'] = 'Updated';
                            } else {
                                throw new Exception('Failed to upload profile picture.');
                            }
                        }

                        // Handle password change with validation
                        if (!empty($_POST['password'])) {
                            // Require current password when changing password
                            if (empty($_POST['current_password'])) {
                                throw new Exception('Current password is required to change password.');
                            }

                            // Verify current password
                            $stmt_verify = $conn->prepare("SELECT password FROM users WHERE id = ?");
                            $stmt_verify->bind_param("i", $id);
                            $stmt_verify->execute();
                            $current_hash = $stmt_verify->get_result()->fetch_assoc()['password'];
                            
                            if (!password_verify($_POST['current_password'], $current_hash)) {
                                throw new Exception('Current password is incorrect.');
                            }

                            // Verify password confirmation
                            if ($_POST['password'] !== $_POST['confirm_password']) {
                                throw new Exception('New passwords do not match.');
                            }

                            // Validate password strength
                            if (strlen($_POST['password']) < 8) {
                                throw new Exception('New password must be at least 8 characters long.');
                            }

                            $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                            $sql_parts[] = "password = ?";
                            $params[] = $hashed_password;
                            $types .= "s";
                            $email_changes['Password'] = 'Changed for security';
                        }

                        $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
                        $params[] = $id;
                        $types .= "i";

                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();

                        // --- Compare and track changes ---
                        if ($old_user_data['name'] !== $name) {
                            $email_changes['Name'] = ['old' => $old_user_data['name'], 'new' => $name];
                        }
                        if ($old_user_data['email'] !== $email) {
                            $email_changes['Email'] = ['old' => $old_user_data['email'], 'new' => $email];
                        }
                        if ($old_user_data['phone'] !== $phone) {
                            $email_changes['Phone Number'] = ['old' => $old_user_data['phone'], 'new' => $phone];
                        }
                        if (($old_user_data['gender'] ?? '') !== ($gender ?? '')) {
                            $email_changes['Gender'] = ['old' => ($old_user_data['gender'] ?? 'Not set'), 'new' => ($gender ?? 'Not set')];
                        }
                        if (($old_user_data['date_of_birth'] ?? '') !== ($date_of_birth ?? '')) {
                            $email_changes['Date of Birth'] = ['old' => ($old_user_data['date_of_birth'] ?? 'Not set'), 'new' => ($date_of_birth ?? 'Not set')];
                        }
                        // --- End tracking ---

                        // --- Send Email Notification ---
                        if (!empty($email_changes)) {
                            try {
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
                            }
                        }
                        // --- End Email Notification ---

                        $_SESSION['username'] = $name;
                        log_activity($conn, $admin_user_id_for_log, 'update_own_profile', $id, 'Admin updated their own profile details.');
                        
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Your profile has been updated successfully.'];
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'removeOwnProfilePicture':
                    $id = $_SESSION['user_id'];
                    
                    // Fetch current profile picture
                    $stmt_old = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
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
                        log_activity($conn, $admin_user_id_for_log, 'remove_own_profile_picture', $id, 'Admin removed their own profile picture.');
                        $response = ['success' => true, 'message' => 'Profile picture removed successfully.'];
                    } else {
                        throw new Exception('Failed to remove profile picture.');
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

                    $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
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

                    // --- Audit Log: Fetch medicine name before update ---
                    $stmt_med = $conn->prepare("SELECT name FROM medicines WHERE id = ?");
                    $stmt_med->bind_param("i", $id);
                    $stmt_med->execute();
                    $med_data = $stmt_med->get_result()->fetch_assoc();
                    $old_med_name = $med_data ? $med_data['name'] : 'Unknown';
                    // --- End Fetch ---

                    $stmt = $conn->prepare("UPDATE medicines SET name = ?, description = ?, quantity = ?, unit_price = ?, low_stock_threshold = ? WHERE id = ?");
                    $stmt->bind_param("ssidii", $name, $description, $quantity, $unit_price, $low_stock_threshold, $id);
                    if ($stmt->execute()) {
                        $log_details = "Updated medicine '{$old_med_name}' (ID: {$id}). New name: '{$name}', New quantity: {$quantity}.";
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

                    // In admin/api.php, find case 'addDepartment' and modify it

                    case 'addDepartment':
                        if (empty($_POST['name'])) {
                            throw new Exception('Department name is required.');
                        }
                        $name = $_POST['name'];
                        // Handle the optional Head of Department ID
                        $head_id = !empty($_POST['head_of_department_id']) ? (int)$_POST['head_of_department_id'] : null;

                        $stmt = $conn->prepare("INSERT INTO departments (name, head_of_department_id) VALUES (?, ?)");
                        $stmt->bind_param("si", $name, $head_id);
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
                            $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
                            // Handle the optional Head of Department ID
                            $head_id = !empty($_POST['head_of_department_id']) ? (int)$_POST['head_of_department_id'] : null;

                    // --- Audit Log: Fetch department name before update ---
                    $stmt_dept = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                    $stmt_dept->bind_param("i", $id);
                    $stmt_dept->execute();
                    $dept_data = $stmt_dept->get_result()->fetch_assoc();
                    $old_dept_name = $dept_data ? $dept_data['name'] : 'Unknown';
                    // --- End Fetch ---

                    $stmt = $conn->prepare("UPDATE departments SET name = ?, is_active = ?, head_of_department_id = ? WHERE id = ?");
                    $stmt->bind_param("siii", $name, $is_active, $head_id, $id);
                    if ($stmt->execute()) {
                            // --- Audit Log ---
                            $status_text = $is_active ? 'Active' : 'Inactive';
                            $log_details = "Updated department '{$old_dept_name}' (ID: {$id}). New name: '{$name}', Status: '{$status_text}'.";
                            log_activity($conn, $admin_user_id_for_log, 'update_department', null, $log_details);
                            // --- End Audit Log ---
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

                    // --- Audit Log: Fetch department name before deactivating ---
                    $stmt_dept = $conn->prepare("SELECT name FROM departments WHERE id = ?");
                    $stmt_dept->bind_param("i", $id);
                    $stmt_dept->execute();
                    $dept_data = $stmt_dept->get_result()->fetch_assoc();
                    $dept_name_for_log = $dept_data ? $dept_data['name'] : "ID {$id}";
                    // --- End Fetch ---

                    $stmt = $conn->prepare("UPDATE departments SET is_active = 0 WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'deactivate_department', null, "Deactivated department '{$dept_name_for_log}'.");
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
                            // --- Audit Log ---
                        $log_details = "Created new ward '{$name}' with capacity {$capacity}.";
                        log_activity($conn, $admin_user_id_for_log, 'create_ward', null, $log_details);
                        // --- End Audit Log ---
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
                            // --- Audit Log ---
                            $log_details = "Updated ward '{$name}' (ID: {$id}).";
                            log_activity($conn, $admin_user_id_for_log, 'update_ward', null, $log_details);
                            // --- End Audit Log ---
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
                            // --- Audit Log ---
                            $log_details = "Deleted ward '{$ward_name}' (ID: {$id}).";
                            log_activity($conn, $admin_user_id_for_log, 'delete_ward', null, $log_details);
                            // --- End Audit Log ---
                        $response = ['success' => true, 'message' => 'Ward deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete ward. Ensure no accommodations are assigned to it.');
                    }
                    break;

                case 'addAccommodation':
                    if (empty($_POST['type']) || empty($_POST['number']) || !isset($_POST['price_per_day'])) {
                        throw new Exception('Type, number, and price are required.');
                    }
                    $type = $_POST['type']; // 'bed' or 'room'
                    $number = $_POST['number'];
                    $ward_id = ($type === 'bed' && !empty($_POST['ward_id'])) ? (int)$_POST['ward_id'] : null;
                    $price_per_day = (float)$_POST['price_per_day'];

                    $stmt = $conn->prepare("INSERT INTO accommodations (type, number, ward_id, price_per_day) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssid", $type, $number, $ward_id, $price_per_day);
                    if ($stmt->execute()) {
                            // --- Audit Log ---
                            $log_details = "Added new accommodation {$type} with number '{$number}'.";
                            log_activity($conn, $admin_user_id_for_log, 'create_accommodation', null, $log_details);
                            // --- End Audit Log ---
                        $response = ['success' => true, 'message' => ucfirst($type) . ' added successfully.'];
                    } else {
                        throw new Exception('Failed to add ' . $type . '. It might already exist in the selected ward.');
                    }
                    break;

                case 'updateAccommodation':
                    if (empty($_POST['id']) || empty($_POST['number']) || empty($_POST['status'])) {
                        throw new Exception('ID, number, and status are required.');
                    }
                    $id = (int)$_POST['id'];
                    $number = $_POST['number'];
                    $new_status = $_POST['status'];
                    $new_patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
                    $new_doctor_id = !empty($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : null;
                    $price_per_day = (float)$_POST['price_per_day'];
                    $ward_id = !empty($_POST['ward_id']) ? (int)$_POST['ward_id'] : null;

                    $conn->begin_transaction();
                    try {
                        $stmt_current = $conn->prepare("SELECT status, patient_id, type FROM accommodations WHERE id = ? FOR UPDATE");
                        $stmt_current->bind_param("i", $id);
                        $stmt_current->execute();
                        $current_acc = $stmt_current->get_result()->fetch_assoc();

                        if ($current_acc) {
                            $old_status = $current_acc['status'];
                            $old_patient_id = $current_acc['patient_id'];

                            // Patient is being discharged
                            if ($old_status === 'occupied' && $new_status !== 'occupied' && $old_patient_id) {
                                $stmt_discharge = $conn->prepare("UPDATE admissions SET discharge_date = NOW() WHERE patient_id = ? AND accommodation_id = ? AND discharge_date IS NULL");
                                $stmt_discharge->bind_param("ii", $old_patient_id, $id);
                                $stmt_discharge->execute();
                            }

                            // Patient is being admitted
                            if ($new_status === 'occupied' && $old_status !== 'occupied' && $new_patient_id) {
                                $stmt_admit = $conn->prepare("INSERT INTO admissions (patient_id, doctor_id, accommodation_id, admission_date) VALUES (?, ?, ?, NOW())");
                                $stmt_admit->bind_param("iii", $new_patient_id, $new_doctor_id, $id);
                                $stmt_admit->execute();
                            }
                        }

                        $occupied_since = ($new_status === 'occupied') ? date('Y-m-d H:i:s') : null;
                        $reserved_since = ($new_status === 'reserved') ? date('Y-m-d H:i:s') : null;
                        $patient_id_to_set = ($new_status === 'occupied' || $new_status === 'reserved') ? $new_patient_id : null;
                        $doctor_id_to_set = ($new_status === 'occupied') ? $new_doctor_id : null;

                        $stmt = $conn->prepare("UPDATE accommodations SET number = ?, ward_id = ?, status = ?, patient_id = ?, doctor_id = ?, occupied_since = ?, reserved_since = ?, price_per_day = ? WHERE id = ?");
                        $stmt->bind_param("sisissdsi", $number, $ward_id, $new_status, $patient_id_to_set, $doctor_id_to_set, $occupied_since, $reserved_since, $price_per_day, $id);
                        $stmt->execute();

                        // --- Audit Log ---
                        $log_details = "Updated accommodation {$current_acc['type']} '{$number}' (ID: {$id}) to status '{$new_status}'.";
                        if ($new_status === 'occupied' && $new_patient_id) {
                            $log_details .= " Assigned patient ID {$new_patient_id}.";
                        }
                        log_activity($conn, $admin_user_id_for_log, 'update_accommodation', $new_patient_id, $log_details);
                        // --- End Audit Log ---
                        
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Accommodation updated successfully.'];
                    } catch (Exception $e) {
                        $conn->rollback();
                        throw $e;
                    }
                    break;

                case 'deleteAccommodation':
                    if (empty($_POST['id'])) {
                        throw new Exception('Accommodation ID is required.');
                    }
                    $id = (int)$_POST['id'];
                    $stmt = $conn->prepare("DELETE FROM accommodations WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) {
                            // --- Audit Log ---
                            $log_details = "Deleted accommodation {$acc_type} '{$acc_number}' (ID: {$id}).";
                            log_activity($conn, $admin_user_id_for_log, 'delete_accommodation', null, $log_details);
                            // --- End Audit Log ---
                        $response = ['success' => true, 'message' => 'Accommodation deleted successfully.'];
                    } else {
                        throw new Exception('Failed to delete accommodation.');
                    }
                    break;

                case 'update_doctor_schedule':
                    if (empty($_POST['doctor_id']) || !isset($_POST['slots'])) {
                        throw new Exception('Doctor ID and slots data are required.');
                    }
                    $doctor_id = (int) $_POST['doctor_id'];
                    $slots_json = $_POST['slots']; // This will be a JSON string from the frontend

                    // --- Audit Log: Fetch doctor details ---
                    $stmt_doc_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                    $stmt_doc_info->bind_param("i", $doctor_id);
                    $stmt_doc_info->execute();
                    $doc_info = $stmt_doc_info->get_result()->fetch_assoc();
                    $doc_name_for_log = $doc_info ? "{$doc_info['name']} ({$doc_info['display_user_id']})" : "ID {$doctor_id}";
                    // --- End Fetch ---

                    $stmt = $conn->prepare("UPDATE doctors SET slots = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $slots_json, $doctor_id);

                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'update_doctor_schedule', $doctor_id, "Updated schedule for doctor {$doc_name_for_log}.");
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

                    // --- Audit Log: Fetch recipient details ---
                    $stmt_recipient_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                    $stmt_recipient_info->bind_param("i", $recipient_user_id);
                    $stmt_recipient_info->execute();
                    $recipient_info = $stmt_recipient_info->get_result()->fetch_assoc();
                    $recipient_name_for_log = $recipient_info ? "{$recipient_info['name']} ({$recipient_info['display_user_id']})" : "ID {$recipient_user_id}";
                    // --- End Fetch ---

                    $sql = "INSERT INTO notifications (sender_id, message, recipient_user_id) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("isi", $admin_user_id, $message, $recipient_user_id);

                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'send_notification', $recipient_user_id, "Sent an individual message to {$recipient_name_for_log}.");
                        $response = ['success' => true, 'message' => 'Message sent successfully.'];
                    } else {
                        throw new Exception('Failed to send message.');
                    }
                    break;
                
                case 'sendMessage':
                    $conn->begin_transaction();
                    $transaction_active = true;

                    if (empty($_POST['receiver_id']) || empty(trim($_POST['message_text']))) {
                        throw new Exception("Receiver and message text cannot be empty.");
                    }
                    $receiver_id = (int) $_POST['receiver_id'];
                    $message_text = trim($_POST['message_text']);
                    $sender_id = $admin_user_id_for_log;

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

                    // --- Audit Log: Fetch staff details ---
                    $stmt_staff_info = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
                    $stmt_staff_info->bind_param("i", $staff_id);
                    $stmt_staff_info->execute();
                    $staff_info = $stmt_staff_info->get_result()->fetch_assoc();
                    $staff_name_for_log = $staff_info ? "{$staff_info['name']} ({$staff_info['display_user_id']})" : "ID {$staff_id}";
                    // --- End Fetch ---

                    $stmt = $conn->prepare("UPDATE staff SET shift = ? WHERE user_id = ?");
                    $stmt->bind_param("si", $shift, $staff_id);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'update_staff_shift', $staff_id, "Updated shift to '{$shift}' for staff member {$staff_name_for_log}.");
                        $response = ['success' => true, 'message' => 'Staff shift updated successfully.'];
                    } else {
                        throw new Exception('Failed to update staff shift.');
                    }
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
                            $response = ['success' => false, 'message' => 'Database update failed during execution.'];
                        }
                        $stmt->close();
                    } else {
                        $response = ['success' => false, 'message' => 'Database statement could not be prepared.'];
                    }
                    break;

                case 'dismiss_all_notifications':
                    $admin_user_id = $_SESSION['user_id'];


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
                
                case 'download_pdf':
                    if (empty($_POST['report_type']) || empty($_POST['start_date']) || empty($_POST['end_date'])) {
                        die('Report type and date range are required to generate a PDF.');
                    }

                    header_remove('Content-Type');
            
                    generatePdfReport($conn, $_POST['report_type'], $_POST['start_date'], $_POST['end_date']);
                    exit();

                case 'blockIp':
                if (empty($_POST['ip_address'])) {
                    throw new Exception('IP address is required.');
                }
                $ip_address = trim($_POST['ip_address']);
                $reason = !empty($_POST['reason']) ? trim($_POST['reason']) : null;

                $stmt = $conn->prepare("INSERT INTO ip_blocks (ip_address, reason) VALUES (?, ?)");
                $stmt->bind_param("ss", $ip_address, $reason);
                if ($stmt->execute()) {
                    log_activity($conn, $admin_user_id_for_log, 'block_ip', null, "Blocked IP: {$ip_address}");
                    $response = ['success' => true, 'message' => 'IP address blocked successfully.'];
                } else {
                    throw new Exception('Failed to block IP address. It might already be blocked.');
                }
                break;

                case 'unblockIp':
                    if (empty($_POST['ip_address'])) {
                        throw new Exception('IP address is required.');
                    }
                    $ip_address = trim($_POST['ip_address']);

                    $stmt = $conn->prepare("DELETE FROM ip_blocks WHERE ip_address = ?");
                    $stmt->bind_param("s", $ip_address);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'unblock_ip', null, "Unblocked IP: {$ip_address}");
                        $response = ['success' => true, 'message' => 'IP address unblocked successfully.'];
                    } else {
                        throw new Exception('Failed to unblock IP address.');
                    }
                    break;

                case 'updateIpName':
                    if (empty($_POST['ip_address']) || !isset($_POST['name'])) {
                        throw new Exception('IP address and name are required.');
                    }
                    $ip_address = trim($_POST['ip_address']);
                    $name = trim($_POST['name']);

                    $stmt = $conn->prepare("UPDATE ip_tracking SET name = ? WHERE ip_address = ?");
                    $stmt->bind_param("ss", $name, $ip_address);
                    if ($stmt->execute()) {
                        log_activity($conn, $admin_user_id_for_log, 'update_ip_name', null, "Updated name for IP {$ip_address} to '{$name}'.");
                        $response = ['success' => true, 'message' => 'IP address name updated successfully.'];
                    } else {
                        throw new Exception('Failed to update IP address name.');
                    }
                    break;
                    
                    }
                } elseif (isset($_GET['fetch'])) {
                    $fetch_target = $_GET['fetch'];
                    switch ($fetch_target) {

                        case 'get_system_settings':
                    $settings = [];
                    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('system_email')");
                    while ($row = $result->fetch_assoc()) {
                        $settings[$row['setting_key']] = $row['setting_value'];
                    }
                    // Provide a default value if not set in the database
                    if (!isset($settings['system_email'])) {
                        $settings['system_email'] = 'Not configured';
                    }
                    $response = ['success' => true, 'data' => $settings];
                    break;



                        case 'active_doctors':
                            $sql = "SELECT u.id, u.name, d.specialty 
                                    FROM users u 
                                    JOIN doctors d ON u.id = d.user_id 
                                    JOIN roles r ON u.role_id = r.id
                                    WHERE u.is_active = 1 AND r.role_name = 'doctor' 
                                    ORDER BY u.name ASC";
                            $result = $conn->query($sql);
                            $data = $result->fetch_all(MYSQLI_ASSOC);
                            $response = ['success' => true, 'data' => $data];
                            break;

                case 'available_accommodations':
                    $type = $_GET['type'] ?? 'bed'; // default to bed
                    if ($type === 'bed') {
                        $sql = "SELECT a.id, a.number as bed_number, w.name as ward_name 
                                FROM accommodations a
                                JOIN wards w ON a.ward_id = w.id
                                WHERE a.type = 'bed' AND a.status = 'available'
                                ORDER BY w.name, a.number ASC";
                    } else { // room
                        $sql = "SELECT id, number as room_number 
                                FROM accommodations
                                WHERE type = 'room' AND a.status = 'available'
                                ORDER BY number ASC";
                    }
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'unassigned_patients':
                    $sql = "SELECT u.id, u.name, u.display_user_id 
                            FROM users u
                            LEFT JOIN accommodations a ON u.id = a.patient_id AND a.status = 'occupied'
                            JOIN roles r ON u.role_id = r.id
                            WHERE r.role_name = 'user' AND u.is_active = 1 AND a.id IS NULL
                            ORDER BY u.name ASC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'appointments':
                    $search = $_GET['search'] ?? '';
                    $statusFilter = $_GET['status'] ?? 'all';
                    $tokenStatusFilter = $_GET['token_status'] ?? 'all';
                    $dateFrom = $_GET['date_from'] ?? '';
                    $dateTo = $_GET['date_to'] ?? '';
                    $doctorId = $_GET['doctor_id'] ?? 'all';

                    $sql = "SELECT 
                                a.id, 
                                a.token_number,
                                a.token_status,
                                a.slot_start_time,
                                a.slot_end_time,
                                a.appointment_date, 
                                a.status,
                                p.name as patient_name, 
                                p.display_user_id as patient_display_id,
                                p.phone as patient_phone,
                                p.email as patient_email,
                                p.gender as patient_gender,
                                TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as patient_age,
                                d.name as doctor_name,
                                d.display_user_id as doctor_display_id,
                                sp.name as doctor_specialty
                            FROM appointments a
                            JOIN users p ON a.user_id = p.id
                            JOIN users d ON a.doctor_id = d.id
                            LEFT JOIN doctors doc ON d.id = doc.user_id
                            LEFT JOIN specialities sp ON doc.specialty_id = sp.id";

                    $whereConditions = [];
                    $params = [];
                    $types = "";

                    // Search filter
                    if (!empty($search)) {
                        $whereConditions[] = "(p.name LIKE ? OR p.display_user_id LIKE ? OR d.name LIKE ? OR d.display_user_id LIKE ?)";
                        $searchTerm = "%{$search}%";
                        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
                        $types .= "ssss";
                    }

                    // Status filter
                    if ($statusFilter !== 'all') {
                        $whereConditions[] = "a.status = ?";
                        $params[] = $statusFilter;
                        $types .= "s";
                    }

                    // Token status filter
                    if ($tokenStatusFilter !== 'all') {
                        $whereConditions[] = "a.token_status = ?";
                        $params[] = $tokenStatusFilter;
                        $types .= "s";
                    }

                    // Date range filter
                    if (!empty($dateFrom)) {
                        $whereConditions[] = "DATE(a.appointment_date) >= ?";
                        $params[] = $dateFrom;
                        $types .= "s";
                    }
                    if (!empty($dateTo)) {
                        $whereConditions[] = "DATE(a.appointment_date) <= ?";
                        $params[] = $dateTo;
                        $types .= "s";
                    }

                    // Doctor filter
                    if ($doctorId !== 'all') {
                        $whereConditions[] = "a.doctor_id = ?";
                        $params[] = (int)$doctorId;
                        $types .= "i";
                    }

                    if (!empty($whereConditions)) {
                        $sql .= " WHERE " . implode(" AND ", $whereConditions);
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
                    $role_name = $_GET['role'];
                    $search = $_GET['search'] ?? '';

                    $sql = "SELECT u.id, u.display_user_id, u.name, u.username, u.email, u.phone, r.role_name as role, u.is_active as active, u.created_at, u.date_of_birth, u.gender, u.profile_picture, sp.name as specialty, d.specialty_id";
                    $params = [];
                    $types = "";

                    // MODIFIED: Moved the JOINs out of the 'if' statement so they always apply
                    $base_from = " FROM users u 
                                 JOIN roles r ON u.role_id = r.id 
                                 LEFT JOIN doctors d ON u.id = d.user_id 
                                 LEFT JOIN specialities sp ON d.specialty_id = sp.id ";

                    if ($role_name === 'doctor') {
                        $sql .= ", d.qualifications, d.department_id, d.is_available as availability";
                    } elseif ($role_name === 'staff') {
                        $sql .= ", s.shift, s.assigned_department_id, dep.name as assigned_department";
                        $base_from .= " LEFT JOIN staff s ON u.id = s.user_id LEFT JOIN departments dep ON s.assigned_department_id = dep.id ";
                    }
                    $sql .= $base_from;

                    $where_clauses = [];
                    if ($role_name !== 'all_users') {
                        $where_clauses[] = "r.role_name = ?";
                        $params[] = $role_name;
                        $types .= "s";
                    }

                    if (!empty($search)) {
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
                    $stmt = $conn->prepare("SELECT u.*, r.role_name, d.specialty, d.qualifications, s.shift 
                                            FROM users u
                                            JOIN roles r ON u.role_id = r.id
                                            LEFT JOIN doctors d ON u.id = d.user_id
                                            LEFT JOIN staff s ON u.id = s.user_id
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
                    if ($data['user']['role_name'] === 'doctor') {
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
                    $result = $conn->query("SELECT u.id, u.name, u.display_user_id FROM users u JOIN doctors d ON u.id = d.user_id JOIN roles r ON u.role_id = r.id WHERE u.is_active = 1 AND r.role_name = 'doctor' ORDER BY u.name ASC");
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
                        'Monday' => [], 'Tuesday' => [], 'Wednesday' => [], 'Thursday' => [], 'Friday' => [], 'Saturday' => [], 'Sunday' => []
                    ];
                    $response = ['success' => true, 'data' => $slots];
                    break;
                
                case 'conversations':
                    $user_id = $admin_user_id_for_log;
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

                    $response = ['success' => true, 'data' => $conversations];
                    break;
                
                case 'messages':
                    if (!isset($_GET['conversation_id'])) {
                        throw new Exception('Conversation ID is required.');
                    }
                    $conversation_id = (int) $_GET['conversation_id'];
                    $user_id = $admin_user_id_for_log;

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

                case 'staff_for_shifting':
                    $search = $_GET['search'] ?? '';
                    $sql = "SELECT u.id, u.name, u.display_user_id, s.shift 
                            FROM users u 
                            JOIN staff s ON u.id = s.user_id 
                            JOIN roles r ON u.role_id = r.id 
                            WHERE u.is_active = 1 AND r.role_name = 'staff'";
                    
                    $params = [];
                    $types = "";

                    if (!empty($search)) {
                        $sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.display_user_id LIKE ?)";
                        $searchTerm = "%{$search}%";
                        array_push($params, $searchTerm, $searchTerm, $searchTerm);
                        $types .= "sss";
                    }
                    
                    $sql .= " ORDER BY u.name ASC";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
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

                    case 'specialities':
                    $result = $conn->query("SELECT id, name FROM specialities ORDER BY name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'dashboard_stats':
                    $stats = [];
                    $stats['total_users'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE role_id = 1 AND is_active = 1")->fetch_assoc()['c'];
                    $stats['active_doctors'] = $conn->query("SELECT COUNT(*) as c FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name='doctor' AND u.is_active=1")->fetch_assoc()['c'];
                    $stats['pending_appointments'] = $conn->query("SELECT COUNT(*) as c FROM appointments WHERE status='scheduled'")->fetch_assoc()['c'];

                    $role_counts_sql = "SELECT r.role_name, COUNT(u.id) as count FROM users u JOIN roles r ON u.role_id = r.id GROUP BY r.role_name";
                    $result = $conn->query($role_counts_sql);
                    $counts = ['user' => 0, 'doctor' => 0, 'staff' => 0, 'admin' => 0];
                    while ($row = $result->fetch_assoc()) {
                        if (array_key_exists($row['role_name'], $counts)) {
                            $counts[$row['role_name']] = (int) $row['count'];
                        }
                    }
                    $stats['role_counts'] = $counts;

                    // Fetch low stock alerts
                    $stats['low_medicines_count'] = $conn->query("SELECT COUNT(*) as c FROM medicines WHERE quantity < low_stock_threshold")->fetch_assoc()['c'];
                    $stats['low_blood_count'] = $conn->query("SELECT COUNT(*) as c FROM blood_inventory WHERE quantity_ml < low_stock_threshold_ml")->fetch_assoc()['c'];
                    
                    $response = ['success' => true, 'data' => $stats];
                    break;

                case 'my_profile':
                    $admin_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT name, email, phone, username, gender, date_of_birth, profile_picture FROM users WHERE id = ?");
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_assoc();

                    // Fetch last login info from ip_tracking
                    $stmt_ip = $conn->prepare("SELECT ip_address, login_time, name FROM ip_tracking WHERE user_id = ? ORDER BY login_time DESC LIMIT 1");
                    $stmt_ip->bind_param("i", $admin_id);
                    $stmt_ip->execute();
                    $ip_result = $stmt_ip->get_result();
                    $ip_data = $ip_result->fetch_assoc();
                    
                    $data['last_login_ip'] = $ip_data['ip_address'] ?? 'N/A';
                    $data['last_login_time'] = $ip_data['login_time'] ?? null;
                    $data['last_login_device'] = $ip_data['name'] ?? 'Unknown';

                    $response = ['success' => true, 'data' => $data];
                    break;

                    // In admin/api.php

                    case 'departments_management':
                        $sql = "
                            SELECT
                                d.id,
                                d.name,
                                d.is_active,
                                u.name AS head_of_department,
                                (SELECT COUNT(*) FROM doctors doc WHERE doc.department_id = d.id) AS doctor_count,
                                (SELECT COUNT(*) FROM staff s WHERE s.assigned_department_id = d.id) AS staff_count
                            FROM departments d
                            LEFT JOIN users u ON d.head_of_department_id = u.id
                            ORDER BY d.name ASC
                        ";
                        $result = $conn->query($sql);
                        $data = $result->fetch_all(MYSQLI_ASSOC);
                        $response = ['success' => true, 'data' => $data];
                        break;

                // --- INVENTORY FETCH ENDPOINTS ---
                case 'medicines':
                    $search = $_GET['search'] ?? '';
                    
                    $sql = "SELECT * FROM medicines";
                    $params = [];
                    $types = "";

                    if (!empty($search)) {
                        $sql .= " WHERE name LIKE ? OR description LIKE ?";
                        $searchTerm = "%{$search}%";
                        array_push($params, $searchTerm, $searchTerm);
                        $types .= "ss";
                    }

                    $sql .= " ORDER BY name ASC";
                    
                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
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

                case 'accommodations':
                    $type = $_GET['type'] ?? 'bed';
                    $sql = "SELECT a.id, a.type, a.number, a.ward_id, w.name as ward_name, a.status, 
                                   a.patient_id, p.name as patient_name, 
                                   a.doctor_id, d.name as doctor_name,
                                   a.occupied_since, a.reserved_since, a.price_per_day 
                            FROM accommodations a 
                            LEFT JOIN wards w ON a.ward_id = w.id 
                            LEFT JOIN users p ON a.patient_id = p.id
                            LEFT JOIN users d ON a.doctor_id = d.id
                            WHERE a.type = ?
                            ORDER BY w.name, a.number ASC";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $type);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'patients_for_accommodations':
                    $result = $conn->query("SELECT u.id, u.name, u.display_user_id FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'user' AND u.is_active = 1 ORDER BY u.name ASC");
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'feedback_summary':
                    $summary_sql = "SELECT 
                                        AVG(overall_rating) as average_rating, 
                                        COUNT(*) as total_reviews,
                                        SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as five_star,
                                        SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as four_star,
                                        SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as three_star,
                                        SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as two_star,
                                        SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as one_star
                                    FROM feedback";
                    $summary_result = $conn->query($summary_sql);
                    $data = $summary_result->fetch_assoc();
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'feedback_list':
                    $sql = "SELECT 
                                f.id, 
                                IF(f.is_anonymous, 'Anonymous', p.name) as patient_name,
                                f.overall_rating, f.comments, f.feedback_type, f.created_at
                            FROM feedback f
                            LEFT JOIN users p ON f.patient_id = p.id
                            ORDER BY f.created_at DESC";
                    $result = $conn->query($sql);
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'report':
                    if (empty($_GET['type']) || empty($_GET['start_date']) || empty($_GET['end_date'])) {
                        throw new Exception('Report type, start date, and end date are required.');
                    }
                    $reportType = $_GET['type'];
                    $startDate = $_GET['start_date'];
                    $endDate = $_GET['end_date'] . ' 23:59:59'; // Include the entire end day

                    // chartData is no longer needed
                    $data = ['summary' => [], 'tableData' => []];
                    $date_column = '';
                    $summary_sql = '';
                    $table_sql = '';

                    if ($reportType === 'financial') {
                        $date_column = 'created_at';
                        $summary_sql = "SELECT SUM(IF(type='payment', amount, 0)) as total_revenue, SUM(IF(type='refund', amount, 0)) as total_refunds, COUNT(*) as total_transactions FROM transactions WHERE $date_column BETWEEN ? AND ?";
                        $table_sql = "SELECT t.id, u.name as user_name, t.description, t.amount, t.type, DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as date FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.created_at BETWEEN ? AND ? ORDER BY t.created_at DESC";
                    } elseif ($reportType === 'patient') {
                        $date_column = 'appointment_date';
                        $summary_sql = "SELECT COUNT(*) as total_appointments, SUM(IF(status='completed', 1, 0)) as completed, SUM(IF(status='cancelled', 1, 0)) as cancelled FROM appointments WHERE $date_column BETWEEN ? AND ?";
                        $table_sql = "SELECT a.id, p.name as patient_name, d.name as doctor_name, a.status, DATE_FORMAT(a.appointment_date, '%Y-%m-%d %H:%i') as date FROM appointments a JOIN users p ON a.user_id = p.id JOIN users d ON a.doctor_id = d.id WHERE a.appointment_date BETWEEN ? AND ? ORDER BY a.appointment_date DESC";
                    } else { // resource
                        $date_column = 'admission_date';
                        // Summary for resource utilization is a snapshot, not date-based
                        $summary_sql = "SELECT 
                            (SELECT COUNT(*) FROM accommodations WHERE type='bed') as total_beds,
                            (SELECT COUNT(*) FROM accommodations WHERE type='room') as total_rooms,
                            (SELECT COUNT(*) FROM accommodations WHERE status = 'occupied' AND type='bed') as occupied_beds,
                            (SELECT COUNT(*) FROM accommodations WHERE status = 'occupied' AND type='room') as occupied_rooms";
                        $table_sql = "SELECT 
                            a.id, p.name as patient_name, 
                            CASE 
                                WHEN acc.type = 'bed' THEN CONCAT('Bed ', acc.number, ' (', w.name, ')')
                                WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                                ELSE 'N/A' 
                            END as location,
                            DATE_FORMAT(a.admission_date, '%Y-%m-%d %H:%i') as admission_date,
                            IF(a.discharge_date IS NOT NULL, DATE_FORMAT(a.discharge_date, '%Y-%m-%d %H:%i'), 'Admitted') as discharge_date
                        FROM admissions a
                        JOIN users p ON a.patient_id = p.id
                        LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
                        LEFT JOIN wards w ON acc.ward_id = w.id
                        WHERE a.admission_date BETWEEN ? AND ?
                        ORDER BY a.admission_date DESC";
                    }

                    // Fetch Summary
                    if ($reportType === 'resource') {
                        $data['summary'] = $conn->query($summary_sql)->fetch_assoc();
                    } else {
                        $stmt_summary = $conn->prepare($summary_sql);
                        $stmt_summary->bind_param("ss", $startDate, $endDate);
                        $stmt_summary->execute();
                        $data['summary'] = $stmt_summary->get_result()->fetch_assoc();
                    }

                    // Fetch Table Data
                    $stmt_table = $conn->prepare($table_sql);
                    $stmt_table->bind_param("ss", $startDate, $endDate);
                    $stmt_table->execute();
                    $data['tableData'] = $stmt_table->get_result()->fetch_all(MYSQLI_ASSOC);

                    $response = ['success' => true, 'data' => $data];
                    break;

                    case 'search_users':
                    $term = $_GET['term'] ?? '';
                    if (empty($term)) {
                        $response = ['success' => true, 'data' => []];
                        break;
                    }
                    $searchTerm = "%{$term}%";
                    $sql = "SELECT u.id, u.name, u.profile_picture, u.display_user_id, r.role_name as role FROM users u JOIN roles r ON u.role_id = r.id WHERE u.name LIKE ? OR u.username LIKE ? OR u.display_user_id LIKE ? LIMIT 10";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'search_doctors':
                    $term = $_GET['term'] ?? '';
                    if (empty($term)) {
                        $response = ['success' => true, 'data' => []];
                        break;
                    }
                    $searchTerm = "%{$term}%";
                    $sql = "SELECT u.id, u.name, u.display_user_id 
                            FROM users u 
                            JOIN roles r ON u.role_id = r.id 
                            WHERE r.role_name = 'doctor' AND (u.name LIKE ? OR u.username LIKE ? OR u.display_user_id LIKE ?) 
                            LIMIT 10";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $response = ['success' => true, 'data' => $data];
                    break;

                case 'all_notifications':
                    $admin_id = $_SESSION['user_id'];
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
                    $sql = "SELECT COUNT(*) as unread_count 
                            FROM notifications
                            WHERE (recipient_user_id = ? OR recipient_role = 'admin' OR recipient_role = 'all') AND is_read = 0";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $admin_id);
                    $stmt->execute();
                    $result = $stmt->get_result()->fetch_assoc();
                    $response = ['success' => true, 'count' => $result['unread_count']];
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

                case 'getTrackedIps':
                    $search = $_GET['search'] ?? '';
                    $status = $_GET['status'] ?? 'all'; // all, active, blocked
                    $dateFrom = $_GET['date_from'] ?? null;
                    $dateTo = $_GET['date_to'] ?? null;
                    $sortBy = $_GET['sort_by'] ?? 'last_login';
                    $sortOrder = $_GET['sort_order'] ?? 'DESC';

                    // Validate sort column
                    $allowedSorts = ['ip_address', 'name', 'last_login', 'user_count', 'login_count'];
                    if (!in_array($sortBy, $allowedSorts)) {
                        $sortBy = 'last_login';
                    }
                    $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

                    $sql = "SELECT 
                                it.ip_address, 
                                MAX(it.name) as name, 
                                COUNT(DISTINCT it.user_id) as user_count,
                                GROUP_CONCAT(DISTINCT u.username ORDER BY u.username SEPARATOR ', ') as usernames, 
                                MAX(it.login_time) as last_login,
                                COUNT(it.id) as login_count,
                                EXISTS(SELECT 1 FROM ip_blocks ib WHERE ib.ip_address = it.ip_address) as is_blocked,
                                (SELECT reason FROM ip_blocks WHERE ip_address = it.ip_address LIMIT 1) as block_reason
                            FROM ip_tracking it
                            JOIN users u ON it.user_id = u.id";

                    $whereConditions = [];
                    $params = [];
                    $types = "";

                    // Search filter
                    if (!empty($search)) {
                        $whereConditions[] = "(it.ip_address LIKE ? OR it.name LIKE ? OR u.username LIKE ?)";
                        $searchTerm = "%{$search}%";
                        array_push($params, $searchTerm, $searchTerm, $searchTerm);
                        $types .= "sss";
                    }

                    // Date range filter
                    if (!empty($dateFrom)) {
                        $whereConditions[] = "it.login_time >= ?";
                        $params[] = $dateFrom . ' 00:00:00';
                        $types .= "s";
                    }
                    if (!empty($dateTo)) {
                        $whereConditions[] = "it.login_time <= ?";
                        $params[] = $dateTo . ' 23:59:59';
                        $types .= "s";
                    }

                    if (!empty($whereConditions)) {
                        $sql .= " WHERE " . implode(" AND ", $whereConditions);
                    }

                    $sql .= " GROUP BY it.ip_address";

                    // Status filter (applied after grouping)
                    if ($status === 'blocked') {
                        $sql .= " HAVING is_blocked = 1";
                    } elseif ($status === 'active') {
                        $sql .= " HAVING is_blocked = 0";
                    }

                    // Sorting
                    $sql .= " ORDER BY {$sortBy} {$sortOrder}";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);

                    // Calculate statistics
                    $stats = [
                        'total_ips' => count($data),
                        'blocked_ips' => 0,
                        'active_ips' => 0
                    ];

                    foreach ($data as $row) {
                        if ($row['is_blocked'] == 1) {
                            $stats['blocked_ips']++;
                        } else {
                            $stats['active_ips']++;
                        }
                    }

                    $response = ['success' => true, 'data' => $data, 'stats' => $stats];
                    break;

            }
        }

    } catch (Throwable $e) {
        error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());

        http_response_code(500); // Use 500 for internal server errors
        $response['message'] = 'An unexpected error occurred. Please try again later or contact support.';
    }

    restore_error_handler();
    echo json_encode($response);
    exit();
}

function generatePdfReport($conn, $reportType, $startDate, $endDate) {
    $endDateWithTime = $endDate . ' 23:59:59';
    
    // --- Data Fetching ---
    $table_sql = '';
    $table_headers = [];

    if ($reportType === 'financial') {
        $table_headers = ['ID', 'User', 'Description', 'Amount', 'Type', 'Date'];
        $table_sql = "SELECT t.id, u.name as user_name, t.description, t.amount, t.type, DATE_FORMAT(t.created_at, '%Y-%m-%d %H:%i') as date FROM transactions t JOIN users u ON t.user_id = u.id WHERE t.created_at BETWEEN ? AND ? ORDER BY t.created_at DESC";
    } elseif ($reportType === 'patient') {
        $table_headers = ['ID', 'Patient', 'Doctor', 'Status', 'Date'];
        $table_sql = "SELECT a.id, p.name as patient_name, d.name as doctor_name, a.status, DATE_FORMAT(a.appointment_date, '%Y-%m-%d %H:%i') as date FROM appointments a JOIN users p ON a.user_id = p.id JOIN users d ON a.doctor_id = d.id WHERE a.appointment_date BETWEEN ? AND ? ORDER BY a.appointment_date DESC";
    } elseif ($reportType === 'resource') {
        $table_headers = ['Admission ID', 'Patient Name', 'Location', 'Admission Date', 'Discharge Date'];
        $table_sql = "SELECT 
                    a.id, p.name as patient_name, 
                    CASE 
                        WHEN acc.type = 'bed' THEN CONCAT('Bed ', acc.number, ' (', w.name, ')')
                        WHEN acc.type = 'room' THEN CONCAT('Room ', acc.number)
                        ELSE 'N/A' 
                    END as location,
                    DATE_FORMAT(a.admission_date, '%Y-%m-%d %H:%i') as admission_date,
                    IF(a.discharge_date IS NOT NULL, DATE_FORMAT(a.discharge_date, '%Y-%m-%d %H:%i'), 'Admitted') as discharge_date
                FROM admissions a
                JOIN users p ON a.patient_id = p.id
                LEFT JOIN accommodations acc ON a.accommodation_id = acc.id
                LEFT JOIN wards w ON acc.ward_id = w.id
                WHERE a.admission_date BETWEEN ? AND ?
                ORDER BY a.admission_date DESC";
    }
    
    $stmt = $conn->prepare($table_sql);
    $stmt->bind_param("ss", $startDate, $endDateWithTime);
    $stmt->execute();
    $tableData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $conn->close();

    // --- HTML Template for PDF ---
    $medsync_logo_path = '../images/logo.png';
    $hospital_logo_path = '../images/hospital.png';
    $medsync_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($medsync_logo_path));
    $hospital_logo_base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($hospital_logo_path));

   
    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Report</title>
        <style>
            @page { margin: 130px 20px 20px 20px; }
            body { font-family: "DejaVu Sans", sans-serif; color: #333; }
            .header { position: fixed; top: -110px; left: 0; right: 0; width: 100%; height: 120px; }
            .medsync-logo { position: absolute; top: 10px; left: 20px; }
            .medsync-logo img { width: 80px; } /* <-- Reduced size */
            .hospital-logo { position: absolute; top: 10px; right: 20px; }
            .hospital-logo img { width: 70px; } /* <-- Reduced size */
            .hospital-details { text-align: center; margin-top: 0; }
            .hospital-details h2 { margin: 0; font-size: 1.5em; color: #007BFF; }
            .hospital-details p { margin: 2px 0; font-size: 0.85em; }
            .report-title { text-align: center; margin-top: 0; margin-bottom: 20px; }
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
            <p>Date Range: ' . htmlspecialchars($startDate) . ' to ' . htmlspecialchars($endDate) . ' | Generated on: ' . date('Y-m-d H:i:s') . '</p>
        </div>
        <table class="data-table">
            <thead>
                <tr>';
    foreach ($table_headers as $header) {
        $html .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
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
    $html .= '</tbody></table>
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
}
?>