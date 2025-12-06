<?php
/**
 * Authentication & Authorization Test Suite
 * 
 * Tests login, session management, role-based access control
 * Access via: http://localhost/medsync/tests/test_auth.php
 * 
 * ⚠️ DELETE THIS FILE IN PRODUCTION! It exposes authentication logic.
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
    <title>MedSync Authentication Test</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        h1 { color: #6f42c1; border-bottom: 3px solid #6f42c1; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1; }
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #6f42c1; color: white; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-primary { background: #6f42c1; color: white; }
        .user-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin: 10px 0; }
        .user-card h4 { margin: 0 0 10px 0; }
        .user-card p { margin: 5px 0; opacity: 0.9; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 3px solid #6f42c1; overflow-x: auto; }
        .test-form { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1rem; }
        .btn { padding: 10px 20px; background: #6f42c1; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #5a32a3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>

<h1>🔐 MedSync Authentication Test Suite</h1>

<div class="alert alert-warning">
    <strong>⚠️ WARNING:</strong> This file tests authentication mechanisms and exposes sensitive information. 
    <strong>DELETE IT</strong> before production deployment!
</div>

<?php
$conn = getDbConnection();
$testsPassed = 0;
$totalTests = 0;

// Test 1: Session Status
echo "<div class='section'>";
echo "<h2>Test 1: Session Management</h2>";

$totalTests++;
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p class='success'>✅ Session is active</p>";
    $testsPassed++;
    
    echo "<table>";
    echo "<thead><tr><th>Session Property</th><th>Value</th></tr></thead>";
    echo "<tbody>";
    echo "<tr><td><strong>Session ID</strong></td><td><code>" . session_id() . "</code></td></tr>";
    echo "<tr><td><strong>Session Name</strong></td><td><code>" . session_name() . "</code></td></tr>";
    echo "<tr><td><strong>Session Save Path</strong></td><td><code>" . session_save_path() . "</code></td></tr>";
    echo "</tbody></table>";
    
    if (isset($_SESSION['user_id'])) {
        echo "<div class='user-card'>";
        echo "<h4>👤 Current User Session</h4>";
        echo "<p><strong>User ID:</strong> " . htmlspecialchars($_SESSION['user_id']) . "</p>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email'] ?? 'Not set') . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($_SESSION['role'] ?? 'Not set') . "</p>";
        echo "<p><strong>Name:</strong> " . htmlspecialchars($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'Not set') . "</p>";
        echo "</div>";
        echo "<p class='info'>✓ User is currently logged in</p>";
    } else {
        echo "<p class='info'>ℹ️ No user is currently logged in</p>";
    }
} else {
    echo "<p class='error'>❌ Session is not active</p>";
}

echo "</div>";

// Test 2: User Roles Check
echo "<div class='section'>";
echo "<h2>Test 2: User Roles Configuration</h2>";

// First check if roles table exists
$rolesTableQuery = "SHOW TABLES LIKE 'roles'";
$rolesTableResult = $conn->query($rolesTableQuery);

if ($rolesTableResult && $rolesTableResult->num_rows > 0) {
    echo "<p class='success'>✅ Roles table exists</p>";
    
    // Get all roles from roles table
    $rolesQuery = "SELECT id, role_name FROM roles ORDER BY id";
    $rolesResult = $conn->query($rolesQuery);
    
    if ($rolesResult) {
        echo "<h3>Available Roles</h3>";
        echo "<table>";
        echo "<thead><tr><th>Role ID</th><th>Role Name</th><th>User Count</th></tr></thead>";
        echo "<tbody>";
        
        while ($roleRow = $rolesResult->fetch_assoc()) {
            // Count users for each role
            $countQuery = "SELECT COUNT(*) as count FROM users WHERE role_id = " . $roleRow['id'];
            $countResult = $conn->query($countQuery);
            $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
            
            echo "<tr>";
            echo "<td><strong>" . $roleRow['id'] . "</strong></td>";
            echo "<td><span class='badge badge-info'>" . ucfirst($roleRow['role_name']) . "</span></td>";
            echo "<td>" . $count . " users</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        
        $totalTests++;
        $testsPassed++;
    }
    
    // Check user role distribution
    $userRolesQuery = "SELECT r.role_name, COUNT(u.id) as count 
                       FROM roles r 
                       LEFT JOIN users u ON r.id = u.role_id 
                       GROUP BY r.id, r.role_name";
    $userRolesResult = $conn->query($userRolesQuery);
    
    if ($userRolesResult) {
        $totalTests++;
        $testsPassed++;
        echo "<p class='success'>✅ User role distribution verified</p>";
    }
} else {
    echo "<p class='error'>❌ Roles table not found</p>";
}

echo "</div>";

// Test 3: User Status Check
echo "<div class='section'>";
echo "<h2>Test 3: User Account Status</h2>";

$statusQuery = "SELECT is_active, COUNT(*) as count FROM users GROUP BY is_active";
$statusResult = $conn->query($statusQuery);

if ($statusResult) {
    $totalTests++;
    $testsPassed++;
    
    echo "<table>";
    echo "<thead><tr><th>Status</th><th>User Count</th><th>Description</th></tr></thead>";
    echo "<tbody>";
    
    while ($row = $statusResult->fetch_assoc()) {
        $isActive = $row['is_active'];
        $status = $isActive == 1 ? 'Active' : 'Inactive';
        $badgeClass = $isActive == 1 ? 'badge-success' : 'badge-danger';
        $description = $isActive == 1 ? 'Can access the system' : 'Account disabled';
        
        echo "<tr>";
        echo "<td><span class='badge $badgeClass'>$status</span></td>";
        echo "<td>" . $row['count'] . " users</td>";
        echo "<td>$description</td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<p class='error'>❌ Could not retrieve user status information</p>";
}

echo "</div>";

// Test 4: Password Security Check
echo "<div class='section'>";
echo "<h2>Test 4: Password Security Validation</h2>";

// Get a sample user's password hash
$sampleQuery = "SELECT password FROM users WHERE password IS NOT NULL AND password != '' LIMIT 1";
$sampleResult = $conn->query($sampleQuery);

if ($sampleResult && $sampleResult->num_rows > 0) {
    $totalTests++;
    $row = $sampleResult->fetch_assoc();
    $hash = $row['password'];
    
    // Check hash format
    $info = password_get_info($hash);
    
    echo "<p><strong>Sample Password Hash:</strong></p>";
    echo "<pre>" . substr($hash, 0, 60) . "...</pre>";
    echo "<p><strong>Algorithm:</strong> " . $info['algoName'] . "</p>";
    
    if ($info['algoName'] === 'bcrypt' || $info['algoName'] === 'argon2i' || $info['algoName'] === 'argon2id') {
        echo "<p class='success'>✅ Using secure hashing algorithm: {$info['algoName']}</p>";
        $testsPassed++;
    } else {
        echo "<p class='error'>❌ Using insecure hashing algorithm: {$info['algoName']}</p>";
    }
    
    // Test password verification
    $totalTests++;
    $testPassword = "TestPassword123!";
    $testHash = password_hash($testPassword, PASSWORD_DEFAULT);
    
    if (password_verify($testPassword, $testHash)) {
        echo "<p class='success'>✅ Password verification function working correctly</p>";
        $testsPassed++;
    } else {
        echo "<p class='error'>❌ Password verification not working</p>";
    }
} else {
    echo "<p class='warning'>⚠️ No users with password hashes found</p>";
}

echo "</div>";

// Test 5: Login Attempt Test (Simulation)
echo "<div class='section'>";
echo "<h2>Test 5: Login Authentication Logic</h2>";

echo "<div class='alert alert-info'>";
echo "<strong>ℹ️ Info:</strong> This test simulates login logic without actually logging you in.";
echo "</div>";

// Check if login process file exists
$totalTests++;
$loginFile = __DIR__ . '/../login/login_process.php';
if (file_exists($loginFile)) {
    echo "<p class='success'>✅ Login process file exists: <code>login/login_process.php</code></p>";
    $testsPassed++;
} else {
    echo "<p class='error'>❌ Login process file not found</p>";
}

// Test with a sample email
$testEmail = "test@example.com";
$loginQuery = "SELECT u.id, u.email, u.password, r.role_name, u.is_active, u.name 
               FROM users u 
               LEFT JOIN roles r ON u.role_id = r.id 
               WHERE u.email = ? LIMIT 1";
$stmt = $conn->prepare($loginQuery);

if ($stmt) {
    $totalTests++;
    echo "<p class='success'>✅ Prepared statement for login query created successfully</p>";
    $testsPassed++;
    
    $stmt->bind_param("s", $testEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo "<p class='info'>Test query: <code>SELECT * FROM users WHERE email = '$testEmail'</code></p>";
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<p class='info'>✓ User found with email: $testEmail</p>";
        echo "<p><strong>Status:</strong> " . ($user['is_active'] ? 'Active' : 'Inactive') . "</p>";
        echo "<p><strong>Role:</strong> " . ($user['role_name'] ?? 'Unknown') . "</p>";
    } else {
        echo "<p class='info'>ℹ️ No user found with email: $testEmail (expected for test email)</p>";
    }
    
    $stmt->close();
} else {
    echo "<p class='error'>❌ Could not create prepared statement for login</p>";
}

echo "</div>";

// Test 6: Google OAuth Configuration
echo "<div class='section'>";
echo "<h2>Test 6: Google OAuth Integration</h2>";

$totalTests++;
$googleAuthFile = __DIR__ . '/../auth/google_auth_process.php';
if (file_exists($googleAuthFile)) {
    echo "<p class='success'>✅ Google OAuth file exists</p>";
    $testsPassed++;
} else {
    echo "<p class='error'>❌ Google OAuth file not found</p>";
}

// Check Firebase configuration for Google Sign-In
$totalTests++;
if (isset($_ENV['FIREBASE_API_KEY']) && !empty($_ENV['FIREBASE_API_KEY'])) {
    echo "<p class='success'>✅ Firebase API key configured (required for Google Sign-In)</p>";
    $testsPassed++;
} else {
    echo "<p class='error'>❌ Firebase API key not configured</p>";
}

echo "</div>";

// Test 7: Session Hijacking Protection
echo "<div class='section'>";
echo "<h2>Test 7: Session Security Features</h2>";

$totalTests++;
$sessionHttpOnly = ini_get('session.cookie_httponly');
if ($sessionHttpOnly == '1') {
    echo "<p class='success'>✅ Session cookies are HTTP-only (prevents JavaScript access)</p>";
    $testsPassed++;
} else {
    echo "<p class='error'>❌ Session cookies are NOT HTTP-only (security risk!)</p>";
}

$totalTests++;
$sessionStrictMode = ini_get('session.use_strict_mode');
if ($sessionStrictMode == '1') {
    echo "<p class='success'>✅ Session strict mode enabled (prevents session fixation)</p>";
    $testsPassed++;
} else {
    echo "<p class='warning'>⚠️ Session strict mode disabled (recommended to enable)</p>";
}

// Check if session regeneration is implemented
echo "<h3>Session Regeneration Check</h3>";
echo "<p class='info'>Session regeneration should occur after login to prevent session fixation attacks.</p>";
echo "<p>Check your <code>login_process.php</code> for: <code>session_regenerate_id(true);</code></p>";

echo "</div>";

// Test 8: Role-Based Access Control
echo "<div class='section'>";
echo "<h2>Test 8: Role-Based Access Control (RBAC)</h2>";

$rolePages = [
    'admin' => ['admin/dashboard.php', 'admin/api.php'],
    'doctor' => ['doctor/dashboard.php', 'doctor/api.php'],
    'staff' => ['staff/dashboard.php', 'staff/api.php'],
    'user' => ['user/dashboard.php', 'user/api.php'],
];

echo "<table>";
echo "<thead><tr><th>Role</th><th>Dashboard</th><th>API</th></tr></thead>";
echo "<tbody>";

foreach ($rolePages as $role => $pages) {
    $totalTests += 2;
    $dashboardExists = file_exists(__DIR__ . '/../' . $pages[0]);
    $apiExists = file_exists(__DIR__ . '/../' . $pages[1]);
    
    echo "<tr>";
    echo "<td><strong>" . ucfirst($role) . "</strong></td>";
    
    if ($dashboardExists) {
        echo "<td><span class='badge badge-success'>✓ EXISTS</span></td>";
        $testsPassed++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ MISSING</span></td>";
    }
    
    if ($apiExists) {
        echo "<td><span class='badge badge-success'>✓ EXISTS</span></td>";
        $testsPassed++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ MISSING</span></td>";
    }
    
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div class='alert alert-info'>";
echo "<strong>💡 Best Practice:</strong> Each role's dashboard should check for proper authentication and authorization:";
echo "<pre>if (!isset(\$_SESSION['user_id']) || \$_SESSION['role'] !== 'admin') {
    header('Location: ../login/');
    exit();
}</pre>";
echo "</div>";

echo "</div>";

// Test 9: Logout Functionality
echo "<div class='section'>";
echo "<h2>Test 9: Logout Functionality</h2>";

$totalTests++;
$logoutFile = __DIR__ . '/../logout.php';
if (file_exists($logoutFile)) {
    echo "<p class='success'>✅ Logout file exists</p>";
    $testsPassed++;
    
    echo "<p class='info'>Logout should:</p>";
    echo "<ul>";
    echo "<li>Destroy the session with <code>session_destroy()</code></li>";
    echo "<li>Unset all session variables with <code>session_unset()</code></li>";
    echo "<li>Delete the session cookie</li>";
    echo "<li>Redirect to login page</li>";
    echo "</ul>";
} else {
    echo "<p class='error'>❌ Logout file not found</p>";
}

echo "</div>";

// Test 10: Account Verification
echo "<div class='section'>";
echo "<h2>Test 10: Email Verification System</h2>";

// Check if users table has verification columns
$totalTests++;
$columnsQuery = "SHOW COLUMNS FROM users";
$columnsResult = $conn->query($columnsQuery);

$hasEmailVerified = false;
if ($columnsResult) {
    while ($col = $columnsResult->fetch_assoc()) {
        if (in_array($col['Field'], ['email_verified', 'is_verified', 'verified'])) {
            $hasEmailVerified = true;
            break;
        }
    }
}

if ($hasEmailVerified) {
    echo "<p class='success'>✅ Email verification column exists in users table</p>";
    $testsPassed++;
    
    // Count verified vs unverified users
    $statsQuery = "SELECT 
        SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified,
        SUM(CASE WHEN email_verified = 0 OR email_verified IS NULL THEN 1 ELSE 0 END) as unverified
        FROM users";
    $statsResult = $conn->query($statsQuery);
    
    if ($statsResult) {
        $stats = $statsResult->fetch_assoc();
        echo "<p><strong>Verified Users:</strong> {$stats['verified']}</p>";
        echo "<p><strong>Unverified Users:</strong> {$stats['unverified']}</p>";
    }
} else {
    echo "<p class='info'>ℹ️ Email verification column not found in database</p>";
    echo "<p class='info'>Your system uses OTP verification during registration instead</p>";
}

// Check for OTP verification files
$totalTests++;
$otpFile = __DIR__ . '/../register/verify_otp.php';
if (file_exists($otpFile)) {
    echo "<p class='success'>✅ OTP verification file exists (used during registration)</p>";
    $testsPassed++;
} else {
    echo "<p class='warning'>⚠️ OTP verification file not found</p>";
}

echo "</div>";

// Authentication Score Summary
echo "<div class='section'>";
echo "<h2>🎯 Authentication Test Score</h2>";

$percentage = $totalTests > 0 ? round(($testsPassed / $totalTests) * 100) : 0;

echo "<div style='text-align: center; padding: 30px;'>";
echo "<div style='font-size: 4rem; font-weight: bold; color: " . ($percentage >= 80 ? '#28a745' : ($percentage >= 60 ? '#ffc107' : '#dc3545')) . ";'>";
echo "$percentage%";
echo "</div>";
echo "<p style='font-size: 1.2rem;'>$testsPassed out of $totalTests tests passed</p>";
echo "</div>";

if ($percentage >= 80) {
    echo "<div class='alert alert-success'>";
    echo "<h3 style='margin: 0; color: #155724;'>✅ STRONG AUTHENTICATION</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Your authentication system is well-configured.</p>";
    echo "</div>";
} elseif ($percentage >= 60) {
    echo "<div class='alert alert-warning'>";
    echo "<h3 style='margin: 0; color: #856404;'>⚠️ MODERATE AUTHENTICATION</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Some improvements needed. Review failed tests above.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3 style='margin: 0; color: #721c24;'>❌ AUTHENTICATION ISSUES</h3>";
    echo "<p style='margin: 10px 0 0 0;'><strong>CRITICAL:</strong> Fix authentication issues before production!</p>";
    echo "</div>";
}

echo "<h3>📋 Authentication Best Practices</h3>";
echo "<ul>";
echo "<li>✓ Always use prepared statements to prevent SQL injection</li>";
echo "<li>✓ Hash passwords with <code>password_hash()</code> using bcrypt or argon2</li>";
echo "<li>✓ Regenerate session ID after login with <code>session_regenerate_id(true)</code></li>";
echo "<li>✓ Implement rate limiting to prevent brute force attacks</li>";
echo "<li>✓ Use HTTPS in production to encrypt login credentials</li>";
echo "<li>✓ Set session.cookie_httponly = 1 to prevent XSS attacks</li>";
echo "<li>✓ Implement 2FA (Two-Factor Authentication) for admin accounts</li>";
echo "<li>✓ Log all authentication attempts in audit_log table</li>";
echo "<li>✓ Expire sessions after inactivity period</li>";
echo "<li>✓ <strong>DELETE THIS TEST FILE</strong> before production</li>";
echo "</ul>";

echo "</div>";

$conn->close();
?>

</body>
</html>
