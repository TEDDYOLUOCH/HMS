<?php
/**
 * Hospital Management System - Walk-in Pharmacy Sales
 * Sell drugs to customers without prescription
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'pharmacist'], '../dashboard');

// Set page title
$page_title = 'Walk-in Sales';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

// Generate CSRF token
$csrf_token = csrfToken();

// Handle form submissions
$message = '';
$message_type = '';
$sale_complete = false;
$last_sale = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $db = Database::getInstance();
            
            if ($action === 'sell') {
                $drug_id = $_POST['drug_id'];
                $quantity = $_POST['quantity'];
                
                // Get drug details
                $stmt = $db->prepare("SELECT * FROM drug_stock WHERE id = ?");
                $stmt->execute([$drug_id]);
                $drug = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$drug) {
                    $message = 'Drug not found.';
                    $message_type = 'danger';
                } elseif ($drug['quantity'] < $quantity) {
                    $message = 'Insufficient stock! Available: ' . $drug['quantity'];
                    $message_type = 'danger';
                } else {
                    $unit_price = $drug['unit_price'];
                    $total_price = $unit_price * $quantity;
                    
                    // Record sale
                    $stmt = $db->prepare("INSERT INTO pharmacy_sales 
                        (customer_name, customer_phone, drug_name, drug_id, quantity, unit_price, total_price, sold_by, payment_method, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['customer_name'] ?: null,
                        $_POST['customer_phone'] ?: null,
                        $drug['drug_name'],
                        $drug_id,
                        $quantity,
                        $unit_price,
                        $total_price,
                        $_SESSION['user_id'],
                        $_POST['payment_method'] ?: 'Cash',
                        $_POST['notes'] ?: null
                    ]);
                    
                    $sale_id = $db->lastInsertId();
                    
                    // Update stock
                    $stmt = $db->prepare("UPDATE drug_stock SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$quantity, $drug_id]);
                    
                    // Log stock movement
                    $stmt = $db->prepare("INSERT INTO pharmacy_stock_log (drug_id, adjustment, reason, adjusted_by, created_at) VALUES (?, ?, ?, ?, NOW())");
                    $stmt->execute([$drug_id, -$quantity, "Walk-in sale #$sale_id", $_SESSION['user_id']]);
                    
                    // Log activity
                    logActivity('Sold', 'Pharmacy', 'pharmacy_sales', $sale_id, "Walk-in sale: $quantity x $drug[drug_name] for KSh $total_price");
                    
                    $last_sale = [
                        'id' => $sale_id,
                        'drug_name' => $drug['drug_name'],
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $total_price,
                        'customer_name' => $_POST['customer_name'] ?: 'Cash Customer'
                    ];
                    
                    $sale_complete = true;
                    $message = 'Sale completed successfully!';
                    $message_type = 'success';
                }
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get available drugs for dropdown
$drugs = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM drug_stock WHERE quantity > 0 ORDER BY drug_name");
    $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get recent sales
$recent_sales = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT s.*, u.username as sold_by_name 
                        FROM pharmacy_sales s 
                        LEFT JOIN users u ON s.sold_by = u.id
                        ORDER BY s.sale_date DESC LIMIT 20");
    $recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Today's stats
$today_sales = 0;
$today_total = 0;
try {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    $stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(total_price), 0) as total 
                        FROM pharmacy_sales 
                        WHERE DATE(sale_date) = '$today'");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_sales = $stats['count'];
    $today_total = $stats['total'];
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
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-shopping-cart text-purple-600 mr-2"></i> Walk-in Sales
                    </h1>
                    <p class="text-gray-500 mt-1">Sell drugs to customers without prescription</p>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($sale_complete && $last_sale): ?>
            <!-- Invoice -->
            <div class="bg-white rounded-xl border-2 border-brand-500 shadow-lg mb-6 overflow-hidden">
                <div class="bg-green-500 text-white p-4 text-center">
                    <i class="fas fa-check-circle text-4xl mb-2"></i>
                    <h2 class="text-xl font-bold">Sale Completed Successfully!</h2>
                </div>
                <div class="p-6">
                    <div class="text-center mb-6">
                        <h3 class="text-lg font-bold text-gray-800">SIWOT HOSPITAL PHARMACY</h3>
                        <p class="text-sm text-gray-500">Walk-in Sale Invoice</p>
                    </div>
                    
                    <div class="border-t border-b border-gray-200 py-4 mb-4">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Invoice No:</span>
                            <span class="font-medium"><?php echo $last_sale['id']; ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Customer:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($last_sale['customer_name']); ?></span>
                        </div>
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-medium"><?php echo date('M j, Y g:i A'); ?></span>
                        </div>
                    </div>
                    
                    <table class="w-full mb-4">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2">Drug</th>
                                <th class="text-right py-2">Qty</th>
                                <th class="text-right py-2">Price</th>
                                <th class="text-right py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="py-2"><?php echo htmlspecialchars($last_sale['drug_name']); ?></td>
                                <td class="text-right py-2"><?php echo $last_sale['quantity']; ?></td>
                                <td class="text-right py-2">KSh <?php echo number_format($last_sale['unit_price'], 2); ?></td>
                                <td class="text-right py-2 font-bold">KSh <?php echo number_format($last_sale['total_price'], 2); ?></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-gray-800">
                                <td colspan="3" class="text-right py-2 font-bold">TOTAL</td>
                                <td class="text-right py-2 font-bold text-lg">KSh <?php echo number_format($last_sale['total_price'], 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="text-center text-sm text-gray-500">
                        <p>Thank you for choosing SIWOT Hospital!</p>
                        <p>Prescriptions available upon request</p>
                    </div>
                    
                    <div class="mt-6 flex justify-center gap-3">
                        <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                        <button onclick="location.reload()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            <i class="fas fa-plus mr-2"></i> New Sale
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Today's Sales</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $today_sales; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-shopping-bag text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Today's Revenue</p>
                            <p class="text-2xl font-bold text-brand-600">KSh <?php echo number_format($today_total, 0); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-money-bill-wave text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Available Drugs</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($drugs); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="fas fa-pills text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- New Sale Form -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                    <div class="p-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">New Sale</h2>
                    </div>
                    <form method="POST" class="p-4 space-y-4">
                        <input type="hidden" name="action" value="sell">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                                <input type="text" name="customer_name" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Optional">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                                <input type="text" name="customer_phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Optional">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Drug *</label>
                            <select name="drug_id" id="drugSelect" required onchange="updateDrugInfo()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="">Select Drug</option>
                                <?php foreach ($drugs as $drug): ?>
                                <option value="<?php echo $drug['id']; ?>" 
                                        data-qty="<?php echo $drug['quantity']; ?>" 
                                        data-price="<?php echo $drug['unit_price']; ?>"
                                        data-unit="<?php echo htmlspecialchars($drug['unit'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($drug['drug_name']); ?> 
                                    (Stock: <?php echo $drug['quantity']; ?> | KSh <?php echo number_format($drug['unit_price'], 2); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Available Stock</label>
                                <p id="availableStock" class="text-gray-600 font-medium">-</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Unit Price</label>
                                <p id="unitPrice" class="text-gray-600 font-medium">-</p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <input type="number" name="quantity" id="quantity" required min="1" oninput="calculateTotal()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Total Price</label>
                            <p id="totalPrice" class="text-2xl font-bold text-brand-600">KSh 0.00</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="Cash">Cash</option>
                                <option value="M-Pesa">M-Pesa</option>
                                <option value="Card">Card</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Any special instructions..."></textarea>
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-3 bg-brand-600 text-white rounded-lg hover:bg-brand-700 font-medium">
                            <i class="fas fa-check-circle mr-2"></i> Complete Sale
                        </button>
                    </form>
                </div>
                
                <!-- Recent Sales -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                    <div class="p-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Sales</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sale ID</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Drug</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Qty</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($recent_sales)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No sales yet today.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recent_sales as $sale): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium">#<?php echo $sale['id']; ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($sale['drug_name']); ?></td>
                                    <td class="px-4 py-3"><?php echo $sale['quantity']; ?></td>
                                    <td class="px-4 py-3 text-brand-600 font-medium">KSh <?php echo number_format($sale['total_price'], 0); ?></td>
                                    <td class="px-4 py-3 text-gray-500 text-sm"><?php echo date('g:i A', strtotime($sale['sale_date'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    function updateDrugInfo() {
        const select = document.getElementById('drugSelect');
        const option = select.options[select.selectedIndex];
        const qty = option.getAttribute('data-qty');
        const price = option.getAttribute('data-price');
        const unit = option.getAttribute('data-unit') || '';
        
        if (qty) {
            document.getElementById('availableStock').textContent = qty + ' ' + (unit || 'units');
            document.getElementById('unitPrice').textContent = 'KSh ' + parseFloat(price).toFixed(2);
            document.getElementById('quantity').max = qty;
            calculateTotal();
        } else {
            document.getElementById('availableStock').textContent = '-';
            document.getElementById('unitPrice').textContent = '-';
        }
    }
    
    function calculateTotal() {
        const select = document.getElementById('drugSelect');
        const option = select.options[select.selectedIndex];
        const price = parseFloat(option.getAttribute('data-price')) || 0;
        const qty = parseInt(document.getElementById('quantity').value) || 0;
        const total = price * qty;
        
        document.getElementById('totalPrice').textContent = 'KSh ' + total.toFixed(2);
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
