<?php
require_once 'config/database.php';

$db = Database::getInstance();

// Simulate the exact query from manage_users.php
$role_filter = '';
$status_filter = '';

$params = [];
$where_clauses = [];

if ($role_filter) {
    $where_clauses[] = "role = ?";
    $params[] = $role_filter;
}
if ($status_filter) {
    $where_clauses[] = "status = ?";
    $params[] = $status_filter;
}

$where_sql = empty($where_clauses) ? "1=1" : implode(" AND ", $where_clauses);

$per_page = 10;
$offset = 0;

echo "Where SQL: $where_sql\n";
echo "Params: " . print_r($params, true) . "\n";

// Test count query
try {
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE $where_sql");
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    echo "Total records: $total_records\n";
} catch (Exception $e) {
    echo "Count error: " . $e->getMessage() . "\n";
}

// Test main query
try {
    $stmt = $db->prepare("SELECT id, username, full_name, email, role, department, status, last_login, created_at FROM users WHERE $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Users fetched: " . count($users) . "\n";
    print_r($users);
} catch (Exception $e) {
    echo "Main query error: " . $e->getMessage() . "\n";
}

// Test stats query
try {
    $stmt = $db->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = $row['count'];
        $stats['total'] += $row['count'];
    }
    echo "Stats: ";
    print_r($stats);
} catch (Exception $e) {
    echo "Stats error: " . $e->getMessage() . "\n";
}
?>