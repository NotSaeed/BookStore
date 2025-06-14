<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "bookstore";

// Set default timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

try {
    // Create connection with error handling
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Set charset to utf8mb4 for full Unicode support
    $conn->set_charset("utf8mb4");
    
    // Check connection
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        die("Connection failed. Please try again later.");
    }
    
    // Set SQL mode for better data integrity
    $conn->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'");
    
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please contact support.");
}

/**
 * Enhanced logging function for seller activities
 */
function logSellerActivity($conn, $seller_id, $action, $book_id = null, $details = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, book_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isissn", $seller_id, $action, $book_id, $ip_address, $user_agent, $details);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Activity logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Security logging function
 */
function logSecurityEvent($conn, $event_type, $email = null, $details = null, $severity = 'medium') {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $details_json = $details ? json_encode($details) : null;
        
        $stmt = $conn->prepare("INSERT INTO security_logs (event_type, email, ip_address, user_agent, details, severity) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $event_type, $email, $ip_address, $user_agent, $details_json, $severity);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Security logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Database audit logging function
 */
function auditLog($conn, $seller_id, $operation, $table_affected, $record_id = null, $old_values = null, $new_values = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $old_json = $old_values ? json_encode($old_values) : null;
        $new_json = $new_values ? json_encode($new_values) : null;
        
        $stmt = $conn->prepare("INSERT INTO db_audit_log (seller_id, operation, table_affected, record_id, old_values, new_values, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississss", $seller_id, $operation, $table_affected, $record_id, $old_json, $new_json, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        return true;
    } catch (Exception $e) {
        error_log("Audit logging failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get seller preferences
 */
function getSellerPreferences($conn, $seller_id) {
    try {
        $stmt = $conn->prepare("SELECT dark_mode, compact_view, email_notifications, language, timezone, currency FROM seller_users WHERE seller_id = ?");
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $preferences = $result->fetch_assoc();
        $stmt->close();
        
        return $preferences ?: [
            'dark_mode' => 0,
            'compact_view' => 0,
            'email_notifications' => 1,
            'language' => 'en',
            'timezone' => 'Asia/Kuala_Lumpur',
            'currency' => 'MYR'
        ];
    } catch (Exception $e) {
        error_log("Error fetching preferences: " . $e->getMessage());
        return [];
    }
}

/**
 * Update seller preferences
 */
function updateSellerPreferences($conn, $seller_id, $preferences) {
    try {
        $fields = [];
        $values = [];
        $types = '';
        
        foreach ($preferences as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
            $types .= is_int($value) ? 'i' : 's';
        }
        
        $values[] = $seller_id;
        $types .= 'i';
        
        $sql = "UPDATE seller_users SET " . implode(', ', $fields) . " WHERE seller_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$values);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error updating preferences: " . $e->getMessage());
        return false;
    }
}

/**
 * Create notification for seller
 */
function createNotification($conn, $seller_id, $type, $title, $message, $data = null) {
    try {
        $data_json = $data ? json_encode($data) : null;
        
        $stmt = $conn->prepare("INSERT INTO seller_notifications (seller_id, type, title, message, data) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $seller_id, $type, $title, $message, $data_json);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($conn, $seller_id) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM seller_notifications WHERE seller_id = ? AND read_at IS NULL");
        $stmt->bind_param("i", $seller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)$row['count'];
    } catch (Exception $e) {
        error_log("Error getting notifications count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Sanitize and validate input
 */
function sanitizeInput($input, $type = 'string') {
    switch ($type) {
        case 'email':
            return filter_var(trim($input), FILTER_SANITIZE_EMAIL);
        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);
        case 'url':
            return filter_var(trim($input), FILTER_SANITIZE_URL);
        default:
            return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate book data
 */
function validateBookData($data) {
    $errors = [];
    
    if (empty($data['title']) || strlen($data['title']) < 2) {
        $errors[] = "Book title must be at least 2 characters long.";
    }
    
    if (empty($data['author']) || strlen($data['author']) < 2) {
        $errors[] = "Author name must be at least 2 characters long.";
    }
    
    if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] <= 0) {
        $errors[] = "Price must be a valid positive number.";
    }
    
    if (!empty($data['isbn']) && !preg_match('/^[0-9\-]{10,17}$/', $data['isbn'])) {
        $errors[] = "ISBN format is invalid.";
    }
    
    if (!in_array($data['condition_type'], ['new', 'like_new', 'very_good', 'good', 'acceptable'])) {
        $errors[] = "Invalid condition type.";
    }
    
    if (isset($data['stock_quantity']) && (!is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0)) {
        $errors[] = "Stock quantity must be a non-negative number.";
    }
    
    return $errors;
}

/**
 * Get comprehensive book statistics
 */
function getBookStatistics($conn, $seller_id, $book_id = null) {
    try {
        $where_clause = "WHERE sb.seller_id = ?";
        $params = [$seller_id];
        $types = "i";
        
        if ($book_id) {
            $where_clause .= " AND sb.book_id = ?";
            $params[] = $book_id;
            $types .= "i";
        }
        
        $sql = "SELECT 
                    COUNT(sb.book_id) as total_books,
                    SUM(sb.stock_quantity) as total_stock,
                    SUM(sb.price * sb.stock_quantity) as inventory_value,
                    AVG(sb.price) as avg_price,
                    COUNT(CASE WHEN sb.is_public = 1 THEN 1 END) as public_books,
                    COUNT(CASE WHEN sb.is_featured = 1 THEN 1 END) as featured_books,
                    SUM(sb.view_count) as total_views,
                    SUM(sb.sales_count) as total_sales,
                    COALESCE(AVG(sr.rating), 0) as avg_rating,
                    COUNT(sr.review_id) as total_reviews
                FROM seller_books sb
                LEFT JOIN seller_reviews sr ON sb.book_id = sr.book_id
                $where_clause";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting book statistics: " . $e->getMessage());
        return [];
    }
}

// Global error handler for database operations
function handleDatabaseError($conn, $operation = 'Database operation') {
    if ($conn->error) {
        error_log("$operation failed: " . $conn->error);
        return "An error occurred. Please try again.";
    }
    return null;
}

// Connection cleanup on script end (removed to prevent double-close error)
// Database connections are automatically closed when the script ends
?>
