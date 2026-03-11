<?php
session_start();
require_once '../config/database.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

$database = new Database();
$db = $database->getConnection();

// Verify this order belongs to this courier
$query = "SELECT o.*, u.username as customer_name, u.address, u.phone 
          FROM orders o 
          JOIN users u ON o.user_id = u.id 
          WHERE o.id = :order_id AND o.courier_id = :courier_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':order_id', $order_id);
$stmt->bindParam(':courier_id', $_SESSION['courier_id']);
$stmt->execute();
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: dashboard.php");
    exit();
}

// Update order status to Shipped if it's Processing and tracking starts
if ($order['status'] == 'Processing' && isset($_GET['start'])) {
    $update_query = "UPDATE orders SET status = 'Shipped' WHERE id = :order_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':order_id', $order_id);
    $update_stmt->execute();
    $order['status'] = 'Shipped';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking #<?php echo $order_id; ?> - SecureApp</title>
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

        .back-btn {
            background: #6c757d;
        }

        .back-btn:hover {
            background: #5a6268 !important;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .tracking-header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .order-info h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 5px;
        }

        .order-info p {
            color: #666;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
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

        .map-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        #tracking-map {
            height: 400px;
            width: 100%;
            border-radius: 10px;
            z-index: 1;
        }

        .tracking-controls {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .control-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .control-card h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .location-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-label {
            color: #666;
        }

        .info-value {
            color: #333;
            font-weight: 600;
            font-family: monospace;
        }

        .tracking-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-start, .btn-stop, .btn-complete, .btn-refresh {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-start {
            background: #28a745;
            color: white;
        }

        .btn-start:hover:not(:disabled) {
            background: #218838;
        }

        .btn-stop {
            background: #dc3545;
            color: white;
        }

        .btn-stop:hover:not(:disabled) {
            background: #c82333;
        }

        .btn-complete {
            background: #007bff;
            color: white;
        }

        .btn-complete:hover:not(:disabled) {
            background: #0056b3;
        }

        .btn-refresh {
            background: #6c757d;
            color: white;
        }

        .btn-refresh:hover {
            background: #5a6268;
        }

        .btn-start:disabled,
        .btn-stop:disabled,
        .btn-complete:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .update-log {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .update-log h3 {
            color: #333;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .log-container {
            max-height: 200px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .log-entry {
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .log-time {
            color: #6c757d;
            font-family: monospace;
            min-width: 70px;
        }

        .log-message {
            color: #333;
            flex: 1;
        }

        .log-success {
            color: #28a745;
        }

        .log-error {
            color: #dc3545;
        }

        .live-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #dc3545;
            color: white;
            border-radius: 20px;
            font-size: 11px;
            animation: pulse 1.5s infinite;
            margin-left: 10px;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        @media (max-width: 768px) {
            .tracking-header {
                flex-direction: column;
                text-align: center;
            }
            
            .tracking-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="navbar-brand">
            <span>🚚</span> Live Tracking
        </a>
        <div class="navbar-menu">
            <span><?php echo htmlspecialchars($_SESSION['courier_name']); ?></span>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <!-- Tracking Header -->
        <div class="tracking-header">
            <div class="order-info">
                <h1>📍 Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></h1>
                <p><?php echo htmlspecialchars($order['address']); ?></p>
            </div>
            <div>
                <span class="status-badge status-<?php echo $order['status']; ?>">
                    <?php echo $order['status']; ?>
                </span>
                <?php if ($order['status'] == 'Shipped'): ?>
                    <span class="live-badge" id="liveBadge">● LIVE</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Map Container -->
        <div class="map-container">
            <div id="tracking-map"></div>
        </div>

        <!-- Tracking Controls -->
        <div class="tracking-controls">
            <div class="control-card">
                <h3>📍 Current Location</h3>
                <div class="location-info">
                    <div class="info-row">
                        <span class="info-label">Latitude:</span>
                        <span class="info-value" id="latitude">--</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Longitude:</span>
                        <span class="info-value" id="longitude">--</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Speed:</span>
                        <span class="info-value" id="speed">0 km/h</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Accuracy:</span>
                        <span class="info-value" id="accuracy">--</span>
                    </div>
                </div>
            </div>

            <div class="control-card">
                <h3>🎮 Tracking Controls</h3>
                <div class="tracking-buttons">
                    <button onclick="startTracking()" id="startBtn" class="btn-start" 
                            <?php echo ($order['status'] == 'Delivered') ? 'disabled' : ''; ?>>
                        ▶ Start Tracking
                    </button>
                    <button onclick="stopTracking()" id="stopBtn" class="btn-stop" style="display: none;">
                        ⏹ Stop Tracking
                    </button>
                    <button onclick="refreshLocation()" id="refreshBtn" class="btn-refresh">
                        🔄 Refresh
                    </button>
                </div>
                
                <?php if ($order['status'] != 'Delivered'): ?>
                <div class="tracking-buttons" style="margin-top: 10px;">
                    <button onclick="completeDelivery()" id="completeBtn" class="btn-complete" 
                            <?php echo ($order['status'] == 'Processing') ? 'disabled' : ''; ?>>
                        ✅ Mark as Delivered
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Log -->
        <div class="update-log">
            <h3>📋 Tracking Log</h3>
            <div class="log-container" id="logContainer">
                <div class="log-entry">
                    <span class="log-time"><?php echo date('h:i:s A'); ?></span>
                    <span class="log-message">📍 Ready to start tracking</span>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize variables
    let map;
    let marker;
    let watchId = null;
    let updateInterval = null;
    const orderId = <?php echo $order_id; ?>;
    const orderStatus = "<?php echo $order['status']; ?>";
    const logContainer = document.getElementById('logContainer');
    
    // Destination coordinates (will be geocoded from address)
    let destinationLat = null;
    let destinationLng = null;
    const destinationAddress = "<?php echo addslashes($order['address']); ?>";

    // Initialize map
    function initMap() {
        // Default to Manila
        const defaultLat = 14.5995;
        const defaultLng = 120.9842;
        
        map = L.map('tracking-map').setView([defaultLat, defaultLng], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Create custom marker icon
        const courierIcon = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color: #007bff; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3); animation: pulse 1.5s infinite;"></div>',
            iconSize: [24, 24],
            popupAnchor: [0, -12]
        });
        
        marker = L.marker([defaultLat, defaultLng], { icon: courierIcon }).addTo(map);
        
        // Geocode destination address
        if (destinationAddress) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(destinationAddress)}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        destinationLat = parseFloat(data[0].lat);
                        destinationLng = parseFloat(data[0].lon);
                        
                        // Add destination marker
                        const destIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: '<div style="background-color: #dc3545; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                            iconSize: [24, 24],
                            popupAnchor: [0, -12]
                        });
                        
                        L.marker([destinationLat, destinationLng], { icon: destIcon }).addTo(map)
                            .bindPopup('🏠 Destination');
                    }
                });
        }
    }

    // Add log entry
    function addLog(message, type = 'info') {
        const entry = document.createElement('div');
        entry.className = 'log-entry';
        
        const time = document.createElement('span');
        time.className = 'log-time';
        time.textContent = new Date().toLocaleTimeString();
        
        const msg = document.createElement('span');
        msg.className = 'log-message';
        if (type === 'success') msg.classList.add('log-success');
        if (type === 'error') msg.classList.add('log-error');
        msg.textContent = message;
        
        entry.appendChild(time);
        entry.appendChild(msg);
        
        logContainer.insertBefore(entry, logContainer.firstChild);
    }

    // Start tracking
    function startTracking() {
        if (!navigator.geolocation) {
            addLog('❌ Geolocation not supported', 'error');
            alert('Geolocation is not supported by your browser');
            return;
        }
        
        document.getElementById('startBtn').style.display = 'none';
        document.getElementById('stopBtn').style.display = 'block';
        addLog('📍 Starting GPS tracking...', 'success');
        
        // Watch position continuously
        watchId = navigator.geolocation.watchPosition(sendLocation, handleError, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 5000
        });
        
        // Also send every 5 seconds as backup
        updateInterval = setInterval(() => {
            navigator.geolocation.getCurrentPosition(sendLocation, handleError);
        }, 5000);
    }

    // Stop tracking
    function stopTracking() {
        if (watchId) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
        
        document.getElementById('startBtn').style.display = 'block';
        document.getElementById('stopBtn').style.display = 'none';
        addLog('⏹ Tracking stopped');
    }

    // Send location to server
    function sendLocation(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const speed = position.coords.speed || 0;
        const accuracy = position.coords.accuracy || 0;
        const bearing = position.coords.heading || 0;
        
        // Update display
        document.getElementById('latitude').textContent = lat.toFixed(6);
        document.getElementById('longitude').textContent = lng.toFixed(6);
        document.getElementById('speed').textContent = (speed * 3.6).toFixed(1) + ' km/h';
        document.getElementById('accuracy').textContent = '±' + accuracy.toFixed(0) + 'm';
        
        // Update marker
        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng]);
        
        // Draw route if destination exists
        if (destinationLat && destinationLng) {
            if (window.routeLine) map.removeLayer(window.routeLine);
            window.routeLine = L.polyline([[lat, lng], [destinationLat, destinationLng]], {
                color: '#007bff',
                weight: 3,
                opacity: 0.6,
                dashArray: '8, 8'
            }).addTo(map);
            
            // Fit bounds to show both markers
            const bounds = L.latLngBounds([[lat, lng], [destinationLat, destinationLng]]);
            map.fitBounds(bounds, { padding: [50, 50] });
        }
        
        // THIS IS THE FETCH CODE THAT SENDS LOCATION TO SERVER
        fetch('../update_courier_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                latitude: lat,
                longitude: lng,
                speed: speed,
                bearing: bearing
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addLog(`✅ Location sent: ${lat.toFixed(4)}, ${lng.toFixed(4)}`, 'success');
            } else {
                addLog(`❌ Failed to send location`, 'error');
            }
        })
        .catch(error => {
            addLog(`❌ Error: ${error.message}`, 'error');
        });
    }

    // Handle geolocation errors
    function handleError(error) {
        let message = '';
        switch(error.code) {
            case error.PERMISSION_DENIED:
                message = 'Location permission denied';
                break;
            case error.POSITION_UNAVAILABLE:
                message = 'Location unavailable';
                break;
            case error.TIMEOUT:
                message = 'Location request timeout';
                break;
        }
        addLog(`❌ ${message}`, 'error');
    }

    // Refresh current location
    function refreshLocation() {
        navigator.geolocation.getCurrentPosition(sendLocation, handleError);
    }

    // Complete delivery
    function completeDelivery() {
        if (confirm('Mark this order as delivered?')) {
            fetch('update_delivery_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order_id: orderId,
                    status: 'Delivered'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    addLog('✅ Delivery completed!', 'success');
                    stopTracking();
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 2000);
                }
            });
        }
    }

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        
        // Auto-start if order is Shipped
        if (orderStatus === 'Shipped') {
            startTracking();
        }
    });

    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        stopTracking();
    });
    </script>
</body>
</html>