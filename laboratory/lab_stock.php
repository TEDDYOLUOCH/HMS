<?php
/**
 * Hospital Management System - Laboratory Stock Management
 * View and manage laboratory reagents and supplies
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission - lab roles, admin
requireRole(['admin', 'lab_technologist', 'lab_scientist'], '../dashboard');

// Set page title
$page_title = 'Lab Stock Management';

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
            
            if ($action === 'add') {
                // Add new stock item
                $stmt = $db->prepare("INSERT INTO lab_stock (item_name, category, quantity, unit, expiry_date, supplier, low_stock_threshold, added_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['item_name'],
                    $_POST['category'],
                    $_POST['quantity'],
                    $_POST['unit'],
                    $_POST['expiry_date'] ?: null,
                    $_POST['supplier'] ?: null,
                    $_POST['low_stock_threshold'] ?? 10,
                    $_SESSION['user_id']
                ]);
                $message = 'Stock item added successfully!';
                $message_type = 'success';
            }
            
            if ($action === 'update') {
                // Update stock quantity
                $stmt = $db->prepare("UPDATE lab_stock SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$_POST['quantity_change'], $_POST['stock_id']]);
                $message = 'Stock updated successfully!';
                $message_type = 'success';
            }
            
            if ($action === 'delete') {
                // Delete stock item
                $stmt = $db->prepare("DELETE FROM lab_stock WHERE id = ?");
                $stmt->execute([$_POST['stock_id']]);
                $message = 'Stock item deleted successfully!';
                $message_type = 'success';
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get stock items
$stock_items = [];
$low_stock_items = [];
try {
    $db = Database::getInstance();
    
    // Get all stock items
    $stmt = $db->query("SELECT * FROM lab_stock ORDER BY category, item_name");
    $stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get low stock items
    $stmt = $db->query("SELECT * FROM lab_stock WHERE quantity <= low_stock_threshold ORDER BY quantity ASC");
    $low_stock_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Error loading stock data';
}

// Get categories for filter
$categories = ['Reagents', 'Consumables', 'Test Strips', 'Other'];

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
                            <i class="fas fa-flask text-brand-600 mr-2"></i> Lab Stock Management
                        </h1>
                        <p class="text-gray-500 mt-1">Manage laboratory reagents and supplies</p>
                    </div>
                    <button onclick="openAddModal()" class="inline-flex items-center px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-plus mr-2"></i> Add Stock Item
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Low Stock Alerts -->
            <?php if (!empty($low_stock_items)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-lg">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-500"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Low Stock Alert</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>The following items are running low:</p>
                            <ul class="list-disc list-inside mt-1">
                                <?php foreach ($low_stock_items as $item): ?>
                                <li><?php echo htmlspecialchars($item['item_name']); ?> - <?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit']); ?> remaining</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Stock Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Items</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($stock_items); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-boxes text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Quantity</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo array_sum(array_column($stock_items, 'quantity')); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-clipboard-list text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Low Stock</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo count($low_stock_items); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center text-red-600">
                            <i class="fas fa-exclamation-circle text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Categories</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count(array_unique(array_column($stock_items, 'category'))); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="fas fa-tags text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Current Stock</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($stock_items)): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">No stock items found. Click "Add Stock Item" to get started.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($stock_items as $item): ?>
                            <?php 
                                $is_low = $item['quantity'] <= $item['low_stock_threshold'];
                                $is_expiring = $item['expiry_date'] && strtotime($item['expiry_date']) < strtotime('+30 days');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                        <?php echo htmlspecialchars($item['category']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="<?php echo $is_low ? 'text-red-600 font-semibold' : 'text-gray-900'; ?>">
                                        <?php echo $item['quantity']; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($item['expiry_date']): ?>
                                    <span class="<?php echo $is_expiring ? 'text-red-600' : 'text-gray-600'; ?>">
                                        <?php echo date('M j, Y', strtotime($item['expiry_date'])); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600"><?php echo htmlspecialchars($item['supplier'] ?? '-'); ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($is_low): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Low Stock</span>
                                    <?php elseif ($is_expiring): ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Expiring Soon</span>
                                    <?php else: ?>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-brand-100 text-green-700">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button onclick="openUpdateModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>)" 
                                                class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-lg" title="Update Stock">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="openDeleteModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')" 
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
    
    <!-- Add Stock Modal -->
    <div id="addModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div id="addModalBackdrop" class="fixed inset-0 bg-black/50" onclick="closeAddModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden flex flex-col">
                <div class="modal-header-bg">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">Add Stock Item</h2>
                        <button onclick="closeAddModal()" class="modal-close-btn p-1 rounded-full">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" class="p-4 space-y-4">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Name *</label>
                        <input type="text" name="item_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <input type="number" name="quantity" required min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Unit *</label>
                            <input type="text" name="unit" required placeholder="e.g., tests, pieces, ml" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                            <input type="date" name="expiry_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Low Stock Alert</label>
                            <input type="number" name="low_stock_threshold" value="10" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                        <input type="text" name="supplier" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Add Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Stock Modal -->
    <div id="updateModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div id="updateModalBackdrop" class="fixed inset-0 bg-black/50" onclick="closeUpdateModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden flex flex-col">
                <div class="modal-header-bg">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">Update Stock</h2>
                        <button onclick="closeUpdateModal()" class="modal-close-btn p-1 rounded-full">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" class="p-4 space-y-4">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="stock_id" id="updateStockId">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Item Name</label>
                        <p id="updateItemName" class="text-gray-900 font-medium"></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Quantity</label>
                        <p id="currentQty" class="text-gray-600"></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Quantity Change *</label>
                        <input type="number" name="quantity_change" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Use negative number to reduce stock">
                        <p class="text-xs text-gray-500 mt-1">Use positive number to add, negative to reduce</p>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeUpdateModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div id="deleteModalBackdrop" class="fixed inset-0 bg-black/50" onclick="closeDeleteModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
                <div class="modal-header-bg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        <h2 class="text-lg font-semibold text-white">Delete Stock Item</h2>
                    </div>
                </div>
                <form method="POST" class="p-4">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="stock_id" id="deleteStockId">
                    
                    <div class="text-center py-4">
                        <p class="text-gray-600">Are you sure you want to delete:</p>
                        <p id="deleteItemName" class="font-medium text-gray-900 mt-2"></p>
                    </div>
                    
                    <div class="flex justify-end gap-3 mt-4">
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
    
    <script>
    function openAddModal() {
        document.getElementById('addModal').classList.remove('hidden');
    }
    
    function closeAddModal() {
        document.getElementById('addModal').classList.add('hidden');
    }
    
    function openUpdateModal(id, name, qty) {
        document.getElementById('updateStockId').value = id;
        document.getElementById('updateItemName').textContent = name;
        document.getElementById('currentQty').textContent = qty;
        document.getElementById('updateModal').classList.remove('hidden');
    }
    
    function closeUpdateModal() {
        document.getElementById('updateModal').classList.add('hidden');
    }
    
    function openDeleteModal(id, name) {
        document.getElementById('deleteStockId').value = id;
        document.getElementById('deleteItemName').textContent = name;
        document.getElementById('deleteModal').classList.remove('hidden');
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.add('hidden');
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
