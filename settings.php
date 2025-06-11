<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier-login.html");
    exit();
}

$courier_id = $_SESSION['courier_id'];

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM couriers WHERE courier_id = ?");
        $stmt->bind_param("s", $courier_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $courier = $result->fetch_assoc();

        if (!password_verify($current_password, $courier['password'])) {
            throw new Exception("Current password is incorrect");
        }

        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match");
        }

        if (strlen($new_password) < 8) {
            throw new Exception("Password must be at least 8 characters long");
        }

        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE couriers SET password = ? WHERE courier_id = ?");
        $update_stmt->bind_param("ss", $hashed_password, $courier_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['success_message'] = "Password updated successfully!";
            header("Location: settings.php");
            exit();
        } else {
            throw new Exception("Failed to update password");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

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
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

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
    $error_message = "Could not load settings. Using defaults.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BookStore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        :root {
            --primary-color: #9b59b6;
            --primary-dark: #8e44ad;
            --text-color: #2c3e50;
            --background-color: #f4f6f8;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--background-color);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .settings-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .settings-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: bold;
        }

        .form-group input[type="password"] {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input[type="password"]:focus {
            border-color: var(--primary-color);
            outline: none;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .toggle-switch label {
            margin-left: 1rem;
            color: var(--text-color);
            font-weight: normal;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
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
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
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
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            justify-content: center;
        }

        .btn-save:hover {
            background: var(--primary-dark);
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
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

        h2 {
            color: var(--text-color);
            margin-top: 0;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-truck"></i>
            <h2>Courier Dashboard</h2>
        </div>
        <ul class="nav-links">
            <li><a href="courier-dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="active-deliveries.php"><i class="fas fa-box"></i> Active Deliveries</a></li>
            <li><a href="delivery-history.php"><i class="fas fa-history"></i> Delivery History</a></li>
            <li><a href="route-planning.php"><i class="fas fa-route"></i> Route Planning</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1><i class="fas fa-cog"></i> Settings</h1>

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
        <?php endif; ?>

        <div class="settings-container">
            <div class="settings-card">
                <h2><i class="fas fa-lock"></i> Change Password</h2>
                <form action="" method="POST" id="passwordForm">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <input type="hidden" name="change_password" value="1">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Update Password
                    </button>
                </form>
            </div>

            <div class="settings-card">
                <h2><i class="fas fa-bell"></i> Notification Settings</h2>
                <form action="" method="POST">
                    <div class="toggle-switch">
                        <label class="switch">
                            <input type="checkbox" name="email_notifications" 
                                   <?php echo $settings['email_notifications'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <label>Email Notifications</label>
                    </div>

                    <div class="toggle-switch">
                        <label class="switch">
                            <input type="checkbox" name="sms_notifications"
                                   <?php echo $settings['sms_notifications'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <label>SMS Notifications</label>
                    </div>

                    <div class="toggle-switch">
                        <label class="switch">
                            <input type="checkbox" name="push_notifications"
                                   <?php echo $settings['push_notifications'] ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                        <label>Push Notifications</label>
                    </div>

                    <input type="hidden" name="update_notifications" value="1">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Update Notifications
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
            }
        });
    </script>
</body>
</html>
