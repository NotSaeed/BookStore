<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id']) || !isset($_SESSION['courier_email'])) {
    header('Location: courier-login.html');
    exit();
}

// Get courier information
$courier_id = $_SESSION['courier_id'];
$stmt = $conn->prepare("SELECT * FROM couriers WHERE courier_id = ?");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$result = $stmt->get_result();
$courier = $result->fetch_assoc();

// Initialize search and filter parameters
$search_query = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$rating_filter = $_GET['rating_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'completion_time';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build the WHERE clause for filters
$where_conditions = ["d.courier_id = ?"];
$params = [$courier_id];
$param_types = "s";

if (!empty($search_query)) {
    $where_conditions[] = "(d.order_id LIKE ? OR d.delivery_address LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $param_types .= "sss";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(du.update_time) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(du.update_time) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

if (!empty($rating_filter)) {
    if ($rating_filter === 'no_feedback') {
        $where_conditions[] = "cf.id IS NULL";
    } else {
        $where_conditions[] = "cf.customer_rating = ?";
        $params[] = $rating_filter;
        $param_types .= "i";
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Validate sort parameters
$valid_sort_columns = ['completion_time', 'order_id', 'customer_rating'];
$valid_sort_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'completion_time';
}
if (!in_array($sort_order, $valid_sort_orders)) {
    $sort_order = 'DESC';
}

// Get completed deliveries for the courier with feedback status
$sql = "
    SELECT d.*, 
           du.update_time as completion_time,
           du.notes as completion_notes,
           cf.id as feedback_id,
           cf.customer_rating,
           cf.customer_comment,
           cf.delivery_experience,
           c.name as customer_name
    FROM deliveries d
    LEFT JOIN delivery_updates du ON d.id = du.delivery_id 
        AND du.status = 'completed'
    LEFT JOIN customer_feedback cf ON d.id = cf.delivery_id
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE $where_clause
    AND d.status = 'completed'
    ORDER BY ";

// Handle different sort columns
switch ($sort_by) {
    case 'customer_rating':
        $sql .= "cf.customer_rating $sort_order, du.update_time DESC";
        break;
    case 'order_id':
        $sql .= "d.order_id $sort_order";
        break;
    default:
        $sql .= "du.update_time $sort_order";
}

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$completed_deliveries = $stmt->get_result();

// Get statistics with current filters
$stats_sql = "
    SELECT 
        COUNT(*) as total_completed,
        AVG(TIMESTAMPDIFF(MINUTE, d.created_at, du.update_time)) as avg_delivery_time,
        AVG(cf.customer_rating) as avg_customer_rating,
        COUNT(cf.id) as feedback_count
    FROM deliveries d
    JOIN delivery_updates du ON d.id = du.delivery_id 
        AND du.status = 'completed'
    LEFT JOIN customer_feedback cf ON d.id = cf.delivery_id
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE $where_clause
    AND d.status = 'completed'";

$stmt = $conn->prepare($stats_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Delivery History - BookStore</title>    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        /* Page-specific styles only - sidebar styles moved to css/sidebar.css */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: bold;
        }

        .history-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .delivery-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .delivery-card:last-child {
            border-bottom: none;
        }

        .delivery-info h3 {
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .delivery-info p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }

        .completion-info {
            text-align: right;
        }

        .completion-time {
            color: #666;
            font-size: 0.9rem;
        }

        .view-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #9b59b6;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            transition: background 0.3s ease;
        }        .view-btn:hover {
            background: #8e44ad;
        }

        .feedback-btn {
            background: #f39c12;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            margin-left: 0.5rem;
            transition: background 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .feedback-btn:hover {
            background: #e67e22;
            color: white;
        }

        .feedback-completed {
            background: #27ae60;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            margin-left: 0.5rem;
            display: inline-block;
            cursor: default;
        }

        .delivery-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }        .rating-display {
            color: #f39c12;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        /* Search and Filter Styles */
        .search-filter-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 0.9rem;
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .form-group input,
        .form-group select {
            padding: 0.5rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #9b59b6;
            outline: none;
        }

        .filter-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn-search {
            background: #9b59b6;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .btn-search:hover {
            background: #8e44ad;
        }

        .btn-clear {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
            text-decoration: none;
        }

        .btn-clear:hover {
            background: #5a6268;
            color: white;
        }

        .results-summary {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
        }

        .enhanced-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #9b59b6;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-container {
                grid-template-columns: 1fr;
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
            <li><a href="delivery-history.php" class="active"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="delivery-status-management.php"><i class="fas fa-edit"></i> Status & Cancel Management</a></li>
            <li><a href="customer-feedback.php"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Delivery History</h1>
        </div>        <!-- Statistics -->
        <div class="stats-container enhanced-stats">
            <div class="stat-card">
                <h3>Total Completed Deliveries</h3>
                <div class="value"><?php echo $stats['total_completed'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Delivery Time</h3>
                <div class="value">
                    <?php 
                        $avg_time = $stats['avg_delivery_time'] ?? 0;
                        echo floor($avg_time / 60) . 'h ' . ($avg_time % 60) . 'm';
                    ?>
                </div>
            </div>
            <div class="stat-card">
                <h3>Average Customer Rating</h3>
                <div class="value">
                    <?php echo number_format($stats['avg_customer_rating'] ?? 0, 1); ?> 
                    <i class="fas fa-star" style="color: gold; font-size: 1rem;"></i>
                </div>
            </div>
            <div class="stat-card">
                <h3>Feedback Collected</h3>
                <div class="value"><?php echo $stats['feedback_count'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="search-filter-container">
            <h3 style="margin-bottom: 1rem; color: #2c3e50;">
                <i class="fas fa-search"></i> Search & Filter Deliveries
            </h3>
            <form method="GET" class="search-form" id="search-form">
                <div class="form-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" 
                           placeholder="Order ID, address, or customer name"
                           value="<?php echo htmlspecialchars($search_query); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="form-group">
                    <label for="date_to">To Date</label>
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
                    <label for="sort_by">Sort By</label>
                    <select id="sort_by" name="sort_by">
                        <option value="completion_time" <?php echo $sort_by === 'completion_time' ? 'selected' : ''; ?>>Completion Date</option>
                        <option value="order_id" <?php echo $sort_by === 'order_id' ? 'selected' : ''; ?>>Order ID</option>
                        <option value="customer_rating" <?php echo $sort_by === 'customer_rating' ? 'selected' : ''; ?>>Customer Rating</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sort_order">Order</label>
                    <select id="sort_order" name="sort_order">
                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Oldest First</option>
                    </select>
                </div>
            </form>
            
            <div class="filter-buttons">
                <button type="submit" form="search-form" class="btn-search">
                    <i class="fas fa-search"></i> Search & Filter
                </button>
                <a href="delivery-history.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
            </div>
            
            <?php if (!empty($search_query) || !empty($date_from) || !empty($date_to) || !empty($rating_filter)): ?>
                <div class="results-summary">
                    <strong>Active Filters:</strong>
                    <?php if (!empty($search_query)): ?>
                        Search: "<?php echo htmlspecialchars($search_query); ?>" |
                    <?php endif; ?>
                    <?php if (!empty($date_from)): ?>
                        From: <?php echo date('M j, Y', strtotime($date_from)); ?> |
                    <?php endif; ?>
                    <?php if (!empty($date_to)): ?>
                        To: <?php echo date('M j, Y', strtotime($date_to)); ?> |
                    <?php endif; ?>
                    <?php if (!empty($rating_filter)): ?>
                        Rating: <?php echo $rating_filter === 'no_feedback' ? 'No Feedback' : $rating_filter . ' Stars'; ?> |
                    <?php endif; ?>
                    Showing <?php echo $completed_deliveries->num_rows; ?> result(s)
                </div>
            <?php endif; ?>
        </div>

        <!-- Delivery History -->
        <div class="history-container">
            <?php if ($completed_deliveries->num_rows > 0): ?>
                <?php while ($delivery = $completed_deliveries->fetch_assoc()): ?>
                    <div class="delivery-card">                        <div class="delivery-info">
                            <h3>Order #<?php echo htmlspecialchars($delivery['order_id']); ?></h3>
                            <p><i class="fas fa-user"></i> Customer: <?php echo htmlspecialchars($delivery['customer_name'] ?? 'Unknown'); ?></p>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($delivery['delivery_address']); ?></p>
                            <p><i class="fas fa-box"></i> <?php echo htmlspecialchars($delivery['delivery_details']); ?></p>
                            <?php if ($delivery['feedback_id'] && !empty($delivery['customer_comment'])): ?>
                                <p><i class="fas fa-comment"></i> <em>"<?php echo htmlspecialchars(substr($delivery['customer_comment'], 0, 100)); ?><?php echo strlen($delivery['customer_comment']) > 100 ? '...' : ''; ?>"</em></p>
                            <?php endif; ?>
                        </div><div class="completion-info">
                            <div class="completion-time">
                                Completed on <?php echo date('M d, Y h:i A', strtotime($delivery['completion_time'])); ?>
                            </div>
                            <?php if ($delivery['feedback_id']): ?>
                                <div class="rating-display">
                                    <i class="fas fa-star"></i> Customer Rating: <?php echo $delivery['customer_rating']; ?>/5
                                </div>
                            <?php endif; ?>
                            <div class="delivery-actions">
                                <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" class="view-btn">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                <?php if ($delivery['feedback_id']): ?>
                                    <span class="feedback-completed">
                                        <i class="fas fa-check"></i> Feedback Collected
                                    </span>
                                <?php else: ?>
                                    <a href="customer-feedback.php?delivery_id=<?php echo $delivery['id']; ?>" class="feedback-btn">
                                        <i class="fas fa-star"></i> Collect Feedback
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-history"></i>
                    <h2>No Completed Deliveries</h2>
                    <p>Your completed deliveries will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>