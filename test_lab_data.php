<?php
/**
 * Test page to check lab data in database
 */
session_start();
require_once 'config/database.php';

echo "<h1>Lab Data Test</h1>";

try {
    $db = Database::getInstance();
    
    // Check lab_requests
    echo "<h2>lab_requests table</h2>";
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM lab_requests");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total records: " . $count['cnt'] . "<br>";
    
    if ($count['cnt'] > 0) {
        $stmt = $db->query("SELECT * FROM lab_requests ORDER BY id DESC LIMIT 5");
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";
    }
    
    // Check patients
    echo "<h2>patients table</h2>";
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM patients");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total patients: " . $count['cnt'] . "<br>";
    
    if ($count['cnt'] > 0) {
        $stmt = $db->query("SELECT id, patient_id, first_name, last_name FROM patients LIMIT 3");
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";
    }
    
    // Check lab_test_types
    echo "<h2>lab_test_types table</h2>";
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM lab_test_types");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total test types: " . $count['cnt'] . "<br>";
    
    if ($count['cnt'] > 0) {
        $stmt = $db->query("SELECT id, test_name, category FROM lab_test_types LIMIT 5");
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";
    }
    
    // Check users
    echo "<h2>users table</h2>";
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total users: " . $count['cnt'] . "<br>";
    
    if ($count['cnt'] > 0) {
        $stmt = $db->query("SELECT id, username, role FROM users LIMIT 3");
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
