<?php


session_start();
require_once __DIR__ . '/includes/seller_db.php';

$alert = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];

    // Enhanced validation
    if (empty($email)) {
        $alert = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $alert = "Please enter a valid email address.";
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $alert = "Please fill in all password fields.";
    } elseif ($newPassword !== $confirmPassword) {
        $alert = "Passwords do not match. Please try again.";
    } elseif (strlen($newPassword) < 8) {
        $alert = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $newPassword)) {
        $alert = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $newPassword)) {
        $alert = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $newPassword)) {
        $alert = "Password must contain at least one number.";
    } else {
        $stmt = $conn->prepare("SELECT seller_id, seller_name FROM seller_users WHERE seller_email = ?");
        if (!$stmt) {
            $alert = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($seller_id, $seller_name);
                $stmt->fetch();
                $stmt->close();

                // Hash password securely with bcrypt
                $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
                $update = $conn->prepare("UPDATE seller_users SET seller_password = ?, password_reset_date = NOW() WHERE seller_id = ?");
                if (!$update) {
                    $alert = "Database error: " . $conn->error;
                } else {
                    $update->bind_param("si", $hashed, $seller_id);                    if ($update->execute()) {
                        // Log the password reset action
                        $action = "Password reset successfully from " . $_SERVER['REMOTE_ADDR'];
                        $log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                        if ($log) {
                            $log->bind_param("is", $seller_id, $action);
                            $log->execute();
                            $log->close();
                        }

                        // Auto-login the user after successful password reset
                        $_SESSION['seller_id'] = $seller_id;
                        $_SESSION['seller_name'] = $seller_name;
                        $_SESSION['seller_email'] = $email;
                        $_SESSION['login_time'] = time();
                        $_SESSION['logged_in'] = true;
                        
                        // Log the auto-login action
                        $login_action = "Auto-login after password reset from " . $_SERVER['REMOTE_ADDR'];
                        $login_log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                        if ($login_log) {
                            $login_log->bind_param("is", $seller_id, $login_action);
                            $login_log->execute();
                            $login_log->close();
                        }

                        $success = "🎉 Password reset successful! You are now logged in with your new password. Redirecting to dashboard...";
                        
                        // JavaScript redirect to dashboard after success
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = 'seller_dashboard.php?status=password_reset_success';
                            }, 3000);
                        </script>";
                    } else {
                        $alert = "Failed to update password: " . $update->error;
                    }
                    $update->close();
                }
            } else {
                $alert = "No account found with this email address. Please check your email or register for a new account.";
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password | BookStore Seller Hub</title>
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="security" x="0" y="0" width="25" height="25" patternUnits="userSpaceOnUse"><rect fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" x="2" y="2" width="21" height="21"/><circle fill="rgba(255,255,255,0.03)" cx="12.5" cy="12.5" r="8"/></pattern></defs><rect width="100" height="100" fill="url(%23security)"/></svg>') repeat;
            opacity: 0.4;
            animation: float 25s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-15px) scale(1.02); }
        }
        
        .reset-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }
        
        .reset-card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .reset-card:hover {
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
        
        .reset-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: securityPulse 2s ease-in-out infinite;
        }
        
        @keyframes securityPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); filter: brightness(1.1); }
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
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: #667eea;
            font-size: 1.3rem;
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
        
        .security-tips {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .security-tip {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            color: #2d3748;
            font-weight: 500;
        }
        
        .security-tip i {
            color: #667eea;
            font-size: 1.2rem;
            margin-right: 1rem;
            width: 20px;
        }
        
        .security-tip:last-child {
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
                font-size: 0.8rem;
            }
            
            .reset-icon {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 1rem 0;
            }
            
            .reset-container {
                padding: 0 1rem;
            }
            
            .security-tips {
                padding: 1.5rem;
            }
        }
        
        /* Focus styles for accessibility */
        .form-control:focus,
        .btn:focus {
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
    <a href="login.php" class="back-btn animate-on-scroll">
        <i class="bi bi-arrow-left me-2"></i>Back to Login
    </a>
    
    <div class="reset-container">
        <div class="reset-card animate-on-scroll">
            <div class="card-header">
                <i class="bi bi-shield-lock reset-icon"></i>
                <h2>Reset Password</h2>
                <p>Secure your account with a new password</p>
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

                <form method="POST" action="" class="needs-validation" novalidate id="resetForm">
                    
                    <!-- Account Verification Section -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="bi bi-person-check"></i>Account Verification
                        </h5>
                        
                        <div class="form-floating">
                            <input type="email" name="email" id="email" class="form-control" 
                                   placeholder="Email Address" required>
                            <label for="email"><i class="bi bi-envelope me-2"></i>Email Address *</label>
                            <div class="invalid-feedback">Please enter a valid email address</div>
                            <div class="form-text">Enter the email associated with your seller account</div>
                        </div>
                    </div>

                    <!-- New Password Section -->
                    <div class="form-section">
                        <h5 class="section-title">
                            <i class="bi bi-key"></i>New Password
                        </h5>
                        
                        <div class="form-floating position-relative">
                            <input type="password" name="new_password" id="new_password" class="form-control" 
                                   placeholder="New Password" required minlength="8">
                            <label for="new_password"><i class="bi bi-lock me-2"></i>New Password *</label>
                            <button type="button" class="password-toggle" id="toggleNewPassword">
                                <i class="bi bi-eye" id="eyeIconNew"></i>
                            </button>
                            <div class="invalid-feedback">Password must meet all requirements</div>
                            
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
                        
                        <div class="form-floating position-relative">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                                   placeholder="Confirm Password" required>
                            <label for="confirm_password"><i class="bi bi-lock-fill me-2"></i>Confirm Password *</label>
                            <button type="button" class="password-toggle" id="toggleConfirmPassword">
                                <i class="bi bi-eye" id="eyeIconConfirm"></i>
                            </button>
                            <div class="invalid-feedback">Passwords do not match</div>
                        </div>
                    </div>
                    
                    <button class="btn btn-gradient py-3 mb-3" type="submit" id="submitBtn">
                        <i class="bi bi-shield-check me-2"></i>Reset Password Securely
                    </button>
                    
                    <div class="security-badge">
                        <i class="bi bi-shield-lock-fill"></i>
                        Password encrypted with industry-standard security
                    </div>
                    
                    <div class="login-link">
                        <p class="text-muted mb-0">
                            Remember your password? 
                            <a href="login.php">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Back to Login
                            </a>
                        </p>
                    </div>
                </form>

                <!-- Security Tips Section -->
                <div class="security-tips">
                    <h6 class="fw-bold mb-3 text-center">Password Security Tips</h6>
                    <div class="security-tip">
                        <i class="bi bi-shield-check"></i>
                        <span>Use a unique password for your seller account</span>
                    </div>
                    <div class="security-tip">
                        <i class="bi bi-eye-slash"></i>
                        <span>Never share your password with anyone</span>
                    </div>
                    <div class="security-tip">
                        <i class="bi bi-arrow-clockwise"></i>
                        <span>Consider using a password manager</span>
                    </div>
                    <div class="security-tip">
                        <i class="bi bi-clock-history"></i>
                        <span>Update your password regularly for security</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <div class="loading-text">Resetting password...</div>
            <div class="loading-subtext">Please wait while we securely update your credentials</div>
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
            const form = document.getElementById('resetForm');
            const submitBtn = document.getElementById('submitBtn');
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const emailInput = document.getElementById('email');
            const loadingOverlay = document.getElementById('loadingOverlay');

            // Password visibility toggles
            document.getElementById('toggleNewPassword').addEventListener('click', function() {
                togglePasswordVisibility('new_password', 'eyeIconNew');
            });

            document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
                togglePasswordVisibility('confirm_password', 'eyeIconConfirm');
            });

            function togglePasswordVisibility(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                if (type === 'text') {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }

            // Password strength checker
            newPasswordInput.addEventListener('input', function() {
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
                const newPassword = newPasswordInput.value;
                if (!newPassword) {
                    newPasswordInput.setCustomValidity('Please enter a new password');
                    isValid = false;
                } else if (newPassword.length < 8) {
                    newPasswordInput.setCustomValidity('Password must be at least 8 characters long');
                    isValid = false;
                } else if (!/[A-Z]/.test(newPassword) || !/[a-z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
                    newPasswordInput.setCustomValidity('Password must meet all requirements');
                    isValid = false;
                } else {
                    newPasswordInput.setCustomValidity('');
                }
                
                // Confirm password validation
                const confirmPassword = confirmPasswordInput.value;
                if (!confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Please confirm your password');
                    isValid = false;
                } else if (newPassword !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    isValid = false;
                } else {
                    confirmPasswordInput.setCustomValidity('');
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

            // Password match validation
            function validatePasswords() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword && newPassword !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    confirmPasswordInput.classList.add('is-invalid');
                    confirmPasswordInput.classList.remove('is-valid');
                } else if (confirmPassword && newPassword === confirmPassword) {
                    confirmPasswordInput.setCustomValidity('');
                    confirmPasswordInput.classList.remove('is-invalid');
                    confirmPasswordInput.classList.add('is-valid');
                } else {
                    confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
                }
            }

            newPasswordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);

            // Auto-hide alerts after some time
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    setTimeout(() => {
                        alert.style.opacity = '0';
                        alert.style.transform = 'translateY(-20px)';
                        setTimeout(() => alert.remove(), 300);
                    }, 8000);
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
            loadingText.textContent = 'Password reset successful!';
            loadingSubtext.textContent = 'Redirecting to login page...';
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