<?php


session_start();
require_once __DIR__ . '/includes/seller_db.php';

$alert = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['seller_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $phone = trim($_POST['phone'] ?? '');
    $business_name = trim($_POST['business_name'] ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    
    // Enhanced validation
    if(empty($name)) {
        $alert = "Please enter your full name.";
    } elseif(strlen($name) < 2) {
        $alert = "Name must be at least 2 characters long.";
    } elseif(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = "Please enter a valid email address.";
    } elseif(strlen($password) < 8) {
        $alert = "Password must be at least 8 characters long.";
    } elseif(!preg_match('/[A-Z]/', $password)) {
        $alert = "Password must contain at least one uppercase letter.";
    } elseif(!preg_match('/[a-z]/', $password)) {
        $alert = "Password must contain at least one lowercase letter.";
    } elseif(!preg_match('/[0-9]/', $password)) {
        $alert = "Password must contain at least one number.";
    } elseif($phone && !preg_match('/^[\+]?[1-9][\d]{3,14}$/', str_replace([' ', '-', '(', ')'], '', $phone))) {
        $alert = "Please enter a valid phone number.";
    } else {
        // Check for existing email
        $check = $conn->prepare("SELECT seller_id FROM seller_users WHERE seller_email = ?");
        if(!$check) {
            $alert = "Database error: " . $conn->error;
        } else {
            $check->bind_param("s", $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $alert = "This email is already registered. Please use a different email or try logging in.";
                $check->close();
            } else {
                $check->close();
                
                // Hash password securely with bcrypt
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);                // Insert new seller with all required fields and proper defaults
                $stmt = $conn->prepare("INSERT INTO seller_users (
                    seller_name, seller_email, seller_password, phone, business_name, business_address,
                    bio, website, location, business_type, business_phone, business_email, tax_id, 
                    profile_photo, dark_mode, compact_view, email_notifications, language, timezone, 
                    currency, notify_orders, notify_messages, notify_reviews, notify_system, 
                    notify_marketing, sms_notifications, two_factor_enabled, two_factor_secret, 
                    remember_token, registration_date
                ) VALUES (?, ?, ?, ?, ?, ?, '', '', '', '', '', '', '', '', 0, 0, 1, 'en', 'Asia/Kuala_Lumpur', 'MYR', 1, 1, 1, 1, 0, 0, 0, '', '', NOW())");
                if(!$stmt) {
                    $alert = "Database error: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssss", $name, $email, $hashedPassword, $phone, $business_name, $business_address);

                    if ($stmt->execute()) {
                        $seller_id = $stmt->insert_id;
                        
                        // Log the registration action
                        $action = "Successfully registered as seller - Welcome to BookStore!";
                        $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                        if($log) {
                            $log->bind_param("is", $seller_id, $action);
                            $log->execute();
                            $log->close();
                        }

                        // Set session and redirect
                        $_SESSION['seller_id'] = $seller_id;
                        $_SESSION['seller_name'] = $name;
                        $_SESSION['registration_success'] = true;
                        $success = "🎉 Registration successful! Welcome to BookStore Seller Hub. Redirecting to your dashboard...";
                        
                        // JavaScript redirect after showing success message
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'seller_dashboard.php';
                            }, 2500);
                        </script>";
                    } else {
                        $alert = "Registration failed: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Join BookStore | Seller Registration</title>
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
        }
        
        .registration-container {
            width: 100%;
            max-width: 900px;
            margin: 0 auto;
        }
        
        .registration-card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .registration-card:hover {
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
        
        .registration-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
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
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }
        
        .strength-bar {
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 0.5rem;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #fd7e14; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        
        .requirement i {
            margin-right: 0.5rem;
            font-size: 0.7rem;
        }
        
        .requirement.met {
            color: #28a745;
        }
        
        .requirement.unmet {
            color: #dc3545;
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
            margin-right: 0.5rem;
        }
        
        .terms-section {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .form-check {
            margin-bottom: 1.5rem;
        }
        
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            border: 2px solid #667eea;
            border-radius: 4px;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .form-check-label {
            font-weight: 500;
            color: #2d3748;
            margin-left: 0.5rem;
        }
        
        .terms-link {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
        }
        
        .terms-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(102, 126, 234, 0.1);
        }
        
        .login-link a {
            color: #667eea;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .feature-highlights {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            color: #2d3748;
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
                font-size: 0.9rem;
            }
            
            .registration-icon {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 1rem 0;
            }
            
            .registration-container {
                padding: 0 1rem;
            }
            
            .section-title {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <a href="seller_login.php" class="back-btn animate-on-scroll">
        <i class="bi bi-arrow-left me-2"></i>Back to Login
    </a>
    
    <div class="registration-container">
        <div class="registration-card animate-on-scroll">
            <div class="card-header">
                <i class="bi bi-shop registration-icon"></i>
                <h2>Join BookStore</h2>
                <p>Start your journey as a professional book seller</p>
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

                <form method="POST" action="" class="needs-validation" novalidate id="registrationForm">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="bi bi-person-circle"></i>Personal Information
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="text" name="seller_name" id="seller_name" class="form-control" 
                                           placeholder="Full Name" required minlength="2"
                                           value="<?= isset($_POST['seller_name']) ? htmlspecialchars($_POST['seller_name']) : '' ?>">
                                    <label for="seller_name"><i class="bi bi-person me-2"></i>Full Name *</label>
                                    <div class="invalid-feedback">Please enter your full name (minimum 2 characters)</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-floating">
                                    <input type="tel" name="phone" id="phone" class="form-control" 
                                           placeholder="Phone Number"
                                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                                    <label for="phone"><i class="bi bi-telephone me-2"></i>Phone Number</label>
                                    <div class="form-text">Optional - for account security</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="email" name="email" id="email" class="form-control" 
                                   placeholder="Business Email" required
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            <label for="email"><i class="bi bi-envelope me-2"></i>Email Address *</label>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                            <div class="form-text">This will be your login email</div>
                        </div>
                        
                        <div class="form-floating">
                            <input type="password" name="password" id="password" class="form-control" 
                                   placeholder="Create Password" required minlength="8">
                            <label for="password"><i class="bi bi-lock me-2"></i>Create Password *</label>
                            <div class="invalid-feedback">Password must be at least 8 characters</div>
                            
                            <div class="password-strength">
                                <div class="strength-bar">
                                    <div class="strength-fill" id="strengthBar"></div>
                                </div>
                                <div class="password-requirements" id="passwordRequirements">
                                    <div class="requirement unmet" id="req-length">
                                        <i class="bi bi-x-circle"></i>At least 8 characters
                                    </div>
                                    <div class="requirement unmet" id="req-upper">
                                        <i class="bi bi-x-circle"></i>One uppercase letter
                                    </div>
                                    <div class="requirement unmet" id="req-lower">
                                        <i class="bi bi-x-circle"></i>One lowercase letter
                                    </div>
                                    <div class="requirement unmet" id="req-number">
                                        <i class="bi bi-x-circle"></i>One number
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Information Section -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="bi bi-building"></i>Business Information
                            <small class="text-muted ms-2">(Optional)</small>
                        </h5>
                        
                        <div class="form-floating">
                            <input type="text" name="business_name" id="business_name" class="form-control" 
                                   placeholder="Business Name"
                                   value="<?= isset($_POST['business_name']) ? htmlspecialchars($_POST['business_name']) : '' ?>">
                            <label for="business_name"><i class="bi bi-shop me-2"></i>Business Name</label>
                            <div class="form-text">Leave blank if selling as an individual</div>
                        </div>
                        
                        <div class="form-floating">
                            <textarea name="business_address" id="business_address" class="form-control" 
                                      placeholder="Business Address" style="min-height: 100px;"><?= isset($_POST['business_address']) ? htmlspecialchars($_POST['business_address']) : '' ?></textarea>
                            <label for="business_address"><i class="bi bi-geo-alt me-2"></i>Business Address</label>
                            <div class="form-text">Optional - for shipping and tax purposes</div>
                        </div>
                    </div>

                    <!-- Terms and Conditions -->
                    <div class="terms-section">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                            <label class="form-check-label" for="terms">
                                I agree to the <a href="#" class="terms-link" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a> 
                                and <a href="#" class="terms-link" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a> *
                            </label>
                            <div class="invalid-feedback">You must agree to the terms and conditions</div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="newsletter" name="newsletter">
                            <label class="form-check-label" for="newsletter">
                                Subscribe to our newsletter for seller tips and updates
                            </label>
                        </div>
                    </div>

                    <!-- Feature Highlights -->
                    <div class="feature-highlights">
                        <h6 class="fw-bold mb-3 text-center">Why Join BookStore?</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="feature-item">
                                    <i class="bi bi-shield-check"></i>
                                    <span>Secure & Trusted Platform</span>
                                </div>
                                <div class="feature-item">
                                    <i class="bi bi-graph-up"></i>
                                    <span>Advanced Analytics Dashboard</span>
                                </div>
                                <div class="feature-item">
                                    <i class="bi bi-people"></i>
                                    <span>Large Customer Base</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="feature-item">
                                    <i class="bi bi-credit-card"></i>
                                    <span>Easy Payment Processing</span>
                                </div>
                                <div class="feature-item">
                                    <i class="bi bi-headset"></i>
                                    <span>24/7 Customer Support</span>
                                </div>
                                <div class="feature-item">
                                    <i class="bi bi-award"></i>
                                    <span>Seller Verification Program</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button class="btn btn-gradient w-100 py-3 mb-3" type="submit" id="submitBtn">
                        <i class="bi bi-person-plus me-2"></i>Create Seller Account
                    </button>
                    
                    <div class="login-link">
                        <p class="text-muted mb-0">
                            Already have an account? 
                            <a href="seller_login.php">Login here</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Terms Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px 20px 0 0;">
                    <h5 class="modal-title"><i class="bi bi-file-text me-2"></i>Terms & Conditions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>1. Acceptance of Terms</h6>
                    <p>By registering as a seller on BookStore, you agree to these terms and conditions.</p>
                    
                    <h6>2. Seller Responsibilities</h6>
                    <ul>
                        <li>Provide accurate book descriptions and pricing</li>
                        <li>Maintain adequate inventory levels</li>
                        <li>Ship books within specified timeframes</li>
                        <li>Respond to customer inquiries promptly</li>
                    </ul>
                    
                    <h6>3. Commission Structure</h6>
                    <p>BookStore charges a competitive commission on each sale to cover platform costs and services.</p>
                    
                    <h6>4. Account Security</h6>
                    <p>You are responsible for maintaining the security of your account credentials.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius: 20px;">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 20px 20px 0 0;">
                    <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Privacy Policy</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6>Information We Collect</h6>
                    <p>We collect information you provide when registering and using our platform.</p>
                    
                    <h6>How We Use Your Information</h6>
                    <ul>
                        <li>To provide and maintain our services</li>
                        <li>To process transactions</li>
                        <li>To communicate with you</li>
                        <li>To improve our platform</li>
                    </ul>
                    
                    <h6>Data Security</h6>
                    <p>We implement appropriate security measures to protect your personal information.</p>
                    
                    <h6>Contact Us</h6>
                    <p>If you have questions about this Privacy Policy, please contact our support team.</p>
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

        // Form validation with enhanced features
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            const submitBtn = document.getElementById('submitBtn');
            const passwordInput = document.getElementById('password');
            
            // Password strength checker
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                checkPasswordStrength(password);
            });
            
            function checkPasswordStrength(password) {
                const requirements = {
                    length: password.length >= 8,
                    upper: /[A-Z]/.test(password),
                    lower: /[a-z]/.test(password),
                    number: /[0-9]/.test(password)
                };
                
                // Update requirement indicators
                Object.keys(requirements).forEach(req => {
                    const element = document.getElementById(`req-${req}`);
                    const icon = element.querySelector('i');
                    
                    if (requirements[req]) {
                        element.classList.remove('unmet');
                        element.classList.add('met');
                        icon.className = 'bi bi-check-circle';
                    } else {
                        element.classList.remove('met');
                        element.classList.add('unmet');
                        icon.className = 'bi bi-x-circle';
                    }
                });
                
                // Update strength bar
                const strengthBar = document.getElementById('strengthBar');
                const metCount = Object.values(requirements).filter(Boolean).length;
                
                strengthBar.className = 'strength-fill';
                
                if (metCount === 0) {
                    strengthBar.style.width = '0%';
                } else if (metCount === 1) {
                    strengthBar.classList.add('strength-weak');
                } else if (metCount === 2) {
                    strengthBar.classList.add('strength-fair');
                } else if (metCount === 3) {
                    strengthBar.classList.add('strength-good');
                } else if (metCount === 4) {
                    strengthBar.classList.add('strength-strong');
                }
            }
            
            // Enhanced form validation
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                let isValid = true;
                
                // Check all required fields
                const requiredFields = form.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!field.checkValidity()) {
                        isValid = false;
                    }
                });
                
                // Custom email validation
                const email = document.getElementById('email');
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email.value)) {
                    email.setCustomValidity('Please enter a valid email address');
                    isValid = false;
                } else {
                    email.setCustomValidity('');
                }
                
                // Custom phone validation (if provided)
                const phone = document.getElementById('phone');
                if (phone.value && phone.value.trim() !== '') {
                    const phoneRegex = /^[\+]?[1-9][\d]{3,14}$/;
                    const cleanPhone = phone.value.replace(/[\s\-\(\)]/g, '');
                    if (!phoneRegex.test(cleanPhone)) {
                        phone.setCustomValidity('Please enter a valid phone number');
                        isValid = false;
                    } else {
                        phone.setCustomValidity('');
                    }
                }
                
                // Password strength validation
                const password = passwordInput.value;
                if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                    passwordInput.setCustomValidity('Password must meet all requirements');
                    isValid = false;
                } else {
                    passwordInput.setCustomValidity('');
                }
                
                form.classList.add('was-validated');
                
                if (isValid) {
                    // Show loading state
                    submitBtn.classList.add('btn-loading');
                    submitBtn.disabled = true;
                    
                    // Add a small delay to show the loading animation
                    setTimeout(() => {
                        form.submit();
                    }, 500);
                } else {
                    // Scroll to first invalid field
                    const firstInvalid = form.querySelector(':invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstInvalid.focus();
                    }
                }
            });
            
            // Real-time email validation
            document.getElementById('email').addEventListener('blur', function() {
                const email = this.value;
                if (email) {
                    // Here you could add AJAX call to check if email already exists
                    // For now, just validate format
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(email)) {
                        this.setCustomValidity('Please enter a valid email address');
                    } else {
                        this.setCustomValidity('');
                    }
                }
            });
            
            // Auto-resize textarea
            document.getElementById('business_address').addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Phone number formatting
            document.getElementById('phone').addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length >= 6) {
                    if (value.length <= 10) {
                        value = value.replace(/(\d{3})(\d{3})(\d{0,4})/, '($1) $2-$3');
                    } else {
                        value = value.replace(/(\d{1})(\d{3})(\d{3})(\d{0,4})/, '+$1 ($2) $3-$4');
                    }
                }
                this.value = value;
            });
        });

        // Success message auto-hide
        <?php if ($success): ?>
        setTimeout(function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.opacity = '0';
                successAlert.style.transform = 'translateY(-20px)';
                setTimeout(() => successAlert.remove(), 300);
            }
        }, 8000);
        <?php endif; ?>

        // Enhanced error handling
        document.addEventListener('invalid', function(e) {
            e.target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, true);

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>