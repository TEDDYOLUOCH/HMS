<?php
// Hospital Management System - Sidebar
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get user role from database
$db_role = $_SESSION['user_role'] ?? 'guest';

// Map database roles to menu roles for sidebar filtering
// Database roles: admin, doctor, lab_technologist, lab_scientist, pharmacist, nurse, theatre_officer
$role_mapping = [
    'admin' => 'admin',
    'doctor' => 'doctor',
    'nurse' => 'nurse',
    'lab_technologist' => 'lab',
    'lab_scientist' => 'lab',
    'pharmacist' => 'pharmacy',
    'theatre_officer' => 'theatre',
    'guest' => 'guest'
];

// Get mapped role for menu filtering
$user_role = $role_mapping[$db_role] ?? 'guest';
$full_name = $_SESSION['full_name'] ?? 'User';

// Add body class for sidebar layout
if (!isset($GLOBALS['sidebar_included'])) {
    $GLOBALS['sidebar_included'] = true;
    // Add class to body if not already added
    echo "<script>document.body.classList.add('with-sidebar');</script>";
}

// Determine base path for links (root = '', subfolder = '../')
$base_path = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false || 
              strpos($_SERVER['PHP_SELF'], '/patients/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/opd/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/nursing/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/laboratory/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/pharmacy/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/theatre/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/reports/') !== false ||
              strpos($_SERVER['PHP_SELF'], '/billing/') !== false) ? '../' : '';

