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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Dashboard - BookStore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            min-height: 100vh;
            background: #f4f6f8;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #9b59b6;
            padding: 2rem;
            color: white;
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .sidebar-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .nav-links {
            list-style: none;
        }

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
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .profile-section img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
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
        </div>
        <ul class="nav-links">
            <li><a href="courier-dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="route-planning.php"><i class="fas fa-route"></i> Route Planning</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Welcome back, <?php echo htmlspecialchars($courier['name']); ?>!</h1>
            <div class="profile-section">
                <span><?php echo $active_deliveries->num_rows > 0 ? 'On Duty' : 'Available'; ?></span>
                <img src="<?php echo htmlspecialchars($courier['profile_image'] ?? 'https://via.placeholder.com/40'); ?>" alt="Profile Picture">
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Today's Deliveries</h3>
                <div class="value"><?php echo $counts['total_today'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Completed Today</h3>
                <div class="value"><?php echo $counts['completed_today'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="value"><?php echo $counts['pending_today'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Average Rating</h3>
                <div class="value"><?php echo number_format($courier['avg_rating'] ?? 0, 1); ?> <i class="fas fa-star" style="color: gold; font-size: 1.5rem;"></i></div>
            </div>
        </div>

        <!-- Active Deliveries -->
        <div class="delivery-list">
            <h2>Active Deliveries</h2>
            <?php if ($active_deliveries->num_rows > 0): ?>
                <?php while ($delivery = $active_deliveries->fetch_assoc()): ?>
                    <div class="delivery-item">
                        <div class="delivery-info">
                            <h3>Order #<?php echo htmlspecialchars($delivery['id']); ?></h3>
                            <p><?php echo htmlspecialchars($delivery['delivery_details']); ?></p>
                        </div>
                        <span class="delivery-status status-<?php echo $delivery['status'] == 'in_progress' ? 'in-transit' : 'pending'; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                        </span>
                        <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" class="action-btn btn-primary">
                            <?php echo $delivery['status'] == 'pending' ? 'Start Delivery' : 'Update Status'; ?>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="text-align: center; color: #666;">No active deliveries at the moment.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>