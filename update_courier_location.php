<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

// Check if courier is logged in (you'll need a courier authentication system)
if (!isset($_SESSION['courier_id']) && !isset($_GET['api_key'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? $_POST['order_id'] ?? 0;
$latitude = $input['latitude'] ?? $_POST['latitude'] ?? 0;
$longitude = $input['longitude'] ?? $_POST['longitude'] ?? 0;
$speed = $input['speed'] ?? $_POST['speed'] ?? 0;
$bearing = $input['bearing'] ?? $_POST['bearing'] ?? 0;

if (!$order_id || !$latitude || !$longitude) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Update current location
$update_query = "UPDATE orders SET 
                 courier_lat = :lat,
                 courier_lng = :lng,
                 courier_speed = :speed,
                 courier_bearing = :bearing,
                 courier_last_update = NOW()
                 WHERE id = :order_id";
$update_stmt = $db->prepare($update_query);
$update_stmt->bindParam(':lat', $latitude);
$update_stmt->bindParam(':lng', $longitude);
$update_stmt->bindParam(':speed', $speed);
$update_stmt->bindParam(':bearing', $bearing);
$update_stmt->bindParam(':order_id', $order_id);

// Save to history
$history_query = "INSERT INTO courier_location_history 
                  (order_id, courier_lat, courier_lng, speed, bearing) 
                  VALUES (:order_id, :lat, :lng, :speed, :bearing)";
$history_stmt = $db->prepare($history_query);
$history_stmt->bindParam(':order_id', $order_id);
$history_stmt->bindParam(':lat', $latitude);
$history_stmt->bindParam(':lng', $longitude);
$history_stmt->bindParam(':speed', $speed);
$history_stmt->bindParam(':bearing', $bearing);

if ($update_stmt->execute() && $history_stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>