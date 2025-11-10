<?php
/**
 * MedSync User/Patient Logic (user/api.php)
 *
 * Handles backend logic for the patient dashboard shell and AJAX requests.
 * - Enforces session security and role-based access control.
 * - Initializes session variables for the frontend header and profile.
 * - Manages session timeout for security.
 * - Provides API endpoints for dashboard data, notifications, profile management, and live tokens.
 */

require_once '../config.php'; // Includes session_start() and database connection ($conn)
require_once '../vendor/autoload.php'; // Assuming Composer autoload for Dompdf
use Dompdf\Dompdf;
use Dompdf\Options;

function log_user_activity($conn, $user_id, $action, $details = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    }
}

function getReceiptHtml($receipt_data) {
    // Prepare variables for cleaner HTML
    $patient_name = htmlspecialchars($receipt_data['patient_name'] ?? 'N/A');
    $display_user_id = htmlspecialchars($receipt_data['display_user_id'] ?? 'N/A');
    $receipt_id = htmlspecialchars($receipt_data['id']);
    $payment_date = htmlspecialchars(date('F j, Y, g:i A', strtotime($receipt_data['paid_at'])));
    $payment_mode = htmlspecialchars(ucfirst($receipt_data['payment_mode'])); // Capitalize first letter
    $description = htmlspecialchars($receipt_data['description']);
    $amount = htmlspecialchars(number_format($receipt_data['amount'], 2));

    // Use HEREDOC for the template
    return <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Payment Receipt</title>
        <style>
            /* Using DejaVu Sans as it supports more characters, including '₹' */
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { margin: 0; }
            .header p { margin: 5px 0; }
            
            .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .info-table td { padding: 8px; border: 1px solid #ddd; }
            .info-table td:first-child { background-color: #f2f2f2; font-weight: bold; width: 150px; }
            
            h2 { border-bottom: 2px solid #4a90e2; padding-bottom: 5px; margin-top: 25px; color: #4a90e2; }
            
            .charges-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .charges-table th, .charges-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            .charges-table th { background-color: #f2f2f2; }
            .total-row td { font-weight: bold; font-size: 1.1em; text-align: right; }
            .status-paid { color: #28a745; font-weight: bold; font-size: 1.2em; }

            .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #777; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Calysta Health Institute</h1>
            <p>Kerala, India</p>
            <h1>Payment Receipt</h1>
        </div>

        <h2>Receipt Details</h2>
        <table class="info-table">
            <tr>
                <td>Patient Name</td>
                <td>{$patient_name}</td>
            </tr>
            <tr>
                <td>Patient ID</td>
                <td>{$display_user_id}</td>
            </tr>
             <tr>
                <td>Receipt ID</td>
                <td>TXN{$receipt_id}</td>
            </tr>
            <tr>
                <td>Payment Date</td>
                <td>{$payment_date}</td>
            </tr>
             <tr>
                <td>Payment Mode</td>
                <td>{$payment_mode}</td>
            </tr>
        </table>

        <h2>Charges</h2>
        <table class="charges-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{$description}</td>
                    <td>₹{$amount}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Paid</td>
                    <td>₹{$amount}</td>
                </tr>
            </tbody>
        </table>
        
        <p style="text-align: center; margin-top: 20px;">
            Status: <span class="status-paid">PAID</span>
        </p>

        <div class="footer">
            This is a computer-generated document. Thank you for your payment.
        </div>
    </body>
    </html>
    HTML;
}

// --- Security & Session Management ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in again.']);
        exit();
    }
    header("Location: ../login/index.php?error=unauthorized");
    exit();
}

// 2. Implement session timeout.
$session_timeout = 1800; // 30 minutes
if (isset($_SESSION['loggedin_time']) && (time() - $_SESSION['loggedin_time'] > $session_timeout)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
        exit();
    }
    session_unset();
    session_destroy();
    header("Location: ../login/index.php?error=session_expired");
    exit();
}
$_SESSION['loggedin_time'] = time(); // Reset timeout counter

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$conn = getDbConnection();

// --- AJAX API Endpoint Logic ---
if (isset($_GET['action']) || isset($_POST['action'])) {

    // --- Special Case for PDF Download ---
    if (isset($_GET['action']) && $_GET['action'] == 'download_discharge_summary' && isset($_GET['id'])) {
        $summary_id = (int)$_GET['id'];
        
        // UPDATED QUERY: Fetch more details for the PDF template
        $stmt = $conn->prepare("
            SELECT 
                dc.*, 
                p.name as patient_name,
                p.display_user_id,
                a.admission_date,
                COALESCE(a.discharge_date, dc.discharge_date) as discharge_date,
                doc.name as doctor_name
            FROM discharge_clearance dc
            JOIN admissions a ON dc.admission_id = a.id
            JOIN users p ON a.patient_id = p.id
            LEFT JOIN users doc ON dc.doctor_id = doc.id
            WHERE dc.id = ? AND p.id = ?
        ");
        $stmt->bind_param("ii", $summary_id, $user_id);
        $stmt->execute();
        $summary_data = $stmt->get_result()->fetch_assoc();
        
        if ($summary_data) {
            log_user_activity($conn, $user_id, 'downloaded_summary', "Downloaded discharge summary for admission on " . date('M j, Y', strtotime($summary_data['admission_date'])));
            
            // Check if summary_template.php exists before including
            if (file_exists('summary_template.php')) {
                ob_start();
                include 'summary_template.php';
                $html = ob_get_clean();

                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true); // Good for images if you add them later
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                // Use a more descriptive filename
                $filename = "Discharge_Summary_" . htmlspecialchars($summary_data['patient_name']) . "_" . $summary_data['id'] . ".pdf";
                $dompdf->stream($filename, array("Attachment" => 1)); // 1 = force download
                exit();
            } else {
                 die('Error: PDF template file is missing.');
            }
        } else {
            die('Summary not found or you do not have permission to access it.');
        }
    }

    // --- Special Case for Lab Report PDF Download ---
    if (isset($_GET['action']) && $_GET['action'] == 'download_lab_report' && isset($_GET['id'])) {
        $lab_result_id = (int)$_GET['id'];

        $stmt = $conn->prepare("
            SELECT 
                lo.*,
                p.name AS patient_name,
                p.display_user_id,
                doc.name AS doctor_name
            FROM lab_orders lo
            JOIN users p ON lo.patient_id = p.id
            LEFT JOIN users doc ON lo.doctor_id = doc.id
            WHERE lo.id = ? AND p.id = ?
        ");
        $stmt->bind_param("ii", $lab_result_id, $user_id);
        $stmt->execute();
        $lab_data = $stmt->get_result()->fetch_assoc();

        if ($lab_data) {
            log_user_activity($conn, $user_id, 'downloaded_lab_report', "Downloaded lab report for '{$lab_data['test_name']}'.");

            if (file_exists('lab_report_pdf_template.php')) {
                ob_start();
                include 'lab_report_pdf_template.php';
                $html = ob_get_clean();

                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $options->set('isRemoteEnabled', true); 
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                
                $filename = "Lab_Report_" . str_replace(' ', '_', $lab_data['test_name']) . "_" . $lab_data['id'] . ".pdf";
                $dompdf->stream($filename, array("Attachment" => 1));
                exit();
            } else {
                 die('Error: Lab report PDF template file is missing.');
            }
        } else {
            die('Lab result not found or you do not have permission to access it.');
        }
    }

    // --- Special Case for Prescription PDF Download ---
    if (isset($_GET['action']) && $_GET['action'] == 'download_prescription' && isset($_GET['id'])) {
        $prescription_id = (int)$_GET['id'];
    
        // 1. Fetch main prescription data
        $stmt_main = $conn->prepare("
            SELECT 
                p.*, 
                pat.name as patient_name,
                pat.display_user_id,
                doc.name as doctor_name,
                d.qualifications as doctor_qualifications,
                sp.name as doctor_specialty
            FROM prescriptions p
            JOIN users pat ON p.patient_id = pat.id
            JOIN users doc ON p.doctor_id = doc.id
            LEFT JOIN doctors d ON doc.id = d.user_id
            LEFT JOIN specialities sp ON d.specialty_id = sp.id
            WHERE p.id = ? AND p.patient_id = ?
        ");
        $stmt_main->bind_param("ii", $prescription_id, $user_id);
        $stmt_main->execute();
        $prescription_data = $stmt_main->get_result()->fetch_assoc();
        $stmt_main->close();
    
        if ($prescription_data) {
            // 2. Fetch prescription items
            $stmt_items = $conn->prepare("
                SELECT 
                    pi.dosage,
                    pi.frequency,
                    pi.quantity_prescribed,
                    m.name as medicine_name
                FROM prescription_items pi
                JOIN medicines m ON pi.medicine_id = m.id
                WHERE pi.prescription_id = ?
            ");
            $stmt_items->bind_param("i", $prescription_id);
            $stmt_items->execute();
            $prescription_data['items'] = $stmt_items->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_items->close();
            
            log_user_activity($conn, $user_id, 'downloaded_prescription', "Downloaded prescription ID: {$prescription_id}.");
    
            // 3. Check for template and render PDF
            if (file_exists('prescription_template.php')) {
                ob_start();
                include 'prescription_template.php';
                $html = ob_get_clean();
    
                $options = new Options();
                $options->set('isHtml5ParserEnabled', true);
                $dompdf = new Dompdf($options);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $filename = "Prescription_" . $prescription_data['patient_name'] . "_" . $prescription_data['id'] . ".pdf";
                $dompdf->stream($filename, array("Attachment" => 1));
                exit();
            } else {
                die('Error: Prescription PDF template file is missing.');
            }
        } else {
            die('Prescription not found or you do not have permission to access it.');
        }
    }

    // --- Special Case for Receipt PDF Download ---
    if (isset($_GET['action']) && $_GET['action'] == 'download_receipt' && isset($_GET['id'])) {
        $receipt_id = (int)$_GET['id'];
    
        $stmt = $conn->prepare("
            SELECT 
                t.id, t.created_at, t.description, t.amount, t.status, t.paid_at, t.payment_mode,
                p.name as patient_name,
                p.display_user_id
            FROM transactions t
            JOIN users p ON t.user_id = p.id
            WHERE t.id = ? AND p.id = ? AND t.status = 'paid'
        ");
        $stmt->bind_param("ii", $receipt_id, $user_id);
        $stmt->execute();
        $receipt_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    
        if ($receipt_data) {
            log_user_activity($conn, $user_id, 'downloaded_receipt', "Downloaded receipt ID: {$receipt_id}.");
    
            $html = getReceiptHtml($receipt_data);

            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $filename = "Receipt_TXN" . $receipt_data['id'] . "_" . $receipt_data['patient_name'] . ".pdf";
            $dompdf->stream($filename, array("Attachment" => 1));
            exit();

        } else {
            die('Receipt not found, is not paid, or you do not have permission to access it.');
        }
    }


    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        // Handle GET requests for fetching data
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                
                case 'get_billing_data':
                    $summary = [
                        'outstanding_balance' => 0.00,
                        'last_payment_amount' => 0.00,
                        'last_payment_date' => 'N/A'
                    ];

                    $stmt_due = $conn->prepare("SELECT COALESCE(SUM(amount), 0.00) as total_due FROM transactions WHERE user_id = ? AND status = 'pending'");
                    $stmt_due->bind_param("i", $user_id);
                    $stmt_due->execute();
                    $summary['outstanding_balance'] = $stmt_due->get_result()->fetch_assoc()['total_due'];
                    $stmt_due->close();

                    $stmt_last_paid = $conn->prepare("SELECT amount, paid_at FROM transactions WHERE user_id = ? AND status = 'paid' ORDER BY paid_at DESC LIMIT 1");
                    $stmt_last_paid->bind_param("i", $user_id);
                    $stmt_last_paid->execute();
                    $last_payment = $stmt_last_paid->get_result()->fetch_assoc();
                    if ($last_payment) {
                        $summary['last_payment_amount'] = $last_payment['amount'];
                        $summary['last_payment_date'] = date('M j, Y', strtotime($last_payment['paid_at']));
                    }
                    $stmt_last_paid->close();

                    $sql_history = "SELECT id, created_at, description, amount, status, paid_at FROM transactions WHERE user_id = ?";
                    $params = [$user_id];
                    $types = "i";

                    if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'paid', 'due'])) {
                        $db_status = ($_GET['status'] === 'due') ? 'pending' : $_GET['status'];
                        $sql_history .= " AND status = ?";
                        $params[] = $db_status;
                        $types .= "s";
                    }

                    if (!empty($_GET['date'])) {
                        $date_filter = $_GET['date'] . '-%';
                        $sql_history .= " AND created_at LIKE ?";
                        $params[] = $date_filter;
                        $types .= "s";
                    }
                    
                    $sql_history .= " ORDER BY created_at DESC";

                    $stmt_history = $conn->prepare($sql_history);
                    $stmt_history->bind_param($types, ...$params);
                    $stmt_history->execute();
                    $history = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_history->close();

                    $response = ['success' => true, 'data' => ['summary' => $summary, 'history' => $history]];
                    break;
                
                case 'get_bill_details':
                    if (!isset($_GET['bill_id'])) {
                        throw new Exception("Bill ID is required.");
                    }
                    $bill_id = (int)$_GET['bill_id'];

                    $stmt = $conn->prepare("
                        SELECT 
                            t.id, t.created_at, t.description, t.amount, t.status, t.paid_at,
                            u.name as patient_name
                        FROM transactions t
                        JOIN users u ON t.user_id = u.id
                        WHERE t.id = ? AND t.user_id = ?
                    ");
                    $stmt->bind_param("ii", $bill_id, $user_id);
                    $stmt->execute();
                    $bill_details = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if ($bill_details) {
                        $response = ['success' => true, 'data' => $bill_details];
                    } else {
                        throw new Exception("Bill not found or access denied.");
                    }
                    break;
                
                case 'get_discharge_summaries':
                    $stmt = $conn->prepare("
                        SELECT 
                            MAX(dc.id) as id,
                            a.admission_date,
                            COALESCE(a.discharge_date, MAX(dc.discharge_date)) as discharge_date,
                            MAX(doc.name) as doctor_name,
                            MAX(dept.name) as department_name,
                            MAX(dc.summary_text) as summary_text,
                            MAX(dc.notes) as notes
                        FROM discharge_clearance dc
                        JOIN admissions a ON dc.admission_id = a.id
                        LEFT JOIN users doc ON dc.doctor_id = doc.id
                        LEFT JOIN doctors dr ON doc.id = dr.user_id
                        LEFT JOIN departments dept ON dr.department_id = dept.id
                        WHERE a.patient_id = ? AND (a.discharge_date IS NOT NULL OR dc.discharge_date IS NOT NULL)
                        GROUP BY a.id
                        ORDER BY discharge_date DESC
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $summaries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $summaries];
                    break;
                
                case 'get_dashboard_data':
                    $dashboard_data = [];
                    $stmt_app = $conn->prepare("
                        SELECT 
                            a.appointment_date, 
                            u.name as doctorName, 
                            u.profile_picture as avatar, 
                            sp.name as specialty
                        FROM appointments a
                        JOIN users u ON a.doctor_id = u.id
                        JOIN doctors d ON u.id = d.user_id
                        LEFT JOIN specialities sp ON d.specialty_id = sp.id
                        WHERE a.user_id = ? AND a.appointment_date >= CURDATE() AND a.status = 'scheduled'
                        ORDER BY a.appointment_date ASC LIMIT 2
                    ");
                    $stmt_app->bind_param("i", $user_id);
                    $stmt_app->execute();
                    $dashboard_data['appointments'] = $stmt_app->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_app->close();

                    $stmt_act = $conn->prepare("
                        SELECT action, details, created_at as time 
                        FROM activity_logs 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ");
                    $stmt_act->bind_param("i", $user_id);
                    $stmt_act->execute();
                    $dashboard_data['activity'] = $stmt_act->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_act->close();

                    $stmt_token = $conn->prepare("
                        SELECT a.token_number AS yours, u.name AS doctorName,
                            (
                                SELECT COALESCE(
                                    (SELECT token_number 
                                     FROM appointments 
                                     WHERE doctor_id = a.doctor_id AND DATE(appointment_date) = CURDATE() AND token_status = 'in_consultation' 
                                     LIMIT 1),
                                    (SELECT MAX(token_number) 
                                     FROM appointments 
                                     WHERE doctor_id = a.doctor_id AND DATE(appointment_date) = CURDATE() AND token_status IN ('completed', 'skipped')),
                                    0
                                )
                            ) AS current
                        FROM appointments a JOIN users u ON a.doctor_id = u.id
                        WHERE a.user_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled' LIMIT 1
                    ");
                    $stmt_token->bind_param("i", $user_id);
                    $stmt_token->execute();
                    $token_result = $stmt_token->get_result();
                    $dashboard_data['token'] = $token_result->num_rows > 0 ? $token_result->fetch_assoc() : null;
                    $stmt_token->close();
                    
                    $response = ['success' => true, 'data' => $dashboard_data];
                    break;

                case 'get_lab_results':
                    $sql = "
                        SELECT 
                            lo.id,
                            lo.test_date,
                            lo.test_name,
                            lo.status,
                            lo.result_details,
                            doc.name AS doctor_name
                        FROM lab_orders lo
                        LEFT JOIN users doc ON lo.doctor_id = doc.id
                        WHERE lo.patient_id = ?
                    ";
                
                    $params = [$user_id];
                    $types = "i";
                
                    if (!empty($_GET['search'])) {
                        $search_term = '%' . $_GET['search'] . '%';
                        $sql .= " AND lo.test_name LIKE ?";
                        $params[] = $search_term;
                        $types .= "s";
                    }
                
                    if (!empty($_GET['date'])) {
                        $date_filter = $_GET['date'] . '-%';
                        $sql .= " AND lo.test_date LIKE ?";
                        $params[] = $date_filter;
                        $types .= "s";
                    }
                
                    $sql .= " ORDER BY lo.test_date DESC";
                
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param($types, ...$params); 
                    $stmt->execute();
                    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $results];
                    break;

                case 'get_medical_records':
                    $records = [];
                    $sql = "
                        -- Admissions
                        SELECT
                            a.id,
                            a.admission_date AS record_date,
                            'admission' AS record_type,
                            d.name AS title,
                            CONCAT('Admitted under Dr. ', doc.name) AS details,
                            CASE WHEN a.discharge_date IS NULL THEN 'Admitted' ELSE 'Discharged' END AS status
                        FROM admissions a
                        JOIN users doc ON a.doctor_id = doc.id
                        JOIN doctors dr ON doc.id = dr.user_id
                        JOIN departments d ON dr.department_id = d.id
                        WHERE a.patient_id = ?

                        UNION ALL

                        -- Lab Results
                        SELECT
                            lo.id,
                            lo.test_date AS record_date,
                            'lab_result' AS record_type,
                            lo.test_name AS title,
                            CONCAT('Ordered by Dr. ', doc.name) AS details,
                            lo.status AS status
                        FROM lab_orders lo
                        LEFT JOIN users doc ON lo.doctor_id = doc.id
                        WHERE lo.patient_id = ? AND lo.status = 'completed'

                        UNION ALL

                        -- Prescriptions
                        SELECT
                            p.id,
                            p.prescription_date AS record_date,
                            'prescription' AS record_type,
                            'New Prescription Issued' AS title,
                            CONCAT('Prescribed by Dr. ', doc.name) AS details,
                            p.status AS status
                        FROM prescriptions p
                        JOIN users doc ON p.doctor_id = doc.id
                        WHERE p.patient_id = ?

                        ORDER BY record_date DESC;
                    ";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $records = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $records];
                    break;

                case 'get_notifications':
                    $filter = $_GET['filter'] ?? 'all';
                    $sql = "SELECT id, message, is_read, created_at as timestamp FROM notifications 
                            WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all')";
                    if ($filter === 'unread') $sql .= " AND is_read = 0";
                    $sql .= " ORDER BY created_at DESC";

                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("is", $user_id, $user_role);
                    $stmt->execute();
                    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $stmt_count = $conn->prepare("SELECT COUNT(id) as unread_count FROM notifications WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all') AND is_read = 0");
                    $stmt_count->bind_param("is", $user_id, $user_role);
                    $stmt_count->execute();
                    $unread_count = $stmt_count->get_result()->fetch_assoc()['unread_count'];
                    $stmt_count->close();

                    foreach ($notifications as &$notification) {
                        if (strpos(strtolower($notification['message']), 'lab') !== false) $notification['type'] = 'labs';
                        elseif (strpos(strtolower($notification['message']), 'bill') !== false || strpos(strtolower($notification['message']), 'payment') !== false) $notification['type'] = 'billing';
                        elseif (strpos(strtolower($notification['message']), 'prescription') !== false) $notification['type'] = 'prescriptions';
                        else $notification['type'] = 'appointments';
                    }
                    $response = ['success' => true, 'notifications' => $notifications, 'unread_count' => $unread_count];
                    break;
                
                case 'get_live_tokens':
                    $sql_tokens = "SELECT 
                                       a.token_number, 
                                       a.token_status, 
                                       a.doctor_id, 
                                       u.name as doctor_name, 
                                       sp.name as specialty,
                                       d.office_floor,
                                       d.office_room_number
                                   FROM appointments a
                                   JOIN users u ON a.doctor_id = u.id
                                   LEFT JOIN doctors d ON u.id = d.user_id
                                   LEFT JOIN specialities sp ON d.specialty_id = sp.id
                                   WHERE a.user_id = ? AND DATE(a.appointment_date) = CURDATE() AND a.status = 'scheduled'";
                    
                    $stmt_tokens = $conn->prepare($sql_tokens);
                    $stmt_tokens->bind_param("i", $user_id);
                    $stmt_tokens->execute();
                    $todays_appointments = $stmt_tokens->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt_tokens->close();

                    if (empty($todays_appointments)) {
                        $response = ['success' => true, 'tokens' => [], 'message' => "No active tokens for today."];
                        break;
                    }

                    $tokens = [];
                    
                    $current_token_sql = "
                        SELECT COALESCE(
                            (SELECT token_number 
                             FROM appointments 
                             WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND token_status = 'in_consultation' 
                             LIMIT 1),
                            (SELECT MAX(token_number) 
                             FROM appointments 
                             WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND token_status IN ('completed', 'skipped')),
                            0
                        ) as current_token
                    ";
                    
                    $total_patients_sql = "SELECT COUNT(id) as total_patients 
                                           FROM appointments 
                                           WHERE doctor_id = ? AND DATE(appointment_date) = CURDATE() AND status = 'scheduled'";

                    $current_token_stmt = $conn->prepare($current_token_sql);
                    $total_patients_stmt = $conn->prepare($total_patients_sql);
                    
                    foreach ($todays_appointments as $appointment) {
                        
                        $current_token_stmt->bind_param("ii", $appointment['doctor_id'], $appointment['doctor_id']);
                        $current_token_stmt->execute();
                        $current_token_result = $current_token_stmt->get_result()->fetch_assoc();
                        $current_serving_token = $current_token_result['current_token'];

                        $consultation_time = null;
                        if ($current_token_result['current_token'] > 0) {
                            $stmt_time = $conn->prepare("
                                SELECT consultation_start_time 
                                FROM appointments 
                                WHERE doctor_id = ? 
                                  AND DATE(appointment_date) = CURDATE() 
                                  AND token_number = ? 
                                  AND token_status = 'in_consultation'
                            ");
                            $stmt_time->bind_param("ii", $appointment['doctor_id'], $current_token_result['current_token']);
                            $stmt_time->execute();
                            $time_res = $stmt_time->get_result()->fetch_assoc();
                            if ($time_res) {
                                $consultation_time = $time_res['consultation_start_time'];
                            }
                            $stmt_time->close();
                        }

                        $total_patients_stmt->bind_param("i", $appointment['doctor_id']);
                        $total_patients_stmt->execute();
                        $total_patients_result = $total_patients_stmt->get_result()->fetch_assoc();
                        
                        $patients_ahead_sql = "SELECT COUNT(id) as count 
                                               FROM appointments 
                                               WHERE doctor_id = ? 
                                               AND DATE(appointment_date) = CURDATE() 
                                               AND status = 'scheduled'
                                               AND token_number > ? 
                                               AND token_number < ?";
                        
                        $patients_ahead_stmt = $conn->prepare($patients_ahead_sql);
                        $your_token = $appointment['token_number'];
                        $patients_ahead_stmt->bind_param("iii", $appointment['doctor_id'], $current_serving_token, $your_token);
                        $patients_ahead_stmt->execute();
                        $patients_ahead_count = $patients_ahead_stmt->get_result()->fetch_assoc()['count'];
                        $patients_ahead_stmt->close();
                        
                        $patients_left = $total_patients_result['total_patients'] - $current_serving_token;

                        $tokens[] = [
                            'your_token' => $appointment['token_number'],
                            'current_token' => $current_token_result['current_token'],
                            'consultation_start_time' => $consultation_time,
                            'doctor_name' => $appointment['doctor_name'],
                            'specialty' => $appointment['specialty'],
                            'token_status' => $appointment['token_status'],
                            'office_floor' => $appointment['office_floor'],
                            'office_room_number' => $appointment['office_room_number'],
                            'total_patients' => $total_patients_result['total_patients'],
                            'patients_left' => max(0, $patients_left),
                            'patients_ahead' => $patients_ahead_count
                        ];
                    }
                    $current_token_stmt->close();
                    $total_patients_stmt->close();
                    
                    $response = ['success' => true, 'tokens' => $tokens, 'message' => "Live token status fetched successfully."];
                    break;
                
                case 'get_appointments':
                    $appointments = [
                        'upcoming' => [],
                        'past' => []
                    ];
                
                    $sql = "SELECT 
                                a.id, 
                                a.appointment_date, 
                                a.token_number, 
                                a.status,
                                doc_user.name as doctor_name,
                                sp.name AS specialty
                            FROM appointments a
                            JOIN users doc_user ON a.doctor_id = doc_user.id
                            JOIN doctors d ON doc_user.id = d.user_id
                            LEFT JOIN specialities sp ON d.specialty_id = sp.id
                            WHERE a.user_id = ?
                            ORDER BY a.appointment_date DESC";
                    
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                
                    while ($row = $result->fetch_assoc()) {
                        $is_upcoming = (new DateTime($row['appointment_date']) >= new DateTime('today')) && ($row['status'] == 'scheduled');
                        if ($is_upcoming) {
                            $appointments['upcoming'][] = $row;
                        } else {
                            $appointments['past'][] = $row;
                        }
                    }
                    $stmt->close();
                    usort($appointments['upcoming'], function($a, $b) {
                        return strtotime($a['appointment_date']) - strtotime($b['appointment_date']);
                    });
                
                    $response = ['success' => true, 'data' => $appointments];
                    break;
                
                case 'get_login_activity':
                    $stmt = $conn->prepare("
                        SELECT ip_address, login_time 
                        FROM ip_tracking 
                        WHERE user_id = ? 
                        ORDER BY login_time DESC 
                        LIMIT 5
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $activity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => $activity];
                    break;

                case 'get_specialties':
                    $stmt = $conn->prepare("SELECT name FROM specialities ORDER BY name ASC");
                    $stmt->execute();
                    $specialties = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $specialties];
                    break;
                
                case 'get_doctors':
                    $specialty_filter = $_GET['specialty'] ?? '';
                    $name_search = $_GET['name_search'] ?? '';

                    $sql = "SELECT
                                d.user_id as id,
                                u.name,
                                sp.name AS specialty,
                                u.profile_picture,
                                d.slots 
                            FROM doctors d
                            JOIN users u ON d.user_id = u.id
                            LEFT JOIN specialities sp ON d.specialty_id = sp.id
                            WHERE d.is_available = 1 AND u.is_active = 1";

                    $params = [];
                    $types = "";

                    if (!empty($name_search)) {
                        $sql .= " AND u.name LIKE ?";
                        $params[] = '%' . $name_search . '%';
                        $types .= "s";
                    }

                    if (!empty($specialty_filter)) {
                        $sql .= " AND sp.name = ?";
                        $params[] = $specialty_filter;
                        $types .= "s";
                    }

                    $sql .= " ORDER BY u.name ASC";

                    $stmt = $conn->prepare($sql);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $doctors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();

                    $response = ['success' => true, 'data' => $doctors];
                    break;
                
                case 'get_doctor_slots':
                    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
                    $date = $_GET['date'] ?? '';

                    if (empty($doctor_id) || empty($date)) {
                        throw new Exception("Doctor ID and date are required.");
                    }

                    $all_possible_slots = [
                        "09:00 AM - 10:00 AM", 
                        "10:00 AM - 11:00 AM", 
                        "11:00 AM - 12:00 PM", 
                        "02:00 PM - 03:00 PM",
                        "03:00 PM - 04:00 PM"
                    ];

                    $stmt = $conn->prepare("
                        SELECT appointment_date 
                        FROM appointments 
                        WHERE doctor_id = ? 
                        AND DATE(appointment_date) = ? 
                        AND status = 'scheduled'
                    ");
                    $stmt->bind_param("is", $doctor_id, $date);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    $booked_slots = [];
                    while ($row = $result->fetch_assoc()) {
                        $booked_hour = (int)date('H', strtotime($row['appointment_date']));
                        if ($booked_hour >= 9 && $booked_hour < 10) $booked_slots[] = "09:00 AM - 10:00 AM";
                        else if ($booked_hour >= 10 && $booked_hour < 11) $booked_slots[] = "10:00 AM - 11:00 AM";
                        else if ($booked_hour >= 11 && $booked_hour < 12) $booked_slots[] = "11:00 AM - 12:00 PM";
                        else if ($booked_hour >= 14 && $booked_hour < 15) $booked_slots[] = "02:00 PM - 03:00 PM";
                        else if ($booked_hour >= 15 && $booked_hour < 16) $booked_slots[] = "03:00 PM - 04:00 PM";
                    }
                    $stmt->close();
                    
                    $available_slots = array_diff($all_possible_slots, $booked_slots);

                    $response = ['success' => true, 'data' => array_values($available_slots)];
                    break;
                
                case 'get_available_tokens':
                    $doctor_id = (int)($_GET['doctor_id'] ?? 0);
                    $date = $_GET['date'] ?? '';
                    
                    if (empty($doctor_id) || empty($date)) {
                        throw new Exception("Doctor ID and date are required.");
                    }
                    
                    $stmt = $conn->prepare("SELECT token_number FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status = 'scheduled'");
                    $stmt->bind_param("is", $doctor_id, $date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $booked_tokens = [];
                    while ($row = $result->fetch_assoc()) {
                        $booked_tokens[] = $row['token_number'];
                    }
                    $stmt->close();
                    
                    $response = ['success' => true, 'data' => ['total' => 20, 'booked' => $booked_tokens]];
                    break;
                
                case 'get_prescriptions':
                    $sql_prescriptions = "
                        SELECT 
                            p.id,
                            p.prescription_date,
                            p.status,
                            p.notes,
                            doc.name as doctor_name
                        FROM prescriptions p
                        JOIN users doc ON p.doctor_id = doc.id
                        WHERE p.patient_id = ?
                    ";
                    
                    $params = [$user_id];
                    $types = "i";

                    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
                        $sql_prescriptions .= " AND p.status = ?";
                        $params[] = $_GET['status'];
                        $types .= "s";
                    }

                    if (!empty($_GET['date'])) {
                        $sql_prescriptions .= " AND p.prescription_date LIKE ?";
                        $params[] = $_GET['date'] . '-%';
                        $types .= "s";
                    }
                    
                    $sql_prescriptions .= " ORDER BY p.prescription_date DESC";

                    $stmt_main = $conn->prepare($sql_prescriptions);
                    $stmt_main->bind_param($types, ...$params);
                    $stmt_main->execute();
                    $prescriptions_result = $stmt_main->get_result();
                    $prescriptions = $prescriptions_result->fetch_all(MYSQLI_ASSOC);
                    $stmt_main->close();

                    $stmt_items = $conn->prepare("
                        SELECT 
                            pi.dosage,
                            pi.frequency,
                            pi.quantity_prescribed,
                            m.name as medicine_name
                        FROM prescription_items pi
                        JOIN medicines m ON pi.medicine_id = m.id
                        WHERE pi.prescription_id = ?
                    ");

                    foreach ($prescriptions as &$prescription) {
                        $stmt_items->bind_param("i", $prescription['id']);
                        $stmt_items->execute();
                        $items_result = $stmt_items->get_result();
                        $prescription['items'] = $items_result->fetch_all(MYSQLI_ASSOC);
                    }
                    unset($prescription);
                    $stmt_items->close();

                    $response = ['success' => true, 'data' => $prescriptions];
                    break;
                
                case 'get_feedback_appointments':
                    $stmt = $conn->prepare("
                        SELECT 
                            a.id as appointment_id, 
                            a.appointment_date, 
                            doc.name as doctor_name, 
                            sp.name as specialty,
                            f.id as feedback_id,
                            f.overall_rating,
                            f.comments
                        FROM appointments a
                        JOIN users doc ON a.doctor_id = doc.id
                        LEFT JOIN doctors d ON doc.id = d.user_id
                        LEFT JOIN specialities sp ON d.specialty_id = sp.id
                        LEFT JOIN feedback f ON a.id = f.appointment_id
                        WHERE a.user_id = ? AND a.status = 'completed'
                        ORDER BY a.appointment_date DESC
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $appointments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    $response = ['success' => true, 'data' => $appointments];
                    break;
            }
        }
        elseif (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'mark_read':
                    if (!isset($_POST['id'])) throw new Exception('Notification ID is required.');
                    $notification_id = (int)$_POST['id'];
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (recipient_user_id = ? OR recipient_role = ?)");
                    $stmt->bind_param("iis", $notification_id, $user_id, $user_role);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Notification marked as read.'];
                    } else {
                        throw new Exception('Failed to update notification status.');
                    }
                    $stmt->close();
                    break;

                case 'mark_all_read':
                    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all') AND is_read = 0");
                    $stmt->bind_param("is", $user_id, $user_role);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'All notifications marked as read.'];
                    } else {
                        throw new Exception('Failed to update notifications.');
                    }
                    $stmt->close();
                    break;

                case 'update_personal_info':
                    $name = trim($_POST['name'] ?? '');
                    $email = trim($_POST['email'] ?? '');
                    $phone = trim($_POST['phone'] ?? '');
                    $dob = !empty($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : null;
                    $gender = trim($_POST['gender'] ?? '');

                    if (empty($name) || empty($email)) throw new Exception('Full name and email are required.');
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Invalid email format.');

                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->bind_param("si", $email, $user_id);
                    $stmt->execute();
                    if ($stmt->get_result()->num_rows > 0) throw new Exception('This email address is already in use.');
                    $stmt->close();

                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, date_of_birth = ?, gender = ? WHERE id = ?");
                    $stmt->bind_param("sssssi", $name, $email, $phone, $dob, $gender, $user_id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Personal information updated successfully.'];
                    } else {
                        throw new Exception('Failed to update information.');
                    }
                    $stmt->close();
                    break;
                
                case 'change_password':
                    $current_password = $_POST['current_password'] ?? '';
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    if (empty($current_password) || empty($new_password) || empty($confirm_password)) throw new Exception('All password fields are required.');
                    if ($new_password !== $confirm_password) throw new Exception('New password and confirmation do not match.');
                    if (strlen($new_password) < 8) throw new Exception('New password must be at least 8 characters long.');

                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$user || !password_verify($current_password, $user['password'])) throw new Exception('The current password you entered is incorrect.');
                    
                    $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $new_password_hashed, $user_id);
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Password changed successfully.'];
                    } else {
                        throw new Exception('Failed to update password.');
                    }
                    $stmt->close();
                    break;
                
                case 'update_profile_picture':
                    if (!isset($_FILES['profile_picture'])) throw new Exception('No file was uploaded.');

                    $file = $_FILES['profile_picture'];
                    $upload_dir = '../uploads/profile_pictures/';
                    
                    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('File upload error. Code: ' . $file['error']);
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array(mime_content_type($file['tmp_name']), $allowed_types)) throw new Exception('Invalid file type. Please upload a JPG, PNG, or GIF.');
                    if ($file['size'] > 2097152) throw new Exception('File size exceeds the 2MB limit.');
                    
                    $stmt_old = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                    $stmt_old->bind_param("i", $user_id);
                    $stmt_old->execute();
                    $old_pic = $stmt_old->get_result()->fetch_assoc()['profile_picture'];
                    $stmt_old->close();

                    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                        $stmt->bind_param("si", $new_filename, $user_id);
                        if ($stmt->execute()) {
                            if ($old_pic && $old_pic !== 'default.png' && file_exists($upload_dir . $old_pic)) {
                                @unlink($upload_dir . $old_pic);
                            }
                            $response = ['success' => true, 'message' => 'Profile picture updated!', 'filepath' => $new_filename];
                        } else {
                            @unlink($upload_path);
                            throw new Exception('Database update failed.');
                        }
                        $stmt->close();
                    } else {
                        throw new Exception('Failed to move uploaded file.');
                    }
                    break;

                case 'update_notification_prefs':
                    $notify_appointments = isset($_POST['notify_appointments']) ? 1 : 0;
                    $notify_billing = isset($_POST['notify_billing']) ? 1 : 0;
                    $notify_labs = isset($_POST['notify_labs']) ? 1 : 0;
                    $notify_prescriptions = isset($_POST['notify_prescriptions']) ? 1 : 0;
                
                    $stmt = $conn->prepare("
                        UPDATE users 
                        SET 
                            notify_appointments = ?, 
                            notify_billing = ?, 
                            notify_labs = ?, 
                            notify_prescriptions = ? 
                        WHERE id = ?
                    ");
                    $stmt->bind_param("iiiii", $notify_appointments, $notify_billing, $notify_labs, $notify_prescriptions, $user_id);
                    
                    if ($stmt->execute()) {
                        $response = ['success' => true, 'message' => 'Notification preferences updated.'];
                    } else {
                        throw new Exception('Failed to update preferences.');
                    }
                    $stmt->close();
                    break;

                case 'process_payment':
                    $bill_id = (int)($_POST['bill_id'] ?? 0);
                    $payment_mode = $_POST['payment_mode'] ?? 'online'; // 'card' or 'upi'

                    if (empty($bill_id)) {
                        throw new Exception("Bill ID is required.");
                    }
                    if (!in_array($payment_mode, ['card', 'upi'])) {
                        throw new Exception("Invalid payment mode.");
                    }

                    // Update the transaction to 'paid'
                    $stmt = $conn->prepare("
                        UPDATE transactions 
                        SET status = 'paid', payment_mode = ?, paid_at = NOW() 
                        WHERE id = ? AND user_id = ? AND status = 'pending'
                    ");
                    $stmt->bind_param("sii", $payment_mode, $bill_id, $user_id);
                    $stmt->execute();

                    if ($stmt->affected_rows > 0) {
                        log_user_activity($conn, $user_id, 'made_payment', "Paid bill TXN{$bill_id} via {$payment_mode}.");
                        $response = ['success' => true, 'message' => 'Payment successful!'];
                    } else {
                        throw new Exception("Payment failed. The bill may already be paid or could not be found.");
                    }
                    $stmt->close();
                    break;
                
                case 'book_appointment':
                    $doctor_id = (int)($_POST['doctorId'] ?? 0);
                    $date = $_POST['date'] ?? '';
                    
                    if (empty($doctor_id) || empty($date)) {
                        throw new Exception("Doctor and date are required.");
                    }
                    
                    $stmt_slots = $conn->prepare("SELECT slots FROM doctors WHERE user_id = ?");
                    $stmt_slots->bind_param("i", $doctor_id);
                    $stmt_slots->execute();
                    $slots_result = $stmt_slots->get_result()->fetch_assoc();
                    $stmt_slots->close();

                    if (!$slots_result || empty($slots_result['slots'])) {
                        throw new Exception("Could not find schedule information for this doctor.");
                    }
                    
                    $slots_data = json_decode($slots_result['slots'], true);
                    if (!isset($slots_data['general_availability']) || !isset($slots_data['days_available'])) {
                         throw new Exception("Doctor's availability is not configured correctly.");
                    }

                    date_default_timezone_set('Asia/Kolkata');

                    list($start_time_str, $end_time_str) = array_map('trim', explode(' - ', $slots_data['general_availability']));

                    $shift_start_datetime = new DateTime("$date $start_time_str");
                    $shift_end_datetime = new DateTime("$date $end_time_str");
                    $now = new DateTime("now");
                    $is_today = ($now->format('Y-m-d') === $date);
                    $slot_duration_minutes = 10;

                    $stmt_token = $conn->prepare("SELECT MAX(token_number) as last_token FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status = 'scheduled'");
                    $stmt_token->bind_param("is", $doctor_id, $date);
                    $stmt_token->execute();
                    $last_token_result = $stmt_token->get_result()->fetch_assoc();
                    $new_token = ($last_token_result['last_token'] ?? 0) + 1;
                    $stmt_token->close();

                    if ($new_token > 50) {
                        throw new Exception("Booking for today is over. The maximum of 50 tokens for this doctor has been reached.");
                    }

                    $booking_cutoff_datetime = (clone $shift_end_datetime)->modify('-2 hours');

                    if ($is_today && $now >= $booking_cutoff_datetime) {
                        throw new Exception("Online booking for today is closed (ends 2 hours before shift). Please come to the hospital directly to get a token.");
                    }

                    $final_appointment_datetime_obj = null;

                    $stmt_last_time = $conn->prepare("SELECT MAX(appointment_date) as last_app_time FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status = 'scheduled'");
                    $stmt_last_time->bind_param("is", $doctor_id, $date);
                    $stmt_last_time->execute();
                    $last_time_result = $stmt_last_time->get_result()->fetch_assoc();
                    $stmt_last_time->close();

                    if ($last_time_result && $last_time_result['last_app_time']) {
                        $last_app_time_obj = new DateTime($last_time_result['last_app_time']);
                        $calculated_next_slot = (clone $last_app_time_obj)->modify("+$slot_duration_minutes minutes");

                        if ($is_today && $calculated_next_slot < $now) {
                            $final_appointment_datetime_obj = (clone $now)->modify('+10 minutes');
                        } else {
                            $final_appointment_datetime_obj = $calculated_next_slot;
                        }

                    } else {
                        if ($is_today && $now > $shift_start_datetime) {
                            $final_appointment_datetime_obj = (clone $now)->modify('+20 minutes');
                        } else {
                            $final_appointment_datetime_obj = $shift_start_datetime;
                        }
                    }

                    if ($final_appointment_datetime_obj >= $shift_end_datetime) {
                         throw new Exception("Booking for today is over. The doctor's schedule is full.");
                    }

                    $appointment_datetime = $final_appointment_datetime_obj->format('Y-m-d H:i:s');
                
                    $stmt_insert = $conn->prepare("INSERT INTO appointments (user_id, doctor_id, appointment_date, token_number, status) VALUES (?, ?, ?, ?, 'scheduled')");
                    $stmt_insert->bind_param("iisi", $user_id, $doctor_id, $appointment_datetime, $new_token);
                
                    if ($stmt_insert->execute()) {
                        $stmt_doctor = $conn->prepare("SELECT name FROM users WHERE id = ?");
                        $stmt_doctor->bind_param("i", $doctor_id);
                        $stmt_doctor->execute();
                        $doctor_name = $stmt_doctor->get_result()->fetch_assoc()['name'];
                        $stmt_doctor->close();
                        log_user_activity($conn, $user_id, 'booked_appointment', "Booked an appointment with Dr. {$doctor_name} for {$date} (Token #{$new_token}).");
                        
                        $response = ['success' => true, 'message' => "Appointment booked successfully! Your token number is #{$new_token} for " . $final_appointment_datetime_obj->format('g:i A') . "."];
                    } else {
                        throw new Exception("Failed to book the appointment. Please try again.");
                    }
                    $stmt_insert->close();
                    break;
                    
                case 'cancel_appointment':
                    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
                
                    if (empty($appointment_id)) {
                        throw new Exception("Appointment ID is required.");
                    }
                
                    $conn->begin_transaction();
                
                    try {
                        $stmt_get = $conn->prepare("
                            SELECT doctor_id, appointment_date, token_number 
                            FROM appointments 
                            WHERE id = ? AND user_id = ? AND status = 'scheduled'
                        ");
                        $stmt_get->bind_param("ii", $appointment_id, $user_id);
                        $stmt_get->execute();
                        $cancelled_app = $stmt_get->get_result()->fetch_assoc();
                        $stmt_get->close();
                
                        if (!$cancelled_app) {
                            $conn->rollback();
                            throw new Exception("Could not cancel this appointment. It may have already started, been cancelled, or does not exist.");
                        }
                
                        $doctor_id = $cancelled_app['doctor_id'];
                        $cancelled_token = $cancelled_app['token_number'];
                        $appointment_day = date('Y-m-d', strtotime($cancelled_app['appointment_date']));
                
                        $stmt_cancel = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
                        $stmt_cancel->bind_param("i", $appointment_id);
                        if (!$stmt_cancel->execute()) {
                            throw new Exception("Failed to cancel the appointment.");
                        }
                        $stmt_cancel->close();
                
                        $stmt_find_next = $conn->prepare("
                            SELECT id 
                            FROM appointments 
                            WHERE doctor_id = ? 
                              AND DATE(appointment_date) = ? 
                              AND token_number > ? 
                              AND status = 'scheduled'
                            ORDER BY token_number ASC
                        ");
                        $stmt_find_next->bind_param("isi", $doctor_id, $appointment_day, $cancelled_token);
                        $stmt_find_next->execute();
                        $apps_to_shift = $stmt_find_next->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt_find_next->close();
                
                        $slot_duration_minutes = 10; 
                
                        if (count($apps_to_shift) > 0) {
                            $stmt_update_shift = $conn->prepare("
                                UPDATE appointments 
                                SET 
                                    token_number = token_number - 1,
                                    appointment_date = DATE_SUB(appointment_date, INTERVAL ? MINUTE)
                                WHERE id = ?
                            ");
                            
                            foreach ($apps_to_shift as $app) {
                                $stmt_update_shift->bind_param("ii", $slot_duration_minutes, $app['id']);
                                if (!$stmt_update_shift->execute()) {
                                    throw new Exception("Failed to update subsequent appointments.");
                                }
                            }
                            $stmt_update_shift->close();
                        }
                
                        log_user_activity($conn, $user_id, 'cancelled_appointment', "Cancelled appointment ID: {$appointment_id} (Token #{$cancelled_token}).");
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Appointment cancelled successfully. Subsequent tokens have been updated.'];
                
                    } catch (Exception $e) {
                        $conn->rollback(); 
                        throw $e; 
                    }
                    break;

                case 'submit_feedback':
                    $patient_id = $user_id;
                    $appointment_id = (int)($_POST['appointment_id'] ?? 0);
                    $overall_rating = (int)($_POST['overall_rating'] ?? 0);
                    $doctor_rating = (int)($_POST['doctor_rating'] ?? 0);
                    $comments = trim($_POST['comments'] ?? '');

                    if (empty($appointment_id)) {
                        throw new Exception("Appointment ID is missing.");
                    }
                    if (empty($overall_rating) || $overall_rating < 1 || $overall_rating > 5) {
                        throw new Exception("Please provide a valid overall rating (1-5).");
                    }

                    // 1. Verify user owns this appointment
                    $stmt_check = $conn->prepare("SELECT user_id FROM appointments WHERE id = ? AND user_id = ? AND status = 'completed'");
                    $stmt_check->bind_param("ii", $appointment_id, $patient_id);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows === 0) {
                        $stmt_check->close();
                        throw new Exception("You do not have permission to review this appointment.");
                    }
                    $stmt_check->close();

                    // 2. Check for existing feedback
                    $stmt_exists = $conn->prepare("SELECT id FROM feedback WHERE appointment_id = ?");
                    $stmt_exists->bind_param("i", $appointment_id);
                    $stmt_exists->execute();
                    if ($stmt_exists->get_result()->num_rows > 0) {
                        $stmt_exists->close();
                        throw new Exception("You have already submitted feedback for this appointment.");
                    }
                    $stmt_exists->close();

                    // 3. Insert new feedback
                    $stmt_insert = $conn->prepare("
                        INSERT INTO feedback 
                            (patient_id, appointment_id, overall_rating, doctor_rating, comments, feedback_type, is_anonymous) 
                        VALUES 
                            (?, ?, ?, ?, ?, 'Suggestion', 0)
                    ");
                    $stmt_insert->bind_param("iiiis", $patient_id, $appointment_id, $overall_rating, $doctor_rating, $comments);
                    
                    if ($stmt_insert->execute()) {
                        log_user_activity($conn, $user_id, 'submitted_feedback', "Submitted feedback for appointment ID: {$appointment_id}.");
                        $response = ['success' => true, 'message' => 'Thank you for your feedback!'];
                    } else {
                        throw new Exception('Failed to submit feedback. Please try again.');
                    }
                    $stmt_insert->close();
                    break;
            }
        }

    } catch (Exception $e) {
        http_response_code(400); // Bad Request
        $response['message'] = $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// --- Standard Page Load Data Preparation ---
$user_details = [];
$stmt = $conn->prepare("
    SELECT 
        username, name, email, phone, date_of_birth, gender, profile_picture,
        notify_appointments, notify_billing, notify_labs, notify_prescriptions
    FROM users 
    WHERE id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $user_details = $result->fetch_assoc();
    } else {
        session_unset();
        session_destroy();
        header("Location: ../login/index.php?error=user_not_found");
        exit();
    }
    $stmt->close();
} else {
    error_log("Database statement preparation failed: " . $conn->error);
    die("An internal error occurred. Please try again later.");
}

$unread_notification_count = 0;
$stmt_count = $conn->prepare("SELECT COUNT(id) as unread_count FROM notifications WHERE (recipient_user_id = ? OR recipient_role = ? OR recipient_role = 'all') AND is_read = 0");
if ($stmt_count) {
    $stmt_count->bind_param("is", $user_id, $user_role);
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    if($row = $result_count->fetch_assoc()) {
        $unread_notification_count = $row['unread_count'];
    }
    $stmt_count->close();
} else {
     error_log("Database statement preparation failed for notification count: " . $conn->error);
}

$username = isset($user_details['name']) && !empty($user_details['name']) ? htmlspecialchars($user_details['name']) : 'User';
$display_user_id = isset($_SESSION['display_user_id']) ? htmlspecialchars($_SESSION['display_user_id']) : 'N/A';
$profile_picture = isset($user_details['profile_picture']) ? htmlspecialchars($user_details['profile_picture']) : 'default.png';

date_default_timezone_set('Asia/Kolkata'); 
$current_hour = date('G');
if ($current_hour < 12) {
    $greeting = "Good Morning";
} elseif ($current_hour < 17) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}
?>