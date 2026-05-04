<?php
/**
 * Hospital Management System - OPD Admit Patient
 * Patient admission interface
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Admit Patient';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get patient ID from URL
$patient_id = $_GET['patient_id'] ?? 0;
$consultation_id = $_GET['consultation_id'] ?? 0;
$patient = null;

if ($patient_id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = 'Patient not found';
    }
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_admission']) && $patient) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $ward = $_POST['ward'] ?? '';
            $bed_number = trim($_POST['bed_number'] ?? '');
            $admission_reason = trim($_POST['admission_reason'] ?? '');
            $admission_notes = trim($_POST['admission_notes'] ?? '');
            $expected_stay = $_POST['expected_stay'] ?? 1;
            $special_requirements = trim($_POST['special_requirements'] ?? '');
            
            if (empty($ward)) {
                $message = 'Please select a ward';
                $message_type = 'error';
            } elseif (empty($admission_reason)) {
                $message = 'Reason for admission is required';
                $message_type = 'error';
            } else {
                // Generate admission number
                $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(admission_number, 5) AS UNSIGNED)) as max_num FROM admissions WHERE admission_number LIKE 'ADM-" . date('Y') . "-%'");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $next_num = ($result['max_num'] ?? 0) + 1;
                $admission_number = 'ADM-' . date('Y') . '-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);
                
                // Insert admission record
                $stmt = $db->prepare("INSERT INTO admissions (admission_number, patient_id, ward, bed_number, admission_reason, admission_notes, expected_stay_days, special_requirements, admitted_by, admission_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Admitted')");
                $stmt->execute([
                    $admission_number,
                    $patient_id,
                    $ward,
                    $bed_number,
                    $admission_reason,
                    $admission_notes,
                    $expected_stay,
                    $special_requirements,
                    $_SESSION['user_id']
                ]);
                
                $admission_id = $db->lastInsertId();
                
                // Log the activity
                logActivity('Admitted', 'Patients', 'patients', $patient_id, 
                    "Admitted to $ward, Admission #: $admission_number");
                
                $message = 'Patient admitted successfully! Admission #: ' . $admission_number;
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
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
                    <i class="fas fa-procedures text-primary-600 mr-2"></i> Admit Patient
                </h1>
                <p class="text-gray-500 mt-1">Admit patient to inpatient care</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="consultation.php?patient_id=<?php echo (int)$patient_id; ?>" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-smooth">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Consultation
                </a>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <i class="fas fa-<?php echo $message_type === 'success' ? 'check' : 'exclamation'; ?>-circle text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-500 mr-3"></i>
            <p class="text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-800"><?php echo $message; ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!$patient): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-800">Patient not found. <a href="consultation" class="underline">Go back to consultation</a></p>
    </div>
    <?php else: ?>
    
    <!-- Patient Info Card -->
    <div class="bg-gradient-to-r from-primary-50 to-teal-50 rounded-xl p-6 border border-primary-100 mb-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white text-xl font-bold">
                        <?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?>
                    </span>
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-800">
                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                    </p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($patient['patient_id']); ?></p>
                    <p class="text-sm text-gray-500">
                        <?php 
                        $age = floor((time() - strtotime($patient['date_of_birth'])) / (365.25 * 60 * 60 * 24));
                        echo $age . ' years • ' . htmlspecialchars(ucfirst($patient['gender']));
                        ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admission Form -->
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="submit_admission" value="1">
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-bed text-gray-400 mr-2"></i> Admission Details
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Ward <span class="text-red-500">*</span></label>
                    <select name="ward" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Ward</option>
                        <option value="General Ward">General Ward</option>
                        <option value="Male Ward">Male Ward</option>
                        <option value="Female Ward">Female Ward</option>
                        <option value="Pediatric Ward">Pediatric Ward</option>
                        <option value="Maternity Ward">Maternity Ward</option>
                        <option value="NICU">NICU (Newborn Intensive Care)</option>
                        <option value="ICU">ICU (Intensive Care Unit)</option>
                        <option value="HDU">HDU (High Dependency Unit)</option>
                        <option value="Isolation Ward">Isolation Ward</option>
                        <option value="Emergency Ward">Emergency Ward</option>
                        <option value="Surgical Ward">Surgical Ward</option>
                        <option value="Medical Ward">Medical Ward</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bed Number</label>
                    <input type="text" name="bed_number" placeholder="e.g., Bed 101, Ward A" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expected Stay (Days)</label>
                    <input type="number" name="expected_stay" value="1" min="1" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Admission <span class="text-red-500">*</span></label>
                    <textarea name="admission_reason" rows="3" required placeholder="Why is the patient being admitted?"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clinical Notes</label>
                    <textarea name="admission_notes" rows="3" placeholder="Initial clinical assessment and notes..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Special Requirements</label>
                    <textarea name="special_requirements" rows="2" placeholder="Any special care needs, equipment, or accommodations..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Submit -->
        <div class="flex items-center justify-end gap-4">
            <a href="consultation.php?patient_id=<?php echo (int)$patient_id; ?>" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-smooth">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-rose-600 text-white rounded-lg hover:bg-rose-700 transition-smooth">
                <i class="fas fa-procedures mr-2"></i> Admit Patient
            </button>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
