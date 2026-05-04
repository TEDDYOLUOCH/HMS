<?php
/**
 * Hospital Management System - OPD Prescriptions
 * Drug prescription interface with interaction warnings
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Prescriptions';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prescription'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $patient_id = $_POST['patient_id'] ?? 0;
            $consultation_id = $_POST['consultation_id'] ?? 0;
            $drug_name = trim($_POST['drug_name'] ?? '');
            $dosage = trim($_POST['dosage'] ?? '');
            $frequency = $_POST['frequency'] ?? '';
            $duration = $_POST['duration'] ?? 1;
            $duration_unit = $_POST['duration_unit'] ?? 'days';
            $instructions = trim($_POST['instructions'] ?? '');
            $send_to_pharmacy = isset($_POST['send_to_pharmacy']);
            
            if (empty($patient_id) || empty($drug_name) || empty($dosage) || empty($frequency)) {
                $message = 'Drug name, dosage, and frequency are required';
                $message_type = 'error';
            } else {
                // Calculate end date
                $end_date = date('Y-m-d', strtotime("+ $duration $duration_unit"));
                
                // Insert prescription
                $stmt = $db->prepare("INSERT INTO prescriptions 
                    (visit_id, patient_id, consultation_id, drug_id, drug_name, dosage, frequency, duration, duration_unit, end_date, instructions, status, prescribed_by, prescribed_at)
                    VALUES (NULL, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $status = $send_to_pharmacy ? 'Pending' : 'Completed';
                $stmt->execute([
                    $patient_id, $consultation_id, $drug_name, $dosage, $frequency,
                    $duration, $duration_unit, $end_date, $instructions, $status,
                    $_SESSION['user_id']
                ]);
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Prescribed', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "Prescribed $drug_name for patient ID $patient_id"]);
                } catch (Exception $e) {}
                
                $message = $send_to_pharmacy ? 'Prescription sent to pharmacy!' : 'Prescription saved successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error saving prescription: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get current patient
$patient_id = $_GET['patient_id'] ?? 0;
$consultation_id = $_GET['consultation_id'] ?? 0;
$patient = null;
$consultation = null;

if ($patient_id) {
    try {
        $db = Database::getInstance();
        
        // Get patient details
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get consultation details
        if ($consultation_id) {
            $stmt = $db->prepare("SELECT * FROM consultations WHERE id = ?");
            $stmt->execute([$consultation_id]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {}
}

// Get drug list from pharmacy
$drugs = [];
try {
    $db = Database::getInstance();
    // Try different table/column names that might exist
    try {
        $stmt = $db->query("SELECT id, brand_name, generic_name, strength, stock_quantity FROM drugs WHERE stock_quantity > 0 ORDER BY brand_name LIMIT 50");
        $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Try alternative table name
        try {
            $stmt = $db->query("SELECT id, drug_name as brand_name, NULL as generic_name, NULL as strength, quantity as stock_quantity FROM pharmacy_drugs WHERE quantity > 0 ORDER BY drug_name LIMIT 50");
            $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {}
    }
} catch (Exception $e) {}

// Get previous prescriptions for interaction check
$previous_prescriptions = [];
if ($patient_id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM prescriptions WHERE patient_id = ? AND end_date >= CURDATE() ORDER BY prescribed_at DESC");
        $stmt->execute([$patient_id]);
        $previous_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
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
                    <i class="fas fa-prescription text-orange-600 mr-2"></i> New Prescription
                </h1>
                <p class="text-gray-500 mt-1">Create and manage patient prescriptions</p>
            </div>
            <a href="consultation.php<?php echo $patient_id ? '?patient_id=' . $patient_id : ''; ?>" 
               class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Consultation
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
    
    <?php if ($patient): ?>
    <!-- Patient Info Banner -->
    <div class="bg-gradient-to-r from-orange-50 to-yellow-50 rounded-xl p-4 border border-orange-100 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-lg font-bold">
                        <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                    </span>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </h2>
                    <p class="text-sm text-gray-600">
                        <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                        <?php echo floor((time() - strtotime($patient['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years • 
                        <?php echo ucfirst($patient['gender']); ?>
                    </p>
                </div>
            </div>
            
            <!-- Allergy Alert -->
            <?php if ($patient['allergies']): ?>
            <div class="flex items-center gap-2 bg-red-100 px-3 py-2 rounded-lg">
                <i class="fas fa-exclamation-triangle text-red-600"></i>
                <span class="text-sm font-medium text-red-700">
                    Allergies: <?php echo htmlspecialchars($patient['allergies']); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Current Diagnosis -->
        <?php if ($consultation && $consultation['diagnosis']): ?>
        <div class="mt-3 pt-3 border-t border-orange-100">
            <span class="text-sm font-medium text-gray-600">Diagnosis:</span>
            <span class="text-sm text-gray-800"><?php echo htmlspecialchars($consultation['diagnosis']); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Drug Interaction Warning -->
    <?php if (!empty($previous_prescriptions)): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
        <div class="flex items-start gap-3">
            <i class="fas fa-exclamation-triangle text-yellow-600 mt-1"></i>
            <div>
                <p class="font-medium text-yellow-800">Current Medications</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <?php foreach ($previous_prescriptions as $rx): ?>
                    <span class="px-2 py-1 bg-white border border-yellow-200 rounded text-sm">
                        <?php echo htmlspecialchars($rx['drug_name'] . ' ' . $rx['dosage']); ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Prescription Form -->
        <div class="lg:col-span-2">
            <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="save_prescription" value="1">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="consultation_id" value="<?php echo $consultation_id; ?>">
                
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-pills text-gray-400 mr-2"></i> Prescription Details
                </h3>
                
                <div class="space-y-4">
                    <!-- Drug Search -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Drug Name <span class="text-red-500">*</span></label>
                        <input type="text" name="drug_name" id="drugName" required list="drugList"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="Search or type drug name...">
                        <datalist id="drugList">
                            <?php foreach ($drugs as $drug): ?>
                            <option value="<?php echo htmlspecialchars(($drug['brand_name'] ?? $drug['generic_name']) . ' ' . $drug['strength']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    
                    <!-- Dosage -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dosage <span class="text-red-500">*</span></label>
                        <input type="text" name="dosage" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                               placeholder="e.g., 500mg, 10ml, 1 tablet">
                    </div>
                    
                    <!-- Frequency -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Frequency <span class="text-red-500">*</span></label>
                            <select name="frequency" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                                <option value="">Select Frequency</option>
                                <option value="OD">OD (Once Daily)</option>
                                <option value="BD">BD (Twice Daily)</option>
                                <option value="TDS">TDS (Three Times Daily)</option>
                                <option value="QID">QID (Four Times Daily)</option>
                                <option value="Q4H">Q4H (Every 4 Hours)</option>
                                <option value="Q6H">Q6H (Every 6 Hours)</option>
                                <option value="Q8H">Q8H (Every 8 Hours)</option>
                                <option value="Q12H">Q12H (Every 12 Hours)</option>
                                <option value="PRN">PRN (As Required)</option>
                                <option value="STAT">STAT (Immediately)</option>
                                <option value="NOCTE">NOCTE (At Night)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
                            <div class="flex gap-2">
                                <input type="number" name="duration" value="7" min="1" max="365"
                                       class="w-20 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <select name="duration_unit" class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months">Months</option>
                                </select>
                            </div>
                            <p class="text-xs text-gray-500 mt-1" id="endDateDisplay">End date: <?php echo date('M j, Y', strtotime('+7 days')); ?></p>
                        </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructions for Patient</label>
                        <textarea name="instructions" rows="3"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                                  placeholder="e.g., Take after food, Complete the full course..."></textarea>
                    </div>
                    
                    <!-- Send to Pharmacy -->
                    <div class="flex items-center gap-3 pt-2">
                        <input type="checkbox" name="send_to_pharmacy" id="sendToPharmacy" checked
                               class="w-4 h-4 text-primary-600 rounded">
                        <label for="sendToPharmacy" class="text-sm text-gray-700">
                            Send to pharmacy queue for dispensing
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                    <button type="button" onclick="this.form.reset()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        <i class="fas fa-redo mr-2"></i> Reset
                    </button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        <i class="fas fa-save mr-2"></i> Save Prescription
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Right Sidebar -->
        <div class="space-y-6">
            <!-- Quick Reference -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-800 mb-3">
                    <i class="fas fa-info-circle text-gray-400 mr-2"></i> Common Frequencies
                </h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">OD</span>
                        <span class="text-gray-800">Once Daily</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">BD</span>
                        <span class="text-gray-800">Twice Daily</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">TDS</span>
                        <span class="text-gray-800">Three Times Daily</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">QID</span>
                        <span class="text-gray-800">Four Times Daily</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">PRN</span>
                        <span class="text-gray-800">As Required</span>
                    </div>
                </div>
            </div>
            
            <!-- Prescription History -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <h3 class="font-semibold text-gray-800 mb-3">
                    <i class="fas fa-history text-gray-400 mr-2"></i> Today's Prescriptions
                </h3>
                
                <?php 
                // Get today's prescriptions for this patient
                $today_prescriptions = [];
                try {
                    $db = Database::getInstance();
                    $stmt = $db->prepare("SELECT * FROM prescriptions WHERE patient_id = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC");
                    $stmt->execute([$patient_id]);
                    $today_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
                ?>
                
                <?php if (empty($today_prescriptions)): ?>
                <p class="text-sm text-gray-500 text-center py-4">No prescriptions today</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($today_prescriptions as $rx): ?>
                    <div class="p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($rx['drug_name']); ?></span>
                            <span class="px-2 py-0.5 rounded text-xs <?php echo $rx['status'] === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700'; ?>">
                                <?php echo ucfirst($rx['status']); ?>
                            </span>
                        </div>
                        <p class="text-xs text-gray-600 mt-1">
                            <?php echo htmlspecialchars($rx['dosage']); ?> - <?php echo htmlspecialchars($rx['frequency']); ?> for <?php echo $rx['duration'] . ' ' . $rx['duration_unit']; ?>
                        </p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php else: ?>
    <!-- No patient selected -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-user-plus text-gray-400 text-3xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Select a Patient</h3>
        <p class="text-gray-500">Choose a patient from consultation to create prescription</p>
    </div>
    <?php endif; ?>
</div>

<script>
    // Update end date display
    const durationInput = document.querySelector('input[name="duration"]');
    const durationUnit = document.querySelector('select[name="duration_unit"]');
    const endDateDisplay = document.getElementById('endDateDisplay');
    
    function updateEndDate() {
        const duration = parseInt(durationInput.value) || 1;
        const unit = durationUnit.value;
        
        let days = duration;
        if (unit === 'weeks') days = duration * 7;
        if (unit === 'months') days = duration * 30;
        
        const endDate = new Date();
        endDate.setDate(endDate.getDate() + days);
        
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        endDateDisplay.textContent = 'End date: ' + endDate.toLocaleDateString('en-US', options);
    }
    
    durationInput.addEventListener('input', updateEndDate);
    durationUnit.addEventListener('change', updateEndDate);
    
    // Drug interaction check (basic example)
    const drugNameInput = document.getElementById('drugName');
    const previousDrugs = <?php echo json_encode(array_column($previous_prescriptions, 'drug_name')); ?>;
    
    drugNameInput.addEventListener('change', function() {
        const newDrug = this.value.toLowerCase();
        
        // Basic example - would need comprehensive database for real interactions
        previousDrugs.forEach(function(drug) {
            if (drug.toLowerCase().includes(newDrug) || newDrug.includes(drug.toLowerCase())) {
                showToast('Warning: ' + drug + ' appears to be already prescribed', 'warning');
            }
        });
    });
</script>

<?php
require_once '../includes/footer.php';
?>
