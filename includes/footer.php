<?php
// Hospital Management System - Footer
// Calculate page load time if enabled
$page_load_time = defined('START_TIME') ? round(microtime(true) - START_TIME, 3) : null;
?>
    
    </main> <!-- End Main Content -->
</div> <!-- End Page Wrapper -->

<!-- Footer -->
<footer class="bg-white border-t border-gray-200 py-4 px-6 mt-auto">
    <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
        <div class="flex items-center gap-2 text-sm text-gray-500">
            <span>© <?php echo date('Y'); ?> SIWOT Hospital Management System. All rights reserved.</span>
        </div>
        
        </div>
    </div>
</footer>

<!-- Toast Notification Container -->
<div id="toastContainer" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2"></div>

<!-- Alpine.js Collapse plugin (must load before Alpine) -->
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<!-- Alpine.js for interactivity -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<!-- Common JavaScript -->
<script>
    // Use toggleSidebar from sidebar.php - don't redefine
    // The sidebar.php already defines toggleSidebar function
    
    // Submenu toggle - delegated event listener
    document.addEventListener('click', function(e) {
        const submenuBtn = e.target.closest('[data-has-submenu="true"] button');
        if (submenuBtn && e.target.closest('.submenu-container') === null) {
            // Let the inline onclick handle it
        }
    });
    
    // User Dropdown Toggle
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
            document.getElementById('notificationDropdown').classList.add('hidden');
        });
    }
    
    // Notification Dropdown Toggle
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationDropdown.classList.toggle('hidden');
            if (userDropdown) userDropdown.classList.add('hidden');
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', () => {
        if (userDropdown) userDropdown.classList.add('hidden');
        if (notificationDropdown) notificationDropdown.classList.add('hidden');
    });
    
    // Global Search - Keyboard shortcut (Cmd/Ctrl + K)
    const globalSearch = document.getElementById('globalSearch');
    
    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            if (globalSearch) {
                globalSearch.focus();
            }
        }
    });
    
    // Global Search functionality
    if (globalSearch) {
        globalSearch.addEventListener('input', (e) => {
            const query = e.target.value.trim();
            if (query.length >= 2) {
                // Implement search functionality
                console.log('Searching for:', query);
            }
        });
    }
    
    // Toast Notification Function
    function showToast(message, type = 'info', duration = 3000) {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        const colors = {
            success: 'bg-green-50 border-green-200 text-green-800',
            error: 'bg-red-50 border-red-200 text-red-800',
            warning: 'bg-yellow-50 border-yellow-200 text-yellow-800',
            info: 'bg-blue-50 border-blue-200 text-blue-800'
        };
        
        const iconColors = {
            success: 'text-green-500',
            error: 'text-red-500',
            warning: 'text-yellow-500',
            info: 'text-blue-500'
        };
        
        toast.className = `flex items-center gap-3 px-4 py-3 rounded-lg border shadow-lg ${colors[type]} transform transition-all duration-300 translate-x-full opacity-0`;
        toast.innerHTML = `
            <i class="fas ${icons[type]} ${iconColors[type]}"></i>
            <span class="text-sm font-medium">${message}</span>
            <button onclick="this.parentElement.remove()" class="ml-2 opacity-60 hover:opacity-100">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        container.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        }, 10);
        
        // Auto remove
        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }
    
    // Confirm Delete Dialog
    function confirmDelete(message = 'Are you sure you want to delete this item?') {
        return confirm(message);
    }
    
    // Loading Spinner
    function showLoading(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.innerHTML = '<div class="flex justify-center items-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div></div>';
        }
    }
    
    // Format Date
    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString('en-US', options);
    }
    
    // Format Time
    function formatTime(dateString) {
        const options = { hour: '2-digit', minute: '2-digit' };
        return new Date(dateString).toLocaleTimeString('en-US', options);
    }
    
    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', () => {
        const alerts = document.querySelectorAll('.alert-auto-hide');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    });
</script>

</body>
</html>
