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

// Get cart items
$cart_query = "
    SELECT c.*, p.name, p.price, p.stock 
    FROM cart c 
    JOIN products p ON c.product_id = p.id 
    WHERE c.user_id = :user_id
";
$cart_stmt = $db->prepare($cart_query);
$cart_stmt->bindParam(':user_id', $_SESSION['user_id']);
$cart_stmt->execute();
$cart_items = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Calculate total
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$shipping = 100;
$total = $subtotal + $shipping;

// Process order
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $shipping_address = trim($_POST['address']);
    $contact_number = trim($_POST['contact']);
    $payment_method = $_POST['payment_method'];
    
    if (empty($shipping_address) || empty($contact_number)) {
        $error = "Please fill in all fields";
    } else {
        try {
            $db->beginTransaction();
            
            // Generate order number
            $order_number = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
            
            // Insert order
            $order_query = "INSERT INTO orders (user_id, order_number, total_amount, payment_method, shipping_address, contact_number) 
                           VALUES (:user_id, :order_number, :total_amount, :payment_method, :shipping_address, :contact_number)";
            $order_stmt = $db->prepare($order_query);
            $order_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $order_stmt->bindParam(':order_number', $order_number);
            $order_stmt->bindParam(':total_amount', $total);
            $order_stmt->bindParam(':payment_method', $payment_method);
            $order_stmt->bindParam(':shipping_address', $shipping_address);
            $order_stmt->bindParam(':contact_number', $contact_number);
            $order_stmt->execute();
            
            $order_id = $db->lastInsertId();
            
            // Insert order items and update stock
            foreach ($cart_items as $item) {
                // Insert order item
                $item_query = "INSERT INTO order_items (order_id, product_id, product_name, quantity, price) 
                              VALUES (:order_id, :product_id, :product_name, :quantity, :price)";
                $item_stmt = $db->prepare($item_query);
                $item_stmt->bindParam(':order_id', $order_id);
                $item_stmt->bindParam(':product_id', $item['product_id']);
                $item_stmt->bindParam(':product_name', $item['name']);
                $item_stmt->bindParam(':quantity', $item['quantity']);
                $item_stmt->bindParam(':price', $item['price']);
                $item_stmt->execute();
                
                // Update stock
                $stock_query = "UPDATE products SET stock = stock - :quantity WHERE id = :id";
                $stock_stmt = $db->prepare($stock_query);
                $stock_stmt->bindParam(':quantity', $item['quantity']);
                $stock_stmt->bindParam(':id', $item['product_id']);
                $stock_stmt->execute();
            }
            
            // Clear cart
            $clear_query = "DELETE FROM cart WHERE user_id = :user_id";
            $clear_stmt = $db->prepare($clear_query);
            $clear_stmt->bindParam(':user_id', $_SESSION['user_id']);
            $clear_stmt->execute();
            
            $db->commit();
            
            // Redirect to order confirmation
            header("Location: order_confirmation.php?order=" . $order_number);
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Order failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SecureApp</title>
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

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .checkout-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }

        .checkout-form {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-title {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
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
            height: 100px;
            resize: vertical;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .payment-method {
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method:hover {
            border-color: #4834d4;
        }

        .payment-method.selected {
            border-color: #4834d4;
            background: #f0f0ff;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method label {
            cursor: pointer;
            display: block;
        }

        .payment-method .icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .order-summary {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-items {
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .item-name {
            flex: 1;
        }

        .item-quantity {
            color: #666;
            margin: 0 10px;
        }

        .item-price {
            font-weight: 600;
            color: #333;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
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

        .place-order-btn {
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
        }

        .place-order-btn:hover {
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

        @media (max-width: 768px) {
            .checkout-container {
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
            <a href="products.php">Products</a>
            <a href="cart.php">🛒 Cart</a>
            <a href="orders.php">My Orders</a>
            <a href="logout.php" style="background: #dc3545;">Logout</a>
        </div>
    </nav>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="checkout-container">
            <div class="checkout-form">
                <h2 class="form-title">Shipping Information</h2>
                <form method="POST" id="checkoutForm">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($_SESSION['email']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Shipping Address *</label>
                        <textarea name="address" placeholder="Enter your complete address" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Contact Number *</label>
                        <input type="text" name="contact" placeholder="Enter your contact number" required>
                    </div>
                    
                    <h2 class="form-title" style="margin-top: 30px;">Payment Method</h2>
                    
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" name="payment_method" id="cod" value="Cash on Delivery" checked>
                            <label for="cod">
                                <div class="icon">💵</div>
                                <div>Cash on Delivery</div>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="payment_method" id="card" value="Credit Card">
                            <label for="card">
                                <div class="icon">💳</div>
                                <div>Credit Card</div>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" name="payment_method" id="gcash" value="GCash">
                            <label for="gcash">
                                <div class="icon">📱</div>
                                <div>GCash</div>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <div class="order-summary">
                <h3>Order Summary</h3>
                
                <div class="summary-items">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="summary-item">
                            <span class="item-name"><?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="item-quantity">x<?php echo $item['quantity']; ?></span>
                            <span class="item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

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

                <button type="submit" form="checkoutForm" class="place-order-btn" onclick="return confirm('Place order now?')">
                    Place Order
                </button>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
    </script>
</body>
</html>