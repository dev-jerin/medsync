<?php
require_once 'config.php';

// --- Session Security ---
// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has the correct role ('admin')
if ($_SESSION['role'] !== 'admin') {
    // If the role is incorrect, destroy the session and redirect to login
    session_destroy();
    header("Location: login.php?error=unauthorized");
    exit();
}

// 3. Check for session timeout (e.g., 30 minutes)
$session_timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php?session_expired=true");
    exit();
}
// Update the session time
$_SESSION['loggedin_time'] = time();

// Fetch user details from session
$username = $_SESSION['username'];
$display_user_id = $_SESSION['display_user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MedSync</title>
    
    <!-- Google Fonts: Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js for Charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Favicon Links -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        /* --- Admin Theme Color Palette --- */
        :root {
            --primary-color: #2c3e50; /* Dark Slate Blue */
            --secondary-color: #3498db; /* Bright Blue for accents */
            --danger-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --info-color: #9b59b6; /* Amethyst */
            --text-dark: #34495e;
            --text-light: #ecf0f1;
            --background-light: #ffffff;
            --background-grey: #f5f7fa;
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
            width: 280px;
            background-color: var(--background-light);
            box-shadow: 0 0 25px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            transition: width 0.3s ease, left 0.3s ease-in-out;
            z-index: 1000;
            /* --- CHANGE: Making sidebar fixed --- */
            position: fixed;
            height: 100vh;
            top: 0;
            left: 0;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
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

        .sidebar-nav a, .nav-dropdown-toggle {
            display: flex;
            align-items: center;
            padding: 0.9rem 1rem;
            color: #5a6a7c;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: background-color 0.3s, color 0.3s;
            font-weight: 500;
            cursor: pointer;
        }

        .sidebar-nav a i, .nav-dropdown-toggle i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .sidebar-nav a.active, .sidebar-nav a:hover, .nav-dropdown-toggle:hover, .nav-dropdown-toggle.active {
            background-color: var(--primary-color);
            color: var(--text-light);
        }
        
        .nav-dropdown-toggle .arrow {
            margin-left: auto;
            transition: transform 0.3s;
        }

        .nav-dropdown-toggle.active .arrow {
            transform: rotate(90deg);
        }

        .nav-dropdown {
            list-style: none;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-in-out;
            padding-left: 1.5rem; /* Indent dropdown items */
        }
        
        .nav-dropdown a {
            font-size: 0.95rem;
            padding: 0.7rem 1rem 0.7rem 0.5rem;
            background-color: rgba(0,0,0,0.02);
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
            overflow-y: auto;
            /* --- CHANGE: Add margin to account for fixed sidebar --- */
            margin-left: 280px;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .main-header h1 {
            font-size: 1.8rem;
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
        }
        .welcome-message p {
            color: #6c757d;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .grid-card {
            background-color: var(--background-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
        }

        .grid-card h3 {
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        /* --- Admin Stat Cards --- */
        .stat-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .stat-card {
            background: var(--background-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            border-left: 5px solid var(--primary-color);
        }
        
        .stat-card .icon {
            font-size: 2.5rem;
            padding: 1rem;
            border-radius: 50%;
            color: var(--primary-color);
            background-color: var(--background-grey);
        }

        .stat-card.blue .icon { color: var(--secondary-color); }
        .stat-card.green .icon { color: var(--success-color); }
        .stat-card.orange .icon { color: var(--warning-color); }

        .stat-card.blue { border-left-color: var(--secondary-color); }
        .stat-card.green { border-left-color: var(--success-color); }
        .stat-card.orange { border-left-color: var(--warning-color); }

        .stat-card .info .value {
            font-size: 1.75rem;
            font-weight: 600;
        }

        .stat-card .info .label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* --- NEW: Quick Actions --- */
        .quick-actions .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
            transition: transform 0.2s, box-shadow 0.2s;
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

        /* --- NEW: Recent Activity --- */
        .activity-feed ul {
            list-style: none;
            max-height: 300px;
            overflow-y: auto;
        }
        .activity-feed li {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        .activity-feed li:last-child {
            border-bottom: none;
        }
        .activity-feed .activity-icon {
            font-size: 1.2rem;
            color: var(--text-light);
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            border-radius: 50%;
        }
        .activity-feed .activity-icon.bg-success { background-color: var(--success-color); }
        .activity-feed .activity-icon.bg-info { background-color: var(--info-color); }
        .activity-feed .activity-icon.bg-warning { background-color: var(--warning-color); }

        .activity-feed .activity-details .timestamp {
            font-size: 0.8rem;
            color: #999;
        }


        /* --- Hamburger Menu & Overlay for Mobile --- */
        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
            z-index: 1001; /* Ensure it's clickable */
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998;
        }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                width: 100%;
                /* --- CHANGE: Remove margin on mobile --- */
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
                    <li><a href="#" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-users"></i> Users <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <!-- --- CHANGE: "Patients" to "Users" --- -->
                            <li><a href="#"><i class="fas fa-user-injured"></i> Users</a></li>
                            <li><a href="#"><i class="fas fa-user-md"></i> Doctors</a></li>
                            <li><a href="#"><i class="fas fa-user-shield"></i> Staff</a></li>
                            <li><a href="#"><i class="fas fa-user-cog"></i> Admins</a></li>
                        </ul>
                    </li>
                    <li><a href="#"><i class="fas fa-calendar-alt"></i> Staff Shifts</a></li>
                    <li><a href="#"><i class="fas fa-chart-line"></i> Reports</a></li>
                    <li><a href="#"><i class="fas fa-history"></i> Activity Logs</a></li>
                    <li><a href="#"><i class="fas fa-cogs"></i> Settings</a></li>
                    <li><a href="#"><i class="fas fa-database"></i> Backup</a></li>
                    <li><a href="#"><i class="fas fa-bullhorn"></i> Notifications</a></li>
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
                <h1>Admin Dashboard</h1>
                <div class="user-profile-widget">
                    <i class="fas fa-user-crown"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($username); ?></strong><br>
                        <span>ID: <?php echo htmlspecialchars($display_user_id); ?></span>
                    </div>
                </div>
            </header>

            <div class="content-panel">
                <div class="welcome-message">
                    <h2>Welcome, Administrator!</h2>
                    <p>Oversee and manage all aspects of the MedSync platform. Use the sidebar to navigate through different management sections.</p>
                </div>
                
                <div class="stat-cards-container">
                    <div class="stat-card blue">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="info">
                            <div class="value">1,254</div>
                            <!-- --- CHANGE: "Patients" to "Users" --- -->
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="icon"><i class="fas fa-user-md"></i></div>
                        <div class="info">
                            <div class="value">78</div>
                            <div class="label">Active Doctors</div>
                        </div>
                    </div>
                    <div class="stat-card orange">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="info">
                            <div class="value">231</div>
                            <div class="label">Pending Appointments</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon"><i class="fas fa-server"></i></div>
                        <div class="info">
                            <div class="value">99.9%</div>
                            <div class="label">System Uptime</div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="grid-card quick-actions">
                        <h3>Quick Actions</h3>
                        <div class="actions-grid">
                            <a href="#" class="action-btn"><i class="fas fa-user-plus"></i> Add User</a>
                            <a href="#" class="action-btn"><i class="fas fa-file-alt"></i> Generate Report</a>
                            <a href="#" class="action-btn"><i class="fas fa-database"></i> Backup Data</a>
                            <a href="#" class="action-btn"><i class="fas fa-bullhorn"></i> Send Notification</a>
                        </div>
                    </div>
                    <div class="grid-card">
                        <h3>User Roles</h3>
                        <canvas id="userRolesChart"></canvas>
                    </div>
                    <div class="grid-card activity-feed" style="grid-column: 1 / -1;">
                        <h3>Recent Activity</h3>
                        <ul>
                            <li>
                                <div class="activity-icon bg-success"><i class="fas fa-user-plus"></i></div>
                                <div class="activity-details">
                                    <!-- --- CHANGE: "patient" to "user" --- -->
                                    New user 'John Doe' was registered.
                                    <div class="timestamp">2 minutes ago</div>
                                </div>
                            </li>
                            <li>
                                <div class="activity-icon bg-info"><i class="fas fa-user-md"></i></div>
                                <div class="activity-details">
                                    Dr. Smith logged in from a new device.
                                    <div class="timestamp">15 minutes ago</div>
                                </div>
                            </li>
                            <li>
                                <div class="activity-icon bg-warning"><i class="fas fa-calendar-plus"></i></div>
                                <div class="activity-details">
                                    Appointment booked for 'Jane Roe' with Dr. Jones.
                                    <div class="timestamp">1 hour ago</div>
                                </div>
                            </li>
                             <li>
                                <div class="activity-icon bg-success"><i class="fas fa-user-plus"></i></div>
                                <div class="activity-details">
                                    New staff member 'Emily White' was added.
                                    <div class="timestamp">3 hours ago</div>
                                </div>
                            </li>
                        </ul>
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
            const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');

            function toggleMenu() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }

            function closeMenu() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }

            hamburgerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleMenu();
            });

            overlay.addEventListener('click', closeMenu);
            
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !hamburgerBtn.contains(e.target) && sidebar.classList.contains('active')) {
                    closeMenu();
                }
            });
            
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    dropdownToggles.forEach(otherToggle => {
                        if (otherToggle !== this && otherToggle.classList.contains('active')) {
                            otherToggle.classList.remove('active');
                            otherToggle.nextElementSibling.style.maxHeight = null;
                        }
                    });

                    this.classList.toggle('active');
                    const dropdown = this.nextElementSibling;
                    if (dropdown.style.maxHeight) {
                        dropdown.style.maxHeight = null;
                    } else {
                        dropdown.style.maxHeight = dropdown.scrollHeight + "px";
                    }
                });
            });

            const navLinks = document.querySelectorAll('.sidebar-nav a');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 992) {
                        if (!link.closest('.nav-dropdown')) {
                             closeMenu();
                        }
                    }
                });
            });

            // --- NEW: Chart.js Initialization ---
            const ctx = document.getElementById('userRolesChart').getContext('2d');
            const userRolesChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    // --- CHANGE: "Patients" to "Users" ---
                    labels: ['Users', 'Doctors', 'Staff', 'Admins'],
                    datasets: [{
                        label: 'User Roles',
                        data: [1254, 78, 45, 5], // Example data
                        backgroundColor: [
                            '#3498db', // secondary-color
                            '#2ecc71', // success-color
                            '#f39c12', // warning-color
                            '#9b59b6'  // info-color
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    },
                    cutout: '70%'
                }
            });
        });
    </script>
</body>
</html>
