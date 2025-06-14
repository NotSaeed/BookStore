<?php
/**
 * BookStore Database Installation Script
 * This script will create the database and all required tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'bookstore'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BookStore Database Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
        }
        .install-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .install-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 1rem;
            font-weight: bold;
            position: relative;
        }
        .step.active {
            background: #28a745;
            color: white;
        }
        .step.pending {
            background: #6c757d;
            color: white;
        }
        .step.complete {
            background: #198754;
            color: white;
        }
        .log-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .success-message {
            color: #198754;
        }
        .error-message {
            color: #dc3545;
        }
        .warning-message {
            color: #fd7e14;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><i class="bi bi-database me-2"></i>BookStore Database Installation</h1>
                <p class="mb-0">Setting up your BookStore database and tables</p>
            </div>
            
            <div class="p-4">
                <div class="step-indicator">
                    <div class="step pending" id="step1">1</div>
                    <div class="step pending" id="step2">2</div>
                    <div class="step pending" id="step3">3</div>
                    <div class="step pending" id="step4">4</div>
                </div>
                
                <div id="installation-progress">
                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
                        <?php
                        echo '<div class="log-output" id="log-output">';
                        installDatabase($config);
                        echo '</div>';
                        ?>
                    <?php else: ?>
                        <div class="text-center">
                            <h4>Ready to Install Database</h4>
                            <p class="text-muted">This will create the BookStore database and all required tables.</p>
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>Prerequisites:</h6>
                                <ul class="text-start mb-0">
                                    <li>XAMPP/WAMP/MAMP running with MySQL</li>
                                    <li>MySQL root access (default configuration)</li>
                                    <li>PHP 7.4 or higher</li>
                                </ul>
                            </div>
                            
                            <form method="POST" id="installForm">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="bi bi-play-circle me-2"></i>Start Installation
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStep(stepNumber, status) {
            const step = document.getElementById('step' + stepNumber);
            step.className = 'step ' + status;
            if (status === 'complete') {
                step.innerHTML = '<i class="bi bi-check"></i>';
            }
        }

        function logMessage(message, type = 'info') {
            const logOutput = document.getElementById('log-output');
            if (logOutput) {
                const className = type === 'error' ? 'error-message' : 
                                type === 'success' ? 'success-message' : 
                                type === 'warning' ? 'warning-message' : '';
                logOutput.innerHTML += `<div class="${className}">${message}</div>`;
                logOutput.scrollTop = logOutput.scrollHeight;
            }
        }
    </script>
</body>
</html>

<?php
function installDatabase($config) {
    echo "<script>updateStep(1, 'active');</script>";
    echo "<script>logMessage('Starting database installation...', 'info');</script>";
    flush();
    
    try {
        // Step 1: Connect to MySQL server
        echo "<script>logMessage('Connecting to MySQL server...', 'info');</script>";
        flush();
        
        $conn = new mysqli($config['host'], $config['username'], $config['password']);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        echo "<script>updateStep(1, 'complete');</script>";
        echo "<script>logMessage('✓ Connected to MySQL server', 'success');</script>";
        flush();
        
        // Step 2: Create database
        echo "<script>updateStep(2, 'active');</script>";
        echo "<script>logMessage('Creating database...', 'info');</script>";
        flush();
        
        $sql = "CREATE DATABASE IF NOT EXISTS `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
        if ($conn->query($sql) === TRUE) {
            echo "<script>logMessage('✓ Database \"{$config['database']}\" created successfully', 'success');</script>";
        } else {
            throw new Exception("Error creating database: " . $conn->error);
        }
        
        // Select the database
        $conn->select_db($config['database']);
        echo "<script>updateStep(2, 'complete');</script>";
        flush();
        
        // Step 3: Create tables
        echo "<script>updateStep(3, 'active');</script>";
        echo "<script>logMessage('Creating tables...', 'info');</script>";
        flush();
        
        // Read and execute schema file
        $schemaFile = __DIR__ . '/../database/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("Schema file not found: $schemaFile");
        }
        
        $schema = file_get_contents($schemaFile);
        $statements = explode(';', $schema);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || substr($statement, 0, 2) === '--' || substr($statement, 0, 3) === 'SET' || substr($statement, 0, 3) === 'USE') {
                continue;
            }
            
            if ($conn->query($statement) === FALSE) {
                echo "<script>logMessage('Warning: " . addslashes($conn->error) . "', 'warning');</script>";
            }
        }
        
        echo "<script>updateStep(3, 'complete');</script>";
        echo "<script>logMessage('✓ Tables created successfully', 'success');</script>";
        flush();
        
        // Step 4: Verify installation
        echo "<script>updateStep(4, 'active');</script>";
        echo "<script>logMessage('Verifying installation...', 'info');</script>";
        flush();
        
        $tables = [
            'seller_users', 'seller_books', 'seller_activity_log', 'seller_reviews',
            'seller_orders', 'db_audit_log', 'security_logs', 'password_reset_tokens',
            'seller_sessions', 'seller_notifications', 'book_images'
        ];
        
        $existingTables = [];
        $result = $conn->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $existingTables[] = $row[0];
        }
        
        $missing = array_diff($tables, $existingTables);
        if (empty($missing)) {
            echo "<script>updateStep(4, 'complete');</script>";
            echo "<script>logMessage('✓ All tables verified successfully', 'success');</script>";
            echo "<script>logMessage('', 'info');</script>";
            echo "<script>logMessage('🎉 Installation completed successfully!', 'success');</script>";
            echo "<script>logMessage('You can now use your BookStore application.', 'success');</script>";
            echo "<script>logMessage('', 'info');</script>";
            echo "<script>logMessage('Default login credentials:', 'info');</script>";
            echo "<script>logMessage('Email: admin@bookstore.com', 'info');</script>";
            echo "<script>logMessage('Password: admin123', 'info');</script>";
        } else {
            throw new Exception("Missing tables: " . implode(', ', $missing));
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        echo "<script>logMessage('✗ Error: " . addslashes($e->getMessage()) . "', 'error');</script>";
        echo "<script>logMessage('Installation failed. Please check your configuration and try again.', 'error');</script>";
    }
    
    flush();
}
?>
