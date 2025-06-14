<?php
/**
 * Simple Database Installation Script
 * Installs the BookStore database with correct schema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "BookStore Database Installation\n";
echo "==============================\n\n";

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'bookstore';

try {
    // Connect to MySQL (without selecting a database first)
    $conn = new mysqli($host, $username, $password);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Connected to MySQL server\n";
    
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql) === TRUE) {
        echo "✅ Database '$database' created/verified\n";
    } else {
        throw new Exception("Error creating database: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($database);
    echo "✅ Selected database '$database'\n";
    
    // Read and execute schema file
    $schema_file = __DIR__ . '/schema.sql';
    if (!file_exists($schema_file)) {
        throw new Exception("Schema file not found: $schema_file");
    }
    
    $schema = file_get_contents($schema_file);
    echo "✅ Schema file loaded\n";
    
    // Split schema into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function($stmt) { return !empty($stmt) && !preg_match('/^\s*--/', $stmt); }
    );
    
    echo "📝 Executing " . count($statements) . " SQL statements...\n\n";
    
    $success_count = 0;
    foreach ($statements as $i => $statement) {
        if (trim($statement)) {
            if ($conn->query($statement) === TRUE) {
                $success_count++;
                echo "   ✅ Statement " . ($i + 1) . " executed successfully\n";
            } else {
                echo "   ❌ Error in statement " . ($i + 1) . ": " . $conn->error . "\n";
                echo "   📄 Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }
    
    echo "\n🎉 Installation completed!\n";
    echo "📊 Successfully executed $success_count/" . count($statements) . " statements\n\n";
    
    // Verify tables were created
    $result = $conn->query("SHOW TABLES");
    if ($result) {
        echo "📋 Created tables:\n";
        while ($row = $result->fetch_array()) {
            echo "   • " . $row[0] . "\n";
        }
    }
    
    echo "\n✅ Database installation completed successfully!\n";
    echo "🌐 You can now use the BookStore application.\n";
    
} catch (Exception $e) {
    echo "❌ Installation failed: " . $e->getMessage() . "\n";
    exit(1);
}

$conn->close();
?>
