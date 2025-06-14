<?php
/**
 * Database Helper Functions for BookStore Seller System
 * Enhanced utility functions for database operations with improved error handling,
 * logging, and additional database management features.
 * 
 * @version 2.0.0
 * @author BookStore Development Team
 */

/**
 * Check if a column exists in a table
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @return bool True if column exists, false otherwise
 */
function column_exists($conn, $table, $column) {
    try {
        $sql = "SHOW COLUMNS FROM `{$table}` LIKE ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare column check query: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("s", $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = ($result && $result->num_rows > 0);
        $stmt->close();
        
        return $exists;
    } catch (Exception $e) {
        error_log("Error checking column existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if a table exists in the database
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @return bool True if table exists, false otherwise
 */
function table_exists($conn, $table) {
    try {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare table check query: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("s", $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = ($result && $result->num_rows > 0);
        $stmt->close();
        
        return $exists;
    } catch (Exception $e) {
        error_log("Error checking table existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if an index exists on a table
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $index Index name
 * @return bool True if index exists, false otherwise
 */
function index_exists($conn, $table, $index) {
    try {
        $sql = "SHOW INDEX FROM `{$table}` WHERE Key_name = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Failed to prepare index check query: " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("s", $index);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = ($result && $result->num_rows > 0);
        $stmt->close();
        
        return $exists;
    } catch (Exception $e) {
        error_log("Error checking index existence: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe prepare - wraps mysqli prepare with enhanced error checking and logging
 *
 * @param mysqli $conn Database connection
 * @param string $sql SQL statement
 * @param bool $log_errors Whether to log errors (default: true)
 * @return mysqli_stmt|false Statement object or false on failure
 */
function safe_prepare($conn, $sql, $log_errors = true) {
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            if ($log_errors) {
                error_log("Prepare failed: " . $conn->error . " for SQL: " . substr($sql, 0, 200) . "...");
            }
            return false;
        }
        return $stmt;
    } catch (Exception $e) {
        if ($log_errors) {
            error_log("Exception in safe_prepare: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Execute a prepared statement safely with error handling
 *
 * @param mysqli_stmt $stmt Prepared statement
 * @param array $params Parameters to bind
 * @param string $types Parameter types string
 * @param bool $log_errors Whether to log errors
 * @return bool True on success, false on failure
 */
function safe_execute($stmt, $params = [], $types = '', $log_errors = true) {
    try {
        if (!empty($params) && !empty($types)) {
            if (!$stmt->bind_param($types, ...$params)) {
                if ($log_errors) {
                    error_log("Failed to bind parameters: " . $stmt->error);
                }
                return false;
            }
        }
        
        if (!$stmt->execute()) {
            if ($log_errors) {
                error_log("Failed to execute statement: " . $stmt->error);
            }
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        if ($log_errors) {
            error_log("Exception in safe_execute: " . $e->getMessage());
        }
        return false;
    }
}

/**
 * Add column to table if it doesn't exist with enhanced error handling
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @param string $definition Column definition (e.g. "INT NOT NULL DEFAULT 0")
 * @param string $after_column Add column after this column (optional)
 * @return bool True if column was added or already exists, false on error
 */
function add_column_if_not_exists($conn, $table, $column, $definition, $after_column = null) {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        if (!column_exists($conn, $table, $column)) {
            $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
            
            if ($after_column && column_exists($conn, $table, $after_column)) {
                $sql .= " AFTER `{$after_column}`";
            }
            
            $result = $conn->query($sql);
            if (!$result) {
                error_log("Failed to add column '{$column}' to table '{$table}': " . $conn->error);
                return false;
            }
            
            error_log("Successfully added column '{$column}' to table '{$table}'");
            return true;
        }
        
        return true; // Column already exists
    } catch (Exception $e) {
        error_log("Exception in add_column_if_not_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Drop column from table if it exists
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @return bool True if column was dropped or doesn't exist, false on error
 */
function drop_column_if_exists($conn, $table, $column) {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        if (column_exists($conn, $table, $column)) {
            $sql = "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Failed to drop column '{$column}' from table '{$table}': " . $conn->error);
                return false;
            }
            
            error_log("Successfully dropped column '{$column}' from table '{$table}'");
            return true;
        }
        
        return true; // Column doesn't exist
    } catch (Exception $e) {
        error_log("Exception in drop_column_if_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Modify column definition if it exists
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $column Column name
 * @param string $new_definition New column definition
 * @return bool True on success, false on error
 */
function modify_column_if_exists($conn, $table, $column, $new_definition) {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        if (column_exists($conn, $table, $column)) {
            $sql = "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$new_definition}";
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Failed to modify column '{$column}' in table '{$table}': " . $conn->error);
                return false;
            }
            
            error_log("Successfully modified column '{$column}' in table '{$table}'");
            return true;
        }
        
        error_log("Column '{$column}' does not exist in table '{$table}'");
        return false;
    } catch (Exception $e) {
        error_log("Exception in modify_column_if_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Add index to table if it doesn't exist
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $index_name Index name
 * @param array $columns Array of column names
 * @param string $type Index type ('INDEX', 'UNIQUE', 'FULLTEXT')
 * @return bool True if index was added or already exists, false on error
 */
function add_index_if_not_exists($conn, $table, $index_name, $columns, $type = 'INDEX') {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        if (!index_exists($conn, $table, $index_name)) {
            $columns_str = '`' . implode('`, `', $columns) . '`';
            $sql = "ALTER TABLE `{$table}` ADD {$type} `{$index_name}` ({$columns_str})";
            
            $result = $conn->query($sql);
            if (!$result) {
                error_log("Failed to add index '{$index_name}' to table '{$table}': " . $conn->error);
                return false;
            }
            
            error_log("Successfully added index '{$index_name}' to table '{$table}'");
            return true;
        }
        
        return true; // Index already exists
    } catch (Exception $e) {
        error_log("Exception in add_index_if_not_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Drop index from table if it exists
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $index_name Index name
 * @return bool True if index was dropped or doesn't exist, false on error
 */
function drop_index_if_exists($conn, $table, $index_name) {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        if (index_exists($conn, $table, $index_name)) {
            $sql = "ALTER TABLE `{$table}` DROP INDEX `{$index_name}`";
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Failed to drop index '{$index_name}' from table '{$table}': " . $conn->error);
                return false;
            }
            
            error_log("Successfully dropped index '{$index_name}' from table '{$table}'");
            return true;
        }
        
        return true; // Index doesn't exist
    } catch (Exception $e) {
        error_log("Exception in drop_index_if_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Create table if it doesn't exist
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $definition Table definition SQL
 * @return bool True if table was created or already exists, false on error
 */
function create_table_if_not_exists($conn, $table, $definition) {
    try {
        if (!table_exists($conn, $table)) {
            $sql = "CREATE TABLE `{$table}` ({$definition})";
            $result = $conn->query($sql);
            
            if (!$result) {
                error_log("Failed to create table '{$table}': " . $conn->error);
                return false;
            }
            
            error_log("Successfully created table '{$table}'");
            return true;
        }
        
        return true; // Table already exists
    } catch (Exception $e) {
        error_log("Exception in create_table_if_not_exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Get table column information
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @return array|false Array of column information or false on error
 */
function get_table_columns($conn, $table) {
    try {
        $sql = "SHOW COLUMNS FROM `{$table}`";
        $result = $conn->query($sql);
        
        if (!$result) {
            error_log("Failed to get columns for table '{$table}': " . $conn->error);
            return false;
        }
        
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row;
        }
        
        return $columns;
    } catch (Exception $e) {
        error_log("Exception in get_table_columns: " . $e->getMessage());
        return false;
    }
}

/**
 * Get table size and row count
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @return array|false Array with size info or false on error
 */
function get_table_info($conn, $table) {
    try {
        $sql = "SELECT 
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size,
                    auto_increment
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() AND table_name = ?";
        
        $stmt = safe_prepare($conn, $sql);
        if (!$stmt) {
            return false;
        }
        
        $stmt->bind_param("s", $table);
        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        $info = $result->fetch_assoc();
        $stmt->close();
        
        return $info;
    } catch (Exception $e) {
        error_log("Exception in get_table_info: " . $e->getMessage());
        return false;
    }
}

/**
 * Backup table data to SQL file
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @param string $backup_file Backup file path
 * @return bool True on success, false on error
 */
function backup_table($conn, $table, $backup_file) {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        // Get table structure
        $create_table_query = $conn->query("SHOW CREATE TABLE `{$table}`");
        if (!$create_table_query) {
            error_log("Failed to get table structure for '{$table}': " . $conn->error);
            return false;
        }
        
        $create_table = $create_table_query->fetch_row();
        $sql_content = "-- Backup for table: {$table}\n";
        $sql_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql_content .= $create_table[1] . ";\n\n";
        
        // Get table data
        $data_query = $conn->query("SELECT * FROM `{$table}`");
        if (!$data_query) {
            error_log("Failed to get table data for '{$table}': " . $conn->error);
            return false;
        }
        
        while ($row = $data_query->fetch_assoc()) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = "'" . $conn->real_escape_string($value) . "'";
                }
            }
            $sql_content .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
        }
        
        // Write to file
        if (file_put_contents($backup_file, $sql_content) === false) {
            error_log("Failed to write backup file: {$backup_file}");
            return false;
        }
        
        error_log("Successfully backed up table '{$table}' to '{$backup_file}'");
        return true;
    } catch (Exception $e) {
        error_log("Exception in backup_table: " . $e->getMessage());
        return false;
    }
}

/**
 * Execute multiple SQL statements from a file or string
 *
 * @param mysqli $conn Database connection
 * @param string $sql_content SQL content or file path
 * @param bool $is_file Whether the content is a file path
 * @return bool True on success, false on error
 */
function execute_sql_batch($conn, $sql_content, $is_file = false) {
    try {
        if ($is_file) {
            if (!file_exists($sql_content)) {
                error_log("SQL file does not exist: {$sql_content}");
                return false;
            }
            $sql_content = file_get_contents($sql_content);
        }
        
        // Split SQL statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql_content)),
            function($stmt) { return !empty($stmt) && !preg_match('/^--/', $stmt); }
        );
        
        $conn->autocommit(false);
        
        foreach ($statements as $statement) {
            if (!$conn->query($statement)) {
                error_log("Failed to execute SQL: " . $statement . " Error: " . $conn->error);
                $conn->rollback();
                $conn->autocommit(true);
                return false;
            }
        }
        
        $conn->commit();
        $conn->autocommit(true);
        
        error_log("Successfully executed " . count($statements) . " SQL statements");
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        $conn->autocommit(true);
        error_log("Exception in execute_sql_batch: " . $e->getMessage());
        return false;
    }
}

/**
 * Optimize table (equivalent to OPTIMIZE TABLE)
 *
 * @param mysqli $conn Database connection
 * @param string $table Table name
 * @return bool True on success, false on error
 */
function optimize_table($conn, $table) {
    try {
        if (!table_exists($conn, $table)) {
            error_log("Table '{$table}' does not exist");
            return false;
        }
        
        $sql = "OPTIMIZE TABLE `{$table}`";
        $result = $conn->query($sql);
        
        if (!$result) {
            error_log("Failed to optimize table '{$table}': " . $conn->error);
            return false;
        }
        
        error_log("Successfully optimized table '{$table}'");
        return true;
    } catch (Exception $e) {
        error_log("Exception in optimize_table: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database connection status and statistics
 *
 * @param mysqli $conn Database connection
 * @return array Connection status information
 */
function get_connection_status($conn) {
    try {
        $status = [
            'connected' => $conn->ping(),
            'host_info' => $conn->host_info,
            'server_info' => $conn->server_info,
            'protocol_version' => $conn->protocol_version,
            'thread_id' => $conn->thread_id,
            'charset' => $conn->character_set_name(),
            'autocommit' => $conn->autocommit(null),
            'warnings' => $conn->warning_count
        ];
        
        // Get additional server status
        $result = $conn->query("SHOW STATUS LIKE 'Connections'");
        if ($result) {
            $row = $result->fetch_assoc();
            $status['total_connections'] = $row['Value'];
        }
        
        $result = $conn->query("SHOW STATUS LIKE 'Uptime'");
        if ($result) {
            $row = $result->fetch_assoc();
            $status['uptime_seconds'] = $row['Value'];
        }
        
        return $status;
    } catch (Exception $e) {
        error_log("Exception in get_connection_status: " . $e->getMessage());
        return ['connected' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Log database operation for audit purposes
 *
 * @param mysqli $conn Database connection
 * @param int $seller_id Seller ID
 * @param string $operation Operation description
 * @param string $table_affected Table name affected
 * @param array $additional_data Additional data to log
 * @return bool True on success, false on error
 */
function log_db_operation($conn, $seller_id, $operation, $table_affected = '', $additional_data = []) {
    try {
        // Create audit log table if it doesn't exist
        $table_sql = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT,
            operation VARCHAR(255) NOT NULL,
            table_affected VARCHAR(100),
            additional_data JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_seller_id (seller_id),
            INDEX idx_operation (operation),
            INDEX idx_created_at (created_at)
        ";
        
        create_table_if_not_exists($conn, 'db_audit_log', $table_sql);
        
        $stmt = safe_prepare($conn, "
            INSERT INTO db_audit_log 
            (seller_id, operation, table_affected, additional_data, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            return false;
        }
        
        $additional_json = json_encode($additional_data);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->bind_param("isssss", $seller_id, $operation, $table_affected, $additional_json, $ip_address, $user_agent);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Exception in log_db_operation: " . $e->getMessage());
        return false;
    }
}

/**
 * Initialize required tables and columns for the seller system
 *
 * @param mysqli $conn Database connection
 * @return bool True on success, false on error
 */
function initialize_seller_tables($conn) {
    try {
        $success = true;
        
        // Add commonly needed columns to seller_users table
        $seller_columns = [
            'profile_photo' => 'VARCHAR(255)',
            'bio' => 'TEXT',
            'website' => 'VARCHAR(255)',
            'location' => 'VARCHAR(100)',
            'phone' => 'VARCHAR(20)',
            'business_name' => 'VARCHAR(255)',
            'business_type' => 'VARCHAR(50)',
            'business_address' => 'TEXT',
            'business_phone' => 'VARCHAR(20)',
            'business_email' => 'VARCHAR(255)',
            'tax_id' => 'VARCHAR(50)',
            'dark_mode' => 'TINYINT(1) DEFAULT 0',
            'compact_view' => 'TINYINT(1) DEFAULT 0',
            'email_notifications' => 'TINYINT(1) DEFAULT 1',
            'language' => 'VARCHAR(5) DEFAULT "en"',
            'timezone' => 'VARCHAR(50) DEFAULT "Asia/Kuala_Lumpur"',
            'currency' => 'VARCHAR(3) DEFAULT "MYR"',
            'notify_orders' => 'TINYINT(1) DEFAULT 1',
            'notify_messages' => 'TINYINT(1) DEFAULT 1',
            'notify_reviews' => 'TINYINT(1) DEFAULT 1',
            'notify_system' => 'TINYINT(1) DEFAULT 1',
            'notify_marketing' => 'TINYINT(1) DEFAULT 0',
            'sms_notifications' => 'TINYINT(1) DEFAULT 0',
            'two_factor_enabled' => 'TINYINT(1) DEFAULT 0',
            'two_factor_secret' => 'VARCHAR(32)',
            'password_changed_at' => 'TIMESTAMP NULL',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        foreach ($seller_columns as $column => $definition) {
            if (!add_column_if_not_exists($conn, 'seller_users', $column, $definition)) {
                $success = false;
            }
        }
        
        // Add indexes for better performance
        $indexes = [
            'idx_seller_email' => ['seller_email'],
            'idx_business_name' => ['business_name'],
            'idx_location' => ['location'],
            'idx_updated_at' => ['updated_at']
        ];
        
        foreach ($indexes as $index_name => $columns) {
            add_index_if_not_exists($conn, 'seller_users', $index_name, $columns);
        }
        
        // Create seller activity log table if it doesn't exist
        $activity_table_sql = "
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_seller_id (seller_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (seller_id) REFERENCES seller_users(seller_id) ON DELETE CASCADE
        ";
        
        if (!create_table_if_not_exists($conn, 'seller_activity_log', $activity_table_sql)) {
            $success = false;
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Exception in initialize_seller_tables: " . $e->getMessage());
        return false;
    }
}