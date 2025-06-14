<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'];

// Function to get profile photo
function getProfilePhoto($seller_id, $conn) {
    $stmt = $conn->prepare("SELECT profile_photo FROM seller_users WHERE seller_id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!empty($result['profile_photo']) && file_exists(__DIR__ . '/' . $result['profile_photo'])) {
        return $result['profile_photo'] . '?v=' . time();
    }
    return null;
}

$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'b.created_at DESC';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 6;
$offset = ($page - 1) * $limit;

$filter_sql = "WHERE b.seller_id = ?";
$params = [$seller_id];
$types = "i";
if ($search) {
    $filter_sql .= " AND (b.title LIKE ? OR b.author LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= "ss";
}

$sql = "SELECT b.book_id, b.title, b.author, b.description, b.price, b.cover_image, b.is_public, b.stock_quantity, b.status, b.category, b.created_at, b.isbn
        FROM seller_books b
        $filter_sql
        ORDER BY $sort
        LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!$stmt) die("SQL error: " . $conn->error);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$count_sql = "SELECT COUNT(*) FROM seller_books b $filter_sql";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
$count_stmt->execute();
$count_stmt->bind_result($totalBooks);
$count_stmt->fetch();
$count_stmt->close();
$totalPages = ceil($totalBooks / $limit);

// Get quick stats
$total_books = $totalBooks;
$total_value = 0;
$in_stock = 0;
$out_of_stock = 0;

