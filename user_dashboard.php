<?php
/**
 * MedSync User Dashboard (user_dashboard.php)
 *
 * This script serves as the main portal for registered users (patients).
 * - It enforces session security, ensuring only authenticated users with the 'user' role can access it.
 * - It provides a central hub for users to manage their appointments, view records, and interact with the system.
 */

require_once 'config.php'; // Includes session_start() and database connection

// --- Session Security ---
// 1. Check if a user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('user').
// This prevents users with other roles (like admin or doctor) from accessing this page.
if ($_SESSION['role'] !== 'user') {
    // If the role is incorrect, it's best to destroy the session as a security measure
    // before redirecting them to the login page with an error.
    session_destroy();
    header("Location: login.php?error=unauthorized");
    exit();
}

// 3. Implement a session timeout to automatically log out inactive users.
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();     // Unset all session variables
    session_destroy();   // Destroy the session
    header("Location: login.php?session_expired=true");
    exit();
}
// If the session is active, update the 'loggedin_time' to reset the timeout timer.
$_SESSION['loggedin_time'] = time();

// Fetch user details from the session to be displayed on the page.
// Using htmlspecialchars to prevent XSS vulnerabilities when echoing these values.
$username = htmlspecialchars($_SESSION['username']);
$display_user_id = htmlspecialchars($_SESSION['display_user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - MedSync</title>
    
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Favicon Links -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        /* --- Base Styles & Color Palette --- */
        :root {
            --primary-color: #007BFF;
            --secondary-color: #17a2b8;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --text-dark: #343a40;
            --text-light: #f8f9fa;
            --background-light: #ffffff;
            --background-grey: #f1f5f9;
            --border-radius: 12px;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
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
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 260px;
            background-color: var(--background-light);
            box-shadow: 0 0 25px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            transition: left 0.3s ease-in-out;
            z-index: 1000;
            position: fixed; /* Fixed position for consistent visibility */
            height: 100%;
            left: 0;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
            padding: 0 0.5rem;
        }

        .sidebar-header .logo-img {
            height: 40px;
            margin-right: 10px;
        }
        
        .sidebar-header .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .sidebar-nav {
            flex-grow: 1;
            overflow-y: auto; /* Allow scrolling if nav items exceed height */
        }

        .sidebar-nav ul {
            list-style: none;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.9rem 1rem;
            color: #5a6a7c;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: background-color 0.3s, color 0.3s, transform 0.2s;
            font-weight: 500;
        }

        .sidebar-nav a i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .sidebar-nav a.active, .sidebar-nav a:hover {
            background-color: var(--primary-color);
            color: var(--text-light);
            transform: translateX(5px);
        }
        
        .sidebar-footer {
            margin-top: auto; /* Pushes footer to the bottom */
            padding-top: 1rem;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.9rem 1rem;
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .logout-btn:hover {
            background-color: var(--danger-color);
            color: var(--text-light);
        }

        /* --- Main Content --- */
        .main-content {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            margin-left: 260px; /* Account for the fixed sidebar width */
            transition: margin-left 0.3s ease-in-out;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .main-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
        }

        .user-profile-widget {
            display: flex;
            align-items: center;
            gap: 1rem;
            background-color: var(--background-light);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }
        
        .user-profile-widget i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .content-panel {
            background-color: var(--background-light);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }
        
        .welcome-message h2 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        .welcome-message p {
            color: #6c757d;
            max-width: 800px;
        }

        /* --- Dashboard Widgets --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .grid-card {
            background-color: var(--background-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }
        
        .grid-card h3 {
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 0.75rem;
        }

        /* Stat Cards */
        .stat-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .stat-card {
            padding: 1.5rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-light);
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-card.warning { background: linear-gradient(135deg, #ff9a00, var(--warning-color)); }
        .stat-card.success { background: linear-gradient(135deg, #34d399, var(--success-color)); }
        
        .stat-card .icon { font-size: 2.2rem; opacity: 0.8; }
        .stat-card .info .value { font-size: 1.75rem; font-weight: 600; }
        .stat-card .info .label { font-size: 0.9rem; opacity: 0.9; }

        /* Quick Actions */
        .quick-actions .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        .quick-actions .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem 1rem;
            border-radius: var(--border-radius);
            background-color: var(--background-grey);
            color: var(--text-dark);
            text-decoration: none;
            font-weight: 500;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s, background-color 0.2s;
        }
        .quick-actions .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            background-color: var(--primary-color);
            color: var(--text-light);
        }
        .quick-actions .action-btn i {
            font-size: 2rem;
            margin-bottom: 0.75rem;
        }

        /* Upcoming Appointments & Activity Feed */
        .item-list ul { list-style: none; }
        .item-list li {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.85rem 0.25rem;
            border-bottom: 1px solid #f0f0f0;
        }
        .item-list li:last-child { border-bottom: none; }
        .item-list .item-icon {
            font-size: 1.2rem;
            color: var(--text-light);
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            flex-shrink: 0;
        }
        .item-list .item-icon.bg-primary { background-color: var(--primary-color); }
        .item-list .item-icon.bg-success { background-color: var(--success-color); }
        .item-list .item-icon.bg-secondary { background-color: var(--secondary-color); }

        .item-list .item-details .timestamp {
            font-size: 0.8rem;
            color: #999;
            margin-top: 2px;
        }

        /* --- Hamburger Menu & Overlay for Mobile --- */
        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
            z-index: 1001;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .sidebar {
                left: -260px; /* Hide sidebar off-screen */
            }

            .sidebar.active {
                left: 0; /* Show sidebar */
                box-shadow: 0 0 40px rgba(0,0,0,0.1);
            }

            .main-content {
                margin-left: 0; /* Full width on mobile */
            }
            
            .hamburger-btn {
                display: block; /* Show hamburger on mobile */
            }

            .main-header {
                justify-content: flex-start;
                gap: 1rem;
            }
            .user-profile-widget {
                margin-left: auto; /* Push profile widget to the right */
            }

            .overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                    <li><a href="#"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="#"><i class="fas fa-file-prescription"></i> Prescriptions</a></li>
                    <li><a href="#"><i class="fas fa-vials"></i> Lab Results</a></li>
                    <li><a href="#"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                    <li><a href="#"><i class="fas fa-history"></i> Medical History</a></li>
                    <li><a href="#"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="#"><i class="fas fa-comment-dots"></i> Feedback</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                 <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Dashboard</h1>
                <div class="user-profile-widget">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <strong><?php echo $username; ?></strong><br>
                        <span>ID: <?php echo $display_user_id; ?></span>
                    </div>
                </div>
            </header>

            <div class="content-panel">
                <div class="welcome-message">
                    <h2>Welcome back, <?php echo $username; ?>!</h2>
                    <p>This is your personal health dashboard. From here, you can manage your appointments, view your medical records, and communicate with your healthcare providers.</p>
                </div>
                
                <!-- Stat Cards -->
                <div class="stat-cards-container">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="info">
                            <div class="value">3</div>
                            <div class="label">Upcoming Appointments</div>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon"><i class="fas fa-envelope"></i></div>
                        <div class="info">
                            <div class="value">2</div>
                            <div class="label">Unread Messages</div>
                        </div>
                    </div>
                    <div class="stat-card success">
                        <div class="icon"><i class="fas fa-file-alt"></i></div>
                        <div class="info">
                            <div class="value">1</div>
                            <div class="label">New Lab Result</div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <div class="grid-card quick-actions">
                        <h3>Quick Actions</h3>
                        <div class="actions-grid">
                            <a href="#" class="action-btn"><i class="fas fa-calendar-plus"></i> Book Appointment</a>
                            <a href="#" class="action-btn"><i class="fas fa-pills"></i> Refill Prescription</a>
                            <a href="#" class="action-btn"><i class="fas fa-file-invoice"></i> View Bills</a>
                            <a href="#" class="action-btn"><i class="fas fa-user-md"></i> Contact Doctor</a>
                        </div>
                    </div>

                    <div class="grid-card item-list">
                        <h3>Upcoming Appointments</h3>
                        <ul>
                            <li>
                                <div class="item-icon bg-primary"><i class="fas fa-stethoscope"></i></div>
                                <div class="item-details">
                                    <strong>General Checkup with Dr. Smith</strong>
                                    <div class="timestamp">July 15, 2025 at 10:00 AM</div>
                                </div>
                            </li>
                            <li>
                                <div class="item-icon bg-secondary"><i class="fas fa-tooth"></i></div>
                                <div class="item-details">
                                    <strong>Dental Cleaning with Dr. Jones</strong>
                                    <div class="timestamp">July 22, 2025 at 2:30 PM</div>
                                </div>
                            </li>
                             <li>
                                <div class="item-icon bg-primary"><i class="fas fa-eye"></i></div>
                                <div class="item-details">
                                    <strong>Eye Exam with Dr. Ray</strong>
                                    <div class="timestamp">August 5, 2025 at 11:00 AM</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="grid-card item-list" style="grid-column: 1 / -1;">
                        <h3>Recent Activity</h3>
                        <ul>
                            <li>
                                <div class="item-icon bg-success"><i class="fas fa-vials"></i></div>
                                <div class="item-details">
                                    <strong>New lab result for Blood Test is available.</strong>
                                    <div class="timestamp">July 7, 2025</div>
                                </div>
                            </li>
                            <li>
                                <div class="item-icon bg-primary"><i class="fas fa-user-edit"></i></div>
                                <div class="item-details">
                                    <strong>Profile information was updated.</strong>
                                    <div class="timestamp">July 5, 2025</div>
                                </div>
                            </li>
                            <li>
                                <div class="item-icon bg-secondary"><i class="fas fa-file-prescription"></i></div>
                                <div class="item-details">
                                    <strong>Prescription for 'Amoxicillin' was filled.</strong>
                                    <div class="timestamp">July 2, 2025</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Overlay for mobile menu -->
    <div class="overlay" id="overlay"></div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const navLinks = document.querySelectorAll('.sidebar-nav a');

            /**
             * Toggles the mobile menu open/closed.
             */
            function toggleMenu() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }

            /**
             * Closes the mobile menu.
             */
            function closeMenu() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }

            // Event listener for the hamburger button
            hamburgerBtn.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent click from bubbling up to the document
                toggleMenu();
            });

            // Event listener for the overlay (closes menu when clicked)
            overlay.addEventListener('click', closeMenu);

            // Event listener for nav links (closes menu when a link is clicked)
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 992) {
                        closeMenu();
                    }
                });
            });

            // Close menu if clicking outside of it on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
                    if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                        closeMenu();
                    }
                }
            });
        });
    </script>
</body>
</html>
