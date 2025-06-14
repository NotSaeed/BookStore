<?php
session_start();
header('Content-Type: application/json');

// Update these with your actual DB credentials
$host = 'localhost';
$db   = 'bookstore';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// You should store the logged-in user's ID/email in session during login
if (!isset($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$customer_id = $_SESSION['customer_id'];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    $stmt = $pdo->prepare('SELECT first_name, last_name, email, phone, address, city, state, zip FROM customers WHERE id = ?');
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();
    if ($customer) {
        echo json_encode(array_merge(['success' => true], $customer));
    } else {
        echo json_encode(['success' => false, 'error' => 'Customer not found']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
