<?php
/**
 * Processes the OTP, creates the user account, and sends a welcome email.
 */

// --- PHPMailer Inclusion ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/PHPMailer/PHPMailer/src/PHPMailer.php';
require '../vendor/PHPMailer/PHPMailer/src/SMTP.php';

// --- Other Required Files ---
require_once '../config.php';
require_once '../register/welcome_email_template.php';

/**
 * Generates a unique, sequential display ID for a new user based on their role.
 * This is a duplicate of the function in admin_dashboard.php for use in registration.
 *
 * @param string $role The role of the user ('admin', 'doctor', 'staff', 'user').
 * @param mysqli $conn The database connection object.
 * @return string The formatted display ID.
 * @throws Exception If the role is invalid or a database error occurs.
 */
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
            throw new Exception("Role prefix '$prefix' not found in counters table.");
        }
        $row = $result->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;

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
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $_SESSION['verify_error'] = "Invalid request method.";
    header("Location: ../register/verify_otp.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['verify_error'] = "CSRF validation failed. Please try again.";
    header("Location: ../register/verify_otp.php");
    exit();
}
if (!isset($_SESSION['registration_data'])) {
    header("Location: ../register.php");
    exit();
}

// --- OTP Validation ---
$submitted_otp = trim($_POST['otp']);
$session_data = $_SESSION['registration_data'];

if ($submitted_otp != $session_data['otp']) {
    $_SESSION['verify_error'] = "Invalid OTP. Please try again.";
    header("Location: ../register/verify_otp.php");
    exit();
}

$otp_expiry_time = 600; // 10 minutes
if (time() - $session_data['timestamp'] > $otp_expiry_time) {
    $_SESSION['register_error'] = "OTP has expired. Please start the registration process again.";
    unset($_SESSION['registration_data']);
    header("Location: ../register.php");
    exit();
}

// --- Database Insertion ---
$conn = getDbConnection();

try {
    // Generate the final display_user_id
    $display_user_id = generateDisplayId($session_data['role'], $conn);

    $sql_insert = "INSERT INTO users (display_user_id, username, name, email, phone, password, role, date_of_birth, gender) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_insert = $conn->prepare($sql_insert);

    $stmt_insert->bind_param(
        "sssssssss",
        $display_user_id,
        $session_data['username'],
        $session_data['name'],
        $session_data['email'],
        $session_data['phone'],
        $session_data['password'],
        $session_data['role'],
        $session_data['date_of_birth'],
        $session_data['gender']
    );

    if ($stmt_insert->execute()) {
        // --- Send Welcome Email ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'medsync.calysta@gmail.com';
            $mail->Password   = 'sswyqzegdpyixbyw';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('medsync.calysta@gmail.com', 'MedSync');
            $mail->addAddress($session_data['email'], $session_data['name']);

            $mail->isHTML(true);
            $mail->Subject = 'Welcome to MedSync, ' . $session_data['name'] . '!';
            $mail->Body    = getWelcomeEmailTemplate(
                $session_data['name'],
                $session_data['username'],
                $display_user_id,
                $session_data['email']
            );
            $mail->AltBody = "Welcome to MedSync! Your User ID is {$display_user_id}.";
            $mail->send();
        } catch (Exception $e) {
            // Optional: Log email error, but don't fail registration
            error_log("Welcome email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }

        // Clean up and redirect
        unset($_SESSION['registration_data']);
        $_SESSION['register_success'] = "Registration successful! Your User ID is " . $display_user_id . ". You can now log in.";
        header("Location: ../login.php");
        exit();

    } else {
        throw new Exception("Database error: Could not create account.");
    }
} catch (Exception $e) {
    $_SESSION['verify_error'] = $e->getMessage();
    header("Location: ../register/verify_otp.php");
    exit();
} finally {
    if (isset($stmt_insert)) $stmt_insert->close();
    $conn->close();
}
