<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier-login.html");
    exit();
}

$courier_id = $_SESSION['courier_id'];
$success_message = '';
$error_message = '';

// Handle delivery cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cancel_delivery'])) {
    try {
        $delivery_id = intval($_POST['delivery_id']);
        $cancellation_reason = trim($_POST['cancellation_reason']);
        $confirm_cancellation = isset($_POST['confirm_cancellation']);
        
        // Validation
        if (!$confirm_cancellation) {
            throw new Exception("Please confirm that you want to cancel this delivery.");
        }
        
        if (empty($cancellation_reason)) {
            throw new Exception("Please provide a reason for cancelling this delivery.");
        }
        
        if (strlen($cancellation_reason) < 15) {
            throw new Exception("Cancellation reason must be at least 15 characters long.");
        }
        
        // Verify delivery belongs to courier and can be cancelled
        $stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ? AND courier_id = ? AND status IN ('pending', 'in_progress')");
        $stmt->bind_param("is", $delivery_id, $courier_id);
        $stmt->execute();
        $delivery = $stmt->get_result()->fetch_assoc();
        
        if (!$delivery) {
            throw new Exception("Delivery not found, unauthorized, or cannot be cancelled.");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update delivery status to cancelled
            $stmt = $conn->prepare("UPDATE deliveries SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $delivery_id);
            $stmt->execute();
            
            // Log the cancellation in delivery_cancellations table
            $stmt = $conn->prepare("INSERT INTO delivery_cancellations (delivery_id, cancelled_by, cancellation_reason) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $delivery_id, $courier_id, $cancellation_reason);
            $stmt->execute();
            
            // Log the status change in delivery_status_log
            $stmt = $conn->prepare("INSERT INTO delivery_status_log (delivery_id, old_status, new_status, updated_by, update_reason) VALUES (?, ?, 'cancelled', ?, ?)");
            $stmt->bind_param("isss", $delivery_id, $delivery['status'], $courier_id, $cancellation_reason);
            $stmt->execute();
            
            // Add entry to delivery_updates table for tracking
            $stmt = $conn->prepare("INSERT INTO delivery_updates (delivery_id, status, notes, update_time) VALUES (?, 'cancelled', ?, NOW())");
            $stmt->bind_param("is", $delivery_id, $cancellation_reason);
            $stmt->execute();
            
            $conn->commit();
            $success_message = "Delivery #{$delivery['order_id']} has been successfully cancelled.";
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle bulk cancellation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_cancel'])) {
    try {
        $selected_deliveries = $_POST['selected_deliveries'] ?? [];
        $bulk_reason = trim($_POST['bulk_cancellation_reason']);
        
        if (empty($selected_deliveries)) {
            throw new Exception("Please select at least one delivery to cancel.");
        }
        
        if (empty($bulk_reason)) {
            throw new Exception("Please provide a reason for bulk cancellation.");
        }
        
        if (strlen($bulk_reason) < 20) {
            throw new Exception("Bulk cancellation reason must be at least 20 characters long.");
        }
        
        $cancelled_count = 0;
        $conn->begin_transaction();
        
        try {
            foreach ($selected_deliveries as $delivery_id) {
                $delivery_id = intval($delivery_id);
                
                // Verify delivery belongs to courier and can be cancelled
                $stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ? AND courier_id = ? AND status IN ('pending', 'in_progress')");
                $stmt->bind_param("is", $delivery_id, $courier_id);
                $stmt->execute();
                $delivery = $stmt->get_result()->fetch_assoc();
                
                if ($delivery) {
                    // Update delivery status
                    $stmt = $conn->prepare("UPDATE deliveries SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param("i", $delivery_id);
                    $stmt->execute();
                    
                    // Log cancellation
                    $stmt = $conn->prepare("INSERT INTO delivery_cancellations (delivery_id, cancelled_by, cancellation_reason) VALUES (?, ?, ?)");
                    $stmt->bind_param("iss", $delivery_id, $courier_id, $bulk_reason);
                    $stmt->execute();
                    
                    // Log status change
                    $stmt = $conn->prepare("INSERT INTO delivery_status_log (delivery_id, old_status, new_status, updated_by, update_reason) VALUES (?, ?, 'cancelled', ?, ?)");
                    $stmt->bind_param("isss", $delivery_id, $delivery['status'], $courier_id, $bulk_reason);
                    $stmt->execute();
                    
                    // Add to delivery updates
                    $stmt = $conn->prepare("INSERT INTO delivery_updates (delivery_id, status, notes, update_time) VALUES (?, 'cancelled', ?, NOW())");
                    $stmt->bind_param("is", $delivery_id, $bulk_reason);
                    $stmt->execute();
                    
                    $cancelled_count++;
                }
            }
            
            $conn->commit();
            $success_message = "Successfully cancelled {$cancelled_count} delivery(ies).";
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get cancellable deliveries (pending and in_progress only)
$stmt = $conn->prepare("
    SELECT d.*, c.name as customer_name,
           (SELECT COUNT(*) FROM delivery_status_log WHERE delivery_id = d.id) as status_changes_count
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    WHERE d.courier_id = ? 
    AND d.status IN ('pending', 'in_progress')
    ORDER BY d.created_at DESC
");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$cancellable_deliveries = $stmt->get_result();

// Get recent cancellations for this courier
$stmt = $conn->prepare("
    SELECT dc.*, d.order_id, c.name as customer_name 
    FROM delivery_cancellations dc
    JOIN deliveries d ON dc.delivery_id = d.id
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE d.courier_id = ?
    ORDER BY dc.cancelled_at DESC
    LIMIT 10
");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$recent_cancellations = $stmt->get_result();

// Get cancellation statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT d.id) as total_deliveries,
        COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.id END) as pending_count,
        COUNT(DISTINCT CASE WHEN d.status = 'in_progress' THEN d.id END) as in_progress_count,
        COUNT(DISTINCT CASE WHEN d.status = 'cancelled' THEN d.id END) as cancelled_count,
        COUNT(DISTINCT dc.id) as total_cancellations,
        ROUND((COUNT(DISTINCT CASE WHEN d.status = 'cancelled' THEN d.id END) / COUNT(DISTINCT d.id)) * 100, 2) as cancellation_rate
    FROM deliveries d
    LEFT JOIN delivery_cancellations dc ON d.id = dc.delivery_id
    WHERE d.courier_id = ?
");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Pending Deliveries - BookStore</title>
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
            overflow-y: auto;
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

        .page-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
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

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid #e74c3c;
        }

        .stat-card h3 {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 1.8rem;
            color: #2c3e50;
            font-weight: bold;
        }

        .stat-card.warning .value {
            color: #e74c3c;
        }

        /* Main Content Sections */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .section {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .section.full-width {
            grid-column: 1 / -1;
        }

        .section-title {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Delivery Cards */
        .delivery-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .delivery-card:hover {
            border-color: #e74c3c;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .delivery-card.selected {
            border-color: #e74c3c;
            background: #ffeaea;
        }

        .delivery-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-info h3 {
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .order-info p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
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

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
            resize: vertical;
            min-height: 100px;
        }

        .form-group textarea:focus {
            border-color: #e74c3c;
            outline: none;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-group input[type="checkbox"] {
            transform: scale(1.2);
        }

        /* Button Styles */
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

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
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

        /* Bulk Actions */
        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 2px dashed #dee2e6;
        }

        .bulk-actions.active {
            border-color: #e74c3c;
            background: #ffeaea;
        }

        .delivery-selector {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .delivery-selector input[type="checkbox"] {
            transform: scale(1.3);
        }

        /* Recent Cancellations */
        .cancellation-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.3s ease;
        }

        .cancellation-item:hover {
            background: #f8f9fa;
        }

        .cancellation-item:last-child {
            border-bottom: none;
        }

        .cancellation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .cancellation-title {
            color: #2c3e50;
            font-weight: 600;
        }

        .cancellation-time {
            color: #666;
            font-size: 0.8rem;
        }

        .cancellation-reason {
            color: #666;
            font-size: 0.9rem;
            font-style: italic;
            margin-top: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #27ae60;
            margin-bottom: 1rem;
        }

        /* Warning Box */
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .warning-box strong {
            display: block;
            margin-bottom: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="delivery-status-management.php"><i class="fas fa-edit"></i> Status Management</a></li>            <li><a href="cancel-deliveries.php" class="active"><i class="fas fa-times-circle"></i> Cancel Deliveries</a></li>
            <li><a href="customer-feedback.php"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-times-circle"></i> Cancel Pending Deliveries</h1>
            <p>Manage delivery cancellations with comprehensive tracking and reporting</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Warning about cancellations -->
        <div class="warning-box">
            <strong><i class="fas fa-exclamation-triangle"></i> Important Notice:</strong>
            Cancelling deliveries affects customer satisfaction and should only be done when absolutely necessary. 
            All cancellations are tracked and logged for accountability.
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Deliveries</h3>
                <div class="value"><?php echo $stats['total_deliveries'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Pending</h3>
                <div class="value"><?php echo $stats['pending_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>In Progress</h3>
                <div class="value"><?php echo $stats['in_progress_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Cancelled</h3>
                <div class="value"><?php echo $stats['cancelled_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card warning">
                <h3>Cancellation Rate</h3>
                <div class="value"><?php echo $stats['cancellation_rate'] ?? 0; ?>%</div>
            </div>
        </div>

        <?php if ($cancellable_deliveries->num_rows > 0): ?>
            <!-- Bulk Cancellation Section -->
            <div class="section full-width">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Cancellable Deliveries
                </h2>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <span><span id="selectedCount">0</span> delivery(ies) selected</span>
                        <div>
                            <button type="button" class="btn btn-secondary" onclick="selectAll()">Select All</button>
                            <button type="button" class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
                            <button type="button" class="btn btn-warning" onclick="showBulkCancelForm()">Bulk Cancel</button>
                        </div>
                    </div>

                    <!-- Bulk Cancellation Form -->
                    <form method="POST" id="bulkCancelForm" style="display: none;">
                        <div class="form-group">
                            <label for="bulk_cancellation_reason">Reason for Bulk Cancellation:</label>
                            <textarea id="bulk_cancellation_reason" name="bulk_cancellation_reason" 
                                    placeholder="Please provide a detailed reason for cancelling these deliveries (minimum 20 characters)"
                                    required></textarea>
                        </div>
                        <div class="checkbox-group">
                            <input type="checkbox" id="bulk_confirm" required>
                            <label for="bulk_confirm">I confirm that I want to cancel the selected deliveries</label>
                        </div>
                        <button type="submit" name="bulk_cancel" class="btn btn-danger">
                            <i class="fas fa-times-circle"></i> Cancel Selected Deliveries
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideBulkCancelForm()">
                            <i class="fas fa-times"></i> Cancel Action
                        </button>
                    </form>
                </div>

                <!-- Deliveries List -->
                <div class="deliveries-list">
                    <?php while ($delivery = $cancellable_deliveries->fetch_assoc()): ?>
                        <div class="delivery-card" data-delivery-id="<?php echo $delivery['id']; ?>">
                            <div class="delivery-selector">
                                <input type="checkbox" name="delivery_checkbox" value="<?php echo $delivery['id']; ?>" 
                                       onchange="updateSelection()">
                            </div>

                            <div class="delivery-header">
                                <div class="order-info">
                                    <h3>Order #<?php echo htmlspecialchars($delivery['order_id']); ?></h3>
                                    <p><i class="fas fa-user"></i> Customer: <?php echo htmlspecialchars($delivery['customer_name'] ?? 'Unknown Customer'); ?></p>
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($delivery['delivery_address']); ?></p>
                                    <p><i class="fas fa-box"></i> <?php echo htmlspecialchars($delivery['delivery_details']); ?></p>
                                    <p><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y h:i A', strtotime($delivery['created_at'])); ?></p>
                                    <?php if ($delivery['status_changes_count'] > 0): ?>
                                        <p><i class="fas fa-history"></i> <?php echo $delivery['status_changes_count']; ?> status changes</p>
                                    <?php endif; ?>
                                </div>
                                <span class="status-badge status-<?php echo str_replace('_', '-', $delivery['status']); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                </span>
                            </div>

                            <!-- Individual Cancellation Form -->
                            <div class="individual-cancel" style="margin-top: 1rem; border-top: 1px solid #eee; padding-top: 1rem;">
                                <button type="button" class="btn btn-danger" onclick="showCancelForm(<?php echo $delivery['id']; ?>)">
                                    <i class="fas fa-times-circle"></i> Cancel This Delivery
                                </button>

                                <form method="POST" id="cancelForm<?php echo $delivery['id']; ?>" style="display: none; margin-top: 1rem;">
                                    <input type="hidden" name="delivery_id" value="<?php echo $delivery['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="cancellation_reason<?php echo $delivery['id']; ?>">Reason for Cancellation:</label>
                                        <textarea id="cancellation_reason<?php echo $delivery['id']; ?>" name="cancellation_reason" 
                                                placeholder="Please provide a detailed reason for cancelling this delivery (minimum 15 characters)"
                                                required></textarea>
                                    </div>
                                    
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="confirm_cancellation<?php echo $delivery['id']; ?>" name="confirm_cancellation" required>
                                        <label for="confirm_cancellation<?php echo $delivery['id']; ?>">I confirm that I want to cancel this delivery</label>
                                    </div>
                                    
                                    <button type="submit" name="cancel_delivery" class="btn btn-danger">
                                        <i class="fas fa-times-circle"></i> Confirm Cancellation
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="hideCancelForm(<?php echo $delivery['id']; ?>)">
                                        <i class="fas fa-times"></i> Cancel Action
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Recent Cancellations -->
            <div class="content-grid" style="margin-top: 2rem;">
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Recent Cancellations
                    </h2>

                    <?php if ($recent_cancellations->num_rows > 0): ?>
                        <div class="cancellations-list" style="max-height: 400px; overflow-y: auto;">
                            <?php while ($cancellation = $recent_cancellations->fetch_assoc()): ?>
                                <div class="cancellation-item">
                                    <div class="cancellation-header">
                                        <span class="cancellation-title">Order #<?php echo htmlspecialchars($cancellation['order_id']); ?></span>
                                        <span class="cancellation-time"><?php echo date('M d, h:i A', strtotime($cancellation['cancelled_at'])); ?></span>
                                    </div>
                                    <div class="cancellation-reason">
                                        "<?php echo htmlspecialchars($cancellation['cancellation_reason']); ?>"
                                    </div>
                                    <?php if ($cancellation['customer_name']): ?>
                                        <p style="color: #666; font-size: 0.8rem; margin-top: 0.5rem;">
                                            <i class="fas fa-user"></i> Customer: <?php echo htmlspecialchars($cancellation['customer_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <h3>No Recent Cancellations</h3>
                            <p>You haven't cancelled any deliveries recently.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Quick Actions -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-tools"></i>
                        Quick Actions
                    </h2>

                    <div style="display: grid; gap: 1rem;">
                        <a href="active-deliveries.php" class="btn btn-secondary">
                            <i class="fas fa-box"></i> View Active Deliveries
                        </a>
                        <a href="delivery-status-management.php" class="btn btn-secondary">
                            <i class="fas fa-edit"></i> Manage Status Updates
                        </a>
                        <a href="delivery-history.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i> View Delivery History
                        </a>
                        <a href="courier-dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- No Cancellable Deliveries -->
            <div class="section full-width">
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h2>No Deliveries to Cancel</h2>
                    <p>You don't have any pending or in-progress deliveries that can be cancelled.</p>
                    <div style="margin-top: 2rem;">
                        <a href="active-deliveries.php" class="btn btn-secondary">
                            <i class="fas fa-box"></i> View Active Deliveries
                        </a>
                        <a href="courier-dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-home"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let selectedDeliveries = [];

        function updateSelection() {
            const checkboxes = document.querySelectorAll('input[name="delivery_checkbox"]:checked');
            selectedDeliveries = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selectedCount').textContent = selectedDeliveries.length;
            
            const bulkActions = document.getElementById('bulkActions');
            if (selectedDeliveries.length > 0) {
                bulkActions.classList.add('active');
            } else {
                bulkActions.classList.remove('active');
                hideBulkCancelForm();
            }

            // Update delivery card styling
            document.querySelectorAll('.delivery-card').forEach(card => {
                const deliveryId = card.dataset.deliveryId;
                if (selectedDeliveries.includes(deliveryId)) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }

        function selectAll() {
            document.querySelectorAll('input[name="delivery_checkbox"]').forEach(cb => {
                cb.checked = true;
            });
            updateSelection();
        }

        function clearSelection() {
            document.querySelectorAll('input[name="delivery_checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            updateSelection();
        }

        function showBulkCancelForm() {
            if (selectedDeliveries.length === 0) {
                alert('Please select at least one delivery to cancel.');
                return;
            }
            document.getElementById('bulkCancelForm').style.display = 'block';
            
            // Add hidden inputs for selected deliveries
            const form = document.getElementById('bulkCancelForm');
            selectedDeliveries.forEach(deliveryId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_deliveries[]';
                input.value = deliveryId;
                form.appendChild(input);
            });
        }

        function hideBulkCancelForm() {
            document.getElementById('bulkCancelForm').style.display = 'none';
            
            // Remove hidden inputs
            document.querySelectorAll('input[name="selected_deliveries[]"]').forEach(input => {
                input.remove();
            });
        }

        function showCancelForm(deliveryId) {
            document.getElementById('cancelForm' + deliveryId).style.display = 'block';
        }

        function hideCancelForm(deliveryId) {
            document.getElementById('cancelForm' + deliveryId).style.display = 'none';
        }

        // Form validation
        document.getElementById('bulkCancelForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('bulk_cancellation_reason').value.trim();
            if (reason.length < 20) {
                e.preventDefault();
                alert('Please provide a more detailed reason (at least 20 characters).');
                return false;
            }
            
            if (!confirm(`Are you sure you want to cancel ${selectedDeliveries.length} delivery(ies)?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Individual form validation
        document.querySelectorAll('form[id^="cancelForm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const textarea = this.querySelector('textarea[name="cancellation_reason"]');
                const reason = textarea.value.trim();
                
                if (reason.length < 15) {
                    e.preventDefault();
                    alert('Please provide a more detailed reason (at least 15 characters).');
                    return false;
                }
                
                if (!confirm('Are you sure you want to cancel this delivery?')) {
                    e.preventDefault();
                    return false;
                }
            });
        });

        // Auto-expand textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });
    </script>
</body>
</html>
