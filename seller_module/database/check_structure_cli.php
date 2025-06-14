<?php
require_once '../seller/includes/seller_db.php';

echo "Database Structure Check\n";
echo "========================\n\n";

// Check current structure of seller_books table
$query = "DESCRIBE seller_books";
$result = $conn->query($query);

echo "Current seller_books table structure:\n";
echo "-------------------------------------\n";
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-20s | %-15s | %-5s | %-5s | %s\n", 
        $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default']);
}

echo "\n";

// Check if seller_users table has password_reset_date column
echo "Checking seller_users table for password_reset_date:\n";
echo "---------------------------------------------------\n";
$query = "SHOW COLUMNS FROM seller_users LIKE 'password_reset_date'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✓ password_reset_date column exists\n";
} else {
    echo "✗ password_reset_date column is missing\n";
    echo "Need to add it: ALTER TABLE seller_users ADD COLUMN password_reset_date DATETIME NULL;\n";
}

echo "\n";

// Check seller_users structure
echo "Current seller_users table structure:\n";
echo "------------------------------------\n";
$query = "DESCRIBE seller_users";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo sprintf("%-25s | %-15s | %-5s | %-5s | %s\n", 
        $row['Field'], $row['Type'], $row['Null'], $row['Key'], $row['Default']);
}

$conn->close();
?>
