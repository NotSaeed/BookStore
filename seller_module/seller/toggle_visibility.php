<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Set JSON response header
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Enhanced security and validation
function sendResponse($success = false, $message = '', $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
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

// Rate limiting (simple implementation)
function checkRateLimit($seller_id) {
    $key = "rate_limit_visibility_" . $seller_id;
    $limit = 10; // 10 requests per minute
    $window = 60; // 60 seconds
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    $_SESSION[$key]['count']++;
    
    if ($_SESSION[$key]['count'] > $limit) {
        sendResponse(false, 'Rate limit exceeded. Please try again later.');
    }
}

// Validation functions
function validateBookId($book_id) {
    return is_numeric($book_id) && $book_id > 0;
}

function validateVisibilityValue($visibility) {
    return in_array($visibility, [0, 1, '0', '1']);
}

// Log activity function
function logActivity($conn, $seller_id, $action, $book_id = null) {
    $log_stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, book_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    if ($log_stmt) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $log_stmt->bind_param("isiss", $seller_id, $action, $book_id, $ip, $user_agent);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// Get book information
function getBookInfo($conn, $book_id, $seller_id) {
    $stmt = $conn->prepare("SELECT title, is_public, is_featured FROM seller_books WHERE book_id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $book_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();
    return $book;
}

// Main execution starts here
try {
    // Check if user is logged in
    if (!isset($_SESSION['seller_id'])) {
        sendResponse(false, 'Authentication required. Please log in.');
    }

    $seller_id = intval($_SESSION['seller_id']);
    $sellerName = $_SESSION['seller_name'] ?? 'Unknown';

    // Check rate limiting
    checkRateLimit($seller_id);

    // Only accept POST requests for security
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(false, 'Invalid request method. Only POST requests are allowed.');
    }

    // Validate CSRF token for POST requests
    if (!validateCSRFToken()) {
        sendResponse(false, 'Invalid security token. Please refresh the page and try again.');
    }

    // Get and validate input parameters
    $book_id = $_POST['book_id'] ?? null;
    $is_public = $_POST['is_public'] ?? null;
    $batch_ids = $_POST['batch_ids'] ?? null; // For bulk operations

    if (!$book_id && !$batch_ids) {
        sendResponse(false, 'Missing required parameters: book_id or batch_ids.');
    }

    // Handle bulk operations
    if ($batch_ids && is_array($batch_ids)) {
        $results = [];
        $success_count = 0;
        $error_count = 0;
        
        foreach ($batch_ids as $id) {
            if (!validateBookId($id)) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Invalid book ID'];
                $error_count++;
                continue;
            }
            
            $id = intval($id);
            $book = getBookInfo($conn, $id, $seller_id);
            
            if (!$book) {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Book not found'];
                $error_count++;
                continue;
            }
            
            // Toggle visibility
            $new_visibility = $book['is_public'] ? 0 : 1;
            
            $stmt = $conn->prepare("UPDATE seller_books SET is_public = ?, updated_at = NOW() WHERE book_id = ? AND seller_id = ?");
            $stmt->bind_param("iii", $new_visibility, $id, $seller_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Log the activity
                $action = $new_visibility ? "Made book '{$book['title']}' public (ID: {$id})" : "Made book '{$book['title']}' private (ID: {$id})";
                logActivity($conn, $seller_id, $action, $id);
                
                $results[] = [
                    'id' => $id,
                    'success' => true,
                    'new_visibility' => $new_visibility,
                    'message' => $new_visibility ? 'Book made public' : 'Book made private'
                ];
                $success_count++;
            } else {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Failed to update visibility'];
                $error_count++;
            }
            
            $stmt->close();
        }
        
        sendResponse(true, "Bulk operation completed: {$success_count} successful, {$error_count} failed", [
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count
        ]);
    }

    // Handle single book operation
    if (!validateBookId($book_id)) {
        sendResponse(false, 'Invalid book ID provided.');
    }

    if ($is_public !== null && !validateVisibilityValue($is_public)) {
        sendResponse(false, 'Invalid visibility value. Must be 0 or 1.');
    }

    $book_id = intval($book_id);

    // Get current book information
    $book = getBookInfo($conn, $book_id, $seller_id);

    if (!$book) {
        sendResponse(false, 'Book not found or you do not have permission to modify it.');
    }

    // Determine new visibility state
    $new_visibility = $is_public !== null ? intval($is_public) : ($book['is_public'] ? 0 : 1);

    // Check if the visibility is already the desired state
    if ($book['is_public'] == $new_visibility) {        sendResponse(true, 'Book visibility is already set to ' . ($new_visibility ? 'public' : 'private'), [
            'book_id' => $book_id,
            'is_public' => $new_visibility,
            'book_title' => $book['title'],
            'no_change' => true
        ]);
    }

    // Prepare the update statement with timestamp
    $stmt = $conn->prepare("UPDATE seller_books SET is_public = ?, updated_at = NOW() WHERE book_id = ? AND seller_id = ?");
    $stmt->bind_param("iii", $new_visibility, $book_id, $seller_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Log the activity
            $visibility_text = $new_visibility ? 'public' : 'private';            $action = "Changed book visibility to {$visibility_text}: '{$book['title']}' (ID: {$book_id})";
            logActivity($conn, $seller_id, $action, $book_id);

            // Additional logic for public books            if ($new_visibility == 1) {
                // Check if book meets public visibility requirements
                $check_stmt = $conn->prepare("SELECT title, author, price, description FROM seller_books WHERE book_id = ? AND seller_id = ?");
                $check_stmt->bind_param("ii", $book_id, $seller_id);
                $check_stmt->execute();
                $book_details = $check_stmt->get_result()->fetch_assoc();
                $check_stmt->close();

                $warnings = [];
                if (empty($book_details['description'])) {
                    $warnings[] = 'Consider adding a description to attract more buyers';
                }                if ($book_details['price'] <= 0) {
                    $warnings[] = 'Please set a valid price for your book';
                }

                sendResponse(true, "Book '{$book['title']}' is now {$visibility_text}.", [
                    'book_id' => $book_id,
                    'is_public' => $new_visibility,
                    'book_title' => $book['title'],
                    'visibility_text' => $visibility_text,
                    'warnings' => $warnings,
                    'is_featured' => $book['is_featured']
                ]);
            } else {
                sendResponse(true, "Book '{$book['title']}' is now {$visibility_text}.", [
                    'book_id' => $book_id,
                    'is_public' => $new_visibility,
                    'book_title' => $book['title'],
                    'visibility_text' => $visibility_text,
                    'is_featured' => $book['is_featured']
                ]);
            }
        } else {
            sendResponse(false, 'No changes were made. The book may already have the requested visibility setting.');
        }
    } else {
        // Log the error
        $error_msg = "Failed to update book visibility: " . $conn->error;
        logActivity($conn, $seller_id, "Error: " . $error_msg, $book_id);
        sendResponse(false, 'Failed to update book visibility. Please try again.');
    }

    $stmt->close();

} catch (Exception $e) {
    // Log the exception
    error_log("Toggle Visibility Error: " . $e->getMessage());
    
    if (isset($seller_id)) {
        logActivity($conn, $seller_id, "System error during visibility toggle: " . $e->getMessage(), $book_id ?? null);
    }
    
    sendResponse(false, 'An unexpected error occurred. Please contact support if the problem persists.');
} finally {
    // Ensure database connection is closed
    if (isset($conn)) {
        $conn->close();
    }
}
?>