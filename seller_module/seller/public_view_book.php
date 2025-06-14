<?php

require_once __DIR__ . '/includes/seller_db.php';

$book_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$book_id) {
    header("HTTP/1.0 404 Not Found");
    die("Invalid book ID provided.");
}

try {
    // Get comprehensive book details with seller information
    $book_query = "SELECT 
                      sb.book_id, sb.title, sb.author, sb.isbn, sb.genre, sb.condition_type,
                      sb.price, sb.cost_price, sb.stock_quantity, sb.description, sb.cover_image,
                      sb.created_at, sb.updated_at,
                      su.seller_name, su.business_name, su.seller_email,
                      COALESCE(AVG(sr.rating), 0) as avg_rating,
                      COUNT(sr.review_id) as review_count,
                      COALESCE(SUM(so.quantity), 0) as total_sold
                   FROM seller_books sb
                   LEFT JOIN seller_users su ON sb.seller_id = su.seller_id
                   LEFT JOIN seller_reviews sr ON sb.book_id = sr.book_id
                   LEFT JOIN seller_orders so ON sb.book_id = so.book_id
                   WHERE sb.book_id = ? AND sb.stock_quantity > 0
                   GROUP BY sb.book_id";

    $stmt = $conn->prepare($book_query);
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();

    if (!$book) {
        header("HTTP/1.0 404 Not Found");
        die("Book not found or currently unavailable.");
    }

    // Get recent reviews
    $reviews_query = "SELECT sr.rating, sr.review_text, sr.created_at, cu.name as reviewer_name 
                      FROM seller_reviews sr 
                      LEFT JOIN customer_users cu ON sr.customer_id = cu.customer_id 
                      WHERE sr.book_id = ? 
                      ORDER BY sr.created_at DESC 
                      LIMIT 5";
    
    $reviews_stmt = $conn->prepare($reviews_query);
    $reviews = [];
    if ($reviews_stmt) {
        $reviews_stmt->bind_param("i", $book_id);
        $reviews_stmt->execute();
        $reviews_result = $reviews_stmt->get_result();
        $reviews = $reviews_result->fetch_all(MYSQLI_ASSOC);
        $reviews_stmt->close();
    }

    // Get related books from same genre or author
    $related_query = "SELECT book_id, title, author, price, cover_image, genre 
                      FROM seller_books 
                      WHERE book_id != ? 
                      AND (genre = ? OR author = ?) 
                      AND stock_quantity > 0 
                      ORDER BY RAND() 
                      LIMIT 4";
    
    $related_stmt = $conn->prepare($related_query);
    $related_books = [];
    if ($related_stmt) {
        $related_stmt->bind_param("iss", $book_id, $book['genre'], $book['author']);
        $related_stmt->execute();
        $related_result = $related_stmt->get_result();
        $related_books = $related_result->fetch_all(MYSQLI_ASSOC);
        $related_stmt->close();
    }

} catch (Exception $e) {
    error_log("Public view book error: " . $e->getMessage());
    die("An error occurred while loading the book details.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?> | BookStore</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= htmlspecialchars(substr($book['description'] ?? '', 0, 160)) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($book['title']) ?>, <?= htmlspecialchars($book['author']) ?>, <?= htmlspecialchars($book['genre'] ?? '') ?>, books, bookstore">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(substr($book['description'] ?? '', 0, 300)) ?>">
    <?php if ($book['cover_image']): ?>
    <meta property="og:image" content="<?= htmlspecialchars($book['cover_image']) ?>">
    <?php endif; ?>
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0 2rem 0;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="book-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" x="2" y="2" width="16" height="16"/><circle fill="rgba(255,255,255,0.03)" cx="10" cy="10" r="2"/></pattern></defs><rect width="100" height="100" fill="url(%23book-pattern)"/></svg>') repeat;
            opacity: 0.3;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .book-card {
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: none;
            transition: all 0.3s ease;
            margin-top: -100px;
            position: relative;
            z-index: 3;
        }
        
        .book-image {
            max-height: 500px;
            object-fit: cover;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .book-image:hover {
            transform: scale(1.02);
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
        }
        
        .book-details {
            padding: 2.5rem;
        }
        
        .book-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .book-author {
            font-size: 1.3rem;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .price-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .price-main {
            font-size: 2.5rem;
            font-weight: 800;
            color: #667eea;
            margin: 0;
        }
        
        .price-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
            margin: 0.25rem;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }
        
        .info-badge i {
            margin-right: 0.5rem;
        }
        
        .condition-badge {
            font-size: 1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .condition-new { background: linear-gradient(135deg, #51cf66 0%, #40c057 100%); color: white; }
        .condition-like_new { background: linear-gradient(135deg, #69db7c 0%, #51cf66 100%); color: white; }
        .condition-very_good { background: linear-gradient(135deg, #74c0fc 0%, #339af0 100%); color: white; }
        .condition-good { background: linear-gradient(135deg, #ffd43b 0%, #ffc107 100%); color: #333; }
        .condition-acceptable { background: linear-gradient(135deg, #ff8787 0%, #ff6b6b 100%); color: white; }
        
        .rating-display {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .stars {
            color: #ffc107;
            font-size: 1.5rem;
            margin-right: 0.75rem;
        }
        
        .rating-text {
            font-weight: 600;
            color: #2d3748;
        }
        
        .rating-count {
            color: #6c757d;
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }
        
        .description-section {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 2rem;
            margin: 2rem 0;
            border-left: 4px solid #667eea;
        }
        
        .description-text {
            font-size: 1.1rem;
            line-height: 1.8;
            color: #2d3748;
            margin: 0;
        }
        
        .seller-info {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .seller-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .seller-title i {
            margin-right: 0.75rem;
            color: #667eea;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-gradient:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-gradient {
            background: transparent;
            border: 2px solid #667eea;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            color: #667eea;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-outline-gradient:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .reviews-section {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin: 3rem 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .section-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 1rem;
            color: #667eea;
        }
        
        .review-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid #667eea;
        }
        
        .review-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .reviewer-name {
            font-weight: 600;
            color: #2d3748;
        }
        
        .review-date {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .review-stars {
            color: #ffc107;
            margin-bottom: 0.5rem;
        }
        
        .review-text {
            color: #2d3748;
            line-height: 1.6;
            font-style: italic;
        }
        
        .related-books {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            margin: 3rem 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .related-book-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
        }
        
        .related-book-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            border-color: #667eea;
            color: inherit;
        }
        
        .related-book-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 1rem;
        }
        
        .related-book-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .related-book-author {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .related-book-price {
            color: #667eea;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .stock-indicator {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .stock-high {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }
        
        .stock-medium {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.2);
        }
        
        .stock-low {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 1rem 0;
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            content: "›";
            color: rgba(255, 255, 255, 0.7);
        }
        
        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .breadcrumb-item a:hover {
            color: white;
        }
        
        .breadcrumb-item.active {
            color: white;
        }
        
        .back-btn {
            position: fixed;
            top: 30px;
            left: 30px;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.3);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 0.75rem 1.5rem;
            color: #667eea;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 0 1rem 0;
            }
            
            .book-card {
                margin-top: -50px;
            }
            
            .book-title {
                font-size: 2rem;
            }
            
            .price-main {
                font-size: 2rem;
            }
            
            .book-details {
                padding: 1.5rem;
            }
            
            .back-btn {
                top: 15px;
                left: 15px;
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        /* Animation classes */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        
        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <a href="javascript:history.back()" class="back-btn">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
    
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <div class="container">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="../books.php">Books</a></li>
                        <?php if ($book['genre']): ?>
                        <li class="breadcrumb-item"><a href="../books.php?genre=<?= urlencode($book['genre']) ?>"><?= htmlspecialchars($book['genre']) ?></a></li>
                        <?php endif; ?>
                        <li class="breadcrumb-item active"><?= htmlspecialchars($book['title']) ?></li>
                    </ol>
                </nav>
                
                <div class="text-center">
                    <h1 class="display-4 fw-bold mb-2">Book Details</h1>
                    <p class="lead mb-0">Discover your next great read</p>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <!-- Main Book Card -->
        <div class="book-card fade-in">
            <div class="row g-0">
                <?php if ($book['cover_image'] && file_exists($book['cover_image'])): ?>
                <div class="col-lg-5">
                    <div class="p-4">
                        <img src="<?= htmlspecialchars($book['cover_image']) ?>" 
                             class="book-image w-100" 
                             alt="<?= htmlspecialchars($book['title']) ?> cover"
                             loading="lazy">
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="<?= $book['cover_image'] ? 'col-lg-7' : 'col-12' ?>">
                    <div class="book-details">
                        <!-- Title & Author -->
                        <h1 class="book-title"><?= htmlspecialchars($book['title']) ?></h1>
                        <div class="book-author">by <?= htmlspecialchars($book['author']) ?></div>
                        
                        <!-- Rating Section -->
                        <?php if ($book['avg_rating'] > 0): ?>
                        <div class="rating-display">
                            <div class="stars">
                                <?php 
                                $rating = round($book['avg_rating']);
                                for ($i = 1; $i <= 5; $i++) {
                                    echo $i <= $rating ? '★' : '☆';
                                }
                                ?>
                            </div>
                            <span class="rating-text"><?= number_format($book['avg_rating'], 1) ?>/5</span>
                            <span class="rating-count">(<?= $book['review_count'] ?> review<?= $book['review_count'] != 1 ? 's' : '' ?>)</span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Price Section -->
                        <div class="price-section">
                            <div class="price-label">Price</div>
                            <div class="price-main">RM <?= number_format($book['price'], 2) ?></div>
                        </div>
                        
                        <!-- Book Information -->
                        <div class="mb-4">
                            <?php if ($book['isbn']): ?>
                            <span class="info-badge">
                                <i class="bi bi-upc-scan"></i>ISBN: <?= htmlspecialchars($book['isbn']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if ($book['genre']): ?>
                            <span class="info-badge">
                                <i class="bi bi-tag"></i><?= htmlspecialchars($book['genre']) ?>
                            </span>
                            <?php endif; ?>
                            
                            <span class="info-badge">
                                <i class="bi bi-calendar-plus"></i>Listed <?= date('M j, Y', strtotime($book['created_at'])) ?>
                            </span>
                            
                            <?php if ($book['total_sold'] > 0): ?>
                            <span class="info-badge">
                                <i class="bi bi-graph-up"></i><?= $book['total_sold'] ?> sold
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Condition & Stock -->
                        <div class="d-flex flex-wrap align-items-center mb-4 gap-3">
                            <div class="condition-badge condition-<?= $book['condition_type'] ?>">
                                <?= ucwords(str_replace('_', ' ', $book['condition_type'])) ?> Condition
                            </div>
                            
                            <div class="stock-indicator <?= $book['stock_quantity'] > 10 ? 'stock-high' : ($book['stock_quantity'] > 3 ? 'stock-medium' : 'stock-low') ?>">
                                <i class="bi bi-box me-2"></i>
                                <?= $book['stock_quantity'] ?> in stock
                                <?php if ($book['stock_quantity'] <= 3): ?>
                                    - Hurry, only few left!
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap gap-3 mb-4">
                            <a href="mailto:<?= htmlspecialchars($book['seller_email']) ?>?subject=Inquiry about <?= urlencode($book['title']) ?>&body=Hi, I'm interested in purchasing the book '<?= urlencode($book['title']) ?>' by <?= urlencode($book['author']) ?>. Please let me know about availability and purchase process." 
                               class="btn-gradient">
                                <i class="bi bi-envelope me-2"></i>Contact Seller
                            </a>
                            
                            <button class="btn-outline-gradient" onclick="shareBook()">
                                <i class="bi bi-share me-2"></i>Share Book
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Description Section -->
        <?php if (!empty($book['description'])): ?>
        <div class="description-section fade-in">
            <h3 class="section-title">
                <i class="bi bi-journal-text"></i>Description
            </h3>
            <p class="description-text"><?= nl2br(htmlspecialchars($book['description'])) ?></p>
        </div>
        <?php endif; ?>

        <!-- Seller Information -->
        <div class="seller-info fade-in">
            <h3 class="seller-title">
                <i class="bi bi-shop"></i>Seller Information
            </h3>
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2">
                        <strong>Seller:</strong> <?= htmlspecialchars($book['seller_name']) ?>
                    </p>
                    <?php if ($book['business_name']): ?>
                    <p class="mb-2">
                        <strong>Business:</strong> <?= htmlspecialchars($book['business_name']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <p class="mb-2">
                        <strong>Contact:</strong> 
                        <a href="mailto:<?= htmlspecialchars($book['seller_email']) ?>" class="text-decoration-none">
                            <?= htmlspecialchars($book['seller_email']) ?>
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <?php if (!empty($reviews)): ?>
        <div class="reviews-section fade-in">
            <h3 class="section-title">
                <i class="bi bi-chat-dots"></i>Customer Reviews
            </h3>
            
            <?php foreach ($reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="reviewer-name"><?= htmlspecialchars($review['reviewer_name'] ?? 'Anonymous') ?></div>
                    <div class="review-date"><?= date('M j, Y', strtotime($review['created_at'])) ?></div>
                </div>
                <div class="review-stars">
                    <?php for ($i = 1; $i <= 5; $i++) echo $i <= $review['rating'] ? '★' : '☆'; ?>
                </div>
                <?php if (!empty($review['review_text'])): ?>
                <div class="review-text">"<?= htmlspecialchars($review['review_text']) ?>"</div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Related Books -->
        <?php if (!empty($related_books)): ?>
        <div class="related-books fade-in">
            <h3 class="section-title">
                <i class="bi bi-collection"></i>You Might Also Like
            </h3>
            
            <div class="row">
                <?php foreach ($related_books as $related): ?>
                <div class="col-md-3 col-sm-6 mb-4">
                    <a href="public_view_book.php?id=<?= $related['book_id'] ?>" class="related-book-card d-block">
                        <?php if ($related['cover_image'] && file_exists($related['cover_image'])): ?>
                        <img src="<?= htmlspecialchars($related['cover_image']) ?>" 
                             class="related-book-image" 
                             alt="<?= htmlspecialchars($related['title']) ?>"
                             loading="lazy">
                        <?php else: ?>
                        <div class="related-book-image d-flex align-items-center justify-content-center bg-light">
                            <i class="bi bi-book" style="font-size: 3rem; color: #ccc;"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="related-book-title"><?= htmlspecialchars($related['title']) ?></div>
                        <div class="related-book-author">by <?= htmlspecialchars($related['author']) ?></div>
                        <div class="related-book-price">RM <?= number_format($related['price'], 2) ?></div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fade in animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Share book function
        function shareBook() {
            const title = '<?= addslashes($book['title']) ?>';
            const author = '<?= addslashes($book['author']) ?>';
            const url = window.location.href;
            
            if (navigator.share) {
                navigator.share({
                    title: `${title} by ${author}`,
                    text: `Check out this book: ${title} by ${author}`,
                    url: url
                }).catch(console.error);
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Book link copied to clipboard!');
                }).catch(() => {
                    // Final fallback: show share modal or prompt
                    const shareText = `Check out this book: ${title} by ${author} - ${url}`;
                    prompt('Copy this link to share:', shareText);
                });
            }
        }

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Smooth scrolling for internal links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add loading states to buttons
            document.querySelectorAll('.btn-gradient, .btn-outline-gradient').forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.getAttribute('href') && this.getAttribute('href').startsWith('mailto:')) {
                        return; // Don't add loading state for mailto links
                    }
                    
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Loading...';
                    this.style.pointerEvents = 'none';
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.pointerEvents = 'auto';
                    }, 2000);
                });
            });
        });

        // Add some visual feedback for interactions
        document.addEventListener('mousemove', function(e) {
            const cards = document.querySelectorAll('.book-card, .seller-info, .reviews-section, .related-books');
            cards.forEach(card => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                if (x >= 0 && x <= rect.width && y >= 0 && y <= rect.height) {
                    card.style.transform = `perspective(1000px) rotateX(${(y - rect.height / 2) / 50}deg) rotateY(${(x - rect.width / 2) / 50}deg)`;
                } else {
                    card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
                }
            });
        });
    </script>
</body>
</html>