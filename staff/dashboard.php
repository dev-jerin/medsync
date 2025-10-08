<?php
// This file is already correctly structured for the discharge process.
// No changes were needed.

// Include the backend logic for session management and data retrieval.
require_once 'api.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - MedSync</title>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">

    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <div class="dashboard-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i>
                            <span>Dashboard</span></a></li>
                    <li><a href="#" class="nav-link" data-page="live-tokens"><i class="fas fa-ticket-alt"></i> <span>Live Tokens</span></a></li>
                    <li><a href="#" class="nav-link" data-page="callbacks"><i class="fas fa-phone-volume"></i> <span>Callback
                            Requests</span></a></li>
                    <li><a href="#" class="nav-link" data-page="bed-management"><i class="fas fa-bed-pulse"></i> <span>Bed
                            Management</span></a></li>
                    <li><a href="#" class="nav-link" data-page="inventory"><i class="fas fa-boxes-stacked"></i>
                            <span>Inventory</span></a></li>
                    <li><a href="#" class="nav-link" data-page="pharmacy"><i class="fas fa-pills"></i> <span>Pharmacy</span></a></li>
                    <li><a href="#" class="nav-link" data-page="billing"><i class="fas fa-file-invoice-dollar"></i>
                            <span>Billing</span></a></li>
                    <li><a href="#" class="nav-link" data-page="admissions"><i class="fas fa-person-booth"></i>
                            <span>Admissions</span></a></li>
                    <li><a href="#" class="nav-link" data-page="discharge"><i class="fas fa-hospital-user"></i>
                            <span>Discharges</span></a></li>
                    
                    <li><a href="#" class="nav-link" data-page="labs"><i class="fas fa-vials"></i> <span>Lab Orders</span></a></li>
                    <li><a href="#" class="nav-link" data-page="user-management"><i class="fas fa-users-cog"></i> <span>User
                            Management</span></a></li>
                    <li><a href="#" class="nav-link" data-page="messenger"><i class="fas fa-paper-plane"></i>
                            <span>Messenger</span></a></li>
                    <li><a href="#" class="nav-link" data-page="notifications"><i class="fas fa-bell"></i>
                            <span>Notifications</span></a></li>
                    <li><a href="#" class="nav-link" data-page="profile"><i class="fas fa-user-cog"></i> <span>Profile
                            Settings</span></a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                <a href="../logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <div class="overlay" id="overlay"></div>

        <main class="main-content">
            <header class="main-header">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 id="main-header-title">Dashboard</h1>
                <div class="header-widgets">
                    <div class="theme-toggle-widget">
                        <i class="fas fa-sun"></i>
                        <label class="theme-toggle-switch">
                            <input type="checkbox" id="theme-toggle-checkbox">
                            <span class="theme-slider"></span>
                        </label>
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="notification-widget">
                        <button class="notification-bell" id="notification-bell" aria-label="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notification-badge" style="display: none;"></span>
                        </button>
                        <div class="notification-dropdown" id="notification-panel">
                            <div class="dropdown-header">
                                <h4>Recent Notifications</h4>
                            </div>
                            <div class="dropdown-body">
                                <p class="no-items-message">No new notifications.</p>
                            </div>
                            <div class="dropdown-footer">
                                <a href="#" id="view-all-notifications-link">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    <div class="user-profile-widget" id="user-profile-widget">
                        <img src="<?php echo $profile_picture_path; ?>?v=<?php echo time(); ?>" alt="Staff Avatar"
                            class="profile-picture">
                        <div class="profile-info">
                            <strong><?php echo $username; ?></strong>
                            <span><?php echo ucfirst($_SESSION['role']); ?></span>
                        </div>
                    </div>
                </div>
            </header>
            
            <div id="dashboard-page" class="page active">
                <div class="content-panel">
                    <div class="welcome-message">
                        <h2>Welcome back, <?php echo $username; ?>!</h2>
                        <p>Here’s a real-time overview of hospital resources and patient flow. Let's make today
                            efficient.</p>
                    </div>
                    <div class="stat-cards-container">
                        <div class="stat-card beds">
                            <div class="icon"><i class="fas fa-bed"></i></div>
                            <div class="info">
                                <div class="value" id="stat-available-beds">...</div>
                                <div class="label">Available Beds</div>
                            </div>
                        </div>
                        <div class="stat-card inventory">
                            <div class="icon"><i class="fas fa-capsules"></i></div>
                            <div class="info">
                                <div class="value" id="stat-low-stock">...</div>
                                <div class="label">Low Stock Items</div>
                            </div>
                        </div>
                        <div class="stat-card discharges">
                            <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div class="info">
                                <div class="value" id="stat-pending-discharges">...</div>
                                <div class="label">Pending Discharges</div>
                            </div>
                        </div>
                        <div class="stat-card patients">
                            <div class="icon"><i class="fas fa-hospital-user"></i></div>
                            <div class="info">
                                <div class="value" id="stat-active-patients">...</div>
                                <div class="label">Active In-Patients</div>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="grid-card">
                            <h3><i class="fas fa-tasks"></i> Pending Discharge Clearances</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pending-discharges-table-body">
                                    </tbody>
                            </table>
                        </div>
                        <div class="grid-card">
                            <h3><i class="fas fa-chart-pie"></i> Bed Occupancy</h3>
                            <div class="chart-container" style="position: relative; height:250px;">
                                <canvas id="bedOccupancyChart"></canvas>
                            </div>
                        </div>
                        <div class="grid-card">
                             <h3><i class="fas fa-history"></i> Recent Activity</h3>
                             <div id="activity-feed-container" class="activity-feed">
                                </div>
                        </div>
                        <div class="grid-card">
                            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                            <div class="quick-actions-container">
                                <a href="#" class="action-card" id="quick-action-admit"><i
                                        class="fas fa-user-plus"></i><span>Admit Patient</span></a>
                                <a href="#" class="action-card" id="quick-action-add-user"><i
                                        class="fas fa-user-edit"></i><span>Add New User</span></a>
                                <a href="#" class="action-card" id="quick-action-update-inventory"><i
                                        class="fas fa-dolly"></i><span>Update Stock</span></a>
                                <a href="#" class="action-card" id="quick-action-add-bed"><i
                                        class="fas fa-bed-pulse"></i><span>Add New Bed</span></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="live-tokens-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-ticket-alt"></i> Live Token Queue</h3>
                    </div>
                    <div class="filters">
                        <div class="patient-search-container" style="flex-grow: 1;">
                            <input type="text" id="token-doctor-search" placeholder="Search for a doctor by name..." autocomplete="off">
                            <input type="hidden" id="token-doctor-id-hidden">
                            <div id="token-doctor-search-results" class="search-results-list"></div>
                        </div>
                    </div>
                    <div id="token-display-container">
                        <p class="no-items-message">Please select a doctor to see their live token queue for today.</p>
                    </div>
                </div>
            </div>

            <div id="callbacks-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-phone-volume"></i> Callback Requests</h3>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone Number</th>
                                    <th>Requested At</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="callbacks-table-body">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="bed-management-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-bed-pulse"></i> Bed Management Overview</h3>
                        <div class="header-actions">
                            <button class="btn btn-secondary" id="bulk-update-beds-btn" style="display: none;"><i class="fas fa-check-double"></i> Mark as Available</button>
                            <button class="btn btn-primary" id="add-new-bed-btn"><i class="fas fa-plus"></i> Add New Bed / Room</button>
                        </div>
                    </div>
                    <div class="filters">
                        <input type="text" id="bed-search-filter" placeholder="Search by Patient or Bed No..." style="flex-grow: 2;">
                        <select id="bed-location-filter">
                            <option value="all">All Wards & Rooms</option>
                        </select>
                        <select id="bed-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="cleaning">Cleaning</option>
                            <option value="reserved">Reserved</option>
                        </select>
                    </div>
                    <div class="bed-legend">
                        <div class="legend-item"><span class="legend-color available"></span> Available</div>
                        <div class="legend-item"><span class="legend-color occupied"></span> Occupied</div>
                        <div class="legend-item"><span class="legend-color cleaning"></span> Cleaning</div>
                        <div class="legend-item"><span class="legend-color reserved"></span> Reserved</div>
                    </div>
                    <div class="bed-grid-container" id="bed-grid-container">
                        </div>
                </div>
            </div>

            <div id="inventory-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-boxes-stacked"></i> Inventory Management</h3>
                    </div>
                    <div class="tabs">
                        <button class="tab-link active" data-tab="medicines">Medicines</button>
                        <button class="tab-link" data-tab="blood">Blood Bank</button>
                    </div>
                    <div id="medicines-tab" class="inventory-tab active">
                        <div class="filters"><input type="text" id="medicine-search" class="search-bar"
                                placeholder="Search by medicine name..."></div>
                        <table class="data-table" id="medicines-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                    <div id="blood-tab" class="inventory-tab">
                        <table class="data-table" id="blood-table">
                            <thead>
                                <tr>
                                    <th>Blood Type</th>
                                    <th>Units Available</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="blood-table-body">
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="pharmacy-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-pills"></i> Pharmacy Prescriptions</h3>
                    </div>
                    <div class="filters">
                        <input type="text" id="pharmacy-search" class="search-bar" placeholder="Search by patient name or ID...">
                        <select id="pharmacy-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="dispensed">Dispensed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <table class="data-table" id="pharmacy-prescriptions-table">
                        <thead>
                            <tr>
                                <th>Prescription ID</th>
                                <th>Patient Name</th>
                                <th>Doctor Name</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>

            <div id="billing-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-file-invoice-dollar"></i> Billing Management</h3><button
                            class="btn btn-primary" id="create-invoice-btn"><i class="fas fa-plus"></i> Create
                            Invoice</button>
                    </div>
                    <div class="filters">
                        <input type="text" id="billing-search" class="search-bar"
                            placeholder="Search by patient name or invoice ID...">
                        <select id="billing-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <table class="data-table" id="billing-table">
                        <thead>
                            <tr>
                                <th>Invoice ID</th>
                                <th>Patient Name</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>

            <div id="admissions-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-person-booth"></i> Patient Admissions</h3><button class="btn btn-primary"
                            id="admit-patient-btn"><i class="fas fa-plus"></i> Admit Patient</button>
                    </div>
                    <div class="filters"><input type="text" id="admissions-search" class="search-bar"
                            placeholder="Search by patient name or ID..."></div>
                    <table class="data-table" id="admissions-table">
                        <thead>
                            <tr>
                                <th>Adm. ID</th>
                                <th>Patient Name</th>
                                <th>Location</th>
                                <th>Admitted On</th>
                                <th>Discharged On</th> <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>
            <div id="discharge-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-hospital-user"></i> Discharge Processing</h3>
                    </div>
                    <div class="filters"><input type="text" id="discharge-search" class="search-bar"
                            placeholder="Search by patient..."><select id="discharge-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="nursing">Pending Nursing</option>
                            <option value="pharmacy">Pending Pharmacy</option>
                            <option value="billing">Pending Billing</option>
                        </select></div>
                    <table class="data-table" id="discharge-table">
                        <thead>
                            <tr>
                                <th>Req. ID</th>
                                <th>Patient</th>
                                <th>Status</th>
                                <th>Doctor</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>

            <div id="labs-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-vials"></i> Lab Order Queue</h3>
                        <button class="btn btn-primary" id="add-walkin-lab-order-btn"><i class="fas fa-plus"></i> Add Walk-in Order</button>
                    </div>
                    <div class="filters">
                        <input type="text" id="lab-search" class="search-bar" placeholder="Search by patient name or ID...">
                        <select id="lab-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="ordered">Ordered</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <table class="data-table" id="lab-orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th>Report</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div id="user-management-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-users-cog"></i> User Management</h3><button class="btn btn-primary"
                            id="add-new-user-btn"><i class="fas fa-user-plus"></i> Add New User</button>
                    </div>
                    <div class="filters"><input type="text" id="user-search" class="search-bar"
                            placeholder="Search by name or ID..."><select id="user-role-filter">
                            <option value="all">All Roles</option>
                            <option value="doctor">Doctors</option>
                            <option value="user">Patients</option>
                        </select></div>
                    <table class="data-table" id="users-table">
                        <thead>
                            <tr>
                                <th>User ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            </tbody>
                    </table>
                </div>
            </div>

            <div id="messenger-page" class="page">
                <div class="page-header">
                    <h3><i class="fas fa-paper-plane"></i> Messenger</h3>
                </div>
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
                                <div class="user-info">
                                    <img src="../images/staff-avatar.jpg" alt="Avatar" class="chat-avatar"
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

            <div id="notifications-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-bell"></i> Notifications</h3>
                        <button class="btn btn-secondary" id="mark-all-read-btn">Mark All as Read</button>
                    </div>
                    <div class="notification-list-container">
                        <p class="no-items-message">Loading notifications...</p>
                    </div>
                </div>
            </div>

            <div id="profile-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-user-cog"></i> Profile Settings</h3>
                    </div>
                    <div class="profile-tabs">
                        <button class="profile-tab-link active" data-tab="personal-info"><i
                                class="fas fa-user-edit"></i> Personal Information</button>
                        <button class="profile-tab-link" data-tab="security"><i class="fas fa-shield-alt"></i>
                            Security</button>
                        <button class="profile-tab-link" data-tab="audit-log"><i class="fas fa-history"></i> Audit
                            Log</button>
                    </div>
                    <div id="personal-info-tab" class="profile-tab-content active">
                        <form id="personal-info-form" class="settings-form" novalidate>
                            <h4>Edit Your Personal Details</h4>
                            <div class="profile-picture-editor">
                                <img src="<?php echo $profile_picture_path; ?>?v=<?php echo time(); ?>"
                                    alt="Staff Avatar" class="editable-profile-picture">
                                <label for="profile-picture-upload" class="edit-picture-btn" title="Upload new picture">
                                    <i class="fas fa-camera"></i>
                                    <input type="file" id="profile-picture-upload"
                                        accept="image/jpeg, image/png, image/gif">
                                </label>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="profile-name">Full Name</label>
                                    <input type="text" id="profile-name" name="name" value="<?php echo $username; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="profile-id">Staff ID</label>
                                    <input type="text" id="profile-id" name="display_id"
                                        value="<?php echo $display_user_id; ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="profile-username">Username</label>
                                    <input type="text" id="profile-username" name="username"
                                        value="<?php echo $raw_username; ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="profile-dob">Date of Birth</label>
                                    <input type="date" id="profile-dob" name="date_of_birth" 
                                        value="<?php echo $date_of_birth; ?>" max="<?php echo date('Y-m-d'); ?>">
                                    <small class="validation-error" id="profile-dob-error"></small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-email">Email Address</label>
                                    <input type="email" id="profile-email" name="email" value="<?php echo $email; ?>" required>
                                    <small class="validation-error" id="profile-email-error"></small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-phone">Phone Number</label>
                                    <input type="tel" id="profile-phone" name="phone" value="<?php echo $phone; ?>" pattern="\+91[0-9]{10}" minlength="13" maxlength="13">
                                    <small class="validation-error" id="profile-phone-error"></small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-department">Department</label>
                                    <select id="profile-department" name="department" required>
                                        <option value="">-- Select Department --</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['name']); ?>" <?php echo ($dept['name'] === $assigned_department) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($dept['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="profile-shift">Shift</label>
                                    <input type="text" id="profile-shift" name="shift"
                                        value="<?php echo ucfirst($shift); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save
                                    Changes</button>
                            </div>
                        </form>
                    </div>
                    <div id="security-tab" class="profile-tab-content">
                        <form id="security-form" class="settings-form" novalidate>
                            <h4>Change Your Password</h4>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="current-password">Current Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="current-password" name="current_password" required
                                            autocomplete="current-password">
                                        <i class="fas fa-eye-slash toggle-password"></i>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="new-password">New Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="new-password" name="new_password" required
                                            autocomplete="new-password">
                                        <i class="fas fa-eye-slash toggle-password"></i>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="confirm-password">Confirm New Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="confirm-password" name="confirm_password" required
                                            autocomplete="new-password">
                                        <i class="fas fa-eye-slash toggle-password"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update
                                    Password</button>
                            </div>
                        </form>
                    </div>
                    <div id="audit-log-tab" class="profile-tab-content">
                        <div class="settings-form">
                            <h4>Recent Account Activity</h4>
                            <p class="form-description">This is a read-only log of recent actions you have performed.</p>
                            <div class="table-container">
                                <table class="data-table" id="audit-log-table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <input type="hidden" id="csrf-token" value="<?php echo $_SESSION['csrf_token']; ?>">
    <input type="hidden" id="current-user-id" value="<?php echo $_SESSION['user_id']; ?>">
    
    <div class="modal-overlay" id="user-management-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="user-modal-title">Add New User</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="user-management-form" novalidate>
                    <input type="hidden" name="id" id="user-id">
                    <input type="hidden" name="action" id="user-form-action">
                    
                    <div class="form-group">
                        <label for="user-name">Full Name</label>
                        <input type="text" id="user-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="user-username">Username</label>
                        <input type="text" id="user-username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="user-email">Email</label>
                        <input type="email" id="user-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="user-phone">Phone Number</label>
                        <input type="tel" id="user-phone" name="phone" placeholder="+919876543210" maxlength="13">
                        <small class="validation-error" id="user-phone-error"></small>
                    </div>
                    <div class="form-group">
                        <label for="user-dob">Date of Birth</label>
                        <input type="date" id="user-dob" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>">
                        <small class="validation-error" id="user-dob-error"></small>
                    </div>
                    <div class="form-group" id="active-group" style="display: none;">
                        <label for="user-active">Account Status</label>
                        <select id="user-active" name="active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group" id="password-group">
                        <label for="user-password">Password</label>
                        <input type="password" id="user-password" name="password" required>
                        <small>Required for new users. Leave blank when editing to keep the same password.</small>
                    </div>
                    <div class="form-group">
                        <label for="user-role">Role</label>
                        <select id="user-role" name="role" required>
                            <option value="user">Patient</option>
                            <option value="doctor">Doctor</option>
                        </select>
                    </div>
                    
                    <div id="doctor-fields" style="display:none; border-top: 1px solid var(--border-color); margin-top: 1rem; padding-top: 1rem;">
                        <div class="form-group">
                            <label for="doctor-specialty">Specialty</label>
                            <select id="doctor-specialty" name="specialty_id">
                                </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="user-management-form" class="btn btn-primary">Save User</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="confirmation-dialog">
        <div class="modal-container" style="max-width: 400px;">
             <div class="modal-header">
                <h4 id="confirm-title">Confirm Action</h4>
                <button class="modal-close-btn" id="confirm-close-btn">&times;</button>
             </div>
             <div class="modal-body">
                <p id="confirm-message">Are you sure you want to proceed?</p>
             </div>
             <div class="modal-footer">
                <button id="confirm-cancel-btn" class="btn btn-secondary">Cancel</button>
                <button id="confirm-ok-btn" class="btn btn-danger">OK</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="medicine-stock-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="medicine-stock-modal-title">Update Medicine Stock</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="medicine-stock-form" novalidate>
                    <input type="hidden" name="action" value="updateMedicineStock">
                    <input type="hidden" name="id" id="medicine-stock-id">
                    
                    <div class="form-group">
                        <label for="medicine-stock-name">Medicine Name</label>
                        <input type="text" id="medicine-stock-name" name="name" readonly>
                    </div>
                    <div class="form-group">
                        <label for="medicine-stock-quantity">New Stock Quantity</label>
                        <input type="number" id="medicine-stock-quantity" name="quantity" min="0" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="medicine-stock-form" class="btn btn-primary">Update Stock</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="blood-stock-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="blood-stock-modal-title">Update Blood Stock</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="blood-stock-form" novalidate>
                    <input type="hidden" name="action" value="updateBloodStock">
                    
                    <div class="form-group">
                        <label for="blood-stock-group">Blood Group</label>
                        <input type="text" id="blood-stock-group" name="blood_group" readonly>
                    </div>
                    <div class="form-group">
                        <label for="blood-stock-quantity">New Quantity (ml)</label>
                        <input type="number" id="blood-stock-quantity" name="quantity_ml" min="0" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="blood-stock-form" class="btn btn-primary">Update Stock</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="bed-management-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="bed-modal-title">Add New Bed/Room</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bed-management-form" novalidate>
                    <input type="hidden" name="id" id="bed-form-id">
                    <input type="hidden" name="action" id="bed-form-action" value="addBedOrRoom">
                    
                    <div class="form-group">
                        <label for="bed-form-type">Type</label>
                        <select id="bed-form-type" name="type" required>
                            <option value="bed">Bed</option>
                            <option value="room">Private Room</option>
                        </select>
                    </div>
                    <div class="form-group" id="bed-form-ward-group">
                        <label for="bed-form-ward">Ward</label>
                        <select id="bed-form-ward" name="ward_id" required>
                            </select>
                    </div>
                    <div class="form-group">
                        <label for="bed-form-number">Number / Identifier</label>
                        <input type="text" id="bed-form-number" name="number" required>
                    </div>
                    <div class="form-group">
                        <label for="bed-form-price">Price per Day (₹)</label>
                        <input type="number" id="bed-form-price" name="price" min="0" step="0.01" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="bed-management-form" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="bed-assign-modal">
        <div class="modal-container" style="max-width: 500px;">
            <div class="modal-header">
                <h4 id="bed-assign-modal-title">Manage Occupancy</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="bed-assign-form" novalidate>
                    <input type="hidden" name="id" id="bed-assign-id">
                    <input type="hidden" name="type" id="bed-assign-type">
                    <input type="hidden" name="action" value="updateBedOrRoom">
                    
                    <p><strong>Status:</strong> <span id="bed-assign-current-status"></span></p>
                    <hr style="margin: 1rem 0;">

                    <div id="assign-patient-section">
                        <h4>Assign Patient</h4>
                        <div class="form-group">
                            <label for="bed-assign-patient-search">Select Patient</label>
                            <div class="patient-search-container">
                                <input type="text" id="bed-assign-patient-search" placeholder="Search by name or ID..." autocomplete="off">
                                <input type="hidden" id="bed-assign-patient-id" name="patient_id" required>
                                <div id="bed-assign-patient-results" class="search-results-list"></div>
                            </div>
                            <div id="bed-assign-selected-patient" class="selected-item-display" style="display:none;">
                                <strong>Selected:</strong>
                                <span id="bed-assign-selected-patient-name"></span>
                                <button type="button" class="clear-selection-btn" id="bed-assign-clear-patient-btn" title="Change Patient">&times;</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="bed-assign-doctor-search">Assign Doctor</label>
                            <div class="patient-search-container">
                                <input type="text" id="bed-assign-doctor-search" placeholder="Search by name or ID..." autocomplete="off">
                                <input type="hidden" id="bed-assign-doctor-id" name="doctor_id" required>
                                <div id="bed-assign-doctor-results" class="search-results-list"></div>
                            </div>
                            <div id="bed-assign-selected-doctor" class="selected-item-display" style="display:none;">
                                <strong>Selected:</strong>
                                <span id="bed-assign-selected-doctor-name"></span>
                                <button type="button" class="clear-selection-btn" id="bed-assign-clear-doctor-btn" title="Change Doctor">&times;</button>
                            </div>
                        </div>
                    </div>

                    <div id="discharge-patient-section" style="display: none;">
                        <h4>Patient Details</h4>
                        <p><strong>Patient:</strong> <span id="bed-assign-patient-name"></span></p>
                        <p><strong>Doctor:</strong> <span id="bed-assign-doctor-name"></span></p>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="bed-assign-form" class="btn btn-primary" id="bed-assign-submit-btn">Assign Patient</button>
                <button type="button" class="btn btn-danger" id="bed-discharge-btn" style="display: none;">Discharge Patient</button>
            </div>
        </div>
    </div>
    
    <div class="modal-overlay" id="lab-order-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="lab-modal-title">Manage Lab Order</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="lab-order-form" novalidate>
                    <input type="hidden" name="id" id="lab-order-id">
                    <input type="hidden" name="action" id="lab-form-action">
                    
                    <div class="form-group">
                        <label for="lab-patient-search">Patient</label>
                        <div class="patient-search-container">
                            <input type="text" id="lab-patient-search" placeholder="Search by patient name or ID..." autocomplete="off">
                            <input type="hidden" id="lab-patient-id" name="patient_id" required>
                            <div id="patient-search-results" class="search-results-list"></div>
                        </div>
                        <div id="selected-patient-display" class="selected-item-display" style="display:none;">
                            <strong>Selected:</strong>
                            <span id="selected-patient-name"></span>
                            <button type="button" id="clear-selected-patient-btn" class="clear-selection-btn" title="Change Patient">&times;</button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="lab-doctor-search">Requesting Doctor (Optional)</label>
                        <div class="patient-search-container">
                            <input type="text" id="lab-doctor-search" placeholder="Search by doctor name or ID..." autocomplete="off">
                            
                            <input type="hidden" id="lab-doctor-id" name="doctor_id">
                            
                            <div id="doctor-search-results" class="search-results-list"></div>
                        </div>
                        
                        <div id="selected-doctor-display" class="selected-item-display" style="display:none;">
                            <strong>Selected:</strong>
                            <span id="selected-doctor-name"></span>
                            <button type="button" id="clear-selected-doctor-btn" class="clear-selection-btn" title="Change Doctor">&times;</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="lab-status">Status</label>
                        <select id="lab-status" name="status" required>
                            <option value="ordered">Ordered</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="lab-test-name">Test Name</label>
                            <input type="text" id="lab-test-name" name="test_name" required>
                        </div>
                        <div class="form-group">
                            <label for="lab-test-date">Test Date</label>
                            <input type="date" id="lab-test-date" name="test_date">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="lab-cost">Cost (₹)</label>
                        <input type="number" id="lab-cost" name="cost" min="0" step="0.01" value="0.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Findings</label>
                        <div id="lab-findings-container">
                            </div>
                        <button type="button" id="add-finding-btn" class="btn btn-secondary btn-sm" style="margin-top: 10px;"><i class="fas fa-plus"></i> Add Finding</button>
                    </div>
                    <div class="form-group">
                        <label for="lab-summary">Summary</label>
                        <textarea id="lab-summary" name="summary" rows="3"></textarea>
                    </div>

                    <input type="hidden" id="lab-order-details" name="result_details">

                    <div class="form-group">
                        <label for="lab-attachment">Upload Report (PDF only)</label>
                        <input type="file" id="lab-attachment" name="attachment" accept="application/pdf">
                        <div id="current-attachment-info" style="margin-top: 8px;"></div>
                        <small>Leave empty to keep the existing report. Uploading a new file will replace the old one.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="lab-order-form" class="btn btn-primary">Save Order</button>
            </div>
        </div>
    </div>
    <div class="modal-overlay" id="discharge-clearance-modal">
        <div class="modal-container">
            <div class="modal-header">
                <h4 id="discharge-clearance-modal-title">Process Discharge Clearance</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="discharge-clearance-form" novalidate>
                    <input type="hidden" name="action" value="process_clearance">
                    <input type="hidden" id="discharge-id" name="discharge_id">
                    <div class="form-group">
                        <label for="discharge-notes">Notes</label>
                        <textarea id="discharge-notes" name="notes" rows="4" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="discharge-clearance-form" class="btn btn-primary">Process</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="create-invoice-modal">
        <div class="modal-container" style="max-width: 500px;">
            <div class="modal-header">
                <h4>Create New Invoice</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="create-invoice-form" novalidate>
                    <input type="hidden" name="action" value="generateInvoice">
                    <input type="hidden" name="admission_id" id="invoice-admission-id" required>

                    <div class="form-group">
                        <label for="invoice-patient-search">Find Billable Patient Admission</label>
                        <div class="patient-search-container">
                            <input type="text" id="invoice-patient-search" placeholder="Search by patient name or ID..." autocomplete="off">
                            <div id="invoice-patient-search-results" class="search-results-list"></div>
                        </div>
                        <div id="invoice-selected-patient-display" class="selected-item-display" style="display:none;">
                            <strong>Selected:</strong>
                            <span id="invoice-selected-patient-name"></span>
                            <button type="button" id="invoice-clear-selected-patient-btn" class="clear-selection-btn" title="Change Patient">&times;</button>
                        </div>
                        <small>Select a patient to calculate costs and generate a new invoice.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="create-invoice-form" class="btn btn-primary">Generate Invoice</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="view-prescription-modal">
        <div class="modal-container" style="max-width: 700px;">
            <div class="modal-header">
                <h4>Prescription Details</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <div class="billing-patient-info">
                    <p><strong>Patient:</strong> <span id="view-patient-name"></span></p>
                    <p><strong>Doctor:</strong> <span id="view-doctor-name"></span></p>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Dosage</th>
                            <th>Frequency</th>
                            <th>Qty Prescribed</th>
                            <th>Qty Dispensed</th>
                        </tr>
                    </thead>
                    <tbody id="view-items-tbody">
                        </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Close</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="pharmacy-billing-modal">
        <div class="modal-container" style="max-width: 800px;">
            <div class="modal-header">
                <h4 id="billing-modal-title">Create Pharmacy Bill</h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="pharmacy-billing-form" novalidate>
                    <input type="hidden" name="action" value="create_pharmacy_bill">
                    <input type="hidden" name="prescription_id" id="billing-prescription-id">
                    
                    <div class="billing-patient-info">
                        <p><strong>Patient:</strong> <span id="billing-patient-name"></span></p>
                        <p><strong>Doctor:</strong> <span id="billing-doctor-name"></span></p>
                    </div>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Prescribed</th>
                                <th>In Stock</th>
                                <th>Dispense Qty</th>
                                <th>Unit Price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="billing-items-tbody">
                            </tbody>
                    </table>
                    
                    <div class="billing-summary">
                        <h4>Total Amount: ₹<span id="billing-total-amount">0.00</span></h4>
                    </div>

                    <div class="form-group">
                        <label for="billing-payment-mode">Payment Mode</label>
                        <select id="billing-payment-mode" name="payment_mode" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="pharmacy-billing-form" class="btn btn-primary">Complete Payment & Dispense</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="process-payment-modal">
        <div class="modal-container" style="max-width: 450px;">
            <div class="modal-header">
                <h4>Process Payment for Invoice <span id="payment-invoice-id"></span></h4>
                <button class="modal-close-btn">&times;</button>
            </div>
            <div class="modal-body">
                <form id="process-payment-form" novalidate>
                    <input type="hidden" name="action" value="processPayment">
                    <input type="hidden" name="transaction_id" id="payment-transaction-id">
                    
                    <p>Total Amount Due:</p>
                    <h3 style="color: var(--primary-color);">₹<span id="payment-amount">0.00</span></h3>
                    
                    <div class="form-group" style="margin-top: 1.5rem;">
                        <label for="payment-mode">Payment Mode</label>
                        <select id="payment-mode" name="payment_mode" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close-btn">Cancel</button>
                <button type="submit" form="process-payment-form" class="btn btn-primary">Confirm Payment</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="script.js"></script>
</body>
</html>