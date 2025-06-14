<?php
// Database installation script
require_once 'config.php';

echo "<h2>BookStore Database Installation</h2>";

try {
    // First, create the database if it doesn't exist
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USERNAME, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    echo "<p style='color: green;'>✓ Database 'bookstore' created successfully</p>";
    
    // Use the database
    $pdo->exec("USE " . DB_NAME);
    
    // Read and execute the SQL file
    $sqlFile = __DIR__ . '/bookstore.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Split SQL commands by semicolon and execute them one by one
        $commands = explode(';', $sql);
        
        foreach ($commands as $command) {
            $command = trim($command);
            if (!empty($command) && !preg_match('/^(\/\*|--|#)/', $command)) {
                try {
                    $pdo->exec($command);
                } catch (PDOException $e) {
                    // Ignore errors for comments and existing tables
                    if (!strpos($e->getMessage(), 'already exists')) {
                        echo "<p style='color: orange;'>Warning: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
        
        echo "<p style='color: green;'>✓ Database tables created successfully from bookstore.sql</p>";
    } else {
        echo "<p style='color: red;'>✗ bookstore.sql file not found</p>";
    }
    
    // Test the connection
    $testConnection = getDBConnection();
    echo "<p style='color: green;'>✓ Database connection test successful</p>";
    
    echo "<h3>Installation Summary:</h3>";
    echo "<ul>";
    echo "<li>Database: " . DB_NAME . "</li>";
    echo "<li>Host: " . DB_HOST . "</li>";
    echo "<li>Tables: Created from bookstore.sql</li>";
    echo "<li>Test Users: Available (check bookstore.sql for credentials)</li>";
    echo "</ul>";
    
    echo "<h3>Next Steps:</h3>";
    echo "<p>1. Access the seller login at: <a href='../seller/seller_login.php'>Seller Login</a></p>";
    echo "<p>2. Test credentials: seller1@bookstore.com / password123</p>";
    echo "<p>3. Access the main site at: <a href='../index.html'>Main Site</a></p>";
    
} catch(PDOException $e) {
    echo "<p style='color: red;'>✗ Installation failed: " . $e->getMessage() . "</p>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>BookStore Database Installation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h2 { color: #333; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
    </style>
</head>
<body>
<!-- Content above -->
</body>
</html>
