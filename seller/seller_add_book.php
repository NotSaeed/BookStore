<?php


session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$sellerName = $_SESSION['seller_name'];
$alert = '';
$success = '';

// Function to get profile photo
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $price = floatval($_POST['price']);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $desc = trim($_POST['description']);
    
    // Handle ISBN - completely optional, allow empty or null
    $isbn = trim($_POST['isbn'] ?? '');
    if (empty($isbn)) {
        $isbn = null; // Set to null for database
    }    $category = trim($_POST['category'] ?? '');
    $condition = trim($_POST['condition'] ?? 'New');
    
    // Ensure condition matches database ENUM values exactly
    $valid_conditions = ['New', 'Like New', 'Very Good', 'Good', 'Acceptable'];
    if (!in_array($condition, $valid_conditions)) {
        $condition = 'New'; // Default to 'New' if invalid value
    }
    
    $language = trim($_POST['language'] ?? 'English');
    $publisher = trim($_POST['publisher'] ?? '');
    
    // Handle optional numeric fields
    $publication_year = !empty($_POST['publication_year']) ? intval($_POST['publication_year']) : null;
    $pages = !empty($_POST['pages']) ? intval($_POST['pages']) : null;
    $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
    $dimensions = trim($_POST['dimensions'] ?? '') ?: null;
    
    $stock_quantity = intval($_POST['book_stock'] ?? 1);
    $tags = trim($_POST['tags'] ?? '') ?: null;
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    $seller_id = $_SESSION['seller_id'];

    // Validation
    if (empty($title)) {
        $alert = "Book title is required.";
    } elseif (empty($author)) {
        $alert = "Author name is required.";
    } elseif ($price <= 0) {
        $alert = "Please enter a valid price.";    } elseif ($cost_price < 0) {
        $alert = "Cost price cannot be negative.";
    } elseif ($stock_quantity < 0) {
        $alert = "Stock quantity cannot be negative.";
    } else {
        // Handle file upload
        $cover_image = null;
        $upload_dir = 'uploads/covers/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $temp_name = $_FILES['cover']['tmp_name'];
            $file_name = $_FILES['cover']['name'];
            $file_size = $_FILES['cover']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Valid image extensions
            $valid_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_ext, $valid_extensions)) {
                $alert = "Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.";
            } elseif ($file_size > $max_size) {
                $alert = "File size too large. Maximum size is 5MB.";
            } else {
                // Generate unique filename
                $new_file_name = uniqid('book_') . '.' . $file_ext;
                $destination = $upload_dir . $new_file_name;
                
                if (move_uploaded_file($temp_name, $destination)) {
                    $cover_image = $destination;
                } else {
                    $alert = "Failed to upload image.";
                }
            }
        }        if (empty($alert)) {
            // Check if book already exists (by ISBN if provided, or title + author)
            $check_sql = "SELECT book_id FROM seller_books WHERE seller_id = ? AND ";
            $params = [$seller_id];
            $types = "i";
            
            if (!empty($isbn)) {
                // If ISBN is provided, check for duplicate ISBN
                $check_sql .= "isbn = ?";
                $params[] = $isbn;
                $types .= "s";
            } else {
                // If no ISBN, check for duplicate title + author combination
                $check_sql .= "(title = ? AND author = ?)";
                $params[] = $title;
                $params[] = $author;
                $types .= "ss";
            }
            
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param($types, ...$params);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            $check_stmt->close();

            if ($existing) {
                if (!empty($isbn)) {
                    $alert = "A book with this ISBN already exists in your inventory.";
                } else {
                    $alert = "A book with the same title and author already exists in your inventory.";
                }
            } else {                // Prepare and bind - Updated to match actual database structure
                $stmt = $conn->prepare("INSERT INTO seller_books (
                    title, author, description, price, cost_price, 
                    cover_image, isbn, category, condition, publisher, 
                    publication_year, pages, weight, dimensions, stock_quantity, 
                    tags, language, is_public, is_featured, seller_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
                if (!$stmt) {
                    $alert = "Failed to prepare statement: " . $conn->error;
                } else {
                    $stmt->bind_param("sssddssssiiidsissiii", 
                        $title, $author, $desc, $price, $cost_price, $cover_image, 
                        $isbn, $category, $condition, $publisher, 
                        $publication_year, $pages, $weight, $dimensions, $stock_quantity, 
                        $tags, $language, $is_public, $is_featured, $seller_id
                    );
            
                    if ($stmt->execute()) {
                        $book_id = $stmt->insert_id;
                        
                        // Log the action
                        $action = "Added new book: " . $title . " (ID: " . $book_id . ")";
                        $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action) VALUES (?, ?)");
                        if ($log) {
                            $log->bind_param("is", $seller_id, $action);
                            $log->execute();
                            $log->close();
                        }
                        
                        $success = "Book added successfully! Your book is now in your inventory.";
                        
                        // JavaScript redirect after showing success
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'seller_manage_books.php';
                            }, 2000);
                        </script>";
                    } else {
                        $alert = "Failed to add book: " . $stmt->error;
                    }
            
                    $stmt->close();
                }
            }
        }
    }
}

