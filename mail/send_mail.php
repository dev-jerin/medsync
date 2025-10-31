<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config.php');

function send_mail($name, $to, $subject, $body, $attachment_content = null, $attachment_filename = null) {
    $conn = getDbConnection(); // Use the global function to get the connection
    
    $system_email = get_system_setting($conn, 'system_email');
    $gmail_app_password = get_system_setting($conn, 'gmail_app_password');

    if (empty($system_email) || empty($gmail_app_password)) {
        error_log("Email not sent: Mail service is not configured in system settings.");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $system_email;
        $mail->Password = $gmail_app_password;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom($system_email, $name);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        // Add attachment if provided
        if ($attachment_content !== null && $attachment_filename !== null) {
            $mail->addStringAttachment($attachment_content, $attachment_filename);
        }

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message to {$to} could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}