<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$courier_id = isset($_GET['courier_id']) ? $_GET['courier_id'] : 0;
$success = '';
$error = '';

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
if (isset($_POST['assign_order'])) {
    $order_id = $_POST['order_id'];
    
    // Check if order is available
    $check_query = "SELECT status, courier_id FROM orders WHERE id = :order_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':order_id', $order_id);
    $check_stmt->execute();
    $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order && $order['status'] == 'Processing' && ($order['courier_id'] == 0 || $order['courier_id'] == null)) {
        // Assign order to courier
        $update_query = "UPDATE orders SET courier_id = :courier_id WHERE id = :order_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':courier_id', $courier_id);
        $update_stmt->bindParam(':order_id', $order_id);
        
        if ($update_stmt->execute()) {
            // Add to tracking history
            $track_query = "INSERT INTO order_tracking (order_id, status, description) 
                            VALUES (:order_id, 'Processing', 'Assigned to courier: " . $courier['name'] . "')";
            $track_stmt = $db->prepare($track_query);
            $track_stmt->bindParam(':order_id', $order_id);
            $track_stmt->execute();
            
            $success = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " assigned to " . $courier['name'] . " successfully!";
        } else {
            $error = "Failed to assign order.";
        }
    } else {
        $error = "Order is not available for assignment.";
    }
}

// Handle unassign order
if (isset($_GET['unassign'])) {
    $order_id = $_GET['unassign'];
    
    $update_query = "UPDATE orders SET courier_id = NULL WHERE id = :order_id AND courier_id = :courier_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':order_id', $order_id);
    $update_stmt->bindParam(':courier_id', $courier_id);
    
    if ($update_stmt->execute()) {
        $success = "Order #" . str_pad($order_id, 6, '0', STR_PAD_LEFT) . " unassigned successfully!";
    } else {
        $error = "Failed to unassign order.";
    }
}

// Get available orders (Processing status and no courier assigned)
$available_query = "SELECT o.*, u.username as customer_name, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                   FROM orders o 
                   JOIN users u ON o.user_id = u.id 
                   WHERE o.status = 'Processing' AND (o.courier_id IS NULL OR o.courier_id = 0)
                   ORDER BY o.order_date ASC";
$available_stmt = $db->prepare($available_query);
$available_stmt->execute();
$available_orders = $available_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get assigned orders for this courier
$assigned_query = "SELECT o.*, u.username as customer_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                   FROM orders o 
                   JOIN users u ON o.user_id = u.id 
                   WHERE o.courier_id = :courier_id AND o.status IN ('Processing', 'Shipped')
                   ORDER BY 
                     CASE 
                       WHEN o.status = 'Processing' THEN 1
                       WHEN o.status = 'Shipped' THEN 2
                       ELSE 3
                     END,
                     o.order_date DESC";
$assigned_stmt = $db->prepare($assigned_query);
$assigned_stmt->bindParam(':courier_id', $courier_id);
$assigned_stmt->execute();
$assigned_orders = $assigned_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get completed deliveries
$completed_query = "SELECT o.*, u.username as customer_name,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
                    FROM orders o 
                    JOIN users u ON o.user_id = u.id 
                    WHERE o.courier_id = :courier_id AND o.status = 'Delivered'
                    ORDER BY o.delivered_at DESC
                    LIMIT 10";
