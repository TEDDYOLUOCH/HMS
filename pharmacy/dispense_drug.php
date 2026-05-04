<?php
/**
 * Hospital Management System - Dispense Drug
 * Dispensing interface with stock deduction
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'pharmacist'], '../dashboard');

// Set page title
$page_title = 'Dispense Drug';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispense_drug'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $prescription_id = $_POST['prescription_id'] ?? 0;
            $drug_id = $_POST['drug_id'] ?? 0;
            $quantity = $_POST['quantity'] ?? 1;
            $counseling_notes = $_POST['counseling_notes'] ?? '';
            
            // Get prescription details
            $stmt = $db->prepare("SELECT * FROM prescriptions WHERE id = ?");
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$prescription) {
                $message = 'Prescription not found';
                $message_type = 'error';
            } else {
                // Check stock
                $stmt = $db->prepare("SELECT * FROM drug_stock WHERE id = ?");
                $stmt->execute([$drug_id]);
                $drug = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$drug) {
                    $message = 'Drug not found in stock';
                    $message_type = 'error';
                } elseif ($drug['quantity'] < $quantity) {
                    $message = 'Insufficient stock. Available: ' . $drug['quantity'];
                    $message_type = 'error';
                } else {
                    // Deduct from stock
                    $stmt = $db->prepare("UPDATE drug_stock SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity, $drug_id]);
                    
                    // Log stock movement
                    $stmt = $db->prepare("INSERT INTO pharmacy_stock_log (drug_id, adjustment, reason, adjusted_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$drug_id, -$quantity, "Dispensed to prescription #$prescription_id", $_SESSION['user_id']]);
                    
                    // Update prescription status
                    $stmt = $db->prepare("UPDATE prescriptions SET 
                        status = 'dispensed', 
                        dispensed_by = ?, 
                        dispensed_at = NOW(),
                        notes = CONCAT(COALESCE(notes, ''), '\nDispensed: $quantity $drug[unit]')
                        WHERE id = ?");
                    $stmt->execute([$_SESSION['user_id'], $prescription_id]);
                    
                    // Log activity
                    logActivity('Dispensed', 'Pharmacy', 'prescriptions', $prescription_id, "Dispensed $quantity $drug[unit] of $drug[drug_name] to prescription #$prescription_id");
                    
                    $message = 'Drug dispensed successfully!';
                    $message_type = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->GetMessage();
            $message_type = 'error';
        }
    }
}

// Get prescription details
$prescription_id = $_GET['prescription_id'] ?? 0;
$prescription = null;
$patient = null;
$available_drugs = [];
$pending_prescriptions = [];

// Get all pending prescriptions for the queue view
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT pr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth, p.allergies 
                         FROM prescriptions pr 
                         JOIN patients p ON pr.patient_id = p.id 
                         WHERE pr.status = 'pending' 
                         ORDER BY pr.created_at DESC");
    $pending_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pending_prescriptions = [];
}

if ($prescription_id) {
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT pr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth, p.allergies
                             FROM prescriptions pr
                             JOIN patients p ON pr.patient_id = p.id
                             WHERE pr.id = ?");
        $stmt->execute([$prescription_id]);
        $prescription = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get available drugs matching the prescription
        if ($prescription) {
            $search_term = '%' . $prescription['drug_name'] . '%';
            $stmt = $db->prepare("SELECT * FROM drug_stock WHERE drug_name LIKE ? AND quantity > 0 ORDER BY quantity DESC");
            $stmt->execute([$search_term]);
            $available_drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If no match, get all available drugs
            if (empty($available_drugs)) {
                $stmt = $db->query("SELECT * FROM drug_stock WHERE quantity > 0 ORDER BY drug_name");
                $available_drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
        
    } catch (Exception $e) {
        $error = 'Prescription not found';
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
                    <i class="fas fa-pills text-green-600 mr-2"></i> Dispense Drug
                </h1>
                <p class="text-gray-500 mt-1">Verify and dispense medications to patients</p>
            </div>
            <a href="prescriptions" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-arrow-left mr-2"></i> Back to Queue
            </a>
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
    
    <?php if (!$prescription): ?>
    <!-- Pending Prescriptions Queue -->
    <?php if (empty($pending_prescriptions)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
        </div>
        <p class="text-gray-500 text-lg">No pending prescriptions to dispense</p>
        <a href="prescriptions" class="inline-block mt-4 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
            <i class="fas fa-list mr-2"></i> View All Prescriptions
        </a>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-clock text-orange-500 mr-2"></i> Pending Prescriptions (<?php echo count($pending_prescriptions); ?>)
            </h3>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($pending_prescriptions as $rx): ?>
            <a href="?prescription_id=<?php echo $rx['id']; ?>" class="block p-4 hover:bg-gray-50 transition">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-white font-bold text-sm">
                                <?php echo strtoupper(substr($rx['first_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <div>
                            <p class="font-medium text-gray-800">
                                <?php echo htmlspecialchars($rx['first_name'] . ' ' . $rx['last_name']); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <span class="text-xs bg-gray-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($rx['patient_id']); ?></span>
                                <span class="ml-2"><?php echo htmlspecialchars($rx['drug_name']); ?></span>
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="inline-block px-3 py-1 bg-orange-100 text-orange-700 rounded-full text-xs font-medium">
                            Pending
                        </span>
                        <p class="text-xs text-gray-400 mt-1">
                            <?php echo date('M d, Y', strtotime($rx['created_at'])); ?>
                        </p>
                    </div>
                </div>
                <?php if ($rx['allergies']): ?>
                <div class="mt-2 flex items-center gap-2 text-xs text-red-600">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Allergies: <?php echo htmlspecialchars($rx['allergies']); ?></span>
                </div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Dispensing Form -->
        <div class="lg:col-span-2">
            <form method="POST" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="dispense_drug" value="1">
                <input type="hidden" name="prescription_id" value="<?php echo $prescription_id; ?>">
                
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-pills text-gray-400 mr-2"></i> Dispensing Details
                </h3>
                
                <!-- Drug Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Drug from Stock <span class="text-red-500">*</span></label>
                    <select name="drug_id" id="drugSelect" required onchange="updateStockInfo()"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="">-- Select Drug --</option>
                        <?php foreach ($available_drugs as $drug): ?>
                        <option value="<?php echo $drug['id']; ?>" data-qty="<?php echo $drug['quantity']; ?>" data-price="<?php echo $drug['unit_price']; ?>">
                            <?php echo htmlspecialchars($drug['drug_name']); ?> 
                            (Stock: <?php echo $drug['quantity']; ?>, Price: KSh <?php echo number_format($drug['unit_price'], 2); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div id="stockWarning" class="hidden mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-700">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        <span id="stockWarningText"></span>
                    </div>
                </div>
                
                <!-- Quantity -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity to Dispense <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" id="quantity" value="1" min="1" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                               onchange="calculateTotal()">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Total Cost (KSh)</label>
                        <input type="text" id="totalCost" readonly
                               class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg">
                    </div>
                </div>
                
                <!-- Counseling Notes -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Patient Counseling Notes</label>
                    <textarea name="counseling_notes" rows="3"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                              placeholder="Notes for patient (e.g., take after food, avoid alcohol...)"></textarea>
                </div>
                
                <!-- Dispense Button -->
                <button type="submit" class="w-full px-4 py-3 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition">
                    <i class="fas fa-check-circle mr-2"></i> Confirm Dispensing
                </button>
            </form>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Patient Info -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user text-gray-400 mr-2"></i> Patient Information
                </h3>
                
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-bold">
                            <?php echo strtoupper(substr($prescription['first_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">
                            <?php echo htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']); ?>
                        </p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($prescription['patient_id']); ?></p>
                    </div>
                </div>
                
                <?php if ($prescription['allergies']): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                        <span class="text-sm font-medium text-red-700">Allergies: <?php echo htmlspecialchars($prescription['allergies']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Age/Gender</span>
                        <span class="text-gray-800">
                            <?php echo floor((time() - strtotime($prescription['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> yrs / 
                            <?php echo ucfirst($prescription['gender']); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Prescription Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-prescription text-gray-400 mr-2"></i> Prescription
                </h3>
                
                <div class="space-y-3">
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Drug Prescribed</p>
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($prescription['drug_name']); ?></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Dosage</p>
                            <p class="text-gray-800"><?php echo htmlspecialchars($prescription['dosage']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Frequency</p>
                            <p class="text-gray-800"><?php echo htmlspecialchars($prescription['frequency']); ?></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-xs text-gray-500 uppercase">Duration</p>
                            <p class="text-gray-800"><?php echo $prescription['duration'] . ' ' . $prescription['duration_unit']; ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase">End Date</p>
                            <p class="text-gray-800"><?php echo date('M j, Y', strtotime($prescription['end_date'])); ?></p>
                        </div>
                    </div>
                    <?php if ($prescription['instructions']): ?>
                    <div>
                        <p class="text-xs text-gray-500 uppercase">Instructions</p>
                        <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($prescription['instructions']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Print Label -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-print text-gray-400 mr-2"></i> Print Options
                </h3>
                <button onclick="printLabel()" class="w-full px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i> Print Dispensing Label
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Print Label Modal -->
<div id="printModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-sm w-full p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b-2 border-[#9E2A1E] pb-2">Dispensing Label</h3>
        
        <div id="labelContent" class="border-2 border-black p-4 text-center">
            <p class="font-bold text-lg">SIWOT HOSPITAL</p>
            <p class="text-sm">Pharmacy Department</p>
            <hr class="my-2 border-black">
            <p class="text-sm"><strong>Patient:</strong> <?php echo $prescription ? htmlspecialchars($prescription['first_name'] . ' ' . $prescription['last_name']) : ''; ?></p>
            <p class="text-sm"><strong>Drug:</strong> <?php echo $prescription ? htmlspecialchars($prescription['drug_name']) : ''; ?></p>
            <p class="text-sm"><strong>Dose:</strong> <?php echo $prescription ? htmlspecialchars($prescription['dosage']) : ''; ?></p>
            <p class="text-sm"><strong>Freq:</strong> <?php echo $prescription ? htmlspecialchars($prescription['frequency']) : ''; ?></p>
            <p class="text-sm"><strong>Duration:</strong> <?php echo $prescription ? $prescription['duration'] . ' ' . $prescription['duration_unit'] : ''; ?></p>
            <hr class="my-2 border-black">
            <p class="text-xs"><?php echo date('Y-m-d H:i'); ?></p>
        </div>
        
        <div class="flex gap-3 mt-4">
            <button onclick="window.print()" class="flex-1 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                Print
            </button>
            <button onclick="closePrintModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                Close
            </button>
        </div>
    </div>
</div>

<script>
    function updateStockInfo() {
        const select = document.getElementById('drugSelect');
        const option = select.options[select.selectedIndex];
        const qty = parseInt(option.dataset.qty) || 0;
        
        const warning = document.getElementById('stockWarning');
        const warningText = document.getElementById('stockWarningText');
        
        if (qty <= 10 && qty > 0) {
            warning.classList.remove('hidden');
            warningText.textContent = 'Low stock! Only ' + qty + ' units available.';
        } else if (qty === 0) {
            warning.classList.remove('hidden');
            warningText.textContent = 'Out of stock!';
        } else {
            warning.classList.add('hidden');
        }
        
        calculateTotal();
    }
    
    function calculateTotal() {
        const select = document.getElementById('drugSelect');
        const option = select.options[select.selectedIndex];
        const price = parseFloat(option.dataset.price) || 0;
        const qty = parseInt(document.getElementById('quantity').value) || 0;
        
        const total = price * qty;
        document.getElementById('totalCost').value = 'KSh ' + total.toFixed(2);
    }
    
    function printLabel() {
        document.getElementById('printModal').classList.remove('hidden');
    }
    
    function closePrintModal() {
        document.getElementById('printModal').classList.add('hidden');
    }
</script>

<?php
require_once '../includes/footer.php';
?>
