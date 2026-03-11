<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Update settings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST as $key => $value) {
        if ($key != 'submit') {
            $query = "UPDATE settings SET setting_value = :value WHERE setting_key = :key";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':value', $value);
            $stmt->bindParam(':key', $key);
            $stmt->execute();
        }
    }
    logAdminAction('Update Settings', 'Updated site settings');
    $success = "Settings updated successfully";
}

// Get current settings
$query = "SELECT * FROM settings";
$stmt = $db->query($query);
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin</title>
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
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
        }

        .settings-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            max-width: 600px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #4834d4;
        }

        .btn-save {
            padding: 12px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .btn-save:hover {
            background: #218838;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
            <a href="orders.php" class="menu-item"><i>🛒</i> <span>Orders</span></a>
            <a href="users.php" class="menu-item"><i>👥</i> <span>Users</span></a>
            <a href="messages.php" class="menu-item"><i>✉️</i> <span>Messages</span></a>
            <a href="settings.php" class="menu-item active"><i>⚙️</i> <span>Settings</span></a>
            <a href="logs.php" class="menu-item"><i>📋</i> <span>Activity logs</span></a>
            <a href="../logout.php" class="menu-item"><i>🚪</i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Site Settings</h1>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert"><?php echo $success; ?></div>
        <?php endif; ?>

        <div class="settings-form">
            <form method="POST">
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                </div>

                <div class="form-group">
                    <label>Site Email</label>
                    <input type="email" name="site_email" value="<?php echo htmlspecialchars($settings['site_email']); ?>">
                </div>

                <div class="form-group">
                    <label>Shipping Fee (₱)</label>
                    <input type="number" name="shipping_fee" value="<?php echo htmlspecialchars($settings['shipping_fee']); ?>" step="0.01">
                </div>

                <div class="form-group">
                    <label>Tax Rate (%)</label>
                    <input type="number" name="tax_rate" value="<?php echo htmlspecialchars($settings['tax_rate']); ?>" step="0.01">
                </div>

                <button type="submit" name="submit" class="btn-save">Save Settings</button>
            </form>
        </div>
    </div>
</body>
</html>