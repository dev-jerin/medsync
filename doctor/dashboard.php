<?php
// Include the backend logic for session management and data retrieval.
// This file now prepares $full_name, $username, $email, $phone, $gender, $date_of_birth, etc.
require_once 'api.php';
$profile_picture_path = "../uploads/profile_pictures/" . $profile_picture;
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
                    <li><a href="#" class="nav-link" data-page="labs"><i class="fas fa-vials"></i> Lab Orders</a></li>
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
                            <span class="notification-badge hidden" id="notification-badge">0</span>
                        </button>
                        <div class="notification-dropdown" id="notification-panel">
                            <div class="dropdown-header">
                                <h4>Recent Notifications</h4>
                            </div>
                            <div class="dropdown-body">
                                </div>
                            <div class="dropdown-footer">
                                <a href="#" id="view-all-notifications-link">View All Notifications</a>
                            </div>
                        </div>
                    </div>
                    <div class="user-profile-widget" id="user-profile-widget">
                        <img src="<?php echo $profile_picture_path; ?>" alt="Doctor Avatar" class="profile-picture">
                        <div class="profile-info">
                            <strong>Dr. <?php echo $full_name; ?></strong>
                            <span><?php echo $specialty ?: 'Specialty not set'; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div id="dashboard-page" class="page active">
                <div class="content-panel">
                    <div class="welcome-message">
                        <h2>Welcome back, Dr. <?php echo $full_name; ?>!</h2>
                        <p>Here’s a summary of your activities and patient status for today. Stay organized and efficient.</p>
                    </div>
                    <div class="stat-cards-container">
                        <div class="stat-card appointments">
                            <div class="icon"><i class="fas fa-calendar-check"></i></div>
                            <div class="info">
                                <div class="value" id="stat-appointments-value">--</div>
                                <div class="label">Today's Appointments</div>
                            </div>
                        </div>
                        <div class="stat-card admissions">
                            <div class="icon"><i class="fas fa-procedures"></i></div>
                            <div class="info">
                                <div class="value" id="stat-admissions-value">--</div>
                                <div class="label">Active Admissions</div>
                            </div>
                        </div>
                        <div class="stat-card discharges">
                            <div class="icon"><i class="fas fa-walking"></i></div>
                            <div class="info">
                                <div class="value" id="stat-discharges-value">--</div>
                                <div class="label">Pending Discharges</div>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-grid">
                        <div class="grid-card" style="grid-column: 1 / -1;">
                            <h3><i class="fas fa-user-clock"></i> Today's Appointment Queue</h3>
                            <table class="data-table">
                                <thead><tr><th>Token</th><th>Patient Name</th><th>Time</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody id="dashboard-appointments-tbody">
                                    </tbody>
                            </table>
                        </div>
                        <div class="grid-card">
                             <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                            <div class="quick-actions-container">
                                <a href="#" class="action-card" id="quick-action-admit"><i class="fas fa-notes-medical"></i><span>Admit Patient</span></a>
                                <a href="#" class="action-card" id="quick-action-prescribe"><i class="fas fa-file-medical"></i><span>New Prescription</span></a>
                                <a href="#" class="action-card" id="quick-action-lab"><i class="fas fa-vial"></i><span>Place Lab Order</span></a>
                                <a href="#" class="action-card" id="quick-action-discharge"><i class="fas fa-sign-out-alt"></i><span>Initiate Discharge</span></a>
                            </div>
                        </div>
                        <div class="grid-card">
                            <h3><i class="fas fa-bed"></i> Current In-Patients</h3>
                            <table class="data-table">
                                <thead><tr><th>Patient Name</th><th>Room/Bed</th><th>Action</th></tr></thead>
                                <tbody id="dashboard-inpatients-tbody">
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
                    <div class="filters">
    <input type="text" class="search-bar" placeholder="Search by patient name...">
    <input type="date" id="appointment-date-filter" class="date-filter" > 
    <select class="status-filter"><option value="all">All Statuses</option><option value="confirmed">Confirmed</option><option value="completed">Completed</option><option value="canceled">Canceled</option></select>
