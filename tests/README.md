# MedSync Security Testing Suite

Comprehensive security testing for all MedSync dashboards (Staff, Admin, Doctor, User).

## ⚠️ SECURITY NOTICE

**DO NOT deploy this directory to production!** These files are for development/testing only.

---

## 📁 Files

```
tests/
├── README.md                      # This comprehensive guide
├── security-tests.html            # Security test suite (Staff dashboard configured)
├── manual-test-checklist.md       # Manual testing + dashboard configs for all 4 dashboards
├── test_email_notification.php    # Email system testing tool
├── check-session.php              # Session diagnostic tool
├── console-tests.js               # Browser console test snippets
└── .htaccess                      # Localhost-only access
```

**Quick Links**:
- **Security Tests**: See manual-test-checklist.md for configs
- **Email Test**: `http://localhost:8080/medsync/tests/test_email_notification.php`

---

## 🚀 Quick Start

### Run Existing Tests (Staff Dashboard)

1. **Security Tests**: `http://localhost:8080/medsync/tests/security-tests.html` (log in first)
2. **Email Test**: `http://localhost:8080/medsync/tests/test_email_notification.php`
3. **Session Check**: `http://localhost:8080/medsync/tests/check-session.php`

### Create Tests for Other Dashboards (5 minutes)

**See `manual-test-checklist.md` Part 1 for complete configurations!**

**Quick Steps**:

1. **Copy file**:
   ```bash
   cp security-tests.html security-tests-admin.html
   ```

2. **Open `DASHBOARD_CONFIGS.md`** and copy the CONFIG for your dashboard (Admin/Doctor/User)

3. **Replace CONFIG** (lines 85-130) in your new file

4. **Update titles** (lines 6, 76, 77):
   ```html
   <title>MedSync Security Tests - Admin Dashboard</title>
   <h1>🔒 Security Test Suite - Admin Dashboard</h1>
   <p class="subtitle">Comprehensive security testing for MedSync Admin Dashboard</p>
   ```

5. **Test it**: Log in → Open test file → Run tests!

---

## 🎯 What Gets Tested

All dashboards test these security features:

| Feature | Description | Why Important |
|---------|-------------|---------------|
| **Rate Limiting** | Max 100 requests/60sec | Prevents API abuse |
| **CSRF Protection** | Token validation on POST | Prevents cross-site attacks |
| **SQL Injection** | Input sanitization | Prevents database attacks |
| **Input Validation** | Whitelist filtering | Prevents malicious input |
| **XSS Prevention** | Script tag filtering | Prevents code injection |
| **Functionality** | Endpoints still work | Security doesn't break features |

---

## 📋 Dashboard Configurations

**Complete ready-to-use configurations are in `DASHBOARD_CONFIGS.md`**

Quick reference for what changes between dashboards:

| Dashboard | API Path | Role(s) | Key Endpoints |
|-----------|----------|---------|---------------|
| **Staff** | `../staff/api.php` | staff, admin | admissions, discharge_requests, billing |
| **Admin** | `../admin/api.php` | admin | get_users, departments, system_logs |
| **Doctor** | `../doctor/api.php` | doctor | my_patients, prescriptions, appointments |
| **User** | `../user/api.php` | user | appointments, lab_results, invoices |

See `DASHBOARD_CONFIGS.md` for complete CONFIG objects with all endpoints.

---

## 🔍 Finding Endpoints in api.php

Look for these patterns in your dashboard's `api.php`:

```php
// GET endpoints
if (isset($_GET['fetch'])) {
    switch ($_GET['fetch']) {
        case 'dashboard_stats':    // ← Add to GET_ENDPOINTS
        case 'users':              // ← Add to GET_ENDPOINTS
    }
}

// POST endpoints
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'updateProfile':      // ← Add to POST_ENDPOINTS
        case 'addUser':            // ← Add to POST_ENDPOINTS
    }
}

// Search endpoints (accept user input)
$query = $_GET['query'];           // ← Add to SEARCH_ENDPOINTS

// Valid status values
$allowed = ['pending', 'done'];    // ← Add to STATUS_FILTERS
```

---

## 🛠️ Troubleshooting

### ❌ "Not logged in" Error
- **Fix**: Log in to the dashboard first in the same browser
- Tests share session cookies with your login

### ❌ "Wrong role" Error  
- **Fix**: Log in with correct account type (admin for admin tests, etc.)

### ❌ "Got HTML instead of JSON" Error
- **Fix**: Session expired - log in again
- Check port number is correct (8080)

### ❌ Tests fail after working before
- **Fix**: Refresh the page to reset rate limit counters

---

## 📊 Why This Design?

**93% code reuse** - Only configuration changes per dashboard:

```
┌─────────────────────────────────────────┐
│ Total Code: 520 lines                   │
├─────────────────────────────────────────┤
│ ✅ Reusable Logic: 485 lines (93%)      │
│ ⚙️  Configuration: 30 lines (6%)        │
│ 📝 Titles: 5 lines (1%)                 │
└─────────────────────────────────────────┘
```

**Result**: Professional security testing with minimal effort!

---

## ✅ Best Practices

1. ✅ Run tests after implementing new API endpoints
2. ✅ Run tests after security changes
3. ✅ Include in code review process
4. ✅ Update CONFIG when adding endpoints
5. ❌ **Never deploy tests/ to production**

---

## 📖 Additional Resources

- **Console Tests**: Copy code from `console-tests.js` to browser DevTools
- **Manual Tests**: Follow checklist in `manual-test-checklist.md`
- **Session Check**: Visit `check-session.php` to verify login status

---

## 🎓 Summary

- ✅ One template works for all dashboards
- ✅ 5-minute setup per dashboard
- ✅ Tests rate limiting, CSRF, SQL injection, input validation
- ✅ Automatic consistency across all tests
- ✅ Easy to maintain and update

**Need help?** Check the inline comments in `security-tests-template.html`
