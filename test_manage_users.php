<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simulate session
session_start();
$_SESSION['user_id'] = 1;

require_once 'config/database.php';

$db = Database::getInstance();

// Test the exact query from manage_users.php line 66
try {
    $username = 'test_user';
    $role = 'doctor';
    $department = 'test';
    
    $stmt = $db->query("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, 'User Created', ?, NOW())", [$_SESSION['user_id'], "Created user: $username with role: $role, dept: $department"]);
    echo "manage_users.php style insert: SUCCESS!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
