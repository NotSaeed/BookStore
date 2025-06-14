<?php
require_once __DIR__ . '/seller/includes/seller_db.php';

echo "=== SELLER_BOOKS TABLE STRUCTURE ===\n";
$result = $conn->query('DESCRIBE seller_books');
while($row = $result->fetch_assoc()) {
    echo sprintf("%-20s | %-15s | %-5s | %-10s\n", 
        $row['Field'], 
        $row['Type'], 
        $row['Null'], 
        $row['Default'] ?? 'NULL'
    );
}

echo "\n=== CHECKING FOR WEIGHT COLUMN ===\n";
$weight_check = $conn->query("SHOW COLUMNS FROM seller_books LIKE 'weight'");
if ($weight_check->num_rows > 0) {
    echo "Weight column EXISTS\n";
} else {
    echo "Weight column MISSING - need to add it\n";
}

echo "\n=== CHECKING ISBN COLUMN CONSTRAINTS ===\n";
$isbn_check = $conn->query("SHOW COLUMNS FROM seller_books WHERE Field = 'isbn'");
if ($isbn_row = $isbn_check->fetch_assoc()) {
    echo "ISBN Column Details:\n";
    echo "Type: " . $isbn_row['Type'] . "\n";
    echo "Null: " . $isbn_row['Null'] . "\n";
    echo "Default: " . ($isbn_row['Default'] ?? 'NULL') . "\n";
}

$conn->close();
?>