</div>
                    <div id="today-tab" class="appointment-tab active">
                        <div class="appointment-list">
                            </div>
                    </div>
                    <div id="upcoming-tab" class="appointment-tab" style="display: none;"></div>
                    <div id="past-tab" class="appointment-tab" style="display: none;"></div>
                </div>
            </div>

            <div id="patients-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-users"></i> My Patients</h3>
                    </div>
                    <div class="filters">
                        <input type="text" id="patient-search" class="search-bar" placeholder="Search by patient name or ID...">
                        <select id="patient-status-filter">
                            <option value="all">All Patients</option>
                            <option value="in-patient">In-Patients</option>
                            <option value="out-patient">Out-Patients</option>
                        </select>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="patients-table">
                            <thead><tr><th>Patient ID</th><th>Name</th><th>Status</th><th>Room/Bed</th><th>Actions</th></tr></thead>
                            <tbody>
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="prescriptions-page" class="page">
                <div class="content-panel">
                    <div class="page-header"><h3><i class="fas fa-file-prescription"></i> Manage Prescriptions</h3><button class="btn btn-primary" id="create-prescription-btn"><i class="fas fa-plus"></i> Create New Prescription</button></div>
                    <div class="filters"><input type="text" id="prescription-search" class="search-bar" placeholder="Search by Patient or Rx ID..."><input type="date" id="prescription-date-filter" min="1900-01-01" max="2050-01-01"></div>
                    <div class="table-container">
                        <table class="data-table" id="prescriptions-table">
                            <thead><tr><th>Rx ID</th><th>Patient</th><th>Date Issued</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
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
                            <input type="text" placeholder="Search by name or ID...">
                        </div>
                        <div class="loading-placeholder" style="padding: 2rem; text-align: center;">
                            <i class="fas fa-spinner fa-spin"></i> Loading conversations...
                        </div>
                    </div>
                    <div class="chat-window">
                        <div class="chat-header">
                            <span id="chat-with-user">Select a Conversation</span>
                        </div>
                        <div class="chat-messages" id="chat-messages-container">
                             <div class="message-placeholder" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                Please select a conversation from the left to view messages.
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
                                </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="labs-page" class="page">
                <div class="content-panel">
                    <div class="page-header">
                        <h3><i class="fas fa-vials"></i> Lab Orders</h3>
                        <button class="btn btn-primary" id="place-lab-order-btn"><i class="fas fa-plus"></i> Place New Order</button>
                    </div>
                    <div class="filters">
                        <input type="text" id="lab-search" class="search-bar" placeholder="Search by Patient or Test...">
                        <select id="lab-status-filter">
                            <option value="all">All Statuses</option>
                            <option value="ordered">Ordered</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="lab-orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Patient</th>
                                    <th>Test Name</th>
                                    <th>Order Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
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
                        <button class="profile-tab-link" data-tab="audit-log"><i class="fas fa-history"></i> Audit Log</button>
                    </div>

                    <div id="personal-info-tab" class="profile-tab-content active">
                        <form id="personal-info-form" class="settings-form" novalidate>
                            <h4>Edit Your Personal Details</h4>
                            <div class="profile-picture-editor">
                                 <img src="<?php echo $profile_picture_path; ?>" alt="Doctor Avatar" class="editable-profile-picture">
                                 <label for="profile-picture-upload" class="edit-picture-btn">
                                     <i class="fas fa-camera"></i>
                                     <input type="file" id="profile-picture-upload" accept="image/*">
                                 </label>
                            </div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="profile-name">Full Name</label>
                                    <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <small class="validation-error"></small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-username">Username</label>
                                    <input type="text" id="profile-username" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label for="profile-email">Email Address</label>
                                    <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <small class="validation-error"></small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-phone">Phone Number</label>
                                    <input type="tel" id="profile-phone" name="phone" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" pattern="^\+91\d{10}$" maxlength="13" required>
                                    <small class="validation-error">Format: +91 followed by 10 digits.</small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-dob">Date of Birth</label>
                                    <input type="date" id="profile-dob" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                    <small class="validation-error"></small>
                                </div>
                                <div class="form-group">
                                    <label for="profile-gender">Gender</label>
                                    <select id="profile-gender" name="gender">
                                        <option value="Male" <?php if($gender == 'Male') echo 'selected'; ?>>Male</option>
                                        <option value="Female" <?php if($gender == 'Female') echo 'selected'; ?>>Female</option>
                                        <option value="Other" <?php if($gender == 'Other') echo 'selected'; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="profile-specialty">Specialty</label>
                                    <select id="profile-specialty" name="specialty_id" required>
                                        <option value="">-- Loading Specialties --</option>
                                    </select>
                                    <small class="validation-error"></small>
                                </div>

                                <div class="form-group">
                                    <label for="profile-department">Department</label>
                                    <select id="profile-department" name="department_id">
                                        <option value="">-- Loading Departments --</option>
                                    </select>
                                    <small class="validation-error"></small>
                                </div>

                                <div class="form-group full-width">
                                    <label for="profile-qualifications">Qualifications</label>
                                    <input type="text" id="profile-qualifications" name="qualifications" value="<?php echo htmlspecialchars($qualifications ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="e.g., MBBS, MD, FRCS">
                                    <small class="validation-error"></small>
                                </div>
                                 <div class="form-group">
                                    <label for="profile-id">Doctor ID</label>
                                    <input type="text" id="profile-id" name="display_id" value="<?php echo htmlspecialchars($display_user_id, ENT_QUOTES, 'UTF-8'); ?>" readonly>
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
                <div class="modal-body">
                    <form id="prescription-form">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="patient-select-presc">Select Patient</label>
                                <select id="patient-select-presc" name="patient_id" required>
                                    <option value="">-- Choose a patient --</option>
                                </select>
                            </div>
                        </div>

                        <div id="medication-rows-container">
                            </div>

                        <button type="button" class="btn" id="add-medication-row-btn" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Add Another Medication
                        </button>
                        
                        <div class="form-grid" style="margin-top: 1.5rem;">
                            <div class="form-group full-width">
                                <label for="notes-presc">Notes / Instructions</label>
                                <textarea id="notes-presc" name="notes" placeholder="e.g., Take with food. Complete the full course."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="prescription-modal-overlay">Cancel</button><button type="submit" form="prescription-form" class="btn btn-primary" id="modal-save-btn-presc">Save Prescription</button></div>
            </div>
        </div>

        <div class="modal-overlay" id="admit-patient-modal-overlay">
            <div class="modal-container">
                <div class="modal-header"><h4>Admit New Patient</h4><button class="modal-close-btn" data-modal-id="admit-patient-modal-overlay">&times;</button></div>
                <div class="modal-body"><form id="admit-patient-form"><div class="form-grid"><div class="form-group full-width"><label for="patient-select-admit">Select Patient</label><select id="patient-select-admit" name="patient_id" required><option value="">-- Choose an existing patient --</option></select></div><div class="form-group full-width"><label for="bed-select-admit">Assign Bed</label><select id="bed-select-admit" name="accommodation_id" required><option value="">-- Select an available bed --</option></select></div><div class="form-group full-width"><label for="admission-notes">Admission Notes</label><textarea id="admission-notes" name="notes" placeholder="Reason for admission, initial observations, etc."></textarea></div></div></form></div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="admit-patient-modal-overlay">Cancel</button><button class="btn btn-primary" id="modal-save-btn-admit" type="submit" form="admit-patient-form">Confirm Admission</button></div>
            </div>
        </div>

        <div class="modal-overlay" id="discharge-status-modal-overlay">
            <div class="modal-container">
                <div class="modal-header"><h4 id="discharge-modal-title">Discharge Status</h4><button class="modal-close-btn" data-modal-id="discharge-status-modal-overlay">&times;</button></div>
                <div class="modal-body"><ul class="timeline"></ul></div>
                <div class="modal-footer"><button class="btn btn-secondary" data-modal-id="discharge-status-modal-overlay">Close</button></div>
            </div>
        </div>

        <div class="modal-overlay" id="lab-order-modal-overlay">
            <div class="modal-container">
                <div class="modal-header">
                    <h4>Place New Lab Order</h4>
                    <button class="modal-close-btn" data-modal-id="lab-order-modal-overlay">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="lab-order-form">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="lab-order-patient-select">Select Patient</label>
                                <select id="lab-order-patient-select" name="patient_id" required>
                                    <option value="">-- Choose a patient --</option>
                                </select>
                            </div>
                        </div>

                        <div id="test-rows-container" style="margin-top: 1rem;">
                            </div>

                        <button type="button" class="btn" id="add-test-row-btn" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Add Another Test
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-modal-id="lab-order-modal-overlay">Cancel</button>
                    <button type="submit" form="lab-order-form" class="btn btn-primary">Submit Order</button>
                </div>
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
                        <div><strong>Patient:</strong> <span id="report-patient-name"></span></div>
                        <div><strong>Test:</strong> <span></span></div>
                        <div><strong>Report ID:</strong> <span></span></div>
                        <div><strong>Date:</strong> <span></span></div>
                    </div>
                    <div class="report-view-body">
                        <h5>Findings:</h5>
                        <table class="findings-table">
                            <thead><tr><th>Parameter</th><th>Result</th><th>Reference Range</th></tr></thead>
                            <tbody>
                            </tbody>
                        </table>
                        <h5>Summary:</h5>
                        <p></p>
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
                            <div><strong>Age:</strong> <span id="record-patient-age">N/A</span></div>
                            <div><strong>Gender:</strong> <span id="record-patient-gender">N/A</span></div>
                        </div>
                    </div>
                    <div class="record-section">
                        <h5><i class="fas fa-procedures"></i> Admission History</h5>
                        <table class="record-history-table">
                            <thead><tr><th>Adm. ID</th><th>Date</th><th>Reason</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="record-section">
                        <h5><i class="fas fa-file-prescription"></i> Prescription History</h5>
                        <table class="record-history-table">
                            <thead><tr><th>Rx ID</th><th>Date</th><th>Medication</th><th>Status</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div class="record-section">
                        <h5><i class="fas fa-vials"></i> Lab Results</h5>
                        <table class="record-history-table">
                            <thead><tr><th>Report ID</th><th>Test</th><th>Date</th><th>Action</th></tr></thead>
                            <tbody></tbody>
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
                            <strong>Dr. <?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                            <?php echo htmlspecialchars($specialty, ENT_QUOTES, 'UTF-8'); ?><br>
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
    <script>
        // Pass the session user ID to JavaScript for client-side logic
        const currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>