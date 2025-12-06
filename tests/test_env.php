<?php
/**
 * Test Environment Variables
 * 
 * This file tests if environment variables are loaded correctly.
 * Access via: http://localhost/medsync/test_env.php
 * 
 * ⚠️ DELETE THIS FILE AFTER TESTING! It exposes your configuration.
 */

require_once '../config.php';

echo "<h1>Environment Variables Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { color: green; }
    .error { color: red; }
    .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
    .warning { background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin: 10px 0; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
</style>";

echo "<div class='warning'><strong>⚠️ WARNING:</strong> Delete this file after testing! It exposes your configuration.</div>";

// Test Database Variables
echo "<div class='section'>";
echo "<h2>Database Configuration</h2>";
$dbVars = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
foreach ($dbVars as $var) {
    $value = $_ENV[$var] ?? false;
    if ($value !== false) {
        $displayValue = ($var === 'DB_PASS') ? (strlen($value) > 0 ? str_repeat('*', strlen($value)) : '(empty)') : $value;
        echo "<p class='success'>✓ <code>$var</code>: $displayValue</p>";
    } else {
        echo "<p class='error'>✗ <code>$var</code>: NOT SET</p>";
    }
}
echo "</div>";

// Test Firebase Variables
echo "<div class='section'>";
echo "<h2>Firebase Configuration</h2>";
$firebaseVars = [
    'FIREBASE_API_KEY', 
    'FIREBASE_AUTH_DOMAIN', 
    'FIREBASE_PROJECT_ID', 
    'FIREBASE_STORAGE_BUCKET',
    'FIREBASE_MESSAGING_SENDER_ID',
    'FIREBASE_APP_ID'
];
foreach ($firebaseVars as $var) {
    $value = $_ENV[$var] ?? false;
    if ($value !== false) {
        $displayValue = ($var === 'FIREBASE_API_KEY') ? substr($value, 0, 10) . '...' : $value;
        echo "<p class='success'>✓ <code>$var</code>: $displayValue</p>";
    } else {
        echo "<p class='error'>✗ <code>$var</code>: NOT SET</p>";
    }
}
echo "</div>";

// Test reCAPTCHA Variables
echo "<div class='section'>";
echo "<h2>reCAPTCHA Configuration</h2>";
$recaptchaVars = ['RECAPTCHA_SITE_KEY', 'RECAPTCHA_SECRET_KEY'];
foreach ($recaptchaVars as $var) {
    $value = $_ENV[$var] ?? false;
    if ($value !== false) {
        $displayValue = substr($value, 0, 15) . '...';
        echo "<p class='success'>✓ <code>$var</code>: $displayValue</p>";
    } else {
        echo "<p class='error'>✗ <code>$var</code>: NOT SET</p>";
    }
}
// Test constants
echo "<h3>Constants</h3>";
if (defined('RECAPTCHA_SITE_KEY')) {
    echo "<p class='success'>✓ <code>RECAPTCHA_SITE_KEY</code> constant defined</p>";
} else {
    echo "<p class='error'>✗ <code>RECAPTCHA_SITE_KEY</code> constant NOT defined</p>";
}
if (defined('RECAPTCHA_SECRET_KEY')) {
    echo "<p class='success'>✓ <code>RECAPTCHA_SECRET_KEY</code> constant defined</p>";
} else {
    echo "<p class='error'>✗ <code>RECAPTCHA_SECRET_KEY</code> constant NOT defined</p>";
}
echo "</div>";

// Test Chatbot Variable
echo "<div class='section'>";
echo "<h2>Chatbot Configuration</h2>";
$chatbotId = $_ENV['CHATBOT_ID'] ?? false;
if ($chatbotId !== false) {
    echo "<p class='success'>✓ <code>CHATBOT_ID</code>: $chatbotId</p>";
} else {
    echo "<p class='error'>✗ <code>CHATBOT_ID</code>: NOT SET</p>";
}
if (defined('CHATBOT_ID')) {
    echo "<p class='success'>✓ <code>CHATBOT_ID</code> constant defined: " . CHATBOT_ID . "</p>";
} else {
    echo "<p class='error'>✗ <code>CHATBOT_ID</code> constant NOT defined</p>";
}
echo "</div>";

// Test Firebase Helper
echo "<div class='section'>";
echo "<h2>Firebase Helper Test</h2>";
try {
    require_once '../firebase_helper.php';
    if (isset($firebaseConfig) && is_array($firebaseConfig)) {
        echo "<p class='success'>✓ <code>\$firebaseConfig</code> array loaded successfully</p>";
        echo "<pre>" . print_r($firebaseConfig, true) . "</pre>";
    } else {
        echo "<p class='error'>✗ <code>\$firebaseConfig</code> array NOT loaded</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Error loading firebase_helper.php: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test Database Connection
echo "<div class='section'>";
echo "<h2>Database Connection Test</h2>";
try {
    $testConn = new mysqli(
        $_ENV['DB_HOST'] ?? 'localhost', 
        $_ENV['DB_USER'] ?? 'root', 
        $_ENV['DB_PASS'] ?? '', 
        $_ENV['DB_NAME'] ?? 'medsync'
    );
    
    if ($testConn->connect_error) {
        echo "<p class='error'>✗ Connection failed: " . $testConn->connect_error . "</p>";
    } else {
        echo "<p class='success'>✓ Database connected successfully!</p>";
        echo "<p>Server info: " . $testConn->server_info . "</p>";
        $testConn->close();
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Exception: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='warning'><strong>🔥 IMPORTANT:</strong> Delete <code>test_env.php</code> after confirming everything works!</div>";
?>
