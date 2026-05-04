<?php
/**
 * Hospital Management System - Daily Reports
 * Daily patient statistics and department-wise breakdown
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'nurse', 'pharmacist', 'lab_technologist', 'lab_scientist'], '../dashboard');

// Set page title
$page_title = 'Daily Reports';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get date range
$date = $_GET['date'] ?? date('Y-m-d');
$prev_date = date('Y-m-d', strtotime($date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($date . ' +1 day'));

// Initialize statistics
$stats = [
    'new_patients' => 0,
    'opd_visits' => 0,
    'admissions' => 0,
    'lab_tests' => 0,
    'prescriptions' => 0,
    'procedures' => 0,
    'anc_visits' => 0,
    'vitals_recorded' => 0
];

$prev_stats = [
    'new_patients' => 0,
    'opd_visits' => 0,
    'admissions' => 0,
    'lab_tests' => 0,
    'prescriptions' => 0,
    'procedures' => 0,
    'anc_visits' => 0,
    'vitals_recorded' => 0
];

$dept_breakdown = [];

try {
    $db = Database::getInstance();
    
    // New patients registered today
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['new_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Previous day
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM patients WHERE DATE(created_at) = ?");
    $stmt->execute([$prev_date]);
    $prev_stats['new_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // OPD consultations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM consultations WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $stats['opd_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM consultations WHERE DATE(created_at) = ?");
    $stmt->execute([$prev_date]);
    $prev_stats['opd_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Laboratory tests
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE DATE(request_date) = ?");
    $stmt->execute([$date]);
    $stats['lab_tests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM lab_requests WHERE DATE(request_date) = ?");
    $stmt->execute([$prev_date]);
    $prev_stats['lab_tests'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Prescriptions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE DATE(prescribed_at) = ?");
    $stmt->execute([$date]);
    $stats['prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM prescriptions WHERE DATE(prescribed_at) = ?");
    $stmt->execute([$prev_date]);
    $prev_stats['prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Theatre procedures
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM theatre_procedures WHERE DATE(procedure_date) = ?");
    $stmt->execute([$date]);
    $stats['procedures'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM theatre_procedures WHERE DATE(procedure_date) = ?");
    $stmt->execute([$prev_date]);
    $prev_stats['procedures'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // ANC visits
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM anc_visits WHERE DATE(visit_date) = ?");
    $stmt->execute([$date]);
    $stats['anc_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM anc_visits WHERE DATE(visit_date) = ?");
    $stmt->execute([$prev_date]);
    $prev_stats['anc_visits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Vitals recorded
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM vitals WHERE DATE(recorded_at) = ?");
    $stmt->execute([$date]);
    $stats['vitals_recorded'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    // Department breakdown
    $stmt = $db->query("SELECT 'OPD' as department, COUNT(*) as count FROM consultations WHERE DATE(created_at) = '$date'
                        UNION ALL
                        SELECT 'Laboratory' as department, COUNT(*) as count FROM lab_requests WHERE DATE(request_date) = '$date'
                        UNION ALL
                        SELECT 'Pharmacy' as department, COUNT(*) as count FROM prescriptions WHERE DATE(created_at) = '$date'
                        UNION ALL
                        SELECT 'Theatre' as department, COUNT(*) as count FROM theatre_procedures WHERE DATE(procedure_date) = '$date'
                        UNION ALL
                        SELECT 'Nursing' as department, COUNT(*) as count FROM vitals WHERE DATE(recorded_at) = '$date'");
    $dept_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Handle error silently
}

// Get recent patients for the day
$recent_patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT patient_id, first_name, last_name, created_at FROM patients WHERE DATE(created_at) = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$date]);
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calculate percentage change
function getChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-chart-line text-brand-600 mr-2"></i> Daily Reports
                </h1>
                <p class="text-gray-500 mt-1">Daily patient statistics and department breakdown</p>
            </div>
            <div class="flex gap-2">
                <button onclick="exportToExcel()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                    <i class="fas fa-file-excel mr-2"></i> Export Excel
                </button>
                <button onclick="window.print()" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>
    </div>
    
    <!-- Date Navigator -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex items-center justify-between">
            <a href="?date=<?php echo $prev_date; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-chevron-left mr-1"></i> Previous Day
            </a>
            
            <div class="flex items-center gap-4">
                <input type="date" value="<?php echo $date; ?>" onchange="window.location.href = '?date=' + this.value"
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <span class="text-lg font-semibold text-gray-800">
                    <?php echo date('l, F j, Y', strtotime($date)); ?>
                </span>
            </div>
            
            <?php if ($date < date('Y-m-d')): ?>
            <a href="?date=<?php echo $next_date; ?>" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                Next Day <i class="fas fa-chevron-right ml-1"></i>
            </a>
            <?php else: ?>
            <div class="w-24"></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php 
        $stat_items = [
            ['label' => 'New Patients', 'key' => 'new_patients', 'icon' => 'fa-user-plus', 'color' => 'blue'],
            ['label' => 'OPD Visits', 'key' => 'opd_visits', 'icon' => 'fa-stethoscope', 'color' => 'green'],
            ['label' => 'Lab Tests', 'key' => 'lab_tests', 'icon' => 'fa-flask', 'color' => 'purple'],
            ['label' => 'Prescriptions', 'key' => 'prescriptions', 'icon' => 'fa-pills', 'color' => 'orange'],
            ['label' => 'Procedures', 'key' => 'procedures', 'icon' => 'fa-procedures', 'color' => 'red'],
            ['label' => 'ANC Visits', 'key' => 'anc_visits', 'icon' => 'fa-baby', 'color' => 'pink'],
            ['label' => 'Vitals Recorded', 'key' => 'vitals_recorded', 'icon' => 'fa-heartbeat', 'color' => 'indigo'],
        ];
        
        foreach ($stat_items as $item): 
            $current = $stats[$item['key']];
            $previous = $prev_stats[$item['key']];
            $change = getChange($current, $previous);
            $is_positive = $change >= 0;
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500"><?php echo $item['label']; ?></span>
                <div class="w-8 h-8 bg-<?php echo $item['color']; ?>-100 rounded-lg flex items-center justify-center">
                    <i class="fas <?php echo $item['icon']; ?> text-<?php echo $item['color']; ?>-600 text-sm"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $current; ?></p>
            <div class="flex items-center gap-1 mt-1">
                <i class="fas <?php echo $is_positive ? 'fa-arrow-up text-green-500' : 'fa-arrow-down text-red-500'; ?> text-xs"></i>
                <span class="text-xs <?php echo $is_positive ? 'text-brand-600' : 'text-red-600'; ?>">
                    <?php echo abs($change); ?>% vs yesterday
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Department Breakdown -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-building text-gray-400 mr-2"></i> Department Breakdown
                </h3>
            </div>
            
            <div class="p-4 space-y-4">
                <?php if (empty($dept_breakdown)): ?>
                <p class="text-gray-500 text-center py-4">No department data available</p>
                <?php else: 
                    $total = array_sum(array_column($dept_breakdown, 'count'));
                ?>
                    <?php foreach ($dept_breakdown as $dept): 
                        $percent = $total > 0 ? round(($dept['count'] / $total) * 100) : 0;
                    ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-700"><?php echo $dept['department']; ?></span>
                            <span class="text-sm text-gray-500"><?php echo $dept['count']; ?> (<?php echo $percent; ?>%)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-primary-600 h-2 rounded-full" style="width: <?php echo $percent; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Patients -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-users text-gray-400 mr-2"></i> New Patients Registered
                </h3>
            </div>
            
            <?php if (empty($recent_patients)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-user-plus text-4xl mb-3 text-gray-300"></i>
                <p>No new patients registered today</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($recent_patients as $patient): ?>
                <div class="p-4 flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-medium">
                            <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                        </span>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($patient['patient_id']); ?></p>
                    </div>
                    <span class="text-xs text-gray-400">
                        <?php echo date('g:i A', strtotime($patient['created_at'])); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Summary Table -->
    <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-table text-gray-400 mr-2"></i> Daily Summary Comparison
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Metric</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase"><?php echo date('M j', strtotime($prev_date)); ?></th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase"><?php echo date('M j', strtotime($date)); ?></th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Change</th>
                        <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">% Change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($stat_items as $item): 
                        $current = $stats[$item['key']];
                        $previous = $prev_stats[$item['key']];
                        $change = $current - $previous;
                        $percent = getChange($current, $previous);
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 font-medium text-gray-800"><?php echo $item['label']; ?></td>
                        <td class="py-3 px-4 text-right text-gray-600"><?php echo $previous; ?></td>
                        <td class="py-3 px-4 text-right font-medium text-gray-800"><?php echo $current; ?></td>
                        <td class="py-3 px-4 text-right <?php echo $change >= 0 ? 'text-brand-600' : 'text-red-600'; ?>">
                            <?php echo $change >= 0 ? '+' : ''; ?><?php echo $change; ?>
                        </td>
                        <td class="py-3 px-4 text-right <?php echo $percent >= 0 ? 'text-brand-600' : 'text-red-600'; ?>">
                            <?php echo $percent >= 0 ? '+' : ''; ?><?php echo $percent; ?>%
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToExcel() {
    const date = '<?php echo $date; ?>';
    const data = {
        'New Patients': <?php echo $stats['new_patients']; ?>,
        'OPD Visits': <?php echo $stats['opd_visits']; ?>,
        'Laboratory Tests': <?php echo $stats['lab_tests']; ?>,
        'Prescriptions': <?php echo $stats['prescriptions']; ?>,
        'Procedures': <?php echo $stats['procedures']; ?>,
        'ANC Visits': <?php echo $stats['anc_visits']; ?>,
        'Vitals Recorded': <?php echo $stats['vitals_recorded']; ?>
    };
    
    let csv = 'Category,Value\n';
    for (const [key, value] of Object.entries(data)) {
        csv += key + ',' + value + '\n';
    }
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'daily_report_' + date + '.csv';
    a.click();
}
</script>

<?php
require_once '../includes/footer.php';
?>
