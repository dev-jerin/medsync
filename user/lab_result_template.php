<?php
// This template assumes a variable `$lab_data` is available,
// containing all necessary lab report details.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .header p { margin: 5px 0; }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .info-table td:first-child {
            background-color: #f2f2f2;
            font-weight: bold;
            width: 150px;
        }
        h2 {
            border-bottom: 2px solid #17a2b8; /* Using a different color for labs */
            padding-bottom: 5px;
            margin-top: 25px;
            color: #17a2b8;
        }
        .section-content {
            padding: 10px;
            line-height: 1.6;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            white-space: pre-wrap; /* Crucial for preserving formatting */
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Calysta Health Institute</h1>
        <p>Kerala, India</p>
        <p>Laboratory Report</p>
    </div>

    <h2>Patient Information</h2>
    <table class="info-table">
        <tr>
            <td>Patient Name</td>
            <td><?php echo htmlspecialchars($lab_data['patient_name'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <td>Patient ID</td>
            <td><?php echo htmlspecialchars($lab_data['display_user_id'] ?? 'N/A'); ?></td>
        </tr>
    </table>

    <h2>Test Details</h2>
    <table class="info-table">
        <tr>
            <td>Test Name</td>
            <td><?php echo htmlspecialchars($lab_data['test_name']); ?></td>
        </tr>
        <tr>
            <td>Test Date</td>
            <td><?php echo htmlspecialchars(date('F j, Y', strtotime($lab_data['test_date']))); ?></td>
        </tr>
        <tr>
            <td>Ordering Physician</td>
            <td><?php echo htmlspecialchars($lab_data['doctor_name'] ?? 'N/A'); ?></td>
        </tr>
    </table>

    <h2>Test Results</h2>
    <div class="section-content">
        <?php echo nl2br(htmlspecialchars($lab_data['result_details'] ?? 'No details provided.')); ?>
    </div>
    
    <div class="footer">
        This is a computer-generated document. Page 1 of 1.
    </div>
</body>
</html>