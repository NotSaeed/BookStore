<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$email = filter_input(INPUT_GET, 'email', FILTER_SANITIZE_EMAIL);
$valid = false;
$error = '';
$success = '';

// Enhanced token validation
if ($token && $email) {
    try {
        // Check if the token is valid and not expired (using seller_users table)
        $stmt = $conn->prepare("SELECT seller_id, seller_name FROM seller_users WHERE seller_email = ? AND reset_token = ? AND reset_token_expiry > NOW()");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $email, $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $reset_data = $result->fetch_assoc();
        $stmt->close();
        
        if ($reset_data) {
            $valid = true;
        } else {
            $error = 'Invalid or expired reset link. Please request a new password reset.';
        }
    } catch (Exception $e) {
        $error = 'System error. Please try again later.';
        error_log("Reset password validation error: " . $e->getMessage());
    }
} else {
    $error = 'Invalid reset link format.';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Enhanced validation
    if (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match. Please try again.';
    } else {
        try {
            // Begin transaction for data integrity
            $conn->begin_transaction();
            
            // Verify token again for security
            $stmt = $conn->prepare("SELECT seller_id, seller_name FROM seller_users WHERE seller_email = ? AND reset_token = ? AND reset_token_expiry > NOW()");
            if (!$stmt) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $stmt->bind_param("ss", $email, $token);
            $stmt->execute();
            $result = $stmt->get_result();
            $reset_data = $result->fetch_assoc();
            $stmt->close();
            
            if ($reset_data) {
                // Hash password securely with bcrypt
                $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Update the password and clear reset token
                $update_stmt = $conn->prepare("UPDATE seller_users SET seller_password = ?, reset_token = NULL, reset_token_expiry = NULL, password_updated_at = NOW() WHERE seller_email = ?");
                if (!$update_stmt) {
                    throw new Exception("Database error: " . $conn->error);
                }
                
                $update_stmt->bind_param("ss", $hashed_password, $email);
                  if ($update_stmt->execute()) {
                    // Log the password reset action
                    $action = "Password reset completed successfully from " . $_SERVER['REMOTE_ADDR'];
                    $log_stmt = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                    if ($log_stmt) {
                        $log_stmt->bind_param("is", $reset_data['seller_id'], $action);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                    
                    // Auto-login the user after successful password reset
                    $_SESSION['seller_id'] = $reset_data['seller_id'];
                    $_SESSION['seller_name'] = $reset_data['seller_name'];
                    $_SESSION['seller_email'] = $email;
                    $_SESSION['login_time'] = time();
                    $_SESSION['logged_in'] = true;
                    
                    // Log the auto-login action
                    $login_action = "Auto-login after password reset via token from " . $_SERVER['REMOTE_ADDR'];
                    $login_log = $conn->prepare("INSERT INTO seller_activity_log (seller_id, action, created_at) VALUES (?, ?, NOW())");
                    if ($login_log) {
                        $login_log->bind_param("is", $reset_data['seller_id'], $login_action);
                        $login_log->execute();
                        $login_log->close();
                    }
                    
                    // Commit transaction
                    $conn->commit();
                    $update_stmt->close();
                    
                    // Set success message and prepare for redirect to dashboard
                    $success = "🎉 Password reset successful! You are now logged in with your new password. Redirecting to dashboard...";
                    
                    // JavaScript redirect to dashboard after showing success message
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'seller_dashboard.php?status=password_reset_success';
                        }, 3000);
                    </script>";
                } else {
                    throw new Exception("Failed to update password: " . $update_stmt->error);
                }
            } else {
                throw new Exception("Invalid or expired reset token.");
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = $e->getMessage();
            error_log("Password reset error for email: $email - " . $e->getMessage());
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="key-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" x="2" y="2" width="16" height="16"/><circle fill="rgba(255,255,255,0.03)" cx="10" cy="10" r="3"/></pattern></defs><rect width="100" height="100" fill="url(%23key-pattern)"/></svg>') repeat;
            opacity: 0.4;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-10px) scale(1.02); }
        }
        
        .reset-container {
            width: 100%;
            max-width: 480px;
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
            animation: keyPulse 2s ease-in-out infinite;
        }
        
        @keyframes keyPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.05) rotate(2deg); filter: brightness(1.1); }
        }
        
        .card-header h2 {
            font-weight: 800;
            margin-bottom: 0.5rem;
            font-size: 2rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .card-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 1rem;
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
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: #667eea;
            font-size: 1.2rem;
        }
        
        .form-floating {
            margin-bottom: 1.5rem;
            position: relative;
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
            padding-right: 3rem;
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
        
        .alert-warning {
            background: linear-gradient(135deg, #ffd43b 0%, #ffc107 100%);
            color: #333;
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
        
        .invalid-token-card {
            text-align: center;
            padding: 2rem;
        }
        
        .invalid-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1.5rem;
        }
        
        .btn-outline-gradient {
            background: transparent;
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: #667eea;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline-gradient:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
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
    </style>
</head>
<body>
    <a href="seller_login.php" class="back-btn animate-on-scroll">
        <i class="bi bi-arrow-left me-2"></i>Back to Login
    </a>
    
    <div class="reset-container">
        <div class="reset-card animate-on-scroll">
            <?php if ($valid): ?>
                <div class="card-header">
                    <i class="bi bi-key-fill reset-icon"></i>
                    <h2>Create New Password</h2>
                    <p>Enter your new secure password</p>
                </div>
                
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <?= htmlspecialchars($error) ?>
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
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                        
                        <!-- New Password Section -->
                        <div class="form-section">
                            <h6 class="section-title">
                                <i class="bi bi-lock"></i>New Password
                            </h6>
                            
                            <div class="form-floating position-relative">
                                <input type="password" name="password" id="password" class="form-control" 
                                       placeholder="New Password" required minlength="8">
                                <label for="password"><i class="bi bi-lock me-2"></i>New Password *</label>
                                <button type="button" class="password-toggle" id="togglePassword">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
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
                        
                        <button class="btn-gradient py-3 mb-3" type="submit" id="submitBtn">
                            <i class="bi bi-check-circle me-2"></i>Reset Password Securely
                        </button>
                        
                        <div class="login-link">
                            <p class="text-muted mb-0">
                                Remember your password? 
                                <a href="seller_login.php">
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
                            <span>Use a unique password for your account</span>
                        </div>
                        <div class="security-tip">
                            <i class="bi bi-eye-slash"></i>
                            <span>Never share your password with anyone</span>
                        </div>
                        <div class="security-tip">
                            <i class="bi bi-arrow-clockwise"></i>
                            <span>Consider using a password manager</span>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-header">
                    <i class="bi bi-exclamation-triangle invalid-icon"></i>
                    <h2>Invalid Reset Link</h2>
                    <p>The password reset link is invalid or expired</p>
                </div>
                
                <div class="card-body invalid-token-card">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-circle"></i> 
                        <?= !empty($error) ? htmlspecialchars($error) : 'Invalid or missing reset token.' ?>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="seller_forgot_password.php" class="btn-outline-gradient me-3">
                            <i class="bi bi-arrow-clockwise me-2"></i>Request New Reset Link
                        </a>
                        <a href="seller_login.php" class="btn-outline-gradient">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Back to Login
                        </a>
                    </div>
                </div>
            <?php endif; ?>
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
            if (!form) return; // Exit if form doesn't exist (invalid token case)
            
            const submitBtn = document.getElementById('submitBtn');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');

            // Password visibility toggles
            document.getElementById('togglePassword').addEventListener('click', function() {
                togglePasswordVisibility('password', 'eyeIcon');
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
                
                // Password validation
                const password = passwordInput.value;
                if (!password) {
                    passwordInput.setCustomValidity('Please enter a new password');
                    isValid = false;
                } else if (password.length < 8) {
                    passwordInput.setCustomValidity('Password must be at least 8 characters long');
                    isValid = false;
                } else if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                    passwordInput.setCustomValidity('Password must meet all requirements');
                    isValid = false;
                } else {
                    passwordInput.setCustomValidity('');
                }
                
                // Confirm password validation
                const confirmPassword = confirmPasswordInput.value;
                if (!confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Please confirm your password');
                    isValid = false;
                } else if (password !== confirmPassword) {
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

            // Password match validation
            function validatePasswords() {
                const password = passwordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword && password !== confirmPassword) {
                    confirmPasswordInput.setCustomValidity('Passwords do not match');
                    confirmPasswordInput.classList.add('is-invalid');
                    confirmPasswordInput.classList.remove('is-valid');
                } else if (confirmPassword && password === confirmPassword) {
                    confirmPasswordInput.setCustomValidity('');
                    confirmPasswordInput.classList.remove('is-invalid');
                    confirmPasswordInput.classList.add('is-valid');
                } else {
                    confirmPasswordInput.classList.remove('is-valid', 'is-invalid');
                }
            }

            passwordInput.addEventListener('input', validatePasswords);
            confirmPasswordInput.addEventListener('input', validatePasswords);

            // Auto-hide success alerts
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transform = 'translateY(-20px)';
                    setTimeout(() => successAlert.remove(), 300);
                }, 8000);
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Show success loading message if redirecting
        <?php if ($success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const submitBtn = document.getElementById('submitBtn');
            if (submitBtn) {
                submitBtn.textContent = 'Redirecting to login...';
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>