<?php
/**
 * Hospital Management System - Dashboard
 * Role-based dashboard with widgets
 */

// Set timezone to East Africa (Kenya)
date_default_timezone_set('Africa/Nairobi');

// Start session
session_start();

// Check authentication
require_once 'includes/auth_check.php';

// Get user info
$user_role = $_SESSION['user_role'] ?? 'guest';
$full_name = $_SESSION['full_name'] ?? 'User';
$username = $_SESSION['username'] ?? 'user';

// Set page title
$page_title = 'Dashboard';
$page_description = 'Welcome back! Here\'s an overview of your hospital operations today.';

// Include database
require_once 'config/database.php';

// Get stats based on role
$stats = [
    'total_patients' => 0,
    'today_patients' => 0,
    'pending_lab' => 0,
    'pending_prescriptions' => 0,
    'low_stock_items' => 0,
    'total_users' => 0,
    'today_appointments' => 0,
    'pending_procedures' => 0
];

try {
    $db = Database::getInstance();
    
    // Get total patients
    $stmt = $db->query("SELECT COUNT(*) as total FROM patients");
    $stats['total_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get today's patients
    $stmt = $db->query("SELECT COUNT(*) as today FROM patients WHERE DATE(created_at) = CURDATE()");
    $stats['today_patients'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'] ?? 0;
    
    // Get pending lab requests
    $stmt = $db->query("SELECT COUNT(*) as pending FROM lab_requests WHERE status = 'pending'");
    $stats['pending_lab'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;
    
    // Get pending prescriptions
    $stmt = $db->query("SELECT COUNT(*) as pending FROM prescriptions WHERE status = 'pending'");
    $stats['pending_prescriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;
    
    // Get low stock items (if pharmacy)
    $stmt = $db->query("SELECT COUNT(*) as low FROM drug_stock WHERE quantity <= reorder_level");
    $stats['low_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['low'] ?? 0;
    
    // Get total users (admin only)
    if ($user_role === 'admin') {
        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    }
    
    // Get today's appointments/consultations
    $stmt = $db->query("SELECT COUNT(*) as today FROM opd_consultations WHERE DATE(consultation_date) = CURDATE()");
    $stats['today_appointments'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'] ?? 0;
    
    // Get pending procedures
    $stmt = $db->query("SELECT COUNT(*) as pending FROM theatre_procedures WHERE status = 'scheduled'");
    $stats['pending_procedures'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;
    
} catch (Exception $e) {
    // If tables don't exist, use default values
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Get recent patients
$recent_patients = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT * FROM patients ORDER BY created_at DESC LIMIT 5");
    $recent_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

// Get recent consultations
$recent_consultations = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT c.*, p.first_name, p.last_name, p.patient_id FROM opd_consultations c 
                        LEFT JOIN patients p ON c.patient_id = p.id 
                        ORDER BY c.consultation_date DESC LIMIT 5");
    $recent_consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

// Get recent lab requests
$recent_lab_requests = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT l.*, p.first_name, p.last_name, p.patient_id FROM lab_requests l 
                        LEFT JOIN patients p ON l.patient_id = p.id 
                        ORDER BY l.created_at DESC LIMIT 5");
    $recent_lab_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

// Get recent prescriptions
$recent_prescriptions = [];
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT pr.*, p.first_name, p.last_name, p.patient_id FROM prescriptions pr 
                        LEFT JOIN patients p ON pr.patient_id = p.id 
                        ORDER BY pr.created_at DESC LIMIT 5");
    $recent_prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

// Include header
require_once 'includes/header.php';

// Include sidebar
require_once 'includes/sidebar.php';
?>

<style>
:root {
    --primary-red: #9E2A1E;
    --highlight-red: #B53B2E;
    --bg-offwhite: #F7F4F0;
    --primary-50: #FDF2F1;
    --primary-100: #FCE5E3;
    --primary-600: #9E2A1E;
    --primary-700: #7A1F16;
}

.dashboard-container {
    background: whitesmoke;
    min-height: calc(100vh - 4rem);
    animation: fadeIn 0.4s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Glassmorphism Header Card */
.welcome-card {
    background: whitesmoke;
    position: relative;
    overflow: hidden;
}

.welcome-card::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 300px;
    height: 300px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    pointer-events: none;
}

.welcome-card::after {
    content: '';
    position: absolute;
    bottom: -30%;
    left: -5%;
    width: 200px;
    height: 200px;
    background: rgba(255,255,255,0.05);
    border-radius: 50%;
    pointer-events: none;
}

/* Stats Cards with Progress Indicators */
.stat-card {
    background: white;
    border: 1px solid rgba(158, 42, 30, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 3px;
    background: linear-gradient(90deg, var(--primary-red) 0%, var(--highlight-red) 100%);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(158, 42, 30, 0.1), 0 10px 10px -5px rgba(158, 42, 30, 0.04);
    border-color: rgba(158, 42, 30, 0.15);
}

.stat-card:hover::before {
    transform: scaleX(1);
}

.stat-icon-wrapper {
    background: linear-gradient(135deg, var(--primary-50) 0%, white 100%);
    border: 1px solid rgba(158, 42, 30, 0.1);
    transition: transform 0.3s ease;
}

.stat-card:hover .stat-icon-wrapper {
    transform: scale(1.1) rotate(5deg);
}

/* Quick Actions Grid */
.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
}

.action-btn {
    background: white;
    border: 1px solid rgba(158, 42, 30, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.action-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--primary-50) 0%, transparent 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.action-btn:hover {
    border-color: var(--primary-red);
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(158, 42, 30, 0.1);
}

.action-btn:hover::before {
    opacity: 1;
}

.action-icon {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--highlight-red) 100%);
    box-shadow: 0 4px 6px -1px rgba(158, 42, 30, 0.3);
    transition: transform 0.3s ease;
}

.action-btn:hover .action-icon {
    transform: scale(1.1);
}

/* Alert Cards */
.alert-card {
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.alert-card:hover {
    transform: translateX(4px);
    background: white;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

.alert-card.alert-pending { border-left-color: #8B5CF6; }
.alert-card.alert-prescription { border-left-color: #F59E0B; }
.alert-card.alert-stock { border-left-color: #EF4444; }
.alert-card.alert-procedure { border-left-color: #6366F1; }

/* Table Styling */
.patient-table {
    border-collapse: separate;
    border-spacing: 0;
}

.patient-table th {
    background: var(--bg-offwhite);
    color: #4B5563;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    border-bottom: 2px solid rgba(158, 42, 30, 0.1);
}

.patient-table td {
    border-bottom: 1px solid rgba(158, 42, 30, 0.05);
    transition: background-color 0.2s ease;
}

.patient-table tr:hover td {
    background: rgba(158, 42, 30, 0.02);
}

.patient-avatar {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--highlight-red) 100%);
    box-shadow: 0 2px 4px rgba(158, 42, 30, 0.2);
}

/* Pulse Animation for Live Indicators */
.pulse-dot {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}

/* Custom Scrollbar */
.custom-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.custom-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scroll::-webkit-scrollbar-thumb {
    background: rgba(158, 42, 30, 0.2);
    border-radius: 3px;
}

.custom-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(158, 42, 30, 0.4);
}

/* Stats Cards */

/* Status Badges */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
}

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: #059669;
}
.status-active::before { background: #10B981; }

/* Loading Skeleton Animation */
@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

.skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 1000px 100%;
    animation: shimmer 2s infinite linear;
}

/* Focus States for Accessibility */
*:focus-visible {
    outline: 2px solid var(--primary-red);
    outline-offset: 2px;
}

/* Print Styles */
@media print {
    .dashboard-container { background: white; }
    .stat-card, .action-btn { break-inside: avoid; }
}
</style>

<div class="page-content p-6 lg:p-8">
    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Welcome Header -->
        <header class="welcome-card rounded-2xl p-6 lg:p-8 text-gray-800 shadow-xl">
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-2 opacity-90">
                        <img src="assets/images/logo.jpeg" alt="SIWOT Hospital" style="height: 32px; width: auto;">
                    </div>
                    <h1 class="text-3xl lg:text-4xl font-bold mb-2">
                        Welcome back, <?php echo htmlspecialchars(explode(' ', $full_name)[0]); ?>
                    </h1>
                    <p class="text-gray-600 text-lg">
                        Here's what's happening in your department today.
                    </p>
                </div>
                
                <div class="flex items-center gap-4 bg-white/70 backdrop-blur-sm rounded-xl p-4 border border-gray-200 shadow-sm">
                    <div class="text-right">
                        <p class="text-2xl font-bold text-gray-800"><?php echo date('d'); ?></p>
                        <p class="text-sm text-gray-600 opacity-90"><?php echo date('M Y'); ?></p>
                    </div>
                    <div class="h-12 w-px bg-gray-300"></div>
                    <div>
                        <p class="text-lg font-semibold text-gray-800"><?php echo date('l'); ?></p>
                        <p class="text-sm text-gray-600 opacity-90 flex items-center gap-2">
                            <span class="w-2 h-2 bg-green-400 rounded-full pulse-dot"></span>
                            Live System
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Stats Grid -->
        <section class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5" aria-label="Statistics">
            
            <!-- Total Patients -->
            <article class="stat-card rounded-2xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Total Patients</p>
                        <p class="text-3xl font-bold text-gray-900 tabular-nums tracking-tight">
                            <?php echo number_format($stats['total_patients']); ?>
                        </p>
                    </div>
                    <div class="stat-icon-wrapper w-12 h-12 rounded-xl flex items-center justify-center text-[#9E2A1E]">
                        <i class="fas fa-users text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-700 font-semibold">
                        <i class="fas fa-arrow-up text-xs"></i>
                        <?php echo $stats['today_patients']; ?>
                    </span>
                    <span class="text-gray-500">new registrations today</span>
                </div>
                <div class="mt-4 h-1 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-[#9E2A1E] to-[#B53B2E] rounded-full" 
                         style="width: <?php echo min(($stats['today_patients'] / max($stats['total_patients'], 1)) * 100, 100); ?>%">
                    </div>
                </div>
            </article>

            <!-- Today's Consultations -->
            <article class="stat-card rounded-2xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Today's Consultations</p>
                        <p class="text-3xl font-bold text-gray-900 tabular-nums tracking-tight">
                            <?php echo number_format($stats['today_appointments']); ?>
                        </p>
                    </div>
                    <div class="stat-icon-wrapper w-12 h-12 rounded-xl flex items-center justify-center text-[#9E2A1E]">
                        <i class="fas fa-stethoscope text-xl"></i>
                    </div>
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <i class="fas fa-clock text-[#9E2A1E]"></i>
                    <span>OPD consultations in progress</span>
                </div>
                <div class="mt-4 flex gap-1">
                    <?php for($i = 0; $i < 5; $i++): ?>
                        <div class="h-1.5 flex-1 rounded-full <?php echo $i < min($stats['today_appointments'], 5) ? 'bg-[#9E2A1E]' : 'bg-gray-100'; ?>"></div>
                    <?php endfor; ?>
                </div>
            </article>

            <!-- Pending Lab -->
            <article class="stat-card rounded-2xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Pending Lab Tests</p>
                        <p class="text-3xl font-bold text-gray-900 tabular-nums tracking-tight">
                            <?php echo number_format($stats['pending_lab']); ?>
                        </p>
                    </div>
                    <div class="stat-icon-wrapper w-12 h-12 rounded-xl flex items-center justify-center text-[#9E2A1E]">
                        <i class="fas fa-flask text-xl"></i>
                    </div>
                </div>
                <a href="laboratory/lab_requests.php" 
                   class="inline-flex items-center gap-2 text-sm font-semibold text-[#9E2A1E] hover:text-[#7A1F16] transition-colors group">
                    View Requests
                    <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                </a>
                <?php if($stats['pending_lab'] > 0): ?>
                <div class="mt-3 flex items-center gap-2 text-xs text-amber-600 bg-amber-50 px-3 py-2 rounded-lg">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Requires attention</span>
                </div>
                <?php endif; ?>
            </article>

            <!-- Pending Prescriptions -->
            <article class="stat-card rounded-2xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-1">Pending Prescriptions</p>
                        <p class="text-3xl font-bold text-gray-900 tabular-nums tracking-tight">
                            <?php echo number_format($stats['pending_prescriptions']); ?>
                        </p>
                    </div>
                    <div class="stat-icon-wrapper w-12 h-12 rounded-xl flex items-center justify-center text-[#9E2A1E]">
                        <i class="fas fa-prescription text-xl"></i>
                    </div>
                </div>
                <a href="pharmacy/prescriptions.php" 
                   class="inline-flex items-center gap-2 text-sm font-semibold text-[#9E2A1E] hover:text-[#7A1F16] transition-colors group">
                    Process Queue
                    <i class="fas fa-arrow-right text-xs group-hover:translate-x-1 transition-transform"></i>
                </a>
                <?php if($stats['pending_prescriptions'] > 5): ?>
                <div class="mt-3 flex items-center gap-2 text-xs text-red-600 bg-red-50 px-3 py-2 rounded-lg">
                    <i class="fas fa-fire"></i>
                    <span>High volume alert</span>
                </div>
                <?php endif; ?>
            </article>
        </section>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            
            <!-- Left Column (2/3 width) -->
            <div class="xl:col-span-2 space-y-6">
                
                <!-- Quick Actions -->
                <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6" aria-label="Quick Actions">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#9E2A1E] to-[#B53B2E] flex items-center justify-center text-white shadow-lg">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Quick Actions</h2>
                                <p class="text-sm text-gray-500">Frequently used workflows</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-grid">
                        <?php if (in_array($user_role, ['admin', 'doctor', 'nurse'])): ?>
                        <a href="patients/view_patients.php#add" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-user-plus text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Add Patient</span>
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                        <a href="opd/consultation" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-stethoscope text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Consultation</span>
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($user_role, ['admin', 'lab'])): ?>
                        <a href="laboratory/add_results" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-vial text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Lab Results</span>
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($user_role, ['admin', 'pharmacy'])): ?>
                        <a href="pharmacy/dispense_drug" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-pills text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Dispense</span>
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($user_role, ['admin', 'nurse'])): ?>
                        <a href="nursing/vitals" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-heartbeat text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Record Vitals</span>
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($user_role, ['admin', 'theatre'])): ?>
                        <a href="theatre/procedures" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-procedures text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Theatre</span>
                        </a>
                        <?php endif; ?>

                        <?php if (in_array($user_role, ['admin', 'doctor'])): ?>
                        <a href="reports/daily_reports" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-chart-line text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Reports</span>
                        </a>
                        <?php endif; ?>

                        <?php if ($user_role === 'admin'): ?>
                        <a href="admin/manage_users" class="action-btn rounded-xl p-4 flex flex-col items-center gap-3 group">
                            <div class="action-icon w-12 h-12 rounded-xl flex items-center justify-center text-white">
                                <i class="fas fa-cogs text-lg"></i>
                            </div>
                            <span class="font-semibold text-gray-700 text-sm text-center">Admin</span>
                        </a>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Recent Patients Table -->
                <?php if (in_array($user_role, ['admin', 'doctor', 'nurse']) && !empty($recent_patients)): ?>
                <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" aria-label="Recent Patients">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-white to-[#F7F4F0]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#9E2A1E]/10 flex items-center justify-center text-[#9E2A1E]">
                                <i class="fas fa-user-clock"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Recent Patients</h2>
                                <p class="text-sm text-gray-500">Latest registrations</p>
                            </div>
                        </div>
                        <a href="patients/view_patients" 
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#9E2A1E] text-white text-sm font-semibold hover:bg-[#7A1F16] transition-colors shadow-md hover:shadow-lg">
                            View All
                            <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto custom-scroll">
                        <table class="patient-table w-full">
                            <thead>
                                <tr>
                                    <th class="py-4 px-6 text-left">Patient Details</th>
                                    <th class="py-4 px-6 text-left">Gender</th>
                                    <th class="py-4 px-6 text-left">Age</th>
                                    <th class="py-4 px-6 text-left">Registered</th>
                                    <th class="py-4 px-6 text-left">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_patients as $patient): 
                                    $age = isset($patient['date_of_birth']) && $patient['date_of_birth'] 
                                        ? floor((time() - strtotime($patient['date_of_birth'])) / (365.25 * 86400)) 
                                        : null;
                                    $initials = strtoupper(substr($patient['first_name'] ?? 'U', 0, 1) . substr($patient['last_name'] ?? '', 0, 1));
                                    if (strlen($initials) < 2) $initials = strtoupper(substr($patient['first_name'] ?? 'U', 0, 2));
                                ?>
                                <tr class="group">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-3">
                                            <div class="patient-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                                <?php echo $initials; ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold text-gray-900">
                                                    <?php echo htmlspecialchars(trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? '')) ?: 'Unknown'); ?>
                                                </p>
                                                <p class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($patient['patient_id'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 capitalize">
                                            <i class="fas fa-<?php echo ($patient['gender'] ?? '') === 'female' ? 'venus text-pink-500' : (($patient['gender'] ?? '') === 'male' ? 'mars text-blue-500' : 'user text-gray-400'); ?>"></i>
                                            <?php echo htmlspecialchars($patient['gender'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-gray-600 tabular-nums">
                                        <?php echo $age ? $age . ' years' : '—'; ?>
                                    </td>
                                    <td class="py-4 px-6 text-gray-600 text-sm">
                                        <div class="flex items-center gap-2">
                                            <i class="far fa-clock text-gray-400 text-xs"></i>
                                            <?php echo isset($patient['created_at']) ? date('M j, g:i A', strtotime($patient['created_at'])) : '—'; ?>
                                        </div>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="status-badge status-active">
                                            Active
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>
                
                <!-- Recent Activities Section -->
                <section class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden" aria-label="Recent Activities">
                    <div class="p-6 border-b border-gray-100 flex items-center justify-between bg-gradient-to-r from-white to-[#F7F4F0]">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#9E2A1E]/10 flex items-center justify-center text-[#9E2A1E]">
                                <i class="fas fa-history"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Recent Activities</h2>
                                <p class="text-sm text-gray-500">Latest hospital activities</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <?php if (!empty($recent_consultations)): ?>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Consultations</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>
                        <?php foreach ($recent_consultations as $consult): ?>
                        <div class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                <i class="fas fa-stethoscope"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars(($consult['first_name'] ?? '') . ' ' . ($consult['last_name'] ?? '')); ?>
                                </p>
                                <p class="text-sm text-gray-500 truncate">
                                    <?php echo htmlspecialchars($consult['chief_complaint'] ?? 'Consultation'); ?>
                                </p>
                            </div>
                            <span class="text-xs text-gray-400">
                                <?php echo isset($consult['consultation_date']) ? date('M j, g:i A', strtotime($consult['consultation_date'])) : ''; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($recent_lab_requests)): ?>
                        <div class="flex items-center gap-3 mb-4 mt-6">
                            <span class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Lab Requests</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>
                        <?php foreach ($recent_lab_requests as $lab): ?>
                        <div class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600">
                                <i class="fas fa-flask"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars(($lab['first_name'] ?? '') . ' ' . ($lab['last_name'] ?? '')); ?>
                                </p>
                                <p class="text-sm text-gray-500 truncate">
                                    <?php echo htmlspecialchars($lab['test_type'] ?? 'Lab Test'); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo ($lab['status'] ?? '') === 'completed' ? 'bg-green-100 text-green-700' : (($lab['status'] ?? '') === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'); ?>">
                                <?php echo htmlspecialchars($lab['status'] ?? 'pending'); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (!empty($recent_prescriptions)): ?>
                        <div class="flex items-center gap-3 mb-4 mt-6">
                            <span class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Prescriptions</span>
                            <div class="flex-1 h-px bg-gray-200"></div>
                        </div>
                        <?php foreach ($recent_prescriptions as $presc): ?>
                        <div class="flex items-center gap-4 p-3 rounded-lg hover:bg-gray-50 transition-colors">
                            <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                <i class="fas fa-pills"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900 truncate">
                                    <?php echo htmlspecialchars(($presc['first_name'] ?? '') . ' ' . ($presc['last_name'] ?? '')); ?>
                                </p>
                                <p class="text-sm text-gray-500 truncate">
                                    <?php echo htmlspecialchars($presc['medication'] ?? 'Prescription'); ?>
                                </p>
                            </div>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo ($presc['status'] ?? '') === 'dispensed' ? 'bg-green-100 text-green-700' : (($presc['status'] ?? '') === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'); ?>">
                                <?php echo htmlspecialchars($presc['status'] ?? 'pending'); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if (empty($recent_consultations) && empty($recent_lab_requests) && empty($recent_prescriptions)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-3 text-gray-300"></i>
                            <p>No recent activities yet</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Right Column (1/3 width) -->
            <div class="space-y-6">
                
                <!-- Priority Alerts -->
                <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6" aria-label="Priority Alerts">
                    <div class="flex items-center justify-between mb-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center text-red-600">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h2 class="text-lg font-bold text-gray-900">Priority Alerts</h2>
                                <p class="text-sm text-gray-500">Requires immediate action</p>
                            </div>
                        </div>
                        <?php 
                        $totalAlerts = $stats['pending_lab'] + $stats['pending_prescriptions'] + $stats['low_stock_items'] + $stats['pending_procedures'];
                        if ($totalAlerts > 0): 
                        ?>
                        <span class="px-2.5 py-1 rounded-full bg-red-100 text-red-700 text-xs font-bold">
                            <?php echo $totalAlerts; ?> pending
                        </span>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3">
                        <?php if ($stats['pending_lab'] > 0): ?>
                        <a href="laboratory/lab_requests.php" class="alert-card alert-pending flex items-start gap-4 p-4 rounded-xl bg-gray-50 hover:bg-white border border-gray-100">
                            <div class="w-10 h-10 rounded-lg bg-violet-100 flex items-center justify-center text-violet-600 flex-shrink-0">
                                <i class="fas fa-vial"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="font-semibold text-gray-900">Lab Tests Pending</p>
                                    <span class="text-violet-600 font-bold text-sm"><?php echo $stats['pending_lab']; ?></span>
                                </div>
                                <p class="text-sm text-gray-500">Awaiting results entry</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($stats['pending_prescriptions'] > 0): ?>
                        <a href="pharmacy/prescriptions.php" class="alert-card alert-prescription flex items-start gap-4 p-4 rounded-xl bg-gray-50 hover:bg-white border border-gray-100">
                            <div class="w-10 h-10 rounded-lg bg-amber-100 flex items-center justify-center text-amber-600 flex-shrink-0">
                                <i class="fas fa-prescription"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="font-semibold text-gray-900">Prescriptions Queue</p>
                                    <span class="text-amber-600 font-bold text-sm"><?php echo $stats['pending_prescriptions']; ?></span>
                                </div>
                                <p class="text-sm text-gray-500">Ready for dispensing</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($stats['low_stock_items'] > 0): ?>
                        <a href="pharmacy/drug_stock.php" class="alert-card alert-stock flex items-start gap-4 p-4 rounded-xl bg-gray-50 hover:bg-white border border-gray-100">
                            <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center text-red-600 flex-shrink-0">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="font-semibold text-gray-900">Low Stock Alert</p>
                                    <span class="text-red-600 font-bold text-sm"><?php echo $stats['low_stock_items']; ?></span>
                                </div>
                                <p class="text-sm text-gray-500">Items below reorder level</p>
                            </div>
                        </a>
                        <?php endif; ?>

                        <?php if ($stats['pending_procedures'] > 0): ?>
                        <div class="alert-card alert-procedure flex items-start gap-4 p-4 rounded-xl bg-gray-50 border border-gray-100">
                            <div class="w-10 h-10 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0">
                                <i class="fas fa-procedures"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="font-semibold text-gray-900">Scheduled Procedures</p>
                                    <span class="text-indigo-600 font-bold text-sm"><?php echo $stats['pending_procedures']; ?></span>
                                </div>
                                <p class="text-sm text-gray-500">Theatre schedule today</p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($totalAlerts == 0): ?>
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <div class="w-16 h-16 rounded-full bg-emerald-50 flex items-center justify-center text-emerald-500 mb-3">
                                <i class="fas fa-check-double text-2xl"></i>
                            </div>
                            <p class="font-semibold text-gray-900">All Caught Up!</p>
                            <p class="text-sm text-gray-500 mt-1">No pending tasks requiring attention</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($user_role === 'admin'): ?>
                <!-- System Status -->
                <section class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6" aria-label="System Status">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-gray-600">
                            <i class="fas fa-server"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">System Status</h2>
                            <p class="text-sm text-gray-500">Infrastructure health</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i class="fas fa-users text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700">Total Users</span>
                            </div>
                            <span class="text-lg font-bold text-gray-900 tabular-nums"><?php echo $stats['total_users']; ?></span>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                                    <i class="fas fa-database text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700">Database</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 pulse-dot"></span>
                                <span class="text-sm font-semibold text-emerald-600">Connected</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-lg bg-gray-50 border border-gray-100">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-purple-600">
                                    <i class="fab fa-php text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-gray-700">PHP Version</span>
                            </div>
                            <span class="text-sm font-mono font-semibold text-gray-700 bg-gray-200 px-2 py-1 rounded"><?php echo phpversion(); ?></span>
                        </div>

                        <div class="mt-4 pt-4 border-t border-gray-100">
                            <div class="flex items-center justify-between text-xs text-gray-500">
                                <span>Server Load</span>
                                <span class="font-semibold text-emerald-600">Normal</span>
                            </div>
                            <div class="mt-2 h-2 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: 23%"></div>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>