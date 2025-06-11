<?php
session_start();
require_once 'db_connect.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['deliveryOrder']) || !is_array($data['deliveryOrder'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit();
}

$courier_id = $_SESSION['courier_id'];
$order = $data['deliveryOrder'];

// Begin transaction
$conn->begin_transaction();

try {
    // Update delivery order for each item
    foreach ($order as $index => $delivery_id) {
        $stmt = $conn->prepare("
            UPDATE deliveries 
            SET route_order = ?, 
                updated_at = NOW()
            WHERE id = ? 
            AND courier_id = ?");
        
        $order_num = $index + 1;
        $stmt->bind_param("iis", $order_num, $delivery_id, $courier_id);
        $stmt->execute();
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save route']);
}