// Role-based menu configuration
$menu_items = [
    'dashboard' => [
        'icon' => 'fa-home',
        'label' => 'Dashboard',
        'href' => $base_path . 'dashboard',
        'roles' => ['admin', 'doctor', 'nurse', 'lab', 'pharmacy', 'theatre']
    ],
    'divider1' => ['type' => 'divider', 'label' => 'Main Modules'],
    
    'patients' => [
        'icon' => 'fa-users',
        'label' => 'Patients',
        'href' => $base_path . 'patients/view_patients',
        'roles' => ['admin', 'doctor', 'nurse']
    ],
    'opd' => [
        'icon' => 'fa-stethoscope',
        'label' => 'OPD',
        'href' => $base_path . 'opd/consultation',
        'roles' => ['admin', 'doctor', 'nurse', 'lab', 'pharmacy'],
        'submenu' => [
            ['label' => 'Patient Queue', 'href' => $base_path . 'opd/patient_queue.php'],
            ['label' => 'Consultation', 'href' => $base_path . 'opd/consultation.php'],
            ['label' => 'Diagnosis', 'href' => $base_path . 'opd/diagnosis'],
            ['label' => 'Prescriptions', 'href' => $base_path . 'opd/prescriptions'],
        ]
    ],
    'nursing' => [
        'icon' => 'fa-heartbeat',
        'label' => 'Nursing',
        'href' => $base_path . 'nursing/vitals',
        'roles' => ['admin', 'nurse'],
        'submenu' => [
            ['label' => 'Patient Queue', 'href' => $base_path . 'opd/patient_queue.php'],
            ['label' => 'Vitals', 'href' => $base_path . 'nursing/vitals'],
            ['label' => 'ANC Visits', 'href' => $base_path . 'nursing/anc_visits'],
            ['label' => 'ANC Assessments', 'href' => $base_path . 'nursing/anc_assessments'],
            ['label' => 'Postnatal', 'href' => $base_path . 'nursing/postnatal'],
        ]
    ],
    'laboratory' => [
        'icon' => 'fa-flask',
        'label' => 'Laboratory',
        'href' => $base_path . 'laboratory/lab_requests',
        'roles' => ['admin', 'lab', 'doctor'],
        'submenu' => [
            ['label' => 'Lab Requests', 'href' => $base_path . 'laboratory/lab_requests'],
            ['label' => 'Add Results', 'href' => $base_path . 'laboratory/add_results'],
            ['label' => 'Lab Reports', 'href' => $base_path . 'laboratory/lab_reports'],
            ['label' => 'Lab Stock', 'href' => $base_path . 'laboratory/lab_stock'],
            ['label' => 'Stock Usage', 'href' => $base_path . 'laboratory/stock_usage'],
        ]
    ],
    'pharmacy' => [
        'icon' => 'fa-pills',
        'label' => 'Pharmacy',
        'href' => $base_path . 'pharmacy/drug_stock',
        'roles' => ['admin', 'pharmacy'],
        'submenu' => [
            ['label' => 'Drug Stock', 'href' => $base_path . 'pharmacy/drug_stock'],
            ['label' => 'Prescriptions', 'href' => $base_path . 'pharmacy/prescriptions'],
            ['label' => 'Dispense Drug', 'href' => $base_path . 'pharmacy/dispense_drug'],
            ['label' => 'Walk-in Sales', 'href' => $base_path . 'pharmacy/walkin_sales'],
        ]
    ],
    'billing' => [
        'icon' => 'fa-file-invoice-dollar',
        'label' => 'Billing',
        'href' => $base_path . 'billing/invoices',
        'roles' => ['admin', 'doctor', 'nurse', 'pharmacist'],
        'submenu' => [
            ['label' => 'Invoices', 'href' => $base_path . 'billing/invoices'],
            ['label' => 'Payments', 'href' => $base_path . 'billing/payments'],
            ['label' => 'Payment Reports', 'href' => $base_path . 'billing/payment_reports'],
        ]
    ],
    'theatre' => [
        'icon' => 'fa-procedures',
        'label' => 'Theatre',
        'href' => $base_path . 'theatre/procedures',
        'roles' => ['admin', 'doctor', 'theatre'],
        'submenu' => [
            ['label' => 'Procedures', 'href' => $base_path . 'theatre/procedures'],
            ['label' => 'Reports', 'href' => $base_path . 'theatre/procedure_reports'],
        ]
    ],
    'divider2' => ['type' => 'divider', 'label' => 'Reports & Admin'],
    
    'reports' => [
        'icon' => 'fa-chart-bar',
        'label' => 'Reports',
        'href' => $base_path . 'reports/daily_reports',
        'roles' => ['admin', 'doctor'],
        'submenu' => [
            ['label' => 'Daily Reports', 'href' => $base_path . 'reports/daily_reports'],
            ['label' => 'Monthly Reports', 'href' => $base_path . 'reports/monthly_reports'],
            ['label' => 'Lab Reports', 'href' => $base_path . 'reports/lab_reports'],
            ['label' => 'Pharmacy Reports', 'href' => $base_path . 'reports/pharmacy_reports'],
        ]
    ],
    'admin' => [
        'icon' => 'fa-cogs',
        'label' => 'Administration',
        'href' => $base_path . 'admin/manage_users',
        'roles' => ['admin'],
        'submenu' => [
            ['label' => 'Staff Management', 'href' => $base_path . 'admin/staff'],
            ['label' => 'Manage Users', 'href' => $base_path . 'admin/manage_users'],
            ['label' => 'Activity Logs', 'href' => $base_path . 'admin/activity_logs'],
            ['label' => 'Referrals', 'href' => $base_path . 'admin/referrals'],
            ['label' => 'Admissions', 'href' => $base_path . 'admin/admissions'],
            ['label' => 'System Settings', 'href' => $base_path . 'admin/system_settings'],
        ]
    ]
];

// Filter menu items based on user role
function filterMenuByRole($menu, $role) {
    $filtered = [];
    foreach ($menu as $key => $item) {
        if (isset($item['type']) && $item['type'] === 'divider') {
            $hasItemsAfter = false;
            $foundDivider = false;
            foreach ($menu as $k => $i) {
                if ($k === $key) {
                    $foundDivider = true;
                    continue;
                }
                if ($foundDivider && isset($i['roles']) && in_array($role, $i['roles'])) {
                    $hasItemsAfter = true;
                    break;
                }
            }
            if ($hasItemsAfter) {
                $filtered[$key] = $item;
            }
        } elseif (isset($item['roles']) && in_array($role, $item['roles'])) {
            $filtered[$key] = $item;
        }
    }
    return $filtered;
}

$filtered_menu = filterMenuByRole($menu_items, $user_role);

// Check if current page is in an item's submenu (for auto-expand)
function isCurrentInSubmenu($item, $current_page) {
    if (!isset($item['submenu'])) return false;
    foreach ($item['submenu'] as $s) {
        if (basename($s['href'], '.php') === $current_page) return true;
    }
    return false;
}

?>

<style>
:root {
    --primary-red: #9E2A1E;
    --highlight-red: #B53B2E;
    --bg-offwhite: #D2691E;
    --sidebar-width: 280px;
}

