<?php
/**
 * MedSync Doctor Logic (api.php)
 *
 * This script handles the backend logic for the doctor's dashboard.
 * - It enforces session security.
 * - It now includes ACCURATE backend AJAX handlers for Bed/Room Management based on the provided schema.
 */
require_once '../config.php'; // Contains the database connection ($conn)

// --- Security & Session Management ---
// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login");
    exit();
}

// 2. Verify that the logged-in user has the correct role ('doctor').
if ($_SESSION['role'] !== 'doctor') {
    session_destroy();
    header("Location: ../login/index.php?error=unauthorized");
    exit();
}

// 3. Implement a session timeout.
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    session_unset();
    session_destroy();
    header("Location: ../login/index.php?session_expired=true");
    exit();
}
$_SESSION['loggedin_time'] = time();


// --- Prepare Variables for Frontend ---
$username = htmlspecialchars($_SESSION['username']);
$display_user_id = htmlspecialchars($_SESSION['display_user_id']);


// --- AJAX Request Handler for Occupancy Management ---
if (isset($_REQUEST['action'])) {
    
    header('Content-Type: application/json');

    // Action: Fetch Wards and Rooms for the filter dropdown
    if ($_REQUEST['action'] == 'get_locations') {
        $locations = ['wards' => [], 'rooms' => []];
        
        // Fetch Wards from the 'wards' table
        $ward_result = $conn->query("SELECT id, name FROM wards ORDER BY name ASC");
        if($ward_result) {
            while ($row = $ward_result->fetch_assoc()) {
                $locations['wards'][] = $row;
            }
        }

        // Fetch Rooms from the 'rooms' table
        $room_result = $conn->query("SELECT id, room_number FROM rooms ORDER BY room_number ASC");
        if($room_result) {
            while ($row = $room_result->fetch_assoc()) {
                // Alias 'room_number' as 'name' for frontend consistency
                $locations['rooms'][] = ['id' => $row['id'], 'name' => $row['room_number']];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $locations]);
        exit();
    }

    // Action: Fetch all Beds and Rooms data
    if ($_REQUEST['action'] == 'get_occupancy_data') {
        $occupancy_data = [];

        // Query 1: Get all beds from wards and join with users table for patient info
        $beds_sql = "
            SELECT 'bed' as type, b.id, b.bed_number, b.status, b.ward_id as location_parent_id,
                   w.name as location_name, u.name as patient_name, u.display_user_id as patient_display_id
            FROM beds b
            JOIN wards w ON b.ward_id = w.id
            LEFT JOIN users u ON b.patient_id = u.id
        ";
        $beds_result = $conn->query($beds_sql);
        if ($beds_result) {
            while ($row = $beds_result->fetch_assoc()) {
                $occupancy_data[] = $row;
            }
        }

        // Query 2: Get all rooms and join with users table for patient info
        $rooms_sql = "
            SELECT 'room' as type, r.id, r.room_number as bed_number, r.status, r.id as location_parent_id,
                   'Private Room' as location_name, u.name as patient_name, u.display_user_id as patient_display_id
            FROM rooms r
            LEFT JOIN users u ON r.patient_id = u.id
        ";
        $rooms_result = $conn->query($rooms_sql);
        if ($rooms_result) {
            while ($row = $rooms_result->fetch_assoc()) {
                $occupancy_data[] = $row;
            }
        }

        echo json_encode(['success' => true, 'data' => $occupancy_data]);
        exit();
    }

    // Action: Update status for either a Bed or a Room
    if ($_REQUEST['action'] == 'update_location_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $type = isset($_POST['type']) ? $_POST['type'] : '';
        $new_status = isset($_POST['status']) ? $_POST['status'] : '';
        
        $allowed_statuses = ['available', 'cleaning', 'reserved'];
        $allowed_types = ['bed', 'room'];

        if ($id > 0 && in_array($new_status, $allowed_statuses) && in_array($type, $allowed_types)) {
            $table_name = ($type === 'bed') ? 'beds' : 'rooms';
            
            // When status is changed to available or cleaning, the patient should be unassigned.
            $patient_id_sql = ", patient_id = NULL";

            $stmt = $conn->prepare("UPDATE {$table_name} SET status = ?{$patient_id_sql} WHERE id = ?");
            $stmt->bind_param("si", $new_status, $id);

            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => ucfirst($type) . ' status updated successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: Failed to update status.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data provided for update.']);
        }
        exit();
    }

    // Fallback for any unknown action
    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
    exit();
}
?>