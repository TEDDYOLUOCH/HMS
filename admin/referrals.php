<?php
/**
 * Hospital Management System - View Referrals
 * View all patient referrals
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Referrals';

require_once '../config/database.php';
require_once '../includes/activity_logger.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get filters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = ["1=1"];

if ($status_filter) {
    $where_clauses[] = "r.status = :status";
}
if ($date_from) {
    $where_clauses[] = "DATE(r.referral_date) >= :date_from";
}
if ($date_to) {
    $where_clauses[] = "DATE(r.referral_date) <= :date_to";
}
if ($search) {
    $where_clauses[] = "(p.patient_id LIKE :search OR p.first_name LIKE :search2 OR p.last_name LIKE :search3)";
}

$where_sql = implode(" AND ", $where_clauses);

try {
    $db = Database::getInstance();
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM referrals r JOIN patients p ON r.patient_id = p.id WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    if ($status_filter) $stmt->bindValue(':status', $status_filter);
    if ($date_from) $stmt->bindValue(':date_from', $date_from);
    if ($date_to) $stmt->bindValue(':date_to', $date_to);
    if ($search) {
        $search_param = "%$search%";
        $stmt->bindValue(':search', $search_param);
        $stmt->bindValue(':search2', $search_param);
        $stmt->bindValue(':search3', $search_param);
    }
    $stmt->execute();
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Pagination
    $per_page = 20;
    $page = (int)($_GET['page'] ?? 1);
    $offset = ($page - 1) * $per_page;
    
    // Get referrals
    $sql = "SELECT r.*, p.patient_id, p.first_name, p.last_name, p.gender, p.date_of_birth,
            u.full_name as referred_by_name
            FROM referrals r 
            JOIN patients p ON r.patient_id = p.id
            LEFT JOIN users u ON r.referred_by = u.id
            WHERE $where_sql 
            ORDER BY r.referral_date DESC 
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    if ($status_filter) $stmt->bindValue(':status', $status_filter);
    if ($date_from) $stmt->bindValue(':date_from', $date_from);
    if ($date_to) $stmt->bindValue(':date_to', $date_to);
    if ($search) {
        $search_param = "%$search%";
        $stmt->bindValue(':search', $search_param);
        $stmt->bindValue(':search2', $search_param);
        $stmt->bindValue(':search3', $search_param);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $referrals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $referrals = [];
    $total = 0;
}

$total_pages = ceil($total / $per_page);
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-share text-blue-600 mr-2"></i> Patient Referrals
                </h1>
                <p class="text-gray-500 mt-1">View and manage all patient referrals</p>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by patient ID or name..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="w-40">
                <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Completed" <?php echo $status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="w-40">
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                       placeholder="From" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            <div class="w-40">
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                       placeholder="To" 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-filter mr-2"></i> Filter
            </button>
        </form>
    </div>
    
    <!-- Referrals Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Referral ID</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Referred To</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Reason</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Urgency</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Referred By</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($referrals)): ?>
                    <tr>
                        <td colspan="8" class="py-8 text-center text-gray-500">
                            <i class="fas fa-share text-4xl mb-3 text-gray-300"></i>
                            <p>No referrals found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($referrals as $ref): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="font-mono text-sm text-gray-700">#<?php echo $ref['id']; ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($ref['first_name'] . ' ' . $ref['last_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($ref['patient_id']); ?></p>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php 
                                $refer_to = [];
                                if ($ref['refer_to_department']) $refer_to[] = htmlspecialchars($ref['refer_to_department']);
                                if ($ref['refer_to_facility']) $refer_to[] = htmlspecialchars($ref['refer_to_facility']);
                                echo implode(' / ', $refer_to) ?: '-';
                                ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars(substr($ref['refer_reason'], 0, 50)); ?><?php echo strlen($ref['refer_reason']) > 50 ? '...' : ''; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php 
                                $urgency_class = [
                                    'Normal' => 'bg-blue-100 text-blue-700',
                                    'Urgent' => 'bg-orange-100 text-orange-700',
                                    'Emergency' => 'bg-red-100 text-red-700'
                                ];
                                $class = $urgency_class[$ref['urgency']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($ref['urgency']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <?php 
                                $status_class = [
                                    'Pending' => 'bg-yellow-100 text-yellow-700',
                                    'Completed' => 'bg-green-100 text-green-700',
                                    'Cancelled' => 'bg-gray-100 text-gray-700'
                                ];
                                $class = $status_class[$ref['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($ref['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($ref['referred_by_name'] ?? 'Unknown'); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($ref['referral_date'])); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
            <p class="text-sm text-gray-500">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total); ?> of <?php echo $total; ?> results</p>
            <div class="flex gap-1">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" 
                       class="px-3 py-1 rounded <?php echo $page === $i ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
require_once '../includes/footer.php';
?>