/* Sidebar Container - Matching page background */
#sidebar {
    width: var(--sidebar-width);
    background: linear-gradient(135deg, #9E2A1E 0%, #B53B2E 100%);
    border-right: 1px solid rgba(158, 42, 30, 0.08);
    box-shadow: 4px 0 24px rgba(0, 0, 0, 0.04);
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Custom Scrollbar */
#sidebar::-webkit-scrollbar { 
    width: 5px; 
}
#sidebar::-webkit-scrollbar-track { 
    background: transparent; 
}
#sidebar::-webkit-scrollbar-thumb { 
    background: rgba(158, 42, 30, 0.15); 
    border-radius: 10px; 
}
#sidebar::-webkit-scrollbar-thumb:hover { 
    background: rgba(158, 42, 30, 0.25); 
}

/* Logo Area */
.logo-area {
    background: white;
    border-bottom: 1px solid rgba(158, 42, 30, 0.08);
}

/* User Profile Card */
.user-card {
    background: white;
    border: 1px solid rgba(158, 42, 30, 0.08);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    position: relative;
    overflow: hidden;
}

.user-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: linear-gradient(180deg, var(--primary-red) 0%, var(--highlight-red) 100%);
}

.user-avatar {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--highlight-red) 100%);
    box-shadow: 0 4px 12px rgba(158, 42, 30, 0.3);
}

/* Navigation Items */
.nav-item {
    position: relative;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    border-radius: 12px;
    margin: 2px 0;
    background: transparent;
    color: rgba(255, 255, 255, 0.9);
}

.nav-item::before {
    content: '';
    position: absolute;
    left: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: linear-gradient(180deg, var(--primary-red) 0%, var(--highlight-red) 100%);
    border-radius: 0 4px 4px 0;
    transition: height 0.3s ease;
}

.nav-item:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
}

.nav-item:hover::before {
    height: 60%;
}

.nav-item.active {
    background: white;
    color: var(--primary-red);
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(158, 42, 30, 0.08);
}

.nav-item.active::before {
    height: 80%;
}

/* Icon Containers */
.icon-box {
    transition: all 0.3s ease;
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.8);
}

.nav-item:hover .icon-box {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: scale(1.05);
}

.nav-item.active .icon-box {
    background: linear-gradient(135deg, var(--primary-red) 0%, var(--highlight-red) 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(158, 42, 30, 0.25);
}

/* Submenu Styling - Fixed */
.submenu-container {
    position: relative;
    margin-left: 1.25rem;
    padding-left: 1rem;
    border-left: 2px solid rgba(158, 42, 30, 0.1);
    overflow: hidden;
    transition: all 0.3s ease;
}

.submenu-item {
    position: relative;
    transition: all 0.2s ease;
    border-radius: 8px;
    margin: 2px 0;
    display: block;
    padding: 0.625rem 1rem;
    color: rgba(255, 255, 255, 0.8);
}

.submenu-item::before {
    content: '';
    position: absolute;
    left: -1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background: rgba(158, 42, 30, 0.2);
    border-radius: 50%;
    transition: all 0.2s ease;
}

.submenu-item:hover {
    background: rgba(255, 255, 255, 0.15);
    color: white;
    padding-left: 1.25rem;
}

.submenu-item:hover::before {
    background: var(--primary-red);
    transform: translateY(-50%) scale(1.2);
}

.submenu-item.active {
    background: white;
    color: var(--primary-red);
    font-weight: 600;
    padding-left: 1.25rem;
}

.submenu-item.active::before {
    background: var(--primary-red);
    width: 8px;
    height: 8px;
    box-shadow: 0 0 0 3px rgba(158, 42, 30, 0.1);
}

/* Chevron Animation */
.chevron-icon {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.chevron-icon.rotate-180 {
    transform: rotate(180deg);
    color: var(--primary-red);
}

/* Divider Styling */
.section-divider {
    position: relative;
    padding-top: 1.5rem;
    margin-top: 0.5rem;
}

.section-divider::before {
    content: '';
    position: absolute;
    top: 0.5rem;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(158, 42, 30, 0.15) 20%, rgba(158, 42, 30, 0.15) 80%, transparent 100%);
}

.divider-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    opacity: 0.7;
}

