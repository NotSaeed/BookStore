<?php
require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "Updating password hashes for test accounts...\n\n";
    
    // Generate proper password hash for 'password123'
    $correct_hash = password_hash('password123', PASSWORD_DEFAULT);
    echo "New hash for 'password123': " . substr($correct_hash, 0, 60) . "...\n\n";
    
    // Update both seller accounts
    $stmt = $conn->prepare("UPDATE seller_users SET seller_password = ? WHERE seller_email IN ('seller1@bookstore.com', 'seller2@bookstore.com')");
    $stmt->bind_param("s", $correct_hash);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        echo "✓ Successfully updated $affected_rows seller account(s)\n";
    } else {
        echo "✗ Error updating passwords: " . $stmt->error . "\n";
    }
    
    $stmt->close();
    
    // Verify the updates
    echo "\nVerifying updates:\n";
    $result = $conn->query("SELECT seller_email, seller_password FROM seller_users WHERE seller_email IN ('seller1@bookstore.com', 'seller2@bookstore.com')");
    
    while ($row = $result->fetch_assoc()) {
        echo "Email: " . $row['seller_email'] . "\n";
        if (password_verify('password123', $row['seller_password'])) {
            echo "✓ Password verification successful\n";
        } else {
            echo "✗ Password verification failed\n";
        }
        echo "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
