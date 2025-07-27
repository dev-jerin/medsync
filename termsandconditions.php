<?php
// Start the session to manage user login state

require_once 'config.php';

// If a user is already logged in, redirect them to their dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: " . $_SESSION['role'] . "/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms and Conditions - MedSync</title>
    
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- External Stylesheet -->
    <link rel="stylesheet" href="main/styles.css">
</head>
<body>

    <!-- Header -->
    <header class="header" id="header">
        <nav class="container navbar">
            <a href="index.php" class="logo">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span>MedSync</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="index.php#home">Home</a></li>
                <li><a href="index.php#services">Services</a></li>
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#contact">Contact</a></li>
                <li><a href="index.php#faq">FAQ</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login.php" class="btn btn-secondary">Login</a>
                <a href="register.php" class="btn btn-primary">Register</a>
            </div>

            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
    </header>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
        <div class="close-btn"><i class="fas fa-times"></i></div>
        <ul class="nav-links">
            <li><a href="index.php#home">Home</a></li>
            <li><a href="index.php#services">Services</a></li>
            <li><a href="index.php#about">About</a></li>
            <li><a href="index.php#contact">Contact</a></li>
            <li><a href="index.php#faq">FAQ</a></li>
        </ul>
        <div class="nav-actions-mobile">
            <a href="login.php" class="btn btn-secondary">Login</a>
            <a href="register.php" class="btn btn-primary">Register</a>
        </div>
    </div>

    <!-- Main Content -->
    <main class="policy-page">
        <div class="container">
            <div class="policy-content">
                <h1>Terms and Conditions</h1>
                <p class="last-updated">Last Updated: July 26, 2025</p>

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
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-col">
                    <h4>About MedSync</h4>
                    <p>MedSync, by Calysta Health Institute, is dedicated to revolutionizing patient care through technology, making healthcare more accessible and manageable.</p>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="privacy_policy.php">Privacy Policy</a></li>
                        <li><a href="termsandconditions.php">Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <ul>
                        <li><a href="https://maps.app.goo.gl/fw72bi434jTHJGgC8" target="_blank"><i class="fas fa-map-marker-alt"></i> Kerala, India</a></li>
                        <li><a href="tel:+914523531245"><i class="fas fa-phone"></i> +91 45235 31245</a></li>
                        <li><a href="mailto:medsync.calysta@gmail.com"><i class="fas fa-envelope"></i> medsync.calysta@gmail.com</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Follow Us</h4>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg></a>
                        <a href="#" aria-label="Twitter"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                        <a href="#" aria-label="Instagram"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.011 3.584-.069 4.85c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.584-.012-4.85-.07c-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.069-1.645-.069-4.85s.011-3.584.069-4.85c.149-3.225 1.664-4.771 4.919-4.919C8.416 2.175 8.796 2.163 12 2.163zm0 1.441c-3.117 0-3.482.01-4.694.063-2.433.11-3.58 1.1-3.69 3.69-.052 1.21-.062 1.556-.062 4.634s.01 3.424.062 4.634c.11 2.59 1.257 3.58 3.69 3.69 1.212.053 1.577.063 4.694.063s3.482-.01 4.694-.063c2.433-.11 3.58-1.1 3.69-3.69.052-1.21.062-1.556.062-4.634s-.01-3.424-.062-4.634c-.11-2.59-1.257-3.58-3.69-3.69C15.482 3.613 15.117 3.604 12 3.604zM12 8.25c-2.071 0-3.75 1.679-3.75 3.75s1.679 3.75 3.75 3.75 3.75-1.679 3.75-3.75S14.071 8.25 12 8.25zm0 6c-1.24 0-2.25-1.01-2.25-2.25S10.76 9.75 12 9.75s2.25 1.01 2.25 2.25S13.24 14.25 12 14.25zm6.36-7.18c-.414 0-.75.336-.75.75s.336.75.75.75.75-.336.75-.75-.336-.75-.75-.75z"/></svg></a>
                        <a href="#" aria-label="LinkedIn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date("Y"); ?> Calysta Health Institute. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- External Script -->
    <script src="main/script.js"></script>
</body>
</html>