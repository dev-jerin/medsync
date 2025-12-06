<?php
/**
 * Database Connection & Structure Test
 * 
 * Tests database connectivity, table existence, and structural integrity
 * Access via: http://localhost/medsync/tests/test_database.php
 * 
 * ⚠️ DELETE THIS FILE IN PRODUCTION! It exposes database structure.
 */

require_once __DIR__ . '/../config.php';

// HTML Header
echo "<!DOCTYPE html>
<html>
<head>
    <title>MedSync Database Test</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; background: #f5f5f5; }
        h1 { color: #0067FF; border-bottom: 3px solid #0067FF; padding-bottom: 10px; }
        h2 { color: #333; margin-top: 30px; background: white; padding: 15px; border-radius: 8px; border-left: 4px solid #0067FF; }
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
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #0067FF; color: white; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #000; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card h3 { color: white; margin: 0 0 10px 0; font-size: 2rem; }
        .stat-card p { margin: 0; opacity: 0.9; }
    </style>
</head>
<body>";

echo "<h1>🗄️ MedSync Database Test Suite</h1>";

echo "<div class='alert alert-warning'>";
echo "<strong>⚠️ SECURITY WARNING:</strong> This file exposes sensitive database information. ";
echo "<strong>DELETE IT</strong> before deploying to production!";
echo "</div>";

$allTestsPassed = true;

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h2>Test 1: Database Connection</h2>";

try {
    $conn = getDbConnection();
    
    if ($conn->connect_error) {
        echo "<p class='error'>❌ Connection failed: " . $conn->connect_error . "</p>";
        $allTestsPassed = false;
    } else {
        echo "<p class='success'>✅ Successfully connected to database</p>";
        echo "<p><strong>Host:</strong> " . htmlspecialchars($_ENV['DB_HOST'] ?? 'localhost') . "</p>";
        echo "<p><strong>Database:</strong> " . htmlspecialchars($_ENV['DB_NAME'] ?? 'medsync') . "</p>";
        echo "<p><strong>User:</strong> " . htmlspecialchars($_ENV['DB_USER'] ?? 'root') . "</p>";
        echo "<p><strong>Server Version:</strong> " . $conn->server_info . "</p>";
        echo "<p><strong>Character Set:</strong> " . $conn->character_set_name() . "</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
    $allTestsPassed = false;
}
echo "</div>";

// Test 2: Check Required Tables
echo "<div class='section'>";
echo "<h2>Test 2: Required Tables Check</h2>";

$requiredTables = [
    'roles', 'role_counters', 'users', 'activity_logs', 'departments', 'specialities',
    'doctors', 'staff', 'callback_requests', 'medicines', 'blood_inventory',
    'wards', 'accommodations', 'admissions', 'appointments', 'transactions',
    'notifications', 'system_settings', 'lab_orders', 'conversations', 'messages',
    'prescriptions', 'prescription_items', 'discharge_clearance', 'feedback',
    'pharmacy_bills', 'ip_tracking', 'ip_blocks', 'patient_encounters'
];

echo "<table>";
echo "<thead><tr><th>Table Name</th><th>Status</th><th>Row Count</th><th>Size (MB)</th></tr></thead>";
echo "<tbody>";

$missingTables = [];
$totalRows = 0;

foreach ($requiredTables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        // Get row count
        $countResult = $conn->query("SELECT COUNT(*) as count FROM `$table`");
        $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
        $totalRows += $count;
        
        // Get table size
        $sizeQuery = "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size 
                      FROM information_schema.TABLES 
                      WHERE table_schema = '" . ($_ENV['DB_NAME'] ?? 'medsync') . "' 
                      AND table_name = '$table'";
        $sizeResult = $conn->query($sizeQuery);
        $size = $sizeResult ? $sizeResult->fetch_assoc()['size'] : '0.00';
        
        echo "<tr>";
        echo "<td><code>$table</code></td>";
        echo "<td><span class='badge badge-success'>✓ EXISTS</span></td>";
        echo "<td>" . number_format($count) . "</td>";
        echo "<td>{$size} MB</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td><code>$table</code></td>";
        echo "<td><span class='badge badge-danger'>✗ MISSING</span></td>";
        echo "<td>-</td>";
        echo "<td>-</td>";
        echo "</tr>";
        $missingTables[] = $table;
        $allTestsPassed = false;
    }
}

echo "</tbody></table>";

if (empty($missingTables)) {
    echo "<p class='success'>✅ All required tables exist (" . count($requiredTables) . " tables)</p>";
} else {
    echo "<p class='error'>❌ Missing tables: " . implode(', ', $missingTables) . "</p>";
}

echo "</div>";

// Test 3: Database Statistics
echo "<div class='section'>";
echo "<h2>Test 3: Database Statistics</h2>";

// Get total database size
$dbSizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size 
                FROM information_schema.TABLES 
                WHERE table_schema = '" . ($_ENV['DB_NAME'] ?? 'medsync') . "'";
$dbSizeResult = $conn->query($dbSizeQuery);
$dbSize = $dbSizeResult ? $dbSizeResult->fetch_assoc()['size'] : 0;

// Get table count
$tableCountQuery = "SELECT COUNT(*) as count FROM information_schema.TABLES 
                    WHERE table_schema = '" . ($_ENV['DB_NAME'] ?? 'medsync') . "'";
$tableCountResult = $conn->query($tableCountQuery);
$tableCount = $tableCountResult ? $tableCountResult->fetch_assoc()['count'] : 0;

echo "<div class='stats'>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);'>";
echo "<h3>{$tableCount}</h3><p>Total Tables</p>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);'>";
echo "<h3>" . number_format($totalRows) . "</h3><p>Total Rows</p>";
echo "</div>";
echo "<div class='stat-card' style='background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);'>";
echo "<h3>{$dbSize} MB</h3><p>Database Size</p>";
echo "</div>";
echo "</div>";

echo "</div>";

// Test 4: Key Table Structure Validation
echo "<div class='section'>";
echo "<h2>Test 4: Critical Table Structure Validation</h2>";

$criticalTables = [
    'users' => ['id', 'display_user_id', 'email', 'password', 'role_id', 'is_active'],
    'roles' => ['id', 'role_name'],
    'appointments' => ['id', 'user_id', 'doctor_id', 'appointment_date', 'status'],
    'admissions' => ['id', 'patient_id', 'doctor_id', 'admission_date', 'discharge_date'],
    'discharge_clearance' => ['id', 'admission_id', 'clearance_step', 'is_cleared'],
    'transactions' => ['id', 'user_id', 'amount', 'type', 'status'],
    'prescriptions' => ['id', 'patient_id', 'doctor_id', 'prescription_date'],
    'lab_orders' => ['id', 'patient_id', 'doctor_id', 'ordered_at'],
    'doctors' => ['id', 'user_id', 'specialty_id', 'qualifications'],
    'staff' => ['id', 'user_id', 'shift', 'assigned_department_id']
];

foreach ($criticalTables as $table => $requiredColumns) {
    echo "<h3>📋 Table: <code>$table</code></h3>";
    
    $columnsQuery = "SHOW COLUMNS FROM `$table`";
    $result = $conn->query($columnsQuery);
    
    if ($result) {
        $existingColumns = [];
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
        
        $missingColumns = array_diff($requiredColumns, $existingColumns);
        
        if (empty($missingColumns)) {
            echo "<p class='success'>✅ All required columns exist</p>";
        } else {
            echo "<p class='error'>❌ Missing columns: " . implode(', ', $missingColumns) . "</p>";
            $allTestsPassed = false;
        }
        
        echo "<p class='info'>Columns: " . implode(', ', $existingColumns) . "</p>";
    } else {
        echo "<p class='error'>❌ Could not retrieve table structure</p>";
        $allTestsPassed = false;
    }
}

echo "</div>";

// Test 5: Foreign Key Constraints
echo "<div class='section'>";
echo "<h2>Test 5: Foreign Key Constraints Check</h2>";

$fkQuery = "SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = '" . ($_ENV['DB_NAME'] ?? 'medsync') . "'
AND REFERENCED_TABLE_NAME IS NOT NULL";

$fkResult = $conn->query($fkQuery);

if ($fkResult && $fkResult->num_rows > 0) {
    echo "<p class='success'>✅ Found " . $fkResult->num_rows . " foreign key constraints</p>";
    echo "<table>";
    echo "<thead><tr><th>Table</th><th>Column</th><th>References</th><th>Constraint</th></tr></thead>";
    echo "<tbody>";
    
    while ($row = $fkResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td><code>{$row['TABLE_NAME']}</code></td>";
        echo "<td><code>{$row['COLUMN_NAME']}</code></td>";
        echo "<td><code>{$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}</code></td>";
        echo "<td><small>{$row['CONSTRAINT_NAME']}</small></td>";
        echo "</tr>";
    }
    
    echo "</tbody></table>";
} else {
    echo "<p class='warning'>⚠️ No foreign key constraints found (this may affect data integrity)</p>";
}

echo "</div>";

// Test 6: Index Analysis
echo "<div class='section'>";
echo "<h2>Test 6: Index Analysis</h2>";

$indexQuery = "SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = '" . ($_ENV['DB_NAME'] ?? 'medsync') . "'
ORDER BY TABLE_NAME, INDEX_NAME";

$indexResult = $conn->query($indexQuery);

if ($indexResult) {
    $indexCount = $indexResult->num_rows;
    echo "<p class='success'>✅ Found {$indexCount} indexes</p>";
    
    // Count primary keys and unique indexes
    $primaryKeys = 0;
    $uniqueIndexes = 0;
    $regularIndexes = 0;
    
    $indexResult->data_seek(0);
    while ($row = $indexResult->fetch_assoc()) {
        if ($row['INDEX_NAME'] === 'PRIMARY') {
            $primaryKeys++;
        } elseif ($row['NON_UNIQUE'] == 0) {
            $uniqueIndexes++;
        } else {
            $regularIndexes++;
        }
    }
    
    echo "<p><strong>Primary Keys:</strong> {$primaryKeys} | ";
    echo "<strong>Unique Indexes:</strong> {$uniqueIndexes} | ";
    echo "<strong>Regular Indexes:</strong> {$regularIndexes}</p>";
} else {
    echo "<p class='warning'>⚠️ Could not retrieve index information</p>";
}

echo "</div>";

// Final Summary
echo "<div class='section'>";
echo "<h2>🎯 Test Summary</h2>";

if ($allTestsPassed) {
    echo "<div class='alert alert-success'>";
    echo "<h3 style='margin: 0; color: #155724;'>✅ ALL TESTS PASSED!</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Your database is properly configured and ready for use.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h3 style='margin: 0; color: #721c24;'>❌ SOME TESTS FAILED</h3>";
    echo "<p style='margin: 10px 0 0 0;'>Please review the errors above and fix the database issues.</p>";
    echo "</div>";
}

echo "<h3>📝 Recommendations</h3>";
echo "<ul>";
echo "<li>Ensure all required tables are created from <code>medsync.sql</code></li>";
echo "<li>Add foreign key constraints to maintain data integrity</li>";
echo "<li>Create indexes on frequently queried columns (user_id, email, status, etc.)</li>";
echo "<li>Regularly backup your database</li>";
echo "<li><strong>DELETE THIS TEST FILE</strong> before production deployment</li>";
echo "</ul>";

echo "</div>";

$conn->close();

echo "</body></html>";
?>
