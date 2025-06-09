<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password, "bookstore_db");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Drop the existing courier_logins table
$sql = "DROP TABLE IF EXISTS courier_logins";
if (!$conn->query($sql)) {
    die("Error dropping table: " . $conn->error);
}

// Create the courier_logins table with the correct structure
$sql = "CREATE TABLE courier_logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    courier_id VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    login_time DATETIME NOT NULL,
    status VARCHAR(10) DEFAULT 'success',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!$conn->query($sql)) {
    die("Error creating table: " . $conn->error);
}

echo "Database structure updated successfully!";
$conn->close();
?>
