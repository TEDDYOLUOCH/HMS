<?php
/**
 * Hospital Management System - User Management
 * Create, edit, deactivate users with department assignment
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin'], '../dashboard');

// Set page title
$page_title = 'Manage Users';
$page_description = 'Add, edit, and manage system users and their roles.';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();
        
        // Create new user
        if (isset($_POST['create_user'])) {
            try {
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $full_name = trim($_POST['full_name']);
                $role = $_POST['role'];
                $department = $_POST['department'] ?? '';
                $password = $_POST['password'];
                
                // Check if username exists
                $stmt = $db->query("SELECT id FROM users WHERE username = ?", [$username]);
                if ($stmt->fetch()) {
                    $message = 'Username already exists';
                    $message_type = 'error';
                } else {
                    // Check initials uniqueness
                    $initials = strtoupper(substr($full_name, 0, 2));
                    $stmt = $db->query("SELECT id FROM users WHERE UPPER(SUBSTRING(full_name, 1, 2)) = ?", [$initials]);
                    if ($stmt->fetch()) {
                        $message = 'Warning: Similar initials exist. User created but please verify.';
                        $message_type = 'warning';
                    }
                    
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    
                    $stmt = $db->query("INSERT INTO users (username, email, full_name, initials, role, department, password_hash, status, created_at) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())", [$username, $email, $full_name, $initials, $role, $department, $password_hash]);
                    
                    // Log activity
                    $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'User Created', ?, NOW())", [$_SESSION['user_id'], "Created user: $username with role: $role, dept: $department"]);
                    
                    if ($message_type !== 'warning') {
                        $message = 'User created successfully!';
                        $message_type = 'success';
                    }
                }
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Update user
        if (isset($_POST['update_user'])) {
            try {
                $user_id = $_POST['user_id'];
                $full_name = trim($_POST['full_name']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $department = $_POST['department'] ?? '';
                $status = $_POST['status'];
                
                $stmt = $db->query("UPDATE users SET full_name = ?, email = ?, role = ?, department = ?, status = ? WHERE id = ?", [$full_name, $email, $role, $department, $status, $user_id]);
                
                // Log activity
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'User Updated', ?, NOW())", [$_SESSION['user_id'], "Updated user ID: $user_id - Role: $role, Dept: $department, Status: $status"]);
                
                $message = 'User updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Reset password
        if (isset($_POST['reset_password'])) {
            try {
                $user_id = $_POST['user_id'];
                $password_type = $_POST['password_type'] ?? 'manual';
                
                if ($password_type === 'random') {
                    $new_password = bin2hex(random_bytes(4)); // 8 char random
                } else {
                    $new_password = $_POST['new_password'];
                }
                
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->query("UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?", [$password_hash, $user_id]);
                
                // Log activity
                $details = $password_type === 'random' ? "Reset password for user ID: $user_id (random generated)" : "Reset password for user ID: $user_id";
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Password Reset', ?, NOW())", [$_SESSION['user_id'], $details]);
                
                if ($password_type === 'random') {
                    $message = "Password reset! New password: $new_password (user must change on login)";
                } else {
                    $message = 'Password reset successfully!';
                }
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Deactivate/Activate user
        if (isset($_POST['toggle_status'])) {
            try {
                $user_id = $_POST['user_id'];
                $new_status = $_POST['new_status'];
                
                $stmt = $db->query("UPDATE users SET status = ? WHERE id = ?", [$new_status, $user_id]);
                
                // Log activity
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'User Status', ?, NOW())", [$_SESSION['user_id'], "Changed status to $new_status for user ID: $user_id"]);
                
                $message = "User " . ($new_status === 'active' ? 'activated' : 'deactivated') . " successfully!";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
        
        // Delete user
        if (isset($_POST['delete_user'])) {
            try {
                $user_id = $_POST['user_id'];
                
                // Get username for logging
                $stmt = $db->query("SELECT username, full_name FROM users WHERE id = ?", [$user_id]);
                $user_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Delete the user
                $stmt = $db->query("DELETE FROM users WHERE id = ?", [$user_id]);
                
                // Log activity
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'User Deleted', ?, NOW())", [$_SESSION['user_id'], "Deleted user: " . ($user_to_delete['full_name'] ?? $user_to_delete['username']) . " (ID: $user_id)"]);
                
                $message = 'User deleted successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get users with activity summary - FIXED SQL INJECTION
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$params = [];
$where_clauses = [];

if ($role_filter) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
}
if ($status_filter) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = empty($where_clauses) ? "1=1" : implode(" AND ", $where_clauses);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$users = [];
try {
    $db = Database::getInstance();
    
    // Get total count
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE $where_sql");
    if (!empty($params)) {
        $count_stmt->execute($params);
    } else {
        $count_stmt->execute();
    }
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $per_page);
    
    $stmt = $db->prepare("SELECT id, username, full_name, email, role, department, status, last_login, created_at FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Only pass params if there are filter values
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log user count
    error_log("Users fetched: " . count($users));
    foreach($users as $u) { error_log("User: " . $u['username'] . " - " . $u['full_name']); }
    
    // Get activity counts for each user in the last 30 days
    foreach ($users as &$user) {
        $user_id = $user['id'];
        $activity_stmt = $db->query("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)", [$user_id]);
        $activity_row = $activity_stmt->fetch(PDO::FETCH_ASSOC);
        $user['activity_count'] = $activity_row['count'] ?? 0;
    }
    unset($user); // CRITICAL: Break the reference to prevent corruption!
    
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

// Get statistics
$stats = ['total' => 0, 'active' => 0, 'inactive' => 0];

try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}

// Get departments
$departments = [];
try {
    $db = Database::getInstance();
    // Get departments from departments table
    $stmt = $db->query("SELECT name as department FROM departments WHERE status = 'active' ORDER BY name");
    $dept_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get unique departments from users
    $stmt = $db->query("SELECT DISTINCT department FROM users WHERE department != '' ORDER BY department");
    $user_depts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge both lists and remove duplicates
    $all_depts = array_merge($dept_list, $user_depts);
    $seen = [];
    foreach ($all_depts as $d) {
        $dept = $d['department'] ?? $d['name'];
        if ($dept && !isset($seen[$dept])) {
            $departments[] = ['department' => $dept];
            $seen[$dept] = true;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching departments: " . $e->getMessage());
}

// Generate CSRF token
$csrf_token = csrfToken();

// Get user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    try {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$_GET['edit']]);
        $edit_user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching edit user: " . $e->getMessage());
    }
}
?>

<!-- Page Content -->
<div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-users-cog text-indigo-600 mr-2"></i> Manage Users
                </h1>
                <p class="text-gray-500 mt-1">Create, edit, and manage user accounts</p>
            </div>
            <button onclick="showCreateModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                <i class="fas fa-plus mr-2"></i> Add New User
            </button>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert-auto-hide rounded-lg p-4 mb-6 <?php echo $message_type === 'success' ? 'bg-green-50 border border-green-200' : ($message_type === 'warning' ? 'bg-yellow-50 border border-yellow-200' : 'bg-red-50 border border-red-200'); ?>">
        <div class="flex items-center">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle text-green-500' : ($message_type === 'warning' ? 'fa-exclamation-triangle text-yellow-500' : 'fa-exclamation-circle text-red-500'); ?> mr-3"></i>
            <p class="<?php echo $message_type === 'success' ? 'text-green-800' : ($message_type === 'warning' ? 'text-yellow-800' : 'text-red-800'); ?>"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Total Users</span>
                <i class="fas fa-users text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Active</span>
                <i class="fas fa-check-circle text-green-500"></i>
            </div>
            <p class="text-2xl font-bold text-green-600"><?php echo $stats['active']; ?></p>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm text-gray-500">Inactive</span>
                <i class="fas fa-ban text-red-500"></i>
            </div>
            <p class="text-2xl font-bold text-red-600"><?php echo $stats['inactive']; ?></p>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-filter text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">Filters:</span>
            </div>
            
            <select onchange="window.location.href = '?role=' + this.value + '&status=<?php echo urlencode($status_filter); ?>'" 
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="doctor" <?php echo $role_filter === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
                <option value="nurse" <?php echo $role_filter === 'nurse' ? 'selected' : ''; ?>>Nurse</option>
                <option value="pharmacist" <?php echo $role_filter === 'pharmacist' ? 'selected' : ''; ?>>Pharmacist</option>
                <option value="lab_technologist" <?php echo $role_filter === 'lab_technologist' ? 'selected' : ''; ?>>Laboratory Technologist</option>
                <option value="lab_scientist" <?php echo $role_filter === 'lab_scientist' ? 'selected' : ''; ?>>Laboratory Scientist</option>
                <option value="theatre" <?php echo $role_filter === 'theatre' ? 'selected' : ''; ?>>Theatre</option>
            </select>
            
            <select onchange="window.location.href = '?status=' + this.value + '&role=<?php echo urlencode($role_filter); ?>'" 
                    class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
            
            <?php if ($role_filter || $status_filter): ?>
            <a href="manage_users" class="px-3 py-2 text-sm text-gray-600 hover:text-gray-800">
                <i class="fas fa-times mr-1"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">User</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Department</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Role</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Activity (30d)</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Last Login</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="py-8 text-center text-gray-500">
                            <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                            <p>No users found</p>
                        </td>
                    </tr>
                    <?php else: foreach ($users as $user): 
                        $role_colors = [
                            'admin' => 'bg-red-100 text-red-700',
                            'doctor' => 'bg-blue-100 text-blue-700',
                            'nurse' => 'bg-green-100 text-green-700',
                            'pharmacist' => 'bg-purple-100 text-purple-700',
                            'lab_technologist' => 'bg-yellow-100 text-yellow-700',
                            'lab_scientist' => 'bg-yellow-100 text-yellow-700',
                            'theatre' => 'bg-pink-100 text-pink-700'
                        ];
                        $role_color = $role_colors[$user['role']] ?? 'bg-gray-100 text-gray-700';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium text-gray-600">
                                    <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?php echo htmlspecialchars($user['department']) ?: '-'; ?>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $role_color; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                <?php echo ucfirst($user['status'] ?? 'inactive'); ?>
                            </span>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-primary-600 h-1.5 rounded-full" style="width: <?php echo min(($user['activity_count'] ?? 0) * 2, 100); ?>%"></div>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo $user['activity_count'] ?? 0; ?></span>
                            </div>
                        </td>
                        <td class="py-3 px-4 text-sm text-gray-600">
                            <?php echo $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex items-center gap-2">
                                <button onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>', '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['department'] ?? '', ENT_QUOTES); ?>', '<?php echo $user['status'] ?? 'inactive'; ?>')"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button onclick="resetPassword(<?php echo $user['id']; ?>)"
                                        class="p-2 text-orange-600 hover:bg-orange-50 rounded" title="Reset Password">
                                    <i class="fas fa-key"></i>
                                </button>
                                
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo ($user['status'] ?? 'inactive') === 'active' ? 'inactive' : 'active'; ?>">
                                    <button type="submit" name="toggle_status" 
                                            class="p-2 <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'text-red-600 hover:bg-red-50' : 'text-green-600 hover:bg-green-50'; ?> rounded"
                                            title="<?php echo ($user['status'] ?? 'inactive') === 'active' ? 'Deactivate' : 'Activate'; ?>"
                                            onclick="return confirm('Are you sure you want to <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'deactivate' : 'activate'; ?> this user?')">
                                        <i class="fas <?php echo ($user['status'] ?? 'inactive') === 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                                    </button>
                                </form>
                                
                                <form method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" 
                                            class="p-2 text-red-600 hover:bg-red-50 rounded"
                                            title="Delete User"
                                            onclick="return confirm('Are you sure you want to DELETE this user? This action cannot be undone!')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-4 px-4 py-3 bg-gray-50 rounded-lg">
            <div class="text-sm text-gray-600">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> users
            </div>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" class="px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" class="px-3 py-1.5 rounded-lg <?php echo $i === $page ? 'bg-brand-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-100'; ?> text-sm">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" class="px-3 py-1.5 rounded-lg bg-white border border-gray-300 text-gray-700 hover:bg-gray-100 text-sm">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create User Modal -->
<div id="createModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-md w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Create New User</h3>
                <button type="button" onclick="closeCreateModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="create_user" value="1">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username <span class="text-red-500">*</span></label>
                <input type="text" name="username" required pattern="[a-zA-Z0-9_]+"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <p class="text-xs text-gray-500 mt-1">Letters, numbers, underscores only</p>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                <input type="text" name="full_name" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role <span class="text-red-500">*</span></label>
                    <select name="role" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="lab_technologist">Laboratory Technologist</option>
                        <option value="lab_scientist">Laboratory Scientist</option>
                        <option value="theatre">Theatre Staff</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" name="department" list="dept_list"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <datalist id="dept_list">
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo htmlspecialchars($d['department']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password <span class="text-red-500">*</span></label>
                <input type="password" name="password" required minlength="6"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-md w-full flex flex-col overflow-hidden">
        <div class="modal-banner"></div>
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Edit User</h3>
                <button type="button" onclick="closeEditModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="user_id" id="editUserId" value="">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="full_name" id="editFullName" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" id="editEmail"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select name="role" id="editRole" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="lab_technologist">Laboratory Technologist</option>
                        <option value="lab_scientist">Laboratory Scientist</option>
                        <option value="theatre">Theatre Staff</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" name="department" id="editDepartment" list="dept_list_edit"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <datalist id="dept_list_edit">
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo htmlspecialchars($d['department']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" id="editStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 modal-overlay overflow-y-auto p-3 sm:p-4">
    <div class="modal-dialog bg-white rounded-xl max-w-md w-full flex flex-col overflow-hidden">
        <div class="modal-header-bg">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-white">Reset Password</h3>
                <button type="button" onclick="closeResetModal()" class="text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times text-xl"></i></button>
            </div>
        </div>
        <form method="POST" class="modal-dialog-content p-4 sm:p-6 space-y-4 overflow-y-auto">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="user_id" id="resetUserId" value="">
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password Type</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2">
                        <input type="radio" name="password_type" value="manual" checked class="text-primary-600">
                        <span class="text-sm text-gray-700">Manual password</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="password_type" value="random" class="text-primary-600">
                        <span class="text-sm text-gray-700">Generate random password</span>
                    </label>
                </div>
            </div>
            
            <div id="manualPassword">
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-red-500">*</span></label>
                <input type="password" name="new_password" minlength="6"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
            </div>
            
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-info-circle mr-1"></i>
                    User will be required to change password on next login.
                </p>
            </div>
            
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <button type="button" onclick="closeResetModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700">
                    Reset Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
}

function editUser(id, fullName, email, role, department, status) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editFullName').value = fullName;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = role;
    document.getElementById('editDepartment').value = department;
    document.getElementById('editStatus').value = status;
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function resetPassword(id) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetModal').classList.remove('hidden');
}

function closeResetModal() {
    document.getElementById('resetModal').classList.add('hidden');
}

// Toggle password field based on selection
document.querySelectorAll('input[name="password_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const manualField = document.getElementById('manualPassword');
        if (this.value === 'random') {
            manualField.style.display = 'none';
            manualField.querySelector('input').removeAttribute('required');
        } else {
            manualField.style.display = 'block';
            manualField.querySelector('input').setAttribute('required', 'required');
        }
    });
});
</script>

<?php
require_once '../includes/footer.php';
?>