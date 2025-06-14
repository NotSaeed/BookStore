<?php
require_once('../seller/includes/seller_db.php');

echo "<h1>🎯 Comprehensive BookStore System Validation</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: #10b981; font-weight: bold; }
.error { color: #ef4444; font-weight: bold; }
.warning { color: #f59e0b; font-weight: bold; }
.info { color: #3b82f6; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f3f4f6; }
h2 { color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; }
.code-block { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
</style>";

$validation_results = [];

// 1. Database Connection Test
echo "<div class='section'>";
echo "<h2>🔗 Database Connection Validation</h2>";
if ($conn->connect_error) {
    echo "<p class='error'>❌ Database connection failed: " . $conn->connect_error . "</p>";
    $validation_results['database_connection'] = false;
} else {
    echo "<p class='success'>✅ Database connection successful</p>";
    echo "<p><strong>Database:</strong> bookstore</p>";
    echo "<p><strong>Charset:</strong> " . $conn->character_set_name() . "</p>";
    $validation_results['database_connection'] = true;
}
echo "</div>";

// 2. Table Structure Validation
echo "<div class='section'>";
echo "<h2>📊 Database Tables Validation</h2>";
$required_tables = [
    'seller_users', 'seller_books', 'seller_activity_log', 'seller_reviews', 
    'seller_orders', 'db_audit_log', 'security_logs', 'password_reset_tokens',
    'seller_sessions', 'seller_notifications', 'book_images'
];

$result = $conn->query("SHOW TABLES");
$existing_tables = [];
while ($row = $result->fetch_array()) {
    $existing_tables[] = $row[0];
}

$missing_tables = array_diff($required_tables, $existing_tables);
if (empty($missing_tables)) {
    echo "<p class='success'>✅ All required tables exist (" . count($required_tables) . " tables)</p>";
    $validation_results['tables_complete'] = true;
} else {
    echo "<p class='error'>❌ Missing tables: " . implode(', ', $missing_tables) . "</p>";
    $validation_results['tables_complete'] = false;
}
echo "</div>";

// 3. seller_books Column Validation
echo "<div class='section'>";
echo "<h2>📚 seller_books Table Column Validation</h2>";
$result = $conn->query("DESCRIBE seller_books");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

$required_columns = [
    'book_id', 'seller_id', 'title', 'author', 'description', 'price', 
    'stock_quantity', 'category', 'isbn', 'publication_date', 'language',
    'pages', 'publisher', 'condition_type', 'cover_image', 'is_visible',
    'is_featured', 'view_count', 'sales_count', 'rating', 'date_added'
];

$missing_columns = array_diff($required_columns, $existing_columns);
if (empty($missing_columns)) {
    echo "<p class='success'>✅ All required columns exist (" . count($required_columns) . " columns)</p>";
    $validation_results['columns_complete'] = true;
} else {
    echo "<p class='error'>❌ Missing columns: " . implode(', ', $missing_columns) . "</p>";
    $validation_results['columns_complete'] = false;
}

// Check for old columns
$old_columns = ['book_title', 'book_author', 'book_description', 'book_price', 'book_cover'];
$remaining_old = array_intersect($old_columns, $existing_columns);
if (empty($remaining_old)) {
    echo "<p class='success'>✅ All old columns have been migrated</p>";
    $validation_results['old_columns_removed'] = true;
} else {
    echo "<p class='warning'>⚠️ Old columns still present: " . implode(', ', $remaining_old) . "</p>";
    $validation_results['old_columns_removed'] = false;
}
echo "</div>";

// 4. PHP Files Validation
echo "<div class='section'>";
echo "<h2>🐘 PHP Files Validation</h2>";
$php_files = [
    'seller_dashboard.php' => 'Dashboard functionality',
    'seller_manage_books.php' => 'Book management',
    'seller_add_book.php' => 'Add new books',
    'seller_edit_book.php' => 'Edit book details',
    'seller_view_book.php' => 'View book details',
    'seller_search.php' => 'Search functionality',
    'seller_settings.php' => 'User settings',
    'toggle_visibility.php' => 'Toggle book visibility',
    'toggle_featured.php' => 'Toggle featured status'
];

$files_valid = 0;
foreach ($php_files as $file => $description) {
    $file_path = "../seller/$file";
    if (file_exists($file_path)) {
        // Check if file contains old column references
        $content = file_get_contents($file_path);
        $old_column_references = 0;
        foreach (['book_title', 'book_author', 'book_price', 'book_description'] as $old_col) {
            if (strpos($content, $old_col) !== false) {
                $old_column_references++;
            }
        }
        
        if ($old_column_references == 0) {
            echo "<p class='success'>✅ $file - $description (Updated)</p>";
            $files_valid++;
        } else {
            echo "<p class='warning'>⚠️ $file - $description (Contains $old_column_references old column references)</p>";
        }
    } else {
        echo "<p class='error'>❌ $file - Missing file</p>";
    }
}

$validation_results['php_files_updated'] = ($files_valid == count($php_files));
echo "<p><strong>Files validated:</strong> $files_valid/" . count($php_files) . "</p>";
echo "</div>";

// 5. Enhanced Bootstrap CSS Validation
echo "<div class='section'>";
echo "<h2>🎨 Enhanced Bootstrap CSS Validation</h2>";
$css_file = "../seller/css/bootstrap-enhanced.css";
if (file_exists($css_file)) {
    $css_content = file_get_contents($css_file);
    $css_classes = [
        'card-modern', 'btn-gradient-primary', 'stat-card', 'navbar-modern',
        'form-floating-modern', 'badge-gradient-primary', 'alert-modern',
        'table-modern', 'book-card', 'progress-modern'
    ];
    
    $classes_found = 0;
    foreach ($css_classes as $class) {
        if (strpos($css_content, ".$class") !== false) {
            $classes_found++;
        }
    }
    
    if ($classes_found == count($css_classes)) {
        echo "<p class='success'>✅ Enhanced Bootstrap CSS complete ($classes_found/" . count($css_classes) . " classes)</p>";
        $validation_results['enhanced_css'] = true;
    } else {
        echo "<p class='warning'>⚠️ Enhanced Bootstrap CSS partial ($classes_found/" . count($css_classes) . " classes)</p>";
        $validation_results['enhanced_css'] = false;
    }
} else {
    echo "<p class='error'>❌ Enhanced Bootstrap CSS file missing</p>";
    $validation_results['enhanced_css'] = false;
}
echo "</div>";

// 6. Sample Query Tests
echo "<div class='section'>";
echo "<h2>🔍 Database Query Tests</h2>";
$query_tests = [
    "Basic book select" => "SELECT book_id, title, author, price FROM seller_books LIMIT 3",
    "Books with new columns" => "SELECT book_id, title, view_count, sales_count, rating FROM seller_books LIMIT 3",
    "User book join" => "SELECT sb.title, su.seller_name FROM seller_books sb LEFT JOIN seller_users su ON sb.seller_id = su.seller_id LIMIT 3",
    "Category aggregation" => "SELECT category, COUNT(*) as count FROM seller_books GROUP BY category",
    "Visibility filter" => "SELECT COUNT(*) as visible_books FROM seller_books WHERE is_visible = 1"
];

$queries_passed = 0;
foreach ($query_tests as $test_name => $query) {
    $result = $conn->query($query);
    if ($result) {
        echo "<p class='success'>✅ $test_name</p>";
        $queries_passed++;
    } else {
        echo "<p class='error'>❌ $test_name - " . $conn->error . "</p>";
    }
}

$validation_results['queries_working'] = ($queries_passed == count($query_tests));
echo "<p><strong>Query tests passed:</strong> $queries_passed/" . count($query_tests) . "</p>";
echo "</div>";

// 7. Foreign Key Relationships
echo "<div class='section'>";
echo "<h2>🔗 Foreign Key Relationships</h2>";
$fk_query = "SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = 'bookstore'
    AND REFERENCED_TABLE_NAME IS NOT NULL";

$result = $conn->query($fk_query);
$fk_count = $result->num_rows;
if ($fk_count >= 5) {
    echo "<p class='success'>✅ Foreign key relationships properly configured ($fk_count relationships)</p>";
    $validation_results['foreign_keys'] = true;
} else {
    echo "<p class='warning'>⚠️ Limited foreign key relationships ($fk_count relationships)</p>";
    $validation_results['foreign_keys'] = false;
}
echo "</div>";

// 8. Performance Indexes
echo "<div class='section'>";
echo "<h2>📈 Performance Indexes</h2>";
$index_query = "SELECT COUNT(*) as index_count FROM INFORMATION_SCHEMA.STATISTICS 
               WHERE TABLE_SCHEMA = 'bookstore' AND TABLE_NAME = 'seller_books' AND INDEX_NAME != 'PRIMARY'";
$result = $conn->query($index_query);
$index_count = $result->fetch_assoc()['index_count'];

if ($index_count >= 5) {
    echo "<p class='success'>✅ Performance indexes configured ($index_count indexes)</p>";
    $validation_results['indexes'] = true;
} else {
    echo "<p class='warning'>⚠️ Limited performance indexes ($index_count indexes)</p>";
    $validation_results['indexes'] = false;
}
echo "</div>";

// 9. Overall System Health
echo "<div class='section'>";
echo "<h2>🎯 Overall System Health</h2>";
$total_checks = count($validation_results);
$passed_checks = array_sum($validation_results);
$health_percentage = ($passed_checks / $total_checks) * 100;

echo "<div class='code-block'>";
echo "<strong>Validation Summary:</strong><br>";
foreach ($validation_results as $check => $passed) {
    $status = $passed ? '✅' : '❌';
    echo "$status " . ucfirst(str_replace('_', ' ', $check)) . "<br>";
}
echo "</div>";

if ($health_percentage >= 90) {
    echo "<p class='success'>🎉 <strong>EXCELLENT:</strong> System health at {$health_percentage}% ($passed_checks/$total_checks checks passed)</p>";
    echo "<p class='success'>✅ All major database errors have been resolved!</p>";
    echo "<p class='success'>✅ Enhanced Bootstrap classes are properly implemented!</p>";
    echo "<p class='success'>✅ Database structure is complete and optimized!</p>";
} elseif ($health_percentage >= 75) {
    echo "<p class='warning'>⚠️ <strong>GOOD:</strong> System health at {$health_percentage}% ($passed_checks/$total_checks checks passed)</p>";
    echo "<p class='info'>Some minor improvements needed.</p>";
} else {
    echo "<p class='error'>❌ <strong>NEEDS ATTENTION:</strong> System health at {$health_percentage}% ($passed_checks/$total_checks checks passed)</p>";
    echo "<p class='error'>Significant issues require resolution.</p>";
}

echo "<p><strong>Validation completed:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";

// 10. Next Steps Recommendations
echo "<div class='section'>";
echo "<h2>🚀 Next Steps & Recommendations</h2>";
echo "<ul>";
if (!$validation_results['database_connection']) {
    echo "<li class='error'>🔧 Fix database connection issues</li>";
}
if (!$validation_results['tables_complete']) {
    echo "<li class='error'>🔧 Create missing database tables</li>";
}
if (!$validation_results['columns_complete']) {
    echo "<li class='error'>🔧 Add missing columns to seller_books table</li>";
}
if (!$validation_results['old_columns_removed']) {
    echo "<li class='warning'>🔧 Remove or migrate remaining old columns</li>";
}
if (!$validation_results['php_files_updated']) {
    echo "<li class='warning'>🔧 Update PHP files to use new column names</li>";
}
if (!$validation_results['enhanced_css']) {
    echo "<li class='info'>🎨 Complete Enhanced Bootstrap CSS implementation</li>";
}
if (!$validation_results['queries_working']) {
    echo "<li class='error'>🔧 Fix failing database queries</li>";
}
if (!$validation_results['foreign_keys']) {
    echo "<li class='info'>⚡ Add more foreign key relationships for data integrity</li>";
}
if (!$validation_results['indexes']) {
    echo "<li class='info'>⚡ Add more performance indexes</li>";
}

if ($health_percentage >= 90) {
    echo "<li class='success'>🎉 System is ready for production use!</li>";
    echo "<li class='info'>📚 Consider adding more books to test the system</li>";
    echo "<li class='info'>👥 Consider creating test user accounts</li>";
    echo "<li class='info'>🔍 Test all CRUD operations thoroughly</li>";
}
echo "</ul>";
echo "</div>";

$conn->close();
?>

<style>
.health-excellent { background: linear-gradient(135deg, #10b981, #34d399); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
.health-good { background: linear-gradient(135deg, #f59e0b, #fbbf24); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
.health-poor { background: linear-gradient(135deg, #ef4444, #f87171); color: white; padding: 20px; border-radius: 10px; margin: 20px 0; }
</style>
