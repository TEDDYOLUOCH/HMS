<?php
/**
 * Hospital Management System - Billing & Invoices
 * Generate and manage patient invoices
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'nurse', 'lab_technologist', 'lab_scientist', 'pharmacist'], '../dashboard');

// Set page title
$page_title = 'Invoices';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

// Generate CSRF token
$csrf_token = csrfToken();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request. Please try again.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $db = Database::getInstance();
            
            if ($action === 'create') {
                $patient_id = $_POST['patient_id'];
                $patient_name = $_POST['patient_name'];
                $items = $_POST['items'] ?? [];
                
                if (empty($items)) {
                    $message = 'Please add at least one item.';
                    $message_type = 'danger';
                } else {
                    $db->beginTransaction();
                    
                    // Calculate total
                    $total = 0;
                    foreach ($items as $item) {
                        $total += $item['amount'];
                    }
                    
                    // Generate invoice number
                    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                    
                    // Create invoice
                    $stmt = $db->prepare("INSERT INTO invoices (invoice_number, patient_id, patient_name, total_amount, status, notes, created_by) 
                                          VALUES (?, ?, ?, ?, 'pending', ?, ?)");
                    $stmt->execute([$invoice_number, $patient_id, $patient_name, $total, $_POST['notes'] ?? null, $_SESSION['user_id']]);
                    
                    $invoice_id = $db->lastInsertId();
                    
                    // Add invoice items
                    $stmt = $db->prepare("INSERT INTO invoice_items (invoice_id, service_name, service_type, quantity, unit_price, amount) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    foreach ($items as $item) {
                        $stmt->execute([
                            $invoice_id,
                            $item['name'],
                            $item['type'] ?? 'other',
                            $item['quantity'] ?? 1,
                            $item['price'],
                            $item['amount']
                        ]);
                    }
                    
                    $db->commit();
                    
                    logActivity('Created', 'Billing', 'invoices', $invoice_id, "Invoice $invoice_number created for $patient_name - Total: KSh $total");
                    
                    $message = 'Invoice created successfully!';
                    $message_type = 'success';
                }
            }
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get invoices
$invoices = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT i.*, u.username as created_by_name 
                        FROM invoices i 
                        LEFT JOIN users u ON i.created_by = u.id
                        ORDER BY i.created_at DESC LIMIT 50");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get patients for dropdown
$patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, patient_id, first_name, last_name FROM patients ORDER BY first_name, last_name LIMIT 100");
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Today's stats
$today_invoices = 0;
$today_total = 0;
$today_collected = 0;
try {
    $db = Database::getInstance();
    $today = date('Y-m-d');
    $stmt = $db->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total, COALESCE(SUM(amount_paid), 0) as collected 
                        FROM invoices 
                        WHERE DATE(created_at) = '$today'");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $today_invoices = $stats['count'];
    $today_total = $stats['total'];
    $today_collected = $stats['collected'];
} catch (Exception $e) {}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SIWOT Hospital</title>
    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="with-sidebar">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-enter">
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-file-invoice text-brand-600 mr-2"></i> Invoices
                        </h1>
                        <p class="text-gray-500 mt-1">Generate and manage patient invoices</p>
                    </div>
                    <button onclick="openCreateModal()" class="inline-flex items-center px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-plus mr-2"></i> New Invoice
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Today's Invoices</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo $today_invoices; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-brand-100 flex items-center justify-center text-brand-600">
                            <i class="fas fa-file-invoice text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Invoiced</p>
                            <p class="text-2xl font-bold text-gray-900">KSh <?php echo number_format($today_total, 0); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="fas fa-money-bill text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Collected Today</p>
                            <p class="text-2xl font-bold text-green-600">KSh <?php echo number_format($today_collected, 0); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-600">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Pending</p>
                            <p class="text-2xl font-bold text-red-600">KSh <?php echo number_format($today_total - $today_collected, 0); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center text-red-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Invoices Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Recent Invoices</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Paid</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($invoices)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No invoices found.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($invoices as $inv): ?>
                            <?php 
                                $balance = $inv['total_amount'] - $inv['amount_paid'];
                                $status_class = $inv['status'] === 'paid' ? 'green' : ($inv['status'] === 'partial' ? 'yellow' : 'red');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($inv['invoice_number']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($inv['patient_name']); ?></td>
                                <td class="px-4 py-3">KSh <?php echo number_format($inv['total_amount'], 0); ?></td>
                                <td class="px-4 py-3 text-green-600">KSh <?php echo number_format($inv['amount_paid'], 0); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-<?php echo $status_class; ?>-100 text-<?php echo $status_class; ?>-700">
                                        <?php echo ucfirst($inv['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 text-sm"><?php echo date('M j, Y', strtotime($inv['created_at'])); ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" class="p-1.5 text-brand-600 hover:bg-brand-50 rounded-lg" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($inv['status'] !== 'paid'): ?>
                                        <a href="payments.php?invoice_id=<?php echo $inv['id']; ?>" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Record Payment">
                                            <i class="fas fa-money-bill"></i>
                                        </a>
                                        <?php endif; ?>
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
    
    <!-- Create Invoice Modal -->
    <div id="createModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeCreateModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-2xl w-full overflow-hidden flex flex-col max-h-[90vh]">
                <div class="modal-header-bg">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">Create Invoice</h2>
                        <button onclick="closeCreateModal()" class="modal-close-btn p-1 rounded-full">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" id="invoiceForm" class="p-4 space-y-4 overflow-y-auto flex-1">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Patient ID</label>
                            <select name="patient_id" id="patientSelect" onchange="updatePatientName()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="">Select Patient</option>
                                <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>">
                                    <?php echo htmlspecialchars($p['patient_id']); ?> - <?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Patient Name</label>
                            <input type="text" name="patient_name" id="patientName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Invoice Items</label>
                        <div id="itemsContainer" class="space-y-2">
                            <!-- Items will be added here -->
                        </div>
                        <button type="button" onclick="addItem()" class="mt-2 text-sm text-brand-600 hover:text-blue-700">
                            <i class="fas fa-plus mr-1"></i> Add Item
                        </button>
                    </div>
                    
                    <div class="bg-gray-50 p-3 rounded-lg">
                        <div class="flex justify-between text-lg font-bold">
                            <span>Total:</span>
                            <span id="totalAmount">KSh 0.00</span>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Create Invoice
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    let itemCount = 0;
    
    function openCreateModal() {
        document.getElementById('createModal').classList.remove('hidden');
        addItem(); // Add first item
    }
    
    function closeCreateModal() {
        document.getElementById('createModal').classList.add('hidden');
        document.getElementById('itemsContainer').innerHTML = '';
        document.getElementById('patientSelect').selectedIndex = 0;
        document.getElementById('patientName').value = '';
        itemCount = 0;
    }
    
    function updatePatientName() {
        const select = document.getElementById('patientSelect');
        const option = select.options[select.selectedIndex];
        const name = option.getAttribute('data-name');
        document.getElementById('patientName').value = name || '';
    }
    
    function addItem() {
        itemCount++;
        const container = document.getElementById('itemsContainer');
        const html = `
            <div class="grid grid-cols-12 gap-2 items-end bg-gray-50 p-2 rounded-lg item-row" id="item_${itemCount}">
                <div class="col-span-4">
                    <label class="text-xs text-gray-500">Service</label>
                    <input type="text" name="items[${itemCount}][name]" required class="w-full px-2 py-1 border border-gray-300 rounded text-sm" placeholder="Service name">
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-gray-500">Type</label>
                    <select name="items[${itemCount}][type]" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                        <option value="consultation">Consultation</option>
                        <option value="lab">Lab Test</option>
                        <option value="pharmacy">Pharmacy</option>
                        <option value="procedure">Procedure</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-gray-500">Qty</label>
                    <input type="number" name="items[${itemCount}][quantity]" value="1" min="1" onchange="calculateItem(${itemCount})" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                </div>
                <div class="col-span-2">
                    <label class="text-xs text-gray-500">Price</label>
                    <input type="number" name="items[${itemCount}][price]" value="0" min="0" onchange="calculateItem(${itemCount})" class="w-full px-2 py-1 border border-gray-300 rounded text-sm">
                </div>
                <div class="col-span-1">
                    <label class="text-xs text-gray-500">Total</label>
                    <input type="number" name="items[${itemCount}][amount]" value="0" readonly class="w-full px-2 py-1 border border-gray-300 rounded text-sm bg-gray-100">
                </div>
                <div class="col-span-1">
                    <button type="button" onclick="removeItem(${itemCount})" class="text-red-500 hover:text-red-700">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }
    
    function removeItem(id) {
        document.getElementById('item_' + id).remove();
        calculateTotal();
    }
    
    function calculateItem(id) {
        const row = document.getElementById('item_' + id);
        const qty = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
        const price = parseFloat(row.querySelector('[name$="[price]"]').value) || 0;
        const total = qty * price;
        row.querySelector('[name$="[amount]"]').value = total;
        calculateTotal();
    }
    
    function calculateTotal() {
        let total = 0;
        document.querySelectorAll('[name$="[amount]"]').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        document.getElementById('totalAmount').textContent = 'KSh ' + total.toFixed(2);
    }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
