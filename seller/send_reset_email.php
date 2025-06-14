<?php
<?php
session_start();
require_once __DIR__ . '/includes/seller_db.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configuration
define('MAX_RESET_ATTEMPTS', 3);
define('RESET_COOLDOWN', 900); // 15 minutes
define('TOKEN_EXPIRY', 3600); // 1 hour
define('MAX_DAILY_RESETS', 5);

// Enhanced logging function
function logPasswordResetAttempt($email, $status, $details = []) {
    $log_data = [
        'timestamp' => date('Y-m-d H:i:s'),
        'email' => $email,
        'status' => $status,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'details' => $details
    ];
    
    // Log to file
    $log_message = json_encode($log_data) . "\n";
    file_put_contents(__DIR__ . '/logs/password_reset.log', $log_message, FILE_APPEND | LOCK_EX);
    
    // Log to database
    try {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO security_logs (event_type, email, ip_address, user_agent, details, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute(['password_reset', $email, $log_data['ip_address'], $log_data['user_agent'], json_encode($details)]);
    } catch (Exception $e) {
        error_log("Failed to log password reset attempt: " . $e->getMessage());
    }
}

// Rate limiting function
function checkRateLimit($email, $ip) {
    global $pdo;
    
    // Check IP-based rate limiting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM security_logs 
        WHERE event_type = 'password_reset' 
        AND ip_address = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$ip]);
    $ip_attempts = $stmt->fetchColumn();
    
    if ($ip_attempts >= MAX_RESET_ATTEMPTS) {
        return ['allowed' => false, 'reason' => 'IP rate limit exceeded'];
    }
    
    // Check email-based rate limiting
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM security_logs 
        WHERE event_type = 'password_reset' 
        AND email = ? 
        AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$email]);
    $email_attempts = $stmt->fetchColumn();
    
    if ($email_attempts >= MAX_RESET_ATTEMPTS) {
        return ['allowed' => false, 'reason' => 'Email rate limit exceeded'];
    }
    
    // Check daily limit
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM security_logs 
        WHERE event_type = 'password_reset' 
        AND email = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$email]);
    $daily_attempts = $stmt->fetchColumn();
    
    if ($daily_attempts >= MAX_DAILY_RESETS) {
        return ['allowed' => false, 'reason' => 'Daily limit exceeded'];
    }
    
    return ['allowed' => true];
}

