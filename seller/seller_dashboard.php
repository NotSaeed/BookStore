<?php

session_start();
if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

require_once __DIR__ . '/includes/seller_db.php';

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

// Get statistics
$stats = [];

// Total books listed
$stmt = $conn->prepare("SELECT COUNT(*) as total_books FROM seller_books WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_books'] = $result->fetch_assoc()['total_books'];
$stmt->close();

// Total money spent on books (what seller paid for books)
$stmt = $conn->prepare("SELECT SUM(COALESCE(cost_price, 0)) as total_spent FROM seller_books WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_spent'] = $result->fetch_assoc()['total_spent'] ?? 0;
$stmt->close();

// Total potential earnings (current listed price * stock)
$stmt = $conn->prepare("SELECT SUM(price * stock_quantity) as potential_earnings FROM seller_books WHERE seller_id = ? AND status = 'available'");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['potential_earnings'] = $result->fetch_assoc()['potential_earnings'] ?? 0;
$stmt->close();

// Books available (in stock)
$stmt = $conn->prepare("SELECT SUM(stock_quantity) as books_in_stock FROM seller_books WHERE seller_id = ? AND status = 'available' AND stock_quantity > 0");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['books_in_stock'] = $result->fetch_assoc()['books_in_stock'] ?? 0;
$stmt->close();

// Calculate potential profit (selling price - cost price)
$stmt = $conn->prepare("SELECT SUM((price - COALESCE(cost_price, 0)) * stock_quantity) as potential_profit FROM seller_books WHERE seller_id = ? AND status = 'available'");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['potential_profit'] = $result->fetch_assoc()['potential_profit'] ?? 0;
$stmt->close();

// Get books by category for pie chart
$stmt = $conn->prepare("SELECT category, COUNT(*) as count, SUM(price * stock_quantity) as total_value FROM seller_books WHERE seller_id = ? GROUP BY category ORDER BY count DESC");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();

// Get books by visibility status for pie chart (Public vs Private)
$stmt = $conn->prepare("SELECT 
    CASE 
        WHEN is_public = 1 THEN 'Public' 
        ELSE 'Private' 
    END as visibility_status, 
    COUNT(*) as count 
FROM seller_books 
WHERE seller_id = ? 
GROUP BY is_public");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$book_status = [];
while ($row = $result->fetch_assoc()) {
    $book_status[] = $row;
}
$stmt->close();

// Get monthly book additions for bar chart (last 6 months)
$stmt = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count FROM seller_books WHERE seller_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$monthly_data = [];
while ($row = $result->fetch_assoc()) {
    $monthly_data[] = $row;
}
$stmt->close();

// Get price range distribution
$stmt = $conn->prepare("SELECT 
    CASE 
        WHEN price < 10 THEN 'Under $10'
        WHEN price < 25 THEN '$10-$25'
        WHEN price < 50 THEN '$25-$50'
        WHEN price < 100 THEN '$50-$100'
        ELSE 'Over $100'
    END as price_range,
    COUNT(*) as count,
    SUM(price * stock_quantity) as total_value
    FROM seller_books WHERE seller_id = ? 
    GROUP BY price_range 
    ORDER BY MIN(price)");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$price_ranges = [];
while ($row = $result->fetch_assoc()) {
    $price_ranges[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller Dashboard | BookStore</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bootstrap-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom styles -->
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        
        .navbar-modern {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
            font-size: 1.4rem;
        }
        
        .hero-section {
            padding: 4rem 0;
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .hero-text {
            font-size: 1.3rem;
            opacity: 0.9;
            font-weight: 400;
            margin-bottom: 2rem;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2rem;
        }
        
        .hero-stat {
            text-align: center;
        }
        
        .hero-stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            display: block;
        }
        
        .hero-stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.9);
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .card-title {
            font-weight: 700;
            color: #2d3748;
            font-size: 1.1rem;
        }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats-card {
            padding: 2rem;
            text-align: center;
            color: white;
            border-radius: 20px;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }
        
        .stats-card:hover {
            transform: translateY(-5px) scale(1.02);
        }
        
        .stats-card.books {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stats-card.spent {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
          .stats-card.earnings {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .stats-card.stock {
            background: linear-gradient(135deg, #fd7e14 0%, #ffc107 100%);
            color: white;
        }
        
        .stats-card.profit {
            background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
        }
        
        .stats-number {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .stats-text {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.9;
            font-weight: 600;
        }
        
        .stats-subtext {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 0.5rem;
            font-weight: 400;
        }
        
        .currency {
            font-size: 2.2rem;
        }
          .financial-summary {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            color: #2d3748;
            border: 1px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .financial-summary h5 {
            color: #2d3748;
            margin-bottom: 1.5rem;
            font-weight: 700;
            font-size: 1.3rem;
        }
        
        .financial-item {
            text-align: center;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.08);
            border-radius: 15px;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .financial-item:hover {
            background: rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
            border-color: #667eea;
        }
          .financial-value {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: #667eea;
        }
        
        .financial-label {
            font-size: 0.9rem;
            color: #2d3748;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Chart containers */
        .chart-container {
            position: relative;
            height: 350px;
            margin: 1rem 0;
        }
        
        .chart-card {
            margin-bottom: 2rem;
        }
        
        .chart-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .chart-card .card-body {
            padding: 2rem;
        }
        
        /* Button styles */
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
            color: #667eea;
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
        }
        
        .btn-outline-gradient:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        /* Quick actions */
        .quick-actions {
            margin-top: 3rem;
        }
        
        .quick-action-btn {
            transition: all 0.3s ease;
            border-radius: 15px;
            margin-bottom: 1rem;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-5px);
        }
        
        .quick-action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
          /* Avatar */
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
        
        /* Footer */
        .footer {
            padding: 3rem 0;
            margin-top: 4rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-stats {
                flex-direction: column;
                gap: 1.5rem;
            }
            
            .stats-number {
                font-size: 2.5rem;
            }
            
            .chart-container {
                height: 300px;
            }
        }
        
        /* Animation classes */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Loading animation */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-modern">
    <div class="container">
        <a class="navbar-brand" href="seller_dashboard.php">
            <i class="fas fa-book-open me-2"></i>BookStore Seller Hub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link nav-link-modern active fw-semibold" href="seller_dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-modern fw-semibold" href="seller_manage_books.php">
                        <i class="fas fa-books me-1"></i>My Books
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-modern fw-semibold" href="seller_add_book.php">
                        <i class="fas fa-plus me-1"></i>Add Book
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link nav-link-modern fw-semibold" href="seller_settings.php">
                        <i class="fas fa-cog me-1"></i>Settings
                    </a>
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
                                <i class="fas fa-user-cog me-2"></i>Settings
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="seller_activity_log.php">
                                <i class="fas fa-history me-2"></i>Activity Log
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item logout-btn" id="logoutButton">
                                <i class="fas fa-sign-out-alt me-2 text-danger"></i>
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
<div class="hero-section">
    <div class="container">
        <?php if (isset($_GET['status']) && $_GET['status'] === 'password_reset_success'): ?>
        <!-- Password Reset Success Alert -->
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert" style="background: rgba(25, 135, 84, 0.9); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); color: white;">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Password Reset Successful!</strong> You are now logged in with your new password. Welcome back to your dashboard!
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <h1 class="hero-title animate-on-scroll">Welcome Back, <?= htmlspecialchars($sellerName) ?>!</h1>
        <p class="hero-text animate-on-scroll">Track your investments, analyze performance, and grow your book business</p>
        
        <div class="hero-stats animate-on-scroll">
            <div class="hero-stat">
                <span class="hero-stat-number" data-count="<?= $stats['total_books'] ?>">0</span>
                <span class="hero-stat-label">Total Books</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number">$<span data-count="<?= number_format($stats['potential_earnings'], 0) ?>"><?= number_format($stats['potential_earnings'], 0) ?></span></span>
                <span class="hero-stat-label">Potential Value</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-number">$<span data-count="<?= number_format($stats['potential_profit'], 0) ?>"><?= number_format($stats['potential_profit'], 0) ?></span></span>
                <span class="hero-stat-label">Expected Profit</span>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container">
    <!-- Financial Summary -->
    <div class="financial-summary animate-on-scroll">
        <h5><i class="bi bi-graph-up me-2"></i>Financial Overview</h5>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="financial-item">
                    <div class="financial-value">$<?= number_format($stats['total_spent'], 2) ?></div>
                    <div class="financial-label">Total Investment</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="financial-item">
                    <div class="financial-value">$<?= number_format($stats['potential_earnings'], 2) ?></div>
                    <div class="financial-label">Potential Revenue</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="financial-item">
                    <div class="financial-value">$<?= number_format($stats['potential_profit'], 2) ?></div>
                    <div class="financial-label">Expected Profit</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="financial-item">
                    <div class="financial-value"><?= $stats['total_spent'] > 0 ? number_format(($stats['potential_profit'] / $stats['total_spent']) * 100, 1) : 0 ?>%</div>
                    <div class="financial-label">ROI Percentage</div>
                </div>
            </div>
        </div>
    </div>    <!-- Stats Overview -->
    <div class="row mb-5 animate-on-scroll">
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stats-card books">
                <i class="fas fa-book-open" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                <div class="stats-number" data-count="<?= $stats['total_books'] ?>">0</div>
                <div class="stats-text">Books Listed</div>
                <div class="stats-subtext">Total inventory items</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stats-card spent">
                <i class="fas fa-dollar-sign" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                <div class="stats-number currency">$<span data-count="<?= number_format($stats['total_spent'], 0) ?>">0</span></div>
                <div class="stats-text">Total Investment</div>
                <div class="stats-subtext">Money spent acquiring books</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stats-card earnings">
                <i class="fas fa-chart-line" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                <div class="stats-number currency">$<span data-count="<?= number_format($stats['potential_earnings'], 0) ?>">0</span></div>
                <div class="stats-text">Potential Revenue</div>
                <div class="stats-subtext">If all current stock sells</div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="stats-card stock">
                <i class="fas fa-warehouse" style="font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.9;"></i>
                <div class="stats-number" data-count="<?= $stats['books_in_stock'] ?>">0</div>
                <div class="stats-text">Books in Stock</div>
                <div class="stats-subtext">Ready for immediate sale</div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mb-5">
        <!-- Books by Category Pie Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card-modern chart-card animate-on-scroll">
                <div class="card-header bg-gradient-primary text-white">
                    <i class="fas fa-chart-pie me-2"></i>Books by Category
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Distribution of your book inventory by genre</small>
                    </div>
                </div>
            </div>
        </div>        <!-- Books by Status Pie Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card-modern chart-card animate-on-scroll">
                <div class="card-header bg-gradient-success text-white">
                    <i class="fas fa-chart-pie me-2"></i>Books by Status
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Current status of all your listed books</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Book Additions Bar Chart -->
        <div class="col-lg-6 mb-4">
            <div class="card-modern chart-card animate-on-scroll">
                <div class="card-header">
                    <i class="bi bi-bar-chart me-2"></i>Monthly Book Additions
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">Books added to inventory over the last 6 months</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Price Range Distribution -->
        <div class="col-lg-6 mb-4">
            <div class="card chart-card animate-on-scroll">
                <div class="card-header">
                    <i class="bi bi-bar-chart-line me-2"></i>Price Range Distribution
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="priceChart"></canvas>
                    </div>
                    <div class="mt-3 text-center">
                        <small class="text-muted">How your books are distributed across different price ranges</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Action Cards -->
    <div class="row g-4 mb-5 animate-on-scroll">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center p-4">
                    <i class="bi bi-plus-circle card-icon"></i>
                    <h4 class="card-title mb-3">Add New Book</h4>
                    <p class="card-text text-muted mb-4">Expand your inventory by adding new books with detailed information, pricing, and high-quality images.</p>
                    <a href="seller_add_book.php" class="btn btn-gradient">
                        <i class="bi bi-plus-lg me-2"></i>Add New Book
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-body text-center p-4">
                    <i class="bi bi-collection card-icon"></i>
                    <h4 class="card-title mb-3">Manage Inventory</h4>
                    <p class="card-text text-muted mb-4">View, edit, update pricing, or remove books from your current inventory. Track performance and optimize listings.</p>
                    <a href="seller_manage_books.php" class="btn btn-outline-gradient">
                        <i class="bi bi-pencil-square me-2"></i>Manage Books
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row quick-actions animate-on-scroll">
        <div class="col-12 mb-4">
            <h4 class="text-white fw-bold">Quick Actions</h4>
            <p class="text-white opacity-75">Access frequently used features and tools</p>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <a href="seller_settings.php" class="text-decoration-none">
                <div class="card quick-action-btn h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-gear quick-action-icon"></i>
                        <h6 class="fw-bold mb-2">Account Settings</h6>
                        <small class="text-muted">Update profile and preferences</small>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <a href="seller_activity_log.php" class="text-decoration-none">
                <div class="card quick-action-btn h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history quick-action-icon"></i>
                        <h6 class="fw-bold mb-2">Activity Log</h6>
                        <small class="text-muted">View your recent activities</small>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <a href="#" class="text-decoration-none" onclick="exportToExcel()">
                <div class="card quick-action-btn h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-excel quick-action-icon"></i>
                        <h6 class="fw-bold mb-2">Export Excel</h6>
                        <small class="text-muted">Download inventory report</small>
                    </div>
                </div>
            </a>
        </div>
        
        <div class="col-md-3 col-sm-6 mb-3">
            <a href="#" class="text-decoration-none" onclick="generateReport()">
                <div class="card quick-action-btn h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-file-earmark-pdf quick-action-icon"></i>
                        <h6 class="fw-bold mb-2">Generate Report</h6>
                        <small class="text-muted">Create detailed PDF report</small>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="container">
        <p class="mb-0">&copy; <?= date('Y') ?> BookStore Seller Hub. Empowering book sellers worldwide.</p>
        <small class="opacity-75">Built with ❤️ for passionate book sellers</small>
    </div>
</div>

<!-- Bootstrap JS -->
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
    
    // Animate numbers
    setTimeout(() => {
        document.querySelectorAll('[data-count]').forEach(element => {
            const targetValue = parseInt(element.getAttribute('data-count').replace(/,/g, ''));
            if (targetValue > 0) {
                animateValue(element, 0, targetValue, 2000);
            }
        });
    }, 500);

    // Initialize Charts
    initializeCharts();
});

function initializeCharts() {
    // Category Pie Chart
    const categoryData = <?= json_encode($categories) ?>;
    if (categoryData.length > 0) {
        const ctx1 = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctx1, {
            type: 'doughnut',
            data: {
                labels: categoryData.map(item => item.category || 'Uncategorized'),                datasets: [{
                    data: categoryData.map(item => item.count),
                    backgroundColor: [
                        '#667eea', '#764ba2', '#4facfe', '#00f2fe', 
                        '#fa709a', '#fee140', '#a8edea', '#fed6e3',
                        '#d299c2', '#fef9d7', '#89f7fe', '#66a6ff'
                    ],
                    borderColor: [
                        '#5a67d8', '#6b46c1', '#3b82f6', '#06b6d4', 
                        '#ec4899', '#f59e0b', '#10b981', '#f472b6',
                        '#c084fc', '#facc15', '#0ea5e9', '#3b82f6'
                    ],
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        cornerRadius: 10,
                        displayColors: false
                    }
                }
            }
        });
    }    // Status Pie Chart
    const statusData = <?= json_encode($book_status) ?>;
    if (statusData.length > 0) {
        const ctx2 = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.visibility_status),                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: ['#28A745', '#DC3545', '#FFC107', '#6C757D'],
                    borderColor: ['#1e7e34', '#c82333', '#e0a800', '#545b62'],
                    borderWidth: 2,
                    hoverOffset: 15
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        cornerRadius: 10,
                        displayColors: false
                    }
                }
            }
        });
    }

    // Monthly Bar Chart
    const monthlyData = <?= json_encode($monthly_data) ?>;
    if (monthlyData.length > 0) {
        const ctx3 = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),                datasets: [{
                    label: 'Books Added',
                    data: monthlyData.map(item => item.count),
                    backgroundColor: 'rgba(102, 126, 234, 0.8)',
                    borderColor: '#667eea',
                    borderWidth: 3,
                    borderRadius: 12,
                    borderSkipped: false,
                    hoverBackgroundColor: 'rgba(102, 126, 234, 0.9)',
                    hoverBorderColor: '#5a67d8',
                    hoverBorderWidth: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        cornerRadius: 10,
                        displayColors: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            color: '#6C757D'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            color: '#6C757D'
                        }
                    }
                }
            }
        });
    }

    // Price Range Chart
    const priceData = <?= json_encode($price_ranges) ?>;
    if (priceData.length > 0) {
        const ctx4 = document.getElementById('priceChart').getContext('2d');
        new Chart(ctx4, {
            type: 'bar',
            data: {
                labels: priceData.map(item => item.price_range),                datasets: [{
                    label: 'Number of Books',
                    data: priceData.map(item => item.count),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(250, 112, 154, 0.8)',
                        'rgba(254, 225, 64, 0.8)'
                    ],
                    borderColor: [
                        '#667eea',
                        '#764ba2',
                        '#4facfe',
                        '#fa709a',
                        '#fee140'
                    ],
                    borderWidth: 3,
                    borderRadius: 12,
                    borderSkipped: false,
                    hoverBackgroundColor: [
                        'rgba(102, 126, 234, 0.9)',
                        'rgba(118, 75, 162, 0.9)',
                        'rgba(79, 172, 254, 0.9)',
                        'rgba(250, 112, 154, 0.9)',
                        'rgba(254, 225, 64, 0.9)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            afterBody: function(context) {
                                const dataIndex = context[0].dataIndex;
                                const totalValue = priceData[dataIndex].total_value;
                                return 'Total Value: $' + parseFloat(totalValue).toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            color: '#6C757D'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            },
                            color: '#6C757D'
                        }
                    }
                }
            }
        });
    }
}

// Export functions
function exportToExcel() {
    window.open('export_books_excel.php', '_blank');
}

function generateReport() {
    window.open('export_books_pdf.php', '_blank');
}

// Tooltip initialization
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl)
});
</script>

</body>
</html>