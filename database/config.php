<?php
// Database configuration for BookStore
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bookstore');

// Create connection function
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        $conn->set_charset("utf8");
        return $conn;
    } catch(Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        die("Connection failed. Please check your database configuration.");
    }
}

// Legacy mysqli connection for compatibility
function getMysqliConnection() {
    return getDBConnection();
}

// Test connection
function testDatabaseConnection() {
    try {
        $conn = getDBConnection();
        $result = $conn->query("SELECT 1");
        $conn->close();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
function getMysqliConnection() {
    $connection = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($connection->connect_error) {
        die("Connection failed: " . $connection->connect_error);
    }
    
    return $connection;
}
?>
