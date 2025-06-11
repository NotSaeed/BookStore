<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
      // For now, we'll use hardcoded admin credentials
    // In a real system, this would be stored in a database with proper hashing
    $admin_username = 'admin';
    $admin_password = 'Admin123'; // Updated to meet validation requirements
    
    if ($username === $admin_username && $password === $admin_password) {
        // Login successful
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['user_type'] = 'admin';
        
        header("Location: admin-dashboard.php");
        exit();
    } else {
        header("Location: admin-login.html?error=invalid_credentials");
        exit();
    }
} else {
    header("Location: admin-login.html");
    exit();
}
?>
