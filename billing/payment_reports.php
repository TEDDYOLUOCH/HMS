<?php
/**
 * Hospital Management System - Payment Reports
 * View payment history and statistics
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';
requireRole(['admin', 'doctor'], '../dashboard');

$page_title = 'Payment Reports';

// Include database
require_once '../config/database.php';

// Get date filter
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Get payment data
$payments = [];
$summary = ['total' => 0, 'cash' => 0, 'mpesa' => 0, 'card' => 0, 'insurance' => 0, 'bank' => 0];

try {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("SELECT p.*, i.invoice_number, i.patient_name, u.username as recorded_by_name 
                          FROM payments p 
                          JOIN invoices i ON p.invoice_id = i.id
                          LEFT JOIN users u ON p.recorded_by = u.id
                          WHERE DATE(p.payment_date) BETWEEN ? AND ?
                          ORDER BY p.payment_date DESC");
    $stmt->execute([$start_date, $end_date]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Summary
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total,
                          SUM(CASE WHEN payment_method = 'cash' THEN amount_paid ELSE 0 END) as cash,
                          SUM(CASE WHEN payment_method = 'mpesa' THEN amount_paid ELSE 0 END) as mpesa,
                          SUM(CASE WHEN payment_method = 'card' THEN amount_paid ELSE 0 END) as card,
                          SUM(CASE WHEN payment_method = 'insurance' THEN amount_paid ELSE 0 END) as insurance,
                          SUM(CASE WHEN payment_method = 'bank' THEN amount_paid ELSE 0 END) as bank
                          FROM payments 
                          WHERE DATE(payment_date) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-line text-blue-600 mr-2"></i> Payment Reports
                </h1>
                <p class="text-gray-500 mt-1">View payment history and statistics</p>
            </div>
            
            <!-- Date Filter -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 mb-6">
                <form method="GET" class="flex flex-wrap items-end gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-search mr-2"></i> Filter
                    </button>
                    <a href="payment_reports" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                        Reset
                    </a>
                </form>
            </div>
            
            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-sm text-gray-500">Total Collected</p>
                    <p class="text-2xl font-bold text-green-600">KSh <?php echo number_format($summary['total'], 0); ?></p>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-sm text-gray-500">Cash</p>
                    <p class="text-xl font-bold text-gray-900">KSh <?php echo number_format($summary['cash'], 0); ?></p>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-sm text-gray-500">M-Pesa</p>
                    <p class="text-xl font-bold text-gray-900">KSh <?php echo number_format($summary['mpesa'], 0); ?></p>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-sm text-gray-500">Card</p>
                    <p class="text-xl font-bold text-gray-900">KSh <?php echo number_format($summary['card'], 0); ?></p>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-sm text-gray-500">Insurance</p>
                    <p class="text-xl font-bold text-gray-900">KSh <?php echo number_format($summary['insurance'], 0); ?></p>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                    <p class="text-sm text-gray-500">Bank Transfer</p>
                    <p class="text-xl font-bold text-gray-900">KSh <?php echo number_format($summary['bank'], 0); ?></p>
                </div>
            </div>
            
            <!-- Payments Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">
                        Payment History (<?php echo count($payments); ?> transactions)
                    </h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Invoice #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Patient</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No payments found for selected period.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600"><?php echo date('M j, Y g:i A', strtotime($payment['payment_date'])); ?></td>
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($payment['invoice_number']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($payment['patient_name']); ?></td>
                                <td class="px-4 py-3 text-green-600 font-bold">KSh <?php echo number_format($payment['amount_paid'], 0); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs bg-gray-100">
                                        <?php echo ucfirst($payment['payment_method']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                <td class="px-4 py-3 text-gray-500"><?php echo htmlspecialchars($payment['recorded_by_name'] ?? 'System'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
