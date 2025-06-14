<?php
/**
 * Comprehensive BookStore Application Test
 * Tests database connection, schema, and core functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "BookStore Application Comprehensive Test\n";
echo "=======================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function runTest($test_name, $test_function) {
    global $tests_passed, $tests_failed;
    echo "🧪 Testing: $test_name\n";
    try {
        $result = $test_function();
        if ($result) {
            echo "   ✅ PASSED\n";
            $tests_passed++;
        } else {
            echo "   ❌ FAILED\n";
            $tests_failed++;
        }
    } catch (Exception $e) {
        echo "   ❌ FAILED: " . $e->getMessage() . "\n";
        $tests_failed++;
    }
    echo "\n";
}

// Test 1: Database Connection
runTest("Database Connection", function() {
    require_once __DIR__ . '/../seller/includes/seller_db.php';
    global $conn;
    return $conn && $conn->ping();
});

// Test 2: Database Schema
runTest("Database Schema Validation", function() {
    global $conn;
    
    // Check if seller_books table exists with correct columns
    $result = $conn->query("DESCRIBE seller_books");
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    $required_columns = ['book_id', 'seller_id', 'title', 'author', 'price', 'stock_quantity', 'category', 'description', 'cover_image'];
    
    foreach ($required_columns as $col) {
        if (!in_array($col, $columns)) {
            throw new Exception("Missing required column: $col");
        }
    }
    
    return true;
});

// Test 3: CRUD Operations
runTest("CRUD Operations", function() {
    global $conn;
    
    // Create test seller
    $stmt = $conn->prepare("INSERT INTO seller_users (seller_name, seller_email, seller_password) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE seller_id=LAST_INSERT_ID(seller_id)");
    $name = "Test Seller";
    $email = "test@bookstore.com";
    $password = password_hash("testpass", PASSWORD_DEFAULT);
    $stmt->bind_param("sss", $name, $email, $password);
    $stmt->execute();
    $seller_id = $conn->insert_id ?: $conn->query("SELECT seller_id FROM seller_users WHERE seller_email = 'test@bookstore.com'")->fetch_assoc()['seller_id'];
    $stmt->close();
    
    // CREATE - Insert a test book
    $stmt = $conn->prepare("INSERT INTO seller_books (seller_id, title, author, price, stock_quantity, category, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $title = "Test Book " . uniqid();
    $author = "Test Author";
    $price = 29.99;
    $stock = 10;
    $category = "Fiction";
    $description = "A comprehensive test book";
    
    $stmt->bind_param("issdiis", $seller_id, $title, $author, $price, $stock, $category, $description);
    $stmt->execute();
    $book_id = $conn->insert_id;
    $stmt->close();
    
    if (!$book_id) throw new Exception("Failed to create book");
    
    // READ - Retrieve the book
    $stmt = $conn->prepare("SELECT title, author, price, stock_quantity FROM seller_books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();
    
    if (!$book || $book['title'] !== $title) throw new Exception("Failed to read book");
    
    // UPDATE - Modify the book
    $new_price = 39.99;
    $stmt = $conn->prepare("UPDATE seller_books SET price = ? WHERE book_id = ?");
    $stmt->bind_param("di", $new_price, $book_id);
    $stmt->execute();
    $stmt->close();
    
    // Verify update
    $stmt = $conn->prepare("SELECT price FROM seller_books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $updated_book = $result->fetch_assoc();
    $stmt->close();
    
    if ($updated_book['price'] != $new_price) throw new Exception("Failed to update book");
    
    // DELETE - Remove the test book
    $stmt = $conn->prepare("DELETE FROM seller_books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    
    // Clean up test seller
    $conn->query("DELETE FROM seller_users WHERE seller_email = 'test@bookstore.com'");
    
    return $deleted;
});

// Test 4: File Accessibility
runTest("Core PHP Files Accessibility", function() {
    $core_files = [
        __DIR__ . '/../seller/seller_dashboard.php',
        __DIR__ . '/../seller/seller_manage_books.php',
        __DIR__ . '/../seller/seller_add_book.php',
        __DIR__ . '/../seller/seller_login.php',
        __DIR__ . '/../seller/includes/seller_db.php'
    ];
    
    foreach ($core_files as $file) {
        if (!file_exists($file)) {
            throw new Exception("Missing file: " . basename($file));
        }
        if (!is_readable($file)) {
            throw new Exception("File not readable: " . basename($file));
        }
    }
    
    return true;
});

// Test 5: PHP Syntax Check
runTest("PHP Syntax Validation", function() {
    $php_files = [
        __DIR__ . '/../seller/seller_dashboard.php',
        __DIR__ . '/../seller/seller_manage_books.php',
        __DIR__ . '/../seller/toggle_visibility.php',
        __DIR__ . '/../seller/toggle_featured.php'
    ];
    
    foreach ($php_files as $file) {
        $output = [];
        $return_var = 0;
        exec("php -l \"$file\" 2>&1", $output, $return_var);
        
        if ($return_var !== 0) {
            throw new Exception("Syntax error in " . basename($file) . ": " . implode("\n", $output));
        }
    }
    
    return true;
});

// Test 6: Session Functionality
runTest("Session Management", function() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['test_key'] = 'test_value';
    return isset($_SESSION['test_key']) && $_SESSION['test_key'] === 'test_value';
});

// Test 7: Database Helper Functions
runTest("Database Helper Functions", function() {
    if (!function_exists('logActivity')) {
        require_once __DIR__ . '/../seller/includes/db_helpers.php';
    }
    
    return function_exists('logActivity') && 
           function_exists('validateInput') && 
           function_exists('sanitizeInput');
});

// Test 8: Upload Directory Permissions
runTest("Upload Directory Permissions", function() {
    $upload_dir = __DIR__ . '/../seller/uploads';
    $covers_dir = $upload_dir . '/covers';
    
    // Check if directories exist and are writable
    return is_dir($upload_dir) && is_writable($upload_dir) &&
           is_dir($covers_dir) && is_writable($covers_dir);
});

// Test Results Summary
echo "📊 Test Results Summary\n";
echo "======================\n";
echo "✅ Tests Passed: $tests_passed\n";
echo "❌ Tests Failed: $tests_failed\n";
echo "📈 Success Rate: " . round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 1) . "%\n\n";

if ($tests_failed === 0) {
    echo "🎉 All tests passed! Your BookStore application is ready to use.\n";
    echo "\n🌐 Next Steps:\n";
    echo "1. Start XAMPP (Apache + MySQL)\n";
    echo "2. Access the application at: http://localhost/BookStore/\n";
    echo "3. Register a new seller account\n";
    echo "4. Start adding books to your inventory\n";
} else {
    echo "⚠️  Some tests failed. Please address the issues above before using the application.\n";
}

echo "\n📝 Application URLs:\n";
echo "   🏠 Main Page: http://localhost/BookStore/\n";
echo "   👤 Seller Login: http://localhost/BookStore/seller/seller_login.php\n";
echo "   📚 Seller Dashboard: http://localhost/BookStore/seller/seller_dashboard.php\n";
echo "   ➕ Add Books: http://localhost/BookStore/seller/seller_add_book.php\n";

if (isset($conn)) {
    $conn->close();
}
?>
