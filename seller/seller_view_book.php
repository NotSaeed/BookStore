<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Security check
if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$book_id = $_GET['id'] ?? null;
$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'] ?? 'Unknown';

if (!$book_id || !is_numeric($book_id)) {
    header("Location: seller_manage_books.php?error=invalid_book_id");
    exit();
}

$book_id = intval($book_id);

// Fetch comprehensive book details with analytics
$stmt = $conn->prepare("
    SELECT sb.*, 
           COALESCE(SUM(so.quantity), 0) as total_sold,
           COALESCE(COUNT(DISTINCT so.order_id), 0) as order_count,
           COALESCE(AVG(sr.rating), 0) as avg_rating,
           COALESCE(COUNT(DISTINCT sr.review_id), 0) as review_count,
           COALESCE(COUNT(DISTINCT sb_views.view_id), 0) as view_count,
           COALESCE(COUNT(DISTINCT sb_fav.favorite_id), 0) as favorite_count
    FROM seller_books sb
    LEFT JOIN seller_orders so ON sb.book_id = so.book_id AND so.order_status = 'completed'
    LEFT JOIN seller_reviews sr ON sb.book_id = sr.book_id
    LEFT JOIN seller_book_views sb_views ON sb.book_id = sb_views.book_id
    LEFT JOIN seller_book_favorites sb_fav ON sb.book_id = sb_fav.book_id
    WHERE sb.book_id = ? AND sb.seller_id = ?
    GROUP BY sb.book_id
");
$stmt->bind_param("ii", $book_id, $seller_id);
$stmt->execute();
$book = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$book) {
    header("Location: seller_manage_books.php?error=book_not_found");
    exit();
}

