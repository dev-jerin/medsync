# Security Testing Guide - All Dashboards

Complete manual testing procedures and automated test configurations for all MedSync dashboards.

---

## 📋 Part 1: Automated Test Configurations

Copy `security-tests.html` and use these configs for each dashboard.

### 🟦 Staff Dashboard (Default)

**File**: `security-tests.html` (already configured)

**Configuration**:
```javascript
const CONFIG = {
    API_BASE: '../staff/api.php',
    DASHBOARD_URL: 'http://localhost:8080/medsync/staff/dashboard.php',
    ALLOWED_ROLES: ['staff', 'admin'],
    
    GET_ENDPOINTS: [
        'dashboard_stats', 'active_doctors', 'callbacks', 'conversations',
        'medicines', 'blood_inventory', 'bed_management_data', 'admissions',
        'lab_orders', 'search_patients', 'discharge_requests', 
        'billable_patients', 'invoices', 'pending_prescriptions'
    ],
    
    POST_ENDPOINTS: [
        'updatePersonalInfo', 'updatePassword', 'processCallback',
        'updateAdmission', 'processDischarge'
    ],
    
    SEARCH_ENDPOINTS: ['search_patients', 'medicines', 'admissions', 'invoices'],
    
    STATUS_FILTERS: {
        'status': ['pending', 'completed', 'cancelled', 'active', 'inactive'],
        'discharge_status': ['pending', 'approved', 'completed']
    }
};
```

**Page Titles**:
```html
<title>MedSync Security Tests - Staff Dashboard</title>
<h1>🔒 Security Test Suite - Staff Dashboard</h1>
```

---

### 🟥 Admin Dashboard

**File**: Create `security-tests-admin.html`

**Configuration**:
```javascript
const CONFIG = {
    API_BASE: '../admin/api.php',
    DASHBOARD_URL: 'http://localhost:8080/medsync/admin/dashboard.php',
    ALLOWED_ROLES: ['admin'],
    
    GET_ENDPOINTS: [
        'dashboard_stats', 'get_users', 'staff', 'doctors', 'patients',
        'departments', 'specialities', 'accommodations', 'medicines',
        'blood_inventory', 'admissions', 'lab_orders', 'invoices',
        'callbacks', 'conversations', 'system_logs', 'settings'
    ],
    
    POST_ENDPOINTS: [
        'updatePersonalInfo', 'updatePassword', 'addUser', 'updateUser',
        'deleteUser', 'addDepartment', 'updateDepartment', 'addSpeciality',
        'updateMedicine', 'addBloodInventory', 'updateAccommodation',
        'processCallback', 'updateSettings'
    ],
    
    SEARCH_ENDPOINTS: ['get_users', 'search_patients', 'medicines', 'admissions', 'invoices'],
    
    STATUS_FILTERS: {
        'status': ['pending', 'completed', 'cancelled', 'active', 'inactive'],
        'role': ['admin', 'staff', 'doctor', 'user'],
        'accommodation_status': ['available', 'occupied', 'cleaning', 'maintenance'],
        'user_status': ['active', 'inactive', 'suspended']
    }
};
```

**Page Titles**:
```html
<title>MedSync Security Tests - Admin Dashboard</title>
<h1>🔒 Security Test Suite - Admin Dashboard</h1>
```

---

### 🟩 Doctor Dashboard

**File**: Create `security-tests-doctor.html`

**Configuration**:
```javascript
const CONFIG = {
    API_BASE: '../doctor/api.php',
    DASHBOARD_URL: 'http://localhost:8080/medsync/doctor/dashboard.php',
    ALLOWED_ROLES: ['doctor'],
    
    GET_ENDPOINTS: [
        'dashboard_stats', 'my_patients', 'appointments', 'today_appointments',
        'prescriptions', 'lab_orders', 'conversations', 'patient_history',
        'medicines', 'my_schedule'
    ],
    
    POST_ENDPOINTS: [
        'updatePersonalInfo', 'updatePassword', 'addPrescription',
        'updatePrescription', 'addLabOrder', 'updateLabOrder',
        'updateAppointment', 'addDiagnosis', 'updatePatientNotes'
    ],
    
    SEARCH_ENDPOINTS: ['search_patients', 'my_patients', 'search_medicines', 'appointments'],
    
    STATUS_FILTERS: {
        'status': ['pending', 'completed', 'cancelled'],
        'appointment_status': ['scheduled', 'completed', 'cancelled', 'no-show'],
        'prescription_status': ['active', 'completed', 'cancelled']
    }
};
```

