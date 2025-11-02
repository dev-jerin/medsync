<?php

// 1. Include the configuration file to set up the database connection and session.
require_once 'config.php';

// 2. Check if a user is already logged in (This logic is preserved from your original file).
if (isset($_SESSION['user_id'])) {
    // Redirection based on user role.
    switch ($_SESSION['role']) {
        case 'admin':   header("Location: admin/dashboard");   break;
        case 'doctor':  header("Location: doctor/dashboard");  break;
        case 'staff':   header("Location: staff/dashboard");   break;
        case 'user':    header("Location: user/dashboard");    break;
        default:        header("Location: logout");            break;
    }
    exit();
}

// 3. Query for dynamic data for the "Impact" section.
// For Regular User
$user_sql = "SELECT COUNT(id) AS user_count FROM users WHERE role_id = 1 AND is_active = 1";
$user_result = $conn->query($user_sql);
$user_count = ($user_result && $user_result->num_rows > 0) ? $user_result->fetch_assoc()['user_count'] : 0;

// For doctor .
$doctor_sql = "SELECT COUNT(id) AS doctor_count FROM users WHERE role_id = 2 AND is_active = 1";
$doctor_result = $conn->query($doctor_sql);
$doctor_count = ($doctor_result && $doctor_result->num_rows > 0) ? $doctor_result->fetch_assoc()['doctor_count'] : 0;

// 4. Close the connection as it's no longer needed for rendering the rest of this page.
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedSync - Your Health, Synchronized</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="main/styles.css">

    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
</head>
<body>

    <header class="header" id="header">
        <nav class="container navbar">
            <a href="index" class="logo">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span>MedSync</span>
            </a>
            
            <ul class="nav-links">
                <li><a href="#home">Home</a></li>
                <li><a href="#services">Services</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="#faq">FAQ</a></li>
            </ul>

            <div class="nav-actions">
                <a href="login" class="btn btn-secondary">Login</a>
                <a href="register" class="btn btn-primary">Register</a>
            </div>

            <div class="hamburger">
                <i class="fas fa-bars"></i>
            </div>
        </nav>
    </header>

    <div class="mobile-nav">
        <div class="close-btn"><i class="fas fa-times"></i></div>
        <ul class="nav-links">
            <li><a href="#home">Home</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
            <li><a href="#faq">FAQ</a></li>
        </ul>
        <div class="nav-actions-mobile">
            <a href="login" class="btn btn-secondary">Login</a>
            <a href="register" class="btn btn-primary">Register</a>
        </div>
    </div>

    <main>
        <section id="home" class="hero">
            <div class="container hero-content">
                <div class="hero-text">
                    <h1 class="anim-fade-up">
                        Seamless Healthcare,
                        <span class="highlight">Perfectly in Sync.</span>
                    </h1>
                    <p class="anim-fade-up" style="--delay: 0.2s;">
                        Welcome to MedSync by Calysta Health Institute. Your journey to effortless health management starts here.
                    </p>
                    <div class="anim-fade-up" style="--delay: 0.4s;">
                        <a href="register" class="btn btn-primary">Get Started Now</a>
                    </div>
                </div>

                <div class="hero-image-container anim-fade-up" style="--delay: 0.3s;">
                    <div class="hero-image-bg"></div>
                    <img src="https://images.unsplash.com/photo-1576091160550-2173dba999ef?q=80&w=2070&auto=format&fit=crop" 
                         alt="Doctor using a tablet for patient records" 
                         class="hero-image"
                         onerror="this.onerror=null;this.src='https://placehold.co/600x400/e2e8f0/333?text=MedSync+Platform';">
                </div>
            </div>
        </section>

        <section id="services">
            <div class="container">
                <div class="section-title">
                    <h2 class="anim-fade-up">Our Core Features</h2>
                    <p class="anim-fade-up" style="--delay: 0.2s;">We provide a comprehensive suite of tools to make your healthcare experience smooth, efficient, and patient-centric.</p>
                </div>
                <div class="services-grid">
                    <div class="service-card anim-fade-up" style="--delay: 0s;">
                        <span class="icon"><i class="fas fa-calendar-check"></i></span>
                        <h3>Appointment Scheduling</h3>
                        <p>Easily book and manage your appointments with our specialists anytime, anywhere.</p>
                    </div>
                    <div class="service-card anim-fade-up" style="--delay: 0.2s;">
                        <span class="icon"><i class="fas fa-ticket-alt"></i></span>
                        <h3>Live Token Tracking</h3>
                        <p>No more long waits. Track your queue status in real-time from the comfort of your home.</p>
                    </div>
                    <div class="service-card anim-fade-up" style="--delay: 0.4s;">
                        <span class="icon"><i class="fas fa-file-prescription"></i></span>
                        <h3>Digital Prescriptions</h3>
                        <p>Access your prescriptions online securely and get notified when they are ready.</p>
                    </div>
                    <div class="service-card anim-fade-up" style="--delay: 0.6s;">
                        <span class="icon"><i class="fas fa-procedures"></i></span>
                        <h3>Automated Discharge</h3>
                        <p>A streamlined and transparent discharge process, keeping you informed at every step.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <section id="about">
            <div class="container">
                <div class="about-content">
                    <div class="about-image anim-fade-up">
                        <img src="images/hospital-img.png" 
                             alt="The dedicated medical team at Calysta Health Institute"
                             onerror="this.onerror=null;this.src='https://placehold.co/600x400/e2e8f0/333?text=Our+Team';">
                    </div>
                    <div class="about-text anim-fade-up" style="--delay: 0.2s;">
                        <h3>Our Mission at Calysta Health Institute</h3>
                        <p>To provide compassionate, accessible, and high-quality healthcare by leveraging cutting-edge technology. MedSync empowers patients by giving them control over their healthcare journey, from scheduling to recovery.</p>
                        <div class="about-values">
                            <ul>
                                <li><span class="icon"><i class="fas fa-check-circle"></i></span><div><strong>Patient-First:</strong> Your well-being and convenience are at the core of every decision we make.</div></li>
                                <li><span class="icon"><i class="fas fa-check-circle"></i></span><div><strong>Innovation:</strong> We continuously innovate to improve healthcare delivery and efficiency.</div></li>
                                <li><span class="icon"><i class="fas fa-check-circle"></i></span><div><strong>Integrity:</strong> We adhere to the highest standards of ethics and professionalism.</div></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="impact" class="impact-section">
    <div class="container">
        <div class="section-title">
            <h2 class="anim-fade-up">Our Impact in Numbers</h2>
            <p class="anim-fade-up" style="--delay: 0.2s;">We are proud of the positive change we bring to our community's health and well-being.</p>
        </div>
        <div class="impact-grid">
            <div class="impact-card anim-fade-up" style="--delay: 0s;">
                <h3><?php echo $user_count; ?></h3>
                <p>Happy Users</p>
            </div>
            <div class="impact-card anim-fade-up" style="--delay: 0.2s;">
                <h3><?php echo $doctor_count; ?></h3>
                <p>Specialist Doctors</p>
            </div>
            <div class="impact-card anim-fade-up" style="--delay: 0.4s;">
                <h3>98%</h3>
                <p>Patient Satisfaction</p>
            </div>
            <div class="impact-card anim-fade-up" style="--delay: 0.6s;">
                <h3>40%</h3>
                <p>Reduction in Wait Times</p>
            </div>
        </div>
    </div>
