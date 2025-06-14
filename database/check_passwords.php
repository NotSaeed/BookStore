<?php
require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Checking password hashes in database:\n\n";
    
    $result = $conn->query("SELECT seller_email, seller_password FROM seller_users LIMIT 2");
    
    while ($row = $result->fetch_assoc()) {
        echo "Email: " . $row['seller_email'] . "\n";
        echo "Hash: " . substr($row['seller_password'], 0, 60) . "...\n";
        
        // Test password verification
        $test_password = 'password123';
        if (password_verify($test_password, $row['seller_password'])) {
            echo "✓ Password 'password123' verified successfully\n";
        } else {
            echo "✗ Password 'password123' verification failed\n";
            // Try to create a new hash for comparison
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Expected new hash: " . substr($new_hash, 0, 60) . "...\n";
        }
        echo "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
