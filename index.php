<?php
// Start the session to manage user login state
session_start();

// Check if a user is already logged in. If so, redirect them to their respective dashboard.
// This prevents a logged-in user from seeing the landing page.
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin_dashboard.php");
            break;
        case 'doctor':
            header("Location: doctor_dashboard.php");
            break;
        case 'staff':
            header("Location: staff_dashboard.php");
            break;
        case 'user':
            header("Location: user_dashboard.php");
            break;
        default:
            // If role is not set or unknown, logout to be safe
            header("Location: logout.php");
            break;
    }
    exit(); // Stop further script execution after redirection
}

// Generate a CSRF token if one doesn't exist in the session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedSync - Calysta Health Institute</title>
    
    <!-- Google Fonts: Poppins for a modern, clean look -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!--Favicon-->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- GSAP for Animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>

    <style>
        /* --- Base Styles & Variables --- */
        :root {
            --primary-color: #007BFF; /* A professional and calming blue */
            --primary-dark: #0056b3;
            --secondary-color: #17a2b8; /* A complementary teal */
            --text-dark: #343a40;
            --text-light: #f8f9fa;
            --background-light: #ffffff;
            --background-grey: #f1f5f9;
            --success-color: #28a745;
            --error-color: #dc3545;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 10px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            line-height: 1.7;
            overflow-x: hidden; /* Prevent horizontal scroll */
        }
        
        html {
            scroll-behavior: smooth;
        }

        /* --- Utility Classes --- */
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        section {
            padding: 6rem 0;
        }
        
        /* --- Header & Navigation --- */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            padding: 1rem 0;
            background-color: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .logo-img {
            height: 40px; /* Adjust as needed */
            width: auto;
            margin-right: 8px; /* Match original spacing */
        }

        .nav-links {
            list-style: none;
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            position: relative;
            transition: color 0.3s ease;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--primary-color);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        .nav-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            cursor: pointer;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: var(--text-light);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.2);
            opacity: 1; /* Ensure button is visible by default */
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
        }
        
        .btn-secondary {
            background-color: transparent;
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-secondary:hover {
            background-color: var(--primary-color);
            color: var(--text-light);
            transform: translateY(-3px);
        }

        /* --- Hamburger Menu (Mobile Only) --- */
        .hamburger {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1001; /* Above mobile menu background */
            color: var(--text-dark); /* Default color */
        }
        
        .mobile-nav {
            position: fixed;
            top: 0;
            right: 0;
            width: 70%;
            height: 100vh;
            background-color: var(--background-light);
            z-index: 1000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
        }
        
        .mobile-nav.active {
            transform: translateX(0);
            box-shadow: -10px 0 30px rgba(0,0,0,0.1);
        }
        
        .mobile-nav .nav-links {
            display: flex; 
            flex-direction: column;
            align-items: center;
            font-size: 1.5rem;
            gap: 2rem;
        }
        
        .mobile-nav .btn {
            width: 80%;
            text-align: center;
        }
        
        .mobile-nav .nav-actions-mobile {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 80%;
            margin-top: 1rem;
        }
        
        .mobile-nav .close-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            font-size: 2rem;
            cursor: pointer;
            color: var(--text-dark);
        }

        /* --- Hero Section --- */
        .hero {
            background: linear-gradient(rgba(241, 245, 249, 0.1), rgba(241, 245, 249, 0.1)), url('https://placehold.co/1920x1080/e0f7fa/e0f7fa?text=') no-repeat center center/cover;
            background-color: #e0f7fa; /* Fallback color */
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding-top: 80px; /* Offset for fixed header */
        }
        
        .hero-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            gap: 3rem;
        }
        
        .hero-text {
            flex-basis: 50%;
        }

        .hero-image-container {
            flex-basis: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .hero-image {
            max-width: 650px;
            height: auto;
            border-radius: 20px;
            transform: scale(0.9); /* Initial scale for zoom animation */
            opacity: 0; /* Initial opacity for fade animation */
        }

        .hero-text h1 {
            font-size: 3.5rem;
            font-weight: 700;
            line-height: 1.2;
            color: var(--text-dark);
            margin-bottom: 1rem;
        }
        
        .hero-text .highlight {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-text p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 500px;
        }
        
        /* --- About Us Section --- */
        .about-content {
            display: flex;
            align-items: center;
            gap: 4rem;
        }
        
        .about-text, .about-values {
            flex: 1;
        }
        
        .about-text h3 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .about-values ul {
            list-style: none;
        }
        
        .about-values li {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: flex-start;
        }
        
        .about-values .icon {
            color: var(--success-color);
            margin-right: 1rem;
            font-size: 1.5rem;
        }

        /* --- Contact Section --- */
        #contact {
            background-color: var(--background-grey);
        }

        .contact-layout {
            display: flex;
            gap: 3rem;
            align-items: flex-start;
            background-color: var(--background-light);
            padding: 3rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .contact-info, .contact-form-wrapper {
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
        }

        .contact-info h3 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        .contact-info p {
            margin-bottom: 2rem;
        }

        .contact-info ul {
            list-style: none;
            padding: 0;
        }
        .contact-info li {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .contact-info li i {
            font-size: 1.2rem;
            color: var(--primary-color);
            width: 30px;
        }

        .contact-form-wrapper h4 {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        /* --- Services Section --- */
        #services {
            background-color: var(--background-grey);
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .service-card {
            background-color: var(--background-light);
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            opacity: 0; /* Initially hidden for animation */
            transform: translateY(50px);
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-md);
        }

        .service-card .icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .service-card h3 {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .service-card p {
            color: #6c757d;
        }

        /* --- FAQ Section --- */
        .faq-section {
             background-color: var(--background-light);
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e0e0e0;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        .faq-item {
            border-bottom: 1px solid #e0e0e0;
        }
        .faq-item:last-child {
            border-bottom: none;
        }
        .faq-question {
            width: 100%;
            background: none;
            border: none;
            text-align: left;
            padding: 1.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-dark);
            transition: background-color 0.3s ease;
        }
        .faq-question:hover {
            background-color: #f8f9fa;
        }
        .faq-question::after {
            content: '\f078'; /* Font Awesome down arrow */
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            transition: transform 0.3s ease;
        }
        .faq-question.active::after {
            transform: rotate(-180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-out, padding 0.4s ease-out;
            background-color: #f8f9fa;
        }

        .faq-answer p {
            padding: 0 1.5rem 1.5rem 1.5rem;
            color: #6c757d;
        }
        
        /* --- Footer --- */
        .footer {
            background-color: var(--text-dark);
            color: var(--text-light);
            padding: 4rem 0 2rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .footer-col h4 {
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .footer-col h4::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -8px;
            width: 50px;
            height: 2px;
            background: var(--primary-color);
        }

        .footer-col p, .footer-col ul li {
            color: #adb5bd;
        }
        
        .footer-col ul {
            list-style: none;
        }
        
        .footer-col ul li {
            margin-bottom: 0.75rem;
        }
        
        .footer-col ul a {
            color: #adb5bd;
            text-decoration: none;
            transition: color 0.3s ease, padding-left 0.3s ease;
        }
        
        .footer-col ul a:hover {
            color: var(--text-light);
            padding-left: 5px;
        }
        
        .social-links a {
            display: inline-block;
            height: 40px;
            width: 40px;
            background-color: rgba(255,255,255,0.2);
            margin: 0 10px 10px 0;
            text-align: center;
            line-height: 40px;
            border-radius: 50%;
            color: var(--text-light);
            transition: all 0.5s ease;
        }
        
        .social-links a:hover {
            color: #24262b;
            background-color: var(--text-light);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid #495057;
        }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
            .hero-text {
                order: 1; /* Text comes first on mobile */
                margin-bottom: 2rem;
            }
            .hero-text p {
                margin-left: auto;
                margin-right: auto;
            }

            @media (max-width: 992px) {
    .hero-image {
        width: 550%; /* Or a smaller fixed width, e.g., 500px */
    }
}
            .hero-image-container {
                order: 2; /* Image comes after text on mobile */
                margin-bottom: 2rem;
            }
            .hero-text h1 {
                font-size: 3rem;
            }
            
            .header .navbar > .nav-links, .header .navbar > .nav-actions {
                display: none;
            }
            .hamburger {
                display: block;
            }

            .about-content {
                flex-direction: column;
                text-align: center;
            }

            .contact-layout {
                flex-direction: column;
                padding: 2rem;
            }
        }
        
        @media (max-width: 576px) {
            .hero-text h1 {
                font-size: 2.5rem;
            }
            .btn {
                width: 100%;
            }
            section {
                padding: 4rem 0;
            }
        }
    </style>
</head>
<body>

    <!-- Header -->
    <header class="header">
        <nav class="container navbar">
            <a href="index.php" class="logo">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">MedSync
            </a>
            
            <!-- Desktop Navigation -->
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>
            <div class="nav-actions">
                <a href="login.php" class="btn btn-secondary">Login</a>
                <a href="register.php" class="btn btn-primary">Register</a>
            </div>

            <!-- Hamburger Icon -->
            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
    </header>

    <!-- Mobile Navigation -->
    <div class="mobile-nav">
        <div class="close-btn">
            <i class="fas fa-times"></i>
        </div>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="#faq">FAQ</a></li>
        </ul>
        <div class="nav-actions-mobile">
            <a href="login.php" class="btn btn-secondary">Login</a>
            <a href="register.php" class="btn btn-primary">Register</a>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section id="home" class="hero">
            <div class="container hero-content">
                <div class="hero-text"><br>
                    <h1 class="main-headline">
                        Seamless Healthcare
                        <span class="highlight">Perfectly in Sync.</span>
                    </h1>
                    <p class="sub-headline">
                        Welcome to Calysta Health Institute. Your journey to better health management starts here.
                        Schedule appointments, track your queue, and manage records effortlessly.
                    </p><br>
                    <a href="#services" class="btn btn-primary">Explore Services</a>
                </div>

                <!-- Hero Image -->
                <div class="hero-image-container">
                    <div class="hero-image-container">
    <img src="images/health.png" alt="Modern Healthcare Illustration" class="hero-image">
</div>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section id="services">
            <div class="container">
                <div class="section-title">
                    <h2>Our Core Features</h2>
                    <p>We provide a comprehensive suite of tools to make your healthcare experience smooth and efficient.</p>
                </div>
                <div class="services-grid">
                    <div class="service-card">
                        <span class="icon"><i class="fas fa-calendar-check"></i></span>
                        <h3>Appointment Scheduling</h3>
                        <p>Easily book and manage your appointments with our specialists anytime, anywhere.</p>
                    </div>
                    <div class="service-card">
                        <span class="icon"><i class="fas fa-ticket-alt"></i></span>
                        <h3>Live Token Tracking</h3>
                        <p>No more long waits. Track your queue status in real-time from the comfort of your home.</p>
                    </div>
                    <div class="service-card">
                        <span class="icon"><i class="fas fa-file-prescription"></i></span>
                        <h3>Digital Prescriptions</h3>
                        <p>Access your prescriptions online securely and get notified when they are ready.</p>
                    </div>
                    <div class="service-card">
                        <span class="icon"><i class="fas fa-procedures"></i></span>
                        <h3>Automated Discharge</h3>
                        <p>A streamlined and transparent discharge process, keeping you informed at every step.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- About Us Section -->
        <section id="about">
            <div class="container">
                <div class="section-title">
                    <h2>About Calysta Health Institute</h2>
                </div>
                <div class="about-content">
                    <div class="about-text">
                        <h3>Our Mission</h3>
                        <p>At Calysta Health Institute, our mission is to provide compassionate, accessible, and high-quality healthcare to our community. We believe in leveraging cutting-edge technology to create a seamless and patient-centric experience. Through our MedSync platform, we aim to empower patients by giving them control over their healthcare journey, from scheduling to recovery.</p>
                    </div>
                    <div class="about-values">
                        <h3>Our Core Values</h3>
                        <ul>
                            <li><span class="icon"><i class="fas fa-check-circle"></i></span><div><strong>Patient-First:</strong> Every decision we make is centered around the well-being and convenience of our patients.</div></li>
                            <li><span class="icon"><i class="fas fa-check-circle"></i></span><div><strong>Innovation:</strong> We continuously innovate to improve healthcare delivery and operational efficiency.</div></li>
                            <li><span class="icon"><i class="fas fa-check-circle"></i></span><div><strong>Integrity:</strong> We adhere to the highest standards of ethics and professionalism in all our interactions.</div></li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Contact Section -->
        <section id="contact">
            <div class="container">
                <div class="section-title">
                    <h2>Get in Touch</h2>
                    <p>Have questions or need assistance? We're here to help.</p>
                </div>
                <div class="contact-layout">
                    <div class="contact-info">
                        <h3>Contact Information</h3>
                        <p>Feel free to reach out to us through any of the following methods. Our team is available to assist you during business hours.</p>
                        <ul>
                            <li><i class="fas fa-map-marker-alt"></i><div><strong>Address:</strong><br>Calysta Health Institute, Kerala, India</div></li>
                            <li><i class="fas fa-phone"></i><div><strong>Phone:</strong><br><a href="tel:+914523531245">+91 45235 31245</a></div></li>
                            <li><i class="fas fa-envelope"></i><div><strong>Email:</strong><br><a href="mailto:medsync.calysta@gmail.com">medsync.calysta@gmail.com</a></div></li>
                            <li><i class="fas fa-clock"></i><div><strong>Hours:</strong><br>Mon - Sat: 9:00 AM - 7:00 PM</div></li>
                        </ul>
                    </div>
                    <div class="contact-form-wrapper">
                         <h4>Request a Call Back</h4>
                         <p>Leave your details below, and one of our patient coordinators will call you back shortly.</p>
    <?php
    // Display callback request status messages
    if (isset($_SESSION['callback_message'])) {
        $message = $_SESSION['callback_message'];
        $message_type = $message['type'] === 'success' ? 'var(--success-color)' : 'var(--error-color)';
        echo '<div style="padding: 1rem; margin-bottom: 1rem; border-radius: 8px; color: #fff; background-color:' . $message_type . ';">' . htmlspecialchars($message['text']) . '</div>';
        // Unset the session message so it doesn't show again on refresh
        unset($_SESSION['callback_message']);
    }
    ?>
                        <form action="callback_request.php" method="POST" style="margin-top: 1.5rem;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" required>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%">Request Call</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="faq-section">
            <div class="container">
                <div class="section-title">
                    <h2>Frequently Asked Questions</h2>
                    <p>Find answers to common questions about our services and platform.</p>
                </div>
                <div class="faq-container">
                    <div class="faq-item">
                        <button class="faq-question">How do I book an appointment?</button>
                        <div class="faq-answer">
                            <p>You can book an appointment by logging into your MedSync account, navigating to the 'Book Appointment' tab, searching for a doctor by specialty, and selecting an available time slot that suits you. The system will guide you through the process.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">Can I track my queue position online?</button>
                        <div class="faq-answer">
                            <p>Yes! Our 'Live Token Tracking' feature allows you to see your token number and the current token being served in real-time. You can monitor your position from home or on the go, reducing your waiting time at the institute.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">How do I access my medical records and prescriptions?</button>
                        <div class="faq-answer">
                            <p>Once you log in, you will find dedicated sections for 'Prescriptions' and 'Lab Results' on your dashboard. All your records are stored securely and can be accessed or downloaded anytime.</p>
                        </div>
                    </div>
                     <div class="faq-item">
                        <button class="faq-question">Is my personal information secure on MedSync?</button>
                        <div class="faq-answer">
                            <p>Absolutely. We prioritize your privacy and data security. Our platform uses advanced encryption, CSRF protection, and secure session management to protect your personal and medical information at all times, in compliance with industry standards.</p>
                        </div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">What if I forget my password?</button>
                        <div class="faq-answer">
                            <p>You can easily reset your password by clicking the "Forgot Password?" link on the login page. An OTP (One-Time Password) will be sent to your registered email address to help you set a new password securely.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

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
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="register.php">Register</a></li>
                        <li><a href="privacy_policy.php">Privacy Policy</a></li>
                        <li><a href="termsandconditions.php">Terms and Conditions</a></li>
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
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> Calysta Health Institute. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // --- Mobile Navigation Logic ---
            const hamburger = document.querySelector('.hamburger');
            const mobileNav = document.querySelector('.mobile-nav');
            const closeBtn = document.querySelector('.mobile-nav .close-btn');
            const mobileNavLinks = document.querySelectorAll('.mobile-nav a'); // Select all links to close nav

            if (hamburger) {
                hamburger.addEventListener('click', () => {
                    mobileNav.classList.add('active');
                });
            }
            
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    mobileNav.classList.remove('active');
                });
            }
            
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', () => {
                    mobileNav.classList.remove('active');
                });
            });

            // --- FAQ Accordion Logic ---
            const faqQuestions = document.querySelectorAll('.faq-question');
            faqQuestions.forEach(question => {
                question.addEventListener('click', () => {
                    const answer = question.nextElementSibling;
                    const isActive = question.classList.contains('active');

                    document.querySelectorAll('.faq-question.active').forEach(activeQuestion => {
                        if(activeQuestion !== question) {
                            activeQuestion.classList.remove('active');
                            activeQuestion.nextElementSibling.style.maxHeight = null;
                            activeQuestion.nextElementSibling.style.padding = '0 1.5rem';
                        }
                    });

                    if (isActive) {
                        question.classList.remove('active');
                        answer.style.maxHeight = null;
                        answer.style.padding = '0 1.5rem';
                    } else {
                        question.classList.add('active');
                        answer.style.maxHeight = answer.scrollHeight + "px";
                        answer.style.padding = '0 1.5rem 1.5rem 1.5rem';
                    }
                });
            });

            // --- GSAP Animations ---
            gsap.registerPlugin(ScrollTrigger);
            
            // Animate hero text on page load
            gsap.from(".main-headline", { duration: 1, y: 50, opacity: 0, ease: "power3.out", delay: 0.2 });
            gsap.from(".sub-headline", { duration: 1, y: 50, opacity: 0, ease: "power3.out", delay: 0.4 });
            gsap.from(".hero-text .btn-primary", { 
                duration: 1, 
                y: 50, 
                opacity: 0, 
                ease: "power3.out", 
                delay: 0.6,
                onComplete: function() {
                    // Ensure the button is visible after animation
                    document.querySelector(".hero-text .btn-primary").style.opacity = "1";
                }
            });
            
            // Animate hero image with subtle zoom and fade
            gsap.to(".hero-image", {
                scale: 1,
                opacity: 1,
                duration: 1.5,
                ease: "power2.out",
                delay: 0.8
            });
            
            // Animate service cards on scroll
            const serviceCards = document.querySelectorAll('.service-card');
            serviceCards.forEach(card => {
                gsap.to(card, {
                    scrollTrigger: {
                        trigger: card,
                        start: "top 85%",
                        toggleActions: "play none none none"
                    },
                    opacity: 1,
                    y: 0,
                    duration: 0.8,
                    ease: "power3.out"
                });
            });

            // Animate other sections on scroll
            const sectionsToAnimate = ['#about', '#contact', '#faq'];
            sectionsToAnimate.forEach(selector => {
                gsap.from(selector + " .container", {
                    scrollTrigger: {
                        trigger: selector,
                        start: "top 80%",
                        toggleActions: "play none none none"
                    },
                    opacity: 0,
                    y: 50,
                    duration: 1,
                    ease: 'power3.out'
                });
            });
        });
    </script>
</body>
</html>