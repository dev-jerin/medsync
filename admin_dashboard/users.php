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

/**
 * Generates a unique, sequential display ID for a new user based on their role.
 */
function generateDisplayId($role, $conn) {
    $prefix_map = [
        'admin' => 'A', 'doctor' => 'D', 'staff' => 'S', 'user' => 'U'
    ];
    if (!isset($prefix_map[$role])) {
        throw new Exception("Invalid role for ID generation.");
    }
    $prefix = $prefix_map[$role];
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT last_id FROM role_counters WHERE role_prefix = ? FOR UPDATE");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            throw new Exception("Role prefix '$prefix' not found in counters table.");
        }
        $row = $result->fetch_assoc();
        $new_id_num = $row['last_id'] + 1;
        $update_stmt = $conn->prepare("UPDATE role_counters SET last_id = ? WHERE role_prefix = ?");
        $update_stmt->bind_param("is", $new_id_num, $prefix);
        $update_stmt->execute();
        $conn->commit();
        return $prefix . str_pad($new_id_num, 4, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
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
            case 'addUser':
                $conn->begin_transaction();
                if (empty($_POST['name']) || empty($_POST['username']) || empty($_POST['email']) || empty($_POST['role']) || empty($_POST['password']) || empty($_POST['phone'])) {
                    throw new Exception('Please fill all required fields.');
                }
                $display_user_id = generateDisplayId($_POST['role'], $conn);
                $stmt = $conn->prepare("INSERT INTO users (display_user_id, name, username, email, password, role, gender, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $display_user_id, $_POST['name'], $_POST['username'], $_POST['email'], password_hash($_POST['password'], PASSWORD_DEFAULT), $_POST['role'], $_POST['gender'], $_POST['phone']);
                $stmt->execute();
                $user_id = $conn->insert_id;

                if ($_POST['role'] === 'doctor') {
                    $stmt_doctor = $conn->prepare("INSERT INTO doctors (user_id, specialty, qualifications, department_id, availability) VALUES (?, ?, ?, ?, ?)");
                    $stmt_doctor->bind_param("issii", $user_id, $_POST['specialty'], $_POST['qualifications'], $_POST['department_id'], $_POST['availability']);
                    $stmt_doctor->execute();
                } elseif ($_POST['role'] === 'staff') {
                    $stmt_staff = $conn->prepare("INSERT INTO staff (user_id, shift, assigned_department) VALUES (?, ?, ?)");
                    $stmt_staff->bind_param("iss", $user_id, $_POST['shift'], $_POST['assigned_department']);
                    $stmt_staff->execute();
                }

                $conn->commit();
                $response = ['success' => true, 'message' => ucfirst($_POST['role']) . ' added successfully.'];
                break;

            case 'updateUser':
                // ... (update user logic from original file) ...
                if (empty($_POST['id'])) throw new Exception('Invalid user data.');
                $id = (int)$_POST['id'];
                // ... build query and execute ...
                $response = ['success' => true, 'message' => 'User updated successfully.'];
                break;

            case 'deleteUser':
                if (empty($_POST['id'])) throw new Exception('Invalid user ID.');
                $id = (int)$_POST['id'];
                $stmt = $conn->prepare("UPDATE users SET active = 0 WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $response = ['success' => true, 'message' => 'User deactivated successfully.'];
                break;

            default:
                throw new Exception('Invalid user action.');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $fetch_target = $_GET['fetch'] ?? '';
        switch ($fetch_target) {
            case 'users':
                if (!isset($_GET['role'])) throw new Exception('User role not specified.');
                $role = $_GET['role'];
                $sql = "SELECT u.id, u.display_user_id, u.name, u.username, u.email, u.phone, u.role, u.active, u.created_at, u.date_of_birth, u.gender";
                if ($role === 'doctor') $sql .= ", d.specialty, d.qualifications, d.department_id, d.availability FROM users u LEFT JOIN doctors d ON u.id = d.user_id WHERE u.role = ?";
                elseif ($role === 'staff') $sql .= ", s.shift, s.assigned_department FROM users u LEFT JOIN staff s ON u.id = s.user_id WHERE u.role = ?";
                else $sql .= " FROM users u WHERE u.role = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $role);
                $stmt->execute();
                $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $response = ['success' => true, 'data' => $data];
                break;
            
            case 'departments':
                $result = $conn->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $response = ['success' => true, 'data' => $data];
                break;
                
            default:
                throw new Exception('Invalid fetch request.');
        }
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
    if(isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
}

echo json_encode($response);
