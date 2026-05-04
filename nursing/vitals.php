<?php
/**
 * Hospital Management System - Nursing Vitals
 * Vital signs recording and trend tracking
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'nurse'], '../dashboard');

// Set page title
$page_title = 'Vital Signs';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_vitals'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $patient_id = $_POST['patient_id'] ?? 0;
            $visit_id = $_POST['visit_id'] ?? 0;
            $temperature = $_POST['temperature'] ?? null;
            $blood_pressure = $_POST['blood_pressure'] ?? '';
            $pulse = $_POST['pulse'] ?? null;
            $respiratory_rate = $_POST['respiratory_rate'] ?? null;
            $spo2 = $_POST['spo2'] ?? null;
            $weight = $_POST['weight'] ?? null;
            $height = $_POST['height'] ?? null;
            $pain_score = $_POST['pain_score'] ?? null;
            $consciousness = $_POST['consciousness'] ?? 'A';
            $notes = $_POST['notes'] ?? '';
            
            // Calculate BMI
            $bmi = null;
            if ($weight && $height) {
                $height_m = $height / 100;
                $bmi = round($weight / ($height_m * $height_m), 1);
            }
            
            // Check for abnormal values
            $is_critical = false;
            if ($blood_pressure) {
                $bp_parts = explode('/', $blood_pressure);
                if (count($bp_parts) == 2) {
                    $systolic = intval($bp_parts[0]);
                    $diastolic = intval($bp_parts[1]);
                    if ($systolic > 180 || $diastolic > 120 || $systolic < 90 || $diastolic < 60) {
                        $is_critical = true;
                    }
                }
            }
            
            if ($temperature && ($temperature < 35 || $temperature > 38.5)) {
                $is_critical = true;
            }
            
            if ($pulse && ($pulse > 120 || $pulse < 50)) {
                $is_critical = true;
            }
            
            $stmt = $db->prepare("INSERT INTO vital_signs 
                (patient_id, visit_id, temperature, blood_pressure, pulse, respiratory_rate, spo2, weight, height, bmi, pain_score, consciousness, notes, is_critical, recorded_by, recorded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->execute([
                $patient_id, $visit_id, $temperature, $blood_pressure, $pulse, $respiratory_rate, $spo2,
                $weight, $height, $bmi, $pain_score, $consciousness, $notes, $is_critical ? 1 : 0,
                $_SESSION['user_id']
            ]);
            
            // Update visit status to In Progress after vitals are recorded
            if ($visit_id) {
                $stmt = $db->prepare("UPDATE visits SET status = 'In Progress', department = 'OPD' WHERE id = ? AND status = 'Waiting'");
                $stmt->execute([$visit_id]);
            }
            
            // Log critical values
            if ($is_critical) {
                try {
                    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Critical Vitals', ?, NOW())");
                    $stmt->execute([$_SESSION['user_id'], "Critical vitals recorded for patient ID: $patient_id"]);
                } catch (Exception $e) {}
            }
            
            $message = 'Vitals recorded successfully!';
            $message_type = 'success';
            
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get patient if selected
$patient_id = $_GET['patient_id'] ?? 0;
$visit_id = $_GET['visit_id'] ?? 0;
$patient = null;
$vitals_history = [];

if ($patient_id) {
    try {
        $db = Database::getInstance();
        
        // Get patient details
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get vitals history
        $stmt = $db->prepare("SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY recorded_at DESC LIMIT 10");
        $stmt->execute([$patient_id]);
        $vitals_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {}
}

// Get patients for search with pagination
$patients = [];
$total_patients = 0;
$per_page = 10;
$patient_page = isset($_GET['patient_page']) ? (int)$_GET['patient_page'] : 1;
$patient_offset = ($patient_page - 1) * $per_page;

try {
    $db = Database::getInstance();
    
    // Get total count
    $count_stmt = $db->query("SELECT COUNT(*) as total FROM patients WHERE is_active = TRUE");
    $total_patients = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $db->prepare("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = TRUE ORDER BY first_name LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $patient_offset, PDO::PARAM_INT);
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <i class="fas fa-heartbeat text-red-600 mr-2"></i> Vital Signs Recording
                </h1>
                <p class="text-gray-500 mt-1">Record and track patient vital signs</p>
            </div>
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
    <!-- Patient Banner -->
    <div class="bg-gradient-to-r from-red-50 to-pink-50 rounded-xl p-4 border border-red-100 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-bold">
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
            <a href="?patient_id=0" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                <i class="fas fa-times mr-1"></i> Clear Patient
            </a>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Vitals Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-stethoscope text-gray-400 mr-2"></i> Record Vitals
            </h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="record_vitals" value="1">
                <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
                <input type="hidden" name="visit_id" value="<?php echo $visit_id; ?>">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" placeholder="36.5"
                               class="vital-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               data-normal="36.1-37.2" data-low="<35" data-high=">38.5">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Blood Pressure (mmHg)</label>
                        <input type="text" name="blood_pressure" placeholder="120/80"
                               class="vital-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               data-normal="90/60-120/80" data-low="<90/60" data-high=">140/90">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pulse (bpm)</label>
                        <input type="number" name="pulse" placeholder="72"
                               class="vital-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               data-normal="60-100" data-low="<50" data-high=">120">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Respiratory Rate (/min)</label>
                        <input type="number" name="respiratory_rate" placeholder="16"
                               class="vital-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               data-normal="12-20" data-low="<10" data-high=">24">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SpO2 (%)</label>
                        <input type="number" name="spo2" placeholder="98"
                               class="vital-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               data-normal="95-100" data-low="<90">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pain Score (0-10)</label>
                        <input type="number" name="pain_score" min="0" max="10" placeholder="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weight (kg)</label>
                        <input type="number" step="0.1" name="weight" id="weight" placeholder="70"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               oninput="calculateBMI()">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Height (cm)</label>
                        <input type="number" name="height" id="height" placeholder="170"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               oninput="calculateBMI()">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">BMI (auto-calculated)</label>
                        <input type="text" id="bmi_display" readonly
                               class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg">
                        <input type="hidden" name="bmi" id="bmi_value">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Consciousness (AVPU)</label>
                        <select name="consciousness" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="A">A - Alert</option>
                            <option value="V">V - Voice</option>
                            <option value="P">P - Pain</option>
                            <option value="U">U - Unresponsive</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                              placeholder="Additional observations..."></textarea>
                </div>
                
                <button type="submit" class="w-full px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    <i class="fas fa-save mr-2"></i> Record Vitals
                </button>
            </form>
        </div>
        
        <!-- Vitals History -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-history text-gray-400 mr-2"></i> Vitals History (Last 10)
                </h3>
            </div>
            
            <?php if (empty($vitals_history)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-heartbeat text-4xl mb-3 text-gray-300"></i>
                <p>No vitals recorded yet</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                <?php foreach ($vitals_history as $vital): ?>
                <div class="p-4 hover:bg-gray-50 <?php echo $vital['is_critical'] ? 'bg-red-50' : ''; ?>">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-800">
                            <?php echo date('M j, g:i A', strtotime($vital['recorded_at'])); ?>
                        </span>
                        <?php if ($vital['is_critical']): ?>
                        <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">
                            <i class="fas fa-exclamation-triangle mr-1"></i> Critical
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="grid grid-cols-4 gap-2 text-sm">
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">Temp</p>
                            <p class="font-medium <?php echo ($vital['temperature'] < 35 || $vital['temperature'] > 38.5) ? 'text-red-600' : 'text-gray-800'; ?>">
                                <?php echo $vital['temperature'] ? $vital['temperature'] . '°C' : '-'; ?>
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">BP</p>
                            <p class="font-medium text-gray-800"><?php echo $vital['blood_pressure'] ?: '-'; ?></p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">Pulse</p>
                            <p class="font-medium <?php echo ($vital['pulse'] < 50 || $vital['pulse'] > 120) ? 'text-red-600' : 'text-gray-800'; ?>">
                                <?php echo $vital['pulse'] ? $vital['pulse'] . ' bpm' : '-'; ?>
                            </p>
                        </div>
                        <div class="text-center">
                            <p class="text-gray-500 text-xs">SpO2</p>
                            <p class="font-medium <?php echo ($vital['spo2'] < 90) ? 'text-red-600' : 'text-gray-800'; ?>">
                                <?php echo $vital['spo2'] ? $vital['spo2'] . '%' : '-'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($vital['notes']): ?>
                    <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($vital['notes']); ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php else: ?>
    <!-- Patient Selection -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">Select Patient</h3>
        
        <form method="GET" class="flex gap-4">
            <select name="patient_id" onchange="this.form.submit()" 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <option value="">-- Select Patient --</option>
                <?php foreach ($patients as $p): ?>
                <option value="<?php echo $p['id']; ?>" <?php echo $patient_id == $p['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_id'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
    function calculateBMI() {
        const weight = parseFloat(document.getElementById('weight').value);
        const height = parseFloat(document.getElementById('height').value);
        
        if (weight && height) {
            const height_m = height / 100;
            const bmi = (weight / (height_m * height_m)).toFixed(1);
            document.getElementById('bmi_display').value = bmi;
            document.getElementById('bmi_value').value = bmi;
        } else {
            document.getElementById('bmi_display').value = '';
            document.getElementById('bmi_value').value = '';
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>
