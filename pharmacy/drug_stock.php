<?php
/**
 * Hospital Management System - Pharmacy Drug Stock
 * Complete drug inventory management
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'pharmacist'], '../dashboard');

// Set page title
$page_title = 'Drug Stock Management';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';
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
        
        // Add new drug
        if (isset($_POST['add_drug'])) {
            try {
                $stmt = $db->prepare("INSERT INTO drug_stock 
                    (drug_name, generic_name, category, supplier, batch_number, quantity, reorder_level, expiry_date, unit_price, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $stmt->execute([
                    $_POST['drug_name'],
                    $_POST['generic_name'] ?? '',
                    $_POST['category'] ?? 'General',
                    $_POST['supplier'] ?? '',
                    $_POST['batch_number'] ?? '',
                    $_POST['quantity'] ?? 0,
                    $_POST['reorder_level'] ?? 10,
                    $_POST['expiry_date'] ?? null,
                    $_POST['unit_price'] ?? 0
                ]);
                
                // Log activity
                logActivity('Added', 'Pharmacy', 'drug_stock', null, "Added drug: " . $_POST['drug_name']);
                
                $message = 'Drug added successfully';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Update stock
        if (isset($_POST['update_stock'])) {
            $drug_id = $_POST['drug_id'];
            $adjustment = $_POST['adjustment'];
            $reason = $_POST['reason'] ?? 'Stock adjustment';
            
            try {
                $stmt = $db->prepare("UPDATE drug_stock SET quantity = quantity + ? WHERE id = ?");
                $stmt->execute([$adjustment, $drug_id]);
                
                // Log the adjustment
                $stmt = $db->prepare("INSERT INTO pharmacy_stock_log (drug_id, adjustment, reason, adjusted_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$drug_id, $adjustment, $reason, $_SESSION['user_id']]);
                
                // Log activity
                logActivity('Stock Updated', 'Pharmacy', 'drug_stock', $drug_id, "Stock adjustment: $adjustment - $reason");
                
                $message = 'Stock updated successfully';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get filter
$category_filter = $_GET['category'] ?? '';
$stock_filter = $_GET['stock'] ?? '';

// Build query
$where_clause = "1=1";
$params = [];

if ($category_filter) {
    $where_clause .= " AND category = :category";
    $params['category'] = $category_filter;
}

if ($stock_filter === 'low') {
    $where_clause .= " AND quantity <= reorder_level";
} elseif ($stock_filter === 'out') {
    $where_clause .= " AND quantity = 0";
} elseif ($stock_filter === 'expired') {
    $where_clause .= " AND expiry_date < CURDATE()";
} elseif ($stock_filter === 'expiring') {
    $where_clause .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH)";
}

// Get drugs with pagination
$drugs = [];
$total_records = 0;
$total_pages = 0;
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

try {
    $db = Database::getInstance();
    
    // Get total count
    $count_stmt = $db->query("SELECT COUNT(*) as total FROM drug_stock");
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $per_page);
    
    // Get paginated drugs
    $sql = "SELECT * FROM drug_stock ORDER BY drug_name ASC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist - show empty
    $drugs = [];
}

// Get categories
$categories = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT DISTINCT category FROM drug_stock ORDER BY category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories[] = $row['category'];
    }
} catch (Exception $e) {}

// Get statistics
$stats = ['total' => 0, 'low' => 0, 'expired' => 0, 'expiring' => 0];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as count FROM drug_stock");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM drug_stock WHERE quantity <= reorder_level");
    $stats['low'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM drug_stock WHERE expiry_date < CURDATE()");
    $stats['expired'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM drug_stock WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 MONTH) AND expiry_date >= CURDATE()");
    $stats['expiring'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
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
                    <i class="fas fa-pills text-orange-600 mr-2"></i> Drug Stock Management
                </h1>
                <p class="text-gray-500 mt-1">Manage pharmacy inventory and stock levels</p>
            </div>
            <button onclick="showAddDrugModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-plus mr-2"></i> Add New Drug
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
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Drugs</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-pills text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <a href="?stock=low" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-yellow-400 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Low Stock</p>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['low']; ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?stock=expiring" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-orange-400 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Expiring Soon</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo $stats['expiring']; ?></p>
                </div>
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-orange-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?stock=expired" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-red-400 transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Expired</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['expired']; ?></p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-600"></i>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-filter text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">Filters:</span>
            </div>
            
            <select onchange="window.location.href = '?stock=' + this.value" 
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Stock Levels</option>
                <option value="low" <?php echo $stock_filter === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                <option value="out" <?php echo $stock_filter === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                <option value="expiring" <?php echo $stock_filter === 'expiring' ? 'selected' : ''; ?>>Expiring Soon</option>
                <option value="expired" <?php echo $stock_filter === 'expired' ? 'selected' : ''; ?>>Expired</option>
            </select>
            
            <select onchange="window.location.href = '?category=' + this.value" 
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($stock_filter || $category_filter): ?>
            <a href="drug_stock" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">
                <i class="fas fa-times mr-1"></i> Clear Filters
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Drug List -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Drug Name</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Category</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Batch</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Qty</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Expiry</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Reorder</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Price</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($drugs)): ?>
                    <tr>
                        <td colspan="9" class="py-8 text-center text-gray-500">
                            <i class="fas fa-pills text-4xl mb-3 text-gray-300"></i>
                            <p>No drugs found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($drugs as $drug): ?>
                        <tr class="hover:bg-gray-50 <?php echo $drug['quantity'] <= $drug['reorder_level'] ? 'bg-yellow-50' : ''; ?>">
                            <td class="py-3 px-4">
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($drug['drug_name']); ?></p>
                                    <?php if ($drug['generic_name']): ?>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($drug['generic_name']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($drug['category']); ?></td>
                            <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($drug['batch_number']); ?></td>
                            <td class="py-3 px-4 text-right font-medium text-gray-800"><?php echo $drug['quantity']; ?></td>
                            <td class="py-3 px-4 text-sm">
                                <?php 
                                $expiry = strtotime($drug['expiry_date']);
                                $now = time();
                                $days = ceil(($expiry - $now) / (60 * 60 * 24));
                                
                                if ($expiry < $now) {
                                    echo '<span class="text-red-600 font-medium">Expired</span>';
                                } elseif ($days < 30) {
                                    echo '<span class="text-orange-600 font-medium">' . date('M Y', $expiry) . '</span>';
                                } else {
                                    echo date('M Y', $expiry);
                                }
                                ?>
                            </td>
                            <td class="py-3 px-4 text-right text-sm text-gray-600"><?php echo $drug['reorder_level']; ?></td>
                            <td class="py-3 px-4 text-right text-sm text-gray-600">KSh <?php echo number_format($drug['price'] ?? $drug['unit_price'] ?? 0, 2); ?></td>
                            <td class="py-3 px-4">
                                <?php 
                                if ($drug['quantity'] == 0) {
                                    echo '<span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Out of Stock</span>';
                                } elseif ($drug['quantity'] <= $drug['reorder_level']) {
                                    echo '<span class="px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">Low Stock</span>';
                                } elseif ($expiry < $now) {
                                    echo '<span class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">Expired</span>';
                                } elseif ($days < 90) {
                                    echo '<span class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Expiring</span>';
                                } else {
                                    echo '<span class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">In Stock</span>';
                                }
                                ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1">
                                    <button onclick="showEditStock(<?php echo $drug['id']; ?>, '<?php echo htmlspecialchars($drug['drug_name'], ENT_QUOTES, 'UTF-8'); ?>', <?php echo (int)$drug['quantity']; ?>)" 
                                            class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Adjust Stock">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4 px-4 py-3 bg-gray-50 rounded-lg">
            <div class="text-sm text-gray-600">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> drugs
            </div>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>" class="px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>" class="px-3 py-1.5 rounded-lg <?php echo $i === $page ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-100'; ?> text-sm">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>" class="px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Drug Modal -->
<div id="addDrugModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-lg w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Add New Drug</h3>
                <button type="button" onclick="closeAddDrugModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="add_drug" value="1">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Drug Name <span class="text-red-500">*</span></label>
                <input type="text" name="drug_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Generic Name</label>
                <input type="text" name="generic_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="General">General</option>
                        <option value="Antibiotics">Antibiotics</option>
                        <option value="Pain Relief">Pain Relief</option>
                        <option value="Cardiovascular">Cardiovascular</option>
                        <option value="Diabetes">Diabetes</option>
                        <option value="Respiratory">Respiratory</option>
                        <option value="Gastrointestinal">Gastrointestinal</option>
                        <option value="Vitamins">Vitamins</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                    <input type="text" name="supplier" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Batch Number</label>
                    <input type="text" name="batch_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                    <input type="date" name="expiry_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                    <input type="number" name="quantity" value="0" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reorder Level</label>
                    <input type="number" name="reorder_level" value="10" min="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price (KSh)</label>
                    <input type="number" name="unit_price" value="0" min="0" step="0.01" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeAddDrugModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Add Drug
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="stockModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-md w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Adjust Stock</h3>
                <button type="button" onclick="closeStockModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="p-4 sm:p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="update_stock" value="1">
            <input type="hidden" name="drug_id" id="editDrugId" value="">
            
            <p class="text-sm text-gray-600 mb-4">Drug: <span id="editDrugName" class="font-medium text-gray-800"></span></p>
            <p class="text-sm text-gray-600 mb-4">Current Qty: <span id="editCurrentQty" class="font-medium text-gray-800"></span></p>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Adjustment (+/-)</label>
                <input type="number" name="adjustment" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <p class="text-xs text-gray-500 mt-1">Use negative numbers to reduce stock</p>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                <select name="reason" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="Stock adjustment">Stock adjustment</option>
                    <option value="Damaged">Damaged</option>
                    <option value="Expired">Expired</option>
                    <option value="Returns">Returns</option>
                    <option value="Received stock">Received stock</option>
                    <option value="Other">Other</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeStockModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Update Stock
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Ensure DOM is loaded before attaching event handlers
    document.addEventListener('DOMContentLoaded', function() {
        // Modal functions are now properly scoped
    });
    
    function showAddDrugModal() {
        const modal = document.getElementById('addDrugModal');
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    function closeAddDrugModal() {
        const modal = document.getElementById('addDrugModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    function showEditStock(id, name, qty) {
        const modal = document.getElementById('stockModal');
        const drugIdInput = document.getElementById('editDrugId');
        const drugNameSpan = document.getElementById('editDrugName');
        const currentQtySpan = document.getElementById('editCurrentQty');
        
        if (modal && drugIdInput && drugNameSpan && currentQtySpan) {
            drugIdInput.value = id;
            drugNameSpan.textContent = name;
            currentQtySpan.textContent = qty;
            modal.classList.remove('hidden');
        }
    }
    
    function closeStockModal() {
        const modal = document.getElementById('stockModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    }
    
    // Close modal when clicking outside
    document.addEventListener('click', function(event) {
        const stockModal = document.getElementById('stockModal');
        const addDrugModal = document.getElementById('addDrugModal');
        
        // Close stock modal when clicking on overlay
        if (stockModal && !stockModal.classList.contains('hidden')) {
            if (event.target === stockModal) {
                closeStockModal();
            }
        }
        
        // Close add drug modal when clicking on overlay
        if (addDrugModal && !addDrugModal.classList.contains('hidden')) {
            if (event.target === addDrugModal) {
                closeAddDrugModal();
            }
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeStockModal();
            closeAddDrugModal();
        }
    });
</script>

<?php
require_once '../includes/footer.php';
?>
