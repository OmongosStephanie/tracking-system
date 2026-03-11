<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get order ID from URL
$order_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Get order details with items
$query = "SELECT o.*, u.username, u.email 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.id = :order_id AND o.user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get order items
$items_query = "SELECT oi.*, p.name, p.image 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.id 
                WHERE oi.order_id = :order_id";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':order_id', $order_id);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tracking history
$tracking_query = "SELECT * FROM order_tracking 
                   WHERE order_id = :order_id 
                   ORDER BY created_at DESC";
$tracking_stmt = $db->prepare($tracking_query);
$tracking_stmt->bindParam(':order_id', $order_id);
$tracking_stmt->execute();
$tracking_history = $tracking_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Helper function for time ago
function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    
    if ($seconds < 60) {
        return "just now";
    } else if ($minutes < 60) {
        return $minutes . " minute" . ($minutes != 1 ? 's' : '') . " ago";
    } else {
        return date('M d, h:i A', $time_ago);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo $order_id; ?> - SecureApp</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            color: #333;
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
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .order-status-bar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tracking-number {
            display: inline-block;
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 18px;
            margin: 10px 0;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
        }

        .status-pending { background: #ffc107; color: #333; }
        .status-processing { background: #17a2b8; color: white; }
        .status-shipped { background: #007bff; color: white; }
        .status-delivered { background: #28a745; color: white; }
        .status-cancelled { background: #dc3545; color: white; }

        .tracking-progress {
            display: flex;
            justify-content: space-between;
            margin: 40px 0 20px;
            position: relative;
        }

        .tracking-progress::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            width: 100%;
            height: 3px;
            background: #e1e1e1;
            z-index: 1;
        }

        .progress-step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background: white;
            border: 3px solid #e1e1e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 18px;
            transition: all 0.3s;
        }

        .step-icon.completed {
            background: #28a745;
            border-color: #28a745;
            color: white;
        }

        .step-icon.active {
            border-color: #007bff;
            background: #007bff;
            color: white;
        }

        .step-label {
            font-size: 14px;
            color: #666;
        }

        .step-date {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        .map-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .map-container h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        #tracking-map {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            z-index: 1;
        }

        .map-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .location-detail {
            display: flex;
            flex-direction: column;
        }

        .location-detail .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .location-detail .value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .location-detail .status {
            font-size: 12px;
            margin-top: 4px;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            margin-top: 8px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }

        .map-controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .map-btn {
            padding: 8px 15px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .map-btn:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .live-badge {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 12px;
            animation: pulse 1.5s infinite;
            margin-left: 10px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .order-details-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .order-items {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .order-items h2, .order-info h2, .tracking-history h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            overflow: hidden;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            flex: 1;
        }

        .item-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .item-price {
            color: #4834d4;
            font-weight: 600;
        }

        .item-quantity {
            color: #666;
            font-size: 14px;
        }

        .item-subtotal {
            text-align: right;
            font-weight: 600;
            color: #333;
        }

        .order-info {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .info-group {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .total-amount {
            font-size: 24px;
            color: #4834d4;
            font-weight: bold;
        }

        .tracking-history {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-top: 30px;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e1e1e1;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 25px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-dot {
            position: absolute;
            left: -30px;
            width: 20px;
            height: 20px;
            background: white;
            border: 3px solid;
            border-radius: 50%;
            z-index: 1;
        }

        .timeline-dot.pending { border-color: #ffc107; }
        .timeline-dot.processing { border-color: #17a2b8; }
        .timeline-dot.shipped { border-color: #007bff; }
        .timeline-dot.delivered { border-color: #28a745; }
        .timeline-dot.cancelled { border-color: #dc3545; }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .timeline-status {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .timeline-location {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .timeline-description {
            color: #999;
            font-size: 13px;
        }

        .timeline-date {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }

        .current-location {
            background: #e8f4fd;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .current-location h3 {
            color: #007bff;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .current-location p {
            color: #333;
        }

        .print-btn {
            padding: 10px 20px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 20px;
        }

        .print-btn:hover {
            background: #3c2ba5;
        }

        @media (max-width: 768px) {
            .order-details-grid {
                grid-template-columns: 1fr;
            }
            
            .tracking-progress {
                flex-direction: column;
                gap: 20px;
            }
            
            .tracking-progress::before {
                display: none;
            }
            
            .progress-step {
                display: flex;
                align-items: center;
                gap: 15px;
                text-align: left;
            }
            
            .step-icon {
                margin: 0;
            }

            .map-info {
                grid-template-columns: 1fr;
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
            <h1>Order Details #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></h1>
            <a href="orders.php" class="back-btn">← Back to Orders</a>
        </div>

        <div class="order-status-bar">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                <div>
                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
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
                    <?php if (!empty($order['tracking_number'])): ?>
                        <div class="tracking-number">
                            📦 Tracking #: <?php echo $order['tracking_number']; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <button onclick="window.print()" class="print-btn">🖨️ Print Details</button>
                </div>
            </div>

            <!-- Tracking Progress -->
            <div class="tracking-progress">
                <?php
                $steps = ['Pending', 'Processing', 'Shipped', 'Delivered'];
                $status_index = array_search($order['status'], $steps);
                if ($status_index === false) $status_index = -1;
                
                foreach ($steps as $index => $step):
                    $step_status = '';
                    if ($index < $status_index) $step_status = 'completed';
                    elseif ($index == $status_index) $step_status = 'active';
                ?>
                    <div class="progress-step">
                        <div class="step-icon <?php echo $step_status; ?>">
                            <?php
                            if ($step == 'Pending') echo '⏳';
                            elseif ($step == 'Processing') echo '⚙️';
                            elseif ($step == 'Shipped') echo '🚚';
                            elseif ($step == 'Delivered') echo '✅';
                            ?>
                        </div>
                        <div class="step-label"><?php echo $step; ?></div>
                        <?php if ($index == $status_index && !empty($order['estimated_delivery'])): ?>
                            <div class="step-date">Est: <?php echo date('M d, Y', strtotime($order['estimated_delivery'])); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Current Location (if shipped) -->
            <?php if ($order['status'] == 'Shipped' && !empty($order['current_location'])): ?>
                <div class="current-location">
                    <h3>📍 Current Location</h3>
                    <p><?php echo htmlspecialchars($order['current_location']); ?></p>
                    <small>Last updated: <?php echo date('M d, Y h:i A', strtotime($order['last_update'])); ?></small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Live Courier Tracking Map -->
        <?php if ($order['status'] == 'Shipped' || $order['status'] == 'Processing'): ?>
        <div class="map-container">
            <h2>
                🚚 Live Courier Tracking
                <span class="live-badge" id="live-badge">● LIVE</span>
            </h2>
            <div id="tracking-map"></div>
            
            <div class="map-info">
                <div class="location-detail">
                    <div class="label">📍 Courier Location</div>
                    <div class="value" id="courier-location-name">
                        <?php echo !empty($order['courier_lat']) ? 'Moving' : 'Waiting for update'; ?>
                    </div>
                    <div class="status" id="movement-status">
                        <?php if (!empty($order['courier_last_update'])): ?>
                            Last update: <?php echo timeAgo($order['courier_last_update']); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="location-detail">
                    <div class="label">🏠 Destination</div>
                    <div class="value"><?php echo htmlspecialchars($order['address'] ?? 'Address not available'); ?></div>
                </div>
                <div class="location-detail">
                    <div class="label">⏱️ Estimated Arrival</div>
                    <div class="value" id="eta-display">Calculating...</div>
                </div>
                <div class="location-detail">
                    <div class="label">📊 Progress</div>
                    <div class="value" id="progress-display">--%</div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                    </div>
                </div>
            </div>

            <div class="map-controls">
                <button class="map-btn" onclick="centerOnCourier()">📍 Follow Courier</button>
                <button class="map-btn" onclick="centerOnDestination()">🏠 Show Destination</button>
                <button class="map-btn" onclick="showFullRoute()">🛣️ Full Route</button>
            </div>
        </div>

        <script>
        let map;
        let courierMarker;
        let destinationMarker;
        let routeLine;
        let updateInterval;
        let courierIcon;
        let destinationIcon;

        // Custom icons
        courierIcon = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color: #007bff; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); animation: pulse 1.5s infinite;"></div>',
            iconSize: [24, 24],
            popupAnchor: [0, -12]
        });

        destinationIcon = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color: #dc3545; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
            iconSize: [24, 24],
            popupAnchor: [0, -12]
        });

        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            startTracking();
        });

        function initMap() {
            // Default to Philippines
            const defaultLat = 14.5995;
            const defaultLng = 120.9842;
            
            map = L.map('tracking-map').setView([defaultLat, defaultLng], 12);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add destination marker if we have address
            <?php if (!empty($order['address'])): ?>
            geocodeAddress("<?php echo addslashes($order['address']); ?>", function(lat, lng) {
                destinationMarker = L.marker([lat, lng], { icon: destinationIcon }).addTo(map)
                    .bindPopup('🏠 Destination');
                
                // Store destination for later use
                window.destinationLat = lat;
                window.destinationLng = lng;
            });
            <?php endif; ?>
            
            // Initial courier location if available
            <?php if (!empty($order['courier_lat']) && !empty($order['courier_lng'])): ?>
            updateCourierLocation(
                <?php echo $order['courier_lat']; ?>,
                <?php echo $order['courier_lng']; ?>,
                <?php echo $order['courier_bearing'] ?? 0; ?>
            );
            <?php endif; ?>

            // Track user interaction with map
            map.on('movestart', function() {
                window.userInteracted = true;
            });
        }

        function startTracking() {
            // Update every 5 seconds
            updateInterval = setInterval(fetchCourierLocation, 5000);
        }

        function fetchCourierLocation() {
            fetch(`get_courier_location.php?order_id=<?php echo $order_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateCourierLocation(data.lat, data.lng, data.bearing);
                        updateETA(data.eta_minutes);
                        updateMovementStatus(data.seconds_ago, data.is_moving);
                        
                        // Update progress if we have destination
                        if (window.destinationLat && window.destinationLng) {
                            updateProgress(data.lat, data.lng);
                        }
                        
                        // Blink the live badge
                        document.getElementById('live-badge').style.animation = 'none';
                        setTimeout(() => {
                            document.getElementById('live-badge').style.animation = 'pulse 1.5s infinite';
                        }, 100);
                    }
                })
                .catch(error => console.log('Tracking update failed'));
        }

        function updateCourierLocation(lat, lng, bearing) {
            // Remove old marker
            if (courierMarker) {
                map.removeLayer(courierMarker);
            }
            
            // Add new marker
            courierMarker = L.marker([lat, lng], { 
                icon: courierIcon,
                rotationAngle: bearing || 0
            }).addTo(map)
            .bindPopup('📍 Courier is here');
            
            // Update location name
            document.getElementById('courier-location-name').textContent = 
                `Moving at ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            
            // Center map if not manually moved
            if (!window.userInteracted) {
                map.panTo([lat, lng]);
            }
            
            // Draw route to destination
            if (window.destinationLat && window.destinationLng) {
                drawRoute(lat, lng, window.destinationLat, window.destinationLng);
            }
        }

        function drawRoute(startLat, startLng, endLat, endLng) {
            if (routeLine) {
                map.removeLayer(routeLine);
            }
            
            routeLine = L.polyline([[startLat, startLng], [endLat, endLng]], {
                color: '#007bff',
                weight: 4,
                opacity: 0.6,
                dashArray: '8, 8'
            }).addTo(map);
        }

        function updateETA(minutes) {
            const etaDisplay = document.getElementById('eta-display');
            if (minutes) {
                if (minutes < 1) {
                    etaDisplay.textContent = 'Less than 1 minute';
                } else if (minutes < 60) {
                    etaDisplay.textContent = `${minutes} minutes`;
                } else {
                    const hours = Math.floor(minutes / 60);
                    const mins = minutes % 60;
                    etaDisplay.textContent = `${hours} hour${hours > 1 ? 's' : ''} ${mins} min`;
                }
            } else {
                etaDisplay.textContent = 'Calculating...';
            }
        }

        function updateProgress(currentLat, currentLng) {
            if (!window.destinationLat || !window.destinationLng) return;
            
            // Calculate total distance and remaining distance
            const totalDistance = calculateDistance(
                window.destinationLat, window.destinationLng,
                14.5995, 120.9842 // Origin coordinates (you'd store these)
            );
            
            const remainingDistance = calculateDistance(
                currentLat, currentLng,
                window.destinationLat, window.destinationLng
            );
            
            const progress = ((totalDistance - remainingDistance) / totalDistance) * 100;
            const progressPercent = Math.min(100, Math.max(0, Math.round(progress)));
            
            document.getElementById('progress-display').textContent = `${progressPercent}%`;
            document.getElementById('progress-fill').style.width = `${progressPercent}%`;
        }

        function calculateDistance(lat1, lng1, lat2, lng2) {
            const R = 6371; // Earth's radius in km
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLng/2) * Math.sin(dLng/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function updateMovementStatus(secondsAgo, isMoving) {
            const statusEl = document.getElementById('movement-status');
            if (secondsAgo < 30) {
                statusEl.innerHTML = '🟢 Online • Updated just now';
                statusEl.style.color = '#28a745';
            } else if (secondsAgo < 60) {
                statusEl.innerHTML = '🟡 Online • Updated ' + secondsAgo + 's ago';
                statusEl.style.color = '#ffc107';
            } else {
                statusEl.innerHTML = '🔴 Offline • No recent updates';
                statusEl.style.color = '#dc3545';
            }
        }

        function geocodeAddress(address, callback) {
            // Use Nominatim for free geocoding
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        callback(parseFloat(data[0].lat), parseFloat(data[0].lon));
                    }
                });
        }

        function centerOnCourier() {
            if (courierMarker) {
                const pos = courierMarker.getLatLng();
                map.setView([pos.lat, pos.lng], 15);
            }
        }

        function centerOnDestination() {
            if (destinationMarker) {
                const pos = destinationMarker.getLatLng();
                map.setView([pos.lat, pos.lng], 15);
            }
        }

        function showFullRoute() {
            if (courierMarker && destinationMarker) {
                const courierPos = courierMarker.getLatLng();
                const destPos = destinationMarker.getLatLng();
                const bounds = L.latLngBounds([courierPos, destPos]);
                map.fitBounds(bounds, { padding: [50, 50] });
            }
        }

        // Clean up interval on page unload
        window.addEventListener('beforeunload', function() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
        </script>
        <?php endif; ?>

        <div class="order-details-grid">
            <!-- Order Items -->
            <div class="order-items">
                <h2>Order Items</h2>
                <?php 
                $subtotal = 0;
                foreach ($items as $item): 
                    $subtotal += $item['price'] * $item['quantity'];
                ?>
                    <div class="order-item">
                        <div class="item-image">
                            <?php if (!empty($item['image']) && file_exists('uploads/products/' . $item['image'])): ?>
                                <img src="uploads/products/<?php echo $item['image']; ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php else: ?>
                                📦
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                            <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                            <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                        </div>
                        <div class="item-subtotal">
                            ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Order Information -->
            <div class="order-info">
                <h2>Order Information</h2>
                
                <div class="info-group">
                    <div class="info-label">Order Date</div>
                    <div class="info-value"><?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?></div>
                </div>

                <?php if (!empty($order['address'])): ?>
                <div class="info-group">
                    <div class="info-label">Shipping Address</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($order['address'])); ?></div>
                </div>
                <?php endif; ?>

                <div class="info-group">
                    <div class="info-label">Contact Information</div>
                    <div class="info-value"><?php echo htmlspecialchars($order['username']); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($order['email']); ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Payment Method</div>
                    <div class="info-value"><?php echo $order['payment_method'] ?? 'Cash on Delivery'; ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Subtotal</div>
                    <div class="info-value">₱<?php echo number_format($subtotal, 2); ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Shipping Fee</div>
                    <div class="info-value">₱<?php echo number_format($order['shipping_fee'] ?? 50, 2); ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Total Amount</div>
                    <div class="total-amount">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Tracking History -->
        <?php if (!empty($tracking_history)): ?>
        <div class="tracking-history">
            <h2>Tracking History</h2>
            <div class="timeline">
                <?php foreach ($tracking_history as $track): ?>
                    <div class="timeline-item">
                        <div class="timeline-dot <?php echo strtolower($track['status']); ?>"></div>
                        <div class="timeline-content">
                            <div class="timeline-status">
                                <?php 
                                $history_icons = [
                                    'Pending' => '⏳',
                                    'Processing' => '⚙️',
                                    'Shipped' => '🚚',
                                    'Delivered' => '✅',
                                    'Cancelled' => '❌'
                                ];
                                echo ($history_icons[$track['status']] ?? '📦') . ' ' . $track['status']; 
                                ?>
                            </div>
                            <?php if (!empty($track['location'])): ?>
                                <div class="timeline-location">📍 <?php echo htmlspecialchars($track['location']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($track['description'])): ?>
                                <div class="timeline-description">📝 <?php echo htmlspecialchars($track['description']); ?></div>
                            <?php endif; ?>
                            <div class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($track['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>