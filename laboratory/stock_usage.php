<?php
/**
 * Hospital Management System - Stock Usage Recording
 * Record when stock items are used during lab tests
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'lab_technologist', 'lab_scientist'], '../dashboard');

// Set page title
$page_title = 'Stock Usage';

// Include database and header
require_once '../config/database.php';

// Generate CSRF token
$csrf_token = csrfToken();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $db = Database::getInstance();
            
            if ($action === 'record') {
                // Record stock usage
                $stock_id = $_POST['stock_id'];
                $quantity_used = $_POST['quantity_used'];
                
                // Check if enough stock available
                $stmt = $db->prepare("SELECT quantity, item_name FROM lab_stock WHERE id = ?");
                $stmt->execute([$stock_id]);
                $stock = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$stock) {
                    $message = 'Stock item not found.';
                    $message_type = 'danger';
                } elseif ($stock['quantity'] < $quantity_used) {
                    $message = 'Insufficient stock! Available: ' . $stock['quantity'];
                    $message_type = 'danger';
                } else {
                    // Record usage
                    $stmt = $db->prepare("INSERT INTO lab_stock_usage (stock_id, quantity_used, lab_request_id, test_type, used_by, notes) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $stock_id,
                        $quantity_used,
                        $_POST['lab_request_id'] ?: null,
                        $_POST['test_type'] ?: null,
                        $_SESSION['user_id'],
                        $_POST['notes'] ?: null
                    ]);
                    
                    // Deduct from stock
                    $stmt = $db->prepare("UPDATE lab_stock SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity_used, $stock_id]);
                    
                    $message = 'Stock usage recorded successfully!';
                    $message_type = 'success';
                }
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get stock items for dropdown
$stock_items = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM lab_stock WHERE quantity > 0 ORDER BY item_name");
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get usage history
$usage_history = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT u.*, s.item_name, s.unit, u2.username as used_by_name 
                        FROM lab_stock_usage u 
                        JOIN lab_stock s ON u.stock_id = s.id 
                        LEFT JOIN users u2 ON u.used_by = u2.id
                        ORDER BY u.usage_date DESC LIMIT 50");
    $usage_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get recent lab requests for dropdown
$lab_requests = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT lr.id, lr.test_type_id, lt.test_name, p.first_name, p.last_name 
                        FROM lab_requests lr 
                        LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                        JOIN patients p ON lr.patient_id = p.id
                        WHERE lr.status IN ('pending', 'completed')
                        ORDER BY lr.created_at DESC LIMIT 20");
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

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
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-vial text-brand-600 mr-2"></i> Stock Usage
                        </h1>
                        <p class="text-gray-500 mt-1">Record stock usage when performing lab tests</p>
                    </div>
                    <button onclick="openRecordModal()" class="inline-flex items-center px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-plus mr-2"></i> Record Usage
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Usage Records</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($usage_history); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-list text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Items Used Today</p>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php 
                                $today = date('Y-m-d');
                                echo count(array_filter($usage_history, function($h) use ($today) {
                                    return date('Y-m-d', strtotime($h['usage_date'])) === $today;
                                }));
                                ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Available Items</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($stock_items); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="fas fa-boxes text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Usage History Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Usage History</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity Used</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Test Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Used By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($usage_history)): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">No usage records found.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($usage_history as $usage): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo date('M j, Y g:i A', strtotime($usage['usage_date'])); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($usage['item_name']); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-red-600 font-medium">-<?php echo $usage['quantity_used']; ?></span>
                                    <span class="text-gray-500"><?php echo htmlspecialchars($usage['unit']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo htmlspecialchars($usage['test_type'] ?? '-'); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <?php echo htmlspecialchars($usage['used_by_name'] ?? 'Unknown'); ?>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-sm">
                                    <?php echo htmlspecialchars($usage['notes'] ?? '-'); ?>
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
    
    <!-- Record Usage Modal -->
    <div id="recordModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div id="recordModalBackdrop" class="fixed inset-0 bg-black/50" onclick="closeRecordModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden flex flex-col">
                <div class="modal-header-bg">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">Record Stock Usage</h2>
                        <button onclick="closeRecordModal()" class="modal-close-btn p-1 rounded-full">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" class="p-4 space-y-4">
                    <input type="hidden" name="action" value="record">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stock Item *</label>
                        <select name="stock_id" id="stockSelect" required onchange="updateAvailableQty()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select Item</option>
                            <?php foreach ($stock_items as $item): ?>
                            <option value="<?php echo $item['id']; ?>" data-qty="<?php echo $item['quantity']; ?>" data-unit="<?php echo htmlspecialchars($item['unit']); ?>">
                                <?php echo htmlspecialchars($item['item_name']); ?> (Available: <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p id="availableQty" class="text-xs text-gray-500 mt-1"></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Used *</label>
                        <input type="number" name="quantity_used" id="quantityUsed" required min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Lab Request (Optional)</label>
                        <select name="lab_request_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select Lab Request</option>
                            <?php foreach ($lab_requests as $req): ?>
                            <option value="<?php echo $req['id']; ?>">
                                #<?php echo $req['id']; ?> - <?php echo htmlspecialchars(($req['first_name'] ?? '') . ' ' . ($req['last_name'] ?? '')); ?> (<?php echo htmlspecialchars($req['test_name'] ?? 'Lab Test'); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Test Type</label>
                        <input type="text" name="test_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="e.g., CBC, Chemistry">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Any additional notes..."></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeRecordModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Record Usage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function openRecordModal() {
        document.getElementById('recordModal').classList.remove('hidden');
    }
    
    function closeRecordModal() {
        document.getElementById('recordModal').classList.add('hidden');
    }
    
    function updateAvailableQty() {
        const select = document.getElementById('stockSelect');
        const option = select.options[select.selectedIndex];
        const qty = option.getAttribute('data-qty');
        const unit = option.getAttribute('data-unit');
        
        if (qty) {
            document.getElementById('availableQty').textContent = 'Available: ' + qty + ' ' + unit;
            document.getElementById('quantityUsed').max = qty;
        } else {
            document.getElementById('availableQty').textContent = '';
        }
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
