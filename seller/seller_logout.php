<?php

/**
 * BookStore Seller Hub - Secure Logout System
 * Enhanced logout with security logging, session cleanup, and professional UX
 * Version: 2.0.0
 */

// Start session early for access to session data
session_start();

// Include database connection for logging
require_once __DIR__ . '/includes/seller_db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Function to log logout activity
function logLogoutActivity($conn, $seller_id, $seller_name, $logout_type = 'manual') {
    try {
        // Create table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS seller_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            action VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $conn->prepare("
            INSERT INTO seller_activity_log 
            (seller_id, action, ip_address, user_agent, details, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if ($stmt) {
            $action = "User logout - {$logout_type}";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $details = json_encode([
                'logout_type' => $logout_type,
                'session_duration' => isset($_SESSION['login_time']) ? (time() - $_SESSION['login_time']) : 0,
                'logout_time' => date('Y-m-d H:i:s'),
                'seller_name' => $seller_name,
                'referrer' => $_SERVER['HTTP_REFERER'] ?? 'Direct'
            ]);
            
            $stmt->bind_param("issss", $seller_id, $action, $ip, $user_agent, $details);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Failed to log logout activity: " . $e->getMessage());
    }
}

// Function to clean up seller-specific data
function cleanupSellerData($seller_id) {
    try {
        // Clear any temporary files or cache related to this seller
        $temp_dir = __DIR__ . '/temp/seller_' . $seller_id;
        if (is_dir($temp_dir)) {
            $files = glob("$temp_dir/*");
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            @rmdir($temp_dir);
        }
        
        // Clear any rate limiting data for this seller
        $rate_limit_keys = [
            "rate_limit_login_{$seller_id}",
            "rate_limit_flags_{$seller_id}",
            "rate_limit_upload_{$seller_id}"
        ];
        
        foreach ($rate_limit_keys as $key) {
            if (isset($_SESSION[$key])) {
                unset($_SESSION[$key]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error during seller data cleanup: " . $e->getMessage());
    }
}

// Function to invalidate remember me tokens
function invalidateRememberTokens($conn, $seller_id) {
    try {
        // Check if table exists first
        $result = $conn->query("SHOW TABLES LIKE 'seller_remember_tokens'");
        if ($result && $result->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE seller_remember_tokens SET is_active = 0 WHERE seller_id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $seller_id);
                $stmt->execute();
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log("Failed to invalidate remember tokens: " . $e->getMessage());
    }
}

// Function to send JSON response for AJAX requests
function sendJsonResponse($success = true, $message = '', $redirect = '', $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'redirect' => $redirect,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit();
}

// Function to show logout confirmation page (session still active)
function showLogoutConfirmation($seller_name, $session_id, $login_time, $logout_type) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Confirm Logout - BookStore Seller Hub</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
        <link href="seller_style.css" rel="stylesheet">
        
        <style>
            .logout-container {
                background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Inter', system-ui, sans-serif;
            }
            
            .logout-card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                border-radius: 2rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                border: 1px solid rgba(255, 255, 255, 0.2);
                max-width: 500px;
                width: 100%;
                margin: 2rem;
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 2rem;
                margin: 0 auto 2rem;
                animation: pulse 2s infinite;
            }
            
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.05); }
                100% { transform: scale(1); }
            }
            
            .countdown-circle {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: bold;
                font-size: 1.5rem;
                margin: 0 auto;
                transition: all 0.3s ease;
            }
            
            .security-tips {
                background: #f9fafb;
                border-radius: 1rem;
                padding: 1.5rem;
                margin-top: 2rem;
            }
            
            .tip-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                margin-bottom: 1rem;
            }
            
            .tip-item:last-child {
                margin-bottom: 0;
            }
            
            .tip-icon {
                width: 24px;
                height: 24px;
                background: #667eea;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 0.75rem;
                flex-shrink: 0;
            }

            .btn {
                transition: all 0.3s ease;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
        </style>
    </head>
    <body class="logout-container">
        <div class="logout-card">
            <div class="card-body p-5 text-center">
                <div class="logout-icon">
                    <i class="bi bi-question-circle"></i>
                </div>
                
                <h2 class="card-title text-dark mb-3">Confirm Logout</h2>
                <p class="text-muted mb-4">
                    Are you sure you want to log out, <strong><?= htmlspecialchars($seller_name) ?></strong>? 
                    Your session will be securely terminated.
                </p>
                
                <div class="row text-center mb-4">
                    <div class="col-4">
                        <div class="text-primary">
                            <i class="bi bi-shield-check" style="font-size: 1.5rem;"></i>
                            <div class="small text-muted mt-1">Secure</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-success">
                            <i class="bi bi-lock" style="font-size: 1.5rem;"></i>
                            <div class="small text-muted mt-1">Protected</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-info">
                            <i class="bi bi-clock-history" style="font-size: 1.5rem;"></i>
                            <div class="small text-muted mt-1">Logged</div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <p class="small text-muted mb-2">Auto-logout in:</p>
                    <div class="countdown-circle" id="countdownCircle">5</div>
                </div>
                
                <div class="d-flex gap-2 justify-content-center mb-4">
                    <button type="button" class="btn btn-danger" id="confirmBtn" onclick="confirmLogout()">
                        <i class="bi bi-box-arrow-right"></i> Yes, Log Out
                    </button>
                    <button type="button" class="btn btn-outline-primary" id="cancelBtn" onclick="handleCancel(this)">
                        <i class="bi bi-x-circle"></i> Cancel
                    </button>
                </div>
                
                <div class="security-tips">
                    <h6 class="text-primary mb-3">
                        <i class="bi bi-shield-shaded"></i> Security Tips
                    </h6>
                    
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="bi bi-lock"></i>
                        </div>
                        <div class="text-start">
                            <small><strong>Always log out</strong> when using shared computers</small>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="bi bi-eye-slash"></i>
                        </div>
                        <div class="text-start">
                            <small><strong>Use private browsing</strong> for additional security</small>
                        </div>
                    </div>
                    
                    <div class="tip-item">
                        <div class="tip-icon">
                            <i class="bi bi-key"></i>
                        </div>
                        <div class="text-start">
                            <small><strong>Keep your password secure</strong> and change it regularly</small>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 pt-3 border-top">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Session ID: <?= substr($session_id, 0, 8) ?>... | 
                        Duration: <?= gmdate("H:i:s", time() - $login_time) ?>
                    </small>
                </div>
            </div>
        </div>
        
        <script>
            let countdown = 5;
            let countdownTimer;
            const countdownElement = document.getElementById('countdownCircle');
            
            function startCountdown() {
                countdownTimer = setInterval(() => {
                    countdown--;
                    countdownElement.textContent = countdown;
                    
                    if (countdown <= 0) {
                        clearInterval(countdownTimer);
                        countdownElement.innerHTML = '<i class="bi bi-arrow-right"></i>';
                        // Auto-confirm logout after timeout
                        setTimeout(() => {
                            confirmLogout();
                        }, 500);
                    }
                }, 1000);
            }

            // Start countdown
            startCountdown();
            
            // Confirm logout function
            function confirmLogout() {
                clearInterval(countdownTimer);
                window.location.href = 'seller_logout.php?confirm=true';
            }
              // Handle Cancel click
            function handleCancel(button) {
                // Stop the automatic redirect
                clearInterval(countdownTimer);
                
                // Show loading state briefly
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelling...';
                button.disabled = true;
                
                // Enhanced logic to determine where to go back
                const referrer = document.referrer;
                let redirectUrl = 'seller_dashboard.php'; // Default fallback
                
                // Check if referrer contains a valid seller page
                if (referrer && referrer.includes('/seller/')) {
                    const sellerPages = [
                        'seller_dashboard.php',
                        'seller_manage_books.php', 
                        'seller_add_book.php',
                        'seller_edit_book.php',
                        'seller_settings.php',
                        'seller_view_book.php',
                        'book_preview.php'
                    ];
                    
                    // Extract the page name from referrer
                    const refererFileName = referrer.split('/').pop().split('?')[0];
                    
                    // If it's a valid seller page, use it
                    if (sellerPages.includes(refererFileName)) {
                        redirectUrl = referrer;
                    }
                }
                
                // Immediately redirect back to the previous page
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 300); // Very short delay just to show the cancelling message
            }
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    confirmLogout();
                } else if (e.key === 'Escape' || e.key === 'c' || e.key === 'C') {
                    e.preventDefault();
                    document.getElementById('cancelBtn').click();
                }
            });
            
            // Focus management
            document.getElementById('confirmBtn').focus();
            
            // Prevent accidental navigation
            window.addEventListener('beforeunload', function(e) {
                // Only show warning if user is trying to navigate away manually
                if (countdown > 0) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        </script>
    </body>
    </html>
    <?php
}

// Check if this is a confirmed logout
$confirmed = isset($_GET['confirm']) && $_GET['confirm'] === 'true';

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Store session data for logging before destroying session
$seller_id = $_SESSION['seller_id'] ?? null;
$seller_name = $_SESSION['seller_name'] ?? 'Unknown';
$login_time = $_SESSION['login_time'] ?? time();
$session_id = session_id();

// Determine logout type
$logout_type = 'manual';
if (isset($_GET['auto'])) {
    $logout_type = 'auto_timeout';
} elseif (isset($_GET['security'])) {
    $logout_type = 'security';
} elseif (isset($_GET['forced'])) {
    $logout_type = 'forced';
}

// IMPORTANT: Only proceed with logout if user was actually logged in
if (!$seller_id) {
    // If no session exists, redirect to login
    header("Location: login.php?msg=no_session");
    exit();
}

// If this is not a confirmed logout and not a security/forced logout, show confirmation page
if (!$confirmed && !in_array($logout_type, ['security', 'forced', 'auto_timeout'])) {
    // Show logout confirmation page without actually logging out yet
    showLogoutConfirmation($seller_name, $session_id, $login_time, $logout_type);
    exit();
}

// Perform actual logout activities only when confirmed
try {
    // Log the logout activity
    if (isset($conn)) {
        logLogoutActivity($conn, $seller_id, $seller_name, $logout_type);
        
        // Invalidate remember me tokens if they exist
        invalidateRememberTokens($conn, $seller_id);
        
        // Update last seen timestamp
        $stmt = $conn->prepare("UPDATE sellers SET last_seen = NOW() WHERE seller_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $seller_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Clean up seller-specific data
    cleanupSellerData($seller_id);
    
} catch (Exception $e) {
    error_log("Error during logout process: " . $e->getMessage());
}

// Comprehensive session cleanup
$_SESSION = array();

// Delete session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Clear any remember me cookies
$cookie_options = [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
];

if (isset($_COOKIE['seller_remember'])) {
    setcookie('seller_remember', '', $cookie_options);
}

// Clear any other seller-related cookies
$seller_cookies = ['seller_token', 'seller_preference', 'seller_theme'];
foreach ($seller_cookies as $cookie_name) {
    if (isset($_COOKIE[$cookie_name])) {
        setcookie($cookie_name, '', $cookie_options);
    }
}

// Destroy the session
session_destroy();

// Start a new session to prevent session fixation
session_start();
session_regenerate_id(true);

// Close database connection
if (isset($conn)) {
    $conn->close();
}

// Handle AJAX logout requests
if ($isAjax) {
    sendJsonResponse(true, 'Logged out successfully', 'login.php');
}

// Determine redirect URL based on logout type
$redirect_url = 'login.php';
$logout_message = '';

switch ($logout_type) {
    case 'auto_timeout':
        $logout_message = 'session_timeout';
        break;
    case 'security':
        $logout_message = 'security_logout';
        break;
    case 'forced':
        $logout_message = 'account_disabled';
        break;
    default:
        $logout_message = 'logout_success';
}

// Add logout message to redirect URL
if ($logout_message) {
    $redirect_url .= '?msg=' . urlencode($logout_message);
}

// Handle different logout scenarios with appropriate delays
switch ($logout_type) {
    case 'security':
    case 'forced':
        // Immediate redirect for security reasons
        header("Location: {$redirect_url}");
        exit();
        
    case 'auto_timeout':
        // Show timeout message briefly
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Session Timeout - BookStore Seller Hub</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
            <link href="seller_style.css" rel="stylesheet">
            <meta http-equiv="refresh" content="3;url=<?= htmlspecialchars($redirect_url) ?>">
        </head>
        <body class="d-flex align-items-center justify-content-center min-vh-100" style="background: linear-gradient(135deg, var(--warning-color) 0%, var(--danger-color) 100%);">
            <div class="card text-center" style="max-width: 400px; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-radius: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
                <div class="card-body p-5">
                    <div class="text-warning mb-3">
                        <i class="bi bi-clock-history" style="font-size: 3rem;"></i>
                    </div>
                    <h4 class="card-title text-dark">Session Timeout</h4>
                    <p class="card-text text-muted">Your session has expired due to inactivity. You will be redirected to the login page in <span id="countdown">3</span> seconds.</p>
                    <div class="mt-4">
                        <a href="<?= htmlspecialchars($redirect_url) ?>" class="btn btn-primary">
                            <i class="bi bi-box-arrow-in-right"></i> Login Again
                        </a>
                    </div>
                </div>
            </div>
            <script>
                let count = 3;
                const countdown = setInterval(() => {
                    count--;
                    document.getElementById('countdown').textContent = count;
                    if (count <= 0) {
                        clearInterval(countdown);
                        window.location.href = '<?= htmlspecialchars($redirect_url) ?>';
                    }
                }, 1000);
            </script>
        </body>
        </html>
        <?php
        exit();
        
    default:
        // Normal logout with professional goodbye page
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Logged Out - BookStore Seller Hub</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
            <link href="seller_style.css" rel="stylesheet">
            <meta http-equiv="refresh" content="5;url=<?= htmlspecialchars($redirect_url) ?>">
            
            <style>
                .logout-container {
                    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Inter', system-ui, sans-serif;
                }
                
                .logout-card {
                    background: rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(20px);
                    border-radius: 2rem;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                    border: 1px solid rgba(255, 255, 255, 0.2);
                    max-width: 500px;
                    width: 100%;
                    margin: 2rem;
                }
                
                .logout-icon {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 2rem;
                    margin: 0 auto 2rem;
                    animation: pulse 2s infinite;
                }
                
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                
                .countdown-circle {
                    width: 60px;
                    height: 60px;
                    border-radius: 50%;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    font-size: 1.5rem;
                    margin: 0 auto;
                    transition: all 0.3s ease;
                }
                
                .security-tips {
                    background: #f9fafb;
                    border-radius: 1rem;
                    padding: 1.5rem;
                    margin-top: 2rem;
                }
                
                .tip-item {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    margin-bottom: 1rem;
                }
                
                .tip-item:last-child {
                    margin-bottom: 0;
                }
                
                .tip-icon {
                    width: 24px;
                    height: 24px;
                    background: #667eea;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-size: 0.75rem;
                    flex-shrink: 0;
                }

                .btn {
                    transition: all 0.3s ease;
                }

                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
            </style>
        </head>
        <body class="logout-container">
            <div class="logout-card">
                <div class="card-body p-5 text-center">
                    <div class="logout-icon">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                    
                    <h2 class="card-title text-dark mb-3">Successfully Logged Out</h2>
                    <p class="text-muted mb-4">
                        Thank you for using BookStore Seller Hub, <strong><?= htmlspecialchars($seller_name) ?></strong>. 
                        Your session has been securely terminated.
                    </p>
                    
                    <div class="row text-center mb-4">
                        <div class="col-4">
                            <div class="text-primary">
                                <i class="bi bi-shield-check" style="font-size: 1.5rem;"></i>
                                <div class="small text-muted mt-1">Secure</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-success">
                                <i class="bi bi-lock" style="font-size: 1.5rem;"></i>
                                <div class="small text-muted mt-1">Protected</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-info">
                                <i class="bi bi-clock-history" style="font-size: 1.5rem;"></i>
                                <div class="small text-muted mt-1">Logged</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <p class="small text-muted mb-2">Redirecting in:</p>
                        <div class="countdown-circle" id="countdownCircle">5</div>
                    </div>                    <div class="d-flex gap-2 justify-content-center mb-4">
                        <a href="login.php" class="btn btn-primary" id="loginBtn">
                            <i class="bi bi-box-arrow-in-right"></i> Login Again
                        </a>
                        <button type="button" class="btn btn-outline-primary" id="cancelBtn" onclick="handleCancel(this)">
                            <i class="bi bi-x-circle"></i> Cancel
                        </button>
                    </div>
                    
                    <div class="security-tips">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-shield-shaded"></i> Security Tips
                        </h6>
                        
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="bi bi-lock"></i>
                            </div>
                            <div class="text-start">
                                <small><strong>Always log out</strong> when using shared computers</small>
                            </div>
                        </div>
                        
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="bi bi-eye-slash"></i>
                            </div>
                            <div class="text-start">
                                <small><strong>Use private browsing</strong> for additional security</small>
                            </div>
                        </div>
                        
                        <div class="tip-item">
                            <div class="tip-icon">
                                <i class="bi bi-key"></i>
                            </div>
                            <div class="text-start">
                                <small><strong>Keep your password secure</strong> and change it regularly</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Session ID: <?= substr($session_id, 0, 8) ?>... | 
                            Duration: <?= gmdate("H:i:s", time() - $login_time) ?>
                        </small>
                    </div>
                </div>
            </div>
            
            <script>
                let countdown = 5;
                let countdownTimer;
                const countdownElement = document.getElementById('countdownCircle');
                
                function startCountdown() {
                    countdownTimer = setInterval(() => {
                        countdown--;
                        countdownElement.textContent = countdown;
                        
                        if (countdown <= 0) {
                            clearInterval(countdownTimer);
                            countdownElement.innerHTML = '<i class="bi bi-arrow-right"></i>';
                            setTimeout(() => {
                                window.location.href = '<?= htmlspecialchars($redirect_url) ?>';
                            }, 500);
                        }
                    }, 1000);
                }

                // Start countdown
                startCountdown();                // Handle Cancel click
                function handleCancel(button) {
                    // Stop the automatic redirect
                    clearInterval(countdownTimer);
                    
                    // Show loading state
                    const originalContent = button.innerHTML;
                    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Cancelling...';
                    button.disabled = true;
                    
                    // Update countdown circle
                    countdownElement.innerHTML = '<i class="bi bi-x-circle"></i>';
                    countdownElement.style.background = 'linear-gradient(135deg, #dc3545 0%, #c82333 100%)';
                    
                    // Update the main message
                    document.querySelector('.card-title').textContent = 'Logout Cancelled';
                    document.querySelector('.card-text').innerHTML = 'You have cancelled the logout process. <strong>You are still logged in</strong> and can continue using the system.';
                    
                    // Hide the tips section
                    document.querySelector('.security-tips').style.display = 'none';
                    
                    // Immediately redirect back to the previous page (short delay for visual feedback)
                    setTimeout(() => {
                        // Enhanced logic to determine where to go back
                        const referrer = document.referrer;
                        let redirectUrl = 'seller_dashboard.php'; // Default fallback
                        
                        // Check if referrer contains a valid seller page
                        if (referrer && referrer.includes('/seller/')) {
                            const sellerPages = [
                                'seller_dashboard.php',
                                'seller_manage_books.php', 
                                'seller_add_book.php',
                                'seller_edit_book.php',
                                'seller_settings.php',
                                'seller_view_book.php',
                                'book_preview.php'
                            ];
                            
                            // Extract the page name from referrer
                            const refererFileName = referrer.split('/').pop().split('?')[0];
                            
                            // If it's a valid seller page, use it
                            if (sellerPages.includes(refererFileName)) {
                                redirectUrl = referrer;
                            }
                        }
                        
                        // Redirect back to the previous page
                        window.location.href = redirectUrl;
                    }, 500); // Very short delay just to show the cancelling message
                }                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        clearInterval(countdownTimer);
                        window.location.href = '<?= htmlspecialchars($redirect_url) ?>';
                    } else if (e.key === 'c' || e.key === 'C') {
                        e.preventDefault();
                        document.getElementById('cancelBtn').click();
                    } else if (e.key === 'l' || e.key === 'L') {
                        e.preventDefault();
                        document.getElementById('loginBtn').click();
                    }
                });
                
                // Focus management
                document.getElementById('loginBtn').focus();
                
                // Prevent accidental navigation
                window.addEventListener('beforeunload', function(e) {
                    // Only show warning if user is trying to navigate away manually
                    if (countdown > 0) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit();
}
?>