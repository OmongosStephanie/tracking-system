<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Check if product exists and has stock
$product_query = "SELECT * FROM products WHERE id = :id";
$product_stmt = $db->prepare($product_query);
$product_stmt->bindParam(':id', $product_id);
$product_stmt->execute();
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit();
}

if ($product['stock'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
    exit();
}

// Check if product already in cart
$cart_query = "SELECT * FROM cart WHERE user_id = :user_id AND product_id = :product_id";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->bindParam(':user_id', $user_id);
$cart_stmt->bindParam(':product_id', $product_id);
$cart_stmt->execute();

if ($cart_stmt->rowCount() > 0) {
    // Update quantity
    $cart_item = $cart_stmt->fetch(PDO::FETCH_ASSOC);
    $new_quantity = $cart_item['quantity'] + $quantity;
    
    if ($new_quantity > $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Cannot add more than available stock']);
        exit();
    }
    
    $update_query = "UPDATE cart SET quantity = :quantity WHERE id = :id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':quantity', $new_quantity);
    $update_stmt->bindParam(':id', $cart_item['id']);
    $update_stmt->execute();
} else {
    // Insert new cart item
    $insert_query = "INSERT INTO cart (user_id, product_id, quantity) VALUES (:user_id, :product_id, :quantity)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->bindParam(':user_id', $user_id);
    $insert_stmt->bindParam(':product_id', $product_id);
    $insert_stmt->bindParam(':quantity', $quantity);
    $insert_stmt->execute();
}

echo json_encode(['success' => true, 'message' => 'Added to cart successfully']);
?>