<?php
require_once 'user.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - MedSync</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="apple-touch-icon" sizes="180x180" href="../images/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../images/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../images/favicon/favicon-16x16.png">
    <link rel="manifest" href="../images/favicon/site.webmanifest">

    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="dashboard-container">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="../images/logo.png" alt="MedSync Logo" class="logo">
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
                <a href="../logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
                        </label>
                    </div>
                    <div class="notification-icon-wrapper">
                        <i class="fas fa-bell notification-icon" data-page="notifications"></i>
                        <?php if ($unread_notification_count > 0): ?>
                            <span class="notification-badge"><?php echo $unread_notification_count; ?></span>
                        <?php else: ?>
                            <span class="notification-badge" style="display: none;">0</span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-dropdown-wrapper">
                        <img src="../uploads/profile_pictures/<?php echo $profile_picture; ?>" alt="User Avatar" class="profile-avatar" id="profile-avatar">
                        <div class="profile-dropdown" id="profile-dropdown">
                            <div class="dropdown-header">
                                <h5><?php echo $username; ?></h5>
                                <p><?php echo $display_user_id; ?></p>
                            </div>
                            <a href="#" class="dropdown-item" data-page="profile">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profile</span>
                            </a>
                            <a href="../logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Logout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="page-content">
                <section id="dashboard-page" class="page active">
                    <div class="welcome-banner">
                        <div>
                            <h2 id="welcome-greeting"><?php echo $greeting; ?>, <strong><?php echo $username; ?>!</strong></h2>
                            <p id="welcome-subtext">Here's a summary of your health dashboard.</p>
                        </div>
                        <div class="banner-action">
                            <a href="#" class="btn-primary" data-page="appointments"><i class="fas fa-plus"></i> New Appointment</a>
                        </div>
                    </div>

                    <div class="dashboard-grid">
                        <div class="dashboard-main">
                            <div class="dashboard-panel">
                                <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                                <div id="dashboard-appointments-list">
                                    <div class="skeleton-appointment">
                                        <div class="skeleton-activity-item">
                                            <div class="skeleton-icon"></div>
                                            <div class="skeleton-activity-text">
                                                <div class="skeleton skeleton-text" style="width: 60%;"></div>
                                                <div class="skeleton skeleton-text" style="width: 40%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="skeleton-appointment">
                                        <div class="skeleton-activity-item">
                                            <div class="skeleton-icon"></div>
                                            <div class="skeleton-activity-text">
                                                <div class="skeleton skeleton-text" style="width: 70%;"></div>
                                                <div class="skeleton skeleton-text" style="width: 50%;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="dashboard-appointments-empty" class="empty-state-text" style="display: none;">
                                    <p><i class="fas fa-calendar-check"></i> You have no upcoming appointments. Time to book one!</p>
                                </div>
                            </div>

                            <div class="dashboard-panel">
                                <h3><i class="fas fa-history"></i> Recent Activity</h3>
                                <div id="dashboard-activity-feed">
                                    <div class="skeleton-activity-item">
                                        <div class="skeleton-icon"></div>
                                        <div class="skeleton-activity-text">
                                            <div class="skeleton skeleton-text" style="width: 80%;"></div>
                                            <div class="skeleton skeleton-text" style="width: 30%;"></div>
                                        </div>
                                    </div>
                                    <div class="skeleton-activity-item">
                                        <div class="skeleton-icon"></div>
                                        <div class="skeleton-activity-text">
                                            <div class="skeleton skeleton-text" style="width: 75%;"></div>
                                            <div class="skeleton skeleton-text" style="width: 25%;"></div>
                                        </div>
                                    </div>
                                </div>
                                 <div id="dashboard-activity-empty" class="empty-state-text" style="display: none;">
                                    <p><i class="fas fa-clock"></i> No recent activity to show.</p>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard-side">
                            <div class="dashboard-panel">
                                <h3><i class="fas fa-ticket-alt"></i> Live Token Status</h3>
                                <div id="dashboard-token-widget">
                                    <div class="skeleton-token-card">
                                        <div class="skeleton skeleton-text" style="width: 60%; margin-bottom: 1rem;"></div>
                                        <div class="skeleton-token-body">
                                            <div class="skeleton-token-number">
                                                <div class="skeleton skeleton-text" style="width: 80%; height: 0.8rem;"></div>
                                                <div class="skeleton skeleton-text" style="width: 50%; height: 2rem;"></div>
                                            </div>
                                             <div class="skeleton-token-number">
                                                <div class="skeleton skeleton-text" style="width: 80%; height: 0.8rem;"></div>
                                                <div class="skeleton skeleton-text" style="width: 50%; height: 2rem;"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                 <div id="dashboard-token-empty" class="empty-state-text" style="display: none;">
                                    <p><i class="fas fa-ticket-alt"></i> You have no active tokens for today.</p>
                                </div>
                            </div>

                            <div class="dashboard-panel">
                                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                                <div class="quick-actions-grid">
                                    <a href="#" class="action-box" data-page="appointments"><i class="fas fa-calendar-plus"></i><span>Book Appointment</span></a>
                                    <a href="#" class="action-box" data-page="prescriptions"><i class="fas fa-file-prescription"></i><span>Prescriptions</span></a>
                                    <a href="#" class="action-box" data-page="billing"><i class="fas fa-wallet"></i><span>Billing</span></a>
                                    <a href="#" class="action-box" data-page="labs"><i class="fas fa-vials"></i><span>Lab Results</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="records-page" class="page"></section>
                
                <section id="appointments-page" class="page">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2 class="panel-title-with-icon"><i class="fas fa-calendar-check"></i> My Appointments</h2>
                            <div class="panel-controls">
                                <button id="book-new-appointment-btn" class="btn-primary"><i class="fas fa-plus"></i> Book New Appointment</button>
                            </div>
                        </div>

                        <div class="tabs-wrapper" style="margin-bottom: 1.5rem;">
                            <button class="tab-link active" data-tab="upcoming">Upcoming</button>
                            <button class="tab-link" data-tab="past">Past</button>
                        </div>

                        <div id="upcoming-appointments" class="tab-content active">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Token No.</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="upcoming-appointments-body">
                                        </tbody>
                                </table>
                            </div>
                            <div id="upcoming-empty-state" class="empty-state" style="display: none;">
                                <i class="fas fa-calendar-plus"></i>
                                <h3>No Upcoming Appointments</h3>
                                <p>Click "Book New Appointment" to schedule your next visit.</p>
                            </div>
                        </div>

                        <div id="past-appointments" class="tab-content" style="display: none;">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Doctor</th>
                                            <th>Date & Time</th>
                                            <th>Token No.</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="past-appointments-body">
                                        </tbody>
                                </table>
                            </div>
                            <div id="past-empty-state" class="empty-state" style="display: none;">
                                <i class="fas fa-history"></i>
                                <h3>No Past Appointments</h3>
                                <p>Your completed appointment history will appear here.</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="token-page" class="page">
                    <div class="content-panel">
                        <h2 class="panel-title-with-icon"><i class="fas fa-ticket-alt"></i> Live Token Status</h2>
                        <p class="text-secondary">
                            This page automatically updates every 30 seconds. Here you can track your token for today's appointments.
                        </p>

                        <div id="token-list-container" class="summaries-list">
                            </div>

                        <div id="token-loading-state" style="display: none; text-align: center; padding: 2rem;">
                            <p>Fetching your token status...</p>
                        </div>

                        <div id="token-empty-state" class="empty-state" style="display: none;">
                            <i class="fas fa-ticket-alt"></i>
                            <h3>No Active Tokens</h3>
                            <p>You do not have any appointments with a live token for today.</p>
                        </div>
                    </div>
                </section>
                
                <section id="prescriptions-page" class="page">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2 class="panel-title-with-icon"><i class="fas fa-file-prescription"></i> My Prescriptions</h2>
                            <div class="panel-controls">
                                <input type="month" id="prescription-filter-date" class="form-control-sm">
                                <select id="prescription-filter-status" class="form-control-sm">
                                    <option value="all">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="dispensed">Dispensed</option>
                                    <option value="partial">Partial</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button id="prescription-apply-filters" class="btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                            </div>
                        </div>

                        <div id="prescriptions-list" class="summaries-list">
                            </div>

                        <div id="prescriptions-loading-state" style="display: none; text-align: center; padding: 2rem;">
                            <p>Loading prescriptions...</p>
                        </div>

                        <div id="prescriptions-empty-state" style="display: none; text-align: center; padding: 4rem 2rem;" class="empty-state">
                            <i class="fas fa-file-medical"></i>
                            <h3>No Prescriptions Found</h3>
                            <p>Your medical prescriptions from our doctors will appear here.</p>
                        </div>
                    </div>
                </section>
                
                <section id="billing-page" class="page">
                    <div class="dashboard-grid" style="margin-bottom: 2rem;">
                        <div class="summary-card-financial">
                            <div class="summary-icon bill"><i class="fas fa-file-invoice-dollar"></i></div>
                            <div class="summary-content">
                                <p>Outstanding Balance</p>
                                <h3 id="outstanding-balance">$0.00</h3>
                            </div>
                        </div>
                        <div class="summary-card-financial">
                            <div class="summary-icon paid"><i class="fas fa-check-circle"></i></div>
                            <div class="summary-content">
                                <p>Last Payment Made</p>
                                <h3 id="last-payment-amount">$0.00 on <span id="last-payment-date">N/A</span></h3>
                            </div>
                        </div>
                    </div>

                    <div class="content-panel">
                        <div class="panel-header">
                            <h2 class="panel-title-with-icon"><i class="fas fa-history"></i> Billing History</h2>
                            <div class="panel-controls">
                                <select id="billing-filter-status" class="form-control-sm">
                                    <option value="all">All Statuses</option>
                                    <option value="due">Due</option>
                                    <option value="paid">Paid</option>
                                </select>
                                <input type="month" id="billing-filter-date" class="form-control-sm">
                                <button id="billing-apply-filters" class="btn-primary btn-sm"><i class="fas fa-filter"></i> Apply</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Bill ID</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="billing-table-body">
                                    <tr>
                                        <td data-label="Date">Aug 28, 2025</td>
                                        <td data-label="Bill ID">TXN74652</td>
                                        <td data-label="Description">Consultation with Dr. Carter</td>
                                        <td data-label="Amount"><strong>$50.00</strong></td>
                                        <td data-label="Status"><span class="status due">Due</span></td>
                                        <td data-label="Actions">
                                            <button class="btn-primary btn-sm view-bill-details-btn" data-bill-id="1">Pay Now</button>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td data-label="Date">Aug 20, 2025</td>
                                        <td data-label="Bill ID">TXN74601</td>
                                        <td data-label="Description">Lipid Profile Test</td>
                                        <td data-label="Amount">$75.00</td>
                                        <td data-label="Status"><span class="status paid">Paid</span></td>
                                        <td data-label="Actions">
                                            <button class="btn-secondary btn-sm view-bill-details-btn" data-bill-id="2">View Details</button>
                                            <a href="#" class="action-link" style="margin-left: 10px;"><i class="fas fa-download"></i> Receipt</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div id="billing-empty-state" style="display: none; text-align: center; padding: 2rem;">
                            <p>No billing records found for the selected criteria.</p>
                        </div>
                    </div>
                </section>
                
                <section id="labs-page" class="page">
                    <div class="content-panel">
                        <div class="panel-header">
                            <h2 class="panel-title-with-icon"><i class="fas fa-vials"></i> Lab Results</h2>
                            <div class="panel-controls">
                                <input type="text" id="lab-search-input" class="form-control-sm" placeholder="Search test name...">
                                <input type="month" id="lab-filter-date" class="form-control-sm">
                                <button id="lab-apply-filters" class="btn-primary btn-sm"><i class="fas fa-search"></i> Search</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Test Date</th>
                                        <th>Test Name</th>
                                        <th>Ordering Doctor</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="lab-results-table-body">
                                    </tbody>
                            </table>
                        </div>
                        
                        <div id="lab-results-empty-state" style="display: none; text-align: center; padding: 2rem;">
                            <p>No lab results found for the selected criteria.</p>
                        </div>
                        
                        <div id="lab-results-loading-state" style="display: none; text-align: center; padding: 2rem;">
                            <p>Loading lab results...</p>
                        </div>
                    </div>
                </section>
                
                <section id="summaries-page" class="page">
                    <div class="content-panel">
                        <h2 class="panel-title-with-icon"><i class="fas fa-file-alt"></i> Discharge Summaries</h2>
                        <p class="text-secondary">Here you can find the details of your past hospital admissions and download your summary reports.</p>

                        <div class="summaries-list">
                            <div class="summary-card">
                                <div class="summary-card-header">
                                    <div class="summary-icon"><i class="fas fa-hospital-user"></i></div>
                                    <div class="summary-info">
                                        <h4>Admitted: <strong>Jan 15, 2025</strong> - Discharged: <strong>Jan 20, 2025</strong></h4>
                                        <p>Admitting Physician: <strong>Dr. Emily Carter</strong> (Cardiology)</p>
                                    </div>
                                </div>
                                <div class="summary-card-actions">
                                    <button class="btn-secondary btn-sm toggle-details-btn"><i class="fas fa-eye"></i> View Details</button>
                                    <a href="#" class="btn-primary btn-sm"><i class="fas fa-file-pdf"></i> Download Summary</a>
                                </div>
                                <div class="summary-details">
                                    <hr class="section-divider">
                                    <h5><i class="fas fa-notes-medical"></i> Follow-up Instructions</h5>
                                    <ul>
                                        <li>Rest for one week. Avoid strenuous activity and lifting heavy objects.</li>
                                        <li>Follow a low-sodium diet as discussed.</li>
                                        <li>Follow-up appointment scheduled with Dr. Carter on <strong>Feb 05, 2025 at 10:00 AM</strong>.</li>
                                    </ul>
                                    <h5><i class="fas fa-pills"></i> Medications on Discharge</h5>
                                    <div class="table-responsive">
                                        <table class="data-table compact">
                                           <tbody>
                                                <tr><td>Metoprolol 50mg</td><td>Take one tablet twice daily.</td></tr>
                                                <tr><td>Aspirin 81mg</td><td>Take one tablet every morning.</td></tr>
                                           </tbody>
                                        </table>
                                    </div>
                                    <h5><i class="fas fa-link"></i> Related Records</h5>
                                    <div class="quick-links">
                                        <a href="#" class="action-link" data-page="billing"><i class="fas fa-file-invoice-dollar"></i> View Final Bill</a>
                                        <a href="#" class="action-link" data-page="prescriptions"><i class="fas fa-file-prescription"></i> View Prescriptions</a>
                                        <a href="#" class="action-link" data-page="labs"><i class="fas fa-vials"></i> View Lab Results</a>
                                    </div>
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-card-header">
                                    <div class="summary-icon"><i class="fas fa-hospital-user"></i></div>
                                    <div class="summary-info">
                                        <h4>Admitted: <strong>Nov 02, 2024</strong> - Discharged: <strong>Nov 04, 2024</strong></h4>
                                        <p>Admitting Physician: <strong>Dr. Alan Grant</strong> (General Medicine)</p>
                                    </div>
                                </div>
                                <div class="summary-card-actions">
                                    <button class="btn-secondary btn-sm toggle-details-btn"><i class="fas fa-eye"></i> View Details</button>
                                    <a href="#" class="btn-primary btn-sm"><i class="fas fa-file-pdf"></i> Download Summary</a>
                                </div>
                                <div class="summary-details">
                                    <hr class="section-divider">
                                    <h5><i class="fas fa-notes-medical"></i> Follow-up Instructions</h5>
                                    <p>Details for the November admission...</p>
                                </div>
                            </div>

                            </div>
                    </div>
                </section>
                
                <section id="notifications-page" class="page">
                    <div class="content-panel">
                        <div class="notifications-header">
                            <h2 class="panel-title-with-icon"><i class="fas fa-bell"></i> Notifications</h2>
                            <div class="notifications-controls">
                                <select id="notification-filter" class="form-control-sm">
                                    <option value="all">All Notifications</option>
                                    <option value="unread">Unread Only</option>
                                    <option value="appointments">Appointments</option>
                                    <option value="billing">Billing</option>
                                    <option value="labs">Lab Results</option>
                                    <option value="prescriptions">Prescriptions</option>
                                </select>
                                <button id="mark-all-read-btn" class="btn-secondary btn-sm"><i class="fas fa-check-double"></i> Mark all as read</button>
                            </div>
                        </div>

                        <div class="notifications-list">
                            </div>
                    </div>
                </section>
                <section id="profile-page" class="page">
                    <div class="content-panel">
                        <div class="profile-header">
                            <div class="profile-picture-section">
                                <div class="profile-picture-wrapper">
                                    <img src="../uploads/profile_pictures/<?php echo $profile_picture; ?>" alt="Profile Picture" id="profile-page-avatar">
                                    <label for="avatar-upload" class="profile-picture-edit">
                                        <i class="fas fa-camera"></i><span>Change</span>
                                    </label>
                                </div>
                                <input type="file" id="avatar-upload" hidden accept="image/*">
                                <div class="profile-picture-info">
                                    <h3><?php echo htmlspecialchars($user_details['name'] ?? 'N/A'); ?></h3>
                                    <p><?php echo htmlspecialchars($display_user_id); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="profile-forms-wrapper">
                            <form id="personal-info-form" class="profile-form">
                                <h3 class="form-section-title">Personal Information</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="profile-name">Full Name</label>
                                        <input type="text" id="profile-name" name="name" value="<?php echo htmlspecialchars($user_details['name'] ?? ''); ?>" placeholder="Enter your full name">
                                    </div>
                                    <div class="form-group">
                                        <label for="profile-id">Patient ID</label>
                                        <input type="text" id="profile-id" value="<?php echo htmlspecialchars($display_user_id); ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="profile-email">Email Address</label>
                                        <input type="email" id="profile-email" name="email" value="<?php echo htmlspecialchars($user_details['email'] ?? ''); ?>" placeholder="Enter your email">
                                    </div>
                                    <div class="form-group">
                                        <label for="profile-phone">Phone Number</label>
                                        <input type="tel" id="profile-phone" name="phone" value="<?php echo htmlspecialchars($user_details['phone'] ?? ''); ?>" placeholder="Enter your phone number">
                                    </div>
                                    <div class="form-group">
                                        <label for="profile-dob">Date of Birth</label>
                                        <input type="date" id="profile-dob" name="date_of_birth" value="<?php echo htmlspecialchars($user_details['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="profile-gender">Gender</label>
                                        <select id="profile-gender" name="gender">
                                            <option value="" disabled <?php echo empty($user_details['gender']) ? 'selected' : ''; ?>>Select Gender</option>
                                            <option value="Male" <?php echo ($user_details['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($user_details['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($user_details['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                            </form>

                            <form id="change-password-form" class="profile-form">
                                <h3 class="form-section-title">Change Password</h3>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="current-password">Current Password</label>
                                        <input type="password" id="current-password" name="current_password" placeholder="Enter current password" autocomplete="current-password">
                                    </div>
                                    <div></div> <div class="form-group">
                                        <label for="new-password">New Password</label>
                                        <input type="password" id="new-password" name="new_password" placeholder="Enter new password" autocomplete="new-password">
                                        <div id="password-strength-meter"></div>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm-password">Confirm New Password</label>
                                        <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm new password" autocomplete="new-password">
                                    </div>
                                </div>
                                <button type="submit" class="btn-primary"><i class="fas fa-key"></i> Update Password</button>
                            </form>
                        </div>
                    </div>

                    <div class="content-panel" style="margin-top: 2rem;">
                        <h2 class="panel-title-with-icon"><i class="fas fa-user-shield"></i> Security Settings</h2>
                        <div class="security-option">
                            <h4>Two-Factor Authentication (2FA)</h4>
                            <p class="text-secondary">Add an extra layer of security to your account during login.</p>
                            <button class="btn-secondary" disabled>Enable 2FA (Coming Soon)</button>
                        </div>
                        <hr class="section-divider">
                        <div class="login-history">
                            <h4>Recent Login Activity</h4>
                             <p class="text-secondary">This is a list of devices that have logged into your account.</p>
                            <div class="table-responsive">
                                <table class="data-table compact">
                                    <thead><tr><th>Date & Time</th><th>IP Address</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <tr><td data-label="Date & Time">Aug 29, 2025, 10:15 AM</td><td data-label="IP Address">103.48.196.118</td><td data-label="Status"><span class="status completed">Success</span></td></tr>
                                        <tr><td data-label="Date & Time">Aug 28, 2025, 04:30 PM</td><td data-label="IP Address">202.83.33.22</td><td data-label="Status"><span class="status completed">Success</span></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="content-panel" style="margin-top: 2rem;">
                        <h2 class="panel-title-with-icon"><i class="fas fa-envelope"></i> Notification Preferences</h2>
                        <form id="notification-prefs-form">
                            <p class="text-secondary">Manage the email notifications you receive from MedSync.</p>
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify-appointments" name="notify_appointments" checked>
                                <label for="notify-appointments">Appointment Confirmations & Reminders</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify-billing" name="notify_billing" checked>
                                <label for="notify-billing">New Bills & Payment Confirmations</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify-labs" name="notify_labs">
                                <label for="notify-labs">Lab Result Availability</label>
                            </div>
                            <div class="checkbox-group">
                                <input type="checkbox" id="notify-prescriptions" name="notify_prescriptions" checked>
                                <label for="notify-prescriptions">Prescription Updates</label>
                            </div>
                            <button type="submit" class="btn-primary" style="margin-top: 1.5rem;"><i class="fas fa-save"></i> Save Preferences</button>
                        </form>
                    </div>

                </section>
            </div>
        </main>
    </div>
    
    <div id="booking-modal" class="modal-overlay">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="booking-modal-title">Step 1: Find Your Doctor</h3>
                <button id="booking-modal-close" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="booking-step-1" class="booking-step">
                    <div class="form-grid">
                        <input type="text" id="doctor-search-name" class="form-control-sm" placeholder="Search by Doctor Name...">
                        <select id="doctor-search-specialty" class="form-control-sm">
                            <option value="">Filter by Specialty</option>
                            </select>
                    </div>
                    <div id="doctor-list" style="margin-top: 1.5rem; max-height: 40vh; overflow-y: auto;">
                        </div>
                </div>

                <div id="booking-step-2" class="booking-step" style="display: none;">
                    <p>Selected Doctor: <strong id="selected-doctor-name"></strong></p>
                    <div style="display: flex; flex-wrap: wrap; gap: 2rem;">
                        <div id="datepicker"></div> <div id="slot-list" style="flex-grow: 1;">
                            <h4>Available Slots</h4>
                            <div id="slots-container">
                               </div>
                        </div>
                    </div>
                </div>

                <div id="booking-step-3" class="booking-step" style="display: none;">
                     <p>Doctor: <strong id="token-doctor-name"></strong> | Date: <strong id="token-selected-date"></strong> | Slot: <strong id="token-selected-slot"></strong></p>
                     <div class="token-grid-wrapper">
                        <div class="token-grid-legend">
                            <span><i class="fas fa-square available"></i> Available</span>
                            <span><i class="fas fa-square booked"></i> Booked</span>
                            <span><i class="fas fa-square selected"></i> Your Selection</span>
                        </div>
                        <div id="token-grid" class="token-grid">
                            </div>
                    </div>
                </div>

                <div id="booking-step-4" class="booking-step" style="display: none;">
                    <h4>Confirm Your Appointment</h4>
                    <p>Please review the details below before confirming.</p>
                    <ul style="list-style-type: none; padding-left: 0;">
                        <li style="margin-bottom: 0.5rem;"><strong>Doctor:</strong> <span id="confirm-doctor"></span></li>
                        <li style="margin-bottom: 0.5rem;"><strong>Date:</strong> <span id="confirm-date"></span></li>
                        <li style="margin-bottom: 0.5rem;"><strong>Time Slot:</strong> <span id="confirm-slot"></span></li>
                        <li style="margin-bottom: 0.5rem;"><strong>Your Token Number:</strong> <span id="confirm-token" style="font-size: 1.2rem; font-weight: bold; color: var(--primary-color);"></span></li>
                    </ul>
                </div>

            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between;">
                <button id="booking-back-btn" class="btn-secondary" style="display: none;"><i class="fas fa-arrow-left"></i> Back</button>
                <button id="booking-next-btn" class="btn-primary" disabled>Next <i class="fas fa-arrow-right"></i></button>
                <button id="booking-confirm-btn" class="btn-primary" style="display: none;"><i class="fas fa-check-circle"></i> Confirm Appointment</button>
            </div>
        </div>
    </div>
    
    <div id="bill-details-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bill Details (<span id="modal-bill-id"></span>)</h3>
                <button id="modal-close-btn" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-bill-summary">
                    <p><strong>Patient Name:</strong> <span id="modal-patient-name"></span></p>
                    <p><strong>Bill Date:</strong> <span id="modal-bill-date"></span></p>
                    <p><strong>Status:</strong> <span id="modal-bill-status" class="status"></span></p>
                </div>
                <hr class="section-divider">
                <h4>Itemized Charges</h4>
                <table class="data-table compact">
                    <thead>
                        <tr>
                            <th>Item Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody id="modal-itemized-charges">
                        </tbody>
                </table>
                <div class="modal-total">
                    <strong>Total Amount: <span id="modal-total-amount"></span></strong>
                </div>
            </div>
            <div class="modal-footer" id="modal-payment-section">
                <p>Select a payment method to settle the bill.</p>
                <div class="payment-options">
                    <button class="btn-primary"><i class="fas fa-credit-card"></i> Pay with Card</button>
                    <button class="btn-secondary"><i class="fas fa-qrcode"></i> Pay with UPI</button>
                </div>
            </div>
        </div>
    </div>

    <div id="lab-details-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-lab-test-name">Lab Result Details</h3>
                <button id="modal-lab-close-btn" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="modal-lab-summary">
                    <p><strong>Patient Name:</strong> <span id="modal-lab-patient-name"><?php echo htmlspecialchars($user_details['name'] ?? 'N/A'); ?></span></p>
                    <p><strong>Test Date:</strong> <span id="modal-lab-date"></span></p>
                    <p><strong>Ordering Doctor:</strong> <span id="modal-lab-doctor"></span></p>
                </div>
                <hr class="section-divider">
                <h4>Test Result Details</h4>
                <pre id="modal-lab-result-details"></pre> </div>
            <div class="modal-footer" id="modal-lab-download-section">
                <a href="#" id="modal-lab-download-btn" class="btn-primary" download><i class="fas fa-download"></i> Download Report</a>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>