<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
// Get statistics
$stats = [];

// Total products
$query = "SELECT COUNT(*) as total FROM products";
$stmt = $db->query($query);
$stats['products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total orders
$query = "SELECT COUNT(*) as total FROM orders";
$stmt = $db->query($query);
$stats['orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total users
$query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
$stmt = $db->query($query);
$stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$query = "SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'";
$stmt = $db->query($query);
$stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Recent orders
$query = "SELECT o.*, u.full_name FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.order_date DESC LIMIT 5";
$stmt = $db->query($query);
$recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Low stock products
$query = "SELECT * FROM products WHERE stock <= 5 ORDER BY stock ASC LIMIT 5";
$stmt = $db->query($query);
$low_stock = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>
    <a href="admin/index.php">Admin Panel</a>
<?php endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SecureApp</title>
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

        /* Sidebar */
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

        .sidebar-header h2 {
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.7;
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
            transition: background 0.3s;
        }

        .menu-item:hover,
        .menu-item.active {
            background: #34495e;
        }

        .menu-item i {
            width: 20px;
        }

        /* Main Content */
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
        }

        .header h1 {
            color: #333;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            color: #666;
        }

        .logout-btn {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stat-info h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .chart-card h3 {
            color: #333;
            margin-bottom: 20px;
        }

        /* Tables */
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            color: #333;
        }

        .view-all {
            color: #4834d4;
            text-decoration: none;
            font-size: 14px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            color: #666;
            font-weight: 600;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending { background: #ffd700; color: #856404; }
        .status-processing { background: #17a2b8; color: white; }
        .status-shipped { background: #007bff; color: white; }
        .status-delivered { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }

        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }

        .stock-ok {
            color: #28a745;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2,
            .sidebar-header p,
            .menu-item span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .menu-item {
                justify-content: center;
            }
            
            .charts-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🔐 Admin</h2>
            <p><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item active">
                <i>📊</i> <span>Dashboard</span>
            </a>
            <a href="couriers.php" class="menu-item"><i>🚚</i> <span>Couriers</span></a>
            <a href="products.php" class="menu-item">
                <i>📦</i> <span>Products</span>
            </a>
            <a href="orders.php" class="menu-item">
                <i>🛒</i> <span>Orders</span>
            </a>
            <a href="users.php" class="menu-item">
                <i>👥</i> <span>Users</span>
            </a>
            <a href="messages.php" class="menu-item">
                <i>💬</i> <span>Messages</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i>⚙️</i> <span>Settings</span>
            </a>
            <a href="logs.php" class="menu-item">
                <i>📋</i> <span>Activity Logs</span>
            </a>
            <a href="../logout.php" class="menu-item" style="border-top: 1px solid #34495e; margin-top: 20px;">
                <i>🚪</i> <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="../logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Products</h3>
                    <div class="stat-number"><?php echo $stats['products']; ?></div>
                </div>
                <div class="stat-icon">📦</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Orders</h3>
                    <div class="stat-number"><?php echo $stats['orders']; ?></div>
                </div>
                <div class="stat-icon">🛒</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Users</h3>
                    <div class="stat-number"><?php echo $stats['users']; ?></div>
                </div>
                <div class="stat-icon">👥</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Total Revenue</h3>
                    <div class="stat-number">₱<?php echo number_format($stats['revenue'], 2); ?></div>
                </div>
                <div class="stat-icon">💰</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <h3>Recent Orders</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                            <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="chart-card">
                <h3>Low Stock Alert</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($low_stock as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td class="<?php echo $product['stock'] == 0 ? 'stock-low' : ($product['stock'] <= 5 ? 'stock-low' : 'stock-ok'); ?>">
                                <?php echo $product['stock']; ?>
                            </td>
                            <td>
                                <?php if ($product['stock'] == 0): ?>
                                    <span class="status-badge status-cancelled">Out of Stock</span>
                                <?php elseif ($product['stock'] <= 5): ?>
                                    <span class="status-badge status-pending">Low Stock</span>
                                <?php else: ?>
                                    <span class="status-badge status-delivered">In Stock</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="table-card">
            <div class="table-header">
                <h3>Quick Actions</h3>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="add_product.php" style="text-decoration: none;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; color: #333; transition: transform 0.3s;">
                        <div style="font-size: 30px; margin-bottom: 10px;">➕</div>
                        <div>Add New Product</div>
                    </div>
                </a>
                <a href="orders.php?status=pending" style="text-decoration: none;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; color: #333; transition: transform 0.3s;">
                        <div style="font-size: 30px; margin-bottom: 10px;">⏳</div>
                        <div>View Pending Orders</div>
                    </div>
                </a>
                <a href="users.php" style="text-decoration: none;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; color: #333; transition: transform 0.3s;">
                        <div style="font-size: 30px; margin-bottom: 10px;">👥</div>
                        <div>Manage Users</div>
                    </div>
                </a>
                <a href="reports.php" style="text-decoration: none;">
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; color: #333; transition: transform 0.3s;">
                        <div style="font-size: 30px; margin-bottom: 10px;">📊</div>
                        <div>Sales Reports</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>