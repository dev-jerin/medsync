<?php
// Start the session to manage user login state
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - MedSync</title>

    <!-- Google Fonts: Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    
    <!-- Font Awesome for Icons -->
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

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="policy-content">
                <h1>Privacy Policy</h1>
                <p class="last-updated">Last Updated: June 28, 2025</p>

                <p>Calysta Health Institute ("we," "our," or "us") is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our MedSync platform and associated services (collectively, the "Services").</p>

                <h2>1. Information We Collect</h2>
                <p>We may collect personal information from you in a variety of ways, including:</p>
                
                <h3>Personal Data</h3>
                <ul>
                    <li><strong>Contact Information:</strong> Name, email address, phone number, and postal address.</li>
                    <li><strong>Demographic Information:</strong> Date of birth, gender, and age.</li>
                    <li><strong>Health Information:</strong> Medical history, prescriptions, lab results, appointment details, and other health-related data you provide.</li>
                    <li><strong>Account Information:</strong> Username, password, and user role (e.g., patient, doctor, staff).</li>
                </ul>

                <h3>Usage Data</h3>
                <p>We automatically collect certain information when you access our Services, such as your IP address, browser type, operating system, access times, and the pages you have viewed directly before and after accessing the site.</p>

                <h2>2. How We Use Your Information</h2>
                <p>We use the information we collect to:</p>
                <ul>
                    <li>Provide, operate, and maintain our Services.</li>
                    <li>Manage your account and appointments.</li>
                    <li>Process transactions and send you related information, including confirmations and invoices.</li>
                    <li>Respond to your comments, questions, and requests and provide customer service.</li>
                    <li>Send you technical notices, updates, security alerts, and administrative messages.</li>
                    <li>Improve our Services, and for internal research and analysis.</li>
                    <li>Comply with legal obligations and enforce our terms and conditions.</li>
                </ul>

                <h2>3. Disclosure of Your Information</h2>
                <p>We do not share your personal health information with third parties except as described in this Privacy Policy. We may share information with:</p>
                <ul>
                    <li><strong>Healthcare Providers:</strong> With your consent, to facilitate your care.</li>
                    <li><strong>Service Providers:</strong> Who perform services for us (e.g., data hosting, email delivery) and are contractually obligated to protect your information.</li>
                    <li><strong>Legal Requirements:</strong> If required by law, such as to comply with a subpoena or other legal process.</li>
                </ul>

                <h2>4. Security of Your Information</h2>
                <p>We use administrative, technical, and physical security measures to help protect your personal information. These measures include:</p>
                <ul>
                    <li><strong>Data Encryption:</strong> Using encryption (e.g., SSL/TLS) to protect data in transit.</li>
                    <li><strong>Access Controls:</strong> Implementing strict role-based access controls to limit access to sensitive information.</li>
                    <li><strong>Secure Password Policies:</strong> Enforcing strong password requirements and secure storage.</li>
                    <li><strong>Regular Security Audits:</strong> Conducting regular reviews of our security practices.</li>
                </ul>
                <p>While we have taken reasonable steps to secure the personal information you provide to us, please be aware that despite our efforts, no security measures are perfect or impenetrable.</p>

                <h2>5. Your Data Protection Rights</h2>
                <p>Depending on your location, you may have the following rights regarding your personal information:</p>
                <ul>
                    <li><strong>The right to access</strong> – You have the right to request copies of your personal data.</li>
                    <li><strong>The right to rectification</strong> – You have the right to request that we correct any information you believe is inaccurate.</li>
                    <li><strong>The right to erasure</strong> – You have the right to request that we erase your personal data, under certain conditions.</li>
                    <li><strong>The right to restrict processing</strong> – You have the right to request that we restrict the processing of your personal data, under certain conditions.</li>
                </ul>
                <p>To exercise these rights, please contact us using the contact information below.</p>

                <h2>6. Changes to This Privacy Policy</h2>
                <p>We may update this Privacy Policy from time to time. We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Last Updated" date. You are advised to review this Privacy Policy periodically for any changes.</p>

                <h2>7. Contact Us</h2>
                <p>If you have any questions or concerns about this Privacy Policy, please contact us at:</p>
                <ul>
                    <li><strong>Email:</strong> <a href="mailto:medsync.calysta@gmail.com">medsync.calysta@gmail.com</a></li>
                    <li><strong>Phone:</strong> <a href="tel:+914523531245">+91 45235 31245</a></li>
                    <li><strong>Address:</strong> Calysta Health Institute, Kerala, India</li>
                </ul>

                <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Home</a>
            </div>
        </div>
    </main>
</body>
</html>