// Get recent activity for this book
$activity_stmt = $conn->prepare("
    SELECT action, timestamp 
    FROM seller_activity_log 
    WHERE seller_id = ? AND book_id = ? 
    ORDER BY timestamp DESC 
    LIMIT 5
");
$activity_stmt->bind_param("ii", $seller_id, $book_id);
$activity_stmt->execute();
$recent_activities = $activity_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$activity_stmt->close();

// Get recent reviews
$reviews_stmt = $conn->prepare("
    SELECT sr.*, u.username 
    FROM seller_reviews sr 
    LEFT JOIN users u ON sr.user_id = u.user_id 
    WHERE sr.book_id = ? 
    ORDER BY sr.created_at DESC 
    LIMIT 3
");
$reviews_stmt->bind_param("i", $book_id);
$reviews_stmt->execute();
$recent_reviews = $reviews_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$reviews_stmt->close();

// Calculate performance metrics
function calculateBookScore($book) {
    $views = intval($book['view_count']);
    $sales = intval($book['total_sold']);
    $rating = floatval($book['avg_rating']);
    $reviews = intval($book['review_count']);
    
    // Performance score calculation
    $view_score = min($views / 100, 1) * 25; // Max 25 points for views
    $sales_score = min($sales / 10, 1) * 30; // Max 30 points for sales
    $rating_score = ($rating / 5) * 25; // Max 25 points for rating
    $engagement_score = min($reviews / 5, 1) * 20; // Max 20 points for engagement
    
    return round($view_score + $sales_score + $rating_score + $engagement_score);
}

$performance_score = calculateBookScore($book);

// Record this view in analytics
$view_stmt = $conn->prepare("INSERT INTO seller_book_views (book_id, seller_id, view_date, ip_address) VALUES (?, ?, NOW(), ?)");
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$view_stmt->bind_param("iis", $book_id, $seller_id, $ip_address);
$view_stmt->execute();
$view_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($book['title']) ?> | BookStore Seller Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        
        .navbar {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
            font-size: 1.4rem;
        }
        
        .hero-section {
            padding: 3rem 0 2rem;
            color: white;
            text-align: center;
        }
        
        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .book-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            margin-bottom: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .book-cover {
            position: relative;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .book-cover img {
            transition: transform 0.3s ease;
        }
        
        .book-cover:hover img {
            transform: scale(1.05);
        }
        
        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .book-details {
            padding: 2.5rem;
        }
        
        .book-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: #2d3748;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .book-author {
            font-size: 1.2rem;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        .book-price {
            font-size: 2rem;
            font-weight: 800;
            color: #28a745;
            margin-bottom: 1.5rem;
        }
        
        .book-description {
            font-size: 1rem;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .performance-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .performance-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .performance-score {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .score-excellent { color: #28a745; }
        .score-good { color: #ffc107; }
        .score-average { color: #fd7e14; }
        .score-poor { color: #dc3545; }
        
        .progress-ring {
            width: 120px;
            height: 120px;
            margin: 0 auto 1rem;
        }
        
        .rating-stars {
            color: #ffc107;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
            text-decoration: none;
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
            gap: 0.5rem;
        }
        
        .btn-outline-gradient:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .activity-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 15px;
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1rem;
        }
        
        .reviews-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
        }
        
        .review-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .review-card:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        
        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 12px;
        }
        
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .dropdown-item {
            border-radius: 10px;
            margin: 0.2rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .visibility-toggle {
            background: #6c757d;
            border: none;
            border-radius: 20px;
            padding: 0.5rem 1rem;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .visibility-toggle.public {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .visibility-toggle.featured {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .book-details {
                padding: 1.5rem;
            }
            
            .book-title {
                font-size: 1.8rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="seller_dashboard.php">
            <i class="bi bi-shop me-2"></i>BookStore Seller Hub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="seller_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="seller_manage_books.php">My Books</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_add_book.php">Add Book</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_activity_log.php">Activity Log</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <div class="avatar">
                            <?= strtoupper(substr($sellerName, 0, 1)) ?>
                        </div>
                        <?= htmlspecialchars($sellerName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="seller_dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="seller_settings.php"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="seller_logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title">
            <i class="bi bi-book me-3"></i>Book Details
        </h1>
        <p class="opacity-90 mb-0">Comprehensive view of your book's performance</p>
    </div>
</div>

<div class="container">
    <!-- Main Book Information -->
    <div class="book-container">        <div class="row g-0">
            <?php if ($book['cover_image']): ?>
            <div class="col-lg-4">
                <div class="p-4">
                    <div class="book-cover position-relative">
                        <img src="<?= htmlspecialchars($book['cover_image']) ?>" 
                             class="img-fluid w-100" 
                             style="height: 500px; object-fit: cover;"
                             alt="<?= htmlspecialchars($book['title']) ?>">
                        
                        <!-- Status Badges -->
                        <div class="status-badge bg-<?= $book['is_public'] ? 'success' : 'secondary' ?>">
                            <?= $book['is_public'] ? 'Public' : 'Private' ?>
                        </div>
                        
                        <?php if ($book['is_featured']): ?>
                        <div class="status-badge bg-warning" style="top: 60px;">
                            <i class="bi bi-star-fill me-1"></i>Featured
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-lg-<?= $book['cover_image'] ? '8' : '12' ?>">
                <div class="book-details">                    <h1 class="book-title"><?= htmlspecialchars($book['title']) ?></h1>
                    <p class="book-author">
                        <i class="bi bi-person-fill me-2"></i>
                        by <?= htmlspecialchars($book['author']) ?>
                    </p>
                    
                    <div class="book-price">
                        <i class="bi bi-currency-dollar me-2"></i>
                        RM <?= number_format($book['price'], 2) ?>
                    </div>
                    
                    <!-- Rating Display -->
                    <?php if ($book['avg_rating'] > 0): ?>
                    <div class="rating-stars">
                        <?php 
                        $rating = floatval($book['avg_rating']);
                        for ($i = 1; $i <= 5; $i++): 
                            if ($i <= $rating) {
                                echo '<i class="bi bi-star-fill"></i>';
                            } elseif ($i - 0.5 <= $rating) {
                                echo '<i class="bi bi-star-half"></i>';
                            } else {
                                echo '<i class="bi bi-star"></i>';
                            }
                        endfor; 
                        ?>
                        <span class="ms-2 text-muted"><?= number_format($rating, 1) ?> (<?= $book['review_count'] ?> reviews)</span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($book['view_count']) ?></div>
                            <div class="stat-label">Views</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($book['total_sold']) ?></div>
                            <div class="stat-label">Sold</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($book['favorite_count']) ?></div>
                            <div class="stat-label">Favorites</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?= number_format($book['order_count']) ?></div>
                            <div class="stat-label">Orders</div>
                        </div>
                    </div>
                    
                    <!-- Description -->                    <?php if ($book['description']): ?>
                    <div class="book-description">
                        <h5 class="mb-3"><i class="bi bi-card-text me-2"></i>Description</h5>
                        <p><?= nl2br(htmlspecialchars($book['description'])) ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Quick Actions -->
                    <div class="d-flex gap-2 mb-3">
                        <button class="visibility-toggle <?= $book['is_public'] ? 'public' : '' ?>" 
                                onclick="toggleVisibility(<?= $book_id ?>)">
                            <i class="bi bi-<?= $book['is_public'] ? 'eye' : 'eye-slash' ?> me-1"></i>
                            <?= $book['is_public'] ? 'Public' : 'Private' ?>
                        </button>
                        
                        <button class="visibility-toggle <?= $book['is_featured'] ? 'featured' : '' ?>" 
                                onclick="toggleFeatured(<?= $book_id ?>)">
                            <i class="bi bi-star<?= $book['is_featured'] ? '-fill' : '' ?> me-1"></i>
                            <?= $book['is_featured'] ? 'Featured' : 'Feature' ?>
                        </button>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="seller_edit_book.php?id=<?= $book_id ?>" class="btn-gradient">
                            <i class="bi bi-pencil-fill"></i>Edit Book
                        </a>
                        <a href="seller_manage_books.php" class="btn-outline-gradient">
                            <i class="bi bi-arrow-left"></i>Back to Books
                        </a>
                        <button onclick="duplicateBook(<?= $book_id ?>)" class="btn-outline-gradient">
                            <i class="bi bi-files"></i>Duplicate
                        </button>
                        <button onclick="shareBook(<?= $book_id ?>)" class="btn-outline-gradient">
                            <i class="bi bi-share"></i>Share
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Performance Section -->
        <div class="col-lg-6">
            <div class="performance-section">
                <h5 class="performance-title">
                    <i class="bi bi-graph-up me-2"></i>
                    Performance Score
                </h5>
                
                <div class="text-center">
                    <div class="performance-score <?= 
                        $performance_score >= 80 ? 'score-excellent' : 
                        ($performance_score >= 60 ? 'score-good' : 
                        ($performance_score >= 40 ? 'score-average' : 'score-poor')) 
                    ?>">
                        <?= $performance_score ?>%
                    </div>
                    
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar bg-<?= 
                            $performance_score >= 80 ? 'success' : 
                            ($performance_score >= 60 ? 'warning' : 
                            ($performance_score >= 40 ? 'info' : 'danger')) 
                        ?>" style="width: <?= $performance_score ?>%"></div>
                    </div>
                    
                    <p class="text-muted mb-0">
                        <?php if ($performance_score >= 80): ?>
                            <i class="bi bi-trophy text-success me-1"></i>Excellent performance!
                        <?php elseif ($performance_score >= 60): ?>
                            <i class="bi bi-award text-warning me-1"></i>Good performance
                        <?php elseif ($performance_score >= 40): ?>
                            <i class="bi bi-bar-chart text-info me-1"></i>Average performance
                        <?php else: ?>
                            <i class="bi bi-exclamation-triangle text-danger me-1"></i>Needs improvement
                        <?php endif; ?>
                    </p>
                </div>
                
                <!-- Performance Tips -->
                <div class="mt-4">
                    <h6 class="fw-bold mb-2">💡 Improvement Tips:</h6>
                    <ul class="list-unstyled">
                        <?php if ($book['view_count'] < 100): ?>
                        <li class="mb-1"><i class="bi bi-arrow-right text-primary me-2"></i>Share your book on social media</li>
                        <?php endif; ?>
                        <?php if ($book['total_sold'] < 5): ?>
                        <li class="mb-1"><i class="bi bi-arrow-right text-primary me-2"></i>Consider promotional pricing</li>
                        <?php endif; ?>
                        <?php if ($book['review_count'] < 3): ?>
                        <li class="mb-1"><i class="bi bi-arrow-right text-primary me-2"></i>Encourage customer reviews</li>
                        <?php endif; ?>
                        <?php if (!$book['is_featured']): ?>
                        <li class="mb-1"><i class="bi bi-arrow-right text-primary me-2"></i>Feature your book for more visibility</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-6">
            <div class="activity-section">
                <h5 class="performance-title">
                    <i class="bi bi-clock-history me-2"></i>
                    Recent Activity
                </h5>
                
                <?php if (empty($recent_activities)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-clock text-muted" style="font-size: 3rem;"></i>
                        <p class="text-muted mt-2">No recent activity for this book</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_activities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="bi bi-activity"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= htmlspecialchars($activity['action']) ?></div>
                            <small class="text-muted"><?= date('M j, Y \a\t g:i A', strtotime($activity['timestamp'])) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-3">
                        <a href="seller_activity_log.php?book_id=<?= $book_id ?>" class="btn btn-sm btn-outline-primary">
                            View All Activity
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Reviews -->
    <?php if (!empty($recent_reviews)): ?>
    <div class="reviews-section">
        <h5 class="performance-title">
            <i class="bi bi-star me-2"></i>
            Recent Reviews
        </h5>
        
        <?php foreach ($recent_reviews as $review): ?>
        <div class="review-card">
            <div class="d-flex align-items-start">
                <div class="avatar">
                    <?= strtoupper(substr($review['username'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0"><?= htmlspecialchars($review['username'] ?? 'Anonymous') ?></h6>
                        <div class="text-warning">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi bi-star<?= $i <= $review['rating'] ? '-fill' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="mb-1"><?= htmlspecialchars($review['review_text']) ?></p>
                    <small class="text-muted"><?= date('M j, Y', strtotime($review['created_at'])) ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="text-center mt-3">
            <a href="seller_reviews.php?book_id=<?= $book_id ?>" class="btn btn-sm btn-outline-primary">
                View All Reviews
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Book Metadata -->
    <div class="performance-section">
        <h5 class="performance-title">
            <i class="bi bi-info-circle me-2"></i>
            Book Information
        </h5>
        
        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-semibold">Book ID:</td>
                        <td>#<?= $book['book_id'] ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Created:</td>
                        <td><?= date('F j, Y', strtotime($book['created_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Last Updated:</td>
                        <td><?= date('F j, Y \a\t g:i A', strtotime($book['updated_at'])) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Status:</td>
                        <td>
                            <span class="badge bg-<?= $book['is_public'] ? 'success' : 'secondary' ?>">
                                <?= $book['is_public'] ? 'Public' : 'Private' ?>
                            </span>
                            <?php if ($book['is_featured']): ?>
                            <span class="badge bg-warning">Featured</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless">
                    <tr>
                        <td class="fw-semibold">Total Revenue:</td>
                        <td class="text-success fw-bold">RM <?= number_format($book['total_sold'] * $book['price'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Average Rating:</td>
                        <td>
                            <?php if ($book['avg_rating'] > 0): ?>
                                <?= number_format($book['avg_rating'], 1) ?>/5.0 
                                <small class="text-muted">(<?= $book['review_count'] ?> reviews)</small>
                            <?php else: ?>
                                <span class="text-muted">No ratings yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Conversion Rate:</td>
                        <td>
                            <?php 
                            $conversion_rate = $book['view_count'] > 0 ? ($book['total_sold'] / $book['view_count']) * 100 : 0;
                            echo number_format($conversion_rate, 1) . '%';
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Engagement:</td>
                        <td>
                            <?php 
                            $engagement = $book['view_count'] > 0 ? (($book['favorite_count'] + $book['review_count']) / $book['view_count']) * 100 : 0;
                            echo number_format($engagement, 1) . '%';
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title"><i class="bi bi-share me-2"></i>Share Book</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">Share your book with others:</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="shareUrl" readonly>
                    <button class="btn btn-outline-primary" type="button" onclick="copyShareUrl()">
                        <i class="bi bi-copy"></i>
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" onclick="shareToSocial('facebook')">
                        <i class="bi bi-facebook me-1"></i>Facebook
                    </button>
                    <button class="btn btn-info btn-sm" onclick="shareToSocial('twitter')">
                        <i class="bi bi-twitter me-1"></i>Twitter
                    </button>
                    <button class="btn btn-success btn-sm" onclick="shareToSocial('whatsapp')">
                        <i class="bi bi-whatsapp me-1"></i>WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle visibility
async function toggleVisibility(bookId) {
    try {
        const response = await fetch('toggle_visibility.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `book_id=${bookId}&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to toggle visibility');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Toggle featured status
async function toggleFeatured(bookId) {
    try {
        const response = await fetch('toggle_featured.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `book_id=${bookId}&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`
        });
        
        const result = await response.json();
        if (result.success) {
            location.reload();
        } else {
            alert(result.message || 'Failed to toggle featured status');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Duplicate book
async function duplicateBook(bookId) {
    if (!confirm('Create a duplicate of this book?')) return;
    
    try {
        const response = await fetch('duplicate_book.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `book_id=${bookId}&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`
        });
        
        const result = await response.json();
        if (result.success) {
            window.location.href = `seller_edit_book.php?id=${result.new_book_id}`;
        } else {
            alert(result.message || 'Failed to duplicate book');
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}

// Share book
function shareBook(bookId) {
    const shareUrl = `${window.location.origin}/BookStore/book_details.php?id=${bookId}`;
    document.getElementById('shareUrl').value = shareUrl;
    new bootstrap.Modal(document.getElementById('shareModal')).show();
}

// Copy share URL
function copyShareUrl() {
    const shareUrl = document.getElementById('shareUrl');
    shareUrl.select();
    document.execCommand('copy');
    
    // Show feedback
    const button = event.target.closest('button');
    const originalHtml = button.innerHTML;
    button.innerHTML = '<i class="bi bi-check"></i>';
    setTimeout(() => {
        button.innerHTML = originalHtml;
    }, 2000);
}

// Share to social media
function shareToSocial(platform) {
    const url = document.getElementById('shareUrl').value;
    const title = `<?= htmlspecialchars($book['title']) ?>`;
    const description = `Check out this book: ${title}`;
    
    let shareUrl = '';
    switch (platform) {
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?url=${encodeURIComponent(url)}&text=${encodeURIComponent(description)}`;
            break;
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodeURIComponent(description + ' ' + url)}`;
            break;
    }
    
    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltips.forEach(tooltip => new bootstrap.Tooltip(tooltip));
});
</script>

</body>
</html>