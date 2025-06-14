<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

$alert = '';
$success = '';

// Check if the user was redirected after password reset
if (isset($_GET['status']) && $_GET['status'] === 'reset_success') {
    $success = "Your password has been successfully reset. Please login with your new credentials.";
}

// Check if the user was redirected after registration
if (isset($_GET['status']) && $_GET['status'] === 'registration_success') {
    $success = "Registration successful! Welcome to BookStore Seller Hub. Please login to access your dashboard.";
}

// Check for remember me cookie and auto-fill email
$remembered_email = '';
if(isset($_COOKIE['seller_remember']) && isset($_COOKIE['seller_email'])) {
    $remembered_email = $_COOKIE['seller_email'];
    // You could also auto-login here if you wanted to implement that feature
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Enhanced validation
    if(empty($email)) {
        $alert = "Please enter your email address.";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = "Please enter a valid email address.";
    } elseif(empty($password)) {
        $alert = "Please enter your password.";
    } else {
        $stmt = $conn->prepare("SELECT seller_id, seller_name, seller_password, registration_date FROM seller_users WHERE seller_email = ?");
        if(!$stmt) {
            $alert = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($seller_id, $seller_name, $hashed_password, $registration_date);
                $stmt->fetch();

                if (password_verify($password, $hashed_password)) {
                    $_SESSION['seller_id'] = $seller_id;
                    $_SESSION['seller_name'] = $seller_name;

                    // Enhanced remember me functionality
                    if ($remember) {
                        // Set secure cookies for 30 days
                        $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                        setcookie('seller_email', $email, $expiry, '/', '', false, true);
                        setcookie('seller_remember', '1', $expiry, '/', '', false, true);
                        
                        // For higher security, you could also store a token in the database
                        // and set that as a cookie instead of storing the email directly
                    } else {
                        // If remember me is not checked, clear existing cookies
                        if(isset($_COOKIE['seller_email'])) {
                            setcookie('seller_email', '', time() - 3600, '/');
                            setcookie('seller_remember', '', time() - 3600, '/');
                        }
                    }

                    // Log the login action
                    $action = "Logged in successfully from " . $_SERVER['REMOTE_ADDR'];
                    $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                    if($log) {
                        $log->bind_param("is", $seller_id, $action);
                        $log->execute();
                        $log->close();
                    }

                    $success = "🎉 Login successful! Welcome back to BookStore Seller Hub. Redirecting to your dashboard...";
                    
                    // JavaScript redirect after showing success
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'seller_dashboard.php';
                        }, 2000);
                    </script>";
                } else {
                    $alert = "Invalid password. Please check your credentials and try again.";
                    
                    // Log failed login attempt
                    $action = "Failed login attempt from " . $_SERVER['REMOTE_ADDR'] . " - incorrect password";
                    $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                    if($log) {
                        $log->bind_param("is", $seller_id, $action);
                        $log->execute();
                        $log->close();
                    }
                }
            } else {
                $alert = "No account found with this email address. Please check your email or register for a new account.";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Seller Login | BookStore</title>
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
            display: flex;
            align-items: center;
            padding: 2rem 0;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="books" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" x="2" y="2" width="16" height="16"/><rect fill="rgba(255,255,255,0.02)" x="4" y="4" width="12" height="12"/></pattern></defs><rect width="100" height="100" fill="url(%23books)"/></svg>') repeat;
            opacity: 0.3;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-20px) scale(1.02); }
        }
        
        .login-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }
        
        .login-card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            border-radius: 25px 25px 0 0 !important;
            padding: 3rem 2rem;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }
        
        .login-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .card-header h2 {
            font-weight: 800;
            margin-bottom: 0.5rem;
            font-size: 2.2rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .card-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .card-body {
            padding: 3rem;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 0 0 25px 25px;
            position: relative;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
        }
        
        .form-floating .form-control {
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem 1rem 0.5rem 1rem;
            height: calc(3.5rem + 2px);
            font-weight: 500;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
        }
        
        .form-floating .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: white;
            transform: translateY(-2px);
        }
        
        .form-floating label {
            padding: 1rem 1rem 0.5rem 1rem;
            color: #6c757d;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .form-floating .form-control:focus ~ label,
        .form-floating .form-control:not(:placeholder-shown) ~ label {
            opacity: 0.8;
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: #667eea;
        }
        
        .form-check {
            margin-bottom: 2rem;
        }
        
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            border: 2px solid #667eea;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        
        .form-check-label {
            font-weight: 500;
            color: #2d3748;
            margin-left: 0.5rem;
        }
        
        .forgot-password {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            color: #764ba2;
            text-decoration: underline;
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
            position: relative;
            overflow: hidden;
            width: 100%;
            font-size: 1rem;
        }
        
        .btn-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-gradient:hover::before {
            left: 100%;
        }
        
        .btn-gradient:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-gradient:active {
            transform: translateY(-1px);
        }
        
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
        
        .back-btn {
            position: absolute;
            top: 30px;
            left: 30px;
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            z-index: 1000;
            font-size: 0.9rem;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .alert {
            border: none;
            border-radius: 15px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }
        
        .alert i {
            font-size: 1.2em;
            margin-right: 0.75rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .register-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .features-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            color: #2d3748;
            font-weight: 500;
        }
        
        .feature-item i {
            color: #667eea;
            font-size: 1.2rem;
            margin-right: 1rem;
            width: 20px;
        }
        
        .feature-item:last-child {
            margin-bottom: 0;
        }
        
        .security-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .security-badge i {
            margin-right: 0.5rem;
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
        
        /* Password visibility toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            z-index: 3;
            transition: color 0.3s ease;
        }
        
        .password-toggle:hover {
            color: #667eea;
        }
        
        /* Form validation states */
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .invalid-feedback {
            color: #dc3545;
            font-weight: 500;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .valid-feedback {
            color: #28a745;
            font-weight: 500;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .card-header {
                padding: 2rem 1rem;
            }
            
            .card-header h2 {
                font-size: 1.8rem;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .back-btn {
                top: 15px;
                left: 15px;
                padding: 0.5rem 1rem;
                font-size: 0.8rem;
            }
            
            .login-icon {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 1rem 0;
            }
            
            .login-container {
                padding: 0 1rem;
            }
            
            .features-section {
                padding: 1.5rem;
            }
        }
        
        /* Focus styles for accessibility */
        .form-control:focus,
        .btn:focus,
        .form-check-input:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        
        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(8px);
        }
        
        .loading-content {
            background: white;
            padding: 2.5rem;
            border-radius: 1.5rem;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 350px;
            margin: 1rem;
        }
        
        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }
        
        .loading-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        
        .loading-subtext {
            font-size: 0.9rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <a href="../select-role.html" class="back-btn animate-on-scroll">
        <i class="bi bi-arrow-left me-2"></i>Back to Roles
    </a>
    
    <div class="login-container">
        <div class="login-card animate-on-scroll">
            <div class="card-header">
                <i class="bi bi-shop login-icon"></i>
                <h2>Welcome Back</h2>
                <p>Sign in to your BookStore Seller Hub</p>
            </div>
            
            <div class="card-body">
                <?php if ($alert): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= htmlspecialchars($alert) ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate id="loginForm">
                    <div class="form-floating">
                        <input type="email" name="email" id="email" class="form-control" 
                               placeholder="Email Address" required
                               value="<?= htmlspecialchars($remembered_email) ?>">
                        <label for="email"><i class="bi bi-envelope me-2"></i>Email Address</label>
                        <div class="invalid-feedback">Please enter a valid email address</div>
                    </div>
                    
                    <div class="form-floating position-relative">
                        <input type="password" name="password" id="password" class="form-control" 
                               placeholder="Password" required>
                        <label for="password"><i class="bi bi-lock me-2"></i>Password</label>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                        <div class="invalid-feedback">Please enter your password</div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="remember" name="remember"
                                   <?= isset($_COOKIE['seller_remember']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="remember">
                                <i class="bi bi-bookmark-check me-1"></i> Remember me
                            </label>
                        </div>
                        <a href="seller_forgot_password.php" class="forgot-password">
                            <i class="bi bi-question-circle me-1"></i>Forgot password?
                        </a>
                    </div>
                    
                    <button class="btn btn-gradient py-3 mb-3" type="submit" id="submitBtn">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Sign In to Dashboard
                    </button>
                    
                    <div class="security-badge">
                        <i class="bi bi-shield-lock-fill"></i>
                        Secure SSL encrypted connection
                    </div>
                    
                    <div class="register-link">
                        <p class="text-muted mb-0">
                            Don't have a seller account? 
                            <a href="seller_register.php">
                                <i class="bi bi-person-plus me-1"></i>Create one now
                            </a>
                        </p>
                    </div>
                </form>

                <!-- Features Section -->
                <div class="features-section">
                    <h6 class="fw-bold mb-3 text-center">Why Choose BookStore?</h6>
                    <div class="feature-item">
                        <i class="bi bi-shield-check"></i>
                        <span>Secure seller platform with fraud protection</span>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-graph-up"></i>
                        <span>Advanced analytics and sales insights</span>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-people"></i>
                        <span>Access to thousands of book buyers</span>
                    </div>
                    <div class="feature-item">
                        <i class="bi bi-headset"></i>
                        <span>24/7 customer support and assistance</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Signing you in...</div>
            <div class="loading-subtext">Please wait while we verify your credentials</div>
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

        // Enhanced form validation and functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Password visibility toggle
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                if (type === 'text') {
                    eyeIcon.classList.remove('bi-eye');
                    eyeIcon.classList.add('bi-eye-slash');
                } else {
                    eyeIcon.classList.remove('bi-eye-slash');
                    eyeIcon.classList.add('bi-eye');
                }
            });

            // Enhanced form validation
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                let isValid = true;
                
                // Email validation
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email) {
                    emailInput.setCustomValidity('Please enter your email address');
                    isValid = false;
                } else if (!emailRegex.test(email)) {
                    emailInput.setCustomValidity('Please enter a valid email address');
                    isValid = false;
                } else {
                    emailInput.setCustomValidity('');
                }
                
                // Password validation
                const password = passwordInput.value;
                if (!password) {
                    passwordInput.setCustomValidity('Please enter your password');
                    isValid = false;
                } else if (password.length < 6) {
                    passwordInput.setCustomValidity('Password should be at least 6 characters long');
                    isValid = false;
                } else {
                    passwordInput.setCustomValidity('');
                }
                
                form.classList.add('was-validated');
                
                if (isValid) {
                    // Show loading state
                    submitBtn.classList.add('btn-loading');
                    submitBtn.disabled = true;
                    loadingOverlay.style.display = 'flex';
                    
                    // Prevent scrolling while loading
                    document.body.style.overflow = 'hidden';
                    
                    // Add a small delay to show the loading animation
                    setTimeout(() => {
                        form.submit();
                    }, 1000);
                } else {
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                }
            });

            // Real-time validation feedback
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (email && emailRegex.test(email)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    this.setCustomValidity('');
                } else if (email) {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });

            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                if (password && password.length >= 6) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    this.setCustomValidity('');
                } else if (password) {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-valid', 'is-invalid');
                }
            });

            // Remember me functionality enhancement
            const rememberCheckbox = document.getElementById('remember');
            rememberCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    // Show tooltip or message about remember me
                    console.log('Remember me enabled - your email will be saved for 30 days');
                }
            });

            // Auto-hide alerts after some time
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => alert.remove(), 300);
                    }, 5000);
                }
            });

            // Keyboard accessibility
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && e.target.tagName !== 'BUTTON') {
                    const submitButton = document.getElementById('submitBtn');
                    if (submitButton && !submitButton.disabled) {
                        submitButton.click();
                    }
                }
            });
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Loading animation enhancement
        <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const loadingOverlay = document.getElementById('loadingOverlay');
            loadingOverlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            
            // Show custom success loading message
            const loadingText = document.querySelector('.loading-text');
            const loadingSubtext = document.querySelector('.loading-subtext');
            loadingText.textContent = 'Login successful!';
            loadingSubtext.textContent = 'Redirecting to your dashboard...';
        });
        <?php endif; ?>

        // Enhanced error handling for network issues
        window.addEventListener('online', function() {
            console.log('Connection restored');
        });

        window.addEventListener('offline', function() {
            alert('Network connection lost. Please check your internet connection.');
        });
    </script>
</body>
</html>