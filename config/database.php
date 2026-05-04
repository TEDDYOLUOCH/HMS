<?php

/**

 * Hospital Management System - Database Configuration

 * Using PDO with singleton pattern

 */



class Database {

    private static $instance = null;

    private $pdo;

    

    private $options = [

        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES => false

    ];

    

    private function __construct() {

        $this->connect();

    }

    

    private function connect() {

        // Database configuration

        $host = 'localhost';

        $dbname = 'siwot_hms';

        $user = 'root';

        $pass = '';

        $charset = 'utf8mb4';

        

        // First try to connect without database to create it

        try {

            $dsn = "mysql:host={$host};charset={$charset}";

            $pdo = new PDO($dsn, $user, $pass, $this->options);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $pdo->exec("USE `$dbname`");

            $this->pdo = $pdo;

        } catch (PDOException $e) {

            // If creation fails, try direct connection

            try {

                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

                $this->pdo = new PDO($dsn, $user, $pass, $this->options);

            } catch (PDOException $e2) {

                $this->handleError($e2);

            }

        }

    }

    

    /**

     * Get singleton instance

     */

    public static function getInstance() {

        if (self::$instance === null) {

            self::$instance = new self();

        }

        return self::$instance;

    }

    

    /**

     * Get PDO connection

     */

    public function getConnection() {

        return $this->pdo;

    }

    

    /**

     * Execute a query with prepared statement

     */

    public function query($sql, $params = []) {

        try {

            $stmt = $this->pdo->prepare($sql);

            $stmt->execute($params);

            return $stmt;

        } catch (PDOException $e) {

            $this->logError($e, $sql);

            throw new Exception("Database query failed: " . $e->getMessage());

        }

    }

    /**

     * Prepare a statement for execution (returns PDOStatement)

     */

    public function prepare($sql) {

        return $this->pdo->prepare($sql);

    }

    /**

     * Fetch single row

     */

    public function fetch($sql, $params = []) {

        $stmt = $this->query($sql, $params);

        return $stmt->fetch();

    }

    

    /**

     * Fetch all rows

     */

    public function fetchAll($sql, $params = []) {

        $stmt = $this->query($sql, $params);

        return $stmt->fetchAll();

    }

    

    /**

     * Get last insert ID

     */

    public function lastInsertId() {

        return $this->pdo->lastInsertId();

    }

    

    /**

     * Begin transaction

     */

    public function beginTransaction() {

        return $this->pdo->beginTransaction();

    }

    

    /**

     * Commit transaction

     */

    public function commit() {

        return $this->pdo->commit();

    }

    

    /**

     * Rollback transaction

     */

    public function rollBack() {

        return $this->pdo->rollBack();

    }

    

    /**

     * Handle database errors

     */

    private function handleError($e) {

        $logDir = dirname(__DIR__) . '/logs';

        if (!is_dir($logDir)) {

            @mkdir($logDir, 0755, true);

        }

        

        $logFile = $logDir . '/errors.log';

        $message = date('Y-m-d H:i:s') . " - " . $e->getMessage() . "\n";

        @file_put_contents($logFile, $message, FILE_APPEND);

        

        die("Database connection failed. Please contact administrator.");

    }

    

    /**

     * Log errors

     */

    private function logError($e, $sql = null) {

        $logDir = dirname(__DIR__) . '/logs';

        if (!is_dir($logDir)) {

            @mkdir($logDir, 0755, true);

        }

        

        $logFile = $logDir . '/errors.log';

        $message = date('Y-m-d H:i:s') . " - " . $e->getMessage();

        if ($sql) {

            $message .= " | SQL: " . substr($sql, 0, 100);

        }

        $message .= "\n";

        @file_put_contents($logFile, $message, FILE_APPEND);

    }

    

    private function __clone() {}

    public function __wakeup() {

        throw new Exception("Cannot unserialize singleton");

    }

}



// Helper function for quick access

function getDB() {

    return Database::getInstance();

}



// Alias for backward compatibility

function getDBConnection() {

    return Database::getInstance()->getConnection();

}

