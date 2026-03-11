<?php
session_start();
require_once '../config/database.php';

// Check if courier is logged in
if (!isset($_SESSION['courier_id'])) {
    header("Location: index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : 0;

if ($order_id == 0) {
    header("Location: dashboard.php");
    exit();
}

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

// Get delivery proofs
$proofs_query = "SELECT * FROM delivery_proofs WHERE order_id = :order_id ORDER BY uploaded_at DESC";
$proofs_stmt = $db->prepare($proofs_query);
$proofs_stmt->bindParam(':order_id', $order_id);
$proofs_stmt->execute();
$proofs = $proofs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get courier information
$courier_query = "SELECT * FROM couriers WHERE id = :id";
$courier_stmt = $db->prepare($courier_query);
$courier_stmt->bindParam(':id', $_SESSION['courier_id']);
$courier_stmt->execute();
$courier = $courier_stmt->fetch(PDO::FETCH_ASSOC);
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
            margin: 3px 0;
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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

        .customer-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .customer-info p {
            margin-bottom: 8px;
            font-size: 14px;
        }

        .customer-info strong {
            color: #4834d4;
            width: 70px;
            display: inline-block;
        }

        .tracking-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .btn-start, .btn-stop, .btn-complete, .btn-refresh, .btn-photo {
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

        .btn-start:hover {
            background: #218838;
        }

        .btn-stop {
            background: #dc3545;
            color: white;
            display: none;
        }

        .btn-stop:hover {
            background: #c82333;
        }

        .btn-complete {
            background: #007bff;
            color: white;
        }

        .btn-complete:hover {
            background: #0056b3;
        }

        .btn-refresh {
            background: #6c757d;
            color: white;
        }

        .btn-refresh:hover {
            background: #5a6268;
        }

        .btn-photo {
            background: #fd7e14;
            color: white;
        }

        .btn-photo:hover {
            background: #dc6b12;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .modal-header h2 {
            color: #333;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .photo-preview {
            margin-top: 10px;
            text-align: center;
            display: none;
        }

        .photo-preview.show {
            display: block;
        }

        .photo-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 5px;
            border: 2px solid #e1e1e1;
        }

        .proofs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .proof-item {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            text-align: center;
        }

        .proof-item img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
        }

        .proof-item .proof-type {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
            
            .customer-info strong {
                width: auto;
                margin-right: 10px;
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
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['courier_name'] ?? $courier['name']); ?>!</span>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <!-- Tracking Header -->
        <div class="tracking-header">
            <div class="order-info">
                <h1>📍 Order #<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></h1>
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></p>
                <?php if ($order['status'] == 'Delivered'): ?>
                    <p><strong>Delivered:</strong> <?php echo date('M d, Y h:i A', strtotime($order['delivered_at'])); ?></p>
                    <p><strong>Recipient:</strong> <?php echo htmlspecialchars($order['recipient_name'] ?? 'N/A'); ?></p>
                <?php endif; ?>
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

        <!-- Tracking Buttons -->
        <div class="tracking-buttons">
            <?php if ($order['status'] != 'Delivered'): ?>
                <button onclick="startTracking()" id="startBtn" class="btn-start">
                    ▶ Start Tracking
                </button>
                <button onclick="stopTracking()" id="stopBtn" class="btn-stop">
                    ⏹ Stop Tracking
                </button>
                <button onclick="openPhotoModal()" class="btn-photo">
                    📸 Take Photo
                </button>
            <?php endif; ?>
            <button onclick="refreshLocation()" class="btn-refresh">
                🔄 Refresh Location
            </button>
            <?php if ($order['status'] != 'Delivered'): ?>
                <button onclick="openDeliveryModal()" class="btn-complete">
                    ✅ Complete Delivery
                </button>
            <?php endif; ?>
        </div>

        <!-- Tracking Info -->
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
                </div>
            </div>

            <div class="control-card">
                <h3>📞 Customer</h3>
                <div class="customer-info">
                    <p><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                    <p>📱 <?php echo htmlspecialchars($order['phone'] ?? 'No phone'); ?></p>
                    <p>📍 <?php echo htmlspecialchars($order['address']); ?></p>
                </div>
            </div>
        </div>

        <!-- Delivery Proofs -->
        <?php if (!empty($proofs)): ?>
        <div class="control-card" style="margin-bottom: 20px;">
            <h3>📸 Delivery Proofs</h3>
            <div class="proofs-grid">
                <?php foreach ($proofs as $proof): ?>
                    <div class="proof-item">
                        <img src="../uploads/delivery_proofs/<?php echo $proof['photo_path']; ?>" 
                             onclick="window.open(this.src, '_blank')">
                        <div class="proof-type">
                            <?php echo ucfirst($proof['photo_type']); ?>
                        </div>
                        <small><?php echo date('M d, h:i A', strtotime($proof['uploaded_at'])); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tracking Log -->
        <div class="update-log">
            <h3>📋 Activity Log</h3>
            <div class="log-container" id="logContainer">
                <div class="log-entry">
                    <span class="log-time"><?php echo date('h:i:s A'); ?></span>
                    <span class="log-message">✅ Ready to track</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div id="photoModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📸 Take Delivery Photo</h2>
                <button class="close-btn" onclick="closePhotoModal()">&times;</button>
            </div>
            
            <form id="photoForm" enctype="multipart/form-data">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                
                <div class="form-group">
                    <label>Photo Type</label>
                    <select name="photo_type" id="photo_type">
                        <option value="delivery">Delivery Photo</option>
                        <option value="package">Package Photo</option>
                        <option value="signature">Signature</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Select Photo</label>
                    <input type="file" name="photo" id="photoInput" accept="image/*" required onchange="previewPhoto(this)">
                </div>
                
                <div class="photo-preview" id="photoPreview">
                    <img id="previewImg" src="" alt="Preview">
                </div>
                
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea name="notes" placeholder="Add notes about this photo..."></textarea>
                </div>
                
                <div class="tracking-buttons">
                    <button type="button" class="btn-stop" onclick="closePhotoModal()">Cancel</button>
                    <button type="submit" class="btn-complete">Upload Photo</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delivery Completion Modal -->
    <div id="deliveryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✅ Complete Delivery</h2>
                <button class="close-btn" onclick="closeDeliveryModal()">&times;</button>
            </div>
            
            <form id="deliveryForm">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                
                <div class="form-group">
                    <label>Recipient Name</label>
                    <input type="text" id="recipient_name" placeholder="Name of person who received" required>
                </div>
                
                <div class="form-group">
                    <label>Delivery Notes</label>
                    <textarea id="delivery_notes" placeholder="Any notes about the delivery..."></textarea>
                </div>
                
                <div class="form-group">
                    <label style="color: #dc3545;">⚠️ Important</label>
                    <p style="font-size: 13px; color: #666;">
                        Make sure you have taken a delivery photo before completing.
                        This action cannot be undone.
                    </p>
                </div>
                
                <div class="tracking-buttons">
                    <button type="button" class="btn-stop" onclick="closeDeliveryModal()">Cancel</button>
                    <button type="submit" class="btn-complete">Confirm Delivery</button>
                </div>
            </form>
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
    
    // Destination coordinates
    let destinationLat = null;
    let destinationLng = null;
    const destinationAddress = "<?php echo addslashes($order['address']); ?>";

    // Initialize map
    function initMap() {
        const defaultLat = 14.5995;
        const defaultLng = 120.9842;
        
        map = L.map('tracking-map').setView([defaultLat, defaultLng], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        const courierIcon = L.divIcon({
            className: 'custom-div-icon',
            html: '<div style="background-color: #007bff; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
            iconSize: [24, 24],
            popupAnchor: [0, -12]
        });
        
        marker = L.marker([defaultLat, defaultLng], { icon: courierIcon }).addTo(map);
        
        if (destinationAddress) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(destinationAddress)}`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        destinationLat = parseFloat(data[0].lat);
                        destinationLng = parseFloat(data[0].lon);
                        
                        const destIcon = L.divIcon({
                            className: 'custom-div-icon',
                            html: '<div style="background-color: #dc3545; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                            iconSize: [24, 24],
                            popupAnchor: [0, -12]
                        });
                        
                        L.marker([destinationLat, destinationLng], { icon: destIcon }).addTo(map)
                            .bindPopup('Destination');
                        
                        addLog('📍 Destination found', 'success');
                    }
                });
        }
    }

    // Add log
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
        
        if (logContainer.children.length > 20) {
            logContainer.removeChild(logContainer.lastChild);
        }
    }

    // Start tracking
    function startTracking() {
        if (!navigator.geolocation) {
            alert('Geolocation not supported');
            return;
        }
        
        document.getElementById('startBtn').style.display = 'none';
        document.getElementById('stopBtn').style.display = 'block';
        addLog('📍 Starting GPS...', 'success');
        
        watchId = navigator.geolocation.watchPosition(sendLocation, handleError, {
            enableHighAccuracy: true,
            timeout: 5000
        });
        
        updateInterval = setInterval(() => {
            navigator.geolocation.getCurrentPosition(sendLocation, handleError);
        }, 5000);
    }

    // Stop tracking
    function stopTracking() {
        if (watchId) navigator.geolocation.clearWatch(watchId);
        if (updateInterval) clearInterval(updateInterval);
        
        document.getElementById('startBtn').style.display = 'block';
        document.getElementById('stopBtn').style.display = 'none';
        addLog('⏹ Tracking stopped');
    }

    // Send location
    function sendLocation(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const speed = position.coords.speed || 0;
        
        document.getElementById('latitude').textContent = lat.toFixed(6);
        document.getElementById('longitude').textContent = lng.toFixed(6);
        document.getElementById('speed').textContent = (speed * 3.6).toFixed(1) + ' km/h';
        
        marker.setLatLng([lat, lng]);
        map.panTo([lat, lng]);
        
        fetch('../update_courier_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                latitude: lat,
                longitude: lng,
                speed: speed
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addLog(`📍 Location updated`, 'success');
            }
        })
        .catch(error => {
            addLog(`❌ Error: ${error.message}`, 'error');
        });
    }

    // Handle error
    function handleError(error) {
        let message = 'Location error';
        if (error.code === 1) message = 'Permission denied';
        if (error.code === 2) message = 'Location unavailable';
        if (error.code === 3) message = 'Request timeout';
        addLog(`❌ ${message}`, 'error');
    }

    // Refresh location
    function refreshLocation() {
        addLog('🔄 Refreshing...');
        navigator.geolocation.getCurrentPosition(sendLocation, handleError);
    }

    // Photo Modal Functions
    function openPhotoModal() {
        document.getElementById('photoModal').classList.add('show');
    }
    
    function closePhotoModal() {
        document.getElementById('photoModal').classList.remove('show');
        document.getElementById('photoForm').reset();
        document.getElementById('photoPreview').classList.remove('show');
    }
    
    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        const previewImg = document.getElementById('previewImg');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.classList.add('show');
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Handle photo upload
    document.getElementById('photoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('upload_proof.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addLog('✅ Photo uploaded successfully', 'success');
                closePhotoModal();
                setTimeout(() => location.reload(), 1000);
            } else {
                addLog('❌ ' + data.message, 'error');
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            addLog('❌ Upload failed', 'error');
            alert('Upload failed');
        });
    });

    // Delivery Modal Functions
    function openDeliveryModal() {
        document.getElementById('deliveryModal').classList.add('show');
    }
    
    function closeDeliveryModal() {
        document.getElementById('deliveryModal').classList.remove('show');
    }
    
    // Handle delivery completion
    document.getElementById('deliveryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const recipient = document.getElementById('recipient_name').value;
        const notes = document.getElementById('delivery_notes').value;
        
        if (!recipient) {
            alert('Please enter recipient name');
            return;
        }
        
        if (!confirm('Mark this order as delivered?')) {
            return;
        }
        
        fetch('update_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                order_id: orderId,
                status: 'Delivered',
                recipient_name: recipient,
                notes: notes
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                addLog('✅ Delivery completed!', 'success');
                stopTracking();
                closeDeliveryModal();
                setTimeout(() => location.reload(), 1500);
            } else {
                addLog('❌ Failed to complete delivery', 'error');
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            addLog('❌ Error', 'error');
            alert('Error completing delivery');
        });
    });

    // Initialize on load
    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        if (orderStatus === 'Shipped') {
            setTimeout(startTracking, 1000);
        }
    });

    // Clean up on unload
    window.addEventListener('beforeunload', function() {
        stopTracking();
    });
    </script>
</body>
</html>