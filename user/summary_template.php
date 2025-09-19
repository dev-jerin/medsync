<?php
// This template assumes that a variable `$summary_data` is available,
// which is an associative array containing all the necessary summary details.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discharge Summary</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .header p { margin: 5px 0; }
        .patient-info, .admission-info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .patient-info td, .admission-info td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .patient-info td:first-child, .admission-info td:first-child {
            background-color: #f2f2f2;
            font-weight: bold;
            width: 150px;
        }
        h2 {
            border-bottom: 2px solid #4a90e2;
            padding-bottom: 5px;
            margin-top: 25px;
            color: #4a90e2;
        }
        .section-content {
            padding-left: 10px;
            line-height: 1.6;
            white-space: pre-wrap; /* Preserve line breaks from database */
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
        <h1>MedSync Hospital</h1>
        <p>123 Health St, Wellness City, 12345</p>
        <p>Discharge Summary</p>
    </div>

    <h2>Patient Information</h2>
    <table class="patient-info">
        <tr>
            <td>Patient Name</td>
            <td><?php echo htmlspecialchars($summary_data['patient_name'] ?? 'N/A'); ?></td>
        </tr>
        <tr>
            <td>Patient ID</td>
            <td><?php echo htmlspecialchars($summary_data['display_user_id'] ?? 'N/A'); ?></td>
        </tr>
    </table>

    <h2>Admission Details</h2>
    <table class="admission-info">
        <tr>
            <td>Admission Date</td>
            <td><?php echo htmlspecialchars(date('F j, Y', strtotime($summary_data['admission_date']))); ?></td>
        </tr>
        <tr>
            <td>Discharge Date</td>
            <td><?php echo htmlspecialchars(date('F j, Y', strtotime($summary_data['discharge_date']))); ?></td>
        </tr>
        <tr>
            <td>Admitting Physician</td>
            <td><?php echo htmlspecialchars($summary_data['doctor_name'] ?? 'N/A'); ?></td>
        </tr>
    </table>

    <h2>Discharge Summary</h2>
    <div class="section-content">
        <?php echo nl2br(htmlspecialchars($summary_data['summary_text'] ?? 'No summary provided.')); ?>
    </div>

    <h2>Follow-up Instructions</h2>
    <div class="section-content">
        <?php echo nl2br(htmlspecialchars($summary_data['notes'] ?? 'No specific follow-up instructions.')); ?>
    </div>
    
    <div class="footer">
        This is a computer-generated document.
    </div>
</body>
</html>