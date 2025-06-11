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

// Get today's deliveries count
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_today,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_today,
    SUM(CASE WHEN status IN ('pending', 'in_progress') THEN 1 ELSE 0 END) as pending_today
    FROM deliveries 
    WHERE courier_id = ? AND DATE(created_at) = ?");
$stmt->bind_param("ss", $courier_id, $today);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

// Get active deliveries
$stmt = $conn->prepare("SELECT * FROM deliveries WHERE courier_id = ? AND status != 'completed' ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$active_deliveries = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Courier Dashboard - BookStore</title>    <!-- Bootstrap 5.3.0 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap-sidebar.css">
    <style>

        .nav-links li {
            margin-bottom: 1rem;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.8rem;
            border-radius: 5px;
            transition: background 0.3s ease;
        }

        .nav-links a:hover {
            background: #8e44ad;
        }

        .nav-links a.active {
            background: #8e44ad;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }        .profile-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-status {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .profile-name {
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.2rem;
        }

        .profile-duty-status {
            font-size: 0.85rem;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-weight: 500;
        }

        .status-on-duty {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .status-available {
            background: #e3f2fd;
            color: #1976d2;
        }

        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            color: #2c3e50;
            font-weight: bold;
        }

        /* Delivery List */
        .delivery-list {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .delivery-list h2 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }

        .delivery-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }

        .delivery-item:last-child {
            border-bottom: none;
        }

        .delivery-info h3 {
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .delivery-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .delivery-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-transit {
            background: #cce5ff;
            color: #004085;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }

        .btn-primary {
            background: #9b59b6;
            color: white;
        }

        .btn-primary:hover {
            background: #8e44ad;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
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
            <li><a href="courier-dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="delivery-status-management.php"><i class="fas fa-edit"></i> Status & Cancel Management</a></li>
            <li><a href="customer-feedback.php"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>    <!-- Main Content -->
    <div class="main-content">
        <!-- Header with Bootstrap -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h2 mb-0">Welcome back, <?php echo htmlspecialchars($courier['name']); ?>!</h1>
            <div class="d-flex align-items-center">
                <div class="profile-avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px; font-weight: bold;">
                    <?php 
                    $name_parts = explode(' ', $courier['name']);
                    $initials = '';
                    foreach ($name_parts as $part) {
                        $initials .= strtoupper(substr($part, 0, 1));
                    }
                    echo substr($initials, 0, 2); // Show first 2 initials
                    ?>
                </div>
                <div>
                    <div class="fw-bold"><?php echo htmlspecialchars($courier['name']); ?></div>
                    <span class="badge <?php echo $active_deliveries->num_rows > 0 ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $active_deliveries->num_rows > 0 ? 'On Duty' : 'Available'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistics Cards with Bootstrap -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-calendar-day text-primary fs-2 mb-3"></i>
                        <h5 class="card-title text-muted">Today's Deliveries</h5>
                        <h2 class="text-primary mb-0"><?php echo $counts['total_today'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle text-success fs-2 mb-3"></i>
                        <h5 class="card-title text-muted">Completed Today</h5>
                        <h2 class="text-success mb-0"><?php echo $counts['completed_today'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-clock text-warning fs-2 mb-3"></i>
                        <h5 class="card-title text-muted">Pending</h5>
                        <h2 class="text-warning mb-0"><?php echo $counts['pending_today'] ?? 0; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-star text-warning fs-2 mb-3"></i>
                        <h5 class="card-title text-muted">Average Rating</h5>
                        <h2 class="text-warning mb-0"><?php echo number_format($courier['avg_rating'] ?? 0, 1); ?></h2>
                    </div>
                </div>
            </div>
        </div>        <!-- Recent Deliveries with Bootstrap -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom-0">
                <h3 class="card-title h5 mb-0">
                    <i class="fas fa-shipping-fast text-primary me-2"></i>
                    Recent Active Deliveries
                </h3>
            </div>
            <div class="card-body">
                <?php if ($active_deliveries->num_rows > 0): ?>
                    <div class="row g-3">
                        <?php while ($delivery = $active_deliveries->fetch_assoc()): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border border-light">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title mb-0">Order #<?php echo htmlspecialchars($delivery['id']); ?></h6>
                                            <span class="badge <?php echo $delivery['status'] == 'in_progress' ? 'bg-primary' : 'bg-warning'; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                            </span>
                                        </div>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars($delivery['delivery_address'] ?? 'Address not available'); ?>
                                        </p>
                                        <p class="card-text small mb-3">
                                            <?php echo htmlspecialchars($delivery['delivery_details']); ?>
                                        </p>
                                        <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" 
                                           class="btn btn-primary btn-sm w-100">
                                            <?php echo $delivery['status'] == 'pending' ? 'Start Delivery' : 'Update Status'; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No active deliveries at the moment</h5>
                        <p class="text-muted">Check back later for new delivery assignments.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div></div>
    </div>

    <!-- Bootstrap 5.3.0 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>