**Page Titles**:
```html
<title>MedSync Security Tests - Doctor Dashboard</title>
<h1>🔒 Security Test Suite - Doctor Dashboard</h1>
```

---

### 🟨 User/Patient Dashboard

**File**: Create `security-tests-user.html`

**Configuration**:
```javascript
const CONFIG = {
    API_BASE: '../user/api.php',
    DASHBOARD_URL: 'http://localhost:8080/medsync/user/dashboard.php',
    ALLOWED_ROLES: ['user'],
    
    GET_ENDPOINTS: [
        'dashboard_stats', 'appointments', 'upcoming_appointments',
        'prescriptions', 'lab_results', 'medical_records', 'invoices',
        'payment_history', 'doctors', 'my_doctors', 'health_summary'
    ],
    
    POST_ENDPOINTS: [
        'updatePersonalInfo', 'updatePassword', 'bookAppointment',
        'cancelAppointment', 'requestCallback', 'makePayment', 'uploadDocument'
    ],
    
    SEARCH_ENDPOINTS: ['search_doctors', 'search_appointments', 'appointments', 'invoices'],
    
    STATUS_FILTERS: {
        'status': ['upcoming', 'completed', 'cancelled'],
        'appointment_status': ['scheduled', 'completed', 'cancelled'],
        'payment_status': ['pending', 'paid', 'failed', 'refunded']
    }
};
```

**Page Titles**:
```html
<title>MedSync Security Tests - Patient Dashboard</title>
<h1>🔒 Security Test Suite - Patient Dashboard</h1>
```

---

## 📋 Part 2: Manual Testing Procedures

Perform these tests manually for any dashboard.

### 1. Rate Limiting ✓

**Test:** Make rapid API requests
- [ ] Open your dashboard (staff/admin/doctor/user)
- [ ] Open Network tab in DevTools (F12)
- [ ] Refresh page rapidly 100+ times
- [ ] Verify HTTP 429 response appears
- [ ] Verify "Retry-After" header is present
- [ ] Wait 60 seconds and verify access restored

**Expected:** Should be locked out after ~100 requests in 60 seconds

---

### 2. CSRF Token Validation ✓

**Test A: Missing Token**
- [ ] Open DevTools Console
- [ ] Run (replace `[DASHBOARD]` with staff/admin/doctor/user):
```javascript
fetch('[DASHBOARD]/api.php', {
    method: 'POST',
    body: new FormData()
}).then(r => console.log(r.status))
```
- [ ] Verify HTTP 403 response

**Test B: Invalid Token**
- [ ] Run:
```javascript
const fd = new FormData();
fd.append('csrf_token', 'fake_token');
fd.append('action', 'updatePersonalInfo');
fetch('[DASHBOARD]/api.php', {
    method: 'POST',
    body: fd
}).then(r => r.json()).then(console.log)
```
- [ ] Verify HTTP 403 and error message

**Expected:** All POST requests without valid tokens rejected

---

### 3. SQL Injection Prevention ✓

**Test:** Try malicious search inputs in any search field

**Common Test Payloads:**
- [ ] Search: `' OR '1'='1`
- [ ] Search: `'; DROP TABLE users--`
- [ ] Search: `admin' UNION SELECT * FROM passwords--`
- [ ] Search: `1' OR '1'='1--`
- [ ] Search: `'; DELETE FROM admissions--`
- [ ] Search: `' UNION SELECT password FROM users--`

**Where to Test:**
- **Staff**: Billing/Invoices, Patient Search, Admissions
- **Admin**: User Management, Patient Search, System Logs
- **Doctor**: Patient Search, Prescriptions, Appointments
- **User**: Doctor Search, Appointment Search

**Expected:** All dangerous SQL removed, no errors, no data leaks

---

### 4. Input Validation ✓

**Status Filters:**
- [ ] Lab Orders: Use invalid status in URL
  - `?fetch=lab_orders&status=malicious_value`
  - Should fall back to 'all'
  
- [ ] Discharge Requests: Use invalid status
  - `?fetch=discharge_requests&status=<script>alert(1)</script>`
  - Should fall back to 'all'

- [ ] Prescriptions: Use invalid status
  - `?fetch=pending_prescriptions&status=hacker`
  - Should fall back to 'all'

**Expected:** Invalid values sanitized or defaulted

---

### 5. XSS Prevention ✓

