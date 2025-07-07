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
    
    <!-- Favicon Links -->
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <style>
        :root {
            --primary-color: #007BFF;
            --secondary-color: #17a2b8;
            --danger-color: #dc3545;
            --text-dark: #343a40;
            --text-light: #f8f9fa;
            --background-light: #ffffff;
            --background-grey: #f1f5f9;
            --border-radius: 12px;
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            transition: width 0.3s ease, transform 0.3s ease-in-out;
            z-index: 1000;
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
        
        .sidebar-nav a.active, .sidebar-nav a:hover, .nav-dropdown-toggle:hover {
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
            transition: max-height 0.3s ease-in-out;
            padding-left: 2rem; /* Indent dropdown items */
        }
        
        .nav-dropdown a {
            font-size: 0.95rem;
            padding-top: 0.7rem;
            padding-bottom: 0.7rem;
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

        /* --- Hamburger Menu & Overlay for Mobile --- */
        .hamburger-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
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
                position: fixed;
                left: -280px;
                height: 100%;
                transform: translateX(-280px);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                width: 100%;
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
                            <li><a href="#"><i class="fas fa-user-injured"></i> Patients</a></li>
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
                <!-- Admin-specific dashboard content will be loaded here -->
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

            function closeMenu() {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }

            hamburgerBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });

            overlay.addEventListener('click', closeMenu);
            
            document.addEventListener('click', (e) => {
                if (window.innerWidth <= 992 && !sidebar.contains(e.target) && !hamburgerBtn.contains(e.target) && sidebar.classList.contains('active')) {
                    closeMenu();
                }
            });
            
            // Dropdown functionality
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
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
                        closeMenu();
                    }
                });
            });
        });
    </script>
</body>
</html>
