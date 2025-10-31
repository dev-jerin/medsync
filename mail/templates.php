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
        <div class="header"><h1>Account Update Notification</h1></div>
        <div class="content-body">
            <p>Dear <strong>{$name}</strong>,</p>
            <p>This is to notify you that your MedSync account details have been updated by an administrator.</p>
            
            <div class="changes-box">
                <h3 style="margin-top: 0; color: #0067FF;">Changes Made:</h3>
                {$changesHtml}
            </div>
            
            <div class="alert-box">
                <p style="margin: 0;"><strong>📋 Modification Details:</strong></p>
                <p style="margin: 5px 0 0 0;">Modified by: <strong>System Administrator</strong><br>
                Date & Time: <strong>{$datetime}</strong></p>
            </div>
            
            <div class="contact-info">
                <p style="margin: 0; font-size: 14px;"><strong>ℹ️ Important:</strong></p>
                <p style="margin: 5px 0 0 0; font-size: 14px;">If you did not expect these changes or have any concerns, please contact our support team immediately at <a href="mailto:medsync.calysta@gmail.com">medsync.calysta@gmail.com</a> or call us during business hours.</p>
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

/**
 * Email template for lab result ready notification
 * 
 * @param string $name Patient's name
 * @param string $test_name Name of the lab test
 * @param string $test_date Date when test was conducted (optional)
 * @param string $result_status Status of the result (e.g., 'Ready', 'Completed')
 * @param string $datetime Date and time when result became available
 * @return string HTML email template
 */
function getLabResultReadyTemplate($name, $test_name, $test_date = null, $result_status = 'Ready', $datetime = null) {
    $currentYear = date('Y');
    $datetime = $datetime ?? date('d M Y, h:i A');
    
    $testDateInfo = $test_date ? "<p><strong>Test Date:</strong> " . date('d M Y', strtotime($test_date)) . "</p>" : '';
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Results Ready - MedSync</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 20px auto; max-width: 600px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #38a169 0%, #2f855a 100%); color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #22543d; }
        .header h1 { margin: 0; font-size: 28px; }
        .header-icon { font-size: 48px; margin-bottom: 10px; }
        .content-body { padding: 40px 35px; line-height: 1.6; }
        .result-box { background: linear-gradient(135deg, #e6fffa 0%, #b2f5ea 100%); border-left: 4px solid #38a169; margin: 25px 0; padding: 25px; border-radius: 8px; }
        .result-box h3 { margin-top: 0; color: #2f855a; font-size: 20px; }
        .status-badge { display: inline-block; background-color: #38a169; color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 14px; }
        .action-box { background-color: #edf2f7; border: 2px dashed #4299e1; margin: 25px 0; padding: 20px; border-radius: 8px; text-align: center; }
        .action-button { display: inline-block; background-color: #0067FF; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 10px; }
        .action-button:hover { background-color: #0052cc; }
        .info-box { background-color: #fffbeb; border-left: 4px solid #ffc107; margin: 25px 0; padding: 15px 20px; border-radius: 8px; font-size: 14px; }
        .contact-section { background-color: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; background-color: #f7fafc; }
        .divider { height: 1px; background-color: #e2e8f0; margin: 30px 0; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="header">
            <div class="header-icon">🧪</div>
            <h1>Your Lab Results Are Ready!</h1>
        </div>
        <div class="content-body">
            <p>Dear <strong>{$name}</strong>,</p>
            <p>We are pleased to inform you that your laboratory test results are now available for review.</p>
            
            <div class="result-box">
                <h3>📋 Test Information</h3>
                <p><strong>Test Name:</strong> {$test_name}</p>
                {$testDateInfo}
                <p><strong>Result Status:</strong> <span class="status-badge">{$result_status}</span></p>
                <p><strong>Available Since:</strong> {$datetime}</p>
            </div>
            
            <div class="action-box">
                <p style="margin-top: 0; font-size: 16px; color: #2d3748;"><strong>🔐 Access Your Results</strong></p>
                <p style="margin: 10px 0; font-size: 14px;">Log in to your MedSync dashboard to view and download your complete lab report.</p>
            </div>
            
            <div class="info-box">
                <p style="margin: 0;"><strong>⚕️ What's Next?</strong></p>
                <p style="margin: 10px 0 0 0;">• Review your results in your patient dashboard<br>
                • Download the detailed PDF report if available<br>
                • Contact your doctor if you have any questions<br>
                • Schedule a follow-up consultation if recommended</p>
            </div>
            
            <div class="divider"></div>
            
            <div class="contact-section">
                <p style="margin-top: 0; font-weight: 600; color: #2d3748;">📞 Need Assistance?</p>
                <p style="margin: 10px 0; font-size: 14px;">If you have questions about your results or need to discuss them with your healthcare provider, please:</p>
                <ul style="margin: 10px 0; padding-left: 20px; font-size: 14px;">
                    <li>Contact your assigned physician</li>
                    <li>Call our patient support line during business hours</li>
                    <li>Email us at <a href="mailto:medsync.calysta@gmail.com" style="color: #0067FF;">medsync.calysta@gmail.com</a></li>
                </ul>
            </div>
            
            <p style="margin-top: 30px;">Thank you for choosing MedSync for your healthcare needs.</p>
            <p>Best regards,<br><strong>The MedSync Team</strong><br>Calysta Health Institute</p>
        </div>
    </div>
    <div class="footer">
        <p style="margin: 0 0 10px 0;">This is an automated notification. Please do not reply to this email.</p>
        <p style="margin: 0;">&copy; {$currentYear} Calysta Health Institute. All Rights Reserved.</p>
    </div>
</body>
</html>
HTML;
}
?>

