<?php
// Hospital Management System - Header
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page name for active state
$current_page = basename($_SERVER['PHP_SELF'], '.php');

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

// Get user info from session
$user_role = $_SESSION['user_role'] ?? 'guest';
$full_name = $_SESSION['full_name'] ?? 'User';
$username = $_SESSION['username'] ?? 'user';

// Include activity logger for notifications
require_once 'activity_logger.php';

// Get notifications count from real data
try {
    $notification_count = getNotificationCount();
} catch (Exception $e) {
    $notification_count = 0;
}

// Get user profile image
$user_profile_image = '';
$profile_image_path = '';
$profile_image_url = '';
try {
    $db = Database::getInstance();
    $stmt = $db->query("SELECT profile_image FROM users WHERE id = ?", [$_SESSION['user_id'] ?? 0]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_profile_image = $user_data['profile_image'] ?? '';
    // Get absolute path for file_exists check
    $profile_image_path = dirname(__DIR__) . '/assets/images/profiles/' . $user_profile_image;
    // Build absolute URL for img src
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $profile_image_url = $protocol . '://' . $host . '/hospital-management-system/assets/images/profiles/' . $user_profile_image;
} catch (Exception $e) {}

// Get recent activities for notification dropdown
try {
    $recent_activities = getRecentActivities(10);
    $formatted_activities = array_map('formatActivityForDisplay', $recent_activities);
} catch (Exception $e) {
    $formatted_activities = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="SIWOT Hospital Management System - Comprehensive healthcare administration platform">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#9E2A1E">
    
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; ?>SIWOT HMS</title>
    
    <!-- Tailwind CSS via CDN -->
    <script>
    (function(){
        var w=console.warn;
        console.warn=function(m){
            if(typeof m==='string'&&m.indexOf('cdn.tailwindcss.com')!==-1&&m.indexOf('production')!==-1)return;
            w.apply(console,arguments);
        };
    })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#FDF2F1',
                            100: '#FCE5E3',
                            200: '#F9D1CE',
                            300: '#F4B3AD',
                            400: '#EC8A82',
                            500: '#B53B2E',
                            600: '#9E2A1E',
                            700: '#7A1F16',
                            800: '#5C1912',
                            900: '#3B100C',
                        },
                        primary: {
                            50: '#FDF2F1',
                            100: '#FCE5E3',
                            200: '#F9D1CE',
                            300: '#F4B3AD',
                            400: '#EC8A82',
                            500: '#B53B2E',
                            600: '#9E2A1E',
                            700: '#7A1F16',
                            800: '#5C1912',
                            900: '#3B100C',
                        },
                        medical: {
                            blue: '#0EA5E9',
                            green: '#10B981',
                            teal: '#14B8A6',
                            cyan: '#06B6D4',
                            amber: '#F59E0B',
                            violet: '#8B5CF6',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
                        'glow': '0 0 20px rgba(158, 42, 30, 0.15)',
                        'card': '0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06)',
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'slide-down': 'slideDown 0.2s ease-out',
                        'fade-in': 'fadeIn 0.2s ease-out',
                    },
                    keyframes: {
                        slideDown: {
                            '0%': { transform: 'translateY(-10px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' },
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' },
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #cbd5e1 0%, #94a3b8 100%);
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #94a3b8 0%, #64748b 100%);
        }
        
        /* Header Glassmorphism */
        .header-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(158, 42, 30, 0.08);
        }
        
        /* Dropdown Styles - Explicit display control */
        .dropdown-menu {
            display: none;
            position: absolute;
            animation: slideDown 0.2s ease-out;
            transform-origin: top right;
        }
        
        .dropdown-menu.show {
            display: block !important;
        }
        
        /* Notification Badge Pulse */
        .notification-badge {
            animation: pulse-slow 2s infinite;
        }
        
        /* Mobile notification dropdown - fixed position */
        @media (max-width: 639px) {
            #notificationDropdown {
                position: fixed !important;
                top: 60px !important;
                left: 0.5rem !important;
                right: 0.5rem !important;
                width: auto !important;
            }
        }
        
        /* Hover Transitions */
        .hover-lift {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .hover-lift:hover {
            transform: translateY(-1px);
        }
        
        /* Card Hover Effect */
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Modal System */
        .modal-overlay {
            display: flex;
            position: fixed;
            inset: 0;
            z-index: 9999 !important;
            min-height: 100%;
            min-height: 100dvh;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            padding: 0.5rem;
            padding-left: max(0.5rem, env(safe-area-inset-left));
            padding-right: max(0.5rem, env(safe-area-inset-right));
            padding-top: max(0.5rem, env(safe-area-inset-top));
            padding-bottom: max(0.5rem, env(safe-area-inset-bottom));
            align-items: flex-start;
            justify-content: center;
            box-sizing: border-box;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(4px);
        }
        /* Fix for modal containers with z-50 - override to be above header */
        [class*="fixed"][class*="inset-0"][class*="z-50"][class*="overflow-y-auto"] {
            z-index: 9999 !important;
        }
        /* Also target modal dialogs */
        .modal-dialog {
            z-index: 10000 !important;
        }
        @media (min-width: 640px) {
            .modal-overlay {
                padding: 1rem;
                align-items: center;
            }
        }
        .modal-dialog {
            width: 100%;
            max-width: 100%;
            max-height: calc(100vh - 2rem);
            max-height: calc(100dvh - 2rem);
            margin: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
            background: white;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        @media (min-width: 640px) {
            .modal-dialog {
                margin: 1.75rem auto;
                max-height: 90vh;
                max-width: 600px;
                border-radius: 16px;
            }
        }
        /* Mobile bottom sheet modal */
        @media (max-width: 639px) {
            .modal-overlay {
                align-items: flex-end !important;
                padding: 0 !important;
            }
            .modal-dialog {
                border-bottom-left-radius: 0;
                border-bottom-right-radius: 0;
                max-height: 85dvh;
                margin: 0 !important;
            }
        }
        .modal-dialog-content {
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            flex: 1;
            min-height: 0;
            padding: 1rem;
        }
        @media (min-width: 640px) {
            .modal-dialog-content {
                padding: 1.5rem;
            }
        }
        .modal-banner {
            background: linear-gradient(135deg, #9E2A1E 0%, #B53B2E 100%);
            height: 6px;
        }
        .modal-header-bg {
            background: linear-gradient(135deg, #9E2A1E 0%, #B53B2E 100%);
            padding: 1.5rem;
        }
        .modal-close-btn {
            color: #9E2A1E;
            transition: all 0.2s;
        }
        .modal-close-btn:hover {
            background-color: #FDF2F1;
            transform: rotate(90deg);
        }
        
        /* Focus Visible */
        *:focus-visible {
            outline: 2px solid #9E2A1E;
            outline-offset: 2px;
        }
        
        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
        
        /* Page Content Wrapper */
        .page-content {
            background: whitesmoke;
            min-height: calc(100vh - 4rem);
            animation: fadeIn 0.4s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideDown {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body class="bg-[#F7F4F0] antialiased text-gray-800">

    <!-- Top Navigation Bar -->
    <nav class="fixed top-0 left-0 right-0 z-[100] header-glass h-16 lg:h-18">
        <div class="h-full max-w-[1920px] mx-auto px-4 lg:px-6 flex items-center justify-between gap-4 relative">
            
            <!-- Left: Logo & Brand -->
            <div class="flex items-center gap-3 lg:gap-4 flex-shrink-0">
                <!-- Mobile Menu Toggle -->
                <button id="sidebarToggle" 
                        onclick="toggleSidebar()"
                        class="lg:hidden w-10 h-10 rounded-xl hover:bg-brand-50 active:bg-brand-100 transition-all duration-200 flex items-center justify-center text-brand-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                
                <!-- Logo -->
                <a href="<?php echo $base_path ?? ''; ?>dashboard" class="flex items-center gap-3 group">
                    <div class="relative w-10 h-10 lg:w-11 lg:h-11 rounded-xl flex items-center justify-center shadow-lg group-hover:shadow-brand-600/30 transition-all duration-300 group-hover:scale-105">
                        <img src="<?php echo $base_path ?? ''; ?>assets/images/logo.jpeg" alt="Logo" class="w-10 h-10 lg:w-11 lg:h-11 rounded-xl object-cover">
                        <div class="absolute -bottom-1 -right-1 w-4 h-4 bg-emerald-500 rounded-full border-2 border-white flex items-center justify-center">
                            <div class="w-1.5 h-1.5 bg-white rounded-full animate-pulse"></div>
                        </div>
                    </div>
                    <div class="hidden sm:block">
                        <h1 class="text-lg lg:text-xl font-bold text-gray-900 leading-tight tracking-tight group-hover:text-brand-600 transition-colors">SIWOT</h1>
                        <p class="text-[10px] lg:text-xs text-brand-600 font-semibold tracking-wider uppercase -mt-0.5">Hospital Management</p>
                    </div>
                </a>
            </div>
            
            <!-- Right: Actions & User -->
            <div class="flex items-center gap-1 lg:gap-3 flex-shrink-0 ml-auto">
                
                <!-- Quick Action Buttons (Desktop) -->
                <div class="hidden lg:flex items-center gap-1 mr-2">
                    <a href="<?php echo $base_path ?? ''; ?>patients/view_patients#add" 
                       class="w-9 h-9 rounded-lg hover:bg-brand-50 text-gray-500 hover:text-brand-600 transition-all duration-200 flex items-center justify-center relative group"
                       title="Add Patient">
                        <i class="fas fa-user-plus"></i>
                        <span class="absolute -bottom-8 left-1/2 -translate-x-1/2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">Add Patient</span>
                    </a>
                    <a href="<?php echo $base_path ?? ''; ?>opd/consultation" 
                       class="w-9 h-9 rounded-lg hover:bg-brand-50 text-gray-500 hover:text-brand-600 transition-all duration-200 flex items-center justify-center relative group"
                       title="Consultation">
                        <i class="fas fa-stethoscope"></i>
                        <span class="absolute -bottom-8 left-1/2 -translate-x-1/2 px-2 py-1 bg-gray-800 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">Consultation</span>
                    </a>
                    <div class="w-px h-6 bg-gray-200 mx-1"></div>
                </div>
                
                <!-- Notifications -->
                <div class="relative" id="notificationContainer">
                    <button id="notificationBtn" 
                            onclick="toggleNotifications(event)"
                            type="button"
                            class="relative w-11 h-11 sm:w-10 sm:h-10 rounded-xl hover:bg-brand-50 active:bg-brand-100 text-gray-600 hover:text-brand-600 transition-all duration-200 flex items-center justify-center focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 touch-manipulation">
                        <i class="fas fa-bell text-lg"></i>
                        <?php if ($notification_count > 0): ?>
                        <span class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1.5 bg-red-500 rounded-full border-2 border-white notification-badge flex items-center justify-center text-xs font-bold text-white">
                            <?php echo $notification_count > 99 ? '99+' : $notification_count; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div id="notificationDropdown" 
                         class="dropdown-menu right-2 sm:right-0 mt-3 w-[calc(100vw-2rem)] sm:w-80 md:w-96 bg-white rounded-2xl shadow-soft border border-gray-100 overflow-hidden z-[9999] fixed sm:absolute">
                        <!-- Header -->
                        <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-brand-50 to-white flex items-center justify-between">
                            <div>
                                <h3 class="font-bold text-gray-900">Notifications</h3>
                                <p class="text-xs text-brand-600 mt-0.5 font-medium"><?php echo $notification_count; ?> unread</p>
                            </div>
                            <button onclick="markAllRead()" type="button" class="text-xs font-semibold text-brand-600 hover:text-brand-700 px-3 py-1 rounded-full hover:bg-brand-100 transition-colors">
                                Mark all read
                            </button>
                        </div>
                        
                        <!-- Notification List -->
                        <div class="max-h-[60vh] sm:max-h-80 overflow-y-auto custom-scroll">
                            <div class="divide-y divide-gray-50">
                                <?php if (empty($formatted_activities)): ?>
                                <div class="px-5 py-8 text-center">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 flex items-center justify-center mb-3">
                                        <i class="fas fa-bell-slash text-gray-400"></i>
                                    </div>
                                    <p class="text-sm text-gray-500">No recent activities</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($formatted_activities as $activity): ?>
                                    <div class="px-5 py-4 hover:bg-brand-50/50 cursor-pointer transition-colors group">
                                        <div class="flex items-start gap-3">
                                            <div class="w-9 h-9 rounded-full <?php echo $activity['bg_color']; ?> text-<?php echo $activity['icon_color']; ?> flex items-center justify-center flex-shrink-0 mt-0.5">
                                                <i class="fas <?php echo $activity['icon']; ?> text-sm"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 group-hover:text-brand-700 transition-colors">
                                                    <?php echo htmlspecialchars($activity['action']); ?>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    <?php echo htmlspecialchars($activity['module']); ?> - by <?php echo htmlspecialchars($activity['user']); ?>
                                                </p>
                                                <p class="text-[10px] text-brand-500 font-medium mt-1.5 flex items-center gap-1">
                                                    <i class="far fa-clock"></i> <?php echo htmlspecialchars($activity['time_ago']); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50">
                            <a href="<?php echo $base_path ?? ''; ?>admin/activity_logs" class="flex items-center justify-center gap-2 text-sm font-semibold text-brand-600 hover:text-brand-700 transition-colors">
                                View all notifications
                                <i class="fas fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="relative" id="userMenuContainer">
                    <button id="userMenuBtn" 
                            onclick="toggleUserMenu(event)"
                            type="button"
                            class="flex items-center gap-3 p-1.5 pr-3 rounded-xl hover:bg-brand-50 active:bg-brand-100 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 group">
                        <div class="relative">
                            <?php if (!empty($user_profile_image) && !empty($profile_image_path) && file_exists($profile_image_path)): ?>
                            <img src="<?php echo $profile_image_url; ?>" 
                                 alt="Profile" class="w-9 h-9 lg:w-10 lg:h-10 rounded-full object-cover shadow-md group-hover:shadow-lg">
                            <?php else: ?>
                            <div class="w-9 h-9 lg:w-10 lg:h-10 rounded-full bg-gradient-to-br from-brand-600 to-brand-500 flex items-center justify-center text-white font-bold text-sm shadow-md group-hover:shadow-lg transition-all">
                                <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                            <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 rounded-full border-2 border-white"></span>
                        </div>
                        <div class="hidden md:block text-left">
                            <p class="text-sm font-bold text-gray-900 leading-tight group-hover:text-brand-700 transition-colors"><?php echo htmlspecialchars($full_name); ?></p>
                            <p class="text-xs text-brand-600 font-medium capitalize"><?php echo htmlspecialchars($user_role); ?></p>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-xs hidden md:block transition-transform duration-200" id="userChevron"></i>
                    </button>
                    
                    <!-- User Dropdown -->
                    <div id="userDropdown" 
                         class="dropdown-menu right-0 mt-3 w-64 bg-white rounded-2xl shadow-soft border border-gray-100 overflow-hidden z-[9999]">
                        <!-- User Info Header -->
                        <div class="px-5 py-4 bg-gradient-to-br from-brand-600 to-brand-500 text-white">
                            <div class="flex items-center gap-3">
                                <?php if (!empty($user_profile_image) && !empty($profile_image_path) && file_exists($profile_image_path)): ?>
                                <img src="<?php echo $profile_image_url; ?>" 
                                     alt="Profile" class="w-12 h-12 rounded-full object-cover border-2 border-white/30">
                                <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white font-bold text-lg border-2 border-white/30">
                                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-white truncate"><?php echo htmlspecialchars($full_name); ?></p>
                                    <p class="text-xs text-white/80 truncate"><?php echo htmlspecialchars($username); ?>@siwot.hms</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Menu Items -->
                        <div class="p-2">
                            <a href="<?php echo $base_path ?? ''; ?>admin/profile" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition-colors">
                                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span>My Profile</span>
                            </a>
                            <a href="<?php echo $base_path ?? ''; ?>admin/system_settings" class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium text-gray-700 hover:bg-brand-50 hover:text-brand-700 transition-colors">
                                <div class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center text-gray-500">
                                    <i class="fas fa-cog"></i>
                                </div>
                                <span>Settings</span>
                            </a>
                        </div>
                        
                        <!-- Logout -->
                        <div class="p-2 border-t border-gray-100 bg-gray-50/50">
                            <a href="<?php echo $base_path ?? ''; ?>logout" 
                               class="flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors group">
                                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center text-red-500 group-hover:bg-red-600 group-hover:text-white transition-colors">
                                    <i class="fas fa-sign-out-alt"></i>
                                </div>
                                <span>Sign Out</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Spacer for fixed header -->
    <div class="h-16 lg:h-18"></div>
    
    <!-- Hero Banner -->
    <?php if (isset($page_title)): ?>
    <div class="bg-whitesmoke text-gray-800 py-8 px-4 lg:px-8">
        <div class="max-w-7xl mx-auto pl-0 lg:pl-64">
            <h1 class="text-2xl lg:text-3xl font-bold"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (isset($page_description)): ?>
            <p class="mt-2 text-gray-600"><?php echo htmlspecialchars($page_description); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Page Content Wrapper -->
    <div class="min-h-[calc(100vh-4rem)] flex flex-col">

    <!-- JavaScript for Dropdowns -->
    <script>
        // Global dropdown management
        let activeDropdown = null;

        // Toggle Notifications
        function toggleNotifications(event) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            const dropdown = document.getElementById('notificationDropdown');
            const userDropdown = document.getElementById('userDropdown');
            
            if (!dropdown) {
                console.error('Notification dropdown not found');
                return;
            }
            
            // Close user dropdown if open
            if (userDropdown) {
                userDropdown.classList.remove('show');
            }
            
            // Toggle notification dropdown
            const isShowing = dropdown.classList.contains('show');
            
            if (isShowing) {
                dropdown.classList.remove('show');
                activeDropdown = null;
            } else {
                dropdown.classList.add('show');
                activeDropdown = 'notifications';
            }
        }

        // Mark all notifications as read
        function markAllRead() {
            // Determine base path for API
            const pathMatch = window.location.pathname.match(/\/(admin|opd|nursing|laboratory|pharmacy|theatre|reports|patients)\//);
            const basePath = pathMatch ? '../' : '';
            
            fetch(basePath + 'api/mark_notifications_read')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.querySelector('#notificationBtn span');
                        if (badge) badge.style.display = 'none';
                        
                        const unreadText = document.querySelector('#notificationDropdown .text-brand-600');
                        if (unreadText) unreadText.textContent = '0 unread';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        // Toggle User Menu
        function toggleUserMenu(event) {
            if (event) {
                event.stopPropagation();
                event.preventDefault();
            }
            
            const dropdown = document.getElementById('userDropdown');
            const notificationDropdown = document.getElementById('notificationDropdown');
            const chevron = document.getElementById('userChevron');
            
            if (!dropdown) {
                console.error('User dropdown not found');
                return;
            }
            
            // Close notification dropdown if open
            if (notificationDropdown) {
                notificationDropdown.classList.remove('show');
            }
            
            // Toggle user dropdown
            const isShowing = dropdown.classList.contains('show');
            
            if (isShowing) {
                dropdown.classList.remove('show');
                if (chevron) chevron.classList.remove('rotate-180');
                activeDropdown = null;
            } else {
                dropdown.classList.add('show');
                if (chevron) chevron.classList.add('rotate-180');
                activeDropdown = 'user';
            }
        }

        // Close all dropdowns
        function closeAllDropdowns() {
            const notificationDropdown = document.getElementById('notificationDropdown');
            const userDropdown = document.getElementById('userDropdown');
            const chevron = document.getElementById('userChevron');
            
            if (notificationDropdown) notificationDropdown.classList.remove('show');
            if (userDropdown) userDropdown.classList.remove('show');
            if (chevron) chevron.classList.remove('rotate-180');
            
            activeDropdown = null;
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            const notificationContainer = document.getElementById('notificationContainer');
            const userMenuContainer = document.getElementById('userMenuContainer');
            
            const clickedNotification = notificationContainer && notificationContainer.contains(e.target);
            const clickedUser = userMenuContainer && userMenuContainer.contains(e.target);
            
            if (!clickedNotification && !clickedUser) {
                closeAllDropdowns();
            }
        });

        // Escape key to close dropdowns
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });
    </script>