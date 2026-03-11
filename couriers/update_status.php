<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['courier_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? 0;
$status = $input['status'] ?? '';
$recipient_name = $input['recipient_name'] ?? '';
$notes = $input['notes'] ?? '';

if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Verify order belongs to courier
$check_query = "SELECT id, status FROM orders WHERE id = :order_id AND courier_id = :courier_id";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':order_id', $order_id);
$check_stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$check_stmt->execute();
$order = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

if ($status == 'Delivered') {
    // Update order to Delivered with delivery info
    $update_query = "UPDATE orders SET 
                     status = 'Delivered', 
                     delivered_at = NOW(), 
                     recipient_name = :recipient_name,
                     delivery_notes = :notes 
                     WHERE id = :order_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':recipient_name', $recipient_name);
    $update_stmt->bindParam(':notes', $notes);
    $update_stmt->bindParam(':order_id', $order_id);
    
    // Add to tracking history
    $track_query = "INSERT INTO order_tracking (order_id, status, location, description) 
                    VALUES (:order_id, 'Delivered', 'Customer location', 'Package delivered successfully')";
    $track_stmt = $db->prepare($track_query);
    $track_stmt->bindParam(':order_id', $order_id);
    $track_stmt->execute();
    
} else {
    // Just update status
    $update_query = "UPDATE orders SET status = :status WHERE id = :order_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $status);
    $update_stmt->bindParam(':order_id', $order_id);
}

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status']);
}
?>