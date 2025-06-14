<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['seller_id'])) {
    header("Location: seller_login.php");
    exit();
}

$seller_id = $_SESSION['seller_id'];
$sellerName = $_SESSION['seller_name'] ?? 'Seller';
$book_id = $_GET['id'] ?? null;

if (!$book_id || !is_numeric($book_id)) {
    header("Location: seller_manage_books.php?error=invalid_id");
    exit();
}

$error = '';
$book = null;

try {
    // Get book details first
    $stmt = $conn->prepare("SELECT * FROM seller_books WHERE book_id = ? AND seller_id = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $book_id, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();
    $stmt->close();

    if (!$book) {
        header("Location: seller_manage_books.php?error=book_not_found");
        exit();
    }

    // Handle DELETE - SIMPLIFIED VERSION
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        try {
            // Simply delete the book - foreign key constraints will handle related data
            $delete_stmt = $conn->prepare("DELETE FROM seller_books WHERE book_id = ? AND seller_id = ?");
            if (!$delete_stmt) {
                throw new Exception("Failed to prepare delete statement: " . $conn->error);
            }
            
            $delete_stmt->bind_param("ii", $book_id, $seller_id);
            $delete_result = $delete_stmt->execute();
            $affected_rows = $delete_stmt->affected_rows;
            $delete_stmt->close();
            
            if (!$delete_result) {
                throw new Exception("Failed to execute delete: " . $conn->error);
            }
            
            if ($affected_rows === 0) {
                throw new Exception("Book not found or permission denied.");
            }
            
            // Delete cover image file if exists
            if (!empty($book['cover_image']) && file_exists($book['cover_image'])) {
                if (strpos($book['cover_image'], 'default') === false) {
                    @unlink($book['cover_image']);
                }
            }
            
            // Log the activity
            $log_action = "DELETED BOOK: " . $book['title'] . " (ID: $book_id)";
            $log_stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
            if ($log_stmt) {
                $log_stmt->bind_param("is", $seller_id, $log_action);
                $log_stmt->execute();
                $log_stmt->close();
            }
            
            // SUCCESS - Redirect
            header("Location: seller_manage_books.php?success=book_deleted&title=" . urlencode($book['title']));
            exit();
            
        } catch (Exception $e) {
            $error = "Delete failed: " . $e->getMessage();
            error_log("DELETE ERROR (Book ID: $book_id, Seller ID: $seller_id): " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    error_log("Delete book error: " . $e->getMessage());
    $error = "Error loading book data: " . $e->getMessage();
}

// If book is null, redirect back
if (!$book) {
    header("Location: seller_manage_books.php?error=book_not_found");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Book | BookStore Seller Hub</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .delete-container {
            max-width: 500px;
            width: 100%;
        }
        
        .delete-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2.5rem 2rem;
            text-align: center;
            border: none;
        }
        
        .warning-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #fff;
        }
        
        .card-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.8rem;
        }
        
        .card-body {
            padding: 2.5rem 2rem;
            text-align: center;
        }
        
        .book-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 2rem;
            margin: 1.5rem 0;
            border: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .book-cover {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-bottom: 1rem;
        }
        
        .no-cover {
            width: 80px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
        }
        
        .book-title {
            font-weight: 700;
            font-size: 1.2rem;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .book-author {
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .book-price {
            font-weight: 700;
            font-size: 1.2rem;
            color: #667eea;
        }
        
        .book-stats {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .book-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .warning-text {
            font-size: 1.1rem;
            color: #2d3748;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        
        .danger-text {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 2px solid #f39c12;
            border-radius: 12px;
            padding: 1.25rem;
            margin: 1.5rem 0;
            color: #856404;
            font-weight: 600;
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
            color: white;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 700;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
            color: white;
        }
        
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }
        
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
            text-align: left;
            color: #6c757d;
        }
        
        @media (max-width: 576px) {
            .delete-container {
                padding: 1rem;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn-delete,
            .btn-cancel {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="delete-container">
        <div class="delete-card">
            <div class="card-header">
                <div class="warning-icon">
                    <i class="bi bi-trash"></i>
                </div>
                <h2>Delete Book</h2>
            </div>
            
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="warning-text">
                    Are you sure you want to permanently delete this book?
                </div>

                <div class="book-info">
                    <?php if (!empty($book['cover_image']) && file_exists($book['cover_image'])): ?>
                        <img src="<?= htmlspecialchars($book['cover_image']) ?>?v=<?= time() ?>" 
                             alt="Book Cover" class="book-cover">
                    <?php else: ?>
                        <div class="no-cover">
                            <i class="bi bi-book"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="book-title"><?= htmlspecialchars($book['title']) ?></div>
                    <div class="book-author">by <?= htmlspecialchars($book['author']) ?></div>
                    <div class="book-price">RM <?= number_format($book['price'], 2) ?></div>
                    
                    <div class="book-stats">
                        <div class="book-stat">
                            <i class="bi bi-boxes"></i>
                            <span><?= $book['stock_quantity'] ?> in stock</span>
                        </div>
                        
                        <div class="book-stat">
                            <i class="bi bi-hash"></i>
                            <span>ID: <?= $book['book_id'] ?></span>
                        </div>
                        
                        <div class="book-stat">
                            <i class="bi bi-person"></i>
                            <span>Seller: <?= $seller_id ?></span>
                        </div>
                        
                        <div class="book-stat">
                            <i class="bi bi-calendar-plus"></i>
                            <span>Added <?= date('M j, Y', strtotime($book['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <div class="danger-text">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone. The book and all related data will be permanently deleted from the database.
                </div>

                <!-- SIMPLE FORM WITHOUT JAVASCRIPT CONFIRMATION -->
                <form method="POST" id="deleteForm">
                    <div class="actions">
                        <a href="seller_manage_books.php" class="btn-cancel">
                            <i class="bi bi-arrow-left me-2"></i>Cancel
                        </a>
                        
                        <button type="submit" class="btn-delete" onclick="return confirm('⚠️ Are you sure you want to delete this book? This action cannot be undone!')">
                            <i class="bi bi-trash me-2"></i>Delete Book
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Simple loading state without complex confirmation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const deleteBtn = document.querySelector('.btn-delete');
            setTimeout(() => {
                deleteBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Deleting...';
                deleteBtn.disabled = true;
            }, 100);
        });
    </script>
</body>
</html>