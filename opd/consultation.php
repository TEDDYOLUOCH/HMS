<?php
/**
 * Hospital Management System - OPD Consultation
 * Patient queue and consultation workflow
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'OPD Consultation';
$page_description = 'Record and manage outpatient consultations and diagnoses.';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_consultation'])) {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $patient_id = $_POST['patient_id'] ?? 0;
            $visit_id = $_POST['visit_id'] ?? 0;
            $chief_complaint = trim($_POST['chief_complaint'] ?? '');
            $present_illness = trim($_POST['present_illness'] ?? '');
            $physical_exam = json_encode($_POST['physical_exam'] ?? []);
            $examination_notes = trim($_POST['examination_notes'] ?? '');
            $diagnosis = trim($_POST['diagnosis'] ?? '');
            $differential = trim($_POST['differential'] ?? '');
            $treatment_plan = trim($_POST['treatment_plan'] ?? '');
            $status = $_POST['status'] ?? 'completed';
            
            if (empty($patient_id) || empty($chief_complaint)) {
                $message = 'Patient and chief complaint are required';
                $message_type = 'error';
            } else {
                $consultation_id = $_POST['consultation_id'] ?? 0;
                
                if ($consultation_id) {
                    // Update existing consultation
                    $stmt = $db->prepare("UPDATE consultations SET 
                        chief_complaint = ?, history_of_illness = ?, examination_notes = ?,
                        diagnosis_primary = ?, treatment_plan = ?, notes = ?, follow_up_date = ?,
                        updated_at = NOW()
                        WHERE id = ?");
                    
                    $stmt->execute([
                        $chief_complaint, $present_illness, $examination_notes,
                        $diagnosis, $treatment_plan, $_POST['notes'] ?? '', $_POST['follow_up_date'] ?? null,
                        $consultation_id
                    ]);
                } else {
                    // Create new consultation - use visit_id if available
                    $stmt = $db->prepare("INSERT INTO consultations 
                        (visit_id, patient_id, doctor_id, chief_complaint, history_of_illness, examination_notes,
                        diagnosis_primary, treatment_plan, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    
                    $stmt->execute([
                        $visit_id ?: null, $patient_id, $_SESSION['user_id'], $chief_complaint, $present_illness, $examination_notes,
                        $diagnosis, $treatment_plan, $_POST['notes'] ?? ''
                    ]);
                    
                    $consultation_id = $db->lastInsertId();
                }
                
                // Update visit status - move to Pharmacy if treatment plan has prescription, or complete
                if ($visit_id && $diagnosis) {
                    $new_dept = 'Pharmacy';
                    $stmt = $db->prepare("UPDATE visits SET status = 'In Progress', department = ? WHERE id = ?");
                    $stmt->execute([$new_dept, $visit_id]);
                }
                
                // Log activity
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Consultation', ?, NOW())");
                    $patient_name = $_POST['patient_name'] ?? 'Patient';
                    $stmt->execute([$_SESSION['user_id'], "Consultation for $patient_name - Diagnosis: $diagnosis"]);
                } catch (Exception $e) {}
                
                $message = 'Consultation saved successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error saving consultation: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get current patient if selected
$selected_patient = null;
$patient_vitals = null;
$consultation = null;
$patient_id = $_GET['patient_id'] ?? 0;
$visit_id = $_GET['visit_id'] ?? 0;

if ($patient_id) {
    try {
        $db = Database::getInstance();
        
        // Get patient details
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $selected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get latest vitals
        try {
            $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 1");
            $stmt->execute([$patient_id]);
            $patient_vitals = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
        
        // Get existing consultation
        if ($visit_id) {
            $stmt = $db->prepare("SELECT * FROM consultations WHERE visit_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$visit_id]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($patient_id) {
            // Fallback: get latest consultation for patient today
            $stmt = $db->prepare("SELECT * FROM consultations WHERE patient_id = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$patient_id]);
            $consultation = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {}
}

// Get waiting patients from visits (distinct patients - latest status)
$waiting_patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT v.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth, p.blood_group, p.allergies
                        FROM visits v
                        JOIN patients p ON v.patient_id = p.id
                        INNER JOIN (
                            SELECT patient_id, MAX(visit_time) as latest_visit
                            FROM visits 
                            WHERE status IN ('Waiting', 'In Progress') AND department = 'OPD'
                            AND DATE(visit_date) = CURDATE()
                            GROUP BY patient_id
                        ) latest ON v.patient_id = latest.patient_id AND v.visit_time = latest.latest_visit
                        WHERE v.department = 'OPD' AND DATE(v.visit_date) = CURDATE()
                        ORDER BY v.visit_time ASC");
    $waiting_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Generate CSRF token
$csrf_token = csrfToken();
?>

<style>
.opd-consult-page { animation: opdFadeIn 0.3s ease-out; }
@keyframes opdFadeIn { from { opacity: 0; } to { opacity: 1; } }
.opd-queue-item { transition: background-color 0.15s ease; }
.opd-queue-item:hover { background-color: rgba(241, 245, 249, 0.9); }
.opd-queue-item.active { background-color: rgba(219, 234, 254, 0.6); }
.opd-input:focus { outline: none; box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.3); }
</style>

<div class="opd-consult-page max-w-7xl mx-auto">
    <!-- Page Header -->
    <header class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-xl bg-teal-50 flex items-center justify-center">
                    <i class="fas fa-stethoscope text-teal-600"></i>
                </span>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">OPD Consultation</h1>
                    <p class="text-gray-500 text-sm mt-0.5">Patient queue and consultation workflow</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-50 border border-amber-200/80 text-amber-800 text-sm font-medium">
                    <i class="fas fa-user-clock"></i>
                    <span><?php echo count($waiting_patients); ?> in queue</span>
                </span>
            </div>
        </div>
    </header>

    <?php if ($message): ?>
    <div class="alert-auto-hide rounded-xl p-4 mb-6 flex items-center gap-3 <?php echo $message_type === 'success' ? 'bg-emerald-50 border border-emerald-200' : 'bg-rose-50 border border-rose-200'; ?>">
        <span class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 <?php echo $message_type === 'success' ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600'; ?>">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
        </span>
        <p class="<?php echo $message_type === 'success' ? 'text-emerald-800' : 'text-rose-800'; ?> font-medium"><?php echo htmlspecialchars($message); ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <!-- Left: Find Patient + Queue -->
        <div class="lg:col-span-1 space-y-4">
            <!-- Find Patient -->
            <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-search text-gray-500 text-sm"></i>
                        </span>
                        <h2 class="font-semibold text-gray-900">Find Patient</h2>
                    </div>
                </div>
                <form method="GET" class="p-4">
                    <input type="text" name="patient_id" placeholder="ID or name..." 
                           class="opd-input w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm mb-3 focus:border-primary-500"
                           list="patientSuggestions">
                    <datalist id="patientSuggestions">
                        <?php 
                        try {
                            $db = Database::getInstance();
                            $stmt = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = TRUE ORDER BY first_name LIMIT 20");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . (int)$row['id'] . '">' . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . ' (' . htmlspecialchars($row['patient_id']) . ')</option>';
                            }
                        } catch (Exception $e) {}
                        ?>
                    </datalist>
                    <button type="submit" class="w-full px-4 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition-colors">
                        Select Patient
                    </button>
                </form>
            </section>

            <!-- Waiting Queue -->
            <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden flex flex-col" style="min-height: 280px;">
                <div class="p-4 border-b border-gray-100 bg-amber-50/60">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">
                            <i class="fas fa-list-ol text-amber-600 text-sm"></i>
                        </span>
                        <h2 class="font-semibold text-gray-900">Waiting Queue</h2>
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto divide-y divide-gray-100 max-h-80">
                    <?php if (empty($waiting_patients)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <span class="w-12 h-12 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-user-clock text-gray-400"></i>
                        </span>
                        <p class="text-sm">No patients waiting</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($waiting_patients as $p): ?>
                    <a href="?patient_id=<?php echo (int)$p['patient_id']; ?>" 
                       class="opd-queue-item block p-3 <?php echo (int)$patient_id === (int)$p['patient_id'] ? 'active' : ''; ?>">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-400 to-primary-500 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                <?php echo strtoupper(substr($p['first_name'] ?? 'U', 0, 1)); ?>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-800 truncate text-sm"><?php echo htmlspecialchars(($p['first_name'] ?? '') . ' ' . ($p['last_name'] ?? '')); ?></p>
                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($p['patient_id'] ?? ''); ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-medium flex-shrink-0 <?php 
                                $status = strtolower($p['status'] ?? '');
                                $status_class = '';
                                $status_text = 'Waiting';
                                if ($status === 'in_progress' || $status === 'in progress') {
                                    $status_class = 'bg-blue-100 text-blue-700';
                                    $status_text = 'In Progress';
                                } elseif ($status === 'waiting') {
                                    $status_class = 'bg-amber-100 text-amber-700';
                                    $status_text = 'Waiting';
                                } elseif ($status === 'completed') {
                                    $status_class = 'bg-green-100 text-green-700';
                                    $status_text = 'Completed';
                                } elseif ($status === 'cancelled') {
                                    $status_class = 'bg-gray-100 text-gray-700';
                                    $status_text = 'Cancelled';
                                }
                                echo $status_class;
                            ?>">
                                <?php echo $status_text; ?>
                            </span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        
        <!-- Right: Consultation Form -->
        <div class="lg:col-span-3 space-y-6">
            <?php if ($selected_patient): ?>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="save_consultation" value="1">
                <input type="hidden" name="patient_id" value="<?php echo (int)$selected_patient['id']; ?>">
                <input type="hidden" name="patient_name" value="<?php echo htmlspecialchars($selected_patient['first_name'] . ' ' . $selected_patient['last_name']); ?>">
                <input type="hidden" name="visit_id" value="<?php echo (int)$visit_id; ?>">
                <input type="hidden" name="consultation_id" value="<?php echo (int)($consultation['id'] ?? 0); ?>">

                <!-- Patient Banner -->
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm overflow-hidden">
                    <div class="p-5 bg-gradient-to-r from-primary-50/80 to-teal-50/80 border-b border-gray-100">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <span class="w-14 h-14 rounded-2xl bg-gradient-to-br from-teal-500 to-primary-600 flex items-center justify-center text-white text-xl font-bold shadow-sm">
                                    <?php echo strtoupper(substr($selected_patient['first_name'] ?? 'U', 0, 1)); ?>
                                </span>
                                <div>
                                    <h2 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars(trim(($selected_patient['first_name'] ?? '') . ' ' . ($selected_patient['last_name'] ?? '')) ?: 'Patient'); ?></h2>
                                    <p class="text-sm text-gray-600 mt-0.5">
                                        <?php echo htmlspecialchars($selected_patient['patient_id'] ?? ''); ?> · 
                                        <?php echo isset($selected_patient['date_of_birth']) && $selected_patient['date_of_birth'] ? floor((time() - strtotime($selected_patient['date_of_birth'])) / (365.25 * 86400)) . ' yrs' : '—'; ?> · 
                                        <?php echo ucfirst($selected_patient['gender'] ?? '—'); ?> · 
                                        Blood <?php echo htmlspecialchars($selected_patient['blood_group'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                            </div>
                            <?php if (!empty($selected_patient['allergies'])): ?>
                            <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-rose-100 border border-rose-200/80">
                                <i class="fas fa-exclamation-triangle text-rose-600"></i>
                                <span class="text-sm font-medium text-rose-800">Allergies: <?php echo htmlspecialchars($selected_patient['allergies']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($patient_vitals): ?>
                    <div class="p-4 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Latest vitals · <?php echo date('g:i A', strtotime($patient_vitals['recorded_at'])); ?></p>
                        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                            <div class="bg-gray-50 rounded-xl py-2.5 px-3 text-center"><p class="text-[10px] text-gray-500 uppercase">BP</p><p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($patient_vitals['blood_pressure'] ?? '—'); ?></p></div>
                            <div class="bg-gray-50 rounded-xl py-2.5 px-3 text-center"><p class="text-[10px] text-gray-500 uppercase">Pulse</p><p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($patient_vitals['pulse'] ?? '—'); ?></p></div>
                            <div class="bg-gray-50 rounded-xl py-2.5 px-3 text-center"><p class="text-[10px] text-gray-500 uppercase">Temp</p><p class="font-semibold text-gray-800 text-sm"><?php echo $patient_vitals['temperature'] ? $patient_vitals['temperature'] . '°C' : '—'; ?></p></div>
                            <div class="bg-gray-50 rounded-xl py-2.5 px-3 text-center"><p class="text-[10px] text-gray-500 uppercase">Resp</p><p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($patient_vitals['respiratory_rate'] ?? '—'); ?></p></div>
                            <div class="bg-gray-50 rounded-xl py-2.5 px-3 text-center"><p class="text-[10px] text-gray-500 uppercase">Weight</p><p class="font-semibold text-gray-800 text-sm"><?php echo $patient_vitals['weight'] ? $patient_vitals['weight'] . ' kg' : '—'; ?></p></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>

                <!-- Chief Complaint -->
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-9 h-9 rounded-xl bg-primary-50 flex items-center justify-center"><i class="fas fa-comment-medical text-primary-600 text-sm"></i></span>
                        <h3 class="font-semibold text-gray-900">Chief Complaint</h3>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <select id="complaintTemplate" onchange="fillComplaint()" class="opd-input w-full px-3 py-2 border border-gray-200 rounded-xl text-sm mb-2 focus:border-primary-500">
                                <option value="">— Quick select complaint —</option>
                                <option value="Fever">Fever</option>
                                <option value="Cough">Cough</option>
                                <option value="Headache">Headache</option>
                                <option value="Abdominal pain">Abdominal pain</option>
                                <option value="Chest pain">Chest pain</option>
                                <option value="General weakness">General weakness</option>
                                <option value="Dizziness">Dizziness</option>
                                <option value="Nausea/Vomiting">Nausea/Vomiting</option>
                                <option value="Diarrhea">Diarrhea</option>
                                <option value="Joint pains">Joint pains</option>
                            </select>
                            <textarea name="chief_complaint" rows="2" required class="opd-input w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-primary-500" placeholder="Main complaint..."><?php echo htmlspecialchars($consultation['chief_complaint'] ?? ''); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">History of Present Illness</label>
                            <textarea name="present_illness" rows="3" class="opd-input w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-primary-500" placeholder="Describe illness history..."><?php echo htmlspecialchars($consultation['present_illness'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </section>

                <!-- Physical Examination -->
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-9 h-9 rounded-xl bg-teal-50 flex items-center justify-center"><i class="fas fa-user-md text-teal-600 text-sm"></i></span>
                        <h3 class="font-semibold text-gray-900">Physical Examination</h3>
                    </div>
                    <?php $physical_exam = json_decode($consultation['physical_exam'] ?? '{}', true); ?>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
                        <?php 
                        $exam_items = ['general' => 'General', 'cardiovascular' => 'Cardiovascular', 'respiratory' => 'Respiratory', 'abdomen' => 'Abdomen', 'neurological' => 'Neurological', 'musculoskeletal' => 'Musculoskeletal', 'skin' => 'Skin', 'ent' => 'ENT'];
                        foreach ($exam_items as $key => $label): ?>
                        <label class="flex items-center gap-2 py-2.5 px-3 border border-gray-200 rounded-xl hover:bg-gray-50 cursor-pointer transition-colors">
                            <input type="checkbox" name="physical_exam[<?php echo $key; ?>]" <?php echo !empty($physical_exam[$key]) ? 'checked' : ''; ?> class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="text-sm text-gray-700"><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Examination Notes</label>
                        <textarea name="examination_notes" rows="3" class="opd-input w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-primary-500" placeholder="Detailed findings..."><?php echo htmlspecialchars($consultation['examination_notes'] ?? ''); ?></textarea>
                    </div>
                </section>

                <!-- Diagnosis & Treatment -->
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-9 h-9 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fas fa-diagnoses text-violet-600 text-sm"></i></span>
                        <h3 class="font-semibold text-gray-900">Diagnosis & Treatment</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Primary Diagnosis</label>
                            <input type="text" name="diagnosis" class="opd-input w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-primary-500" placeholder="Main diagnosis..." value="<?php echo htmlspecialchars($consultation['diagnosis'] ?? ''); ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Differential</label>
                            <input type="text" name="differential" class="opd-input w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-primary-500" placeholder="Other possibilities..." value="<?php echo htmlspecialchars($consultation['differential'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Treatment Plan</label>
                        <textarea name="treatment_plan" rows="3" class="opd-input w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:border-primary-500" placeholder="Recommended treatment..."><?php echo htmlspecialchars($consultation['treatment_plan'] ?? ''); ?></textarea>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center"><i class="fas fa-bolt text-amber-600 text-sm"></i></span>
                        <h3 class="font-semibold text-gray-900">Quick Actions</h3>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a href="print_consultation.php?patient_id=<?php echo (int)$patient_id; ?>&consultation_id=<?php echo (int)($consultation['id'] ?? 0); ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-gray-50 text-gray-700 hover:bg-gray-100 transition-colors"><i class="fas fa-print"></i> Print Summary</a>
                        <a href="../laboratory/lab_requests.php?patient_id=<?php echo (int)$patient_id; ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-violet-50 text-violet-700 hover:bg-violet-100 transition-colors"><i class="fas fa-vial"></i> Request Lab</a>
                        <a href="prescriptions.php?patient_id=<?php echo (int)$patient_id; ?>&consultation_id=<?php echo (int)($consultation['id'] ?? 0); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-amber-50 text-amber-700 hover:bg-amber-100 transition-colors"><i class="fas fa-prescription"></i> Prescribe</a>
                        <a href="refer.php?patient_id=<?php echo (int)$patient_id; ?>&consultation_id=<?php echo (int)($consultation['id'] ?? 0); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"><i class="fas fa-share"></i> Refer</a>
                        <a href="admit.php?patient_id=<?php echo (int)$patient_id; ?>&consultation_id=<?php echo (int)($consultation['id'] ?? 0); ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium bg-rose-50 text-rose-700 hover:bg-rose-100 transition-colors"><i class="fas fa-procedures"></i> Admit</a>
                    </div>
                </section>

                <!-- Status & Submit -->
                <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" value="in_consultation" <?php echo ($consultation['status'] ?? 'waiting') === 'in_consultation' ? 'checked' : ''; ?> class="w-4 h-4 text-primary-600 focus:ring-primary-500">
                                <span class="text-sm font-medium text-gray-700">In Consultation</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" name="status" value="completed" <?php echo ($consultation['status'] ?? '') === 'completed' ? 'checked' : ''; ?> class="w-4 h-4 text-emerald-600 focus:ring-emerald-500">
                                <span class="text-sm font-medium text-gray-700">Completed</span>
                            </label>
                        </div>
                        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-primary-600 text-white rounded-xl font-medium hover:bg-primary-700 transition-colors shadow-sm">
                            <i class="fas fa-save"></i> Save Consultation
                        </button>
                    </div>
                </section>
            </form>

            <?php else: ?>
            <section class="bg-white rounded-2xl border border-gray-200/80 shadow-sm p-12 text-center">
                <span class="w-16 h-16 rounded-2xl bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-plus text-gray-400 text-2xl"></i>
                </span>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Select a patient</h3>
                <p class="text-gray-500 text-sm max-w-sm mx-auto">Choose from the waiting queue or search by ID or name to start a consultation.</p>
            </section>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    function fillComplaint() {
        const template = document.getElementById('complaintTemplate').value;
        const textarea = document.querySelector('textarea[name="chief_complaint"]');
        if (template && !textarea.value) {
            textarea.value = template;
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>
