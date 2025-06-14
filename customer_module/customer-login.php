<?php
session_start();
header('Content-Type: application/json');

// Database connection settings
$host = 'localhost:3307'; // Changed to port 3307
$db   = 'bookstore'; // Change to your database name
$user = 'root';      // Default XAMPP user
$pass = '';          // Default XAMPP password is empty
$charset = 'utf8mb4';

// Get POST data
$email = isset($_POST['email']) ? $_POST['email'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

$result = ['success' => false];

if ($email && $password) {
    // Connect to database
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        // Query for user (use correct column names)
        $stmt = $pdo->prepare("SELECT customer_id, customer_password FROM customers WHERE customer_email = ?");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            // For plain text password (not recommended for production)
            if ($row['customer_password'] === $password) {
                $_SESSION['customer_id'] = $row['customer_id']; // Store customer id in session
                $result['success'] = true;
            }
            // For hashed password (recommended):
            // if (password_verify($password, $row['customer_password'])) {
            //     $_SESSION['customer_id'] = $row['customer_id'];
            //     $result['success'] = true;
            // }
        }
    } catch (PDOException $e) {
        $result['error'] = 'Database error';
    }
}

echo json_encode($result);
?>
