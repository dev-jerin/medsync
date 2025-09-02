<?php
// Start the session to manage user login state
session_start();

// Check if a user is already logged in. If so, redirect them to their respective dashboard.
// This prevents a logged-in user from seeing the landing page.
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard");
            break;
        case 'doctor':
            header("Location: doctor/dashboard");
            break;
        case 'staff':
            header("Location: staff/dashboard");
            break;
        case 'user':
            header("Location: user/dashboard");
            break;
        default:
            // If role is not set or unknown, logout to be safe
            header("Location: logout");
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
                        <img src="https://images.unsplash.com/photo-1538108149393-fbbd81895907?q=80&w=2128&auto=format&fit=crop" 
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
                        <h3 class="counter" data-target="20000" data-suffix="+">0</h3>
                        <p>Happy Patients</p>
                    </div>
                    <div class="impact-card anim-fade-up" style="--delay: 0.2s;">
                        <h3 class="counter" data-target="25" data-suffix="+">0</h3>
                        <p>Specialist Doctors</p>
                    </div>
                    <div class="impact-card anim-fade-up" style="--delay: 0.4s;">
                        <h3 class="counter" data-target="98" data-suffix="%">0</h3>
                        <p>Patient Satisfaction</p>
                    </div>
                    <div class="impact-card anim-fade-up" style="--delay: 0.6s;">
                        <h3 class="counter" data-target="40" data-suffix="%">0</h3>
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
                            <strong>Fatima R.</strong>
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
                                <input type="tel" id="phone" name="phone" class="form-control" placeholder=" " required>
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
                        <a href="#" aria-label="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"></path></svg>
                        </a>
                        <a href="#" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"></path></svg>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" role="img"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.85s-.011 3.584-.069 4.85c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07s-3.584-.012-4.85-.07c-3.252-.148-4.771-1.691-4.919-4.919-.058-1.265-.069-1.645-.069-4.85s.011-3.584.069-4.85c.149-3.225 1.664-4.771 4.919-4.919C8.416 2.175 8.796 2.163 12 2.163zm0 1.441c-3.117 0-3.482.01-4.694.063-2.433.11-3.58 1.1-3.69 3.69-.052 1.21-.062 1.556-.062 4.634s.01 3.424.062 4.634c.11 2.59 1.257 3.58 3.69 3.69 1.212.053 1.577.063 4.694.063s3.482-.01 4.694-.063c2.433-.11 3.58-1.1 3.69-3.69.052-1.21.062-1.556.062-4.634s-.01-3.424-.062-4.634c-.11-2.59-1.257-3.58-3.69-3.69C15.482 3.613 15.117 3.604 12 3.604zM12 8.25c-2.071 0-3.75 1.679-3.75 3.75s1.679 3.75 3.75 3.75 3.75-1.679 3.75-3.75S14.071 8.25 12 8.25zm0 6c-1.24 0-2.25-1.01-2.25-2.25S10.76 9.75 12 9.75s2.25 1.01 2.25 2.25S13.24 14.25 12 14.25zm6.36-7.18c-.414 0-.75.336-.75.75s.336.75.75.75.75-.336.75-.75-.336-.75-.75-.75z"></path></svg>
                        </a>
                        <a href="#" aria-label="LinkedIn">
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
    <script> window.chtlConfig = { chatbotId: "4776578598" } </script>
    <script async data-id="4776578598" id="chtl-script" type="text/javascript" src="https://chatling.ai/js/embed.js"></script>

    <script src="main/script.js"></script>
</body>
</html>