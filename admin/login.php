<?php
session_start();
require_once '../config/database.php';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: ../admin/index.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Query to check user and admin role
        $query = "SELECT id, username, email, password, full_name, role FROM users WHERE (username = :username OR email = :username) AND role = 'admin'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Update last login
                $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':id', $user['id']);
                $updateStmt->execute();
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Log admin login
                $logQuery = "INSERT INTO admin_logs (admin_id, action, details, ip_address) VALUES (:admin_id, 'Login', 'Admin logged in', :ip)";
                $logStmt = $db->prepare($logQuery);
                $logStmt->bindParam(':admin_id', $user['id']);
                $logStmt->bindParam(':ip', $_SERVER['REMOTE_ADDR']);
                $logStmt->execute();
                
                header("Location: index.php");
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid admin credentials or not an admin account";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - SecureApp</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            max-width: 400px;
            width: 100%;
        }

        .login-box {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: #2c3e50;
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            pointer-events: none;
        }

        .admin-icon {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            border: 3px solid rgba(255,255,255,0.3);
        }

        .login-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .login-header p {
            opacity: 0.8;
            font-size: 14px;
        }

        .login-form {
            padding: 40px 30px;
            background: #f8f9fa;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e1e1;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2c3e50;
            box-shadow: 0 0 0 3px rgba(44, 62, 80, 0.1);
        }

        .form-group input[type="submit"] {
            background: #2c3e50;
            color: white;
            border: none;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-group input[type="submit"]:hover {
            background: #34495e;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(44, 62, 80, 0.3);
        }

        .form-group input[type="submit"]:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-error::before {
            content: '⚠️';
            font-size: 16px;
        }

        .login-footer {
            text-align: center;
            padding: 25px 30px;
            background: white;
            border-top: 1px solid #dee2e6;
        }

        .login-footer a {
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .login-footer a:hover {
            color: #34495e;
            text-decoration: underline;
        }

        .login-footer p {
            color: #666;
            font-size: 13px;
            margin-top: 10px;
        }

        .security-badge {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            color: #666;
            font-size: 12px;
        }

        .security-badge span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Loading animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .loading.show {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #2c3e50;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-header {
                padding: 30px 20px;
            }
            
            .login-form {
                padding: 30px 20px;
            }
            
            .admin-icon {
                width: 60px;
                height: 60px;
                font-size: 30px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>

    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <div class="admin-icon">
                    👑
                </div>
                <h1>Admin Login</h1>
                <p>Secure access for administrators only</p>
            </div>
            
            <div class="login-form">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="showLoading()">
                    <div class="form-group">
                        <label for="username">
                            <span>👤</span> Username or Email
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               placeholder="Enter your username or email" 
                               required 
                               autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">
                            <span>🔐</span> Password
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               placeholder="Enter your password" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <input type="submit" value="Access Admin Panel">
                    </div>
                </form>
            </div>
            
            <div class="login-footer">
              <div class="login-footer">
    <a href="../index.php">← Back to User Login</a>
    <p>Only users with admin privileges can access this area</p>
    <div class="security-badge">
        <span>🔒 256-bit SSL</span>
        <span>🛡️ Secure Login</span>
        <span>📋 Activity Logged</span>
    </div>
</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loading').classList.add('show');
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // Add password show/hide functionality
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            
            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.innerHTML = '👁️';
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.right = '10px';
            toggleBtn.style.top = '38px';
            toggleBtn.style.background = 'none';
            toggleBtn.style.border = 'none';
            toggleBtn.style.cursor = 'pointer';
            toggleBtn.style.fontSize = '16px';
            toggleBtn.style.opacity = '0.6';
            
            // Add to parent
            const formGroup = passwordInput.parentElement;
            formGroup.style.position = 'relative';
            formGroup.appendChild(toggleBtn);
            
            // Toggle password visibility
            toggleBtn.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleBtn.innerHTML = '👁️‍🗨️';
                } else {
                    passwordInput.type = 'password';
                    toggleBtn.innerHTML = '👁️';
                }
            });
        });

        // Add keyboard shortcut (Ctrl+Alt+A) to focus admin login
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.altKey && e.key === 'a') {
                e.preventDefault();
                document.getElementById('username').focus();
            }
        });
    </script>
    
    </div>
</div>
</body>
</html>