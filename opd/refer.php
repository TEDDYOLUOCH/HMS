<?php
/**
 * Hospital Management System - OPD Refer Patient
 * Patient referral interface
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Refer Patient';

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

// Get departments for dropdown
$departments = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT name FROM departments WHERE status = 'active' ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist, use empty array
}

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_referral']) && $patient) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $refer_to_department = $_POST['refer_to_department'] ?? '';
            $refer_to_facility = trim($_POST['refer_to_facility'] ?? '');
            $refer_reason = trim($_POST['refer_reason'] ?? '');
            $refer_notes = trim($_POST['refer_notes'] ?? '');
            $urgency = $_POST['urgency'] ?? 'Normal';
            
            if (empty($refer_to_department) && empty($refer_to_facility)) {
                $message = 'Please specify a department or facility to refer to';
                $message_type = 'error';
            } elseif (empty($refer_reason)) {
                $message = 'Reason for referral is required';
                $message_type = 'error';
            } else {
                // Save referral to database
                $consultation_id_value = !empty($consultation_id) ? $consultation_id : null;
                $stmt = $db->prepare("INSERT INTO referrals (patient_id, consultation_id, refer_to_department, refer_to_facility, refer_reason, refer_notes, urgency, referred_by, referral_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'Pending')");
                $stmt->execute([
                    $patient_id,
                    $consultation_id_value,
                    $refer_to_department,
                    $refer_to_facility,
                    $refer_reason,
                    $refer_notes,
                    $urgency,
                    $_SESSION['user_id']
                ]);
                
                $referral_id = $db->lastInsertId();
                
                // Log the activity
                logActivity('Referred', 'Patients', 'patients', $patient_id, 
                    "Referred to: $refer_to_department $refer_to_facility, Reason: $refer_reason");
                
                $message = 'Patient referred successfully! Referral ID: #' . $referral_id;
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
                    <i class="fas fa-share text-primary-600 mr-2"></i> Refer Patient
                </h1>
                <p class="text-gray-500 mt-1">Refer patient to another department or facility</p>
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
    
    <!-- Referral Form -->
    <form method="POST" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="submit_referral" value="1">
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-hospital text-gray-400 mr-2"></i> Referral Details
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Refer to Department</label>
                    <select name="refer_to_department" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept['name']); ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($departments)): ?>
                        <option value="OPD">OPD (Outpatient)</option>
                        <option value="Laboratory">Laboratory</option>
                        <option value="Pharmacy">Pharmacy</option>
                        <option value="Nursing">Nursing/Ward</option>
                        <option value="MCH">MCH (Maternal & Child Health)</option>
                        <option value="Theatre">Theatre</option>
                        <option value="Radiology">Radiology/X-Ray</option>
                        <option value="Dental">Dental</option>
                        <option value="Physiotherapy">Physiotherapy</option>
                        <option value="Nutrition">Nutrition</option>
                        <option value="Social Services">Social Services</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Or Refer to External Facility</label>
                    <input type="text" name="refer_to_facility" placeholder="e.g., Kenyatta National Hospital" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Urgency <span class="text-red-500">*</span></label>
                    <select name="urgency" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="Normal">Normal</option>
                        <option value="Urgent">Urgent</option>
                        <option value="Emergency">Emergency</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Referral <span class="text-red-500">*</span></label>
                    <textarea name="refer_reason" rows="3" required placeholder="Why is the patient being referred?"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes</label>
                    <textarea name="refer_notes" rows="3" placeholder="Any additional information for the receiving facility..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                </div>
            </div>
        </div>
        
        <!-- Submit -->
        <div class="flex items-center justify-end gap-4">
            <a href="consultation.php?patient_id=<?php echo (int)$patient_id; ?>" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-smooth">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-smooth">
                <i class="fas fa-share mr-2"></i> Submit Referral
            </button>
        </div>
    </form>
    
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>
