<?php
/**
 * Hospital Management System - View Admissions
 * View all patient admissions
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor'], '../dashboard');

// Set page title
$page_title = 'Admissions';

require_once '../config/database.php';
require_once '../includes/activity_logger.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle discharge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discharge_patient'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            $admission_id = (int)$_POST['admission_id'];
            $discharge_summary = trim($_POST['discharge_summary'] ?? '');
            
            $stmt = $db->prepare("UPDATE admissions SET status = 'Discharged', discharge_date = NOW(), discharge_summary = ? WHERE id = ?");
            $stmt->execute([$discharge_summary, $admission_id]);
            
            $message = 'Patient discharged successfully!';
            $message_type = 'success';
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$ward_filter = $_GET['ward'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = ["1=1"];

if ($status_filter) {
    $where_clauses[] = "a.status = :status";
}
if ($ward_filter) {
    $where_clauses[] = "a.ward = :ward";
}
if ($date_from) {
    $where_clauses[] = "DATE(a.admission_date) >= :date_from";
}
if ($date_to) {
    $where_clauses[] = "DATE(a.admission_date) <= :date_to";
}
if ($search) {
    $where_clauses[] = "(p.patient_id LIKE :search OR p.first_name LIKE :search2 OR p.last_name LIKE :search3)";
}

$where_sql = implode(" AND ", $where_clauses);

try {
    $db = Database::getInstance();
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM admissions a JOIN patients p ON a.patient_id = p.id WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    if ($status_filter) $stmt->bindValue(':status', $status_filter);
    if ($ward_filter) $stmt->bindValue(':ward', $ward_filter);
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
    $per_page = 10;
    $page = (int)($_GET['page'] ?? 1);
    $offset = ($page - 1) * $per_page;
    
    // Get admissions
    $sql = "SELECT a.*, p.patient_id, p.first_name, p.last_name, p.gender, p.date_of_birth, p.blood_group,
            u.full_name as admitted_by_name
            FROM admissions a 
            JOIN patients p ON a.patient_id = p.id
            LEFT JOIN users u ON a.admitted_by = u.id
            WHERE $where_sql 
            ORDER BY a.admission_date DESC 
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    if ($status_filter) $stmt->bindValue(':status', $status_filter);
    if ($ward_filter) $stmt->bindValue(':ward', $ward_filter);
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
    $admissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $admissions = [];
    $total = 0;
}

$total_pages = ceil($total / $per_page);

// Get unique wards for filter
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT DISTINCT ward FROM admissions ORDER BY ward");
    $wards = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $wards = [];
}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-procedures text-rose-600 mr-2"></i> Patient Admissions
                </h1>
                <p class="text-gray-500 mt-1">View and manage all patient admissions</p>
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
                    <option value="Admitted" <?php echo $status_filter === 'Admitted' ? 'selected' : ''; ?>>Admitted</option>
                    <option value="Discharged" <?php echo $status_filter === 'Discharged' ? 'selected' : ''; ?>>Discharged</option>
                    <option value="Transferred" <?php echo $status_filter === 'Transferred' ? 'selected' : ''; ?>>Transferred</option>
                    <option value="Deceased" <?php echo $status_filter === 'Deceased' ? 'selected' : ''; ?>>Deceased</option>
                </select>
            </div>
            <div class="w-48">
                <select name="ward" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="">All Wards</option>
                    <option value="General Ward" <?php echo $ward_filter === 'General Ward' ? 'selected' : ''; ?>>General Ward</option>
                    <option value="Male Ward" <?php echo $ward_filter === 'Male Ward' ? 'selected' : ''; ?>>Male Ward</option>
                    <option value="Female Ward" <?php echo $ward_filter === 'Female Ward' ? 'selected' : ''; ?>>Female Ward</option>
                    <option value="Pediatric Ward" <?php echo $ward_filter === 'Pediatric Ward' ? 'selected' : ''; ?>>Pediatric Ward</option>
                    <option value="Maternity Ward" <?php echo $ward_filter === 'Maternity Ward' ? 'selected' : ''; ?>>Maternity Ward</option>
                    <option value="NICU" <?php echo $ward_filter === 'NICU' ? 'selected' : ''; ?>>NICU</option>
                    <option value="ICU" <?php echo $ward_filter === 'ICU' ? 'selected' : ''; ?>>ICU</option>
                    <option value="HDU" <?php echo $ward_filter === 'HDU' ? 'selected' : ''; ?>>HDU</option>
                    <option value="Isolation Ward" <?php echo $ward_filter === 'Isolation Ward' ? 'selected' : ''; ?>>Isolation Ward</option>
                    <option value="Emergency Ward" <?php echo $ward_filter === 'Emergency Ward' ? 'selected' : ''; ?>>Emergency Ward</option>
                    <option value="Surgical Ward" <?php echo $ward_filter === 'Surgical Ward' ? 'selected' : ''; ?>>Surgical Ward</option>
                    <option value="Medical Ward" <?php echo $ward_filter === 'Medical Ward' ? 'selected' : ''; ?>>Medical Ward</option>
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
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <?php
        try {
            $db = Database::getInstance();
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM admissions WHERE status = 'Admitted'");
            $admitted = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM admissions WHERE status = 'Discharged' AND DATE(discharge_date) = CURDATE()");
            $discharged_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM admissions WHERE DATE(admission_date) = CURDATE()");
            $admitted_today = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM admissions");
            $total_admissions = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
        } catch (Exception $e) {
            $admitted = $discharged_today = $admitted_today = $total_admissions = 0;
        }
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Currently Admitted</p>
                    <p class="text-2xl font-bold text-rose-600"><?php echo $admitted; ?></p>
                </div>
                <div class="w-12 h-12 bg-rose-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-bed text-rose-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Admitted Today</p>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $admitted_today; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-plus text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Discharged Today</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $discharged_today; ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user-minus text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Admissions</p>
                    <p class="text-2xl font-bold text-gray-600"><?php echo $total_admissions; ?></p>
                </div>
                <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-users text-gray-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Admissions Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Admission #</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Ward/Bed</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Reason</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Expected Stay</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Admitted By</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Admission Date</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Discharge Date</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($admissions)): ?>
                    <tr>
                        <td colspan="10" class="py-8 text-center text-gray-500">
                            <i class="fas fa-procedures text-4xl mb-3 text-gray-300"></i>
                            <p>No admissions found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($admissions as $adm): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="font-mono text-sm text-gray-700"><?php echo htmlspecialchars($adm['admission_number']); ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($adm['first_name'] . ' ' . $adm['last_name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($adm['patient_id']); ?></p>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($adm['ward']); ?>
                                <?php if ($adm['bed_number']): ?>
                                <br><span class="text-xs text-gray-400"><?php echo htmlspecialchars($adm['bed_number']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars(substr($adm['admission_reason'], 0, 40)); ?><?php echo strlen($adm['admission_reason']) > 40 ? '...' : ''; ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($adm['expected_stay_days']); ?> days
                            </td>
                            <td class="py-3 px-4">
                                <?php 
                                $status_class = [
                                    'Admitted' => 'bg-green-100 text-green-700',
                                    'Discharged' => 'bg-blue-100 text-blue-700',
                                    'Transferred' => 'bg-purple-100 text-purple-700',
                                    'Deceased' => 'bg-red-100 text-red-700'
                                ];
                                $class = $status_class[$adm['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $class; ?>">
                                    <?php echo htmlspecialchars($adm['status']); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($adm['admitted_by_name'] ?? 'Unknown'); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">
                                <?php echo date('M j, Y g:i A', strtotime($adm['admission_date'])); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">
                                <?php echo $adm['discharge_date'] ? date('M j, Y g:i A', strtotime($adm['discharge_date'])) : '-'; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php if ($adm['status'] === 'Admitted'): ?>
                                <button type="button" onclick="openDischargeModal(<?php echo (int)$adm['id']; ?>)" class="px-3 py-1 bg-green-100 text-green-700 rounded-lg text-sm hover:bg-green-200">
                                    <i class="fas fa-user-minus mr-1"></i> Discharge
                                </button>
                                <?php endif; ?>
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
                    <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&ward=<?php echo urlencode($ward_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>&search=<?php echo urlencode($search); ?>" 
                       class="px-3 py-1 rounded <?php echo $page === $i ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Discharge Modal -->
<div id="dischargeModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
        <div id="dischargeModalBackdrop" class="fixed inset-0 bg-black/50"></div>
        <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
            <div class="modal-header-bg">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-xl bg-white flex items-center justify-center text-green-600"><i class="fas fa-user-minus text-xl"></i></span>
                    <div>
                        <h2 class="text-lg font-semibold text-white">Discharge Patient</h2>
                    </div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <input type="hidden" name="discharge_patient" value="1">
                <input type="hidden" name="admission_id" id="dischargeAdmissionId" value="">
                <div class="p-4">
                    <p class="text-gray-600 text-sm mb-4">Are you sure you want to discharge this patient?</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Discharge Summary / Notes</label>
                        <textarea name="discharge_summary" rows="3" placeholder="Brief summary of treatment and discharge instructions..." 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-4 py-3 border-t border-gray-100">
                    <button type="button" onclick="closeDischargeModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">Discharge Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openDischargeModal(admissionId) {
    document.getElementById('dischargeAdmissionId').value = admissionId;
    document.getElementById('dischargeModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeDischargeModal() {
    document.getElementById('dischargeModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('dischargeModalBackdrop').addEventListener('click', closeDischargeModal);
</script>

<?php
require_once '../includes/footer.php';
?>