</section>

        <section id="testimonials" class="testimonials-section">
            <div class="container">
                <div class="section-title">
                    <h2 class="anim-fade-up">What Our Patients Say</h2>
                    <p class="anim-fade-up" style="--delay: 0.2s;">Their words are a testament to the care and dedication we provide every day.</p>
                </div>
                <div class="testimonial-grid">
                    <div class="testimonial-card anim-fade-up" style="--delay: 0s;">
                        <div class="stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>"The MedSync platform is a game-changer. Tracking my appointment token live from my phone saved me hours of waiting. The entire process was so smooth!"</p>
                        <div class="author">
                            <strong>Priya S.</strong>
                            <span>Thiruvananthapuram</span>
                        </div>
                    </div>
                    <div class="testimonial-card anim-fade-up" style="--delay: 0.2s;">
                        <div class="stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>"Booking an appointment for my father was incredibly easy. The doctors are professional and the staff is very helpful. Highly recommend Calysta Health Institute."</p>
                        <div class="author">
                            <strong>Anil Kumar</strong>
                            <span>Kochi</span>
                        </div>
                    </div>
                    <div class="testimonial-card anim-fade-up" style="--delay: 0.4s;">
                        <div class="stars">
                            <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                        </div>
                        <p>"I received my lab results and discharge summary directly on my phone. The level of transparency and efficiency is something I've never seen before in a hospital."</p>
                        <div class="author">
                            <strong>Arun</strong>
                            <span>Kozhikode</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact">
            <div class="container">
                <div class="section-title anim-fade-up">
                    <h2>Get in Touch</h2>
                    <p>Have questions or need assistance? Our team is here to help you.</p>
                </div>
                <div class="contact-layout anim-fade-up" style="--delay: 0.2s;">
                    <div class="contact-info">
                        <h3>Contact Information</h3>
                        <p>Reach out to us through any of the following methods. Our team is available to assist you during business hours.</p>
                        <ul>
                            <li><i class="fas fa-map-marker-alt"></i><div><strong>Address:</strong><br>Calysta Health Institute, Kerala, India</div></li>
                            <li><i class="fas fa-phone"></i><div><strong>Phone:</strong><br><a href="tel:+914523531245">+91 45235 31245</a></div></li>
                            <li><i class="fas fa-envelope"></i><div><strong>Email:</strong><br><a href="mailto:medsync.calysta@gmail.com">medsync.calysta@gmail.com</a></div></li>
                            <li><i class="fas fa-clock"></i><div><strong>Hours:</strong><br>Mon - Sat: 9:00 AM - 7:00 PM</div></li>
                        </ul>
                    </div>
                    <div class="contact-form-wrapper">
                         <h4>Request a Call Back</h4>
                         <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Leave your details below, and a patient coordinator will call you shortly.</p>
                        <?php
                        // Display callback request status messages
                        if (isset($_SESSION['callback_message'])) {
                            $message = $_SESSION['callback_message'];
                            $message_type = $message['type'] === 'success' ? 'var(--success-color)' : 'var(--error-color)';
                            echo '<div class="callback-message" style="background-color:' . $message_type . ';">' . htmlspecialchars($message['text']) . '</div>';
                            unset($_SESSION['callback_message']);
                        }
                        ?>
                        <form action="callback_request" method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group">
                                <input type="text" id="name" name="name" class="form-control" placeholder=" " required>
                                <label for="name" class="form-label">Full Name</label>
                            </div>
                            <div class="form-group">
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder=" " required maxlength="13">
                                <label for="phone" class="form-label">Phone Number</label>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width:100%; padding: 1rem;">Request Call</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <section id="faq">
            <div class="container">
                <div class="section-title anim-fade-up">
                    <h2>Frequently Asked Questions</h2>
                    <p>Find answers to common questions about our services and platform.</p>
                </div>
                <div class="faq-container anim-fade-up" style="--delay: 0.2s;">
                    <div class="faq-item">
                        <button class="faq-question">How do I book an appointment?<span class="icon"><i class="fas fa-chevron-down"></i></span></button>
                        <div class="faq-answer"><p>You can book an appointment by logging into your MedSync account, navigating to the 'Book Appointment' tab, searching for a doctor by specialty, and selecting an available time slot that suits you.</p></div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">Can I track my queue position online?<span class="icon"><i class="fas fa-chevron-down"></i></span></button>
                        <div class="faq-answer"><p>Yes! Our 'Live Token Tracking' feature allows you to see your token number and the current token being served in real-time. You can monitor your position from home or on the go, reducing your waiting time at the institute.</p></div>
                    </div>
                    <div class="faq-item">
                        <button class="faq-question">How do I access my medical records?<span class="icon"><i class="fas fa-chevron-down"></i></span></button>
                        <div class="faq-answer"><p>Once you log in, you will find dedicated sections for 'Prescriptions' and 'Lab Results' on your dashboard. All your records are stored securely and can be accessed or downloaded anytime.</p></div>
                    </div>
                     <div class="faq-item">
                        <button class="faq-question">Is my personal information secure?<span class="icon"><i class="fas fa-chevron-down"></i></span></button>
                        <div class="faq-answer"><p>Absolutely. We prioritize your privacy and data security. Our platform uses advanced encryption, CSRF protection, and secure session management to protect your personal and medical information at all times.</p></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

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
                        <li><a href="register">Register</a></li>
                        <li><a href="privacy_policy">Privacy Policy</a></li>
                        <li><a href="termsandconditions">Terms & Conditions</a></li>
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
                        <a href="https://facebook.com/" aria-label="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"></path></svg>
                        </a>
                        <a href="https://x.com/" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>
                        </a>
                        <a href="https://instagram.com" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-instagram" viewBox="0 0 16 16">
                                <path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/>
                                </svg>
                        </a>
                        <a href="https://linkedin.com/" aria-label="LinkedIn">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"></path></svg>
                        </a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>© <?php echo date("Y"); ?> Calysta Health Institute. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Chatbot Integration -->
    <script> window.chtlConfig = { chatbotId: "<?php echo htmlspecialchars($_ENV['CHATBOT_ID'] ?? '4776578598'); ?>" } </script>
    <script async data-id="<?php echo htmlspecialchars($_ENV['CHATBOT_ID'] ?? '4776578598'); ?>" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>

    <script src="main/script.js"></script>
</body>
</html>