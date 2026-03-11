<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['courier_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$order_id = $_POST['order_id'] ?? 0;
$photo_type = $_POST['photo_type'] ?? 'delivery';
$notes = $_POST['notes'] ?? '';

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit();
}

// Verify order belongs to courier
$database = new Database();
$db = $database->getConnection();

$check_query = "SELECT id FROM orders WHERE id = :order_id AND courier_id = :courier_id";
$check_stmt = $db->prepare($check_query);
$check_stmt->bindParam(':order_id', $order_id);
$check_stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$check_stmt->execute();

if (!$check_stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit();
}

// Handle file upload
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No photo uploaded or upload error']);
    exit();
}

$file = $_FILES['photo'];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$max_size = 5 * 1024 * 1024; // 5MB

// Validate file type
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF files are allowed']);
    exit();
}

// Validate file size
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
    exit();
}

// Create upload directory if not exists
$upload_dir = '../uploads/delivery_proofs/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'delivery_' . $order_id . '_' . time() . '_' . uniqid() . '.' . $extension;
$filepath = $upload_dir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Save to database
    $insert_query = "INSERT INTO delivery_proofs (order_id, courier_id, photo_path, photo_type, notes) 
                     VALUES (:order_id, :courier_id, :photo_path, :photo_type, :notes)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':order_id', $order_id);
    $insert_stmt->bindParam(':courier_id', $_SESSION['courier_id']);
    $insert_stmt->bindParam(':photo_path', $filename);
    $insert_stmt->bindParam(':photo_type', $photo_type);
    $insert_stmt->bindParam(':notes', $notes);
    
    if ($insert_stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => 'Photo uploaded successfully',
            'filename' => $filename
        ]);
    } else {
        // Delete file if database insert fails
        unlink($filepath);
        echo json_encode(['success' => false, 'message' => 'Failed to save to database']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>