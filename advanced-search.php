<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier-login.html");
    exit();
}

$courier_id = $_SESSION['courier_id'];

// Initialize search parameters
$search_query = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$rating_filter = $_GET['rating_filter'] ?? '';
$address_filter = $_GET['address_filter'] ?? '';
$customer_filter = $_GET['customer_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'created_at';
$sort_order = $_GET['sort_order'] ?? 'DESC';
$results_per_page = intval($_GET['results_per_page'] ?? 10);
$page = intval($_GET['page'] ?? 1);

// Build the WHERE clause for comprehensive filtering
$where_conditions = ["d.courier_id = ?"];
$params = [$courier_id];
$param_types = "s";

// Global text search across multiple fields
if (!empty($search_query)) {
    $where_conditions[] = "(d.order_id LIKE ? OR d.delivery_address LIKE ? OR d.delivery_details LIKE ? OR c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param, $search_param]);
    $param_types .= "ssssss";
}

// Status filter
if (!empty($status_filter)) {
    $where_conditions[] = "d.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

// Date range filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(d.created_at) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(d.created_at) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Customer rating filter
if (!empty($rating_filter)) {
    if ($rating_filter === 'no_feedback') {
        $where_conditions[] = "cf.id IS NULL";
    } else {
        $where_conditions[] = "cf.customer_rating = ?";
        $params[] = $rating_filter;
        $param_types .= "i";
    }
}

// Address filter (city/area based)
if (!empty($address_filter)) {
    $where_conditions[] = "d.delivery_address LIKE ?";
    $address_param = "%$address_filter%";
    $params[] = $address_param;
    $param_types .= "s";
}

// Customer name filter
if (!empty($customer_filter)) {
    $where_conditions[] = "c.name LIKE ?";
    $customer_param = "%$customer_filter%";
    $params[] = $customer_param;
    $param_types .= "s";
}

$where_clause = implode(" AND ", $where_conditions);

// Validate sort parameters
$valid_sort_columns = ['created_at', 'status', 'delivery_address', 'customer_rating', 'order_id'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

// Calculate pagination
$offset = ($page - 1) * $results_per_page;

// Get total count for pagination
$count_sql = "
    SELECT COUNT(DISTINCT d.id) as total_count
    FROM deliveries d
    LEFT JOIN customers c ON d.customer_id = c.id
    LEFT JOIN customer_feedback cf ON d.id = cf.delivery_id
    LEFT JOIN delivery_status_log dsl ON d.id = dsl.delivery_id
    WHERE $where_clause
";

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_results = $count_stmt->get_result()->fetch_assoc()['total_count'];
$total_pages = ceil($total_results / $results_per_page);

// Main search query with comprehensive data
$sql = "
    SELECT DISTINCT d.*, 
           c.name as customer_name,
           c.email as customer_email,
           c.phone as customer_phone,
           cf.id as feedback_id,
           cf.customer_rating,
           cf.customer_comment,
           cf.delivery_experience,
           (SELECT COUNT(*) FROM delivery_status_log WHERE delivery_id = d.id) as status_changes_count,
           (SELECT update_reason FROM delivery_status_log WHERE delivery_id = d.id ORDER BY updated_at DESC LIMIT 1) as last_update_reason,
           (SELECT cancelled_at FROM delivery_cancellations WHERE delivery_id = d.id) as cancelled_at,
           (SELECT cancellation_reason FROM delivery_cancellations WHERE delivery_id = d.id) as cancellation_reason
    FROM deliveries d
    LEFT JOIN customers c ON d.customer_id = c.id
    LEFT JOIN customer_feedback cf ON d.id = cf.delivery_id
    LEFT JOIN delivery_status_log dsl ON d.id = dsl.delivery_id
    WHERE $where_clause
    ORDER BY d.$sort_by $sort_order
    LIMIT $results_per_page OFFSET $offset
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$search_results = $stmt->get_result();

// Get quick stats for current search
$stats_sql = "
    SELECT 
        COUNT(DISTINCT d.id) as total_deliveries,
        COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.id END) as pending_count,
        COUNT(DISTINCT CASE WHEN d.status = 'in_progress' THEN d.id END) as in_progress_count,
        COUNT(DISTINCT CASE WHEN d.status = 'completed' THEN d.id END) as completed_count,
        COUNT(DISTINCT CASE WHEN d.status = 'cancelled' THEN d.id END) as cancelled_count,
        AVG(cf.customer_rating) as avg_rating,
        COUNT(DISTINCT cf.id) as feedback_count
    FROM deliveries d
    LEFT JOIN customers c ON d.customer_id = c.id
    LEFT JOIN customer_feedback cf ON d.id = cf.delivery_id
    WHERE $where_clause
";

$stats_stmt = $conn->prepare($stats_sql);
if (!empty($params)) {
    $stats_stmt->bind_param($param_types, ...$params);
}
$stats_stmt->execute();
$search_stats = $stats_stmt->get_result()->fetch_assoc();

// Get popular delivery areas for suggestions
$areas_stmt = $conn->prepare("
    SELECT SUBSTRING_INDEX(delivery_address, ',', -2) as area, COUNT(*) as count 
    FROM deliveries 
    WHERE courier_id = ? 
    GROUP BY area 
    ORDER BY count DESC 
    LIMIT 10
");
$areas_stmt->bind_param("s", $courier_id);
$areas_stmt->execute();
$popular_areas = $areas_stmt->get_result();

// Get frequent customers for suggestions
$customers_stmt = $conn->prepare("
    SELECT c.name, COUNT(*) as delivery_count 
    FROM deliveries d 
    JOIN customers c ON d.customer_id = c.id 
    WHERE d.courier_id = ? 
    GROUP BY c.id 
    ORDER BY delivery_count DESC 
    LIMIT 10
");
$customers_stmt->bind_param("s", $courier_id);
$customers_stmt->execute();
$frequent_customers = $customers_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Search & Filter - BookStore</title>    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">    <style>
        /* Page-specific styles for Advanced Search */

        .page-header {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        /* Search Form Styles */
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .search-title {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .search-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.9rem;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #3498db;
            outline: none;
        }

        /* Advanced Options */
        .advanced-options {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 2px dashed #dee2e6;
        }

        .advanced-toggle {
            cursor: pointer;
            color: #3498db;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .advanced-content {
            display: none;
        }

        .advanced-content.active {
            display: block;
        }

        /* Filter Suggestions */
        .suggestions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .suggestion-item {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin: 0.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .suggestion-item:hover {
            background: #3498db;
            color: white;
        }

        /* Action Buttons */
        .search-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        /* Search Statistics */
        .search-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #3498db;
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 1.5rem;
            color: #2c3e50;
            font-weight: bold;
        }

        /* Results Section */
        .results-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f1f1f1;
        }

        .results-info {
            color: #666;
            font-size: 0.9rem;
        }

        .pagination-info {
            color: #3498db;
            font-weight: 600;
        }

        /* Result Cards */
        .result-card {
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .result-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-title {
            color: #2c3e50;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .result-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            color: #666;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: #3498db;
            margin-right: 0.5rem;
        }

        .result-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #dee2e6;
            color: #3498db;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #3498db;
            color: white;
        }

        .pagination a.active {
            background: #3498db;
            color: white;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
            }

            .search-grid {
                grid-template-columns: 1fr;
            }

            .search-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-truck"></i>
            <h2>Courier Dashboard</h2>
        </div>        <ul class="nav-links">
            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="delivery-status-management.php"><i class="fas fa-edit"></i> Status & Cancel Management</a></li>
            <li><a href="customer-feedback.php"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php" class="active"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-search"></i> Advanced Search & Filter System</h1>
            <p>Comprehensive search and filtering across all delivery data with powerful analytics</p>
        </div>

        <!-- Search Form -->
        <div class="search-container">
            <h2 class="search-title">
                <i class="fas fa-filter"></i>
                Search Filters
            </h2>

            <form method="GET" id="searchForm">
                <div class="search-grid">
                    <div class="form-group">
                        <label for="search">Global Search</label>
                        <input type="text" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>"
                               placeholder="Order ID, customer name, address, email, phone...">
                        <div class="suggestions">
                            <small><i class="fas fa-lightbulb"></i> Tip: Search across all delivery data simultaneously</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="status_filter">Delivery Status</label>
                        <select id="status_filter" name="status_filter">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>

                    <div class="form-group">
                        <label for="rating_filter">Customer Rating</label>
                        <select id="rating_filter" name="rating_filter">
                            <option value="">All Ratings</option>
                            <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                            <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                            <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                            <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                            <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                            <option value="no_feedback" <?php echo $rating_filter === 'no_feedback' ? 'selected' : ''; ?>>No Feedback</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="results_per_page">Results Per Page</label>
                        <select id="results_per_page" name="results_per_page">
                            <option value="10" <?php echo $results_per_page === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="25" <?php echo $results_per_page === 25 ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo $results_per_page === 50 ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo $results_per_page === 100 ? 'selected' : ''; ?>>100</option>
                        </select>
                    </div>
                </div>

                <!-- Advanced Options -->
                <div class="advanced-options">
                    <div class="advanced-toggle" onclick="toggleAdvanced()">
                        <i class="fas fa-cog"></i>
                        <span>Advanced Search Options</span>
                        <i class="fas fa-chevron-down" id="advancedArrow"></i>
                    </div>
                    
                    <div class="advanced-content" id="advancedContent">
                        <div class="search-grid">
                            <div class="form-group">
                                <label for="address_filter">Delivery Area</label>
                                <input type="text" id="address_filter" name="address_filter" 
                                       value="<?php echo htmlspecialchars($address_filter); ?>"
                                       placeholder="City, area, or postal code...">
                                <div class="suggestions">
                                    <small><strong>Popular Areas:</strong></small><br>
                                    <?php while ($area = $popular_areas->fetch_assoc()): ?>
                                        <span class="suggestion-item" onclick="setFilter('address_filter', '<?php echo htmlspecialchars(trim($area['area'])); ?>')">
                                            <?php echo htmlspecialchars(trim($area['area'])); ?> (<?php echo $area['count']; ?>)
                                        </span>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="customer_filter">Customer Name</label>
                                <input type="text" id="customer_filter" name="customer_filter" 
                                       value="<?php echo htmlspecialchars($customer_filter); ?>"
                                       placeholder="Search by customer name...">
                                <div class="suggestions">
                                    <small><strong>Frequent Customers:</strong></small><br>
                                    <?php while ($customer = $frequent_customers->fetch_assoc()): ?>
                                        <span class="suggestion-item" onclick="setFilter('customer_filter', '<?php echo htmlspecialchars($customer['name']); ?>')">
                                            <?php echo htmlspecialchars($customer['name']); ?> (<?php echo $customer['delivery_count']; ?>)
                                        </span>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="sort_by">Sort By</label>
                                <select id="sort_by" name="sort_by">
                                    <option value="created_at" <?php echo $sort_by === 'created_at' ? 'selected' : ''; ?>>Creation Date</option>
                                    <option value="status" <?php echo $sort_by === 'status' ? 'selected' : ''; ?>>Status</option>
                                    <option value="delivery_address" <?php echo $sort_by === 'delivery_address' ? 'selected' : ''; ?>>Address</option>
                                    <option value="customer_rating" <?php echo $sort_by === 'customer_rating' ? 'selected' : ''; ?>>Customer Rating</option>
                                    <option value="order_id" <?php echo $sort_by === 'order_id' ? 'selected' : ''; ?>>Order ID</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="sort_order">Sort Order</label>
                                <select id="sort_order" name="sort_order">
                                    <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search Actions -->
                <div class="search-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search & Filter
                    </button>
                    <a href="advanced-search.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear All Filters
                    </a>
                    <button type="button" class="btn btn-warning" onclick="exportResults()">
                        <i class="fas fa-download"></i> Export Results
                    </button>
                </div>
            </form>
        </div>

        <!-- Search Statistics -->
        <?php if ($total_results > 0): ?>
            <div class="search-stats">
                <div class="stat-card">
                    <h3>Total Results</h3>
                    <div class="value"><?php echo $search_stats['total_deliveries'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Pending</h3>
                    <div class="value"><?php echo $search_stats['pending_count'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>In Progress</h3>
                    <div class="value"><?php echo $search_stats['in_progress_count'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Completed</h3>
                    <div class="value"><?php echo $search_stats['completed_count'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Cancelled</h3>
                    <div class="value"><?php echo $search_stats['cancelled_count'] ?? 0; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Avg Rating</h3>
                    <div class="value"><?php echo number_format($search_stats['avg_rating'] ?? 0, 1); ?></div>
                </div>
                <div class="stat-card">
                    <h3>With Feedback</h3>
                    <div class="value"><?php echo $search_stats['feedback_count'] ?? 0; ?></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Search Results -->
        <div class="results-container">
            <div class="results-header">
                <div class="results-info">
                    <?php if ($total_results > 0): ?>
                        Showing <?php echo number_format($total_results); ?> result(s)
                        <?php if (!empty($search_query) || !empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($rating_filter) || !empty($address_filter) || !empty($customer_filter)): ?>
                            for your search
                        <?php endif; ?>
                    <?php else: ?>
                        No results found
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($search_results->num_rows > 0): ?>
                <?php while ($result = $search_results->fetch_assoc()): ?>
                    <div class="result-card">
                        <div class="result-header">
                            <div class="order-title">Order #<?php echo htmlspecialchars($result['order_id']); ?></div>
                            <span class="status-badge status-<?php echo str_replace('_', '-', $result['status']); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $result['status'])); ?>
                            </span>
                        </div>

                        <div class="result-details">
                            <div class="detail-item">
                                <i class="fas fa-user"></i> 
                                Customer: <?php echo htmlspecialchars($result['customer_name'] ?? 'Unknown'); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($result['delivery_address']); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i> 
                                Created: <?php echo date('M d, Y h:i A', strtotime($result['created_at'])); ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-box"></i> 
                                <?php echo htmlspecialchars($result['delivery_details']); ?>
                            </div>
                            
                            <?php if ($result['customer_email']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-envelope"></i> 
                                    <?php echo htmlspecialchars($result['customer_email']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($result['customer_phone']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-phone"></i> 
                                    <?php echo htmlspecialchars($result['customer_phone']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($result['feedback_id']): ?>
                                <div class="detail-item">
                                    <i class="fas fa-star" style="color: gold;"></i> 
                                    Rating: <?php echo $result['customer_rating']; ?>/5
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($result['status_changes_count'] > 0): ?>
                                <div class="detail-item">
                                    <i class="fas fa-history"></i> 
                                    <?php echo $result['status_changes_count']; ?> status changes
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($result['cancelled_at']): ?>
                                <div class="detail-item" style="color: #e74c3c;">
                                    <i class="fas fa-times-circle"></i> 
                                    Cancelled: <?php echo date('M d, Y h:i A', strtotime($result['cancelled_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Detailed Information -->
                        <?php if ($result['customer_comment']): ?>
                            <div style="margin: 1rem 0; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                                <strong><i class="fas fa-comment"></i> Customer Feedback:</strong><br>
                                <em>"<?php echo htmlspecialchars($result['customer_comment']); ?>"</em>
                                <br><small>Experience: <?php echo ucfirst($result['delivery_experience']); ?></small>
                            </div>
                        <?php endif; ?>

                        <?php if ($result['last_update_reason']): ?>
                            <div style="margin: 1rem 0; padding: 1rem; background: #e3f2fd; border-radius: 8px;">
                                <strong><i class="fas fa-info-circle"></i> Last Update Reason:</strong><br>
                                "<?php echo htmlspecialchars($result['last_update_reason']); ?>"
                            </div>
                        <?php endif; ?>

                        <?php if ($result['cancellation_reason']): ?>
                            <div style="margin: 1rem 0; padding: 1rem; background: #ffebee; border-radius: 8px;">
                                <strong><i class="fas fa-exclamation-triangle"></i> Cancellation Reason:</strong><br>
                                "<?php echo htmlspecialchars($result['cancellation_reason']); ?>"
                            </div>
                        <?php endif; ?>

                        <div class="result-actions">
                            <a href="delivery_details.php?id=<?php echo $result['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            
                            <?php if (in_array($result['status'], ['pending', 'in_progress'])): ?>
                                <a href="delivery-status-management.php" class="btn btn-warning btn-sm">
                                    <i class="fas fa-edit"></i> Update Status
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($result['status'] === 'completed' && !$result['feedback_id']): ?>
                                <a href="customer-feedback.php?delivery_id=<?php echo $result['id']; ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-star"></i> Collect Feedback
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">&laquo; Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h2>No Results Found</h2>
                    <p>Try adjusting your search criteria or clearing filters to see more results.</p>
                    <div style="margin-top: 2rem;">
                        <a href="advanced-search.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Clear All Filters
                        </a>
                        <a href="active-deliveries.php" class="btn btn-primary">
                            <i class="fas fa-box"></i> View Active Deliveries
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleAdvanced() {
            const content = document.getElementById('advancedContent');
            const arrow = document.getElementById('advancedArrow');
            
            if (content.classList.contains('active')) {
                content.classList.remove('active');
                arrow.classList.remove('fa-chevron-up');
                arrow.classList.add('fa-chevron-down');
            } else {
                content.classList.add('active');
                arrow.classList.remove('fa-chevron-down');
                arrow.classList.add('fa-chevron-up');
            }
        }

        function setFilter(fieldId, value) {
            document.getElementById(fieldId).value = value;
        }

        function exportResults() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            alert('Export functionality would download search results as CSV file.\nURL: ' + window.location.pathname + '?' + params.toString());
        }

        // Auto-submit form on certain changes
        document.getElementById('results_per_page').addEventListener('change', function() {
            document.getElementById('searchForm').submit();
        });

        // Quick search suggestions
        document.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', function() {
                document.getElementById('searchForm').submit();
            });
        });

        // Date range validation
        document.getElementById('date_from').addEventListener('change', function() {
            const dateFrom = this.value;
            const dateTo = document.getElementById('date_to').value;
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                document.getElementById('date_to').value = dateFrom;
            }
        });

        document.getElementById('date_to').addEventListener('change', function() {
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = this.value;
            
            if (dateFrom && dateTo && dateFrom > dateTo) {
                document.getElementById('date_from').value = dateTo;
            }
        });
    </script>
</body>
</html>
