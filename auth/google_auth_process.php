<?php
/**
 * MedSync - Google Authentication Process
 * Handles both login and registration for users signing in with Google.
 * Verifies the Google ID token with Firebase and syncs user data with the local database.
 */

// 1. INCLUDE NECESSARY FILES
// Include Composer's autoloader to load the Firebase Admin SDK
require_once '../vendor/autoload.php';
// Include your project's configuration for database connection and session start
require_once '../config.php';

// Use the Firebase Factory class
use Kreait\Firebase\Factory;

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

// 2. SECURITY CHECK & TOKEN RETRIEVAL
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['id_token'])) {
    header("Location: ../login/index.php");
    exit();
}
$idTokenString = $_POST['id_token'];

// 3. FIREBASE SETUP & TOKEN VERIFICATION
$serviceAccountPath = __DIR__ . '/../_private/firebase_credentials.json';

if (!file_exists($serviceAccountPath)) {
    $_SESSION['login_error'] = 'Server configuration error: Firebase service account not found.';
    header('Location: ../login/index.php');
    exit();
}

try {
    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $auth = $factory->createAuth();
    $verifiedIdToken = $auth->verifyIdToken($idTokenString);
} catch (Throwable $e) {
    error_log("Firebase Auth Error: " . $e->getMessage());
    $_SESSION['login_error'] = 'Authentication failed. Please try again.';
    header('Location: ../login/index.php');
    exit();
}

// 4. EXTRACT USER DATA & CONNECT TO DATABASE
$uid = $verifiedIdToken->claims()->get('sub');
$firebaseUser = $auth->getUser($uid);
$email = $firebaseUser->email;
$name = $firebaseUser->displayName ?? 'New User';
$profilePictureUrl = $firebaseUser->photoUrl;

$conn = getDbConnection();

// 5. CHECK IF USER EXISTS
$stmt = $conn->prepare("SELECT u.id, u.username, r.role_name AS role, u.display_user_id, u.is_active FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // USER EXISTS: LOG THEM IN
    $existingUser = $result->fetch_assoc();
    
    if ($existingUser['is_active'] == 0) {
        $_SESSION['login_message'] = ['type' => 'error', 'text' => 'Your account is currently inactive. Please contact support.'];
        header("Location: ../login/index.php");
        exit();
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $existingUser['id'];
    $_SESSION['username'] = $existingUser['username'];
    $_SESSION['role'] = $existingUser['role'];
    $_SESSION['display_user_id'] = $existingUser['display_user_id'];
    $_SESSION['loggedin_time'] = time();

    header("Location: ../" . $existingUser['role'] . "/dashboard");
    exit();

} else {
    // USER DOES NOT EXIST: CREATE A NEW ACCOUNT
    
    // --- Download and Save Google Profile Picture ---
    $profile_picture_filename = 'default.png';
    if (!empty($profilePictureUrl)) {
        $image_content = @file_get_contents($profilePictureUrl);
        if ($image_content !== false) {
            $upload_dir = '../uploads/profile_pictures/';
            // Ensure directory exists
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $profile_picture_filename = 'google_user_' . uniqid() . '.jpg';
            file_put_contents($upload_dir . $profile_picture_filename, $image_content);
        }
    }
    
    $username = explode('@', $email)[0] . rand(100, 999);
    $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
    $role_id = 1; // 'user' role
    
    try {
        $display_user_id = generateDisplayId('user', $conn);
        
        $stmt_insert = $conn->prepare("INSERT INTO users (display_user_id, username, email, password, role_id, name, profile_picture, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt_insert->bind_param("ssssiss", $display_user_id, $username, $email, $password, $role_id, $name, $profile_picture_filename);
        
        if ($stmt_insert->execute()) {
            $newUserId = $conn->insert_id;

            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $name;
            $_SESSION['role'] = 'user';
            $_SESSION['display_user_id'] = $display_user_id;
            $_SESSION['loggedin_time'] = time();
            
            header("Location: ../user/dashboard");
            exit();
        } else {
            // Log the database error for debugging
            error_log("Database insertion failed: " . $conn->error);
            
            $_SESSION['google_new_user'] = [
                'email' => $email,
                'name' => $name,
                'profilePictureUrl' => $profilePictureUrl
            ];
            
            // Redirect to the new profile completion page
            header("Location: complete_profile.php");
            exit();
        }
    } catch (Exception $e) {
        // Log the exception for debugging
        error_log("User registration error: " . $e->getMessage());
        
        $_SESSION['google_new_user'] = [
            'email' => $email,
            'name' => $name,
            'profilePictureUrl' => $profilePictureUrl
        ];
        
        // Redirect to the new profile completion page
        header("Location: complete_profile.php");
        exit();
    }
}
?>
