<?php
/**
 * Returns the HTML content for the welcome email.
 * This template is mobile-responsive.
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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to MedSync</title>
    <style>
        /* Import Google Font */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap');

        /* Basic Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; font-family: 'Poppins', Arial, sans-serif; }

        /* Main Styles */
        .container {
            width: 100%;
            padding: 20px;
            background-color: #f1f5f9;
        }
        .main-content {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin: 0 auto;
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #007BFF, #17a2b8);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 26px;
            font-weight: 700;
        }
        .content-body {
            padding: 30px 35px;
            color: #343a40;
            line-height: 1.7;
            text-align: left;
        }
        .content-body p {
            font-size: 16px;
            margin: 0 0 15px 0;
        }
        .details-box {
            background-color: #f8f9fa;
            border-left: 4px solid #007BFF;
            margin: 25px 0;
            padding: 15px 20px;
            border-radius: 0 8px 8px 0;
        }
        .details-box p {
            margin: 8px 0;
            font-size: 15px;
        }
        .details-box strong {
            color: #333;
        }
        .footer {
            text-align: center;
            padding: 25px;
            font-size: 13px;
            color: #6c757d;
            background-color: #f8f9fa;
        }
        .footer p {
            margin: 5px 0;
        }

        /* Responsive Styles */
        @media screen and (max-width: 600px) {
            .content-body {
                padding: 25px 20px;
            }
            .header h1 {
                font-size: 22px;
            }
            .content-body p, .details-box p {
                font-size: 15px;
            }
        }
    </style>
</head>
<body style="margin: 0 !important; padding: 0 !important; background-color: #f1f5f9;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" width="100%" class="container">
        <tr>
            <td align="center">
                <div class="main-content">
                    <div class="header">
                        <h1>Welcome Aboard!</h1>
                    </div>

                    <div class="content-body">
                        <p>Hello <strong>{$name}</strong>,</p>
                        <p>Thank you for joining MedSync! Your account has been successfully created. We are thrilled to have you as part of the Calysta Health Institute community.</p>
                        
                        <p>Here are your account details. Please keep them safe:</p>
                        <div class="details-box">
                            <p><strong>Full Name:</strong> {$name}</p>
                            <p><strong>Username:</strong> {$username}</p>
                            <p><strong>User ID:</strong> <span style="font-weight: bold; color: #007BFF;">{$display_user_id}</span></p>
                            <p><strong>Email:</strong> {$email}</p>
                        </div>

                        <p>You can now log in to your dashboard to manage appointments, view your records, and take control of your healthcare journey.</p>

                    </div>

                    <div class="footer">
                        <p>&copy; 2025 Calysta Health Institute. All Rights Reserved.</p>
                        <p>Calysta Health Institute, Kerala, India</p>
                    </div>
                </div>
                </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
?>