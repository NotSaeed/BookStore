<?php
require_once '../seller/includes/seller_db.php';

echo "=== FINAL DATABASE VALIDATION ===\n";
echo "Testing all database queries that were fixed...\n\n";

// Simulate a seller_id for testing (use 1 if exists)
$test_seller_id = 1;

// Test 1: Dashboard price range query (the main error from screenshot)
echo "1. Testing Dashboard Price Range Query:\n";
try {
    $stmt = $conn->prepare("SELECT 
        CASE 
            WHEN price < 10 THEN 'Under \$10'
            WHEN price < 25 THEN '\$10-\$25'
            WHEN price < 50 THEN '\$25-\$50'
            WHEN price < 100 THEN '\$50-\$100'
            ELSE 'Over \$100'
        END as price_range,
        COUNT(*) as count,
        SUM(price * stock_quantity) as total_value
        FROM seller_books WHERE seller_id = ? 
        GROUP BY price_range 
        ORDER BY MIN(price)");
    
    if ($stmt) {
        $stmt->bind_param("i", $test_seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo "   ✓ Query prepared and executed successfully\n";
        echo "   ✓ Found " . $result->num_rows . " price ranges\n";
        $stmt->close();
    } else {
        echo "   ✗ Failed to prepare statement: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 2: Search statistics query
echo "\n2. Testing Search Statistics Query:\n";
try {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total_books,
        COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_books,
        COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_books,
        COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
        AVG(price) as avg_price,
        SUM(COALESCE(view_count, 0)) as total_views,
        SUM(COALESCE(sales_count, 0)) as total_sales
    FROM seller_books WHERE seller_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $test_seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        echo "   ✓ Query executed successfully\n";
        echo "   ✓ Stats: " . $stats['total_books'] . " books, avg price: RM" . number_format($stats['avg_price'] ?? 0, 2) . "\n";
        $stmt->close();
    } else {
        echo "   ✗ Failed to prepare statement: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 3: Add book INSERT query structure
echo "\n3. Testing Add Book INSERT Query:\n";
try {    $stmt = $conn->prepare("INSERT INTO seller_books (
        title, author, description, price, cost_price, 
        cover_image, isbn, category, book_condition, publisher, 
        publication_year, pages, stock_quantity, tags, is_public, is_featured, seller_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if ($stmt) {
        echo "   ✓ INSERT statement prepared successfully\n";
        echo "   ✓ All new column names are correct\n";
        $stmt->close();
    } else {
        echo "   ✗ Failed to prepare INSERT statement: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 4: Check seller_users password_reset_date column
echo "\n4. Testing seller_users password_reset_date column:\n";
try {
    $stmt = $conn->prepare("SELECT password_reset_date FROM seller_users WHERE seller_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $test_seller_id);
        $stmt->execute();
        echo "   ✓ password_reset_date column exists and accessible\n";
        $stmt->close();
    } else {
        echo "   ✗ Failed to access password_reset_date: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
}

// Test 5: Check current database column names
echo "\n5. Verifying Current Column Names:\n";
$query = "DESCRIBE seller_books";
$result = $conn->query($query);
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$expected_new_columns = ['title', 'author', 'price', 'stock_quantity'];
$old_columns = ['book_title', 'book_author', 'book_price', 'book_stock'];

foreach ($expected_new_columns as $col) {
    if (in_array($col, $columns)) {
        echo "   ✓ New column '$col' exists\n";
    } else {
        echo "   ✗ Missing new column '$col'\n";
    }
}

foreach ($old_columns as $col) {
    if (in_array($col, $columns)) {
        echo "   ⚠ Old column '$col' still exists (should be removed)\n";
    } else {
        echo "   ✓ Old column '$col' successfully removed\n";
    }
}

echo "\n=== VALIDATION COMPLETE ===\n";
echo "If all tests show ✓, the database issues should be resolved!\n";

$conn->close();
?>
