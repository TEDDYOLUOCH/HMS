<?php
/**
 * Hospital Management System - System Settings
 * Hospital configuration, departments, categories, and utilities
 */

// Start session
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin'], '../dashboard');

// Set page title
$page_title = 'System Settings';

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
        
        // Update hospital settings
        if (isset($_POST['save_hospital'])) {
            try {
                $settings = [
                    'hospital_name' => $_POST['hospital_name'],
                    'hospital_address' => $_POST['hospital_address'],
                    'hospital_phone' => $_POST['hospital_phone'],
                    'hospital_email' => $_POST['hospital_email']
                ];
                
                foreach ($settings as $key => $value) {
                    $stmt = $db->query("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (?, ?, 'general') 
                                         ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
                }
                
                // Log activity
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Settings Updated', ?, NOW())", [$_SESSION['user_id'], "Updated hospital settings"]);
                
                $message = 'Hospital settings saved successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Toggle maintenance mode
        if (isset($_POST['toggle_maintenance'])) {
            try {
                $maintenance = $_POST['maintenance_mode'] ?? 'off';
                $stmt = $db->query("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES ('maintenance_mode', ?, 'system') 
                                     ON DUPLICATE KEY UPDATE setting_value = ?", [$maintenance, $maintenance]);
                
                $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Maintenance', ?, NOW())", [$_SESSION['user_id'], "Set maintenance mode: $maintenance"]);
                
                $message = $maintenance === 'on' ? 'Maintenance mode enabled' : 'Maintenance mode disabled';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Add department
        if (isset($_POST['add_department'])) {
            try {
                $stmt = $db->query("INSERT INTO departments (name, description, status) VALUES (?, ?, 'active')", [$_POST['department_name'], $_POST['department_description'] ?? '']);
                
                $message = 'Department added successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Add drug category
        if (isset($_POST['add_drug_category'])) {
            try {
                $stmt = $db->query("INSERT INTO drug_categories (category_name, description) VALUES (?, ?)", [$_POST['category_name'], $_POST['category_description'] ?? '']);
                
                $message = 'Drug category added successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Add lab test type
        if (isset($_POST['add_test_type'])) {
            try {
                $stmt = $db->query("INSERT INTO lab_test_types (test_name, category, normal_range, unit, price) VALUES (?, ?, ?, ?, ?)", [
                    $_POST['test_name'],
                    $_POST['test_category'] ?? 'General',
                    $_POST['normal_range'] ?? '',
                    $_POST['test_unit'] ?? '',
                    $_POST['test_price'] ?? 0
                ]);
                
                $message = 'Test type added successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
        
        // Create database backup
        if (isset($_POST['create_backup'])) {
            try {
                $backup_file = 'database/hms_backup_' . date('Y-m-d_His') . '.sql';
                
                // Get all tables
                $tables = [];
                $result = $db->query("SHOW TABLES");
                while ($row = $result->fetch(PDO::FETCH_NUM)) {
                    $tables[] = $row[0];
                }
                
                $sql_content = "-- Hospital Management System Backup\n";
                $sql_content .= "-- Created: " . date('Y-m-d H:i:s') . "\n\n";
                
                foreach ($tables as $table) {
                    $result = $db->query("SELECT * FROM $table");
                    $sql_content .= "\n\n-- Table: $table\n";
                    $sql_content .= "DROP TABLE IF EXISTS $table;\n";
                    
                    $create = $db->query("SHOW CREATE TABLE $table")->fetch(PDO::FETCH_NUM);
                    $sql_content .= $create[1] . ";\n\n";
                    
                    while ($row = $result->fetch(PDO::FETCH_NUM)) {
                        $values = array_map(function($v) {
                            return is_null($v) ? 'NULL' : "'" . addslashes($v) . "'";
                        }, $row);
                        $sql_content .= "INSERT INTO $table VALUES (" . implode(', ', $values) . ");\n";
                    }
                }
                
                // Save backup file
                file_put_contents($backup_file, $sql_content);
                
                $message = "Backup created: $backup_file";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Backup error: ' . $e->GetMessage();
                $message_type = 'error';
            }
        }
    }
}

// Get settings
$settings = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

// Get departments
$departments = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM departments ORDER BY name");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get drug categories
$drug_categories = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM drug_categories ORDER BY category_name");
    $drug_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get lab test types
$test_types = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM lab_test_types ORDER BY test_name");
    $test_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                    <i class="fas fa-cogs text-indigo-600 mr-2"></i> System Settings
                </h1>
                <p class="text-gray-500 mt-1">Configure hospital information and system utilities</p>
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
    
    <!-- Settings Tabs -->
    <div class="mb-6">
        <div class="flex flex-wrap gap-2 border-b border-gray-200">
            <button onclick="showTab('hospital')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-gray-700 tab-btn active" data-tab="hospital">
                Hospital Info
            </button>
            <button onclick="showTab('maintenance')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-gray-700 tab-btn" data-tab="maintenance">
                Maintenance
            </button>
            <button onclick="showTab('departments')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-gray-700 tab-btn" data-tab="departments">
                Departments
            </button>
            <button onclick="showTab('drugs')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-gray-700 tab-btn" data-tab="drugs">
                Drug Categories
            </button>
            <button onclick="showTab('tests')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-gray-700 tab-btn" data-tab="tests">
                Test Types
            </button>
            <button onclick="showTab('backup')" class="px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-gray-700 tab-btn" data-tab="backup">
                Backup & Restore
            </button>
        </div>
    </div>
    
    <!-- Hospital Information -->
    <div id="hospital" class="tab-content">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-hospital text-gray-400 mr-2"></i> Hospital Information
            </h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="save_hospital" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Hospital Name</label>
                        <input type="text" name="hospital_name" value="<?php echo htmlspecialchars($settings['hospital_name'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="hospital_phone" value="<?php echo htmlspecialchars($settings['hospital_phone'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="hospital_address" rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"><?php echo htmlspecialchars($settings['hospital_address'] ?? ''); ?></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" name="hospital_email" value="<?php echo htmlspecialchars($settings['hospital_email'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                </div>
                
                <div class="pt-4">
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        <i class="fas fa-save mr-2"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Maintenance Mode -->
    <div id="maintenance" class="tab-content hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-tools text-gray-400 mr-2"></i> System Maintenance
            </h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="toggle_maintenance" value="1">
                
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div>
                        <h4 class="font-medium text-gray-800">Maintenance Mode</h4>
                        <p class="text-sm text-gray-500">When enabled, only admins can access the system</p>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <span class="px-3 py-1 rounded-full text-sm font-medium <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?>">
                            <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'Enabled' : 'Disabled'; ?>
                        </span>
                        
                        <button type="submit" name="maintenance_mode" value="<?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'off' : 'on'; ?>"
                                class="px-4 py-2 rounded-lg <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'; ?> text-white">
                            <?php echo ($settings['maintenance_mode'] ?? 'off') === 'on' ? 'Disable' : 'Enable'; ?>
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <h4 class="font-medium text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i> Warning
                </h4>
                <p class="text-sm text-yellow-700 mt-1">
                    Enabling maintenance mode will prevent all non-admin users from accessing the system.
                    Make sure to notify users before enabling this feature.
                </p>
            </div>
        </div>
    </div>
    
    <!-- Departments -->
    <div id="departments" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-building text-gray-400 mr-2"></i> Add Department
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="add_department" value="1">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department Name</label>
                        <input type="text" name="department_name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="department_description" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        Add Department
                    </button>
                </form>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Existing Departments</h3>
                </div>
                
                <?php if (empty($departments)): ?>
                <div class="p-6 text-center text-gray-500">
                    <p>No departments configured</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($departments as $dept): ?>
                    <div class="p-4 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($dept['name']); ?></p>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($dept['description'] ?? ''); ?></p>
                        </div>
                        <span class="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs">
                            <?php echo $dept['status'] ?? 'active'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Drug Categories -->
    <div id="drugs" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-pills text-gray-400 mr-2"></i> Add Drug Category
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="add_drug_category" value="1">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                        <input type="text" name="category_name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="category_description" rows="2"
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        Add Category
                    </button>
                </form>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-gray-800">Drug Categories</h3>
                </div>
                
                <?php if (empty($drug_categories)): ?>
                <div class="p-6 text-center text-gray-500">
                    <p>No categories configured</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($drug_categories as $cat): ?>
                    <div class="p-4">
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($cat['category_name']); ?></p>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Test Types -->
    <div id="tests" class="tab-content hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="font-semibold text-gray-800 mb-4">
                <i class="fas fa-flask text-gray-400 mr-2"></i> Add Lab Test Type
            </h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="add_test_type" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Test Name</label>
                        <input type="text" name="test_name" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="test_category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="Hematology">Hematology</option>
                            <option value="Biochemistry">Biochemistry</option>
                            <option value="Microbiology">Microbiology</option>
                            <option value="Parasitology">Parasitology</option>
                            <option value="General">General</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Normal Range</label>
                        <input type="text" name="normal_range" placeholder="e.g., 70-100"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit</label>
                        <input type="text" name="test_unit" placeholder="e.g., mg/dL"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" name="test_price" step="0.01" placeholder="0.00"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                </div>
                
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                    Add Test Type
                </button>
            </form>
        </div>
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 class="font-semibold text-gray-800">Lab Test Types</h3>
            </div>
            
            <?php if (empty($test_types)): ?>
            <div class="p-6 text-center text-gray-500">
                <p>No test types configured</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Test Name</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Category</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Normal Range</th>
                            <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Unit</th>
                            <th class="text-right py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Price</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($test_types as $test): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium text-gray-800"><?php echo htmlspecialchars($test['test_name']); ?></td>
                            <td class="py-3 px-4 text-gray-600"><?php echo htmlspecialchars($test['category']); ?></td>
                            <td class="py-3 px-4 text-gray-600"><?php echo htmlspecialchars($test['normal_range'] ?? '-'); ?></td>
                            <td class="py-3 px-4 text-gray-600"><?php echo htmlspecialchars($test['unit'] ?? '-'); ?></td>
                            <td class="py-3 px-4 text-right text-gray-800"><?php echo number_format($test['price'] ?? 0, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Backup & Restore -->
    <div id="backup" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-database text-gray-400 mr-2"></i> Database Backup
                </h3>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="create_backup" value="1">
                    
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <i class="fas fa-info-circle mr-2"></i>
                            Create a full backup of the database. The backup file will be saved in the database folder.
                        </p>
                    </div>
                    
                    <button type="submit" class="px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700">
                        <i class="fas fa-download mr-2"></i> Create Backup Now
                    </button>
                </form>
            </div>
            
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">
                    <i class="fas fa-upload text-gray-400 mr-2"></i> Restore Database
                </h3>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Warning: Restoring will overwrite all current data. Use with caution!
                        </p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Backup File</label>
                        <input type="file" name="backup_file" accept=".sql" required
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fas fa-upload mr-2"></i> Restore Database
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabId) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.add('hidden');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'border-primary-600', 'text-primary-600');
    });
    
    // Show selected tab
    document.getElementById(tabId).classList.remove('hidden');
    
    // Add active class to selected button
    document.querySelector(`[data-tab="${tabId}"]`).classList.add('active', 'border-primary-600', 'text-primary-600');
}
</script>

<?php
require_once '../includes/footer.php';
?>