**Test:** Try to inject scripts
- [ ] Search: `<script>alert('XSS')</script>`
- [ ] Search: `<img src=x onerror=alert(1)>`
- [ ] Search: `javascript:alert(1)`

**Expected:** Scripts stripped, not executed

---

### 6. Length Limits ✓

**Test:** Send very long inputs
- [ ] Search with 200+ character string
- [ ] Verify truncated to 100 characters
- [ ] Check no performance issues

---

## Functional Tests

### 7. Dashboard ✓

- [ ] Dashboard loads without errors
- [ ] All 4 stat cards display numbers
- [ ] Recent Activity shows
- [ ] Bed Occupancy chart renders
- [ ] Pending Discharges table loads
- [ ] Quick Actions work

---

### 8. Billing Module ✓

- [ ] Can search for billable patients
- [ ] Patient search works with valid names
- [ ] Can generate invoice
- [ ] Invoice amount calculated correctly
- [ ] Can process payment
- [ ] Invoice search works
- [ ] Can view invoice details
- [ ] PDF generation works

---

### 9. User Management ✓

- [ ] User list loads
- [ ] Search by name works
- [ ] Search by ID works
- [ ] Search by email works
- [ ] Can filter by role (user/doctor)
- [ ] Can filter by status (active/inactive)
- [ ] Can add new user
- [ ] Can update user details
- [ ] Can deactivate user
- [ ] Can reactivate user

---

### 10. Inventory ✓

**Medicines:**
- [ ] Medicine list loads
- [ ] Search by name works
- [ ] Can update stock levels
- [ ] Low stock items highlighted

**Blood Inventory:**
- [ ] Blood inventory loads
- [ ] Can update blood stock
- [ ] Low stock threshold works

---

### 11. Bed Management ✓

- [ ] Bed list loads
- [ ] Can filter by ward
- [ ] Can filter by status
- [ ] Can search by patient/bed number
- [ ] Can add new bed
- [ ] Can update bed status
- [ ] Can assign patient to bed
- [ ] Bulk status update works

---

### 12. Admissions ✓

- [ ] Admissions list loads
- [ ] Search by patient name works
- [ ] Search by patient ID works
- [ ] Can view admission details
- [ ] Dates display correctly

---

### 13. Lab Orders ✓

- [ ] Lab orders load
- [ ] Search by patient works
- [ ] Status filter works:
  - [ ] All
  - [ ] Ordered
  - [ ] Pending
  - [ ] Processing
  - [ ] Completed
- [ ] Can view order details
- [ ] Can update status

---

### 14. Pharmacy ✓

- [ ] Pending prescriptions load
- [ ] Search by patient works
- [ ] Status filter works (pending/completed)
- [ ] Can view prescription details
- [ ] Can create pharmacy bill
- [ ] Medicine stock deducted correctly
- [ ] Bill generation works
- [ ] Payment processing works

---

### 15. Discharge Process ✓

- [ ] Discharge requests load
- [ ] Status filter works:
  - [ ] All
  - [ ] Nursing
  - [ ] Pharmacy
  - [ ] Billing
- [ ] Search by patient works
- [ ] Can process nursing clearance
- [ ] Can process pharmacy clearance
- [ ] Can process billing clearance
- [ ] Workflow order enforced (nursing → pharmacy → billing)
- [ ] Auto-discharge on all clearances

---

### 16. Messenger ✓

- [ ] Conversation list loads
- [ ] Can search for users
- [ ] Can start conversation
- [ ] Can send messages
- [ ] Messages display correctly
- [ ] Unread count updates

---

### 17. Email Notifications ✓

**Automated Test Tool**: `http://localhost:8080/medsync/tests/test_email_notification.php`

**Configuration Check:**
- [ ] System email is configured (Admin → Settings)
- [ ] Gmail App Password is set
- [ ] SMTP settings are correct

**Email Template Tests:**
- [ ] Account modification email template generates
- [ ] Lab report ready template generates
- [ ] Discharge notification template generates
- [ ] All templates include correct variables

**Send Email Tests:**
- [ ] Can send test email to configured address
- [ ] Email is received in inbox (check spam folder)
- [ ] Email formatting is correct
- [ ] Links in email work
- [ ] Unsubscribe link works

**Triggered Email Tests:**
- [ ] Account update triggers email
- [ ] Lab report upload triggers email
- [ ] Discharge clearance triggers email
- [ ] Password reset triggers email

