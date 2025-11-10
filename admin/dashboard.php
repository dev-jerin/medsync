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

// Fetch admin's full name and profile picture for the welcome message
$stmt = $conn->prepare("SELECT name, display_user_id, profile_picture, email FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin_user = $result->fetch_assoc();
$admin_name = $admin_user ? htmlspecialchars($admin_user['name']) : 'Admin';
$display_user_id = $admin_user ? htmlspecialchars($admin_user['display_user_id']) : 'N/A';
$admin_profile_picture = $admin_user && $admin_user['profile_picture'] ? htmlspecialchars($admin_user['profile_picture']) : 'default.png';
$admin_email = $admin_user ? htmlspecialchars($admin_user['email']) : '';
$stmt->close();


$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$total_users_stmt = $conn->prepare("SELECT COUNT(*) as c FROM users");
$total_users_stmt->execute();
$total_users = $total_users_stmt->get_result()->fetch_assoc()['c'];

$active_doctors_stmt = $conn->prepare("SELECT COUNT(*) as c FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'doctor' AND u.is_active = 1");
$active_doctors_stmt->execute();
$active_doctors = $active_doctors_stmt->get_result()->fetch_assoc()['c'];

$pending_appointments_stmt = $conn->prepare("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'");
$pending_appointments_stmt->execute();
$pending_appointments = $pending_appointments_stmt->get_result()->fetch_assoc()['c']; 
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
                    <li><a href="#" class="nav-link" data-target="ip-management"><i class="fas fa-network-wired"></i> IP Management</a></li>
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

                    <div class="user-profile-widget" id="user-profile-dropdown-trigger">
                        <img src="../uploads/profile_pictures/<?php echo $admin_profile_picture; ?>" 
                             alt="Profile" 
                             class="profile-avatar"
                             onerror="this.src='../uploads/profile_pictures/default.png'">
                        <div class="user-info">
                            <strong><?php echo $admin_name; ?></strong><br>
                            <span style="color: var(--text-muted); font-size: 0.8rem;">ID:
                                <?php echo $display_user_id; ?></span>
                        </div>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                        
                        <!-- Dropdown Menu -->
                        <div class="user-dropdown-menu" id="user-dropdown-menu">
                            <div class="dropdown-header">
                                <img src="../uploads/profile_pictures/<?php echo $admin_profile_picture; ?>" 
                                     alt="Profile" 
                                     class="dropdown-avatar"
                                     onerror="this.src='../uploads/profile_pictures/default.png'">
                                <div class="dropdown-user-info">
                                    <strong><?php echo $admin_name; ?></strong>
                                    <span class="user-role-badge">Admin</span>
                                    <span class="user-email"><?php echo $admin_email; ?></span>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="#" class="dropdown-item dropdown-nav-link" data-target="settings">
                                <i class="fas fa-user-circle"></i>
                                <span>View My Profile</span>
                            </a>
                            <a href="#" class="dropdown-item dropdown-nav-link" data-target="activity">
                                <i class="fas fa-history"></i>
                                <span>Activity Log</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="../logout.php" class="dropdown-item logout-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>

                </div>
            </header>
            <div id="dashboard-panel" class="content-panel active">
                <div class="stat-cards-container">
                    <div class="stat-card blue clickable-stat-card nav-link" data-target="users-user" title="Click to view all users">
                        <div class="icon"><i class="fas fa-users"></i></div>
                        <div class="info">
                            <div class="value" id="total-users-stat"><?php echo $total_users; ?></div>
                            <div class="label">Total Users</div>
                        </div>
                    </div>
                    <div class="stat-card green clickable-stat-card nav-link" data-target="users-doctor" title="Click to view all doctors">
                        <div class="icon"><i class="fas fa-user-md"></i></div>
                        <div class="info">
                            <div class="value" id="active-doctors-stat"><?php echo $active_doctors; ?></div>
                            <div class="label">Active Doctors</div>
                        </div>
                    </div>
                    <div class="stat-card orange clickable-stat-card nav-link" data-target="appointments" title="Click to view appointments">
                        <div class="icon"><i class="fas fa-calendar-check"></i></div>
                        <div class="info">
                            <div class="value" id="pending-appointments-stat">0</div>
                            <div class="label">Pending Appointments</div>
                        </div>
                    </div>
                    <div class="stat-card purple clickable-stat-card nav-link" id="patient-satisfaction-stat" data-target="feedback" title="Click to view patient feedback" style="display: none;">
                        <div class="icon"><i class="fas fa-star-half-alt"></i></div>
                        <div class="info">
                            <div class="value" id="satisfaction-score">0/5</div>
                            <div class="label">Patient Satisfaction</div>
                        </div>
                    </div>
                    <div class="stat-card red clickable-stat-card nav-link" id="low-medicine-stat" data-target="inventory-medicine" title="Click to view medicine inventory" style="display: none;">
                        <div class="icon"><i class="fas fa-pills"></i></div>
                        <div class="info">
                            <div class="value" id="low-medicine-count">0</div>
                            <div class="label">Low Medicines</div>
                        </div>
                    </div>
                    <div class="stat-card red clickable-stat-card nav-link" id="low-blood-stat" data-target="inventory-blood" title="Click to view blood inventory" style="display: none;">
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2>Patient Appointments</h2>
                </div>

                <!-- Enhanced Filters Section -->
                <div style="background: var(--bg-grey); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                        <!-- Search Input -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="appointment-search-input" style="font-size: 0.9rem; font-weight: 500;">Search</label>
                            <div class="search-container" style="position: relative;">
                                <i class="fas fa-search search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                                <input type="text" 
                                       id="appointment-search-input" 
                                       placeholder="Search by patient or doctor..." 
                                       style="padding-left: 2.5rem; width: 100%;">
                            </div>
                        </div>

                        <!-- Appointment Status Filter -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="appointment-status-filter" style="font-size: 0.9rem; font-weight: 500;">Appointment Status</label>
                            <select id="appointment-status-filter">
                                <option value="all">All Statuses</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <!-- Token Status Filter -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="token-status-filter" style="font-size: 0.9rem; font-weight: 500;">Token Status</label>
                            <select id="token-status-filter">
                                <option value="all">All Token Statuses</option>
                                <option value="waiting">Waiting</option>
                                <option value="in_consultation">In Consultation</option>
                                <option value="completed">Completed</option>
                                <option value="skipped">Skipped</option>
                            </select>
                        </div>

                        <!-- Date From -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="appointment-date-from" style="font-size: 0.9rem; font-weight: 500;">Date From</label>
                            <input type="date" id="appointment-date-from">
                        </div>

                        <!-- Date To -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="appointment-date-to" style="font-size: 0.9rem; font-weight: 500;">Date To</label>
                            <input type="date" id="appointment-date-to" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <!-- Doctor Filter -->
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="appointment-doctor-select" style="font-size: 0.9rem; font-weight: 500;">Filter by Doctor</label>
                            <select id="appointment-doctor-select">
                                <option value="all">All Doctors</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <button id="appointment-apply-filters-btn" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <button id="appointment-reset-filters-btn" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button id="appointment-export-csv-btn" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem; margin-left: auto;">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <i class="fas fa-calendar-check" style="font-size: 2rem; opacity: 0.8;"></i>
                            <div>
                                <div style="font-size: 1.8rem; font-weight: 700;" id="total-appointments-stat">0</div>
                                <div style="font-size: 0.85rem; opacity: 0.9;">Total Appointments</div>
                            </div>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <i class="fas fa-clock" style="font-size: 2rem; opacity: 0.8;"></i>
                            <div>
                                <div style="font-size: 1.8rem; font-weight: 700;" id="scheduled-appointments-stat">0</div>
                                <div style="font-size: 0.85rem; opacity: 0.9;">Scheduled</div>
                            </div>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <i class="fas fa-check-circle" style="font-size: 2rem; opacity: 0.8;"></i>
                            <div>
                                <div style="font-size: 1.8rem; font-weight: 700;" id="completed-appointments-stat">0</div>
                                <div style="font-size: 0.85rem; opacity: 0.9;">Completed</div>
                            </div>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <i class="fas fa-times-circle" style="font-size: 2rem; opacity: 0.8;"></i>
                            <div>
                                <div style="font-size: 1.8rem; font-weight: 700;" id="cancelled-appointments-stat">0</div>
                                <div style="font-size: 0.85rem; opacity: 0.9;">Cancelled</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="id">Appt. ID <i class="fas fa-sort"></i></th>
                                <th class="sortable" data-sort="token">Token <i class="fas fa-sort"></i></th>
                                <th>Patient Details</th>
                                <th>Contact</th>
                                <th>Doctor Details</th>
                                <th class="sortable" data-sort="date">Date & Time <i class="fas fa-sort"></i></th>
                                <th>Status</th>
                                <th>Token Status</th>
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
                    <div class="search-container" style="flex-grow: 1; max-width: 400px;">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="medicine-search-input" placeholder="Search...">
                        <label for="medicine-search-input" id="medicine-search-label">Search by name or description...</label>
                    </div>
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
                                <th>Head of Department</th>
                                <th>Doctor Count</th>
                                <th>Staff Count</th>
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

                <!-- Security Information Display -->
                <div id="security-info-panel" style="margin: 2rem 0; padding: 1.5rem; background: var(--bg-grey); border-radius: 8px; border-left: 4px solid var(--primary-color); max-width: 600px;">
                    <h4 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-shield-alt"></i> Security Information
                    </h4>
                    <div id="security-info-content" style="display: grid; gap: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid var(--border-light);">
                            <span style="color: var(--text-muted);"><i class="fas fa-clock"></i> Last Login:</span>
                            <strong id="last-login-time">Loading...</strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
                            <span style="color: var(--text-muted);"><i class="fas fa-network-wired"></i> IP Address:</span>
                            <strong id="last-login-ip">Loading...</strong>
                        </div>
                    </div>
                </div>

                <form id="profile-form" style="margin-top: 2rem; max-width: 600px;">
                    <input type="hidden" name="action" value="updateProfile">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <!-- Profile Picture Section -->
                    <div class="form-group" style="text-align: center; margin-bottom: 2rem;">
                        <label style="display: block; margin-bottom: 1rem; font-weight: 600;">Profile Picture</label>
                        <div class="profile-picture-section">
                            <div class="profile-picture-editor">
                                <img id="profile-picture-preview" 
                                     src="../uploads/profile_pictures/default.png" 
                                     alt="Profile Picture" 
                                     class="editable-profile-picture">
                                <div class="profile-picture-overlay">
                                    <label for="profile-picture-input" class="picture-action-btn upload-btn" title="Upload from device">
                                        <i class="fas fa-upload"></i>
                                        <input type="file" 
                                               id="profile-picture-input" 
                                               name="profile_picture" 
                                               accept="image/jpeg,image/png,image/jpg" 
                                               style="display: none;">
                                    </label>
                                    <button type="button" class="picture-action-btn webcam-btn" id="admin-open-webcam-btn" title="Take photo with webcam">
                                        <i class="fas fa-camera"></i>
                                    </button>
                                    <button type="button" class="picture-action-btn remove-btn" id="remove-profile-picture-btn" 
                                        title="Remove profile picture" style="display: none;">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="profile-picture-hint">Hover to upload, take photo, or remove picture</p>
                        </div>
                    </div>

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
                        <input type="tel" id="profile-phone" name="phone" pattern="\+91[0-9]{10}"
                            title="Enter in format +91 followed by 10 digits" maxlength="13" minlength="13">
                    </div>
                    <div class="form-group">
                        <label for="profile-gender">Gender</label>
                        <select id="profile-gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="profile-dob">Date of Birth</label>
                        <input type="date" id="profile-dob" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="profile-username">Username</label>
                        <input type="text" id="profile-username" name="username" disabled>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Username cannot be changed.</small>
                    </div>

                    <!-- Password Change Section -->
                    <div style="margin-top: 2rem; padding: 1.5rem; background: var(--bg-grey); border-radius: 8px; border: 1px solid var(--border-light);">
                        <h4 style="margin-top: 0; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-lock"></i> Change Password
                        </h4>
                        <div class="form-group">
                            <label for="profile-current-password">Current Password</label>
                            <input type="password" id="profile-current-password" name="current_password" autocomplete="current-password">
                            <small style="color: var(--text-muted); font-size: 0.8rem;">Required when changing password</small>
                        </div>
                        <div class="form-group">
                            <label for="profile-password">New Password</label>
                            <input type="password" id="profile-password" name="password" autocomplete="new-password">
                            <small style="color: var(--text-muted); font-size: 0.8rem;">Leave blank to keep your current password</small>
                        </div>
                        <div class="form-group">
                            <label for="profile-confirm-password">Confirm New Password</label>
                            <input type="password" id="profile-confirm-password" name="confirm_password" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top: 1.5rem;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>

            <div id="system-settings-panel" class="content-panel">
                <h3>System Settings</h3>
                <p>Configure system-wide settings here. Changes will take effect immediately.</p>

                <!-- Database Backup Section -->
                <div style="margin-top: 2rem; padding: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <h4 style="margin-top: 0; display: flex; align-items: center; gap: 0.5rem; color: white;">
                        <i class="fas fa-database"></i> Database Backup
                    </h4>
                    <p style="margin-bottom: 1.5rem; opacity: 0.9; font-size: 0.95rem;">
                        Create a complete backup of your database. This will download a SQL file containing all tables and data.
                    </p>
                    <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <button type="button" id="backup-database-btn" class="btn" style="background: white; color: #667eea; font-weight: 600; border: none; padding: 0.75rem 1.5rem;">
                            <i class="fas fa-download"></i> Download Database Backup
                        </button>
                        <span id="backup-status" style="display: none; font-size: 0.9rem;">
                            <i class="fas fa-spinner fa-spin"></i> Creating backup...
                        </span>
                    </div>
                    <div style="margin-top: 1rem; padding: 0.75rem; background: rgba(255,255,255,0.1); border-radius: 4px; font-size: 0.85rem;">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Note:</strong> This backup includes all tables, users, appointments, inventory, and settings. Store it securely.
                    </div>
                </div>

                <div style="margin-top: 1.5rem; padding: 1rem; background-color: var(--bg-grey); border-radius: 8px; border: 1px solid var(--border-light);">
                    <p style="margin: 0; font-weight: 500;">
                        <strong>Current System Email:</strong> 
                        <span id="current-system-email-display">Loading...</span>
                    </p>
                </div>

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
                    <div>
                        <form id="feedback-filter-form" method="get" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                            <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search by patient, comment, appointment..." style="padding: 0.5rem; border-radius: 6px; border: 1px solid #ccc;">
                            <select name="type" style="padding: 0.5rem; border-radius: 6px;">
                                <option value="">All Types</option>
                                <option value="Suggestion" <?php if(isset($_GET['type']) && $_GET['type']=='Suggestion') echo 'selected'; ?>>Suggestion</option>
                                <option value="Complaint" <?php if(isset($_GET['type']) && $_GET['type']=='Complaint') echo 'selected'; ?>>Complaint</option>
                                <option value="Praise" <?php if(isset($_GET['type']) && $_GET['type']=='Praise') echo 'selected'; ?>>Praise</option>
                            </select>
                            <select name="anonymous" style="padding: 0.5rem; border-radius: 6px;">
                                <option value="">All</option>
                                <option value="1" <?php if(isset($_GET['anonymous']) && $_GET['anonymous']=='1') echo 'selected'; ?>>Anonymous</option>
                                <option value="0" <?php if(isset($_GET['anonymous']) && $_GET['anonymous']=='0') echo 'selected'; ?>>Identified</option>
                            </select>
                            <input type="date" name="date_from" value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>" style="padding: 0.5rem; border-radius: 6px;">
                            <input type="date" name="date_to" value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>" style="padding: 0.5rem; border-radius: 6px;">
                            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">Apply</button>
                        </form>
                    </div>
                </div>
                <div id="feedback-analytics" style="margin-bottom: 2rem;">
                    <?php
                    $conn = getDbConnection();
                    // Analytics: average ratings, count by type
                    $avg_stmt = $conn->query("SELECT AVG(overall_rating) as avg_overall, AVG(doctor_rating) as avg_doctor, AVG(nursing_rating) as avg_nursing, AVG(staff_rating) as avg_staff, AVG(cleanliness_rating) as avg_clean FROM feedback");
                    $avg = $avg_stmt->fetch_assoc();
                    $count_stmt = $conn->query("SELECT feedback_type, COUNT(*) as cnt FROM feedback GROUP BY feedback_type");
                    $type_counts = [];
                    while ($row = $count_stmt->fetch_assoc()) { $type_counts[$row['feedback_type']] = $row['cnt']; }
                    ?>
                    <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                        <div><strong>Avg. Overall:</strong> <?php echo round($avg['avg_overall'],2); ?>/5</div>
                        <div><strong>Avg. Doctor:</strong> <?php echo round($avg['avg_doctor'],2); ?>/5</div>
                        <div><strong>Avg. Nursing:</strong> <?php echo round($avg['avg_nursing'],2); ?>/5</div>
                        <div><strong>Avg. Staff:</strong> <?php echo round($avg['avg_staff'],2); ?>/5</div>
                        <div><strong>Avg. Cleanliness:</strong> <?php echo round($avg['avg_clean'],2); ?>/5</div>
                        <?php foreach (["Suggestion","Complaint","Praise"] as $ftype): ?>
                            <div><strong><?php echo $ftype; ?>s:</strong> <?php echo isset($type_counts[$ftype]) ? $type_counts[$ftype] : 0; ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div id="feedback-container">
                    <?php
                    // Filtering logic
                    $where = [];
                    $params = [];
                    if (!empty($_GET['search'])) {
                        $where[] = "(comments LIKE ? OR patient_id IN (SELECT id FROM users WHERE name LIKE ?))";
                        $params[] = "%".$_GET['search']."%";
                        $params[] = "%".$_GET['search']."%";
                    }
                    if (!empty($_GET['type'])) {
                        $where[] = "feedback_type = ?";
                        $params[] = $_GET['type'];
                    }
                    if (isset($_GET['anonymous']) && $_GET['anonymous'] !== "") {
                        $where[] = "is_anonymous = ?";
                        $params[] = $_GET['anonymous'];
                    }
                    if (!empty($_GET['date_from'])) {
                        $where[] = "created_at >= ?";
                        $params[] = $_GET['date_from'];
                    }
                    if (!empty($_GET['date_to'])) {
                        $where[] = "created_at <= ?";
                        $params[] = $_GET['date_to'];
                    }
                    $page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
                    $limit = 10;
                    $offset = ($page-1)*$limit;
                    $where_sql = $where ? ("WHERE ".implode(" AND ", $where)) : "";
                    $sql = "SELECT * FROM feedback $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
                    $stmt = $conn->prepare($sql);
                    if ($params) $stmt->bind_param(str_repeat('s',count($params)), ...$params);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows === 0) {
                        echo "<div style='padding:2rem;text-align:center;color:#888;'>No feedback found.</div>";
                    } else {
                        echo "<table class='data-table' style='width:100%;margin-bottom:2rem;'>";
                        echo "<thead><tr><th>Patient</th><th>Type</th><th>Ratings</th><th>Comments</th><th>Date</th><th>Anonymous</th><th>Appointment</th><th>Admission</th></tr></thead><tbody>";
                        while ($row = $result->fetch_assoc()) {
                            $patient = $conn->query("SELECT name FROM users WHERE id=".intval($row['patient_id']))->fetch_assoc();
                            echo "<tr>";
                            echo "<td>".htmlspecialchars($patient ? $patient['name'] : 'Unknown')."</td>";
                            echo "<td>".htmlspecialchars($row['feedback_type'])."</td>";
                            echo "<td>Overall: ".$row['overall_rating'].", Doctor: ".$row['doctor_rating'].", Nursing: ".$row['nursing_rating'].", Staff: ".$row['staff_rating'].", Cleanliness: ".$row['cleanliness_rating']."</td>";
                            echo "<td>".htmlspecialchars($row['comments'])."</td>";
                            echo "<td>".date('Y-m-d',strtotime($row['created_at']))."</td>";
                            echo "<td>".($row['is_anonymous'] ? 'Yes' : 'No')."</td>";
                            echo "<td>".($row['appointment_id'] ? $row['appointment_id'] : '-')."</td>";
                            echo "<td>".($row['admission_id'] ? $row['admission_id'] : '-')."</td>";
                            echo "</tr>";
                        }
                        echo "</tbody></table>";
                        // Pagination
                        $count_sql = "SELECT COUNT(*) as cnt FROM feedback $where_sql";
                        $count_stmt = $conn->prepare($count_sql);
                        if ($params) $count_stmt->bind_param(str_repeat('s',count($params)), ...$params);
                        $count_stmt->execute();
                        $total = $count_stmt->get_result()->fetch_assoc()['cnt'];
                        $pages = ceil($total/$limit);
                        echo "<div style='text-align:center;'>";
                        for ($i=1;$i<=$pages;$i++) {
                            $active = $i==$page ? "style='font-weight:bold;text-decoration:underline;'" : "";
                            $url = $_SERVER['PHP_SELF'].'?'.http_build_query(array_merge($_GET,['page'=>$i]));
                            echo "<a href='$url' $active style='margin:0 0.5rem;'>$i</a>";
                        }
                        echo "</div>";
                    }
                    $stmt->close();
                    $conn->close();
                    ?>
                </div>
            </div>

            <div id="ip-management-panel" class="content-panel">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
        <h2>IP Address Management</h2>
        <button id="add-ip-block-btn" class="btn btn-danger"><i class="fas fa-ban"></i> Block New IP</button>
    </div>

    <!-- Enhanced Filters Section -->
    <div style="background: var(--bg-grey); padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
            <!-- Search Input -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="ip-search-input" style="font-size: 0.9rem; font-weight: 500;">Search</label>
                <div class="search-container" style="position: relative;">
                    <i class="fas fa-search search-icon" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" 
                           id="ip-search-input" 
                           placeholder="Search by IP, username, or label..." 
                           style="padding-left: 2.5rem; width: 100%;">
                </div>
            </div>

            <!-- Status Filter -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="ip-status-filter" style="font-size: 0.9rem; font-weight: 500;">Status</label>
                <select id="ip-status-filter">
                    <option value="all">All IPs</option>
                    <option value="active">Active Only</option>
                    <option value="blocked">Blocked Only</option>
                </select>
            </div>

            <!-- Date From -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="ip-date-from" style="font-size: 0.9rem; font-weight: 500;">Date From</label>
                <input type="date" id="ip-date-from" max="<?php echo date('Y-m-d'); ?>">
            </div>

            <!-- Date To -->
            <div class="form-group" style="margin-bottom: 0;">
                <label for="ip-date-to" style="font-size: 0.9rem; font-weight: 500;">Date To</label>
                <input type="date" id="ip-date-to" max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <button id="ip-apply-filters-btn" class="btn btn-primary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <button id="ip-reset-filters-btn" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                <i class="fas fa-redo"></i> Reset
            </button>
            <button id="ip-export-csv-btn" class="btn btn-secondary" style="font-size: 0.9rem; padding: 0.5rem 1rem; margin-left: auto;">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-network-wired" style="font-size: 2rem; opacity: 0.8;"></i>
                <div>
                    <div style="font-size: 1.8rem; font-weight: 700;" id="total-ips-stat">0</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">Total IPs</div>
                </div>
            </div>
        </div>
        <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-ban" style="font-size: 2rem; opacity: 0.8;"></i>
                <div>
                    <div style="font-size: 1.8rem; font-weight: 700;" id="blocked-ips-stat">0</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">Blocked IPs</div>
                </div>
            </div>
        </div>
        <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-check-circle" style="font-size: 2rem; opacity: 0.8;"></i>
                <div>
                    <div style="font-size: 1.8rem; font-weight: 700;" id="active-ips-stat">0</div>
                    <div style="font-size: 0.85rem; opacity: 0.9;">Active IPs</div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th class="sortable" data-sort="ip_address">
                        IP Address <i class="fas fa-sort"></i>
                    </th>
                    <th class="sortable" data-sort="name">
                        Name/Label <i class="fas fa-sort"></i>
                    </th>
                    <th class="sortable" data-sort="user_count">
                        User Count <i class="fas fa-sort"></i>
                    </th>
                    <th>Associated Users</th>
                    <th class="sortable" data-sort="last_login">
                        Last Login <i class="fas fa-sort"></i>
                    </th>
                    <th class="sortable" data-sort="login_count">
                        Login Count <i class="fas fa-sort"></i>
                    </th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="ip-tracking-table-body">
                </tbody>
        </table>
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
                    <input type="tel" id="phone" name="phone" pattern="\+91[0-9]{10}" minlength="13" maxlength="13"
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
                        <label for="specialty_id">Specialty</label>
                        <select id="specialty_id" name="specialty_id">
                            <option value="">Select Specialty</option>
                            </select>
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

                <div class="form-group">
                    <label for="head_of_department_id">Head of Department (Optional)</label>
                    <select id="head_of_department_id" name="head_of_department_id">
                        <option value="">-- None --</option>
                        </select>
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

    <!-- Webcam Capture Modal -->
    <div id="admin-webcam-modal" class="modal">
        <div class="modal-content webcam-modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Capture Profile Picture</h3>
                <button type="button" class="modal-close-btn" id="close-admin-webcam-modal">&times;</button>
            </div>
            <div class="modal-body webcam-modal-body">
                <div class="webcam-container">
                    <video id="admin-webcam-video" autoplay playsinline></video>
                    <canvas id="admin-webcam-canvas" style="display: none;"></canvas>
                    <div id="admin-webcam-preview" class="webcam-preview" style="display: none;">
                        <img id="admin-webcam-captured-image" alt="Captured">
                    </div>
                </div>
                <div class="webcam-status" id="admin-webcam-status">
                    <i class="fas fa-info-circle"></i> <span>Initializing camera...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="admin-webcam-cancel-btn">Cancel</button>
                <button type="button" class="btn btn-primary" id="admin-webcam-capture-btn">
                    <i class="fas fa-camera"></i> Capture
                </button>
                <button type="button" class="btn btn-warning" id="admin-webcam-retake-btn" style="display: none;">
                    <i class="fas fa-redo"></i> Retake
                </button>
                <button type="button" class="btn btn-success" id="admin-webcam-use-btn" style="display: none;">
                    <i class="fas fa-check"></i> Use This Photo
                </button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>

</html>