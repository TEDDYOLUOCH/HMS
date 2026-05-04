<?php
/**
 * Hospital Management System - Laboratory Requests
 * Incoming test requests queue and management
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'lab_technologist', 'lab_scientist', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Laboratory Requests';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle request actions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();
        
        // Accept request
        if (isset($_POST['accept_request'])) {
            $request_id = $_POST['request_id'] ?? 0;
            try {
                $stmt = $db->prepare("UPDATE lab_requests SET status = 'In Progress', accepted_by = ?, accepted_at = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $request_id]);
                $message = 'Request accepted successfully';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Reject request
        if (isset($_POST['reject_request'])) {
            $request_id = $_POST['request_id'] ?? 0;
            $reason = $_POST['reject_reason'] ?? 'No reason provided';
            try {
                $stmt = $db->prepare("UPDATE lab_requests SET status = 'Rejected', reject_reason = ?, rejected_by = ?, rejected_at = NOW() WHERE id = ?");
                $stmt->execute([$reason, $_SESSION['user_id'], $request_id]);
                $message = 'Request rejected';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Complete request
        if (isset($_POST['complete_request'])) {
            $request_id = $_POST['request_id'] ?? 0;
            try {
                $stmt = $db->prepare("UPDATE lab_requests SET status = 'Completed', completed_at = NOW() WHERE id = ?");
                $stmt->execute([$request_id]);
                $message = 'Request marked as completed';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get filter
$status_filter = $_GET['status'] ?? '';
$patient_id_param = $_GET['patient_id'] ?? 0;

// Convert status filter to match database ENUM values
$status_map = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress', 
    'completed' => 'Completed',
    'rejected' => 'Rejected'
];
$db_status = $status_filter ? ($status_map[$status_filter] ?? $status_filter) : '';
$show_request_form = isset($_GET['new_request']);

// Get patient info if patient_id is provided
$patient = null;
if ($patient_id_param) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM patients WHERE id = ?");
        $stmt->execute([$patient_id_param]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Handle new request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_lab_request']) && $patient) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            $test_type_id = $_POST['test_type_id'] ?? 0;
            $clinical_notes = trim($_POST['clinical_notes'] ?? '');
            $is_urgent = isset($_POST['is_urgent']) ? 1 : 0;
            
            if (empty($test_type_id)) {
                $message = 'Please select a test type';
                $message_type = 'error';
            } else {
                // Create lab request - use NULL if no visit associated
                $priority = $_POST['is_urgent'] ?? 'Normal';
                
                $stmt = $db->prepare("INSERT INTO lab_requests (visit_id, patient_id, test_type_id, requested_by, clinical_notes, priority, status, request_date) VALUES (NULL, ?, ?, ?, ?, ?, 'Pending', NOW())");
                $stmt->execute([
                    $patient_id_param,
                    $test_type_id,
                    $_SESSION['user_id'],
                    $clinical_notes,
                    $priority
                ]);
                
                $message = 'Lab request submitted successfully!';
                $message_type = 'success';
                $show_request_form = false;
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get available test types
$test_types = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM lab_test_types WHERE is_active = 1 ORDER BY category, test_name");
    $test_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Build query
$where_clause = "1=1";
$params = [];

if ($db_status) {
    $where_clause .= " AND lr.status = :status";
    $params['status'] = $db_status;
}

// Get total count
$total_count = 0;
try {
    $db = Database::getInstance();
    $count_sql = "SELECT COUNT(*) as total FROM lab_requests lr WHERE $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total_count = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {}

$total_pages = ceil($total_count / $limit);

// Get lab requests with pagination
$lab_requests = [];
try {
    $db = Database::getInstance();
    $sql = "SELECT lr.*, p.first_name, p.last_name, p.patient_id, p.gender, p.date_of_birth,
            u.username as requested_by_name,
            lt.test_name as test_type
            FROM lab_requests lr
            JOIN patients p ON lr.patient_id = p.id
            LEFT JOIN users u ON lr.requested_by = u.id
            LEFT JOIN lab_test_types lt ON lr.test_type_id = lt.id
            WHERE $where_clause
            ORDER BY FIELD(lr.priority, 'STAT', 'Urgent', 'Normal'), lr.created_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    $lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist, show empty state
}

// Get statistics
$stats = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0, 'Urgent' => 0];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM lab_requests GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
    }
    $stmt = $db->query("SELECT COUNT(*) as count FROM lab_requests WHERE priority IN ('Urgent', 'STAT') AND status IN ('Pending', 'In Progress')");
    $stats['Urgent'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
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
                    <i class="fas fa-flask text-purple-600 mr-2"></i> Laboratory Requests
                </h1>
                <p class="text-gray-500 mt-1">Manage incoming test requests</p>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($patient): ?>
                <a href="?new_request=1&patient_id=<?php echo (int)$patient_id_param; ?>" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-plus mr-2"></i> New Request
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert-auto-hide rounded-lg p-4 mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'; ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500'; ?> mr-3"></i>
            <p class="<?php echo $message_type === 'success' ? 'text-green-800' : 'text-red-800'; ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- New Request Form -->
    <?php if ($show_request_form && $patient): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">New Lab Request</h2>
        
        <!-- Patient Info -->
        <div class="bg-gradient-to-r from-purple-50 to-indigo-50 rounded-lg p-4 mb-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-indigo-500 rounded-full flex items-center justify-center">
                    <span class="text-white font-bold"><?php echo strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1)); ?></span>
                </div>
                <div>
                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($patient['patient_id']); ?></p>
                </div>
            </div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="create_lab_request" value="1">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Test <span class="text-red-500">*</span></label>
                    <select name="test_type_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Select Test</option>
                        <?php 
                        $current_category = '';
                        foreach ($test_types as $test): 
                            if ($test['category'] !== $current_category): 
                                if ($current_category) echo '</optgroup>';
                                $current_category = $test['category'];
                        ?>
                        <optgroup label="<?php echo htmlspecialchars($current_category); ?>">
                        <?php endif; ?>
                            <option value="<?php echo $test['id']; ?>"><?php echo htmlspecialchars($test['test_name']); ?></option>
                        <?php endforeach; ?>
                        <?php if ($current_category) echo '</optgroup>'; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                    <select name="is_urgent" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="">Select Priority</option>
                        <option value="Normal">Normal</option>
                        <option value="Urgent">Urgent</option>
                        <option value="STAT">STAT (Emergency)</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Clinical Notes / Reason for Test</label>
                    <textarea name="clinical_notes" rows="3" placeholder="Enter clinical indication for the test..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end gap-2">
                <a href="lab_requests.php<?php echo $patient_id_param ? '?patient_id=' . (int)$patient_id_param : ''; ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Request
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <a href="?status=pending" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-yellow-400 transition <?php echo $status_filter === 'pending' ? 'ring-2 ring-yellow-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['Pending']; ?></p>
                </div>
                <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?status=in_progress" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-blue-400 transition <?php echo $status_filter === 'in_progress' ? 'ring-2 ring-blue-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">In Progress</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['In Progress']; ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-spinner text-blue-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?status=completed" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-green-400 transition <?php echo $status_filter === 'completed' ? 'ring-2 ring-green-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Completed</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['Completed']; ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check text-green-600"></i>
                </div>
            </div>
        </a>
        
        <a href="?status=urgent" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:border-red-400 transition <?php echo $status_filter === 'urgent' ? 'ring-2 ring-red-400' : ''; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Urgent</p>
                    <p class="text-2xl font-bold text-red-600"><?php echo $stats['Urgent']; ?></p>
                </div>
                <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
            </div>
        </a>
    </div>
    
    <!-- Filter Bar -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-600">Filter:</span>
            <a href="lab_requests" class="px-3 py-1 rounded-full text-sm <?php echo !$status_filter ? 'bg-primary-100 text-primary-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                All
            </a>
            <a href="?status=pending" class="px-3 py-1 rounded-full text-sm <?php echo $status_filter === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                Pending
            </a>
            <a href="?status=in_progress" class="px-3 py-1 rounded-full text-sm <?php echo $status_filter === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                In Progress
            </a>
            <a href="?status=completed" class="px-3 py-1 rounded-full text-sm <?php echo $status_filter === 'completed' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                Completed
            </a>
            <a href="?status=rejected" class="px-3 py-1 rounded-full text-sm <?php echo $status_filter === 'rejected' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
                Rejected
            </a>
        </div>
    </div>
    
    <!-- Requests Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Patient</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Test Type</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Priority</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Requested By</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($lab_requests)): ?>
                    <tr>
                        <td colspan="7" class="px-4 py-12 text-center">
                            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-flask text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-800 mb-1">No Lab Requests</h3>
                            <p class="text-xs text-gray-500">
                                <?php echo $status_filter ? 'No requests with this status' : 'No lab requests today'; ?>
                            </p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($lab_requests as $request): ?>
                        <?php $isUrgent = in_array($request['priority'] ?? '', ['Urgent', 'STAT']); ?>
                        <tr class="hover:bg-gray-50 <?php echo $isUrgent ? 'bg-red-50' : ''; ?>">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-xs font-bold">
                                            <?php echo strtoupper(substr($request['first_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-800">
                                            <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            <?php echo htmlspecialchars($request['patient_id']); ?> • 
                                            <?php echo floor((time() - strtotime($request['date_of_birth'])) / (365.25 * 60 * 60 * 24)); ?> yrs • 
                                            <?php echo ucfirst($request['gender']); ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-800"><?php echo htmlspecialchars($request['test_type']); ?></p>
                                <?php if ($request['clinical_notes']): ?>
                                <p class="text-xs text-gray-500 mt-0.5"><?php echo htmlspecialchars(substr($request['clinical_notes'], 0, 50)) . (strlen($request['clinical_notes']) > 50 ? '...' : ''); ?></p>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($isUrgent): ?>
                                <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-medium">
                                    <i class="fas fa-exclamation-triangle mr-1"></i><?php echo $request['priority']; ?>
                                </span>
                                <?php else: ?>
                                <span class="text-sm text-gray-600">Normal</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php 
                                $status_config = [
                                    'Pending' => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'label' => 'Pending'],
                                    'In Progress' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'In Progress'],
                                    'Completed' => ['bg' => 'bg-green-100', 'text' => 'text-green-700', 'label' => 'Completed'],
                                    'Rejected' => ['bg' => 'bg-red-100', 'text' => 'text-red-700', 'label' => 'Rejected'],
                                    'Cancelled' => ['bg' => 'bg-gray-100', 'text' => 'text-gray-700', 'label' => 'Cancelled'],
                                ];
                                $status_style = $status_config[$request['status']] ?? $status_config['Pending'];
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_style['bg'] . ' ' . $status_style['text']; ?>">
                                    <?php echo $status_style['label']; ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-800"><?php echo htmlspecialchars($request['requested_by_name'] ?? 'Unknown'); ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-sm text-gray-800"><?php echo date('M j, Y', strtotime($request['created_at'])); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($request['created_at'])); ?></p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1">
                                    <?php if ($request['status'] === 'Pending'): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <button type="submit" name="accept_request" class="px-2 py-1 bg-brand-600 text-white rounded text-xs hover:bg-brand-700" title="Accept">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    
                                    <button onclick="showRejectModal(<?php echo $request['id']; ?>)" class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs hover:bg-red-200" title="Reject">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($request['status'] === 'In Progress'): ?>
                                    <a href="add_results.php?request_id=<?php echo $request['id']; ?>" class="px-2 py-1 bg-purple-600 text-white rounded text-xs hover:bg-purple-700" title="Enter Results">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($request['status'] === 'Completed'): ?>
                                    <a href="lab_reports.php?request_id=<?php echo $request['id']; ?>" class="px-2 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700" title="View Report">
                                        <i class="fas fa-file-pdf"></i>
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
    
    <?php if ($total_pages > 1): ?>
    <div class="px-4 py-3 bg-white border-t border-gray-200">
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">
                Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_count); ?> of <?php echo $total_count; ?> entries
            </p>
            <nav class="flex items-center gap-1">
                <?php $query_params = $_GET; ?>
                <?php if ($page > 1): ?>
                <?php $query_params['page'] = $page - 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)): ?>
                    <?php $query_params['page'] = $i; ?>
                    <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1.5 text-sm rounded-lg <?php echo $i == $page ? 'bg-purple-600 text-white' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                    <span class="px-2 text-gray-400">...</span>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <?php $query_params['page'] = $page + 1; ?>
                <a href="?<?php echo http_build_query($query_params); ?>" class="px-3 py-1.5 text-sm rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-md w-full p-4 sm:p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b-2 border-[#9E2A1E] pb-2">Reject Request</h3>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="request_id" id="rejectRequestId" value="">
            <input type="hidden" name="reject_request" value="1">
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection</label>
                <textarea name="reject_reason" rows="3" required
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"
                          placeholder="Enter reason..."></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    Reject Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showRejectModal(requestId) {
        document.getElementById('rejectRequestId').value = requestId;
        document.getElementById('rejectModal').classList.remove('hidden');
    }
    
    function closeRejectModal() {
        document.getElementById('rejectModal').classList.add('hidden');
    }
</script>

<?php
require_once '../includes/footer.php';
?>
