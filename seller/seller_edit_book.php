<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'];
$book_id = $_GET['id'] ?? null;
$success = $error = '';

if (!$book_id || !is_numeric($book_id)) {
    header("Location: seller_manage_books.php");
    exit();
}

try {
    // Load book with basic data first
    $stmt = $conn->prepare("SELECT * FROM seller_books WHERE book_id = ? AND seller_id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $book_id, $seller_id);
    $stmt->execute();
    $book = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$book) {
        header("Location: seller_manage_books.php?error=book_not_found");
        exit();
    }

    // Initialize stats with default values
    $book['total_sold'] = 0;
    $book['total_revenue'] = 0;
    $book['order_count'] = 0;
    
    // Try to get sales data if seller_orders table exists
    $orders_check = $conn->query("SHOW TABLES LIKE 'seller_orders'");
    if ($orders_check && $orders_check->num_rows > 0) {
        // Check what columns exist in seller_orders table
        $columns_result = $conn->query("SHOW COLUMNS FROM seller_orders");
        $columns = [];
        while ($col = $columns_result->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        
        // Build query based on available columns
        $sales_sql = "SELECT ";
        $sales_fields = [];
        
        if (in_array('quantity', $columns)) {
            $sales_fields[] = "COALESCE(SUM(quantity), 0) as total_sold";
        } else {
            $sales_fields[] = "COALESCE(COUNT(*), 0) as total_sold";
        }
        
        if (in_array('total_amount', $columns)) {
            $sales_fields[] = "COALESCE(SUM(total_amount), 0) as total_revenue";
        } elseif (in_array('price', $columns) && in_array('quantity', $columns)) {
            $sales_fields[] = "COALESCE(SUM(price * quantity), 0) as total_revenue";
        } elseif (in_array('price', $columns)) {
            $sales_fields[] = "COALESCE(SUM(price), 0) as total_revenue";
        } else {
            $sales_fields[] = "0 as total_revenue";
        }
        
        $sales_fields[] = "COUNT(DISTINCT " . (in_array('order_id', $columns) ? 'order_id' : 'id') . ") as order_count";
        
        $sales_sql .= implode(', ', $sales_fields);
        $sales_sql .= " FROM seller_orders WHERE book_id = ?";
        
        $sales_stmt = $conn->prepare($sales_sql);
        if ($sales_stmt) {
            $sales_stmt->bind_param("i", $book_id);
            $sales_stmt->execute();
            $sales_result = $sales_stmt->get_result()->fetch_assoc();
            if ($sales_result) {
                $book['total_sold'] = $sales_result['total_sold'] ?? 0;
                $book['total_revenue'] = $sales_result['total_revenue'] ?? 0;
                $book['order_count'] = $sales_result['order_count'] ?? 0;
            }
            $sales_stmt->close();
        }
    }

} catch (Exception $e) {
    error_log("Edit book error: " . $e->getMessage());
    $error = "Error loading book data. Please try again.";
}

// Handle update
if (isset($_POST['update_book'])) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $price = floatval($_POST['price']);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity']);
    $description = trim($_POST['description']);
    $genre = trim($_POST['genre'] ?? '');
    $isbn = trim($_POST['isbn'] ?? '');
    $condition_type = $_POST['condition_type'] ?? 'new';
    $publisher = trim($_POST['publisher'] ?? '');
    $publication_year = intval($_POST['publication_year'] ?? 0);
    $language = $_POST['language'] ?? 'English';
    $pages = intval($_POST['pages'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $dimensions = trim($_POST['dimensions'] ?? '');
    $is_public = isset($_POST['is_public']) ? 1 : 0;

    // Validation
    if (empty($title) || empty($author) || $price <= 0 || $stock_quantity < 0) {
        $error = "Please fill in all required fields with valid values.";
    } else {
        // Handle cover image update
        $cover_image = $book['cover_image']; // Default to current image
        
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $temp_name = $_FILES['cover']['tmp_name'];
            $file_name = $_FILES['cover']['name'];
            $file_size = $_FILES['cover']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Enhanced validation
            $valid_extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file_ext, $valid_extensions)) {
                $error = "Invalid file type. Only JPG, JPEG, PNG, WebP and GIF are allowed.";
            } elseif ($file_size > $max_size) {
                $error = "File too large. Maximum size is 5MB.";
            } else {
                // Verify it's actually an image
                $image_info = getimagesize($temp_name);
                if (!$image_info) {
                    $error = "Invalid image file.";
                } else {
                    $upload_dir = 'uploads/covers/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $new_file_name = 'book_' . $book_id . '_' . time() . '.' . $file_ext;
                    $destination = $upload_dir . $new_file_name;
                    
                    // Try to resize and save image, fallback to simple move
                    if (simpleImageResize($temp_name, $destination, $file_ext, $image_info)) {
                        // If old file exists and is not default, delete it
                        if (!empty($book['cover_image']) && 
                            $book['cover_image'] !== 'uploads/covers/default.jpg' && 
                            file_exists($book['cover_image'])) {
                            unlink($book['cover_image']);
                        }
                        $cover_image = $destination;
                    } else {
                        $error = "Failed to process image.";
                    }
                }
            }
        }

        if (empty($error)) {
            // Check what columns exist in seller_books table
            $columns_result = $conn->query("SHOW COLUMNS FROM seller_books");
            $available_columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $available_columns[] = $col['Field'];
            }
            
            // Build update query based on available columns
            $update_fields = [
                'title' => $title,
                'author' => $author,
                'price' => $price,
                'description' => $description,
                'cover_image' => $cover_image
            ];
            
            // Add optional fields if they exist in the table
            $optional_fields = [
                'cost_price' => $cost_price,
                'stock_quantity' => $stock_quantity,
                'genre' => $genre,
                'isbn' => $isbn,
                'condition_type' => $condition_type,
                'publisher' => $publisher,
                'publication_year' => $publication_year,
                'language' => $language,
                'pages' => $pages,
                'weight' => $weight,
                'dimensions' => $dimensions,
                'is_public' => $is_public
            ];
            
            foreach ($optional_fields as $field => $value) {
                if (in_array($field, $available_columns)) {
                    $update_fields[$field] = $value;
                }
            }
            
            // Build SQL
            $set_clauses = [];
            $params = [];
            $types = '';
            
            foreach ($update_fields as $field => $value) {
                $set_clauses[] = "$field = ?";
                $params[] = $value;
                if (is_int($value)) {
                    $types .= 'i';
                } elseif (is_float($value)) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                }
            }
            
            // Add updated_at if column exists
            if (in_array('updated_at', $available_columns)) {
                $set_clauses[] = "updated_at = NOW()";
            }
            
            $sql = "UPDATE seller_books SET " . implode(', ', $set_clauses) . " WHERE book_id = ? AND seller_id = ?";
            $params[] = $book_id;
            $params[] = $seller_id;
            $types .= 'ii';
            
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                
                if ($stmt->execute()) {
                    $success = "📚 Book updated successfully! All changes have been saved.";
                    
                    // Log the action if activity log table exists
                    $log_check = $conn->query("SHOW TABLES LIKE 'seller_activity_log'");
                    if ($log_check && $log_check->num_rows > 0) {
                        $action = "Updated book: " . $title . " (ID: " . $book_id . ") - Price: RM" . number_format($price, 2) . ", Stock: " . $stock_quantity;
                        $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                        if ($log) {
                            $log->bind_param("is", $seller_id, $action);
                            $log->execute();
                            $log->close();
                        }
                    }
                    
                    // Update the book array to reflect changes
                    $book = array_merge($book, $update_fields);
                } else {
                    $error = "Failed to update book: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "SQL Prepare failed: " . $conn->error;
            }
        }
    }
}

