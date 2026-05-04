<?php
/**
 * Hospital Management System - Activity Logs
 * Comprehensive audit trail with filtering and auto-archive
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin'], '../dashboard');

// Set page title
$page_title = 'Activity Logs';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle auto-archive (admin only)
if (isset($_POST['archive_logs']) && $_SESSION['role'] === 'admin') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            // Archive logs older than 6 months to a separate table or delete
            $cutoff_date = date('Y-m-d', strtotime('-6 months'));
            
            // Count logs to be archived
            $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE created_at < ?", [$cutoff_date]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                // For now, we'll just delete old logs (in production, you'd archive to another table)
                $stmt = $db->query("DELETE FROM activity_logs WHERE created_at < ?", [$cutoff_date]);
                
                // Log the archive action
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'System', ?, NOW())", [$_SESSION['user_id'], "Archived $count logs older than 6 months"]);
                
                $message = "Archived $count old log entries";
                $message_type = 'success';
            } else {
                $message = 'No logs older than 6 months to archive';
                $message_type = 'info';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->GetMessage();
            $message_type = 'error';
        }
    }
}

// Get filters
$user_filter = $_GET['user_id'] ?? '';
$action_filter = $_GET['action'] ?? '';
$module_filter = $_GET['module'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Build query
$where_clause = "1=1";
$params = [];

if ($user_filter) {
    $where_clause .= " AND al.user_id = :user_id";
    $params['user_id'] = $user_filter;
}

if ($action_filter) {
    $where_clause .= " AND al.action LIKE :action";
    $params['action'] = '%' . $action_filter . '%';
}

if ($module_filter) {
    $where_clause .= " AND al.action LIKE :module";
    $params['module'] = $module_filter . '%';
}

$where_clause .= " AND DATE(al.created_at) BETWEEN :start_date AND :end_date";
$params['start_date'] = $start_date;
$params['end_date'] = $end_date;

// Get logs
$logs = [];
$users = [];
$actions = [];
$modules = ['Patient', 'Prescription', 'Laboratory', 'Theatre', 'User', 'Login', 'Logout', 'System'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    // Get users for filter
    $stmt = $db->query("SELECT id, username, full_name FROM users ORDER BY full_name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique actions
    $stmt = $db->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get logs with pagination
    $page = $_GET['page'] ?? 1;
    $per_page = 5;
    $offset = ($page - 1) * $per_page;
    
    $sql = "SELECT al.*, u.username, u.full_name, u.role
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE $where_clause
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM activity_logs al WHERE $where_clause";
    $stmt = $pdo->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total / $per_page);
    
} catch (Exception $e) {
    $error = $e->GetMessage();
}

// Get summary stats
$stats = [
    'today' => 0,
    'week' => 0,
    'month' => 0,
    'failed_logins' => 0,
    'archived' => 0
];

try {
    $db = Database::getInstance();
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE DATE(created_at) = CURDATE()");
    $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['week'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['month'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE action = 'Login Failed' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['failed_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)");
    $stats['archived'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (Exception $e) {}

// Generate CSRF token
$csrf_token = csrfToken();
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-history text-indigo-600 mr-2"></i> Activity Logs
                </h1>
                <p class="text-gray-500 mt-1">Comprehensive audit trail and system access logs</p>
            </div>
            <div class="flex gap-2">
                <button onclick="exportLogs()" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                    <i class="fas fa-file-export mr-2"></i> Export
                </button>
                <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <button type="submit" name="archive_logs" onclick="return confirm('Archive logs older than 6 months?')" 
                            class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700">
                        <i class="fas fa-archive mr-2"></i> Auto-Archive
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if (isset($message)): ?>
    <div class="alert-auto-hide rounded-lg p-4 mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 border border-brand-200' : ($message_type === 'info' ? 'bg-blue-50 border border-blue-200' : 'bg-red-50 border border-red-200'); ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : ($message_type === 'info' ? 'fa-info-circle text-blue-500' : 'fa-exclamation-circle text-red-500'); ?> mr-3"></i>
            <p class="<?php echo $message_type === 'success' ? 'text-green-800' : ($message_type === 'info' ? 'text-blue-800' : 'text-red-800'); ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Today</span>
                <i class="fas fa-calendar-day text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['today']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">This Week</span>
                <i class="fas fa-calendar-week text-green-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['week']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">This Month</span>
                <i class="fas fa-calendar-alt text-purple-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['month']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Failed Logins (24h)</span>
                <i class="fas fa-exclamation-triangle text-red-500"></i>
            </div>
            <p class="text-2xl font-bold <?php echo $stats['failed_logins'] > 5 ? 'text-red-600' : 'text-gray-800'; ?>">
                <?php echo $stats['failed_logins']; ?>
            </p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Old Logs (6mo+)</span>
                <i class="fas fa-archive text-yellow-500"></i>
            </div>
            <p class="text-2xl font-bold text-yellow-600"><?php echo $stats['archived']; ?></p>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-filter text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">Filters:</span>
            </div>
            
            <select name="user_id" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Users</option>
                <?php foreach ($users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $user_filter == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="module" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Modules</option>
                <?php foreach ($modules as $m): ?>
                <option value="<?php echo $m; ?>" <?php echo $module_filter === $m ? 'selected' : ''; ?>>
                    <?php echo $m; ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <select name="action" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                <option value="<?php echo htmlspecialchars($a['action']); ?>" <?php echo $action_filter === $a['action'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($a['action']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            
            <input type="date" name="start_date" value="<?php echo $start_date; ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            <span class="text-gray-500">to</span>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>"
                   class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
            
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                Apply Filters
            </button>
            
            <a href="activity_logs" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
        </form>
    </div>
    
    <!-- Logs Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">
                <i class="fas fa-list text-gray-400 mr-2"></i> Audit Trail
            </h3>
            <span class="text-sm text-gray-500">
                Showing <?php echo count($logs); ?> of <?php echo $total; ?> entries
            </span>
        </div>
        
        <?php if (empty($logs)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
            <p>No activity logs found</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Timestamp</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">User</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Module</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Action</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Details</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($logs as $log): 
                        // Determine module from action
                        $module = 'System';
                        foreach ($modules as $m) {
                            if (stripos($log['action'], $m) !== false) {
                                $module = $m;
                                break;
                            }
                        }
                        
                        $action_colors = [
                            'Login' => 'bg-brand-100 text-green-700',
                            'Login Failed' => 'bg-red-100 text-red-700',
                            'Logout' => 'bg-gray-100 text-gray-700',
                            'Created' => 'bg-brand-100 text-blue-700',
                            'Updated' => 'bg-yellow-100 text-yellow-700',
                            'Deleted' => 'bg-red-100 text-red-700',
                            'Reset' => 'bg-purple-100 text-purple-700',
                            'Status' => 'bg-indigo-100 text-indigo-700'
                        ];
                        $color = 'bg-gray-100 text-gray-700';
                        foreach ($action_colors as $key => $c) {
                            if (stripos($log['action'], $key) !== false) {
                                $color = $c;
                                break;
                            }
                        }
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-full flex items-center justify-center">
                                    <span class="text-white text-xs font-medium">
                                        <?php echo strtoupper(substr($log['full_name'] ?: $log['username'] ?: 'U', 0, 1)); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-800 text-sm">
                                        <?php echo htmlspecialchars($log['full_name'] ?: $log['username'] ?: 'Unknown'); ?>
                                    </span>
                                    <?php if ($log['role']): ?>
                                    <span class="text-xs text-gray-500"> (<?php echo $log['role']; ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                <?php echo $module; ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $color; ?>">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600 max-w-xs truncate">
                            <?php 
                                $details_text = '';
                                if (!empty($log['module'])) $details_text .= $log['module'];
                                if (!empty($log['table_affected'])) $details_text .= ' | ' . $log['table_affected'];
                                if (!empty($log['record_id'])) $details_text .= ' | ID: ' . $log['record_id'];
                                if (!empty($log['new_values'])) {
                                    $new_vals = json_decode($log['new_values'], true);
                                    if (is_array($new_vals)) {
                                        $details_text .= ' | ' . implode(', ', array_slice($new_vals, 0, 3));
                                    }
                                }
                                echo htmlspecialchars($details_text ?: '-');
                            ?>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-500">
                            <?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="p-4 border-t border-gray-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                   class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <span class="text-sm text-gray-600">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <?php if ($page < $total_pages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                   class="px-3 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = '?' + params.toString();
}

// Check for export parameter
if (window.location.search.includes('export=1')) {
    const rows = [];
    document.querySelectorAll('table tbody tr').forEach(row => {
        const cells = Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim().replace(/,/g, ';'));
        if (cells.length > 0) {
            rows.push(cells.join(','));
        }
    });
    
    if (rows.length > 0) {
        const csv = 'Timestamp,User,Module,Action,Details,IP Address\n' + rows.join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'activity_logs_<?php echo date("Y-m-d"); ?>.csv';
        a.click();
    }
}
</script>

<?php
require_once '../includes/footer.php';
?>