/* Logout Button */
.logout-btn {
    background: white;
    border: 1px solid rgba(239, 68, 68, 0.15);
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.05);
    transform: translateX(4px);
    border-color: rgba(239, 68, 68, 0.25);
}

.logout-btn .icon-box {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.logout-btn:hover .icon-box {
    background: #dc2626;
    color: white;
    transform: rotate(180deg);
}

/* Version Badge */
.version-badge {
    background: rgba(158, 42, 30, 0.06);
    color: rgba(158, 42, 30, 0.7);
    font-size: 0.65rem;
    letter-spacing: 0.05em;
    font-weight: 600;
}

/* Mobile Overlay */
#sidebarOverlay {
    background: rgba(0, 0, 0, 0.4);
    backdrop-filter: blur(4px);
    transition: opacity 0.3s ease;
}

/* Focus States */
.nav-item:focus-visible,
.submenu-item:focus-visible,
.logout-btn:focus-visible {
    outline: 2px solid var(--primary-red);
    outline-offset: 2px;
}

/* Submenu expand/collapse animation */
.submenu-container {
    max-height: 0;
    opacity: 0;
    transition: max-height 0.3s ease, opacity 0.3s ease, margin-top 0.3s ease;
}

.submenu-container.expanded {
    max-height: 500px;
    opacity: 1;
    margin-top: 0.25rem;
}

/* Print Styles */
@media print {
    #sidebar { display: none; }
    #mainContent { margin-left: 0 !important; }
}
</style>

<!-- Sidebar -->
<aside id="sidebar" class="fixed left-0 top-0 h-screen flex flex-col z-40 transform -translate-x-full lg:translate-x-0">
    
    <!-- Logo Area -->
    <div class="logo-area flex-none h-16 flex items-center px-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white shadow-lg shadow-red-900/20 overflow-hidden">
                <img src="<?php echo $base_path; ?>assets/images/logo.jpeg" alt="Logo" class="w-10 h-10 rounded-xl object-cover" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=\'fas fa-hospital text-[#9E2A1E] text-2xl\'></i>';">
            </div>
            <div class="flex flex-col">
                <span class="text-lg font-bold text-gray-900 leading-tight">SIWOT</span>
                <span class="text-[10px] text-gray-500 font-semibold tracking-wider uppercase">Hospital</span>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-4 px-3 custom-scroll">
        <ul class="space-y-1">
            <?php foreach ($filtered_menu as $key => $item): ?>
                <?php if (isset($item['type']) && $item['type'] === 'divider'): ?>
                    <li class="section-divider">
                        <span class="divider-label px-3 block">
                            <?php echo htmlspecialchars($item['label']); ?>
                        </span>
                    </li>
                <?php else: ?>
                    <?php
                    $is_parent_active = ($key === 'patients' && in_array($current_page, ['view_patients', 'edit_patient', 'add_patient', 'delete_patient'])) || isCurrentInSubmenu($item, $current_page);
                    $default_open = isset($item['submenu']) && isCurrentInSubmenu($item, $current_page);
                    $has_submenu = isset($item['submenu']) && !empty($item['submenu']);
                    ?>
                    <li class="relative" data-has-submenu="<?php echo $has_submenu ? 'true' : 'false'; ?>">
                        <?php if ($has_submenu): ?>
                            <button type="button"
                                    onclick="toggleSubmenu(this)"
                                    class="nav-item w-full flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-gray-700 <?php echo $is_parent_active ? 'active' : ''; ?>"
                                    aria-expanded="<?php echo $default_open ? 'true' : 'false'; ?>">
                                <span class="flex items-center gap-3 min-w-0">
                                    <span class="icon-box w-9 h-9 rounded-lg flex items-center justify-center flex-none">
                                        <i class="fas <?php echo $item['icon']; ?>"></i>
                                    </span>
                                    <span class="truncate nav-text"><?php echo htmlspecialchars($item['label']); ?></span>
                                </span>
                                <i class="fas fa-chevron-down chevron-icon text-xs text-gray-400 flex-none <?php echo $default_open ? 'rotate-180' : ''; ?>"></i>
                            </button>
                            
                            <!-- Submenu -->
                            <div class="submenu-container <?php echo $default_open ? 'expanded' : ''; ?>" 
                                 style="<?php echo $default_open ? '' : 'display: none;'; ?>">
                                <?php foreach ($item['submenu'] as $submenu): ?>
                                    <?php $submenu_page = basename($submenu['href'], '.php'); ?>
                                    <a href="<?php echo htmlspecialchars($submenu['href']); ?>"
                                       class="submenu-item text-sm text-gray-600 <?php echo $current_page === $submenu_page ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($submenu['label']); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($item['href']); ?>"
                               class="nav-item flex items-center gap-3 px-4 py-3 text-sm font-medium text-gray-700 <?php echo $current_page === $key ? 'active' : ''; ?>">
                                <span class="icon-box w-9 h-9 rounded-lg flex items-center justify-center flex-none">
                                    <i class="fas <?php echo $item['icon']; ?>"></i>
                                </span>
                                <span class="truncate nav-text"><?php echo htmlspecialchars($item['label']); ?></span>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Footer -->
    <div class="flex-none p-4 border-t border-gray-200/80 bg-white/50">
        <a href="<?php echo $base_path; ?>logout"
           class="logout-btn flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-red-600">
            <span class="icon-box w-9 h-9 rounded-lg flex items-center justify-center flex-none">
                <i class="fas fa-sign-out-alt"></i>
            </span>
            <span class="nav-text">Logout</span>
        </a>
    </div>
