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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception('Invalid CSRF token.');
        }

        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'addMedicine':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Medicine added.'];
                break;
            case 'updateMedicine':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Medicine updated.'];
                break;
            case 'deleteMedicine':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Medicine deleted.'];
                break;
            case 'updateBlood':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Blood stock updated.'];
                break;
            case 'addWard':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Ward added.'];
                break;
            case 'updateWard':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Ward updated.'];
                break;
            case 'deleteWard':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Ward deleted.'];
                break;
            case 'addBed':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Bed added.'];
                break;
            case 'updateBed':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Bed updated.'];
                break;
            case 'deleteBed':
                // ... (logic from original file) ...
                $response = ['success' => true, 'message' => 'Bed deleted.'];
                break;
            default:
                throw new Exception('Invalid inventory action.');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $fetch_target = $_GET['fetch'] ?? '';
        switch ($fetch_target) {
            case 'medicines':
                $result = $conn->query("SELECT * FROM medicines ORDER BY name ASC");
                $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];
                break;
            case 'blood_inventory':
                $result = $conn->query("SELECT * FROM blood_inventory ORDER BY blood_group ASC");
                $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];
                break;
            case 'wards':
                $result = $conn->query("SELECT id, name, capacity, description, is_active FROM wards ORDER BY name ASC");
                $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];
                break;
            case 'beds':
                $sql = "SELECT b.id, b.ward_id, w.name as ward_name, b.bed_number, b.status, b.patient_id, u.name as patient_name, b.occupied_since, b.price_per_day FROM beds b JOIN wards w ON b.ward_id = w.id LEFT JOIN users u ON b.patient_id = u.id ORDER BY w.name, b.bed_number ASC";
                $result = $conn->query($sql);
                $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];
                break;
            case 'patients_for_beds':
                $result = $conn->query("SELECT id, name, display_user_id FROM users WHERE role = 'user' AND active = 1 ORDER BY name ASC");
                $response = ['success' => true, 'data' => $result->fetch_all(MYSQLI_ASSOC)];
                break;
            default:
                throw new Exception('Invalid inventory fetch request.');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
