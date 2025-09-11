<?php
// --- CONFIG & SESSION START ---
require_once '../config.php'; 
require_once 'api.php'; 

// --- SESSION SECURITY & ROLE CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    session_destroy();
    header("Location: ../login/index.php?error=unauthorized"); 
    exit();
}

// --- BIND SESSION TO USER AGENT (PREVENTS SESSION HIJACKING) ---
if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    // If the user agent does not match, destroy the session.
    session_destroy();
    header("Location: ../login/index.php?error=hijacking_detected");
    exit();
}
// Set the user agent if it's not already set
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}

// --- PERIODICALLY REGENERATE SESSION ID (LIMITS SESSION LIFESPAN) ---
if (!isset($_SESSION['last_regen'])) {
    $_SESSION['last_regen'] = time();
} else if (time() - $_SESSION['last_regen'] > 900) { // Regenerate every 15 minutes
    session_regenerate_id(true);
    $_SESSION['last_regen'] = time();
}


// --- SESSION TIMEOUT ---
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_destroy();
    header("Location: ../login/index.php?session_expired=true"); 
    exit();
}
$_SESSION['loggedin_time'] = time();


// ===================================================================================
// --- STANDARD PAGE LOAD LOGIC ---
// ===================================================================================
$conn = getDbConnection();
$admin_id = $_SESSION['user_id'];

