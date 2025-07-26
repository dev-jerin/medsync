<?php
// Include the backend logic for session management and data retrieval.
require_once 'user/user.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - MedSync</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="apple-touch-icon" sizes="180x180" href="images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="images/favicon/favicon-16x16.png">
    <link rel="manifest" href="images/favicon/site.webmanifest">

    <link rel="stylesheet" href="user/styles.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="images/logo.png" alt="MedSync Logo" class="logo">
                <span class="logo-text">MedSync</span>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-link active" data-page="dashboard"><i class="fas fa-home-alt"></i><span>Dashboard</span></a>
                <a href="#" class="nav-link" data-page="records"><i class="fas fa-file-medical-alt"></i><span>Medical Records</span></a>
                <a href="#" class="nav-link" data-page="appointments"><i class="fas fa-calendar-check"></i><span>Appointments</span></a>
                <a href="#" class="nav-link" data-page="token"><i class="fas fa-ticket-alt"></i><span>Live Token</span></a>
                <a href="#" class="nav-link" data-page="prescriptions"><i class="fas fa-file-prescription"></i><span>Prescriptions</span></a>
                <a href="#" class="nav-link" data-page="labs"><i class="fas fa-vials"></i><span>Lab Results</span></a>
                <a href="#" class="nav-link" data-page="billing"><i class="fas fa-file-invoice-dollar"></i><span>Bills & Payments</span></a>
                <a href="#" class="nav-link" data-page="summaries"><i class="fas fa-file-alt"></i><span>Discharge Summaries</span></a>
                <a href="#" class="nav-link" data-page="notifications"><i class="fas fa-bell"></i><span>Notifications</span></a>
                <a href="#" class="nav-link" data-page="profile"><i class="fas fa-user-cog"></i><span>Profile</span></a>
            </nav>
            <div class="sidebar-footer">
                </div>
        </aside>

        <main class="main-content">
            <header class="main-header">
                <div class="header-left">
                    <button id="menu-toggle" class="menu-toggle"><i class="fas fa-bars"></i></button>
                    <h1 class="header-title" id="header-title">Dashboard</h1>
                </div>
                <div class="header-profile">
                    <span><?php echo $greeting; ?>, <strong><?php echo $username; ?></strong></span>
                    <div class="theme-switch-wrapper">
                        <label class="theme-switch" for="theme-checkbox">
                            <input type="checkbox" id="theme-checkbox" />
