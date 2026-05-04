<?php
/**
 * Activity Logger Helper
 * Unified logging function for all CRUD operations in the system
 */

/**
 * Log an activity to the activity_logs table
 * 
 * @param string $action Action type (Created, Updated, Deleted, etc.)
 * @param string $module Module name (Patients, Pharmacy, Lab, etc.)
 * @param string $table_affected Database table affected
 * @param int|null $record_id ID of the affected record
 * @param string|null $details Additional details about the action
 * @param array|null $old_values Old values (for updates/deletes)
 * @param array|null $new_values New values (for creates/updates)
 * @return bool Success status
 */
function logActivity($action, $module, $table_affected = null, $record_id = null, $details = null, $old_values = null, $new_values = null) {
    try {
        $db = Database::getInstance();
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Serialize arrays if provided
        $old_values_json = $old_values ? json_encode($old_values) : null;
        $new_values_json = $new_values ? json_encode($new_values) : null;
        
        $stmt = $db->query(
            "INSERT INTO activity_logs (user_id, action, module, table_affected, record_id, old_values, new_values, ip_address, user_agent, created_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [$user_id, $action, $module, $table_affected, $record_id, $old_values_json, $new_values_json, $ip_address, $user_agent]
        );
        
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent activities for notifications
 * 
 * @param int $limit Number of activities to fetch
 * @param int|null $user_id Filter by user (null for all users)
 * @return array Array of activities
 */
function getRecentActivities($limit = 10, $user_id = null) {
    try {
        $db = Database::getInstance();
        
        if ($user_id) {
            $stmt = $db->query(
                "SELECT al.*, u.username, u.full_name 
                 FROM activity_logs al 
                 LEFT JOIN users u ON al.user_id = u.id 
                 WHERE al.user_id = ? 
                 ORDER BY al.created_at DESC 
                 LIMIT ?",
                [$user_id, $limit]
            );
        } else {
            $stmt = $db->query(
                "SELECT al.*, u.username, u.full_name 
                 FROM activity_logs al 
                 LEFT JOIN users u ON al.user_id = u.id 
                 ORDER BY al.created_at DESC 
                 LIMIT ?",
                [$limit]
            );
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Failed to fetch activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get notification count (activities in last 24 hours)
 * 
 * @return int Count of recent activities
 */
function getNotificationCount() {
    try {
        $db = Database::getInstance();
        
        $stmt = $db->query(
            "SELECT COUNT(*) as count 
             FROM activity_logs 
             WHERE is_read = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Mark all notifications as read
 * 
 * @return bool Success status
 */
function markAllNotificationsAsRead() {
    try {
        $db = Database::getInstance();
        
        $stmt = $db->query(
            "UPDATE activity_logs 
             SET is_read = 1 
             WHERE is_read = 0"
        );
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get activities grouped by type for display
 * 
 * @param int $limit Number of activities per type
 * @return array Grouped activities
 */
function getGroupedActivities($limit = 5) {
    $activities = getRecentActivities(20);
    
    $grouped = [
        'patients' => [],
        'prescriptions' => [],
        'lab' => [],
        'theatre' => [],
        'pharmacy' => [],
        'nursing' => [],
        'other' => []
    ];
    
    foreach ($activities as $activity) {
        $module = strtolower($activity['module'] ?? 'other');
        
        // Map module names to groups
        if (strpos($module, 'patient') !== false) {
            $grouped['patients'][] = $activity;
        } elseif (strpos($module, 'prescription') !== false || strpos($module, 'pharmacy') !== false || strpos($module, 'drug') !== false) {
            $grouped['pharmacy'][] = $activity;
        } elseif (strpos($module, 'lab') !== false || strpos($module, 'test') !== false) {
            $grouped['lab'][] = $activity;
        } elseif (strpos($module, 'theatre') !== false || strpos($module, 'procedure') !== false) {
            $grouped['theatre'][] = $activity;
        } elseif (strpos($module, 'nursing') !== false || strpos($module, 'vital') !== false || strpos($module, 'anc') !== false || strpos($module, 'postnatal') !== false) {
            $grouped['nursing'][] = $activity;
        } else {
            $grouped['other'][] = $activity;
        }
    }
    
    // Limit each group
    foreach ($grouped as $key => &$items) {
        $items = array_slice($items, 0, $limit);
    }
    
    return $grouped;
}

/**
 * Format activity for display in notifications
 * 
 * @param array $activity Activity data
 * @return array Formatted activity
 */
function formatActivityForDisplay($activity) {
    $action = $activity['action'] ?? 'Unknown';
    $module = $activity['module'] ?? 'System';
    $details = $activity['old_values'] ?: $activity['new_values'] ?: $activity['action'];
    
    // Determine icon and color based on action
    $icon = 'fa-info-circle';
    $bg_color = 'bg-blue-100';
    $icon_color = 'text-blue-600';
    
    if (stripos($action, 'create') !== false || stripos($action, 'add') !== false || stripos($action, 'register') !== false) {
        $icon = 'fa-plus-circle';
        $bg_color = 'bg-green-100';
        $icon_color = 'text-green-600';
    } elseif (stripos($action, 'update') !== false || stripos($action, 'edit') !== false) {
        $icon = 'fa-edit';
        $bg_color = 'bg-amber-100';
        $icon_color = 'text-amber-600';
    } elseif (stripos($action, 'delete') !== false || stripos($action, 'archive') !== false) {
        $icon = 'fa-trash-alt';
        $bg_color = 'bg-red-100';
        $icon_color = 'text-red-600';
    } elseif (stripos($action, 'login') !== false) {
        $icon = 'fa-sign-in-alt';
        $bg_color = 'bg-indigo-100';
        $icon_color = 'text-indigo-600';
    } elseif (stripos($action, 'logout') !== false) {
        $icon = 'fa-sign-out-alt';
        $bg_color = 'bg-gray-100';
        $icon_color = 'text-gray-600';
    } elseif (stripos($action, 'dispense') !== false || stripos($action, 'prescribe') !== false) {
        $icon = 'fa-pills';
        $bg_color = 'bg-purple-100';
        $icon_color = 'text-purple-600';
    } elseif (stripos($action, 'lab') !== false || stripos($action, 'test') !== false) {
        $icon = 'fa-flask';
        $bg_color = 'bg-violet-100';
        $icon_color = 'text-violet-600';
    }
    
    // Determine relative time
    $time_ago = getTimeAgo($activity['created_at']);
    
    return [
        'id' => $activity['id'],
        'action' => $action,
        'module' => $module,
        'details' => $details,
        'user' => $activity['full_name'] ?? $activity['username'] ?? 'System',
        'icon' => $icon,
        'bg_color' => $bg_color,
        'icon_color' => $icon_color,
        'time_ago' => $time_ago,
        'created_at' => $activity['created_at']
    ];
}

/**
 * Get time ago string from timestamp
 * 
 * @param string $timestamp MySQL timestamp
 * @return string Time ago string
 */
function getTimeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
