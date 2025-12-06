<?php
/**
 * API Endpoints Test Suite
 * 
 * Tests all API endpoints for each role (admin, doctor, staff, user)
 * Access via: http://localhost/medsync/tests/test_api_endpoints.php
 * 
 * ⚠️ DELETE THIS FILE IN PRODUCTION! It exposes API structure.
 */

require_once __DIR__ . '/../config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HTML Header
?>
<!DOCTYPE html>
<html>
<head>
    <title>MedSync API Endpoints Test</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 1400px; margin: 0 auto; background: #f5f5f5; }
        h1 { color: #007bff; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #007bff; }
        h3 { color: #555; margin-top: 20px; }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        .section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .alert { padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid; }
        .alert-danger { background: #f8d7da; border-color: #dc3545; color: #721c24; }
        .alert-warning { background: #fff3cd; border-color: #ffc107; color: #856404; }
        .alert-success { background: #d4edda; border-color: #28a745; color: #155724; }
        .alert-info { background: #d1ecf1; border-color: #17a2b8; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; font-weight: 600; position: sticky; top: 0; }
        tr:hover { background: #f8f9fa; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; font-size: 0.85rem; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-primary { background: #007bff; color: white; }
        .method-get { background: #28a745; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .method-post { background: #007bff; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .method-put { background: #ffc107; color: #000; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .method-delete { background: #dc3545; color: white; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .endpoint { font-family: 'Courier New', monospace; color: #495057; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card h3 { color: white; margin: 0 0 10px 0; font-size: 2rem; }
        .stat-card p { margin: 0; opacity: 0.9; }
    </style>
</head>
<body>

<h1>🔌 MedSync API Endpoints Test Suite</h1>

<div class="alert alert-warning">
    <strong>⚠️ WARNING:</strong> This file exposes your API structure and available endpoints. 
    <strong>DELETE IT</strong> before deploying to production!
</div>

<?php
$totalEndpoints = 0;
$workingEndpoints = 0;
$failedEndpoints = 0;

// Define expected endpoints for each role
$apiEndpoints = [
    'admin' => [
        // User Management (POST actions)
        ['method' => 'POST', 'action' => 'addUser', 'description' => 'Add new user'],
        ['method' => 'POST', 'action' => 'updateUser', 'description' => 'Update user information'],
        ['method' => 'POST', 'action' => 'deleteUser', 'description' => 'Delete user'],
        ['method' => 'POST', 'action' => 'updateProfile', 'description' => 'Update own profile'],
        ['method' => 'POST', 'action' => 'removeOwnProfilePicture', 'description' => 'Remove own profile picture'],
        ['method' => 'POST', 'action' => 'removeProfilePicture', 'description' => 'Remove user profile picture'],
        
        // Department Management (POST actions)
        ['method' => 'POST', 'action' => 'addDepartment', 'description' => 'Add department'],
        ['method' => 'POST', 'action' => 'updateDepartment', 'description' => 'Update department'],
        ['method' => 'POST', 'action' => 'deleteDepartment', 'description' => 'Delete department (soft)'],
        ['method' => 'POST', 'action' => 'specialities', 'description' => 'Manage specialities'],
        
        // Medicine Management (POST actions)
        ['method' => 'POST', 'action' => 'addMedicine', 'description' => 'Add medicine'],
        ['method' => 'POST', 'action' => 'updateMedicine', 'description' => 'Update medicine'],
        ['method' => 'POST', 'action' => 'deleteMedicine', 'description' => 'Delete medicine'],
        
        // Blood Inventory (POST actions)
        ['method' => 'POST', 'action' => 'updateBlood', 'description' => 'Update blood inventory'],
        
        // Ward Management (POST actions)
        ['method' => 'POST', 'action' => 'addWard', 'description' => 'Add ward'],
        ['method' => 'POST', 'action' => 'updateWard', 'description' => 'Update ward'],
        ['method' => 'POST', 'action' => 'deleteWard', 'description' => 'Delete ward'],
        
        // Accommodation Management (POST actions)
        ['method' => 'POST', 'action' => 'addAccommodation', 'description' => 'Add accommodation (bed/room)'],
        ['method' => 'POST', 'action' => 'updateAccommodation', 'description' => 'Update accommodation'],
        ['method' => 'POST', 'action' => 'deleteAccommodation', 'description' => 'Delete accommodation'],
        
        // Doctor Schedule (POST actions)
        ['method' => 'POST', 'action' => 'update_doctor_schedule', 'description' => 'Update doctor schedule'],
        
        // Staff Shifts (POST actions)
        ['method' => 'POST', 'action' => 'update_staff_shift', 'description' => 'Update staff shift'],
        
        // Messaging & Notifications (POST actions)
        ['method' => 'POST', 'action' => 'sendIndividualNotification', 'description' => 'Send individual notification'],
        ['method' => 'POST', 'action' => 'sendMessage', 'description' => 'Send message'],
        ['method' => 'POST', 'action' => 'sendNotification', 'description' => 'Send notification'],
        ['method' => 'POST', 'action' => 'mark_notifications_read', 'description' => 'Mark notifications as read'],
        ['method' => 'POST', 'action' => 'dismiss_all_notifications', 'description' => 'Dismiss all notifications'],
        
        // System Settings (POST actions)
        ['method' => 'POST', 'action' => 'updateSystemSettings', 'description' => 'Update system settings'],
        
        // Reports & Downloads (POST actions)
        ['method' => 'POST', 'action' => 'download_pdf', 'description' => 'Download PDF report'],
        
        // IP Blocking (POST actions)
        ['method' => 'POST', 'action' => 'blockIp', 'description' => 'Block IP address'],
        ['method' => 'POST', 'action' => 'unblockIp', 'description' => 'Unblock IP address'],
        ['method' => 'POST', 'action' => 'updateIpName', 'description' => 'Update IP name'],
        
        // System Settings (GET fetch)
        ['method' => 'GET', 'action' => 'get_system_settings', 'description' => 'Get system settings'],
        
        // Doctors & Accommodations (GET fetch)
        ['method' => 'GET', 'action' => 'active_doctors', 'description' => 'Get active doctors'],
        ['method' => 'GET', 'action' => 'available_accommodations', 'description' => 'Get available accommodations'],
        ['method' => 'GET', 'action' => 'unassigned_patients', 'description' => 'Get unassigned patients'],
        
        // Appointments (GET fetch)
        ['method' => 'GET', 'action' => 'appointments', 'description' => 'Get all appointments'],
        
        // User Management (GET fetch)
        ['method' => 'GET', 'action' => 'users', 'description' => 'Get all users'],
        ['method' => 'GET', 'action' => 'user_details', 'description' => 'Get user details'],
        ['method' => 'GET', 'action' => 'search_users', 'description' => 'Search users'],
        
        // Doctor Scheduling (GET fetch)
        ['method' => 'GET', 'action' => 'doctors_for_scheduling', 'description' => 'Get doctors for scheduling'],
        ['method' => 'GET', 'action' => 'fetch_doctor_schedule', 'description' => 'Fetch doctor schedule'],
        ['method' => 'GET', 'action' => 'search_doctors', 'description' => 'Search doctors'],
        
        // Staff Management (GET fetch)
        ['method' => 'GET', 'action' => 'staff_for_shifting', 'description' => 'Get staff for shift management'],
        
        // Messaging (GET fetch)
        ['method' => 'GET', 'action' => 'conversations', 'description' => 'Get conversations'],
        ['method' => 'GET', 'action' => 'messages', 'description' => 'Get messages'],
        
        // Department & Speciality (GET fetch)
        ['method' => 'GET', 'action' => 'departments', 'description' => 'Get departments'],
        ['method' => 'GET', 'action' => 'departments_management', 'description' => 'Get departments for management'],
        ['method' => 'GET', 'action' => 'specialities', 'description' => 'Get specialities'],
        
        // Dashboard Stats (GET fetch)
        ['method' => 'GET', 'action' => 'dashboard_stats', 'description' => 'Get dashboard statistics'],
        
        // Profile (GET fetch)
        ['method' => 'GET', 'action' => 'my_profile', 'description' => 'Get own profile'],
        
        // Medicine & Inventory (GET fetch)
        ['method' => 'GET', 'action' => 'medicines', 'description' => 'Get medicines'],
        ['method' => 'GET', 'action' => 'blood_inventory', 'description' => 'Get blood inventory'],
        
        // Ward & Accommodation (GET fetch)
        ['method' => 'GET', 'action' => 'wards', 'description' => 'Get wards'],
        ['method' => 'GET', 'action' => 'accommodations', 'description' => 'Get accommodations'],
        ['method' => 'GET', 'action' => 'patients_for_accommodations', 'description' => 'Get patients for accommodation assignment'],
        
        // Feedback (GET fetch)
        ['method' => 'GET', 'action' => 'feedback_summary', 'description' => 'Get feedback summary'],
        ['method' => 'GET', 'action' => 'feedback_list', 'description' => 'Get feedback list'],
        
        // Reports (GET fetch)
        ['method' => 'GET', 'action' => 'report', 'description' => 'Get report data'],
        
        // Notifications (GET fetch)
        ['method' => 'GET', 'action' => 'all_notifications', 'description' => 'Get all notifications'],
        ['method' => 'GET', 'action' => 'unread_notification_count', 'description' => 'Get unread notification count'],
        
        // Activity Logs (GET fetch)
        ['method' => 'GET', 'action' => 'activity', 'description' => 'Get activity logs'],
        
        // IP Tracking (GET fetch)
        ['method' => 'GET', 'action' => 'getTrackedIps', 'description' => 'Get tracked IP addresses'],
    ],
    
    'doctor' => [
        // POST Actions - Discharge Management
        ['method' => 'POST', 'action' => 'initiate_discharge', 'description' => 'Initiate patient discharge'],
        ['method' => 'POST', 'action' => 'save_discharge_summary', 'description' => 'Save discharge summary'],
        
        // POST Actions - Encounters & Appointments
        ['method' => 'POST', 'action' => 'save_encounter', 'description' => 'Save patient encounter'],
        ['method' => 'POST', 'action' => 'update_token_status', 'description' => 'Update token/appointment status'],
        
        // POST Actions - Admissions
        ['method' => 'POST', 'action' => 'admit_patient', 'description' => 'Admit patient'],
        
        // POST Actions - Bed Occupancy
        ['method' => 'POST', 'action' => 'update_location_status', 'description' => 'Update location status'],
        
        // POST Actions - Prescriptions
        ['method' => 'POST', 'action' => 'add_prescription', 'description' => 'Add prescription'],
        
        // POST Actions - Profile Management
        ['method' => 'POST', 'action' => 'update_personal_info', 'description' => 'Update personal information'],
        ['method' => 'POST', 'action' => 'updatePassword', 'description' => 'Update password'],
        ['method' => 'POST', 'action' => 'updateProfilePicture', 'description' => 'Update profile picture'],
        ['method' => 'POST', 'action' => 'removeProfilePicture', 'description' => 'Remove profile picture'],
        
        // POST Actions - Messenger
        ['method' => 'POST', 'action' => 'delete_message', 'description' => 'Delete message'],
        ['method' => 'POST', 'action' => 'send_message', 'description' => 'Send message'],
        
        // POST Actions - Lab Orders
        ['method' => 'POST', 'action' => 'create_lab_order', 'description' => 'Create lab order'],
        
        // POST Actions - Notifications
        ['method' => 'POST', 'action' => 'mark_all_notifications_read', 'description' => 'Mark all notifications as read'],
        
        // GET Actions - Dropdown & Profile Data
        ['method' => 'GET', 'action' => 'get_specialities', 'description' => 'Get all specialities'],
        ['method' => 'GET', 'action' => 'get_departments', 'description' => 'Get all departments'],
        ['method' => 'GET', 'action' => 'get_doctor_details', 'description' => 'Get doctor details'],
        
        // GET Actions - Dashboard
        ['method' => 'GET', 'action' => 'get_dashboard_data', 'description' => 'Get dashboard statistics and data'],
        
        // GET Actions - Discharge Management
        ['method' => 'GET', 'action' => 'get_discharge_requests', 'description' => 'Get discharge requests'],
        ['method' => 'GET', 'action' => 'get_discharge_status', 'description' => 'Get discharge status'],
        ['method' => 'GET', 'action' => 'get_discharge_summary_details', 'description' => 'Get discharge summary details'],
        
        // GET Actions - Encounters & Appointments
        ['method' => 'GET', 'action' => 'get_encounter_details', 'description' => 'Get encounter details'],
        ['method' => 'GET', 'action' => 'get_appointments', 'description' => 'Get doctor appointments'],
        
        // GET Actions - Admissions
        ['method' => 'GET', 'action' => 'get_admissions', 'description' => 'Get patient admissions'],
        ['method' => 'GET', 'action' => 'get_available_accommodations', 'description' => 'Get available beds/rooms'],
        
        // GET Actions - Bed Occupancy & Locations
        ['method' => 'GET', 'action' => 'get_locations', 'description' => 'Get bed/room locations'],
        ['method' => 'GET', 'action' => 'get_occupancy_data', 'description' => 'Get occupancy statistics'],
        
        // GET Actions - Patients
        ['method' => 'GET', 'action' => 'get_my_patients', 'description' => 'Get doctor\'s patients'],
        ['method' => 'GET', 'action' => 'search_patients', 'description' => 'Search patients'],
        ['method' => 'GET', 'action' => 'get_patients_for_dropdown', 'description' => 'Get patients for dropdown'],
        ['method' => 'GET', 'action' => 'get_patient_medical_record', 'description' => 'Get patient medical record'],
        
        // GET Actions - Prescriptions
        ['method' => 'GET', 'action' => 'get_prescriptions', 'description' => 'Get prescriptions'],
        ['method' => 'GET', 'action' => 'search_medicines', 'description' => 'Search medicines'],
        ['method' => 'GET', 'action' => 'get_prescription_details', 'description' => 'Get prescription details'],
        
        // GET Actions - Audit Logs
        ['method' => 'GET', 'action' => 'get_audit_log', 'description' => 'Get audit logs'],
        
        // GET Actions - Messenger
        ['method' => 'GET', 'action' => 'searchUsers', 'description' => 'Search users for messaging'],
        ['method' => 'GET', 'action' => 'get_conversations', 'description' => 'Get conversations'],
        ['method' => 'GET', 'action' => 'get_messages', 'description' => 'Get messages'],
        ['method' => 'GET', 'action' => 'get_unread_count', 'description' => 'Get unread message count'],
        
        // GET Actions - Lab Orders
        ['method' => 'GET', 'action' => 'get_lab_orders', 'description' => 'Get lab orders'],
        ['method' => 'GET', 'action' => 'get_lab_report_details', 'description' => 'Get lab report details'],
        
        // GET Actions - Notifications
        ['method' => 'GET', 'action' => 'get_notifications', 'description' => 'Get notifications'],
        ['method' => 'GET', 'action' => 'get_unread_notification_count', 'description' => 'Get unread notification count'],
    ],
    
    'staff' => [
        // POST Actions - User Management
        ['method' => 'POST', 'action' => 'addUser', 'description' => 'Add new user'],
        ['method' => 'POST', 'action' => 'updateUser', 'description' => 'Update user information'],
        ['method' => 'POST', 'action' => 'removeUser', 'description' => 'Remove/deactivate user'],
        ['method' => 'POST', 'action' => 'reactivateUser', 'description' => 'Reactivate user'],
        
        // POST Actions - Profile Management
        ['method' => 'POST', 'action' => 'updatePersonalInfo', 'description' => 'Update personal information'],
        ['method' => 'POST', 'action' => 'updatePassword', 'description' => 'Update password'],
        ['method' => 'POST', 'action' => 'updateProfilePicture', 'description' => 'Update profile picture'],
        ['method' => 'POST', 'action' => 'removeProfilePicture', 'description' => 'Remove profile picture'],
        
        // POST Actions - Inventory Management
        ['method' => 'POST', 'action' => 'updateMedicineStock', 'description' => 'Update medicine stock'],
        ['method' => 'POST', 'action' => 'updateBloodStock', 'description' => 'Update blood inventory'],
        
        // POST Actions - Bed Management
        ['method' => 'POST', 'action' => 'addBedOrRoom', 'description' => 'Add bed or room'],
        ['method' => 'POST', 'action' => 'updateBedOrRoom', 'description' => 'Update bed or room'],
        ['method' => 'POST', 'action' => 'bulkUpdateBedStatus', 'description' => 'Bulk update bed status'],
        
        // POST Actions - Lab Orders
        ['method' => 'POST', 'action' => 'addLabOrder', 'description' => 'Add lab order'],
        ['method' => 'POST', 'action' => 'updateLabOrder', 'description' => 'Update lab order'],
        ['method' => 'POST', 'action' => 'removeLabOrder', 'description' => 'Remove lab order'],
        
        // POST Actions - Discharge & Billing
        ['method' => 'POST', 'action' => 'process_clearance', 'description' => 'Process discharge clearance'],
        ['method' => 'POST', 'action' => 'generateInvoice', 'description' => 'Generate invoice'],
        ['method' => 'POST', 'action' => 'processPayment', 'description' => 'Process payment'],
        ['method' => 'POST', 'action' => 'create_pharmacy_bill', 'description' => 'Create pharmacy bill'],
        
        // POST Actions - Callbacks & Messaging
        ['method' => 'POST', 'action' => 'markCallbackContacted', 'description' => 'Mark callback as contacted'],
        ['method' => 'POST', 'action' => 'markNotificationsRead', 'description' => 'Mark notifications as read'],
        ['method' => 'POST', 'action' => 'searchUsers', 'description' => 'Search users for messaging'],
        ['method' => 'POST', 'action' => 'sendMessage', 'description' => 'Send message'],
        
        // GET Actions - Dashboard & Stats
        ['method' => 'GET', 'action' => 'dashboard_stats', 'description' => 'Get dashboard statistics'],
        ['method' => 'GET', 'action' => 'specialities', 'description' => 'Get all specialities'],
        
        // GET Actions - Doctors & Appointments
        ['method' => 'GET', 'action' => 'active_doctors', 'description' => 'Get active doctors'],
        ['method' => 'GET', 'action' => 'fetch_tokens', 'description' => 'Get appointment tokens'],
        
        // GET Actions - Users & Patients
        ['method' => 'GET', 'action' => 'get_users', 'description' => 'Get all users'],
        ['method' => 'GET', 'action' => 'search_patients', 'description' => 'Search patients'],
        
        // GET Actions - Callbacks & Messaging
        ['method' => 'GET', 'action' => 'callbacks', 'description' => 'Get callback requests'],
        ['method' => 'GET', 'action' => 'conversations', 'description' => 'Get conversations'],
        ['method' => 'GET', 'action' => 'messages', 'description' => 'Get messages'],
        
        // GET Actions - Audit & Notifications
        ['method' => 'GET', 'action' => 'audit_log', 'description' => 'Get audit logs'],
        ['method' => 'GET', 'action' => 'notifications', 'description' => 'Get notifications'],
        ['method' => 'GET', 'action' => 'unread_notification_count', 'description' => 'Get unread notification count'],
        
        // GET Actions - Inventory
        ['method' => 'GET', 'action' => 'medicines', 'description' => 'Get medicines'],
        ['method' => 'GET', 'action' => 'blood_inventory', 'description' => 'Get blood inventory'],
        
        // GET Actions - Bed Management
        ['method' => 'GET', 'action' => 'bed_management_data', 'description' => 'Get bed management data'],
        
        // GET Actions - Admissions
        ['method' => 'GET', 'action' => 'admissions', 'description' => 'Get all admissions'],
        
        // GET Actions - Lab Orders
        ['method' => 'GET', 'action' => 'lab_orders', 'description' => 'Get lab orders'],
        ['method' => 'GET', 'action' => 'lab_form_data', 'description' => 'Get lab form data'],
        
        // GET Actions - Discharge Management
        ['method' => 'GET', 'action' => 'discharge_requests', 'description' => 'Get discharge requests'],
        
        // GET Actions - Billing
        ['method' => 'GET', 'action' => 'billable_patients', 'description' => 'Get billable patients'],
        ['method' => 'GET', 'action' => 'invoices', 'description' => 'Get invoices'],
        
        // GET Actions - Prescriptions
        ['method' => 'GET', 'action' => 'pending_prescriptions', 'description' => 'Get pending prescriptions'],
        ['method' => 'GET', 'action' => 'prescription_details', 'description' => 'Get prescription details'],
    ],
    
    'user' => [
        // POST Actions - Appointments
        ['method' => 'POST', 'action' => 'book_appointment', 'description' => 'Book appointment'],
        ['method' => 'POST', 'action' => 'cancel_appointment', 'description' => 'Cancel appointment'],
        
        // POST Actions - Notifications
        ['method' => 'POST', 'action' => 'mark_read', 'description' => 'Mark notification as read'],
        ['method' => 'POST', 'action' => 'mark_all_read', 'description' => 'Mark all notifications as read'],
        
        // POST Actions - Profile Management
        ['method' => 'POST', 'action' => 'update_personal_info', 'description' => 'Update personal information'],
        ['method' => 'POST', 'action' => 'change_password', 'description' => 'Change password'],
        ['method' => 'POST', 'action' => 'update_profile_picture', 'description' => 'Update profile picture'],
        
        // GET Actions - Dashboard
        ['method' => 'GET', 'action' => 'get_dashboard_data', 'description' => 'Get dashboard statistics'],
        
        // GET Actions - Appointments
        ['method' => 'GET', 'action' => 'get_appointments', 'description' => 'Get my appointments'],
        ['method' => 'GET', 'action' => 'get_live_tokens', 'description' => 'Get live appointment tokens'],
        ['method' => 'GET', 'action' => 'get_available_tokens', 'description' => 'Get available tokens for booking'],
        
        // GET Actions - Doctors & Specialties
        ['method' => 'GET', 'action' => 'get_specialties', 'description' => 'Get all specialties'],
        ['method' => 'GET', 'action' => 'get_doctors', 'description' => 'Get available doctors'],
        ['method' => 'GET', 'action' => 'get_doctor_slots', 'description' => 'Get doctor available slots'],
        
        // GET Actions - Medical Records
        ['method' => 'GET', 'action' => 'get_medical_records', 'description' => 'Get my medical records'],
        
        // GET Actions - Lab Results
        ['method' => 'GET', 'action' => 'get_lab_results', 'description' => 'Get my lab results'],
        
        // GET Actions - Discharge Summaries
        ['method' => 'GET', 'action' => 'get_discharge_summaries', 'description' => 'Get my discharge summaries'],
        
        // GET Actions - Billing
        ['method' => 'GET', 'action' => 'get_billing_data', 'description' => 'Get billing data and transactions'],
        
        // GET Actions - Notifications
        ['method' => 'GET', 'action' => 'get_notifications', 'description' => 'Get notifications'],
    ],
];

// Test each role's API
foreach ($apiEndpoints as $role => $endpoints) {
    echo "<div class='section'>";
    echo "<h2>🔐 " . ucfirst($role) . " API Endpoints</h2>";
    
    $apiFile = __DIR__ . "/../{$role}/api.php";
    
    if (file_exists($apiFile)) {
        echo "<p class='success'>✅ API file exists: <code>{$role}/api.php</code></p>";
        
        // Read the API file content
        $apiContent = file_get_contents($apiFile);
        
        echo "<table>";
        echo "<thead><tr><th>Method</th><th>Action</th><th>Description</th><th>Status</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($endpoints as $endpoint) {
            $totalEndpoints++;
            $action = $endpoint['action'];
            $method = $endpoint['method'];
            $description = $endpoint['description'];
            
            // Check if action exists in the API file
            // Pattern 1: case 'action': (used by admin/api.php)
            $pattern1 = '/case\s+[\'"]' . preg_quote($action, '/') . '[\'"]:/';
            // Pattern 2: if ($action == 'action') - with or without additional conditions
            // Matches: if ($action == 'action') or if ($action == 'action' && ...)
            $pattern2 = '/if\s*\(\s*\$action\s*==\s*[\'"]' . preg_quote($action, '/') . '[\'"]/';
            // Pattern 3: elseif ($action == 'action')
            $pattern3 = '/elseif\s*\(\s*\$action\s*==\s*[\'"]' . preg_quote($action, '/') . '[\'"]/';
            
            $exists = preg_match($pattern1, $apiContent) || preg_match($pattern2, $apiContent) || preg_match($pattern3, $apiContent);
            
            echo "<tr>";
            echo "<td><span class='method-" . strtolower($method) . "'>" . $method . "</span></td>";
            echo "<td class='endpoint'>" . $action . "</td>";
            echo "<td>" . $description . "</td>";
            
            if ($exists) {
                echo "<td><span class='badge badge-success'>✓ FOUND</span></td>";
                $workingEndpoints++;
            } else {
                echo "<td><span class='badge badge-danger'>✗ MISSING</span></td>";
                $failedEndpoints++;
            }
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        
    } else {
        echo "<p class='error'>❌ API file not found: <code>{$role}/api.php</code></p>";
        $failedEndpoints += count($endpoints);
        $totalEndpoints += count($endpoints);
    }
    
    echo "</div>";
}

// Summary Statistics
echo "<div class='section'>";
echo "<h2>📊 API Test Summary</h2>";

$percentage = $totalEndpoints > 0 ? round(($workingEndpoints / $totalEndpoints) * 100) : 0;

echo "<div class='stats-grid'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);'>";
echo "<h3>{$totalEndpoints}</h3><p>Total Endpoints</p>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #28a745 0%, #20c997 100%);'>";
echo "<h3>{$workingEndpoints}</h3><p>Found</p>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);'>";
echo "<h3>{$failedEndpoints}</h3><p>Missing</p>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);'>";
echo "<h3>{$percentage}%</h3><p>Coverage</p>";
echo "</div>";
echo "</div>";

if ($percentage >= 80) {
    echo "<div class='alert alert-success'>";
    echo "<h3 style='margin: 0; color: #155724;'>✅ EXCELLENT API COVERAGE</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Most API endpoints are implemented correctly.</p>";
    echo "</div>";
} elseif ($percentage >= 60) {
    echo "<div class='alert alert-warning'>";
    echo "<h3 style='margin: 0; color: #856404;'>⚠️ MODERATE API COVERAGE</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Some API endpoints are missing. Review the tables above.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3 style='margin: 0; color: #721c24;'>❌ LOW API COVERAGE</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Many API endpoints are missing. Implement missing endpoints!</p>";
    echo "</div>";
}

echo "<h3>💡 API Best Practices</h3>";
echo "<ul>";
echo "<li>✓ Always validate user authentication before processing requests</li>";
echo "<li>✓ Use prepared statements to prevent SQL injection</li>";
echo "<li>✓ Return consistent JSON response format: <code>{'success': true/false, 'message': '...', 'data': {...}}</code></li>";
echo "<li>✓ Implement proper error handling with try-catch blocks</li>";
echo "<li>✓ Validate and sanitize all input parameters</li>";
echo "<li>✓ Use appropriate HTTP methods (GET for read, POST for create/update)</li>";
echo "<li>✓ Log important actions to activity_logs table</li>";
echo "<li>✓ Set proper headers: <code>header('Content-Type: application/json');</code></li>";
echo "<li>✓ Implement rate limiting to prevent abuse</li>";
echo "<li>✓ <strong>DELETE THIS TEST FILE</strong> before production</li>";
echo "</ul>";

echo "</div>";
?>

</body>
</html>