<div class="slider round"></div>
                    </div>
                    <i class="fas fa-bell notification-icon" data-page="notifications"></i>
                    <div class="profile-dropdown-wrapper">
                        <img src="images/patient-avatar.png" alt="User Avatar" class="profile-avatar" id="profile-avatar">
                        <div class="profile-dropdown" id="profile-dropdown">
                            <div class="dropdown-header">
                                <h5><?php echo $username; ?></h5>
                                <p><?php echo $display_user_id; ?></p>
                            </div>
                            <a href="#" class="dropdown-item" data-page="profile">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profile</span>
                            </a>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-content">
                <section id="dashboard-page" class="page active">
                    <div class="dashboard-grid">
                        <div class="dashboard-main">
                            <div class="dashboard-panel">
                                <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                                <div class="table-responsive"><table class="data-table compact"><tbody><tr><td>Cardiology with <strong>Dr. Emily Carter</strong></td><td>Tomorrow, 11:00 AM</td><td><a href="#" class="action-link" data-page="appointments">View</a></td></tr><tr><td>General Checkup with <strong>Dr. Alan Grant</strong></td><td>Aug 05, 2025, 02:30 PM</td><td><a href="#" class="action-link" data-page="appointments">View</a></td></tr></tbody></table></div>
                            </div>
                             <div class="dashboard-panel">
                                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                                <ul class="activity-feed"><li class="activity-item"><div class="activity-icon lab"><i class="fas fa-vial"></i></div><p>Your <strong>Lipid Profile</strong> lab result is ready.</p><small>1 day ago</small></li><li class="activity-item"><div class="activity-icon bill"><i class="fas fa-file-invoice-dollar"></i></div><p>You made a payment of <strong>$50.00</strong> for Consultation.</p><small>2 days ago</small></li><li class="activity-item"><div class="activity-icon prescription"><i class="fas fa-pills"></i></div><p>A new prescription was added by <strong>Dr. James Smith</strong>.</p<small>5 days ago</small></li></ul>
                            </div>
                        </div>
                        <div class="dashboard-side">
                             <div class="dashboard-panel">
                                <h3><i class="fas fa-ticket-alt"></i> Live Token Status</h3>
                                <div class="mini-token-card"><h4>Dr. Carter's Clinic</h4><div class="mini-token-body"><div class="mini-token-number"><p>Serving</p><span>#05</span></div><div class="mini-token-divider"></div><div class="mini-token-number your"><p>Your Token</p><span>#08</span></div></div><a href="#" class="btn-primary full-width" data-page="token">View Details</a></div>
                            </div>
                            <div class="dashboard-panel">
                                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                                <div class="quick-actions-grid"><a href="#" class="action-box" data-page="appointments"><i class="fas fa-plus-circle"></i><span>New Appointment</span></a><a href="#" class="action-box" data-page="prescriptions"><i class="fas fa-file-prescription"></i><span>My Prescriptions</span></a><a href="#" class="action-box" data-page="billing"><i class="fas fa-wallet"></i><span>Billing History</span></a><a href="#" class="action-box" data-page="labs"><i class="fas fa-vials"></i><span>Lab Results</span></a></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="records-page" class="page">
                    <div class="content-panel">
                        <h2 class="panel-title-with-icon"><i class="fas fa-file-medical-alt"></i> My Medical Records</h2>
                        <p class="text-secondary">A complete timeline of your health history with MedSync.</p>
                        <div class="medical-timeline">
                            <div class="timeline-item"><div class="timeline-icon appointment"><i class="fas fa-user-md"></i></div><div class="timeline-content"><h4>Appointment with Dr. Emily Carter</h4><p>A routine cardiology checkup was completed.</p><a href="#" class="timeline-link" data-page="appointments">View Appointment</a><span class="timeline-date">July 25, 2025</span></div></div>
                            <div class="timeline-item"><div class="timeline-icon lab"><i class="fas fa-vial"></i></div><div class="timeline-content"><h4>Lab Result Ready</h4><p>Your 'Lipid Profile' test results are now available.</p><a href="#" class="timeline-link" data-page="labs">View Report</a><span class="timeline-date">July 25, 2025</span></div></div>
                            <div class="timeline-item"><div class="timeline-icon prescription"><i class="fas fa-file-prescription"></i></div><div class="timeline-content"><h4>New Prescription Issued</h4><p>Dr. James Smith issued a prescription for 'Isotretinoin 20mg'.</p><a href="#" class="timeline-link" data-page="prescriptions">View Prescription</a><span class="timeline-date">June 15, 2025</span></div></div>
                            <div class="timeline-item"><div class="timeline-icon discharge"><i class="fas fa-hospital-user"></i></div><div class="timeline-content"><h4>Discharged from Hospital</h4><p>You were discharged after successful recovery. Summary is available.</p><a href="#" class="timeline-link" data-page="summaries">Download Summary</a><span class="timeline-date">May 15, 2025</span></div></div>
                        </div>
                    </div>
                </section>

                <section id="appointments-page" class="page">
                    <div class="content-panel">
                        <h2 class="panel-title-with-icon"><i class="fas fa-search"></i> Find a Doctor</h2>
                        <div class="doctor-search-bar advanced"><input type="text" placeholder="Doctor Name"><select><option value="">Any Specialty</option><option value="cardiology">Cardiology</option><option value="dermatology">Dermatology</option><option value="neurology">Neurology</option></select><input type="date"><button class="btn-primary"><i class="fas fa-search"></i> Search Availability</button></div>
                        <div class="doctor-search-results">
                            <div class="doctor-card"><img src="images/doctor-avatar-1.png" alt="Dr. Emily Carter" class="doctor-avatar"><div class="doctor-details"><h4>Dr. Emily Carter</h4><p>Cardiology</p></div><div class="doctor-slots"><p>Available Slots:</p><div class="slots-container"><button class="slot-btn">03:00 PM</button><button class="slot-btn">03:30 PM</button><button class="slot-btn active">04:00 PM</button></div></div><button class="btn-primary">Book Now</button></div>
                            <div class="doctor-card"><img src="images/doctor-avatar-2.png" alt="Dr. Alan Grant" class="doctor-avatar"><div class="doctor-details"><h4>Dr. Alan Grant</h4><p>General Physician</p></div><div class="doctor-slots"><p>Available Slots:</p><div class="slots-container"><button class="slot-btn">01:00 PM</button><button class="slot-btn">01:30 PM</button></div></div><button class="btn-primary">Book Now</button></div>
                        </div>
                        <h2 class="panel-title-with-icon" style="margin-top: 3rem;"><i class="fas fa-calendar-check"></i> My Upcoming Appointments</h2>
                        <div class="table-responsive"><table class="data-table"><thead><tr><th>Doctor</th><th>Date & Time</th><th>Status</th><th>Actions</th></tr></thead><tbody><tr><td data-label="Doctor">Dr. Emily Carter (Cardiology)</td><td data-label="Date & Time">July 27, 2025, 11:00 AM</td><td data-label="Status"><span class="status upcoming">Upcoming</span></td><td data-label="Actions"><button class="btn-danger">Cancel</button></td></tr><tr><td data-label="Doctor">Dr. James Smith (Dermatology)</td><td data-label="Date & Time">June 15, 2025, 09:30 AM</td><td data-label="Status"><span class="status completed">Completed</span></td><td data-label="Actions"><button class="btn-secondary" disabled>View Details</button></td></tr></tbody></table></div>
                    </div>
                </section>
                
                <section id="token-page" class="page"><div class="content-panel"><h2>Live Appointment Token</h2><div class="token-display-wrapper"><div class="token-card"><div class="token-header"><h3>Dr. Emily Carter's Clinic</h3><span>Cardiology</span></div><div class="token-body"><div class="token-number now-serving"><p>Now Serving</p><span>#05</span></div><div class="token-number your-token"><p>Your Token</p><span>#08</span></div></div><div class="token-footer"><p>Estimated Wait: <strong>15 minutes</strong></p><div class="progress-bar"><div class="progress" style="width: 60%;"></div></div></div></div></div></div></section>
                <section id="prescriptions-page" class="page"><div class="content-panel"><h2>My Prescriptions</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Prescription ID</th><th>Doctor</th><th>Date Issued</th><th>Medication</th><th>Action</th></tr></thead><tbody><tr><td data-label="Prescription ID">PRES-0615-001</td><td data-label="Doctor">Dr. James Smith</td><td data-label="Date Issued">June 15, 2025</td><td data-label="Medication">Isotretinoin 20mg</td><td data-label="Action"><button class="btn-primary"><i class="fas fa-download"></i> Download</button></td></tr><tr><td data-label="Prescription ID">PRES-0402-007</td><td data-label="Doctor">Dr. Emily Carter</td><td data-label="Date Issued">April 02, 2025</td><td data-label="Medication">Aspirin 81mg</td><td data-label="Action"><button class="btn-primary"><i class="fas fa-download"></i> Download</button></td></tr></tbody></table></div></div></section>
                <section id="billing-page" class="page"><div class="content-panel"><h2>Bills & Payments</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Bill ID</th><th>Date</th><th>Description</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead><tbody><tr><td data-label="Bill ID">BILL-0725-001</td><td data-label="Date">July 25, 2025</td><td data-label="Description">Consultation Fee - Dr. Carter</td><td data-label="Amount">$50.00</td><td data-label="Status"><span class="status paid">Paid</span></td><td data-label="Action"><button class="btn-secondary"><i class="fas fa-download"></i> Download</button></td></tr><tr><td data-label="Bill ID">BILL-0720-005</td><td data-label="Date">July 20, 2025</td><td data-label="Description">Lab Test - Blood Panel</td><td data-label="Amount">$120.00</td><td data-label="Status"><span class="status due">Due</span></td><td data-label="Action"><button class="btn-primary"><i class="fas fa-credit-card"></i> Pay Now</button></td></tr></tbody></table></div></div></section>
                <section id="labs-page" class="page"><div class="content-panel"><h2>My Lab Results</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Report ID</th><th>Test Name</th><th>Doctor</th><th>Date</th><th>Status</th><th>Action</th></tr></thead><tbody><tr><td data-label="Report ID">LR7202</td><td data-label="Test Name">Lipid Profile</td><td data-label="Doctor">Dr. Emily Carter</td><td data-label="Date">July 25, 2025</td><td data-label="Status"><span class="status ready">Ready</span></td><td data-label="Action"><button class="btn-primary"><i class="fas fa-eye"></i> View Report</button></td></tr><tr><td data-label="Report ID">LR7201</td><td data-label="Test Name">Complete Blood Count</td><td data-label="Doctor">Dr. James Smith</td><td data-label="Date">July 24, 2025</td><td data-label="Status"><span class="status ready">Ready</span></td><td data-label="Action"><button class="btn-primary"><i class="fas fa-eye"></i> View Report</button></td></tr></tbody></table></div></div></section>
                <section id="summaries-page" class="page"><div class="content-panel"><h2>Discharge Summaries</h2><div class="table-responsive"><table class="data-table"><thead><tr><th>Admission ID</th><th>Doctor</th><th>Admission Date</th><th>Discharge Date</th><th>Action</th></tr></thead><tbody><tr><td data-label="Admission ID">ADMIT-0510-003</td><td data-label="Doctor">Dr. Emily Carter</td><td data-label="Admission Date">May 10, 2025</td><td data-label="Discharge Date">May 15, 2025</td><td data-label="Action"><button class="btn-primary"><i class="fas fa-download"></i> Download Summary</button></td></tr></tbody></table></div></div></section>
                <section id="notifications-page" class="page"><div class="content-panel"><h2>Notifications</h2><ul class="notification-list"><li class="notification-item unread"><div class="notification-icon"><i class="fas fa-pills"></i></div><div class="notification-content"><p><strong>Medication Update:</strong> Your prescription from Dr. Smith is ready for pickup.</p><small>5 minutes ago</small></div></li><li class="notification-item"><div class="notification-icon"><i class="fas fa-calendar-check"></i></div><div class="notification-content"><p><strong>Appointment Confirmed:</strong> Your appointment with Dr. Carter for tomorrow at 11:00 AM is confirmed.</p><small>1 day ago</small></div></li><li class="notification-item"><div class="notification-icon"><i class="fas fa-file-invoice-dollar"></i></div><div class="notification-content"><p><strong>Payment Received:</strong> We have received your payment of $120.00 for BILL-0720-005.</p><small>3 days ago</small></div></li></ul></div></section>
                <section id="profile-page" class="page"><div class="content-panel"><h2>Profile Settings</h2><div class="profile-picture-section"><div class="profile-picture-wrapper"><img src="images/patient-avatar.png" alt="Profile Picture" id="profile-page-avatar"><label for="avatar-upload" class="profile-picture-edit"><i class="fas fa-camera"></i><span>Change</span></label></div><input type="file" id="avatar-upload" hidden accept="image/*"><div class="profile-picture-info"><h3><?php echo $username; ?></h3><p><?php echo $display_user_id; ?></p></div></div><form class="profile-form"><h3 class="form-section-title">Personal Information</h3><div class="form-grid"><div class="form-group"><label for="profile-name">Full Name</label><input type="text" id="profile-name" value="<?php echo $username; ?>" placeholder="Enter your full name"></div><div class="form-group"><label for="profile-id">Patient ID</label><input type="text" id="profile-id" value="<?php echo $display_user_id; ?>" readonly></div><div class="form-group"><label for="profile-email">Email Address</label><input type="email" id="profile-email" value="john.doe@example.com" placeholder="Enter your email"></div><div class="form-group"><label for="profile-phone">Phone Number</label><input type="tel" id="profile-phone" value="+1-555-123-4567" placeholder="Enter your phone number"></div></div><h3 class="form-section-title">Change Password</h3><div class="form-grid"><div class="form-group"><label for="current-password">Current Password</label><input type="password" id="current-password" placeholder="Enter current password"></div><div class="form-group"><label for="new-password">New Password</label><input type="password" id="new-password" placeholder="Enter new password"></div></div><button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button></form></div></section>
            </div>
        </main>
    </div>
    
    <script src="user/script.js"></script>
</body>
</html>