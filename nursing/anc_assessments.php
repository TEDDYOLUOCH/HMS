<?php
/**
 * Hospital Management System - ANC Assessments
 * Antenatal clinical assessments and tests
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';
requireRole(['admin', 'nurse'], '../dashboard');

$page_title = 'ANC Assessments';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

$csrf_token = csrfToken();
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'danger';
    } else {
        $db = Database::getInstance();
        
        // Save assessment
        if (isset($_POST['save_assessment'])) {
            try {
                $stmt = $db->prepare("INSERT INTO anc_assessments 
                    (patient_id, visit_id, blood_pressure, weight, urine_test, hemoglobin, 
                     scan_results, ve_results, fetal_heartbeat, gestation_weeks, 
                     risk_notes, notes, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $_POST['patient_id'],
                    $_POST['visit_id'] ?? null,
                    $_POST['blood_pressure'] ?? null,
                    $_POST['weight'] ?? null,
                    $_POST['urine_test'] ?? null,
                    $_POST['hemoglobin'] ?? null,
                    $_POST['scan_results'] ?? null,
                    $_POST['ve_results'] ?? null,
                    $_POST['fetal_heartbeat'] ?? null,
                    $_POST['gestation_weeks'] ?? null,
                    $_POST['risk_notes'] ?? null,
                    $_POST['notes'] ?? null,
                    $_SESSION['user_id']
                ]);
                
                $assessment_id = $db->lastInsertId();
                logActivity('Added', 'ANC', 'anc_assessments', $assessment_id, "Recorded ANC assessment for patient");
                
                $message = 'Assessment recorded successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        // Update assessment
        if (isset($_POST['update_assessment'])) {
            try {
                $stmt = $db->prepare("UPDATE anc_assessments SET 
                    blood_pressure = ?, weight = ?, urine_test = ?, hemoglobin = ?,
                    scan_results = ?, ve_results = ?, fetal_heartbeat = ?, 
                    gestation_weeks = ?, risk_notes = ?, notes = ?
                    WHERE assessment_id = ?");
                
                $stmt->execute([
                    $_POST['blood_pressure'] ?? null,
                    $_POST['weight'] ?? null,
                    $_POST['urine_test'] ?? null,
                    $_POST['hemoglobin'] ?? null,
                    $_POST['scan_results'] ?? null,
                    $_POST['ve_results'] ?? null,
                    $_POST['fetal_heartbeat'] ?? null,
                    $_POST['gestation_weeks'] ?? null,
                    $_POST['risk_notes'] ?? null,
                    $_POST['notes'] ?? null,
                    $_POST['assessment_id']
                ]);
                
                logActivity('Updated', 'ANC', 'anc_assessments', $_POST['assessment_id'], "Updated ANC assessment");
                
                $message = 'Assessment updated successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
        
        // Delete assessment
        if (isset($_POST['delete_assessment'])) {
            try {
                $stmt = $db->prepare("DELETE FROM anc_assessments WHERE assessment_id = ?");
                $stmt->execute([$_POST['assessment_id']]);
                
                logActivity('Deleted', 'ANC', 'anc_assessments', $_POST['assessment_id'], "Deleted ANC assessment");
                
                $message = 'Assessment deleted successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'danger';
            }
        }
    }
}

// Get patients with ANC profiles
$anc_patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT ap.*, p.first_name, p.last_name, p.patient_id as pat_id, p.gender, p.dob 
                        FROM anc_profiles ap 
                        JOIN patients p ON ap.patient_id = p.id 
                        ORDER BY p.first_name, p.last_name");
    $anc_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get selected patient
$patient_id = $_GET['patient_id'] ?? null;
$patient = null;
$anc_profile = null;
$assessments = [];

if ($patient_id) {
    try {
        $db = Database::getInstance();
        
        // Get patient details
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get ANC profile
        $stmt = $db->prepare("SELECT * FROM anc_profiles WHERE patient_id = ?");
        $stmt->execute([$patient_id]);
        $anc_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get assessments
        $stmt = $db->prepare("SELECT a.*, u.username as recorded_by_name 
                              FROM anc_assessments a 
                              LEFT JOIN users u ON a.recorded_by = u.id 
                              WHERE a.patient_id = ? 
                              ORDER BY a.date_recorded DESC");
        $stmt->execute([$patient_id]);
        $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get ANC visits for dropdown
        $stmt = $db->prepare("SELECT * FROM anc_visits WHERE patient_id = ? ORDER BY visit_date DESC");
        $stmt->execute([$patient_id]);
        $anc_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {}
}

// Get single assessment for editing
$edit_assessment = null;
if (isset($_GET['edit_id'])) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM anc_assessments WHERE assessment_id = ?");
        $stmt->execute([$_GET['edit_id']]);
        $edit_assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

function getGestationalAge($lmp) {
    if (!$lmp) return '-';
    $weeks = floor((strtotime(date('Y-m-d')) - strtotime($lmp)) / (60 * 60 * 24 * 7));
    $days = floor(((strtotime(date('Y-m-d')) - strtotime($lmp)) % (60 * 60 * 24 * 7)) / (60 * 60 * 24));
    return $weeks . ' weeks ' . $days . ' days';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SIWOT Hospital</title>
    <?php include '../includes/header.php'; ?>
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-enter">
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-clipboard-check text-pink-600 mr-2"></i> ANC Assessments
                        </h1>
                        <p class="text-gray-500 mt-1">Record clinical assessments for antenatal patients</p>
                    </div>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if (!$patient_id): ?>
            <!-- Select Patient -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h3 class="font-semibold text-gray-800 mb-4">Select ANC Patient</h3>
                
                <?php if (empty($anc_patients)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                    <p>No ANC patients found. Please register ANC profiles first.</p>
                    <a href="anc_visits" class="inline-block mt-3 px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        Go to ANC Visits
                    </a>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($anc_patients as $p): ?>
                    <a href="?patient_id=<?php echo $p['patient_id']; ?>" 
                       class="block p-4 border border-gray-200 rounded-xl hover:border-pink-300 hover:bg-pink-50 transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-gradient-to-br from-pink-400 to-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                                <?php echo strtoupper(substr($p['first_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($p['pat_id']); ?></p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100 flex justify-between text-sm">
                            <span class="text-gray-500">G<?php echo $p['gravida']; ?>P<?php echo $p['para']; ?>A<?php echo $p['abortus']; ?></span>
                            <span class="px-2 py-0.5 rounded text-xs <?php echo $p['risk_level'] === 'high' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                                <?php echo ucfirst($p['risk_level']); ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            
            <!-- Patient Banner -->
            <div class="bg-gradient-to-r from-pink-50 to-purple-50 rounded-xl p-4 border border-pink-100 mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-pink-400 to-purple-500 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold">
                                <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-800">
                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                            </h2>
                            <p class="text-sm text-gray-600">
                                <?php echo htmlspecialchars($patient['patient_id']); ?> | 
                                <?php echo $patient['gender']; ?> | 
                                Age: <?php echo date('Y') - date('Y', strtotime($patient['dob'])); ?>
                            </p>
                        </div>
                    </div>
                    <a href="?patient_id=0" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                        <i class="fas fa-times mr-1"></i> Change Patient
                    </a>
                </div>
            </div>
            
            <?php if ($anc_profile): ?>
            <!-- ANC Profile Summary -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap gap-6">
                        <div>
                            <span class="text-xs text-gray-500">Gestational Age</span>
                            <p class="font-semibold text-pink-600"><?php echo getGestationalAge($anc_profile['lmp']); ?></p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">EDD</span>
                            <p class="font-semibold text-purple-600"><?php echo date('M j, Y', strtotime($anc_profile['edd'])); ?></p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">GPA</span>
                            <p class="font-semibold text-gray-800">G<?php echo $anc_profile['gravida']; ?>P<?php echo $anc_profile['para']; ?>A<?php echo $anc_profile['abortus']; ?></p>
                        </div>
                        <div>
                            <span class="text-xs text-gray-500">Risk Level</span>
                            <span class="px-2 py-1 rounded text-xs font-medium <?php echo $anc_profile['risk_level'] === 'high' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                                <?php echo ucfirst($anc_profile['risk_level']); ?>
                            </span>
                        </div>
                    </div>
                    <button onclick="openAssessmentModal()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-plus mr-2"></i> New Assessment
                    </button>
                </div>
            </div>
            
            <!-- Assessment Form / Edit Mode -->
            <?php if ($edit_assessment || isset($_GET['new'])): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-clipboard-list text-gray-400 mr-2"></i>
                    <?php echo $edit_assessment ? 'Edit Assessment' : 'New Assessment'; ?>
                </h3>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <?php if ($edit_assessment): ?>
                    <input type="hidden" name="update_assessment" value="1">
                    <input type="hidden" name="assessment_id" value="<?php echo $edit_assessment['assessment_id']; ?>">
                    <?php else: ?>
                    <input type="hidden" name="save_assessment" value="1">
                    <?php endif; ?>
                    <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                    
                    <!-- Vital Signs -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Vital Signs</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Blood Pressure (mmHg)</label>
                                <input type="text" name="blood_pressure" placeholder="e.g., 120/80" 
                                       value="<?php echo $edit_assessment['blood_pressure'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                                <input type="number" name="weight" step="0.1" placeholder="e.g., 65.5" 
                                       value="<?php echo $edit_assessment['weight'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Gestational Weeks</label>
                                <input type="number" name="gestation_weeks" placeholder="e.g., 24" 
                                       value="<?php echo $edit_assessment['gestation_weeks'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lab Tests -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Laboratory Tests</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Urine Test</label>
                                <select name="urine_test" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                                    <option value="">Select result...</option>
                                    <option value="Normal" <?php echo ($edit_assessment['urine_test'] ?? '') === 'Normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="Protein+" <?php echo ($edit_assessment['urine_test'] ?? '') === 'Protein+' ? 'selected' : ''; ?>>Protein +</option>
                                    <option value="Protein++" <?php echo ($edit_assessment['urine_test'] ?? '') === 'Protein++' ? 'selected' : ''; ?>>Protein ++</option>
                                    <option value="Protein+++" <?php echo ($edit_assessment['urine_test'] ?? '') === 'Protein+++' ? 'selected' : ''; ?>>Protein +++</option>
                                    <option value="Glucose+" <?php echo ($edit_assessment['urine_test'] ?? '') === 'Glucose+' ? 'selected' : ''; ?>>Glucose +</option>
                                    <option value="Ketones+" <?php echo ($edit_assessment['urine_test'] ?? '') === 'Ketones+' ? 'selected' : ''; ?>>Ketones +</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Hemoglobin (g/dL)</label>
                                <input type="number" name="hemoglobin" step="0.1" placeholder="e.g., 12.5" 
                                       value="<?php echo $edit_assessment['hemoglobin'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Clinical Examinations -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Clinical Examinations</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ultrasound Scan Results</label>
                                <textarea name="scan_results" rows="3" placeholder="Scan findings..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500"><?php echo $edit_assessment['scan_results'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Vaginal Examination (V.E)</label>
                                <textarea name="ve_results" rows="3" placeholder="VE findings..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500"><?php echo $edit_assessment['ve_results'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fetal Assessment -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Fetal Assessment</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fetal Heartbeat</label>
                                <input type="text" name="fetal_heartbeat" placeholder="e.g., 140 bpm, Regular" 
                                       value="<?php echo $edit_assessment['fetal_heartbeat'] ?? ''; ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes & Risk -->
                    <div>
                        <h4 class="text-sm font-semibold text-gray-700 mb-3 pb-2 border-b">Notes & Risk Assessment</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Risk Notes</label>
                                <textarea name="risk_notes" rows="3" placeholder="Any risk factors..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500"><?php echo $edit_assessment['risk_notes'] ?? ''; ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">General Notes</label>
                                <textarea name="notes" rows="3" placeholder="Additional notes..."
                                          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-pink-500"><?php echo $edit_assessment['notes'] ?? ''; ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3">
                        <a href="anc_assessments.php?patient_id=<?php echo $patient_id; ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            <i class="fas fa-save mr-2"></i> Save Assessment
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Assessments History -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Assessment History</h3>
                </div>
                
                <?php if (empty($assessments)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
                    <p>No assessments recorded yet.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">BP</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Weight</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Urine</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Hb</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">FHR</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gest.</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($assessments as $a): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm"><?php echo date('M j, Y', strtotime($a['date_recorded'])); ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['blood_pressure'] ?? '-'; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['weight'] ? $a['weight'] . ' kg' : '-'; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['urine_test'] ?? '-'; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['hemoglobin'] ? $a['hemoglobin'] . ' g/dL' : '-'; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['fetal_heartbeat'] ?? '-'; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['gestation_weeks'] ? $a['gestation_weeks'] . ' wks' : '-'; ?></td>
                                <td class="px-4 py-3 text-sm"><?php echo $a['recorded_by_name'] ?? '-'; ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="?patient_id=<?php echo $patient_id; ?>&edit_id=<?php echo $a['assessment_id']; ?>" 
                                           class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $a['assessment_id']; ?>)" 
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                <p class="text-gray-500">This patient does not have an ANC profile.</p>
                <a href="anc_visits.php?patient_id=<?php echo $patient_id; ?>" class="inline-block mt-3 px-4 py-2 bg-pink-600 text-white rounded-lg hover:bg-pink-700">
                    Register ANC Profile
                </a>
            </div>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeDeleteModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
                <div class="p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">Delete Assessment?</h3>
                    <p class="text-gray-500">This action cannot be undone.</p>
                    
                    <form method="POST" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="delete_assessment" value="1">
                        <input type="hidden" name="assessment_id" id="deleteAssessmentId">
                        
                        <div class="flex justify-end gap-3">
                            <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                Delete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function openAssessmentModal() {
        window.location.href = '?patient_id=<?php echo $patient_id; ?>&new=1';
    }
    
    function confirmDelete(id) {
        document.getElementById('deleteAssessmentId').value = id;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
