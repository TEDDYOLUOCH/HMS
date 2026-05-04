<?php
/**
 * Hospital Management System - Payments
 * Record patient payments
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';
requireRole(['admin', 'doctor', 'nurse', 'pharmacist'], '../dashboard');

$page_title = 'Payments';

// Include database
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

$csrf_token = csrfToken();

$message = '';
$message_type = '';
$payment_complete = false;
$last_payment = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $db = Database::getInstance();
            
            if ($action === 'payment') {
                $invoice_id = $_POST['invoice_id'];
                $amount = $_POST['amount'];
                
                $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
                $stmt->execute([$invoice_id]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$invoice) {
                    $message = 'Invoice not found.';
                    $message_type = 'danger';
                } else {
                    $balance = $invoice['total_amount'] - $invoice['amount_paid'];
                    
                    if ($amount > $balance) {
                        $message = 'Amount exceeds balance of KSh ' . number_format($balance);
                        $message_type = 'danger';
                    } else {
                        $db->beginTransaction();
                        
                        // Record payment
                        $stmt = $db->prepare("INSERT INTO payments (invoice_id, amount_paid, payment_method, reference_number, paid_by, notes, recorded_by) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $invoice_id,
                            $amount,
                            $_POST['payment_method'],
                            $_POST['reference_number'] ?? null,
                            $_POST['paid_by'] ?? null,
                            $_POST['notes'] ?? null,
                            $_SESSION['user_id']
                        ]);
                        
                        $payment_id = $db->lastInsertId();
                        
                        // Update invoice
                        $new_paid = $invoice['amount_paid'] + $amount;
                        $new_status = 'paid';
                        if ($new_paid < $invoice['total_amount']) {
                            $new_status = ($new_paid > 0) ? 'partial' : 'pending';
                        }
                        
                        $stmt = $db->prepare("UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?");
                        $stmt->execute([$new_paid, $new_status, $invoice_id]);
                        
                        $db->commit();
                        
                        logActivity('Payment', 'Billing', 'invoices', $invoice_id, "Payment of KSh $amount recorded for invoice {$invoice['invoice_number']}");
                        
                        $last_payment = [
                            'invoice_number' => $invoice['invoice_number'],
                            'patient_name' => $invoice['patient_name'],
                            'amount' => $amount,
                            'method' => $_POST['payment_method'],
                            'reference' => $_POST['reference_number'] ?? 'N/A'
                        ];
                        
                        $payment_complete = true;
                        $message = 'Payment recorded successfully!';
                        $message_type = 'success';
                    }
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

// Get invoices for payment
$pending_invoices = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM invoices WHERE status IN ('pending', 'partial') ORDER BY created_at DESC");
    $pending_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get selected invoice
$selected_invoice = null;
$invoice_id = $_GET['invoice_id'] ?? ($_POST['invoice_id'] ?? 0);
if ($invoice_id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $selected_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Get recent payments
$recent_payments = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT p.*, i.invoice_number, i.patient_name, u.username as recorded_by_name 
                        FROM payments p 
                        JOIN invoices i ON p.invoice_id = i.id
                        LEFT JOIN users u ON p.recorded_by = u.id
                        ORDER BY p.payment_date DESC LIMIT 20");
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            <div class="mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">
                        <i class="fas fa-money-bill-wave text-brand-600 mr-2"></i> Payments
                    </h1>
                    <p class="text-gray-500 mt-1">Record patient payments</p>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($payment_complete && $last_payment): ?>
            <!-- Receipt -->
            <div class="bg-white rounded-xl border-2 border-brand-500 shadow-lg mb-6 overflow-hidden">
                <div class="bg-green-500 text-white p-4 text-center">
                    <i class="fas fa-check-circle text-4xl mb-2"></i>
                    <h2 class="text-xl font-bold">Payment Received!</h2>
                </div>
                <div class="p-6 text-center">
                    <div class="mb-4">
                        <p class="text-4xl font-bold text-brand-600">KSh <?php echo number_format($last_payment['amount'], 0); ?></p>
                    </div>
                    <div class="text-left max-w-md mx-auto space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Invoice #:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($last_payment['invoice_number']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Patient:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($last_payment['patient_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Method:</span>
                            <span class="font-medium"><?php echo ucfirst($last_payment['method']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Reference:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($last_payment['reference']); ?></span>
                        </div>
                    </div>
                    <div class="mt-6">
                        <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 mr-2">
                            <i class="fas fa-print mr-2"></i> Print
                        </button>
                        <button onclick="location.reload()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            <i class="fas fa-plus mr-2"></i> New Payment
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Record Payment Form -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                    <div class="p-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Record Payment</h2>
                    </div>
                    <form method="POST" class="p-4 space-y-4">
                        <input type="hidden" name="action" value="payment">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Select Invoice *</label>
                            <select name="invoice_id" id="invoiceSelect" required onchange="updateInvoiceInfo()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="">Select Invoice</option>
                                <?php foreach ($pending_invoices as $inv): ?>
                                <?php $balance = $inv['total_amount'] - $inv['amount_paid']; ?>
                                <option value="<?php echo $inv['id']; ?>" 
                                        data-number="<?php echo htmlspecialchars($inv['invoice_number']); ?>"
                                        data-patient="<?php echo htmlspecialchars($inv['patient_name']); ?>"
                                        data-total="<?php echo $inv['total_amount']; ?>"
                                        data-paid="<?php echo $inv['amount_paid']; ?>"
                                        data-balance="<?php echo $balance; ?>"
                                        <?php echo $invoice_id == $inv['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($inv['invoice_number']); ?> - <?php echo htmlspecialchars($inv['patient_name']); ?> (Balance: KSh <?php echo number_format($balance); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="invoiceInfo" class="bg-gray-50 p-3 rounded-lg <?php echo !$selected_invoice ? 'hidden' : ''; ?>">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>Total:</div>
                                <div class="font-medium" id="infoTotal">KSh 0</div>
                                <div>Paid:</div>
                                <div class="text-brand-600" id="infoPaid">KSh 0</div>
                                <div>Balance:</div>
                                <div class="font-bold text-red-600" id="infoBalance">KSh 0</div>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Amount *</label>
                            <input type="number" name="amount" id="paymentAmount" required min="1" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                            <select name="payment_method" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="card">Card</option>
                                <option value="insurance">Insurance</option>
                                <option value="bank">Bank Transfer</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reference Number</label>
                            <input type="text" name="reference_number" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="M-Pesa code, Transaction ID, etc.">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paid By</label>
                            <input type="text" name="paid_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500" placeholder="Payer name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" rows="2" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                        </div>
                        
                        <button type="submit" class="w-full px-4 py-3 bg-brand-600 text-white rounded-lg hover:bg-brand-700 font-medium">
                            <i class="fas fa-check-circle mr-2"></i> Record Payment
                        </button>
                    </form>
                </div>
                
                <!-- Recent Payments -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                    <div class="p-4 border-b border-gray-100">
                        <h2 class="text-lg font-semibold text-gray-800">Recent Payments</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">No payments recorded yet.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($recent_payments as $payment): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                                    <td class="px-4 py-3"><?php echo htmlspecialchars($payment['patient_name']); ?></td>
                                    <td class="px-4 py-3 text-brand-600 font-medium">KSh <?php echo number_format($payment['amount_paid'], 0); ?></td>
                                    <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs bg-gray-100"><?php echo ucfirst($payment['payment_method']); ?></span></td>
                                    <td class="px-4 py-3 text-gray-500 text-sm"><?php echo date('M j, g:i A', strtotime($payment['payment_date'])); ?></td>
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
    function updateInvoiceInfo() {
        const select = document.getElementById('invoiceSelect');
        const option = select.options[select.selectedIndex];
        const balance = parseFloat(option.getAttribute('data-balance')) || 0;
        
        if (balance > 0) {
            document.getElementById('invoiceInfo').classList.remove('hidden');
            document.getElementById('infoTotal').textContent = 'KSh ' + parseFloat(option.getAttribute('data-total')).toLocaleString();
            document.getElementById('infoPaid').textContent = 'KSh ' + parseFloat(option.getAttribute('data-paid')).toLocaleString();
            document.getElementById('infoBalance').textContent = 'KSh ' + balance.toLocaleString();
            document.getElementById('paymentAmount').value = balance;
            document.getElementById('paymentAmount').max = balance;
        } else {
            document.getElementById('invoiceInfo').classList.add('hidden');
            document.getElementById('paymentAmount').value = '';
        }
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateInvoiceInfo();
    });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
