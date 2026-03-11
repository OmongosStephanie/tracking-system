<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    echo json_encode(['updated' => false]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['order_id'];

// Get latest tracking info
$query = "SELECT current_location, last_update, current_lat, current_lng 
          FROM orders 
          WHERE id = :order_id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if ($order) {
    echo json_encode([
        'updated' => true,
        'location' => $order['current_location'],
        'last_update' => date('M d, Y h:i A', strtotime($order['last_update'])),
        'lat' => $order['current_lat'],
        'lng' => $order['current_lng']
    ]);
} else {
    echo json_encode(['updated' => false]);
}
?>