<?php
/**
 * Hospital Management System - View Invoice
 * View and print invoice
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';
requireRole(['admin', 'doctor', 'nurse', 'lab_technologist', 'lab_scientist', 'pharmacist'], '../dashboard');

$page_title = 'Invoice';

// Include database
require_once '../config/database.php';

$invoice_id = $_GET['id'] ?? 0;

// Get invoice
$invoice = null;
$items = [];
$payments = [];

try {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("SELECT i.*, u.username as created_by_name 
                          FROM invoices i 
                          LEFT JOIN users u ON i.created_by = u.id
                          WHERE i.id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invoice) {
        $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$invoice_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("SELECT p.*, u.username as recorded_by_name 
                              FROM payments p 
                              LEFT JOIN users u ON p.recorded_by = u.id
                              WHERE p.invoice_id = ?
                              ORDER BY p.payment_date DESC");
        $stmt->execute([$invoice_id]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = 'Invoice not found';
}

$is_print = isset($_GET['print']);

if ($is_print && $invoice) {
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.4; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
        .header h1 { font-size: 24pt; margin-bottom: 5px; }
        .header p { font-size: 11pt; color: #666; }
        .invoice-info { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .invoice-info div { width: 48%; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #333; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .total-row { font-weight: bold; background: #f9f9f9; }
        .paid { color: green; }
        .pending { color: red; }
        .footer { margin-top: 30px; text-align: center; font-size: 10pt; color: #666; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>SIWOT HOSPITAL</h1>
        <p>P.O. Box 12345, Nairobi, Kenya | Tel: +254 700 000 000</p>
    </div>
    
    <div class="invoice-info">
        <div>
            <h3>Invoice To:</h3>
            <p><strong><?php echo htmlspecialchars($invoice['patient_name']); ?></strong></p>
            <?php if (!empty($invoice['notes'])): ?>
            <p><?php echo htmlspecialchars($invoice['notes']); ?></p>
            <?php endif; ?>
        </div>
        <div style="text-align: right;">
            <h3>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h3>
            <p>Date: <?php echo date('F j, Y', strtotime($invoice['created_at'])); ?></p>
            <p>Status: <?php echo ucfirst($invoice['status']); ?></p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Service</th>
                <th>Type</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td><?php echo htmlspecialchars($item['service_name']); ?></td>
                <td><?php echo ucfirst($item['service_type']); ?></td>
                <td><?php echo $item['quantity']; ?></td>
                <td>KSh <?php echo number_format($item['unit_price'], 2); ?></td>
                <td>KSh <?php echo number_format($item['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Total Amount:</td>
                <td><strong>KSh <?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">Amount Paid:</td>
                <td class="paid">KSh <?php echo number_format($invoice['amount_paid'], 2); ?></td>
            </tr>
            <tr>
                <td colspan="4" style="text-align: right;">Balance Due:</td>
                <td class="<?php echo $invoice['status'] === 'paid' ? 'paid' : 'pending'; ?>">
                    <strong>KSh <?php echo number_format($invoice['total_amount'] - $invoice['amount_paid'], 2); ?></strong>
                </td>
            </tr>
        </tbody>
    </table>
    
    <?php if (!empty($payments)): ?>
    <h3>Payment History</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></td>
                <td>KSh <?php echo number_format($payment['amount_paid'], 2); ?></td>
                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    
    <div class="footer">
        <p>Thank you for choosing SIWOT Hospital!</p>
        <p>Invoice generated on <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <script>window.print();</script>
</body>
</html>
<?php
    exit;
}

if (!$invoice) {
    die('Invoice not found');
}
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
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-file-invoice text-blue-600 mr-2"></i> Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                        </h1>
                        <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($invoice['patient_name']); ?></p>
                    </div>
                    <div class="flex gap-2">
                        <a href="view_invoice.php?id=<?php echo $invoice_id; ?>&print=1" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                            <i class="fas fa-print mr-2"></i> Print
                        </a>
                        <?php if ($invoice['status'] !== 'paid'): ?>
                        <a href="payments.php?invoice_id=<?php echo $invoice_id; ?>" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-money-bill mr-2"></i> Record Payment
                        </a>
                        <?php endif; ?>
                        <a href="invoices" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Invoice Details -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-100 bg-gray-50">
                            <h2 class="text-lg font-semibold text-gray-800">Invoice Details</h2>
                        </div>
                        
                        <div class="p-4">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Qty</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td class="px-4 py-3"><?php echo htmlspecialchars($item['service_name']); ?></td>
                                        <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-xs bg-gray-100"><?php echo ucfirst($item['service_type']); ?></span></td>
                                        <td class="px-4 py-3 text-right"><?php echo $item['quantity']; ?></td>
                                        <td class="px-4 py-3 text-right">KSh <?php echo number_format($item['unit_price'], 0); ?></td>
                                        <td class="px-4 py-3 text-right font-medium">KSh <?php echo number_format($item['amount'], 0); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-right font-bold">Total Amount:</td>
                                        <td class="px-4 py-3 text-right font-bold text-lg">KSh <?php echo number_format($invoice['total_amount'], 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-4 py-2 text-right text-green-600">Amount Paid:</td>
                                        <td class="px-4 py-2 text-right text-green-600 font-medium">KSh <?php echo number_format($invoice['amount_paid'], 0); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" class="px-4 py-2 text-right <?php echo $invoice['status'] === 'paid' ? 'text-green-600' : 'text-red-600'; ?> font-bold">
                                            Balance Due:
                                        </td>
                                        <td class="px-4 py-2 text-right <?php echo $invoice['status'] === 'paid' ? 'text-green-600' : 'text-red-600'; ?> font-bold">
                                            KSh <?php echo number_format($invoice['total_amount'] - $invoice['amount_paid'], 0); ?>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Summary -->
                <div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Status</h3>
                        <?php 
                            $status_class = $invoice['status'] === 'paid' ? 'green' : ($invoice['status'] === 'partial' ? 'yellow' : 'red');
                        ?>
                        <span class="px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $status_class; ?>-100 text-<?php echo $status_class; ?>-700">
                            <?php echo ucfirst($invoice['status']); ?>
                        </span>
                    </div>
                    
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Details</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-500">Invoice #:</span>
                                <span class="font-medium"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Date:</span>
                                <span><?php echo date('M j, Y', strtotime($invoice['created_at'])); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-500">Created By:</span>
                                <span><?php echo htmlspecialchars($invoice['created_by_name'] ?? 'System'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($payments)): ?>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                        <h3 class="font-semibold text-gray-800 mb-3">Payment History</h3>
                        <div class="space-y-3">
                            <?php foreach ($payments as $payment): ?>
                            <div class="border-b border-gray-100 pb-2">
                                <div class="flex justify-between font-medium">
                                    <span>KSh <?php echo number_format($payment['amount_paid'], 0); ?></span>
                                    <span class="text-green-600 text-sm"><?php echo ucfirst($payment['payment_method']); ?></span>
                                </div>
                                <div class="text-xs text-gray-500">
                                    <?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
