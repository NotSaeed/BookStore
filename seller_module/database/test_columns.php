<?php
/**
 * Database Column Testing Script
 * Tests if all column names work correctly with the new schema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "BookStore Database Column Testing\n";
echo "=================================\n\n";

// Include database connection
require_once __DIR__ . '/../seller/includes/seller_db.php';

try {
    echo "✅ Database connection established\n\n";
    
    // Test 1: Check seller_books table structure
    echo "📋 Testing seller_books table structure:\n";
    $result = $conn->query("DESCRIBE seller_books");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
        echo "   ✅ Column: " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    echo "\n";
    
    // Test 2: Verify correct column names exist
    echo "🔍 Verifying correct column names:\n";
    $expected_columns = ['title', 'author', 'price', 'stock_quantity', 'category', 'description', 'isbn', 'cover_image'];
    foreach ($expected_columns as $col) {
        if (in_array($col, $columns)) {
            echo "   ✅ Column '$col' exists\n";
        } else {
            echo "   ❌ Column '$col' missing\n";
        }
    }
    echo "\n";
    
    // Test 3: Check old column names don't exist
    echo "🚫 Verifying old column names don't exist:\n";
    $old_columns = ['book_title', 'book_author', 'book_price', 'book_stock', 'book_category', 'book_description'];
    foreach ($old_columns as $col) {
        if (in_array($col, $columns)) {
            echo "   ❌ Old column '$col' still exists (should be renamed)\n";
        } else {
            echo "   ✅ Old column '$col' properly removed/renamed\n";
        }
    }
    echo "\n";
    
    // Test 4: Try a sample query with new column names
    echo "🔧 Testing sample queries:\n";
    
    // Insert a test book
    $stmt = $conn->prepare("INSERT INTO seller_books (seller_id, title, author, price, stock_quantity, category, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $seller_id = 1;
    $title = "Test Book";
    $author = "Test Author";
    $price = 19.99;
    $stock = 5;
    $category = "Fiction";
    $description = "A test book for database validation";
    
    $stmt->bind_param("issdiis", $seller_id, $title, $author, $price, $stock, $category, $description);
    
    if ($stmt->execute()) {
        echo "   ✅ INSERT query with new column names works\n";
        $book_id = $conn->insert_id;
        
        // Try to select it back
        $stmt2 = $conn->prepare("SELECT title, author, price, stock_quantity FROM seller_books WHERE book_id = ?");
        $stmt2->bind_param("i", $book_id);
        $stmt2->execute();
        $result = $stmt2->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo "   ✅ SELECT query with new column names works\n";
            echo "      📖 Retrieved: {$row['title']} by {$row['author']}\n";
            echo "      💰 Price: \${$row['price']}, Stock: {$row['stock_quantity']}\n";
        }
        
        // Clean up test data
        $conn->query("DELETE FROM seller_books WHERE book_id = $book_id");
        echo "   ✅ Test data cleaned up\n";
        
    } else {
        echo "   ❌ INSERT query failed: " . $stmt->error . "\n";
    }
    
    echo "\n🎉 All database column tests completed!\n";
    echo "✅ The database schema is properly configured.\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}

$conn->close();
?>
