<?php
/**
 * Returns the HTML content for the welcome email.
 *
 * @param string $name The user's full name.
 * @param string $username The user's username.
 * @param string $display_user_id The user's formatted User ID (e.g., U0001).
 * @param string $email The user's email address.
 * @return string The complete HTML email body.
 */
function getWelcomeEmailTemplate($name, $username, $display_user_id, $email) {
    // Using a HEREDOC for clean HTML structure
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to MedSync</title>
    <style>
        /* Basic Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: 'Poppins', Arial, sans-serif; }

        /* Main Styles */
        .container {
            padding: 20px;
            background-color: #f1f5f9;
        }
        .main-content {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(90deg, #007BFF, #17a2b8);
            color: #ffffff;
            padding: 40px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .content-body {
            padding: 30px 40px;
            color: #343a40;
            line-height: 1.7;
        }
        .content-body p {
            font-size: 16px;
        }
        .details-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007BFF;
            margin: 20px 0;
            padding: 15px 20px;
        }
        .details-box p {
            margin: 8px 0;
            font-size: 15px;
        }
        .footer {
            text-align: center;
            padding: 20px;
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #f1f5f9;">
    <div class="container">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td>
                    <div class="main-content">
                        <!-- Header -->
                        <div class="header">
                             <div style="text-decoration:none; color: #ffffff; font-size: 24px; font-weight: bold;">MedSync</div>
                            <h1>Welcome Aboard!</h1>
                        </div>

                        <!-- Body -->
                        <div class="content-body">
                            <p>Hello <strong>{$name}</strong>,</p>
                            <p>Thank you for joining MedSync! Your account has been successfully created. We are thrilled to have you as part of the Calysta Health Institute community.</p>
                            
                            <p>Here are your registration details:</p>
                            <div class="details-box">
                                <p><strong>Full Name:</strong> {$name}</p>
                                <p><strong>Username:</strong> {$username}</p>
                                <p><strong>User ID:</strong> <span style="font-weight: bold; color: #007BFF;">{$display_user_id}</span></p>
                                <p><strong>Email:</strong> {$email}</p>
                            </div>

                            <p>You can now log in to your dashboard to manage appointments, view your records, and more.</p>

                        </div>

                        <!-- Footer -->
                        <div class="footer">
                            <p>&copy; 2025 Calysta Health Institute. All Rights Reserved.</p>
                            <p>If you did not create this account, please contact our support team immediately.</p>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
HTML;
}
?>
