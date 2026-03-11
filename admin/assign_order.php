<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$courier_id = isset($_GET['courier_id']) ? $_GET['courier_id'] : 0;

// Get courier info
$courier_query = "SELECT * FROM couriers WHERE id = :id";
$courier_stmt = $db->prepare($courier_query);
$courier_stmt->bindParam(':id', $courier_id);
$courier_stmt->execute();
$courier = $courier_stmt->fetch(PDO::FETCH_ASSOC);

if (!$courier) {
    header("Location: couriers.php");
    exit();
}

// Handle order assignment
if (isset($_POST['assign'])) {
    $order_id = $_POST['order_id'];
    
    $update_query = "UPDATE orders SET courier_id = :courier_id WHERE id = :order_id AND status = 'Processing'";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':courier_id', $courier_id);
    $update_stmt->bindParam(':order_id', $order_id);
    
    if ($update_stmt->execute()) {
        $success = "Order assigned successfully!";
    }
}

// Get available orders
$orders_query = "SELECT o.*, u.username as customer_name 
                 FROM orders o 
                 JOIN users u ON o.user_id = u.id 
                 WHERE o.status = 'Processing' AND (o.courier_id IS NULL OR o.courier_id = 0)";
$orders_stmt = $db->query($orders_query);
$available_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned orders for this courier
$assigned_query = "SELECT o.*, u.username as customer_name 
                   FROM orders o 
                   JOIN users u ON o.user_id = u.id 
                   WHERE o.courier_id = :courier_id AND o.status IN ('Processing', 'Shipped')";
$assigned_stmt = $db->prepare($assigned_query);
$assigned_stmt->bindParam(':courier_id', $courier_id);
$assigned_stmt->execute();
$assigned_orders = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Assign Orders to <?php echo $courier['name']; ?></title>
    <style>
        body { font-family: Arial; background: #f4f4f4; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .orders-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .orders-list { background: white; padding: 20px; border-radius: 10px; }
        .order-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
        .btn-assign { background: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 3px; cursor: pointer; }
        .back-btn { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .alert { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Assign Orders to <?php echo $courier['name']; ?></h1>
            <a href="couriers.php" class="back-btn">← Back to Couriers</a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="orders-grid">
            <div class="orders-list">
                <h2>Available Orders</h2>
                <?php foreach ($available_orders as $order): ?>
                <div class="order-card">
                    <p><strong>Order #<?php echo $order['id']; ?></strong></p>
                    <p>Customer: <?php echo $order['customer_name']; ?></p>
                    <p>Total: ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                    <form method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" name="assign" class="btn-assign">Assign to <?php echo $courier['name']; ?></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="orders-list">
                <h2>Assigned to <?php echo $courier['name']; ?></h2>
                <?php foreach ($assigned_orders as $order): ?>
                <div class="order-card">
                    <p><strong>Order #<?php echo $order['id']; ?></strong></p>
                    <p>Customer: <?php echo $order['customer_name']; ?></p>
                    <p>Status: <?php echo $order['status']; ?></p>
                    <p>Total: ₱<?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>