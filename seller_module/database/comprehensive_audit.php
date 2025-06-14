<?php
require_once('../seller/includes/seller_db.php');

echo "<h1>📊 BookStore Database Comprehensive Audit</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: #10b981; font-weight: bold; }
.error { color: #ef4444; font-weight: bold; }
.warning { color: #f59e0b; font-weight: bold; }
.info { color: #3b82f6; font-weight: bold; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f3f4f6; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
h2 { color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; }
</style>";

// Test database connection
echo "<div class='section'>";
echo "<h2>🔗 Database Connection Test</h2>";
if ($conn->connect_error) {
    echo "<p class='error'>❌ Connection failed: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p class='success'>✅ Database connection successful</p>";
    echo "<p><strong>Database:</strong> $dbname</p>";
    echo "<p><strong>Charset:</strong> " . $conn->character_set_name() . "</p>";
}
echo "</div>";

// Check all tables exist
echo "<div class='section'>";
echo "<h2>📋 Database Tables Audit</h2>";
$expected_tables = [
    'seller_users', 'seller_books', 'seller_activity_log', 'seller_reviews', 
    'seller_orders', 'db_audit_log', 'security_logs', 'password_reset_tokens',
    'seller_sessions', 'seller_notifications', 'book_images'
];

$result = $conn->query("SHOW TABLES");
$existing_tables = [];
while ($row = $result->fetch_array()) {
    $existing_tables[] = $row[0];
}

echo "<h3>Existing Tables:</h3>";
echo "<table><tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";
foreach ($existing_tables as $table) {
    $count_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    $count = $count_result->fetch_assoc()['count'];
    $status = in_array($table, $expected_tables) ? "<span class='success'>✅ Expected</span>" : "<span class='warning'>⚠️ Unexpected</span>";
    echo "<tr><td>$table</td><td>$status</td><td>$count</td></tr>";
}
echo "</table>";

echo "<h3>Missing Tables:</h3>";
$missing_tables = array_diff($expected_tables, $existing_tables);
if (empty($missing_tables)) {
    echo "<p class='success'>✅ All expected tables exist</p>";
} else {
    echo "<table><tr><th>Missing Table</th></tr>";
    foreach ($missing_tables as $table) {
        echo "<tr><td class='error'>❌ $table</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// Check seller_books table structure
echo "<div class='section'>";
echo "<h2>📚 seller_books Table Structure Audit</h2>";
if (in_array('seller_books', $existing_tables)) {
    $result = $conn->query("DESCRIBE seller_books");
    echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for required columns
    $required_columns = [
        'book_id', 'seller_id', 'title', 'author', 'description', 'price', 
        'stock_quantity', 'category', 'isbn', 'publication_date', 'language',
        'pages', 'publisher', 'condition_type', 'cover_image', 'is_visible',
        'is_featured', 'view_count', 'sales_count', 'rating', 'date_added'
    ];
    
    echo "<h3>Column Status Check:</h3>";
    echo "<table><tr><th>Required Column</th><th>Status</th></tr>";
    foreach ($required_columns as $col) {
        $status = in_array($col, $columns) ? "<span class='success'>✅ Present</span>" : "<span class='error'>❌ Missing</span>";
        echo "<tr><td>$col</td><td>$status</td></tr>";
    }
    echo "</table>";
    
    // Check for old columns that should be removed
    $old_columns = ['book_title', 'book_author', 'book_description', 'book_price', 'book_cover'];
    echo "<h3>Old Columns Check:</h3>";
    echo "<table><tr><th>Old Column</th><th>Status</th></tr>";
    foreach ($old_columns as $col) {
        $status = in_array($col, $columns) ? "<span class='error'>❌ Still Present</span>" : "<span class='success'>✅ Removed</span>";
        echo "<tr><td>$col</td><td>$status</td></tr>";
    }
    echo "</table>";
    
} else {
    echo "<p class='error'>❌ seller_books table does not exist!</p>";
}
echo "</div>";

// Check seller_users table structure
echo "<div class='section'>";
echo "<h2>👥 seller_users Table Structure Audit</h2>";
if (in_array('seller_users', $existing_tables)) {
    $result = $conn->query("DESCRIBE seller_users");
    echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>❌ seller_users table does not exist!</p>";
}
echo "</div>";

// Check foreign key relationships
echo "<div class='section'>";
echo "<h2>🔗 Foreign Key Relationships Audit</h2>";
$fk_query = "SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = '$dbname'
    AND REFERENCED_TABLE_NAME IS NOT NULL";

$result = $conn->query($fk_query);
if ($result->num_rows > 0) {
    echo "<table><tr><th>Table</th><th>Column</th><th>References</th><th>Referenced Column</th></tr>";
    while ($row = $result->fetch_assoc()) {    
        echo "<tr>";
        echo "<td>" . $row['TABLE_NAME'] . "</td>";
        echo "<td>" . $row['COLUMN_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>⚠️ No foreign key relationships found</p>";
}
echo "</div>";

// Check indexes
echo "<div class='section'>";
echo "<h2>📊 Database Indexes Audit</h2>";
foreach (['seller_books', 'seller_users'] as $table) {
    if (in_array($table, $existing_tables)) {
        echo "<h3>Indexes for $table:</h3>";
        $result = $conn->query("SHOW INDEX FROM `$table`");
        if ($result->num_rows > 0) {
            echo "<table><tr><th>Key Name</th><th>Column</th><th>Unique</th><th>Type</th></tr>";
            while ($row = $result->fetch_assoc()) {
                $unique = $row['Non_unique'] == 0 ? 'Yes' : 'No';
                echo "<tr>";
                echo "<td>" . $row['Key_name'] . "</td>";
                echo "<td>" . $row['Column_name'] . "</td>";
                echo "<td>$unique</td>";
                echo "<td>" . $row['Index_type'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ No indexes found for $table</p>";
        }
    }
}
echo "</div>";

// Test sample queries
echo "<div class='section'>";
echo "<h2>🔍 Sample Query Tests</h2>";

$test_queries = [
    "Test seller_books basic select" => "SELECT book_id, title, author, price FROM seller_books LIMIT 5",
    "Test seller_users basic select" => "SELECT seller_id, seller_name, email FROM seller_users LIMIT 5",
    "Test join query" => "SELECT sb.title, su.seller_name FROM seller_books sb LEFT JOIN seller_users su ON sb.seller_id = su.seller_id LIMIT 5",
    "Test view_count column" => "SELECT book_id, title, view_count FROM seller_books WHERE view_count IS NOT NULL LIMIT 5",
    "Test sales_count column" => "SELECT book_id, title, sales_count FROM seller_books WHERE sales_count IS NOT NULL LIMIT 5"
];

foreach ($test_queries as $test_name => $query) {
    echo "<h4>$test_name:</h4>";
    $result = $conn->query($query);
    if ($result) {
        if ($result->num_rows > 0) {
            echo "<p class='success'>✅ Query successful - " . $result->num_rows . " rows returned</p>";
            
            // Show first few results
            echo "<table>";
            $first_row = true;
            while ($row = $result->fetch_assoc()) {
                if ($first_row) {
                    echo "<tr>";
                    foreach (array_keys($row) as $column) {
                        echo "<th>$column</th>";
                    }
                    echo "</tr>";
                    $first_row = false;
                }
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Query successful but no data returned</p>";
        }
    } else {
        echo "<p class='error'>❌ Query failed: " . $conn->error . "</p>";
    }
    echo "<hr>";
}
echo "</div>";

// Data integrity checks
echo "<div class='section'>";
echo "<h2>🛡️ Data Integrity Checks</h2>";

if (in_array('seller_books', $existing_tables)) {
    // Check for NULL values in required fields
    $integrity_checks = [
        "Books with NULL titles" => "SELECT COUNT(*) as count FROM seller_books WHERE title IS NULL OR title = ''",
        "Books with NULL authors" => "SELECT COUNT(*) as count FROM seller_books WHERE author IS NULL OR author = ''",
        "Books with NULL/zero prices" => "SELECT COUNT(*) as count FROM seller_books WHERE price IS NULL OR price <= 0",
        "Books with negative stock" => "SELECT COUNT(*) as count FROM seller_books WHERE stock_quantity < 0",
        "Books with invalid view_count" => "SELECT COUNT(*) as count FROM seller_books WHERE view_count < 0",
        "Books with invalid sales_count" => "SELECT COUNT(*) as count FROM seller_books WHERE sales_count < 0"
    ];
    
    foreach ($integrity_checks as $check_name => $query) {
        $result = $conn->query($query);
        if ($result) {
            $count = $result->fetch_assoc()['count'];
            $status = $count == 0 ? "<span class='success'>✅ Clean ($count issues)</span>" : "<span class='error'>❌ Issues found ($count)</span>";
            echo "<p><strong>$check_name:</strong> $status</p>";
        } else {
            echo "<p><strong>$check_name:</strong> <span class='error'>❌ Check failed</span></p>";
        }
    }
}
echo "</div>";

// Performance recommendations
echo "<div class='section'>";
echo "<h2>⚡ Performance Recommendations</h2>";
echo "<ul>";
echo "<li><strong>Add indexes:</strong> Consider adding indexes on frequently queried columns like category, is_visible, is_featured</li>";
echo "<li><strong>Foreign keys:</strong> Add foreign key constraints to ensure data integrity</li>";
echo "<li><strong>Data types:</strong> Review column data types for optimal storage</li>";
echo "<li><strong>Null constraints:</strong> Add NOT NULL constraints where appropriate</li>";
echo "</ul>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>📝 Summary</h2>";
echo "<p class='info'>Database audit completed successfully. Review the findings above and address any issues found.</p>";
echo "<p><strong>Total tables:</strong> " . count($existing_tables) . "</p>";
echo "<p><strong>Missing tables:</strong> " . count($missing_tables) . "</p>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";

$conn->close();
?>
