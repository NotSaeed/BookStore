<?php

session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Redirect if already logged in
if (isset($_SESSION['seller_id'])) {
    header("Location: seller_dashboard.php");
    exit();
}

$status = $_GET['status'] ?? '';
$error = $_GET['error'] ?? '';
$email = $_GET['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | BookStore Seller Hub</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Reset your BookStore seller account password securely">
    
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="mail-pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><rect fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="0.5" x="2" y="2" width="16" height="16"/><circle fill="rgba(255,255,255,0.03)" cx="10" cy="10" r="3"/></pattern></defs><rect width="100" height="100" fill="url(%23mail-pattern)"/></svg>') repeat;
            opacity: 0.4;
            animation: float 20s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-10px) scale(1.02); }
        }
        
        .forgot-container {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }
        
        .forgot-card {
            border: none;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .forgot-card:hover {
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
        
        .forgot-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: white;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            animation: mailPulse 2s ease-in-out infinite;
        }
        
        @keyframes mailPulse {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.05) rotate(-2deg); filter: brightness(1.1); }
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
        
        .form-floating {
            margin-bottom: 2rem;
            position: relative;
        }
        
        .form-floating .form-control {
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem 1rem 0.5rem 3.5rem;
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
            padding: 1rem 1rem 0.5rem 3.5rem;
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
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #667eea;
            font-size: 1.2rem;
            z-index: 3;
            transition: all 0.3s ease;
        }
        
        .form-floating .form-control:focus ~ .input-icon {
            color: #667eea;
            transform: translateY(-50%) scale(1.1);
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
            animation: slideInDown 0.5s ease;
        }
        
        @keyframes slideInDown {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66 0%, #40c057 100%);
            color: white;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #74c0fc 0%, #339af0 100%);
            color: white;
        }
        
        .alert i {
            font-size: 1.2em;
            margin-right: 0.75rem;
        }
        
        .info-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 20px;
            padding: 2rem;
            margin-top: 2rem;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .info-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        
        .info-title i {
            margin-right: 0.75rem;
            color: #667eea;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: #2d3748;
            font-weight: 500;
        }
        
        .info-item i {
            color: #667eea;
            font-size: 1rem;
            margin-right: 1rem;
            width: 16px;
        }
        
        .info-item:last-child {
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
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .login-link a:hover {
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .login-link a i {
            margin-right: 0.5rem;
        }
        
        /* Email validation feedback */
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .invalid-feedback,
        .valid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .invalid-feedback {
            color: #dc3545;
        }
        
        .valid-feedback {
            color: #28a745;
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
            
            .forgot-icon {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 576px) {
            body {
                padding: 1rem 0;
            }
            
            .forgot-container {
                padding: 0 1rem;
            }
        }
        
        /* Animation classes */
        .animate-on-scroll {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease;
        }
        
        .animate-on-scroll.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        /* Progress indicator */
        .progress-steps {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2rem;
            padding: 0 1rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            position: relative;
        }
        
        .step.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step.inactive {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .step-connector {
            flex: 1;
            height: 2px;
            background: #e9ecef;
            margin: 0 0.5rem;
        }
        
        .step-connector.active {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <a href="seller_login.php" class="back-btn animate-on-scroll">
        <i class="bi bi-arrow-left me-2"></i>Back to Login
    </a>
    
    <div class="forgot-container">
        <div class="forgot-card animate-on-scroll">
            <div class="card-header">
                <i class="bi bi-envelope-exclamation forgot-icon"></i>
                <h2>Forgot Password?</h2>
                <p>No worries! We'll send you reset instructions</p>
            </div>
            
            <div class="card-body">
                <!-- Progress Steps -->
                <div class="progress-steps">
                    <div class="step <?= $status === 'sent' ? 'completed' : 'active' ?>">1</div>
                    <div class="step-connector <?= $status === 'sent' ? 'active' : '' ?>"></div>
                    <div class="step <?= $status === 'sent' ? 'active' : 'inactive' ?>">2</div>
                    <div class="step-connector"></div>
                    <div class="step inactive">3</div>
                </div>
                
                <?php if ($status === 'sent'): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-envelope-check-fill"></i>
                        <strong>Email Sent Successfully!</strong><br>
                        A password reset link has been sent to your email address. Please check your inbox and spam folder.
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                    </div>
                    
                    <div class="info-section">
                        <div class="info-title">
                            <i class="bi bi-info-circle"></i>What's Next?
                        </div>
                        <div class="info-item">
                            <i class="bi bi-1-circle-fill"></i>
                            <span>Check your email inbox (and spam folder)</span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-2-circle-fill"></i>
                            <span>Click the reset link in the email</span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-3-circle-fill"></i>
                            <span>Create your new secure password</span>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <p class="text-muted mb-3">Didn't receive the email?</p>
                        <a href="forgot_password.php" class="btn-gradient">
                            <i class="bi bi-arrow-clockwise me-2"></i>Try Again
                        </a>
                    </div>
                    
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form action="send_reset_email.php" method="POST" class="needs-validation" novalidate id="forgotForm">
                        <div class="form-floating">
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   class="form-control" 
                                   placeholder="Enter your email address"
                                   value="<?= htmlspecialchars($email) ?>"
                                   required
                                   autocomplete="email">
                            <label for="email">Email Address *</label>
                            <i class="bi bi-envelope input-icon"></i>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                            <div class="valid-feedback">
                                Email format looks good!
                            </div>
                        </div>
                        
                        <button type="submit" class="btn-gradient" id="submitBtn">
                            <i class="bi bi-send me-2"></i>Send Reset Link
                        </button>
                    </form>
                    
                    <!-- Information Section -->
                    <div class="info-section">
                        <div class="info-title">
                            <i class="bi bi-shield-check"></i>Secure Password Reset
                        </div>
                        <div class="info-item">
                            <i class="bi bi-clock"></i>
                            <span>Reset links expire after 1 hour for security</span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-shield-lock"></i>
                            <span>Your account remains secure during this process</span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-envelope-paper"></i>
                            <span>Only you can access the reset link sent to your email</span>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="login-link">
                    <p class="text-muted mb-3">Remember your password?</p>
                    <a href="seller_login.php">
                        <i class="bi bi-box-arrow-in-right"></i>Back to Login
                    </a>
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

        // Enhanced form validation and functionality
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgotForm');
            if (!form) return; // Exit if form doesn't exist (success case)
            
            const submitBtn = document.getElementById('submitBtn');
            const emailInput = document.getElementById('email');

            // Real-time email validation
            emailInput.addEventListener('input', function() {
                const email = this.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                if (!email) {
                    this.classList.remove('is-valid', 'is-invalid');
                } else if (emailRegex.test(email)) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                    this.setCustomValidity('');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                    this.setCustomValidity('Please enter a valid email address');
                }
            });

            // Enhanced form submission
            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();
                
                const email = emailInput.value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                
                // Validate email
                if (!email) {
                    emailInput.setCustomValidity('Please enter your email address');
                    emailInput.classList.add('is-invalid');
                    emailInput.focus();
                    return;
                } else if (!emailRegex.test(email)) {
                    emailInput.setCustomValidity('Please enter a valid email address');
                    emailInput.classList.add('is-invalid');
                    emailInput.focus();
                    return;
                } else {
                    emailInput.setCustomValidity('');
                    emailInput.classList.remove('is-invalid');
                    emailInput.classList.add('is-valid');
                }
                
                form.classList.add('was-validated');
                
                // Show loading state
                submitBtn.classList.add('btn-loading');
                submitBtn.disabled = true;
                
                // Add a small delay to show the loading animation
                setTimeout(() => {
                    form.submit();
                }, 800);
            });

            // Auto-hide alerts after 8 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 300);
                }, 8000);
            });

            // Focus on email input when page loads
            if (emailInput && !emailInput.value) {
                setTimeout(() => {
                    emailInput.focus();
                }, 500);
            }
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add some visual feedback for user interaction
        document.addEventListener('mousemove', function(e) {
            const card = document.querySelector('.forgot-card');
            if (!card) return;
            
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            if (x >= 0 && x <= rect.width && y >= 0 && y <= rect.height) {
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                const rotateX = (y - centerY) / 20;
                const rotateY = (centerX - x) / 20;
                
                card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            } else {
                card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
            }
        });
    </script>
</body>
</html>