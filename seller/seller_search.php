<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Authentication check
if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$seller_id = intval($_SESSION['seller_id']);
$seller_name = $_SESSION['seller_name'] ?? 'Seller';

// Get search parameters
$query = trim($_GET['q'] ?? "");
$category = $_GET['category'] ?? "";
$price_min = floatval($_GET['price_min'] ?? 0);
$price_max = floatval($_GET['price_max'] ?? 0);
$stock_filter = $_GET['stock_filter'] ?? "";
$status_filter = $_GET['status_filter'] ?? "";
$sort_by = $_GET['sort_by'] ?? "created_at";
$order = $_GET['order'] ?? "desc";
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Enhanced validation for sort options
$allowed_sort = [
    "title" => "title",
    "author" => "author", 
    "price" => "price",
    "stock" => "stock_quantity",
    "created_at" => "created_at",
    "updated_at" => "updated_at",
    "views" => "view_count",
    "sales" => "sales_count"
];

$allowed_order = ["asc", "desc"];
$sort_col = $allowed_sort[$sort_by] ?? "created_at";
$sort_order = in_array($order, $allowed_order) ? $order : "desc";

// Build dynamic query
$search_conditions = ["seller_id = ?"];
$search_params = [$seller_id];
$param_types = "i";

// Text search
if (!empty($query)) {
    $search_conditions[] = "(title LIKE ? OR author LIKE ? OR description LIKE ? OR isbn LIKE ?)";
    $search_term = "%" . $query . "%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
}

// Category filter
if (!empty($category)) {
    $search_conditions[] = "category = ?";
    $search_params[] = $category;
    $param_types .= "s";
}

// Price range filter
if ($price_min > 0) {
    $search_conditions[] = "price >= ?";
    $search_params[] = $price_min;
    $param_types .= "d";
}

if ($price_max > 0) {
    $search_conditions[] = "price <= ?";
    $search_params[] = $price_max;
    $param_types .= "d";
}

// Stock filter
switch ($stock_filter) {
    case 'in_stock':
        $search_conditions[] = "stock_quantity > 0";
        break;
    case 'out_of_stock':
        $search_conditions[] = "stock_quantity = 0";
        break;
    case 'low_stock':
        $search_conditions[] = "stock_quantity > 0 AND stock_quantity <= 5";
        break;
}

// Status filter
switch ($status_filter) {
    case 'public':
        $search_conditions[] = "is_public = 1";
        break;
    case 'private':
        $search_conditions[] = "is_public = 0";
        break;
    case 'featured':
        $search_conditions[] = "is_featured = 1";
        break;
}

$where_clause = implode(" AND ", $search_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM seller_books WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param($param_types, ...$search_params);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_results / $per_page);

// Get search results
$search_results = [];
if ($total_results > 0) {    $main_sql = "SELECT 
        book_id, title, author, price, stock_quantity, cover_image,
        category, description, isbn, is_public, is_featured,
        created_at, updated_at,
        COALESCE(view_count, 0) as view_count,
        COALESCE(sales_count, 0) as sales_count
    FROM seller_books 
    WHERE $where_clause 
    ORDER BY $sort_col $sort_order 
    LIMIT ? OFFSET ?";
    
    $main_stmt = $conn->prepare($main_sql);
    $search_params[] = $per_page;
    $search_params[] = $offset;
    $param_types .= "ii";
    
    $main_stmt->bind_param($param_types, ...$search_params);
    $main_stmt->execute();
    $search_results = $main_stmt->get_result();
    $main_stmt->close();
}

// Get categories for filter dropdown
$categories_sql = "SELECT DISTINCT category FROM seller_books WHERE seller_id = ? AND category IS NOT NULL AND category != '' ORDER BY category";
$cat_stmt = $conn->prepare($categories_sql);
$cat_stmt->bind_param("i", $seller_id);
$cat_stmt->execute();
$categories = $cat_stmt->get_result();
$cat_stmt->close();

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_books,
    COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_books,    COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_books,
    COUNT(CASE WHEN stock_quantity = 0 THEN 1 END) as out_of_stock,
    AVG(price) as avg_price,
    SUM(COALESCE(view_count, 0)) as total_views,
    SUM(COALESCE(sales_count, 0)) as total_sales
