<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Restrict to logged-in sellers
if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'];

// Pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build filter conditions
$filter_sql = "WHERE seller_id = ?";
$params = [$seller_id];
$types = "i";

if ($search) {
    $filter_sql .= " AND action LIKE ?";
    $params[] = "%{$search}%";
    $types .= "s";
}

if ($filter !== 'all') {
    switch ($filter) {
        case 'book':
            $filter_sql .= " AND (action LIKE '%book%' OR action LIKE '%Book%')";
            break;
        case 'login':
            $filter_sql .= " AND (action LIKE '%login%' OR action LIKE '%Login%')";
            break;
        case 'settings':
            $filter_sql .= " AND (action LIKE '%settings%' OR action LIKE '%Settings%' OR action LIKE '%updated%')";
            break;
        case 'export':
            $filter_sql .= " AND (action LIKE '%export%' OR action LIKE '%Export%')";
            break;
    }
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM seller_activity_log " . $filter_sql;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($totalLogs);
$count_stmt->fetch();
$count_stmt->close();
$totalPages = ceil($totalLogs / $limit);

// Fetch logs with pagination
$sql = "SELECT action, timestamp FROM seller_activity_log " . $filter_sql . " ORDER BY timestamp DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$logs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get activity statistics
$stats_sql = "SELECT 
    COUNT(*) as total_activities,
    COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as today_activities,
    COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_activities,
    COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_activities
    FROM seller_activity_log WHERE seller_id = ?";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $seller_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

// Function to get activity icon and color
function getActivityDetails($action) {
    $action_lower = strtolower($action);
    
    if (strpos($action_lower, 'login') !== false || strpos($action_lower, 'logged') !== false) {
        return ['icon' => 'bi-box-arrow-in-right', 'color' => 'success', 'category' => 'Authentication'];
    } elseif (strpos($action_lower, 'logout') !== false) {
        return ['icon' => 'bi-box-arrow-right', 'color' => 'secondary', 'category' => 'Authentication'];
    } elseif (strpos($action_lower, 'added') !== false || strpos($action_lower, 'created') !== false) {
        return ['icon' => 'bi-plus-circle', 'color' => 'success', 'category' => 'Creation'];
    } elseif (strpos($action_lower, 'updated') !== false || strpos($action_lower, 'edited') !== false) {
        return ['icon' => 'bi-pencil-square', 'color' => 'warning', 'category' => 'Modification'];
    } elseif (strpos($action_lower, 'deleted') !== false || strpos($action_lower, 'removed') !== false) {
        return ['icon' => 'bi-trash', 'color' => 'danger', 'category' => 'Deletion'];
    } elseif (strpos($action_lower, 'export') !== false) {
        return ['icon' => 'bi-download', 'color' => 'info', 'category' => 'Export'];
    } elseif (strpos($action_lower, 'book') !== false) {
        return ['icon' => 'bi-book', 'color' => 'primary', 'category' => 'Books'];
    } elseif (strpos($action_lower, 'settings') !== false || strpos($action_lower, 'profile') !== false) {
        return ['icon' => 'bi-gear', 'color' => 'secondary', 'category' => 'Settings'];
    } else {
        return ['icon' => 'bi-activity', 'color' => 'primary', 'category' => 'General'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Log | BookStore Seller Hub</title>
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
        
        .activity-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }
        
        .activity-header {
            display: flex;
            align-items: center;
            justify-content: between;
            margin-bottom: 2rem;
        }
        
        .activity-title {
            font-weight: 700;
            color: #2d3748;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
        }
        
        .activity-timeline {
            position: relative;
            padding-left: 3rem;
        }
        
        .activity-timeline::before {
            content: '';
            position: absolute;
            left: 1.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            border-radius: 1px;
        }
        
        .activity-item {
            position: relative;
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .activity-item:hover {
            transform: translateX(10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -3.75rem;
            top: 1.5rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: white;
            border: 3px solid #667eea;
            z-index: 1;
        }
        
        .activity-icon {
            position: absolute;
            left: -3.5rem;
            top: 1.75rem;
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 50%;
            z-index: 2;
        }
        
        .activity-content {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .activity-icon-badge {
            min-width: 3rem;
            height: 3rem;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-action {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }
        
        .activity-time {
            color: #6c757d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .activity-category {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 20px;
            margin-top: 0.5rem;
        }
        
        .bg-success { background: linear-gradient(135deg, #28a745, #20c997) !important; }
        .bg-warning { background: linear-gradient(135deg, #ffc107, #fd7e14) !important; }
        .bg-danger { background: linear-gradient(135deg, #dc3545, #e83e8c) !important; }
        .bg-info { background: linear-gradient(135deg, #17a2b8, #6f42c1) !important; }
        .bg-primary { background: linear-gradient(135deg, #667eea, #764ba2) !important; }
        .bg-secondary { background: linear-gradient(135deg, #6c757d, #495057) !important; }
        
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
        
        .filter-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .filter-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .filter-badge.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .filter-badge:not(.active) {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        
        .filter-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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
        
        .activity-item {
            animation: slideInRight 0.6s ease forwards;
            opacity: 0;
        }
        
        .activity-item:nth-child(1) { animation-delay: 0.1s; }
        .activity-item:nth-child(2) { animation-delay: 0.2s; }
        .activity-item:nth-child(3) { animation-delay: 0.3s; }
        .activity-item:nth-child(4) { animation-delay: 0.4s; }
        .activity-item:nth-child(5) { animation-delay: 0.5s; }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .filters-section, .activity-container {
                padding: 1.5rem;
            }
            
            .activity-timeline {
                padding-left: 2rem;
            }
            
            .activity-timeline::before {
                left: 1rem;
            }
            
            .activity-item::before {
                left: -3.25rem;
            }
            
            .activity-icon {
                left: -3rem;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .filter-badges {
                justify-content: center;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .activity-item {
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(20px);
                border-color: rgba(255, 255, 255, 0.1);
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
                    <a class="nav-link" href="seller_manage_books.php">My Books</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_add_book.php">Add Book</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="seller_activity_log.php">Activity Log</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_settings.php">Settings</a>
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
        <h1 class="hero-title animate-on-scroll">
            <i class="bi bi-clock-history me-3"></i>Activity Timeline
        </h1>
        <p class="hero-subtitle animate-on-scroll">Track your account activity and monitor all actions</p>
        
        <!-- Quick Stats -->
        <div class="stats-overview animate-on-scroll">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $stats['total_activities'] ?>">0</div>
                        <div class="stat-label">Total Activities</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $stats['today_activities'] ?>">0</div>
                        <div class="stat-label">Today</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $stats['week_activities'] ?>">0</div>
                        <div class="stat-label">This Week</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-item">
                        <div class="stat-number" data-count="<?= $stats['month_activities'] ?>">0</div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <!-- Action Buttons -->
    <div class="action-buttons animate-on-scroll">
        <button onclick="exportLogs()" class="btn btn-outline-gradient">
            <i class="bi bi-download me-2"></i>Export Logs
        </button>
        <button onclick="clearOldLogs()" class="btn btn-outline-gradient">
            <i class="bi bi-trash me-2"></i>Clear Old Logs
        </button>
        <button onclick="refreshLogs()" class="btn btn-gradient">
            <i class="bi bi-arrow-clockwise me-2"></i>Refresh
        </button>
    </div>

    <!-- Search & Filter Section -->
    <div class="filters-section animate-on-scroll">
        <h5 class="filters-title">
            <i class="bi bi-funnel me-2"></i>Filter Activity Log
        </h5>
        <form method="GET" class="row gy-3 gx-3 align-items-end">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Search Activities</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by action description..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Filter by Type</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-filter"></i></span>
                    <select name="filter" class="form-select">
                        <option value="all" <?= $filter==='all'?'selected':''; ?>>All Activities</option>
                        <option value="book" <?= $filter==='book'?'selected':''; ?>>Book Actions</option>
                        <option value="login" <?= $filter==='login'?'selected':''; ?>>Login/Logout</option>
                        <option value="settings" <?= $filter==='settings'?'selected':''; ?>>Settings</option>
                        <option value="export" <?= $filter==='export'?'selected':''; ?>>Exports</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <button class="btn btn-gradient w-100">
                    <i class="bi bi-search me-2"></i>Apply Filters
                </button>
            </div>
        </form>
        
        <!-- Filter Badges -->
        <div class="filter-badges">
            <a href="?filter=all" class="filter-badge <?= $filter==='all'?'active':'' ?>">
                <i class="bi bi-list me-1"></i>All
            </a>
            <a href="?filter=book" class="filter-badge <?= $filter==='book'?'active':'' ?>">
                <i class="bi bi-book me-1"></i>Books
            </a>
            <a href="?filter=login" class="filter-badge <?= $filter==='login'?'active':'' ?>">
                <i class="bi bi-box-arrow-in-right me-1"></i>Authentication
            </a>
            <a href="?filter=settings" class="filter-badge <?= $filter==='settings'?'active':'' ?>">
                <i class="bi bi-gear me-1"></i>Settings
            </a>
            <a href="?filter=export" class="filter-badge <?= $filter==='export'?'active':'' ?>">
                <i class="bi bi-download me-1"></i>Exports
            </a>
        </div>
    </div>

    <!-- Activity Timeline -->
    <div class="activity-container animate-on-scroll">
        <div class="activity-header">
            <h5 class="activity-title mb-0">
                <i class="bi bi-activity me-2"></i>
                Recent Activities
                <?php if($search): ?>
                    <small class="text-muted">- Results for "<?= htmlspecialchars($search) ?>"</small>
                <?php endif; ?>
            </h5>
            <span class="badge bg-primary rounded-pill px-3 py-2">
                <?= number_format($totalLogs) ?> total activities
            </span>
        </div>

        <?php if(empty($logs)): ?>
            <div class="empty-state">
                <i class="bi bi-clock-history empty-state-icon"></i>
                <h3 class="empty-state-title">No Activities Found</h3>
                <p class="empty-state-text">
                    <?php if($search || $filter !== 'all'): ?>
                        No activities match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        Your activity log is empty. Start using the platform to see your activities here.
                    <?php endif; ?>
                </p>
                <a href="seller_dashboard.php" class="btn btn-gradient">
                    <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="activity-timeline">
                <?php 
                $currentDate = '';
                foreach($logs as $index => $log): 
                    $details = getActivityDetails($log['action']);
                    $logDate = date('Y-m-d', strtotime($log['timestamp']));
                    $displayDate = date('F j, Y', strtotime($log['timestamp']));
                    $timeAgo = timeAgo($log['timestamp']);
                    
                    // Show date separator
                    if($currentDate !== $logDate):
                        $currentDate = $logDate;
                        if($index > 0): ?>
                            <div class="date-separator mt-4 mb-3">
                                <hr class="my-2" style="border-color: rgba(102, 126, 234, 0.2);">
                            </div>
                        <?php endif; ?>
                        <div class="date-header mb-3">
                            <h6 class="text-muted fw-bold">
                                <i class="bi bi-calendar3 me-2"></i>
                                <?= $displayDate ?>
                                <?php if($logDate === date('Y-m-d')): ?>
                                    <span class="badge bg-success ms-2">Today</span>
                                <?php elseif($logDate === date('Y-m-d', strtotime('-1 day'))): ?>
                                    <span class="badge bg-warning ms-2">Yesterday</span>
                                <?php endif; ?>
                            </h6>
                        </div>
                    <?php endif; ?>
                    
                    <div class="activity-item" data-category="<?= $details['category'] ?>">
                        <div class="activity-icon bg-<?= $details['color'] ?>"></div>
                        <div class="activity-content">
                            <div class="activity-icon-badge bg-<?= $details['color'] ?> text-white">
                                <i class="<?= $details['icon'] ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-action"><?= htmlspecialchars($log['action']) ?></div>
                                <div class="activity-time">
                                    <i class="bi bi-clock me-1"></i>
                                    <?= date('g:i A', strtotime($log['timestamp'])) ?>
                                    <span class="text-muted">• <?= $timeAgo ?></span>
                                </div>
                                <span class="activity-category bg-<?= $details['color'] ?> text-white">
                                    <?= $details['category'] ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if($totalPages > 1): ?>
            <nav class="pagination-nav">
                <ul class="pagination">
                    <?php if($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $page-1 ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                
                    <?php 
                    $startPage = max(1, min($page - 2, $totalPages - 4));
                    $endPage = min($totalPages, max($page + 2, 5));
                    
                    if ($startPage > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($search) . '&filter=' . urlencode($filter) . '&page=1">1</a></li>';
                        if ($startPage > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for($i = $startPage; $i <= $endPage; $i++): 
                    ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; 
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?search=' . urlencode($search) . '&filter=' . urlencode($filter) . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
                    }
                    ?>
                    
                    <?php if($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?search=<?= urlencode($search) ?>&filter=<?= urlencode($filter) ?>&page=<?= $page+1 ?>">
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
<div class="footer">
    <div class="container">
        <p class="mb-0">&copy; <?= date('Y') ?> BookStore Seller Hub. All rights reserved.</p>
        <small class="opacity-75">Your activities are securely logged and monitored</small>
    </div>
</div>

<!-- Clear Logs Confirmation Modal -->
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Clear Old Logs</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-3">This will permanently delete activity logs older than:</p>
                <div class="mb-3">
                    <select class="form-select" id="clearDuration">
                        <option value="30">30 days</option>
                        <option value="60">60 days</option>
                        <option value="90" selected>90 days</option>
                        <option value="180">180 days</option>
                        <option value="365">1 year</option>
                    </select>
                </div>
                <div class="alert alert-warning">
                    <i class="bi bi-warning me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
            </div>
            <div class="modal-footer" style="border: none; padding: 1.5rem;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmClearLogs()">
                    <i class="bi bi-trash me-2"></i>Clear Logs
                </button>
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

// Initialize animations when page loads
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        document.querySelectorAll('[data-count]').forEach(element => {
            const targetValue = parseInt(element.getAttribute('data-count'));
            if (targetValue > 0) {
                animateValue(element, 0, targetValue, 1500);
            }
        });
    }, 500);
});

// Export logs function
function exportLogs() {
    const filter = '<?= $filter ?>';
    const search = '<?= $search ?>';
    
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Action,Category,Date,Time\n";
    
    // Add log data (you would get this from PHP)
    const logs = <?= json_encode($logs) ?>;
    logs.forEach(log => {
        const details = getActivityDetailsJS(log.action);
        const date = new Date(log.timestamp);
        csvContent += `"${log.action}","${details.category}","${date.toDateString()}","${date.toTimeString()}"\n`;
    });
    
    // Create and download file
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `activity_log_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showToast('Activity logs exported successfully!', 'success');
}

// Clear old logs function
function clearOldLogs() {
    const modal = new bootstrap.Modal(document.getElementById('clearLogsModal'));
    modal.show();
}

function confirmClearLogs() {
    const duration = document.getElementById('clearDuration').value;
    
    // Here you would make an AJAX call to delete old logs
    fetch('clear_old_logs.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ days: duration })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(`Logs older than ${duration} days cleared successfully!`, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast('Failed to clear logs. Please try again.', 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred. Please try again.', 'error');
    });
    
    const modal = bootstrap.Modal.getInstance(document.getElementById('clearLogsModal'));
    modal.hide();
}

// Refresh logs function
function refreshLogs() {
    showToast('Refreshing activity logs...', 'info');
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// JavaScript version of getActivityDetails function
function getActivityDetailsJS(action) {
    const actionLower = action.toLowerCase();
    
    if (actionLower.includes('login') || actionLower.includes('logged')) {
        return { category: 'Authentication', color: 'success' };
    } else if (actionLower.includes('logout')) {
        return { category: 'Authentication', color: 'secondary' };
    } else if (actionLower.includes('added') || actionLower.includes('created')) {
        return { category: 'Creation', color: 'success' };
    } else if (actionLower.includes('updated') || actionLower.includes('edited')) {
        return { category: 'Modification', color: 'warning' };
    } else if (actionLower.includes('deleted') || actionLower.includes('removed')) {
        return { category: 'Deletion', color: 'danger' };
    } else if (actionLower.includes('export')) {
        return { category: 'Export', color: 'info' };
    } else if (actionLower.includes('book')) {
        return { category: 'Books', color: 'primary' };
    } else if (actionLower.includes('settings') || actionLower.includes('profile')) {
        return { category: 'Settings', color: 'secondary' };
    } else {
        return { category: 'General', color: 'primary' };
    }
}

// Toast notification helper
function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.style.borderRadius = '15px';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'} me-2"></i>
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        toastContainer.removeChild(toast);
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

// Real-time activity updates (WebSocket simulation)
function startRealTimeUpdates() {
    // This would normally use WebSocket or Server-Sent Events
    setInterval(() => {
        // Check for new activities
        fetch('check_new_activities.php')
        .then(response => response.json())
        .then(data => {
            if (data.hasNewActivities) {
                showToast('New activities detected. Click refresh to see them.', 'info');
            }
        })
        .catch(error => {
            // Silently handle errors for background updates
        });
    }, 30000); // Check every 30 seconds
}

// Initialize real-time updates
// startRealTimeUpdates();

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey || e.metaKey) {
        switch(e.key) {
            case 'r':
                e.preventDefault();
                refreshLogs();
                break;
            case 'e':
                e.preventDefault();
                exportLogs();
                break;
            case 'f':
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
                break;
        }
    }
});

// Show keyboard shortcuts hint
document.addEventListener('DOMContentLoaded', function() {
    const hint = document.createElement('div');
    hint.className = 'position-fixed bottom-0 start-0 p-3 text-white small';
    hint.style.zIndex = '1000';
    hint.style.background = 'rgba(0,0,0,0.7)';
    hint.style.borderRadius = '10px';
    hint.innerHTML = `
        <strong>Keyboard Shortcuts:</strong><br>
        Ctrl+R: Refresh • Ctrl+E: Export • Ctrl+F: Search
    `;
    hint.style.opacity = '0.7';
    hint.style.transition = 'opacity 0.3s';
    
    document.body.appendChild(hint);
    
    // Hide after 5 seconds
    setTimeout(() => {
        hint.style.opacity = '0';
        setTimeout(() => document.body.removeChild(hint), 300);
    }, 5000);
});
</script>

</body>
</html>

<?php
// Helper function for time ago
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}
?>