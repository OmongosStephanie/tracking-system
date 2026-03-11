<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle order cancellation
if (isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $order_id = $_GET['cancel'];
    
    // Check if order is still pending
    $check_query = "SELECT status FROM orders WHERE id = :id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $order_id);
    $check_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $check_stmt->execute();
    $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && $order['status'] == 'Pending') {
        // Update order status to cancelled
        $update_query = "UPDATE orders SET status = 'Cancelled' WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $order_id);
        
        if ($update_stmt->execute()) {
            // Restore product stock
            $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = :order_id";
            $items_stmt = $db->prepare($items_query);
            $items_stmt->bindParam(':order_id', $order_id);
            $items_stmt->execute();
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                $restore_query = "UPDATE products SET stock = stock + :quantity WHERE id = :product_id";
                $restore_stmt = $db->prepare($restore_query);
                $restore_stmt->bindParam(':quantity', $item['quantity']);
                $restore_stmt->bindParam(':product_id', $item['product_id']);
                $restore_stmt->execute();
            }
            
            $success = "Order has been cancelled successfully.";
        }
    } else {
        $error = "This order cannot be cancelled.";
    }
}

// Get user's orders - FIXED: changed created_at to order_date
$query = "SELECT * FROM orders WHERE user_id = :user_id ORDER BY order_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count for navbar
$cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->bindParam(':user_id', $_SESSION['user_id']);
$cart_stmt->execute();
$cart_count = $cart_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get order count for navbar
$order_count_query = "SELECT COUNT(*) as total FROM orders WHERE user_id = :user_id";
$order_count_stmt = $db->prepare($order_count_query);
$order_count_stmt->bindParam(':user_id', $_SESSION['user_id']);
$order_count_stmt->execute();
$order_count = $order_count_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - SecureApp</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
        }

        .navbar {
            background: #4834d4;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-decoration: none;
        }

        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .navbar-menu a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .navbar-menu a:hover {
            background: rgba(255,255,255,0.1);
        }

        .cart-icon, .orders-icon {
            position: relative;
        }

        .cart-count, .order-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .order-count {
            background: #28a745;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: #333;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .orders-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .order-number {
            font-size: 18px;
            font-weight: bold;
            color: #4834d4;
        }

        .order-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending { 
            background: #ffd700; 
            color: #856404; 
        }
        .status-processing { 
            background: #17a2b8; 
            color: white; 
        }
        .status-shipped { 
            background: #007bff; 
            color: white; 
        }
        .status-delivered { 
            background: #28a745; 
            color: white; 
        }
        .status-cancelled { 
            background: #dc3545; 
            color: white; 
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 15px;
        }

        .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #28a745;
        }

        .action-btns {
            display: flex;
            gap: 10px;
        }

        .btn-view {
            padding: 8px 20px;
            background: #17a2b8;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view:hover {
            background: #138496;
        }

        .btn-cancel {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        .empty-orders {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 10px;
        }

        .empty-orders h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 24px;
        }

        .empty-orders p {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .shop-now-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #4834d4;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            transition: background 0.3s;
        }

        .shop-now-btn:hover {
            background: #3c2ba5;
        }

        .tracking-info {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 14px;
        }

        .tracking-number {
            font-family: monospace;
            font-weight: bold;
            color: #007bff;
        }

        @media (max-width: 768px) {
            .order-header {
                flex-direction: column;
                text-align: center;
            }
            
            .order-footer {
                flex-direction: column;
            }
            
            .action-btns {
                width: 100%;
            }
            
            .btn-view, .btn-cancel {
                flex: 1;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">🔐 SecureApp</a>
        <div class="navbar-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="product.php">Products</a>
            <a href="cart.php" class="cart-icon">
                🛒 Cart
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="orders.php" class="orders-icon">
                My Orders
                <?php if ($order_count > 0): ?>
                    <span class="order-count"><?php echo $order_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" style="background: #dc3545;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>My Orders</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="empty-orders">
                <h3>No orders yet</h3>
                <p>Looks like you haven't placed any orders yet. Start shopping now!</p>
                <a href="product.php" class="shop-now-btn">🛍️ Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-number">Order #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                <?php 
                                $status_icons = [
                                    'Pending' => '⏳',
                                    'Processing' => '⚙️',
                                    'Shipped' => '🚚',
                                    'Delivered' => '✅',
                                    'Cancelled' => '❌'
                                ];
                                echo ($status_icons[$order['status']] ?? '📦') . ' ' . $order['status']; 
                                ?>
                            </span>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-item">
                                <span class="detail-label">Order Date</span>
                                <span class="detail-value"><?php echo date('F d, Y', strtotime($order['order_date'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Payment Method</span>
                                <span class="detail-value"><?php echo $order['payment_method'] ?? 'Cash on Delivery'; ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Items</span>
                                <span class="detail-value"><?php 
                                    // Get item count
                                    $item_query = "SELECT COUNT(*) as count FROM order_items WHERE order_id = :order_id";
                                    $item_stmt = $db->prepare($item_query);
                                    $item_stmt->bindParam(':order_id', $order['id']);
                                    $item_stmt->execute();
                                    $item_count = $item_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                    echo $item_count . ' item(s)';
                                ?></span>
                            </div>
                        </div>

                        <?php if (!empty($order['tracking_number'])): ?>
                            <div class="tracking-info">
                                📦 Tracking Number: <span class="tracking-number"><?php echo $order['tracking_number']; ?></span>
                                <?php if (!empty($order['estimated_delivery'])): ?>
                                    <br>📅 Estimated Delivery: <?php echo date('F d, Y', strtotime($order['estimated_delivery'])); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="order-footer">
                            <span class="total-amount">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            <div class="action-btns">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-view">
                                    👁️ View Details
                                </a>
                                <?php if ($order['status'] == 'Pending'): ?>
                                    <a href="?cancel=<?php echo $order['id']; ?>" 
                                       class="btn-cancel" 
                                       onclick="return confirm('Are you sure you want to cancel this order? This action cannot be undone.')">
                                        ❌ Cancel Order
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>