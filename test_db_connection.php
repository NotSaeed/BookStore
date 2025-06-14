<?php
// Simple database connection test
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing database connection...<br>";

try {
    // Test basic connection
    $conn = new mysqli('localhost', 'root', '', 'bookstore');
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "✓ Database connection successful!<br>";
    
    // Test seller_users table
    $result = $conn->query("SELECT COUNT(*) as count FROM seller_users");
    if ($result) {
        $row = $result->fetch_assoc();
        echo "✓ seller_users table accessible - " . $row['count'] . " users found<br>";
    } else {
        echo "✗ Error accessing seller_users table: " . $conn->error . "<br>";
    }
    
    // Show table structure
    echo "<h3>seller_users table structure:</h3>";
    $result = $conn->query("DESCRIBE seller_users");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test data in seller_users
    echo "<h3>Sample data from seller_users:</h3>";
    $result = $conn->query("SELECT * FROM seller_users LIMIT 3");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Registration Date</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['seller_id'] . "</td>";
            echo "<td>" . $row['seller_name'] . "</td>";
            echo "<td>" . $row['seller_email'] . "</td>";
            echo "<td>" . $row['registration_date'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
