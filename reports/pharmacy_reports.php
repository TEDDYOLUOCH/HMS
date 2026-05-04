<?php
/**
 * Hospital Management System - Pharmacy Reports
 * Drug consumption, stock status, and expiry analysis
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'pharmacist', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Pharmacy Reports';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Sanitize date inputs
$start_date = isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date'], ENT_QUOTES, 'UTF-8') : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date'], ENT_QUOTES, 'UTF-8') : date('Y-m-d');

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
// Disable animations globally for better performance
Chart.defaults.animation = false;
Chart.defaults.responsive = true;
</script>

<?php
// Initialize all variables
$stats = [
    'total_prescriptions' => 0,
    'total_items_dispensed' => 0,
    'total_value' => 0,
    'unique_patients' => 0,
    'out_of_stock' => 0,
    'low_stock' => 0,
    'near_expiry' => 0
];

$consumption = [];
$stock_status = [];
$category_breakdown = [];
$daily_trend = []; // Initialize this variable
$error_message = '';

try {
    $db = Database::getInstance();
    
    // Dispensing stats - Fixed: prescriptions table logic
    $stmt = $db->prepare("SELECT COUNT(DISTINCT p.id) as prescriptions, 
                         COUNT(p.id) as items, 
                         COALESCE(SUM(p.quantity_prescribed), 0) as total_qty,
                         COUNT(DISTINCT p.patient_id) as patients
                         FROM prescriptions p
                         WHERE DATE(p.prescribed_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['total_prescriptions'] = $result['prescriptions'] ?? 0;
    $stats['total_items_dispensed'] = $result['items'] ?? 0;
    $stats['unique_patients'] = $result['patients'] ?? 0;
    
    // Stock status
    $stmt = $db->query("SELECT 
                        SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock,
                        SUM(CASE WHEN stock_quantity > 0 AND stock_quantity <= reorder_level THEN 1 ELSE 0 END) as low_stock,
                        SUM(CASE WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH) THEN 1 ELSE 0 END) as near_expiry
                        FROM drugs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['out_of_stock'] = $result['out_of_stock'] ?? 0;
    $stats['low_stock'] = $result['low_stock'] ?? 0;
    $stats['near_expiry'] = $result['near_expiry'] ?? 0;
    
    // Top drugs consumed
    $stmt = $db->prepare("SELECT d.brand_name as drug_name, 
                         SUM(p.quantity_prescribed) as total_qty,
                         COUNT(DISTINCT p.patient_id) as patient_count,
                         COUNT(*) as times_prescribed
                         FROM prescriptions p
                         JOIN drugs d ON p.drug_id = d.id
                         WHERE DATE(p.prescribed_at) BETWEEN ? AND ?
                         GROUP BY d.id, d.brand_name
                         ORDER BY total_qty DESC
                         LIMIT 15");
    $stmt->execute([$start_date, $end_date]);
    $consumption = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stock status details
    $stmt = $db->query("SELECT brand_name as drug_name, stock_quantity as quantity, reorder_level, expiry_date, unit_price,
                       CASE 
                           WHEN stock_quantity <= 0 THEN 'out_of_stock'
                           WHEN stock_quantity <= reorder_level THEN 'low_stock'
                           WHEN expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH) THEN 'near_expiry'
                           ELSE 'ok'
                       END as status
                       FROM drugs
                       WHERE stock_quantity <= reorder_level OR (expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 3 MONTH))
                       ORDER BY status, stock_quantity ASC");
    $stock_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Category breakdown
    $stmt = $db->prepare("SELECT dc.category_name as category, 
                         SUM(p.quantity_prescribed) as total_qty,
                         COUNT(*) as items
                         FROM prescriptions p
                         JOIN drugs d ON p.drug_id = d.id
                         LEFT JOIN drug_categories dc ON d.category_id = dc.id
                         WHERE DATE(p.prescribed_at) BETWEEN ? AND ?
                         GROUP BY dc.id, dc.category_name
                         ORDER BY total_qty DESC");
    $stmt->execute([$start_date, $end_date]);
    $category_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily dispensing trend
    $stmt = $db->prepare("SELECT DATE(p.prescribed_at) as date, 
                         COUNT(DISTINCT p.id) as prescriptions,
                         COALESCE(SUM(p.quantity_prescribed), 0) as items
                         FROM prescriptions p
                         WHERE DATE(p.prescribed_at) BETWEEN ? AND ?
                         GROUP BY DATE(p.prescribed_at)
                         ORDER BY date");
    $stmt->execute([$start_date, $end_date]);
    $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading report data: " . htmlspecialchars($e->getMessage());
    // Log error for debugging
    error_log("Pharmacy Report Error: " . $e->getMessage());
}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Error Message -->
    <?php if ($error_message): ?>
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error_message; ?>
    </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-pills text-orange-600 mr-2"></i> Pharmacy Reports
                </h1>
                <p class="text-gray-500 mt-1">Drug consumption, stock status, and expiry analysis</p>
            </div>
            <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 print:hidden">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-calendar text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">Date Range:</span>
            </div>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <span class="text-gray-500">to</span>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                Generate Report
            </button>
        </form>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-4 mb-6">
        <!-- ... cards remain the same ... -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Prescriptions</span>
                <i class="fas fa-prescription text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_prescriptions']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Items Dispensed</span>
                <i class="fas fa-pills text-green-500"></i>
            </div>
            <p class="text-2xl font-bold text-green-600"><?php echo number_format($stats['total_items_dispensed']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Patients Served</span>
                <i class="fas fa-users text-purple-500"></i>
            </div>
            <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['unique_patients']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Out of Stock</span>
                <i class="fas fa-times-circle text-red-500"></i>
            </div>
            <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['out_of_stock']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Low Stock</span>
                <i class="fas fa-exclamation-triangle text-yellow-500"></i>
            </div>
            <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats['low_stock']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Near Expiry</span>
                <i class="fas fa-calendar-times text-orange-500"></i>
            </div>
            <p class="text-2xl font-bold text-orange-600"><?php echo number_format($stats['near_expiry']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Stock Issues</span>
                <i class="fas fa-warehouse text-indigo-500"></i>
            </div>
            <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($stats['out_of_stock'] + $stats['low_stock'] + $stats['near_expiry']); ?></p>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Category Breakdown -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-gray-400 mr-2"></i> Consumption by Category
            </h3>
            <div class="relative h-64">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        
        <!-- Daily Trend -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-gray-400 mr-2"></i> Daily Dispensing Trend
            </h3>
            <div class="relative h-64">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Drugs Consumed -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-arrow-up text-gray-400 mr-2"></i> Top 15 Drugs Consumed
            </h3>
        </div>
        
        <?php if (empty($consumption)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-pills text-4xl mb-3 text-gray-300"></i>
            <p>No dispensing data available for this period</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Drug Name</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Total Qty</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Times Prescribed</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patients</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($consumption as $drug): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4 font-medium text-gray-800"><?php echo htmlspecialchars($drug['drug_name']); ?></td>
                        <td class="py-3 px-4 text-right text-green-600 font-medium"><?php echo number_format($drug['total_qty']); ?></td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo number_format($drug['times_prescribed']); ?></td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo number_format($drug['patient_count']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Stock Alerts -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i> Stock Alerts & Near Expiry
            </h3>
        </div>
        
        <?php if (empty($stock_status)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-check-circle text-4xl mb-3 text-green-300"></i>
            <p>All stock levels are healthy</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Drug Name</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Current Qty</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Reorder Level</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Expiry Date</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($stock_status as $stock): 
                        $status_labels = [
                            'out_of_stock' => ['label' => 'Out of Stock', 'color' => 'bg-red-100 text-red-700 border border-red-200'],
                            'low_stock' => ['label' => 'Low Stock', 'color' => 'bg-yellow-100 text-yellow-700 border border-yellow-200'],
                            'near_expiry' => ['label' => 'Near Expiry', 'color' => 'bg-orange-100 text-orange-700 border border-orange-200']
                        ];
                        $status = $status_labels[$stock['status']] ?? ['label' => 'Unknown', 'color' => 'bg-gray-100 text-gray-700'];
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="py-3 px-4 font-medium text-gray-800"><?php echo htmlspecialchars($stock['drug_name']); ?></td>
                        <td class="py-3 px-4 text-right <?php echo $stock['quantity'] <= 0 ? 'text-red-600 font-bold' : 'text-gray-800'; ?>">
                            <?php echo number_format($stock['quantity']); ?>
                        </td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo number_format($stock['reorder_level']); ?></td>
                        <td class="py-3 px-4 text-gray-600">
                            <?php echo $stock['expiry_date'] ? date('M Y', strtotime($stock['expiry_date'])) : '-'; ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status['color']; ?>">
                                <?php echo $status['label']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category pie chart
    const categoryData = <?php echo json_encode($category_breakdown); ?> || [];
    const categoryCtx = document.getElementById('categoryChart');
    
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.length ? categoryData.map(c => c.category || 'Uncategorized') : ['No Data'],
                datasets: [{
                    data: categoryData.length ? categoryData.map(c => parseInt(c.total_qty) || 0) : [1],
                    backgroundColor: categoryData.length ? [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)',
                        'rgba(16, 185, 129, 0.8)',
                        'rgba(59, 130, 246, 0.8)',
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(236, 72, 153, 0.8)',
                        'rgba(107, 114, 128, 0.8)'
                    ] : ['rgba(209, 213, 219, 0.5)'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return label + ': ' + value.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Daily trend line chart
    const trendData = <?php echo json_encode($daily_trend); ?> || [];
    const trendCtx = document.getElementById('trendChart');
    
    if (trendCtx && trendData.length > 0) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(d => {
                    const date = new Date(d.date);
                    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                }),
                datasets: [{
                    label: 'Prescriptions',
                    data: trendData.map(d => parseInt(d.prescriptions) || 0),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }, {
                    label: 'Items',
                    data: trendData.map(d => parseInt(d.items) || 0),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { usePointStyle: true }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                return 'Date: ' + context[0].label;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 8 }
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        title: { display: true, text: 'Prescriptions' },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: true,
                        title: { display: true, text: 'Items' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    } else if (trendCtx) {
        // Show "No Data" message
        trendCtx.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400"><i class="fas fa-chart-line mr-2"></i> No trend data available</div>';
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>