FROM seller_books WHERE seller_id = ?";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("i", $seller_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Book Search - BookStore Seller Hub</title>
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="seller_style.css" rel="stylesheet">
    
    <!-- Meta tags -->
    <meta name="description" content="Advanced search and filtering for your book inventory">
    <meta name="robots" content="noindex, nofollow">
    
    <style>
        .search-container {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
        }
        
        .search-header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem 0;
        }
        
        .search-title {
            color: white;
            font-weight: 800;
            text-align: center;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
        }
        
        .search-subtitle {
            color: rgba(255, 255, 255, 0.9);
            text-align: center;
            font-size: 1.1rem;
        }
        
        .filters-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem;
            border-radius: 1rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .book-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .book-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .book-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .book-cover {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-300) 100%);
        }
        
        .book-info {
            padding: 1.5rem;
        }
        
        .book-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .book-author {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .book-meta {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }
        
        .book-price {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--success-color);
            margin-bottom: 1rem;
        }
        
        .book-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .badge-public {
            background: var(--success-light);
            color: var(--success-color);
        }
        
        .badge-private {
            background: var(--gray-200);
            color: var(--gray-600);
        }
        
        .badge-featured {
            background: var(--warning-light);
            color: var(--warning-color);
        }
        
        .badge-out-of-stock {
            background: var(--danger-light);
            color: var(--danger-color);
        }
        
        .search-form {
            background: white;
            padding: 2rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .advanced-filters {
            margin-top: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .filter-section {
            margin-bottom: 1.5rem;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .no-results-icon {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 1rem;
        }
        
        .pagination-custom {
            background: rgba(255, 255, 255, 0.95);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .quick-filters {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        
        .quick-filter {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: 9999px;
            background: white;
            color: var(--gray-700);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .quick-filter:hover,
        .quick-filter.active {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
            text-decoration: none;
        }
        
        .results-summary {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }
        
        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }
        
        .view-btn {
            padding: 0.5rem;
            border: 2px solid var(--gray-300);
            border-radius: 0.5rem;
            background: white;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .view-btn.active,
        .view-btn:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
        
        .list-view .book-grid {
            display: block;
        }
        
        .list-view .book-card {
            display: flex;
            margin-bottom: 1rem;
            border-radius: 1rem;
        }
        
        .list-view .book-cover {
            width: 120px;
            height: 160px;
            flex-shrink: 0;
        }
        
        .list-view .book-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        @media (max-width: 768px) {
            .search-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .book-grid {
                grid-template-columns: 1fr;
            }
            
            .results-summary {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .quick-filters {
                justify-content: center;
            }
        }
    </style>
</head>

<body class="search-container">
    <!-- Header -->
    <div class="search-header">
        <div class="container">
            <h1 class="search-title">
                <i class="bi bi-search"></i>
                Advanced Book Search
            </h1>
            <p class="search-subtitle">
                Discover and manage your book inventory with powerful search and filtering
            </p>
        </div>
    </div>

    <div class="container py-4">
        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_books']) ?></div>
                <div class="stat-label">Total Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['public_books']) ?></div>
                <div class="stat-label">Public Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['featured_books']) ?></div>
                <div class="stat-label">Featured Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">RM <?= number_format($stats['avg_price'] ?? 0, 2) ?></div>
                <div class="stat-label">Avg Price</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_views']) ?></div>
                <div class="stat-label">Total Views</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['total_sales']) ?></div>
                <div class="stat-label">Total Sales</div>
            </div>
        </div>

        <!-- Search Form -->
        <div class="filters-card">
            <div class="search-form">
                <form method="GET" id="searchForm">
                    <!-- Main Search -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="text" 
                                       name="q" 
                                       value="<?= htmlspecialchars($query) ?>" 
                                       class="form-control border-start-0" 
                                       placeholder="Search by title, author, description, or ISBN..."
                                       id="searchInput">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select name="sort_by" class="form-select form-select-lg">
                                <option value="created_at" <?= $sort_by === "created_at" ? "selected" : "" ?>>📅 Date Added</option>
                                <option value="updated_at" <?= $sort_by === "updated_at" ? "selected" : "" ?>>🔄 Last Updated</option>
                                <option value="title" <?= $sort_by === "title" ? "selected" : "" ?>>📚 Title</option>
                                <option value="author" <?= $sort_by === "author" ? "selected" : "" ?>>✍️ Author</option>
                                <option value="price" <?= $sort_by === "price" ? "selected" : "" ?>>💰 Price</option>
                                <option value="stock" <?= $sort_by === "stock" ? "selected" : "" ?>>📦 Stock</option>
                                <option value="views" <?= $sort_by === "views" ? "selected" : "" ?>>👁️ Views</option>
                                <option value="sales" <?= $sort_by === "sales" ? "selected" : "" ?>>🛒 Sales</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="btn-group w-100" role="group">
                                <input type="radio" name="order" value="asc" id="asc" <?= $order === "asc" ? "checked" : "" ?> class="btn-check">
                                <label class="btn btn-outline-primary" for="asc" title="Ascending">
                                    <i class="bi bi-sort-up"></i>
                                </label>
                                <input type="radio" name="order" value="desc" id="desc" <?= $order === "desc" ? "checked" : "" ?> class="btn-check">
                                <label class="btn btn-outline-primary" for="desc" title="Descending">
                                    <i class="bi bi-sort-down"></i>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Filters -->
                    <div class="quick-filters">
                        <a href="?q=<?= urlencode($query) ?>&sort_by=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="quick-filter <?= empty($status_filter) && empty($stock_filter) ? 'active' : '' ?>">
                            All Books
                        </a>
                        <a href="?q=<?= urlencode($query) ?>&status_filter=public&sort_by=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="quick-filter <?= $status_filter === 'public' ? 'active' : '' ?>">
                            Public Only
                        </a>
                        <a href="?q=<?= urlencode($query) ?>&status_filter=featured&sort_by=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="quick-filter <?= $status_filter === 'featured' ? 'active' : '' ?>">
                            Featured
                        </a>
                        <a href="?q=<?= urlencode($query) ?>&stock_filter=out_of_stock&sort_by=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="quick-filter <?= $stock_filter === 'out_of_stock' ? 'active' : '' ?>">
                            Out of Stock
                        </a>
                        <a href="?q=<?= urlencode($query) ?>&stock_filter=low_stock&sort_by=<?= $sort_by ?>&order=<?= $order ?>" 
                           class="quick-filter <?= $stock_filter === 'low_stock' ? 'active' : '' ?>">
                            Low Stock
                        </a>
                    </div>

                    <!-- Advanced Filters -->
                    <div class="advanced-filters" id="advancedFilters" style="display: none;">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="filter-section">
                                    <label class="filter-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="">All Categories</option>
                                        <?php while ($cat = $categories->fetch_assoc()): ?>
                                            <option value="<?= htmlspecialchars($cat['book_category']) ?>" 
                                                    <?= $category === $cat['book_category'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat['book_category']) ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="filter-section">
                                    <label class="filter-label">Price Range</label>
                                    <div class="row g-2">
                                        <div class="col">
                                            <input type="number" name="price_min" value="<?= $price_min > 0 ? $price_min : '' ?>" 
                                                   class="form-control" placeholder="Min RM" step="0.01" min="0">
                                        </div>
                                        <div class="col">
                                            <input type="number" name="price_max" value="<?= $price_max > 0 ? $price_max : '' ?>" 
                                                   class="form-control" placeholder="Max RM" step="0.01" min="0">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="filter-section">
                                    <label class="filter-label">Stock Status</label>
                                    <select name="stock_filter" class="form-select">
                                        <option value="">All Stock Levels</option>
                                        <option value="in_stock" <?= $stock_filter === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                                        <option value="low_stock" <?= $stock_filter === 'low_stock' ? 'selected' : '' ?>>Low Stock (≤5)</option>
                                        <option value="out_of_stock" <?= $stock_filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <div class="filter-section">
                                    <label class="filter-label">Visibility Status</label>
                                    <select name="status_filter" class="form-select">
                                        <option value="">All Statuses</option>
                                        <option value="public" <?= $status_filter === 'public' ? 'selected' : '' ?>>Public</option>
                                        <option value="private" <?= $status_filter === 'private' ? 'selected' : '' ?>>Private</option>
                                        <option value="featured" <?= $status_filter === 'featured' ? 'selected' : '' ?>>Featured</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="filter-section">
                                    <label class="filter-label">Results Per Page</label>
                                    <select name="per_page" class="form-select" onchange="this.form.submit()">
                                        <option value="12" <?= $per_page === 12 ? 'selected' : '' ?>>12 per page</option>
                                        <option value="24" <?= $per_page === 24 ? 'selected' : '' ?>>24 per page</option>
                                        <option value="48" <?= $per_page === 48 ? 'selected' : '' ?>>48 per page</option>
                                        <option value="96" <?= $per_page === 96 ? 'selected' : '' ?>>96 per page</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div>
                            <button type="submit" class="btn btn-primary btn-lg me-2">
                                <i class="bi bi-search"></i> Search
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="toggleAdvanced">
                                <i class="bi bi-sliders"></i> Advanced Filters
                            </button>
                        </div>
                        <div>
                            <a href="seller_search.php" class="btn btn-outline-danger">
                                <i class="bi bi-x-circle"></i> Clear All
                            </a>
                            <a href="seller_dashboard.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Section -->
        <?php if (!empty($query) || !empty($category) || !empty($status_filter) || !empty($stock_filter) || $price_min > 0 || $price_max > 0): ?>
            
            <!-- Results Summary -->
            <div class="results-summary">
                <div>
                    <strong><?= number_format($total_results) ?></strong> books found
                    <?php if (!empty($query)): ?>
                        for "<strong><?= htmlspecialchars($query) ?></strong>"
                    <?php endif; ?>
                    <?php if ($page > 1): ?>
                        (Page <?= $page ?> of <?= $total_pages ?>)
                    <?php endif; ?>
                </div>
                <div class="view-toggle">
                    <button class="view-btn active" id="gridView" title="Grid View">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </button>
                    <button class="view-btn" id="listView" title="List View">
                        <i class="bi bi-list"></i>
                    </button>
                </div>
            </div>

            <?php if ($search_results && $search_results->num_rows > 0): ?>
                <!-- Results Grid -->
                <div class="book-grid" id="resultsContainer">
                    <?php while ($book = $search_results->fetch_assoc()): ?>
                        <div class="book-card" data-book-id="<?= $book['book_id'] ?>">
                            <?php if (!empty($book['book_cover'])): ?>
                                <img src="uploads/<?= htmlspecialchars($book['book_cover']) ?>" 
                                     alt="<?= htmlspecialchars($book['title']) ?>" 
                                     class="book-cover"
                                     onerror="this.src='https://via.placeholder.com/200x250/e5e7eb/6b7280?text=No+Image'">
                            <?php else: ?>
                                <div class="book-cover d-flex align-items-center justify-content-center text-muted">
                                    <i class="bi bi-book" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="book-info">                                <h3 class="book-title"><?= htmlspecialchars($book['title']) ?></h3>
                                <p class="book-author">by <?= htmlspecialchars($book['author']) ?></p>
                                
                                <div class="book-meta">                                    <div><i class="bi bi-box"></i> Stock: <?= $book['stock_quantity'] ?></div>
                                    <div><i class="bi bi-eye"></i> Views: <?= number_format($book['view_count']) ?></div>
                                    <div><i class="bi bi-calendar"></i> Added: <?= date("M d, Y", strtotime($book['created_at'])) ?></div>
                                    <div><i class="bi bi-cart"></i> Sales: <?= number_format($book['sales_count']) ?></div>
                                </div>
                                
                                <div class="book-price">RM <?= number_format($book['price'], 2) ?></div>
                                
                                <div class="book-badges">
                                    <?php if ($book['is_public']): ?>
                                        <span class="status-badge badge-public">Public</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-private">Private</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($book['is_featured']): ?>
                                        <span class="status-badge badge-featured">Featured</span>
                                    <?php endif; ?>
                                      <?php if ($book['stock_quantity'] == 0): ?>
                                        <span class="status-badge badge-out-of-stock">Out of Stock</span>
                                    <?php elseif ($book['stock_quantity'] <= 5): ?>
                                        <span class="status-badge" style="background: var(--warning-light); color: var(--warning-color);">Low Stock</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex gap-2 mt-3">
                                    <a href="seller_edit_book.php?id=<?= $book['book_id'] ?>" 
                                       class="btn btn-primary btn-sm flex-fill">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="seller_view_book.php?id=<?= $book['book_id'] ?>" 
                                       class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-custom">
                        <nav aria-label="Search results pagination">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">
                                            <i class="bi bi-chevron-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                            <i class="bi bi-chevron-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center mt-3 text-muted">
                            Showing <?= (($page - 1) * $per_page) + 1 ?> to <?= min($page * $per_page, $total_results) ?> 
                            of <?= number_format($total_results) ?> results
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- No Results -->
                <div class="no-results">
                    <div class="no-results-icon">
                        <i class="bi bi-search"></i>
                    </div>
                    <h3>No books found</h3>
                    <p class="text-muted mb-4">
                        We couldn't find any books matching your search criteria. 
                        Try adjusting your filters or search terms.
                    </p>
                    <div class="d-flex gap-2 justify-content-center">
                        <a href="seller_search.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Clear Filters
                        </a>
                        <a href="seller_add_book.php" class="btn btn-success">
                            <i class="bi bi-plus-circle"></i> Add New Book
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Welcome Message -->
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="bi bi-search"></i>
                </div>
                <h3>Ready to search your book collection?</h3>
                <p class="text-muted mb-4">
                    Use the search form above to find books in your inventory. 
                    You can search by title, author, description, or ISBN, and use advanced filters to narrow down results.
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="seller_manage_books.php" class="btn btn-primary">
                        <i class="bi bi-list"></i> View All Books
                    </a>
                    <a href="seller_add_book.php" class="btn btn-success">
                        <i class="bi bi-plus-circle"></i> Add New Book
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Advanced filters toggle
        document.getElementById('toggleAdvanced').addEventListener('click', function() {
            const filters = document.getElementById('advancedFilters');
            const isVisible = filters.style.display !== 'none';
            
            filters.style.display = isVisible ? 'none' : 'block';
            this.innerHTML = isVisible 
                ? '<i class="bi bi-sliders"></i> Advanced Filters'
                : '<i class="bi bi-sliders"></i> Hide Filters';
        });

        // View toggle functionality
        document.getElementById('gridView').addEventListener('click', function() {
            document.body.classList.remove('list-view');
            document.getElementById('listView').classList.remove('active');
            this.classList.add('active');
            localStorage.setItem('bookViewMode', 'grid');
        });

        document.getElementById('listView').addEventListener('click', function() {
            document.body.classList.add('list-view');
            document.getElementById('gridView').classList.remove('active');
            this.classList.add('active');
            localStorage.setItem('bookViewMode', 'list');
        });

        // Restore view mode
        const savedViewMode = localStorage.getItem('bookViewMode');
        if (savedViewMode === 'list') {
            document.getElementById('listView').click();
        }

        // Auto-submit form on certain changes
        document.querySelectorAll('select[name="sort_by"], select[name="order"]').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('searchForm').submit();
            });
        });

        // Search input enhancements
        const searchInput = document.getElementById('searchInput');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 3) {
                searchTimeout = setTimeout(() => {
                    // Auto-search functionality could be added here
                    console.log('Auto-searching for:', query);
                }, 500);
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                searchInput.blur();
            }
        });

        // Smooth scrolling for pagination
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });

        // Book card interactions
        document.querySelectorAll('.book-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(-10px)';
            });
        });

        // Loading states for form submission
        document.getElementById('searchForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Searching...';
            submitBtn.disabled = true;
            
            // Re-enable after a delay (in case search is instant)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        });

        // Initialize tooltips if using Bootstrap tooltips
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            tooltipTriggerList.forEach(tooltipTriggerEl => {
                new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    </script>
</body>
</html>