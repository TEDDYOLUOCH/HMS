<?php
/**
 * Hospital Management System - Patient Queue
 * Tracks patient workflow through all departments
 * Shows current status and allows department transfers
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'nurse', 'lab_technologist', 'lab_scientist', 'pharmacist'], '../dashboard');

// Set page title
$page_title = 'Patient Queue';
$page_description = 'Track patient flow through departments';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle actions
$message = '';
$message_type = '';

// Update patient status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();
        
        $visit_id = $_POST['visit_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        $new_department = $_POST['new_department'] ?? '';
        
        if ($visit_id && ($new_status || $new_department)) {
            try {
                $updates = [];
                $params = [];
                
                if ($new_status) {
                    $updates[] = 'status = ?';
                    $params[] = $new_status;
                    
                    // If completed, set closed_at
                    if ($new_status === 'Completed') {
                        $updates[] = 'closed_at = NOW()';
                    }
                }
                
                if ($new_department) {
                    $updates[] = 'department = ?';
                    $params[] = $new_department;
                }
                
                $params[] = $visit_id;
                
                $sql = "UPDATE visits SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                // Log activity
                $status_text = $new_status ? "Status: $new_status" : "";
                $dept_text = $new_department ? "Department: $new_department" : "";
                logActivity('Updated', 'Visits', 'visits', $visit_id, "Patient queue updated - $status_text $dept_text");
                
                $message = 'Patient status updated successfully!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating status: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Close/complete visit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_visit'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();
        
        $visit_id = $_POST['visit_id'] ?? 0;
        
        if ($visit_id) {
            try {
                $stmt = $db->prepare("UPDATE visits SET status = 'Completed', closed_at = NOW() WHERE id = ?");
                $stmt->execute([$visit_id]);
                
                logActivity('Completed', 'Visits', 'visits', $visit_id, "Visit completed - Patient left facility");
                
                $message = 'Visit completed - Patient has left the facility!';
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = 'Error completing visit: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get filter
$dept_filter = $_GET['department'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get all active visits with patient details
$db = Database::getInstance();

$sql = "SELECT v.*, p.patient_id, p.first_name, p.last_name, p.phone_primary, p.gender,
        u.full_name as assigned_to_name,
        (SELECT COUNT(*) FROM vital_signs WHERE visit_id = v.id) as has_vitals,
        (SELECT COUNT(*) FROM consultations WHERE visit_id = v.id) as has_consultation,
        (SELECT COUNT(*) FROM lab_requests WHERE visit_id = v.id AND status != 'Cancelled') as has_lab_requests,
        (SELECT COUNT(*) FROM prescriptions WHERE visit_id = v.id AND status != 'Cancelled') as has_prescriptions,
        (SELECT COUNT(*) FROM dispensing WHERE prescription_id IN (SELECT id FROM prescriptions WHERE visit_id = v.id)) as has_dispensing
        FROM visits v
        JOIN patients p ON v.patient_id = p.id
        LEFT JOIN users u ON v.assigned_to = u.id
        WHERE v.status != 'Completed' AND v.status != 'Cancelled'";

$params = [];

if ($dept_filter) {
    $sql .= " AND v.department = ?";
    $params[] = $dept_filter;
}

if ($status_filter) {
    $sql .= " AND v.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY v.visit_date DESC, v.visit_time DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get department counts
$stmt = $db->query("
    SELECT department, status, COUNT(*) as count 
    FROM visits 
    WHERE status != 'Completed' AND status != 'Cancelled'
    GROUP BY department, status
");
$dept_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by department
$dept_counts = [];
foreach ($dept_stats as $stat) {
    if (!isset($dept_counts[$stat['department']])) {
        $dept_counts[$stat['department']] = 0;
    }
    $dept_counts[$stat['department']] += $stat['count'];
}
?>

<div class="content-wrapper">
    <div class="container mx-auto px-4 py-6">
        <!-- Page Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Patient Queue</h1>
                <p class="text-gray-600"><?php echo $page_description; ?></p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="../patients/view_patients.php#add" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <i class="fas fa-user-plus mr-2"></i> Register New Patient
                </a>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-red-100 text-red-700 border border-red-300'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Department Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
            <div class="flex flex-wrap gap-2">
                <a href="patient_queue" class="px-4 py-2 rounded-lg <?php echo !$dept_filter ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    All Departments
                </a>
                <a href="patient_queue.php?department=OPD" class="px-4 py-2 rounded-lg <?php echo $dept_filter === 'OPD' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    OPD (<?php echo $dept_counts['OPD'] ?? 0; ?>)
                </a>
                <a href="patient_queue.php?department=MCH" class="px-4 py-2 rounded-lg <?php echo $dept_filter === 'MCH' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    MCH (<?php echo $dept_counts['MCH'] ?? 0; ?>)
                </a>
                <a href="patient_queue.php?department=Laboratory" class="px-4 py-2 rounded-lg <?php echo $dept_filter === 'Laboratory' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Laboratory (<?php echo $dept_counts['Laboratory'] ?? 0; ?>)
                </a>
                <a href="patient_queue.php?department=Pharmacy" class="px-4 py-2 rounded-lg <?php echo $dept_filter === 'Pharmacy' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                    Pharmacy (<?php echo $dept_counts['Pharmacy'] ?? 0; ?>)
                </a>
            </div>
        </div>

        <!-- Patient Queue Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">ID</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Department</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Progress</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Visit Time</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if (empty($visits)): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500">
                                <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                                <p>No patients in queue</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($visits as $visit): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 font-semibold mr-3">
                                        <?php echo strtoupper(substr($visit['first_name'], 0, 1) . substr($visit['last_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($visit['phone_primary']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-sm font-mono text-gray-600"><?php echo htmlspecialchars($visit['patient_id']); ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <?php 
                                $dept_colors = [
                                    'OPD' => 'bg-blue-100 text-blue-700',
                                    'MCH' => 'bg-pink-100 text-pink-700',
                                    'Laboratory' => 'bg-purple-100 text-purple-700',
                                    'Pharmacy' => 'bg-green-100 text-green-700',
                                    'Nursing' => 'bg-red-100 text-red-700'
                                ];
                                $dept_class = $dept_colors[$visit['department']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $dept_class; ?>">
                                    <?php echo htmlspecialchars($visit['department']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <?php 
                                $status_colors = [
                                    'Waiting' => 'bg-yellow-100 text-yellow-700',
                                    'In Progress' => 'bg-blue-100 text-blue-700',
                                    'Completed' => 'bg-green-100 text-green-700'
                                ];
                                $status_class = $status_colors[$visit['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($visit['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1">
                                    <?php if ($visit['has_vitals'] > 0): ?>
                                    <span class="w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs" title="Vitals Recorded">
                                        <i class="fas fa-heartbeat"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($visit['has_consultation'] > 0): ?>
                                    <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs" title="Doctor Consulted">
                                        <i class="fas fa-stethoscope"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($visit['has_lab_requests'] > 0): ?>
                                    <span class="w-6 h-6 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center text-xs" title="Lab Tests Done">
                                        <i class="fas fa-flask"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($visit['has_prescriptions'] > 0): ?>
                                    <span class="w-6 h-6 rounded-full bg-orange-100 text-orange-600 flex items-center justify-center text-xs" title="Prescription">
                                        <i class="fas fa-prescription"></i>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ($visit['has_dispensing'] > 0): ?>
                                    <span class="w-6 h-6 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-xs" title="Medication Dispensed">
                                        <i class="fas fa-pills"></i>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo date('M d, H:i', strtotime($visit['visit_date'] . ' ' . $visit['visit_time'])); ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <!-- Quick Actions based on department -->
                                    <?php if ($visit['department'] === 'OPD' && $visit['has_vitals'] == 0): ?>
                                    <a href="../nursing/vitals.php?patient_id=<?php echo $visit['patient_id']; ?>&visit_id=<?php echo $visit['id']; ?>" class="px-2 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200" title="Record Vitals">
                                        <i class="fas fa-heartbeat"></i> Vitals
                                    </a>
                                    <?php elseif ($visit['department'] === 'OPD' && $visit['has_consultation'] == 0): ?>
                                    <a href="consultation.php?patient_id=<?php echo $visit['patient_id']; ?>&visit_id=<?php echo $visit['id']; ?>" class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200" title="Doctor Consultation">
                                        <i class="fas fa-stethoscope"></i> Consult
                                    </a>
                                    <?php elseif ($visit['department'] === 'OPD' && $visit['has_prescriptions'] > 0 && $visit['has_dispensing'] == 0): ?>
                                    <a href="../pharmacy/dispense_drug.php?patient_id=<?php echo $visit['patient_id']; ?>&visit_id=<?php echo $visit['id']; ?>" class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200" title="Dispense Medication">
                                        <i class="fas fa-pills"></i> Pharmacy
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- MCH Actions -->
                                    <?php if ($visit['department'] === 'MCH'): ?>
                                    <a href="../nursing/anc_visits.php?patient_id=<?php echo $visit['patient_id']; ?>" class="px-2 py-1 text-xs bg-pink-100 text-pink-700 rounded hover:bg-pink-200" title="ANC/Postnatal">
                                        <i class="fas fa-baby"></i> MCH
                                    </a>
                                    <?php endif; ?>
                                    
                                    <!-- Complete Visit Button -->
                                    <?php if ($visit['has_dispensing'] > 0 || ($visit['department'] === 'MCH')): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Complete visit? Patient will be marked as left facility.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="complete_visit" value="1">
                                        <input type="hidden" name="visit_id" value="<?php echo $visit['id']; ?>">
                                        <button type="submit" class="px-2 py-1 text-xs bg-gray-600 text-white rounded hover:bg-gray-700" title="Complete Visit">
                                            <i class="fas fa-check"></i> Done
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
