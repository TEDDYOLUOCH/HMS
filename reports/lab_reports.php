<?php
/**
 * Hospital Management System - Laboratory Reports
 * Comprehensive test volume, turnaround time, and priority analysis
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'lab_technologist', 'lab_scientist'], '../dashboard');

// Handle CSV export - MUST be before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $start_date = $_GET['start_date'] ?? '2025-01-01';
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    require_once '../config/database.php';
    
    try {
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT 
            lr.id as request_id,
            p.patient_id,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
            lt.test_name,
            lt.category,
            lr.priority,
            lr.status,
            lr.request_date,
            lr.completed_at,
            lr.specimen_collected,
            lr.clinical_notes,
            u.full_name as requested_by_name
            FROM lab_requests lr
            JOIN patients p ON lr.patient_id = p.id
            LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
            LEFT JOIN users u ON lr.requested_by = u.id
            WHERE DATE(lr.request_date) BETWEEN ? AND ?
            ORDER BY lr.request_date DESC");
        $stmt->execute([$start_date, $end_date]);
        $export_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="lab_reports_' . $start_date . '_to_' . $end_date . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Request ID', 'Patient ID', 'Patient Name', 'Test Name', 'Category', 'Priority', 'Status', 'Request Date', 'Completed Date', 'Specimen Collected', 'Clinical Notes', 'Requested By']);
        
        foreach ($export_data as $row) {
            fputcsv($output, [$row['request_id'], $row['patient_id'], $row['patient_name'], $row['test_name'], $row['category'], $row['priority'], $row['status'], $row['request_date'], $row['completed_at'], $row['specimen_collected'] ? 'Yes' : 'No', $row['clinical_notes'], $row['requested_by_name']]);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        // Log error
    }
}

// Set page title
$page_title = 'Laboratory Reports';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php

// Get date range - default to include all data
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '2025-01-01';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get statistics
$stats = [
    'total_requests' => 0,
    'completed' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'rejected' => 0,
    'abnormal' => 0,
    'critical' => 0,
    'avg_turnaround' => 0,
    'urgent_count' => 0,
    'stat_count' => 0
];

$test_type_stats = [];
$daily_stats = [];
$turnaround_by_type = [];
$priority_stats = [];
$category_stats = [];

try {
    $db = Database::getInstance();
    
    // Overall stats
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM lab_requests 
                          WHERE DATE(request_date) BETWEEN ? AND ? 
                          GROUP BY status");
    $stmt->execute([$start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
        $stats['total_requests'] += $row['count'];
    }
    
    // Priority stats
    $stmt = $db->prepare("SELECT priority, COUNT(*) as count FROM lab_requests 
                          WHERE DATE(request_date) BETWEEN ? AND ? 
                          GROUP BY priority");
    $stmt->execute([$start_date, $end_date]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $priority_stats[$row['priority']] = $row['count'];
        if (in_array($row['priority'], ['Urgent', 'STAT'])) {
            $stats['urgent_count'] += $row['count'];
        }
        if ($row['priority'] === 'STAT') {
            $stats['stat_count'] = $row['count'];
        }
    }
    
    // Abnormal/critical - use is_critical from lab_requests
    $stmt = $db->prepare("SELECT 
                          SUM(CASE WHEN is_critical = 1 THEN 1 ELSE 0 END) as critical,
                          COUNT(*) as total
                          FROM lab_requests 
                          WHERE DATE(request_date) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $result_flags = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['abnormal'] = $result_flags['total'] ?? 0;
    $stats['critical'] = $result_flags['critical'] ?? 0;
    
    // Average turnaround - use completed_at column
    $stmt = $db->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, request_date, completed_at)) as avg_hours 
                          FROM lab_requests 
                          WHERE DATE(request_date) BETWEEN ? AND ? 
                          AND status = 'Completed' 
                          AND completed_at IS NOT NULL");
    $stmt->execute([$start_date, $end_date]);
    $stats['avg_turnaround'] = round($stmt->fetch(PDO::FETCH_ASSOC)['avg_hours'] ?? 0, 1);
    
    // Test volume by type - JOIN with lab_test_types
    $stmt = $db->prepare("SELECT lt.test_name as test_type, lt.category, COUNT(lr.id) as count, 
                         SUM(CASE WHEN lr.status = 'Completed' THEN 1 ELSE 0 END) as completed,
                         SUM(CASE WHEN lr.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                         SUM(CASE WHEN lr.status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
                         FROM lab_requests lr
                         LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                         WHERE DATE(lr.request_date) BETWEEN ? AND ?
                         GROUP BY lr.test_type_id, lt.test_name, lt.category
                         ORDER BY count DESC");
    $stmt->execute([$start_date, $end_date]);
    $test_type_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Test volume by category
    $stmt = $db->prepare("SELECT lt.category, COUNT(lr.id) as count,
                         SUM(CASE WHEN lr.status = 'Completed' THEN 1 ELSE 0 END) as completed
                         FROM lab_requests lr
                         LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                         WHERE DATE(lr.request_date) BETWEEN ? AND ?
                         GROUP BY lt.category
                         ORDER BY count DESC");
    $stmt->execute([$start_date, $end_date]);
    $category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Daily stats
    $stmt = $db->prepare("SELECT DATE(lr.request_date) as date, COUNT(*) as count,
                         SUM(CASE WHEN lr.status = 'Completed' THEN 1 ELSE 0 END) as completed,
                         SUM(CASE WHEN lr.status = 'Pending' THEN 1 ELSE 0 END) as pending,
                         SUM(CASE WHEN lr.priority IN ('Urgent', 'STAT') THEN 1 ELSE 0 END) as urgent
                         FROM lab_requests lr
                         WHERE DATE(lr.request_date) BETWEEN ? AND ?
                         GROUP BY DATE(lr.request_date)
                         ORDER BY date");
    $stmt->execute([$start_date, $end_date]);
    $daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Turnaround time by test type
    $stmt = $db->prepare("SELECT lt.test_name, 
                         AVG(TIMESTAMPDIFF(HOUR, lr.request_date, lr.completed_at)) as avg_hours,
                         MIN(TIMESTAMPDIFF(HOUR, lr.request_date, lr.completed_at)) as min_hours,
                         MAX(TIMESTAMPDIFF(HOUR, lr.request_date, lr.completed_at)) as max_hours,
                         COUNT(lr.id) as total
                         FROM lab_requests lr
                         LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
                         WHERE DATE(lr.request_date) BETWEEN ? AND ?
                         AND lr.status = 'Completed'
                         AND lr.completed_at IS NOT NULL
                         GROUP BY lr.test_type_id, lt.test_name
                         ORDER BY avg_hours DESC");
    $stmt->execute([$start_date, $end_date]);
    $turnaround_by_type = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Lab Reports Error: " . $e->getMessage());
}

// Calculate rates
$abnormal_rate = ($stats['completed'] ?? 0) > 0 ? round(($stats['abnormal'] / $stats['completed']) * 100, 1) : 0;
$critical_rate = ($stats['completed'] ?? 0) > 0 ? round(($stats['critical'] / $stats['completed']) * 100, 1) : 0;
$completion_rate = ($stats['total_requests'] ?? 0) > 0 ? round(($stats['completed'] / $stats['total_requests']) * 100, 1) : 0;

// Debug: Check total records in database
$debug_total = 0;
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM lab_requests");
    $debug_total = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
} catch (Exception $e) {}

// Get specimen data
$specimen_data = ['collected' => 0, 'total' => 0];
try {
    $db = Database::getInstance();
    $specimen_query = "SELECT SUM(CASE WHEN specimen_collected = 1 THEN 1 ELSE 0 END) as collected,
                      COUNT(*) as total FROM lab_requests 
                      WHERE DATE(request_date) BETWEEN ? AND ?";
    $specimen_stmt = $db->prepare($specimen_query);
    $specimen_stmt->execute([$start_date, $end_date]);
    $specimen_data = $specimen_stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
$specimen_rate = ($specimen_data['total'] ?? 0) > 0 ? round(($specimen_data['collected'] / $specimen_data['total']) * 100) : 0;
?>

<!-- Chart.js with Animation Disabled -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Disable Chart.js animations globally to prevent requestAnimationFrame warnings
    if (window.Chart) {
        Chart.defaults.animation = false;
        Chart.defaults.responsiveAnimationDuration = 0;
        Chart.defaults.transitions = {};
    }
</script>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-flask text-brand-600 mr-2"></i> Laboratory Reports
                </h1>
                <p class="text-gray-500 mt-1">Comprehensive test analysis, turnaround time, and priority metrics</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                   class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                    <i class="fas fa-download mr-2"></i> Export CSV
                </a>
                <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
    </div>
    
    <!-- Date Range Filter -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-calendar text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">Date Range:</span>
            </div>
            
            <!-- Quick Date Presets -->
            <div class="flex items-center gap-1 w-full md:w-auto">
                <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600 hover:bg-gray-200">Today</a>
                <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600 hover:bg-gray-200">Last 7 Days</a>
                <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600 hover:bg-gray-200">This Month</a>
                <a href="?start_date=2025-01-01&end_date=<?php echo date('Y-m-d'); ?>" class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-600 hover:bg-gray-200">All Time</a>
            </div>
            
            <div class="flex items-center gap-2 w-full md:w-auto">
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <span class="text-gray-500">to</span>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                       class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Generate Report
                </button>
            </div>
        </form>
    </div>
    
    <!-- Statistics Cards Row 1 -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Total Requests</span>
                <i class="fas fa-file-medical text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stats['total_requests']); ?></p>
            <p class="text-xs text-gray-400 mt-1">Completion: <?php echo $completion_rate; ?>%</p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Completed</span>
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <p class="text-2xl font-bold text-brand-600"><?php echo number_format($stats['Completed'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Pending</span>
                <i class="fas fa-clock text-yellow-500"></i>
            </div>
            <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats['Pending'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">In Progress</span>
                <i class="fas fa-spinner text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-brand-600"><?php echo number_format($stats['In Progress'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Rejected</span>
                <i class="fas fa-times-circle text-red-500"></i>
            </div>
            <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats['Rejected'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Avg TAT (hrs)</span>
                <i class="fas fa-hourglass-half text-indigo-500"></i>
            </div>
            <p class="text-2xl font-bold text-indigo-600"><?php echo $stats['avg_turnaround']; ?></p>
        </div>
    </div>
    
    <!-- Statistics Cards Row 2 - Priority & Results -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Normal Priority</span>
                <i class="fas fa-minus-circle text-gray-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($priority_stats['Normal'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Urgent</span>
                <i class="fas fa-exclamation-circle text-orange-500"></i>
            </div>
            <p class="text-2xl font-bold text-orange-600"><?php echo number_format($priority_stats['Urgent'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">STAT (Emergency)</span>
                <i class="fas fa-bomb text-red-500"></i>
            </div>
            <p class="text-2xl font-bold text-red-600"><?php echo number_format($priority_stats['STAT'] ?? 0); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Total Requests</span>
                <i class="fas fa-clipboard-list text-purple-500"></i>
            </div>
            <p class="text-2xl font-bold text-purple-600"><?php echo number_format($stats['total_requests']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Critical Results</span>
                <i class="fas fa-radiation text-red-600"></i>
            </div>
            <p class="text-2xl font-bold text-red-700"><?php echo number_format($stats['critical']); ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Specimens</span>
                <i class="fas fa-vial text-purple-500"></i>
            </div>
            <p class="text-2xl font-bold text-purple-600"><?php echo $specimen_rate; ?>%</p>
            <p class="text-xs text-gray-400 mt-1"><?php echo number_format($specimen_data['collected'] ?? 0); ?>/<?php echo number_format($specimen_data['total'] ?? 0); ?></p>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Daily Test Volume -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-gray-400 mr-2"></i> Daily Test Volume
            </h3>
            <div style="height: 200px;">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
        <!-- Test Category Distribution -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-pie text-gray-400 mr-2"></i> Tests by Category
            </h3>
            <div style="height: 200px;">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>
        
        <!-- Priority Distribution -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-bar text-gray-400 mr-2"></i> Priority Distribution
            </h3>
            <div style="height: 200px;">
                <canvas id="priorityChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Test Type Details -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-list text-gray-400 mr-2"></i> Test Type Details
            </h3>
        </div>
        
        <?php if (empty($test_type_stats)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-flask text-4xl mb-3 text-gray-300"></i>
            <p>No laboratory data available for this period</p>
            <p class="text-sm text-gray-400 mt-2">Try selecting "All Time" date range</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Test Name</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Category</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Total</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Completed</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Pending</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">In Progress</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Completion</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($test_type_stats as $test): 
                        $completion = $test['count'] > 0 ? round(($test['completed'] / $test['count']) * 100) : 0;
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 font-medium text-gray-800"><?php echo htmlspecialchars($test['test_type'] ?? 'Unknown'); ?></td>
                        <td class="py-3 px-4 text-gray-600">
                            <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                <?php echo htmlspecialchars($test['category'] ?? 'N/A'); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right text-gray-800 font-medium"><?php echo $test['count']; ?></td>
                        <td class="py-3 px-4 text-right text-brand-600"><?php echo $test['completed']; ?></td>
                        <td class="py-3 px-4 text-right text-yellow-600"><?php echo $test['pending']; ?></td>
                        <td class="py-3 px-4 text-right text-brand-600"><?php echo $test['in_progress']; ?></td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-16 bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $completion; ?>%"></div>
                                </div>
                                <span class="text-sm <?php echo $completion >= 80 ? 'text-brand-600' : ($completion >= 50 ? 'text-yellow-600' : 'text-red-600'); ?>">
                                    <?php echo $completion; ?>%
                                </span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Turnaround Time Analysis -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-stopwatch text-gray-400 mr-2"></i> Turnaround Time Analysis (Hours)
            </h3>
        </div>
        
        <?php if (empty($turnaround_by_type)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-stopwatch text-4xl mb-3 text-gray-300"></i>
            <p>No turnaround time data available</p>
            <p class="text-sm text-gray-400 mt-2">Turnaround time is calculated for completed requests with completed_at timestamp</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Test Type</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Tests Completed</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Average</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Minimum</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Maximum</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Performance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($turnaround_by_type as $tat): 
                        $avg = round($tat['avg_hours'], 1);
                        $performance = $avg <= 4 ? 'excellent' : ($avg <= 12 ? 'good' : ($avg <= 24 ? 'moderate' : 'needs improvement'));
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 font-medium text-gray-800"><?php echo htmlspecialchars($tat['test_name'] ?? 'Unknown'); ?></td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo $tat['total']; ?></td>
                        <td class="py-3 px-4 text-right text-gray-800 font-medium"><?php echo $avg; ?> hrs</td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo $tat['min_hours']; ?> hrs</td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo $tat['max_hours']; ?> hrs</td>
                        <td class="py-3 px-4">
                            <?php if ($performance === 'excellent'): ?>
                            <span class="px-2 py-1 bg-brand-100 text-green-700 rounded-full text-xs">Excellent (≤4hrs)</span>
                            <?php elseif ($performance === 'good'): ?>
                            <span class="px-2 py-1 bg-brand-100 text-blue-700 rounded-full text-xs">Good (≤12hrs)</span>
                            <?php elseif ($performance === 'moderate'): ?>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full text-xs">Moderate (≤24hrs)</span>
                            <?php else: ?>
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded-full text-xs">Needs Improvement</span>
                            <?php endif; ?>
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
    // Chart 1: Daily Test Volume (Line Chart)
    const dailyData = <?php echo json_encode($daily_stats); ?>;
    
    const dailyCtx = document.getElementById('dailyChart');
    if (dailyCtx && dailyData && dailyData.length > 0) {
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyData.map(d => d.date),
                datasets: [{
                    label: 'Total Tests',
                    data: dailyData.map(d => parseInt(d.count) || 0),
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }, {
                    label: 'Completed',
                    data: dailyData.map(d => parseInt(d.completed) || 0),
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'transparent',
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, padding: 10 } }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } },
                    x: { grid: { display: false } }
                }
            }
        });
    } else if (dailyCtx) {
        dailyCtx.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400">No daily data available</div>';
    }
    
    // Chart 2: Category Distribution (Doughnut Chart)
    const categoryData = <?php echo json_encode($category_stats); ?>;
    
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx && categoryData && categoryData.length > 0) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(c => c.category || 'Uncategorized'),
                datasets: [{
                    data: categoryData.map(c => parseInt(c.count) || 0),
                    backgroundColor: [
                        'rgba(99, 102, 241, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)',
                        'rgba(139, 92, 246, 0.7)',
                        'rgba(236, 72, 153, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, padding: 10, font: { size: 11 } } }
                }
            }
        });
    } else if (categoryCtx) {
        categoryCtx.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400">No category data</div>';
    }
    
    // Chart 3: Priority Distribution (Bar Chart)
    const priorityData = <?php echo json_encode($priority_stats); ?>;
    
    const priorityCtx = document.getElementById('priorityChart');
    if (priorityCtx && priorityData && Object.keys(priorityData).length > 0) {
        const priorityLabels = Object.keys(priorityData);
        const priorityValues = Object.values(priorityData).map(v => parseInt(v) || 0);
        
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: priorityLabels,
                datasets: [{
                    label: 'Requests',
                    data: priorityValues,
                    backgroundColor: [
                        'rgba(107, 114, 128, 0.7)',
                        'rgba(249, 115, 22, 0.7)',
                        'rgba(239, 68, 68, 0.7)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    } else if (priorityCtx) {
        priorityCtx.parentElement.innerHTML = '<div class="flex items-center justify-center h-full text-gray-400">No priority data</div>';
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>