<?php
// Start the session to manage user login state
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - MedSync</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        /* --- Base Styles & Variables --- */
        :root {
            --primary-color: #007BFF;
            --primary-dark: #0056b3;
            --secondary-color: #17a2b8;
            --text-dark: #343a40;
            --text-light: #f8f9fa;
            --background-light: #ffffff;
            --background-grey: #f1f5f9;
            --border-radius: 12px;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-grey);
            color: var(--text-dark);
            line-height: 1.8;
        }
        
        /* --- Utility Classes --- */
        .container {
            width: 90%;
            max-width: 900px; /* More readable width for text */
            margin: 0 auto;
            padding: 4rem 15px;
        }

        /* --- Header --- */
        .header {
            padding: 1rem 0;
            background-color: var(--background-light);
            box-shadow: var(--shadow-sm);
            text-align: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }

        .logo-img {
            height: 40px;
            width: auto;
            margin-right: 8px;
        }

        /* --- Policy Content Styles --- */
        .policy-content {
            background: var(--background-light);
            padding: 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            margin-top: 2rem;
        }

        .policy-content h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 2rem;
        }

        .policy-content h2 {
            font-size: 1.8rem;
            font-weight: 600;
            margin-top: 2.5rem;
            margin-bottom: 1rem;
            color: var(--text-dark);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }

        .policy-content h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--secondary-color);
        }

        .policy-content p, .policy-content ul {
            margin-bottom: 1rem;
            color: #555;
        }
        
        .policy-content strong {
            color: var(--text-dark);
        }

        .policy-content ul {
            list-style-position: inside;
            padding-left: 1rem;
        }

        .policy-content li {
            margin-bottom: 0.5rem;
        }

        .policy-content a {
            color: var(--primary-dark);
            text-decoration: none;
            font-weight: 500;
        }

        .policy-content a:hover {
            text-decoration: underline;
        }

        .last-updated {
            text-align: center;
            color: #777;
            font-style: italic;
            margin-bottom: 2rem;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 3rem;
            font-weight: 600;
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .back-link:hover {
            color: var(--primary-dark);
        }

    </style>
</head>
<body>

    <!-- Header -->
    <header class="header">
        <a href="index.php" class="logo">
            <img src="images/logo.png" alt="MedSync Logo" class="logo-img" onerror="this.style.display='none'">MedSync
        </a>
    </header>

    <main>
        <div class="container">
            <div class="policy-content">
                <h1>Terms and Conditions</h1>
                <p class="last-updated">Last Updated: July 15, 2025</p>

                <h2>1. Acceptance of the Terms of Use</h2>
                <p>These Terms of Use are entered into by and between you and the MedSync Healthcare Platform (“MedSync,” “our,” “us,” or “we”). The following terms and conditions, together with our Privacy Policy, govern your access to and use of our platform and associated services (collectively, “MedSync’s Services”). By using the platform, you accept and agree to be bound by these Terms of Use.</p>

                <h2>2. Representations You Make</h2>
                <p>By using MedSync’s Services, you represent and warrant that all information you provide is current, true, and accurate. If you are a healthcare provider (e.g., Doctor or Staff), you warrant that you have all necessary rights and consents to manage patient information as per your role.</p>

                <h2>3. Who We Are and What We Are Not</h2>
                <p>MedSync is a technology platform for Calysta Health Institute to manage healthcare operations like appointments, billing, and prescriptions. <strong>We do not practice medicine.</strong> The platform is a tool to facilitate care management and does not create a physician-patient relationship between you and MedSync.</p>
                <p><strong>IF YOU ARE EXPERIENCING A MEDICAL EMERGENCY, CALL FOR EMERGENCY MEDICAL HELP IMMEDIATELY.</strong> Never disregard professional medical advice because of something you have seen on our platform.</p>

                <h2>4. Accessing the Platform and Account Security</h2>
                <p>We are dedicated to protecting your information through robust security measures. You are responsible for keeping your account credentials (username, password) confidential and for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account. We have the right to disable any user account if you have violated these terms.</p>

                <h2>5. Prohibited Uses</h2>
                <p>You may use the platform only for lawful purposes. You agree not to:</p>
                <ul>
                    <li>Use the platform in any way that violates the law.</li>
                    <li>Transmit any "spam" or unsolicited promotional material.</li>
                    <li>Impersonate any person or entity.</li>
                    <li>Introduce any viruses, Trojan horses, or other malicious material.</li>
                    <li>Attempt to gain unauthorized access to any part of the platform, including circumventing our security controls.</li>
                    <li>Interfere with the proper working of the platform.</li>
                </ul>

                <h2>6. Reliance on Information Posted</h2>
                <p>The information presented on the platform is for informational and operational purposes. We do not warrant the absolute accuracy or completeness of all information, especially data entered by other users. Any reliance you place on such information is strictly at your own risk. We disclaim all liability arising from any reliance on such materials.</p>

                <h2>7. Changes to These Terms</h2>
                <p>We may update these Terms of Use from time to time. We will notify you of any changes by posting the new terms on this page and updating the "Last Updated" date. You are advised to review these Terms periodically for any changes.</p>

                <h2>8. Contact Us</h2>
                <p>If you have any questions about these Terms of Use, please contact us at:</p>
                <ul>
                    <li><strong>Email:</strong> <a href="mailto:medsync.calysta@gmail.com">medsync.calysta@gmail.com</a></li>
                    <li><strong>Phone:</strong> <a href="tel:+914523531245">+91 45235 31245</a></li>
                </ul>

                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </main>
</body>
</html>