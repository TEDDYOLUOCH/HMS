<?php
/**
 * API endpoint to mark all notifications as read
 */

session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

// Set response type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Mark all notifications as read
$result = markAllNotificationsAsRead();

if ($result) {
    echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to mark notifications as read']);
}
