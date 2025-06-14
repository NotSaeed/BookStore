<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookStore System Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
    </style>
</head>
<body>
    <h1>BookStore System Test</h1>
    
    <?php
    // Test database connection
    echo "<h2>Database Connection Test</h2>";
    
    try {
        require_once __DIR__ . '/database/config.php';
        $pdo = getDBConnection();
        echo "<div class='test-result success'>✓ Database connection successful</div>";
        
        // Test if tables exist
        $tables = ['seller_users', 'seller_books', 'customers', 'users'];
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT 1 FROM $table LIMIT 1");
                echo "<div class='test-result success'>✓ Table '$table' exists and accessible</div>";
            } catch (Exception $e) {
                echo "<div class='test-result error'>✗ Table '$table' not found or not accessible</div>";
            }
        }
        
        // Test seller data
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM seller_users");
            $result = $stmt->fetch();
            echo "<div class='test-result info'>📊 Found {$result['count']} seller accounts in database</div>";
        } catch (Exception $e) {
            echo "<div class='test-result error'>✗ Could not count seller accounts</div>";
        }
        
        // Test seller books
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM seller_books");
            $result = $stmt->fetch();
            echo "<div class='test-result info'>📚 Found {$result['count']} books in database</div>";
        } catch (Exception $e) {
            echo "<div class='test-result error'>✗ Could not count books</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='test-result error'>✗ Database connection failed: " . $e->getMessage() . "</div>";
        echo "<div class='test-result info'>💡 Try running the database installation first</div>";
    }
    ?>
    
    <h2>File Structure Test</h2>
    
    <?php
    $required_files = [
        'index.html' => 'Main landing page',
        'select-role.html' => 'Role selection page',
        'seller/seller_login.php' => 'Seller login page',
        'seller/seller_dashboard.php' => 'Seller dashboard',
        'seller/includes/seller_db.php' => 'Database connection file',
        'seller/includes/seller_header.php' => 'Common header',
        'database/config.php' => 'Database configuration',
        'database/bookstore.sql' => 'Database schema'
    ];
    
    foreach ($required_files as $file => $description) {
        if (file_exists(__DIR__ . '/' . $file)) {
            echo "<div class='test-result success'>✓ $description ($file)</div>";
        } else {
            echo "<div class='test-result error'>✗ Missing: $description ($file)</div>";
        }
    }
    ?>
    
    <h2>Quick Navigation</h2>
    
    <a href="index.html" class="btn btn-primary">Main Site</a>
    <a href="select-role.html" class="btn btn-success">Select Role</a>
    <a href="seller/seller_login.php" class="btn btn-warning">Seller Login</a>
    <a href="database/install.php" class="btn btn-primary">Database Install</a>
    
    <h2>Test Credentials</h2>
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
    
    <p><small>Generated on: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>
</html>
