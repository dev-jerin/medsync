<?php
// This template is included by api.php, so it should not be accessed directly.
if (!isset($conn) || !isset($transaction_id)) {
    die('This template cannot be accessed directly.');
}

// Fetch all necessary data for the invoice PDF
$sql = "
    SELECT 
        t.*, 
        p.name as patient_name, 
        p.display_user_id as patient_display_id, 
        a.admission_date, 
        a.discharge_date 
    FROM transactions t 
    JOIN users p ON t.user_id = p.id 
    LEFT JOIN admissions a ON t.admission_id = a.id 
    WHERE t.id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$invoice) {
    die('Invoice data not found.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?php echo htmlspecialchars($invoice['id']); ?></title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; font-size: 12px; }
        .container { width: 100%; margin: 0 auto; padding: 20px; }
        .header { text-align: center; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24px; color: #0056b3; }
        .header p { margin: 2px 0; }
        .invoice-details, .patient-details { margin-bottom: 20px; }
        .details-grid { display: block; width: 100%; }
        .details-grid::after { content: ""; clear: both; display: table; }
        .patient-details { float: left; width: 50%; }
        .invoice-details { float: right; width: 50%; text-align: right; }
        .details-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .details-table th, .details-table td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        .details-table th { background-color: #f8f8f8; }
        .total-section { margin-top: 30px; text-align: right; }
        .total-section h2 { margin: 0; font-size: 18px; color: #0056b3; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #888; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Calysta Health Institute</h1>
            <p>Kerala, India</p>
            <p>+91 45235 31245 | medsync.calysta@gmail.com</p>
        </div>
        
        <h2>INVOICE</h2>

        <div class="details-grid">
            <div class="patient-details">
                <strong>Billed To:</strong><br>
                <?php echo htmlspecialchars($invoice['patient_name']); ?><br>
                Patient ID: <?php echo htmlspecialchars($invoice['patient_display_id']); ?>
            </div>
            <div class="invoice-details">
                <strong>Invoice ID:</strong> INV-<?php echo str_pad($invoice['id'], 5, '0', STR_PAD_LEFT); ?><br>
                <strong>Date Paid:</strong> <?php echo date("d M Y", strtotime($invoice['paid_at'])); ?><br>
                <strong>Admission Date:</strong> <?php echo date("d M Y", strtotime($invoice['admission_date'])); ?><br>
            </div>
        </div>

        <table class="details-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php 
                            // This provides a cleaner, multi-line description
                            $description_parts = explode(",", $invoice['description']);
                            foreach ($description_parts as $part) {
                                echo htmlspecialchars(trim($part)) . "<br>";
                            }
                        ?>
                    </td>
                    <td>₹<?php echo number_format($invoice['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>

        <div class="total-section">
            <h2>Total Paid: ₹<?php echo number_format($invoice['amount'], 2); ?></h2>
        </div>

        <div class="footer">
            Thank you for choosing Calysta Health Institute. | MedSync Healthcare Platform
        </div>
    </div>
</body>
</html>