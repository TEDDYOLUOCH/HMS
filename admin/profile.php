<?php
/**
 * Hospital Management System - User Profile
 * View and edit current user's profile
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Set page title
$page_title = 'My Profile';
$page_description = 'View and manage your account settings.';

// Include database and header
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Get current user info from session
$user_id = $_SESSION['user_id'] ?? 0;
$message = '';
$message_type = '';

// Handle profile image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_image'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/profiles/';
            $file_ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($file_ext, $allowed_exts)) {
                $message = 'Invalid file type. Allowed: jpg, jpeg, png, gif, webp';
                $message_type = 'error';
            } else {
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    try {
                        $db = Database::getInstance();
                        
                        // Get old image to delete
                        $stmt = $db->query("SELECT profile_image FROM users WHERE id = ?", [$user_id]);
                        $old_user = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($old_user && $old_user['profile_image'] && file_exists($upload_dir . $old_user['profile_image'])) {
                            unlink($upload_dir . $old_user['profile_image']);
                        }
                        
                        // Update with new image
                        $stmt = $db->query("UPDATE users SET profile_image = ?, updated_at = NOW() WHERE id = ?", [$new_filename, $user_id]);
                        
                        $message = 'Profile image uploaded successfully!';
                        $message_type = 'success';
                    } catch (Exception $e) {
                        $message = 'Error saving image: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = 'Failed to upload image';
                    $message_type = 'error';
                }
            }
        } else {
            $message = 'Please select an image to upload';
            $message_type = 'error';
        }
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            
            // Check if email is already taken by another user
            $stmt = $db->query("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id]);
            if ($stmt->fetch()) {
                $message = 'Email already in use by another user';
                $message_type = 'error';
            } else {
                // Update profile
                $stmt = $db->query("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?", [$full_name, $email, $phone, $user_id]);
                
                // Update session
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            // Verify current password
            $stmt = $db->query("SELECT password_hash FROM users WHERE id = ?", [$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($current_password, $user['password_hash'])) {
                $message = 'Current password is incorrect';
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match';
                $message_type = 'error';
            } elseif (strlen($new_password) < 6) {
                $message = 'Password must be at least 6 characters';
                $message_type = 'error';
            } else {
                // Update password
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->query("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?", [$password_hash, $user_id]);
                
                $message = 'Password changed successfully!';
                $message_type = 'success';
            }
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch current user data
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM users WHERE id = ?", [$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $user = [
        'username' => $_SESSION['username'] ?? 'user',
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email' => $_SESSION['email'] ?? '',
        'phone' => '',
        'role' => $_SESSION['user_role'] ?? 'guest',
        'department' => '',
        'created_at' => '',
        'last_login' => ''
    ];
}

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
                    <i class="fas fa-user-circle text-primary-600 mr-2"></i> My Profile
                </h1>
                <p class="text-gray-500 mt-1">View and manage your account settings</p>
            </div>
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Profile Card -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden sticky top-24">
                <!-- Avatar Section -->
                <div class="p-6 text-center bg-gradient-to-br from-primary-50 to-purple-50">
                    <div class="relative inline-block">
                        <?php if (!empty($user['profile_image']) && file_exists('../assets/images/profiles/' . $user['profile_image'])): ?>
                        <img src="../assets/images/profiles/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                             alt="Profile" class="w-24 h-24 rounded-full object-cover shadow-lg">
                        <?php else: ?>
                        <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary-600 to-primary-500 flex items-center justify-center text-white text-4xl font-bold shadow-lg">
                            <?php echo strtoupper(substr($user['full_name'] ?? $_SESSION['full_name'] ?? 'U', 0, 2)); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <h2 class="mt-4 text-xl font-bold text-gray-900"><?php echo htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'] ?? 'User'); ?></h2>
                    <p class="text-sm text-gray-500 capitalize"><?php echo htmlspecialchars($user['role'] ?? $_SESSION['user_role'] ?? 'user'); ?></p>
                    <span class="inline-flex items-center mt-2 px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                        <span class="w-2 h-2 mr-1.5 rounded-full bg-green-500"></span>
                        Active
                    </span>
                </div>

                <!-- Image Upload Form -->
                <div class="px-6 pb-6">
                    <form method="POST" enctype="multipart/form-data" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <label for="profile_image" class="block">
                            <span class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-lg cursor-pointer hover:bg-primary-700 transition-colors inline-flex items-center gap-2">
                                <i class="fas fa-camera"></i> Change Photo
                            </span>
                            <input type="file" id="profile_image" name="profile_image" accept="image/*" class="hidden" onchange="this.form.submit()">
                            <input type="hidden" name="upload_image" value="1">
                        </label>
                        <p class="text-xs text-gray-500 mt-2">JPG, PNG, GIF, WebP (max 2MB)</p>
                    </form>
                </div>

                <!-- Info List -->
                <div class="p-6 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Username</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username'] ?? $_SESSION['username'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Email</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Phone</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas fa-building"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Department</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['department'] ?? 'Not assigned'); ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                            <i class="fas fa-sign-in-alt"></i>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Last Login</p>
                            <p class="text-sm font-medium text-gray-900"><?php echo $user['last_login'] ? date('M d, Y g:i A', strtotime($user['last_login'])) : 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Forms -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Edit Profile Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-user-edit text-primary-600"></i>
                        Edit Profile
                    </h3>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                            <input type="text" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name'] ?? $_SESSION['full_name'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all outline-none"
                                   required>
                        </div>
                        <div>
                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" id="username" 
                                   value="<?php echo htmlspecialchars($user['username'] ?? $_SESSION['username'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed"
                                   disabled>
                            <p class="text-xs text-gray-400 mt-1">Username cannot be changed</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                            <input type="email" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all outline-none"
                                   required>
                        </div>
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                            <input type="tel" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all outline-none">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <input type="text" id="role" 
                                   value="<?php echo htmlspecialchars(ucfirst($user['role'] ?? $_SESSION['user_role'] ?? 'user')); ?>"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed"
                                   disabled>
                            <p class="text-xs text-gray-400 mt-1">Contact admin to change role</p>
                        </div>
                        <div>
                            <label for="department" class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                            <input type="text" id="department" 
                                   value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-200 bg-gray-50 text-gray-500 cursor-not-allowed"
                                   disabled>
                            <p class="text-xs text-gray-400 mt-1">Contact admin to change department</p>
                        </div>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" name="update_profile" 
                                class="px-6 py-2.5 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-all flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Change Password Form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                        <i class="fas fa-lock text-primary-600"></i>
                        Change Password
                    </h3>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div>
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" id="current_password" name="current_password" 
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all outline-none"
                               required>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                            <input type="password" id="new_password" name="new_password" 
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all outline-none"
                                   required minlength="6">
                            <p class="text-xs text-gray-400 mt-1">Minimum 6 characters</p>
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all outline-none"
                                   required>
                        </div>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" name="change_password" 
                                class="px-6 py-2.5 bg-primary-600 text-white font-medium rounded-lg hover:bg-primary-700 transition-all flex items-center gap-2">
                            <i class="fas fa-key"></i>
                            Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