**Error Handling:**
- [ ] Invalid email address rejected
- [ ] SMTP errors logged properly
- [ ] Failed emails retried (if configured)

---

### 18. System Notifications ✓

- [ ] Notification bell shows count
- [ ] Can view notifications
- [ ] Can mark as read
- [ ] Can mark all as read

---

### 18. Profile Settings ✓

- [ ] Can view profile
- [ ] Can update name, email, phone, DOB
- [ ] Email validation works
- [ ] Phone validation works (+91 format)
- [ ] DOB validation works
- [ ] Can upload profile picture
- [ ] Can capture photo with webcam
- [ ] Can remove profile picture
- [ ] Can change password
- [ ] Audit log displays

---

## Performance Tests

### 19. Load Times ✓

- [ ] Dashboard loads in < 2 seconds
- [ ] Search results appear in < 1 second
- [ ] Large tables (500+ rows) load smoothly
- [ ] No JavaScript errors in console

---

### 20. Concurrent Operations ✓

- [ ] Two staff can work simultaneously
- [ ] No race conditions on bed assignment
- [ ] No duplicate invoices
- [ ] Discharge clearances don't conflict

---

## Browser Compatibility

### 21. Test in Different Browsers ✓

- [ ] Chrome/Edge (Chromium)
- [ ] Firefox
- [ ] Safari (if available)

---

## Security Audit Summary

After completing all tests:

- [ ] ✅ Rate limiting active
- [ ] ✅ CSRF protection working
- [ ] ✅ SQL injection prevented
- [ ] ✅ XSS prevented
- [ ] ✅ Input validation working
- [ ] ✅ No sensitive data leaks
- [ ] ✅ All functionality working
- [ ] ✅ No console errors
- [ ] ✅ Performance acceptable

---

## 📊 Dashboard Testing Summary

### Quick Reference

| Dashboard | Config File | Test As | Key Security Areas |
|-----------|-------------|---------|-------------------|
| **Staff** | `security-tests.html` | staff/admin | Admissions, discharges, billing |
| **Admin** | `security-tests-admin.html` | admin | User management, system access |
| **Doctor** | `security-tests-doctor.html` | doctor | Patient data, prescriptions |
| **User** | `security-tests-user.html` | user | Personal data, appointments |

### Testing Workflow

1. **Automated Tests** (5 min per dashboard)
   - Copy `security-tests.html` → `security-tests-[dashboard].html`
   - Use CONFIG from Part 1 of this file
   - Update titles (3 lines)
   - Log in → Open test file → Run all tests

2. **Manual Tests** (15 min per dashboard)
   - Follow Part 2 procedures
   - Test all search fields for SQL injection
   - Test all forms for CSRF protection
   - Test status filters for validation
   - Test rate limiting with rapid requests

3. **Verify** (5 min)
   - Check all boxes in Final Verification
   - Document any issues found
   - Sign off

**Total Time**: ~25 minutes per dashboard

---

## 🔍 Finding Your Endpoints

If your actual API endpoints differ from the configs above, search your `api.php`:

**GET Endpoints**:
```php
if (isset($_GET['fetch'])) {
    switch ($_GET['fetch']) {
        case 'dashboard_stats':    // ← Add to GET_ENDPOINTS
        case 'patients':           // ← Add to GET_ENDPOINTS
    }
}
```

**POST Endpoints**:
```php
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'updateProfile':      // ← Add to POST_ENDPOINTS
    }
}
```

**Search Endpoints**:
```php
$search = $_GET['search'];         // ← Add to SEARCH_ENDPOINTS
$query = $_GET['query'];           // ← Add to SEARCH_ENDPOINTS
```

**Status Filters**:
```php
$allowed = ['pending', 'completed'];  // ← Add to STATUS_FILTERS
```

---

## Issues Found

Document any issues discovered during testing:

| Issue | Dashboard | Severity | Status | Notes |
|-------|-----------|----------|--------|-------|
|       |           |          |        |       |

---

## Sign-off

**Tester Name:** _________________  
**Dashboard Tested:** Staff / Admin / Doctor / User  
**Date:** _________________  
**Environment:** Development / Staging  
**Overall Status:** Pass / Fail / Partial  

**Security Tests:**
- [ ] Rate Limiting: PASS / FAIL
- [ ] CSRF Protection: PASS / FAIL  
- [ ] SQL Injection Prevention: PASS / FAIL
- [ ] Input Validation: PASS / FAIL
- [ ] XSS Prevention: PASS / FAIL

**Notes:**
