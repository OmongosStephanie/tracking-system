<?php
session_start();

// Check if user is logged in and is admin
function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Redirect if not admin
function requireAdmin() {
    if (!isAdmin()) {
        header("Location: ../admin/login.php");
        exit();
    }
}

// Log admin action
function logAdminAction($action, $details = '') {
    require_once '../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (:admin_id, :action, :details, :ip)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':admin_id', $_SESSION['user_id']);
    $stmt->bindParam(':action', $action);
    $stmt->bindParam(':details', $details);
    $stmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
    $stmt->execute();
}
?>