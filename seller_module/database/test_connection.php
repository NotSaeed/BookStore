<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing Database Connection...\n";

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bookstore";

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Database connection successful\n";
    echo "Server info: " . $conn->server_info . "\n";
    
    // Test the bookstore database
    $result = $conn->query("SELECT DATABASE()");
    $row = $result->fetch_row();
    echo "Connected to database: " . $row[0] . "\n";
    
    // Test seller_books table
    $result = $conn->query("SHOW TABLES LIKE 'seller_books'");
    if ($result->num_rows > 0) {
        echo "✅ seller_books table exists\n";
        
        // Check columns
        $result = $conn->query("DESCRIBE seller_books");
        echo "Columns in seller_books:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ seller_books table does not exist\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
