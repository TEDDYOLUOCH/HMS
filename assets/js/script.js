/**
 * Hospital Management System - JavaScript Utilities
 * Interactive features, accessibility, and keyboard shortcuts
 */

// ============================================
// DOM READY
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initAutoHideAlerts();
    initConfirmDialogs();
    initFormValidation();
    initKeyboardShortcuts();
    initFontSizeControls();
    initHighContrastToggle();
    initLoadingButtons();
    initPrintStyles();
    
    // Set up auto-save draft functionality
    initAutoSaveDrafts();
});

// ============================================
// TOAST NOTIFICATIONS
// ============================================
function showToast(message, type = 'info', duration = 5000) {
    // Remove existing toast container if not present
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');
    
    // Icon mapping
    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
    };
    
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || icons.info}</span>
        <span class="toast-message">${escapeHtml(message)}</span>
        <button class="toast-close" aria-label="Close notification">&times;</button>
    `;
    
    // Add close handler
    toast.querySelector('.toast-close').addEventListener('click', function() {
        removeToast(toast);
    });
    
    // Add to container
    container.appendChild(toast);
    
    // Auto-remove after duration
    if (duration > 0) {
        setTimeout(() => removeToast(toast), duration);
    }
    
    // Announce to screen readers
    announceToScreenReader(message);
    
    return toast;
}

function removeToast(toast) {
    if (toast && toast.parentElement) {
        toast.style.animation = 'slideOut 0.2s ease-in forwards';
        setTimeout(() => {
            if (toast.parentElement) {
                toast.parentElement.removeChild(toast);
            }
        }, 200);
    }
}

// Add slideOut animation
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// ============================================
// AUTO-HIDE ALERTS
// ============================================
function initAutoHideAlerts() {
    const alerts = document.querySelectorAll('.alert-auto-hide');
    
    alerts.forEach(alert => {
        // Auto-hide after 5 seconds
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
}

// ============================================
// CONFIRMATION DIALOGS
// ============================================
function initConfirmDialogs() {
    // Add confirmation to all forms with data-confirm attribute
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = form.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Add confirmation to links with data-confirm attribute
    document.querySelectorAll('a[data-confirm]').forEach(link => {
        link.addEventListener('click', function(e) {
            const message = link.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

// Custom confirm dialog
function showConfirmDialog(options) {
    return new Promise((resolve) => {
        const {
            title = 'Confirm Action',
            message = 'Are you sure you want to proceed?',
            confirmText = 'Confirm',
            cancelText = 'Cancel',
            type = 'warning' // 'warning' or 'danger'
        } = options;
        
        // Create backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'confirm-title');
        
        // Create dialog
        const dialog = document.createElement('div');
        dialog.className = 'modal';
        dialog.style.maxWidth = '400px';
        
        dialog.innerHTML = `
            <div class="modal-body">
                <div class="confirm-dialog">
                    <div class="confirm-icon ${type}">
                        ${type === 'danger' ? '⚠' : '?'}
                    </div>
                    <h3 class="confirm-title" id="confirm-title">${escapeHtml(title)}</h3>
                    <p class="confirm-message">${escapeHtml(message)}</p>
                    <div class="confirm-actions">
                        <button class="btn btn-secondary" id="confirm-cancel">${escapeHtml(cancelText)}</button>
                        <button class="btn ${type === 'danger' ? 'btn-danger' : 'btn-primary'}" id="confirm-ok">${escapeHtml(confirmText)}</button>
                    </div>
                </div>
            </div>
        `;
        
        backdrop.appendChild(dialog);
        document.body.appendChild(backdrop);
        
        // Focus trap
        const cancelBtn = dialog.querySelector('#confirm-cancel');
        const okBtn = dialog.querySelector('#confirm-ok');
        
        // Handle cancel
        cancelBtn.addEventListener('click', () => {
            document.body.removeChild(backdrop);
            resolve(false);
        });
        
        // Handle confirm
        okBtn.addEventListener('click', () => {
            document.body.removeChild(backdrop);
            resolve(true);
        });
        
        // Handle backdrop click
        backdrop.addEventListener('click', (e) => {
            if (e.target === backdrop) {
                document.body.removeChild(backdrop);
                resolve(false);
            }
        });
        
        // Handle Escape key
        document.addEventListener('keydown', function escapeHandler(e) {
            if (e.key === 'Escape') {
                document.body.removeChild(backdrop);
                document.removeEventListener('keydown', escapeHandler);
                resolve(false);
            }
        });
        
        // Focus confirm button
        okBtn.focus();
    });
}

// ============================================
// FORM VALIDATION
// ============================================
function initFormValidation() {
    // Real-time validation for required fields
    document.querySelectorAll('input[required], select[required], textarea[required]').forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField(this);
            }
        });
    });
    
    // Form submit validation
    document.querySelectorAll('form[data-validate]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            form.querySelectorAll('[required]').forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showToast('Please fill in all required fields', 'error');
                return false;
            }
        });
    });
}

function validateField(field) {
    const isValid = field.checkValidity();
    
    if (isValid) {
        field.classList.remove('error');
        field.removeAttribute('aria-invalid');
    } else {
        field.classList.add('error');
        field.setAttribute('aria-invalid', 'true');
        
        // Show error message
        let errorEl = field.parentElement.querySelector('.form-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            field.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = field.validationMessage;
    }
    
    return isValid;
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Don't trigger shortcuts when typing in forms
        if (e.target.matches('input, textarea, select, [contenteditable]')) {
            // Allow Escape to blur
            if (e.key === 'Escape') {
                e.target.blur();
            }
            return;
        }
        
        // Ctrl+S or Cmd+S - Save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const saveBtn = document.querySelector('[data-save-btn]');
            if (saveBtn) {
                saveBtn.click();
            } else {
                // Try to find submit button in current form
                const form = document.querySelector('form');
                if (form) {
                    form.dispatchEvent(new Event('submit', { bubbles: true }));
                }
            }
        }
        
        // Ctrl+N or Cmd+N - New (when on list page)
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const newBtn = document.querySelector('[data-new-btn]');
            if (newBtn) {
                newBtn.click();
            }
        }
        
        // Ctrl+F or Cmd+F - Focus search
        if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
            e.preventDefault();
            const searchInput = document.querySelector('[type="search"], [name="search"], .search-input');
            if (searchInput) {
                searchInput.focus();
            }
        }
        
        // Ctrl+H - Home/Dashboard
        if ((e.ctrlKey || e.metaKey) && e.key === 'h') {
            e.preventDefault();
            window.location.href = 'dashboard.php';
        }
        
        // Ctrl+L - Logout
        if ((e.ctrlKey || e.metaKey) && e.key === 'l') {
            e.preventDefault();
            window.location.href = 'logout.php';
        }
        
        // ? - Show keyboard shortcuts help
        if (e.key === '?') {
            e.preventDefault();
            showKeyboardShortcutsHelp();
        }
    });
}

function showKeyboardShortcutsHelp() {
    const shortcuts = [
        { key: 'Ctrl+S', description: 'Save form' },
        { key: 'Ctrl+N', description: 'New record' },
        { key: 'Ctrl+F', description: 'Search' },
        { key: 'Ctrl+H', description: 'Go to Dashboard' },
        { key: 'Ctrl+L', description: 'Logout' },
        { key: '?', description: 'Show this help' },
        { key: 'Esc', description: 'Close modal / Cancel' }
    ];
    
    let html = '<div style="text-align: left;"><strong>Keyboard Shortcuts</strong><br><br>';
    shortcuts.forEach(s => {
        html += `<div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
            <span class="kbd">${s.key}</span>
            <span>${s.description}</span>
        </div>`;
    });
    html += '</div>';
    
    // Create modal
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.innerHTML = `
        <div class="modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3 class="modal-title">Keyboard Shortcuts</h3>
                <button class="modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body">${html}</div>
        </div>
    `;
    
    backdrop.querySelector('.modal-close').addEventListener('click', () => backdrop.remove());
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) backdrop.remove(); });
    document.body.appendChild(backdrop);
}

// ============================================
// FONT SIZE CONTROLS
// ============================================
function initFontSizeControls() {
    const container = document.querySelector('.page-content') || document.body;
    
    // Create font size controls if they don't exist
    if (!document.querySelector('.font-size-controls')) {
        const controls = document.createElement('div');
        controls.className = 'font-size-controls';
        controls.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            display: flex;
            gap: 8px;
            background: white;
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        `;
        
        controls.innerHTML = `
            <button class="btn btn-sm btn-secondary" data-font-size="normal" aria-label="Normal font size">A</button>
            <button class="btn btn-sm btn-secondary" data-font-size="large" aria-label="Large font size" style="font-size: 14px;">A</button>
            <button class="btn btn-sm btn-secondary" data-font-size="larger" aria-label="Larger font size" style="font-size: 16px;">A</button>
        `;
        
        document.body.appendChild(controls);
        
        // Handle font size changes
        controls.addEventListener('click', function(e) {
            const btn = e.target.closest('[data-font-size]');
            if (!btn) return;
            
            const size = btn.dataset.fontSize;
            
            // Remove existing font size classes
            document.body.classList.remove('font-size-large', 'font-size-larger');
            
            // Add new class
            if (size !== 'normal') {
                document.body.classList.add(`font-size-${size}`);
            }
            
            // Save preference
            localStorage.setItem('hms-font-size', size);
        });
        
        // Load saved preference
        const savedSize = localStorage.getItem('hms-font-size');
        if (savedSize) {
            document.body.classList.add(`font-size-${savedSize}`);
        }
    }
}