</aside>

<!-- Mobile Overlay -->
<div id="sidebarOverlay" class="fixed inset-0 z-30 hidden lg:hidden opacity-0 transition-opacity" onclick="toggleSidebar()"></div>

<!-- Mobile Toggle Button (visible only on mobile) -->
<button onclick="toggleSidebar()" class="fixed bottom-6 right-6 lg:hidden z-50 w-14 h-14 rounded-full bg-gradient-to-br from-[#9E2A1E] to-[#B53B2E] text-white shadow-xl shadow-red-900/30 flex items-center justify-center hover:scale-110 transition-transform">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Main Content Area -->
<main id="mainContent" class="flex-1 lg:ml-[280px] min-h-screen bg-[#F7F4F0] transition-all duration-300">

<script>
// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const isOpen = !sidebar.classList.contains('-translate-x-full');
    
    if (isOpen) {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
        overlay.classList.remove('opacity-100');
        document.body.style.overflow = '';
    } else {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
        // Trigger reflow for animation
        void overlay.offsetWidth;
        overlay.classList.add('opacity-100');
        document.body.style.overflow = 'hidden';
    }
}

// Submenu toggle functionality - FIXED
function toggleSubmenu(button) {
    const submenu = button.nextElementSibling;
    const chevron = button.querySelector('.chevron-icon');
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    
    if (isExpanded) {
        // Collapse
        submenu.style.maxHeight = submenu.scrollHeight + 'px';
        submenu.offsetHeight; // Trigger reflow
        submenu.style.maxHeight = '0';
        submenu.style.opacity = '0';
        submenu.style.marginTop = '0';
        setTimeout(() => {
            submenu.style.display = 'none';
        }, 300);
        button.setAttribute('aria-expanded', 'false');
        chevron.classList.remove('rotate-180');
    } else {
        // Expand
        submenu.style.display = 'block';
        const height = submenu.scrollHeight;
        submenu.style.maxHeight = '0';
        submenu.offsetHeight; // Trigger reflow
        submenu.style.maxHeight = height + 'px';
        submenu.style.opacity = '1';
        submenu.style.marginTop = '0.25rem';
        button.setAttribute('aria-expanded', 'true');
        chevron.classList.add('rotate-180');
        
        // Clean up after animation
        setTimeout(() => {
            submenu.style.maxHeight = 'none';
        }, 300);
    }
}

// Close sidebar when clicking on a link (mobile)
document.querySelectorAll('#sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 1024) {
            toggleSidebar();
        }
    });
});

// Handle resize
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        document.getElementById('sidebarOverlay').classList.add('hidden');
        document.body.style.overflow = '';
    }
});

// Initialize submenus on page load
document.addEventListener('DOMContentLoaded', function() {
    // Ensure all expanded submenus have proper display
    document.querySelectorAll('.submenu-container.expanded').forEach(submenu => {
        submenu.style.display = 'block';
        submenu.style.maxHeight = 'none';
        submenu.style.opacity = '1';
    });
});
</script>