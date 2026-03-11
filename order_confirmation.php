<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$order_number = $_GET['order'] ?? '';

$database = new Database();
$db = $database->getConnection();

// Get order details
$query = "SELECT o.*, u.full_name, u.email 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.order_number = :order_number AND o.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_number', $order_number);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "SELECT * FROM order_items WHERE order_id = :order_id";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':order_id', $order['id']);
$items_stmt->execute();
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - SecureApp</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .confirmation-box {
            max-width: 600px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 50px;
            color: white;
            animation: scaleIn 0.5s ease;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }
            to {
                transform: scale(1);
            }
        }

        h1 {
            color: #333;
            margin-bottom: 15px;
        }

        .order-number {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .order-number h3 {
            color: #666;
            margin-bottom: 5px;
        }

        .order-number p {
            font-size: 24px;
            font-weight: bold;
            color: #4834d4;
            letter-spacing: 2px;
        }

        .order-details {
            text-align: left;
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .detail-row {
            display: flex;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            width: 120px;
            color: #666;
        }

        .detail-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }

        .items-list {
            margin: 15px 0;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            color: #666;
        }

        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #4834d4;
            color: white;
        }

        .btn-primary:hover {
            background: #3c2ba5;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e9ecef;
        }
    </style>
</head>
<body>
    <div class="confirmation-box">
        <div class="success-icon">✓</div>
        
        <h1>Order Confirmed!</h1>
        <p>Thank you for your purchase. Your order has been placed successfully.</p>
        
        <div class="order-number">
            <h3>Order Number</h3>
            <p><?php echo htmlspecialchars($order['order_number']); ?></p>
        </div>
        
        <div class="order-details">
            <h3 style="margin-bottom: 15px;">Order Details</h3>
            
            <div class="detail-row">
                <span class="detail-label">Order Date</span>
                <span class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Payment Method</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['payment_method']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Shipping Address</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Contact Number</span>
                <span class="detail-value"><?php echo htmlspecialchars($order['contact_number']); ?></span>
            </div>
            
            <h4 style="margin: 20px 0 10px;">Items Ordered:</h4>
            <div class="items-list">
                <?php foreach ($order_items as $item): ?>
                    <div class="item-row">
                        <span><?php echo htmlspecialchars($item['product_name']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="detail-row" style="margin-top: 15px; border-top: 2px solid #dee2e6; padding-top: 15px;">
                <span class="detail-label" style="font-weight: bold;">Total Amount</span>
                <span class="detail-value" style="font-weight: bold; color: #4834d4;">₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>
        
        <div class="buttons">
            <a href="orders.php" class="btn btn-primary">View My Orders</a>
            <a href="product.php" class="btn btn-secondary">Continue Shopping</a>
        </div>
    </div>
</body>
</html>