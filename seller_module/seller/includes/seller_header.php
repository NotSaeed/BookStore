<?php

session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

// Get page name for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread notifications count (example)
// You would replace this with your actual notification system
$unread_notifications = 0; // Replace with actual count from database
$has_notifications = ($unread_notifications > 0);

// Get seller name initial for avatar
$seller_initial = !empty($_SESSION['seller_name']) ? strtoupper(substr($_SESSION['seller_name'], 0, 1)) : 'S';

// Get user preferences (Dark mode, etc.)
$user_preferences = [
    'dark_mode' => $_COOKIE['dark_mode'] ?? false,
    'compact_view' => $_COOKIE['compact_view'] ?? false,
    'sidebar_collapsed' => $_COOKIE['sidebar_collapsed'] ?? false
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $user_preferences['dark_mode'] ? 'dark' : 'light' ?>">
<head>
    <meta charset="UTF-8" />
    <title><?= isset($page_title) ? $page_title . ' | BookStore Seller Hub' : 'BookStore Seller Hub' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="description" content="BookStore Seller Hub - Manage your book inventory and sales efficiently">
    <meta name="theme-color" content="#198754">
    
    <!-- Open Graph Meta Tags for Social Sharing -->
    <meta property="og:title" content="<?= isset($page_title) ? $page_title . ' | BookStore Seller Hub' : 'BookStore Seller Hub' ?>">
    <meta property="og:description" content="BookStore Seller Hub - Manage your book inventory and sales efficiently">
    <meta property="og:type" content="website">
    <meta property="og:image" content="../assets/images/og-image.jpg">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="../assets/images/apple-touch-icon.png">
      <!-- Bootstrap and Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/bootstrap-enhanced.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Custom Styles -->
    <style>
        :root {
            --bs-primary: #198754;
            --bs-primary-dark: #146c43;
            --bs-primary-rgb: 25, 135, 84;
            --bs-primary-subtle: rgba(25, 135, 84, 0.1);
            --bs-secondary-subtle: rgba(108, 117, 125, 0.1);
            --bs-navbar-active-color: white;
            --bs-body-font-family: 'Inter', system-ui, -apple-system, sans-serif;
            --bs-body-font-size: 0.95rem;
            --book-bg-blur: 10px;
            --book-bg-overlay: rgba(0, 0, 0, 0.7);
            --card-bg-opacity: 0.95;
            
            /* Animation durations */
            --trans-normal: 0.2s;
            --trans-slow: 0.4s;
        }
        
        [data-bs-theme="dark"] {
            --bs-navbar-active-color: #ffffff;
            --book-bg-overlay: rgba(0, 0, 0, 0.85);
            --card-bg-opacity: 0.95;
            color-scheme: dark;
        }
        
        @media (prefers-color-scheme: dark) {
            [data-bs-theme="auto"] {
                --bs-navbar-active-color: #ffffff;
                --book-bg-overlay: rgba(0, 0, 0, 0.85);
                --card-bg-opacity: 0.95;
                color-scheme: dark;
            }
        }
        
        /* Import Inter font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            background-image: url('https://images.unsplash.com/photo-1507842217343-583bb7270b66?ixlib=rb-1.2.1');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-color: var(--book-bg-overlay);
            background-blend-mode: overlay;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color var(--trans-slow);
        }
        
        .navbar {
            backdrop-filter: blur(var(--book-bg-blur));
            -webkit-backdrop-filter: blur(var(--book-bg-blur)); 
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
            transition: all var(--trans-normal);
        }
        
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
        }
        
        .logo-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all var(--trans-normal);
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
        }
        
        .nav-link i {
            margin-right: 0.5rem;
        }
        
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.25);
            font-weight: 600;
        }
        
        .content-wrapper {
            flex: 1;
            padding: 2rem 0;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--bs-primary), var(--bs-primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
            transition: transform var(--trans-normal);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.5);
        }
        
        .avatar:hover {
            transform: scale(1.05);
        }
        
        .dropdown-menu {
            border: none;
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15);
            overflow: hidden;
            padding: 0.5rem;
            animation: dropdown-animation 0.2s ease;
        }
        
        @keyframes dropdown-animation {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            border-radius: 0.5rem;
            transition: all var(--trans-normal);
            margin-bottom: 0.125rem;
        }
        
        .dropdown-item i {
            margin-right: 0.75rem;
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }
        
        .dropdown-item:hover {
            background-color: var(--bs-primary-subtle);
            color: var(--bs-primary);
        }
        
        .dropdown-item:active {
            background-color: var(--bs-primary);
            color: white;
        }
        
        .dropdown-header {
            padding: 0.75rem 1rem;
            background-color: var(--bs-secondary-subtle);
            margin-top: -0.5rem;
            margin-bottom: 0.5rem;
            border-top-left-radius: 0.65rem;
            border-top-right-radius: 0.65rem;
        }
        
        .welcome-banner {
            background-color: rgba(255, 255, 255, var(--card-bg-opacity));
            border-left: 4px solid var(--bs-primary);
            border-radius: 0.75rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            transition: all var(--trans-normal);
        }
        
        [data-bs-theme="dark"] .welcome-banner {
            background-color: rgba(33, 37, 41, var(--card-bg-opacity));
        }
        
        .welcome-banner .btn {
            transition: all var(--trans-normal);
        }
        
        .welcome-banner .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 0.75rem rgba(0, 0, 0, 0.15);
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(25%, -25%);
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 0.25rem 0.45rem;
            font-size: 0.7rem;
            font-weight: bold;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .search-form {
            position: relative;
        }
        
        .search-form .form-control {
            padding-left: 2.5rem;
            border-radius: 50px;
            border: none;
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            transition: all var(--trans-normal);
            width: 200px;
        }
        
        .search-form .form-control:focus {
            width: 280px;
            background-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.25);
        }
        
        .search-form .bi-search {
            position: absolute;
            top: 50%;
            left: 1rem;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            transition: all var(--trans-normal);
        }
        
        .search-form .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        @media (max-width: 991.98px) {
            .navbar-nav.me-auto {
                margin-bottom: 1rem;
            }
            
            .search-form {
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .search-form .form-control,
            .search-form .form-control:focus {
                width: 100%;
            }
        }
        
        .btn {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all var(--trans-normal);
        }
        
        .btn-success {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            box-shadow: 0 0.25rem 0.5rem rgba(var(--bs-primary-rgb), 0.15);
        }
        
        .btn-success:hover {
            background-color: var(--bs-primary-dark);
            border-color: var(--bs-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 0.375rem 0.75rem rgba(var(--bs-primary-rgb), 0.2);
        }
        
        .btn-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 50%;
        }
        
        .dropdown-menu-notification {
            width: 320px;
            padding: 0;
        }
        
        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--bs-secondary-subtle);
            transition: all var(--trans-normal);
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item:hover {
            background-color: var(--bs-secondary-subtle);
        }
        
        .notification-item.unread {
            background-color: var(--bs-primary-subtle);
        }
        
        .notification-item.unread:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.2);
        }
        
        .notification-content {
            font-size: 0.875rem;
        }
        
        .notification-time {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .notification-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--bs-primary);
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        /* Dark mode toggle */
        .theme-toggle {
            cursor: pointer;
            border: none;
            background: transparent;
            color: white;
            padding: 0;
            font-size: 1.5rem;
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 50%;
            transition: all var(--trans-normal);
        }
        
        .theme-toggle:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        /* Helper class for focus accessibility */
        .focus-ring:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25) !important;
        }
        
        [data-bs-theme="dark"] .theme-toggle .bi-sun,
        [data-bs-theme="light"] .theme-toggle .bi-moon {
            display: block;
        }
        
        [data-bs-theme="dark"] .theme-toggle .bi-moon,
        [data-bs-theme="light"] .theme-toggle .bi-sun {
            display: none;
        }
        
        /* Enhance accessibility */
        .nav-link:focus, 
        .navbar-brand:focus,
        .dropdown-item:focus {
            outline: none;
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25) !important;
        }
        
        /* Compact view */
        body.compact-view {
            --bs-body-font-size: 0.875rem;
        }
        
        body.compact-view .navbar {
            padding-top: 0.25rem;
            padding-bottom: 0.25rem;
        }
        
        body.compact-view .welcome-banner {
            padding: 0.75rem !important;
            margin-bottom: 1.25rem;
        }
        
        body.compact-view .content-wrapper {
            padding: 1.5rem 0;
        }
        
        /* Tooltip enhancement */
        .tooltip {
            --bs-tooltip-bg: var(--bs-primary);
            --bs-tooltip-opacity: 0.95;
        }
        
        /* Status indicator for user dropdown */
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #10b981;
            border: 2px solid white;
            position: absolute;
            bottom: 2px;
            right: 2px;
        }
        
        /* Improve dropdown header */
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info .avatar {
            width: 48px;
            height: 48px;
            font-size: 1.25rem;
            margin-right: 12px;
        }
    </style>
    
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body class="<?= $user_preferences['compact_view'] ? 'compact-view' : '' ?>">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
    <div class="container">
        <a class="navbar-brand focus-ring" href="seller_dashboard.php">
            <span class="logo-icon"><i class="bi bi-shop"></i></span>
            <span class="brand-text">BookStore Seller Hub</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link focus-ring <?= $current_page === 'seller_dashboard.php' ? 'active' : '' ?>" href="seller_dashboard.php" aria-current="<?= $current_page === 'seller_dashboard.php' ? 'page' : 'false' ?>">
                        <i class="bi bi-house"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link focus-ring <?= $current_page === 'seller_manage_books.php' ? 'active' : '' ?>" href="seller_manage_books.php" aria-current="<?= $current_page === 'seller_manage_books.php' ? 'page' : 'false' ?>">
                        <i class="bi bi-collection"></i>My Books
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['seller_add_book.php', 'seller_import_books.php', 'seller_bulk_edit.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-plus-circle"></i>Add Books
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= $current_page === 'seller_add_book.php' ? 'active' : '' ?>" href="seller_add_book.php"><i class="bi bi-plus-lg"></i> Add Single Book</a></li>
                        <li><a class="dropdown-item <?= $current_page === 'seller_import_books.php' ? 'active' : '' ?>" href="seller_import_books.php"><i class="bi bi-file-earmark-arrow-up"></i> Import Books</a></li>
                        <li><a class="dropdown-item <?= $current_page === 'seller_bulk_edit.php' ? 'active' : '' ?>" href="seller_bulk_edit.php"><i class="bi bi-pencil-square"></i> Bulk Edit</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= in_array($current_page, ['seller_analytics.php', 'seller_reports.php', 'seller_sales.php']) ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-graph-up"></i>Analytics
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?= $current_page === 'seller_analytics.php' ? 'active' : '' ?>" href="seller_analytics.php"><i class="bi bi-pie-chart"></i> Dashboard</a></li>
                        <li><a class="dropdown-item <?= $current_page === 'seller_reports.php' ? 'active' : '' ?>" href="seller_reports.php"><i class="bi bi-file-earmark-bar-graph"></i> Reports</a></li>
                        <li><a class="dropdown-item <?= $current_page === 'seller_sales.php' ? 'active' : '' ?>" href="seller_sales.php"><i class="bi bi-cart-check"></i> Sales</a></li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link focus-ring <?= $current_page === 'seller_settings.php' ? 'active' : '' ?>" href="seller_settings.php" aria-current="<?= $current_page === 'seller_settings.php' ? 'page' : 'false' ?>">
                        <i class="bi bi-gear"></i>Settings
                    </a>
                </li>
            </ul>
            
            <!-- Search Box -->
            <form class="search-form me-3 d-flex" action="seller_search.php" method="GET">
                <i class="bi bi-search"></i>
                <input type="search" name="q" class="form-control focus-ring" placeholder="Search books..." aria-label="Search" autocomplete="off">
            </form>
            
            <div class="d-flex align-items-center">
                <!-- Theme Toggle -->
                <button type="button" class="theme-toggle me-3 focus-ring" id="themeToggle" aria-label="Toggle dark mode" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Toggle dark mode">
                    <i class="bi bi-sun"></i>
                    <i class="bi bi-moon"></i>
                </button>
                
                <!-- Notifications Dropdown -->
                <div class="nav-item dropdown me-3">
                    <a class="nav-link text-light position-relative focus-ring" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($has_notifications): ?>
                            <span class="notification-badge" role="status" aria-live="polite"><?= $unread_notifications ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-notification p-0" aria-labelledby="notificationsDropdown">
                        <div class="dropdown-header d-flex justify-content-between align-items-center">
                            <span>Notifications</span>
                            <a href="#" class="text-decoration-none small text-primary">Mark all as read</a>
                        </div>
                        
                        <!-- Example notifications - Replace with actual ones from your system -->
                        <div class="notification-item unread">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold"><span class="notification-dot"></span>New Order</span>
                                <span class="notification-time">2h ago</span>
                            </div>
                            <div class="notification-content">
                                You received a new order for "The Great Gatsby"
                            </div>
                        </div>
                        
                        <div class="notification-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold">System Update</span>
                                <span class="notification-time">Yesterday</span>
                            </div>
                            <div class="notification-content">
                                The platform has been updated with new features
                            </div>
                        </div>
                        
                        <div class="notification-item">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-semibold">Welcome</span>
                                <span class="notification-time">3d ago</span>
                            </div>
                            <div class="notification-content">
                                Welcome to BookStore Seller Hub! Get started by adding your books.
                            </div>
                        </div>
                        
                        <div class="text-center p-2 border-top">
                            <a href="seller_notifications.php" class="text-decoration-none text-primary small">View all notifications</a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="nav-item dropdown me-3">
                    <a class="nav-link text-light focus-ring" href="#" id="quickActions" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Quick actions" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Quick actions">
                        <i class="bi bi-lightning-charge fs-5"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-0" aria-labelledby="quickActions">
                        <div class="dropdown-header">Quick Actions</div>
                        <a href="seller_add_book.php" class="dropdown-item">
                            <i class="bi bi-plus-circle"></i> Add New Book
                        </a>
                        <a href="export_books_excel.php" class="dropdown-item">
                            <i class="bi bi-file-earmark-excel"></i> Export to Excel
                        </a>
                        <a href="export_books_pdf.php" class="dropdown-item">
                            <i class="bi bi-file-earmark-pdf"></i> Export to PDF
                        </a>
                        <a href="seller_analytics.php" class="dropdown-item">
                            <i class="bi bi-graph-up"></i> View Analytics
                        </a>
                    </div>
                </div>
                
                <!-- Help Button -->
                <div class="nav-item me-3">
                    <a href="seller_help.php" class="nav-link text-light focus-ring" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Help & Resources" aria-label="Help and resources">
                        <i class="bi bi-question-circle fs-5"></i>
                    </a>
                </div>
                
                <!-- User Dropdown -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center text-white focus-ring" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User menu">
                        <div class="avatar position-relative">
                            <?= $seller_initial ?>
                            <span class="status-indicator"></span>
                        </div>
                        <span class="d-none d-lg-inline"><?= htmlspecialchars($_SESSION['seller_name']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li class="dropdown-header">
                            <div class="user-info">
                                <div class="avatar">
                                    <?= $seller_initial ?>
                                </div>
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($_SESSION['seller_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($_SESSION['seller_email'] ?? 'Seller') ?></small>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="seller_profile.php"><i class="bi bi-person"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="seller_store.php"><i class="bi bi-shop"></i> My Store</a></li>
                        <li><a class="dropdown-item" href="seller_settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                        <li><a class="dropdown-item" href="seller_activity_log.php"><i class="bi bi-clock-history"></i> Activity Log</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <div class="px-3 py-1">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-muted small">Compact View</span>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="compactViewToggle" <?= $user_preferences['compact_view'] ? 'checked' : '' ?>>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="seller_help.php"><i class="bi bi-question-circle"></i> Help Center</a></li>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#feedbackModal"><i class="bi bi-chat-text"></i> Send Feedback</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="seller_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Welcome Banner (shown on dashboard only) -->
<?php if ($current_page === 'seller_dashboard.php'): ?>
<div class="container mt-4">
    <div class="welcome-banner p-3">
        <div class="d-flex flex-wrap align-items-center">
            <div class="me-3 mb-2 mb-md-0">
                <i class="bi bi-hand-thumbs-up-fill fs-1 text-success"></i>
            </div>
            <div class="mb-3 mb-md-0">
                <h4 class="fw-bold mb-1">Welcome back, <?= htmlspecialchars($_SESSION['seller_name']) ?>!</h4>
                <p class="mb-0 text-muted">It's <?= date('l, F j, Y') ?>. Here's what's happening with your store today.</p>
            </div>
            <div class="ms-md-auto mt-2 mt-md-0 d-flex gap-2">
                <a href="seller_analytics.php" class="btn btn-outline-success">
                    <i class="bi bi-graph-up me-1"></i>View Analytics
                </a>
                <a href="seller_add_book.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-1"></i>Add New Book
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Start main content container -->
<div class="content-wrapper">
    <div class="container">

<!-- Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="feedbackModalLabel">Send Feedback</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <div class="mb-3">
                        <label for="feedbackType" class="form-label">Feedback Type</label>
                        <select class="form-select" id="feedbackType" required>
                            <option value="">Select type...</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="problem">Report a Problem</option>
                            <option value="question">Question</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="feedbackMessage" class="form-label">Message</label>
                        <textarea class="form-control" id="feedbackMessage" rows="4" required placeholder="Please describe your feedback..."></textarea>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="contactConsent">
                        <label class="form-check-label" for="contactConsent">
                            I consent to be contacted regarding this feedback
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitFeedback()">Send Feedback</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast container for notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<script>
// Dark mode toggle
document.addEventListener('DOMContentLoaded', function() {
    // Theme toggle functionality
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const htmlElement = document.documentElement;
            const currentTheme = htmlElement.getAttribute('data-bs-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            htmlElement.setAttribute('data-bs-theme', newTheme);
            
            // Save preference to cookie (30 day expiry)
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + 30);
            document.cookie = `dark_mode=${newTheme === 'dark'}; expires=${expiryDate.toUTCString()}; path=/`;
        });
    }
    
    // Compact view toggle
    const compactViewToggle = document.getElementById('compactViewToggle');
    if (compactViewToggle) {
        compactViewToggle.addEventListener('change', function() {
            document.body.classList.toggle('compact-view', this.checked);
            
            // Save preference to cookie (30 day expiry)
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + 30);
            document.cookie = `compact_view=${this.checked}; expires=${expiryDate.toUTCString()}; path=/`;
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(tooltipTriggerEl => {
        new bootstrap.Tooltip(tooltipTriggerEl, {
            delay: { show: 500, hide: 100 }
        });
    });
});

// Feedback form submission
function submitFeedback() {
    const feedbackType = document.getElementById('feedbackType').value;
    const feedbackMessage = document.getElementById('feedbackMessage').value;
    
    if (!feedbackType || !feedbackMessage) {
        showToast('Please complete all required fields', 'warning');
        return;
    }
    
    // Here you would normally send this to the server via AJAX
    // For now, just log it and show success message
    console.log('Feedback submitted:', {
        type: feedbackType,
        message: feedbackMessage,
        contactConsent: document.getElementById('contactConsent').checked
    });
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('feedbackModal'));
    modal.hide();
    
    // Show success toast
    showToast('Thank you! Your feedback has been submitted.', 'success');
    
    // Reset form
    document.getElementById('feedbackForm').reset();
}

// Toast notification helper
function showToast(message, type = 'success') {
    const toastContainer = document.querySelector('.toast-container');
    const icons = {
        'success': 'bi-check-circle',
        'warning': 'bi-exclamation-triangle',
        'danger': 'bi-x-circle',
        'info': 'bi-info-circle'
    };
    
    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icons[type] || 'bi-bell'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    // Add toast to container
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Get the toast element we just added
    const toastElement = toastContainer.lastElementChild;
    
    // Initialize and show the toast
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    toast.show();
    
    // Remove toast from DOM after it's hidden
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}
</script>