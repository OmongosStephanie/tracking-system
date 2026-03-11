<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle Add/Edit Courier
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add new courier
        if ($_POST['action'] == 'add') {
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $vehicle_type = $_POST['vehicle_type'];
            $license_number = $_POST['license_number'];
            
            $query = "INSERT INTO couriers (username, password, name, email, phone, address, vehicle_type, license_number, created_by) 
                      VALUES (:username, :password, :name, :email, :phone, :address, :vehicle_type, :license_number, :created_by)";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':vehicle_type', $vehicle_type);
            $stmt->bindParam(':license_number', $license_number);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = "Courier added successfully!";
            } else {
                $error = "Failed to add courier.";
            }
        }
        
        // Update courier
        if ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $email = $_POST['email'];
            $phone = $_POST['phone'];
            $address = $_POST['address'];
            $vehicle_type = $_POST['vehicle_type'];
            $license_number = $_POST['license_number'];
            $status = $_POST['status'];
            
            $query = "UPDATE couriers SET name=:name, email=:email, phone=:phone, address=:address, 
                      vehicle_type=:vehicle_type, license_number=:license_number, status=:status WHERE id=:id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':vehicle_type', $vehicle_type);
            $stmt->bindParam(':license_number', $license_number);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':id', $id);
            
            if ($stmt->execute()) {
                $success = "Courier updated successfully!";
            } else {
                $error = "Failed to update courier.";
            }
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check if courier has orders
    $check_query = "SELECT COUNT(*) as total FROM orders WHERE courier_id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $id);
    $check_stmt->execute();
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] == 0) {
        $delete_query = "DELETE FROM couriers WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            $success = "Courier deleted successfully!";
        }
    } else {
        $error = "Cannot delete courier with assigned orders";
    }
}

// Get all couriers
$query = "SELECT c.*, u.username as created_by_name, 
          (SELECT COUNT(*) FROM orders WHERE courier_id = c.id AND status IN ('Processing', 'Shipped')) as active_deliveries 
          FROM couriers c 
          LEFT JOIN users u ON c.created_by = u.id 
          ORDER BY c.id DESC";
$stmt = $db->query($query);
$couriers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Couriers - Admin</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #333;
        }
        
        .add-btn {
            padding: 12px 25px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-btn:hover {
            background: #218838;
        }
        
        .alert {
            padding: 15px;
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
        
        .couriers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .courier-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .courier-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .courier-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-inactive {
            background: #dc3545;
            color: white;
        }
        
        .status-busy {
            background: #ffc107;
            color: #333;
        }
        
        .courier-details {
            margin: 15px 0;
            padding: 15px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .detail-label {
            width: 100px;
            color: #666;
            font-size: 14px;
        }
        
        .detail-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }
        
        .courier-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
        }
        
        .active-deliveries {
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
        }
        
        .btn-edit, .btn-delete, .btn-assign {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-edit {
            background: #007bff;
            color: white;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        
        .btn-assign {
            background: #28a745;
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
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
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #007bff;
            outline: none;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-save, .btn-cancel {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-save {
            background: #28a745;
            color: white;
        }
        
        .btn-save:hover {
            background: #218838;
        }
        
        .btn-cancel {
            background: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
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
            <a href="couriers.php" class="menu-item active"><i>🚚</i> <span>Couriers</span></a>
            <a href="../logout.php" class="menu-item"><i>🚪</i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Manage Couriers</h1>
            <button class="add-btn" onclick="openAddModal()">➕ Add New Courier</button>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="couriers-grid">
            <?php foreach ($couriers as $courier): ?>
            <div class="courier-card">
                <div class="courier-header">
                    <span class="courier-name"><?php echo htmlspecialchars($courier['name']); ?></span>
                    <span class="status-badge status-<?php echo $courier['status']; ?>">
                        <?php echo ucfirst($courier['status']); ?>
                    </span>
                </div>
                
                <div class="courier-details">
                    <div class="detail-row">
                        <span class="detail-label">Username:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($courier['username']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($courier['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($courier['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Vehicle:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($courier['vehicle_type'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">License:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($courier['license_number'] ?? 'N/A'); ?></span>
                    </div>
                </div>

                <div class="courier-footer">
                    <span class="active-deliveries">
                        📦 <?php echo $courier['active_deliveries']; ?> Active
                    </span>
                    <div class="action-btns">
                        <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($courier)); ?>)">✏️ Edit</button>
                        <a href="?delete=<?php echo $courier['id']; ?>" class="btn-delete" onclick="return confirm('Delete this courier?')">🗑️ Delete</a>
                        <a href="assign_orders.php?courier_id=<?php echo $courier['id']; ?>" class="btn-assign">📋 Assign</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Add Courier Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Courier</h2>
                <button class="close-btn" onclick="closeModal('addModal')">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Vehicle Type</label>
                    <select name="vehicle_type">
                        <option value="Motorcycle">Motorcycle</option>
                        <option value="Bicycle">Bicycle</option>
                        <option value="Car">Car</option>
                        <option value="Van">Van</option>
                        <option value="Truck">Truck</option>
                        <option value="On Foot">On Foot</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_number">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn-save">Add Courier</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Courier Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Courier</h2>
                <button class="close-btn" onclick="closeModal('editModal')">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" id="edit_phone">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Vehicle Type</label>
                    <select name="vehicle_type" id="edit_vehicle_type">
                        <option value="Motorcycle">Motorcycle</option>
                        <option value="Bicycle">Bicycle</option>
                        <option value="Car">Car</option>
                        <option value="Van">Van</option>
                        <option value="Truck">Truck</option>
                        <option value="On Foot">On Foot</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>License Number</label>
                    <input type="text" name="license_number" id="edit_license">
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="busy">Busy</option>
                    </select>
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn-save">Update Courier</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }
        
        function openEditModal(courier) {
            document.getElementById('edit_id').value = courier.id;
            document.getElementById('edit_name').value = courier.name;
            document.getElementById('edit_email').value = courier.email;
            document.getElementById('edit_phone').value = courier.phone || '';
            document.getElementById('edit_address').value = courier.address || '';
            document.getElementById('edit_vehicle_type').value = courier.vehicle_type || 'Motorcycle';
            document.getElementById('edit_license').value = courier.license_number || '';
            document.getElementById('edit_status').value = courier.status || 'active';
            
            document.getElementById('editModal').classList.add('show');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>