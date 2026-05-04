<?php
/**
 * Hospital Management System - Pharmacy Prescriptions
 * Incoming prescriptions queue from OPD
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'pharmacist'], '../dashboard');

// Set page title
$page_title = 'Prescriptions Queue';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();
        
        // Mark as dispensed
        if (isset($_POST['dispense'])) {
            $prescription_id = $_POST['prescription_id'] ?? 0;
            try {
                $stmt = $db->prepare("UPDATE prescriptions SET status = 'dispensed', dispensed_by = ?, dispensed_at = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $prescription_id]);
                
                $message = 'Prescription marked as dispensed';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Send to doctor for review
        if (isset($_POST['request_review'])) {
            $prescription_id = $_POST['prescription_id'] ?? 0;
            try {
                $stmt = $db->prepare("UPDATE prescriptions SET status = 'review_requested', notes = CONCAT(COALESCE(notes, ''), '\nReview requested by pharmacy') WHERE id = ?");
                $stmt->execute([$prescription_id]);
                
                $message = 'Request sent to doctor';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get filter
$status_filter = $_GET['status'] ?? '';

// Build query
$where_clause = "1=1";
$params = [];

if ($status_filter) {
    if ($status_filter === 'all') {
        // Show all prescriptions
    } else {
        $where_clause .= " AND pr.status = :status";
        $params['status'] = $status_filter;
    }
} else {
    // Default show pending and review_requested
    $where_clause .= " AND pr.status IN ('pending', 'review_requested')";
}

// Get prescriptions
$prescriptions = [];
try {
    $db = Database::getInstance();
    $sql = "SELECT pr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth, p.allergies,
            u.username as prescribed_by_name
            FROM prescriptions pr
            JOIN patients p ON pr.patient_id = p.id
            LEFT JOIN users u ON pr.prescribed_by = u.id
            WHERE $where_clause
            ORDER BY pr.created_at ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist
}

// Get statistics
$stats = ['pending' => 0, 'dispensed' => 0, 'review_requested' => 0, 'all' => 0];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT COALESCE(status, 'pending') as status, COUNT(*) as count FROM prescriptions GROUP BY COALESCE(status, 'pending')");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
        $stats['all'] += $row['count'];
    }
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
                    <i class="fas fa-prescription text-orange-600 mr-2"></i> Prescriptions Queue
                </h1>
                <p class="text-gray-500 mt-1">Manage incoming prescriptions from OPD</p>
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
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <a href="?status=pending" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-yellow-400 transition <?php echo $status_filter === 'pending' ? 'ring-2 ring-yellow-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending']; ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?status=review_requested" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-blue-400 transition <?php echo $status_filter === 'review_requested' ? 'ring-2 ring-blue-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Review Requested</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $stats['review_requested']; ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-question-circle text-blue-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?status=dispensed" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-green-400 transition <?php echo $status_filter === 'dispensed' ? 'ring-2 ring-green-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Dispensed</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['dispensed']; ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check text-green-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?status=all" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-gray-400 transition <?php echo $status_filter === 'all' ? 'ring-2 ring-gray-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">All</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['all']; ?></p>
                </div>
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-list text-gray-600"></i>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Prescriptions List -->
    <div class="space-y-4">
        <?php if (empty($prescriptions)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-prescription text-gray-400 text-3xl"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Prescriptions</h3>
            <p class="text-gray-500">
                <?php echo $status_filter ? 'No prescriptions with this status' : 'No pending prescriptions in queue'; ?>
            </p>
        </div>
        <?php else: ?>
            <?php foreach ($prescriptions as $rx): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <!-- Prescription Header -->
                <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold">
                                <?php echo strtoupper(substr($rx['first_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-800">
                                    <?php echo htmlspecialchars($rx['first_name'] . ' ' . $rx['last_name']); ?>
                                </h3>
                                <?php if ($rx['allergies']): ?>
                                <span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">
                                    <i class="fas fa-exclamation-triangle mr-1"></i> Allergies
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-500">
                                <?php echo htmlspecialchars($rx['patient_id']); ?> • 
                                <?php echo floor((time() - strtotime($rx['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years • 
                                <?php echo ucfirst($rx['gender']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Status Badge -->
                    <?php 
                    $status_config = [
                        'pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'label' => 'Pending'],
                        'review_requested' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Review Requested'],
                        'dispensed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Dispensed'],
                        'completed' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Completed'],
                    ];
                    $status_style = $status_config[$rx['status']] ?? $status_config['pending'];
                    ?>
                    <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo $status_style['bg'] . ' ' . $status_style['text']; ?>">
                        <?php echo $status_style['label']; ?>
                    </span>
                </div>
                
                <!-- Prescription Details -->
                <div class="px-4 pb-4 border-t border-gray-100">
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Drug</p>
                            <p class="text-gray-800 font-medium"><?php echo htmlspecialchars($rx['drug_name']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Dosage & Frequency</p>
                            <p class="text-gray-800"><?php echo htmlspecialchars($rx['dosage']); ?> - <?php echo htmlspecialchars($rx['frequency']); ?></p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-gray-500 uppercase">Duration</p>
                            <p class="text-gray-800"><?php echo $rx['duration'] . ' ' . $rx['duration_unit']; ?></p>
                        </div>
                    </div>
                    
                    <?php if ($rx['instructions']): ?>
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <p class="text-xs font-semibold text-gray-500 uppercase">Instructions</p>
                        <p class="text-gray-700 text-sm"><?php echo htmlspecialchars($rx['instructions']); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                        <span>Prescribed by: Dr. <?php echo htmlspecialchars($rx['prescribed_by_name'] ?? 'Unknown'); ?></span>
                        <span><?php echo date('M j, g:i A', strtotime($rx['created_at'])); ?></span>
                    </div>
                    
                    <!-- Actions -->
                    <?php if ($rx['status'] === 'pending' || $rx['status'] === 'review_requested'): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100 flex flex-wrap gap-2">
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="prescription_id" value="<?php echo $rx['id']; ?>">
                            <button type="submit" name="dispense" class="px-3 py-1.5 bg-brand-600 text-white rounded-lg text-sm hover:bg-brand-700">
                                <i class="fas fa-check mr-1"></i> Mark Dispensed
                            </button>
                        </form>
                        
                        <a href="dispense_drug.php?prescription_id=<?php echo $rx['id']; ?>" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">
                            <i class="fas fa-pills mr-1"></i> Dispense
                        </a>
                        
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="prescription_id" value="<?php echo $rx['id']; ?>">
                            <button type="submit" name="request_review" class="px-3 py-1.5 bg-orange-100 text-orange-700 rounded-lg text-sm hover:bg-orange-200">
                                <i class="fas fa-question mr-1"></i> Request Review
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($rx['status'] === 'dispensed'): ?>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <span class="text-sm text-green-600">
                            <i class="fas fa-check-circle mr-1"></i> Dispensed at <?php echo date('g:i A', strtotime($rx['dispensed_at'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
