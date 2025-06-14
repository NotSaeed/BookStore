<?php
/**
 * Database Column Migration Script
 * Renames columns to match the new schema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "BookStore Database Column Migration\n";
echo "===================================\n\n";

require_once __DIR__ . '/../seller/includes/seller_db.php';

try {
    echo "✅ Connected to database\n\n";
    
    // Check current table structure
    echo "📋 Current seller_books table structure:\n";
    $result = $conn->query("DESCRIBE seller_books");
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
        echo "   • " . $row['Field'] . "\n";
    }
    echo "\n";
    
    // Define column migrations needed
    $migrations = [];
    
    // Only add migration if old column exists and new column doesn't
    if (in_array('book_title', $existing_columns) && !in_array('title', $existing_columns)) {
        $migrations[] = "ALTER TABLE seller_books CHANGE book_title title VARCHAR(255) NOT NULL";
    }
    
    if (in_array('book_author', $existing_columns) && !in_array('author', $existing_columns)) {
        $migrations[] = "ALTER TABLE seller_books CHANGE book_author author VARCHAR(255) NOT NULL";
    }
    
    if (in_array('book_price', $existing_columns) && !in_array('price', $existing_columns)) {
        $migrations[] = "ALTER TABLE seller_books CHANGE book_price price DECIMAL(10,2) NOT NULL";
    }
    
    if (in_array('book_stock', $existing_columns) && !in_array('stock_quantity', $existing_columns)) {
        $migrations[] = "ALTER TABLE seller_books CHANGE book_stock stock_quantity INT DEFAULT 1";
    }
    
    if (in_array('book_description', $existing_columns) && in_array('description', $existing_columns)) {
        // Remove duplicate book_description column if description already exists
        $migrations[] = "ALTER TABLE seller_books DROP COLUMN book_description";
    } elseif (in_array('book_description', $existing_columns) && !in_array('description', $existing_columns)) {
        $migrations[] = "ALTER TABLE seller_books CHANGE book_description description TEXT";
    }
    
    if (in_array('book_cover', $existing_columns) && in_array('cover_image', $existing_columns)) {
        // Remove duplicate book_cover column if cover_image already exists
        $migrations[] = "ALTER TABLE seller_books DROP COLUMN book_cover";
    } elseif (in_array('book_cover', $existing_columns) && !in_array('cover_image', $existing_columns)) {
        $migrations[] = "ALTER TABLE seller_books CHANGE book_cover cover_image VARCHAR(255)";
    }
    
    if (empty($migrations)) {
        echo "ℹ️  No column migrations needed. Database appears to be correctly configured.\n";
    } else {
        echo "🔧 Executing " . count($migrations) . " column migrations:\n\n";
        
        foreach ($migrations as $i => $sql) {
            echo "   " . ($i + 1) . ". " . $sql . "\n";
            
            if ($conn->query($sql) === TRUE) {
                echo "      ✅ Migration successful\n";
            } else {
                echo "      ❌ Migration failed: " . $conn->error . "\n";
            }
            echo "\n";
        }
    }
    
    // Show final table structure
    echo "📋 Final seller_books table structure:\n";
    $result = $conn->query("DESCRIBE seller_books");
    while ($row = $result->fetch_assoc()) {
        echo "   • " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
    echo "\n✅ Column migration completed!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}

$conn->close();
?>
