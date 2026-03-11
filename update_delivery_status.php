<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['courier_id'])) {
    echo json_encode(['success' => false]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'];
$status = $input['status'];

$database = new Database();
$db = $database->getConnection();

$query = "UPDATE orders SET status = :status WHERE id = :order_id AND courier_id = :courier_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':status', $status);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':courier_id', $_SESSION['courier_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>