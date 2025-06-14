<?php
<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configuration
define('MAX_FEATURED_BOOKS', 10);
define('RATE_LIMIT_ATTEMPTS', 20);
define('RATE_LIMIT_WINDOW', 300); // 5 minutes

// Enhanced response function
function sendResponse($success = false, $message = '', $data = [], $httpCode = 200) {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json');
    }
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c'),
        'request_id' => uniqid('req_', true)
    ]);
    exit();
}

// CSRF Protection
function validateCSRFToken() {
    if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting function
function checkRateLimit($seller_id) {
    $key = "rate_limit_flags_" . $seller_id;
    $limit = RATE_LIMIT_ATTEMPTS;
    $window = RATE_LIMIT_WINDOW;
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    $_SESSION[$key]['count']++;
    
    if ($_SESSION[$key]['count'] > $limit) {
        sendResponse(false, 'Rate limit exceeded. Please try again later.', [], 429);
    }
}

// Enhanced logging function
function logActivity($conn, $seller_id, $action, $book_id = null, $details = []) {
    try {
        $stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, book_id, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        if ($stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $details_json = json_encode($details);
            $stmt->bind_param("isssss", $seller_id, $action, $book_id, $ip, $user_agent, $details_json);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Get comprehensive book information
function getBookInfo($conn, $book_id, $seller_id) {    $stmt = $conn->prepare("
        SELECT book_id, title, author, is_public, is_featured, 
               price, created_at, updated_at, description,
               COALESCE(view_count, 0) as view_count,
               COALESCE(sales_count, 0) as sales_count
        FROM seller_books 
        WHERE book_id = ? AND seller_id = ?
    ");
    $stmt->bind_param("ii", $book_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();
    return $book;
}

// Check featured book limit
function checkFeaturedLimit($conn, $seller_id, $excluding_book_id = null) {
    $sql = "SELECT COUNT(*) as featured_count FROM seller_books WHERE seller_id = ? AND is_featured = 1";
    $params = [$seller_id];
    $types = "i";
    
    if ($excluding_book_id) {
        $sql .= " AND book_id != ?";
        $params[] = $excluding_book_id;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return intval($row['featured_count']);
}

// Validate input parameters
function validateInput($book_id, $action) {
    $errors = [];
    
    if (!is_numeric($book_id) || $book_id <= 0) {
        $errors[] = 'Invalid book ID';
    }
    
    if (!in_array($action, ['toggle_public', 'toggle_featured', 'bulk_toggle_public', 'bulk_toggle_featured'])) {
        $errors[] = 'Invalid action';
    }
    
    return $errors;
}

// Handle bulk operations
function handleBulkOperation($conn, $seller_id, $action, $book_ids) {
    $results = [];
    $success_count = 0;
    $error_count = 0;
    
    foreach ($book_ids as $book_id) {
        if (!is_numeric($book_id) || $book_id <= 0) {
            $results[] = ['id' => $book_id, 'success' => false, 'message' => 'Invalid book ID'];
            $error_count++;
            continue;
        }
        
        $book_id = intval($book_id);
        $book = getBookInfo($conn, $book_id, $seller_id);
        
        if (!$book) {
            $results[] = ['id' => $book_id, 'success' => false, 'message' => 'Book not found'];
            $error_count++;
            continue;
        }
        
        $result = processSingleToggle($conn, $seller_id, $action, $book);
        $results[] = array_merge(['id' => $book_id], $result);
        
        if ($result['success']) {
            $success_count++;
        } else {
            $error_count++;
        }
    }
    
    return [
        'results' => $results,
        'success_count' => $success_count,
        'error_count' => $error_count
    ];
}

// Process single toggle operation
function processSingleToggle($conn, $seller_id, $action, $book) {
    try {
        $book_id = $book['book_id'];
        
        switch ($action) {
            case 'toggle_public':
            case 'bulk_toggle_public':
                return togglePublicStatus($conn, $seller_id, $book);
                
            case 'toggle_featured':
            case 'bulk_toggle_featured':
                return toggleFeaturedStatus($conn, $seller_id, $book);
                
            default:
                return ['success' => false, 'message' => 'Invalid action'];
        }
    } catch (Exception $e) {
        error_log("Toggle operation error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error occurred'];
    }
}

// Toggle public status
function togglePublicStatus($conn, $seller_id, $book) {
    $book_id = $book['book_id'];
    $current_status = $book['is_public'];
    $new_status = $current_status ? 0 : 1;
    
    // Validation for making book public
    if ($new_status == 1) {
        $warnings = [];
        
        if (empty($book['book_description'])) {
            $warnings[] = 'Consider adding a description';
        }
          if ($book['price'] <= 0) {
            $warnings[] = 'Please set a valid price';
        }
        
        if (!empty($warnings)) {
            logActivity($conn, $seller_id, "Attempted to make book public with warnings", $book_id, ['warnings' => $warnings]);
        }
    }
    
    // Update the status
    $stmt = $conn->prepare("UPDATE seller_books SET is_public = ?, updated_at = NOW() WHERE book_id = ? AND seller_id = ?");
    $stmt->bind_param("iii", $new_status, $book_id, $seller_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $status_text = $new_status ? 'public' : 'private';
        $action_text = "Changed book visibility to {$status_text}: '{$book['book_title']}' (ID: {$book_id})";
        
        logActivity($conn, $seller_id, $action_text, $book_id, [
            'previous_status' => $current_status,
            'new_status' => $new_status,
            'book_title' => $book['book_title']
        ]);
        
        $stmt->close();
        
        return [
            'success' => true,
            'message' => "Book is now {$status_text}",
            'new_status' => $new_status,
            'status_text' => $status_text,
            'warnings' => $warnings ?? []
        ];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to update visibility status'];
    }
}

// Toggle featured status
function toggleFeaturedStatus($conn, $seller_id, $book) {
    $book_id = $book['book_id'];
    $current_status = $book['is_featured'];
    $new_status = $current_status ? 0 : 1;
    
    // Check if book is public when trying to feature
    if ($new_status == 1 && !$book['is_public']) {
        return [
            'success' => false,
            'message' => 'Only public books can be featured',
            'suggestion' => 'make_public_first'
        ];
    }
    
    // Check featured book limit when trying to feature
    if ($new_status == 1) {
        $current_featured = checkFeaturedLimit($conn, $seller_id, $book_id);
        if ($current_featured >= MAX_FEATURED_BOOKS) {
            return [
                'success' => false,
                'message' => "You have reached the maximum limit of " . MAX_FEATURED_BOOKS . " featured books",
                'current_featured' => $current_featured,
                'max_featured' => MAX_FEATURED_BOOKS,
                'suggestion' => 'unfeature_other_books'
            ];
        }
    }
    
    // Update the status
    $stmt = $conn->prepare("UPDATE seller_books SET is_featured = ?, updated_at = NOW() WHERE book_id = ? AND seller_id = ?");
    $stmt->bind_param("iii", $new_status, $book_id, $seller_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $status_text = $new_status ? 'featured' : 'unfeatured';
        $action_text = "Changed book featured status to {$status_text}: '{$book['book_title']}' (ID: {$book_id})";
        
        logActivity($conn, $seller_id, $action_text, $book_id, [
            'previous_status' => $current_status,
            'new_status' => $new_status,
            'book_title' => $book['book_title']
        ]);
        
        $stmt->close();
        
        // Get updated stats
        $current_featured = checkFeaturedLimit($conn, $seller_id);
        
        return [
            'success' => true,
            'message' => "Book is now {$status_text}",
            'new_status' => $new_status,
            'status_text' => $status_text,
            'current_featured' => $current_featured,
            'max_featured' => MAX_FEATURED_BOOKS,
            'remaining_slots' => MAX_FEATURED_BOOKS - $current_featured
        ];
    } else {
        $stmt->close();
        return ['success' => false, 'message' => 'Failed to update featured status'];
    }
}

// Main execution starts here
try {
    // Check if user is logged in
    if (!isset($_SESSION['seller_id'])) {
        sendResponse(false, 'Authentication required. Please log in.', [], 401);
    }
    
    $seller_id = intval($_SESSION['seller_id']);
    $sellerName = $_SESSION['seller_name'] ?? 'Unknown';
    
    // Check rate limiting
    checkRateLimit($seller_id);
    
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method. Only POST requests are allowed.', [], 405);
    }
    
    // Validate CSRF token
    if (!validateCSRFToken()) {
        sendResponse(false, 'Invalid security token. Please refresh the page and try again.', [], 403);
    }
    
    // Get and validate input parameters
    $action = $_POST['action'] ?? '';
    $book_id = $_POST['book_id'] ?? null;
    $book_ids = $_POST['book_ids'] ?? null;
    
    // Handle bulk operations
    if (in_array($action, ['bulk_toggle_public', 'bulk_toggle_featured']) && is_array($book_ids)) {
        $bulk_result = handleBulkOperation($conn, $seller_id, $action, $book_ids);
        
        sendResponse(true, "Bulk operation completed: {$bulk_result['success_count']} successful, {$bulk_result['error_count']} failed", $bulk_result);
    }
    
    // Handle single book operations
    if (!$book_id) {
        sendResponse(false, 'Missing required parameter: book_id or book_ids', [], 400);
    }
    
    // Validate input
    $validation_errors = validateInput($book_id, $action);
    if (!empty($validation_errors)) {
        sendResponse(false, implode(', ', $validation_errors), [], 400);
    }
    
    $book_id = intval($book_id);
    
    // Get book information
    $book = getBookInfo($conn, $book_id, $seller_id);
    if (!$book) {
        sendResponse(false, 'Book not found or you do not have permission to modify it.', [], 404);
    }
    
    // Process the toggle operation
    $result = processSingleToggle($conn, $seller_id, $action, $book);
    
    if ($result['success']) {
        sendResponse(true, $result['message'], array_merge($result, [
            'book_id' => $book_id,
            'book_title' => $book['book_title']
        ]));
    } else {
        sendResponse(false, $result['message'], $result, 400);
    }
    
} catch (PDOException $e) {
    error_log("Database error in toggle flags: " . $e->getMessage());
    logActivity($conn, $seller_id ?? 0, "Database error during flag toggle: " . $e->getMessage(), $book_id ?? null);
    sendResponse(false, 'Database error occurred. Please try again.', [], 500);
} catch (Exception $e) {
    error_log("General error in toggle flags: " . $e->getMessage());
    logActivity($conn, $seller_id ?? 0, "System error during flag toggle: " . $e->getMessage(), $book_id ?? null);
    sendResponse(false, 'An unexpected error occurred. Please contact support if the problem persists.', [], 500);
} finally {
    // Ensure database connection is closed
    if (isset($conn)) {
        $conn->close();
    }
}
?>