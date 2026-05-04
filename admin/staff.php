<?php
/**
 * Hospital Management System - Staff Management
 * Manage hospital staff records
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';
requireRole(['admin'], '../dashboard');

$page_title = 'Staff Management';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

$csrf_token = csrfToken();

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request.';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            $db = Database::getInstance();
            
            if ($action === 'add') {
                $staff_number = 'STF-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                
                $stmt = $db->prepare("INSERT INTO staff (staff_number, name, role, department, phone, email, date_joined, status, created_by) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $staff_number,
                    $_POST['name'],
                    $_POST['role'],
                    $_POST['department'],
                    $_POST['phone'] ?? null,
                    $_POST['email'] ?? null,
                    $_POST['date_joined'] ?? date('Y-m-d'),
                    $_POST['status'] ?? 'active',
                    $_SESSION['user_id']
                ]);
                
                logActivity('Added', 'HR', 'staff', null, "Added staff: {$_POST['name']} - {$_POST['role']}");
                
                $message = 'Staff member added successfully!';
                $message_type = 'success';
            }
            
            if ($action === 'update') {
                $stmt = $db->prepare("UPDATE staff SET name = ?, role = ?, department = ?, phone = ?, email = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['role'],
                    $_POST['department'],
                    $_POST['phone'] ?? null,
                    $_POST['email'] ?? null,
                    $_POST['status'],
                    $_POST['staff_id']
                ]);
                
                logActivity('Updated', 'HR', 'staff', $_POST['staff_id'], "Updated staff: {$_POST['name']}");
                
                $message = 'Staff information updated successfully!';
                $message_type = 'success';
            }
            
            if ($action === 'deactivate') {
                $stmt = $db->prepare("UPDATE staff SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$_POST['staff_id']]);
                
                logActivity('Deactivated', 'HR', 'staff', $_POST['staff_id'], "Deactivated staff");
                
                $message = 'Staff member deactivated.';
                $message_type = 'success';
            }
            
            if ($action === 'activate') {
                $stmt = $db->prepare("UPDATE staff SET status = 'active' WHERE id = ?");
                $stmt->execute([$_POST['staff_id']]);
                
                logActivity('Activated', 'HR', 'staff', $_POST['staff_id'], "Reactivated staff");
                
                $message = 'Staff member activated.';
                $message_type = 'success';
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get staff list
$staff = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT s.*, u.username as created_by_name 
                         FROM staff s 
                         LEFT JOIN users u ON s.created_by = u.id
                         ORDER BY s.status DESC, s.name ASC");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get existing user accounts for linking
$users = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT id, username, email, role FROM users WHERE is_active = 1 ORDER BY username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Roles and departments
$roles = ['Doctor', 'Nurse', 'Laboratory Technologist', 'Laboratory Scientist', 'Pharmacist', 'Theatre Officer', 'Administrator', 'Receptionist', 'Accountant', 'HR Officer', 'Driver', 'Cleaner', 'Security', 'Other'];
$departments = ['Administration', 'Clinical', 'Laboratory', 'Pharmacy', 'Nursing', 'Theatre', 'OPD', 'IPD', 'Radiology', 'Finance', 'HR', 'Maintenance', 'Security'];
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
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">
                            <i class="fas fa-users text-blue-600 mr-2"></i> Staff Management
                        </h1>
                        <p class="text-gray-500 mt-1">Manage hospital staff records</p>
                    </div>
                    <button onclick="openAddModal()" class="inline-flex items-center px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-plus mr-2"></i> Add Staff
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Total Staff</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($staff); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-blue-600">
                            <i class="fas fa-users text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <?php 
                    $active = count(array_filter($staff, fn($s) => $s['status'] === 'active'));
                    $inactive = count(array_filter($staff, fn($s) => $s['status'] === 'inactive'));
                ?>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Active</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $active; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-green-600">
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Inactive</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo $inactive; ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center text-red-600">
                            <i class="fas fa-user-times text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-500">Departments</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count(array_unique(array_filter(array_column($staff, 'department')))); ?></p>
                        </div>
                        <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-purple-600">
                            <i class="fas fa-building text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Staff Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-800">Staff List</h2>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff #</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($staff)): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">No staff records found.</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($staff as $s): ?>
                            <?php 
                                $status_class = $s['status'] === 'active' ? 'green' : ($s['status'] === 'on_leave' ? 'yellow' : 'red');
                            ?>
                            <tr class="hover:bg-gray-50 <?php echo $s['status'] !== 'active' ? 'bg-gray-50' : ''; ?>">
                                <td class="px-4 py-3 font-medium"><?php echo htmlspecialchars($s['staff_number']); ?></td>
                                <td class="px-4 py-3">
                                    <div>
                                        <span class="font-medium text-gray-900"><?php echo htmlspecialchars($s['name']); ?></span>
                                        <?php if ($s['email']): ?>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($s['email']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($s['role']); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($s['department'] ?? '-'); ?></td>
                                <td class="px-4 py-3"><?php echo htmlspecialchars($s['phone'] ?? '-'); ?></td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium bg-<?php echo $status_class; ?>-100 text-<?php echo $status_class; ?>-700">
                                        <?php echo ucfirst($s['status']); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button onclick="openEditModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($s['role']); ?>', '<?php echo htmlspecialchars($s['department'] ?? ''); ?>', '<?php echo htmlspecialchars($s['phone'] ?? ''); ?>', '<?php echo htmlspecialchars($s['email'] ?? ''); ?>', '<?php echo $s['status']; ?>')" 
                                                class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($s['status'] === 'active'): ?>
                                        <button onclick="openDeactivateModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>')" 
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg" title="Deactivate">
                                            <i class="fas fa-user-times"></i>
                                        </button>
                                        <?php else: ?>
                                        <button onclick="openActivateModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>')" 
                                                class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg" title="Activate">
                                            <i class="fas fa-user-check"></i>
                                        </button>
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
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="addModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeAddModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden flex flex-col max-h-[90vh]">
                <div class="modal-header-bg">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">Add Staff Member</h2>
                        <button onclick="closeAddModal()" class="modal-close-btn p-1 rounded-full">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" class="p-4 space-y-4 overflow-y-auto flex-1">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                            <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role; ?>"><?php echo $role; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" name="phone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date Joined</label>
                        <input type="date" name="date_joined" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Add Staff
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Staff Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeEditModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden flex flex-col max-h-[90vh]">
                <div class="modal-header-bg">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-white">Edit Staff</h2>
                        <button onclick="closeEditModal()" class="modal-close-btn p-1 rounded-full">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                </div>
                <form method="POST" class="p-4 space-y-4 overflow-y-auto flex-1">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="staff_id" id="editStaffId">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="name" id="editName" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                            <select name="role" id="editRole" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <?php foreach ($roles as $role): ?>
                                <option value="<?php echo $role; ?>"><?php echo $role; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <select name="department" id="editDepartment" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="text" name="phone" id="editPhone" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" id="editEmail" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="editStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Update Staff
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Deactivate Modal -->
    <div id="deactivateModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeDeactivateModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
                <div class="modal-header-bg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                        <h2 class="text-lg font-semibold text-white">Deactivate Staff</h2>
                    </div>
                </div>
                <form method="POST" class="p-4">
                    <input type="hidden" name="action" value="deactivate">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="staff_id" id="deactivateStaffId">
                    
                    <p class="text-center py-4">Are you sure you want to deactivate:<br><strong id="deactivateStaffName"></strong>?</p>
                    
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="closeDeactivateModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                            Deactivate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Activate Modal -->
    <div id="activateModal" class="hidden fixed inset-0 z-[9999] overflow-y-auto" aria-modal="true">
        <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
            <div class="fixed inset-0 bg-black/50" onclick="closeActivateModal()"></div>
            <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
                <div class="modal-header-bg">
                    <div class="flex items-center gap-3">
                        <i class="fas fa-check-circle text-white text-xl"></i>
                        <h2 class="text-lg font-semibold text-white">Activate Staff</h2>
                    </div>
                </div>
                <form method="POST" class="p-4">
                    <input type="hidden" name="action" value="activate">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="staff_id" id="activateStaffId">
                    
                    <p class="text-center py-4">Are you sure you want to activate:<br><strong id="activateStaffName"></strong>?</p>
                    
                    <div class="flex justify-end gap-3 mt-4">
                        <button type="button" onclick="closeActivateModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                            Activate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function openAddModal() { document.getElementById('addModal').classList.remove('hidden'); }
    function closeAddModal() { document.getElementById('addModal').classList.add('hidden'); }
    
    function openEditModal(id, name, role, dept, phone, email, status) {
        document.getElementById('editStaffId').value = id;
        document.getElementById('editName').value = name;
        document.getElementById('editRole').value = role;
        document.getElementById('editDepartment').value = dept;
        document.getElementById('editPhone').value = phone;
        document.getElementById('editEmail').value = email;
        document.getElementById('editStatus').value = status;
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }
    
    function openDeactivateModal(id, name) {
        document.getElementById('deactivateStaffId').value = id;
        document.getElementById('deactivateStaffName').textContent = name;
        document.getElementById('deactivateModal').classList.remove('hidden');
    }
    function closeDeactivateModal() { document.getElementById('deactivateModal').classList.add('hidden'); }
    
    function openActivateModal(id, name) {
        document.getElementById('activateStaffId').value = id;
        document.getElementById('activateStaffName').textContent = name;
        document.getElementById('activateModal').classList.remove('hidden');
    }
    function closeActivateModal() { document.getElementById('activateModal').classList.add('hidden'); }
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>
