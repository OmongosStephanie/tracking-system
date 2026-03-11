<?php
require_once 'auth.php';
requireAdmin();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = trim($_POST['category']);
    
    if (empty($name) || empty($price) || empty($stock)) {
        $error = "Please fill in all required fields";
    } else {
        $query = "INSERT INTO products (name, description, price, stock, category) 
                  VALUES (:name, :description, :price, :stock, :category)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':stock', $stock);
        $stmt->bindParam(':category', $category);
        
        if ($stmt->execute()) {
            logAdminAction('Add Product', "Added product: $name");
            header("Location: products.php?success=1");
            exit();
        } else {
            $error = "Failed to add product";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin</title>
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

        .form-container {
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4834d4;
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .btn-submit {
            padding: 12px 30px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        .btn-submit:hover {
            background: #218838;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <a href="../logout.php" class="menu-item"><i>🚪</i> <span>Logout</span></a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Add New Product</h1>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" required>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>

                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" step="0.01" min="0" required>
                </div>

                <div class="form-group">
                    <label>Stock *</label>
                    <input type="number" name="stock" min="0" required>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category">
                        <option value="Electronics">Electronics</option>
                        <option value="Accessories">Accessories</option>
                        <option value="Bags">Bags</option>
                        <option value="Home">Home</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <button type="submit" class="btn-submit">Add Product</button>
            </form>
        </div>
    </div>
</body>
</html>