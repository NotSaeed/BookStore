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

// Get all active deliveries for the courier
$stmt = $conn->prepare("SELECT d.*, 
    (SELECT MAX(du.status) FROM delivery_updates du WHERE du.delivery_id = d.id) as current_status
    FROM deliveries d 
    WHERE d.courier_id = ? 
    AND d.status != 'completed' 
    ORDER BY d.created_at DESC");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$active_deliveries = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Deliveries - BookStore</title>
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

        /* Delivery Cards */
        .deliveries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .delivery-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .order-id {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
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

        .delivery-details {
            margin-bottom: 1rem;
        }

        .detail-item {
            margin-bottom: 0.8rem;
        }

        .detail-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.3rem;
        }

        .detail-value {
            color: #2c3e50;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            padding: 0.8rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            text-align: center;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .btn-primary {
            background: #9b59b6;
            color: white;
        }

        .btn-primary:hover {
            background: #8e44ad;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2c3e50;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e9ecef;
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
            <li><a href="active-deliveries.php" class="active"><i class="fas fa-box"></i> Active Deliveries</a></li>
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
            <h1>Active Deliveries</h1>
            <div class="profile-section">
                <span><?php echo $active_deliveries->num_rows > 0 ? 'On Duty' : 'Available'; ?></span>
                <img src="<?php echo htmlspecialchars($courier['profile_image'] ?? 'https://via.placeholder.com/40'); ?>" alt="Profile Picture">
            </div>
        </div>

        <!-- Deliveries Grid -->
        <div class="deliveries-grid">
            <?php if ($active_deliveries->num_rows > 0): ?>
                <?php while ($delivery = $active_deliveries->fetch_assoc()): ?>
                    <div class="delivery-card">
                        <div class="delivery-header">
                            <span class="order-id">Order #<?php echo htmlspecialchars($delivery['id']); ?></span>
                            <span class="delivery-status status-<?php echo $delivery['current_status'] == 'in_progress' ? 'in-transit' : 'pending'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $delivery['current_status'])); ?>
                            </span>
                        </div>
                        <div class="delivery-details">
                            <div class="detail-item">
                                <div class="detail-label"><i class="fas fa-map-marker-alt"></i> Delivery Address</div>
                                <div class="detail-value"><?php echo htmlspecialchars($delivery['delivery_details']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label"><i class="fas fa-clock"></i> Estimated Delivery Time</div>
                                <div class="detail-value">
                                    <?php 
                                        $estimate = new DateTime($delivery['created_at']);
                                        $estimate->add(new DateInterval('PT2H'));
                                        echo $estimate->format('h:i A');
                                    ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label"><i class="fas fa-box"></i> Order Details</div>
                                <div class="detail-value"><?php echo htmlspecialchars($delivery['customer_info']); ?></div>
                            </div>
                        </div>
                        <div class="action-buttons">
                            <?php if ($delivery['current_status'] == 'pending'): ?>
                                <a href="update_delivery_status.php?id=<?php echo $delivery['id']; ?>&status=in_progress" class="btn btn-primary">Start Delivery</a>
                            <?php else: ?>
                                <a href="update_delivery_status.php?id=<?php echo $delivery['id']; ?>&status=completed" class="btn btn-primary">Mark as Delivered</a>
                            <?php endif; ?>
                            <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" class="btn btn-secondary">View Details</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 2rem; background: white; border-radius: 10px;">
                    <i class="fas fa-box-open" style="font-size: 3rem; color: #9b59b6; margin-bottom: 1rem;"></i>
                    <h2 style="color: #2c3e50; margin-bottom: 0.5rem;">No Active Deliveries</h2>
                    <p style="color: #666;">You don't have any active deliveries at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
