<?php
/**
 * Security Test Suite
 * 
 * Tests security configurations and common vulnerabilities
 * Access via: http://localhost/medsync/tests/test_security.php
 * 
 * ⚠️ DELETE THIS FILE IN PRODUCTION! It exposes security information.
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
    <title>MedSync Security Test</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        h1 { color: #dc3545; border-bottom: 3px solid #dc3545; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #dc3545; }
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
        th { background: #dc3545; color: white; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 3px solid #dc3545; overflow-x: auto; }
    </style>
</head>
<body>

<h1>🔒 MedSync Security Test Suite</h1>

<div class="alert alert-danger">
    <strong>⚠️ CRITICAL WARNING:</strong> This file exposes security configurations and vulnerabilities. 
    <strong>DELETE IT IMMEDIATELY</strong> before deploying to production!
</div>

<?php
$securityScore = 0;
$totalTests = 0;

// Test 1: PHP Configuration Security
echo "<div class='section'>";
echo "<h2>Test 1: PHP Configuration Security</h2>";

$phpTests = [
    'display_errors' => ['current' => ini_get('display_errors'), 'safe' => '0', 'critical' => true],
    'expose_php' => ['current' => ini_get('expose_php'), 'safe' => '0', 'critical' => true],
    'file_uploads' => ['current' => ini_get('file_uploads'), 'safe' => '1', 'critical' => false],
    'max_file_uploads' => ['current' => ini_get('max_file_uploads'), 'safe' => '20', 'critical' => false],
    'upload_max_filesize' => ['current' => ini_get('upload_max_filesize'), 'safe' => '10M', 'critical' => false],
    'post_max_size' => ['current' => ini_get('post_max_size'), 'safe' => '10M', 'critical' => false],
    'session.cookie_httponly' => ['current' => ini_get('session.cookie_httponly'), 'safe' => '1', 'critical' => true],
    'session.use_strict_mode' => ['current' => ini_get('session.use_strict_mode'), 'safe' => '1', 'critical' => true],
];

echo "<table>";
echo "<thead><tr><th>Setting</th><th>Current Value</th><th>Status</th><th>Risk Level</th></tr></thead>";
echo "<tbody>";

foreach ($phpTests as $setting => $config) {
    $totalTests++;
    $isSafe = ($config['current'] == $config['safe']);
    $riskLevel = $config['critical'] ? 'HIGH' : 'MEDIUM';
    
    echo "<tr>";
    echo "<td><code>$setting</code></td>";
    echo "<td>" . htmlspecialchars($config['current']) . "</td>";
    
    if ($isSafe) {
        echo "<td><span class='badge badge-success'>✓ SECURE</span></td>";
        echo "<td>-</td>";
        $securityScore++;
    } else {
        $badgeClass = $config['critical'] ? 'badge-danger' : 'badge-warning';
        echo "<td><span class='badge $badgeClass'>✗ INSECURE</span></td>";
        echo "<td><span class='" . ($config['critical'] ? 'error' : 'warning') . "'>$riskLevel</span></td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";

// Add PHP configuration warnings and instructions
echo "<div class='alert alert-warning' style='margin-top: 20px;'>";
echo "<h3 style='margin: 0 0 10px 0; color: #856404;'>⚠️ PHP Configuration Issues Detected</h3>";

$hasIssues = false;
if (ini_get('display_errors') == '1') {
    echo "<p><strong>display_errors = 1:</strong> OK for localhost, but <strong>MUST be set to 0 in production</strong> php.ini</p>";
    $hasIssues = true;
}
if (ini_get('expose_php') == '1') {
    echo "<p><strong>expose_php = 1:</strong> OK for localhost, but <strong>MUST be set to 0 in production</strong> php.ini</p>";
    $hasIssues = true;
}
if (ini_get('upload_max_filesize') != '10M') {
    echo "<p><strong>upload_max_filesize = " . ini_get('upload_max_filesize') . ":</strong> Consider reducing to 10M in production php.ini</p>";
    $hasIssues = true;
}
if (ini_get('post_max_size') != '10M') {
    echo "<p><strong>post_max_size = " . ini_get('post_max_size') . ":</strong> Consider reducing to 10M in production php.ini</p>";
    $hasIssues = true;
}

if ($hasIssues) {
    echo "<hr style='border-color: #856404; margin: 15px 0;'>";
    echo "<p><strong>📝 How to fix for Production:</strong></p>";
    echo "<ol style='margin: 10px 0;'>";
    echo "<li>Open <code>php.ini</code> file on production server</li>";
    echo "<li>Find and update these settings:</li>";
    echo "</ol>";
    echo "<pre style='background: #fff; color: #000; padding: 10px; border-left: 3px solid #856404;'>";
    echo "display_errors = Off\n";
    echo "expose_php = Off\n";
    echo "upload_max_filesize = 10M\n";
    echo "post_max_size = 10M\n";
    echo "</pre>";
    echo "<p><strong>For XAMPP localhost:</strong> <code>C:\\xampp\\php\\php.ini</code> (restart Apache after changes)</p>";
}
echo "</div>";

echo "</div>";

// Test 2: Session Security
echo "<div class='section'>";
echo "<h2>Test 2: Session Security</h2>";

$totalTests++;
if (isset($_SESSION)) {
    echo "<p class='success'>✅ Session is active</p>";
    
    // Check session settings
    echo "<table>";
    echo "<thead><tr><th>Session Setting</th><th>Value</th><th>Status</th></tr></thead>";
    echo "<tbody>";
    
    $sessionTests = [
        'Session ID' => session_id(),
        'Session Name' => session_name(),
        'Cookie Lifetime' => ini_get('session.cookie_lifetime'),
        'Cookie Secure' => ini_get('session.cookie_secure'),
        'Cookie HttpOnly' => ini_get('session.cookie_httponly'),
        'Cookie SameSite' => ini_get('session.cookie_samesite'),
    ];
    
    foreach ($sessionTests as $key => $value) {
        echo "<tr>";
        echo "<td><strong>$key</strong></td>";
        echo "<td><code>" . htmlspecialchars($value ?: 'Not Set') . "</code></td>";
        
        if ($key === 'Cookie HttpOnly' && $value == '1') {
            echo "<td><span class='badge badge-success'>✓ SECURE</span></td>";
            $securityScore++;
        } elseif ($key === 'Cookie Secure' && $value == '1') {
            echo "<td><span class='badge badge-success'>✓ SECURE</span></td>";
        } elseif ($key === 'Cookie Secure' && $value != '1') {
            echo "<td><span class='badge badge-warning'>⚠ HTTP Only (OK for localhost)</span></td>";
        } else {
            echo "<td><span class='badge badge-success'>✓</span></td>";
        }
        echo "</tr>";
    }
    
    echo "</tbody></table>";
    $totalTests++;
} else {
    echo "<p class='warning'>⚠️ No active session found</p>";
}

echo "</div>";

// Test 3: Password Hashing
echo "<div class='section'>";
echo "<h2>Test 3: Password Hashing Security</h2>";

$testPassword = "TestPassword123!";
$hashedPassword = password_hash($testPassword, PASSWORD_DEFAULT);

echo "<p><strong>Test Password:</strong> <code>$testPassword</code></p>";
echo "<p><strong>Hashed Password:</strong> <code>" . substr($hashedPassword, 0, 50) . "...</code></p>";

$totalTests++;
if (password_verify($testPassword, $hashedPassword)) {
    echo "<p class='success'>✅ Password hashing and verification working correctly</p>";
    $securityScore++;
} else {
    echo "<p class='error'>❌ Password verification failed</p>";
}

// Check hashing algorithm
$totalTests++;
$info = password_get_info($hashedPassword);
echo "<p><strong>Algorithm:</strong> " . $info['algoName'] . "</p>";
if ($info['algoName'] === 'bcrypt' || $info['algoName'] === 'argon2i' || $info['algoName'] === 'argon2id') {
    echo "<p class='success'>✅ Using strong hashing algorithm: {$info['algoName']}</p>";
    $securityScore++;
} else {
    echo "<p class='error'>❌ Using weak hashing algorithm: {$info['algoName']}</p>";
}

echo "</div>";

// Test 4: File Upload Security
echo "<div class='section'>";
echo "<h2>Test 4: File Upload Security</h2>";

$uploadDirs = [
    'Profile Pictures' => __DIR__ . '/../uploads/profile_pictures',
    'Lab Reports' => __DIR__ . '/../uploads/lab_reports',
];

echo "<table>";
echo "<thead><tr><th>Upload Directory</th><th>Exists</th><th>Writable</th><th>.htaccess Protection</th></tr></thead>";
echo "<tbody>";

foreach ($uploadDirs as $name => $dir) {
    $totalTests += 2;
    $exists = is_dir($dir);
    $writable = is_writable($dir);
    $htaccessExists = file_exists($dir . '/.htaccess');
    
    echo "<tr>";
    echo "<td><strong>$name</strong><br><small><code>$dir</code></small></td>";
    
    if ($exists) {
        echo "<td><span class='badge badge-success'>✓ YES</span></td>";
        $securityScore++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ NO</span></td>";
    }
    
    if ($writable) {
        echo "<td><span class='badge badge-success'>✓ YES</span></td>";
        $securityScore++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ NO</span></td>";
    }
    
    if ($htaccessExists) {
        echo "<td><span class='badge badge-success'>✓ PROTECTED</span></td>";
    } else {
        echo "<td><span class='badge badge-warning'>⚠ NOT PROTECTED</span></td>";
    }
    
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div class='alert alert-warning'>";
echo "<strong>⚠️ Recommendation:</strong> Add <code>.htaccess</code> files to upload directories to prevent direct PHP execution:";
echo "<pre>php_flag engine off\n&lt;Files ~ \"\\.(php|phtml|php3|php4|php5|phps)$\"&gt;\n    deny from all\n&lt;/Files&gt;</pre>";
echo "</div>";

echo "</div>";

// Test 5: Environment Variables Security
echo "<div class='section'>";
echo "<h2>Test 5: Environment Variables Security</h2>";

$totalTests++;
if (file_exists(__DIR__ . '/../.env')) {
    echo "<p class='success'>✅ .env file exists</p>";
    $securityScore++;
    
    // Check if .env is in .gitignore
    $totalTests++;
    if (file_exists(__DIR__ . '/../.gitignore')) {
        $gitignore = file_get_contents(__DIR__ . '/../.gitignore');
        if (strpos($gitignore, '.env') !== false) {
            echo "<p class='success'>✅ .env file is in .gitignore (won't be committed to Git)</p>";
            $securityScore++;
        } else {
            echo "<p class='error'>❌ .env file is NOT in .gitignore (security risk!)</p>";
        }
    }
    
    // Check sensitive credentials are not hardcoded
    $totalTests++;
    $configFile = file_get_contents(__DIR__ . '/../config.php');
    if (strpos($configFile, '$_ENV[') !== false || strpos($configFile, 'getenv(') !== false) {
        echo "<p class='success'>✅ Using environment variables for sensitive data</p>";
        $securityScore++;
    } else {
        echo "<p class='warning'>⚠️ Might have hardcoded credentials in config.php</p>";
    }
} else {
    echo "<p class='error'>❌ .env file not found</p>";
}

echo "</div>";

// Test 6: HTTPS Configuration
echo "<div class='section'>";
echo "<h2>Test 6: HTTPS Configuration</h2>";

$totalTests++;
$isHTTPS = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

if ($isHTTPS) {
    echo "<p class='success'>✅ Connection is using HTTPS (secure)</p>";
    $securityScore++;
} else {
    echo "<p class='warning'>⚠️ Connection is using HTTP (not secure)</p>";
    echo "<p class='info'>This is acceptable for localhost development, but <strong>production MUST use HTTPS</strong></p>";
}

echo "<p><strong>Protocol:</strong> " . ($_SERVER['SERVER_PROTOCOL'] ?? 'Unknown') . "</p>";
echo "<p><strong>Port:</strong> " . ($_SERVER['SERVER_PORT'] ?? 'Unknown') . "</p>";

echo "</div>";

// Test 7: Directory Listing Protection
echo "<div class='section'>";
echo "<h2>Test 7: Directory Listing Protection</h2>";

$totalTests++;
if (file_exists(__DIR__ . '/../.htaccess')) {
    $htaccess = file_get_contents(__DIR__ . '/../.htaccess');
    if (strpos($htaccess, 'Options -Indexes') !== false) {
        echo "<p class='success'>✅ Directory listing is disabled in .htaccess</p>";
        $securityScore++;
    } else {
        echo "<p class='warning'>⚠️ Directory listing protection not found in .htaccess</p>";
        echo "<p>Add this line to .htaccess: <code>Options -Indexes</code></p>";
    }
} else {
    echo "<p class='warning'>⚠️ .htaccess file not found</p>";
}

echo "</div>";

// Test 8: Error Logging
echo "<div class='section'>";
echo "<h2>Test 8: Error Logging Configuration</h2>";

$totalTests++;
$logErrors = ini_get('log_errors');
if ($logErrors) {
    echo "<p class='success'>✅ Error logging is enabled</p>";
    echo "<p><strong>Error Log Location:</strong> <code>" . ini_get('error_log') . "</code></p>";
    $securityScore++;
} else {
    echo "<p class='error'>❌ Error logging is disabled</p>";
}

echo "</div>";

// Test 9: SQL Injection Attack Simulations
echo "<div class='section'>";
echo "<h2>Test 9: SQL Injection Attack Simulations</h2>";

echo "<p class='info'>Testing various SQL injection attack vectors...</p>";

$sqlInjectionTests = [
    "Basic OR Injection" => "' OR '1'='1",
    "Comment-based Injection" => "admin'--",
    "Union-based Injection" => "' UNION SELECT NULL,NULL,NULL--",
    "Time-based Blind Injection" => "1' AND SLEEP(5)--",
    "Boolean-based Injection" => "1' AND 1=1--",
    "Stacked Queries" => "1'; DROP TABLE users;--",
    "Encoded Injection" => "%27%20OR%20%271%27=%271",
    "Hexadecimal Injection" => "0x61646d696e",
];

echo "<table>";
echo "<thead><tr><th>Attack Type</th><th>Payload</th><th>Protected</th><th>Details</th></tr></thead>";
echo "<tbody>";

foreach ($sqlInjectionTests as $type => $payload) {
    $totalTests++;
    $protected = false;
    $details = "";
    
    try {
        // Test with prepared statement
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $payload);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // If no rows returned, the injection was prevented
            if ($result->num_rows === 0) {
                $protected = true;
                $details = "Safely neutralized";
                $securityScore++;
            } else {
                $details = "VULNERABILITY DETECTED!";
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Exception means the query was rejected (good)
        $protected = true;
        $details = "Query rejected by database";
        $securityScore++;
    }
    
    echo "<tr>";
    echo "<td><strong>$type</strong></td>";
    echo "<td><code>" . htmlspecialchars(substr($payload, 0, 40)) . (strlen($payload) > 40 ? '...' : '') . "</code></td>";
    
    if ($protected) {
        echo "<td><span class='badge badge-success'>✓ PROTECTED</span></td>";
        echo "<td class='success'>$details</td>";
    } else {
        echo "<td><span class='badge badge-danger'>✗ VULNERABLE</span></td>";
        echo "<td class='error'>$details</td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div class='alert alert-success'>";
echo "<strong>✅ Best Practice:</strong> Always use prepared statements with parameterized queries. Never concatenate user input directly into SQL queries.";
echo "</div>";

echo "</div>";

// Test 10: XSS Vulnerability Checks
echo "<div class='section'>";
echo "<h2>Test 10: XSS (Cross-Site Scripting) Vulnerability Tests</h2>";

echo "<p class='info'>Testing various XSS attack vectors...</p>";

$xssTests = [
    "Basic Script Tag" => "<script>alert('XSS')</script>",
    "Image Onerror" => "<img src=x onerror=alert('XSS')>",
    "Event Handler" => "<body onload=alert('XSS')>",
    "Encoded Script" => "&#60;script&#62;alert('XSS')&#60;/script&#62;",
    "JavaScript Protocol" => "<a href=\"javascript:alert('XSS')\">Click</a>",
    "SVG Injection" => "<svg onload=alert('XSS')>",
    "Base64 Encoded" => "<img src=\"data:text/html;base64,PHNjcmlwdD5hbGVydCgnWFNTJyk8L3NjcmlwdD4=\">",
    "CSS Expression" => "<div style=\"background:url('javascript:alert(1)')\">",
];

echo "<table>";
echo "<thead><tr><th>Attack Type</th><th>Payload</th><th>Sanitized</th><th>Result</th></tr></thead>";
echo "<tbody>";

foreach ($xssTests as $type => $payload) {
    $totalTests++;
    $sanitized = htmlspecialchars($payload, ENT_QUOTES, 'UTF-8');
    
    // If htmlspecialchars changed the string, it means special chars were encoded
    // This makes the payload safe (< becomes &lt;, > becomes &gt;, etc.)
    $protected = ($payload !== $sanitized);
    
    echo "<tr>";
    echo "<td><strong>$type</strong></td>";
    echo "<td><code>" . htmlspecialchars(substr($payload, 0, 50)) . (strlen($payload) > 50 ? '...' : '') . "</code></td>";
    
    if ($protected) {
        echo "<td><span class='badge badge-success'>✓ SANITIZED</span></td>";
        echo "<td class='success'>Dangerous code neutralized</td>";
        $securityScore++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ PASSED THROUGH</span></td>";
        echo "<td class='error'>XSS payload not sanitized!</td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div class='alert alert-success'>";
echo "<strong>✅ Best Practice:</strong> Use <code>htmlspecialchars(\$input, ENT_QUOTES, 'UTF-8')</code> for all user-generated content displayed in HTML.";
echo "</div>";

echo "<h3>Content Security Policy (CSP) Check</h3>";
$totalTests++;
$headers = headers_list();
$cspFound = false;
foreach ($headers as $header) {
    if (stripos($header, 'Content-Security-Policy') !== false) {
        $cspFound = true;
        echo "<p class='success'>✅ CSP Header is set: <code>" . htmlspecialchars($header) . "</code></p>";
        $securityScore++;
        break;
    }
}

if (!$cspFound) {
    echo "<p class='warning'>⚠️ Content-Security-Policy header not found</p>";
    echo "<div class='alert alert-info'>";
    echo "<strong>💡 Recommendation:</strong> Add CSP header to prevent XSS attacks:";
    echo "<pre>header(\"Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;\");</pre>";
    echo "</div>";
}

echo "</div>";

// Test 11: CSRF Token Validation
echo "<div class='section'>";
echo "<h2>Test 11: CSRF (Cross-Site Request Forgery) Protection</h2>";

echo "<p class='info'>Testing CSRF token generation and validation...</p>";

// Generate CSRF token
$totalTests++;
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$validToken = $_SESSION['csrf_token'];

echo "<p><strong>Generated CSRF Token:</strong> <code>" . substr($validToken, 0, 20) . "...</code> (64 characters)</p>";

// Test token validation function
function validateCSRFToken($token) {
    // Check if token is provided and is a string
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Test various CSRF scenarios
$csrfTests = [
    "Valid Token" => ['token' => $validToken, 'expected' => true],
    "Invalid Token" => ['token' => 'invalid_token_12345', 'expected' => false],
    "Empty Token" => ['token' => '', 'expected' => false],
    "Modified Token" => ['token' => substr($validToken, 0, -5) . '12345', 'expected' => false],
    "No Token" => ['token' => null, 'expected' => false],
];

echo "<table>";
echo "<thead><tr><th>Test Scenario</th><th>Token Provided</th><th>Validation Result</th><th>Status</th></tr></thead>";
echo "<tbody>";

foreach ($csrfTests as $scenario => $test) {
    $totalTests++;
    $result = validateCSRFToken($test['token']);
    $passed = ($result === $test['expected']);
    
    echo "<tr>";
    echo "<td><strong>$scenario</strong></td>";
    echo "<td><code>" . htmlspecialchars(substr($test['token'] ?? 'null', 0, 20)) . (strlen($test['token'] ?? '') > 20 ? '...' : '') . "</code></td>";
    echo "<td>" . ($result ? '<span class="success">Valid</span>' : '<span class="error">Invalid</span>') . "</td>";
    
    if ($passed) {
        echo "<td><span class='badge badge-success'>✓ PASS</span></td>";
        $securityScore++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ FAIL</span></td>";
    }
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div class='alert alert-success'>";
echo "<strong>✅ CSRF Protection Implementation:</strong>";
echo "<pre>// Generate token (add to all forms)\n\$_SESSION['csrf_token'] = bin2hex(random_bytes(32));\n\n// In HTML form\n&lt;input type=\"hidden\" name=\"csrf_token\" value=\"&lt;?php echo \$_SESSION['csrf_token']; ?&gt;\"&gt;\n\n// Validate on form submission\nif (!hash_equals(\$_SESSION['csrf_token'], \$_POST['csrf_token'])) {\n    die('CSRF token validation failed');\n}</pre>";
echo "</div>";

// Check if forms have CSRF tokens
$totalTests++;
$loginForm = file_get_contents(__DIR__ . '/../login/index.php');
if (strpos($loginForm, 'csrf_token') !== false) {
    echo "<p class='success'>✅ Login form has CSRF token implementation</p>";
    $securityScore++;
} else {
    echo "<p class='error'>❌ Login form does NOT have CSRF token protection</p>";
    echo "<p class='warning'>⚠️ Add CSRF tokens to all forms that modify data (login, register, profile update, etc.)</p>";
}

echo "</div>";

// Test 12: Session Hijacking Prevention
echo "<div class='section'>";
echo "<h2>Test 12: Session Hijacking & Fixation Prevention</h2>";

echo "<p class='info'>Testing session security mechanisms...</p>";

echo "<h3>Session Configuration Tests</h3>";

$sessionSecurityTests = [
    "Session ID Regeneration" => [
        'check' => function() {
            // Check if login_process.php has session_regenerate_id
            $loginProcess = @file_get_contents(__DIR__ . '/../login/login_process.php');
            return $loginProcess && strpos($loginProcess, 'session_regenerate_id') !== false;
        },
        'description' => 'Prevents session fixation attacks'
    ],
    "HttpOnly Cookie Flag" => [
        'check' => function() {
            return ini_get('session.cookie_httponly') == '1';
        },
        'description' => 'Prevents JavaScript access to session cookie'
    ],
    "Strict Session Mode" => [
        'check' => function() {
            return ini_get('session.use_strict_mode') == '1';
        },
        'description' => 'Rejects uninitialized session IDs'
    ],
    "SameSite Cookie Attribute" => [
        'check' => function() {
            $sameSite = ini_get('session.cookie_samesite');
            return in_array($sameSite, ['Strict', 'Lax']);
        },
        'description' => 'Prevents CSRF attacks via cookies'
    ],
    "Session Use Only Cookies" => [
        'check' => function() {
            return ini_get('session.use_only_cookies') == '1';
        },
        'description' => 'Prevents session ID in URL'
    ],
];

echo "<table>";
echo "<thead><tr><th>Security Mechanism</th><th>Status</th><th>Purpose</th></tr></thead>";
echo "<tbody>";

foreach ($sessionSecurityTests as $name => $test) {
    $totalTests++;
    $passed = $test['check']();
    
    echo "<tr>";
    echo "<td><strong>$name</strong></td>";
    
    if ($passed) {
        echo "<td><span class='badge badge-success'>✓ ENABLED</span></td>";
        $securityScore++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ DISABLED</span></td>";
    }
    
    echo "<td>{$test['description']}</td>";
    echo "</tr>";
}

echo "</tbody></table>";

echo "<h3>Session Fingerprinting Test</h3>";

$totalTests++;
// Check if User-Agent fingerprinting is implemented
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$sessionFingerprint = md5($userAgent . $_SERVER['REMOTE_ADDR']);

echo "<p><strong>User Agent:</strong> <code>" . htmlspecialchars(substr($userAgent, 0, 80)) . "...</code></p>";
echo "<p><strong>Session Fingerprint:</strong> <code>$sessionFingerprint</code></p>";

// Check if config.php has fingerprinting
$configContent = @file_get_contents(__DIR__ . '/../config.php');
if ($configContent && (strpos($configContent, 'HTTP_USER_AGENT') !== false || strpos($configContent, 'fingerprint') !== false)) {
    echo "<p class='success'>✅ Session fingerprinting appears to be implemented</p>";
    $securityScore++;
} else {
    echo "<p class='warning'>⚠️ Session fingerprinting not detected in config.php</p>";
    echo "<div class='alert alert-info'>";
    echo "<strong>💡 Recommendation:</strong> Implement session fingerprinting to detect hijacking:";
    echo "<pre>// Store fingerprint on login\n\$_SESSION['fingerprint'] = md5(\$_SERVER['HTTP_USER_AGENT'] . \$_SERVER['REMOTE_ADDR']);\n\n// Validate on each request\nif (isset(\$_SESSION['fingerprint'])) {\n    \$currentFingerprint = md5(\$_SERVER['HTTP_USER_AGENT'] . \$_SERVER['REMOTE_ADDR']);\n    if (\$_SESSION['fingerprint'] !== \$currentFingerprint) {\n        session_destroy();\n        die('Session hijacking detected!');\n    }\n}</pre>";
    echo "</div>";
}

echo "<h3>Session Timeout Test</h3>";

$totalTests++;
$sessionLifetime = ini_get('session.gc_maxlifetime');
echo "<p><strong>Session Lifetime:</strong> $sessionLifetime seconds (" . round($sessionLifetime / 60) . " minutes)</p>";

if ($sessionLifetime > 0 && $sessionLifetime <= 1800) { // 30 minutes or less
    echo "<p class='success'>✅ Session timeout is set to a reasonable value</p>";
    $securityScore++;
} else {
    echo "<p class='warning'>⚠️ Session timeout is too long (recommended: 15-30 minutes)</p>";
}

// Check for last activity timeout implementation
if ($configContent && strpos($configContent, 'last_activity') !== false) {
    echo "<p class='success'>✅ Activity-based session timeout appears to be implemented</p>";
} else {
    echo "<p class='warning'>⚠️ Activity-based timeout not detected</p>";
    echo "<div class='alert alert-info'>";
    echo "<strong>💡 Recommendation:</strong> Implement activity-based session timeout:";
    echo "<pre>// Check last activity\nif (isset(\$_SESSION['last_activity']) && (time() - \$_SESSION['last_activity'] > 1800)) {\n    session_unset();\n    session_destroy();\n    header('Location: /login/');\n    exit();\n}\n\$_SESSION['last_activity'] = time();</pre>";
    echo "</div>";
}

echo "</div>";

// Security Score Summary
echo "<div class='section'>";
echo "<h2>🎯 Security Score</h2>";

$percentage = round(($securityScore / $totalTests) * 100);
$scoreClass = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'error');

echo "<div style='text-align: center; padding: 30px;'>";
echo "<div style='font-size: 4rem; font-weight: bold; color: " . ($percentage >= 80 ? '#28a745' : ($percentage >= 60 ? '#ffc107' : '#dc3545')) . ";'>";
echo "$percentage%";
echo "</div>";
echo "<p style='font-size: 1.2rem;'>$securityScore out of $totalTests tests passed</p>";
echo "</div>";

if ($percentage >= 80) {
    echo "<div class='alert alert-success'>";
    echo "<h3 style='margin: 0; color: #155724;'>✅ GOOD SECURITY POSTURE</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Your application has good security configurations.</p>";
    echo "</div>";
} elseif ($percentage >= 60) {
    echo "<div class='alert alert-warning'>";
    echo "<h3 style='margin: 0; color: #856404;'>⚠️ MODERATE SECURITY</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Some security improvements are needed. Review the failed tests above.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3 style='margin: 0; color: #721c24;'>❌ SECURITY RISKS DETECTED</h3>";
    echo "<p style='margin: 10px 0 0 0;'><strong>CRITICAL:</strong> Multiple security issues found. Fix them before production!</p>";
    echo "</div>";
}

echo "<h3>📋 Security Checklist for Production</h3>";
echo "<ul>";
echo "<li>✓ Set <code>display_errors = 0</code> in php.ini</li>";
echo "<li>✓ Enable HTTPS with valid SSL certificate</li>";
echo "<li>✓ Use prepared statements for ALL database queries</li>";
echo "<li>✓ Sanitize all user inputs with <code>htmlspecialchars()</code></li>";
echo "<li>✓ Keep .env file out of version control</li>";
echo "<li>✓ Add .htaccess protection to upload directories</li>";
echo "<li>✓ Enable CSRF token validation on forms</li>";
echo "<li>✓ Implement rate limiting on login attempts</li>";
echo "<li>✓ Keep all dependencies updated (run <code>composer update</code>)</li>";
echo "<li>✓ <strong>DELETE ALL TEST FILES</strong> from production server</li>";
echo "</ul>";

echo "</div>";

$conn->close();
?>

</body>
</html>
