<?php
/**
 * Database Column Migration Script
 * Fixes all database column naming inconsistencies in BookStore application
 */

// Define column mappings
$column_mappings = [
    'book_title' => 'title',
    'book_author' => 'author',
    'book_price' => 'price', 
    'book_stock' => 'stock_quantity',
    'book_category' => 'category',
    'book_description' => 'description',
    'book_isbn' => 'isbn',
    'book_cover' => 'cover_image',
    'book_genre' => 'genre',
    'book_image' => 'cover_image'
];

$files_to_update = [
    'seller_dashboard.php',
    'seller_manage_books.php', 
    'seller_add_book.php',
    'seller_edit_book.php',
    'seller_delete_book.php',
    'seller_search.php',
    'seller_view_book.php',
    'toggle_visibility.php',
    'toggle_featured.php',
    'seller_toggle_flags.php',
    'seller_export_excel.php',
    'seller_export_pdf.php',
    'book_preview.php',
    'public_view_book.php'
];

echo "Database Column Migration Script\n";
echo "================================\n\n";

foreach ($files_to_update as $file) {
    $file_path = __DIR__ . '/seller/' . $file;
    if (!file_exists($file_path)) {
        echo "❌ File not found: $file\n";
        continue;
    }
    
    $content = file_get_contents($file_path);
    $original_content = $content;
    $changes_made = 0;
    
    // Apply column name replacements
    foreach ($column_mappings as $old_name => $new_name) {
        // Replace in SQL queries
        $patterns = [
            "/\b$old_name\b/",
            "/\['$old_name'\]/",
            "/\"$old_name\"/"
        ];
        
        foreach ($patterns as $pattern) {
            $replacement = str_replace($old_name, $new_name, $pattern);
            $new_content = preg_replace($pattern, $new_name, $content);
            if ($new_content !== $content) {
                $content = $new_content;
                $changes_made++;
            }
        }
    }
    
    if ($content !== $original_content) {
        file_put_contents($file_path, $content);
        echo "✅ Updated $file ($changes_made changes)\n";
    } else {
        echo "ℹ️  No changes needed for $file\n";
    }
}

echo "\n✅ Migration completed!\n";
echo "\nNext steps:\n";
echo "1. Test database connection\n";
echo "2. Verify all PHP files work correctly\n";
echo "3. Check for any remaining column name issues\n";
?>
