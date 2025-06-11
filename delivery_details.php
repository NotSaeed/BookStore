<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header('Location: courier-login.html');
    exit();
}

// Check if delivery ID is provided
if (!isset($_GET['id'])) {
    header('Location: activ            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>veries.php');
    exit();
}

$delivery_id = $_GET['id'];
$courier_id = $_SESSION['courier_id'];

// Get delivery information with customer details and status history
$stmt = $conn->prepare("
    SELECT d.*, 
           c.name as customer_name,
           c.phone as customer_phone,
           c.email as customer_email,
           c.address as customer_address
    FROM deliveries d
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE d.id = ? AND d.courier_id = ?
");
$stmt->bind_param("is", $delivery_id, $courier_id);
$stmt->execute();
$delivery = $stmt->get_result()->fetch_assoc();

if (!$delivery) {
    header('Location: active-deliveries.php');
    exit();
}

// Get delivery status history
$stmt = $conn->prepare("
    SELECT status, update_time, notes 
    FROM delivery_updates 
    WHERE delivery_id = ? 
    ORDER BY update_time DESC
");
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$status_history = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Details - BookStore</title>
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

        .back-link {
            color: #9b59b6;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Detail Cards */
        .detail-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f1f1;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
        }

        .detail-item {
            margin-bottom: 1rem;
        }

        .detail-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 500;
        }

        /* Status Timeline */
        .timeline {
            position: relative;
            margin: 2rem 0;
        }

        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #9b59b6;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 12px;
            width: 2px;
            height: calc(100% + 1rem);
            background: #e1e1e1;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
        }

        .timeline-date {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.3rem;
        }

        .timeline-status {
            color: #2c3e50;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
                display: none;
            }

            .main-content {
                margin-left: 0;
            }

            .detail-grid {
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
            <a href="active-deliveries.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                Back to Active Deliveries
            </a>
            <h1>Delivery Details</h1>
        </div>

        <!-- Order Information -->
        <div class="detail-section">
            <h2 class="section-title">Order Information</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Order ID</div>
                    <div class="detail-value">#<?php echo htmlspecialchars($delivery['id']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Status</div>
                    <div class="detail-value"><?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Order Date</div>
                    <div class="detail-value"><?php echo date('M d, Y h:i A', strtotime($delivery['created_at'])); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Estimated Delivery</div>
                    <div class="detail-value">
                        <?php
                            $estimate = new DateTime($delivery['created_at']);
                            $estimate->add(new DateInterval('PT2H'));
                            echo $estimate->format('M d, Y h:i A');
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="detail-section">
            <h2 class="section-title">Customer Information</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Name</div>
                    <div class="detail-value"><?php echo htmlspecialchars($delivery['customer_name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Phone</div>
                    <div class="detail-value"><?php echo htmlspecialchars($delivery['customer_phone']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($delivery['customer_email']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Delivery Address</div>
                    <div class="detail-value"><?php echo htmlspecialchars($delivery['customer_address']); ?></div>
                </div>
            </div>
        </div>

        <!-- Status History -->
        <div class="detail-section">
            <h2 class="section-title">Status History</h2>
            <div class="timeline">
                <?php while ($status = $status_history->fetch_assoc()): ?>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-date">
                            <?php echo date('M d, Y h:i A', strtotime($status['update_time'])); ?>
                        </div>
                        <div class="timeline-status">
                            <?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?>
                        </div>
                        <?php if (!empty($status['notes'])): ?>
                        <div class="timeline-notes">
                            <?php echo htmlspecialchars($status['notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <?php if ($delivery['status'] == 'pending'): ?>
            <a href="update_delivery_status.php?id=<?php echo $delivery_id; ?>&status=in_progress" class="btn btn-primary">
                <i class="fas fa-truck"></i>
                Start Delivery
            </a>
            <?php elseif ($delivery['status'] == 'in_progress'): ?>
            <a href="update_delivery_status.php?id=<?php echo $delivery_id; ?>&status=completed" class="btn btn-primary">
                <i class="fas fa-check"></i>
                Mark as Delivered
            </a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="fas fa-print"></i>
                Print Details
            </button>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
