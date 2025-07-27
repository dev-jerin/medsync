<?php
// Include the backend logic for session management and data retrieval.
require_once 'staff.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - MedSync</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
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
                <img src="images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-page="bed-management"><i class="fas fa-bed-pulse"></i> Bed Management</a></li>
                    <li><a href="#" class="nav-link" data-page="inventory"><i class="fas fa-boxes-stacked"></i> Inventory</a></li>
                    <li><a href="#" class="nav-link" data-page="billing"><i class="fas fa-file-invoice-dollar"></i> Billing</a></li>
                    <li><a href="#" class="nav-link" data-page="admissions"><i class="fas fa-person-booth"></i> Admissions</a></li>
                    <li><a href="#" class="nav-link" data-page="discharge"><i class="fas fa-hospital-user"></i> Discharges</a></li>
                    <li><a href="#" class="nav-link" data-page="labs"><i class="fas fa-vials"></i> Lab Results</a></li>
                    <li><a href="#" class="nav-link" data-page="user-management"><i class="fas fa-users-cog"></i> User Management</a></li>
                    <li><a href="#" class="nav-link" data-page="messenger"><i class="fas fa-paper-plane"></i> Messenger</a></li>
                    <li><a href="#" class="nav-link" data-page="notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="#" class="nav-link" data-page="profile"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
                </ul>
            </nav>
            <div class="sidebar-footer">
                 <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </aside>

        <div class="overlay" id="overlay"></div>

        <main class="main-content">
            <header class="main-header">
                <button class="hamburger-btn" id="hamburger-btn" aria-label="Open Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 id="main-header-title">Staff Dashboard</h1>
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
                            <span class="notification-badge" id="notification-badge">3</span>
                        </button>
                        <div class="notification-dropdown" id="notification-panel">
                            <div class="dropdown-header">
                                <h4>Recent Notifications</h4>
                            </div>
                            <div class="dropdown-body">
                                <a href="#" class="notification-item">
                                    <i class="fas fa-file-alt item-icon discharge"></i>
                                    <div>
                                        <p>New discharge request for <strong>Emily Davis</strong>.</p>
                                        <small>2 minutes ago</small>
                                    </div>
                                </a>
                                <a href="#" class="notification-item">
                                    <i class="fas fa-capsules item-icon inventory"></i>
                                    <div>
                                        <p>Inventory for <strong>Lisinopril</strong> is low.</p>
                                        <small>15 minutes ago</small>
                                    </div>
                                </a>
                                 <a href="#" class="notification-item">
                                    <i class="fas fa-pump-soap item-icon bed"></i>
                                    <div>
                                        <p><strong>Bed 105-A</strong> cleaning complete and ready.</p>
                                        <small>1 hour ago</small>
                                    </div>
                                </a>
                                <a href="#" class="notification-item">
                                    <i class="fas fa-bullhorn item-icon announcement"></i>
                                    <div>
                                        <p><strong>Admin:</strong> Monthly staff meeting at 4 PM today.</p>
                                        <small>3 hours ago</small>
                                    </div>
                                </a>
                            </div>
                            <div class="dropdown-footer">
                                <a href="#" id="view-all-notifications-link">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    <div class="user-profile-widget" id="user-profile-widget">
                        <img src="../images/staff-avatar.jpg" alt="Staff Avatar" class="profile-picture">
                        <div class="profile-info">
                            <strong><?php echo $username; ?></strong>
                            <span>Hospital Staff</span>
                        </div>
                    </div>
                </div>
            </header>

            <div id="dashboard-page" class="page active">
                <!-- Dashboard content -->
                 <div class="content-panel">
                    <div class="welcome-message">
                        <h2>Welcome back, <?php echo $username; ?>!</h2>
                        <p>Here’s a real-time overview of hospital resources and patient flow. Let's make today efficient.</p>
                    </div>
                    <div class="stat-cards-container">
                        <div class="stat-card beds"><div class="icon"><i class="fas fa-bed"></i></div><div class="info"><div class="value">45</div><div class="label">Available Beds</div></div></div>
                        <div class="stat-card inventory"><div class="icon"><i class="fas fa-capsules"></i></div><div class="info"><div class="value">12</div><div class="label">Low Stock Items</div></div></div>
                        <div class="stat-card discharges"><div class="icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="info"><div class="value">8</div><div class="label">Pending Discharges</div></div></div>
                        <div class="stat-card patients"><div class="icon"><i class="fas fa-hospital-user"></i></div><div class="info"><div class="value">62</div><div class="label">Active In-Patients</div></div></div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="grid-card">
                            <h3><i class="fas fa-tasks"></i> Pending Discharge Clearances</h3>
                            <table class="data-table">
                                <thead><tr><th>Patient</th><th>Room</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <tr><td data-label="Patient">Michael Brown</td><td data-label="Room">201-A</td><td data-label="Status"><span class="status pending-pharmacy">Pharmacy</span></td><td data-label="Action"><button class="action-btn">Clear</button></td></tr>
                                    <tr><td data-label="Patient">Emily Davis</td><td data-label="Room">B-05</td><td data-label="Status"><span class="status pending-nursing">Nursing</span></td><td data-label="Action"><button class="action-btn">Clear</button></td></tr>
                                    <tr><td data-label="Patient">Laura White</td><td data-label="Room">102-A</td><td data-label="Status"><span class="status pending-billing">Billing</span></td><td data-label="Action"><button class="action-btn">View</button></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="grid-card">
                             <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                             <div class="quick-actions-container">
                                 <a href="#" class="action-card" id="quick-action-admit"><i class="fas fa-user-plus"></i><span>Admit Patient</span></a>
                                 <a href="#" class="action-card" id="quick-action-add-user"><i class="fas fa-user-edit"></i><span>Add New User</span></a>
                                 <a href="#" class="action-card" id="quick-action-update-inventory"><i class="fas fa-dolly"></i><span>Update Stock</span></a>
                                 <a href="#" class="action-card" id="quick-action-add-bed"><i class="fas fa-bed-pulse"></i><span>Add New Bed</span></a>
                             </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="bed-management-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-bed-pulse"></i> Bed Management Overview</h3><button class="btn btn-primary" id="add-new-bed-btn"><i class="fas fa-plus"></i> Add New Bed</button></div>
                    <div class="filters">
                        <select id="bed-floor-filter"><option value="all">All Floors</option><option value="floor1">Floor 1 (General)</option><option value="floor2">Floor 2 (Cardiology)</option></select>
                        <select id="bed-status-filter"><option value="all">All Statuses</option><option value="available">Available</option><option value="occupied">Occupied</option><option value="cleaning">Cleaning</option></select>
                    </div>
                    <div class="bed-legend">
                        <div class="legend-item"><span class="legend-color available"></span> Available</div>
                        <div class="legend-item"><span class="legend-color occupied"></span> Occupied</div>
                        <div class="legend-item"><span class="legend-color cleaning"></span> Cleaning</div>
                        <div class="legend-item"><span class="legend-color reserved"></span> Reserved</div>
                    </div>
                    <div class="bed-grid-container">
                        <div class="bed-card status-occupied" data-status="occupied" data-floor="floor2"><div class="bed-id">201-A</div><div class="bed-details">Cardiology</div><div class="patient-info">Michael Brown</div></div>
                        <div class="bed-card status-available" data-status="available" data-floor="floor2"><div class="bed-id">201-B</div><div class="bed-details">Cardiology</div></div>
                        <div class="bed-card status-cleaning" data-status="cleaning" data-floor="floor1"><div class="bed-id">105-A</div><div class="bed-details">General</div></div>
                    </div>
                </div>
            </div>
            
            <div id="inventory-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-boxes-stacked"></i> Inventory Management</h3><button class="btn btn-primary" id="update-stock-btn"><i class="fas fa-plus"></i> Update Stock</button></div>
                    <div class="tabs"><button class="tab-link active" data-tab="medicines">Medicines</button><button class="tab-link" data-tab="blood">Blood Bank</button></div>
                    <div id="medicines-tab" class="inventory-tab active">
                        <div class="filters"><input type="text" id="medicine-search" class="search-bar" placeholder="Search by medicine name..."></div>
                        <table class="data-table" id="medicines-table">
                            <thead><tr><th>ID</th><th>Name</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <tr data-name="Aspirin 81mg"><td data-label="ID">MED101</td><td data-label="Name">Aspirin 81mg</td><td data-label="Stock">500</td><td data-label="Status"><span class="status in-stock">In Stock</span></td><td data-label="Action"><button class="action-btn">Edit</button></td></tr>
                                <tr data-name="Lisinopril 10mg"><td data-label="ID">MED102</td><td data-label="Name">Lisinopril 10mg</td><td data-label="Stock">45</td><td data-label="Status"><span class="status low-stock">Low Stock</span></td><td data-label="Action"><button class="action-btn">Edit</button></td></tr>
                                <tr data-name="Amoxicillin 500mg"><td data-label="ID">MED103</td><td data-label="Name">Amoxicillin 500mg</td><td data-label="Stock">0</td><td data-label="Status"><span class="status out-of-stock">Out of Stock</span></td><td data-label="Action"><button class="action-btn">Edit</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="blood-tab" class="inventory-tab" style="display: none;">
                        <table class="data-table" id="blood-table">
                            <thead><tr><th>Blood Type</th><th>Units Available</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <tr><td data-label="Blood Type">A+</td><td data-label="Units">25</td><td data-label="Status"><span class="status in-stock">Available</span></td><td data-label="Action"><button class="action-btn">Edit</button></td></tr>
                                <tr><td data-label="Blood Type">O-</td><td data-label="Units">8</td><td data-label="Status"><span class="status low-stock">Low Supply</span></td><td data-label="Action"><button class="action-btn">Edit</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="billing-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-file-invoice-dollar"></i> Billing Management</h3><button class="btn btn-primary" id="create-invoice-btn"><i class="fas fa-plus"></i> Create Invoice</button></div>
                    <div class="filters">
                        <input type="text" id="billing-search" class="search-bar" placeholder="Search by patient name or invoice ID...">
                        <select id="billing-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>
                    <table class="data-table" id="billing-table">
                        <thead><tr><th>Invoice ID</th><th>Patient Name</th><th>Amount</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <tr><td data-label="Invoice ID">INV-001</td><td data-label="Patient">Michael Brown</td><td data-label="Amount">$1,250.00</td><td data-label="Date">2025-07-26</td><td data-label="Status"><span class="status unpaid">Unpaid</span></td><td data-label="Actions"><button class="action-btn">View</button> <button class="action-btn">Print</button></td></tr>
                            <tr><td data-label="Invoice ID">INV-002</td><td data-label="Patient">Emily Davis</td><td data-label="Amount">$850.50</td><td data-label="Date">2025-07-25</td><td data-label="Status"><span class="status paid">Paid</span></td><td data-label="Actions"><button class="action-btn">View</button> <button class="action-btn">Print</button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="admissions-page" class="page">
                 <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-person-booth"></i> Patient Admissions</h3><button class="btn btn-primary" id="admit-patient-btn"><i class="fas fa-plus"></i> Admit Patient</button></div>
                    <div class="filters"><input type="text" id="admissions-search" class="search-bar" placeholder="Search by patient name or ID..."></div>
                    <table class="data-table" id="admissions-table">
                        <thead><tr><th>Adm. ID</th><th>Patient Name</th><th>Room/Bed</th><th>Admitted On</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr><td data-label="Adm. ID">ADM001</td><td data-label="Patient">Michael Brown</td><td data-label="Room">201-A</td><td data-label="Admitted On">2025-07-24</td><td data-label="Status"><span class="status admitted">Admitted</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="discharge-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-hospital-user"></i> Discharge Processing</h3></div>
                    <div class="filters"><input type="text" id="discharge-search" class="search-bar" placeholder="Search by patient..."><select id="discharge-status-filter"><option value="all">All Statuses</option><option value="pending-nursing">Pending Nursing</option><option value="pending-pharmacy">Pending Pharmacy</option></select></div>
                    <table class="data-table" id="discharge-table">
                         <thead><tr><th>Req. ID</th><th>Patient</th><th>Status</th><th>Doctor</th><th>Action</th></tr></thead>
                         <tbody>
                            <tr data-status="pending-nursing"><td data-label="Req. ID">D4501</td><td data-label="Patient">Emily Davis</td><td data-label="Status"><span class="status pending-nursing">Nursing</span></td><td data-label="Doctor">Dr. Carter</td><td data-label="Action"><button class="action-btn">Process Clearance</button></td></tr>
                            <tr data-status="pending-pharmacy"><td data-label="Req. ID">D4502</td><td data-label="Patient">Michael Brown</td><td data-label="Status"><span class="status pending-pharmacy">Pharmacy</span></td><td data-label="Doctor">Dr. Smith</td><td data-label="Action"><button class="action-btn">Process Clearance</button></td></tr>
                         </tbody>
                    </table>
                </div>
            </div>
            <div id="labs-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-vials"></i> Lab Results</h3><button class="btn btn-primary" id="add-lab-result-btn"><i class="fas fa-plus"></i> Add Lab Result</button></div>
                    <div class="filters"><input type="text" id="lab-search" class="search-bar" placeholder="Search by patient..."></div>
                     <table class="data-table" id="lab-results-table">
                        <thead><tr><th>Report ID</th><th>Patient</th><th>Test</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <tr><td data-label="ID">LR7201</td><td data-label="Patient">Sarah Johnson</td><td data-label="Test">CBC</td><td data-label="Status"><span class="status completed">Completed</span></td><td data-label="Actions"><button class="action-btn">View</button></td></tr>
                            <tr><td data-label="ID">LR7203</td><td data-label="Patient">Chris Lee</td><td data-label="Test">Thyroid Panel</td><td data-label="Status"><span class="status pending">Pending</span></td><td data-label="Actions"><button class="action-btn">Add Result</button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div id="user-management-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-users-cog"></i> User Management</h3><button class="btn btn-primary" id="add-new-user-btn"><i class="fas fa-user-plus"></i> Add New User</button></div>
                    <div class="filters"><input type="text" id="user-search" class="search-bar" placeholder="Search by name or ID..."><select id="user-role-filter"><option value="all">All Roles</option><option value="doctor">Doctors</option><option value="patient">Patients</option></select></div>
                    <table class="data-table" id="users-table">
                        <thead><tr><th>User ID</th><th>Name</th><th>Role</th><th>Email</th><th>Actions</th></tr></thead>
                        <tbody>
                            <tr data-role="doctor"><td data-label="ID">MED-DOC-001</td><td data-label="Name">Dr. Emily Carter</td><td data-label="Role">Doctor</td><td data-label="Email">e.carter@medsync.com</td><td data-label="Actions"><button class="action-btn">Edit</button> <button class="action-btn danger">Remove</button></td></tr>
                            <tr data-role="patient"><td data-label="ID">MED-PAT-001</td><td data-label="Name">Michael Brown</td><td data-label="Role">Patient</td><td data-label="Email">m.brown@email.com</td><td data-label="Actions"><button class="action-btn">Edit</button> <button class="action-btn danger">Remove</button></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="messenger-page" class="page">
                <div class="page-header"><h3><i class="fas fa-paper-plane"></i> Messenger</h3></div>
                <div class="messenger-layout">
                    <div class="conversation-list">
                        <div class="conversation-search">
                            <input type="text" placeholder="Search users...">
                        </div>
                        <div class="conversation-item active" data-user-name="Dr. Emily Carter">
                            <i class="fas fa-user-doctor user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">Dr. Emily Carter</div>
                                <div class="last-message">Bed 105-A is ready for your patient.</div>
                            </div>
                            <div class="message-meta">
                                <div class="message-time">2:15 PM</div>
                                <span class="unread-indicator"></span>
                            </div>
                        </div>
                        <div class="conversation-item" data-user-name="John (Billing)">
                            <i class="fas fa-user-tie user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">John (Billing)</div>
                                <div class="last-message">Please confirm the final charges for P003.</div>
                            </div>
                            <div class="message-meta">
                                <div class="message-time">1:45 PM</div>
                            </div>
                        </div>
                        <div class="conversation-item" data-user-name="Sarah (Head Nurse)">
                            <i class="fas fa-user-nurse user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">Sarah (Head Nurse)</div>
                                <div class="last-message">We need more saline drips on Floor 2.</div>
                            </div>
                             <div class="message-meta">
                                <div class="message-time">Yesterday</div>
                            </div>
                        </div>
                    </div>
                    <div class="chat-window">
                        <div class="chat-header">
                            <span id="chat-with-user">Dr. Emily Carter</span>
                        </div>
                        <div class="chat-messages" id="chat-messages-container">
                            <div class="message received">
                                <div class="message-content">
                                    <p>Hi Alice, has the cleaning for Bed 105-A been completed yet?</p>
                                    <span class="message-timestamp">2:14 PM</span>
                                </div>
                            </div>
                            <div class="message sent">
                                <div class="message-content">
                                    <p>Yes, Dr. Carter. I've just updated its status to Available.</p>
                                    <span class="message-timestamp">2:14 PM</span>
                                </div>
                            </div>
                             <div class="message received">
                                <div class="message-content">
                                    <p>Excellent, thank you. I'm admitting a new patient there shortly.</p>
                                    <span class="message-timestamp">2:15 PM</span>
                                </div>
                            </div>
                            <div class="message sent">
                                <div class="message-content">
                                    <p>Bed 105-A is ready for your patient.</p>
                                    <span class="message-timestamp">2:15 PM</span>
                                </div>
                            </div>
                        </div>
                        <form class="chat-input" id="message-form">
                            <input type="text" id="message-input" placeholder="Type your message..." autocomplete="off">
                            <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                </div>
            </div>

            <div id="notifications-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-bell"></i> All Notifications</h3>
                        <button class="btn btn-secondary" id="mark-all-read-btn">Mark All as Read</button>
                    </div>
                    <div class="filters">
                        <select id="notification-type-filter">
                            <option value="all">All Types</option>
                            <option value="discharge">Discharge</option>
                            <option value="inventory">Inventory</option>
                            <option value="bed">Beds</option>
                        </select>
                    </div>
                    <div class="notification-list-container">
                        <div class="notification-list-item unread" data-type="discharge">
                            <div class="item-icon-wrapper"><i class="fas fa-file-alt item-icon discharge"></i></div>
                            <div class="item-content"><p>New discharge request for <strong>Emily Davis</strong>.</p><small>2 minutes ago</small></div>
                        </div>
                        <div class="notification-list-item unread" data-type="inventory">
                            <div class="item-icon-wrapper"><i class="fas fa-capsules item-icon inventory"></i></div>
                            <div class="item-content"><p>Inventory for <strong>Lisinopril</strong> is low.</p><small>15 minutes ago</small></div>
                        </div>
                        <div class="notification-list-item unread" data-type="bed">
                            <div class="item-icon-wrapper"><i class="fas fa-pump-soap item-icon bed"></i></div>
                            <div class="item-content"><p><strong>Bed 105-A</strong> cleaning complete and ready.</p><small>1 hour ago</small></div>
                        </div>
                         <div class="notification-list-item read" data-type="announcement">
                            <div class="item-icon-wrapper"><i class="fas fa-bullhorn item-icon announcement"></i></div>
                            <div class="item-content"><p><strong>Admin:</strong> Monthly staff meeting at 4 PM today.</p><small>3 hours ago</small></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="profile-page" class="page">
                <!-- Profile content -->
                 <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-user-cog"></i> Profile Settings</h3>
                    </div>
                    <div class="profile-tabs">
                        <button class="profile-tab-link active" data-tab="personal-info"><i class="fas fa-user-edit"></i> Personal Information</button>
                        <button class="profile-tab-link" data-tab="security"><i class="fas fa-shield-alt"></i> Security</button>
                        <button class="profile-tab-link" data-tab="notifications"><i class="fas fa-bell"></i> Notifications</button>
                        <button class="profile-tab-link" data-tab="audit-log"><i class="fas fa-history"></i> Audit Log</button>
                    </div>
                    <div id="personal-info-tab" class="profile-tab-content active">
                        <form id="personal-info-form" class="settings-form">
                            <h4>Edit Your Personal Details</h4>
                            <div class="profile-picture-editor">
                                 <img src="../images/staff-avatar.jpg" alt="Staff Avatar" class="editable-profile-picture">
                                 <label for="profile-picture-upload" class="edit-picture-btn">
                                     <i class="fas fa-camera"></i>
                                     <input type="file" id="profile-picture-upload" accept="image/*">
                                 </label>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="profile-name">Full Name</label>
                                    <input type="text" id="profile-name" name="name" value="<?php echo $username; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="profile-id">Staff ID</label>
                                    <input type="text" id="profile-id" name="display_id" value="<?php echo $display_user_id; ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="profile-email">Email Address</label>
                                    <input type="email" id="profile-email" name="email" value="a.johnson@medsync.com">
                                </div>
                                <div class="form-group">
                                    <label for="profile-phone">Phone Number</label>
                                    <input type="tel" id="profile-phone" name="phone" value="+1-202-555-0199">
                                </div>
                                <div class="form-group full-width">
                                    <label for="profile-department">Department</label>
                                    <input type="text" id="profile-department" name="department" value="Administrative Staff">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            </div>
                        </form>
                    </div>
                    <div id="security-tab" class="profile-tab-content">
                        <form id="security-form" class="settings-form">
                            <h4>Change Your Password</h4>
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="current-password">Current Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="current-password" name="current_password" required>
                                        <i class="fas fa-eye-slash toggle-password"></i>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="new-password">New Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="new-password" name="new_password" required>
                                        <i class="fas fa-eye-slash toggle-password"></i>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="confirm-password">Confirm New Password</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="confirm-password" name="confirm_password" required>
                                        <i class="fas fa-eye-slash toggle-password"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
                            </div>
                        </form>
                    </div>
                    <div id="notifications-tab" class="profile-tab-content">
                        <form id="notifications-form" class="settings-form">
                            <h4>Manage Email Notifications</h4>
                            <p class="form-description">Control which email notifications you want to receive.</p>
                            <div class="notification-options">
                                <div class="notification-item">
                                    <div class="label-group">
                                        <label for="notif-discharge-req">Discharge Requests</label>
                                        <span>For new discharge requests needing clearance.</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notif-discharge-req" name="discharge_requests" checked>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="notification-item">
                                    <div class="label-group">
                                        <label for="notif-inventory">Inventory Alerts</label>
                                        <span>When medicine or blood stock is critically low.</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notif-inventory" name="inventory_alerts" checked>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="notification-item">
                                    <div class="label-group">
                                        <label for="notif-system">System Announcements</label>
                                        <span>For important updates, maintenance, and new features.</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notif-system" name="system_announcements" checked>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Preferences</button>
                            </div>
                        </form>
                    </div>
                    <div id="audit-log-tab" class="profile-tab-content">
                         <div class="settings-form">
                            <h4>Recent Account Activity</h4>
                            <p class="form-description">This is a read-only log of recent actions performed on your account.</p>
                            <div class="table-container">
                                <table class="data-table" id="audit-log-table">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Action</th>
                                            <th>Target</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-26 12:30 PM</td>
                                            <td data-label="Action"><span class="log-action-update">Bed Status Updated</span></td>
                                            <td data-label="Target">Bed 105-A</td>
                                            <td data-label="Details">Status changed to 'Available'</td>
                                        </tr>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-26 11:15 AM</td>
                                            <td data-label="Action"><span class="log-action-create">User Added</span></td>
                                            <td data-label="Target">Patient: John Appleseed</td>
                                            <td data-label="Details">Patient ID: MED-PAT-092</td>
                                        </tr>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-25 04:00 PM</td>
                                            <td data-label="Action"><span class="log-action-update">Inventory Updated</span></td>
                                            <td data-label="Target">Lisinopril (MED102)</td>
                                            <td data-label="Details">Stock set to 200 units</td>
                                        </tr>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-25 09:05 AM</td>
                                            <td data-label="Action"><span class="log-action-auth">Logged In</span></td>
                                            <td data-label="Target">Self</td>
                                            <td data-label="Details">IP: 192.168.1.15</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <!-- MODALS -->
        <div class="modal-overlay" id="add-bed-modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <h4>Add New Bed</h4>
                    <button class="modal-close-btn" data-modal-id="add-bed-modal-overlay">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="add-bed-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="bed-id">Bed ID / Name</label>
                                <input type="text" id="bed-id" name="bed_id" placeholder="e.g., Room 401-A" required>
                            </div>
                            <div class="form-group">
                                <label for="bed-floor">Floor / Ward</label>
                                <select id="bed-floor" name="bed_floor" required>
                                    <option value="">-- Select Floor --</option>
                                    <option value="floor1">Floor 1 (General)</option>
                                    <option value="floor2">Floor 2 (Cardiology)</option>
                                    <option value="floor3">Floor 3 (ICU)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="bed-type">Bed Type</label>
                                <select id="bed-type" name="bed_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="standard">Standard</option>
                                    <option value="icu">ICU</option>
                                    <option value="private">Private Room</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="bed-price">Price per Day ($)</label>
                                <input type="number" id="bed-price" name="bed_price" placeholder="e.g., 250" required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary modal-close-btn" data-modal-id="add-bed-modal-overlay">Cancel</button>
                    <button class="btn btn-primary" id="modal-save-btn-bed">Add Bed</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="create-invoice-modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <h4>Create New Invoice</h4>
                    <button class="modal-close-btn" data-modal-id="create-invoice-modal-overlay">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="create-invoice-form">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="invoice-patient">Select Patient</label>
                                <select id="invoice-patient" name="patient_id" required>
                                    <option value="">-- Choose a patient --</option>
                                    <option value="P001">P001 - Michael Brown</option>
                                    <option value="P003">P003 - Emily Davis</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="invoice-service">Service / Item</label>
                                <input type="text" id="invoice-service" name="service" placeholder="e.g., Consultation Fee" required>
                            </div>
                            <div class="form-group">
                                <label for="invoice-amount">Amount ($)</label>
                                <input type="number" id="invoice-amount" name="amount" placeholder="e.g., 150" required>
                            </div>
                            <div class="form-group full-width">
                                <label for="invoice-notes">Notes</label>
                                <textarea id="invoice-notes" name="notes" placeholder="Additional details..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary modal-close-btn" data-modal-id="create-invoice-modal-overlay">Cancel</button>
                    <button class="btn btn-primary" id="modal-save-btn-invoice">Generate Invoice</button>
                </div>
            </div>
        </div>
        <!-- End Modals -->

    </div>
    <script src="script.js"></script>
</body>
</html>