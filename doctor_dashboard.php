<?php
/**
 * MedSync Doctor Dashboard (doctor_dashboard.php)
 *
 * This script serves as the main portal for registered doctors.
 * - It enforces session security, ensuring only authenticated users with the 'doctor' role can access it.
 * - It provides a central hub for doctors to manage schedules, view patient information, and perform clinical tasks.
 */

require_once 'config.php'; // Includes session_start() and database connection

// --- Session Security ---
// 1. Check if a user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('doctor').
if ($_SESSION['role'] !== 'doctor') {
    // If the role is incorrect, destroy the session as a security measure.
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

// Fetch user details from the session. Use htmlspecialchars to prevent XSS.
$username = htmlspecialchars($_SESSION['username']);
$display_user_id = htmlspecialchars($_SESSION['display_user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - MedSync</title>
    
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
        /* --- Doctor Dashboard Theme --- */
        :root {
            --primary-color: #1abc9c; /* Teal */
            --secondary-color: #27ae60; /* Green */
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --text-dark: #2c3e50; /* Dark Slate */
            --text-light: #ffffff;
            --background-light: #ffffff;
            --background-grey: #f8f9fa;
            --border-radius: 12px;
            --shadow-md: 0 6px 15px rgba(44, 62, 80, 0.08);
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
            position: fixed;
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
            overflow-y: auto;
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
            transition: all 0.3s ease;
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
            box-shadow: 0 4px 10px rgba(26, 188, 156, 0.3);
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding-top: 1rem;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.9rem 1rem;
            background-color: rgba(231, 76, 60, 0.1);
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
            margin-left: 260px;
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
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .welcome-message p {
            color: #6c757d;
        }

        /* --- Dashboard Widgets --- */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
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
        .stat-card.warning { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card.danger { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .stat-card .icon { font-size: 2.2rem; opacity: 0.8; }
        .stat-card .info .value { font-size: 1.75rem; font-weight: 600; }
        .stat-card .info .label { font-size: 0.9rem; opacity: 0.9; }

        /* Patient Queue Table */
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        .patient-table th, .patient-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        .patient-table th {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .patient-table .status {
            padding: 0.25rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #fff;
        }
        .status.waiting { background-color: var(--warning-color); }
        .status.in-consultation { background-color: var(--secondary-color); }
        .status.completed { background-color: #95a5a6; }

        .patient-table .action-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.2s;
        }
        .patient-table .action-btn:hover { color: var(--text-dark); }

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
                left: -260px;
            }
            .sidebar.active {
                left: 0;
                box-shadow: 0 0 40px rgba(0,0,0,0.1);
            }
            .main-content {
                margin-left: 0;
            }
            .hamburger-btn {
                display: block;
            }
            .main-header {
                justify-content: flex-start;
                gap: 1rem;
            }
            .user-profile-widget {
                margin-left: auto;
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
                    <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#"><i class="fas fa-calendar-day"></i> My Schedule</a></li>
                    <li><a href="#"><i class="fas fa-users"></i> Patients</a></li>
                    <li><a href="#"><i class="fas fa-file-prescription"></i> Prescriptions</a></li>
                    <li><a href="#"><i class="fas fa-vials"></i> Lab Requests</a></li>
                    <li><a href="#"><i class="fas fa-envelope"></i> Messages</a></li>
                    <li><a href="#"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
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
                <h1>Doctor Dashboard</h1>
                <div class="user-profile-widget">
                    <i class="fas fa-user-md"></i>
                    <div>
                        <strong>Dr. <?php echo $username; ?></strong><br>
                        <span>ID: <?php echo $display_user_id; ?></span>
                    </div>
                </div>
            </header>

            <div class="content-panel">
                <div class="welcome-message">
                    <h2>Welcome, Dr. <?php echo $username; ?>!</h2>
                    <p>Here’s a summary of your activities for today. Use the sidebar to navigate to different sections.</p>
                </div>

                <!-- Stat Cards -->
                <div class="stat-cards-container">
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="info">
                            <div class="value">12</div>
                            <div class="label">Today's Appointments</div>
                        </div>
                    </div>
                    <div class="stat-card warning">
                        <div class="icon"><i class="fas fa-flask"></i></div>
                        <div class="info">
                            <div class="value">5</div>
                            <div class="label">Pending Lab Results</div>
                        </div>
                    </div>
                    <div class="stat-card danger">
                        <div class="icon"><i class="fas fa-bed"></i></div>
                        <div class="info">
                            <div class="value">3</div>
                            <div class="label">In-Patients</div>
                        </div>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="dashboard-grid">
                    <div class="grid-card" style="grid-column: 1 / -1;">
                        <h3>Patient Queue for Today</h3>
                        <table class="patient-table">
                            <thead>
                                <tr>
                                    <th>Token</th>
                                    <th>Patient Name</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>05</td>
                                    <td>John Doe</td>
                                    <td>10:30 AM</td>
                                    <td><span class="status in-consultation">In Consultation</span></td>
                                    <td><button class="action-btn" aria-label="View Patient"><i class="fas fa-eye"></i></button></td>
                                </tr>
                                <tr>
                                    <td>06</td>
                                    <td>Jane Smith</td>
                                    <td>10:45 AM</td>
                                    <td><span class="status waiting">Waiting</span></td>
                                    <td><button class="action-btn" aria-label="View Patient"><i class="fas fa-eye"></i></button></td>
                                </tr>
                                <tr>
                                    <td>07</td>
                                    <td>Peter Jones</td>
                                    <td>11:00 AM</td>
                                    <td><span class="status waiting">Waiting</span></td>
                                    <td><button class="action-btn" aria-label="View Patient"><i class="fas fa-eye"></i></button></td>
                                </tr>
                                <tr>
                                    <td>04</td>
                                    <td>Mary Williams</td>
                                    <td>10:15 AM</td>
                                    <td><span class="status completed">Completed</span></td>
                                    <td><button class="action-btn" aria-label="View Patient"><i class="fas fa-eye"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div class="overlay" id="overlay"></div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const navLinks = document.querySelectorAll('.sidebar-nav a');

            function toggleMenu() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }

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

            // Event listener for nav links (closes menu when a link is clicked on mobile)
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
