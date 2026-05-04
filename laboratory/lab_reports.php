<?php
/**
 * Hospital Management System - Laboratory Reports
 * Professional PDF report generation
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'lab_technologist', 'lab_scientist', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Lab Reports';

// Include database and header
require_once '../config/database.php';

// Generate CSRF token
$csrf_token = csrfToken();

// Get request details for report
$request_id = $_GET['request_id'] ?? 0;
$request = null;
$patient = null;

if ($request_id) {
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT lr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth, p.phone_primary, p.email as patient_email,
                             u.username as requested_by_name, u2.username as result_by_name, u2.role as result_by_role,
                             lt.test_name as test_type, lt.unit as test_unit, lt.reference_range as test_ref
                             FROM lab_requests lr
                             JOIN patients p ON lr.patient_id = p.id
                             LEFT JOIN users u ON lr.requested_by = u.id
                             LEFT JOIN users u2 ON lr.result_entered_by = u2.id
                             LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                             WHERE lr.id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Decode results
        if ($request && $request['results']) {
            $request['results_array'] = json_decode($request['results'], true);
        }
        
        // Get role display name for lab technician
        $role_titles = [
            'lab_technologist' => 'Laboratory Technologist',
            'lab_scientist' => 'Laboratory Scientist',
            'admin' => 'Administrator',
            'doctor' => 'Doctor'
        ];
        $result_by_role = $request['result_by_role'] ?? '';
        $lab_role_title = $role_titles[$result_by_role] ?? 'Laboratory Technologist';
        
    } catch (Exception $e) {
        $error = 'Request not found';
    }
}

// If printing, use a print-friendly layout
$is_print = isset($_GET['print']);

if ($is_print && $request) {
    // Print styles
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Lab Report - <?php echo htmlspecialchars($request['patient_id']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.4; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
            .header h1 { font-size: 24pt; margin-bottom: 5px; }
            .header p { font-size: 11pt; color: #666; }
            .report-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .patient-info, .test-info { width: 48%; }
            .patient-info h3, .test-info h3 { font-size: 12pt; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }
            .info-row { display: flex; margin-bottom: 3px; }
            .info-label { font-weight: bold; width: 120px; }
            .info-value { flex: 1; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #333; padding: 8px; text-align: left; }
            th { background: #f0f0f0; font-weight: bold; }
            .normal { color: #28a745; }
            .high { color: #dc3545; }
            .low { color: #007bff; }
            .footer { margin-top: 30px; display: flex; justify-content: space-between; }
            .signature-line { width: 200px; border-top: 1px solid #333; padding-top: 5px; text-align: center; }
            .print-btn { display: none; }
            @media print { body { padding: 0; } .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()" style="position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">Print</button>
    <?php
    // Print layout
    ?>
    <div class="header">
        <h1>SIWOT HOSPITAL</h1>
        <p>P.O. Box 12345, Nairobi, Kenya | Tel: +254 700 000 000 | Email: lab@siwothospital.org</p>
        <p>Laboratory Department</p>
    </div>
    
    <div class="report-info">
        <div class="patient-info">
            <h3>Patient Information</h3>
            <div class="info-row"><span class="info-label">Name:</span><span class="info-value"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></span></div>
            <div class="info-row"><span class="info-label">Patient ID:</span><span class="info-value"><?php echo htmlspecialchars($request['patient_id']); ?></span></div>
            <div class="info-row"><span class="info-label">Age/Gender:</span><span class="info-value"><?php echo floor((time() - strtotime($request['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years / <?php echo ucfirst($request['gender']); ?></span></div>
            <div class="info-row"><span class="info-label">Contact:</span><span class="info-value"><?php echo htmlspecialchars($request['phone_primary']); ?></span></div>
        </div>
        <div class="test-info">
            <h3>Test Information</h3>
            <div class="info-row"><span class="info-label">Test Type:</span><span class="info-value"><?php echo htmlspecialchars($request['test_type']); ?></span></div>
            <div class="info-row"><span class="info-label">Request Date:</span><span class="info-value"><?php echo date('F j, Y g:i A', strtotime($request['created_at'])); ?></span></div>
            <div class="info-row"><span class="info-label">Report Date:</span><span class="info-value"><?php echo date('F j, Y g:i A', strtotime($request['completed_at'] ?? $request['result_entered_at'])); ?></span></div>
            <div class="info-row"><span class="info-label">Requested By:</span><span class="info-value">Dr. <?php echo htmlspecialchars($request['requested_by_name'] ?? 'Unknown'); ?></span></div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Test</th>
                <th>Result</th>
                <th>Unit</th>
                <th>Reference Range</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (!empty($request['results_array'])) {
                // Get default unit and reference from test type
                $default_unit = $request['test_unit'] ?? '';
                $default_ref = $request['test_ref'] ?? '';
                
                // Map test names to their default units and references
                $test_defaults = [
                    'Hemoglobin (HB)' => ['unit' => 'g/dL', 'ref' => '12-17'],
                    'WBC' => ['unit' => 'x10^9/L', 'ref' => '4-11'],
                    'Platelets' => ['unit' => 'x10^9/L', 'ref' => '150-400'],
                    'RBC' => ['unit' => 'x10^12/L', 'ref' => '4.5-5.5'],
                    'Hematocrit (HCT)' => ['unit' => '%', 'ref' => '36-50'],
                    'MCV' => ['unit' => 'fL', 'ref' => '80-100'],
                    'MCH' => ['unit' => 'pg', 'ref' => '27-33'],
                    'Neutrophils' => ['unit' => '%', 'ref' => '40-70'],
                    'Lymphocytes' => ['unit' => '%', 'ref' => '20-40'],
                    'Eosinophils' => ['unit' => '%', 'ref' => '1-6'],
                    'Fasting Glucose' => ['unit' => 'mg/dL', 'ref' => '70-100'],
                    'Random Glucose' => ['unit' => 'mg/dL', 'ref' => '<140'],
                    'Creatinine' => ['unit' => 'mg/dL', 'ref' => '0.6-1.2'],
                    'BUN' => ['unit' => 'mg/dL', 'ref' => '7-20'],
                    'Sodium (Na)' => ['unit' => 'mEq/L', 'ref' => '135-145'],
                    'Potassium (K)' => ['unit' => 'mEq/L', 'ref' => '3.5-5.0'],
                    'Chloride (Cl)' => ['unit' => 'mEq/L', 'ref' => '96-106'],
                    'Total Bilirubin' => ['unit' => 'mg/dL', 'ref' => '0.1-1.2'],
                    'ALT' => ['unit' => 'U/L', 'ref' => '7-56'],
                    'AST' => ['unit' => 'U/L', 'ref' => '10-40'],
                    'Alkaline Phosphatase' => ['unit' => 'U/L', 'ref' => '44-147'],
                    'Total Protein' => ['unit' => 'g/dL', 'ref' => '6-8'],
                    'Albumin' => ['unit' => 'g/dL', 'ref' => '3.5-5.5'],
                    'Uric Acid' => ['unit' => 'mg/dL', 'ref' => '3.5-7.2'],
                    'Cholesterol' => ['unit' => 'mg/dL', 'ref' => '<200'],
                    'Triglycerides' => ['unit' => 'mg/dL', 'ref' => '<150'],
                    'Appearance' => ['unit' => 'qualitative', 'ref' => 'Clear'],
                    'Color' => ['unit' => 'qualitative', 'ref' => 'Yellow'],
                    'Specific Gravity' => ['unit' => 'g/mL', 'ref' => '1.005-1.030'],
                    'pH' => ['unit' => 'pH', 'ref' => '5-7'],
                    'Protein' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                    'Glucose' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                    'Ketones' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                    'Blood' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                    'Leukocytes' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                    'Nitrites' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                    'Pus Cells' => ['unit' => '/HPF', 'ref' => '0-5'],
                    'RBCs' => ['unit' => '/HPF', 'ref' => '0-2'],
                    'Epithelial Cells' => ['unit' => '/HPF', 'ref' => '0-5'],
                ];
                
                foreach ($request['results_array'] as $result) {
                    $test_name = $result['test'] ?? '';
                    $value = $result['value'] ?? '';
                    $unit = $result['unit'] ?? ($test_defaults[$test_name]['unit'] ?? $default_unit);
                    $ref = $result['reference_range'] ?? ($test_defaults[$test_name]['ref'] ?? $default_ref);
                    $status = '-';
                    $status_class = '';
                    
                    // Simple status detection
                    if ($value) {
                        $status = 'Normal';
                        $status_class = 'normal';
                    }
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($test_name) . '</td>';
                    echo '<td>' . htmlspecialchars($value) . '</td>';
                    echo '<td>' . htmlspecialchars($unit) . '</td>';
                    echo '<td>' . htmlspecialchars($ref ?: '-') . '</td>';
                    echo '<td class="' . $status_class . '">' . $status . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5" style="text-align:center;">No results available</td></tr>';
            }
            ?>
        </tbody>
    </table>
    
    <?php if ($request['notes']): ?>
    <div style="margin: 20px 0;">
        <h3 style="font-size: 12pt; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px;">Lab Notes</h3>
        <p><?php echo htmlspecialchars($request['notes']); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="footer">
        <div class="signature-line">
            <p><?php echo htmlspecialchars($lab_role_title); ?></p>
            <p><?php echo htmlspecialchars($request['result_by_name'] ?? ''); ?></p>
        </div>
        <div class="signature-line">
            <p>Verified By</p>
            <p></p>
        </div>
    </div>
    
    <div style="margin-top: 20px; text-align: center; font-size: 10pt; color: #666;">
        <p>This is a computer-generated report. No signature required.</p>
        <p>Report ID: <?php echo $request_id; ?> | Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Normal page view - include header
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-file-medical-alt text-green-600 mr-2"></i> Lab Reports
                </h1>
                <p class="text-gray-500 mt-1">Generate and view laboratory reports</p>
            </div>
            <a href="lab_requests" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Requests
            </a>
        </div>
    </div>
    
    <?php if (!$request): ?>
    <!-- Report Selection -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Select a Completed Test</h3>
        
        <?php 
        // Get completed requests
        $completed_requests = [];
        try {
            $db = Database::getInstance();
            $stmt = $db->query("SELECT lr.id, lr.completed_at, p.first_name, p.last_name, p.patient_id,
                               lt.test_name as test_type
                               FROM lab_requests lr
                               JOIN patients p ON lr.patient_id = p.id
                               LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                               WHERE lr.status = 'Completed'
                               ORDER BY lr.completed_at DESC LIMIT 50");
            $completed_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        ?>
        
        <?php if (empty($completed_requests)): ?>
        <p class="text-gray-500 text-center py-8">No completed lab reports available</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Test Type</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Completed</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($completed_requests as $req): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-medium">
                                        <?php echo strtoupper(substr($req['first_name'], 0, 1)); ?>
                                    </span>
                                </div>
                                <span class="font-medium text-gray-800">
                                    <?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-gray-600"><?php echo htmlspecialchars($req['test_type']); ?></td>
                        <td class="py-3 px-4 text-gray-500 text-sm"><?php echo date('M j, Y g:i A', strtotime($req['completed_at'])); ?></td>
                        <td class="py-3 px-4">
                            <a href="?request_id=<?php echo $req['id']; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <a href="?request_id=<?php echo $req['id']; ?>&print=1" target="_blank" class="px-3 py-1 bg-green-100 text-green-700 rounded text-sm hover:bg-green-200">
                                <i class="fas fa-print mr-1"></i> Print
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- Report Preview -->
    <div class="space-y-6">
        <!-- Report Actions -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="font-semibold text-gray-800">Report Preview</h3>
                    <p class="text-sm text-gray-500">Test ID: <?php echo $request_id; ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="?request_id=<?php echo $request_id; ?>&print=1" target="_blank" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="fas fa-print mr-2"></i> Print Report
                    </a>
                    <button onclick="sendEmail()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">
                        <i class="fas fa-envelope mr-2"></i> Email
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Report Preview Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <!-- Report Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-800 text-white p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold">SIWOT HOSPITAL</h1>
                        <p class="text-blue-200">Laboratory Department</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-blue-200">P.O. Box 12345, Nairobi</p>
                        <p class="text-sm text-blue-200">Tel: +254 700 000 000</p>
                    </div>
                </div>
            </div>
            
            <!-- Patient & Test Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b border-gray-200">
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Patient Information</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Name:</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Patient ID:</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($request['patient_id']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Age/Gender:</span>
                            <span class="font-medium text-gray-800"><?php echo floor((time() - strtotime($request['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years / <?php echo ucfirst($request['gender']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Contact:</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($request['phone_primary']); ?></span>
                        </div>
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Test Information</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Test Type:</span>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($request['test_type']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Request Date:</span>
                            <span class="font-medium text-gray-800"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Report Date:</span>
                            <span class="font-medium text-gray-800"><?php echo date('M j, Y g:i A', strtotime($request['completed_at'] ?? $request['result_entered_at'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Requested By:</span>
                            <span class="font-medium text-gray-800">Dr. <?php echo htmlspecialchars($request['requested_by_name'] ?? 'Unknown'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Results Table -->
            <div class="p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-3">Test Results</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border border-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Test</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Result</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Unit</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Reference Range</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            if (!empty($request['results_array'])) {
                                // Get default unit and reference from test type
                                $default_unit = $request['test_unit'] ?? '';
                                $default_ref = $request['test_ref'] ?? '';
                                
                                // Map test names to their default units and references
                                $test_defaults = [
                                    'Hemoglobin (HB)' => ['unit' => 'g/dL', 'ref' => '12-17'],
                                    'WBC' => ['unit' => 'x10^9/L', 'ref' => '4-11'],
                                    'Platelets' => ['unit' => 'x10^9/L', 'ref' => '150-400'],
                                    'RBC' => ['unit' => 'x10^12/L', 'ref' => '4.5-5.5'],
                                    'Hematocrit (HCT)' => ['unit' => '%', 'ref' => '36-50'],
                                    'MCV' => ['unit' => 'fL', 'ref' => '80-100'],
                                    'MCH' => ['unit' => 'pg', 'ref' => '27-33'],
                                    'Neutrophils' => ['unit' => '%', 'ref' => '40-70'],
                                    'Lymphocytes' => ['unit' => '%', 'ref' => '20-40'],
                                    'Eosinophils' => ['unit' => '%', 'ref' => '1-6'],
                                    'Fasting Glucose' => ['unit' => 'mg/dL', 'ref' => '70-100'],
                                    'Random Glucose' => ['unit' => 'mg/dL', 'ref' => '<140'],
                                    'Creatinine' => ['unit' => 'mg/dL', 'ref' => '0.6-1.2'],
                                    'BUN' => ['unit' => 'mg/dL', 'ref' => '7-20'],
                                    'Sodium (Na)' => ['unit' => 'mEq/L', 'ref' => '135-145'],
                                    'Potassium (K)' => ['unit' => 'mEq/L', 'ref' => '3.5-5.0'],
                                    'Chloride (Cl)' => ['unit' => 'mEq/L', 'ref' => '96-106'],
                                    'Total Bilirubin' => ['unit' => 'mg/dL', 'ref' => '0.1-1.2'],
                                    'ALT' => ['unit' => 'U/L', 'ref' => '7-56'],
                                    'AST' => ['unit' => 'U/L', 'ref' => '10-40'],
                                    'Alkaline Phosphatase' => ['unit' => 'U/L', 'ref' => '44-147'],
                                    'Total Protein' => ['unit' => 'g/dL', 'ref' => '6-8'],
                                    'Albumin' => ['unit' => 'g/dL', 'ref' => '3.5-5.5'],
                                    'Uric Acid' => ['unit' => 'mg/dL', 'ref' => '3.5-7.2'],
                                    'Cholesterol' => ['unit' => 'mg/dL', 'ref' => '<200'],
                                    'Triglycerides' => ['unit' => 'mg/dL', 'ref' => '<150'],
                                    'Appearance' => ['unit' => 'qualitative', 'ref' => 'Clear'],
                                    'Color' => ['unit' => 'qualitative', 'ref' => 'Yellow'],
                                    'Specific Gravity' => ['unit' => 'g/mL', 'ref' => '1.005-1.030'],
                                    'pH' => ['unit' => 'pH', 'ref' => '5-7'],
                                    'Protein' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                                    'Glucose' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                                    'Ketones' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                                    'Blood' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                                    'Leukocytes' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                                    'Nitrites' => ['unit' => 'qualitative', 'ref' => 'Negative'],
                                    'Pus Cells' => ['unit' => '/HPF', 'ref' => '0-5'],
                                    'RBCs' => ['unit' => '/HPF', 'ref' => '0-2'],
                                    'Epithelial Cells' => ['unit' => '/HPF', 'ref' => '0-5'],
                                ];
                                
                                foreach ($request['results_array'] as $result) {
                                    $test_name = $result['test'] ?? '';
                                    $value = $result['value'] ?? '';
                                    $unit = $result['unit'] ?? ($test_defaults[$test_name]['unit'] ?? $default_unit);
                                    $ref = $result['reference_range'] ?? ($test_defaults[$test_name]['ref'] ?? $default_ref);
                                    
                                    echo '<tr>';
                                    echo '<td class="py-3 px-4 text-gray-800">' . htmlspecialchars($test_name) . '</td>';
                                    echo '<td class="py-3 px-4 font-medium text-gray-800">' . htmlspecialchars($value) . '</td>';
                                    echo '<td class="py-3 px-4 text-gray-500">' . htmlspecialchars($unit) . '</td>';
                                    echo '<td class="py-3 px-4 text-gray-500">' . htmlspecialchars($ref ?: '-') . '</td>';
                                    echo '<td class="py-3 px-4"><span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs">Normal</span></td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5" class="py-4 text-center text-gray-500">No results available</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php if ($request['notes']): ?>
            <div class="px-6 pb-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-2">Lab Notes</h3>
                <p class="text-gray-700 bg-gray-50 p-3 rounded"><?php echo htmlspecialchars($request['notes']); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="bg-gray-50 p-6 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        <p>This is a computer-generated report. No signature required.</p>
                        <p>Report ID: <?php echo $request_id; ?> | Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($lab_role_title); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($request['result_by_name'] ?? ''); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Email Modal -->
<div id="emailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-md w-full p-4 sm:p-6 mx-auto" style="margin-top: 100px;">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-envelope text-blue-600 mr-2"></i> Send Lab Report via Email
        </h3>
        
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Recipient Email Address</label>
            <input type="email" id="recipientEmail" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                   placeholder="patient@example.com"
                   value="<?php echo isset($request) && isset($request['patient_email']) ? htmlspecialchars($request['patient_email']) : ''; ?>">
            <p class="text-xs text-gray-500 mt-1">Enter the email address where you want to send this lab report</p>
        </div>
        
        <div class="flex justify-end gap-3">
            <button type="button" onclick="closeEmailModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <button type="button" id="sendEmailBtn" onclick="submitEmail()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                <i class="fas fa-paper-plane mr-2"></i> Send
            </button>
        </div>
    </div>
</div>

<script>
<?php if (isset($request) && $request): ?>
    const requestId = <?php echo json_encode($request_id); ?>;
    const csrfToken = <?php echo json_encode($csrf_token ?? ''); ?>;
<?php else: ?>
    const requestId = null;
    const csrfToken = '';
<?php endif; ?>

    function sendEmail() {
        document.getElementById('emailModal').classList.remove('hidden');
    }
    
    function closeEmailModal() {
        document.getElementById('emailModal').classList.add('hidden');
    }
    
    function submitEmail() {
        const email = document.getElementById('recipientEmail').value;
        
        if (!email) {
            showToast('Please enter an email address', 'error');
            return;
        }
        
        if (!requestId) {
            showToast('Invalid request', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('request_id', requestId);
        formData.append('email', email);
        formData.append('csrf_token', csrfToken);
        
        const btn = document.getElementById('sendEmailBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
        
        fetch('../api/send_lab_report_email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeEmailModal();
            } else {
                showToast(data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Error sending email', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send';
        });
    }
</script>

<?php
require_once '../includes/footer.php';
?>