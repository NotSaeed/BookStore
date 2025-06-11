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

// Get completed deliveries for the courier
$stmt = $conn->prepare("
    SELECT d.*, 
           du.update_time as completion_time,
           du.notes as completion_notes
    FROM deliveries d
    LEFT JOIN delivery_updates du ON d.id = du.delivery_id 
        AND du.status = 'completed'
    WHERE d.courier_id = ? 
    AND d.status = 'completed'
    ORDER BY du.update_time DESC");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$completed_deliveries = $stmt->get_result();

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_completed,
        AVG(TIMESTAMPDIFF(MINUTE, d.created_at, du.update_time)) as avg_delivery_time
    FROM deliveries d
    JOIN delivery_updates du ON d.id = du.delivery_id 
        AND du.status = 'completed'
    WHERE d.courier_id = ? 
    AND d.status = 'completed'");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery History - BookStore</title>
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
        }

        .view-btn:hover {
            background: #8e44ad;
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
        </div>
        <ul class="nav-links">
            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php" class="active"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="route-planning.php"><i class="fas fa-route"></i> Route Planning</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Delivery History</h1>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
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
        </div>

        <!-- Delivery History -->
        <div class="history-container">
            <?php if ($completed_deliveries->num_rows > 0): ?>
                <?php while ($delivery = $completed_deliveries->fetch_assoc()): ?>
                    <div class="delivery-card">
                        <div class="delivery-info">
                            <h3>Order #<?php echo htmlspecialchars($delivery['id']); ?></h3>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($delivery['delivery_address']); ?></p>
                            <p><i class="fas fa-box"></i> <?php echo htmlspecialchars($delivery['delivery_details']); ?></p>
                            <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($delivery['customer_info']); ?></p>
                        </div>
                        <div class="completion-info">
                            <div class="completion-time">
                                Completed on <?php echo date('M d, Y h:i A', strtotime($delivery['completion_time'])); ?>
                            </div>
                            <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" class="view-btn">
                                View Details
                            </a>
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