$completed_stmt = $db->prepare($completed_query);
$completed_stmt->bindParam(':courier_id', $courier_id);
$completed_stmt->execute();
$completed_orders = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Orders to <?php echo htmlspecialchars($courier['name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            display: flex;
        }

        .sidebar {
            width: 280px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            text-decoration: none;
        }

        .menu-item:hover,
        .menu-item.active {
            background: #34495e;
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .courier-info {
            display: flex;
            align-items: center;
            gap: 20px;
            background: #f8f9fa;
            padding: 15px 25px;
            border-radius: 50px;
        }

        .courier-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-active {
            background: #28a745;
            color: white;
        }

        .status-busy {
            background: #ffc107;
            color: #333;
        }

        .status-inactive {
            background: #dc3545;
            color: white;
        }

        .back-btn {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px 20px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: #f0f2f5;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-info h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .orders-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            color: #333;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header .badge {
            background: #007bff;
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .order-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid;
        }

        .order-card.available {
            border-left-color: #28a745;
        }

        .order-card.assigned {
            border-left-color: #007bff;
        }

        .order-card.completed {
            border-left-color: #6c757d;
            opacity: 0.8;
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .order-id {
            font-size: 18px;
            font-weight: bold;
            color: #4834d4;
        }

        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-Processing {
            background: #ffc107;
            color: #333;
        }

        .status-Shipped {
            background: #007bff;
            color: white;
        }

        .status-Delivered {
            background: #28a745;
            color: white;
        }

        .order-details {
            margin: 15px 0;
        }

        .detail-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 13px;
        }

        .detail-label {
            width: 80px;
            color: #666;
        }

        .detail-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }

        .order-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .items-count {
            background: #e9ecef;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-assign, .btn-unassign, .btn-view {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.3s;
        }

        .btn-assign {
            background: #28a745;
            color: white;
        }

        .btn-assign:hover {
            background: #218838;
        }

        .btn-unassign {
            background: #dc3545;
            color: white;
        }

        .btn-unassign:hover {
            background: #c82333;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
            grid-column: 1 / -1;
        }

        .empty-state p {
            margin-top: 10px;
            color: #999;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2,
            .menu-item span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .courier-info {
                flex-direction: column;
                border-radius: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🔐 Admin</h2>
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item"><i>📊</i> <span>Dashboard</span></a>
            <a href="products.php" class="menu-item"><i>📦</i> <span>Products</span></a>
            <a href="orders.php" class="menu-item active"><i>🛒</i> <span>Orders</span></a>
            <a href="users.php" class="menu-item"><i>👥</i> <span>Users</span></a>
            <a href="couriers.php" class="menu-item"><i>🚚</i> <span>Couriers</span></a>
            <a href="../logout.php" class="menu-item"><i>🚪</i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Assign Orders to Courier</h1>
                <a href="couriers.php" class="back-btn" style="margin-top: 10px; display: inline-block;">
                    ← Back to Couriers
                </a>
            </div>
            <div class="courier-info">
                <div>
                    <strong style="font-size: 18px;">🚚 <?php echo htmlspecialchars($courier['name']); ?></strong>
                    <div style="margin-top: 5px; color: #666;">
                        <?php echo htmlspecialchars($courier['vehicle_type'] ?? 'No vehicle'); ?> • 
                        <?php echo htmlspecialchars($courier['phone'] ?? 'No phone'); ?>
                    </div>
                </div>
                <span class="courier-status status-<?php echo $courier['status']; ?>">
                    <?php echo ucfirst($courier['status']); ?>
                </span>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <h3>Available Orders</h3>
                    <div class="stat-number"><?php echo count($available_orders); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚚</div>
                <div class="stat-info">
                    <h3>Active Deliveries</h3>
                    <div class="stat-number"><?php echo count($assigned_orders); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3>Completed</h3>
                    <div class="stat-number"><?php echo count($completed_orders); ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3>Total Earnings</h3>
                    <div class="stat-number">
                        ₱<?php 
                        $total = 0;
                        foreach ($completed_orders as $order) {
                            $total += $order['total_amount'];
                        }
                        echo number_format($total, 2);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Orders Section -->
        <div class="orders-section">
            <div class="section-header">
                <h2>
                    📋 Available Orders
                    <span class="badge">Ready to assign</span>
                </h2>
            </div>

            <?php if (empty($available_orders)): ?>
                <div class="empty-state">
                    <p>No available orders at the moment.</p>
                    <small>All orders are either assigned or already being delivered.</small>
                </div>
            <?php else: ?>
                <div class="orders-grid">
                    <?php foreach ($available_orders as $order): ?>
                        <div class="order-card available">
                            <div class="order-header">
                                <span class="order-id">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                                <span class="order-status status-<?php echo $order['status']; ?>">
                                    <?php echo $order['status']; ?>
                                </span>
                            </div>
                            
                            <div class="order-details">
                                <div class="detail-row">
                                    <span class="detail-label">Customer:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Address:</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($order['address'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Amount:</span>
                                    <span class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Date:</span>
                                    <span class="detail-value"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></span>
                                </div>
                            </div>

                            <div class="order-footer">
                                <span class="items-count">📦 <?php echo $order['item_count']; ?> items</span>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="assign_order" class="btn-assign" 
                                            onclick="return confirm('Assign this order to <?php echo htmlspecialchars($courier['name']); ?>?')">
                                        📋 Assign to Courier
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assigned Orders Section -->
        <?php if (!empty($assigned_orders)): ?>
        <div class="orders-section">
            <div class="section-header">
                <h2>
                    🚚 Active Deliveries for <?php echo htmlspecialchars($courier['name']); ?>
                    <span class="badge" style="background: #28a745;"><?php echo count($assigned_orders); ?> active</span>
                </h2>
            </div>

            <div class="orders-grid">
                <?php foreach ($assigned_orders as $order): ?>
                    <div class="order-card assigned">
                        <div class="order-header">
                            <span class="order-id">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            <span class="order-status status-<?php echo $order['status']; ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-row">
                                <span class="detail-label">Customer:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['address'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount:</span>
                                <span class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                        <div class="order-footer">
                            <span class="items-count">📦 <?php echo $order['item_count']; ?> items</span>
                            <div class="action-buttons">
                                <a href="?unassign=<?php echo $order['id']; ?>&courier_id=<?php echo $courier_id; ?>" 
                                   class="btn-unassign"
                                   onclick="return confirm('Unassign this order from <?php echo htmlspecialchars($courier['name']); ?>?')">
                                    🔄 Unassign
                                </a>
                                <a href="../order_details.php?id=<?php echo $order['id']; ?>" class="btn-view" target="_blank">
                                    👁️ View
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Completed Deliveries Section -->
        <?php if (!empty($completed_orders)): ?>
        <div class="orders-section">
            <div class="section-header">
                <h2>
                    ✅ Recent Deliveries
                    <span class="badge" style="background: #6c757d;">Last 10</span>
                </h2>
            </div>

            <div class="orders-grid">
                <?php foreach ($completed_orders as $order): ?>
                    <div class="order-card completed">
                        <div class="order-header">
                            <span class="order-id">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></span>
                            <span class="order-status status-Delivered">
                                Delivered
                            </span>
                        </div>
                        
                        <div class="order-details">
                            <div class="detail-row">
                                <span class="detail-label">Customer:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount:</span>
                                <span class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Delivered:</span>
                                <span class="detail-value"><?php echo date('M d, Y', strtotime($order['delivered_at'] ?? $order['order_date'])); ?></span>
                            </div>
                        </div>

                        <div class="order-footer">
                            <span class="items-count">📦 <?php echo $order['item_count']; ?> items</span>
                            <a href="../order_details.php?id=<?php echo $order['id']; ?>" class="btn-view" target="_blank">
                                👁️ View
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>