// ============================================
// HIGH CONTRAST MODE
// ============================================
function initHighContrastToggle() {
    // Create toggle button
    if (!document.querySelector('.contrast-toggle')) {
        const toggle = document.createElement('button');
        toggle.className = 'contrast-toggle btn btn-sm btn-secondary';
        toggle.setAttribute('aria-label', 'Toggle high contrast mode');
        toggle.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        `;
        toggle.innerHTML = '◐';
        
        toggle.addEventListener('click', function() {
            document.body.classList.toggle('high-contrast');
            const isHighContrast = document.body.classList.contains('high-contrast');
            localStorage.setItem('hms-high-contrast', isHighContrast);
            showToast(isHighContrast ? 'High contrast mode enabled' : 'High contrast mode disabled', 'info');
        });
        
        document.body.appendChild(toggle);
        
        // Load saved preference
        if (localStorage.getItem('hms-high-contrast') === 'true') {
            document.body.classList.add('high-contrast');
        }
    }
}

// ============================================
// LOADING BUTTONS
// ============================================
function initLoadingButtons() {
    // Handle form submissions with loading state
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                setLoadingState(submitBtn);
            }
        });
    });
    
    // Handle button clicks with loading state
    document.querySelectorAll('[data-loading-btn]').forEach(btn => {
        btn.addEventListener('click', function() {
            setLoadingState(this);
        });
    });
}

function setLoadingState(button) {
    const originalText = button.innerHTML;
    const originalDisabled = button.disabled;
    
    // Add loading class
    button.classList.add('loading');
    button.disabled = true;
    
    // Replace text with spinner
    button.innerHTML = '<span class="spinner spinner-sm"></span> Loading...';
    
    // Store original text for restoration
    button.dataset.originalText = originalText;
    button.dataset.originalDisabled = originalDisabled;
    
    // Create cleanup function
    button.restoreState = function() {
        button.classList.remove('loading');
        button.disabled = originalDisabled;
        button.innerHTML = originalText;
    };
    
    // Auto-restore after 10 seconds (fallback)
    setTimeout(() => {
        if (button.classList.contains('loading')) {
            button.restoreState();
        }
    }, 10000);
}

// ============================================
// AUTO-SAVE DRAFTS
// ============================================
function initAutoSaveDrafts() {
    document.querySelectorAll('[data-autosave]').forEach(form => {
        const formId = form.id || 'autosave-' + Math.random().toString(36).substr(2, 9);
        form.id = formId;
        
        // Load saved draft
        loadDraft(formId, form);
        
        // Save draft on input
        let saveTimeout;
        form.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                saveDraft(formId, form);
            }, 2000); // Auto-save after 2 seconds of inactivity
        });
        
        // Clear draft on successful submit
        form.addEventListener('submit', function() {
            clearDraft(formId);
        });
    });
}

function saveDraft(formId, form) {
    const data = new FormData(form);
    const obj = {};
    data.forEach((value, key) => {
        // Don't save passwords or tokens
        if (key.toLowerCase().includes('password') || key.toLowerCase().includes('token')) {
            return;
        }
        obj[key] = value;
    });
    
    localStorage.setItem('draft-' + formId, JSON.stringify({
        data: obj,
        timestamp: Date.now()
    }));
    
    // Show subtle indicator
    const indicator = document.querySelector(`#${formId} .draft-indicator`) || createDraftIndicator(form);
    indicator.textContent = 'Draft saved';
    indicator.style.opacity = '1';
    setTimeout(() => indicator.style.opacity = '0', 2000);
}

function loadDraft(formId, form) {
    const saved = localStorage.getItem('draft-' + formId);
    if (!saved) return;
    
    try {
        const { data, timestamp } = JSON.parse(saved);
        
        // Only load draft if less than 24 hours old
        if (Date.now() - timestamp > 24 * 60 * 60 * 1000) {
            clearDraft(formId);
            return;
        }
        
        // Ask user if they want to restore
        const indicator = createDraftIndicator(form);
        indicator.innerHTML = `
            <button type="button" class="btn btn-sm btn-warning" onclick="restoreDraft('${formId}', this)">
                Restore saved draft
            </button>
        `;
    } catch (e) {
        console.error('Error loading draft:', e);
    }
}

function restoreDraft(formId, button) {
    const saved = localStorage.getItem('draft-' + formId);
    if (!saved) return;
    
    try {
        const { data } = JSON.parse(saved);
        const form = document.getElementById(formId);
        
        Object.keys(data).forEach(key => {
            const field = form.elements[key];
            if (field) {
                field.value = data[key];
            }
        });
        
        button.closest('.draft-indicator').remove();
        showToast('Draft restored', 'success');
    } catch (e) {
        console.error('Error restoring draft:', e);
    }
}

function clearDraft(formId) {
    localStorage.removeItem('draft-' + formId);
}

function createDraftIndicator(form) {
    let indicator = form.querySelector('.draft-indicator');
    if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'draft-indicator';
        indicator.style.cssText = `
            font-size: 12px;
            color: #666;
            transition: opacity 0.5s;
            margin-top: 8px;
        `;
        form.appendChild(indicator);
    }
    return indicator;
}

