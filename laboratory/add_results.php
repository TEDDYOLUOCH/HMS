<?php
/**
 * Hospital Management System - Laboratory Results Entry
 * Result entry form per test type
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'lab_technologist', 'lab_scientist'], '../dashboard');

// Set page title
$page_title = 'Enter Lab Results';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_results'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $request_id = $_POST['request_id'] ?? 0;
            $results = $_POST['results'] ?? [];
            $notes = $_POST['notes'] ?? '';
            $is_critical = isset($_POST['is_critical']);
            $needs_verification = isset($_POST['needs_verification']);
            
            if (empty($results)) {
                $message = 'Please enter at least one result';
                $message_type = 'error';
            } else {
                // Save results as JSON
                $results_json = json_encode($results);
                
                $stmt = $db->prepare("UPDATE lab_requests SET 
                    results = ?, 
                    notes = ?, 
                    is_critical = ?,
                    status = 'Completed',
                    result_entered_by = ?,
                    result_entered_at = NOW(),
                    completed_at = NOW()
                    WHERE id = ?");
                
                $stmt->execute([$results_json, $notes, $is_critical ? 1 : 0, $_SESSION['user_id'], $request_id]);
                
                // Log critical values
                if ($is_critical) {
                    try {
                        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Critical Result', ?, NOW())");
                        $stmt->execute([$_SESSION['user_id'], "Critical lab result for request ID: $request_id"]);
                    } catch (Exception $e) {}
                }
                
                $message = 'Results saved successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error saving results: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get request details
$request_id = $_GET['request_id'] ?? 0;
$request = null;
$patient = null;

if ($request_id) {
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT lr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth,
                             lt.test_name as test_type
                             FROM lab_requests lr
                             LEFT JOIN patients p ON lr.patient_id = p.id
                             LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                             WHERE lr.id = ?");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = 'Request not found';
    }
}

// Test type configurations with reference ranges
$test_config = [
    'hematology' => [
        'name' => 'Hematology',
        'tests' => [
            ['name' => 'Hemoglobin (HB)', 'unit' => 'g/dL', 'normal' => '12-17', 'low' => '<10', 'high' => '>18'],
            ['name' => 'WBC', 'unit' => 'x10^9/L', 'normal' => '4-11', 'low' => '<3', 'high' => '>12'],
            ['name' => 'Platelets', 'unit' => 'x10^9/L', 'normal' => '150-400', 'low' => '<100', 'high' => '>500'],
            ['name' => 'RBC', 'unit' => 'x10^12/L', 'normal' => '4.5-5.5', 'low' => '<3.5', 'high' => '>6.0'],
            ['name' => 'Hematocrit (HCT)', 'unit' => '%', 'normal' => '36-50', 'low' => '<30', 'high' => '>55'],
            ['name' => 'MCV', 'unit' => 'fL', 'normal' => '80-100', 'low' => '<70', 'high' => '>110'],
            ['name' => 'MCH', 'unit' => 'pg', 'normal' => '27-33', 'low' => '<20', 'high' => '>36'],
            ['name' => 'Neutrophils', 'unit' => '%', 'normal' => '40-70', 'low' => '<30', 'high' => '>80'],
            ['name' => 'Lymphocytes', 'unit' => '%', 'normal' => '20-40', 'low' => '<15', 'high' => '>50'],
            ['name' => 'Eosinophils', 'unit' => '%', 'normal' => '1-6', 'low' => '<1', 'high' => '>10'],
        ]
    ],
    'biochemistry' => [
        'name' => 'Biochemistry',
        'tests' => [
            ['name' => 'Fasting Glucose', 'unit' => 'mg/dL', 'normal' => '70-100', 'low' => '<60', 'high' => '>126'],
            ['name' => 'Random Glucose', 'unit' => 'mg/dL', 'normal' => '<140', 'low' => '<60', 'high' => '>200'],
            ['name' => 'Creatinine', 'unit' => 'mg/dL', 'normal' => '0.6-1.2', 'low' => '', 'high' => '>1.5'],
            ['name' => 'BUN', 'unit' => 'mg/dL', 'normal' => '7-20', 'low' => '', 'high' => '>25'],
            ['name' => 'Sodium (Na)', 'unit' => 'mEq/L', 'normal' => '135-145', 'low' => '<125', 'high' => '>155'],
            ['name' => 'Potassium (K)', 'unit' => 'mEq/L', 'normal' => '3.5-5.0', 'low' => '<3.0', 'high' => '>5.5'],
            ['name' => 'Chloride (Cl)', 'unit' => 'mEq/L', 'normal' => '96-106', 'low' => '<90', 'high' => '>110'],
            ['name' => 'Total Bilirubin', 'unit' => 'mg/dL', 'normal' => '0.1-1.2', 'low' => '', 'high' => '>2.0'],
            ['name' => 'ALT', 'unit' => 'U/L', 'normal' => '7-56', 'low' => '', 'high' => '>80'],
            ['name' => 'AST', 'unit' => 'U/L', 'normal' => '10-40', 'low' => '', 'high' => '>60'],
            ['name' => 'Alkaline Phosphatase', 'unit' => 'U/L', 'normal' => '44-147', 'low' => '', 'high' => '>200'],
            ['name' => 'Total Protein', 'unit' => 'g/dL', 'normal' => '6-8', 'low' => '<5', 'high' => '>9'],
            ['name' => 'Albumin', 'unit' => 'g/dL', 'normal' => '3.5-5.5', 'low' => '<2.5', 'high' => ''],
            ['name' => 'Uric Acid', 'unit' => 'mg/dL', 'normal' => '3.5-7.2', 'low' => '', 'high' => '>8.0'],
            ['name' => 'Cholesterol', 'unit' => 'mg/dL', 'normal' => '<200', 'low' => '', 'high' => '>240'],
            ['name' => 'Triglycerides', 'unit' => 'mg/dL', 'normal' => '<150', 'low' => '', 'high' => '>500'],
        ]
    ],
    'serology' => [
        'name' => 'Serology / Immunology',
        'tests' => [
            ['name' => 'HIV 1&2', 'unit' => '', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'HBs Ag', 'unit' => '', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'HCV Ab', 'unit' => '', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'VDRL/RPR', 'unit' => '', 'normal' => 'Non-Reactive', 'low' => '', 'high' => 'Reactive'],
            ['name' => 'Typhoid IgM', 'unit' => '', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Widal Test', 'unit' => '', 'normal' => '<1:80', 'low' => '', 'high' => '>1:80'],
            ['name' => 'ASO Titer', 'unit' => 'IU/mL', 'normal' => '<200', 'low' => '', 'high' => '>200'],
            ['name' => 'Rheumatoid Factor', 'unit' => 'IU/mL', 'normal' => '<20', 'low' => '', 'high' => '>20'],
        ]
    ],
    'urine' => [
        'name' => 'Urine Analysis',
        'tests' => [
            ['name' => 'Appearance', 'unit' => 'qualitative', 'normal' => 'Clear', 'low' => '', 'high' => 'Turbid'],
            ['name' => 'Color', 'unit' => 'qualitative', 'normal' => 'Yellow', 'low' => '', 'high' => ''],
            ['name' => 'Specific Gravity', 'unit' => 'g/mL', 'normal' => '1.005-1.030', 'low' => '<1.005', 'high' => '>1.030'],
            ['name' => 'pH', 'unit' => 'pH', 'normal' => '5-7', 'low' => '<4.5', 'high' => '>8.0'],
            ['name' => 'Protein', 'unit' => 'qualitative', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Glucose', 'unit' => 'qualitative', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Ketones', 'unit' => 'qualitative', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Blood', 'unit' => 'qualitative', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Leukocytes', 'unit' => 'qualitative', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Nitrites', 'unit' => 'qualitative', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Pus Cells', 'unit' => '/HPF', 'normal' => '0-5', 'low' => '', 'high' => '>10'],
            ['name' => 'RBCs', 'unit' => '/HPF', 'normal' => '0-2', 'low' => '', 'high' => '>5'],
            ['name' => 'Epithelial Cells', 'unit' => '/HPF', 'normal' => '0-5', 'low' => '', 'high' => '>10'],
        ]
    ],
    'stool' => [
        'name' => 'Stool Analysis',
        'tests' => [
            ['name' => 'Appearance', 'unit' => '', 'normal' => 'Formed', 'low' => '', 'high' => 'Watery'],
            ['name' => 'Color', 'unit' => '', 'normal' => 'Brown', 'low' => '', 'high' => ''],
            ['name' => 'Consistency', 'unit' => '', 'normal' => 'Soft', 'low' => '', 'high' => 'Watery'],
            ['name' => 'Mucus', 'unit' => '', 'normal' => 'None', 'low' => '', 'high' => 'Present'],
            ['name' => 'Blood', 'unit' => '', 'normal' => 'Negative', 'low' => '', 'high' => 'Positive'],
            ['name' => 'Worms/Ova', 'unit' => '', 'normal' => 'Not seen', 'low' => '', 'high' => 'Seen'],
            ['name' => 'Giardia', 'unit' => '', 'normal' => 'Not seen', 'low' => '', 'high' => 'Seen'],
            ['name' => 'Pus Cells', 'unit' => '/HPF', 'normal' => '0-2', 'low' => '', 'high' => '>5'],
        ]
    ],
];

// Determine test category from request
$test_category = 'hematology';
if ($request) {
    $test_type = strtolower($request['test_type']);
    foreach (array_keys($test_config) as $key) {
        if (strpos($test_type, $key) !== false) {
            $test_category = $key;
            break;
        }
    }
}

// Generate CSRF token
$csrf_token = csrfToken();
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-vial text-purple-600 mr-2"></i> Enter Lab Results
                </h1>
                <p class="text-gray-500 mt-1">Record test results and generate reports</p>
            </div>
            <a href="lab_requests" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Requests
            </a>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert-auto-hide rounded-lg p-4 mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3"></i>
            <p class="<?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!$request): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <i class="fas fa-exclamation-circle text-4xl text-gray-300 mb-4"></i>
        <p class="text-gray-500 mb-4">Request not found</p>
        <a href="lab_requests" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
            <i class="fas fa-arrow-left mr-2"></i> Back to Lab Requests
        </a>
    </div>
    <?php else: ?>
    <!-- Patient Info -->
    <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl p-4 border border-purple-100 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-bold">
                        <?php echo strtoupper(substr($request['first_name'], 0, 1)); ?>
                    </span>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">
                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                    </h2>
                    <p class="text-sm text-gray-600">
                        <?php echo htmlspecialchars($request['patient_id']); ?> • 
                        <?php echo floor((time() - strtotime($request['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years • 
                        <?php echo ucfirst($request['gender']); ?>
                    </p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm text-gray-500">Test Type</p>
                <p class="font-semibold text-purple-700"><?php echo htmlspecialchars($request['test_type']); ?></p>
            </div>
        </div>
    </div>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="save_results" value="1">
        <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
        
        <!-- Test Results -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-list-alt text-gray-400 mr-2"></i> 
                    <?php echo $test_config[$test_category]['name']; ?> Results
                </h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Test</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Result</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Unit</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Reference Range</th>
                            <th class="text-center py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($test_config[$test_category]['tests'] as $index => $test): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($test['name']); ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <input type="text" 
                                       name="results[<?php echo $index; ?>][value]" 
                                       class="result-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                                       data-normal="<?php echo htmlspecialchars($test['normal']); ?>"
                                       data-low="<?php echo htmlspecialchars($test['low']); ?>"
                                       data-high="<?php echo htmlspecialchars($test['high']); ?>"
                                       placeholder="Enter result">
                                <input type="hidden" name="results[<?php echo $index; ?>][test]" value="<?php echo htmlspecialchars($test['name']); ?>">
                                <input type="hidden" name="results[<?php echo $index; ?>][unit]" value="<?php echo htmlspecialchars($test['unit'] ?? ''); ?>">
                                <input type="hidden" name="results[<?php echo $index; ?>][reference_range]" value="<?php echo htmlspecialchars($test['normal'] ?? ''); ?>">
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($test['unit']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($test['normal']); ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="result-status inline-block px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600">
                                    -
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Notes and Actions -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-sticky-note text-gray-400 mr-2"></i> Lab Notes
                </h3>
                <textarea name="notes" rows="4"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                          placeholder="Enter any additional notes or observations..."></textarea>
                
                <div class="mt-4 space-y-3">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="is_critical" id="isCritical" class="rounded text-red-600"
                               onchange="toggleCriticalAlert()">
                        <span class="text-sm text-red-700 font-medium">Mark as Critical Result</span>
                    </label>
                    
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="needs_verification" class="rounded text-blue-600">
                        <span class="text-sm text-gray-700">Send for Senior Verification</span>
                    </label>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-bolt text-gray-400 mr-2"></i> Actions
                </h3>
                
                <div class="space-y-3">
                    <button type="submit" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                        <i class="fas fa-save mr-2"></i> Save Results
                    </button>
                    
                    <a href="lab_reports.php?request_id=<?php echo $request_id; ?>" class="block w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-center transition">
                        <i class="fas fa-file-pdf mr-2"></i> Generate Report
                    </a>
                </div>
                
                <!-- Critical Value Warning -->
                <div id="criticalWarning" class="hidden mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                        <span class="text-sm text-red-700 font-medium">Critical value will be logged and alerted</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
    // Auto-check result status based on reference ranges
    const resultInputs = document.querySelectorAll('.result-input');
    
    resultInputs.forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const statusSpan = row.querySelector('.result-status');
            const normal = this.dataset.normal.toLowerCase();
            const low = this.dataset.low.toLowerCase();
            const high = this.dataset.high.toLowerCase();
            const value = this.value.toLowerCase().trim();
            
            if (!value) {
                statusSpan.className = 'result-status inline-block px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600';
                statusSpan.textContent = '-';
                return;
            }
            
            let isNormal = false;
            let isHigh = false;
            let isLow = false;
            
            // Check if value contains the normal range text
            if (normal.includes('negative') || normal.includes('positive') || normal.includes('reactive') || normal.includes('non-reactive')) {
                if (value === normal) {
                    isNormal = true;
                } else if ((normal.includes('negative') || normal.includes('non-reactive')) && (value.includes('positive') || value.includes('reactive'))) {
                    isHigh = true;
                } else if ((normal.includes('positive') || normal.includes('reactive')) && value.includes('negative')) {
                    isLow = true;
                }
            } else {
                // Numeric comparison
                const numValue = parseFloat(value);
                const numNormal = parseFloat(normal.split('-')[0]);
                const numHigh = high ? parseFloat(high.replace('>', '')) : null;
                const numLow = low ? parseFloat(low.replace('<', '')) : null;
                
                if (!isNaN(numValue)) {
                    if (numLow && numValue < numLow) {
                        isLow = true;
                    } else if (numHigh && numValue > numHigh) {
                        isHigh = true;
                    } else if (normal.includes('-')) {
                        const parts = normal.split('-');
                        const min = parseFloat(parts[0]);
                        const max = parseFloat(parts[1]);
                        if (numValue >= min && numValue <= max) {
                            isNormal = true;
                        } else if (numValue < min) {
                            isLow = true;
                        } else {
                            isHigh = true;
                        }
                    } else if (normal.includes('<')) {
                        if (numValue <= parseFloat(normal.replace('<', ''))) {
                            isNormal = true;
                        } else {
                            isHigh = true;
                        }
                    } else if (normal.includes('>')) {
                        if (numValue >= parseFloat(normal.replace('>', ''))) {
                            isNormal = true;
                        } else {
                            isLow = true;
                        }
                    }
                } else {
                    // Text comparison
                    if (value === normal) {
                        isNormal = true;
                    }
                }
            }
            
            if (isNormal) {
                statusSpan.className = 'result-status inline-block px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-700';
                statusSpan.textContent = 'Normal';
            } else if (isLow) {
                statusSpan.className = 'result-status inline-block px-2 py-1 rounded text-xs font-medium bg-blue-100 text-blue-700';
                statusSpan.textContent = 'Low';
            } else if (isHigh) {
                statusSpan.className = 'result-status inline-block px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700';
                statusSpan.textContent = 'High';
            } else {
                statusSpan.className = 'result-status inline-block px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600';
                statusSpan.textContent = '-';
            }
        });
    });
    
    function toggleCriticalAlert() {
        const warning = document.getElementById('criticalWarning');
        const checkbox = document.getElementById('isCritical');
        if (checkbox.checked) {
            warning.classList.remove('hidden');
        } else {
            warning.classList.add('hidden');
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>
