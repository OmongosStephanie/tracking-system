<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    $location = $_POST['location'];
    $description = $_POST['description'];
    $tracking_number = $_POST['tracking_number'];
    $estimated_delivery = $_POST['estimated_delivery'];
    
    // Update order
    $query = "UPDATE orders SET 
              status = :status,
              tracking_number = :tracking_number,
              estimated_delivery = :estimated_delivery,
              current_location = :location
              WHERE id = :order_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':tracking_number', $tracking_number);
    $stmt->bindParam(':estimated_delivery', $estimated_delivery);
    $stmt->bindParam(':location', $location);
    $stmt->bindParam(':order_id', $order_id);
    
    if ($stmt->execute()) {
        // Add tracking history
        $track_query = "INSERT INTO order_tracking (order_id, status, location, description) 
                        VALUES (:order_id, :status, :location, :description)";
        $track_stmt = $db->prepare($track_query);
        $track_stmt->bindParam(':order_id', $order_id);
        $track_stmt->bindParam(':status', $status);
        $track_stmt->bindParam(':location', $location);
        $track_stmt->bindParam(':description', $description);
        $track_stmt->execute();
        
        $_SESSION['success'] = "Order tracking updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update tracking.";
    }
    
    header("Location: orders.php");
    exit();
}
?>