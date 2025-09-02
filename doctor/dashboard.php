<?php
// Include the backend logic for session management and data retrieval.
require_once 'api.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - MedSync</title>
    
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
                <img src="../images/logo.png" alt="MedSync Logo" class="logo-img">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <ul>
                    <li><a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="#" class="nav-link" data-page="appointments"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="#" class="nav-link" data-page="patients"><i class="fas fa-users"></i> My Patients</a></li>
                    <li><a href="#" class="nav-link" data-page="prescriptions"><i class="fas fa-file-prescription"></i> Prescriptions</a></li>
                    <li><a href="#" class="nav-link" data-page="admissions"><i class="fas fa-procedures"></i> Admissions</a></li>
                    <li><a href="#" class="nav-link" data-page="bed-management"><i class="fas fa-bed-pulse"></i> Bed Management</a></li>
                    <li><a href="#" class="nav-link" data-page="messenger"><i class="fas fa-paper-plane"></i> Messenger</a></li>
                    <li><a href="#" class="nav-link" data-page="notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="#" class="nav-link" data-page="discharge"><i class="fas fa-sign-out-alt"></i> Discharge Requests</a></li>
                    <li><a href="#" class="nav-link" data-page="labs"><i class="fas fa-vials"></i> Lab Results</a></li>
                    <li><a href="#" class="nav-link" data-page="profile"><i class="fas fa-user-cog"></i> Profile Settings</a></li>
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
                <h1 id="main-header-title">Doctor Dashboard</h1>
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
                                    <i class="fas fa-vial item-icon vial"></i>
                                    <div>
                                        <p>New lab result for <strong>Sarah Johnson</strong> is available.</p>
                                        <small>5 minutes ago</small>
                                    </div>
                                </a>
                                <a href="#" class="notification-item">
                                    <i class="fas fa-bullhorn item-icon announcement"></i>
                                    <div>
                                        <p><strong>Admin:</strong> System maintenance scheduled for 10 PM tonight.</p>
                                        <small>1 hour ago</small>
                                    </div>
                                </a>
                                <a href="#" class="notification-item">
                                    <i class="fas fa-sign-out-alt item-icon discharge"></i>
                                    <div>
                                        <p>Discharge for <strong>David Wilson</strong> is now ready.</p>
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
                        <img src="../images/doctor-avatar.jpg" alt="Doctor Avatar" class="profile-picture">
                        <div class="profile-info">
                            <strong>Dr. <?php echo $username; ?></strong>
                            <span>Cardiology</span>
                        </div>
                    </div>
                </div>
            </header>

            <div id="dashboard-page" class="page active">
                <div class="content-panel">
                    <div class="welcome-message">
                        <h2>Welcome back, Dr. <?php echo $username; ?>!</h2>
                        <p>Here’s a summary of your activities and patient status for today. Stay organized and efficient.</p>
                    </div>
                    <div class="stat-cards-container">
                        <div class="stat-card appointments"><div class="icon"><i class="fas fa-calendar-check"></i></div><div class="info"><div class="value">12</div><div class="label">Today's Appointments</div></div></div>
                        <div class="stat-card admissions"><div class="icon"><i class="fas fa-procedures"></i></div><div class="info"><div class="value">3</div><div class="label">Active Admissions</div></div></div>
                        <div class="stat-card discharges"><div class="icon"><i class="fas fa-walking"></i></div><div class="info"><div class="value">2</div><div class="label">Pending Discharges</div></div></div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="grid-card" style="grid-column: 1 / -1;">
                            <h3><i class="fas fa-user-clock"></i> Today's Appointment Queue</h3>
                            <table class="data-table">
                                <thead><tr><th>Token</th><th>Patient Name</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                    <tr><td data-label="Token"><strong>#05</strong></td><td data-label="Patient">John Doe</td><td data-label="Time">10:30 AM</td><td data-label="Status"><span class="status in-consultation">In Consultation</span></td><td data-label="Action"><button class="action-btn"><i class="fas fa-eye"></i> View</button></td></tr>
                                    <tr><td data-label="Token"><strong>#06</strong></td><td data-label="Patient">Jane Smith</td><td data-label="Time">10:45 AM</td><td data-label="Status"><span class="status waiting">Waiting</span></td><td data-label="Action"><button class="action-btn"><i class="fas fa-eye"></i> View</button></td></tr>
                                    <tr><td data-label="Token"><strong>#07</strong></td><td data-label="Patient">Peter Jones</td><td data-label="Time">11:00 AM</td><td data-label="Status"><span class="status waiting">Waiting</span></td><td data-label="Action"><button class="action-btn"><i class="fas fa-eye"></i> View</button></td></tr>
                                    <tr><td data-label="Token"><strong>#04</strong></td><td data-label="Patient">Mary Williams</td><td data-label="Time">10:15 AM</td><td data-label="Status"><span class="status completed">Completed</span></td><td data-label="Action"><button class="action-btn" disabled><i class="fas fa-check"></i> Done</button></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="grid-card">
                             <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                             <div class="quick-actions-container">
                                 <a href="#" class="action-card" id="quick-action-admit"><i class="fas fa-notes-medical"></i><span>Admit Patient</span></a>
                                 <a href="#" class="action-card" id="quick-action-prescribe"><i class="fas fa-file-medical"></i><span>New Prescription</span></a>
                                 <a href="#" class="action-card" id="quick-action-lab"><i class="fas fa-vial"></i><span>Add Lab Result</span></a>
                                 <a href="#" class="action-card"><i class="fas fa-sign-out-alt"></i><span>Initiate Discharge</span></a>
                             </div>
                        </div>
                        <div class="grid-card">
                            <h3><i class="fas fa-bed"></i> Current In-Patients</h3>
                            <table class="data-table">
                                <thead><tr><th>Patient Name</th><th>Room/Bed</th><th>Action</th></tr></thead>
                                <tbody>
                                    <tr><td data-label="Patient Name">Michael Brown</td><td data-label="Patient ID" style="display:none;">P001</td><td data-label="Room/Bed">Room 201-A</td><td data-label="Action"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> Records</button></td></tr>
                                    <tr><td data-label="Patient Name">Emily Davis</td><td data-label="Patient ID" style="display:none;">P003</td><td data-label="Room/Bed">Ward B-05</td><td data-label="Action"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> Records</button></td></tr>
                                    <tr><td data-label="Patient Name">David Wilson</td><td data-label="Patient ID" style="display:none;">P005</td><td data-label="Room/Bed">Room 305-B</td><td data-label="Action"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> Records</button></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div id="appointments-page" class="page appointments-page">
                 <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-calendar-check"></i> Manage Appointments</h3></div>
                    <div class="tabs"><button class="tab-link active" data-tab="today">Today's</button><button class="tab-link" data-tab="upcoming">Upcoming</button><button class="tab-link" data-tab="past">Past</button></div>
                    <div class="filters"><input type="text" class="search-bar" placeholder="Search by patient name..."><select class="status-filter"><option value="all">All Statuses</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="canceled">Canceled</option></select></div>
                    <div id="today-tab" class="appointment-tab active"><div class="appointment-list"><div class="appointment-item"><div class="patient-info"><div class="patient-name">Robert Brown</div><div class="appointment-details">11:30 AM - Regular Checkup</div></div><div class="status confirmed">Confirmed</div><div class="appointment-actions"><button class="action-btn"><i class="fas fa-eye"></i> View Details</button><button class="action-btn danger"><i class="fas fa-times"></i> Cancel</button></div></div></div></div>
                    <div id="upcoming-tab" class="appointment-tab" style="display: none;"></div><div id="past-tab" class="appointment-tab" style="display: none;"></div>
                </div>
            </div>

            <div id="patients-page" class="page">
                <div class="content-panel">
                    <h3 class="page-header"><i class="fas fa-users"></i> My Patients</h3>
                    <div class="filters"><input type="text" id="patient-search" class="search-bar" placeholder="Search by patient name or ID..."><select id="patient-status-filter"><option value="all">All Patients</option><option value="in-patient">In-Patients</option><option value="out-patient">Out-Patients</option></select></div>
                    <div class="table-container">
                        <table class="data-table" id="patients-table">
                            <thead><tr><th>Patient ID</th><th>Name</th><th>Status</th><th>Room/Bed</th><th>Actions</th></tr></thead>
                            <tbody>
                                <tr class="patient-row" data-status="in-patient"><td data-label="Patient ID">P001</td><td data-label="Name">Michael Brown</td><td data-label="Status"><span class="status in-patient">In-Patient</span></td><td data-label="Room/Bed">Room 201-A</td><td data-label="Actions"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> View Records</button></td></tr>
                                <tr class="patient-row" data-status="out-patient"><td data-label="Patient ID">P002</td><td data-label="Name">Sarah Johnson</td><td data-label="Status"><span class="status out-patient">Out-Patient</span></td><td data-label="Room/Bed">N/A</td><td data-label="Actions"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> View Records</button></td></tr>
                                <tr class="patient-row" data-status="in-patient"><td data-label="Patient ID">P003</td><td data-label="Name">Emily Davis</td><td data-label="Status"><span class="status in-patient">In-Patient</span></td><td data-label="Room/Bed">Ward B-05</td><td data-label="Actions"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> View Records</button></td></tr>
                                <tr class="patient-row" data-status="out-patient"><td data-label="Patient ID">P004</td><td data-label="Name">Chris Lee</td><td data-label="Status"><span class="status out-patient">Out-Patient</span></td><td data-label="Room/Bed">N/A</td><td data-label="Actions"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> View Records</button></td></tr>
                                <tr class="patient-row" data-status="in-patient"><td data-label="Patient ID">P005</td><td data-label="Name">David Wilson</td><td data-label="Status"><span class="status in-patient">In-Patient</span></td><td data-label="Room/Bed">Room 305-B</td><td data-label="Actions"><button class="action-btn view-records-btn"><i class="fas fa-folder-open"></i> View Records</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="prescriptions-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-file-prescription"></i> Manage Prescriptions</h3><button class="btn btn-primary" id="create-prescription-btn"><i class="fas fa-plus"></i> Create New Prescription</button></div>
                    <div class="filters"><input type="text" id="prescription-search" class="search-bar" placeholder="Search by Patient or Rx ID..."><input type="date" id="prescription-date-filter"></div>
                    <div class="table-container">
                        <table class="data-table" id="prescriptions-table">
                            <thead><tr><th>Rx ID</th><th>Patient</th><th>Date Issued</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <tr class="prescription-row"><td data-label="Rx ID">RX78901</td><td data-label="Patient">Michael Brown</td><td data-label="Date Issued">2025-07-25</td><td data-label="Status"><span class="status filled">Filled</span></td><td data-label="Actions"><button class="action-btn view-prescription-btn"><i class="fas fa-eye"></i> View</button></td></tr>
                                <tr class="prescription-row"><td data-label="Rx ID">RX78902</td><td data-label="Patient">Sarah Johnson</td><td data-label="Date Issued">2025-07-24</td><td data-label="Status"><span class="status pending">Pending</span></td><td data-label="Actions"><button class="action-btn view-prescription-btn"><i class="fas fa-eye"></i> View</button></td></tr>
                                <tr class="prescription-row"><td data-label="Rx ID">RX78903</td><td data-label="Patient">Chris Lee</td><td data-label="Date Issued">2025-07-22</td><td data-label="Status"><span class="status filled">Filled</span></td><td data-label="Actions"><button class="action-btn view-prescription-btn"><i class="fas fa-eye"></i> View</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="admissions-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-procedures"></i> Manage Admissions</h3><button class="btn btn-primary" id="admit-patient-btn"><i class="fas fa-plus"></i> Admit New Patient</button></div>
                    <div class="filters"><input type="text" id="admissions-search" class="search-bar" placeholder="Search by Patient Name or ID..."></div>
                    <div class="table-container">
                        <table class="data-table" id="admissions-table">
                            <thead><tr><th>Adm. ID</th><th>Patient Name</th><th>Room/Bed</th><th>Adm. Date</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <tr class="admission-row"><td data-label="Adm. ID">ADM001</td><td data-label="Patient Name">Michael Brown</td><td data-label="Room/Bed">Room 201-A</td><td data-label="Adm. Date">2025-07-24</td><td data-label="Status"><span class="status admitted">Admitted</span></td><td data-label="Actions"><button class="action-btn"><i class="fas fa-eye"></i> View</button><button class="action-btn danger initiate-discharge-btn"><i class="fas fa-sign-out-alt"></i> Initiate Discharge</button></td></tr>
                                <tr class="admission-row"><td data-label="Adm. ID">ADM002</td><td data-label="Patient Name">Emily Davis</td><td data-label="Room/Bed">Ward B-05</td><td data-label="Adm. Date">2025-07-23</td><td data-label="Status"><span class="status admitted">Admitted</span></td><td data-label="Actions"><button class="action-btn"><i class="fas fa-eye"></i> View</button><button class="action-btn danger initiate-discharge-btn"><i class="fas fa-sign-out-alt"></i> Initiate Discharge</button></td></tr>
                                <tr class="admission-row"><td data-label="Adm. ID">ADM003</td><td data-label="Patient Name">David Wilson</td><td data-label="Room/Bed">Room 305-B</td><td data-label="Adm. Date">2025-07-22</td><td data-label="Status"><span class="status admitted">Admitted</span></td><td data-label="Actions"><button class="action-btn"><i class="fas fa-eye"></i> View</button><button class="action-btn danger initiate-discharge-btn"><i class="fas fa-sign-out-alt"></i> Initiate Discharge</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="bed-management-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-bed-pulse"></i> Bed Management Overview</h3></div>
                    <div class="filters">
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
                        <div class="loading-placeholder">
                            <i class="fas fa-spinner fa-spin"></i> Loading bed data...
                        </div>
                    </div>
                </div>
            </div>


            <div id="messenger-page" class="page">
                <div class="page-header"><h3><i class="fas fa-paper-plane"></i> Messenger</h3></div>
                <div class="messenger-layout">
                    <div class="conversation-list">
                        <div class="conversation-search">
                            <input type="text" placeholder="Search users...">
                        </div>
                        <div class="conversation-item active" data-user-id="user1" data-user-name="Dr. James Smith">
                            <i class="fas fa-user-doctor user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">Dr. James Smith</div>
                                <div class="last-message">Yes, I'll review the new lab results.</div>
                            </div>
                            <div class="message-meta">
                                <div class="message-time">8:10 PM</div>
                                <span class="unread-indicator"></span>
                            </div>
                        </div>
                        <div class="conversation-item" data-user-id="user2" data-user-name="Alice (Admin)">
                            <i class="fas fa-user-shield user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">Alice (Admin)</div>
                                <div class="last-message">The staff meeting is scheduled for...</div>
                            </div>
                            <div class="message-meta">
                                <div class="message-time">7:45 PM</div>
                            </div>
                        </div>
                        <div class="conversation-item" data-user-id="user3" data-user-name="Nurse John (Staff)">
                            <i class="fas fa-user-nurse user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">Nurse John (Staff)</div>
                                <div class="last-message">Patient in Room 201-A is stable.</div>
                            </div>
                             <div class="message-meta">
                                <div class="message-time">Yesterday</div>
                            </div>
                        </div>
                    </div>
                    <div class="chat-window">
                        <div class="chat-header">
                            <span id="chat-with-user">Dr. James Smith</span>
                        </div>
                        <div class="chat-messages" id="chat-messages-container">
                            <div class="message received">
                                <div class="message-content">
                                    <p>Hi Dr. Carter, can you please check on Michael Brown's latest ECG report?</p>
                                    <span class="message-timestamp">8:08 PM</span>
                                </div>
                            </div>
                            <div class="message sent">
                                <div class="message-content">
                                    <p>Of course, Dr. Smith. I'm looking at it now. The results seem normal.</p>
                                    <span class="message-timestamp">8:09 PM</span>
                                </div>
                            </div>
                             <div class="message received">
                                <div class="message-content">
                                    <p>Great, thank you. Also, please review the new lab results when you have a moment.</p>
                                    <span class="message-timestamp">8:09 PM</span>
                                </div>
                            </div>
                            <div class="message sent">
                                <div class="message-content">
                                    <p>Yes, I'll review the new lab results.</p>
                                    <span class="message-timestamp">8:10 PM</span>
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
                            <option value="announcement">Announcements</option>
                            <option value="lab">Lab Results</option>
                            <option value="discharge">Discharge Updates</option>
                        </select>
                    </div>
                    <div class="notification-list-container">
                        <div class="notification-list-item unread" data-type="lab">
                            <div class="item-icon-wrapper"><i class="fas fa-vial item-icon vial"></i></div>
                            <div class="item-content">
                                <p>New lab result for <strong>Sarah Johnson</strong> is available for viewing.</p>
                                <small>5 minutes ago</small>
                            </div>
                        </div>
                        <div class="notification-list-item unread" data-type="announcement">
                            <div class="item-icon-wrapper"><i class="fas fa-bullhorn item-icon announcement"></i></div>
                            <div class="item-content">
                                <p><strong>Admin Announcement:</strong> System maintenance is scheduled for 10 PM tonight. Brief downtime expected.</p>
                                <small>1 hour ago</small>
                            </div>
                        </div>
                        <div class="notification-list-item unread" data-type="discharge">
                            <div class="item-icon-wrapper"><i class="fas fa-sign-out-alt item-icon discharge"></i></div>
                            <div class="item-content">
                                <p>Discharge process for <strong>David Wilson</strong> has been completed by billing. Patient is ready for physical discharge.</p>
                                <small>3 hours ago</small>
                            </div>
                        </div>
                        <div class="notification-list-item read" data-type="lab">
                            <div class="item-icon-wrapper"><i class="fas fa-vial item-icon vial"></i></div>
                            <div class="item-content">
                                <p>New lab result for <strong>Michael Brown</strong> is available for viewing.</p>
                                <small>Yesterday</small>
                            </div>
                        </div>
                        <div class="notification-list-item read" data-type="discharge">
                            <div class="item-icon-wrapper"><i class="fas fa-sign-out-alt item-icon discharge"></i></div>
                            <div class="item-content">
                                <p>Discharge process for <strong>Laura White</strong> has moved to the next stage: Pending Billing.</p>
                                <small>2 days ago</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="discharge-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-sign-out-alt"></i> Discharge Requests</h3></div>
                    <div class="filters"><input type="text" id="discharge-search" class="search-bar" placeholder="Search by Patient Name or Req ID..."><select id="discharge-status-filter"><option value="all">All Statuses</option><option value="pending">Pending</option><option value="ready">Ready for Discharge</option><option value="completed">Completed</option></select></div>
                    <div class="table-container">
                        <table class="data-table" id="discharge-requests-table">
                            <thead><tr><th>Req. ID</th><th>Patient</th><th>Room/Bed</th><th>Initiated</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <tr class="discharge-row" data-status="pending"><td data-label="Req. ID">D4501</td><td data-label="Patient">Michael Brown</td><td data-label="Room/Bed">Room 201-A</td><td data-label="Initiated">2025-07-25</td><td data-label="Status"><span class="status pending-clearance">Pending Nursing</span></td><td data-label="Actions"><button class="action-btn view-discharge-status"><i class="fas fa-tasks"></i> View Status</button></td></tr>
                                <tr class="discharge-row" data-status="pending"><td data-label="Req. ID">D4502</td><td data-label="Patient">Emily Davis</td><td data-label="Room/Bed">Ward B-05</td><td data-label="Initiated">2025-07-25</td><td data-label="Status"><span class="status pending-billing">Pending Billing</span></td><td data-label="Actions"><button class="action-btn view-discharge-status"><i class="fas fa-tasks"></i> View Status</button></td></tr>
                                <tr class="discharge-row" data-status="ready"><td data-label="Req. ID">D4503</td><td data-label="Patient">David Wilson</td><td data-label="Room/Bed">Room 305-B</td><td data-label="Initiated">2025-07-24</td><td data-label="Status"><span class="status ready-discharge">Ready for Discharge</span></td><td data-label="Actions"><button class="action-btn view-discharge-status"><i class="fas fa-tasks"></i> View Status</button></td></tr>
                                <tr class="discharge-row" data-status="completed"><td data-label="Req. ID">D4498</td><td data-label="Patient">Laura White</td><td data-label="Room/Bed">Room 102-A</td><td data-label="Initiated">2025-07-23</td><td data-label="Status"><span class="status completed">Discharged</span></td><td data-label="Actions"><button class="action-btn"><i class="fas fa-file-pdf"></i> View Summary</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="labs-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-vials"></i> Lab Results</h3>
                        <button class="btn btn-primary" id="add-lab-result-btn"><i class="fas fa-plus"></i> Add Lab Result</button>
                    </div>
                    <div class="filters">
                        <input type="text" id="lab-search" class="search-bar" placeholder="Search by Patient or Test...">
                        <select id="lab-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="lab-results-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Patient</th>
                                    <th>Test Name</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="lab-row" data-status="completed">
                                    <td data-label="Report ID">LR7201</td>
                                    <td data-label="Patient">Sarah Johnson</td>
                                    <td data-label="Test Name">Complete Blood Count</td>
                                    <td data-label="Date">2025-07-24</td>
                                    <td data-label="Status"><span class="status completed">Completed</span></td>
                                    <td data-label="Actions"><button class="action-btn view-lab-report"><i class="fas fa-file-alt"></i> View Report</button></td></tr>
                                <tr class="lab-row" data-status="processing">
                                    <td data-label="Report ID">LR7202</td>
                                    <td data-label="Patient">Michael Brown</td>
                                    <td data-label="Test Name">Lipid Profile</td>
                                    <td data-label="Date">2025-07-25</td>
                                    <td data-label="Status"><span class="status processing">Processing</span></td>
                                    <td data-label="Actions"><button class="action-btn" disabled><i class="fas fa-spinner"></i> In Progress</button></td>
                                </tr>
                                <tr class="lab-row" data-status="pending">
                                    <td data-label="Report ID">LR7203</td>
                                    <td data-label="Patient">Chris Lee</td>
                                    <td data-label="Test Name">Thyroid Function Test</td>
                                    <td data-label="Date">2025-07-25</td>
                                    <td data-label="Status"><span class="status pending">Pending</span></td>
                                    <td data-label="Actions"><button class="action-btn add-result-entry"><i class="fas fa-plus-circle"></i> Add Result</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="profile-page" class="page">
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
                                 <img src="../images/doctor-avatar.jpg" alt="Doctor Avatar" class="editable-profile-picture">
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
                                    <label for="profile-id">Doctor ID</label>
                                    <input type="text" id="profile-id" name="display_id" value="<?php echo $display_user_id; ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="profile-email">Email Address</label>
                                    <input type="email" id="profile-email" name="email" value="emily.carter@medsync.com">
                                </div>
                                <div class="form-group">
                                    <label for="profile-phone">Phone Number</label>
                                    <input type="tel" id="profile-phone" name="phone" value="+1-202-555-0186">
                                </div>
                                <div class="form-group full-width">
                                    <label for="profile-specialty">Specialty</label>
                                    <input type="text" id="profile-specialty" name="specialty" value="Cardiology">
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
                            <p class="form-description">Control which email notifications you want to receive from MedSync.</p>
                            <div class="notification-options">
                                <div class="notification-item">
                                    <div class="label-group">
                                        <label for="notif-appointment">Appointment Alerts</label>
                                        <span>For new bookings, changes, and cancellations.</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notif-appointment" name="appointment_alerts" checked>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="notification-item">
                                    <div class="label-group">
                                        <label for="notif-discharge">Discharge Updates</label>
                                        <span>When a discharge you initiated moves to the next stage.</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notif-discharge" name="discharge_updates" checked>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                                <div class="notification-item">
                                    <div class="label-group">
                                        <label for="notif-lab">Lab Result Availability</label>
                                        <span>When new lab results for your patients are ready.</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="notif-lab" name="lab_results">
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
                            <p class="form-description">This is a read-only log of the most recent actions performed on your account.</p>
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
                                            <td data-label="Date & Time">2025-07-25 08:05 PM</td>
                                            <td data-label="Action"><span class="log-action-create">Prescription Issued</span></td>
                                            <td data-label="Target">Michael Brown (P001)</td>
                                            <td data-label="Details">Rx ID: RX78901</td>
                                        </tr>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-25 07:30 PM</td>
                                            <td data-label="Action"><span class="log-action-update">Discharge Initiated</span></td>
                                            <td data-label="Target">Emily Davis (P003)</td>
                                            <td data-label="Details">Request ID: D4502</td>
                                        </tr>
                                         <tr>
                                            <td data-label="Date & Time">2025-07-25 06:15 PM</td>
                                            <td data-label="Action"><span class="log-action-create">Lab Result Added</span></td>
                                            <td data-label="Target">Chris Lee (P004)</td>
                                            <td data-label="Details">Report ID: LR7203</td>
                                        </tr>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-25 04:50 PM</td>
                                            <td data-label="Action"><span class="log-action-view">Viewed Patient Record</span></td>
                                            <td data-label="Target">Sarah Johnson (P002)</td>
                                            <td data-label="Details">Accessed via "My Patients"</td>
                                        </tr>
                                        <tr>
                                            <td data-label="Date & Time">2025-07-25 09:00 AM</td>
                                            <td data-label="Action"><span class="log-action-auth">Logged In</span></td>
                                            <td data-label="Target">Self</td>
                                            <td data-label="Details">IP: 192.168.1.10</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </main>
        
        <div class="modal-overlay" id="prescription-modal-overlay">
            <div class="modal-container">
                <div class="modal-header"><h4>Create New Prescription</h4><button class="modal-close-btn" data-modal-id="prescription-modal-overlay">&times;</button></div>
                <div class="modal-body"><form id="prescription-form"><div class="form-grid"><div class="form-group full-width"><label for="patient-select-presc">Select Patient</label><select id="patient-select-presc" name="patient_id" required><option value="">-- Choose a patient --</option><option value="P001" data-name="Michael Brown">P001 - Michael Brown</option><option value="P002" data-name="Sarah Johnson">P002 - Sarah Johnson</option></select></div><div class="form-group full-width"><label for="medication">Medication Name</label><input type="text" id="medication" name="medication" placeholder="e.g., Amoxicillin" required></div><div class="form-group"><label for="dosage">Dosage</label><input type="text" id="dosage" name="dosage" placeholder="e.g., 500mg" required></div><div class="form-group"><label for="frequency">Frequency</label><input type="text" id="frequency" name="frequency" placeholder="e.g., Twice a day" required></div><div class="form-group full-width"><label for="notes-presc">Notes / Instructions</label><textarea id="notes-presc" name="notes" placeholder="e.g., Take with food."></textarea></div></div></form></div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="prescription-modal-overlay">Cancel</button><button class="btn btn-primary" id="modal-save-btn-presc">Preview Prescription</button></div>
            </div>
        </div>

        <div class="modal-overlay" id="admit-patient-modal-overlay">
            <div class="modal-container">
                <div class="modal-header"><h4>Admit New Patient</h4><button class="modal-close-btn" data-modal-id="admit-patient-modal-overlay">&times;</button></div>
                <div class="modal-body"><form id="admit-patient-form"><div class="form-grid"><div class="form-group full-width"><label for="patient-select-admit">Select Patient</label><select id="patient-select-admit" name="patient_id" required><option value="">-- Choose an existing patient --</option><option value="P002">P002 - Sarah Johnson</option><option value="P004">P004 - Chris Lee</option><option value="P006">P006 - John Doe</option></select></div><div class="form-group full-width"><label for="bed-select-admit">Assign Bed</label><select id="bed-select-admit" name="bed_id" required><option value="">-- Select an available bed --</option><option value="B001" class="bed-available">Room 101-A (Available)</option><option value="B002" class="bed-occupied" disabled>Room 101-B (Occupied)</option><option value="B003" class="bed-available">Ward A-01 (Available)</option><option value="B004" class="bed-cleaning" disabled>Room 202-A (Cleaning)</option><option value="B005" class="bed-available">Room 202-B (Available)</option></select></div><div class="form-group full-width"><label for="admission-notes">Admission Notes</label><textarea id="admission-notes" name="notes" placeholder="Reason for admission, initial observations, etc."></textarea></div></div></form></div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="admit-patient-modal-overlay">Cancel</button><button class="btn btn-primary" id="modal-save-btn-admit">Confirm Admission</button></div>
            </div>
        </div>

        <div class="modal-overlay" id="discharge-status-modal-overlay">
            <div class="modal-container">
                <div class="modal-header"><h4 id="discharge-modal-title">Discharge Status</h4><button class="modal-close-btn" data-modal-id="discharge-status-modal-overlay">&times;</button></div>
                <div class="modal-body"><ul class="timeline"><li class="timeline-item complete"><div class="timeline-icon"><i class="fas fa-user-md"></i></div><div class="timeline-content"><strong>Doctor's Intimation</strong><p>Dr. Emily Carter initiated the discharge. <span>(2025-07-25 10:00 AM)</span></p></div></li><li class="timeline-item complete"><div class="timeline-icon"><i class="fas fa-user-nurse"></i></div><div class="timeline-content"><strong>Nursing Clearance</strong><p>All nursing checks completed by Nurse John. <span>(2025-07-25 10:30 AM)</span></p></div></li><li class="timeline-item complete"><div class="timeline-icon"><i class="fas fa-pills"></i></div><div class="timeline-content"><strong>Pharmacy Clearance</strong><p>Medication returns and billing finalized. <span>(2025-07-25 11:15 AM)</span></p></div></li><li class="timeline-item pending"><div class="timeline-icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="timeline-content"><strong>Bill Settlement</strong><p>Final bill generated. Awaiting payment clearance from accounts.</p></div></li><li class="timeline-item"><div class="timeline-icon"><i class="fas fa-file-alt"></i></div><div class="timeline-content"><strong>Discharge Summary</strong><p>Pending bill settlement.</p></div></li><li class="timeline-item"><div class="timeline-icon"><i class="fas fa-walking"></i></div><div class="timeline-content"><strong>Physical Discharge</strong><p>Pending previous steps.</p></div></li></ul></div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="discharge-status-modal-overlay">Close</button></div>
            </div>
        </div>

        <div class="modal-overlay" id="lab-result-modal-overlay">
            <div class="modal-container">
                <div class="modal-header"><h4 id="lab-modal-title">Add New Lab Result</h4><button class="modal-close-btn" data-modal-id="lab-result-modal-overlay">&times;</button></div>
                <div class="modal-body">
                    <form id="lab-result-form">
                        <div class="form-grid">
                            <div class="form-group"><label for="lab-patient-select">Patient</label><select id="lab-patient-select" required><option value="P004">P004 - Chris Lee</option></select></div>
                            <div class="form-group"><label for="lab-test-name">Test Name</label><input type="text" id="lab-test-name" value="Thyroid Function Test" required></div>
                            <div class="form-group full-width"><label>Key Findings</label><div id="key-findings-container"></div><button type="button" class="action-btn" id="add-finding-btn" style="margin-top: 0.5rem;"><i class="fas fa-plus"></i> Add Finding</button></div>
                            <div class="form-group full-width"><label for="lab-summary">Result Summary</label><textarea id="lab-summary" placeholder="Enter overall summary of the results..."></textarea></div>
                            <div class="form-group full-width"><label for="lab-file-upload">Upload Full Report (PDF)</label><input type="file" id="lab-file-upload" accept=".pdf"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="lab-result-modal-overlay">Cancel</button><button class="btn btn-primary" id="modal-save-btn-lab">Save Result</button></div>
            </div>
        </div>
        
        <div class="modal-overlay" id="lab-report-view-modal-overlay">
            <div class="modal-container" style="max-width: 800px;">
                <div class="modal-header">
                    <h4 id="lab-report-view-title">Lab Report</h4>
                    <div>
                        <button class="btn btn-secondary"><i class="fas fa-print"></i> Print</button>
                        <button class="btn btn-primary"><i class="fas fa-download"></i> Download PDF</button>
                        <button class="modal-close-btn" data-modal-id="lab-report-view-modal-overlay" style="margin-left: 1rem;">&times;</button>
                    </div>
                </div>
                <div class="modal-body" id="lab-report-content">
                    <div class="report-view-header">
                        <div><strong>Patient:</strong> <span id="report-patient-name">Sarah Johnson</span></div>
                        <div><strong>Test:</strong> Complete Blood Count</div>
                        <div><strong>Report ID:</strong> LR7201</div>
                        <div><strong>Date:</strong> 2025-07-24</div>
                    </div>
                    <div class="report-view-body">
                        <h5>Findings:</h5>
                        <table class="findings-table">
                            <thead><tr><th>Parameter</th><th>Result</th><th>Reference Range</th></tr></thead>
                            <tbody>
                                <tr><td>Hemoglobin</td><td>14.5 g/dL</td><td>13.5 - 17.5 g/dL</td></tr>
                                <tr><td>WBC Count</td><td>7,500 /mcL</td><td>4,500 - 11,000 /mcL</td></tr>
                                <tr><td>Platelet Count</td><td>250,000 /mcL</td><td>150,000 - 450,000 /mcL</td></tr>
                            </tbody>
                        </table>
                        <h5>Summary:</h5>
                        <p>All values within normal range. No abnormalities detected.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="medical-record-modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <h4 id="record-modal-title">Patient Medical Record</h4>
                    <button class="modal-close-btn" data-modal-id="medical-record-modal-overlay">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="record-section patient-details-section">
                        <h5>Patient Details</h5>
                        <div class="details-grid">
                            <div><strong>Name:</strong> <span id="record-patient-name">N/A</span></div>
                            <div><strong>Patient ID:</strong> <span id="record-patient-id">N/A</span></div>
                            <div><strong>Age:</strong> 34</div>
                            <div><strong>Gender:</strong> Male</div>
                        </div>
                    </div>
                    <div class="record-section">
                        <h5><i class="fas fa-procedures"></i> Admission History</h5>
                        <table class="record-history-table">
                            <thead><tr><th>Adm. ID</th><th>Date</th><th>Reason</th></tr></thead>
                            <tbody>
                                <tr><td>ADM001</td><td>2025-07-24</td><td>Chest Pain Observation</td></tr>
                                <tr><td>ADM-PREV-098</td><td>2024-11-10</td><td>Routine Check-up</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="record-section">
                        <h5><i class="fas fa-file-prescription"></i> Prescription History</h5>
                        <table class="record-history-table">
                            <thead><tr><th>Rx ID</th><th>Date</th><th>Medication</th><th>Status</th></tr></thead>
                            <tbody>
                                <tr><td>RX78901</td><td>2025-07-25</td><td>Aspirin 81mg</td><td><span class="status filled">Filled</span></td></tr>
                                <tr><td>RX-PREV-451</td><td>2024-11-10</td><td>Lisinopril 10mg</td><td><span class="status completed">Completed</span></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="record-section">
                        <h5><i class="fas fa-vials"></i> Lab Results</h5>
                        <table class="record-history-table">
                            <thead><tr><th>Report ID</th><th>Test</th><th>Date</th><th>Action</th></tr></thead>
                            <tbody>
                                <tr><td>LR7202</td><td>Lipid Profile</td><td>2025-07-25</td><td><button class="action-btn" disabled><i class="fas fa-spinner"></i> In Progress</button></td></tr>
                                <tr>
                                    <td>LR7201</td>
                                    <td>Complete Blood Count</td>
                                    <td>2025-07-24</td>
                                    <td><button class="action-btn view-lab-report" data-patient-name="Michael Brown"><i class="fas fa-file-alt"></i> View</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-modal-id="medical-record-modal-overlay">Close</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="prescription-view-modal-overlay">
            <div class="modal-container prescription-preview-container" id="prescription-to-print">
                <div class="modal-header-print">
                    <h4 class="modal-title-print">Prescription</h4>
                    <button class="modal-close-btn" data-modal-id="prescription-view-modal-overlay">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="rx-header">
                        <div class="rx-hospital-details">
                            <img src="../images/logo.png" alt="MedSync Logo" class="rx-logo">
                            <div>
                                <strong>MedSync Hospital</strong><br>
                                123 Health St, Wellness City<br>
                                medsync.hospital@email.com
                            </div>
                        </div>
                        <div class="rx-doctor-details">
                            <strong>Dr. <?php echo $username; ?></strong><br>
                            Cardiology<br>
                            Reg. No: MS-DOC-12345
                        </div>
                    </div>
                    <div class="rx-patient-details">
                        <div><strong>Patient:</strong> <span id="rx-patient-name"></span></div>
                        <div><strong>Patient ID:</strong> <span id="rx-patient-id"></span></div>
                        <div><strong>Date:</strong> <span id="rx-date"></span></div>
                    </div>
                    <div class="rx-body">
                        <div class="rx-symbol">R<sub>x</sub></div>
                        <div class="rx-medication-area">
                            <table class="rx-medication-table">
                                <tbody id="rx-medication-list">
                                    </tbody>
                            </table>
                            <div class="rx-notes">
                                <strong>Notes:</strong>
                                <p id="rx-notes-content"></p>
                            </div>
                        </div>
                    </div>
                    <div class="rx-signature">
                        <p>Doctor's Signature</p>
                    </div>
                </div>
                <div class="modal-footer-print">
                    <button class="btn btn-secondary" data-modal-id="prescription-view-modal-overlay">Close</button>
                    <button class="btn btn-primary" id="print-prescription-btn"><i class="fas fa-print"></i> Print Prescription</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="edit-bed-modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <h4 id="edit-bed-modal-title">Update Location Status</h4>
                    <button class="modal-close-btn" data-modal-id="edit-bed-modal-overlay">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="edit-bed-form">
                        <input type="hidden" id="edit-location-id" name="id">
                        <input type="hidden" id="edit-location-type" name="type">
                        <p>You are editing location: <strong id="edit-location-identifier-text"></strong></p>
                        <div class="form-group full-width">
                            <label for="edit-location-status-select">New Status</label>
                            <select id="edit-location-status-select" name="status" required>
                                <option value="available">Available</option>
                                <option value="cleaning">Cleaning</option>
                                <option value="reserved">Reserved</option>
                                <option value="occupied" disabled>Occupied (Assign from Admissions)</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-modal-id="edit-bed-modal-overlay">Cancel</button>
                    <button class="btn btn-primary" id="save-location-changes-btn">Save Changes</button>
                </div>
            </div>
        </div>


    </div>
    <script src="script.js"></script>
</body>
</html>