// ============================================
// PRINT STYLES
// ============================================
function initPrintStyles() {
    // Add print header/footer
    if (window.matchMedia) {
        window.matchMedia('print').addListener(function(mql) {
            if (mql.matches) {
                addPrintHeader();
            } else {
                removePrintHeader();
            }
        });
    }
}

function addPrintHeader() {
    if (document.querySelector('.print-header')) return;
    
    const header = document.createElement('div');
    header.className = 'print-header';
    header.style.cssText = 'display: none;';
    header.innerHTML = `
        <h1>${document.title}</h1>
        <p>Generated: ${new Date().toLocaleString()}</p>
    `;
    document.body.insertBefore(header, document.body.firstChild);
}

function removePrintHeader() {
    const header = document.querySelector('.print-header');
    if (header) header.remove();
}

// ============================================
// SCREEN READER ANNOUNCEMENTS
// ============================================
function announceToScreenReader(message) {
    // Create live region for screen reader announcements
    let announcer = document.querySelector('.sr-only[aria-live]');
    if (!announcer) {
        announcer = document.createElement('div');
        announcer.className = 'sr-only';
        announcer.setAttribute('aria-live', 'polite');
        announcer.setAttribute('aria-atomic', 'true');
        document.body.appendChild(announcer);
    }
    
    // Clear and set message
    announcer.textContent = '';
    setTimeout(() => {
        announcer.textContent = message;
    }, 100);
}

// ============================================
// UTILITY FUNCTIONS
// ============================================
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(date, format = 'default') {
    const d = new Date(date);
    const options = {
        default: { year: 'numeric', month: 'short', day: 'numeric' },
        short: { month: 'short', day: 'numeric' },
        long: { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' },
        time: { hour: '2-digit', minute: '2-digit' }
    };
    return d.toLocaleDateString('en-US', options[format] || options.default);
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Export utilities for global use
window.HMS = {
    showToast,
    showConfirmDialog,
    validateField,
    setLoadingState,
    announceToScreenReader,
    escapeHtml,
    formatDate,
    debounce,
    throttle
};
