<?php
/**
 * MedSync - Email Templates
 * This file contains functions that generate the HTML body for all system emails.
 * Centralizing them here makes them easy to manage and update.
 */

/**
 * Returns the HTML content for the OTP verification email (for new registrations).
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @param string $ip_address The user's IP address (optional).
 * @return string The complete HTML email body.
 */
function getOtpEmailTemplate($name, $otp, $ip_address = null) {
    $currentYear = date('Y');
    $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'];
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your MedSync Verification Code</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 20px auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #0067FF; color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #00D9E9; }
        .header h1 { margin: 0; font-size: 28px; }
        .content-body { padding: 40px 35px; line-height: 1.6; }
        .otp-code { background-color: #e6f0ff; color: #0058d6; font-size: 36px; font-weight: 700; letter-spacing: 10px; padding: 15px 30px; border-radius: 8px; border: 1px dashed #0067FF; text-align: center; margin: 20px 0; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header"><h1>Account Verification</h1></div>
        <div class="content-body">
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Thank you for registering with MedSync. Use the code below to complete your registration.</p>
            <div class="otp-code">{$otp}</div>
            <p style="font-size: 14px; color: #6c757d; text-align: center;">This code is valid for 10 minutes.</p>
            <p>If you did not initiate this, please disregard this email.</p>
            <p style="font-size: 12px; color: #888; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <strong>Security Information:</strong><br>
                Request from IP: {$ip_address}<br>
                Time: {$currentYear}
            </p>
            <p>Sincerely,<br>The MedSync Team</p>
        </div>
    </div>
    <div class="footer">&copy; {$currentYear} Calysta Health Institute. All Rights Reserved.</div>
</body>
</html>
HTML;
}

/**
 * Returns the HTML content for the welcome email after successful registration.
 *
 * @param string $name The user's full name.
 * @param string $username The user's username.
 * @param string $display_user_id The user's formatted User ID (e.g., U0001).
 * @param string $ip_address The user's IP address (optional).
 * @return string The complete HTML email body.
 */
function getWelcomeEmailTemplate($name, $username, $display_user_id, $ip_address = null) {
    $currentYear = date('Y');
    $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'];
    $currentDateTime = date('d M Y, h:i A');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Welcome to MedSync!</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 20px auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #0067FF; color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #00D9E9; }
        .header h1 { margin: 0; font-size: 28px; }
        .content-body { padding: 40px 35px; line-height: 1.6; }
        .details-box { background-color: #f7fafc; border-left: 4px solid #0067FF; margin: 25px 0; padding: 20px; border-radius: 8px; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header"><h1>Welcome to MedSync</h1></div>
        <div class="content-body">
            <p>Dear <strong>{$name}</strong>,</p>
            <p>Your account with MedSync has been successfully created. You can now log in to manage your appointments and health records.</p>
            <div class="details-box">
                <p><strong>Your User ID:</strong> <span style="font-weight: bold; color: #0058d6;">{$display_user_id}</span></p>
                <p><strong>Your Username:</strong> {$username}</p>
            </div>
            <p>Thank you for choosing Calysta Health Institute.</p>
            <p style="font-size: 12px; color: #888; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <strong>Registration Details:</strong><br>
                Registered from IP: {$ip_address}<br>
                Time: {$currentDateTime}
            </p>
            <p>Sincerely,<br>The MedSync Team</p>
        </div>
    </div>
    <div class="footer">&copy; {$currentYear} Calysta Health Institute. All Rights Reserved.</div>
</body>
</html>
HTML;
}

/**
 * Returns the HTML content for the password reset OTP email.
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @param string $ip_address The user's IP address (optional).
 * @return string The complete HTML email body.
 */
function getPasswordResetEmailTemplate($name, $otp, $ip_address = null) {
    $currentYear = date('Y');
    $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'];
    $currentDateTime = date('d M Y, h:i A');
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your MedSync Password Reset Code</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 20px auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #0067FF; color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #00D9E9; }
        .header h1 { margin: 0; font-size: 28px; }
        .content-body { padding: 40px 35px; line-height: 1.6; }
        .otp-code { background-color: #e6f0ff; color: #0058d6; font-size: 36px; font-weight: 700; letter-spacing: 10px; padding: 15px 30px; border-radius: 8px; border: 1px dashed #0067FF; text-align: center; margin: 20px 0; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header"><h1>Password Reset Request</h1></div>
        <div class="content-body">
            <p>Hello <strong>{$name}</strong>,</p>
            <p>We received a request to reset your password. Use the code below to complete the process.</p>
            <div class="otp-code">{$otp}</div>
            <p style="font-size: 14px; color: #6c757d; text-align: center;">This code is valid for 10 minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
            <p style="font-size: 12px; color: #888; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                <strong>Security Information:</strong><br>
                Request from IP: {$ip_address}<br>
                Time: {$currentDateTime}
            </p>
            <p>Sincerely,<br>The MedSync Support Team</p>
        </div>
    </div>
    <div class="footer">&copy; {$currentYear} Calysta Health Institute. All Rights Reserved.</div>
</body>
</html>
HTML;
}

/**
 * Returns the HTML content for the password reset confirmation email.
 *
 * @param string $name The user's full name.
 * @param string $datetime The date and time of the password change.
 * @param string $ip_address The user's IP address (optional).
 * @return string The complete HTML email body.
 */
function getPasswordResetConfirmationTemplate($name, $datetime, $ip_address = null) {
    $currentYear = date('Y');
    $ip_address = $ip_address ?: $_SERVER['REMOTE_ADDR'];
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Alert: Your MedSync Password Was Changed</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 20px auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #ffc107; color: #1a202c; padding: 40px 20px; text-align: center; border-bottom: 5px solid #e9a900; }
        .header h1 { margin: 0; font-size: 28px; }
        .content-body { padding: 40px 35px; line-height: 1.6; }
        .alert-details { background-color: #fffbeb; border-left: 4px solid #ffc107; margin: 25px 0; padding: 20px; border-radius: 8px; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header"><h1>Security Alert</h1></div>
        <div class="content-body">
            <p>Hello <strong>{$name}</strong>,</p>
            <p>This is a confirmation that the password for your MedSync account was successfully changed at the time below.</p>
            <div class="alert-details">
                <p><strong>Date & Time of Change:</strong> {$datetime}</p>
                <p><strong>IP Address:</strong> {$ip_address}</p>
            </div>
            <p>If you made this change, you can safely ignore this email. If you did NOT authorize this, please contact support immediately.</p>
            <p>Sincerely,<br>The MedSync Security Team</p>
        </div>
    </div>
    <div class="footer">&copy; {$currentYear} Calysta Health Institute. All Rights Reserved.</div>
</body>
</html>
HTML;
}

/**
 * Returns the HTML content for the account modification notification email.
 *
 * @param string $name The user's full name.
 * @param string $username The user's username.
 * @param array $changes Array of changes made (e.g., ['name' => ['old' => 'John', 'new' => 'Jane'], ...])
 * @param string $datetime The date and time of the modification.
 * @param string $admin_name The name of the administrator who made the changes.
 * @return string The complete HTML email body.
 */
function getAccountModificationTemplate($name, $username, $changes, $datetime, $admin_name = 'System Administrator') {
    $currentYear = date('Y');
    
    // Determine if the user modified their own profile
    // Check for various self-modification indicators
    $isSelfModification = (
        $admin_name === $name || 
        $admin_name === 'Self' || 
        $admin_name === $username ||
        stripos($admin_name, 'You') !== false ||
        stripos($admin_name, 'Self') !== false
    );
    
    // Set appropriate messaging based on who made the change
    if ($isSelfModification) {
        $headerTitle = 'Profile Update Confirmation';
        $introMessage = "This is to confirm that your MedSync profile has been successfully updated.";
        $modifiedByLabel = 'Modified by';
        $modifiedByValue = 'You';
        $alertTitle = '✓ Update Confirmation';
        $importantNote = "This email confirms the changes you made to your profile. If you did not make these changes, please contact our support team immediately.";
    } else {
        $headerTitle = 'Account Update Notification';
        $introMessage = "This is to notify you that your MedSync account details have been updated by an administrator.";
        $modifiedByLabel = 'Modified by';
        $modifiedByValue = $admin_name;
        $alertTitle = '📋 Modification Details';
        $importantNote = "If you did not expect these changes or have any concerns, please contact our support team immediately.";
    }
    
    // Build the changes list HTML
    $changesHtml = '';
    foreach ($changes as $field => $change) {
        $fieldLabel = ucwords(str_replace('_', ' ', $field));
        if (is_array($change) && isset($change['old']) && isset($change['new'])) {
            $changesHtml .= "<p><strong>{$fieldLabel}:</strong><br>
                <span style='color: #e53e3e; text-decoration: line-through;'>{$change['old']}</span> 
                → <span style='color: #38a169;'>{$change['new']}</span></p>";
        } else {
            $changesHtml .= "<p><strong>{$fieldLabel}:</strong> {$change}</p>";
        }
    }
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Details Updated - MedSync</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 20px auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #0067FF; color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #00D9E9; }
        .header h1 { margin: 0; font-size: 28px; }
        .content-body { padding: 40px 35px; line-height: 1.6; }
        .changes-box { background-color: #f7fafc; border-left: 4px solid #0067FF; margin: 25px 0; padding: 20px; border-radius: 8px; }
        .changes-box p { margin: 10px 0; }
        .alert-box { background-color: #fffbeb; border-left: 4px solid #ffc107; margin: 25px 0; padding: 15px 20px; border-radius: 8px; font-size: 14px; }
        .contact-info { background-color: #e6f0ff; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header"><h1>{$headerTitle}</h1></div>
        <div class="content-body">
            <p>Dear <strong>{$name}</strong>,</p>
            <p>{$introMessage}</p>
            
            <div class="changes-box">
                <h3 style="margin-top: 0; color: #0067FF;">Changes Made:</h3>
                {$changesHtml}
            </div>
            
            <div class="alert-box">
                <p style="margin: 0;"><strong>{$alertTitle}:</strong></p>
                <p style="margin: 5px 0 0 0;">{$modifiedByLabel}: <strong>{$modifiedByValue}</strong><br>
                Date & Time: <strong>{$datetime}</strong></p>
            </div>
            
            <div class="contact-info">
                <p style="margin: 0; font-size: 14px;"><strong>ℹ️ Important:</strong></p>
                <p style="margin: 5px 0 0 0; font-size: 14px;">{$importantNote} Contact us at <a href="mailto:medsync.calysta@gmail.com">medsync.calysta@gmail.com</a> or call during business hours.</p>
            </div>
            
            <p>Thank you for being a valued member of the MedSync community.</p>
            <p>Sincerely,<br>The MedSync Team</p>
        </div>
    </div>
    <div class="footer">&copy; {$currentYear} Calysta Health Institute. All Rights Reserved.</div>
</body>
</html>
HTML;
}
?>


