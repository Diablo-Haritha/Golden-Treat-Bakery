<?php
session_start();
ob_start();

// Simple admin authentication
$admin_username = 'admin';
$admin_password = 'admin123'; // Change this in production!

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_last_login'] = time();
            header('Location: admin.php');
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
    
    // Show login form
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login - Golden Treat Bakery</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                background: linear-gradient(135deg, #8B4513 0%, #A0522D 50%, #e0c99d 100%);
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            }
            
            .login-container {
                background: rgba(255, 255, 255, 0.95);
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 400px;
                backdrop-filter: blur(10px);
                animation: fadeIn 0.6s ease;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header h2 {
                color: #8B4513;
                margin-bottom: 10px;
                font-size: 2rem;
            }
            
            .login-header i {
                font-size: 3rem;
                color: #d4af37;
                margin-bottom: 15px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            label {
                display: block;
                margin-bottom: 8px;
                font-weight: 600;
                color: #555;
            }
            
            input {
                width: 100%;
                padding: 12px 15px;
                border: 2px solid #ddd;
                border-radius: 10px;
                font-size: 16px;
                transition: border-color 0.3s ease;
            }
            
            input:focus {
                outline: none;
                border-color: #8B4513;
            }
            
            .btn {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #8B4513, #A0522D);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                margin-top: 10px;
                transition: transform 0.3s ease;
            }
            
            .btn:hover {
                transform: translateY(-2px);
            }
            
            .message {
                text-align: center;
                padding: 12px;
                margin-bottom: 20px;
                border-radius: 8px;
                background: #f8d7da;
                color: #721c24;
                animation: shake 0.5s ease;
            }
            
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-user-shield"></i>
                <h2>Admin Login</h2>
                <p>Golden Treat Bakery Management</p>
            </div>
            <?php if (isset($error)): ?>
                <div class="message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autocomplete="off" value="admin">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required value="admin123">
                </div>
                <button type="submit" class="btn">Login to Dashboard</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}?>