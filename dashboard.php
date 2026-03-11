<?php
session_start();
require_once 'config/database.php'; // I-add ni para magamit ang database connection

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get cart count para sa navbar
$cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->bindParam(':user_id', $_SESSION['user_id']);
$cart_stmt->execute();
$cart_count = $cart_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Check if we need to show welcome message
$show_welcome = isset($_SESSION['show_welcome']) ? $_SESSION['show_welcome'] : false;
if ($show_welcome) {
    unset($_SESSION['show_welcome']); // Clear it after displaying
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Your App</title>
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 24px;
            font-weight: bold;
        }

        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
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

        .cart-icon {
            position: relative;
        }

        .cart-count {
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

        /* Welcome Notification */
        .welcome-notification {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .welcome-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome-icon {
            font-size: 30px;
            background: rgba(255,255,255,0.2);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .welcome-text h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .welcome-text p {
            font-size: 14px;
            opacity: 0.9;
        }

        .close-notification {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.3s;
        }

        .close-notification:hover {
            background: rgba(255,255,255,0.3);
        }

        .welcome-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .welcome-card h1 {
            color: #333;
            margin-bottom: 15px;
        }

        .welcome-card p {
            color: #666;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 40px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #4834d4;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .info-item {
            display: flex;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .info-label {
            width: 120px;
            font-weight: 600;
            color: #666;
        }

        .info-value {
            flex: 1;
            color: #333;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .navbar-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-notification {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .welcome-content {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">🔐 SecureApp</div>
        <div class="navbar-menu">
            <a href="dashboard.php">Dashboard</a>
            <a href="product.php">Products</a>
            <a href="cart.php" class="cart-icon">
                🛒 Cart
                <?php if ($cart_count > 0): ?>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="profile.php">Profile</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if ($show_welcome): ?>
        <div class="welcome-notification" id="welcomeNotification">
            <div class="welcome-content">
                <div class="welcome-icon">🎉</div>
                <div class="welcome-text">
                    <h3>Welcome to SecureApp, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h3>
                    <p>Your account has been successfully created. We're excited to have you on board!</p>
                </div>
            </div>
            <button class="close-notification" onclick="document.getElementById('welcomeNotification').style.display='none'">✕</button>
        </div>
        <?php endif; ?>

        <div class="welcome-card">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h1>
            <p>You are logged in as <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            <p>Last login: <?php echo date('F j, Y, g:i a'); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-number">12</div>
                <div class="stat-label">Active Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-number">5</div>
                <div class="stat-label">Unread Messages</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number">4.8</div>
                <div class="stat-label">Rating</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number">30</div>
                <div class="stat-label">Days Active</div>
            </div>
        </div>

        <div class="info-card">
            <h2 style="margin-bottom: 20px; color: #333;">Your Information</h2>
            <div class="info-item">
                <span class="info-label">User ID</span>
                <span class="info-value"><?php echo $_SESSION['user_id']; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Username</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Full Name</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>
                <span class="info-value"><?php echo htmlspecialchars($_SESSION['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Account Status</span>
                <span class="info-value"><span style="color: #28a745;">✓ Active</span></span>
            </div>
            <div class="info-item">
                <span class="info-label">Member Since</span>
                <span class="info-value"><?php echo date('F j, Y'); ?></span>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide notification after 5 seconds
        setTimeout(function() {
            var notification = document.getElementById('welcomeNotification');
            if (notification) {
                notification.style.transition = 'opacity 0.5s';
                notification.style.opacity = '0';
                setTimeout(function() {
                    notification.style.display = 'none';
                }, 500);
            }
        }, 5000);
    </script>
</body>
</html>