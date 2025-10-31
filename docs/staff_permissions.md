# Staff User Management Permissions

## Overview
Staff members have **limited permissions** when managing user accounts to ensure system security and prevent unauthorized modifications to sensitive accounts.

---

## ✅ **What Staff CAN Do:**

### 1. **Create New Accounts**
- ✅ Create new **Patient** accounts (for walk-in registrations)
- ✅ Create new **Doctor** accounts (when onboarding medical professionals)
- ✅ Set initial username, password, email, and profile details

### 2. **Edit Patient & Doctor Accounts**
- ✅ Update name, email, phone number, date of birth, gender
- ✅ Modify basic profile information
- ✅ View patient medical history and lab orders
- ✅ Update activity status (active/inactive)

### 3. **Account Status Management**
- ✅ **Deactivate** patient accounts (for no-shows, inactive patients)
- ✅ **Reactivate** previously deactivated patient accounts
- ✅ View account activity logs and last login timestamps

### 4. **Search & Filter**
- ✅ Search users by name, ID, email, or phone
- ✅ Filter by role (Doctors, Patients)
- ✅ Filter by status (Active, Inactive)

---

## ❌ **What Staff CANNOT Do:**

### 1. **Protected Accounts**
- ❌ Edit **Admin** accounts
- ❌ Edit **Staff** accounts (including their own)
- ❌ Deactivate or delete Admin/Staff accounts
- ❌ View passwords of any accounts

### 2. **Security-Sensitive Fields**
- ❌ Change **usernames** (locked after creation)
- ❌ Change **user roles** (Patient ↔ Doctor ↔ Admin transitions blocked)
- ❌ Reset passwords directly (users must use "Forgot Password" flow)

### 3. **Account Creation Restrictions**
- ❌ Create new **Admin** accounts
- ❌ Create new **Staff** accounts
- ❌ Assign elevated privileges to users

---

## 🔒 **Security Implementation**

### **Backend (API) Validation**
Located in: `staff/api.php`

```php
// Example: Prevent editing Admin/Staff accounts
if ($target_role !== 'user' && $target_role !== 'doctor') {
    throw new Exception("You are not authorized to edit users with the role '{$target_role}'.");
}
```

**Actions:**
- ✅ Server-side role validation on every update/create request
- ✅ Database transaction rollback on permission violations
- ✅ Activity logging for all user management actions
- ✅ CSRF token validation on all forms

### **Frontend (UI) Controls**
Located in: `staff/script.js`, `staff/dashboard.php`

**Actions:**
- ✅ Role dropdown restricted to "Patient" and "Doctor" only
- ✅ Username field disabled when editing (read-only)
- ✅ Email field disabled when editing (read-only)
- ✅ Role field disabled when editing (read-only)
- ✅ "Protected Account" label shown for Admin/Staff users
- ✅ Deactivate/Reactivate buttons hidden for Admin/Staff
- ✅ Permission denial alerts when attempting unauthorized actions

---

## 📋 **User Management Table Columns**

| Column | Editable by Staff? | Notes |
|--------|-------------------|-------|
| **Photo** | ✅ Yes | Profile picture upload |
| **User ID** | ❌ No | Auto-generated, read-only |
| **Name** | ✅ Yes | Full legal name |
| **Gender** | ✅ Yes | Male/Female/Other |
| **Age/DOB** | ✅ Yes | Date of birth (age calculated) |
| **Phone** | ✅ Yes | Format: +91xxxxxxxxxx |
| **Role** | ❌ No | Locked after creation |
| **Email** | ✅ Yes | Primary contact email |
| **Status** | ✅ Yes | Active/Inactive toggle |
| **Last Active** | ❌ No | System-tracked, read-only |

---

## 🎯 **Permission Rationale**

### **Why These Restrictions?**

1. **Security**
   - Prevents privilege escalation attacks
   - Protects system administrator accounts
   - Maintains audit trail integrity

2. **Data Integrity**
   - Username changes can break authentication
   - Role changes could compromise access control
   - Prevents accidental administrative lockouts

3. **Compliance**
   - HIPAA/medical data protection requirements
   - Audit log accuracy and accountability
   - Separation of duties in healthcare settings

4. **Operational Safety**
   - Prevents staff from deactivating critical accounts
   - Ensures only authorized personnel manage staff access
   - Reduces risk of human error

---

## 🔔 **User Experience Enhancements**

### **Visual Indicators**
- 🟡 **Yellow info banner**: Displays permission scope at top of page
- 🔒 **"Protected Account" label**: Shown instead of action buttons for Admin/Staff
- ⚠️ **Permission denial alerts**: Clear error messages when unauthorized actions attempted
- 🔴 **Disabled fields**: Grayed-out fields that cannot be modified

### **Smart UI Behavior**
- Role dropdown shows only "Patient" and "Doctor" when creating users
- Edit modal automatically locks username, email, and role fields
- Search and filter work across all users (view-only for protected accounts)
- Status toggle buttons hidden for Admin/Staff accounts

---

## 📊 **Activity Logging**

All user management actions are logged in the `activity_logs` table:

| Action | Logged Event | Details |
|--------|--------------|---------|
| Create User | `create_user` | User ID, role, name, display ID |
| Update User | `update_user` | Changed fields, user ID |
| Deactivate | `deactivate_user` | User ID, name, timestamp |
| Reactivate | `reactivate_user` | User ID, name, timestamp |
| Permission Denial | `permission_denied` | Attempted action, target user |

---

## 🛡️ **Best Practices for Staff**

### **DO:**
- ✅ Verify patient identity before creating accounts
- ✅ Use proper phone number format (+91xxxxxxxxxx)
- ✅ Confirm email addresses to prevent typos
- ✅ Deactivate accounts for inactive patients
- ✅ Keep profile information up-to-date

### **DON'T:**
- ❌ Share login credentials with patients
- ❌ Create duplicate accounts for same patient
- ❌ Deactivate accounts without patient consent
- ❌ Modify information without patient verification
- ❌ Attempt to access Admin/Staff account settings

---

## 🔧 **Technical Details**

### **Files Modified**
1. **staff/api.php** - Backend permission validation
2. **staff/script.js** - Frontend permission checks and UI controls
3. **staff/dashboard.php** - Permission info banner and UI structure
4. **staff/styles.css** - Styling for protected account indicators

### **Database Tables Used**
- `users` - User account data
- `roles` - Role definitions and permissions
- `activity_logs` - Action tracking and audit trail
- `doctors` - Doctor-specific information

### **Security Mechanisms**
- Prepared SQL statements (prevents SQL injection)
- CSRF token validation on all forms
- Password hashing (bcrypt)
- Session-based authentication
- Role-based access control (RBAC)

---

## 📞 **Support**

If staff members need to:
- Reset Admin/Staff passwords → Contact System Administrator
- Modify Admin/Staff accounts → Contact System Administrator
- Create Admin/Staff accounts → Contact System Administrator
- Change system permissions → Contact System Administrator

**Emergency Access:** Only Admins can modify system-wide settings and user permissions.

---

**Last Updated:** October 31, 2025  
**Version:** 2.0  
**Author:** MedSync Development Team
