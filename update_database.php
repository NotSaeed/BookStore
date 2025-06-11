<?php
// Database update script to add new columns for courier preferences
require_once 'db_connect.php';

try {
    // Add new columns to couriers table if they don't exist
    $columns_to_add = [
        'max_deliveries_per_day INT DEFAULT 15',
        'preferred_delivery_radius INT DEFAULT 10', 
        'auto_accept_orders BOOLEAN DEFAULT FALSE',
        'express_delivery_enabled BOOLEAN DEFAULT FALSE'
    ];
    
    foreach ($columns_to_add as $column) {
        $column_name = explode(' ', $column)[0];
        
        // Check if column exists
        $check_sql = "SHOW COLUMNS FROM couriers LIKE '$column_name'";
        $result = $conn->query($check_sql);
        
        if ($result->num_rows == 0) {
            // Column doesn't exist, add it
            $add_sql = "ALTER TABLE couriers ADD COLUMN $column";
            if ($conn->query($add_sql)) {
                echo "Added column: $column_name<br>";
            } else {
                echo "Error adding column $column_name: " . $conn->error . "<br>";
            }
        } else {
            echo "Column $column_name already exists<br>";
        }
    }
    
    echo "<br>Database update completed successfully!<br>";
    echo "<a href='settings.php'>Go to Settings</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

$conn->close();
?>