// Fetch admin's full name for the welcome message
$stmt = $conn->prepare("SELECT name, display_user_id FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_user = $result->fetch_assoc();
$admin_name = $admin_user ? htmlspecialchars($admin_user['name']) : 'Admin';
$display_user_id = $admin_user ? htmlspecialchars($admin_user['display_user_id']) : 'N/A';
$stmt->close();


$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$total_users_stmt = $conn->prepare("SELECT COUNT(*) as c FROM users");
$total_users_stmt->execute();
$total_users = $total_users_stmt->get_result()->fetch_assoc()['c'];

$active_doctors_stmt = $conn->prepare("SELECT COUNT(*) as c FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'doctor' AND u.is_active = 1");
$active_doctors_stmt->execute();
$active_doctors = $active_doctors_stmt->get_result()->fetch_assoc()['c'];

$pending_appointments = 0; 
$conn->close();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MedSync</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">
    
    <link rel="stylesheet" href="styles.css">
</head>

<body class="light-mode">
    <input type="hidden" id="current-user-id" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
    <div class="dashboard-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-target="dashboard"><i class="fas fa-home"></i>
                            Dashboard</a></li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-users"></i> Users <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <li><a href="#" class="nav-link" data-target="users-user"><i
                                        class="fas fa-user-injured"></i> Regular Users</a></li>
                            <li><a href="#" class="nav-link" data-target="users-doctor"><i class="fas fa-user-md"></i>
                                    Doctors</a></li>
                            <li><a href="#" class="nav-link" data-target="users-staff"><i
                                        class="fas fa-user-shield"></i> Staff</a></li>
                            <li><a href="#" class="nav-link" data-target="users-admin"><i class="fas fa-user-cog"></i>
                                    Admins</a></li>
                        </ul>
                    </li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-warehouse"></i> Inventory <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                            <li><a href="#" class="nav-link" data-target="inventory-blood"><i class="fas fa-tint"></i>
                                    Blood Inventory</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-medicine"><i
                                        class="fas fa-pills"></i> Medicine Inventory</a></li>
                            <li><a href="#" class="nav-link" data-target="inventory-departments"><i
                                        class="fas fa-building"></i> Departments</a></li>
                        </ul>
                    </li>
                    <li>
                        <div class="nav-dropdown-toggle">
                            <i class="fas fa-procedures"></i> Accommodations <i class="fas fa-chevron-right arrow"></i>
                        </div>
                        <ul class="nav-dropdown">
                             <li><a href="#" class="nav-link" data-target="inventory-wards"><i
                                        class="fas fa-hospital"></i> Wards</a></li>
                            <li><a href="#" class="nav-link" data-target="accommodations-bed"><i class="fas fa-bed"></i>
                                    Beds</a></li>
                            <li><a href="#" class="nav-link" data-target="accommodations-room"><i
                                        class="fas fa-door-closed"></i> Rooms</a></li>
                        </ul>
                    </li>
                    <li><a href="#" class="nav-link" data-target="appointments"><i class="fas fa-calendar-check"></i>
                            Appointments</a></li>
                    <li><a href="#" class="nav-link" data-target="schedules"><i class="fas fa-calendar-alt"></i>
                            Schedules</a></li>
                    <li><a href="#" class="nav-link" data-target="messenger"><i class="fas fa-paper-plane"></i>
                            Messenger</a></li>
                    <li><a href="#" class="nav-link" data-target="reports"><i class="fas fa-chart-line"></i> Reports</a>
                    </li>
                    <li><a href="#" class="nav-link" data-target="feedback"><i class="fas fa-comment-medical"></i> Patient Feedback</a></li>
                    <li><a href="#" class="nav-link" data-target="activity"><i class="fas fa-history"></i> Activity
                            Logs</a></li>
                    <li><a href="#" class="nav-link" data-target="settings"><i class="fas fa-user-edit"></i> My
                            Account</a></li>
                    <li><a href="#" class="nav-link" data-target="system-settings"><i class="fas fa-cog"></i> System
                            Settings</a></li>
                    <li><a href="#" class="nav-link" data-target="notifications"><i class="fas fa-bullhorn"></i>
                            Notifications</a></li>
                </ul>
            </nav>
            <a href="../logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="title-group">
                    <h1 id="panel-title">Dashboard</h1>
                    <h2 id="welcome-message">Hello, <?php echo $admin_name; ?>!</h2>
                </div>
                <div class="header-actions">

                    <div class="notification-bell-wrapper nav-link" id="notification-bell-wrapper"
                        data-target="all-notifications" style="position: relative; cursor: pointer; padding: 0.5rem;">
                        <i class="fas fa-bell" style="font-size: 1.2rem;"></i>
                        <span id="notification-count"
                            style="position: absolute; top: -5px; right: -8px; background-color: var(--danger-color); color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.7rem; display: none; place-items: center;"></span>
                    </div>

                    <div class="theme-switch-wrapper">
                        <i class="fas fa-sun"></i>
                        <label class="theme-switch" for="theme-toggle">
                            <input type="checkbox" id="theme-toggle" />
                            <span class="slider"></span>
                        </label>
                        <i class="fas fa-moon"></i>
                    </div>

                    <div class="user-profile-widget">
                        <i class="fas fa-user-crown"></i>
                        <div class="user-info">
                            <strong><?php echo $admin_name; ?></strong><br>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">ID:
                                <?php echo $display_user_id; ?></span>
                        </div>
                    </div>

                </div>
            </header>
            <div id="dashboard-panel" class="content-panel active">
                <div class="stat-cards-container">
                    <div class="stat-card blue">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="info">
                            <div class="value" id="total-users-stat"><?php echo $total_users; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card green">
                        <div class="icon"><i class="fas fa-user-md"></i></div>
                        <div class="info">
                            <div class="value" id="active-doctors-stat"><?php echo $active_doctors; ?></div>
                            <div class="label">Active Doctors</div>
                        </div>
                    </div>
                    <div class="stat-card orange">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="info">
                            <div class="value" id="pending-appointments-stat">0</div>
                            <div class="label">Pending Appointments</div>
                        </div>
                    </div>
                    <div class="stat-card purple" id="patient-satisfaction-stat" style="display: none;">
                        <div class="icon"><i class="fas fa-star-half-alt"></i></div>
                        <div class="info">
                            <div class="value" id="satisfaction-score">0/5</div>
                            <div class="label">Patient Satisfaction</div>
                        </div>
                    </div>
                    <div class="stat-card red" id="low-medicine-stat" style="display: none;">
                        <div class="icon"><i class="fas fa-pills"></i></div>
                        <div class="info">
                            <div class="value" id="low-medicine-count">0</div>
                            <div class="label">Low Medicines</div>
                        </div>
                    </div>
                    <div class="stat-card red" id="low-blood-stat" style="display: none;">
                        <div class="icon"><i class="fas fa-tint"></i></div>
                        <div class="info">
                            <div class="value" id="low-blood-count">0</div>
                            <div class="label">Low Blood Units</div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-grid">
                    <div class="grid-card">
                        <h3>User Roles Distribution</h3>
                        <div style="position: relative; height: auto; max-width: 480px; margin: auto;">
                            <canvas id="userRolesChart"></canvas>
                        </div>
                    </div>
                    <div class="grid-card quick-actions">
                        <h3>Quick Actions</h3>
                        <div class="actions-grid">
                            <a href="#" class="action-btn nav-link" data-target="users-user" id="quick-add-user-btn"><i
                                    class="fas fa-user-plus"></i> Add User</a>
                            <a href="#" class="action-btn nav-link" data-target="activity"><i
                                    class="fas fa-history"></i> Activity Log</a>
                            <a href="#" class="action-btn nav-link" data-target="inventory-departments"><i
                                    class="fas fa-building"></i> Departments</a>
                            <a href="#" class="action-btn nav-link" data-target="notifications"><i
                                    class="fas fa-bullhorn"></i> Send Notifications</a>
                            <a href="#" class="action-btn nav-link" data-target="system-settings"><i
                                    class="fas fa-cog"></i>
                                System Settings</a>
                            <a href="#" class="action-btn nav-link" data-target="settings"><i
                                    class="fas fa-user-edit"></i> My Account</a>
                        </div>
                    </div>
                </div>

            </div>
            <div id="appointments-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 id="appointments-table-title">Patient Appointments</h2>
                    <div class="form-group" style="flex-grow: 1; max-width: 400px; margin-bottom: 0;">
                        <label for="appointment-doctor-filter" style="margin-bottom: 0.25rem; font-weight: 500;">Filter
                            by Doctor</label>
                        <select id="appointment-doctor-filter">
                            <option value="all">All Doctors</option>
                        </select>
                    </div>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Appt. ID</th>
                                <th>Patient Details</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="appointments-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="users-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 id="user-table-title">Users</h2>
                    <div class="search-container" style="flex-grow: 1; max-width: 400px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="user-search-input" placeholder="Search...">
                        <label for="user-search-input" id="user-search-label">Search users...</label>
                    </div>
                    <button id="add-user-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New User</button>
                </div>
                <div class="table-container">
                    <table class="data-table user-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>User ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="user-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-blood-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Blood Inventory</h2>
                    <button id="add-blood-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Update Blood
                        Unit</button>
                </div>
                <div class="table-container">
                    <table class="data-table blood-table">
                        <thead>
                            <tr>
                                <th>Blood Group</th>
                                <th>Quantity (ml)</th>
                                <th>Status</th>
                                <th>Low Stock Threshold (ml)</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="blood-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-medicine-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Medicine Inventory</h2>
                    <button id="add-medicine-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New
                        Medicine</button>
                </div>
                <div class="table-container">
                    <table class="data-table medicine-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Unit Price (₹)</th>
                                <th>Low Stock Threshold</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="medicine-table-body">
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="inventory-departments-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Department Management</h2>
                    <button id="add-department-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New
                        Department</button>
                </div>
                <div class="table-container">
                    <table class="data-table" id="department-table">
                        <thead>
                            <tr>
                                <th>Department Name</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="department-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="inventory-wards-panel" class="content-panel">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2>Ward Management</h2>
                    <button id="add-ward-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Ward</button>
                </div>
                <div class="table-container">
                    <table class="data-table ward-table">
                        <thead>
                            <tr>
                                <th>Ward Name</th>
                                <th>Capacity</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="ward-table-body">
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="accommodations-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 id="accommodations-title">Bed Management</h2> <button id="add-accommodation-btn" class="btn btn-primary"><i class="fas fa-plus"></i> Add New Bed</button> </div>
                <div id="accommodations-container" class="resource-grid-container">
                    </div>
            </div>
            
            <div id="messenger-panel" class="content-panel">
                <div class="messenger-layout">
                    <div class="conversation-list" id="conversation-list">
                        <div class="conversation-search">
                            <input type="text" id="messenger-user-search"
                                placeholder="Search for staff, doctors, admins...">
                        </div>
                        <div class="scrollable-area" id="conversation-list-items">
                            <p class="no-items-message">Loading conversations...</p>
                        </div>
                    </div>

                    <div class="chat-area">
                        <div class="chat-window" id="chat-window" style="display: none;">
                            <div class="chat-header">
                                <button class="btn-back-to-list" id="back-to-conversations-btn" aria-label="Back to conversations">
                                    <i class="fas fa-arrow-left"></i>
                                </button>
                                <div class="user-info">
                                    <img src="../uploads/profile_pictures/default.png" alt="Avatar" class="chat-avatar"
                                        id="chat-header-avatar">
                                    <div>
                                        <span class="chat-user-name" id="chat-with-user-name"></span>
                                        <span class="chat-user-id" id="chat-with-user-id"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="chat-messages" id="chat-messages-container">
                            </div>
                            <form class="chat-input" id="message-form" novalidate>
                                <input type="text" id="message-input" placeholder="Type your message..."
                                    autocomplete="off" disabled>
                                <button type="submit" class="send-btn" disabled><i
                                        class="fas fa-paper-plane"></i></button>
                            </form>
                        </div>
                        <div class="no-chat-selected" id="no-chat-placeholder">
                            <i class="fas fa-comments"></i>
                            <h3>MedSync Messenger</h3>
                            <p>Select a conversation or search for a user to begin chatting.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div id="reports-panel" class="content-panel">
                <div class="report-controls">
                    <div class="form-group">
                        <label for="report-type">Report Type</label>
                        <select id="report-type" name="report_type">
                            <option value="financial">Financial</option>
                            <option value="patient">Patient Statistics</option>
                            <option value="resource">Resource Utilization</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start-date">Start Date</label>
                        <input type="date" id="start-date" name="start_date" value="<?php echo date('Y-m-01'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="end-date">End Date</label>
                        <input type="date" id="end-date" name="end_date" value="<?php echo date('Y-m-t'); ?>">
                    </div>

                    <button id="generate-report-btn" class="btn btn-primary"><i class="fas fa-sync"></i> Generate Report</button>

                    <form id="download-pdf-form" method="POST" action="api.php" target="_blank">
                        <input type="hidden" name="action" value="download_pdf">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" id="pdf-report-type" name="report_type">
                        <input type="hidden" id="pdf-start-date" name="start_date">
                        <input type="hidden" id="pdf-end-date" name="end_date">
                        <button type="submit" class="btn btn-secondary"><i class="fas fa-file-pdf"></i> Download PDF</button>
                    </form>
                </div>

                <div class="report-summary-cards" id="report-summary-cards">
                </div>

                <div id="report-table-container" style="margin-top: 2rem;">
                    </div>
            </div>

            <div id="activity-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Recent Activity Logs</h2>
                    <button id="refresh-logs-btn" class="btn btn-secondary"><i class="fas fa-sync-alt"></i>
                        Refresh</button>
                </div>
                <div id="activity-log-container">
                </div>
            </div>

            <div id="settings-panel" class="content-panel">
                <h3>My Account Details</h3>
                <p>Edit your personal information and password here.</p>
                <form id="profile-form" style="margin-top: 2rem; max-width: 600px;">
                    <input type="hidden" name="action" value="updateProfile">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label for="profile-name">Full Name</label>
                        <input type="text" id="profile-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-email">Email</label>
                        <input type="email" id="profile-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="profile-phone">Phone Number</label>
                        <input type="tel" id="profile-phone" name="phone" pattern="\+[0-9]{10,15}"
                            title="Enter in format +CountryCodeNumber">
                    </div>
                    <div class="form-group">
                        <label for="profile-username">Username</label>
                        <input type="text" id="profile-username" name="username" disabled>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Username cannot be changed.</small>
                    </div>
                    <div class="form-group">
                        <label for="profile-password">New Password</label>
                        <input type="password" id="profile-password" name="password">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep your current
                            password.</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>

            <div id="system-settings-panel" class="content-panel">
                <h3>System Settings</h3>
                <p>Configure system-wide settings here. Changes will take effect immediately.</p>
                <form id="system-settings-form" style="margin-top: 2rem; max-width: 600px;">
                    <input type="hidden" name="action" value="updateSystemSettings">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div class="form-group">
                        <label for="system_email">System Email Address</label>
                        <input type="email" id="system_email" name="system_email"
                            placeholder="e.g., your_email@gmail.com">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">This email will be used to send OTPs
                            and all other system notifications.</small>
                    </div>

                    <div class="form-group">
                        <label for="gmail_app_password">Gmail App Password</label>
                        <input type="password" id="gmail_app_password" name="gmail_app_password">
                        <small style="color: var(--text-muted); font-size: 0.8rem;">This is used for sending system
                            emails (e.g., OTPs, notifications). <a
                                href="https://support.google.com/accounts/answer/185833" target="_blank">How to get an
                                App Password</a>.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </form>
            </div>
            <div id="schedules-panel" class="content-panel">
                <div class="schedule-tabs">
                    <button class="schedule-tab-button active" data-tab="doctor-availability">Doctor
                        Availability</button>
                    <button class="schedule-tab-button" data-tab="staff-shifts">Staff Shifts</button>
                </div>

                <div id="doctor-availability-content" class="schedule-tab-content active">
                    <div class="schedule-controls">
                        <div class="form-group" style="flex-grow: 1; position: relative;">
                            <label for="doctor-search-input">Search for Doctor</label>
                            <input type="text" id="doctor-search-input" class="form-control" autocomplete="off" placeholder="Search by name, username, or ID...">
                            <input type="hidden" id="selected-doctor-id">
                            <div id="doctor-search-results" style="position: absolute; top: 100%; left: 0; right: 0; background-color: var(--bg-light); border: 1px solid var(--border-light); border-radius: 8px; z-index: 100; display: none; max-height: 250px; overflow-y: auto;">
                            </div>
                        </div>
                    </div>
                    <div id="doctor-schedule-editor" class="schedule-editor-container">
                        <p class="placeholder-text">Please select a doctor to view or edit their schedule.</p>
                    </div>
                    <div class="schedule-actions" style="display:none;">
                        <button id="save-schedule-btn" class="btn btn-primary"><i class="fas fa-save"></i> Save
                            Schedule</button>
                    </div>
                </div>

                <div id="staff-shifts-content" class="schedule-tab-content">
                    <div class="schedule-controls">
                        <div class="form-group" style="flex-grow: 1;">
                            <label for="staff-search-input">Search Staff</label>
                            <input type="text" id="staff-search-input" class="form-control" autocomplete="off" placeholder="Search by name, username, or ID...">
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>User ID</th>
                                    <th>Current Shift</th>
                                    <th>Assign New Shift</th>
                                </tr>
                            </thead>
                            <tbody id="staff-shifts-table-body">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="all-notifications-panel" class="content-panel">
            </div>
            <div id="notifications-panel" class="content-panel">
                <div class="schedule-tabs">
                    <button class="schedule-tab-button active" data-tab="broadcast">Broadcast</button>
                    <button class="schedule-tab-button" data-tab="individual">Individual</button>
                </div>

                <div id="broadcast-content" class="schedule-tab-content active">
                    <h3>Send Broadcast Notification</h3>
                    <form id="notification-form">
                        <input type="hidden" name="action" value="sendNotification">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div class="form-group">
                            <label for="notification-role">Select Role</label>
                            <select id="notification-role" name="role" required>
                                <option value="all">All Users</option>
                                <option value="user">Regular Users</option>
                                <option value="doctor">Doctors</option>
                                <option value="staff">Staff</option>
                                <option value="admin">Admins</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notification-message">Message</label>
                            <textarea id="notification-message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Broadcast</button>
                    </form>
                </div>

                <div id="individual-content" class="schedule-tab-content">
                    <h3>Send Individual Notification</h3>
                    <form id="individual-notification-form">
                        <input type="hidden" name="action" value="sendIndividualNotification">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" id="recipient-user-id" name="recipient_user_id" required>

                        <div class="form-group">
                            <label for="user-search">Search for User (Recipient)</label>
                            <input type="text" id="user-search" autocomplete="off"
                                placeholder="Search by name, username, or ID..." class="form-control">
                            <div id="user-search-results"
                                style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 8px; margin-top: 5px; display: none;">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="individual-notification-message">Message</label>
                            <textarea id="individual-notification-message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
            </div>
            
            <div id="feedback-panel" class="content-panel">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Patient Feedback & Ratings</h2>
                    </div>
                <div id="feedback-container">
                    </div>
            </div>
            
        </main>
    </div>

    <div class="overlay" id="overlay"></div>

    <div id="user-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title">Add New User</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="user-form" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="id" id="user-id">
                <input type="hidden" name="action" id="form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="profile_picture">Profile Picture</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*"
                            style="flex-grow: 1;">
                        <button type="button" id="remove-pfp-btn" class="btn btn-secondary"
                            style="display: none;">Remove Photo</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required minlength="4">
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="phone" pattern="\+[0-9]{10,15}"
                        title="Enter in format +CountryCodeNumber" required>
                    <div class="error-message"></div>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date of Birth</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group" id="password-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8">
                    <div class="error-message"></div>
                    <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep current password
                        when editing. Must be at least 8 characters.</small>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="user">Regular User</option>
                        <option value="doctor">Doctor</option>
                        <option value="staff">Staff</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div id="doctor-fields" class="role-specific-fields" style="display: none;">
                    <h4>Doctor Details</h4>
                    <div class="form-group">
                        <label for="specialty">Specialty</label>
                        <input type="text" id="specialty" name="specialty">
                    </div>
                    <div class="form-group">
                        <label for="qualifications">Qualifications (e.g., MBBS, MD)</label>
                        <input type="text" id="qualifications" name="qualifications">
                    </div>
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select id="department_id" name="department_id">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="is_available">Availability</label>
                        <select id="is_available" name="is_available">
                            <option value="1">Available</option>
                            <option value="0">On Leave</option>
                        </select>
                    </div>
                </div>

                <div id="staff-fields" class="role-specific-fields" style="display: none;">
                    <h4>Staff Details</h4>
                    <div class="form-group">
                        <label for="shift">Shift</label>
                        <select id="shift" name="shift">
                            <option value="day">Day</option>
                            <option value="night">Night</option>
                            <option value="off">Off</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="assigned_department_id">Assigned Department</label>
                        <select id="assigned_department_id" name="assigned_department_id">
                            <option value="">Select Department</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="is_active-group" style="display: none;">
                    <label for="is_active">Status</label>
                    <select id="is_active" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save User</button>
            </form>
        </div>
    </div>

    <div id="user-detail-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>User Profile</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div id="user-detail-content">
            </div>
        </div>
    </div>
    <div id="department-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="department-modal-title">Add New Department</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="department-form">
                <input type="hidden" name="id" id="department-id">
                <input type="hidden" name="action" id="department-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="department-name">Department Name</label>
                    <input type="text" id="department-name" name="name" required>
                </div>
                <div class="form-group" id="department-active-group" style="display: none;">
                    <label for="department-is-active">Status</label>
                    <select id="department-is-active" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Department</button>
            </form>
        </div>
    </div>

    <div id="medicine-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="medicine-modal-title">Add New Medicine</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="medicine-form">
                <input type="hidden" name="id" id="medicine-id">
                <input type="hidden" name="action" id="medicine-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="medicine-name">Medicine Name</label>
                    <input type="text" id="medicine-name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="medicine-description">Description</label>
                    <textarea id="medicine-description" name="description" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label for="medicine-quantity">Quantity</label>
                    <input type="number" id="medicine-quantity" name="quantity" min="0" required>
                </div>
                <div class="form-group">
                    <label for="medicine-unit-price">Unit Price (₹)</label>
                    <input type="number" id="medicine-unit-price" name="unit_price" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="medicine-low-stock-threshold">Low Stock Threshold</label>
                    <input type="number" id="medicine-low-stock-threshold" name="low_stock_threshold" min="0" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Medicine</button>
            </form>
        </div>
    </div>

    <div id="blood-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="blood-modal-title">Update Blood Inventory</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="blood-form">
                <input type="hidden" name="action" value="updateBlood">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group">
                    <label for="blood-group">Blood Group</label>
                    <select id="blood-group" name="blood_group" required>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="blood-quantity-ml">Quantity (ml)</label>
                    <input type="number" id="blood-quantity-ml" name="quantity_ml" min="0" required>
                </div>
                <div class="form-group">
                    <label for="blood-low-stock-threshold-ml">Low Stock Threshold (ml)</label>
                    <input type="number" id="blood-low-stock-threshold-ml" name="low_stock_threshold_ml" min="0"
                        required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Blood</button>
            </form>
        </div>
    </div>

    <div id="ward-form-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="ward-form-modal-title">Add New Ward</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="ward-form">
                <input type="hidden" name="id" id="ward-id-input">
                <input type="hidden" name="action" id="ward-form-action">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <label for="ward-name-input">Ward Name</label>
                    <input type="text" id="ward-name-input" name="name" required>
                </div>
                <div class="form-group">
                    <label for="ward-capacity-input">Capacity</label>
                    <input type="number" id="ward-capacity-input" name="capacity" min="0" required>
                </div>
                <div class="form-group">
                    <label for="ward-description-input">Description</label>
                    <textarea id="ward-description-input" name="description" rows="3"></textarea>
                </div>
                <div class="form-group" id="ward-active-group" style="display: none;">
                    <label for="ward-is-active-input">Status</label>
                    <select id="ward-is-active-input" name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save Ward</button>
            </form>
        </div>
    </div>

    <div id="accommodation-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="accommodation-modal-title">Add New Bed</h3>
                <button class="modal-close-btn">&times;</button>
            </div>
            <form id="accommodation-form">
                <input type="hidden" name="id" id="accommodation-id">
                <input type="hidden" name="action" id="accommodation-form-action">
                <input type="hidden" name="type" id="accommodation-type"> <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                <div class="form-group" id="accommodation-ward-group"> <label for="accommodation-ward-id">Ward</label>
                    <select id="accommodation-ward-id" name="ward_id">
                        </select>
                </div>
                <div class="form-group">
                    <label for="accommodation-number" id="accommodation-number-label">Bed Number</label>
                    <input type="text" id="accommodation-number" name="number" required>
                </div>
                <div class="form-group">
                    <label for="accommodation-price-per-day">Price Per Day (₹)</label>
                    <input type="number" id="accommodation-price-per-day" name="price_per_day" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="accommodation-status">Status</label>
                    <select id="accommodation-status" name="status" required>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="cleaning">Cleaning</option>
                    </select>
                </div>
                <div class="form-group" id="accommodation-patient-group" style="display: none;">
                    <label for="accommodation-patient-id">Patient</label>
                    <select id="accommodation-patient-id" name="patient_id">
                        <option value="">Select Patient</option>
                    </select>
                </div>
                <div class="form-group" id="accommodation-doctor-group" style="display: none;">
                    <label for="accommodation-doctor-id">Assign Doctor</label>
                    <select id="accommodation-doctor-id" name="doctor_id">
                        <option value="">Select Doctor</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Save</button>
            </form>
        </div>
    </div>


    <div id="notification-container"></div>

    <div id="confirm-dialog" class="confirm-dialog">
        <div class="confirm-content">
            <h4 id="confirm-title">Are you sure?</h4>
            <p id="confirm-message">This action cannot be undone.</p>
            <div class="confirm-buttons">
                <button id="confirm-btn-cancel" class="btn btn-secondary">Cancel</button>
                <button id="confirm-btn-ok" class="btn btn-danger">Confirm</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>