// Simplified image resize function that works without GD extension
function simpleImageResize($source, $destination, $ext, $image_info) {
    // Check if GD extension is available
    if (!extension_loaded('gd')) {
        // Fallback: just move the file without resizing
        return move_uploaded_file($source, $destination);
    }
    
    try {
        $max_width = 400;
        $max_height = 600;
        $quality = 85;

        list($orig_width, $orig_height) = $image_info;
        
        // Calculate new dimensions
        $ratio = min($max_width / $orig_width, $max_height / $orig_height);
        $new_width = intval($orig_width * $ratio);
        $new_height = intval($orig_height * $ratio);

        // Create image resource based on type
        $src_image = null;
        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                if (function_exists('imagecreatefromjpeg')) {
                    $src_image = imagecreatefromjpeg($source);
                }
                break;
            case 'png':
                if (function_exists('imagecreatefrompng')) {
                    $src_image = imagecreatefrompng($source);
                }
                break;
            case 'gif':
                if (function_exists('imagecreatefromgif')) {
                    $src_image = imagecreatefromgif($source);
                }
                break;
            case 'webp':
                if (function_exists('imagecreatefromwebp')) {
                    $src_image = imagecreatefromwebp($source);
                }
                break;
        }

        if (!$src_image) {
            // Fallback to simple file move
            return move_uploaded_file($source, $destination);
        }

        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG and GIF
        if ($ext == 'png' || $ext == 'gif') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefill($new_image, 0, 0, $transparent);
        }

        // Resize
        imagecopyresampled($new_image, $src_image, 0, 0, 0, 0, 
                          $new_width, $new_height, $orig_width, $orig_height);

        // Save based on type
        $result = false;
        switch ($ext) {
            case 'jpeg':
            case 'jpg':
                $result = imagejpeg($new_image, $destination, $quality);
                break;
            case 'png':
                $result = imagepng($new_image, $destination, 9);
                break;
            case 'gif':
                $result = imagegif($new_image, $destination);
                break;
            case 'webp':
                $result = imagewebp($new_image, $destination, $quality);
                break;
        }

        imagedestroy($src_image);
        imagedestroy($new_image);
        
        return $result;
        
    } catch (Exception $e) {
        // If any error occurs, fallback to simple file move
        return move_uploaded_file($source, $destination);
    }
}

