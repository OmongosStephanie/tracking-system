<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle product deletion
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get product image before deleting
    $img_query = "SELECT image FROM products WHERE id = :id";
    $img_stmt = $db->prepare($img_query);
    $img_stmt->bindParam(':id', $id);
    $img_stmt->execute();
    $product_img = $img_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if product has orders
    $check_query = "SELECT COUNT(*) as total FROM order_items WHERE product_id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':id', $id);
    $check_stmt->execute();
    $result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['total'] == 0) {
        // Delete image file if exists
        if ($product_img && !empty($product_img['image'])) {
            $image_path = '../uploads/products/' . $product_img['image'];
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
        
        $delete_query = "DELETE FROM products WHERE id = :id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $id);
        
        if ($delete_stmt->execute()) {
            logAdminAction('Delete Product', "Deleted product ID: $id");
            $success = "Product deleted successfully";
        }
    } else {
        $error = "Cannot delete product with existing orders";
    }
}

// Get all products
$query = "SELECT * FROM products ORDER BY id DESC";
$stmt = $db->query($query);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin</title>
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
            text-decoration: none;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background 0.3s;
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

        .products-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
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
            vertical-align: middle;
        }

        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 24px;
        }

        .stock-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .stock-high { 
            background: #28a745; 
            color: white; 
        }
        .stock-medium { 
            background: #ffc107; 
            color: #333; 
        }
        .stock-low { 
            background: #dc3545; 
            color: white; 
        }

        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-edit, .btn-delete, .btn-image {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: none;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .btn-edit {
            background: #007bff;
            color: white;
        }

        .btn-edit:hover {
            background: #0056b3;
        }

        .btn-image {
            background: #17a2b8;
            color: white;
        }

        .btn-image:hover {
            background: #138496;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
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
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #333;
            font-size: 24px;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .close-btn:hover {
            color: #333;
        }

        .current-image {
            text-align: center;
            margin-bottom: 20px;
        }

        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            border: 3px solid #f0f0f0;
        }

        .current-image .no-image {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
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

        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .image-preview {
            margin-top: 15px;
            text-align: center;
            display: none;
        }

        .image-preview.show {
            display: block;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 5px;
            border: 2px solid #e1e1e1;
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

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.show {
            display: block;
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
            <a href="products.php" class="menu-item active"><i>📦</i> <span>Products</span></a>
            <a href="orders.php" class="menu-item"><i>🛒</i> <span>Orders</span></a>
            <a href="users.php" class="menu-item"><i>👥</i> <span>Users</span></a>
             <a href="messages.php" class="menu-item"><i>✉️</i> <span>Messages</span></a>
            <a href="logs.php" class="menu-item"><i>📋</i> <span>Activity logs</span></a>
            <a href="settings.php" class="menu-item"><i>⚙️</i> <span>Settings</span></a>
             <a href="logs.php" class="menu-item"><i>📋</i> <span>Activity logs</span></a>
            <a href="../logout.php" class="menu-item"><i>🚪</i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Manage Products</h1>
            <a href="add_product.php" class="add-btn">➕ Add New Product</a>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="products-table">
            <table>
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td>
                            <div class="product-image">
                                <?php if (!empty($product['image']) && file_exists('../uploads/products/' . $product['image'])): ?>
                                    <img src="../uploads/products/<?php echo $product['image']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'>📦</div>';">
                                <?php else: ?>
                                    <div class="no-image">📦</div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                        <td>₱<?php echo number_format($product['price'], 2); ?></td>
                        <td>
                            <?php 
                            $stock_class = 'stock-high';
                            if ($product['stock'] <= 5) $stock_class = 'stock-low';
                            elseif ($product['stock'] <= 10) $stock_class = 'stock-medium';
                            ?>
                            <span class="stock-badge <?php echo $stock_class; ?>">
                                <?php echo $product['stock']; ?> units
                            </span>
                        </td>
                        <td>
                            <?php if ($product['stock'] > 0): ?>
                                <span style="color: #28a745;">In Stock</span>
                            <?php else: ?>
                                <span style="color: #dc3545;">Out of Stock</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-btns">
                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn-edit">✏️ Edit</a>
                            <button onclick="openImageModal(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', '<?php echo $product['image']; ?>')" class="btn-image">🖼️ Image</button>
                            <a href="?delete=<?php echo $product['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this product?')">🗑️ Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Image Upload Modal -->
    <div id="imageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change Product Image</h2>
                <button class="close-btn" onclick="closeImageModal()">&times;</button>
            </div>
            
            <form id="imageForm" method="POST" enctype="multipart/form-data" action="update_product_image.php">
                <input type="hidden" name="product_id" id="product_id">
                
                <div class="current-image" id="currentImage">
                    <!-- Current image will be displayed here -->
                </div>

                <div class="form-group">
                    <label>Select New Image</label>
                    <input type="file" name="product_image" id="product_image" accept="image/*" onchange="previewImage(this)" required>
                    <small>Allowed formats: JPG, JPEG, PNG, GIF (Max size: 2MB)</small>
                </div>

                <div class="image-preview" id="imagePreview">
                    <img id="previewImg" src="" alt="Preview">
                </div>

                <div class="loading" id="loading">
                    Uploading... Please wait.
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeImageModal()">Cancel</button>
                    <button type="submit" class="btn-save" onclick="return validateImage()">Upload Image</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentProductId = null;

        function openImageModal(productId, productName, currentImage) {
            currentProductId = productId;
            document.getElementById('product_id').value = productId;
            
            // Display current image
            const currentImageDiv = document.getElementById('currentImage');
            if (currentImage) {
                currentImageDiv.innerHTML = `
                    <p><strong>Current Image:</strong></p>
                    <img src="../uploads/products/${currentImage}" alt="Current" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'>📦</div>';">
                `;
            } else {
                currentImageDiv.innerHTML = `
                    <p><strong>No current image</strong></p>
                    <div class="no-image">📦</div>
                `;
            }
            
            // Reset preview
            document.getElementById('imagePreview').classList.remove('show');
            document.getElementById('product_image').value = '';
            
            // Show modal
            document.getElementById('imageModal').classList.add('show');
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('show');
            document.getElementById('loading').classList.remove('show');
        }

        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
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

        function validateImage() {
            const fileInput = document.getElementById('product_image');
            const file = fileInput.files[0];
            
            if (!file) {
                alert('Please select an image file');
                return false;
            }
            
            // Check file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                return false;
            }
            
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Only JPG, JPEG, PNG, and GIF files are allowed');
                return false;
            }
            
            document.getElementById('loading').classList.add('show');
            return true;
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeImageModal();
            }
        }
    </script>
</body>
</html>