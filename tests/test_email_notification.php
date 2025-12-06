<?php
/**
 * Test Email Notification System
 * Use this file to debug email notification issues
 * 
 * Access: http://localhost:8080/medsync/tests/test_email_notification.php
 */

require_once '../config.php';
require_once '../mail/send_mail.php';
require_once '../mail/templates.php';

echo "<h2>MedSync Email Notification Test</h2>";

// Test 1: Check system email configuration
echo "<h3>Test 1: System Email Configuration</h3>";
$conn = getDbConnection();
$system_email = get_system_setting($conn, 'system_email');
$gmail_app_password = get_system_setting($conn, 'gmail_app_password');

if (empty($system_email)) {
    echo "❌ <strong>ERROR:</strong> System email is not configured!<br>";
    echo "👉 Go to Admin Dashboard → System Settings and configure your system email.<br><br>";
} else {
    echo "✅ System Email: " . htmlspecialchars($system_email) . "<br>";
}

if (empty($gmail_app_password)) {
    echo "❌ <strong>ERROR:</strong> Gmail App Password is not configured!<br>";
    echo "👉 Go to Admin Dashboard → System Settings and add your Gmail App Password.<br>";
    echo "👉 Learn how to get one: <a href='https://support.google.com/accounts/answer/185833' target='_blank'>Get App Password</a><br><br>";
} else {
    echo "✅ Gmail App Password: [CONFIGURED - " . strlen($gmail_app_password) . " characters]<br>";
}

// Test 2: Test email template generation
echo "<h3>Test 2: Email Template Generation</h3>";
try {
    $test_changes = [
        'Name' => ['old' => 'John Doe', 'new' => 'Jane Doe'],
        'Email' => ['old' => 'john@example.com', 'new' => 'jane@example.com'],
        'Phone Number' => ['old' => '+911234567890', 'new' => '+919876543210']
    ];
    
    $test_email = getAccountModificationTemplate(
        'Test User', 
        'testuser', 
        $test_changes, 
        date('d M Y, h:i A'), 
        'Admin Test'
    );
    
    if (!empty($test_email)) {
        echo "✅ Email template generated successfully (" . strlen($test_email) . " characters)<br>";
        echo "<details><summary>Click to view template HTML</summary>";
        echo "<textarea style='width:100%; height:300px;'>" . htmlspecialchars($test_email) . "</textarea>";
        echo "</details><br>";
    } else {
        echo "❌ <strong>ERROR:</strong> Email template generation failed!<br><br>";
    }
} catch (Exception $e) {
    echo "❌ <strong>ERROR:</strong> " . $e->getMessage() . "<br><br>";
}

// Test 3: Send test email (only if configuration is complete)
if (!empty($system_email) && !empty($gmail_app_password)) {
    echo "<h3>Test 3: Send Test Email</h3>";
    echo "<form method='POST' style='margin: 20px 0;'>";
    echo "<label>Enter your email address to receive a test notification:</label><br>";
    echo "<input type='email' name='test_email' placeholder='your@email.com' required style='padding: 8px; width: 300px;'>";
    echo "<button type='submit' name='send_test' style='padding: 8px 20px; margin-left: 10px; cursor: pointer;'>Send Test Email</button>";
    echo "</form>";
    
    if (isset($_POST['send_test']) && !empty($_POST['test_email'])) {
        $test_recipient = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
        
        if ($test_recipient) {
            echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0;'>";
            echo "📧 Sending test email to: <strong>" . htmlspecialchars($test_recipient) . "</strong>...<br>";
            
            $test_changes = [
                'Name' => ['old' => 'John Doe', 'new' => 'Jane Doe'],
                'Email' => ['old' => 'john@example.com', 'new' => 'jane@example.com'],
                'Account Status' => ['old' => 'Inactive', 'new' => 'Active']
            ];
            
            $test_body = getAccountModificationTemplate(
                'Test User',
                'testuser',
                $test_changes,
                date('d M Y, h:i A'),
                'System Administrator (Test)'
            );
            
            $result = send_mail('MedSync', $test_recipient, 'TEST: Your MedSync Account Has Been Updated', $test_body);
            
            if ($result) {
                echo "✅ <strong>SUCCESS!</strong> Test email sent successfully!<br>";
                echo "Please check your inbox (and spam folder) at: <strong>" . htmlspecialchars($test_recipient) . "</strong><br>";
            } else {
                echo "❌ <strong>FAILED!</strong> Could not send test email.<br>";
                echo "Check the error log at: <code>medsync/log.txt</code><br>";
            }
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
            echo "❌ Invalid email address!";
            echo "</div>";
        }
    }
} else {
    echo "<h3>Test 3: Send Test Email</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0;'>";
    echo "❌ Cannot send test email: System email configuration is incomplete.";
    echo "</div>";
}

// Test 4: Check PHP requirements
echo "<h3>Test 4: PHP Configuration</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PHPMailer installed: " . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? '✅ Yes' : '❌ No') . "<br>";
echo "OpenSSL enabled: " . (extension_loaded('openssl') ? '✅ Yes' : '❌ No') . "<br>";

echo "<hr>";
echo "<h3>Troubleshooting Tips</h3>";
echo "<ul>";
echo "<li>Make sure you've configured <strong>System Email</strong> in Admin Dashboard → System Settings</li>";
echo "<li>Use a <strong>Gmail App Password</strong>, not your regular Gmail password</li>";
echo "<li>Check if your email is in the spam/junk folder</li>";
echo "<li>Check error logs at: <code>medsync/log.txt</code></li>";
echo "<li>If using Gmail, enable <strong>Less secure app access</strong> or use App Password</li>";
echo "</ul>";

$conn->close();
?>

<style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto; }
    h2 { color: #0067FF; border-bottom: 2px solid #0067FF; padding-bottom: 10px; }
    h3 { color: #333; margin-top: 30px; }
    code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    details { margin: 10px 0; }
    summary { cursor: pointer; color: #0067FF; }
</style>
