<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'] ?? 'Unknown Seller';
$businessName = $_SESSION['business_name'] ?? '';
$book_id = $_GET['id'] ?? null;

if (!$book_id || !is_numeric($book_id)) {
    showErrorPage('Invalid Request', 'Invalid book ID provided.');
    exit();
}

try {
    // First, get basic book details
    $stmt = $conn->prepare("SELECT * FROM seller_books WHERE book_id = ? AND seller_id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $book_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();

    if (!$book) {
        showErrorPage('Book Not Found', 'The requested book was not found or you do not have permission to view it.');
        exit();
    }

    // Get review statistics (optional tables)
    $avg_rating = 0;
    $review_count = 0;
    $reviews = [];
    
    // Check if reviews table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'seller_reviews'");
    if ($table_check && $table_check->num_rows > 0) {
        // Get review stats
        $review_stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM seller_reviews WHERE book_id = ?");
        if ($review_stmt) {
            $review_stmt->bind_param("i", $book_id);
            $review_stmt->execute();
            $review_result = $review_stmt->get_result()->fetch_assoc();
            $avg_rating = $review_result['avg_rating'] ?? 0;
            $review_count = $review_result['review_count'] ?? 0;
            $review_stmt->close();
            
            // Get recent reviews
            $reviews_stmt = $conn->prepare("SELECT sr.*, 'Anonymous' as customer_name FROM seller_reviews sr WHERE sr.book_id = ? ORDER BY sr.created_at DESC LIMIT 5");
            if ($reviews_stmt) {
                $reviews_stmt->bind_param("i", $book_id);
                $reviews_stmt->execute();
                $reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $reviews_stmt->close();
            }
        }
    }

    // Get sales statistics (optional tables)
    $total_sold = 0;
    $total_revenue = 0;
    $last_sold_date = null;
    
    $orders_check = $conn->query("SHOW TABLES LIKE 'seller_orders'");
    if ($orders_check && $orders_check->num_rows > 0) {
        $sales_stmt = $conn->prepare("SELECT SUM(quantity) as total_sold, SUM(quantity * price) as total_revenue, MAX(created_at) as last_sold FROM seller_orders WHERE book_id = ?");
        if ($sales_stmt) {
            $sales_stmt->bind_param("i", $book_id);
            $sales_stmt->execute();
            $sales_result = $sales_stmt->get_result()->fetch_assoc();
            $total_sold = $sales_result['total_sold'] ?? 0;
            $total_revenue = $sales_result['total_revenue'] ?? 0;
            $last_sold_date = $sales_result['last_sold'];
            $sales_stmt->close();
        }
    }

    // Get similar books
    $similar_stmt = $conn->prepare("SELECT book_id, title, author, price, cover_image FROM seller_books WHERE seller_id = ? AND book_id != ? AND (genre = ? OR author = ?) LIMIT 4");
    $similarBooks = [];
    if ($similar_stmt) {
        $similar_stmt->bind_param("iiss", $seller_id, $book_id, $book['genre'] ?? '', $book['author']);
        $similar_stmt->execute();
        $similarBooks = $similar_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $similar_stmt->close();
    }

    // Add calculated fields to book array
    $book['avg_rating'] = $avg_rating;
    $book['review_count'] = $review_count;
    $book['total_sold'] = $total_sold;
    $book['total_revenue'] = $total_revenue;
    $book['last_sold_date'] = $last_sold_date;
    $book['profit_margin_percent'] = ($book['cost_price'] > 0) ? round((($book['price'] - $book['cost_price']) / $book['cost_price']) * 100, 2) : 0;
    $book['genre_books_count'] = 1; // Default value
    
    // Log activity if table exists
    $activity_check = $conn->query("SHOW TABLES LIKE 'seller_activity_log'");
    if ($activity_check && $activity_check->num_rows > 0) {
        $log_stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
        if ($log_stmt) {
            $action = "Viewed book preview: " . $book['title'];
            $log_stmt->bind_param("is", $seller_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        }
    }

} catch (Exception $e) {
    error_log("Book preview error: " . $e->getMessage());
    showErrorPage('System Error', 'An error occurred while loading the book details.');
    exit();
}

$conn->close();

// Helper function to generate star rating HTML
function generateStarRating($rating, $maxStars = 5) {
    $html = '';
    $fullStars = floor($rating);
    $halfStar = $rating - $fullStars >= 0.5;
    
    for ($i = 1; $i <= $maxStars; $i++) {
        if ($i <= $fullStars) {
            $html .= '<i class="bi bi-star-fill text-warning"></i>';
        } elseif ($i == $fullStars + 1 && $halfStar) {
            $html .= '<i class="bi bi-star-half text-warning"></i>';
        } else {
            $html .= '<i class="bi bi-star text-muted"></i>';
        }
    }
    
    return $html;
}

// Helper function to show error pages
function showErrorPage($title, $message) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($title) ?> | BookStore</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
        <style>
            body { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                min-height: 100vh; 
                font-family: 'Inter', sans-serif;
            }
            .error-card { 
                border-radius: 20px; 
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1); 
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
            }
        </style>
    </head>
    <body class="d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="card error-card border-0">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 4rem;"></i>
                            <h3 class="mt-4 mb-3"><?= htmlspecialchars($title) ?></h3>
                            <p class="text-muted mb-4"><?= htmlspecialchars($message) ?></p>
                            <a href="seller_manage_books.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Books
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($book['title']) ?> | Book Preview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Preview of <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?>">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
        }
        
        .navbar {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand, .nav-link { color: white !important; font-weight: 600; }
        .nav-link:hover { color: rgba(255, 255, 255, 0.8) !important; }
        
        .preview-container { padding: 2rem 0; }
        
        .preview-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border: none;
        }
        
        .book-cover-container {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            margin-bottom: 2rem;
            background: #f8f9fa;
        }
        
        .book-cover {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        
        .no-cover-placeholder {
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .no-cover-placeholder i { font-size: 4rem; margin-bottom: 1rem; }
        
        .book-info { padding: 2rem; }
        
        .book-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }
        
        .book-author {
            font-size: 1.4rem;
            color: #667eea;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .price-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 1.5rem;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .rating-section {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(255, 193, 7, 0.1);
            border-radius: 15px;
            border-left: 4px solid #ffc107;
        }
        
        .rating-stars { font-size: 1.2rem; margin-right: 1rem; }
        .rating-text { color: #495057; font-weight: 600; }
        
        .book-description {
            color: #495057;
            margin-bottom: 2rem;
            line-height: 1.8;
            font-size: 1.1rem;
            padding: 1.5rem;
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }
        
        .book-meta {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .meta-icon {
            font-size: 1.5rem;
            color: #667eea;
            margin-right: 1rem;
            width: 40px;
            text-align: center;
        }
        
        .meta-label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 0.25rem;
        }
        
        .meta-value {
            color: #495057;
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        .btn-enhanced {
            padding: 0.75rem 2rem;
            border-radius: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            border: none;
            margin-right: 1rem;
            margin-bottom: 1rem;
        }
        
        .btn-enhanced:hover { transform: translateY(-3px); }
        
        .btn-back { background: #6c757d; color: white; }
        .btn-edit { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-share { background: #17a2b8; color: white; }
        
        .performance-metrics {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }
        
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .metric-number {
            font-size: 2rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
        }
        
        .qr-section {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            margin-bottom: 2rem;
        }
        
        .qr-code {
            display: inline-block;
            padding: 1rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .share-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .share-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .share-btn:hover { transform: translateY(-2px); text-decoration: none; }
        
        .share-facebook { background: #3b5998; color: white; }
        .share-twitter { background: #1da1f2; color: white; }
        .share-whatsapp { background: #25d366; color: white; }
        .share-email { background: #6c757d; color: white; }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .status-public { background: #28a745; color: white; }
        .status-private { background: #6c757d; color: white; }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            margin-right: 12px;
        }
        
        @media (max-width: 768px) {
            .book-title { font-size: 2rem; }
            .book-author { font-size: 1.2rem; }
            .meta-grid { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="seller_dashboard.php">
            <i class="bi bi-shop me-2"></i>BookStore Seller Hub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="seller_dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="seller_manage_books.php">
                        <i class="bi bi-books me-1"></i>My Books
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_add_book.php">
                        <i class="bi bi-plus-circle me-1"></i>Add Book
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <div class="avatar">
                            <?= strtoupper(substr($sellerName, 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($sellerName) ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="seller_settings.php">Settings</a></li>
                        <li><a class="dropdown-item" href="seller_logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="preview-container">
    <div class="container">
        <!-- Main Book Preview Card -->
        <div class="preview-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h2 class="mb-0">
                    <i class="bi bi-book me-2"></i>Book Preview
                </h2>
                <span class="status-badge <?= isset($book['is_public']) && $book['is_public'] ? 'status-public' : 'status-private' ?>">
                    <i class="bi <?= isset($book['is_public']) && $book['is_public'] ? 'bi-eye' : 'bi-eye-slash' ?> me-2"></i>
                    <?= isset($book['is_public']) && $book['is_public'] ? 'Public' : 'Private' ?>
                </span>
            </div>
            
            <div class="row g-0">
                <div class="col-lg-4">
                    <div class="p-4">
                        <!-- Book Cover -->
                        <?php if (!empty($book['cover_image']) && file_exists($book['cover_image'])): ?>
                            <div class="book-cover-container">
                                <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="<?= htmlspecialchars($book['title']) ?>" class="book-cover">
                            </div>
                        <?php else: ?>
                            <div class="book-cover-container">
                                <div class="no-cover-placeholder">
                                    <i class="bi bi-book"></i>
                                    <span>No Cover Available</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- QR Code Section -->
                        <div class="qr-section">
                            <h6 class="mb-3"><i class="bi bi-qr-code me-2"></i>Share This Book</h6>
                            <div class="qr-code">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" 
                                     alt="QR Code">
                            </div>
                            <p class="text-muted mb-3">Scan to view this book</p>
                            
                            <!-- Share Buttons -->
                            <div class="share-buttons">
                                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" 
                                   target="_blank" class="share-btn share-facebook">
                                    <i class="bi bi-facebook me-1"></i>Facebook
                                </a>
                                <a href="https://twitter.com/intent/tweet?text=<?= urlencode($book['title'] . ' by ' . $book['author']) ?>&url=<?= urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" 
                                   target="_blank" class="share-btn share-twitter">
                                    <i class="bi bi-twitter me-1"></i>Twitter
                                </a>
                                <a href="https://wa.me/?text=<?= urlencode('Check out this book: ' . $book['title'] . ' by ' . $book['author'] . ' - http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" 
                                   target="_blank" class="share-btn share-whatsapp">
                                    <i class="bi bi-whatsapp me-1"></i>WhatsApp
                                </a>
                                <a href="mailto:?subject=<?= urlencode($book['title'] . ' - Book Recommendation') ?>&body=<?= urlencode('Check out this book: ' . $book['title'] . ' by ' . $book['author'] . ' - http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>" 
                                   class="share-btn share-email">
                                    <i class="bi bi-envelope me-1"></i>Email
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-8">
                    <div class="book-info">
                        <!-- Book Title & Author -->
                        <h1 class="book-title"><?= htmlspecialchars($book['title']) ?></h1>
                        <p class="book-author">by <?= htmlspecialchars($book['author']) ?></p>
                        
                        <!-- Price -->
                        <div class="price-badge">RM <?= number_format($book['price'], 2) ?></div>
                        
                        <!-- Rating Section -->
                        <div class="rating-section">
                            <div class="rating-stars">
                                <?= generateStarRating($book['avg_rating']) ?>
                            </div>
                            <div class="rating-text">
                                <?php if ($book['review_count'] > 0): ?>
                                    <strong><?= number_format($book['avg_rating'], 1) ?></strong> out of 5 
                                    (<?= $book['review_count'] ?> <?= $book['review_count'] == 1 ? 'review' : 'reviews' ?>)
                                <?php else: ?>
                                    <span class="text-muted">No reviews yet</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <?php if (!empty($book['description'])): ?>
                            <div class="book-description">
                                <h5><i class="bi bi-card-text me-2"></i>Description</h5>
                                <?= nl2br(htmlspecialchars($book['description'])) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap">
                            <a href="seller_manage_books.php" class="btn btn-enhanced btn-back">
                                <i class="bi bi-arrow-left me-2"></i>Back to List
                            </a>
                            <a href="seller_edit_book.php?id=<?= $book['book_id'] ?>" class="btn btn-enhanced btn-edit">
                                <i class="bi bi-pencil me-2"></i>Edit Book
                            </a>
                            <button class="btn btn-enhanced btn-share" onclick="shareBook()">
                                <i class="bi bi-share me-2"></i>Share
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Book Metadata -->
        <div class="book-meta">
            <h4 class="mb-4"><i class="bi bi-info-circle me-2"></i>Book Details</h4>
            <div class="meta-grid">
                <div class="meta-item">
                    <i class="bi bi-hash meta-icon"></i>
                    <div class="meta-content">
                        <div class="meta-label">Book ID</div>
                        <div class="meta-value">#<?= $book['book_id'] ?></div>
                    </div>
                </div>
                
                <?php if (!empty($book['isbn'])): ?>
                <div class="meta-item">
                    <i class="bi bi-upc meta-icon"></i>
                    <div class="meta-content">
                        <div class="meta-label">ISBN</div>
                        <div class="meta-value"><?= htmlspecialchars($book['isbn']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($book['genre'])): ?>
                <div class="meta-item">
                    <i class="bi bi-tags meta-icon"></i>
                    <div class="meta-content">
                        <div class="meta-label">Genre</div>
                        <div class="meta-value"><?= htmlspecialchars($book['genre']) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($book['condition_type'])): ?>
                <div class="meta-item">
                    <i class="bi bi-shield-check meta-icon"></i>
                    <div class="meta-content">
                        <div class="meta-label">Condition</div>
                        <div class="meta-value"><?= ucwords(str_replace('_', ' ', $book['condition_type'])) ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($book['stock_quantity'])): ?>
                <div class="meta-item">
                    <i class="bi bi-boxes meta-icon"></i>
                    <div class="meta-content">
                        <div class="meta-label">Stock</div>
                        <div class="meta-value"><?= $book['stock_quantity'] ?> units</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="meta-item">
                    <i class="bi bi-calendar-plus meta-icon"></i>
                    <div class="meta-content">
                        <div class="meta-label">Added On</div>
                        <div class="meta-value"><?= date("F j, Y", strtotime($book['created_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Metrics -->
        <div class="performance-metrics">
            <h4 class="mb-4"><i class="bi bi-graph-up me-2"></i>Performance Metrics</h4>
            <div class="metric-grid">
                <div class="metric-card">
                    <div class="metric-number"><?= $book['total_sold'] ?></div>
                    <div class="metric-label">Total Sold</div>
                </div>
                <div class="metric-card">
                    <div class="metric-number">RM <?= number_format($book['total_revenue'], 2) ?></div>
                    <div class="metric-label">Total Revenue</div>
                </div>
                <div class="metric-card">
                    <div class="metric-number"><?= number_format($book['avg_rating'], 1) ?></div>
                    <div class="metric-label">Average Rating</div>
                </div>
                <div class="metric-card">
                    <div class="metric-number"><?= $book['review_count'] ?></div>
                    <div class="metric-label">Reviews</div>
                </div>
            </div>
        </div>

        <!-- Recent Reviews -->
        <?php if (!empty($reviews)): ?>
        <div class="preview-card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-chat-left-text me-2"></i>Recent Reviews</h4>
            </div>
            <div class="p-4">
                <?php foreach ($reviews as $review): ?>
                <div class="mb-3 p-3 bg-light rounded">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <strong><?= htmlspecialchars($review['customer_name']) ?></strong>
                            <div class="d-inline-block ms-2">
                                <?= generateStarRating($review['rating']) ?>
                            </div>
                        </div>
                        <small class="text-muted"><?= date('M j, Y', strtotime($review['created_at'])) ?></small>
                    </div>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Similar Books -->
        <?php if (!empty($similarBooks)): ?>
        <div class="preview-card">
            <div class="card-header">
                <h4 class="mb-0"><i class="bi bi-collection me-2"></i>Similar Books</h4>
            </div>
            <div class="p-4">
                <div class="row">
                    <?php foreach ($similarBooks as $similar): ?>
                    <div class="col-md-3 mb-3">
                        <div class="card h-100">
                            <?php if (!empty($similar['cover_image']) && file_exists($similar['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($similar['cover_image']) ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                            <?php else: ?>
                                <div class="card-img-top d-flex align-items-center justify-content-center" style="height: 200px; background: #f8f9fa;">
                                    <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars(substr($similar['title'], 0, 30)) ?><?= strlen($similar['title']) > 30 ? '...' : '' ?></h6>
                                <p class="card-text text-muted small"><?= htmlspecialchars($similar['author']) ?></p>
                                <p class="card-text text-success fw-bold">RM <?= number_format($similar['price'], 2) ?></p>
                                <a href="book_preview.php?id=<?= $similar['book_id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function shareBook() {
    if (navigator.share) {
        navigator.share({
            title: '<?= htmlspecialchars($book['title']) ?>',
            text: 'Check out this book: <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?>',
            url: window.location.href
        }).catch(console.error);
    } else {
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('Book link copied to clipboard!');
        }).catch(() => {
            alert('Book URL: ' + window.location.href);
        });
    }
}
</script>

</body>
</html>