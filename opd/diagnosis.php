<?php
/**
 * Hospital Management System - OPD Diagnosis
 * Diagnosis history and chronic condition management
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Patient Diagnosis History';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get patient ID from URL
$patient_id = $_GET['patient_id'] ?? 0;
$patient = null;
$diagnoses = [];
$chronic_conditions = [];
$allergies = [];

if ($patient_id) {
    try {
        $db = Database::getInstance();
        
        // Get patient details
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get chronic conditions from patient record
        $patient_chronic_conditions = [];
        if ($patient && !empty($patient['chronic_conditions'])) {
            $patient_chronic_conditions = array_map('trim', explode(',', $patient['chronic_conditions']));
        }
        
        // Get allergies
        if ($patient && $patient['allergies']) {
            $allergies = array_map('trim', explode(',', $patient['allergies']));
        }
        
        // Get diagnosis history
        $stmt = $db->prepare("SELECT c.*, u.username as doctor_name 
                              FROM consultations c 
                              LEFT JOIN users u ON c.doctor_id = u.id 
                              WHERE c.patient_id = ? AND c.diagnosis IS NOT NULL AND c.diagnosis != '' 
                              ORDER BY c.consultation_date DESC");
        $stmt->execute([$patient_id]);
        $diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get chronic conditions (if there's a flag in the database, or infer from recurring diagnoses)
        // For now, we'll mark diagnoses with 'chronic' keyword as chronic
        foreach ($diagnoses as $diagnosis) {
            if (stripos($diagnosis['diagnosis'], 'chronic') !== false || 
                stripos($diagnosis['notes'], 'chronic') !== false) {
                $chronic_conditions[] = $diagnosis;
            }
        }
        
    } catch (Exception $e) {
        $error = 'Error loading patient data: ' . $e->getMessage();
    }
}

// Get all patients for search
$patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = TRUE ORDER BY first_name LIMIT 50");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-diagnoses text-primary-600 mr-2"></i> Diagnosis History
                </h1>
                <p class="text-gray-500 mt-1">View patient diagnosis records and chronic conditions</p>
            </div>
        </div>
    </div>
    
    <!-- Patient Search -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <select name="patient_id" onchange="this.form.submit()" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $p): ?>
                    <option value="<?php echo $p['id']; ?>" <?php echo $patient_id == $p['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_id'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($patient): ?>
            <a href="consultation.php?patient_id=<?php echo $patient_id; ?>" 
               class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-stethoscope mr-2"></i> New Consultation
            </a>
            <?php endif; ?>
        </form>
    </div>
    
    <?php if ($patient): ?>
    <!-- Patient Info -->
    <div class="bg-gradient-to-r from-primary-50 to-teal-50 rounded-xl p-6 border border-primary-100 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-xl font-bold">
                        <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                    </span>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </h2>
                    <p class="text-sm text-gray-600">
                        <?php echo htmlspecialchars($patient['patient_id']); ?> • 
                        <?php echo floor((time() - strtotime($patient['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years • 
                        <?php echo ucfirst($patient['gender']); ?>
                    </p>
                </div>
            </div>
            
            <!-- Allergy Alerts -->
            <?php if (!empty($allergies)): ?>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($allergies as $allergy): ?>
                <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    <?php echo htmlspecialchars(trim($allergy)); ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Patient Chronic Conditions -->
    <?php if (!empty($patient_chronic_conditions)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-heartbeat text-red-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800">Chronic Conditions</h3>
        </div>
        
        <div class="flex flex-wrap gap-2">
            <?php foreach ($patient_chronic_conditions as $condition): ?>
            <span class="px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-red-700 font-medium">
                <?php echo htmlspecialchars(trim($condition)); ?>
            </span>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Chronic Conditions from Diagnoses -->
    <?php if (!empty($chronic_conditions)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center">
                <i class="fas fa-exclamation-circle text-red-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800">Chronic Conditions</h3>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($chronic_conditions as $condition): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="font-medium text-red-800"><?php echo htmlspecialchars($condition['diagnosis']); ?></span>
                    <span class="px-2 py-1 bg-red-200 text-red-800 rounded text-xs">Chronic</span>
                </div>
                <p class="text-sm text-red-600">
                    <?php echo date('M j, Y', strtotime($condition['consultation_date'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Diagnosis History -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-history text-gray-400 mr-2"></i> Visit History
            </h3>
        </div>
        
        <?php if (empty($diagnoses)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
            <p>No diagnosis records found</p>
            <a href="consultation.php?patient_id=<?php echo $patient_id; ?>" class="text-primary-600 hover:underline mt-2 inline-block">
                Start new consultation
            </a>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($diagnoses as $index => $record): ?>
            <div class="p-4 hover:bg-gray-50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm font-medium text-gray-800">
                                <?php echo htmlspecialchars($record['diagnosis']); ?>
                            </span>
                            <?php if (stripos($record['diagnosis'], 'chronic') !== false): ?>
                            <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">Chronic</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($record['chief_complaint']): ?>
                        <p class="text-sm text-gray-600 mb-1">
                            <span class="font-medium">Complaint:</span> 
                            <?php echo htmlspecialchars($record['chief_complaint']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if ($record['treatment_plan']): ?>
                        <p class="text-sm text-gray-600 mb-1">
                            <span class="font-medium">Treatment:</span> 
                            <?php echo htmlspecialchars($record['treatment_plan']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-4 mt-2 text-xs text-gray-500">
                            <span>
                                <i class="fas fa-user-md mr-1"></i>
                                Dr. <?php echo htmlspecialchars($record['doctor_name'] ?: 'Unknown'); ?>
                            </span>
                            <span>
                                <i class="fas fa-calendar mr-1"></i>
                                <?php echo date('M j, Y g:i A', strtotime($record['consultation_date'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <button onclick="toggleDetails(<?php echo $index; ?>)" 
                            class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded">
                        <i class="fas fa-chevron-down" id="chevron-<?php echo $index; ?>"></i>
                    </button>
                </div>
                
                <!-- Collapsible Details -->
                <div id="details-<?php echo $index; ?>" class="hidden mt-4 pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Differential Diagnosis</p>
                            <p class="text-sm text-gray-700"><?php echo $record['differential'] ?: '-'; ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Physical Exam Findings</p>
                            <p class="text-sm text-gray-700"><?php echo $record['examination_notes'] ?: '-'; ?></p>
                        </div>
                    </div>
                    
                    <div class="mt-4 flex gap-2">
                        <a href="prescriptions.php?patient_id=<?php echo $patient_id; ?>&consultation_id=<?php echo $record['id']; ?>" 
                           class="px-3 py-1 bg-orange-100 text-orange-700 rounded text-sm hover:bg-orange-200">
                            <i class="fas fa-prescription mr-1"></i> View Prescription
                        </a>
                        <a href="consultation.php?patient_id=<?php echo $patient_id; ?>" 
                           class="px-3 py-1 bg-blue-100 text-blue-700 rounded text-sm hover:bg-blue-200">
                            <i class="fas fa-edit mr-1"></i> Edit Consultation
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- No patient selected -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-search text-gray-400 text-3xl"></i>
        </div>
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Select a Patient</h3>
        <p class="text-gray-500">Choose a patient to view their diagnosis history</p>
    </div>
    <?php endif; ?>
</div>

<script>
    function toggleDetails(index) {
        const details = document.getElementById('details-' + index);
        const chevron = document.getElementById('chevron-' + index);
        
        if (details.classList.contains('hidden')) {
            details.classList.remove('hidden');
            chevron.classList.add('rotate-180');
        } else {
            details.classList.add('hidden');
            chevron.classList.remove('rotate-180');
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>
