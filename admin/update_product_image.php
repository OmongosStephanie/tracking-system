<?php
session_start();
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['product_image']) && isset($_POST['product_id'])) {
    
    $database = new Database();
    $db = $database->getConnection();
    
    $product_id = $_POST['product_id'];
    
    // Get current image
    $query = "SELECT image FROM products WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Upload directory
    $target_dir = "../uploads/products/";
    
    // Create directory if it doesn't exist
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file = $_FILES['product_image'];
    $file_name = time() . '_' . basename($file['name']);
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Check if image file is actual image
    $check = getimagesize($file['tmp_name']);
    if ($check === false) {
        $_SESSION['error'] = "File is not an image.";
        header("Location: products.php");
        exit();
    }
    
    // Check file size (2MB max)
    if ($file['size'] > 2000000) {
        $_SESSION['error'] = "File is too large. Maximum size is 2MB.";
        header("Location: products.php");
        exit();
    }
    
    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        header("Location: products.php");
        exit();
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        
        // Delete old image if exists
        if (!empty($product['image']) && file_exists($target_dir . $product['image'])) {
            unlink($target_dir . $product['image']);
        }
        
        // Update database
        $update_query = "UPDATE products SET image = :image WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':image', $file_name);
        $update_stmt->bindParam(':id', $product_id);
        
        if ($update_stmt->execute()) {
            logAdminAction('Update Product Image', "Updated image for product ID: $product_id");
            $_SESSION['success'] = "Product image updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update database.";
        }
    } else {
        $_SESSION['error'] = "Failed to upload file.";
    }
    
    header("Location: products.php");
    exit();
} else {
    header("Location: products.php");
    exit();
}
?>