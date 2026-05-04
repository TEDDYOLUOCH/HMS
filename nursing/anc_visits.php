<?php
/**
 * Hospital Management System - ANC Visits
 * Antenatal care profile and visit tracking
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'nurse'], '../dashboard');

// Set page title
$page_title = 'ANC Visits';

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
        
        // Register new ANC profile
        if (isset($_POST['register_anc'])) {
            try {
                $lmp = $_POST['lmp'] ?? date('Y-m-d');
                $edd = date('Y-m-d', strtotime($lmp . ' + 280 days'));
                
                $stmt = $db->prepare("INSERT INTO anc_profiles 
                    (patient_id, gravida, para, abortus, lmp, edd, risk_level)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $_POST['patient_id'],
                    $_POST['gravida'] ?? 1,
                    $_POST['para'] ?? 0,
                    $_POST['abortus'] ?? 0,
                    $lmp,
                    $edd,
                    $_POST['risk_level'] ?? 'low'
                ]);
                
                $message = 'ANC profile registered successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Record ANC visit
        if (isset($_POST['record_visit'])) {
            try {
                // Get patient_id from anc_profile
                $stmt = $db->prepare("SELECT patient_id FROM anc_profiles WHERE id = ?");
                $stmt->execute([$_POST['anc_profile_id']]);
                $anc_profile = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $db->prepare("INSERT INTO anc_visits 
                    (patient_id, anc_profile_id, visit_date, gestational_weeks, fundal_height, fetal_heart_rate, presenting_part, lie, position, 
                     urine_protein, urine_glucose, tt_immunization, ipt_dose, iron_folate, danger_signs, notes, recorded_by)
                    VALUES (?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $anc_profile['patient_id'],
                    $_POST['anc_profile_id'],
                    $_POST['gestational_weeks'],
                    $_POST['fundal_height'],
                    $_POST['fetal_heart_rate'],
                    $_POST['presenting_part'],
                    $_POST['lie'],
                    $_POST['position'],
                    $_POST['urine_protein'],
                    $_POST['urine_glucose'],
                    $_POST['tt_immunization'],
                    $_POST['ipt_dose'],
                    $_POST['iron_folate'],
                    $_POST['danger_signs'],
                    $_POST['notes'],
                    $_SESSION['user_id']
                ]);
                
                // Update risk level if needed
                if (isset($_POST['risk_level']) && $_POST['risk_level'] === 'high') {
                    $stmt = $db->prepare("UPDATE anc_profiles SET risk_level = 'high' WHERE id = ?");
                    $stmt->execute([$_POST['anc_profile_id']]);
                }
                
                $message = 'ANC visit recorded successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get selected patient
$patient_id = $_GET['patient_id'] ?? 0;
$patient = null;
$anc_profile = null;
$anc_visits = [];

if ($patient_id) {
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get ANC profile
        $stmt = $db->prepare("SELECT * FROM anc_profiles WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$patient_id]);
        $anc_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get ANC visits
        if ($anc_profile) {
            $stmt = $db->prepare("SELECT * FROM anc_visits WHERE anc_profile_id = ? ORDER BY visit_date DESC");
            $stmt->execute([$anc_profile['id']]);
            $anc_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {}
}

// Get patients for selection
$patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, patient_id, first_name, last_name, gender FROM patients WHERE is_active = TRUE ORDER BY first_name LIMIT 30");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Generate CSRF token
$csrf_token = csrfToken();

// Calculate gestational age
function getGestationalAge($lmp) {
    $lmp_date = new DateTime($lmp);
    $today = new DateTime();
    $diff = $today->diff($lmp_date);
    $weeks = floor($diff->days / 7);
    $days = $diff->days % 7;
    return "$weeks weeks $days days";
}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-baby text-pink-600 mr-2"></i> Antenatal Care
                </h1>
                <p class="text-gray-500 mt-1">Manage ANC profiles and visits</p>
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
    
    <!-- Patient Selection -->
    <?php if (!$patient): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="font-semibold text-gray-800 mb-4">Select Patient</h3>
        <form method="GET" class="flex gap-4">
            <select name="patient_id" onchange="this.form.submit()" 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <option value="">-- Select Female Patient --</option>
                <?php foreach ($patients as $p): ?>
                <option value="<?php echo $p['id']; ?>">
                    <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_id'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
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
                        <?php echo htmlspecialchars($patient['patient_id']); ?>
                    </p>
                </div>
            </div>
            <a href="?patient_id=0" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                <i class="fas fa-times mr-1"></i> Change Patient
            </a>
        </div>
    </div>
    
    <?php if (!$anc_profile): ?>
    <!-- Register New ANC -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 class="font-semibold text-gray-800 mb-4">
            <i class="fas fa-plus-circle text-gray-400 mr-2"></i> Register New ANC Profile
        </h3>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="register_anc" value="1">
            <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gravida (G)</label>
                    <input type="number" name="gravida" value="1" min="1" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Para (P)</label>
                    <input type="number" name="para" value="0" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Abortus (A)</label>
                    <input type="number" name="abortus" value="0" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Risk Level</label>
                    <select name="risk_level" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="low">Low Risk</option>
                        <option value="high">High Risk</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Last Menstrual Period (LMP) <span class="text-red-500">*</span></label>
                    <input type="date" name="lmp" required max="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                           onchange="calculateEDD(this.value)">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expected Delivery Date (EDD)</label>
                    <input type="text" id="edd_display" readonly
                           class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg">
                </div>
            </div>
            
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-save mr-2"></i> Register ANC Profile
            </button>
        </form>
    </div>
    
    <?php else: ?>
    <!-- ANC Profile & Visits -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- ANC Profile -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-gray-800">ANC Profile</h3>
                    <span class="px-2 py-1 rounded text-xs font-medium <?php echo $anc_profile['risk_level'] === 'high' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                        <?php echo ucfirst($anc_profile['risk_level']); ?> Risk
                    </span>
                </div>
                
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">G</span>
                        <span class="font-medium text-gray-800"><?php echo $anc_profile['gravida']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">P</span>
                        <span class="font-medium text-gray-800"><?php echo $anc_profile['para']; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">A</span>
                        <span class="font-medium text-gray-800"><?php echo $anc_profile['abortus']; ?></span>
                    </div>
                    <div class="border-t pt-2 mt-2">
                        <p class="text-gray-500 text-xs">LMP</p>
                        <p class="font-medium text-gray-800"><?php echo date('M j, Y', strtotime($anc_profile['lmp'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs">EDD</p>
                        <p class="font-medium text-purple-700"><?php echo date('M j, Y', strtotime($anc_profile['edd'])); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-500 text-xs">Gestational Age</p>
                        <p class="font-medium text-pink-700"><?php echo getGestationalAge($anc_profile['lmp']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- New Visit Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-calendar-plus text-gray-400 mr-2"></i> Record ANC Visit
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="record_visit" value="1">
                    <input type="hidden" name="anc_profile_id" value="<?php echo $anc_profile['id']; ?>">
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Gestational Wks</label>
                            <input type="number" name="gestational_weeks" required
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Fundal Height (cm)</label>
                            <input type="number" name="fundal_height"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">FHR (/min)</label>
                            <input type="number" name="fetal_heart_rate"
                                   class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Presenting Part</label>
                            <select name="presenting_part" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="">Select</option>
                                <option value="cephalic">Cephalic</option>
                                <option value="breech">Breech</option>
                                <option value="transverse">Transverse</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Lie</label>
                            <select name="lie" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="">Select</option>
                                <option value="longitudinal">Longitudinal</option>
                                <option value="transverse">Transverse</option>
                                <option value="oblique">Oblique</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Position</label>
                            <select name="position" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="">Select</option>
                                <option value="LOA">LOA</option>
                                <option value="ROA">ROA</option>
                                <option value="LOP">LOP</option>
                                <option value="ROP">ROP</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Urine Protein</label>
                            <select name="urine_protein" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="negative">Negative</option>
                                <option value="trace">Trace</option>
                                <option value="1+">1+</option>
                                <option value="2+">2+</option>
                                <option value="3+">3+</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Urine Glucose</label>
                            <select name="urine_glucose" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="negative">Negative</option>
                                <option value="trace">Trace</option>
                                <option value="1+">1+</option>
                                <option value="2+">2+</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">TT</label>
                            <select name="tt_immunization" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="none">None</option>
                                <option value="TT1">TT1</option>
                                <option value="TT2">TT2</option>
                                <option value="TT3">TT3</option>
                                <option value="TT4">TT4</option>
                                <option value="TT5">TT5</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">IPT</label>
                            <select name="ipt_dose" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="none">None</option>
                                <option value="IPT1">IPT1</option>
                                <option value="IPT2">IPT2</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Iron/Folate</label>
                            <select name="iron_folate" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                                <option value="given">Given</option>
                                <option value="not given">Not Given</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Danger Signs</label>
                        <textarea name="danger_signs" rows="2" placeholder="Any danger signs..."
                                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></textarea>
                    </div>
                    
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="risk_level" value="high" class="rounded text-red-600">
                        <span class="text-sm text-red-600">Mark as High Risk</span>
                    </label>
                    
                    <button type="submit" class="w-full px-3 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700">
                        <i class="fas fa-save mr-2"></i> Record Visit
                    </button>
                </form>
            </div>
        </div>
        
        <!-- ANC Visits History -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">
                        <i class="fas fa-history text-gray-400 mr-2"></i> ANC Visit History
                    </h3>
                </div>
                
                <?php if (empty($anc_visits)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-calendar text-4xl mb-3 text-gray-300"></i>
                    <p>No visits recorded yet</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($anc_visits as $visit): ?>
                    <div class="p-4 hover:bg-gray-50">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-800">
                                Visit <?php echo count($anc_visits) - array_search($visit, $anc_visits) + 1; ?>
                            </span>
                            <span class="text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($visit['visit_date'])); ?>
                            </span>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                            <div>
                                <p class="text-gray-500 text-xs">Gestational Age</p>
                                <p class="font-medium"><?php echo $visit['gestational_weeks']; ?> weeks</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">Fundal Height</p>
                                <p class="font-medium"><?php echo $visit['fundal_height'] ?: '-'; ?> cm</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">FHR</p>
                                <p class="font-medium"><?php echo $visit['fetal_heart_rate'] ?: '-'; ?> bpm</p>
                            </div>
                            <div>
                                <p class="text-gray-500 text-xs">TT</p>
                                <p class="font-medium"><?php echo $visit['tt_immunization'] ?: '-'; ?></p>
                            </div>
                        </div>
                        
                        <?php if ($visit['danger_signs']): ?>
                        <div class="mt-2 p-2 bg-red-50 rounded text-xs text-red-700">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            <?php echo htmlspecialchars($visit['danger_signs']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function calculateEDD(lmp) {
        if (lmp) {
            const date = new Date(lmp);
            date.setDate(date.getDate() + 280);
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            document.getElementById('edd_display').value = date.toLocaleDateString('en-US', options);
        }
    }
</script>

<?php
require_once '../includes/footer.php';
?>
