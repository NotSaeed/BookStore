<?php
session_start();
header('Content-Type: application/json');

// Database connection settings
$host = 'localhost:3307';
$db   = 'bookstore'; // Change to your database name
$user = 'root';      // Default XAMPP user
$pass = '';          // Default XAMPP password is empty
$charset = 'utf8mb4';

$email = isset($_POST['email']) ? $_POST['email'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$name = isset($_POST['name']) ? $_POST['name'] : '';

$result = ['success' => false];

if ($email && $password && $name) {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $result['error'] = 'Email already registered.';
        } else {
            // Insert new user (plain password for demo; use password_hash for production)
            $stmt = $pdo->prepare("INSERT INTO customers (email, password, name) VALUES (?, ?, ?)");
            if ($stmt->execute([$email, $password, $name])) {
                // Get the new user's id
                $newId = $pdo->lastInsertId();
                $_SESSION['customer_id'] = $newId; // Log in the new user
                $result['success'] = true;
            } else {
                $result['error'] = 'Registration failed.';
            }
        }
    } catch (PDOException $e) {
        $result['error'] = 'Database error';
    }
} else {
    $result['error'] = 'All fields are required.';
}

echo json_encode($result);
?>
