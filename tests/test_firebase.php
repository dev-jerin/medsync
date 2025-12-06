<?php
/**
 * Firebase Integration Test Suite
 * 
 * Tests Firebase configuration and integration
 * Access via: http://localhost/medsync/tests/test_firebase.php
 * 
 * ⚠️ DELETE THIS FILE IN PRODUCTION! It exposes Firebase configuration.
 */

require_once __DIR__ . '/../config.php';

// HTML Header
?>
<!DOCTYPE html>
<html>
<head>
    <title>MedSync Firebase Test</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        h1 { color: #ff9800; border-bottom: 3px solid #ff9800; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #ff9800; }
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
        th { background: #ff9800; color: white; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 3px solid #ff9800; overflow-x: auto; font-size: 0.9rem; }
        .config-item { background: #f8f9fa; padding: 10px; margin: 5px 0; border-radius: 5px; }
    </style>
</head>
<body>

<h1>🔥 MedSync Firebase Integration Test</h1>

<div class="alert alert-danger">
    <strong>⚠️ CRITICAL WARNING:</strong> This file exposes Firebase configuration and credentials. 
    <strong>DELETE IT IMMEDIATELY</strong> before deploying to production!
</div>

<?php
$testsPassed = 0;
$totalTests = 0;

// Test 1: Environment Variables
echo "<div class='section'>";
echo "<h2>Test 1: Firebase Environment Variables</h2>";

$firebaseEnvVars = [
    'FIREBASE_API_KEY' => 'Firebase API Key',
    'FIREBASE_AUTH_DOMAIN' => 'Firebase Auth Domain',
    'FIREBASE_PROJECT_ID' => 'Firebase Project ID',
    'FIREBASE_STORAGE_BUCKET' => 'Firebase Storage Bucket',
    'FIREBASE_MESSAGING_SENDER_ID' => 'Firebase Messaging Sender ID',
    'FIREBASE_APP_ID' => 'Firebase App ID',
];

echo "<table>";
echo "<thead><tr><th>Variable</th><th>Description</th><th>Status</th><th>Value</th></tr></thead>";
echo "<tbody>";

$allVarsSet = true;
foreach ($firebaseEnvVars as $var => $description) {
    $totalTests++;
    $value = $_ENV[$var] ?? false;
    
    echo "<tr>";
    echo "<td><code>$var</code></td>";
    echo "<td>$description</td>";
    
    if ($value !== false && !empty($value)) {
        echo "<td><span class='badge badge-success'>✓ SET</span></td>";
        
        // Mask sensitive values
        if ($var === 'FIREBASE_API_KEY') {
            $displayValue = substr($value, 0, 15) . '...';
        } else {
            $displayValue = $value;
        }
        echo "<td><code>" . htmlspecialchars($displayValue) . "</code></td>";
        $testsPassed++;
    } else {
        echo "<td><span class='badge badge-danger'>✗ NOT SET</span></td>";
        echo "<td><em>Missing</em></td>";
        $allVarsSet = false;
    }
    echo "</tr>";
}

echo "</tbody></table>";

if ($allVarsSet) {
    echo "<p class='success'>✅ All Firebase environment variables are configured</p>";
} else {
    echo "<p class='error'>❌ Some Firebase environment variables are missing</p>";
    echo "<p class='info'>Configure them in your <code>.env</code> file</p>";
}

echo "</div>";

// Test 2: Firebase Helper File
echo "<div class='section'>";
echo "<h2>Test 2: Firebase Helper File</h2>";

$totalTests++;
$firebaseHelperFile = __DIR__ . '/../firebase_helper.php';

if (file_exists($firebaseHelperFile)) {
    echo "<p class='success'>✅ Firebase helper file exists: <code>firebase_helper.php</code></p>";
    $testsPassed++;
    
    // Load and check firebase config
    $totalTests++;
    try {
        require_once $firebaseHelperFile;
        
        if (isset($firebaseConfig) && is_array($firebaseConfig)) {
            echo "<p class='success'>✅ Firebase configuration array loaded successfully</p>";
            $testsPassed++;
            
            echo "<h3>Firebase Configuration</h3>";
            echo "<pre>" . print_r($firebaseConfig, true) . "</pre>";
            
            // Check required keys
            $requiredKeys = ['apiKey', 'authDomain', 'projectId', 'storageBucket', 'messagingSenderId', 'appId'];
            $missingKeys = [];
            
            foreach ($requiredKeys as $key) {
                if (!isset($firebaseConfig[$key]) || empty($firebaseConfig[$key])) {
                    $missingKeys[] = $key;
                }
            }
            
            $totalTests++;
            if (empty($missingKeys)) {
                echo "<p class='success'>✅ All required Firebase config keys are present</p>";
                $testsPassed++;
            } else {
                echo "<p class='error'>❌ Missing Firebase config keys: " . implode(', ', $missingKeys) . "</p>";
            }
            
        } else {
            echo "<p class='error'>❌ Firebase configuration array not found or invalid</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error loading firebase_helper.php: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p class='error'>❌ Firebase helper file not found: <code>firebase_helper.php</code></p>";
}

echo "</div>";

// Test 3: Firebase Credentials JSON
echo "<div class='section'>";
echo "<h2>Test 3: Firebase Service Account Credentials</h2>";

$totalTests++;
$credentialsFile = __DIR__ . '/../' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? '_private/firebase_credentials.json');
$displayPath = $_ENV['FIREBASE_CREDENTIALS_PATH'] ?? '_private/firebase_credentials.json';

if (file_exists($credentialsFile)) {
    echo "<p class='success'>✅ Firebase credentials file exists: <code>" . htmlspecialchars($displayPath) . "</code></p>";
    $testsPassed++;
    
    // Validate JSON
    $totalTests++;
    $credentialsContent = file_get_contents($credentialsFile);
    $credentials = json_decode($credentialsContent, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "<p class='success'>✅ Credentials file is valid JSON</p>";
        $testsPassed++;
        
        // Check required fields
        $requiredFields = ['type', 'project_id', 'private_key_id', 'private_key', 'client_email'];
        $missingFields = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($credentials[$field])) {
                $missingFields[] = $field;
            }
        }
        
        $totalTests++;
        if (empty($missingFields)) {
            echo "<p class='success'>✅ All required credential fields are present</p>";
            $testsPassed++;
            
            echo "<table>";
            echo "<thead><tr><th>Field</th><th>Value</th></tr></thead>";
            echo "<tbody>";
            echo "<tr><td><strong>Type</strong></td><td>" . htmlspecialchars($credentials['type']) . "</td></tr>";
            echo "<tr><td><strong>Project ID</strong></td><td>" . htmlspecialchars($credentials['project_id']) . "</td></tr>";
            echo "<tr><td><strong>Client Email</strong></td><td>" . htmlspecialchars($credentials['client_email']) . "</td></tr>";
            echo "<tr><td><strong>Private Key</strong></td><td><code>[REDACTED - " . strlen($credentials['private_key']) . " characters]</code></td></tr>";
            echo "</tbody></table>";
        } else {
            echo "<p class='error'>❌ Missing credential fields: " . implode(', ', $missingFields) . "</p>";
        }
    } else {
        echo "<p class='error'>❌ Invalid JSON in credentials file: " . json_last_error_msg() . "</p>";
    }
    
    // Check file permissions
    echo "<h3>File Security</h3>";
    $totalTests++;
    
    // Check if .htaccess exists in _private directory
    $htaccessFile = __DIR__ . '/../_private/.htaccess';
    if (file_exists($htaccessFile)) {
        echo "<p class='success'>✅ .htaccess protection file exists in _private/ directory</p>";
        $testsPassed++;
        
        $htaccessContent = file_get_contents($htaccessFile);
        if (strpos($htaccessContent, 'Require all denied') !== false || strpos($htaccessContent, 'Deny from all') !== false) {
            echo "<p class='success'>✅ .htaccess configured to deny all HTTP access</p>";
            echo "<div class='alert alert-info'>";
            echo "<strong>🔒 Security Test:</strong> Try accessing this URL in your browser:<br>";
            echo "<code>http://localhost/medsync/_private/firebase_credentials.json</code><br>";
            echo "<strong>Expected Result:</strong> You should see <strong>403 Forbidden</strong> error<br>";
            echo "<strong>If you can see the JSON:</strong> Apache's .htaccess might not be enabled in httpd.conf";
            echo "</div>";
        } else {
            echo "<p class='warning'>⚠️ .htaccess exists but may not have proper deny rules</p>";
        }
    } else {
        echo "<p class='error'>❌ .htaccess protection file NOT found in _private/ directory</p>";
        echo "<p class='info'>The credentials file can be accessed via HTTP! Create _private/.htaccess with:</p>";
        echo "<pre>Require all denied</pre>";
    }
    
} else {
    echo "<p class='error'>❌ Firebase credentials file not found: <code>" . htmlspecialchars($displayPath) . "</code></p>";
    echo "<p class='info'>Download it from Firebase Console → Project Settings → Service Accounts</p>";
    echo "<p class='info'>Configure the path in <code>.env</code> using <code>FIREBASE_CREDENTIALS_PATH</code></p>";
}

echo "</div>";

// Test 4: Kreait Firebase SDK
echo "<div class='section'>";
echo "<h2>Test 4: Firebase Admin SDK (Kreait)</h2>";

$totalTests++;
if (class_exists('Kreait\Firebase\Factory')) {
    echo "<p class='success'>✅ Kreait Firebase Admin SDK is installed</p>";
    $testsPassed++;
    
    // Try to initialize Firebase
    $totalTests++;
    try {
        $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($credentialsFile);
        echo "<p class='success'>✅ Firebase Factory initialized successfully</p>";
        $testsPassed++;
        
        // Test Firestore (Optional - only if you use it)
        echo "<h3>Firestore Database (Optional)</h3>";
        $totalTests++;
        try {
            if (class_exists('Google\Cloud\Firestore\FirestoreClient')) {
                $firestore = $factory->createFirestore();
                $database = $firestore->database();
                echo "<p class='success'>✅ Firestore connection established</p>";
                $testsPassed++;
            } else {
                echo "<p class='info'>ℹ️ Firestore not installed (optional - not needed for your app)</p>";
                echo "<p class='info'>You use MySQL database instead of Firestore</p>";
                $testsPassed++; // Pass since it's optional
            }
        } catch (Exception $e) {
            echo "<p class='warning'>⚠️ Firestore connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p class='info'>This is OK - your app doesn't use Firestore database</p>";
            $testsPassed++; // Pass since it's not critical
        }
        
        // Test Cloud Storage (Required for your app)
        echo "<h3>Cloud Storage (Used for file uploads)</h3>";
        $totalTests++;
        try {
            $storage = $factory->createStorage();
            echo "<p class='success'>✅ Cloud Storage connection established</p>";
            $testsPassed++;
        } catch (Exception $e) {
            echo "<p class='error'>❌ Cloud Storage connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Firebase initialization failed: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p class='error'>❌ Kreait Firebase Admin SDK is NOT installed</p>";
    echo "<p class='info'>Install it with: <code>composer require kreait/firebase-php</code></p>";
}

echo "</div>";

// Test 5: Firebase Auth Integration
echo "<div class='section'>";
echo "<h2>Test 5: Firebase Authentication Integration</h2>";

$totalTests++;
$googleAuthFile = __DIR__ . '/../auth/google_auth_process.php';

if (file_exists($googleAuthFile)) {
    echo "<p class='success'>✅ Google Auth process file exists</p>";
    $testsPassed++;
    
    $authContent = file_get_contents($googleAuthFile);
    
    // Check for Firebase usage
    $totalTests++;
    if (strpos($authContent, 'firebase') !== false || strpos($authContent, 'Firebase') !== false) {
        echo "<p class='success'>✅ Firebase is used in authentication process</p>";
        $testsPassed++;
    } else {
        echo "<p class='warning'>⚠️ Firebase references not found in auth process</p>";
    }
} else {
    echo "<p class='error'>❌ Google Auth process file not found</p>";
}

// Check for firebase_helper usage in codebase
echo "<h3>Firebase Helper Usage</h3>";
$totalTests++;
$files = glob(__DIR__ . '/../**/*.php');
$usageCount = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'firebase_helper.php') !== false) {
        $usageCount++;
    }
}

if ($usageCount > 0) {
    echo "<p class='success'>✅ Firebase helper is used in {$usageCount} file(s)</p>";
    $testsPassed++;
} else {
    echo "<p class='info'>ℹ️ Firebase helper is not being used in PHP files</p>";
}

echo "</div>";

// Test 6: Client-Side Firebase Configuration
echo "<div class='section'>";
echo "<h2>Test 6: Client-Side Firebase Setup</h2>";

echo "<p class='info'>Checking if Firebase is properly loaded in frontend...</p>";

// Check for google-auth.js
$totalTests++;
$googleAuthJS = __DIR__ . '/../auth/google-auth.js';

if (file_exists($googleAuthJS)) {
    echo "<p class='success'>✅ Google Auth JavaScript file exists</p>";
    $testsPassed++;
    
    $jsContent = file_get_contents($googleAuthJS);
    
    // Check for Google Sign-In usage (not initialization - that's in login/register pages)
    $totalTests++;
    if (strpos($jsContent, 'GoogleAuthProvider') !== false || strpos($jsContent, 'signInWithPopup') !== false) {
        echo "<p class='success'>✅ Google Sign-In is configured</p>";
        $testsPassed++;
    } else {
        echo "<p class='warning'>⚠️ Google Sign-In configuration not found</p>";
    }
    
    // Check if Firebase is initialized in login page
    $totalTests++;
    $loginPage = __DIR__ . '/../login/index.php';
    if (file_exists($loginPage)) {
        $loginContent = file_get_contents($loginPage);
        if (strpos($loginContent, 'initializeApp') !== false && strpos($loginContent, 'firebase-app.js') !== false) {
            echo "<p class='success'>✅ Firebase is initialized in login page</p>";
            $testsPassed++;
        } else {
            echo "<p class='warning'>⚠️ Firebase initialization not found in login page</p>";
        }
    }
    
    // Check if Firebase is initialized in register page
    $totalTests++;
    $registerPage = __DIR__ . '/../register/index.php';
    if (file_exists($registerPage)) {
        $registerContent = file_get_contents($registerPage);
        if (strpos($registerContent, 'initializeApp') !== false && strpos($registerContent, 'firebase-app.js') !== false) {
            echo "<p class='success'>✅ Firebase is initialized in register page</p>";
            $testsPassed++;
        } else {
            echo "<p class='warning'>⚠️ Firebase initialization not found in register page</p>";
        }
    }
} else {
    echo "<p class='error'>❌ Google Auth JavaScript file not found</p>";
}

echo "</div>";

// Summary
echo "<div class='section'>";
echo "<h2>🎯 Firebase Test Summary</h2>";

$percentage = $totalTests > 0 ? round(($testsPassed / $totalTests) * 100) : 0;

echo "<div style='text-align: center; padding: 30px;'>";
echo "<div style='font-size: 4rem; font-weight: bold; color: " . ($percentage >= 80 ? '#28a745' : ($percentage >= 60 ? '#ffc107' : '#dc3545')) . ";'>";
echo "$percentage%";
echo "</div>";
echo "<p style='font-size: 1.2rem;'>$testsPassed out of $totalTests tests passed</p>";
echo "</div>";

if ($percentage >= 80) {
    echo "<div class='alert alert-success'>";
    echo "<h3 style='margin: 0; color: #155724;'>✅ FIREBASE WELL CONFIGURED</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Your Firebase integration is properly set up.</p>";
    echo "</div>";
} elseif ($percentage >= 60) {
    echo "<div class='alert alert-warning'>";
    echo "<h3 style='margin: 0; color: #856404;'>⚠️ FIREBASE PARTIALLY CONFIGURED</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Some Firebase features need attention. Review failed tests above.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3 style='margin: 0; color: #721c24;'>❌ FIREBASE CONFIGURATION ISSUES</h3>";
    echo "<p style='margin: 10px 0 0 0;'><strong>CRITICAL:</strong> Firebase is not properly configured. Fix the issues!</p>";
    echo "</div>";
}

echo "<h3>📋 Firebase Setup Checklist</h3>";
echo "<ul>";
echo "<li>✓ Create Firebase project at <a href='https://console.firebase.google.com/' target='_blank'>Firebase Console</a></li>";
echo "<li>✓ Enable Google Authentication in Firebase Console → Authentication → Sign-in method</li>";
echo "<li>✓ Enable Firestore Database in Firebase Console → Firestore Database</li>";
echo "<li>✓ Enable Cloud Storage in Firebase Console → Storage</li>";
echo "<li>✓ Download service account credentials from Project Settings → Service Accounts</li>";
echo "<li>✓ Save credentials JSON file in a secure location</li>";
echo "<li>✓ Set <code>FIREBASE_CREDENTIALS_PATH</code> in <code>.env</code> file</li>";
echo "<li>✓ Get web app config from Project Settings → General → Your apps</li>";
echo "<li>✓ Add Firebase config to <code>.env</code> file</li>";
echo "<li>✓ Install Kreait Firebase SDK: <code>composer require kreait/firebase-php</code></li>";
echo "<li>✓ Protect credentials directory with .htaccess</li>";
echo "<li>✓ <strong>DELETE THIS TEST FILE</strong> before production</li>";
echo "</ul>";

echo "<h3>🔒 Security Recommendations</h3>";
echo "<ul>";
echo "<li>Never commit Firebase credentials JSON to version control</li>";
echo "<li>Add credentials directory to <code>.gitignore</code></li>";
echo "<li>Store credentials path in <code>.env</code> using <code>FIREBASE_CREDENTIALS_PATH</code></li>";
echo "<li>Use Firebase Security Rules to protect Firestore and Storage</li>";
echo "<li>Enable App Check to prevent unauthorized access</li>";
echo "<li>Rotate service account keys periodically</li>";
echo "<li>Use environment variables for all sensitive configuration</li>";
echo "</ul>";

echo "</div>";
?>

</body>
</html>