foreach($books as $book) {
    $total_value += $book['price'] * $book['stock_quantity'];
    if($book['stock_quantity'] > 0) $in_stock++;
    else $out_of_stock++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Books | BookStore Seller</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bootstrap-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
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
        
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
            font-size: 1.4rem;
        }
        
        .hero-section {
            padding: 3rem 0 2rem;
            text-align: center;
            color: white;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .stats-overview {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-item {
            text-align: center;
            color: white;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        .filters-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .filters-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
        }
        
        .form-control, .form-select {
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 0.875rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .input-group-text {
            background: transparent;
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-right: none;
            border-radius: 15px 0 0 15px;
            color: #667eea;
            font-weight: 600;
        }
        
        .books-grid {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .books-grid-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 2rem;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: between;
        }
        
        .book-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            overflow: hidden;
            height: 100%;
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
        }
        
        .book-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
        }
        
        .book-cover-container {
            position: relative;
            overflow: hidden;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .book-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }
        
        .book-card:hover .book-cover {
            transform: scale(1.1);
        }
        
        .book-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            backdrop-filter: blur(10px);
            border: none;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
        }
        
        .badge-public {
            background: rgba(40, 167, 69, 0.9);
            color: white;
        }
        
        .badge-private {
            background: rgba(108, 117, 125, 0.9);
            color: white;
        }
        
        .book-info {
            padding: 1.5rem;
        }
        
        .book-title {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .book-author {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }
        
        .book-description {
            color: #718096;
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .book-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .book-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .stock-info {
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .stock-available {
            color: #28a745;
        }
        
        .stock-low {
            color: #ffc107;
        }
        
        .stock-out {
            color: #dc3545;
        }
        
        .book-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-action {
            padding: 0.5rem;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
          .btn-delete {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .btn-toggle {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-toggle.private {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            color: white;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-outline-gradient {
            background: transparent;
            border: 2px solid transparent;
            background-clip: padding-box;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            color: white;
        }
        
        .btn-outline-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 15px;
            padding: 2px;
            background: linear-gradient(135deg, #fff 0%, rgba(255,255,255,0.8) 100%);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
        }
        
        .btn-outline-gradient:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateY(-2px);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #718096;
        }
        
        .empty-state-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            color: #cbd5e0;
        }
        
        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #4a5568;
        }
        
        .empty-state-text {
            font-size: 1rem;
            margin-bottom: 2rem;
        }
        
        .pagination {
            margin-top: 3rem;
            justify-content: center;
        }
        
        .page-link {
            border: none;
            padding: 0.75rem 1rem;
            margin: 0 0.25rem;
            border-radius: 12px;
            font-weight: 600;
            color: #667eea;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .qr-code {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Dropdown - FIXED STYLES */
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            z-index: 1050;
            min-width: 200px;
        }
        
        .dropdown-item {
            border-radius: 10px;
            margin: 0.2rem;
            transition: all 0.3s ease;
            padding: 0.75rem 1rem;
            cursor: pointer;
            user-select: none;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
        }

        /* Logout button specific styling - FIXED */
        .dropdown-item.logout-btn {
            border-top: 1px solid #e9ecef;
            margin-top: 0.5rem;
            padding-top: 0.75rem;
            cursor: pointer !important;
            pointer-events: auto !important;
        }

        .dropdown-item.logout-btn:hover {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            color: white !important;
        }

        .dropdown-item.logout-btn:hover .text-danger {
            color: white !important;
        }

        .dropdown-item.logout-btn:hover i {
            color: white !important;
        }

        .dropdown-item.logout-btn .text-danger {
            color: #dc3545 !important;
        }

        /* Fix for dropdown toggle */
        .nav-link.dropdown-toggle {
            cursor: pointer;
        }

        /* Ensure dropdown items are clickable */
        .dropdown-menu .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }
        
        .footer {
            padding: 3rem 0;
            margin-top: 4rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-bottom: 2rem;
        }
        
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 12px;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .filters-section, .books-grid {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: none !important;
            }
            .books-grid, .filters-section {
                box-shadow: none !important;
                background: white !important;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark no-print">
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
                    <a class="nav-link fw-semibold" href="seller_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="seller_manage_books.php">My Books</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="seller_add_book.php">Add Book</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="seller_settings.php">Settings</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">                    <a class="nav-link dropdown-toggle d-flex align-items-center fw-semibold" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar">
                            <?php 
                            $photo = getProfilePhoto($seller_id, $conn);
                            if ($photo): ?>
                                <img src="<?= htmlspecialchars($photo) ?>" alt="Profile">
                            <?php else: ?>
                                <?= strtoupper(substr($sellerName, 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <?= htmlspecialchars($sellerName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="seller_settings.php">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="seller_activity_log.php">
                                <i class="bi bi-clock-history me-2"></i>Activity Log
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item logout-btn" id="logoutButton">
                                <i class="bi bi-box-arrow-right me-2 text-danger"></i>
                                <span class="text-danger fw-semibold">Logout</span>
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<div class="hero-section no-print">
    <div class="container">
        <h1 class="hero-title animate-on-scroll">
            <i class="bi bi-collection me-3"></i>My Book Collection
        </h1>
        <p class="hero-subtitle animate-on-scroll">Manage, organize and track your entire book inventory</p>
        
        <!-- Quick Stats -->
        <div class="stats-overview animate-on-scroll">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $totalBooks ?>">0</div>
                        <div class="stat-label">Total Books</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number">$<span data-count="<?= number_format($total_value, 0) ?>">0</span></div>
                        <div class="stat-label">Total Value</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $in_stock ?>">0</div>
                        <div class="stat-label">In Stock</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $out_of_stock ?>">0</div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Action Buttons -->
    <div class="action-buttons no-print animate-on-scroll">
        <a href="seller_add_book.php" class="btn btn-gradient">
            <i class="bi bi-plus-circle me-2"></i>Add New Book
        </a>
        <button onclick="exportToCSV()" class="btn btn-outline-gradient">
            <i class="bi bi-file-earmark-excel me-2"></i>Export CSV
        </button>
        <button onclick="exportToPDF()" class="btn btn-outline-gradient">
            <i class="bi bi-file-earmark-pdf me-2"></i>Export PDF
        </button>
    </div>

    <!-- Search & Filter Section -->
    <div class="filters-section no-print animate-on-scroll">
        <h5 class="filters-title">
            <i class="bi bi-funnel me-2"></i>Search & Filter Books
        </h5>
        <form method="GET" class="row gy-3 gx-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Search Books</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by title or author..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Sort By</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-sort-down"></i></span>                    <select name="sort" class="form-select">
                        <option value="b.created_at DESC" <?= $sort==='b.created_at DESC'?'selected':''; ?>>Newest First</option>
                        <option value="price ASC" <?= $sort==='price ASC'?'selected':''; ?>>Price: Low to High</option>
                        <option value="price DESC" <?= $sort==='price DESC'?'selected':''; ?>>Price: High to Low</option>
                        <option value="title ASC" <?= $sort==='title ASC'?'selected':''; ?>>Title: A-Z</option>
                        <option value="author ASC" <?= $sort==='author ASC'?'selected':''; ?>>Author: A-Z</option>
                        <option value="stock_quantity DESC" <?= $sort==='stock_quantity DESC'?'selected':''; ?>>Stock: High to Low</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <button class="btn btn-gradient w-100">
                    <i class="bi bi-search me-2"></i>Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- Books Grid -->
    <div class="books-grid animate-on-scroll">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="books-grid-title mb-0">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>
                Your Books
                <?php if($search): ?>
                    <small class="text-muted">- Results for "<?= htmlspecialchars($search) ?>"</small>
                <?php endif; ?>
            </h5>
            <span class="badge bg-primary rounded-pill px-3 py-2">
                <?= $totalBooks ?> book<?= $totalBooks !== 1 ? 's' : '' ?> found
            </span>
        </div>

        <?php if(empty($books)): ?>
            <div class="empty-state">
                <i class="bi bi-book empty-state-icon"></i>
                <h3 class="empty-state-title">No Books Found</h3>
                <p class="empty-state-text">
                    <?php if($search): ?>
                        No books match your search criteria. Try adjusting your search terms.
                    <?php else: ?>
                        You haven't added any books to your inventory yet.
                    <?php endif; ?>
                </p>
                <a href="seller_add_book.php" class="btn btn-gradient">
                    <i class="bi bi-plus-circle me-2"></i>Add Your First Book
                </a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($books as $i => $book): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="book-card">
                        <div class="book-cover-container">
                            <?php if (!empty($book['cover_image']) && file_exists($book['cover_image'])): ?>
                                <img src="<?= htmlspecialchars($book['cover_image']) ?>" 
                                     alt="<?= htmlspecialchars($book['title']) ?>" 
                                     class="book-cover">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <i class="bi bi-book text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="book-status-badge <?= $book['is_public'] ? 'badge-public' : 'badge-private' ?>">
                                <i class="bi bi-<?= $book['is_public'] ? 'eye' : 'eye-slash' ?> me-1"></i>
                                <?= $book['is_public'] ? 'Public' : 'Private' ?>
                            </div>
                        </div>
                        
                        <div class="book-info">
                            <?php if($book['category']): ?>
                                <span class="category-badge"><?= htmlspecialchars($book['category']) ?></span>
                            <?php endif; ?>
                              <h6 class="book-title"><?= htmlspecialchars($book['title']) ?></h6>
                            <p class="book-author">by <?= htmlspecialchars($book['author']) ?></p>
                            
                            <?php if($book['description']): ?>
                            <p class="book-description">
                                <?= htmlspecialchars(strlen($book['description']) > 120 ? 
                                    substr($book['description'], 0, 120) . '...' : 
                                    $book['description']) ?>
                            </p>
                            <?php endif; ?>
                            
                            <div class="book-price">$<?= number_format($book['price'], 2) ?></div>
                            
                            <div class="book-meta">                                <div class="stock-info">
                                    <?php if($book['stock_quantity'] > 10): ?>
                                        <span class="stock-available">
                                            <i class="bi bi-check-circle me-1"></i>
                                            <?= $book['stock_quantity'] ?> in stock
                                        </span>
                                    <?php elseif($book['stock_quantity'] > 0): ?>
                                        <span class="stock-low">
                                            <i class="bi bi-exclamation-triangle me-1"></i>
                                            <?= $book['stock_quantity'] ?> left
                                        </span>
                                    <?php else: ?>
                                        <span class="stock-out">
                                            <i class="bi bi-x-circle me-1"></i>
                                            Out of stock
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-end">
                                    <small class="text-muted">
                                        Added <?= date('M j, Y', strtotime($book['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                              <div class="book-actions">
                                <a href="book_preview.php?id=<?= $book['book_id'] ?>" 
                                   class="btn btn-action btn-view flex-fill" 
                                   title="View Book">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button onclick="toggleVisibility(<?= $book['book_id'] ?>)" 
                                        class="btn btn-action btn-toggle <?= $book['is_public'] ? '' : 'private' ?> flex-fill" 
                                        title="<?= $book['is_public'] ? 'Make Private' : 'Make Public' ?>">
                                    <i class="bi bi-<?= $book['is_public'] ? 'eye-slash' : 'eye' ?>"></i>
                                </button>
                                <a href="seller_edit_book.php?id=<?= $book['book_id'] ?>" 
                                   class="btn btn-action btn-edit flex-fill" 
                                   title="Edit Book">
                                    <i class="bi bi-pencil"></i>
                                </a>                                <button onclick="showQR(<?= $book['book_id'] ?>, '<?= htmlspecialchars($book['isbn'] ?? '') ?>')" 
                                        class="btn btn-action btn-view flex-fill" 
                                        title="Show QR Code">
                                    <i class="bi bi-qr-code"></i>
                                </button>
                                <a href="seller_delete_book.php?id=<?= $book['book_id'] ?>" 
                                   class="btn btn-action btn-delete flex-fill" 
                                   title="Delete Book"
                                   onclick="return confirm('Are you sure you want to delete this book? This action cannot be undone.')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
            <nav class="pagination-nav no-print">
                <ul class="pagination">
                    <?php if($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&page=<?= $page-1 ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                
                    <?php 
                    $startPage = max(1, min($page - 2, $totalPages - 4));
                    $endPage = min($totalPages, max($page + 2, 5));
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($search) . '&sort=' . urlencode($sort) . '&page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; 
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($search) . '&sort=' . urlencode($sort) . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <?php if($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>&page=<?= $page+1 ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<div class="footer no-print">
    <div class="container">
        <p class="mb-0">&copy; <?= date('Y') ?> BookStore Seller Hub. All rights reserved.</p>
        <small class="opacity-75">Manage your books with style and efficiency</small>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title"><i class="bi bi-qr-code me-2"></i>Book QR Code</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <div id="qrCodeContainer">
                    <!-- QR code will be inserted here -->
                </div>
                <p class="text-muted mt-3">Scan this QR code to view the book details</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animation on scroll
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

document.querySelectorAll('.animate-on-scroll').forEach(el => {
    observer.observe(el);
});

// Number animation
function animateValue(element, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        const current = Math.floor(progress * (end - start) + start);
        element.textContent = current.toLocaleString();
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}

// FIXED LOGOUT FUNCTIONALITY
function performLogout() {
    // Show confirmation dialog
    const confirmed = confirm('🔐 Are you sure you want to logout?\n\nYour session will be securely terminated.');
    
    if (!confirmed) {
        return;
    }

    // Find the logout button and show loading state
    const logoutBtn = document.getElementById('logoutButton');
    const originalContent = logoutBtn.innerHTML;
    logoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';
    logoutBtn.disabled = true;
    
    // Create and show loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.innerHTML = `
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        ">
            <div style="
                background: white;
                padding: 2.5rem;
                border-radius: 1.5rem;
                text-align: center;
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
                max-width: 350px;
                margin: 1rem;
            ">
                <div style="
                    width: 60px;
                    height: 60px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 1.5rem;
                "></div>
                <div style="
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #2d3748;
                    margin-bottom: 0.5rem;
                ">Logging out securely...</div>
                <div style="
                    font-size: 0.9rem;
                    color: #6c757d;
                ">Please wait while we terminate your session</div>
            </div>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    
    document.body.appendChild(loadingOverlay);
    
    // Prevent scrolling while loading
    document.body.style.overflow = 'hidden';
    
    // Redirect to logout after delay
    setTimeout(() => {
        window.location.href = 'seller_logout.php';
    }, 2000);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize logout functionality with multiple approaches
    const logoutButton = document.getElementById('logoutButton');
    
    if (logoutButton) {
        // Method 1: Direct click event
        logoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            performLogout();
        });

        // Method 2: Touch events for mobile
        logoutButton.addEventListener('touchstart', function(e) {
            e.preventDefault();
            performLogout();
        });

        // Method 3: Keyboard support
        logoutButton.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                performLogout();
            }
        });
    }

    // Alternative: Also listen for any element with logout class
    document.addEventListener('click', function(e) {
        if (e.target.closest('.logout-btn') || e.target.closest('#logoutButton')) {
            e.preventDefault();
            e.stopPropagation();
            performLogout();
        }
    });
    
    // Initialize animations
    setTimeout(() => {
        document.querySelectorAll('[data-count]').forEach(element => {
            const targetValue = parseInt(element.getAttribute('data-count').replace(/,/g, ''));
            if (targetValue > 0) {
                animateValue(element, 0, targetValue, 2000);
            }
        });
    }, 500);
});

// QR Code Modal
function showQR(bookId, isbn) {
    const qrContainer = document.getElementById('qrCodeContainer');
    
    // If ISBN is provided and not empty, generate QR code for ISBN
    let qrData;
    if (isbn && isbn.trim() !== '' && isbn.trim().toLowerCase() !== 'unknown') {
        qrData = isbn.trim();
    } else {
        // Fall back to book preview URL
        qrData = `http://localhost/BookStore/seller/book_preview.php?id=${bookId}`;
    }
    
    qrContainer.innerHTML = `
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(qrData)}" 
             alt="QR Code" class="img-fluid" style="border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('qrModal'));
    modal.show();
}

// Export functions
function exportToCSV() {
    const books = <?= json_encode($books) ?>;
    
    // Create CSV headers
    const headers = ['Title', 'Author', 'Category', 'Price', 'Stock', 'Status', 'Visibility', 'Date Added'];
      // Create CSV rows
    const rows = books.map(book => [
        book.title,
        book.author,
        book.category || 'Uncategorized',
        book.price,
        book.stock_quantity,
        book.status,
        book.is_public ? 'Public' : 'Private',
        book.created_at
    ]);
    
    // Combine headers and rows
    const csvContent = [headers, ...rows]
        .map(row => row.map(field => `"${field}"`).join(','))
        .join('\n');
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `books_inventory_${new Date().toISOString().split('T')[0]}.csv`;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    // Hide elements not needed in PDF
    const originalTitle = document.title;
    document.title = `Books Inventory - ${new Date().toLocaleDateString()}`;
    window.print();
    document.title = originalTitle;
}

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});

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
            // Reload the page to reflect changes
            location.reload();
        } else {
            alert(result.message || 'Failed to toggle visibility');
        }
    } catch (error) {
        console.error('Toggle visibility error:', error);
        alert('Error: ' + error.message);
    }
}
</script>

</body>
</html>