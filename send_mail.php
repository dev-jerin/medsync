<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require_once __DIR__ . '/vendor/autoload.php';

function send_mail($to, $subject, $body, $attachment_string = null, $attachment_name = null) {
    // It's better to include config and get a new DB connection
    // to ensure the function is self-contained.
    require_once __DIR__ . '/config.php';
    $conn = getDbConnection();
    
    // Fetch Gmail App Password from the database
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'gmail_app_password'");
    $stmt->execute();
    $app_password = $stmt->get_result()->fetch_assoc()['setting_value'];
    $stmt->close();
    // Note: Do not close the global connection here, as it may be used elsewhere in the script.

    if (!$app_password) {
        // Log error or handle it gracefully
        error_log("Gmail app password not found in system_settings.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        //Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable for verbose debug output
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'medsync.calysta@gmail.com'; // Your Gmail address
        $mail->Password   = $app_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;

        //Recipients
        $mail->setFrom('medsync.calysta@gmail.com', 'MedSync Notifications');
        $mail->addAddress($to);

        //Attachments
        if ($attachment_string && $attachment_name) {
            $mail->addStringAttachment($attachment_string, $attachment_name);
        }

        //Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}