<?php
/**
 * Hospital Management System - Monthly Reports
 * Monthly summary dashboard with trends and analytics
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Monthly Reports';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<!-- Chart.js - Load with animation disabled for performance -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Disable all Chart.js animations to prevent requestAnimationFrame warnings
    if (window.Chart) {
        Chart.defaults.animation = false;
        Chart.defaults.transition = false;
        Chart.defaults.datasets.line = Chart.defaults.datasets.line || {};
        Chart.defaults.datasets.line.animation = false;
        Chart.defaults.datasets.bar = Chart.defaults.datasets.bar || {};
        Chart.defaults.datasets.bar.animation = false;
        Chart.defaults.datasets.doughnut = Chart.defaults.datasets.doughnut || {};
        Chart.defaults.datasets.doughnut.animation = false;
        Chart.defaults.datasets.pie = Chart.defaults.datasets.pie || {};
        Chart.defaults.datasets.pie.animation = false;
        Chart.defaults.responsiveAnimationDuration = 0;
    }
</script>

<?php
// Get date range
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$start_date = date('Y-m-01', strtotime("$year-$month-01"));
$end_date = date('Y-m-t', strtotime("$year-$month-01"));
$prev_month = date('Y-m', strtotime("$year-$month-01 -1 month"));
$next_month = date('Y-m', strtotime("$year-$month-01 +1 month"));

// Initialize statistics
$stats = [
    'total_patients' => 0,
    'total_visits' => 0,
    'total_lab_tests' => 0,
    'total_prescriptions' => 0,
    'total_procedures' => 0,
    'total_anc' => 0
];

$trends = [];
$top_diagnoses = [];
$top_drugs = [];
$lab_summary = [];
$daily_visits = [];

try {
    $db = Database::getInstance();
    
    // Total new patients this month
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // OPD consultations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM consultations WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Lab tests
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_lab_tests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE DATE(prescribed_at) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Procedures
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM theatre_procedures WHERE DATE(procedure_date) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_procedures'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ANC visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM anc_visits WHERE DATE(visit_date) BETWEEN ? AND ?");
    $stmt->execute([$start_date, $end_date]);
    $stats['total_anc'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Daily trends for the month
    $stmt = $db->prepare("SELECT DATE(created_at) as date, COUNT(*) as count 
                         FROM consultations 
                         WHERE DATE(created_at) BETWEEN ? AND ?
                         GROUP BY DATE(created_at)
                         ORDER BY date");
    $stmt->execute([$start_date, $end_date]);
    $daily_visits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    } catch (Exception $e) {}
    
    // Top 10 diagnoses
    $top_diagnoses = [];
    try {
        $stmt = $db->prepare("SELECT diagnosis_primary as diagnosis, COUNT(*) as count 
                             FROM consultations 
                             WHERE DATE(created_at) BETWEEN ? AND ? AND diagnosis_primary IS NOT NULL AND diagnosis_primary != ''
                             GROUP BY diagnosis_primary 
                             ORDER BY count DESC 
                             LIMIT 10");
        $stmt->execute([$start_date, $end_date]);
        $top_diagnoses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    
    // Top 10 drugs dispensed
    $top_drugs = [];
    try {
        $stmt = $db->prepare("SELECT dr.drug_name, SUM(pr.quantity_prescribed) as total_qty, COUNT(*) as times_prescribed
                              FROM prescriptions pr
                              JOIN drug_stock dr ON pr.drug_id = dr.id
                              WHERE DATE(pr.prescribed_at) BETWEEN ? AND ?
                              GROUP BY dr.drug_name
                              ORDER BY total_qty DESC
                              LIMIT 10");
        $stmt->execute([$start_date, $end_date]);
        $top_drugs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    
    // Lab workload by test type
    $lab_summary = [];
    try {
        $stmt = $db->prepare("SELECT lt.test_name as test_type, COUNT(*) as count, 
                             SUM(CASE WHEN lrx.status = 'Completed' THEN 1 ELSE 0 END) as completed
                             FROM lab_requests lrx
                             JOIN lab_test_types lt ON lrx.test_type_id = lt.id
                             WHERE DATE(lrx.created_at) BETWEEN ? AND ?
                             GROUP BY lt.test_name
                             ORDER BY count DESC");
        $stmt->execute([$start_date, $end_date]);
        $lab_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}

// Generate chart data
$chart_dates = [];
$chart_values = [];
for ($d = 1; $d <= date('t', strtotime($start_date)); $d++) {
    $day = sprintf('%02d', $d);
    $chart_dates[] = "$year-$month-$day";
    $chart_values[] = 0;
}

foreach ($daily_visits as $visit) {
    $day_num = (int)date('j', strtotime($visit['date'])) - 1;
    if (isset($chart_values[$day_num])) {
        $chart_values[$day_num] = (int)$visit['count'];
    }
}

$month_name = date('F Y', strtotime($start_date));
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-bar text-purple-600 mr-2"></i> Monthly Reports
                </h1>
                <p class="text-gray-500 mt-1">Monthly summary dashboard and analytics</p>
            </div>
            <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                <i class="fas fa-print mr-2"></i> Print Report
            </button>
        </div>
    </div>
    
    <!-- Month Navigator -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between">
            <a href="?year=<?php echo date('Y', strtotime($prev_month)); ?>&month=<?php echo date('m', strtotime($prev_month)); ?>" 
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-chevron-left mr-1"></i> Previous
            </a>
            
            <div class="flex items-center gap-4">
                <select onchange="window.location.href = '?year=<?php echo $year; ?>&month=' + this.value" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo sprintf('%02d', $m); ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                        <?php echo date('F', strtotime("2024-$m-01")); ?>
                    </option>
                    <?php endfor; ?>
                </select>
                
                <select onchange="window.location.href = '?year=' + this.value&month=<?php echo $month; ?>" 
                        class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <?php for ($y = date('Y') - 2; $y <= date('Y'); $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if ($prev_month < date('Y-m')): ?>
            <a href="?year=<?php echo date('Y', strtotime($next_month)); ?>&month=<?php echo date('m', strtotime($next_month)); ?>" 
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                Next <i class="fas fa-chevron-right ml-1"></i>
            </a>
            <?php else: ?>
            <div class="w-20"></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">New Patients</span>
                <i class="fas fa-user-plus text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_patients']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">OPD Visits</span>
                <i class="fas fa-stethoscope text-green-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_visits']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Lab Tests</span>
                <i class="fas fa-flask text-purple-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_lab_tests']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Prescriptions</span>
                <i class="fas fa-pills text-orange-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_prescriptions']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Procedures</span>
                <i class="fas fa-procedures text-red-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_procedures']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">ANC Visits</span>
                <i class="fas fa-baby text-pink-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_anc']; ?></p>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Daily Visits Chart -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-chart-line text-gray-400 mr-2"></i> Daily OPD Visits - <?php echo $month_name; ?>
            </h3>
            <div class="h-[200px] w-full">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>
        
        <!-- Top Diagnoses -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-diagnosis text-gray-400 mr-2"></i> Top 10 Diagnoses
            </h3>
            <div class="space-y-3">
                <?php if (empty($top_diagnoses)): ?>
                <p class="text-gray-500 text-center py-4">No diagnosis data available</p>
                <?php else: 
                    $max_count = $top_diagnoses[0]['count'] ?? 1;
                    foreach ($top_diagnoses as $dx): 
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm text-gray-700 truncate flex-1 mr-2"><?php echo htmlspecialchars($dx['diagnosis']); ?></span>
                        <span class="text-sm font-medium text-gray-800"><?php echo $dx['count']; ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo ($dx['count'] / $max_count) * 100; ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Top Drugs -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-pills text-gray-400 mr-2"></i> Top 10 Drugs Dispensed
                </h3>
            </div>
            
            <?php if (empty($top_drugs)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-pills text-4xl mb-3 text-gray-300"></i>
                <p>No prescription data available</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Drug Name</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Times</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Total Qty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($top_drugs as $drug): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($drug['drug_name']); ?></td>
                            <td class="py-3 px-4 text-sm text-right text-gray-600"><?php echo $drug['times_prescribed']; ?></td>
                            <td class="py-3 px-4 text-sm text-right font-medium text-gray-800"><?php echo $drug['total_qty']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Lab Workload -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-flask text-gray-400 mr-2"></i> Laboratory Workload Summary
                </h3>
            </div>
            
            <?php if (empty($lab_summary)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-flask text-4xl mb-3 text-gray-300"></i>
                <p>No laboratory data available</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Test Type</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Requested</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Completed</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">% Done</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($lab_summary as $lab): 
                            $percent = $lab['count'] > 0 ? round(($lab['completed'] / $lab['count']) * 100) : 0;
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 text-sm text-gray-800"><?php echo htmlspecialchars($lab['test_type']); ?></td>
                            <td class="py-3 px-4 text-sm text-right text-gray-600"><?php echo $lab['count']; ?></td>
                            <td class="py-3 px-4 text-sm text-right text-gray-600"><?php echo $lab['completed']; ?></td>
                            <td class="py-3 px-4 text-sm text-right">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $percent >= 80 ? 'bg-green-100 text-green-700' : ($percent >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700'); ?>">
                                    <?php echo $percent; ?>%
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
    
    <!-- Summary -->
    <div class="bg-gradient-to-r from-primary-600 to-purple-600 rounded-xl p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold"><?php echo $month_name; ?> Summary</h3>
                <p class="text-primary-100 mt-1">Total patients served: <?php echo array_sum($stats); ?></p>
            </div>
            <div class="text-right">
                <p class="text-3xl font-bold"><?php echo array_sum($stats); ?></p>
                <p class="text-primary-100 text-sm">Total Activities</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('dailyChart');
    if (!canvas || typeof Chart === 'undefined') return;
    var ctx = canvas.getContext('2d');
    var chartDates = <?php echo json_encode(array_map(function($d) { return date('j', strtotime($d)); }, $chart_dates)); ?>;
    var chartValues = <?php echo json_encode($chart_values); ?>;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartDates,
            datasets: [{
                label: 'OPD Visits',
                data: chartValues,
                backgroundColor: 'rgba(99, 102, 241, 0.5)',
                borderColor: 'rgb(99, 102, 241)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>
