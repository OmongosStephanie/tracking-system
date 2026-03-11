<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Update order status
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    $query = "UPDATE orders SET status = :status WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':id', $order_id);
    
    if ($stmt->execute()) {
        logAdminAction('Update Order', "Updated order ID: $order_id to status: $status");
        $success = "Order status updated";
    }
}

// Get filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Get orders
$query = "SELECT o.*, u.full_name, u.email FROM orders o 
          JOIN users u ON o.user_id = u.id";
if ($status_filter) {
    $query .= " WHERE o.status = :status";
}
$query .= " ORDER BY o.order_date DESC";

$stmt = $db->prepare($query);
if ($status_filter) {
    $stmt->bindParam(':status', $status_filter);
}
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            color: #333;
        }

        .filters {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 20px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            color: #333;
            text-decoration: none;
        }

        .filter-btn.active {
            background: #4834d4;
            color: white;
            border-color: #4834d4;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .orders-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        .status-select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 3px;
        }

        .btn-update {
            padding: 5px 10px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-view {
            padding: 5px 10px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
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
            <a href="messages.php" class="menu-item"><i>✉️</i> <span>Messages</span></a>
            <a href="logs.php" class="menu-item"><i>📋</i> <span>Activity logs</span></a>
            <a href="settings.php" class="menu-item"><i>⚙️</i> <span>Settings</span></a>
             <a href="logs.php" class="menu-item"><i>📋</i> <span>Activity Logs</span></a>
            <a href="../logout.php" class="menu-item"><i>🚪</i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Manage Orders</h1>
            <div class="filters">
                <a href="orders.php" class="filter-btn <?php echo !$status_filter ? 'active' : ''; ?>">All</a>
                <a href="?status=pending" class="filter-btn <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?status=processing" class="filter-btn <?php echo $status_filter == 'processing' ? 'active' : ''; ?>">Processing</a>
                <a href="?status=shipped" class="filter-btn <?php echo $status_filter == 'shipped' ? 'active' : ''; ?>">Shipped</a>
                <a href="?status=delivered" class="filter-btn <?php echo $status_filter == 'delivered' ? 'active' : ''; ?>">Delivered</a>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="orders-table">
            <table>
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($order['full_name']); ?><br>
                            <small><?php echo htmlspecialchars($order['email']); ?></small>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($order['payment_method']); ?></td>
                        <td>
                            <form method="POST" style="display: flex; gap: 5px;">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" class="status-select">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] == 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="btn-update">✓</button>
                            </form>
                        </td>
                        <td>
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-view">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>