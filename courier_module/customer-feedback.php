<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: courier-login.html");
    exit();
}

$courier_id = $_SESSION['courier_id'];
$delivery_id = $_GET['delivery_id'] ?? null;
$success_message = '';
$error_message = '';

// Validate delivery_id and check if it belongs to the courier
if ($delivery_id) {
    $stmt = $conn->prepare("SELECT d.*, c.name as customer_name FROM deliveries d 
                           JOIN customers c ON d.customer_id = c.id 
                           WHERE d.id = ? AND d.courier_id = ? AND d.status = 'completed'");
    $stmt->bind_param("is", $delivery_id, $courier_id);
    $stmt->execute();
    $delivery = $stmt->get_result()->fetch_assoc();
    
    if (!$delivery) {
        header("Location: delivery-history.php");
        exit();
    }
    
    // Check if feedback already exists
    $stmt = $conn->prepare("SELECT id FROM customer_feedback WHERE delivery_id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $existing_feedback = $stmt->get_result()->fetch_assoc();
    
    if ($existing_feedback) {
        $error_message = "Feedback has already been submitted for this delivery.";
    }
} else {
    header("Location: delivery-history.php");
    exit();
}

// Handle feedback submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_feedback'])) {
    try {
        $customer_rating = intval($_POST['customer_rating']);
        $customer_comment = trim($_POST['customer_comment']);
        $delivery_experience = $_POST['delivery_experience'];
        
        // Validation
        if ($customer_rating < 1 || $customer_rating > 5) {
            throw new Exception("Please provide a valid rating between 1 and 5 stars.");
        }
        
        if (empty($customer_comment)) {
            throw new Exception("Please provide a comment about the delivery experience.");
        }
        
        if (strlen($customer_comment) < 10) {
            throw new Exception("Comment must be at least 10 characters long.");
        }
        
        // Check again if feedback already exists (prevent duplicates)
        $stmt = $conn->prepare("SELECT id FROM customer_feedback WHERE delivery_id = ?");
        $stmt->bind_param("i", $delivery_id);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            throw new Exception("Feedback has already been submitted for this delivery.");
        }
        
        // Insert feedback
        $stmt = $conn->prepare("INSERT INTO customer_feedback (delivery_id, courier_id, customer_rating, customer_comment, delivery_experience) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isiss", $delivery_id, $courier_id, $customer_rating, $customer_comment, $delivery_experience);
        
        if ($stmt->execute()) {
            $success_message = "Customer feedback submitted successfully! Thank you for collecting valuable feedback.";
            
            // Update courier's average rating
            $stmt = $conn->prepare("UPDATE couriers SET avg_rating = (
                SELECT AVG(customer_rating) FROM customer_feedback WHERE courier_id = ?
            ) WHERE courier_id = ?");
            $stmt->bind_param("ss", $courier_id, $courier_id);
            $stmt->execute();
            
        } else {
            throw new Exception("Failed to submit feedback. Please try again.");
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get courier information
$stmt = $conn->prepare("SELECT name FROM couriers WHERE courier_id = ?");
$stmt->bind_param("s", $courier_id);
$stmt->execute();
$courier = $stmt->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">    <title>Customer Feedback - BookStore</title>    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <style>
        /* Page-specific styles only - sidebar styles moved to css/sidebar.css */
        .main-content {
            max-width: 800px;
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

        .feedback-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-top: 4px solid var(--primary-color);
        }

        .delivery-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }

        .delivery-info h3 {
            color: var(--text-color);
            margin-bottom: 1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            background: white;
            padding: 1rem;
            border-radius: 6px;
        }

        .info-item strong {
            color: var(--text-color);
            display: block;
            margin-bottom: 0.3rem;
            font-size: 0.85rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .star-rating {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star {
            font-size: 2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .star:hover,
        .star.active {
            color: #ffd700;
        }

        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            resize: vertical;
            min-height: 100px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(155, 89, 182, 0.1);
        }

        .form-group select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(155, 89, 182, 0.1);
        }

        .btn-submit {
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
        }

        .btn-submit:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(155, 89, 182, 0.3);
        }

        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }

        .btn-back:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: var(--success-color);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: var(--error-color);
        }

        .character-count {
            font-size: 0.8rem;
            color: #666;
            text-align: right;
            margin-top: 0.3rem;
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

            .info-grid {
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
            <li><a href="customer-feedback.php" class="active"><i class="fas fa-star"></i> Customer Feedback</a></li>
            <li><a href="advanced-search.php"><i class="fas fa-search"></i> Advanced Search</a></li>
            <li><a href="courier-profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-star"></i> Customer Feedback</h1>
            <p>Collect valuable customer feedback for completed deliveries</p>
        </div>

        <a href="delivery-history.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Delivery History
        </a>

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

        <div class="feedback-container">
            <div class="delivery-info">
                <h3><i class="fas fa-package"></i> Delivery Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Order ID:</strong> <?php echo htmlspecialchars($delivery['order_id']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Customer:</strong> <?php echo htmlspecialchars($delivery['customer_name']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Delivery Address:</strong> <?php echo htmlspecialchars($delivery['delivery_address']); ?>
                    </div>
                    <div class="info-item">
                        <strong>Completed On:</strong> <?php echo date('M j, Y g:i A', strtotime($delivery['updated_at'])); ?>
                    </div>
                </div>
            </div>

            <?php if (!$existing_feedback && !$success_message): ?>
            <form action="" method="POST" id="feedbackForm">
                <div class="form-group">
                    <label for="customer_rating">Customer Rating *</label>
                    <div class="star-rating" id="starRating">
                        <i class="fas fa-star star" data-rating="1"></i>
                        <i class="fas fa-star star" data-rating="2"></i>
                        <i class="fas fa-star star" data-rating="3"></i>
                        <i class="fas fa-star star" data-rating="4"></i>
                        <i class="fas fa-star star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="customer_rating" id="rating" required>
                    <small style="color: #666;">Click on the stars to rate the customer's satisfaction</small>
                </div>

                <div class="form-group">
                    <label for="delivery_experience">Overall Delivery Experience *</label>
                    <select name="delivery_experience" id="delivery_experience" required>
                        <option value="">Select experience level</option>
                        <option value="excellent">Excellent - Customer very satisfied</option>
                        <option value="good">Good - Customer satisfied</option>
                        <option value="average">Average - Customer neutral</option>
                        <option value="poor">Poor - Customer dissatisfied</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="customer_comment">Customer Feedback Comment *</label>
                    <textarea name="customer_comment" id="customer_comment" 
                              placeholder="Enter the customer's feedback about the delivery service, condition of items, timing, etc..." 
                              required minlength="10" maxlength="500"></textarea>
                    <div class="character-count">
                        <span id="charCount">0</span>/500 characters (minimum 10)
                    </div>
                </div>

                <input type="hidden" name="submit_feedback" value="1">
                <button type="submit" class="btn-submit" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Customer Feedback
                </button>
            </form>
            <?php elseif ($existing_feedback): ?>
                <div class="alert alert-error">
                    <i class="fas fa-info-circle"></i> Customer feedback has already been collected for this delivery.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('rating');
            const commentTextarea = document.getElementById('customer_comment');
            const charCountSpan = document.getElementById('charCount');
            const submitBtn = document.getElementById('submitBtn');
            
            // Star rating functionality
            stars.forEach((star, index) => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    ratingInput.value = rating;
                    
                    // Update star display
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                    
                    validateForm();
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = this.getAttribute('data-rating');
                    stars.forEach((s, i) => {
                        if (i < rating) {
                            s.style.color = '#ffd700';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });
            
            // Reset star colors on mouse leave
            document.getElementById('starRating').addEventListener('mouseleave', function() {
                const currentRating = ratingInput.value;
                stars.forEach((s, i) => {
                    if (i < currentRating) {
                        s.style.color = '#ffd700';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
            
            // Character count for textarea
            commentTextarea.addEventListener('input', function() {
                const count = this.value.length;
                charCountSpan.textContent = count;
                
                if (count < 10) {
                    charCountSpan.style.color = '#e74c3c';
                } else if (count > 450) {
                    charCountSpan.style.color = '#f39c12';
                } else {
                    charCountSpan.style.color = '#27ae60';
                }
                
                validateForm();
            });
            
            // Form validation
            function validateForm() {
                const rating = ratingInput.value;
                const comment = commentTextarea.value;
                const experience = document.getElementById('delivery_experience').value;
                
                if (rating && comment.length >= 10 && experience) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            // Initial validation
            document.getElementById('delivery_experience').addEventListener('change', validateForm);
            
            // Form submission
            document.getElementById('feedbackForm').addEventListener('submit', function(e) {
                if (!ratingInput.value || commentTextarea.value.length < 10) {
                    e.preventDefault();
                    alert('Please complete all required fields with valid data.');
                }
            });
        });
    </script>
</body>
</html>
