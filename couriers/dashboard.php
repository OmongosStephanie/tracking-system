<?php
session_start();
require_once '../config/database.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get courier information
$courier_query = "SELECT * FROM couriers WHERE id = :id";
$courier_stmt = $db->prepare($courier_query);
$courier_stmt->bindParam(':id', $_SESSION['courier_id']);
$courier_stmt->execute();
$courier = $courier_stmt->fetch(PDO::FETCH_ASSOC);

// If courier not found, logout
if (!$courier) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// Get assigned deliveries - WITH phone and address from users table
$query = "SELECT o.*, u.username as customer_name, u.phone, u.address,
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.courier_id = :courier_id 
          ORDER BY 
            CASE 
                WHEN o.status = 'Processing' THEN 1
                WHEN o.status = 'Shipped' THEN 2
                WHEN o.status = 'Delivered' THEN 3
                ELSE 4
            END,
            o.order_date DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$stmt->execute();
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(CASE WHEN status = 'Processing' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'Shipped' THEN 1 END) as ongoing,
                COUNT(CASE WHEN status = 'Delivered' THEN 1 END) as completed,
                IFNULL(SUM(CASE WHEN status = 'Delivered' THEN total_amount ELSE 0 END), 0) as total_delivered_value
                FROM orders WHERE courier_id = :courier_id";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Dashboard - SecureApp</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .logout-btn {
            background: #dc3545;
        }

        .logout-btn:hover {
            background: #c82333 !important;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .welcome-text p {
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            background: #28a745;
            color: white;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
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

        .stat-info .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title h2 {
            color: #333;
            font-size: 22px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 8px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: #4834d4;
            color: white;
            border-color: #4834d4;
        }

        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            border-left: 5px solid;
        }

        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .order-card.status-Processing {
            border-left-color: #ffc107;
        }

        .order-card.status-Shipped {
            border-left-color: #007bff;
        }

        .order-card.status-Delivered {
            border-left-color: #28a745;
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
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
            font-size: 14px;
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
            border-top: 1px solid #f0f0f0;
        }

        .items-count {
            background: #f0f2f5;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 13px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-track {
            padding: 10px 18px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: 2px solid #1e7e34;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .btn-track:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .btn-view {
            padding: 10px 18px;
            background: #17a2b8;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: 2px solid #117a8b;
        }

        .btn-view:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }

        .empty-state h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 20px;
        }

        .empty-state p {
            color: #666;
        }

        /* Test section styles */
        .test-section {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .test-section h3 {
            color: #856404;
            margin-bottom: 15px;
        }

        .test-button {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-right: 10px;
            border: 2px solid #1e7e34;
            transition: all 0.3s;
        }

        .test-button:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .test-note {
            margin-top: 10px;
            color: #856404;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .welcome-section {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .orders-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-wrap: wrap;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-track, .btn-view {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span>🚚</span> Courier Panel
        </a>
        <div class="navbar-menu">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['courier_name'] ?? $courier['name']); ?>!</span>
            <a href="dashboard.php">Dashboard</a>
            <a href="track.php">Live Tracking</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
  
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="welcome-text">
                <h1>Hello, <?php echo htmlspecialchars($courier['name']); ?>! 👋</h1>
                <p>Here's your delivery summary for today</p>
            </div>
            <div>
                <span class="status-badge">
                    <?php echo ucfirst($courier['status'] ?? 'Active'); ?> • 
                    <?php echo $courier['vehicle_type'] ?? 'No vehicle'; ?>
                </span>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <h3>Pending Pickups</h3>
                    <div class="stat-number"><?php echo $stats['pending'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚚</div>
                <div class="stat-info">
                    <h3>Ongoing Deliveries</h3>
                    <div class="stat-number"><?php echo $stats['ongoing'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-info">
                    <h3>Completed</h3>
                    <div class="stat-number"><?php echo $stats['completed'] ?? 0; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3>Total Delivered Value</h3>
                    <div class="stat-number">₱<?php echo number_format($stats['total_delivered_value'] ?? 0, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- My Deliveries -->
        <div class="section-title">
            <h2>📋 My Deliveries</h2>
            <div class="filter-buttons">
                <button class="filter-btn active" onclick="filterOrders('all')">All</button>
                <button class="filter-btn" onclick="filterOrders('Processing')">Pending</button>
                <button class="filter-btn" onclick="filterOrders('Shipped')">Ongoing</button>
                <button class="filter-btn" onclick="filterOrders('Delivered')">Completed</button>
            </div>
        </div>

        <div class="orders-grid" id="ordersGrid">
            <?php if (empty($deliveries)): ?>
                <div class="empty-state">
                    <h3>No deliveries assigned yet</h3>
                    <p>You'll see your assigned deliveries here once the admin assigns them.</p>
                    <p style="margin-top: 15px; color: #28a745;">
                        <strong>But you can still test the tracking button above! 👆</strong>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($deliveries as $order): ?>
                    <div class="order-card status-<?php echo $order['status']; ?>" data-status="<?php echo $order['status']; ?>">
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
                                <span class="detail-value"><?php echo htmlspecialchars($order['address'] ?? 'No address provided'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['phone'] ?? 'No phone provided'); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Amount:</span>
                                <span class="detail-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                        <div class="order-footer">
                            <span class="items-count">📦 <?php echo $order['item_count'] ?? 0; ?> items</span>
                            <div class="action-buttons">
                                <?php if ($order['status'] != 'Delivered'): ?>
                                    <a href="track.php?order_id=<?php echo $order['id']; ?>" class="btn-track">
                                        🚚 Live Tracking
                                    </a>
                                <?php endif; ?>
                                <a href="../order_details.php?id=<?php echo $order['id']; ?>" class="btn-view">
                                    👁️ View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterOrders(status) {
            const cards = document.querySelectorAll('.order-card');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update active button
            buttons.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent.toLowerCase().includes(status.toLowerCase()) || 
                    (status === 'all' && btn.textContent === 'All')) {
                    btn.classList.add('active');
                }
            });
            
            // Filter cards
            cards.forEach(card => {
                if (status === 'all') {
                    card.style.display = 'block';
                } else {
                    const cardStatus = card.getAttribute('data-status');
                    if (cardStatus === status) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }

        // Auto refresh dashboard every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>