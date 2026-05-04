<?php
/**
 * Hospital Management System - Theatre Reports
 * Operative reports and statistics
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'theatre_officer'], '../dashboard');

// Set page title
$page_title = 'Theatre Reports';

// Include database and header
require_once '../config/database.php';

// Get statistics
$stats = [
    'total' => 0,
    'Major Surgery' => 0,
    'Minor Surgery' => 0,
    'Completed' => 0,
    'Cancelled' => 0
];

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT COUNT(*) as count FROM theatre_procedures");
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    
    $stmt = $db->query("SELECT procedure_category, COUNT(*) as count FROM theatre_procedures GROUP BY procedure_category");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($stats[$row['procedure_category']])) {
            $stats[$row['procedure_category']] = $row['count'];
        }
    }
    
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM theatre_procedures GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
    }
} catch (Exception $e) {}

// Get completed procedures for report
$procedure_id = $_GET['id'] ?? 0;
$procedure = null;

if ($procedure_id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT tp.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth
                             FROM theatre_procedures tp
                             JOIN patients p ON tp.patient_id = p.id
                             WHERE tp.id = ?");
        $stmt->execute([$procedure_id]);
        $procedure = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Print mode
$is_print = isset($_GET['print']);

if ($is_print && $procedure) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Operative Report - <?php echo htmlspecialchars($procedure['patient_id']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.4; padding: 20px; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 15px; }
            .header h1 { font-size: 18pt; margin-bottom: 5px; }
            .header p { font-size: 10pt; color: #666; }
            .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
            .info-section h3 { font-size: 11pt; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }
            .info-row { display: flex; margin-bottom: 3px; }
            .info-label { font-weight: bold; width: 140px; }
            .info-value { flex: 1; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th, td { border: 1px solid #333; padding: 6px; text-align: left; }
            th { background: #f0f0f0; font-size: 10pt; }
            .signature-line { width: 200px; border-top: 1px solid #333; padding-top: 5px; text-align: center; margin-top: 40px; }
            .print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
            @media print { .print-btn { display: none; } }
        </style>
    </head>
    <body>
        <button class="print-btn" onclick="window.print()">Print Report</button>
        
        <div class="header">
            <h1>SIWOT HOSPITAL</h1>
            <p>P.O. Box 12345, Nairobi, Kenya | Tel: +254 700 000 000</p>
            <p>Theatre Department - Operative Report</p>
        </div>
        
        <div class="info-grid">
            <div class="info-section">
                <h3>Patient Information</h3>
                <div class="info-row"><span class="info-label">Name:</span><span class="info-value"><?php echo htmlspecialchars($procedure['first_name'] . ' ' . $procedure['last_name']); ?></span></div>
                <div class="info-row"><span class="info-label">Patient ID:</span><span class="info-value"><?php echo htmlspecialchars($procedure['patient_id']); ?></span></div>
                <div class="info-row"><span class="info-label">Age:</span><span class="info-value"><?php echo floor((time() - strtotime($procedure['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> years</span></div>
                <div class="info-row"><span class="info-label">Gender:</span><span class="info-value"><?php echo ucfirst($procedure['gender']); ?></span></div>
            </div>
            <div class="info-section">
                <h3>Procedure Information</h3>
                <div class="info-row"><span class="info-label">Procedure:</span><span class="info-value"><?php echo htmlspecialchars($procedure['procedure_name']); ?></span></div>
                <div class="info-row"><span class="info-label">Type:</span><span class="info-value"><?php echo ucfirst($procedure['procedure_category']); ?></span></div>
                <div class="info-row"><span class="info-label">Date:</span><span class="info-value"><?php echo date('F j, Y', strtotime($procedure['procedure_date'])); ?></span></div>
                <div class="info-row"><span class="info-label">Status:</span><span class="info-value"><?php echo ucfirst($procedure['status']); ?></span></div>
            </div>
        </div>
        
        <table>
            <tr><th colspan="2">Surgical Team</th></tr>
            <tr><td>Surgeon</td><td><?php echo htmlspecialchars($procedure['surgeon']); ?></td></tr>
            <tr><td>Assistant</td><td><?php echo $procedure['assistant'] ?: '-'; ?></td></tr>
            <tr><td>Anesthetist</td><td><?php echo $procedure['anesthetist'] ?: '-'; ?></td></tr>
            <tr><td>Scrub Nurse</td><td><?php echo $procedure['scrub_nurse'] ?: '-'; ?></td></tr>
        </table>
        
        <table>
            <tr><th colspan="2">Intra-Operative Details</th></tr>
            <tr><td>Anesthesia Type</td><td><?php echo ucfirst($procedure['anesthesia_type'] ?? '-'); ?></td></tr>
            <tr><td>Anesthesia Agents</td><td><?php echo $procedure['anesthesia_agents'] ?: '-'; ?></td></tr>
            <tr><td>Start Time</td><td><?php echo $procedure['start_time'] ? date('g:i A', strtotime($procedure['start_time'])) : '-'; ?></td></tr>
            <tr><td>End Time</td><td><?php echo $procedure['end_time'] ? date('g:i A', strtotime($procedure['end_time'])) : '-'; ?></td></tr>
            <tr><td>Blood Loss</td><td><?php echo $procedure['blood_loss'] ? $procedure['blood_loss'] . ' ml' : '-'; ?></td></tr>
            <tr><td>Fluids Given</td><td><?php echo $procedure['fluids_given'] ? $procedure['fluids_given'] . ' ml' : '-'; ?></td></tr>
            <tr><td>Specimens</td><td><?php echo $procedure['specimens'] ?: '-'; ?></td></tr>
        </table>
        
        <?php if ($procedure['intraop_notes']): ?>
        <table>
            <tr><th>Intra-Operative Notes</th></tr>
            <tr><td><?php echo nl2br(htmlspecialchars($procedure['intraop_notes'])); ?></td></tr>
        </table>
        <?php endif; ?>
        
        <?php if ($procedure['complications']): ?>
        <table>
            <tr><th>Complications</th></tr>
            <tr><td><?php echo nl2br(htmlspecialchars($procedure['complications'])); ?></td></tr>
        </table>
        <?php endif; ?>
        
        <div style="display: flex; justify-content: space-between;">
            <div class="signature-line">
                <p>Surgeon Signature</p>
            </div>
            <div class="signature-line">
                <p>Anesthetist Signature</p>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 30px; font-size: 10pt; color: #666;">
            This is a computer-generated report. Report ID: <?php echo $procedure_id; ?> | Generated: <?php echo date('Y-m-d H:i:s'); ?>
        </p>
    </body>
    </html>
    <?php
    exit;
}

// Include header for normal page view
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get procedures list for selection
$status_filter = $_GET['status'] ?? '';
$procedures_list = [];
try {
    $db = Database::getInstance();
    $where_clause = "1=1";
    if ($status_filter) {
        $where_clause .= " AND tp.status = '" . ucfirst($status_filter) . "'";
    }
    $stmt = $db->query("SELECT tp.id, tp.procedure_name, tp.procedure_category, tp.procedure_date, tp.status,
                       p.first_name, p.last_name, p.patient_id
                       FROM theatre_procedures tp
                       JOIN patients p ON tp.patient_id = p.id
                       WHERE $where_clause
                       ORDER BY tp.procedure_date DESC LIMIT 50");
    $procedures_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get surgeon stats
$surgeon_stats = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT COALESCE(u.full_name, 'Unknown') as surgeon, COUNT(*) as count 
                FROM theatre_procedures tp 
                LEFT JOIN users u ON tp.surgeon_id = u.id 
                WHERE tp.status = 'Completed' 
                GROUP BY tp.surgeon_id 
                ORDER BY count DESC LIMIT 5");
    $surgeon_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-file-medical-alt text-green-600 mr-2"></i> Theatre Reports
                </h1>
                <p class="text-gray-500 mt-1">Operative reports and statistics</p>
            </div>
            <?php if ($status_filter): ?>
            <a href="?" class="px-3 py-1 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
                <i class="fas fa-times mr-1"></i> Clear Filter
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Procedures</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-procedures text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Major</p>
                    <p class="text-2xl font-bold text-purple-600"><?php echo $stats['Major Surgery'] ?? 0; ?></p>
                </div>
                <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-procedures text-purple-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Completed</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['Completed'] ?? 0; ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check text-green-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Cancelled</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['Cancelled'] ?? 0; ?></p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-times text-red-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Procedures List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">
                    <i class="fas fa-list text-gray-400 mr-2"></i> Completed Procedures
                </h3>
            </div>
            
            <?php if (empty($procedures_list)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-clipboard-list text-4xl mb-3 text-gray-300"></i>
                <p>No procedures found</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Procedure</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Date</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($procedures_list as $proc): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-medium">
                                            <?php echo strtoupper(substr($proc['first_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <span class="font-medium text-gray-800 text-sm">
                                        <?php echo htmlspecialchars($proc['first_name'] . ' ' . $proc['last_name']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600"><?php echo htmlspecialchars($proc['procedure_name']); ?></td>
                            <td class="py-3 px-4 text-sm text-gray-500"><?php echo !empty($proc['procedure_date']) ? date('M j, Y', strtotime($proc['procedure_date'])) : '-'; ?></td>
                            <td class="py-3 px-4">
                                <?php if ($proc['status'] === 'Completed'): ?>
                                <a href="?id=<?php echo $proc['id']; ?>" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                                <a href="?id=<?php echo $proc['id']; ?>&print=1" target="_blank" class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">
                                    <i class="fas fa-print mr-1"></i> Print
                                </a>
                                <?php else: ?>
                                <span class="text-xs text-gray-400"><?php echo ucfirst($proc['status']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Statistics -->
        <div class="space-y-6">
            <!-- Top Surgeons -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-user-md text-gray-400 mr-2"></i> Top Surgeons
                </h3>
                
                <?php if (empty($surgeon_stats)): ?>
                <p class="text-gray-500 text-sm">No surgeon data available</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($surgeon_stats as $stat): ?>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700"><?php echo htmlspecialchars($stat['surgeon']); ?></span>
                        <div class="flex items-center gap-2">
                            <div class="w-24 bg-gray-200 rounded-full h-2">
                                <div class="bg-primary-600 h-2 rounded-full" style="width: <?php echo $stats['total'] > 0 ? ($stat['count'] / $stats['total']) * 100 : 0; ?>%"></div>
                            </div>
                            <span class="text-sm font-medium text-gray-800"><?php echo $stat['count']; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Report -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-chart-pie text-gray-400 mr-2"></i> Quick Report
                </h3>
                
                <div class="space-y-3">
                    <a href="?status=Completed" class="flex items-center justify-between p-3 bg-green-50 rounded-lg hover:bg-green-100">
                        <span class="text-green-800">Completed Procedures</span>
                        <span class="text-green-700 font-bold"><?php echo $stats['Completed'] ?? 0; ?></span>
                    </a>
                    <a href="?status=Cancelled" class="flex items-center justify-between p-3 bg-red-50 rounded-lg hover:bg-red-100">
                        <span class="text-red-800">Cancelled Procedures</span>
                        <span class="text-red-700 font-bold"><?php echo $stats['Cancelled'] ?? 0; ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
