<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in
if (isset($_SESSION['courier_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Query to check courier credentials
    $query = "SELECT * FROM couriers WHERE username = :username AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $courier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($courier && password_verify($password, $courier['password'])) {
        $_SESSION['courier_id'] = $courier['id'];
        $_SESSION['courier_name'] = $courier['name'];
        $_SESSION['courier_username'] = $courier['username'];
        $_SESSION['courier_vehicle'] = $courier['vehicle_type'];
        
        // Update last login
        $update_query = "UPDATE couriers SET last_login = NOW() WHERE id = :id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':id', $courier['id']);
        $update_stmt->execute();
        
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid username or password or account is inactive";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Courier Login - SecureApp</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 400px;
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .login-header .subtitle {
            color: #666;
            font-size: 14px;
        }

        .login-header .icon {
            font-size: 60px;
            margin-bottom: 20px;
            color: #4834d4;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e1e1;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            border-color: #4834d4;
            outline: none;
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: #4834d4;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }

        .login-btn:hover {
            background: #3c2ba5;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 13px;
        }

        .footer a {
            color: #4834d4;
            text-decoration: none;
            font-weight: 600;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .demo-credentials {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 13px;
            border: 1px dashed #4834d4;
        }

        .demo-credentials p {
            color: #666;
            margin-bottom: 5px;
        }

        .demo-credentials code {
            background: #e9ecef;
            padding: 2px 5px;
            border-radius: 3px;
            color: #4834d4;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="icon">🚚</div>
            <h1>Courier Login</h1>
            <div class="subtitle">Enter your credentials to access your deliveries</div>
        </div>

        <?php if ($error): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Enter your username" required autofocus>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="login-btn">Login to Dashboard</button>
        </form>

        <div class="demo-credentials">
            <p><strong>📋 Test Credentials:</strong></p>
            <p>Username: <code>courier1</code></p>
            <p>Password: <code>password123</code></p>
            <p style="margin-top: 5px; color: #999;">(Create this in admin panel first)</p>
        </div>

        <div class="footer">
            <p>Secure Courier System</p>
            <p style="margin-top: 5px;">&copy; <?php echo date('Y'); ?> SecureApp. All rights reserved.</p>
        </div>
    </div>
</body>
</html>