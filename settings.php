<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier-login.html");
    exit();
}

$courier_id = $_SESSION['courier_id'];

// Handle notification settings
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_notifications'])) {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notifications = isset($_POST['sms_notifications']) ? 1 : 0;
        $push_notifications = isset($_POST['push_notifications']) ? 1 : 0;

        $update_stmt = $conn->prepare("
            UPDATE courier_settings 
            SET email_notifications = ?, 
                sms_notifications = ?, 
                push_notifications = ? 
            WHERE courier_id = ?");
        $update_stmt->bind_param("iiis", $email_notifications, $sms_notifications, $push_notifications, $courier_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Notification settings updated successfully!";
            header("Location: settings.php");
            exit();
        } else {
            throw new Exception("Failed to update notification settings");
        }    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get courier information with preferences
$courier_stmt = $conn->prepare("SELECT * FROM couriers WHERE courier_id = ?");
$courier_stmt->bind_param("s", $courier_id);
$courier_stmt->execute();
$courier_info = $courier_stmt->get_result()->fetch_assoc();

// Get or create notification settings
try {
    $settings_stmt = $conn->prepare("SELECT * FROM courier_settings WHERE courier_id = ?");
    $settings_stmt->bind_param("s", $courier_id);
    $settings_stmt->execute();
    $settings = $settings_stmt->get_result()->fetch_assoc();

    // If no settings exist, create default settings
    if (!$settings) {
        $insert_stmt = $conn->prepare("INSERT INTO courier_settings (courier_id, email_notifications, sms_notifications, push_notifications) VALUES (?, 1, 1, 1)");
        $insert_stmt->bind_param("s", $courier_id);
        $insert_stmt->execute();
        
        $settings = [
            'email_notifications' => 1,
            'sms_notifications' => 1,
            'push_notifications' => 1
        ];
    }
} catch (Exception $e) {
    // If there's any database error, use default settings
    $settings = [
        'email_notifications' => 1,
        'sms_notifications' => 1,
        'push_notifications' => 1
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Settings - BookStore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>

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
        }        .nav-links a.active {
            background: #8e44ad;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: var(--text-color);
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #666;
            font-size: 1rem;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .settings-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-top: 4px solid var(--primary-color);
        }

        .settings-card h2 {
            color: var(--text-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-card h2 i {
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .toggle-section {
            margin-bottom: 2rem;
        }

        .toggle-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .toggle-item:last-child {
            border-bottom: none;
        }

        .toggle-info {
            flex: 1;
        }

        .toggle-title {
            color: var(--text-color);
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .toggle-description {
            color: #666;
            font-size: 0.85rem;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
        }        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ddd;
            transition: .4s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--primary-color);
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .btn-save {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
            width: 100%;
            margin-top: 1rem;
        }

        .btn-save:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #e8f5e8;
            color: #2e7d32;
            border-left-color: #4caf50;
        }        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left-color: #f44336;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border-left: 3px solid var(--primary-color);
        }

        .info-item strong {
            color: var(--text-color);
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
        }

        @media (max-width: 1200px) {
            .settings-grid {
                grid-template-columns: 1fr;
            }
        }

        h2 {
            color: var(--text-color);
            margin-top: 0;            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-truck"></i>
            <h2>Courier Dashboard</h2>
        </div>        <ul class="nav-links">
            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="delivery-status-management.php"><i class="fas fa-edit"></i> Status & Cancel Management</a></li>
            <li><a href="customer-feedback.php"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>    <div class="main-content">        <div class="page-header">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <p>Manage your notifications and account information</p>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>        <div class="settings-grid">
            <div class="settings-card">
                <h2><i class="fas fa-bell"></i> Notification Settings</h2>
                <div class="toggle-section">
                    <form action="" method="POST">                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-title">Email Notifications</div>
                                <div class="toggle-description">Receive updates via email</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_notifications" 
                                       <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-title">SMS Notifications</div>
                                <div class="toggle-description">Get instant alerts via text</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="sms_notifications"
                                       <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-item">
                            <div class="toggle-info">
                                <div class="toggle-title">Push Notifications</div>
                                <div class="toggle-description">Real-time notifications on device</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="push_notifications"
                                       <?php echo $settings['push_notifications'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <input type="hidden" name="update_notifications" value="1">
                        <button type="submit" class="btn-save">
                            <i class="fas fa-save"></i> Update Notifications
                        </button>
                    </form>
                </div>
            </div>

            <div class="settings-card">
                <h2><i class="fas fa-info-circle"></i> Account Information</h2>                <div class="preference-card">
                    <div class="preference-title">
                        <i class="fas fa-user"></i> Account Details
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <strong>Name:</strong> <?php echo htmlspecialchars($courier_info['name']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Email:</strong> <?php echo htmlspecialchars($courier_info['email']); ?>
                        </div>
                        <div class="info-item">
                            <strong>Phone:</strong> <?php echo htmlspecialchars($courier_info['phone'] ?? 'Not set'); ?>
                        </div>
                        <div class="info-item">
                            <strong>Rating:</strong> <?php echo number_format($courier_info['avg_rating'] ?? 0, 1); ?> ⭐
                        </div>
                    </div>
                    <p style="margin-top: 1rem; color: #666; font-size: 0.9rem;">
                        <i class="fas fa-edit"></i> <a href="courier-profile.php" style="color: var(--primary-color);">Edit Profile</a> to update your information.
                    </p>
                </div>
            </div>
        </div>
    </div>    <script>
        // Show success message for settings updates
        document.addEventListener('DOMContentLoaded', function() {
            const successAlerts = document.querySelectorAll('.alert-success');
            successAlerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>
