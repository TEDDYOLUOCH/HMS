<?php
/**
 * Simple test to check database connection
 */
session_start();

echo "<h1>Database Connection Test</h1>";

echo "<h2>1. Session Test</h2>";
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . "<br>";

echo "<h2>2. Testing Database Connection</h2>";

try {
    // Try to include the database config
    require_once __DIR__ . '/config/database.php';
    
    echo "Database class loaded successfully<br>";
    
    $db = Database::getInstance();
    echo "Database instance created<br>";
    
    $pdo = $db->getConnection();
    echo "PDO connection established<br>";
    
    // Try a simple query
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Users count: " . $result['cnt'] . "<br>";
    
    // Try to get the admin user
    $stmt = $pdo->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ?");
    $stmt->execute(['admin']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<h3>Admin User Found:</h3>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Username: " . $user['username'] . "<br>";
        echo "Role: " . $user['role'] . "<br>";
        echo "is_active: " . ($user['is_active'] ? 'TRUE' : 'FALSE') . "<br>";
        echo "Password hash: " . substr($user['password_hash'], 0, 20) . "...<br>";
        
        // Test password
        $test_pass = 'admin123';
        $verify = password_verify($test_pass, $user['password_hash']);
        echo "Password 'admin123' verification: " . ($verify ? '<span style="color:green">SUCCESS</span>' : '<span style="color:red">FAILED</span>') . "<br>";
    } else {
        echo "<span style='color:red'>Admin user NOT found!</span><br>";
    }
    
} catch (PDOException $e) {
    echo "<span style='color:red'>PDO Error: " . $e->getMessage() . "</span><br>";
} catch (Exception $e) {
    echo "<span style='color:red'>Error: " . $e->getMessage() . "</span><br>";
}

echo "<h2>3. POST Data Test</h2>";
echo "Request method: " . $_SERVER['REQUEST_METHOD'] . "<br>";
echo "Username in POST: " . ($_POST['username'] ?? 'NOT SET') . "<br>";
echo "Password in POST: " . (isset($_POST['password']) ? 'SET' : 'NOT SET') . "<br>";
?>
