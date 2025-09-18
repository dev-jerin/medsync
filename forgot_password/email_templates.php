<?php
/**
 * Contains HTML email templates for the MedSync application.
 */

/**
 * Returns the HTML content for the password reset OTP email.
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @return string The complete HTML email body.
 */
function getPasswordResetEmailTemplate($name, $otp) {
    $currentYear = date('Y');
    
    // Using a HEREDOC for clean, modern HTML structure
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MedSync Password Reset Code</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .container { padding: 20px; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 0 auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #0067FF; color: #ffffff; padding: 40px 20px; text-align: center; border-bottom: 5px solid #00D9E9; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .content-body { padding: 40px 35px; line-height: 1.6; text-align: left; }
        .content-body p { font-size: 16px; margin: 0 0 20px 0; }
        .otp-box { text-align: center; margin: 30px 0; }
        .otp-label { font-size: 14px; color: #6c757d; margin-bottom: 10px; }
        .otp-code { display: inline-block; background-color: #e6f0ff; color: #0058d6; font-size: 36px; font-weight: 700; letter-spacing: 10px; padding: 15px 30px; border-radius: 8px; user-select: all; border: 1px dashed #0067FF; }
        .validity-text { font-size: 14px; color: #6c757d; text-align: center; margin-top: 15px; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Password Reset Request</h1>
                    </div>
                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>We received a request to reset the password for your MedSync account. Please use the following One-Time Password (OTP) to complete the process.</p>
                        <div class="otp-box">
                            <p class="otp-label">Your Password Reset Code:</p>
                            <span class="otp-code">{$otp}</span>
                        </div>
                        <p class="validity-text">This code is valid for the next 10 minutes.</p>
                        <p>If you did not request a password reset, please ignore this email. Your account is still secure and no action is needed.</p>
                        <p>Sincerely,<br>The MedSync Support Team</p>
                    </div>
                </div>
                <div class="footer">
                    &copy; {$currentYear} Calysta Health Institute. All Rights Reserved.<br>
                    Kerala, India
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Returns the HTML content for the password reset confirmation email.
 *
 * @param string $name The user's full name.
 * @param string $datetime The date and time of the password change.
 * @return string The complete HTML email body.
 */
function getPasswordResetConfirmationTemplate($name, $datetime) {
    $currentYear = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert: Your MedSync Password Was Changed</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap');
        body { margin: 0; padding: 0; width: 100% !important; font-family: 'Inter', Arial, sans-serif; background-color: #f7fafc; color: #4a5568; }
        .container { padding: 20px; }
        .main-content { background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; margin: 0 auto; max-width: 600px; overflow: hidden; }
        .header { background-color: #ffc107; color: #1a202c; padding: 40px 20px; text-align: center; border-bottom: 5px solid #e9a900; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
        .content-body { padding: 40px 35px; line-height: 1.6; text-align: left; }
        .content-body p { font-size: 16px; margin: 0 0 20px 0; }
        .alert-details { background-color: #fffbeb; border-left: 4px solid #ffc107; margin: 25px 0; padding: 20px; border-radius: 8px; }
        .alert-details p { margin: 10px 0; font-size: 15px; color: #5c3f00; }
        .alert-details strong { color: #1a202c; }
        .footer { text-align: center; padding: 25px; font-size: 13px; color: #a0aec0; }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Security Alert</h1>
                    </div>
                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>This is a confirmation that the password for your MedSync account was successfully changed. Your account security is our top priority.</p>
                        <div class="alert-details">
                            <p><strong>Date & Time of Change:</strong> {$datetime}</p>
                        </div>
                        <p>If you made this change, you can safely ignore this email. Your account is secure.</p>
                        <p><strong>If you did NOT authorize this change,</strong> please contact our support team immediately so we can help you secure your account.</p>
                        <p>Sincerely,<br>The MedSync Security Team</p>
                    </div>
                </div>
                <div class="footer">
                    &copy; {$currentYear} Calysta Health Institute. All Rights Reserved.<br>
                    Kerala, India
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}