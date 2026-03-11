<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['order_id'];

// Get latest courier location
$query = "SELECT courier_lat, courier_lng, courier_speed, courier_bearing, 
                 courier_last_update,
                 TIMESTAMPDIFF(SECOND, courier_last_update, NOW()) as seconds_ago
          FROM orders 
          WHERE id = :order_id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$location = $stmt->fetch(PDO::FETCH_ASSOC);

if ($location && $location['courier_lat'] && $location['courier_lng']) {
    // Calculate ETA to destination (you'd need destination coordinates)
    // This is a simplified example
    $eta = calculateETA(
        $location['courier_lat'], 
        $location['courier_lng'],
        $destination_lat ?? 14.5995, // You'd store these in orders table
        $destination_lng ?? 120.9842,
        $location['courier_speed']
    );
    
    echo json_encode([
        'success' => true,
        'lat' => $location['courier_lat'],
        'lng' => $location['courier_lng'],
        'speed' => $location['courier_speed'],
        'bearing' => $location['courier_bearing'],
        'last_update' => $location['courier_last_update'],
        'seconds_ago' => $location['seconds_ago'],
        'is_moving' => $location['seconds_ago'] < 60, // Updated in last minute
        'eta_minutes' => $eta
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No location available']);
}

function calculateETA($lat1, $lng1, $lat2, $lng2, $speed_kmh) {
    if (!$speed_kmh || $speed_kmh < 1) return null;
    
    // Haversine formula for distance
    $earth_radius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    // ETA in minutes
    return round(($distance / $speed_kmh) * 60);
}
?>