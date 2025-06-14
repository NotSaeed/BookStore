<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Set JSON response header with security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Enhanced response function
function sendResponse($success = false, $message = '', $data = [], $httpCode = 200) {
    http_response_code($httpCode);
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
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    $token = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? null;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $token = $_GET['csrf_token'] ?? null;
    }
    
    return $token && hash_equals($_SESSION['csrf_token'], $token);
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting function
function checkRateLimit($seller_id) {
    $key = "rate_limit_featured_" . $seller_id;
    $limit = 15; // 15 requests per minute
    $window = 60; // 60 seconds
    
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

// Validation functions
function validateBookId($book_id) {
    return is_numeric($book_id) && $book_id > 0;
}

function validateFeaturedValue($featured) {
    return in_array($featured, [0, 1, '0', '1']);
}

// Log activity function with enhanced details
function logActivity($conn, $seller_id, $action, $book_id = null, $details = []) {
    try {
        $log_stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, book_id, ip_address, user_agent, details) VALUES (?, ?, ?, ?, ?, ?)");
        if ($log_stmt) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $details_json = json_encode($details);
            $log_stmt->bind_param("isssss", $seller_id, $action, $book_id, $ip, $user_agent, $details_json);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Get comprehensive book information
function getBookInfo($conn, $book_id, $seller_id) {    $stmt = $conn->prepare("
        SELECT book_id, title, author, is_featured, is_public, 
               price, created_at, updated_at, view_count, sales_count
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

// Check featured book limits
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

// Get seller plan limits
function getSellerLimits($conn, $seller_id) {
    // Default limits - could be extended with seller plans/tiers
    return [
        'max_featured_books' => 10, // Maximum featured books allowed
        'featured_duration_days' => 30, // How long books stay featured
        'featured_boost_multiplier' => 2.5 // Featured books get 2.5x more visibility
    ];
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

    // Validate CSRF token
    if (!validateCSRFToken()) {
        sendResponse(false, 'Invalid security token. Please refresh the page and try again.', [], 403);
    }

    // Get and validate input parameters
    $book_id = null;
    $is_featured = null;
    $batch_ids = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $book_id = $_POST['book_id'] ?? null;
        $is_featured = $_POST['is_featured'] ?? null;
        $batch_ids = $_POST['batch_ids'] ?? null;
    } else {
        $book_id = $_GET['id'] ?? null;
        $is_featured = $_GET['featured'] ?? null;
    }

    // Handle bulk operations
    if ($batch_ids && is_array($batch_ids)) {
        $results = [];
        $success_count = 0;
        $error_count = 0;
        $limits = getSellerLimits($conn, $seller_id);
        $current_featured = checkFeaturedLimit($conn, $seller_id);
        
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

            // Check if trying to feature and already at limit
            $new_featured = $book['is_featured'] ? 0 : 1;
            if ($new_featured == 1 && $current_featured >= $limits['max_featured_books']) {
                $results[] = [
                    'id' => $id, 
                    'success' => false, 
                    'message' => "Featured book limit reached ({$limits['max_featured_books']} max)"
                ];
                $error_count++;
                continue;
            }

            // Update featured status
            $stmt = $conn->prepare("UPDATE seller_books SET is_featured = ?, updated_at = NOW() WHERE book_id = ? AND seller_id = ?");
            $stmt->bind_param("iii", $new_featured, $id, $seller_id);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                if ($new_featured == 1) $current_featured++;
                else $current_featured--;

                // Log the activity                $action = $new_featured ? 
                    "Featured book: '{$book['title']}' (ID: {$id})" : 
                    "Unfeatured book: '{$book['title']}' (ID: {$id})";
                
                logActivity($conn, $seller_id, $action, $id, [
                    'previous_featured' => $book['is_featured'],
                    'new_featured' => $new_featured,
                    'operation' => 'bulk_toggle'
                ]);
                
                $results[] = [
                    'id' => $id,
                    'success' => true,
                    'new_featured' => $new_featured,
                    'book_title' => $book['title'],
                    'message' => $new_featured ? 'Book featured successfully' : 'Book unfeatured successfully'
                ];
                $success_count++;
            } else {
                $results[] = ['id' => $id, 'success' => false, 'message' => 'Failed to update featured status'];
                $error_count++;
            }
            
            $stmt->close();
        }
        
        sendResponse(true, "Bulk operation completed: {$success_count} successful, {$error_count} failed", [
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'current_featured_count' => $current_featured,
            'featured_limit' => $limits['max_featured_books']
        ]);
    }

    // Validate single book parameters
    if (!$book_id) {
        sendResponse(false, 'Missing required parameter: book_id or batch_ids.', [], 400);
    }

    if (!validateBookId($book_id)) {
        sendResponse(false, 'Invalid book ID provided.', [], 400);
    }

    if ($is_featured !== null && !validateFeaturedValue($is_featured)) {
        sendResponse(false, 'Invalid featured value. Must be 0 or 1.', [], 400);
    }

    $book_id = intval($book_id);

    // Get current book information
    $book = getBookInfo($conn, $book_id, $seller_id);
    if (!$book) {
        sendResponse(false, 'Book not found or you do not have permission to modify it.', [], 404);
    }

    // Check if book is public when trying to feature
    if (!$book['is_public'] && ($is_featured === null ? !$book['is_featured'] : intval($is_featured))) {
        sendResponse(false, 'Only public books can be featured. Please make the book public first.', [
            'book_id' => $book_id,
            'is_public' => $book['is_public'],
            'suggested_action' => 'make_public'
        ], 400);
    }

    // Determine new featured state
    $new_featured = $is_featured !== null ? intval($is_featured) : ($book['is_featured'] ? 0 : 1);

    // Check if already in desired state
    if ($book['is_featured'] == $new_featured) {        sendResponse(true, 'Book featured status is already set to ' . ($new_featured ? 'featured' : 'not featured'), [
            'book_id' => $book_id,
            'is_featured' => $new_featured,
            'book_title' => $book['title'],
            'no_change' => true
        ]);
    }

    // Check featured book limits when trying to feature
    $limits = getSellerLimits($conn, $seller_id);
    if ($new_featured == 1) {
        $current_featured = checkFeaturedLimit($conn, $seller_id, $book_id);
        if ($current_featured >= $limits['max_featured_books']) {
            sendResponse(false, "You have reached the maximum limit of {$limits['max_featured_books']} featured books. Please unfeature other books first.", [
                'current_featured' => $current_featured,
                'max_featured' => $limits['max_featured_books'],
                'suggested_action' => 'manage_featured_books'
            ], 400);
        }
    }

    // Update the featured status
    $stmt = $conn->prepare("UPDATE seller_books SET is_featured = ?, updated_at = NOW() WHERE book_id = ? AND seller_id = ?");
    $stmt->bind_param("iii", $new_featured, $book_id, $seller_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Log the activity            $featured_text = $new_featured ? 'featured' : 'unfeatured';
            $action = "Changed book featured status to {$featured_text}: '{$book['title']}' (ID: {$book_id})";
              logActivity($conn, $seller_id, $action, $book_id, [
                'previous_featured' => $book['is_featured'],
                'new_featured' => $new_featured,
                'price' => $book['price'],
                'is_public' => $book['is_public']
            ]);

            // Get updated stats
            $current_featured = checkFeaturedLimit($conn, $seller_id);
              $response_data = [
                'book_id' => $book_id,
                'is_featured' => $new_featured,
                'book_title' => $book['title'],
                'featured_text' => $featured_text,
                'current_featured_count' => $current_featured,
                'featured_limit' => $limits['max_featured_books'],
                'remaining_featured_slots' => $limits['max_featured_books'] - $current_featured
            ];

            // Add tips for featured books
            if ($new_featured == 1) {
                $response_data['tips'] = [
                    'Featured books get higher visibility in search results',
                    'Featured books appear in recommendation sections',
                    'Consider updating your book description to maximize impact',
                    'Featured status helps with seasonal promotions'
                ];
                $response_data['benefits'] = [
                    'visibility_boost' => $limits['featured_boost_multiplier'] . 'x more visibility',
                    'duration' => "Featured for up to {$limits['featured_duration_days']} days",
                    'placement' => 'Priority placement in search results'
                ];
            }

            sendResponse(true, "Book '{$book['title']}' is now {$featured_text}.", $response_data);
        } else {
            sendResponse(false, 'No changes were made. The book may already have the requested featured status.', [], 409);
        }
    } else {
        // Log the error
        $error_msg = "Failed to update book featured status: " . $conn->error;
        logActivity($conn, $seller_id, "Error: " . $error_msg, $book_id);
        sendResponse(false, 'Failed to update book featured status. Please try again.', [], 500);
    }

    $stmt->close();

} catch (Exception $e) {
    // Log the exception
    error_log("Toggle Featured Error: " . $e->getMessage());
    
    if (isset($seller_id)) {
        logActivity($conn, $seller_id, "System error during featured toggle: " . $e->getMessage(), $book_id ?? null);
    }
    
    sendResponse(false, 'An unexpected error occurred. Please contact support if the problem persists.', [], 500);
} finally {
    // Ensure database connection is closed
    if (isset($conn)) {
        $conn->close();
    }
}
?>