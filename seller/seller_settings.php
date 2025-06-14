<?php

session_start();
require_once 'includes/seller_db.php';
require_once 'includes/db_helpers.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$success = $error = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $result = updateProfile($conn, $seller_id, $_POST, $_FILES);
    
    if ($result['success']) {
        $success = $result['message'];
        $_SESSION['seller_name'] = $_POST['name'] ?? '';
    } else {
        $error = $result['message'];
    }
}

// Load seller data
$stmt = $conn->prepare("SELECT * FROM seller_users WHERE seller_id = ?");
$stmt->bind_param("i", $seller_id);
$stmt->execute();
$seller = $stmt->get_result()->fetch_assoc();
$stmt->close();

function updateProfile($conn, $seller_id, $data, $files) {
    // Ensure required columns exist
    add_column_if_not_exists($conn, 'seller_users', 'profile_photo', 'VARCHAR(255) DEFAULT NULL');
    add_column_if_not_exists($conn, 'seller_users', 'phone', 'VARCHAR(20) DEFAULT NULL');
    add_column_if_not_exists($conn, 'seller_users', 'bio', 'TEXT DEFAULT NULL');
    add_column_if_not_exists($conn, 'seller_users', 'website', 'VARCHAR(255) DEFAULT NULL');
    add_column_if_not_exists($conn, 'seller_users', 'location', 'VARCHAR(255) DEFAULT NULL');
    
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $bio = trim($data['bio'] ?? '');
    $website = trim($data['website'] ?? '');
    $location = trim($data['location'] ?? '');
    
    if (empty($name) || empty($email)) {
        return ['success' => false, 'message' => 'Name and email are required'];
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    // Handle photo upload
    $photo_path = null;
    if (!empty($files['profile_photo']['name'])) {
        $upload_dir = __DIR__ . '/uploads/profiles/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $ext = strtolower(pathinfo($files['profile_photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return ['success' => false, 'message' => 'Only JPG, PNG, GIF, WebP files allowed'];
        }
        
        if ($files['profile_photo']['size'] > 5000000) {
            return ['success' => false, 'message' => 'Image too large (max 5MB)'];
        }
        
        // Verify it's actually an image
        if (!getimagesize($files['profile_photo']['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }
        
        $filename = 'profile_' . $seller_id . '_' . time() . '.' . $ext;
        $target = $upload_dir . $filename;
        
        if (move_uploaded_file($files['profile_photo']['tmp_name'], $target)) {
            $photo_path = 'uploads/profiles/' . $filename;
        } else {
            return ['success' => false, 'message' => 'Failed to upload image'];
        }
    }
    
    // Update database
    $sql = "UPDATE seller_users SET seller_name=?, seller_email=?, phone=?, bio=?, website=?, location=?";
    $params = [$name, $email, $phone, $bio, $website, $location];
    $types = "ssssss";
    
    if ($photo_path) {
        $sql .= ", profile_photo=?";
        $params[] = $photo_path;
        $types .= "s";
    }
    
    $sql .= " WHERE seller_id=?";
    $params[] = $seller_id;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $stmt->close();
        return ['success' => true, 'message' => 'Profile updated successfully!'];
    }
    
    $stmt->close();
    return ['success' => false, 'message' => 'Failed to update profile'];
}

function getProfilePhoto($seller_id, $conn) {
    $stmt = $conn->prepare("SELECT profile_photo FROM seller_users WHERE seller_id = ?");
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!empty($result['profile_photo']) && file_exists(__DIR__ . '/' . $result['profile_photo'])) {
        return $result['profile_photo'] . '?v=' . time();
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings | BookStore Seller Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        
        .navbar-modern {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
            font-size: 1.4rem;
            color: white !important;
        }
        
        .nav-link {
            color: white !important;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
        }
        
        .container-main {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .settings-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: none;
            backdrop-filter: blur(20px);
        }
        
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .settings-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }
        
        .avatar-main {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            font-weight: 800;
            overflow: hidden;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .avatar-main:hover {
            transform: scale(1.05);
        }
        
        .avatar-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .settings-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .settings-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            font-weight: 400;
        }
        
        .form-control {
            border: 2px solid #e8ecf4;
            border-radius: 12px;
            padding: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary-modern:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
        }
        
        .photo-upload-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
            border: 2px dashed rgba(102, 126, 234, 0.2);
            transition: all 0.3s ease;
        }
        
        .photo-upload-section:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }
        
        .photo-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid rgba(102, 126, 234, 0.3);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .photo-preview:hover {
            transform: scale(1.05);
            border-color: #667eea;
        }
        
        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-placeholder {
            color: #667eea;
            font-size: 2.5rem;
        }
        
        .upload-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
        
        .user-avatar-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 0.75rem;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .user-avatar-nav img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .dropdown-item {
            border-radius: 10px;
            margin: 0.2rem;
            transition: all 0.3s ease;
            padding: 0.75rem 1rem;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
        }
        
        .form-row {
            margin-bottom: 1.5rem;
        }
        
        .upload-info {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .settings-header {
                padding: 2rem 1rem;
            }
            
            .settings-title {
                font-size: 1.5rem;
            }
            
            .avatar-main {
                width: 100px;
                height: 100px;
            }
            
            .photo-preview {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-modern">
    <div class="container">
        <a class="navbar-brand" href="seller_dashboard.php">
            <i class="fas fa-book-open me-2"></i>BookStore Seller Hub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="seller_dashboard.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_manage_books.php">
                        <i class="fas fa-books me-1"></i>My Books
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_add_book.php">
                        <i class="fas fa-plus me-1"></i>Add Book
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="seller_settings.php">
                        <i class="fas fa-cog me-1"></i>Settings
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <div class="user-avatar-nav">
                            <?php 
                            $photo = getProfilePhoto($seller_id, $conn);
                            if ($photo): ?>
                                <img src="<?= htmlspecialchars($photo) ?>" alt="Profile">
                            <?php else: ?>
                                <?= strtoupper(substr($_SESSION['seller_name'] ?? 'S', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <?= htmlspecialchars($_SESSION['seller_name'] ?? 'Seller') ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="seller_settings.php">
                            <i class="fas fa-user-cog me-2"></i>Settings
                        </a></li>
                        <li><a class="dropdown-item" href="seller_activity_log.php">
                            <i class="fas fa-history me-2"></i>Activity Log
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="seller_logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-main" style="margin-top: 100px;">
    <?php if ($success): ?>
        <div class="alert alert-success alert-modern alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-modern alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card settings-card">
        <div class="settings-header">
            <div class="avatar-main">
                <?php if (!empty($seller['profile_photo']) && file_exists(__DIR__ . '/' . $seller['profile_photo'])): ?>
                    <img src="<?= htmlspecialchars($seller['profile_photo']) ?>?v=<?= time() ?>" alt="Profile">
                <?php else: ?>
                    <?= strtoupper(substr($seller['seller_name'] ?? 'S', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <h2 class="settings-title"><?= htmlspecialchars($seller['seller_name'] ?? 'User') ?></h2>
            <p class="settings-subtitle"><?= htmlspecialchars($seller['seller_email'] ?? '') ?></p>
        </div>
        
        <div class="card-body p-4">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <!-- Profile Photo Upload Section -->
                <div class="photo-upload-section">
                    <h5 class="mb-3"><i class="bi bi-camera me-2"></i>Profile Photo</h5>
                    <div class="photo-preview" onclick="document.getElementById('photo').click()">
                        <?php if (!empty($seller['profile_photo']) && file_exists(__DIR__ . '/' . $seller['profile_photo'])): ?>
                            <img id="preview" src="<?= htmlspecialchars($seller['profile_photo']) ?>?v=<?= time() ?>" alt="Profile">
                        <?php else: ?>
                            <div class="upload-placeholder">
                                <i class="bi bi-camera"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="photo" name="profile_photo" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    <button type="button" class="upload-btn" onclick="document.getElementById('photo').click()">
                        <i class="bi bi-camera-fill me-2"></i>Change Photo
                    </button>
                    <div class="upload-info">JPG, PNG, GIF, WebP • Max 5MB</div>
                </div>
                
                <!-- Profile Information -->
                <div class="row form-row">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($seller['seller_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($seller['seller_email'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="row form-row">
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" value="<?= htmlspecialchars($seller['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" value="<?= htmlspecialchars($seller['location'] ?? '') ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <label class="form-label">Website URL</label>
                    <input type="url" class="form-control" name="website" value="<?= htmlspecialchars($seller['website'] ?? '') ?>">
                </div>
                
                <div class="form-row">
                    <label class="form-label">Bio</label>
                    <textarea class="form-control" name="bio" rows="4" placeholder="Tell us about yourself and your book business..."><?= htmlspecialchars($seller['bio'] ?? '') ?></textarea>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary-modern">
                        <i class="bi bi-check-lg me-2"></i>Update Profile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.photo-preview');
            preview.innerHTML = `<img id="preview" src="${e.target.result}" alt="Profile Preview">`;
            
            // Update main avatar
            const mainAvatar = document.querySelector('.avatar-main');
            mainAvatar.innerHTML = `<img src="${e.target.result}" alt="Profile">`;
            
            // Update nav avatar
            const navAvatar = document.querySelector('.user-avatar-nav');
            navAvatar.innerHTML = `<img src="${e.target.result}" alt="Profile">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Drag and drop functionality
const uploadSection = document.querySelector('.photo-upload-section');

['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadSection.addEventListener(eventName, preventDefaults, false);
});

function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
}

['dragenter', 'dragover'].forEach(eventName => {
    uploadSection.addEventListener(eventName, highlight, false);
});

['dragleave', 'drop'].forEach(eventName => {
    uploadSection.addEventListener(eventName, unhighlight, false);
});

function highlight() {
    uploadSection.style.borderColor = '#667eea';
    uploadSection.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%)';
}

function unhighlight() {
    uploadSection.style.borderColor = 'rgba(102, 126, 234, 0.2)';
    uploadSection.style.background = 'linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%)';
}

uploadSection.addEventListener('drop', handleDrop, false);

function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    
    if (files.length > 0) {
        document.getElementById('photo').files = files;
        previewImage(document.getElementById('photo'));
    }
}
</script>

</body>
</html>