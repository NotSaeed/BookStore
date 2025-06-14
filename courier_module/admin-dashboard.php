<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin-login.html');
    exit();
}

require_once 'db_connect.php';

// Get some basic statistics
$courier_count = 0;
$delivery_count = 0;
$customer_count = 0;

// Get courier count
$result = $conn->query("SELECT COUNT(*) as count FROM couriers");
if ($result) {
    $courier_count = $result->fetch_assoc()['count'];
}

// Get delivery count
$result = $conn->query("SELECT COUNT(*) as count FROM deliveries");
if ($result) {
    $delivery_count = $result->fetch_assoc()['count'];
}

// Get customer count
$result = $conn->query("SELECT COUNT(*) as count FROM customers");
if ($result) {
    $customer_count = $result->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - BookStore</title>
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

        /* Header */
        .header {
            background: #e74c3c;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
        }

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
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.couriers {
            background: #9b59b6;
        }

        .stat-icon.deliveries {
            background: #3498db;
        }

        .stat-icon.customers {
            background: #2ecc71;
        }

        .stat-info h3 {
            color: #2c3e50;
            font-size: 2rem;
        }

        .stat-info p {
            color: #666;
            margin-top: 0.5rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .quick-actions h2 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #2c3e50;
            text-align: center;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: #f8f9fa;
            border-color: #e74c3c;
            color: #e74c3c;
        }

        .action-btn i {
            display: block;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .main-content {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>
            <i class="fas fa-user-shield"></i>
            Admin Dashboard
        </h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon couriers">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $courier_count; ?></h3>
                    <p>Total Couriers</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon deliveries">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $delivery_count; ?></h3>
                    <p>Total Deliveries</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $customer_count; ?></h3>
                    <p>Total Customers</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-grid">
                <a href="#" class="action-btn">
                    <i class="fas fa-users-cog"></i>
                    Manage Couriers
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    View Reports
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-cog"></i>
                    System Settings
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-database"></i>
                    Database Management
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-user-plus"></i>
                    Add New User
                </a>
                <a href="#" class="action-btn">
                    <i class="fas fa-shield-alt"></i>
                    Security Settings
                </a>
            </div>
        </div>
    </div>
</body>
</html>
