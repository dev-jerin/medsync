<?php
// This template assumes a variable `$prescription_data` is available.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Prescription</title>
    <style>
        /* Using DejaVu Sans as it supports more characters, including '₹' */
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .header { text-align: left; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #4a90e2; }
        .header p { margin: 2px 0; }
        .doctor-info { float: left; width: 50%; }
        .patient-info { float: right; width: 50%; text-align: right; }
        .info-table { width: 100%; margin-top: 15px; }
        .info-table td { vertical-align: top; }
        .clearfix { clear: both; content: ""; display: table; }
        .rx-symbol { font-size: 40px; font-weight: bold; float: left; margin-right: 15px; line-height: 1; }
        .medications { margin-top: 20px; }
        .medications h2 { border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-left: 50px; }
        .med-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .med-table th, .med-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        .med-table th { background-color: #f2f2f2; }
        .notes { margin-top: 20px; border-top: 1px solid #eee; padding-top: 10px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <div class="doctor-info">
            <h1>Dr. <?php echo htmlspecialchars($prescription_data['doctor_name'] ?? 'N/A'); ?></h1>
            <p><?php echo htmlspecialchars($prescription_data['doctor_specialty'] ?? 'Physician'); ?></p>
            <p><?php echo htmlspecialchars($prescription_data['doctor_qualifications'] ?? ''); ?></p>
            <p>Calysta Health Institute</p>
        </div>
        <div class="patient-info">
            <p><strong>Patient:</strong> <?php echo htmlspecialchars($prescription_data['patient_name'] ?? 'N/A'); ?></p>
            <p><strong>Patient ID:</strong> <?php echo htmlspecialchars($prescription_data['display_user_id'] ?? 'N/A'); ?></p>
            <p><strong>Date:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($prescription_data['prescription_date']))); ?></p>
        </div>
    </div>
    <div class="clearfix"></div>

    <div class="medications">
        <div class="rx-symbol">℞</div>
        <h2>Prescription Details</h2>

        <table class="med-table">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>Dosage</th>
                    <th>Frequency</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($prescription_data['items'])): ?>
                    <?php foreach ($prescription_data['items'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['dosage']); ?></td>
                            <td><?php echo htmlspecialchars($item['frequency']); ?></td>
                            <td><?php echo htmlspecialchars($item['quantity_prescribed']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">No medication items listed.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="notes">
        <strong>Doctor's Notes:</strong>
        <p><?php echo nl2br(htmlspecialchars($prescription_data['notes'] ?? 'No specific notes.')); ?></p>
    </div>

    <div class="footer">
        This is a computer-generated document. Not valid for medico-legal purposes without a signature.
    </div>
</body>
</html>