// Get popular categories for dropdown
$cat_stmt = $conn->prepare("SELECT category, COUNT(*) as count FROM seller_books WHERE category != '' GROUP BY category ORDER BY count DESC LIMIT 10");
$cat_stmt->execute();
$popular_categories = $cat_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cat_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Book | BookStore Seller Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            background-attachment: fixed;
        }
        
        .navbar {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 1000;
        }
        
        .navbar-brand {
            font-weight: 800;
            letter-spacing: 0.5px;
            font-size: 1.4rem;
        }
        
        .hero-section {
            padding: 3rem 0 2rem;
            text-align: center;
            color: white;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 2rem;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 0;
            margin-bottom: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .form-header h4 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            opacity: 0.9;
            margin: 0;
        }
        
        .form-body {
            padding: 3rem;
        }
        
        .form-section {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: #667eea;
            font-size: 1.5rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating .form-control,
        .form-floating .form-select {
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem 1rem 0.5rem 1rem;
            height: calc(3.5rem + 2px);
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-floating .form-control:focus,
        .form-floating .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .form-floating label {
            padding: 1rem 1rem 0.5rem 1rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label,
        .form-floating .form-select:focus ~ label,
        .form-floating .form-select:not([value=""]) ~ label {
            opacity: 0.8;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: #667eea;
        }
        
        .image-upload-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            border: 2px dashed rgba(102, 126, 234, 0.3);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }
        
        .image-upload-section:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .upload-text {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .upload-subtext {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        #cover {
            display: none;
        }
        
        .image-preview {
            margin-top: 1rem;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            max-width: 200px;
            margin: 1rem auto 0;
        }
        
        .image-preview img {
            width: 100%;
            height: auto;
            border-radius: 15px;
        }
        
        .advanced-options {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .option-group {
            margin-bottom: 1.5rem;
        }
        
        .custom-switch {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 15px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .custom-switch:hover {
            border-color: rgba(102, 126, 234, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .custom-switch input {
            width: 60px;
            height: 30px;
            margin-right: 1rem;
        }
        
        .switch-content {
            flex: 1;
        }
        
        .switch-title {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.25rem;
        }
        
        .switch-description {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 15px;
            padding: 1rem 2.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            color: white;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-gradient:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-gradient:active {
            transform: translateY(-1px);
        }
        
        .btn-outline-gradient {
            background: transparent;
            border: 2px solid transparent;
            background-clip: padding-box;
            border-radius: 15px;
            padding: 1rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            position: relative;
            color: #667eea;
        }
        
        .btn-outline-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 15px;
            padding: 2px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            -webkit-mask-composite: xor;
        }
        
        .btn-outline-gradient:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(102, 126, 234, 0.1);
        }
          .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            margin-right: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Dropdown - FIXED STYLES */
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            z-index: 1050;
            min-width: 200px;
        }
        
        .dropdown-item {
            border-radius: 10px;
            margin: 0.2rem;
            transition: all 0.3s ease;
            padding: 0.75rem 1rem;
            cursor: pointer;
            user-select: none;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
        }

        /* Logout button specific styling - FIXED */
        .dropdown-item.logout-btn {
            border-top: 1px solid #e9ecef;
            margin-top: 0.5rem;
            padding-top: 0.75rem;
            cursor: pointer !important;
            pointer-events: auto !important;
        }

        .dropdown-item.logout-btn:hover {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
            color: white !important;
        }

        .dropdown-item.logout-btn:hover .text-danger {
            color: white !important;
        }

        .dropdown-item.logout-btn:hover i {
            color: white !important;
        }

        .dropdown-item.logout-btn .text-danger {
            color: #dc3545 !important;
        }

        /* Fix for dropdown toggle */
        .nav-link.dropdown-toggle {
            cursor: pointer;
        }

        /* Ensure dropdown items are clickable */
        .dropdown-menu .dropdown-item {
            display: block;
            width: 100%;
            padding: 0.75rem 1rem;
            clear: both;
            font-weight: 400;
            color: #212529;
            text-align: inherit;
            text-decoration: none;
            white-space: nowrap;
            background-color: transparent;
            border: 0;
            cursor: pointer;
        }
        
        .footer {
            padding: 3rem 0;
            margin-top: 4rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }
        
        .tag-input {
            position: relative;
        }
        
        .tag-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .tag-suggestion {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .tag-suggestion:hover {
            background-color: #f8f9fa;
        }
        
        .profit-calculator {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .profit-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .profit-item.total {
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            font-weight: 700;
        }
        
        /* Animations */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Loading state */
        .btn-loading {
            position: relative;
            color: transparent !important;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top-color: white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .form-body {
                padding: 2rem 1.5rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-gradient,
            .btn-outline-gradient {
                width: 100%;
                margin-bottom: 1rem;
            }
        }
        
        /* Accessibility improvements */
        .form-control:focus,
        .form-select:focus,
        .btn:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        
        /* Custom scrollbar for tag suggestions */
        .tag-suggestions::-webkit-scrollbar {
            width: 6px;
        }
        
        .tag-suggestions::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .tag-suggestions::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container">
        <a class="navbar-brand" href="seller_dashboard.php">
            <i class="bi bi-shop me-2"></i>BookStore Seller Hub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="seller_dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="seller_manage_books.php">My Books</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active fw-semibold" href="seller_add_book.php">Add Book</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link fw-semibold" href="seller_settings.php">Settings</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">                    <a class="nav-link dropdown-toggle d-flex align-items-center fw-semibold" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar">
                            <?php 
                            $photo = getProfilePhoto($_SESSION['seller_id'], $conn);
                            if ($photo): ?>
                                <img src="<?= htmlspecialchars($photo) ?>" alt="Profile">
                            <?php else: ?>
                                <?= strtoupper(substr($sellerName, 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <?= htmlspecialchars($sellerName) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="seller_settings.php">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="seller_activity_log.php">
                                <i class="bi bi-clock-history me-2"></i>Activity Log
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item logout-btn" id="logoutButton">
                                <i class="bi bi-box-arrow-right me-2 text-danger"></i>
                                <span class="text-danger fw-semibold">Logout</span>
                            </button>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <h1 class="hero-title animate-on-scroll">
            <i class="bi bi-plus-circle me-3"></i>Add New Book
        </h1>
        <p class="hero-subtitle animate-on-scroll">Expand your inventory with detailed book information</p>
    </div>
</div>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="form-container animate-on-scroll">
                <div class="form-header">
                    <h4><i class="bi bi-book me-2"></i>Book Information Form</h4>
                    <p>Fill in the details below to add a new book to your inventory</p>
                </div>
                
                <div class="form-body">
                    <?php if ($alert): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Error:</strong> <?= htmlspecialchars($alert) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>Success:</strong> <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate id="bookForm">
                        
                        <!-- Basic Information Section -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="bi bi-info-circle"></i>Basic Information
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="title" name="title" 
                                               placeholder="Book Title" required autocomplete="off">
                                        <label for="title"><i class="bi bi-book me-2"></i>Book Title *</label>
                                        <div class="invalid-feedback">Please enter the book title</div>
                                    </div>
                                </div>
                                  <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="isbn" name="isbn" 
                                               placeholder="ISBN (Optional)" pattern="[0-9-]{10,17}">
                                        <label for="isbn"><i class="bi bi-upc me-2"></i>ISBN (Optional)</label>
                                        <div class="form-text">ISBN-10 or ISBN-13 (Leave empty if unknown)</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="author" name="author" 
                                               placeholder="Author Name" required>
                                        <label for="author"><i class="bi bi-person me-2"></i>Author *</label>
                                        <div class="invalid-feedback">Please enter the author name</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="publisher" name="publisher" 
                                               placeholder="Publisher">
                                        <label for="publisher"><i class="bi bi-building me-2"></i>Publisher</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating">
                                <textarea class="form-control" id="description" name="description" 
                                          placeholder="Book Description" style="min-height: 120px;"></textarea>
                                <label for="description"><i class="bi bi-card-text me-2"></i>Description</label>
                                <div class="form-text">Provide a detailed description to attract buyers</div>
                            </div>
                        </div>

                        <!-- Book Details Section -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="bi bi-list-ul"></i>Book Details
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="category" name="category">
                                            <option value="">Select Category</option>
                                            <option value="Fiction">Fiction</option>
                                            <option value="Non-Fiction">Non-Fiction</option>
                                            <option value="Science">Science</option>
                                            <option value="Technology">Technology</option>
                                            <option value="History">History</option>
                                            <option value="Biography">Biography</option>
                                            <option value="Business">Business</option>
                                            <option value="Self-Help">Self-Help</option>
                                            <option value="Education">Education</option>
                                            <option value="Children">Children</option>
                                            <option value="Romance">Romance</option>
                                            <option value="Mystery">Mystery</option>
                                            <option value="Fantasy">Fantasy</option>
                                            <option value="Horror">Horror</option>
                                            <option value="Other">Other</option>
                                            <?php foreach($popular_categories as $cat): ?>
                                                <option value="<?= htmlspecialchars($cat['category']) ?>">
                                                    <?= htmlspecialchars($cat['category']) ?> (<?= $cat['count'] ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="category"><i class="bi bi-tags me-2"></i>Category</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="condition" name="condition">
                                            <option value="new">New</option>
                                            <option value="like-new">Like New</option>
                                            <option value="very-good">Very Good</option>
                                            <option value="good">Good</option>
                                            <option value="acceptable">Acceptable</option>
                                        </select>
                                        <label for="condition"><i class="bi bi-shield-check me-2"></i>Condition</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <select class="form-select" id="language" name="language">
                                            <option value="English">English</option>
                                            <option value="Spanish">Spanish</option>
                                            <option value="French">French</option>
                                            <option value="German">German</option>
                                            <option value="Italian">Italian</option>
                                            <option value="Chinese">Chinese</option>
                                            <option value="Japanese">Japanese</option>
                                            <option value="Other">Other</option>
                                        </select>
                                        <label for="language"><i class="bi bi-translate me-2"></i>Language</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="publication_year" name="publication_year" 
                                               placeholder="Publication Year" min="1800" max="<?= date('Y') + 1 ?>">
                                        <label for="publication_year"><i class="bi bi-calendar me-2"></i>Publication Year</label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="pages" name="pages" 
                                               placeholder="Number of Pages" min="1">
                                        <label for="pages"><i class="bi bi-file-earmark-text me-2"></i>Number of Pages</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Physical Attributes Row -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="weight" name="weight" 
                                               placeholder="Weight in grams" min="0" step="0.01">
                                        <label for="weight"><i class="bi bi-box me-2"></i>Weight (grams)</label>
                                        <div class="form-text">Optional - Enter book weight in grams</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="dimensions" name="dimensions" 
                                               placeholder="e.g. 20x15x2 cm">
                                        <label for="dimensions"><i class="bi bi-rulers me-2"></i>Dimensions</label>
                                        <div class="form-text">Optional - Length x Width x Height</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="tag-input">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           placeholder="Tags (comma separated)">
                                    <label for="tags"><i class="bi bi-tags me-2"></i>Tags (comma separated)</label>
                                    <div class="form-text">Add keywords to help buyers find your book</div>
                                </div>
                                <div class="tag-suggestions" id="tagSuggestions"></div>
                            </div>
                        </div>

                        <!-- Pricing & Inventory Section -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="bi bi-currency-dollar"></i>Pricing & Inventory
                            </h5>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="cost_price" name="cost_price" 
                                               placeholder="Cost Price" min="0" step="0.01">
                                        <label for="cost_price"><i class="bi bi-receipt me-2"></i>Cost Price ($)</label>
                                        <div class="form-text">What you paid for this book</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="price" name="price" 
                                               placeholder="Selling Price" min="0.01" step="0.01" required>
                                        <label for="price"><i class="bi bi-tag me-2"></i>Selling Price ($) *</label>
                                        <div class="invalid-feedback">Please enter a valid selling price</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" id="book_stock" name="book_stock" 
                                               placeholder="Stock Quantity" min="0" value="1">
                                        <label for="book_stock"><i class="bi bi-box me-2"></i>Stock Quantity</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="profitCalculator" class="profit-calculator" style="display: none;">
                                <h6><i class="bi bi-calculator me-2"></i>Profit Calculator</h6>
                                <div class="profit-item">
                                    <span>Selling Price:</span>
                                    <span id="calcSellingPrice">$0.00</span>
                                </div>
                                <div class="profit-item">
                                    <span>Cost Price:</span>
                                    <span id="calcCostPrice">$0.00</span>
                                </div>
                                <div class="profit-item">
                                    <span>Stock Quantity:</span>
                                    <span id="calcStock">0</span>
                                </div>
                                <div class="profit-item total">
                                    <span>Potential Profit:</span>
                                    <span id="calcProfit">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Cover Image Section -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="bi bi-image"></i>Book Cover
                            </h5>
                            
                            <div class="image-upload-section" onclick="document.getElementById('cover').click()">
                                <i class="bi bi-cloud-upload upload-icon"></i>
                                <div class="upload-text">Click to upload book cover</div>
                                <div class="upload-subtext">
                                    Supports JPG, PNG, GIF, WEBP • Max size: 5MB • Recommended: 600x800px
                                </div>
                                <input type="file" id="cover" name="cover" accept="image/*" onchange="previewImage(this)">
                            </div>
                            
                            <div id="imagePreview" class="image-preview" style="display: none;">
                                <img id="previewImg" src="" alt="Book Cover Preview">
                            </div>
                        </div>

                        <!-- Advanced Options Section -->
                        <div class="form-section">
                            <h5 class="section-title">
                                <i class="bi bi-gear"></i>Publishing Options
                            </h5>
                            
                            <div class="advanced-options">
                                <div class="custom-switch">
                                    <input class="form-check-input" type="checkbox" id="is_public" name="is_public" checked>
                                    <div class="switch-content">
                                        <div class="switch-title">Make Public</div>
                                        <div class="switch-description">Allow customers to see and purchase this book</div>
                                    </div>
                                </div>
                                
                                <div class="custom-switch">
                                    <input class="form-check-input" type="checkbox" id="is_featured" name="is_featured">
                                    <div class="switch-content">
                                        <div class="switch-title">Feature This Book</div>
                                        <div class="switch-description">Highlight this book in search results and recommendations</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="form-actions">
                            <a href="seller_manage_books.php" class="btn btn-outline-gradient">
                                <i class="bi bi-arrow-left me-2"></i>Cancel
                            </a>
                            <button type="button" class="btn btn-outline-gradient" onclick="saveDraft()">
                                <i class="bi bi-save me-2"></i>Save Draft
                            </button>
                            <button type="submit" class="btn btn-gradient" id="submitBtn">
                                <i class="bi bi-plus-circle me-2"></i>Add Book
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="container">
        <p class="mb-0">&copy; <?= date('Y') ?> BookStore Seller Hub. All rights reserved.</p>
        <small class="opacity-75">Empowering sellers to grow their book business</small>
    </div>
</div>

<!-- ISBN Lookup Modal -->
<div class="modal fade" id="isbnModal" tabindex="-1" aria-labelledby="isbnModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px 20px 0 0;">
                <h5 class="modal-title" id="isbnModalLabel"><i class="bi bi-search me-2"></i>ISBN Lookup</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Would you like to automatically fill book details using the ISBN?</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-gradient" onclick="lookupISBN()">
                        <i class="bi bi-search me-2"></i>Lookup Book Details
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, observerOptions);

document.querySelectorAll('.animate-on-scroll').forEach(el => {
    observer.observe(el);
});

// FIXED LOGOUT FUNCTIONALITY
function performLogout() {
    // Show confirmation dialog
    const confirmed = confirm('🔐 Are you sure you want to logout?\n\nYour session will be securely terminated.');
    
    if (!confirmed) {
        return;
    }

    // Find the logout button and show loading state
    const logoutBtn = document.getElementById('logoutButton');
    const originalContent = logoutBtn.innerHTML;
    logoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging out...';
    logoutBtn.disabled = true;
    
    // Create and show loading overlay
    const loadingOverlay = document.createElement('div');
    loadingOverlay.innerHTML = `
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        ">
            <div style="
                background: white;
                padding: 2.5rem;
                border-radius: 1.5rem;
                text-align: center;
                box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
                max-width: 350px;
                margin: 1rem;
            ">
                <div style="
                    width: 60px;
                    height: 60px;
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 1.5rem;
                "></div>
                <div style="
                    font-size: 1.1rem;
                    font-weight: 600;
                    color: #2d3748;
                    margin-bottom: 0.5rem;
                ">Logging out securely...</div>
                <div style="
                    font-size: 0.9rem;
                    color: #6c757d;
                ">Please wait while we terminate your session</div>
            </div>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    
    document.body.appendChild(loadingOverlay);
    
    // Prevent scrolling while loading
    document.body.style.overflow = 'hidden';
    
    // Redirect to logout after delay
    setTimeout(() => {
        window.location.href = 'seller_logout.php';
    }, 2000);
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initialize logout functionality with multiple approaches
    const logoutButton = document.getElementById('logoutButton');
    
    if (logoutButton) {
        // Method 1: Direct click event
        logoutButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            performLogout();
        });

        // Method 2: Touch events for mobile 
        logoutButton.addEventListener('touchstart', function(e) {
            e.preventDefault();
            performLogout();
        });

        // Method 3: Keyboard support
        logoutButton.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                performLogout();
            }
        });
    }

    // Alternative: Also listen for any element with logout class
    document.addEventListener('click', function(e) {
        if (e.target.closest('.logout-btn') || e.target.closest('#logoutButton')) {
            e.preventDefault();
            e.stopPropagation();
            performLogout();
        }
    });

    // Load draft functionality
    const draft = localStorage.getItem('bookDraft');
    if (draft) {
        const draftData = JSON.parse(draft);
        
        Object.keys(draftData).forEach(key => {
            const element = document.querySelector(`[name="${key}"]`);
            if (element) {
                if (element.type === 'checkbox') {
                    element.checked = draftData[key] === 'on';
                } else {
                    element.value = draftData[key];
                }
            }
        });
        
        showToast('Draft loaded from previous session', 'info');
    }
});

// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Image preview
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Profit calculator
function updateProfitCalculator() {
    const costPrice = parseFloat(document.getElementById('cost_price').value) || 0;
    const sellingPrice = parseFloat(document.getElementById('price').value) || 0;
    const stock = parseInt(document.getElementById('book_stock').value) || 0;
    
    if (costPrice > 0 || sellingPrice > 0) {
        document.getElementById('profitCalculator').style.display = 'block';
        
        const profit = (sellingPrice - costPrice) * stock;
        
        document.getElementById('calcCostPrice').textContent = '$' + costPrice.toFixed(2);
        document.getElementById('calcSellingPrice').textContent = '$' + sellingPrice.toFixed(2);
        document.getElementById('calcStock').textContent = stock;
        document.getElementById('calcProfit').textContent = '$' + profit.toFixed(2);
        
        // Color coding for profit
        const profitElement = document.getElementById('calcProfit');
        if (profit > 0) {
            profitElement.style.color = '#28a745';
        } else if (profit < 0) {
            profitElement.style.color = '#dc3545';
        } else {
            profitElement.style.color = '#ffc107';
        }
    } else {
        document.getElementById('profitCalculator').style.display = 'none';
    }
}

// Add event listeners for profit calculator
document.getElementById('cost_price').addEventListener('input', updateProfitCalculator);
document.getElementById('price').addEventListener('input', updateProfitCalculator);
document.getElementById('book_stock').addEventListener('input', updateProfitCalculator);

// ISBN lookup
document.getElementById('isbn').addEventListener('blur', function() {
    const isbn = this.value.trim();
    if (isbn.length >= 10 && !document.getElementById('title').value) {
        const modal = new bootstrap.Modal(document.getElementById('isbnModal'));
        modal.show();
    }
});

function lookupISBN() {
    const isbn = document.getElementById('isbn').value.trim();
    if (!isbn) return;
    
    // This would normally call a real ISBN API
    // For demo purposes, we'll simulate a lookup
    setTimeout(() => {
        // Simulated data - replace with actual API call
        const mockData = {
            title: "Sample Book Title",
            author: "Sample Author",
            publisher: "Sample Publisher",
            year: 2023,
            pages: 300,
            description: "This is a sample book description that would be retrieved from an ISBN database."
        };
        
        // Fill form fields
        document.getElementById('title').value = mockData.title;
        document.getElementById('author').value = mockData.author;
        document.getElementById('publisher').value = mockData.publisher;
        document.getElementById('publication_year').value = mockData.year;
        document.getElementById('pages').value = mockData.pages;
        document.getElementById('description').value = mockData.description;
        
        // Close modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('isbnModal'));
        modal.hide();
        
        // Show success message
        showToast('Book details filled automatically!', 'success');
    }, 1000);
}

// Tag suggestions
const tagSuggestions = [
    'bestseller', 'award-winning', 'classic', 'contemporary', 'educational', 
    'reference', 'textbook', 'rare', 'first-edition', 'signed', 'hardcover', 
    'paperback', 'illustrated', 'photography', 'art', 'cooking', 'travel', 
    'biography', 'memoir', 'thriller', 'romance', 'sci-fi', 'fantasy'
];

document.getElementById('tags').addEventListener('input', function() {
    const input = this.value.toLowerCase();
    const suggestions = document.getElementById('tagSuggestions');
    
    if (input.length > 1) {
        const matches = tagSuggestions.filter(tag => 
            tag.toLowerCase().includes(input) && !this.value.includes(tag)
        );
        
        if (matches.length > 0) {
            suggestions.innerHTML = matches.map(tag => 
                `<div class="tag-suggestion" onclick="addTag('${tag}')">${tag}</div>`
            ).join('');
            suggestions.style.display = 'block';
        } else {
            suggestions.style.display = 'none';
        }
    } else {
        suggestions.style.display = 'none';
    }
});

function addTag(tag) {
    const tagsInput = document.getElementById('tags');
    const currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);
    
    if (!currentTags.includes(tag)) {
        currentTags.push(tag);
        tagsInput.value = currentTags.join(', ');
    }
    
    document.getElementById('tagSuggestions').style.display = 'none';
}

// Save draft functionality
function saveDraft() {
    const formData = new FormData(document.getElementById('bookForm'));
    
    // Save to localStorage
    const draftData = {};
    for (let [key, value] of formData.entries()) {
        if (key !== 'cover') { // Don't save file data
            draftData[key] = value;
        }
    }
    
    localStorage.setItem('bookDraft', JSON.stringify(draftData));
    showToast('Draft saved successfully!', 'success');
}

// Toast notification helper
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-check-circle me-2"></i>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    
    document.body.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
    
    toast.addEventListener('hidden.bs.toast', () => {
        document.body.removeChild(toast);
    });
}

// Clear draft after successful submission
if (document.querySelector('.alert-success')) {
    localStorage.removeItem('bookDraft');
}

// Auto-resize textarea
document.getElementById('description').addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = this.scrollHeight + 'px';
});

// Enhanced form validation with custom messages
document.getElementById('price').addEventListener('input', function() {
    const value = parseFloat(this.value);
    if (value <= 0) {
        this.setCustomValidity('Price must be greater than 0');
    } else {
        this.setCustomValidity('');
    }
});

// Improved ISBN validation - now completely optional
document.getElementById('isbn').addEventListener('input', function() {
    const isbn = this.value.replace(/[-\s]/g, '');
    // ISBN is optional, so only validate if something is entered
    if (isbn.length > 0) {
        // Allow 10 or 13 digits, or clear the field if invalid
        if (isbn.length !== 10 && isbn.length !== 13) {
            this.setCustomValidity('ISBN must be 10 or 13 digits (optional field)');
        } else if (!/^\d+$/.test(isbn)) {
            this.setCustomValidity('ISBN must contain only numbers and hyphens');
        } else {
            this.setCustomValidity('');
        }
    } else {
        // Clear any validation errors if field is empty (since it's optional)
        this.setCustomValidity('');
    }
});

// Weight validation
document.getElementById('weight').addEventListener('input', function() {
    const value = parseFloat(this.value);
    if (this.value !== '' && (isNaN(value) || value < 0)) {
        this.setCustomValidity('Weight must be a positive number or left empty');
    } else {
        this.setCustomValidity('');
    }
});

// Hide suggestions when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.tag-input')) {
        document.getElementById('tagSuggestions').style.display = 'none';
    }
});
</script>

</body>
</html>