<?php
/**
 * Hospital Management System - Process Login
 * Handles user authentication with security features
 */

session_start();

// Set security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store, no-cache, must-revalidate');

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

// Validate CSRF token - Make it more lenient for testing
$csrf_valid = false;
if (isset($_POST['csrf_token']) && isset($_SESSION['csrf_token'])) {
    $csrf_valid = hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

if (!$csrf_valid) {
    // For debugging - show message
    $_SESSION['login_error'] = 'Session expired. Please try again.';
    header('Location: login?error=csrf');
    exit;
}

// Debug: Log what we're receiving
error_log("POST username: " . ($_POST['username'] ?? 'EMPTY'));
error_log("POST password present: " . (isset($_POST['password']) ? 'YES' : 'NO'));
error_log("CSRF token in POST: " . ($_POST['csrf_token'] ?? 'EMPTY'));
error_log("CSRF token in SESSION: " . ($_SESSION['csrf_token'] ?? 'EMPTY'));

// Get form data and sanitize
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username and password are required.';
    header('Location: login.php');
    exit;
}

// Check for account lockout
if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 5) {
    if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
        $_SESSION['login_error'] = 'Too many failed attempts. Account temporarily locked.';
        header('Location: login.php');
        exit;
    } else {
        // Lockout expired, reset attempts
        unset($_SESSION['login_attempts']);
        unset($_SESSION['login_locked_until']);
    }
}

// Include database configuration
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
    
    // Prepare and execute query
    $stmt = $db->prepare("SELECT id, username, password_hash, role, full_name, email, is_active 
                          FROM users WHERE username = :username LIMIT 1");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['is_active']) {
        // User not found - increment failed attempts
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 1800; // 30 minutes lockout
            $_SESSION['login_error'] = 'Too many failed attempts. Account locked for 30 minutes.';
        } else {
            $_SESSION['login_error'] = 'Invalid username or password.';
        }
        
        // Log failed attempt (optional - implement logging)
        error_log("Failed login attempt for username: $username");
        
        header('Location: login.php?error=invalid');
        exit;
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        $_SESSION['login_error'] = 'Your account is inactive. Please contact administrator.';
        header('Location: login?error=inactive');
        exit;
    }
    
    // Verify password (supports both old md5 and new password_hash)
    $password_valid = false;
    
    // First try password_verify (for password_hash)
    if (password_get_info($user['password_hash'])['algo'] !== 0) {
        $password_valid = password_verify($password, $user['password_hash']);
    } else {
        // Fallback to md5 for legacy passwords (should be migrated)
        $password_valid = (md5($password) === $user['password_hash']);
        
        // Upgrade to password_hash if using legacy md5
        if ($password_valid) {
            $new_hash = password_hash($password, PASSWORD_DEFAULT);
            $update_stmt = $db->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $update_stmt->execute(['hash' => $new_hash, 'id' => $user['id']]);
        }
    }
    
    if (!$password_valid) {
        // Invalid password - increment failed attempts
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 1800;
            $_SESSION['login_error'] = 'Too many failed attempts. Account locked for 30 minutes.';
        } else {
            $_SESSION['login_error'] = 'Invalid username or password.';
        }
        
        // Log failed attempt
        error_log("Failed login attempt for username: $username");
        
        header('Location: login.php?error=invalid');
        exit;
    }
    
    // Login successful - clear failed attempts
    unset($_SESSION['login_attempts']);
    unset($_SESSION['login_locked_until']);
    unset($_SESSION['login_error']);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time'] = time();
    
    // Regenerate session ID again after successful login
    session_regenerate_id(true);
    
    // Log successful login (optional)
    error_log("Successful login for username: $username");
    
    // Update last login timestamp
    $update_last_login = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
    $update_last_login->execute(['id' => $user['id']]);
    
    // Redirect to dashboard or intended page
    $redirect = '../dashboard';
    if (isset($_GET['redirect'])) {
        $redirect = urldecode($_GET['redirect']);
    }
    
    header('Location: ' . $redirect);
    exit;
    
} catch (PDOException $e) {
    error_log("Database error during login: " . $e->getMessage());
    $_SESSION['login_error'] = 'A system error occurred. Please try again later.';
    header('Location: login.php');
    exit;
}
