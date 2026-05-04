<?php
/**
 * Hospital Management System - Logout
 * Handles user logout with session destruction
 */

// Start session
session_start();

// Include database for activity logging (optional)
try {
    require_once 'config/database.php';
    
    // Log the logout activity (optional - requires activity_logs table)
    if (isset($_SESSION['user_id'])) {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (:user_id, 'Logged out', NOW())");
            $stmt->execute(['user_id' => $_SESSION['user_id']]);
        } catch (Exception $e) {
            // Silently fail if table doesn't exist
        }
    }
} catch (Exception $e) {
    // Continue even if database is not available
}

// Destroy all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Clear remember me cookie if set
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login page with success message
header('Location: auth/login.php?logged_out=1');
exit;
