<?php
// This template assumes that a variable `$lab_data` is available,
// which is an associative array containing all the necessary lab result details.

// Attempt to decode the JSON string from the database
$results = null;
if (isset($lab_data['result_details'])) {
    $results = json_decode($lab_data['result_details'], true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lab Report</title>
    <style>
        /* Using DejaVu Sans as it supports more characters */
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; }
        .header p { margin: 5px 0; }
        
        /* Patient and Test Info Tables */
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
        
        /* Section Headers */
        h2 {
            border-bottom: 2px solid #4a90e2;
            padding-bottom: 5px;
            margin-top: 25px;
            color: #4a90e2;
        }
        
        /* Results Table */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .results-table th, .results-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .results-table th {
            background-color: #f2f2f2;
        }
        
        /* Summary/Notes Section */
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
        <h1>Calysta Health Institute</h1>
        <p>Kerala, India</p>
        <h1>Lab Report</h1>
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
            <td>Test Date</td>
            <td><?php echo htmlspecialchars(date('F j, Y', strtotime($lab_data['test_date']))); ?></td>
        </tr>
        <tr>
            <td>Test Name</td>
            <td><?php echo htmlspecialchars($lab_data['test_name']); ?></td>
        </tr>
        <tr>
            <td>Ordering Physician</td>
            <td><?php echo htmlspecialchars($lab_data['doctor_name'] ?? 'N/A'); ?></td>
        </tr>
    </table>

    <h2>Test Results</h2>
    <table class="results-table">
        <thead>
            <tr>
                <th>Test Description</th>
                <th>Results</th>
                <th>Units</th>
                <th>Biological Reference Value</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($results && !empty($results['findings'])): ?>
                <?php foreach ($results['findings'] as $finding): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($finding['parameter'] ?? 'N/A'); ?></td>
                        <td><strong><?php echo htmlspecialchars($finding['result'] ?? 'N/A'); ?></strong></td>
                        <td><?php echo htmlspecialchars($finding['units'] ?? ''); // Units might be blank ?></td>
                        <td><?php echo htmlspecialchars($finding['range'] ?? 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No specific findings available.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($results && !empty($results['summary'])): ?>
        <h2>Summary</h2>
        <div class="section-content">
            <?php echo nl2br(htmlspecialchars($results['summary'])); ?>
        </div>
    <?php endif; ?>
    
    <div class="footer">
        This is a computer-generated document.
    </div>
</body>
</html>