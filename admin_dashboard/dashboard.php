<?php
// --- CONFIG & SESSION START ---
require_once '../config.php'; 

// --- SESSION SECURITY & ROLE CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    session_destroy();
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// --- API LOGIC ---
header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $conn = getDbConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $fetch_target = $_GET['fetch'] ?? '';
        if ($fetch_target === 'dashboard_stats') {
            $stats = [];
            $stats['total_users'] = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
            $stats['active_doctors'] = $conn->query("SELECT COUNT(*) as c FROM users WHERE role='doctor' AND active=1")->fetch_assoc()['c'];
            
            $role_counts_sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
            $result = $conn->query($role_counts_sql);
            $counts = ['user' => 0, 'doctor' => 0, 'staff' => 0, 'admin' => 0];
             while($row = $result->fetch_assoc()){
                if(array_key_exists($row['role'], $counts)){
                    $counts[$row['role']] = (int)$row['count'];
                }
            }
            $stats['role_counts'] = $counts;

            $low_medicines_stmt = $conn->query("SELECT COUNT(*) as c FROM medicines WHERE quantity <= low_stock_threshold");
            $stats['low_medicines_count'] = $low_medicines_stmt->fetch_assoc()['c'];

            $low_blood_stmt = $conn->query("SELECT COUNT(*) as c FROM blood_inventory WHERE quantity_ml <= low_stock_threshold_ml");
            $stats['low_blood_count'] = $low_blood_stmt->fetch_assoc()['c'];

            $response = ['success' => true, 'data' => $stats];
        } else {
            throw new Exception('Invalid dashboard fetch request.');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
