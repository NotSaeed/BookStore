<?php
require_once '../seller/includes/seller_db.php';

echo "Adding missing password_reset_date column to seller_users table...\n";

// Add the missing column
$query = "ALTER TABLE seller_users ADD COLUMN password_reset_date DATETIME NULL";

if ($conn->query($query)) {
    echo "✓ Successfully added password_reset_date column\n";
} else {
    echo "✗ Error adding column: " . $conn->error . "\n";
}

// Verify the column was added
$query = "SHOW COLUMNS FROM seller_users LIKE 'password_reset_date'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✓ Verified: password_reset_date column now exists\n";
} else {
    echo "✗ Column still missing\n";
}

$conn->close();
echo "Database fix completed.\n";
?>
