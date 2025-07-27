<?php
/**
 * Returns the HTML content for the professional OTP verification email.
 *
 * @param string $name The user's full name.
 * @param string $otp The 6-digit One-Time Password.
 * @return string The complete HTML email body.
 */
function getOtpEmailTemplate($name, $otp) {
    $currentYear = date('Y');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MedSync Verification Code</title>
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
                        <h1>Account Verification</h1>
                    </div>
                    <div class="content-body">
                        <p>Dear <strong>{$name}</strong>,</p>
                        <p>Thank you for registering with MedSync. To ensure the security of your account, please use the following One-Time Password (OTP) to complete your registration process.</p>
                        <div class="otp-box">
                            <p class="otp-label">Your Verification Code:</p>
                            <span class="otp-code">{$otp}</span>
                        </div>
                        <p class="validity-text">This code is valid for the next 10 minutes.</p>
                        <p>If you did not initiate this registration, please disregard this email. No further action is required.</p>
                        <p>Sincerely,<br>The MedSync Team</p>
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
?>