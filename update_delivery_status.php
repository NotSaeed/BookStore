<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header('Location: courier-login.html');
    exit();
}

// Check if we have the required parameters
if (!isset($_GET['id']) || !isset($_GET['status'])) {
    header('Location: active-deliveries.php');
    exit();
}

$delivery_id = $_GET['id'];
$new_status = $_GET['status'];
$courier_id = $_SESSION['courier_id'];

try {
    // Start transaction
    $conn->begin_transaction();

    // First verify that this delivery belongs to the courier
    $stmt = $conn->prepare("SELECT * FROM deliveries WHERE id = ? AND courier_id = ?");
    $stmt->bind_param("is", $delivery_id, $courier_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Delivery not found or unauthorized");
    }

    // Update the delivery status
    $stmt = $conn->prepare("UPDATE deliveries SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $delivery_id);
    $stmt->execute();

    // Add a delivery update record
    $stmt = $conn->prepare("INSERT INTO delivery_updates (delivery_id, status, update_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $delivery_id, $new_status);
    $stmt->execute();

    // Commit transaction
    $conn->commit();

    // Redirect back to active deliveries
    header('Location: active-deliveries.php');
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();

    echo '<!DOCTYPE html>
<html>
<head>
    <title>Update Error</title>
    <style>
        .error-container {
            color: red;
            padding: 20px;
            margin: 20px;
            border: 1px solid red;
            border-radius: 5px;
            text-align: center;
        }
        .back-link {
            display: inline-block;
            margin-top: 15px;
            color: #9b59b6;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h3>Error:</h3>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
        <a href="active-deliveries.php" class="back-link">Go back to Active Deliveries</a>
    </div>
</body>
</html>';
}

$conn->close();
?>
