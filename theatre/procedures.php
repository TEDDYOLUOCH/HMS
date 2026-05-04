<?php
/**
 * Hospital Management System - Theatre Procedures
 * Procedure scheduling and surgical records
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'theatre_officer'], '../dashboard');

// Set page title
$page_title = 'Theatre Procedures';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();
        
        // Schedule new procedure
        if (isset($_POST['schedule_procedure'])) {
            try {
                // Validate surgeon exists
                $surgeon_id = $_POST['surgeon'] ?? 0;
                if (!$surgeon_id) {
                    throw new Exception("Please select a surgeon");
                }
                
                // Check if surgeon exists in database
                $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$surgeon_id]);
                if (!$stmt->fetch()) {
                    throw new Exception("Selected surgeon does not exist. Please create users first in Admin > Manage Users");
                }
                
                $stmt = $db->prepare("INSERT INTO theatre_procedures 
                    (patient_id, procedure_name, procedure_category, procedure_date, start_time, surgeon_id, assistant_surgeon_id, anesthetist_id, scrub_nurse_id, 
                     pre_op_diagnosis, status, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $_POST['patient_id'],
                    $_POST['procedure_name'],
                    $_POST['procedure_category'] ?? '',
                    $_POST['scheduled_date'],
                    $_POST['scheduled_time'],
                    $surgeon_id,
                    !empty($_POST['assistant']) ? $_POST['assistant'] : NULL,
                    !empty($_POST['anesthetist']) ? $_POST['anesthetist'] : NULL,
                    !empty($_POST['scrub_nurse']) ? $_POST['scrub_nurse'] : NULL,
                    $_POST['pre_op_diagnosis'] ?? '',
                    'Scheduled',
                    $_SESSION['user_id']
                ]);
                
                $message = 'Procedure scheduled successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Update procedure (intra-operative)
        if (isset($_POST['update_procedure'])) {
            $procedure_id = $_POST['procedure_id'];
            $status = $_POST['status'] ?? 'in_progress';
            
            try {
                $stmt = $db->prepare("UPDATE theatre_procedures SET 
                    anesthesia_type = ?, start_time = ?, end_time = ?,
                    blood_loss_ml = ?, fluids_given = ?, specimens_collected = ?, complications = ?,
                    procedure_details = ?, status = ?, updated_at = NOW()
                    WHERE id = ?");
                
                $stmt->execute([
                    $_POST['anesthesia_type'],
                    $_POST['start_time'] ?? null,
                    $_POST['end_time'] ?? null,
                    $_POST['blood_loss'] ?? 0,
                    $_POST['fluids_given'] ?? '',
                    $_POST['specimens'] ?? '',
                    $_POST['complications'] ?? '',
                    $_POST['intraop_notes'] ?? '',
                    $status,
                    $procedure_id
                ]);
                
                // Log the update
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Theatre Update', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "Updated procedure ID: $procedure_id, Status: $status"]);
                } catch (Exception $e) {}
                
                $message = 'Procedure updated successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get procedures
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

$where_clause = "1=1";
$params = [];

if ($status_filter) {
    $where_clause .= " AND status = :status";
    $params['status'] = $status_filter;
}

$where_clause .= " AND DATE(procedure_date) = :date";
$params['date'] = $date_filter;

$procedures = [];
try {
    $db = Database::getInstance();
    $sql = "SELECT tp.*, p.first_name, p.last_name, p.patient_id, p.allergies,
            u_surgeon.full_name as surgeon_name, u_anesth.full_name as anesthetist_name, u_nurse.full_name as nurse_name
            FROM theatre_procedures tp
            JOIN patients p ON tp.patient_id = p.id
            LEFT JOIN users u_surgeon ON tp.surgeon_id = u_surgeon.id
            LEFT JOIN users u_anesth ON tp.anesthetist_id = u_anesth.id
            LEFT JOIN users u_nurse ON tp.scrub_nurse_id = u_nurse.id
            WHERE $where_clause
            ORDER BY tp.procedure_date ASC, tp.start_time ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get patients for selection
$patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = TRUE ORDER BY first_name LIMIT 30");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get users for roles
$surgeons = [];
$anesthetists = [];
$nurses = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, username, full_name FROM users WHERE role IN ('doctor', 'admin', 'theatre_officer') AND is_active = 1 ORDER BY full_name");
    $surgeons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT id, username, full_name FROM users WHERE role IN ('doctor', 'admin', 'theatre_officer') AND is_active = 1 ORDER BY full_name");
    $anesthetists = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $db->query("SELECT id, username, full_name FROM users WHERE role IN ('nurse', 'theatre_officer') AND is_active = 1 ORDER BY full_name");
    $nurses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
                    <i class="fas fa-procedures text-indigo-600 mr-2"></i> Theatre Procedures
                </h1>
                <p class="text-gray-500 mt-1">Schedule and manage surgical procedures</p>
            </div>
            <button onclick="showScheduleModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-plus mr-2"></i> Schedule Procedure
            </button>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert-auto-hide rounded-lg p-4 mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 border border-brand-200' : 'bg-red-50 border border-red-200'; ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3"></i>
            <p class="<?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-filter text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">Filters:</span>
            </div>
            
            <input type="date" value="<?php echo $date_filter; ?>" onchange="window.location.href = '?date=' + this.value"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            
            <select onchange="window.location.href = '?status=' + this.value + '&date=<?php echo $date_filter; ?>'" 
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Status</option>
                <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            
            <?php if ($status_filter || $date_filter): ?>
            <a href="procedures" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Procedures List -->
    <div class="space-y-4">
        <?php if (empty($procedures)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-procedures text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Procedures</h3>
            <p class="text-gray-500">No procedures scheduled for this date</p>
        </div>
        <?php else: ?>
            <?php foreach ($procedures as $proc): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Procedure Header -->
                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 <?php echo $proc['status'] === 'in_progress' ? 'bg-yellow-50' : ''; ?>">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold">
                                <?php echo strtoupper(substr($proc['first_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($proc['first_name'] . ' ' . $proc['last_name']); ?>
                                </h3>
                                <?php if ($proc['allergies']): ?>
                                <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Allergies
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($proc['patient_id']); ?> • 
                                <?php echo date('g:i A', strtotime($proc['start_time'])); ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php 
                    $status_config = [
                        'scheduled' => ['bg' => 'bg-brand-100', 'text' => 'text-blue-700', 'label' => 'Scheduled'],
                        'in_progress' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'label' => 'In Progress'],
                        'completed' => ['bg' => 'bg-brand-100', 'text' => 'text-green-700', 'label' => 'Completed'],
                        'cancelled' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Cancelled'],
                    ];
                    $status_style = $status_config[$proc['status']] ?? $status_config['scheduled'];
                    ?>
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_style['bg'] . ' ' . $status_style['text']; ?>">
                        <?php echo $status_style['label']; ?>
                    </span>
                </div>
                
                <!-- Procedure Details -->
                <div class="px-4 pb-4 border-t border-gray-100">
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Procedure</p>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($proc['procedure_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Type</p>
                            <p class="text-gray-800"><?php echo htmlspecialchars($proc['procedure_category']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Surgeon</p>
                            <p class="text-gray-800"><?php echo htmlspecialchars($proc['surgeon_name'] ?? 'TBD'); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Anesthetist</p>
                            <p class="text-gray-800"><?php echo $proc['anesthetist_name'] ?: 'TBD'; ?></p>
                        </div>
                    </div>
                    
                    <?php if ($proc['status'] !== 'completed'): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-2">
                        <button onclick="showProcedureForm(<?php echo $proc['id']; ?>)" 
                                class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm hover:bg-indigo-700">
                            <i class="fas fa-edit mr-1"></i> Update Procedure
                        </button>
                        
                        <?php if ($proc['status'] === 'scheduled'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="procedure_id" value="<?php echo $proc['id']; ?>">
                            <input type="hidden" name="status" value="in_progress">
                            <input type="hidden" name="update_procedure" value="1">
                            <input type="hidden" name="anesthesia_type" value="local">
                            <button type="submit" class="px-3 py-1.5 bg-yellow-600 text-white rounded-lg text-sm hover:bg-yellow-700">
                                <i class="fas fa-play mr-1"></i> Start Procedure
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <?php if ($proc['status'] === 'in_progress'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="procedure_id" value="<?php echo $proc['id']; ?>">
                            <input type="hidden" name="status" value="completed">
                            <input type="hidden" name="update_procedure" value="1">
                            <button type="submit" class="px-3 py-1.5 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700">
                                <i class="fas fa-check mr-1"></i> Complete
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Schedule Procedure Modal -->
<div id="scheduleModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-lg w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Schedule Procedure</h3>
                <button type="button" onclick="closeScheduleModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="schedule_procedure" value="1">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Patient <span class="text-red-500">*</span></label>
                <select name="patient_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="">-- Select Patient --</option>
                    <?php foreach ($patients as $p): ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_id'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Procedure Name <span class="text-red-500">*</span></label>
                    <input type="text" name="procedure_name" required placeholder="e.g., Appendectomy"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Procedure Category</label>
                    <select name="procedure_category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="Major Surgery">Major Surgery</option>
                        <option value="Minor Surgery">Minor Surgery</option>
                        <option value="Endoscopy">Endoscopy</option>
                        <option value="Obstetric">Obstetric</option>
                        <option value="Gynecological">Gynecological</option>
                        <option value="Orthopedic">Orthopedic</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="scheduled_date" required value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Time <span class="text-red-500">*</span></label>
                    <input type="time" name="scheduled_time" required value="08:00"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Surgeon <span class="text-red-500">*</span></label>
                    <select name="surgeon" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">Select Surgeon</option>
                        <?php foreach ($surgeons as $surgeon): ?>
                        <option value="<?php echo $surgeon['id']; ?>"><?php echo htmlspecialchars($surgeon['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assistant</label>
                    <select name="assistant" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">Select Assistant</option>
                        <?php foreach ($surgeons as $surgeon): ?>
                        <option value="<?php echo $surgeon['id']; ?>"><?php echo htmlspecialchars($surgeon['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anesthetist</label>
                    <select name="anesthetist" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">Select Anesthetist</option>
                        <?php foreach ($anesthetists as $anesth): ?>
                        <option value="<?php echo $anesth['id']; ?>"><?php echo htmlspecialchars($anesth['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Scrub Nurse</label>
                    <select name="scrub_nurse" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">Select Nurse</option>
                        <?php foreach ($nurses as $nurse): ?>
                        <option value="<?php echo $nurse['id']; ?>"><?php echo htmlspecialchars($nurse['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- WHO Safety Checklist -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h4 class="font-medium text-yellow-800 mb-3">
                    <i class="fas fa-shield-alt mr-2"></i> Pre-Operative Checklist
                </h4>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="consent" required class="rounded text-yellow-600">
                        <span class="text-sm text-gray-700">Consent Verified</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="npo" required class="rounded text-yellow-600">
                        <span class="text-sm text-gray-700">NPO (Nil Per Os)</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="allergy_check" required class="rounded text-yellow-600">
                        <span class="text-sm text-gray-700">Allergy Checked</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="vitals_recorded" required class="rounded text-yellow-600">
                        <span class="text-sm text-gray-700">Baseline Vitals Recorded</span>
                    </label>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Procedure Update Modal -->
<div id="procedureModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-2xl w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Intra-Operative Record</h3>
                <button type="button" onclick="closeProcedureModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="update_procedure" value="1">
            <input type="hidden" name="procedure_id" id="procedureId" value="">
            <input type="hidden" name="status" value="in_progress">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anesthesia Type</label>
                    <select name="anesthesia_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="general">General</option>
                        <option value="regional">Regional (Spinal/Epidural)</option>
                        <option value="local">Local</option>
                        <option value="sedation">Sedation</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anesthesia Agents</label>
                    <input type="text" name="anesthesia_agents" placeholder="e.g., Propofol, Fentanyl"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                    <input type="time" name="start_time"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                    <input type="time" name="end_time"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Blood Loss (ml)</label>
                    <input type="number" name="blood_loss" placeholder="0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Fluids Given (ml)</label>
                    <input type="number" name="fluids_given" placeholder="e.g., 2000"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specimens</label>
                    <input type="text" name="specimens" placeholder="Any specimens collected"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Intra-Operative Notes</label>
                <textarea name="intraop_notes" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                          placeholder="Surgical findings, technique used..."></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Complications</label>
                <textarea name="complications" rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                          placeholder="Any intra-operative complications..."></textarea>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Equipment Used</label>
                <textarea name="equipment_used" rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                          placeholder="List of equipment used..."></textarea>
            </div>
            
            <!-- WHO Safety Timeout -->
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h4 class="font-medium text-indigo-800 mb-2">
                    <i class="fas fa-clock mr-2"></i> Time Out Verification
                </h4>
                <p class="text-sm text-indigo-700">Confirm patient identity, procedure, and site before incision</p>
            </div>
            
            <!-- Count Verification -->
            <div class="bg-green-50 border border-brand-200 rounded-lg p-4">
                <h4 class="font-medium text-green-800 mb-2">
                    <i class="fas fa-check-double mr-2"></i> Count Verification
                </h4>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" required class="rounded text-brand-600">
                        <span class="text-sm text-gray-700">Instruments count verified</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="checkbox" required class="rounded text-brand-600">
                        <span class="text-sm text-gray-700">Swabs/Needles count verified</span>
                    </label>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeProcedureModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Save Record
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showScheduleModal() {
        document.getElementById('scheduleModal').classList.remove('hidden');
    }
    
    function closeScheduleModal() {
        document.getElementById('scheduleModal').classList.add('hidden');
    }
    
    function showProcedureForm(procedureId) {
        document.getElementById('procedureId').value = procedureId;
        document.getElementById('procedureModal').classList.remove('hidden');
    }
    
    function closeProcedureModal() {
        document.getElementById('procedureModal').classList.add('hidden');
    }
</script>

<?php
require_once '../includes/footer.php';
?>
