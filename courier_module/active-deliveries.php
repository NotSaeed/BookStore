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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Active Deliveries - BookStore</title>
    <!-- Bootstrap 5.3.0 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap-sidebar.css">
    <style>
        /* Page-specific styles only - sidebar styles moved to css/sidebar.css */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }.profile-section {
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
        }        .btn-secondary:hover {
            background: #e9ecef;
        }
        /* Responsive design handled in css/sidebar.css */
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
            <li><a href="active-deliveries.php" class="active"><i class="fas fa-box"></i> Active Deliveries</a></li>
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
            <h1 class="h2 mb-0">
                <i class="fas fa-box text-primary me-2"></i>
                Active Deliveries
            </h1>
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
        </div>        <!-- Deliveries Grid with Bootstrap -->
        <div class="row g-4">            <?php if ($active_deliveries->num_rows > 0): ?>
                <?php while ($delivery = $active_deliveries->fetch_assoc()): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white border-bottom">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0 fw-bold">Order #<?php echo htmlspecialchars($delivery['id']); ?></h6>
                                    <span class="badge <?php echo ($delivery['current_status'] ?? $delivery['status']) == 'in_progress' ? 'bg-primary' : 'bg-warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $delivery['current_status'] ?? $delivery['status'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="small text-muted mb-1">
                                        <i class="fas fa-map-marker-alt me-1"></i> Delivery Address
                                    </div>
                                    <div class="fw-medium"><?php echo htmlspecialchars($delivery['delivery_address'] ?? $delivery['delivery_details']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <div class="small text-muted mb-1">
                                        <i class="fas fa-clock me-1"></i> Estimated Delivery Time
                                    </div>
                                    <div class="fw-medium">
                                        <?php 
                                            $estimate = new DateTime($delivery['created_at']);
                                            $estimate->add(new DateInterval('PT2H'));
                                            echo $estimate->format('h:i A');
                                        ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="small text-muted mb-1">
                                        <i class="fas fa-box me-1"></i> Order Details
                                    </div>
                                    <div class="fw-medium"><?php echo htmlspecialchars($delivery['delivery_details']); ?></div>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <div class="d-grid gap-2">
                                    <?php if (($delivery['current_status'] ?? $delivery['status']) == 'pending'): ?>
                                        <a href="update_delivery_status.php?id=<?php echo $delivery['id']; ?>&status=in_progress" 
                                           class="btn btn-primary">
                                            <i class="fas fa-play me-1"></i> Start Delivery
                                        </a>
                                    <?php else: ?>
                                        <a href="update_delivery_status.php?id=<?php echo $delivery['id']; ?>&status=completed" 
                                           class="btn btn-success">
                                            <i class="fas fa-check me-1"></i> Mark as Delivered
                                        </a>
                                    <?php endif; ?>
                                    <a href="delivery_details.php?id=<?php echo $delivery['id']; ?>" 
                                       class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-eye me-1"></i> View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>            <?php else: ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-box-open text-primary" style="font-size: 3rem;"></i>
                            <h4 class="mt-3 mb-2">No Active Deliveries</h4>
                            <p class="text-muted">You don't have any active deliveries at the moment.</p>
                            <a href="courier-dashboard.php" class="btn btn-primary mt-2">
                                <i class="fas fa-home me-1"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?></div>
    </div>
    
    <!-- Bootstrap 5.3.0 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
