<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle quantity updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update') {
        $cart_id = $_POST['cart_id'];
        $quantity = $_POST['quantity'];
        
        $update_query = "UPDATE cart SET quantity = :quantity WHERE id = :id AND user_id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':quantity', $quantity);
        $update_stmt->bindParam(':id', $cart_id);
        $update_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $update_stmt->execute();
    } elseif ($_POST['action'] == 'remove') {
        $cart_id = $_POST['cart_id'];
        
        $delete_query = "DELETE FROM cart WHERE id = :id AND user_id = :user_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':id', $cart_id);
        $delete_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $delete_stmt->execute();
    } elseif ($_POST['action'] == 'clear') {
        $clear_query = "DELETE FROM cart WHERE user_id = :user_id";
        $clear_stmt = $db->prepare($clear_query);
        $clear_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $clear_stmt->execute();
    }
    
    header("Location: cart.php");
    exit();
}

// Get cart items
$cart_query = "
    SELECT c.*, p.name, p.price, p.image, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = :user_id
";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->bindParam(':user_id', $_SESSION['user_id']);
$cart_stmt->execute();
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
$shipping = 100; // Fixed shipping cost
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal + $shipping;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - SecureApp</title>
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
        }

        .navbar-menu a:hover {
            background: rgba(255,255,255,0.1);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .cart-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        .cart-items {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .cart-header h2 {
            color: #333;
        }

        .clear-cart {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 80px 1fr auto;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .cart-item:last-child {
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
            font-size: 24px;
        }

        .item-details h3 {
            color: #333;
            margin-bottom: 5px;
        }

        .item-price {
            color: #4834d4;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #e9ecef;
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }

        .item-total {
            text-align: right;
        }

        .item-total .label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .item-total .amount {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .cart-summary {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            color: #666;
        }

        .summary-row.total {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            border-top: 2px solid #f0f0f0;
            padding-top: 15px;
            margin-top: 15px;
        }

        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.3s;
        }

        .checkout-btn:hover {
            background: #218838;
        }

        .empty-cart {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-cart h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .empty-cart p {
            color: #666;
            margin-bottom: 20px;
        }

        .shop-now-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #4834d4;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .cart-container {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .item-image {
                margin: 0 auto;
            }
            
            .item-actions {
                justify-content: center;
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
            <a href="cart.php">🛒 Cart</a>
            <a href="orders.php">My Orders</a>
            <a href="profile.php">Profile</a>
            <a href="logout.php" style="background: #dc3545;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <div class="cart-container">
            <div class="cart-items">
                <div class="cart-header">
                    <h2>Shopping Cart (<?php echo count($cart_items); ?> items)</h2>
                    <?php if (!empty($cart_items)): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="clear">
                            <button type="submit" class="clear-cart" onclick="return confirm('Clear all items from cart?')">Clear Cart</button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <h3>Your cart is empty</h3>
                        <p>Looks like you haven't added any items to your cart yet.</p>
                        <a href="products.php" class="shop-now-btn">Shop Now</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item">
                            <div class="item-image">
                                🛍️
                            </div>
                            <div class="item-details">
                                <h3><?php echo htmlspecialchars($item['name']); ?></h3>
                                <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                                <div class="item-actions">
                                    <form method="POST" class="quantity-control">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(this, -1)" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>-</button>
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" class="quantity-input" readonly>
                                        <button type="button" class="quantity-btn" onclick="updateQuantity(this, 1)" <?php echo $item['quantity'] >= $item['stock'] ? 'disabled' : ''; ?>>+</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" class="remove-btn">Remove</button>
                                    </form>
                                </div>
                            </div>
                            <div class="item-total">
                                <div class="label">Subtotal</div>
                                <div class="amount">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if (!empty($cart_items)): ?>
                <div class="cart-summary">
                    <div class="summary-title">Order Summary</div>
                    <div class="summary-row">
                        <span>Subtotal</span>
                        <span>₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping</span>
                        <span>₱<?php echo number_format($shipping, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>Total</span>
                        <span>₱<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <a href="checkout.php">
                        <button class="checkout-btn">Proceed to Checkout</button>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateQuantity(btn, change) {
            const form = btn.closest('form');
            const input = form.querySelector('.quantity-input');
            let newValue = parseInt(input.value) + change;
            const max = parseInt(input.max);
            const min = parseInt(input.min);
            
            if (newValue >= min && newValue <= max) {
                input.value = newValue;
                form.submit();
            }
        }
    </script>
</body>
</html>