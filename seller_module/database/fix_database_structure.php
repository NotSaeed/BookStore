<?php
require_once('../seller/includes/seller_db.php');

echo "<h1>🔧 Database Structure Fix Script</h1>\n";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.success { color: #10b981; font-weight: bold; }
.error { color: #ef4444; font-weight: bold; }
.warning { color: #f59e0b; font-weight: bold; }
.info { color: #3b82f6; font-weight: bold; }
pre { background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-x: auto; }
h2 { color: #1f2937; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
.section { margin: 20px 0; padding: 15px; border: 1px solid #e5e7eb; border-radius: 8px; }
</style>";

// Store all fixes to execute
$fixes = [];

echo "<div class='section'>";
echo "<h2>🏗️ Missing Tables Creation</h2>";

// 1. Create missing tables
$missing_tables_sql = [
    'seller_orders' => "
    CREATE TABLE IF NOT EXISTS seller_orders (
        order_id INT AUTO_INCREMENT PRIMARY KEY,
        seller_id INT NOT NULL,
        book_id INT NOT NULL,
        buyer_email VARCHAR(255) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        total_price DECIMAL(10,2) NOT NULL,
        order_status ENUM('pending', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
        shipping_address TEXT NOT NULL,
        order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES seller_books(book_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'seller_sessions' => "
    CREATE TABLE IF NOT EXISTS seller_sessions (
        session_id VARCHAR(128) PRIMARY KEY,
        seller_id INT NOT NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP,
        is_active TINYINT(1) DEFAULT 1,
        FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'book_images' => "
    CREATE TABLE IF NOT EXISTS book_images (
        image_id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        image_type ENUM('cover', 'gallery') DEFAULT 'gallery',
        is_primary TINYINT(1) DEFAULT 0,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (book_id) REFERENCES seller_books(book_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($missing_tables_sql as $table_name => $sql) {
    echo "<h3>Creating $table_name table:</h3>";
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ Table $table_name created successfully</p>";
        $fixes[] = "✅ Created missing table: $table_name";
    } else {
        echo "<p class='error'>❌ Error creating $table_name: " . $conn->error . "</p>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📊 Missing Columns Addition</h2>";

// 2. Add missing columns to seller_books
$missing_columns = [
    'publication_date' => "ALTER TABLE seller_books ADD COLUMN publication_date DATE NULL AFTER publication_year",
    'language' => "ALTER TABLE seller_books ADD COLUMN language VARCHAR(50) DEFAULT 'English' AFTER publication_date",
    'condition_type' => "ALTER TABLE seller_books ADD COLUMN condition_type ENUM('new', 'like_new', 'very_good', 'good', 'acceptable') DEFAULT 'good' AFTER book_condition",
    'is_visible' => "ALTER TABLE seller_books ADD COLUMN is_visible TINYINT(1) DEFAULT 1 AFTER is_public",
    'rating' => "ALTER TABLE seller_books ADD COLUMN rating DECIMAL(3,2) DEFAULT 0.00 AFTER sales_count",
    'date_added' => "ALTER TABLE seller_books ADD COLUMN date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER rating"
];

// Check which columns already exist
$result = $conn->query("DESCRIBE seller_books");
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

foreach ($missing_columns as $column_name => $sql) {
    if (!in_array($column_name, $existing_columns)) {
        echo "<h3>Adding $column_name column:</h3>";
        if ($conn->query($sql)) {
            echo "<p class='success'>✅ Column $column_name added successfully</p>";
            $fixes[] = "✅ Added missing column: $column_name";
        } else {
            echo "<p class='error'>❌ Error adding $column_name: " . $conn->error . "</p>";
        }
    } else {
        echo "<p class='info'>ℹ️ Column $column_name already exists</p>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🔧 Column Data Type Fixes</h2>";

// 3. Fix data type issues
$column_fixes = [
    "Fix business_email data type" => "ALTER TABLE seller_users MODIFY COLUMN business_email VARCHAR(255) NOT NULL",
    "Add email column to seller_users" => "ALTER TABLE seller_users ADD COLUMN email VARCHAR(255) NULL AFTER seller_email"
];

foreach ($column_fixes as $fix_name => $sql) {
    echo "<h3>$fix_name:</h3>";
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ $fix_name completed successfully</p>";
        $fixes[] = "✅ $fix_name";
    } else {
        echo "<p class='error'>❌ Error: " . $conn->error . "</p>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📈 Performance Indexes Addition</h2>";

// 4. Add performance indexes
$indexes = [
    "Add category index" => "CREATE INDEX idx_category ON seller_books(category)",
    "Add is_visible index" => "CREATE INDEX idx_is_visible ON seller_books(is_visible)",
    "Add is_featured index" => "CREATE INDEX idx_is_featured ON seller_books(is_featured)",
    "Add status index" => "CREATE INDEX idx_status ON seller_books(status)",
    "Add created_at index" => "CREATE INDEX idx_created_at ON seller_books(created_at)",
    "Add seller_email index" => "CREATE INDEX idx_seller_email_lookup ON seller_users(seller_email)"
];

foreach ($indexes as $index_name => $sql) {
    echo "<h3>$index_name:</h3>";
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ $index_name created successfully</p>";
        $fixes[] = "✅ $index_name";
    } else {
        // Index might already exist, check the error
        if (strpos($conn->error, 'Duplicate key name') !== false) {
            echo "<p class='info'>ℹ️ Index already exists</p>";
        } else {
            echo "<p class='error'>❌ Error: " . $conn->error . "</p>";
        }
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🛡️ Data Integrity Constraints</h2>";

// 5. Add NOT NULL constraints where appropriate
$constraints = [
    "Set title NOT NULL" => "ALTER TABLE seller_books MODIFY COLUMN title VARCHAR(255) NOT NULL",
    "Set author NOT NULL" => "ALTER TABLE seller_books MODIFY COLUMN author VARCHAR(255) NOT NULL",
    "Set price NOT NULL" => "ALTER TABLE seller_books MODIFY COLUMN price DECIMAL(10,2) NOT NULL",
    "Set view_count default" => "ALTER TABLE seller_books MODIFY COLUMN view_count INT DEFAULT 0",
    "Set sales_count default" => "ALTER TABLE seller_books MODIFY COLUMN sales_count INT DEFAULT 0"
];

foreach ($constraints as $constraint_name => $sql) {
    echo "<h3>$constraint_name:</h3>";
    if ($conn->query($sql)) {
        echo "<p class='success'>✅ $constraint_name applied successfully</p>";
        $fixes[] = "✅ $constraint_name";
    } else {
        echo "<p class='error'>❌ Error: " . $conn->error . "</p>";
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>🧹 Clean Up Duplicate/Unused Tables</h2>";

// 6. Remove duplicate/unused tables
$cleanup_tables = ['sellers', 'reviews', 'review_replies', 'password_resets'];
foreach ($cleanup_tables as $table) {
    echo "<h3>Checking table: $table</h3>";
    $check_result = $conn->query("SELECT COUNT(*) as count FROM `$table`");
    if ($check_result) {
        $count = $check_result->fetch_assoc()['count'];
        if ($count == 0) {
            if ($conn->query("DROP TABLE IF EXISTS `$table`")) {
                echo "<p class='success'>✅ Removed empty table: $table</p>";
                $fixes[] = "✅ Removed unused table: $table";
            } else {
                echo "<p class='error'>❌ Error removing $table: " . $conn->error . "</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ Table $table has $count records, keeping it</p>";
        }
    }
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📝 Summary of Applied Fixes</h2>";
if (!empty($fixes)) {
    echo "<ul>";
    foreach ($fixes as $fix) {
        echo "<li>$fix</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='info'>No fixes were needed or all fixes failed</p>";
}
echo "<p><strong>Total fixes applied:</strong> " . count($fixes) . "</p>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";

$conn->close();
?>

<script>
// Auto refresh to show updated structure
setTimeout(function() {
    if(confirm('Database structure fixes completed. Would you like to run the audit again to verify changes?')) {
        window.location.href = 'comprehensive_audit.php';
    }
}, 2000);
</script>
