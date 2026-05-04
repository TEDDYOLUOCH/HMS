<?php
/**
 * Hospital Management System - Postnatal Care
 * Postnatal visits and newborn assessment
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'nurse'], '../dashboard');

// Set page title
$page_title = 'Postnatal Care';

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
        
        // Register delivery
        if (isset($_POST['register_delivery'])) {
            try {
                $stmt = $db->prepare("INSERT INTO deliveries 
                    (patient_id, delivery_date, delivery_time, mode, place, attendant, birth_weight, gender, apgar_score, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $_POST['patient_id'],
                    $_POST['delivery_date'],
                    $_POST['delivery_time'] ?? date('H:i'),
                    $_POST['mode'],
                    $_POST['place'],
                    $_POST['attendant'],
                    $_POST['birth_weight'],
                    $_POST['gender'],
                    $_POST['apgar_score'] ?? '8,10'
                ]);
                
                $message = 'Delivery registered successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Record postnatal visit
        if (isset($_POST['record_visit'])) {
            try {
                $delivery_id = $_POST['delivery_id'];
                
                // Determine visit type
                $days_since = isset($_POST['visit_day']) ? (int)$_POST['visit_day'] : 0;
                
                $stmt = $db->prepare("INSERT INTO postnatal_visits 
                    (delivery_id, visit_date, visit_type, mother_bp, mother_temp, mother_pulse, mother_breast, mother_lochia, 
                     baby_weight, baby_temp, baby_feeding, baby_umbilical, danger_signs_mother, danger_signs_baby, 
                     family_planning, notes, recorded_by, created_at)
                    VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $delivery_id,
                    $days_since . '_day',
                    $_POST['mother_bp'],
                    $_POST['mother_temp'],
                    $_POST['mother_pulse'],
                    $_POST['mother_breast'],
                    $_POST['mother_lochia'],
                    $_POST['baby_weight'],
                    $_POST['baby_temp'],
                    $_POST['baby_feeding'],
                    $_POST['baby_umbilical'],
                    $_POST['danger_signs_mother'],
                    $_POST['danger_signs_baby'],
                    $_POST['family_planning'],
                    $_POST['notes'],
                    $_SESSION['user_id']
                ]);
                
                $message = 'Postnatal visit recorded successfully!';
                $message_type = 'success';
                
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get deliveries
$deliveries = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT d.*, p.first_name, p.last_name, p.patient_id 
                       FROM deliveries d
                       JOIN patients p ON d.patient_id = p.id
                       ORDER BY d.delivery_date DESC LIMIT 20");
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get postnatal visits
$postnatal_visits = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT pv.*, d.delivery_date, p.first_name, p.last_name, p.patient_id 
                       FROM postnatal_visits pv
                       LEFT JOIN deliveries d ON pv.delivery_id = d.id
                       LEFT JOIN patients p ON d.patient_id = p.id
                       ORDER BY pv.visit_date DESC LIMIT 50");
    $postnatal_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get patients
$patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, patient_id, first_name, last_name FROM patients WHERE is_active = TRUE ORDER BY first_name LIMIT 30");
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
                    <i class="fas fa-baby-carriage text-purple-600 mr-2"></i> Postnatal Care
                </h1>
                <p class="text-gray-500 mt-1">Manage deliveries and postnatal visits</p>
            </div>
            <button onclick="showRegisterModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-plus mr-2"></i> Register Delivery
            </button>
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
    
    <!-- Deliveries List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-list text-gray-400 mr-2"></i> Recent Deliveries
            </h3>
        </div>
        
        <?php if (empty($deliveries)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-baby text-4xl mb-3 text-gray-300"></i>
            <p>No deliveries registered yet</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Mother</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Mode</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Baby</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Attendant</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($deliveries as $delivery): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-pink-400 to-purple-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-medium">
                                        <?php echo strtoupper(substr($delivery['first_name'], 0, 1)); ?>
                                    </span>
                                </div>
                                <span class="font-medium text-gray-800">
                                    <?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?>
                                </span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?php echo date('M j, Y', strtotime($delivery['delivery_date'])); ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <span class="px-2 py-1 rounded text-xs font-medium 
                                <?php echo $delivery['mode'] === 'normal' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'; ?>">
                                <?php echo ucfirst($delivery['mode']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php echo $delivery['birth_weight']; ?> kg • <?php echo ucfirst($delivery['gender']); ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?php echo htmlspecialchars($delivery['attendant']); ?>
                        </td>
                        <td class="py-3 px-4">
                            <button onclick="showPostnatalForm(<?php echo $delivery['id']; ?>, '<?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?>')" 
                                    class="px-3 py-1 bg-purple-100 text-purple-700 rounded text-sm hover:bg-purple-200">
                                <i class="fas fa-plus mr-1"></i> Visit
                            </button>
                            <button onclick="viewPostnatalVisits(<?php echo $delivery['id']; ?>, '<?php echo htmlspecialchars($delivery['first_name'] . ' ' . $delivery['last_name']); ?>')" 
                                    class="px-3 py-1 bg-gray-100 text-gray-700 rounded text-sm hover:bg-gray-200">
                                <i class="fas fa-eye mr-1"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Postnatal Visits Section -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-baby text-purple-600 mr-2"></i> Postnatal Visits
        </h2>
        
        <?php if (empty($postnatal_visits)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
            <p>No postnatal visits recorded yet</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase">Mother</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase">Visit Type</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase">Mother BP</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase">Baby Weight</th>
                        <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($postnatal_visits as $visit): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm">
                            <?php echo $visit['visit_date'] ? date('M j, Y', strtotime($visit['visit_date'])) : '-'; ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php echo htmlspecialchars($visit['visit_type'] ?? '-'); ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php echo htmlspecialchars($visit['mother_bp'] ?? '-'); ?>
                        </td>
                        <td class="py-3 px-4 text-sm">
                            <?php echo $visit['baby_weight'] ? $visit['baby_weight'] . ' kg' : '-'; ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?php echo htmlspecialchars(substr($visit['notes'] ?? '', 0, 50)); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Register Delivery Modal -->
<div id="registerModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-lg w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-dialog-header sticky top-0 bg-white border-b-2 border-[#9E2A1E] px-4 sm:px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Register Delivery</h3>
            <button type="button" onclick="closeRegisterModal()" class="p-2.5 -mr-2 text-gray-400 hover:text-gray-600 rounded-lg touch-manipulation" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="register_delivery" value="1">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mother <span class="text-red-500">*</span></label>
                <select name="patient_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="">-- Select Mother --</option>
                    <?php foreach ($patients as $p): ?>
                    <option value="<?php echo $p['id']; ?>">
                        <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name'] . ' (' . $p['patient_id'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Delivery Date <span class="text-red-500">*</span></label>
                    <input type="date" name="delivery_date" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                    <input type="time" name="delivery_time" value="<?php echo date('H:i'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Mode of Delivery <span class="text-red-500">*</span></label>
                    <select name="mode" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="normal">Normal (SVD)</option>
                        <option value="cesarean">Cesarean Section</option>
                        <option value="assisted">Assisted (Vacuum/Forceps)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Place of Delivery</label>
                    <select name="place" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="hospital">Hospital</option>
                        <option value="health_center">Health Center</option>
                        <option value="home">Home</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Attendant</label>
                    <input type="text" name="attendant" placeholder="Doctor/Midwife name"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Birth Weight (kg)</label>
                    <input type="number" step="0.1" name="birth_weight" placeholder="3.0"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Baby Gender</label>
                    <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Apgar Score</label>
                    <input type="text" name="apgar_score" placeholder="8,10"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeRegisterModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Register Delivery
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Postnatal Visit Modal -->
<div id="postnatalModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-lg w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-dialog-header sticky top-0 bg-white border-b-2 border-[#9E2A1E] px-4 sm:px-6 py-4 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Postnatal Visit</h3>
            <button type="button" onclick="closePostnatalModal()" class="p-2.5 -mr-2 text-gray-400 hover:text-gray-600 rounded-lg touch-manipulation" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="record_visit" value="1">
            <input type="hidden" name="delivery_id" id="visitDeliveryId" value="">
            
            <div class="bg-purple-50 p-3 rounded-lg mb-4">
                <p class="text-sm text-purple-800">Mother: <span id="visitMotherName" class="font-medium"></span></p>
            </div>
            
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Visit Day</label>
                    <select name="visit_day" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        <option value="1">Day 1</option>
                        <option value="7">Day 7</option>
                        <option value="42">Day 42</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Mother BP</label>
                    <input type="text" name="mother_bp" placeholder="120/80"
                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Mother Temp</label>
                    <input type="number" step="0.1" name="mother_temp" placeholder="36.5"
                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Mother Pulse</label>
                    <input type="number" name="mother_pulse" placeholder="72"
                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Breast</label>
                    <select name="mother_breast" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        <option value="normal">Normal</option>
                        <option value="engorged">Engorged</option>
                        <option value="cracked">Cracked Nipples</option>
                        <option value="mastitis">Mastitis</option>
                    </select>
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Lochia</label>
                <select name="mother_lochia" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <option value="normal">Normal</option>
                    <option value="heavy">Heavy</option>
                    <option value="foul_smell">Foul Smell</option>
                </select>
            </div>
            
            <hr class="my-3">
            <p class="text-xs font-semibold text-gray-500 uppercase">Newborn Assessment</p>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Weight (kg)</label>
                    <input type="number" step="0.1" name="baby_weight" placeholder="3.0"
                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Temp (°C)</label>
                    <input type="number" step="0.1" name="baby_temp" placeholder="36.5"
                           class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Feeding</label>
                    <select name="baby_feeding" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        <option value="breast">Breastfeeding</option>
                        <option value="formula">Formula</option>
                        <option value="mixed">Mixed</option>
                        <option value="poor">Poor Feeding</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Umbilical Cord</label>
                    <select name="baby_umbilical" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                        <option value="normal">Normal/Dry</option>
                        <option value="moist">Moist</option>
                        <option value="infected">Infected</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Danger Signs (Mother)</label>
                    <textarea name="danger_signs_mother" rows="2" placeholder="None..."
                              class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Danger Signs (Baby)</label>
                    <textarea name="danger_signs_baby" rows="2" placeholder="None..."
                              class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></textarea>
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Family Planning</label>
                <select name="family_planning" class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <option value="counselled">Counselled</option>
                    <option value="condoms">Condoms</option>
                    <option value="pills">Pills</option>
                    <option value="iud">IUD</option>
                    <option value="injection">Injection</option>
                    <option value="none">Not Ready</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full px-2 py-1.5 border border-gray-300 rounded text-sm"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closePostnatalModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Record Visit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showRegisterModal() {
        document.getElementById('registerModal').classList.remove('hidden');
    }
    
    function closeRegisterModal() {
        document.getElementById('registerModal').classList.add('hidden');
    }
    
    function showPostnatalForm(deliveryId, motherName) {
        document.getElementById('visitDeliveryId').value = deliveryId;
        document.getElementById('visitMotherName').textContent = motherName;
        document.getElementById('postnatalModal').classList.remove('hidden');
    }
    
    function closePostnatalModal() {
        document.getElementById('postnatalModal').classList.add('hidden');
    }
    
    function viewPostnatalVisits(deliveryId, motherName) {
        // Show visits for this delivery
        const visits = document.querySelectorAll('.postnatal-visit-' + deliveryId);
        visits.forEach(v => v.classList.remove('hidden'));
        alert('Showing postnatal visits for ' + motherName);
    }
</script>

<?php
require_once '../includes/footer.php';
?>
