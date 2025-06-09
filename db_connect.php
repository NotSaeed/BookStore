<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bookstore_db";

// First, create connection without database
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    $conn->close();
    
    // Reconnect with database selected
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create courier_logins table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS courier_logins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        courier_id VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL,
        login_time DATETIME NOT NULL,
        status VARCHAR(10) DEFAULT 'success',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql)) {
        die("Error creating courier_logins table: " . $conn->error);
    }

    // Create couriers table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS couriers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        courier_id VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    if (!$conn->query($sql)) {
        die("Error creating couriers table: " . $conn->error);
    }

    // Add a test courier if it doesn't exist
    $test_courier_id = 'COR001';
    $test_email = 'test.courier@bookstore.com';
    $test_password = password_hash('courier123', PASSWORD_DEFAULT);
    $test_name = 'Test Courier';

    $sql = "INSERT IGNORE INTO couriers (courier_id, name, email, password) 
            VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $test_courier_id, $test_name, $test_email, $test_password);
    $stmt->execute();
} else {
    die("Error creating database: " . $conn->error);
}
?>
