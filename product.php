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

// Get all products
$query = "SELECT * FROM products ORDER BY id DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count
$cart_query = "SELECT SUM(quantity) as total FROM cart WHERE user_id = :user_id";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->bindParam(':user_id', $_SESSION['user_id']);
$cart_stmt->execute();
$cart_count = $cart_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get order count for navbar
$order_query = "SELECT COUNT(*) as total FROM orders WHERE user_id = :user_id";
$order_stmt = $db->prepare($order_query);
$order_stmt->bindParam(':user_id', $_SESSION['user_id']);
$order_stmt->execute();
$order_count = $order_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - SecureApp</title>
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

        .search-box {
            display: flex;
            gap: 10px;
        }

        .search-box input {
            padding: 10px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            width: 250px;
            font-size: 16px;
        }

        .search-box button {
            padding: 10px 20px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .product-image {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 50%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-image .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 48px;
        }

        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }

        .product-description {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.5;
            height: 60px;
            overflow: hidden;
        }

        .product-price {
            font-size: 24px;
            font-weight: bold;
            color: #4834d4;
            margin-bottom: 15px;
        }

        .product-stock {
            font-size: 14px;
            color: #28a745;
            margin-bottom: 15px;
        }

        .product-stock.low {
            color: #ffc107;
        }

        .product-stock.out {
            color: #dc3545;
        }

        .product-actions {
            display: flex;
            gap: 10px;
        }

        .btn-add-cart {
            flex: 1;
            padding: 10px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .btn-add-cart:hover:not(:disabled) {
            background: #3c2ba5;
        }

        .btn-add-cart:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-view {
            padding: 10px 15px;
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-view:hover {
            background: #e9ecef;
        }

        .notification {
            position: fixed;
            top: 80px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transform: translateX(400px);
            transition: transform 0.3s;
            z-index: 1001;
        }

        .notification.show {
            transform: translateX(0);
        }

        .notification.error {
            background: #dc3545;
        }

        .empty-products {
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 10px;
            grid-column: 1 / -1;
        }

        .empty-products h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-products p {
            color: #666;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .search-box input {
                flex: 1;
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
            <h1>Our Products</h1>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search products..." onkeyup="searchProducts()">
                <button onclick="searchProducts()">Search</button>
            </div>
        </div>

        <div class="products-grid" id="productsGrid">
            <?php if (empty($products)): ?>
                <div class="empty-products">
                    <h3>No products available</h3>
                    <p>Check back later for new products!</p>
                </div>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-card" data-name="<?php echo strtolower($product['name']); ?>" data-category="<?php echo strtolower($product['category']); ?>">
                        <div class="product-image">
                            <?php if (!empty($product['image']) && file_exists('uploads/products/' . $product['image'])): ?>
                                <img src="uploads/products/<?php echo $product['image']; ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'no-image\'>📦</div>';">
                            <?php else: ?>
                                <div class="no-image">
                                    <?php 
                                    // Display category-based icon
                                    $category = strtolower($product['category']);
                                    if (strpos($category, 'phone') !== false || strpos($category, 'mobile') !== false) {
                                        echo '📱';
                                    } elseif (strpos($category, 'laptop') !== false || strpos($category, 'computer') !== false) {
                                        echo '💻';
                                    } elseif (strpos($category, 'clothing') !== false || strpos($category, 'apparel') !== false) {
                                        echo '👕';
                                    } elseif (strpos($category, 'food') !== false) {
                                        echo '🍔';
                                    } elseif (strpos($category, 'book') !== false) {
                                        echo '📚';
                                    } else {
                                        echo '🛍️';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                <span class="product-badge">Low Stock</span>
                            <?php elseif ($product['stock'] == 0): ?>
                                <span class="product-badge">Out of Stock</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <div class="product-category"><?php echo htmlspecialchars($product['category']); ?></div>
                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                            <div class="product-description"><?php echo htmlspecialchars($product['description']); ?></div>
                            <div class="product-price">₱<?php echo number_format($product['price'], 2); ?></div>
                            <div class="product-stock <?php 
                                echo $product['stock'] == 0 ? 'out' : ($product['stock'] <= 5 ? 'low' : ''); 
                            ?>">
                                <?php 
                                if ($product['stock'] == 0) {
                                    echo "Out of Stock";
                                } elseif ($product['stock'] <= 5) {
                                    echo "Only {$product['stock']} left!";
                                } else {
                                    echo "In Stock";
                                }
                                ?>
                            </div>
                            <div class="product-actions">
                                <button class="btn-add-cart" 
                                        onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')"
                                        <?php echo $product['stock'] == 0 ? 'disabled' : ''; ?>>
                                    Add to Cart
                                </button>
                                <button class="btn-view" onclick="viewProduct(<?php echo $product['id']; ?>)">
                                    View
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="notification" id="notification"></div>

    <script>
        function addToCart(productId, productName) {
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&quantity=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(productName + ' added to cart!', 'success');
                    // Update cart count
                    updateCartCount();
                } else {
                    showNotification(data.message || 'Error adding to cart', 'error');
                }
            })
            .catch(error => {
                showNotification('Error adding to cart', 'error');
            });
        }

        function showNotification(message, type) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification show ' + (type === 'error' ? 'error' : '');
            
            setTimeout(() => {
                notification.classList.remove('show');
            }, 3000);
        }

        function updateCartCount() {
            fetch('get_cart_count.php')
                .then(response => response.json())
                .then(data => {
                    const cartIcon = document.querySelector('.cart-icon');
                    let countSpan = cartIcon.querySelector('.cart-count');
                    
                    if (data.count > 0) {
                        if (countSpan) {
                            countSpan.textContent = data.count;
                        } else {
                            const newSpan = document.createElement('span');
                            newSpan.className = 'cart-count';
                            newSpan.textContent = data.count;
                            cartIcon.appendChild(newSpan);
                        }
                    } else if (countSpan) {
                        countSpan.remove();
                    }
                });
        }

        function searchProducts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const products = document.querySelectorAll('.product-card');
            
            products.forEach(product => {
                const name = product.getAttribute('data-name');
                const category = product.getAttribute('data-category');
                
                if (name.includes(searchTerm) || category.includes(searchTerm)) {
                    product.style.display = 'block';
                } else {
                    product.style.display = 'none';
                }
            });
        }

        function viewProduct(productId) {
            window.location.href = 'product_details.php?id=' + productId;
        }
    </script>
</body>
</html>