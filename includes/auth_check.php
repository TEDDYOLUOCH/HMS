<?php
/**
 * Hospital Management System - Authentication & Security Check
 * Session management, role-based access, and security functions
 */

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters before starting
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

// Security Headers (prevent clickjacking, XSS, etc.)
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // HSTS for HTTPS connections
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self' https://cdn.jsdelivr.net;");
}

// Regenerate session ID on privilege change (login, role change)
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
    $_SESSION['created_at'] = time();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in
 */
function requireLogin($redirect = 'auth/login') {
    if (!isLoggedIn()) {
        // Check for remember me token
        if (isset($_COOKIE['hms_remember'])) {
            // Validate remember token
            require_once '../config/database.php';
            $db = Database::getInstance();
            
            $token = $_COOKIE['hms_remember'];
            $stmt = $db->prepare("SELECT user_id, expires FROM remember_tokens WHERE token = ? AND expires > NOW()");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                // Restore session
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
                $stmt->execute([$result['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    // Regenerate session ID
                    session_regenerate_id(true);
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    $_SESSION['last_activity'] = time();
                    
                    // Delete used token
                    $stmt = $db->prepare("DELETE FROM remember_tokens WHERE token = ?");
                    $stmt->execute([$token]);
                    
                    // Redirect to dashboard
                    header('Location: ../dashboard');
                    exit;
                }
            }
            
            // Invalid token - delete cookie
            setcookie('hms_remember', '', time() - 3600, '/', '', true, true);
        }
        
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * Check session timeout (30 minutes)
 */
function checkSessionTimeout($timeout = 1800) {
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        
        if ($elapsed > $timeout) {
            // Session expired
            session_unset();
            session_destroy();
            return false;
        }
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Check user role permission
 */
function checkRole($roles, $redirect = '../dashboard') {
    requireLogin();
    
    if (!checkSessionTimeout()) {
        header('Location: auth/login.php?timeout=1');
        exit;
    }
    
    if (!in_array($_SESSION['user_role'], (array)$roles)) {
        header('Location: ' . $redirect);
        exit;
    }
    
    return true;
}

/**
 * Require specific role
 */
function requireRole($roles, $redirect = '../dashboard') {
    requireLogin();
    
    if (!checkSessionTimeout()) {
        header('Location: auth/login.php?timeout=1');
        exit;
    }
    
    if (!in_array($_SESSION['user_role'], (array)$roles)) {
        $_SESSION['error'] = 'You do not have permission to access this page';
        header('Location: ' . $redirect);
        exit;
    }
    
    return true;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Check if user is doctor
 */
function isDoctor() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'doctor';
}

/**
 * Generate CSRF token
 */
function csrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrf($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    // Timing-safe comparison
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate unique token for forms
 */
function generateToken() {
    return bin2hex(random_bytes(16));
}

/**
 * Log security event
 */
function logSecurityEvent($event, $details = '') {
    try {
        $log_entry = date('Y-m-d H:i:s') . " | IP: " . getClientIP() . " | Event: $event | Details: $details\n";
        file_put_contents(__DIR__ . '/../logs/security.log', $log_entry, FILE_APPEND);
    } catch (Exception $e) {
        // Silently fail
    }
}

/**
 * Get client IP address (including proxy)
 */
function getClientIP() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle multiple IPs
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            return $ip;
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Check for suspicious activity
 */
function detectSuspiciousActivity() {
    $indicators = 0;
    
    // Check for common attack patterns in GET/POST
    $attack_patterns = [
        '/<script/i', '/javascript:/i', '/on\w+\s*=/i',
        '/union\s+select/i', '/exec\s*\(/i', '/drop\s+table/i'
    ];
    
    foreach ($_GET as $value) {
        foreach ($attack_patterns as $pattern) {
            if (preg_match($pattern, (string)$value)) {
                $indicators++;
                logSecurityEvent('SUSPICIOUS_GET', "Pattern: $pattern | Value: " . substr($value, 0, 100));
            }
        }
    }
    
    foreach ($_POST as $value) {
        foreach ($attack_patterns as $pattern) {
            if (is_string($value) && preg_match($pattern, $value)) {
                $indicators++;
                logSecurityEvent('SUSPICIOUS_POST', "Pattern: $pattern");
            }
        }
    }
    
    // Block if too many indicators
    if ($indicators > 5) {
        logSecurityEvent('BLOCKED', 'Too many suspicious indicators');
        http_response_code(403);
        die('Access denied - Suspicious activity detected');
    }
    
    return $indicators;
}

/**
 * Rate limiting check
 */
function checkRateLimit($action, $max_attempts = 5, $timeframe = 300) {
    $ip = getClientIP();
    $key = "rate_limit_{$action}_{$ip}";
    
    $attempts = (int)($_SESSION[$key]['attempts'] ?? 0);
    $first_attempt = $_SESSION[$key]['first_attempt'] ?? time();
    
    // Reset if timeframe has passed
    if (time() - $first_attempt > $timeframe) {
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
        return true;
    }
    
    // Check limit
    if ($attempts >= $max_attempts) {
        $remaining = $timeframe - (time() - $first_attempt);
        logSecurityEvent('RATE_LIMIT_EXCEEDED', "Action: $action | Attempts: $attempts");
        return ['blocked' => true, 'remaining' => $remaining];
    }
    
    // Increment attempts
    $_SESSION[$key]['attempts'] = $attempts + 1;
    return ['blocked' => false, 'remaining' => $max_attempts - $attempts - 1];
}

/**
 * Sanitize output for XSS prevention
 */
function sanitizeOutput($data) {
    if (is_array($data)) {
        return array_map('sanitizeOutput', $data);
    }
    
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML in user input for display
 */
function h($string) {
    return sanitizeOutput($string);
}

/**
 * Log all data modifications (audit trail)
 */
function logDataChange($table, $record_id, $action, $old_data = null, $new_data = null) {
    try {
        require_once '../config/database.php';
        $db = Database::getInstance();
        
        $user_id = $_SESSION['user_id'] ?? 0;
        
        // Store old and new values as JSON
        $old_json = $old_data ? json_encode($old_data) : null;
        $new_json = $new_data ? json_encode($new_data) : null;
        
        $stmt = $db->prepare("INSERT INTO data_audit_log 
            (user_id, table_name, record_id, action, old_values, new_values, ip_address, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        
        $stmt->execute([
            $user_id,
            $table,
            $record_id,
            $action,
            $old_json,
            $new_json,
            getClientIP()
        ]);
        
    } catch (Exception $e) {
        // Log error but don't break the application
        error_log("Failed to log data change: " . $e->getMessage());
    }
}

// Run suspicious activity detection
detectSuspiciousActivity();