// Calculate profit metrics safely
$profit_per_unit = $book['price'] - ($book['cost_price'] ?? 0);
$total_profit = $profit_per_unit * ($book['total_sold'] ?? 0);
$profit_margin = ($book['cost_price'] ?? 0) > 0 ? (($profit_per_unit / $book['cost_price']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Book | BookStore Seller Hub</title>
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
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .navbar-brand {
            font-weight: 800;
            color: #667eea !important;
            font-size: 1.5rem;
        }
        
        .nav-link {
            color: #2d3748 !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover,
        .nav-link.active {
            color: #667eea !important;
        }
        
        .page-header {
            padding: 3rem 0 2rem;
            text-align: center;
            color: white;
        }
        
        .page-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .page-description {
            font-size: 1.3rem;
            opacity: 0.9;
            font-weight: 500;
        }
        
        .content-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-bottom: none;
            padding: 2rem;
        }
        
        .card-header h4 {
            font-weight: 700;
            font-size: 1.5rem;
            margin: 0;
        }
        
        .card-body {
            padding: 3rem;
        }
        
        .form-label {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.75rem;
        }
        
        .form-control,
        .form-select {
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            padding: 0.875rem 1.25rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
        }
        
        .input-group-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            font-weight: 600;
            border-radius: 12px 0 0 12px;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        
        .btn-outline-gradient {
            background: transparent;
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-outline-gradient:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-back {
            background: rgba(108, 117, 125, 0.1);
            border: 2px solid rgba(108, 117, 125, 0.2);
            color: #6c757d;
            border-radius: 12px;
            padding: 0.875rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            background: rgba(108, 117, 125, 0.15);
            color: #495057;
            transform: translateY(-2px);
        }
        
        .stats-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 800;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stats-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .current-cover img {
            max-height: 250px;
            max-width: 180px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .no-cover-placeholder {
            width: 180px;
            height: 250px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
        }
        
        .no-cover-placeholder i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .file-upload-label {
            cursor: pointer;
            border: 2px dashed #667eea;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            background: rgba(102, 126, 234, 0.05);
            margin-top: 1rem;
        }
        
        .file-upload-label:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: #764ba2;
            transform: translateY(-2px);
        }
        
        .file-upload-label i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .cover-preview {
            max-height: 250px;
            max-width: 180px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin-top: 1rem;
        }
        
        .alert {
            border: none;
            border-radius: 15px;
            padding: 1.25rem 1.5rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: #667eea;
            font-size: 1.4rem;
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
            margin-right: 10px;
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
            margin: 0.25rem;
            transition: all 0.3s ease;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .stats-card {
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg sticky-top">
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
                    <a class="nav-link" href="seller_dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="seller_manage_books.php">
                        <i class="bi bi-books me-1"></i>My Books
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="seller_add_book.php">
                        <i class="bi bi-plus-circle me-1"></i>Add Book
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown">
                        <div class="avatar">
                            <?= strtoupper(substr($sellerName, 0, 1)) ?>
                        </div>
                        <span class="d-none d-md-inline"><?= htmlspecialchars($sellerName) ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="seller_settings.php">Settings</a></li>
                        <li><a class="dropdown-item" href="seller_logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Page Header -->
<div class="page-header">
    <div class="container">
        <h1 class="page-title">
            <i class="bi bi-pencil-square me-3"></i>Edit Book
        </h1>
        <p class="page-description">
            Update details for "<?= htmlspecialchars($book['title']) ?>"
        </p>
    </div>
</div>

<div class="container pb-5">
    <!-- Performance Stats -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($book['total_sold'] ?? 0) ?></div>
                <div class="stats-label">Books Sold</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stats-card">
                <div class="stats-number">RM <?= number_format($book['total_revenue'] ?? 0, 2) ?></div>
                <div class="stats-label">Total Revenue</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stats-card">
                <div class="stats-number">RM <?= number_format($profit_per_unit, 2) ?></div>
                <div class="stats-label">Profit per Unit</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stats-card">
                <div class="stats-number"><?= number_format($profit_margin, 1) ?>%</div>
                <div class="stats-label">Profit Margin</div>
            </div>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="content-card">
                <div class="card-header">
                    <h4><i class="bi bi-pencil me-2"></i>Book Information</h4>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?= htmlspecialchars($success) ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="row">
                            <!-- Left Column - Book Details -->
                            <div class="col-lg-8">
                                <!-- Basic Information -->
                                <div class="section-title">
                                    <i class="bi bi-info-circle"></i>Basic Information
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="title" class="form-label">
                                                <i class="bi bi-book me-1"></i>Book Title *
                                            </label>
                                            <input type="text" name="title" id="title" class="form-control" required 
                                                   value="<?= htmlspecialchars($book['title']) ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="author" class="form-label">
                                                <i class="bi bi-person me-1"></i>Author *
                                            </label>
                                            <input type="text" name="author" id="author" class="form-control" required 
                                                   value="<?= htmlspecialchars($book['author']) ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="isbn" class="form-label">
                                                <i class="bi bi-upc-scan me-1"></i>ISBN
                                            </label>
                                            <input type="text" name="isbn" id="isbn" class="form-control" 
                                                   value="<?= htmlspecialchars($book['isbn'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="genre" class="form-label">
                                                <i class="bi bi-tags me-1"></i>Genre
                                            </label>
                                            <select name="genre" id="genre" class="form-select">
                                                <option value="">-- Select Genre --</option>
                                                <option value="Fiction" <?= ($book['genre'] ?? '') === 'Fiction' ? 'selected' : '' ?>>Fiction</option>
                                                <option value="Non-Fiction" <?= ($book['genre'] ?? '') === 'Non-Fiction' ? 'selected' : '' ?>>Non-Fiction</option>
                                                <option value="Mystery" <?= ($book['genre'] ?? '') === 'Mystery' ? 'selected' : '' ?>>Mystery & Thriller</option>
                                                <option value="Romance" <?= ($book['genre'] ?? '') === 'Romance' ? 'selected' : '' ?>>Romance</option>
                                                <option value="SciFi" <?= ($book['genre'] ?? '') === 'SciFi' ? 'selected' : '' ?>>Science Fiction</option>
                                                <option value="Fantasy" <?= ($book['genre'] ?? '') === 'Fantasy' ? 'selected' : '' ?>>Fantasy</option>
                                                <option value="Biography" <?= ($book['genre'] ?? '') === 'Biography' ? 'selected' : '' ?>>Biography</option>
                                                <option value="Business" <?= ($book['genre'] ?? '') === 'Business' ? 'selected' : '' ?>>Business</option>
                                                <option value="Self-Help" <?= ($book['genre'] ?? '') === 'Self-Help' ? 'selected' : '' ?>>Self-Help</option>
                                                <option value="Children" <?= ($book['genre'] ?? '') === 'Children' ? 'selected' : '' ?>>Children's Books</option>
                                                <option value="Education" <?= ($book['genre'] ?? '') === 'Education' ? 'selected' : '' ?>>Education</option>
                                                <option value="History" <?= ($book['genre'] ?? '') === 'History' ? 'selected' : '' ?>>History</option>
                                                <option value="Other" <?= ($book['genre'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pricing & Inventory -->
                                <div class="section-title">
                                    <i class="bi bi-currency-dollar"></i>Pricing & Inventory
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="price" class="form-label">
                                                <i class="bi bi-tag me-1"></i>Selling Price (RM) *
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">RM</span>
                                                <input type="number" step="0.01" min="0" name="price" id="price" 
                                                       class="form-control" required value="<?= $book['price'] ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="cost_price" class="form-label">
                                                <i class="bi bi-receipt me-1"></i>Cost Price (RM)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text">RM</span>
                                                <input type="number" step="0.01" min="0" name="cost_price" id="cost_price" 
                                                       class="form-control" value="<?= $book['cost_price'] ?? 0 ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="stock_quantity" class="form-label">
                                                <i class="bi bi-boxes me-1"></i>Stock Quantity *
                                            </label>
                                            <input type="number" min="0" name="stock_quantity" id="stock_quantity" 
                                                   class="form-control" required value="<?= $book['stock_quantity'] ?? 0 ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Additional Details -->
                                <div class="section-title">
                                    <i class="bi bi-info-square"></i>Additional Details
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="condition_type" class="form-label">
                                                <i class="bi bi-star me-1"></i>Condition
                                            </label>
                                            <select name="condition_type" id="condition_type" class="form-select">
                                                <option value="new" <?= ($book['condition_type'] ?? 'new') === 'new' ? 'selected' : '' ?>>New</option>
                                                <option value="like_new" <?= ($book['condition_type'] ?? '') === 'like_new' ? 'selected' : '' ?>>Like New</option>
                                                <option value="very_good" <?= ($book['condition_type'] ?? '') === 'very_good' ? 'selected' : '' ?>>Very Good</option>
                                                <option value="good" <?= ($book['condition_type'] ?? '') === 'good' ? 'selected' : '' ?>>Good</option>
                                                <option value="acceptable" <?= ($book['condition_type'] ?? '') === 'acceptable' ? 'selected' : '' ?>>Acceptable</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="language" class="form-label">
                                                <i class="bi bi-translate me-1"></i>Language
                                            </label>
                                            <select name="language" id="language" class="form-select">
                                                <option value="English" <?= ($book['language'] ?? 'English') === 'English' ? 'selected' : '' ?>>English</option>
                                                <option value="Malay" <?= ($book['language'] ?? '') === 'Malay' ? 'selected' : '' ?>>Bahasa Malaysia</option>
                                                <option value="Chinese" <?= ($book['language'] ?? '') === 'Chinese' ? 'selected' : '' ?>>Chinese</option>
                                                <option value="Tamil" <?= ($book['language'] ?? '') === 'Tamil' ? 'selected' : '' ?>>Tamil</option>
                                                <option value="Other" <?= ($book['language'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="publisher" class="form-label">
                                                <i class="bi bi-building me-1"></i>Publisher
                                            </label>
                                            <input type="text" name="publisher" id="publisher" class="form-control" 
                                                   value="<?= htmlspecialchars($book['publisher'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="publication_year" class="form-label">
                                                <i class="bi bi-calendar me-1"></i>Publication Year
                                            </label>
                                            <input type="number" min="1000" max="<?= date('Y') ?>" name="publication_year" 
                                                   id="publication_year" class="form-control" 
                                                   value="<?= $book['publication_year'] ?? '' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="pages" class="form-label">
                                                <i class="bi bi-file-text me-1"></i>Pages
                                            </label>
                                            <input type="number" min="1" name="pages" id="pages" class="form-control" 
                                                   value="<?= $book['pages'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="weight" class="form-label">
                                                <i class="bi bi-speedometer me-1"></i>Weight (kg)
                                            </label>
                                            <input type="number" step="0.01" min="0" name="weight" id="weight" 
                                                   class="form-control" value="<?= $book['weight'] ?? '' ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="dimensions" class="form-label">
                                                <i class="bi bi-rulers me-1"></i>Dimensions (L x W x H cm)
                                            </label>
                                            <input type="text" name="dimensions" id="dimensions" class="form-control" 
                                                   placeholder="e.g., 20 x 13 x 2"
                                                   value="<?= htmlspecialchars($book['dimensions'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label for="description" class="form-label">
                                        <i class="bi bi-card-text me-1"></i>Description *
                                    </label>
                                    <textarea name="description" id="description" class="form-control" rows="5" required 
                                              placeholder="Describe your book in detail..."><?= htmlspecialchars($book['description']) ?></textarea>
                                </div>
                                
                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_public" name="is_public" 
                                               <?= isset($book['is_public']) && $book['is_public'] ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="is_public">
                                            <i class="bi bi-eye me-2"></i>Make this book public and available for sale
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column - Cover Image -->
                            <div class="col-lg-4">
                                <div class="section-title">
                                    <i class="bi bi-image"></i>Cover Image
                                </div>
                                
                                <div class="text-center mb-4">
                                    <div class="current-cover">
                                        <?php if (!empty($book['cover_image']) && file_exists($book['cover_image'])): ?>
                                            <img src="<?= $book['cover_image'] ?>?v=<?= time() ?>" 
                                                 alt="Current Cover" class="img-fluid" id="currentCover">
                                        <?php else: ?>
                                            <div class="no-cover-placeholder" id="currentCover">
                                                <i class="bi bi-image"></i>
                                                <p class="text-muted mb-0">No cover image</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <input type="file" class="form-control d-none" id="coverUpload" name="cover" 
                                           accept="image/*" onchange="previewImage(this)">
                                    <label for="coverUpload" class="file-upload-label">
                                        <i class="bi bi-cloud-upload"></i>
                                        <div class="mt-2">
                                            <strong>Change Cover Image</strong>
                                            <div class="small text-muted mt-1">
                                                Click to upload new cover<br>
                                                Max 5MB • JPG, PNG, WebP, GIF
                                            </div>
                                        </div>
                                    </label>
                                    
                                    <img id="coverPreview" class="cover-preview d-none" alt="New Cover Preview">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-4 border-top">
                            <a href="seller_manage_books.php" class="btn btn-back">
                                <i class="bi bi-arrow-left me-2"></i>Back to Books
                            </a>
                            <div class="d-flex gap-2">
                                <a href="book_preview.php?id=<?= $book_id ?>" class="btn btn-outline-gradient" target="_blank">
                                    <i class="bi bi-eye me-2"></i>Preview
                                </a>
                                <button type="submit" name="update_book" class="btn btn-gradient">
                                    <i class="bi bi-check2 me-2"></i>Update Book
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const preview = document.getElementById('coverPreview');
        const currentCover = document.getElementById('currentCover');
        
        reader.onload = function(e) {
            currentCover.style.display = 'none';
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            preview.style.display = 'block';
            
            if (!document.getElementById('removePreview')) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.id = 'removePreview';
                removeBtn.className = 'btn btn-sm btn-outline-danger mt-2';
                removeBtn.innerHTML = '<i class="bi bi-x me-1"></i>Remove';
                removeBtn.onclick = function() {
                    input.value = '';
                    preview.classList.add('d-none');
                    currentCover.style.display = 'block';
                    this.remove();
                };
                preview.parentNode.appendChild(removeBtn);
            }
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Auto-hide alerts
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success')) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-20px)';
            setTimeout(() => alert.remove(), 300);
        }
    });
}, 5000);
</script>

</body>
</html>