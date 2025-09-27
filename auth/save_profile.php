<?php
/**
 * MedSync - Save Completed Google Profile
 * Takes the additional details from the form, combines them with the session data,
 * and creates the final user account.
 */

require_once '../config.php';

// Function to generate sequential User IDs
function generateDisplayId($role, $conn) {
    $prefix_map = ['admin' => 'A', 'doctor' => 'D', 'staff' => 'S', 'user' => 'U'];
    if (!isset($prefix_map[$role])) {
        throw new Exception("Invalid role specified for ID generation.");
    }
    $prefix = $prefix_map[$role];
    
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;
        
        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();
        
        $conn->commit();
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $conn->rollback();
        error_log("ID Generation Failed: " . $e->getMessage());
        throw new Exception("Could not generate a new user ID.");
    }
}

// Security Checks
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_SESSION['google_new_user']) || !isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header("Location: ../register/index.php");
    exit();
}

// Form Data Validation
$phone = trim($_POST['phone']);
$date_of_birth = trim($_POST['date_of_birth']);
$gender = trim($_POST['gender']);

if (empty($phone) || empty($date_of_birth) || empty($gender)) {
    $_SESSION['profile_error'] = "All fields are required.";
    header("Location: complete_profile.php");
    exit();
}

if (!preg_match('/^\+91\d{10}$/', $phone)) {
    $_SESSION['profile_error'] = "Phone number must be in the format +91 followed by 10 digits.";
    header("Location: complete_profile.php");
    exit();
}

// Get user info from session
$google_user = $_SESSION['google_new_user'];
$email = $google_user['email'];
$name = $google_user['name'];
$profilePictureUrl = $google_user['profilePictureUrl'];

$conn = getDbConnection();

// --- Download and Save Google Profile Picture ---
$profile_picture_filename = 'default.png';
if (!empty($profilePictureUrl)) {
    $image_content = @file_get_contents($profilePictureUrl);
    if ($image_content !== false) {
        $upload_dir = '../uploads/profile_pictures/';
        $profile_picture_filename = 'google_user_' . uniqid() . '.jpg';
        file_put_contents($upload_dir . $profile_picture_filename, $image_content);
    }
}

// Prepare data for insertion
$username = explode('@', $email)[0] . rand(100, 999);
$password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
$role_id = 1; // 'user' role

try {
    $display_user_id = generateDisplayId('user', $conn);
    
    $stmt_insert = $conn->prepare("INSERT INTO users (display_user_id, username, email, password, role_id, name, profile_picture, phone, date_of_birth, gender, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $stmt_insert->bind_param("ssssisssss", $display_user_id, $username, $email, $password, $role_id, $name, $profile_picture_filename, $phone, $date_of_birth, $gender);
    
    if ($stmt_insert->execute()) {
        $newUserId = $conn->insert_id;

        // Clean up the temporary session data
        unset($_SESSION['google_new_user']);

        // Log the new user in
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['username'] = $name;
        $_SESSION['role'] = 'user';
        $_SESSION['display_user_id'] = $display_user_id;
        $_SESSION['loggedin_time'] = time();
        
        // Redirect to the dashboard
        header("Location: ../user/dashboard");
        exit();
    } else {
        throw new Exception("Database error: Could not create account.");
    }
} catch (Exception $e) {
    $_SESSION['profile_error'] = $e->getMessage();
    header("Location: complete_profile.php");
    exit();
}
?>