// CSRF protection
function validateCSRFToken() {
    return isset($_SESSION['csrf_token']) && 
           isset($_POST['csrf_token']) && 
           hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Generate secure token
function generateSecureToken() {
    return bin2hex(random_bytes(32));
}

// Get client IP (considering proxies)
function getClientIP() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

// Enhanced email template
function getEmailTemplate($seller_name, $reset_link, $expire_time, $location) {
    $template = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; background-color: #f8f9fa; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { padding: 40px 30px; }
        .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; margin: 20px 0; }
        .security-info { background: #f8f9fa; border-left: 4px solid #667eea; padding: 15px; margin: 20px 0; border-radius: 5px; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #6c757d; border-radius: 0 0 10px 10px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset Request</h1>
            <p>BookStore Seller Hub</p>
        </div>
        <div class="content">
            <h2>Hello ' . htmlspecialchars($seller_name) . ',</h2>
            <p>We received a request to reset your password for your BookStore seller account. If you made this request, click the button below to reset your password:</p>
            
            <div style="text-align: center;">
                <a href="' . $reset_link . '" class="button">Reset My Password</a>
            </div>
            
            <div class="security-info">
                <h3>🛡️ Security Information:</h3>
                <ul>
                    <li><strong>Request Time:</strong> ' . date('F j, Y \a\t g:i A T') . '</li>
                    <li><strong>Location:</strong> ' . htmlspecialchars($location) . '</li>
                    <li><strong>Expires:</strong> ' . $expire_time . '</li>
                    <li><strong>Valid for:</strong> 1 hour only</li>
                </ul>
            </div>
            
            <div class="warning">
                <h3>⚠️ Important Security Notes:</h3>
                <ul>
                    <li>This link will expire in <strong>1 hour</strong></li>
                    <li>The link can only be used <strong>once</strong></li>
                    <li>If you didn\'t request this reset, please ignore this email</li>
                    <li>Consider enabling two-factor authentication for better security</li>
                </ul>
            </div>
            
            <p>If the button doesn\'t work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all; background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace;">
                ' . $reset_link . '
            </p>
            
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #e9ecef;">
            
            <h3>🔒 Protect Your Account:</h3>
            <ul>
                <li>Use a strong, unique password</li>
                <li>Enable two-factor authentication</li>
                <li>Don\'t share your login credentials</li>
                <li>Log out from shared computers</li>
            </ul>
        </div>
        <div class="footer">
            <p><strong>BookStore Seller Hub</strong> | Secure Password Management</p>
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>If you need help, contact our support team at support@bookstore.com</p>
        </div>
    </div>
</body>
</html>';
    
    return $template;
}

// Send email using enhanced method
function sendPasswordResetEmail($email, $seller_name, $reset_link, $expire_time, $location) {
    // Email configuration
    $to = $email;
    $subject = "🔐 Password Reset Request - BookStore Seller Hub";
    
    // Get HTML template
    $html_message = getEmailTemplate($seller_name, $reset_link, $expire_time, $location);
    
    // Plain text version
    $text_message = "Hello " . $seller_name . ",\n\n";
    $text_message .= "You have requested to reset your password for your BookStore seller account.\n\n";
    $text_message .= "Reset link: " . $reset_link . "\n\n";
    $text_message .= "Security Information:\n";
    $text_message .= "- Request Time: " . date('F j, Y \a\t g:i A T') . "\n";
    $text_message .= "- Location: " . $location . "\n";
    $text_message .= "- Expires: " . $expire_time . "\n";
    $text_message .= "- Valid for: 1 hour only\n\n";
    $text_message .= "Important: This link will expire in 1 hour and can only be used once.\n";
    $text_message .= "If you didn't request this reset, please ignore this email.\n\n";
    $text_message .= "Best regards,\nBookStore Team\n\n";
    $text_message .= "This is an automated email. Please do not reply.";
    
    // Email headers
    $boundary = md5(time());
    $headers = [
        "From: BookStore Seller Hub <noreply@bookstore.com>",
        "Reply-To: support@bookstore.com",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: BookStore Password Reset System",
        "X-Priority: 3",
        "Message-ID: <" . time() . "@bookstore.com>"
    ];
    
    // Email body
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $text_message . "\r\n";
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $html_message . "\r\n";
    
    $body .= "--{$boundary}--";
    
    // Send email
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

// Get location from IP
function getLocationFromIP($ip) {
    // Simple location detection (you might want to use a more sophisticated service)
    $location = "Unknown Location";
    
    // Try to get basic location info
    if ($ip !== 'Unknown' && filter_var($ip, FILTER_VALIDATE_IP)) {
        // You can integrate with services like ipinfo.io, geoip, etc.
        // For now, just return basic info
        $location = "IP: " . $ip;
    }
    
    return $location;
}

// Main execution
try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logPasswordResetAttempt('', 'invalid_method', ['method' => $_SERVER['REQUEST_METHOD']]);
        header('Location: forgot_password.php?error=invalid_request');
        exit;
    }

    // Validate CSRF token
    if (!validateCSRFToken()) {
        logPasswordResetAttempt('', 'csrf_failed');
        header('Location: forgot_password.php?error=invalid_token');
        exit;
    }

    // Get and validate email
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $client_ip = getClientIP();

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logPasswordResetAttempt($email, 'invalid_email', ['provided_email' => $_POST['email'] ?? '']);
        header('Location: forgot_password.php?error=invalid_email');
        exit;
    }

    // Check rate limiting
    $rate_check = checkRateLimit($email, $client_ip);
    if (!$rate_check['allowed']) {
        logPasswordResetAttempt($email, 'rate_limited', ['reason' => $rate_check['reason']]);
        header('Location: forgot_password.php?error=rate_limited');
        exit;
    }

    // Check if seller exists
    $stmt = $pdo->prepare("SELECT seller_id, seller_name, seller_email, account_status, last_login FROM sellers WHERE seller_email = ?");
    $stmt->execute([$email]);
    $seller = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$seller) {
        // Always log this but don't reveal if email exists
        logPasswordResetAttempt($email, 'email_not_found');
        // Still redirect with success to prevent email enumeration
        header('Location: forgot_password.php?status=sent');
        exit;
    }

    // Check if account is active
    if ($seller['account_status'] !== 'active') {
        logPasswordResetAttempt($email, 'account_inactive', ['status' => $seller['account_status']]);
        header('Location: forgot_password.php?error=account_inactive');
        exit;
    }

    // Generate secure token and expiry
    $token = generateSecureToken();
    $expire = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY);
    $expire_formatted = date('F j, Y \a\t g:i A T', time() + TOKEN_EXPIRY);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Delete any existing reset tokens for this email
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        // Insert new reset token
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (email, token, expire_at, created_at, ip_address, user_agent) 
            VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->execute([$email, $token, $expire, $client_ip, $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown']);

        // Commit transaction
        $pdo->commit();

        // Prepare reset link
        $reset_link = "https://{$_SERVER['HTTP_HOST']}/BookStore/seller/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);
        
        // Get location
        $location = getLocationFromIP($client_ip);

        // Send email
        $email_sent = sendPasswordResetEmail($email, $seller['seller_name'], $reset_link, $expire_formatted, $location);

        if ($email_sent) {
            logPasswordResetAttempt($email, 'success', [
                'token_created' => true,
                'email_sent' => true,
                'expires_at' => $expire
            ]);
            
            // Update seller's last activity
            $stmt = $pdo->prepare("UPDATE sellers SET updated_at = NOW() WHERE seller_id = ?");
            $stmt->execute([$seller['seller_id']]);
            
            header('Location: forgot_password.php?status=sent');
        } else {
            throw new Exception('Failed to send email');
        }

    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollback();
        throw $e;
    }

} catch (PDOException $e) {
    error_log("Password reset database error: " . $e->getMessage());
    logPasswordResetAttempt($email ?? '', 'database_error', ['error' => $e->getMessage()]);
    header('Location: forgot_password.php?error=system_error');
} catch (Exception $e) {
    error_log("Password reset error: " . $e->getMessage());
    logPasswordResetAttempt($email ?? '', 'system_error', ['error' => $e->getMessage()]);
    header('Location: forgot_password.php?error=system_error');
} finally {
    exit;
}
?>