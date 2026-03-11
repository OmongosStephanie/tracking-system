<?php
session_start();
require_once '../config/database.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get courier information
$courier_query = "SELECT * FROM couriers WHERE id = :id";
$courier_stmt = $db->prepare($courier_query);
$courier_stmt->bindParam(':id', $_SESSION['courier_id']);
$courier_stmt->execute();
$courier = $courier_stmt->fetch(PDO::FETCH_ASSOC);

// If courier not found, logout
if (!$courier) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get assigned deliveries
$query = "SELECT o.*, u.username as customer_name, u.address, u.phone,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.courier_id = :courier_id 
          ORDER BY 
            CASE 
                WHEN o.status = 'Processing' THEN 1
                WHEN o.status = 'Shipped' THEN 2
                WHEN o.status = 'Delivered' THEN 3
                ELSE 4
            END,
            o.order_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$stmt->execute();
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN status = 'Processing' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'Shipped' THEN 1 END) as ongoing,
                COUNT(CASE WHEN status = 'Delivered' THEN 1 END) as completed,
                SUM(CASE WHEN status = 'Delivered' THEN total_amount ELSE 0 END) as total_delivered_value
                FROM orders WHERE courier_id = :courier_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!-- Rest of your dashboard code remains the same -->