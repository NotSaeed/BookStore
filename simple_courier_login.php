<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $courier_id = trim($_POST['courier_id']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Check if courier exists
    $sql = "SELECT * FROM couriers WHERE courier_id = ? AND email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $courier_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header("Location: courier-login.html?error=invalid_credentials");
        exit();
    }
    
    $courier = $result->fetch_assoc();    // Verify password
    if (password_verify($password, $courier['password'])) {
        // Login successful
        $_SESSION['courier_id'] = $courier_id;
        $_SESSION['courier_email'] = $email;
        $_SESSION['courier_name'] = $courier['name'];
        
        header("Location: courier-dashboard.php");
        exit();
    } else {
        header("Location: courier-login.html?error=invalid_password");
        exit();
    }
} else {
    header("Location: courier-login.html");
    exit();
}
?>
