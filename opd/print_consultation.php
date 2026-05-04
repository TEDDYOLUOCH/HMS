<?php
/**
 * Hospital Management System - Print Consultation Summary
 * Generates a printable consultation summary for patients
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Include database
require_once '../config/database.php';

// Get parameters
$patient_id = $_GET['patient_id'] ?? 0;
$consultation_id = $_GET['consultation_id'] ?? 0;

// Validate
if (!$patient_id) {
    die("Patient ID is required");
}

try {
    $db = Database::getInstance();
    
    // Get patient details
    $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        die("Patient not found");
    }
    
    // Get consultation details
    $consultation = null;
    if ($consultation_id) {
        $stmt = $db->prepare("SELECT c.*, u.full_name as doctor_name, u.initials 
                              FROM opd_consultations c 
                              LEFT JOIN users u ON c.doctor_id = u.id 
                              WHERE c.id = ?");
        $stmt->execute([$consultation_id]);
        $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get prescriptions if available
    $prescriptions = [];
    if ($consultation_id) {
        $stmt = $db->prepare("SELECT * FROM prescriptions WHERE consultation_id = ?");
        $stmt->execute([$consultation_id]);
        $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get lab requests if available
    $lab_requests = [];
    if ($consultation_id) {
        $stmt = $db->prepare("SELECT lr.*, lt.test_name 
                              FROM lab_requests lr 
                              LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id 
                              WHERE lr.consultation_id = ?");
        $stmt->execute([$consultation_id]);
        $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$current_date = date('F j, Y');
$current_time = date('h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Summary - <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #fff;
            padding: 20px;
        }
        
        .print-container {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #ddd;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2563eb;
        }
        
        .logo img {
            height: 50px;
            max-width: 150px;
        }
        
        .logo-text {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
        }
        
        @media print {
            .logo img {
                height: 40px;
            }
        }
        
        .header-right {
            text-align: right;
            font-size: 11px;
        }
        
        .document-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .patient-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 4px;
        }
        
        .info-group {
            margin-bottom: 8px;
        }
        
        .info-label {
            font-weight: bold;
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 13px;
            color: #1e293b;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .consultation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .detail-item {
            margin-bottom: 8px;
        }
        
        .detail-label {
            font-weight: 600;
            color: #64748b;
            font-size: 10px;
        }
        
        .detail-value {
            color: #1e293b;
        }
        
        .diagnosis-box {
            padding: 12px;
            background: #f0fdf4;
            border-left: 3px solid #16a34a;
            margin-top: 10px;
        }
        
        .prescription-table, .lab-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .prescription-table th, .prescription-table td,
        .lab-table th, .lab-table td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        
        .prescription-table th, .lab-table th {
            background: #f1f5f9;
            font-weight: bold;
        }
        
        .no-data {
            color: #94a3b8;
            font-style: italic;
            padding: 10px;
            text-align: center;
            background: #f8fafc;
            border-radius: 4px;
        }
        
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            font-size: 10px;
            color: #64748b;
        }
        
        .signature-line {
            width: 200px;
            border-top: 1px solid #333;
            padding-top: 5px;
            text-align: center;
        }
        
        .print-btn {
            display: none;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .print-container {
                border: none;
            }
            .print-btn {
                display: none !important;
            }
        }
        
        @media screen {
            .print-container {
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            }
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="logo"><img src="../assets/images/logo.jpeg" alt="Logo" style="height:50px;max-width:150px;"></div>
                <div style="color: #64748b; font-size: 11px;">Hospital Management System</div>
            </div>
            <div class="header-right">
                <div><strong>Date:</strong> <?php echo $current_date; ?></div>
                <div><strong>Time:</strong> <?php echo $current_time; ?></div>
                <div><strong>Document:</strong> Consultation Summary</div>
            </div>
        </div>
        
        <!-- Document Title -->
        <div class="document-title">Consultation Summary</div>
        
        <!-- Patient Information -->
        <div class="patient-info">
            <div>
                <div class="info-group">
                    <div class="info-label">Patient Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name'] . ' ' . ($patient['other_names'] ?? '')); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Patient ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['patient_id']); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value"><?php echo $patient['date_of_birth'] ? date('F j, Y', strtotime($patient['date_of_birth'])) : 'N/A'; ?></div>
                </div>
            </div>
            <div>
                <div class="info-group">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?php echo htmlspecialchars($patient['phone_primary'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Allergies</div>
                    <div class="info-value" style="color: #dc2626;"><?php echo htmlspecialchars($patient['allergies'] ?? 'None reported'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Consultation Details -->
        <?php if ($consultation): ?>
        <div class="section">
            <div class="section-title">Consultation Details</div>
            <div class="consultation-details">
                <div class="detail-item">
                    <span class="detail-label">Date: </span>
                    <span class="detail-value"><?php echo $consultation['consultation_date'] ? date('F j, Y', strtotime($consultation['consultation_date'])) : date('F j, Y'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Doctor: </span>
                    <span class="detail-value"><?php echo htmlspecialchars($consultation['doctor_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Status: </span>
                    <span class="detail-value"><?php echo htmlspecialchars(ucfirst($consultation['status'] ?? 'In Progress')); ?></span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-title">Chief Complaint</div>
            <p><?php echo nl2br(htmlspecialchars($consultation['chief_complaint'] ?? 'No complaint recorded')); ?></p>
        </div>
        
        <div class="section">
            <div class="section-title">History of Present Illness</div>
            <p><?php echo nl2br(htmlspecialchars($consultation['present_illness'] ?? 'No history recorded')); ?></p>
        </div>
        
        <div class="section">
            <div class="section-title">Physical Examination</div>
            <p><?php echo nl2br(htmlspecialchars($consultation['examination_notes'] ?? 'No examination recorded')); ?></p>
        </div>
        
        <?php if (!empty($consultation['diagnosis'])): ?>
        <div class="section">
            <div class="section-title">Diagnosis</div>
            <div class="diagnosis-box">
                <strong><?php echo htmlspecialchars($consultation['diagnosis']); ?></strong>
                <?php if (!empty($consultation['differential'])): ?>
                <p style="margin-top: 5px; font-size: 11px;">Differential: <?php echo htmlspecialchars($consultation['differential']); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($consultation['treatment_plan'])): ?>
        <div class="section">
            <div class="section-title">Treatment Plan</div>
            <p><?php echo nl2br(htmlspecialchars($consultation['treatment_plan'])); ?></p>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="no-data">No consultation recorded yet</div>
        <?php endif; ?>
        
        <!-- Prescriptions -->
        <?php if (!empty($prescriptions)): ?>
        <div class="section">
            <div class="section-title">Prescriptions</div>
            <table class="prescription-table">
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                        <th>Instructions</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prescriptions as $rx): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($rx['drug_name']); ?></td>
                        <td><?php echo htmlspecialchars($rx['dosage']); ?></td>
                        <td><?php echo htmlspecialchars($rx['frequency']); ?></td>
                        <td><?php echo htmlspecialchars($rx['duration'] . ' ' . ($rx['duration_unit'] ?? 'days')); ?></td>
                        <td><?php echo htmlspecialchars($rx['instructions'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($rx['status'] ?? 'Pending')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Lab Requests -->
        <?php if (!empty($lab_requests)): ?>
        <div class="section">
            <div class="section-title">Laboratory Test Requests</div>
            <table class="lab-table">
                <thead>
                    <tr>
                        <th>Test Type</th>
                        <th>Requested Date</th>
                        <th>Status</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lab_requests as $lab): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($lab['test_name'] ?? 'General Test'); ?></td>
                        <td><?php echo $lab['created_at'] ? date('F j, Y', strtotime($lab['created_at'])) : '-'; ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($lab['status'] ?? 'Pending')); ?></td>
                        <td><?php echo htmlspecialchars($lab['notes'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <div>
                <div class="signature-line">Doctor's Signature</div>
            </div>
            <div style="text-align: right;">
                <div>Generated by: <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'System'); ?></div>
                <div><?php echo $current_date . ' ' . $current_time; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Print Button -->
    <div style="text-align: center; margin-top: 20px;" class="print-btn">
        <button onclick="window.print()" style="padding: 12px 30px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px;">
            🖨️ Print Consultation Summary
        </button>
        <button onclick="window.close()" style="padding: 12px 30px; background: #64748b; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; margin-left: 10px;">
            Close
        </button>
    </div>
</body>
</html>
