<?php
/**
 * Processes OTP, creates user, saves profile picture, and sends welcome email.
 */

// --- Includes and Usings ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';
require_once '../config.php';
require_once '../register/welcome_email_template.php';

// ... (The generateDisplayId function remains the same) ...
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
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            // If the prefix doesn't exist, create it.
            $insert_stmt = $conn->prepare("INSERT INTO role_counters (role_prefix, last_id) VALUES (?, 0)");
            $insert_stmt->bind_param("s", $prefix);
            $insert_stmt->execute();
            $new_id_num = 1; // Start from 1
        } else {
            $row = $result->fetch_assoc();
            $new_id_num = $row['last_id'] + 1;
        }

        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();
        
        $conn->commit();
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}


// --- Security & Session Checks ---
// ... (These checks remain the same) ...
if ($_SERVER["REQUEST_METHOD"] !== "POST") { exit(); }
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) { exit(); }
if (!isset($_SESSION['registration_data'])) { header("Location: ../register.php"); exit(); }


// --- OTP Validation ---
// ... (This logic remains the same) ...
$submitted_otp = trim($_POST['otp']);
$session_data = $_SESSION['registration_data'];
if ($submitted_otp != $session_data['otp']) {
    $_SESSION['verify_error'] = "Invalid OTP. Please try again.";
    header("Location: ../register/verify_otp.php");
    exit();
}
// ... (OTP expiry check remains the same) ...


// --- Database Insertion ---
$conn = getDbConnection();

try {
    $display_user_id = generateDisplayId($session_data['role'], $conn);

    // UPDATED SQL QUERY to include profile_picture
    $sql_insert = "INSERT INTO users (display_user_id, username, name, email, phone, password, role, date_of_birth, gender, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    // UPDATED bind_param to include profile_picture (10 params, "ssssssssss")
    $stmt_insert->bind_param(
        "ssssssssss",
        $display_user_id,
        $session_data['username'],
        $session_data['name'],
        $session_data['email'],
        $session_data['phone'],
        $session_data['password'],
        $session_data['role'],
        $session_data['date_of_birth'],
        $session_data['gender'],
        $session_data['profile_picture'] // Add the profile picture filename
    );

    if ($stmt_insert->execute()) {
     // --- Send Welcome Email ---
        $mail = new PHPMailer(true);
        try {
            // ... (Email sending logic remains the same, but calls the updated template) ...
             $system_email = get_system_setting($conn, 'system_email');
             $gmail_app_password = get_system_setting($conn, 'gmail_app_password');
 
             if (empty($system_email) || empty($gmail_app_password)) {
                 throw new Exception("Email settings not configured, skipping welcome email.");
             }
 
             $mail->isSMTP();
             $mail->Host = 'smtp.gmail.com';
             $mail->SMTPAuth = true;
             $mail->Username = $system_email;
             $mail->Password = $gmail_app_password;
             $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
             $mail->Port = 587;
 
             $mail->setFrom($system_email, 'MedSync');
             $mail->addAddress($session_data['email'], $session_data['name']);
 
             $mail->isHTML(true);
             $mail->Subject = 'Welcome to MedSync, ' . $session_data['name'] . '!';
             // Use the updated welcome email template
             $mail->Body = getWelcomeEmailTemplate(
                 $session_data['name'],
                 $session_data['username'],
                 $display_user_id
             );
             $mail->send();

        } catch (Exception $e) {
            error_log("Welcome email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

        unset($_SESSION['registration_data']);
        $_SESSION['register_success'] = "Registration successful! Your User ID is " . $display_user_id . ". You can now log in.";
        header("Location: ../login.php");
        exit();

    } else {
        throw new Exception("Database error: Could not create account.");
    }
} catch (Exception $e) {
    // If user creation fails, delete the uploaded profile picture to clean up
    if (isset($session_data['profile_picture']) && $session_data['profile_picture'] !== 'default.png') {
        $file_to_delete = '../uploads/profile_pictures/' . $session_data['profile_picture'];
        if (file_exists($file_to_delete)) {
            unlink($file_to_delete);
        }
    }
    $_SESSION['verify_error'] = $e->getMessage();
    header("Location: ../register/verify_otp.php");
    exit();
} finally {
    if (isset($stmt_insert)) $stmt_insert->close();
    $conn->close();
}
?>