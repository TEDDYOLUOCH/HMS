<?php
/**
 * Hospital Management System - View Patients
 * Server-side rendered patient list with search and filters
 */

// Start session and output buffering
ob_start();
session_start();

// Check authentication
require_once '../includes/auth_check.php';

// Check role permission
requireRole(['admin', 'doctor', 'nurse'], '../dashboard');

// Set page title
$page_title = 'Patient Records';
$page_description = 'Manage and view all patient information in one place.';

// Include database and header
require_once '../config/database.php';
require_once '../includes/activity_logger.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

// Handle actions
$message = '';
$message_type = '';

// Success message from add redirect
if (isset($_GET['added']) && isset($_GET['patient_id'])) {
    $message = 'Patient registered successfully. ID: ' . htmlspecialchars($_GET['patient_id']);
    $message_type = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCsrf($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $message_type = 'error';
    } else {
        $db = Database::getInstance();

        // Add new patient (from modal)
        if (isset($_POST['submit_patient'])) {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');
            $other_names = trim($_POST['other_names'] ?? '');
            $gender = $_POST['gender'] ?? '';
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $phone = trim($_POST['phone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $emergency_name = trim($_POST['emergency_name'] ?? '');
            $emergency_phone = trim($_POST['emergency_phone'] ?? '');
            $emergency_relationship = trim($_POST['emergency_relationship'] ?? '');
            $blood_group = $_POST['blood_group'] ?? '';
            $allergies = trim($_POST['allergies'] ?? '');
            $medical_history = trim($_POST['medical_history'] ?? '');
            $status = $_POST['status'] ?? 'active';
            $department = $_POST['department'] ?? 'OPD'; // Default to OPD

            $errors = [];
            if (empty($first_name)) $errors[] = 'First name is required';
            if (empty($last_name)) $errors[] = 'Last name is required';
            if (empty($gender)) $errors[] = 'Gender is required';
            if (empty($date_of_birth)) $errors[] = 'Date of birth is required';
            if (empty($phone)) $errors[] = 'Phone number is required';
            if (empty($department)) $errors[] = 'Department is required';

            if (empty($errors)) {
                $max_attempts = 3;
                $attempt = 0;
                $success = false;
                
                while ($attempt < $max_attempts && !$success) {
                    try {
                        $attempt++;
                        // Use transaction with locking to prevent race conditions
                        $db->beginTransaction();
                        
                        $stmt = $db->query("SELECT MAX(CAST(SUBSTRING(patient_id, 12) AS UNSIGNED)) as max_id FROM patients WHERE patient_id LIKE 'SIWOT-" . date('Y') . "-%' FOR UPDATE");
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        $next_num = ($result['max_id'] ?? 0) + 1;
                        $patient_id = 'SIWOT-' . date('Y') . '-' . str_pad($next_num, 5, '0', STR_PAD_LEFT);

                        $stmt = $db->prepare("SELECT id FROM patients WHERE first_name = ? AND last_name = ? AND date_of_birth = ? AND phone_primary = ?");
                        $stmt->execute([$first_name, $last_name, $date_of_birth, $phone]);
                        if ($stmt->fetch()) {
                            $db->rollBack();
                            $message = 'A patient with similar details already exists';
                            $message_type = 'error';
                            $success = true;
                        } else {
                            $stmt = $db->prepare("INSERT INTO patients (patient_id, first_name, last_name, other_names, gender, date_of_birth, phone_primary, email, address, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship, blood_group, allergies, chronic_conditions, is_active, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([
                                $patient_id, $first_name, $last_name, $other_names, $gender, $date_of_birth,
                                $phone, $email, $address, $emergency_name, $emergency_phone,
                                $emergency_relationship, $blood_group, $allergies, $medical_history,
                                ($status === 'active' ? 1 : 0), $_SESSION['user_id']
                            ]);
                            
                            $patient_db_id = $db->lastInsertId();
                            
                            // Create visit based on department
                            $visit_type = 'New';
                            $initial_status = 'Waiting';
                            
                            $stmt = $db->prepare("INSERT INTO visits (patient_id, visit_date, visit_time, department, visit_type, status, priority, created_by) VALUES (?, NOW(), NOW(), ?, ?, ?, 'Normal', ?)");
                            $stmt->execute([$patient_db_id, $department, $visit_type, $initial_status, $_SESSION['user_id']]);
                            
                            $visit_id = $db->lastInsertId();
                            
                            $db->commit();
                            
                            // Log activity
                            logActivity('Created', 'Patients', 'patients', $patient_db_id, "Registered new patient: $first_name $last_name (ID: $patient_id) - Visit created in $department department");
                            
                            header('Location: view_patients.php?added=1&patient_id=' . urlencode($patient_id) . '&visit_id=' . $visit_id);
                            exit;
                        }
                    } catch (PDOException $e) {
                        $db->rollBack();
                        // Check if it's a duplicate key error (error code 1062)
                        if ($e->getCode() == 1062 && $attempt < $max_attempts) {
                            // Retry with new ID
                            continue;
                        }
                        $message = 'Error saving patient: ' . $e->getMessage();
                        $message_type = 'error';
                        $success = true;
                    }
                }
            } else {
                $message = implode('. ', $errors);
                $message_type = 'error';
            }
        }

        // Update patient (from modal)
        if (isset($_POST['update_patient'])) {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            if ($patient_id) {
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $other_names = trim($_POST['other_names'] ?? '');
                $gender = $_POST['gender'] ?? '';
                $date_of_birth = $_POST['date_of_birth'] ?? '';
                $phone = trim($_POST['phone'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $address = trim($_POST['address'] ?? '');
                $emergency_name = trim($_POST['emergency_name'] ?? '');
                $emergency_phone = trim($_POST['emergency_phone'] ?? '');
                $emergency_relationship = $_POST['emergency_relationship'] ?? '';
                $blood_group = $_POST['blood_group'] ?? '';
                $allergies = trim($_POST['allergies'] ?? '');
                $medical_history = trim($_POST['medical_history'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                $errors = [];
                if (empty($first_name)) $errors[] = 'First name is required';
                if (empty($last_name)) $errors[] = 'Last name is required';
                if (empty($gender)) $errors[] = 'Gender is required';
                if (empty($date_of_birth)) $errors[] = 'Date of birth is required';
                if (empty($phone)) $errors[] = 'Phone number is required';
                
                if (empty($errors)) {
                    try {
                        $db = Database::getInstance();
                        $stmt = $db->prepare("UPDATE patients SET 
                            first_name = ?, last_name = ?, other_names = ?, gender = ?, date_of_birth = ?,
                            phone_primary = ?, email = ?, address = ?, emergency_contact_name = ?, 
                            emergency_contact_phone = ?, emergency_contact_relationship = ?, blood_group = ?,
                            allergies = ?, chronic_conditions = ?, is_active = ?, 
                            updated_at = NOW() 
                            WHERE id = ?");
                        $stmt->execute([
                            $first_name, $last_name, $other_names, $gender, $date_of_birth,
                            $phone, $email, $address, $emergency_name,
                            $emergency_phone, $emergency_relationship, $blood_group,
                            $allergies, $medical_history, ($status === 'active' ? 1 : 0),
                            $patient_id
                        ]);
                        
                        logActivity('Updated', 'Patients', 'patients', $patient_id, "Updated patient: $first_name $last_name");
                        
                        $message = 'Patient updated successfully!';
                        $message_type = 'success';
                    } catch (PDOException $e) {
                        $message = 'Error updating patient: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $message = implode('. ', $errors);
                    $message_type = 'error';
                }
            }
        }

        // Delete (archive) patient
        if (isset($_POST['delete_patient'])) {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            if ($patient_id) {
                try {
                    $stmt = $db->prepare("SELECT COUNT(*) as visits FROM visits WHERE patient_id = ? AND DATE(visit_date) = CURDATE() AND status != 'Cancelled'");
                    $stmt->execute([$patient_id]);
                    $active_visits = $stmt->fetch(PDO::FETCH_ASSOC)['visits'] ?? 0;
                    if ($active_visits > 0) {
                        $message = 'Cannot archive patient with active visits today';
                        $message_type = 'error';
                    } else {
                        $stmt = $db->prepare("UPDATE patients SET is_active = FALSE, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$patient_id]);
                        try {
                            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'Archived patient', ?, NOW())");
                            $stmt->execute([$_SESSION['user_id'], 'Patient ID: ' . $patient_id]);
                        } catch (Exception $e) {}
                        $message = 'Patient archived successfully';
                        $message_type = 'success';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }

        // Permanent delete patient
        if (isset($_POST['permanent_delete'])) {
            $patient_id = (int)($_POST['patient_id'] ?? 0);
            if ($patient_id) {
                try {
                    $db = Database::getInstance();
                    // Check if patient exists first
                    $stmt = $db->prepare("SELECT patient_id, first_name, last_name FROM patients WHERE id = ?");
                    $stmt->execute([$patient_id]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($patient) {
                        // Delete the patient permanently (CASCADE will handle related records)
                        $stmt = $db->prepare("DELETE FROM patients WHERE id = ?");
                        $stmt->execute([$patient_id]);
                        
                        logActivity('Deleted', 'Patients', 'patients', $patient_id, "Permanently deleted patient: " . ($patient['first_name'] ?? '') . " " . ($patient['last_name'] ?? '') . " (ID: " . ($patient['patient_id'] ?? '') . ")");
                        
                        $message = 'Patient permanently deleted!';
                        $message_type = 'success';
                    }
                } catch (PDOException $e) {
                    $message = 'Error deleting patient: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    }
}

// Get filters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_clauses = ["1=1"];

if ($status_filter) {
    $where_clauses[] = "is_active = :status";
}

if ($date_from) {
    $where_clauses[] = "DATE(created_at) >= :date_from";
}

if ($date_to) {
    $where_clauses[] = "DATE(created_at) <= :date_to";
}

if ($search) {
    $where_clauses[] = "(first_name LIKE :search OR last_name LIKE :search OR patient_id LIKE :search OR phone LIKE :search)";
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get total count
try {
    $db = Database::getInstance();
    $count_sql = "SELECT COUNT(*) as total FROM patients WHERE $where_sql";
    $stmt = $db->prepare($count_sql);
    
    $params = [];
    if ($status_filter) $params['status'] = $status_filter;
    if ($date_from) $params['date_from'] = $date_from;
    if ($date_to) $params['date_to'] = $date_to;
    if ($search) $params['search'] = "%$search%";
    
    $stmt->execute($params);
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    $total_pages = ceil($total_records / $per_page);
    
    // Get patients
    $sql = "SELECT * FROM patients WHERE $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    if ($status_filter) $stmt->bindValue(':status', $status_filter);
    if ($date_from) $stmt->bindValue(':date_from', $date_from);
    if ($date_to) $stmt->bindValue(':date_to', $date_to);
    if ($search) $stmt->bindValue(':search', "%$search%");
    
    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $patients = [];
    $total_records = 0;
    $total_pages = 0;
    $message = 'Error loading patients: ' . $e->getMessage();
    $message_type = 'error';
}

// Generate CSRF token
$csrf_token = csrfToken();
?>

<!-- Page Content -->
<div class="page-content p-6 lg:p-8">
    <div class="page-enter">
    <!-- Page Header -->
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-users text-primary-600 mr-2"></i> Patient Records
                </h1>
                <p class="text-gray-500 mt-1">View and manage all registered patients</p>
            </div>
            <button type="button" onclick="openAddModal()" class="inline-flex items-center px-4 py-2 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition-smooth">
                <i class="fas fa-user-plus mr-2"></i> Add Patient
            </button>
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
    
    <!-- Search and Filters -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form method="GET" class="space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                <!-- Search -->
                <div class="flex-1">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                            <i class="fas fa-search text-gray-400"></i>
                        </span>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, patient ID, or phone..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="flex flex-wrap items-center gap-3">
                    <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="deceased" <?php echo $status_filter === 'deceased' ? 'selected' : ''; ?>>Deceased</option>
                    </select>
                    
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500" placeholder="Date from">
                    
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                           class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500" placeholder="Date to">
                    
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm hover:bg-primary-700">
                        <i class="fas fa-filter mr-1"></i> Filter
                    </button>
                    
                    <a href="view_patients" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">Reset</a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Results Info & Export -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="p-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-500">Showing</span>
                <span class="text-sm font-semibold text-gray-800"><?php echo $total_records; ?></span>
                <span class="text-sm text-gray-500">patients</span>
            </div>
            
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="px-3 py-2 bg-gray-600 text-white rounded-lg text-sm hover:bg-gray-700">
                    <i class="fas fa-print mr-1"></i> Print
                </button>
            </div>
        </div>
        
        <!-- Patients Table -->
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Patient ID</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Name</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Gender</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Age</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Phone</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Emergency Contact</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Relationship</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Blood</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Status</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Registered</th>
                        <th class="text-left py-3 px-4 text-xs font-semibold text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($patients)): ?>
                    <tr>
                        <td colspan="11" class="py-8 text-center text-gray-500">
                            <i class="fas fa-users text-4xl mb-3 text-gray-300"></i>
                            <p>No patients found</p>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($patients as $patient): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4">
                                <span class="font-mono text-sm text-gray-700"><?php echo htmlspecialchars($patient['patient_id']); ?></span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-teal-400 to-blue-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-xs font-medium">
                                            <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <span class="font-medium text-gray-800">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600 capitalize">
                                <?php echo htmlspecialchars($patient['gender']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php 
                                    $age = floor((time() - strtotime($patient['date_of_birth'])) / (365.25 * 60 * 60 * 24));
                                    echo $age . ' yrs';
                                ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($patient['phone_primary']); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($patient['emergency_contact_name'] ?: '-'); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600 capitalize">
                                <?php echo htmlspecialchars($patient['emergency_contact_relationship'] ?: '-'); ?>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-600">
                                <?php echo $patient['blood_group'] ?: '-'; ?>
                            </td>
                            <td class="py-3 px-4">
                                <?php 
                                    $status_colors = [
                                        'active' => 'bg-green-100 text-green-700',
                                        'inactive' => 'bg-gray-100 text-gray-700',
                                        'deceased' => 'bg-red-100 text-red-700'
                                    ];
                                    $status_value = $patient['is_active'] ? 'active' : 'inactive';
                                    $status_class = $status_colors[$status_value] ?? $status_colors['active'];
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                    <?php echo ucfirst($status_value); ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-sm text-gray-500">
                                <?php echo date('M j, Y', strtotime($patient['created_at'])); ?>
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1">
                                    <button type="button" onclick="openEditModal(<?php echo (int)$patient['id']; ?>, '<?php echo htmlspecialchars(addslashes($patient['first_name'])); ?>', '<?php echo htmlspecialchars(addslashes($patient['last_name'])); ?>', '<?php echo htmlspecialchars(addslashes($patient['other_names'] ?? '')); ?>', '<?php echo htmlspecialchars($patient['gender']); ?>', '<?php echo htmlspecialchars($patient['date_of_birth']); ?>', '<?php echo htmlspecialchars($patient['phone_primary']); ?>', '<?php echo htmlspecialchars($patient['email'] ?? ''); ?>', '<?php echo htmlspecialchars($patient['address'] ?? ''); ?>', '<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>', '<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>', '<?php echo htmlspecialchars($patient['emergency_contact_relationship'] ?? ''); ?>', '<?php echo htmlspecialchars($patient['blood_group'] ?? ''); ?>', '<?php echo htmlspecialchars(addslashes($patient['allergies'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes($patient['chronic_conditions'] ?? '')); ?>', '<?php echo $patient['is_active'] ? 'active' : 'inactive'; ?>')" 
                                       class="p-2 text-blue-600 hover:bg-blue-50 rounded" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="../opd/consultation.php?patient_id=<?php echo $patient['id']; ?>" 
                                       class="p-2 text-teal-600 hover:bg-teal-50 rounded" title="New Visit">
                                        <i class="fas fa-stethoscope"></i>
                                    </a>
                                    
                                    <?php if ($patient['is_active']): ?>
                                    <button type="button" onclick="openDeleteModal(<?php echo (int)$patient['id']; ?>, '<?php echo htmlspecialchars(addslashes($patient['first_name'] . ' ' . $patient['last_name'])); ?>')" class="p-2 text-red-600 hover:bg-red-50 rounded" title="Archive">
                                        <i class="fas fa-archive"></i>
                                    </button>
                                    <button type="button" onclick="openPermanentDeleteModal(<?php echo (int)$patient['id']; ?>, '<?php echo htmlspecialchars(addslashes($patient['first_name'] . ' ' . $patient['last_name'])); ?>')" class="p-2 text-red-700 hover:bg-red-100 rounded" title="Delete Permanently">
                                        <i class="fas fa-trash"></i>
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
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="p-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-500">
                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                </span>
                
                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                       class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php 
                    // Show page numbers
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++): 
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                           class="px-3 py-1 rounded text-sm <?php echo $i === $page ? 'bg-primary-600 text-white' : 'border border-gray-300 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                       class="px-3 py-1 border border-gray-300 rounded text-sm hover:bg-gray-50">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Patient Modal -->
<div id="addPatientModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
        <div id="addPatientModalBackdrop" class="fixed inset-0 bg-black/50"></div>
        <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-2xl w-full overflow-hidden flex flex-col">
            <div class="modal-header-bg">
                <h2 class="text-lg font-semibold text-white">Add Patient</h2>
                <button type="button" onclick="closeAddModal()" class="absolute top-4 right-4 text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="addPatientForm" class="modal-dialog-content overflow-y-auto flex-1 p-4 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="submit_patient" value="1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Other Names</label>
                        <input type="text" name="other_names" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                        <select name="gender" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_birth" required max="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                        <input type="tel" name="phone" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Blood Group</label>
                        <select name="blood_group" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                        <input type="text" name="emergency_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Phone</label>
                        <input type="tel" name="emergency_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                        <select name="emergency_relationship" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select</option>
                            <option value="spouse">Spouse</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="child">Child</option>
                            <option value="friend">Friend</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allergies</label>
                        <textarea name="allergies" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medical History</label>
                        <textarea name="medical_history" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div class="sm:col-span-2 flex items-center gap-4">
                        <label class="flex items-center"><input type="radio" name="status" value="active" checked class="w-4 h-4 text-primary-600"> <span class="ml-2 text-sm text-gray-700">Active</span></label>
                        <label class="flex items-center"><input type="radio" name="status" value="inactive" class="w-4 h-4 text-primary-600"> <span class="ml-2 text-sm text-gray-700">Inactive</span></label>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department <span class="text-red-500">*</span></label>
                        <select name="department" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select Department</option>
                            <option value="OPD">General OPD (Outpatient)</option>
                            <option value="MCH">MCH (Maternal & Child Health)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Select "General OPD" for general patients. Select "MCH" for pregnant mothers or postnatal care.</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Register Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete (Archive) Confirmation Modal -->
<div id="deletePatientModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
        <div id="deletePatientModalBackdrop" class="fixed inset-0 bg-black/50"></div>
        <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
            <div class="modal-header-bg">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-xl bg-white flex items-center justify-center text-[#9E2A1E]"><i class="fas fa-archive text-xl"></i></span>
                    <div>
                        <h2 class="text-lg font-semibold text-white">Archive Patient</h2>
                    </div>
                </div>
            </div>
            <p class="text-gray-600 text-sm mb-2">Are you sure you want to archive this patient? They will be marked inactive and can be restored later.</p>
            <p class="font-medium text-gray-800 mb-4" id="deletePatientName"></p>
            <form method="POST" id="deletePatientForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="delete_patient" value="1">
                <input type="hidden" name="patient_id" id="deletePatientId" value="">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Archive</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Patient Modal -->
<div id="editPatientModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
        <div id="editPatientModalBackdrop" class="fixed inset-0 bg-black/50"></div>
        <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-2xl w-full overflow-hidden flex flex-col">
            <div class="modal-header-bg">
                <h2 class="text-lg font-semibold text-white">Edit Patient</h2>
                <button type="button" onclick="closeEditModal()" class="absolute top-4 right-4 text-white hover:text-gray-200" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" id="editPatientForm" class="modal-dialog-content overflow-y-auto flex-1 p-4 space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="update_patient" value="1">
                <input type="hidden" name="patient_id" id="editPatientId" value="">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="first_name" id="editFirstName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="last_name" id="editLastName" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Other Names</label>
                        <input type="text" name="other_names" id="editOtherNames" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gender <span class="text-red-500">*</span></label>
                        <select name="gender" id="editGender" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth <span class="text-red-500">*</span></label>
                        <input type="date" name="date_of_birth" id="editDob" required max="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone <span class="text-red-500">*</span></label>
                        <input type="tel" name="phone" id="editPhone" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Blood Group</label>
                        <select name="blood_group" id="editBloodGroup" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="editEmail" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea name="address" id="editAddress" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Contact Name</label>
                        <input type="text" name="emergency_name" id="editEmergencyName" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Emergency Phone</label>
                        <input type="tel" name="emergency_phone" id="editEmergencyPhone" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Relationship</label>
                        <select name="emergency_relationship" id="editEmergencyRelationship" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500">
                            <option value="">Select</option>
                            <option value="spouse">Spouse</option>
                            <option value="parent">Parent</option>
                            <option value="sibling">Sibling</option>
                            <option value="child">Child</option>
                            <option value="friend">Friend</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Allergies</label>
                        <textarea name="allergies" id="editAllergies" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Medical History</label>
                        <textarea name="medical_history" id="editMedicalHistory" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500"></textarea>
                    </div>
                    <div class="sm:col-span-2 flex items-center gap-4">
                        <label class="flex items-center"><input type="radio" name="status" id="editStatusActive" value="active" checked class="w-4 h-4 text-primary-600"> <span class="ml-2 text-sm text-gray-700">Active</span></label>
                        <label class="flex items-center"><input type="radio" name="status" id="editStatusInactive" value="inactive" class="w-4 h-4 text-primary-600"> <span class="ml-2 text-sm text-gray-700">Inactive</span></label>
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t border-gray-100">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Update Patient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Permanent Delete Confirmation Modal -->
<div id="permanentDeleteModal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="modal-overlay flex min-h-full items-center justify-center p-3 sm:p-4">
        <div id="permanentDeleteModalBackdrop" class="fixed inset-0 bg-black/50"></div>
        <div class="modal-dialog relative bg-white rounded-2xl shadow-xl max-w-md w-full overflow-hidden">
            <div class="modal-header-bg">
                <div class="flex items-center gap-3">
                    <span class="w-12 h-12 rounded-xl bg-white flex items-center justify-center text-red-600"><i class="fas fa-trash text-xl"></i></span>
                    <div>
                        <h2 class="text-lg font-semibold text-white">Delete Patient Permanently</h2>
                    </div>
                </div>
            </div>
            <p class="text-gray-600 text-sm mb-2">⚠️ WARNING: This action cannot be undone! All patient records, visits, and data will be permanently deleted from the database.</p>
            <p class="font-medium text-gray-800 mb-4" id="permanentDeletePatientName"></p>
            <form method="POST" id="permanentDeleteForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="permanent_delete" value="1">
                <input type="hidden" name="patient_id" id="permanentDeletePatientId" value="">
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closePermanentDeleteModal()" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Delete Permanently</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- End Page Content -->

<script>
function closeAddModal() {
    document.getElementById('addPatientModal').classList.add('hidden');
    document.body.style.overflow = '';
}
function openAddModal() {
    document.getElementById('addPatientModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function openDeleteModal(patientId, patientName) {
    document.getElementById('deletePatientId').value = patientId;
    document.getElementById('deletePatientName').textContent = patientName;
    document.getElementById('deletePatientModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeDeleteModal() {
    document.getElementById('deletePatientModal').classList.add('hidden');
    document.body.style.overflow = '';
}
function openPermanentDeleteModal(patientId, patientName) {
    document.getElementById('permanentDeletePatientId').value = patientId;
    document.getElementById('permanentDeletePatientName').textContent = patientName;
    document.getElementById('permanentDeleteModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closePermanentDeleteModal() {
    document.getElementById('permanentDeleteModal').classList.add('hidden');
    document.body.style.overflow = '';
}
function openEditModal(id, firstName, lastName, otherNames, gender, dob, phone, email, address, emergencyName, emergencyPhone, emergencyRelationship, bloodGroup, allergies, medicalHistory, status) {
    document.getElementById('editPatientId').value = id;
    document.getElementById('editFirstName').value = firstName;
    document.getElementById('editLastName').value = lastName;
    document.getElementById('editOtherNames').value = otherNames || '';
    // Convert gender to lowercase for select option
    document.getElementById('editGender').value = gender ? gender.toLowerCase() : '';
    document.getElementById('editDob').value = dob;
    document.getElementById('editPhone').value = phone;
    document.getElementById('editEmail').value = email;
    document.getElementById('editAddress').value = address;
    document.getElementById('editEmergencyName').value = emergencyName;
    document.getElementById('editEmergencyPhone').value = emergencyPhone;
    document.getElementById('editEmergencyRelationship').value = emergencyRelationship;
    document.getElementById('editBloodGroup').value = bloodGroup;
    document.getElementById('editAllergies').value = allergies;
    document.getElementById('editMedicalHistory').value = medicalHistory;
    
    // Set status radio
    if (status === 'active') {
        document.getElementById('editStatusActive').checked = true;
    } else {
        document.getElementById('editStatusInactive').checked = true;
    }
    
    document.getElementById('editPatientModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    document.getElementById('editPatientModal').classList.add('hidden');
    document.body.style.overflow = '';
}
document.getElementById('addPatientModalBackdrop').addEventListener('click', closeAddModal);
document.getElementById('deletePatientModalBackdrop').addEventListener('click', closeDeleteModal);
document.getElementById('editPatientModalBackdrop').addEventListener('click', closeEditModal);
document.getElementById('permanentDeleteModalBackdrop').addEventListener('click', closePermanentDeleteModal);
if (window.location.hash === '#add') {
    openAddModal();
    history.replaceState(null, '', window.location.pathname + window.location.search);
}
</script>

<?php
require_once '../includes/footer.php';
ob_end_flush();
?>
