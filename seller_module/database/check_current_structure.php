<?php
require_once '../config/database.php';

// Check current structure of seller_books table
$query = "DESCRIBE seller_books";
$result = $conn->query($query);

echo "<h2>Current seller_books table structure:</h2>\n";
echo "<table border='1'>\n";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>\n";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['Field'] . "</td>";
    echo "<td>" . $row['Type'] . "</td>";
    echo "<td>" . $row['Null'] . "</td>";
    echo "<td>" . $row['Key'] . "</td>";
    echo "<td>" . $row['Default'] . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// Check if seller_users table has password_reset_date column
echo "<h2>Checking seller_users table for password_reset_date:</h2>\n";
$query = "SHOW COLUMNS FROM seller_users LIKE 'password_reset_date'";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "✓ password_reset_date column exists\n";
} else {
    echo "✗ password_reset_date column is missing\n";
}

// Check sample data from seller_books
echo "<h2>Sample data from seller_books (first 5 rows):</h2>\n";
$query = "SELECT * FROM seller_books LIMIT 5";
$result = $conn->query($query);
if ($result->num_rows > 0) {
    echo "<table border='1'>\n";
    $first = true;
    while ($row = $result->fetch_assoc()) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($row) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>\n";
            $first = false;
        }
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "No data found in seller_books table\n";
}

$conn->close();
?>
