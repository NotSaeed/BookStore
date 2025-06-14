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

// Handle status update submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    try {
        $delivery_id = intval($_POST['delivery_id']);
        $old_status = trim($_POST['old_status']);
        $new_status = $_POST['new_status'];
        $update_reason = trim($_POST['update_reason']);
        
        // Validation
        if (empty($new_status)) {
            throw new Exception("Please select a new status.");
        }
        
        if (empty($update_reason)) {
            throw new Exception("Please provide a reason for the status update.");
        }
        
        if (strlen($update_reason) < 10) {
            throw new Exception("Update reason must be at least 10 characters long.");
        }
        
        // Verify delivery belongs to courier
        $stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ? AND courier_id = ?");
        $stmt->bind_param("is", $delivery_id, $courier_id);
        $stmt->execute();
        $delivery = $stmt->get_result()->fetch_assoc();
        
        if (!$delivery) {
            throw new Exception("Delivery not found or unauthorized.");
        }
        
        // Prevent invalid status transitions
        $valid_transitions = [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [], // No further transitions allowed
            'cancelled' => [] // No further transitions allowed
        ];
        
        if (!in_array($new_status, $valid_transitions[$old_status] ?? [])) {
            throw new Exception("Invalid status transition from {$old_status} to {$new_status}.");
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update delivery status
            $stmt = $conn->prepare("UPDATE deliveries SET status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("si", $new_status, $delivery_id);
            $stmt->execute();
            
            // Log the status change in delivery_status_log
            $stmt = $conn->prepare("INSERT INTO delivery_status_log (delivery_id, old_status, new_status, updated_by, update_reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $delivery_id, $old_status, $new_status, $courier_id, $update_reason);
            $stmt->execute();
            
            // Add entry to delivery_updates table for tracking
            $stmt = $conn->prepare("INSERT INTO delivery_updates (delivery_id, status, notes, update_time) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $delivery_id, $new_status, $update_reason);
            $stmt->execute();
            
            // If status is cancelled, also log in cancellations table
            if ($new_status === 'cancelled') {
                $stmt = $conn->prepare("INSERT INTO delivery_cancellations (delivery_id, cancelled_by, cancellation_reason) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $delivery_id, $courier_id, $update_reason);
                $stmt->execute();
            }
            
            $conn->commit();
            $success_message = "Delivery status updated successfully from '{$old_status}' to '{$new_status}'.";
            
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get all deliveries for the courier that can be updated
$stmt = $conn->prepare("
    SELECT d.*, c.name as customer_name,
           (SELECT COUNT(*) FROM delivery_status_log WHERE delivery_id = d.id) as status_changes_count,
           (SELECT update_reason FROM delivery_status_log WHERE delivery_id = d.id ORDER BY updated_at DESC LIMIT 1) as last_update_reason
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    WHERE d.courier_id = ? 
    AND d.status NOT IN ('completed', 'cancelled')
    ORDER BY d.created_at DESC
");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$updatable_deliveries = $stmt->get_result();

// Get recent status changes for this courier
$stmt = $conn->prepare("
    SELECT dsl.*, d.order_id, c.name as customer_name 
    FROM delivery_status_log dsl
    JOIN deliveries d ON dsl.delivery_id = d.id
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE d.courier_id = ?
    ORDER BY dsl.updated_at DESC
    LIMIT 10
");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$recent_changes = $stmt->get_result();

// Get statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT d.id) as total_deliveries,
        COUNT(DISTINCT CASE WHEN d.status = 'pending' THEN d.id END) as pending_count,
        COUNT(DISTINCT CASE WHEN d.status = 'in_progress' THEN d.id END) as in_progress_count,
        COUNT(DISTINCT CASE WHEN d.status = 'completed' THEN d.id END) as completed_count,
        COUNT(DISTINCT CASE WHEN d.status = 'cancelled' THEN d.id END) as cancelled_count,
        COUNT(DISTINCT dsl.id) as total_status_changes
    FROM deliveries d
    LEFT JOIN delivery_status_log dsl ON d.id = dsl.delivery_id
    WHERE d.courier_id = ?
");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Status Management - BookStore</title>
    <!-- Bootstrap 5.3.0 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/bootstrap-sidebar.css">
    <style>
        /* Page-specific styles only - sidebar styles moved to css/sidebar.css */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            border-left: 4px solid #9b59b6;
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

        .section-title {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f1f1f1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #9b59b6;
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Delivery Selection */
        .delivery-selector {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .delivery-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: white;
            border-radius: 5px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .delivery-option:hover {
            border-color: #9b59b6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .delivery-option.selected {
            border-color: #9b59b6;
            background: #f8f4ff;
        }

        .delivery-info h4 {
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }

        .delivery-info p {
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

        .btn-primary {
            background: #9b59b6;
            color: white;
        }

        .btn-primary:hover {
            background: #8e44ad;
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

        /* Recent Changes List */
        .changes-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .change-item {
            padding: 1rem;
            border-bottom: 1px solid #f1f1f1;
            transition: background 0.3s ease;
        }

        .change-item:hover {
            background: #f8f9fa;
        }

        .change-item:last-child {
            border-bottom: none;
        }

        .change-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .change-title {
            color: #2c3e50;
            font-weight: 600;
        }

        .change-time {
            color: #666;
            font-size: 0.8rem;
        }

        .status-transition {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0.5rem 0;
        }

        .status-arrow {
            color: #9b59b6;
        }

        .change-reason {
            color: #666;
            font-size: 0.9rem;
            font-style: italic;
            margin-top: 0.5rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }        .empty-state i {
            font-size: 3rem;
            color: #9b59b6;
            margin-bottom: 1rem;
        }

        /* Tab Styles */
        .tab-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .tab-navigation {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .tab-button {
            flex: 1;
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .tab-button:hover {
            background: #e9ecef;
            color: #3498db;
        }

        .tab-button.active {
            background: white;
            color: #3498db;
            border-bottom: 3px solid #3498db;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .section-description {
            color: #666;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
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
        </div>        <ul class="nav-links">
            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="delivery-status-management.php" class="active"><i class="fas fa-edit"></i> Status & Cancel Management</a></li>
            <li><a href="customer-feedback.php"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">        <div class="page-header">
            <h1><i class="fas fa-edit"></i> Status & Cancel Management</h1>
            <p>Update delivery statuses or cancel deliveries with comprehensive tracking and logging</p>
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
                <h3>Completed</h3>
                <div class="value"><?php echo $stats['completed_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Cancelled</h3>
                <div class="value"><?php echo $stats['cancelled_count'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <h3>Status Updates</h3>
                <div class="value"><?php echo $stats['total_status_changes'] ?? 0; ?></div>
            </div>        </div>

        <!-- Tab Navigation -->
        <div class="tab-container">
            <div class="tab-navigation">
                <button class="tab-button active" onclick="showTab('status-tab')">
                    <i class="fas fa-edit"></i> Update Status
                </button>
                <button class="tab-button" onclick="showTab('cancel-tab')">
                    <i class="fas fa-times-circle"></i> Cancel Deliveries
                </button>
            </div>

            <!-- Status Update Tab -->
            <div id="status-tab" class="tab-content active">
                <!-- Main Content Grid -->
                <div class="content-grid">
            <!-- Status Update Form -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-edit"></i>
                    Update Delivery Status
                </h2>

                <?php if ($updatable_deliveries->num_rows > 0): ?>
                    <form method="POST" id="statusUpdateForm">
                        <div class="form-group">
                            <label>Select Delivery to Update:</label>
                            <div class="delivery-selector">
                                <?php while ($delivery = $updatable_deliveries->fetch_assoc()): ?>
                                    <div class="delivery-option" onclick="selectDelivery(<?php echo $delivery['id']; ?>, '<?php echo $delivery['status']; ?>')">
                                        <div class="delivery-info">
                                            <h4>Order #<?php echo htmlspecialchars($delivery['order_id']); ?></h4>
                                            <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($delivery['customer_name'] ?? 'Unknown Customer'); ?></p>
                                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($delivery['delivery_address']); ?></p>
                                            <p><i class="fas fa-clock"></i> Created: <?php echo date('M d, Y h:i A', strtotime($delivery['created_at'])); ?></p>
                                            <?php if ($delivery['status_changes_count'] > 0): ?>
                                                <p><i class="fas fa-history"></i> <?php echo $delivery['status_changes_count']; ?> status changes</p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="status-badge status-<?php echo str_replace('_', '-', $delivery['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>

                        <input type="hidden" id="delivery_id" name="delivery_id" required>
                        <input type="hidden" id="old_status" name="old_status" required>

                        <div class="form-group">
                            <label for="new_status">New Status:</label>
                            <select id="new_status" name="new_status" required disabled>
                                <option value="">Select a delivery first</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="update_reason">Reason for Update:</label>
                            <textarea id="update_reason" name="update_reason" 
                                    placeholder="Please provide a detailed reason for this status update (minimum 10 characters)"
                                    required disabled></textarea>
                        </div>

                        <button type="submit" name="update_status" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>No Deliveries to Update</h3>
                        <p>All your deliveries are either completed or cancelled.</p>
                        <a href="active-deliveries.php" class="btn btn-secondary">
                            <i class="fas fa-box"></i> View Active Deliveries
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Status Changes -->
            <div class="section">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Recent Status Changes
                </h2>

                <?php if ($recent_changes->num_rows > 0): ?>
                    <div class="changes-list">
                        <?php while ($change = $recent_changes->fetch_assoc()): ?>
                            <div class="change-item">
                                <div class="change-header">
                                    <span class="change-title">Order #<?php echo htmlspecialchars($change['order_id']); ?></span>
                                    <span class="change-time"><?php echo date('M d, h:i A', strtotime($change['updated_at'])); ?></span>
                                </div>
                                <div class="status-transition">
                                    <span class="status-badge status-<?php echo str_replace('_', '-', $change['old_status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $change['old_status'])); ?>
                                    </span>
                                    <i class="fas fa-arrow-right status-arrow"></i>
                                    <span class="status-badge status-<?php echo str_replace('_', '-', $change['new_status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $change['new_status'])); ?>
                                    </span>
                                </div>
                                <div class="change-reason">
                                    "<?php echo htmlspecialchars($change['update_reason']); ?>"
                                </div>
                                <?php if ($change['customer_name']): ?>
                                    <p style="color: #666; font-size: 0.8rem; margin-top: 0.5rem;">
                                        <i class="fas fa-user"></i> Customer: <?php echo htmlspecialchars($change['customer_name']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <a href="delivery-history.php" class="btn btn-secondary">
                            <i class="fas fa-history"></i> View Full History
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No Recent Changes</h3>
                        <p>Your status change history will appear here.</p>
                    </div>                <?php endif; ?>
            </div>
        </div>
            </div> <!-- End Status Tab -->

            <!-- Cancel Deliveries Tab -->
            <div id="cancel-tab" class="tab-content">
                <div class="content-grid">
                    <div class="section">
                        <h2 class="section-title">
                            <i class="fas fa-times-circle"></i>
                            Cancel Deliveries
                        </h2>
                        <p class="section-description">Cancel pending or in-progress deliveries with proper tracking and documentation.</p>
                        
                        <div style="text-align: center; padding: 2rem; background: #f8f9fa; border-radius: 10px; margin: 2rem 0;">
                            <i class="fas fa-external-link-alt" style="font-size: 2rem; color: #3498db; margin-bottom: 1rem;"></i>                            <h3>Access Full Cancel Management</h3>
                            <p>For comprehensive cancellation features including bulk operations and detailed tracking, visit the dedicated cancel page.</p>
                            <a href="cancel-deliveries.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-times-circle"></i> Go to Full Cancel System
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- End Tab Container -->
    </div>

    <script>
        // Tab functionality
        function showTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Existing JavaScript code
        let selectedDeliveryId = null;
        let selectedOldStatus = null;

        function selectDelivery(deliveryId, oldStatus) {
            // Remove previous selection
            document.querySelectorAll('.delivery-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Select current option
            event.currentTarget.classList.add('selected');
            
            selectedDeliveryId = deliveryId;
            selectedOldStatus = oldStatus;
            
            // Update form fields
            document.getElementById('delivery_id').value = deliveryId;
            document.getElementById('old_status').value = oldStatus;
            
            // Update status options based on current status
            updateStatusOptions(oldStatus);
            
            // Enable form fields
            document.getElementById('new_status').disabled = false;
            document.getElementById('update_reason').disabled = false;
            document.getElementById('submitBtn').disabled = false;
        }

        function updateStatusOptions(currentStatus) {
            const statusSelect = document.getElementById('new_status');
            statusSelect.innerHTML = '';
            
            const statusTransitions = {
                'pending': [
                    {value: 'in_progress', text: 'In Progress'},
                    {value: 'cancelled', text: 'Cancelled'}
                ],
                'in_progress': [
                    {value: 'completed', text: 'Completed'},
                    {value: 'cancelled', text: 'Cancelled'}
                ]
            };
            
            const availableStatuses = statusTransitions[currentStatus] || [];
            
            if (availableStatuses.length === 0) {
                statusSelect.innerHTML = '<option value="">No valid transitions available</option>';
                statusSelect.disabled = true;
                return;
            }
            
            statusSelect.innerHTML = '<option value="">Select new status</option>';
            availableStatuses.forEach(status => {
                const option = document.createElement('option');
                option.value = status.value;
                option.textContent = status.text;
                statusSelect.appendChild(option);
            });
        }

        // Form validation
        document.getElementById('statusUpdateForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('update_reason').value.trim();
            if (reason.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed reason (at least 10 characters).');
                return false;
            }
            
            const newStatus = document.getElementById('new_status').value;
            if (!newStatus) {
                e.preventDefault();
                alert('Please select a new status.');
                return false;
            }
            
            // Confirm the action
            const oldStatus = document.getElementById('old_status').value;
            if (!confirm(`Are you sure you want to update the status from "${oldStatus}" to "${newStatus}"?`)) {
                e.preventDefault();
                return false;
            }
        });

        // Auto-expand textarea
        document.getElementById('update_reason').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';        });
    </script>
    
    <!-- Bootstrap 5.3.0 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
