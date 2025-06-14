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
$phone = isset($_POST['phone']) ? $_POST['phone'] : '';
$address = isset($_POST['address']) ? $_POST['address'] : '';

$result = ['success' => false];

if ($email && $password && $name && $phone && $address) {
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $result['error'] = 'Email already registered.';
        } else {
            // Insert new user (plain password for demo; use password_hash for production)
            $stmt = $pdo->prepare("INSERT INTO customers (customer_email, customer_password, customer_name, customer_phone, customer_address) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$email, $password, $name, $phone, $address])) {
                // Get the new user's id
                $newId = $pdo->lastInsertId();
                $_SESSION['customer_id'] = $newId; // Log in the new user
                $result['success'] = true;
            } else {
                $result['error'] = 'Registration failed.';
            }
        }
    } catch (PDOException $e) {
        $result['error'] = $e->getMessage(); // Show real error for debugging
    }
} else {
    $result['error'] = 'All fields are required.';
}

echo json_encode($result);
?>
