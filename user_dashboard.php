<?php
require_once 'config.php';

// --- Session Security ---
// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Check if the user has the correct role ('user')
if ($_SESSION['role'] !== 'user') {
    // If the role is incorrect, destroy the session and redirect to login
    session_destroy();
    header("Location: login.php");
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

$username = $_SESSION['username'];
$display_user_id = $_SESSION['display_user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - MedSync</title>
    
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
        }

        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }

        /* --- Sidebar --- */
        .sidebar {
            width: 260px;
            background-color: var(--background-light);
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            transition: width 0.3s ease, left 0.3s ease-in-out;
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

        .sidebar-nav ul {
            list-style: none;
            flex-grow: 1;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.9rem 1rem;
            color: #5a6a7c;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: background-color 0.3s, color 0.3s;
        }

        .sidebar-nav a i {
            width: 20px;
            margin-right: 1rem;
            font-size: 1.1rem;
        }
        
        .sidebar-nav a.active, .sidebar-nav a:hover {
            background-color: var(--primary-color);
            color: var(--text-light);
        }
        
        .sidebar-footer {
            margin-top: auto;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.9rem 1rem;
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
        }
        
        .logout-btn:hover {
            background-color: #dc3545;
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

        /* --- Hamburger Menu & Overlay --- */
        .hamburger-btn {
            display: none; /* Hidden by default on desktop */
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary-color);
            cursor: pointer;
            margin-right: 1rem;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 998; /* Below sidebar, above content */
        }

        /* --- Responsive Design --- */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -260px; /* Hide sidebar off-screen */
                height: 100%;
                z-index: 999;
                transition: left 0.3s ease-in-out;
                overflow-y: auto; /* Allow vertical scrolling on mobile */
            }

            .sidebar.active {
                left: 0; /* Show sidebar */
            }

            .main-content {
                /* On mobile, main content takes full width */
                width: 100%;
            }
            
            .hamburger-btn {
                display: block; /* Show hamburger on mobile */
            }

            .main-header {
                /* Re-order for mobile view */
                justify-content: flex-start;
            }

            .main-header h1 {
                order: 2; /* h1 comes after hamburger */
                flex-grow: 1;
                text-align: center;
            }
            
            .user-profile-widget {
                order: 3; /* Profile widget at the end */
            }

            .hamburger-btn {
                order: 1; /* Hamburger at the start */
            }

            .overlay.active {
                display: block;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <!-- Navigation links will be added here based on project plan -->
                    <li><a href="#" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#"><i class="fas fa-user-edit"></i> Edit Profile</a></li>
                    <li><a href="#"><i class="fas fa-user-md"></i> Search Doctor</a></li>
                    <li><a href="#"><i class="fas fa-calendar-check"></i> Book Appointment</a></li>
                    <li><a href="#"><i class="fas fa-clipboard-list"></i> Appointment Status</a></li>
                    <li><a href="#"><i class="fas fa-history"></i> History</a></li>
                    <li><a href="#"><i class="fas fa-file-prescription"></i> Prescriptions</a></li>
                    <li><a href="#"><i class="fas fa-file-invoice-dollar"></i> Bills</a></li>
                    <li><a href="#"><i class="fas fa-vials"></i> Lab Results</a></li>
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
                <!-- Hamburger button for mobile -->
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>Dashboard</h1>
                <div class="user-profile-widget">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($username); ?></strong><br>
                        <span>ID: <?php echo htmlspecialchars($display_user_id); ?></span>
                    </div>
                </div>
            </header>

            <div class="content-panel">
                <div class="welcome-message">
                    <h2>Welcome back, <?php echo htmlspecialchars($username); ?>!</h2>
                    <p>This is your personal health dashboard. From here, you can manage your appointments, view your medical records, and more.</p>
                </div>
                <!-- More dashboard widgets and content will be added here -->
            </div>
        </main>
    </div>
    
    <!-- Overlay for mobile menu -->
    <div class="overlay" id="overlay"></div>

    <script>
        // --- JavaScript for Hamburger Menu ---
        const hamburgerBtn = document.getElementById('hamburger-btn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('overlay');

        /**
         * Closes the mobile menu by removing the 'active' class
         * from the sidebar and overlay.
         */
        function closeMenu() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Toggles the 'active' class on the sidebar and overlay when the hamburger is clicked.
        hamburgerBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });

        // Closes the menu when the overlay is clicked.
        overlay.addEventListener('click', closeMenu);
        
        // Optional: Close the menu when a navigation link is clicked.
        // This is useful for single-page applications or when links navigate on the same page.
        const navLinks = document.querySelectorAll('.sidebar-nav a');
        navLinks.forEach(link => {
            // Add a click event listener to each navigation link
            link.addEventListener('click', closeMenu);
        });
    </script>
</body>
</html>
