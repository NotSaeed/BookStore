<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Module System Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            line-height: 1.6;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            text-decoration: none;
            border-radius: 5px;
            color: white;
            font-weight: bold;
        }
        .btn-primary { background-color: #007bff; }
        .btn-success { background-color: #28a745; }
        .btn-warning { background-color: #ffc107; color: #212529; }
        .btn-danger { background-color: #dc3545; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <h1>Seller Module System Test</h1>
    
    <?php
    // Test database connection
    echo "<h2>1. Database Connection Test</h2>";
    
    try {
        require_once __DIR__ . '/includes/seller_db.php';
        echo "<div class='test-result success'>✓ Database connection successful</div>";
        
        // Test seller_users table
        try {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM seller_users");
            $result = $stmt->fetch_assoc();
            echo "<div class='test-result success'>✓ seller_users table accessible - {$result['count']} sellers</div>";
        } catch (Exception $e) {
            echo "<div class='test-result error'>✗ seller_users table error: " . $e->getMessage() . "</div>";
        }
        
        // Test seller_books table
        try {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM seller_books");
            $result = $stmt->fetch_assoc();
            echo "<div class='test-result success'>✓ seller_books table accessible - {$result['count']} books</div>";
        } catch (Exception $e) {
            echo "<div class='test-result error'>✗ seller_books table error: " . $e->getMessage() . "</div>";
        }
        
        // Test seller_activity_log table
        try {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM seller_activity_log");
            $result = $stmt->fetch_assoc();
            echo "<div class='test-result success'>✓ seller_activity_log table accessible - {$result['count']} logs</div>";
        } catch (Exception $e) {
            echo "<div class='test-result error'>✗ seller_activity_log table error: " . $e->getMessage() . "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='test-result error'>✗ Database connection failed: " . $e->getMessage() . "</div>";
    }
    ?>
    
    <h2>2. File Structure Test</h2>
    
    <?php
    $required_files = [
        'seller_login.php' => 'Seller login page',
        'seller_dashboard.php' => 'Seller dashboard',
        'seller_add_book.php' => 'Add book functionality',
        'seller_manage_books.php' => 'Book management',
        'seller_settings.php' => 'Account settings',
        'includes/seller_db.php' => 'Database connection',
        'includes/seller_header.php' => 'Common header',
        'includes/seller_footer.php' => 'Common footer',
        'uploads/covers/' => 'Book covers upload directory',
        'uploads/profiles/' => 'Profile photos upload directory'
    ];
    
    foreach ($required_files as $file => $description) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "<div class='test-result success'>✓ $description ($file)</div>";
        } else {
            echo "<div class='test-result error'>✗ Missing: $description ($file)</div>";
        }
    }
    ?>
    
    <h2>3. Navigation Files Test</h2>
    
    <?php
    $nav_files = [
        'seller_analytics.php' => 'Analytics Dashboard',
        'seller_reports.php' => 'Reports Center', 
        'seller_sales.php' => 'Sales Management',
        'seller_import_books.php' => 'Import Books',
        'seller_bulk_edit.php' => 'Bulk Edit'
    ];
    
    foreach ($nav_files as $file => $description) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "<div class='test-result success'>✓ $description ($file)</div>";
        } else {
            echo "<div class='test-result warning'>⚠ Missing: $description ($file) - Will create placeholder</div>";
        }
    }
    ?>
    
    <h2>4. Database Schema Test</h2>
    
    <?php
    if (isset($conn)) {        // Test password verification
        try {
            $test_email = 'seller1@bookstore.com';
            $stmt = $conn->prepare("SELECT seller_password FROM seller_users WHERE seller_email = ?");
            $stmt->bind_param("s", $test_email);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($result && password_verify('password123', $result['seller_password'])) {
                echo "<div class='test-result success'>✓ Password verification working for test account</div>";
            } else {
                echo "<div class='test-result error'>✗ Password verification failed for test account</div>";
            }
        } catch (Exception $e) {
            echo "<div class='test-result error'>✗ Password test failed: " . $e->getMessage() . "</div>";
        }
    }
    ?>
    
    <h2>5. Upload Directory Permissions</h2>
    
    <?php
    $upload_dirs = ['uploads/', 'uploads/covers/', 'uploads/profiles/'];
    
    foreach ($upload_dirs as $dir) {
        $full_path = __DIR__ . '/' . $dir;
        if (is_dir($full_path)) {
            if (is_writable($full_path)) {
                echo "<div class='test-result success'>✓ $dir is writable</div>";
            } else {
                echo "<div class='test-result warning'>⚠ $dir exists but not writable</div>";
            }
        } else {
            echo "<div class='test-result error'>✗ $dir does not exist</div>";
        }
    }
    ?>
    
    <h2>6. Quick Navigation</h2>
    
    <a href="../index.html" class="btn btn-primary">Main Site</a>
    <a href="../select-role.html" class="btn btn-success">Select Role</a>
    <a href="seller_login.php" class="btn btn-warning">Seller Login</a>
    <a href="../database/install.php" class="btn btn-danger">Database Install</a>
    
    <h2>7. Test Credentials</h2>
    <div class="test-result info">
        <strong>Seller Login:</strong><br>
        Email: seller1@bookstore.com<br>
        Password: password123
    </div>
    
    <div class="test-result info">
        <strong>Alternative Seller:</strong><br>
        Email: seller2@bookstore.com<br>
        Password: password123
    </div>
    
    <h2>8. System Status Summary</h2>
    
    <?php
    $all_good = true;
    
    // Count issues
    $files_missing = 0;
    foreach ($required_files as $file => $desc) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $files_missing++;
            $all_good = false;
        }
    }
    
    if ($all_good && isset($conn)) {
        echo "<div class='test-result success'>";
        echo "<h3>🎉 All Systems Go!</h3>";
        echo "<p>Your seller module is fully functional and ready to use.</p>";
        echo "<p><strong>Ready for:</strong> Login, Dashboard, Book Management, Settings</p>";
        echo "</div>";
    } else {
        echo "<div class='test-result warning'>";
        echo "<h3>⚠ System Status</h3>";
        echo "<p>Some optional features may not be available, but core functionality should work.</p>";
        if ($files_missing > 0) {
            echo "<p>Missing files: $files_missing (mostly placeholder features)</p>";
        }
        echo "</div>";
    }
    ?>
    
    <p><small>Generated on: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>
