<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier-login.html");
    exit();
}

// Get courier details with delivery statistics
$courier_id = $_SESSION['courier_id'];
$sql = "SELECT c.*, 
    COUNT(DISTINCT d.id) as total_deliveries,
    COUNT(DISTINCT CASE WHEN d.status = 'completed' THEN d.id END) as completed_deliveries,
    COUNT(DISTINCT CASE WHEN d.status = 'in_progress' THEN d.id END) as active_deliveries,
    AVG(CASE WHEN d.status = 'completed' THEN TIME_TO_SEC(TIMEDIFF(d.updated_at, d.created_at))/3600 END) as avg_delivery_time
    FROM couriers c
    LEFT JOIN deliveries d ON c.courier_id = d.courier_id
    WHERE c.courier_id = ?
    GROUP BY c.courier_id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$courier = $stmt->get_result()->fetch_assoc();

// Handle profile image upload
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['profile_image']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (in_array($ext, $allowed)) {
        $target_dir = "uploads/profile_images/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $new_filename = $courier_id . '.' . $ext;
        $target_file = $target_dir . $new_filename;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
            $update_image_sql = "UPDATE couriers SET profile_image = ? WHERE courier_id = ?";
            $update_image_stmt = $conn->prepare($update_image_sql);
            $update_image_stmt->bind_param("ss", $new_filename, $courier_id);
            $update_image_stmt->execute();
        }
    }
}

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    try {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $vehicle_number = $_POST['vehicle_number'];
        
        // Update profile
        $update_sql = "UPDATE couriers SET name = ?, email = ?, phone = ?, vehicle_number = ? WHERE courier_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssss", $name, $email, $phone, $vehicle_number, $courier_id);
        
        if ($update_stmt->execute()) {
            $_SESSION['courier_name'] = $name;
            $_SESSION['success_message'] = "Profile updated successfully!";
            header("Location: courier-profile.php");
            exit();
        } else {
            throw new Exception("Failed to update profile");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Courier Profile - BookStore</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
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

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }

        .profile-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }        .profile-image-container {
            width: 200px;
            height: 200px;
            margin: 0 auto 1.5rem;
            position: relative;
        }

        .profile-avatar-large {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 4rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border: 4px solid var(--primary-color);
        }
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        .profile-form {
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

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
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
        }        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

            .profile-container {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .profile-image-container {
                width: 150px;
                height: 150px;
            }

            .profile-avatar-large {
                font-size: 3rem;
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
            <li><a href="courier-profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1><i class="fas fa-user"></i> My Profile</h1>

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

        <div class="profile-container">
            <div class="profile-card">                <div class="profile-image-container">
                    <div class="profile-avatar-large">
                        <?php 
                        $name_parts = explode(' ', $courier['name']);
                        $initials = '';
                        foreach ($name_parts as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        echo substr($initials, 0, 2); // Show first 2 initials
                        ?>
                    </div>
                </div>

                <h2><?php echo htmlspecialchars($courier['name']); ?></h2>
                <p style="color: #666; margin-bottom: 1rem;">Courier ID: <?php echo htmlspecialchars($courier['courier_id']); ?></p><!-- Future enhancement: Profile customization form can be added here -->

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($courier['total_deliveries'] ?? 0); ?></div>
                        <div class="stat-label">Total Deliveries</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($courier['completed_deliveries'] ?? 0); ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($courier['active_deliveries'] ?? 0); ?></div>
                        <div class="stat-label">Active</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo isset($courier['avg_delivery_time']) ? number_format($courier['avg_delivery_time'], 1) : '0'; ?>h</div>
                        <div class="stat-label">Avg. Delivery Time</div>
                    </div>
                </div>
            </div>

            <div class="profile-form">
                <form action="" method="POST">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($courier['name'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($courier['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($courier['phone'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="vehicle_number">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number" value="<?php echo htmlspecialchars($courier['vehicle_number'] ?? ''); ?>" required>
                    </div>

                    <input type="hidden" name="update_profile" value="1">
                    <button type="submit" class="btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>    <script>
        // Profile page functionality
        console.log('Profile page loaded successfully');
    </script>
</body>
</html>
<?php $conn->close(); ?>
