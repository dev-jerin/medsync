<?php
/**
 * MedSync Test Suite Dashboard
 * 
 * Central hub for accessing all test files
 * Access via: http://localhost/medsync/tests/
 * 
 * ⚠️ DELETE THIS FOLDER IN PRODUCTION!
 */

// Scan for test files
$testFiles = glob(__DIR__ . '/test_*.php');
$tests = [];

foreach ($testFiles as $file) {
    $filename = basename($file);
    $name = str_replace(['test_', '.php', '_'], ['', '', ' '], $filename);
    $name = ucwords($name);
    
    // Get file size and last modified
    $size = filesize($file);
    $modified = filemtime($file);
    
    $tests[] = [
        'file' => $filename,
        'name' => $name,
        'size' => $size,
        'modified' => $modified,
        'description' => getTestDescription($filename)
    ];
}

// Sort by name
usort($tests, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

function getTestDescription($filename) {
    $descriptions = [
        'test_api_endpoints.php' => 'Validates all API endpoints across admin, doctor, staff, and user roles',
        'test_auth.php' => 'Tests authentication and authorization (login, sessions, RBAC, password security)',
        'test_database.php' => 'Validates database structure, tables, columns, foreign keys, and indexes',
        'test_email_notification.php' => 'Tests email notification system and PHPMailer configuration',
        'test_env.php' => 'Validates .env file configuration and environment variables',
        'test_firebase.php' => 'Tests Firebase integration, credentials, SDK, and client-side setup',
        'test_security.php' => 'Security configuration tests (PHP settings, session, XSS, CSRF, file uploads)',
    ];
    
    return $descriptions[$filename] ?? 'Test suite';
}

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedSync Test Suite</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 2rem;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #667eea;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        .alert {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem 1.5rem;
            margin-top: 1rem;
            border-radius: 4px;
        }
        
        .alert strong {
            color: #856404;
            display: block;
            margin-bottom: 0.25rem;
        }
        
        .alert p {
            color: #856404;
            font-size: 0.95rem;
            margin: 0;
        }
        
        .stats {
            background: white;
            padding: 1.5rem 2rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            border-top: 1px solid #e0e0e0;
        }
        
        .stat-card {
            text-align: center;
        }
        
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-card .label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .test-grid {
            background: white;
            padding: 2rem;
            border-radius: 0 0 12px 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .test-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }
        
        .test-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .test-card h3 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .test-card .icon {
            font-size: 1.5rem;
        }
        
        .test-card p {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .test-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: #999;
            flex-wrap: wrap;
        }
        
        .test-meta span {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.3s ease;
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .footer {
            text-align: center;
            color: white;
            margin-top: 2rem;
            font-size: 0.9rem;
        }
        
        .footer a {
            color: white;
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .btn {
                position: static;
                display: block;
                margin-top: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧪 MedSync Test Suite</h1>
            <p>Comprehensive testing dashboard for the MedSync Hospital Management System</p>
            
            <div class="alert">
                <strong>⚠️ CRITICAL SECURITY WARNING</strong>
                <p>This test suite folder must be <strong>DELETED</strong> before deploying to production! These files expose system configuration and security details.</p>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="number"><?php echo count($tests); ?></div>
                <div class="label">Test Suites</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo formatBytes(array_sum(array_column($tests, 'size'))); ?></div>
                <div class="label">Total Size</div>
            </div>
            <div class="stat-card">
                <div class="number"><?php echo date('M d, Y'); ?></div>
                <div class="label">Last Updated</div>
            </div>
        </div>
        
        <div class="test-grid">
            <?php foreach ($tests as $test): ?>
            <div class="test-card" onclick="window.location.href='<?php echo htmlspecialchars($test['file']); ?>'">
                <a href="<?php echo htmlspecialchars($test['file']); ?>" class="btn">Run Test →</a>
                
                <h3>
                    <span class="icon">
                        <?php
                        $icons = [
                            'Api Endpoints' => '🔌',
                            'Auth' => '🔐',
                            'Database' => '🗄️',
                            'Email Notification' => '📧',
                            'Env' => '⚙️',
                            'Firebase' => '🔥',
                            'Security' => '🛡️',
                        ];
                        echo $icons[$test['name']] ?? '📋';
                        ?>
                    </span>
                    <?php echo htmlspecialchars($test['name']); ?>
                </h3>
                
                <p><?php echo htmlspecialchars($test['description']); ?></p>
                
                <div class="test-meta">
                    <span>📄 <?php echo htmlspecialchars($test['file']); ?></span>
                    <span>📦 <?php echo formatBytes($test['size']); ?></span>
                    <span>🕒 Modified: <?php echo date('M d, Y H:i', $test['modified']); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer">
            <p>MedSync Hospital Management System &copy; <?php echo date('Y'); ?></p>
            <p><a href="../">← Back to Main Site</a></p>
        </div>
    </div>
</body>
</html>
