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
                background: linear-gradient(135deg, #8B4513, #A0522D, #e0c99d);
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
                    <input type="text" id="username" name="username" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login to Dashboard</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Database Configuration
$host = 'localhost';
$dbname = 'golden_treat_bakery';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new product
        if (isset($_POST['add_product'])) {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock']);
            $is_daily_special = isset($_POST['is_daily_special']) ? 1 : 0;
            $discount = floatval($_POST['discount']);
            $visibility = isset($_POST['visibility']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, is_daily_special, discount_percentage, visibility, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $category, $is_daily_special, $discount, $visibility, $stock]);
            
            $productId = $pdo->lastInsertId();
            
            if (isset($_POST['available_customizations']) && is_array($_POST['available_customizations'])) {
                foreach ($_POST['available_customizations'] as $customizationId) {
                    $stmt = $pdo->prepare("INSERT INTO product_customizations (product_id, customization_id) VALUES (?, ?)");
                    $stmt->execute([$productId, $customizationId]);
                }
            }
            
            $message = "Product added successfully!";
            $messageType = 'success';
        }
        
        // Add new customization option
        if (isset($_POST['add_customization'])) {
            $name = trim($_POST['customization_name']);
            $price = floatval($_POST['customization_price']);
            $category = trim($_POST['customization_category']);
            $is_active = isset($_POST['customization_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO customizations (name, price_adjustment, category, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $price, $category, $is_active]);
            
            $message = "Customization option added successfully!";
            $messageType = 'success';
        }
        
        // Toggle visibility
        if (isset($_POST['toggle_visibility'])) {
            $productId = intval($_POST['product_id']);
            $currentVisibility = intval($_POST['current_visibility']);
            $newVisibility = $currentVisibility ? 0 : 1;
            
            $stmt = $pdo->prepare("UPDATE products SET visibility = ? WHERE id = ?");
            $stmt->execute([$newVisibility, $productId]);
            $message = "Product visibility updated!";
            $messageType = 'success';
        }
        
        // Delete product
        if (isset($_POST['delete_product'])) {
            $productId = intval($_POST['product_id']);
            
            $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            
            $message = "Product deleted successfully!";
            $messageType = 'success';
        }
        
        // Delete customization
        if (isset($_POST['delete_customization'])) {
            $customizationId = intval($_POST['customization_id']);
            
            $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE customization_id = ?");
            $stmt->execute([$customizationId]);
            
            $stmt = $pdo->prepare("DELETE FROM customizations WHERE id = ?");
            $stmt->execute([$customizationId]);
            
            $message = "Customization deleted successfully!";
            $messageType = 'success';
        }
        
        // Update product
        if (isset($_POST['update_product'])) {
            $productId = intval($_POST['product_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock']);
            $is_daily_special = isset($_POST['is_daily_special']) ? 1 : 0;
            $discount = floatval($_POST['discount']);
            $visibility = isset($_POST['visibility']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, stock_quantity = ?, is_daily_special = ?, discount_percentage = ?, visibility = ? WHERE id = ?");
            $stmt->execute([$name, $description, $price, $category, $stock, $is_daily_special, $discount, $visibility, $productId]);
            
            $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            if (isset($_POST['available_customizations']) && is_array($_POST['available_customizations'])) {
                foreach ($_POST['available_customizations'] as $customizationId) {
                    $stmt = $pdo->prepare("INSERT INTO product_customizations (product_id, customization_id) VALUES (?, ?)");
                    $stmt->execute([$productId, $customizationId]);
                }
            }
            
            $message = "Product updated successfully!";
            $messageType = 'success';
        }
        
        // Update customization
        if (isset($_POST['update_customization'])) {
            $customizationId = intval($_POST['customization_id']);
            $name = trim($_POST['customization_name']);
            $price = floatval($_POST['customization_price']);
            $category = trim($_POST['customization_category']);
            $is_active = isset($_POST['customization_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE customizations SET name = ?, price_adjustment = ?, category = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $price, $category, $is_active, $customizationId]);
            
            $message = "Customization updated successfully!";
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();

// Get all customizations
$stmt = $pdo->query("SELECT * FROM customizations ORDER BY category, name");
$allCustomizations = $stmt->fetchAll();

// Get orders for dashboard with error handling
$recentOrders = [];
$orderStats = ['total_orders' => 0, 'total_revenue' => 0];

try {
    // Check if orders table exists
    $stmt = $pdo->query("SELECT 1 FROM orders LIMIT 1");
    $ordersTableExists = true;
} catch (PDOException $e) {
    $ordersTableExists = false;
    $message = "Orders table not found. Some features will be disabled.";
    $messageType = 'warning';
}

if ($ordersTableExists) {
    try {
        $stmt = $pdo->query("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 10");
        $recentOrders = $stmt->fetchAll();
        
        $stmt = $pdo->query("SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue FROM orders");
        $orderStats = $stmt->fetch();
    } catch (PDOException $e) {
        // If there's an error with the query, continue without orders data
        $recentOrders = [];
        $orderStats = ['total_orders' => 0, 'total_revenue' => 0];
    }
}

// Get product for editing
$editProduct = null;
$editProductCustomizations = [];
if (isset($_GET['edit_product'])) {
    $editId = intval($_GET['edit_product']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
    
    if ($editProduct) {
        $stmt = $pdo->prepare("SELECT customization_id FROM product_customizations WHERE product_id = ?");
        $stmt->execute([$editId]);
        $editProductCustomizations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Get customization for editing
$editCustomization = null;
if (isset($_GET['edit_customization'])) {
    $editId = intval($_GET['edit_customization']);
    $stmt = $pdo->prepare("SELECT * FROM customizations WHERE id = ?");
    $stmt->execute([$editId]);
    $editCustomization = $stmt->fetch();
}

// Dashboard statistics
$totalProducts = count($products);
$visibleProducts = count(array_filter($products, fn($p) => $p['visibility']));
$dailySpecials = count(array_filter($products, fn($p) => $p['is_daily_special']));
$totalCustomizations = count($allCustomizations);

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Golden Treat Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #8B4513;
            --primary-light: #A0522D;
            --secondary: #e0c99d;
            --accent: #d4af37;
            --light: #f8f4e9;
            --dark: #333;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --text: #444;
            --border: #ddd;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text);
            background: linear-gradient(135deg, #f8f4e9 0%, #fff8e1 100%);
            min-height: 100vh;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            padding: 20px 0;
            box-shadow: var(--shadow-lg);
            z-index: 100;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            text-align: center;
        }

        .sidebar-header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            list-style: none;
            margin-top: 20px;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            border-left-color: var(--accent);
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }

        .admin-header h2 {
            color: var(--primary);
            font-size: 2rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-success { background: linear-gradient(135deg, var(--success), #34ce57); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #e4606d); }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #ffd760); }
        .btn-secondary { background: linear-gradient(135deg, #6c757d, #868e96); }

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: transform 0.3s ease;
            border-left: 4px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card p {
            font-size: 2rem;
            font-weight: bold;
            color: var(--dark);
        }

        /* Table Styles */
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, var(--secondary), #e8d4a6);
            border-bottom: 1px solid var(--border);
        }

        .card-header h3 {
            color: var(--primary);
            font-size: 1.3rem;
        }

        .card-body {
            padding: 25px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .table th {
            background: var(--light);
            color: var(--primary);
            font-weight: 600;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .special-badge {
            background: #fff3cd;
            color: #856404;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .edit-btn { background: #007bff; color: white; }
        .delete-btn { background: var(--danger); color: white; }
        .toggle-btn { background: var(--success); color: white; }

        /* Form Styles */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .customization-options {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
        }

        .customization-category {
            margin-bottom: 15px;
        }

        .customization-category h4 {
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .customization-option {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .customization-option input {
            margin-right: 10px;
        }

        /* Message Styles */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .message.warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid var(--warning);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h1><i class="fas fa-user-shield"></i> Admin Panel</h1>
                <p>Golden Treat Bakery</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="#dashboard" class="active" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#products" data-tab="products"><i class="fas fa-cookie-bite"></i> Products</a></li>
                <li><a href="#customizations" data-tab="customizations"><i class="fas fa-magic"></i> Customizations</a></li>
                <li><a href="#orders" data-tab="orders"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                <li><a href="index.php" target="_blank"><i class="fas fa-store"></i> View Store</a></li>
                <li><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="admin-header">
                <h2>Admin Dashboard</h2>
                <div class="user-info">
                    <div class="user-avatar">A</div>
                    <span>Administrator</span>
                    <a href="index.php" class="btn btn-success">
                        <i class="fas fa-store"></i> View Store
                    </a>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])) {
                session_destroy();
                header('Location: admin.php');
                exit;
            } ?>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content active">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-cookie-bite"></i>
                        <h3>Total Products</h3>
                        <p><?php echo $totalProducts; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-eye"></i>
                        <h3>Visible Products</h3>
                        <p><?php echo $visibleProducts; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-star"></i>
                        <h3>Daily Specials</h3>
                        <p><?php echo $dailySpecials; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-magic"></i>
                        <h3>Customizations</h3>
                        <p><?php echo $totalCustomizations; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>Total Orders</h3>
                        <p><?php echo $orderStats['total_orders']; ?></p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-dollar-sign"></i>
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($orderStats['total_revenue'], 2); ?></p>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Orders</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recentOrders)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $order['status'] === 'completed' ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 20px;">
                                <?php echo $ordersTableExists ? 'No orders found.' : 'Orders table not available. Please run the database setup.'; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Products Tab -->
            <div id="products" class="tab-content">
                <?php if ($editProduct): ?>
                    <!-- Edit Product Form -->
                    <div class="form-container">
                        <h3>Edit Product: <?php echo htmlspecialchars($editProduct['name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                            <input type="hidden" name="update_product" value="1">
                            
                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editProduct['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($editProduct['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $editProduct['price']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="Pastries" <?php echo $editProduct['category'] === 'Pastries' ? 'selected' : ''; ?>>Pastries</option>
                                    <option value="Cakes" <?php echo $editProduct['category'] === 'Cakes' ? 'selected' : ''; ?>>Cakes</option>
                                    <option value="Cupcakes" <?php echo $editProduct['category'] === 'Cupcakes' ? 'selected' : ''; ?>>Cupcakes</option>
                                    <option value="Cookies" <?php echo $editProduct['category'] === 'Cookies' ? 'selected' : ''; ?>>Cookies</option>
                                    <option value="Breads" <?php echo $editProduct['category'] === 'Breads' ? 'selected' : ''; ?>>Breads</option>
                                    <option value="Tarts" <?php echo $editProduct['category'] === 'Tarts' ? 'selected' : ''; ?>>Tarts</option>
                                    <option value="Muffins" <?php echo $editProduct['category'] === 'Muffins' ? 'selected' : ''; ?>>Muffins</option>
                                    <option value="Custom" <?php echo $editProduct['category'] === 'Custom' ? 'selected' : ''; ?>>Custom</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock">Stock Quantity</label>
                                <input type="number" id="stock" name="stock" min="0" value="<?php echo $editProduct['stock_quantity']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_daily_special" name="is_daily_special" <?php echo $editProduct['is_daily_special'] ? 'checked' : ''; ?>>
                                    <label for="is_daily_special">Mark as Daily Special</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount">Discount Percentage (%)</label>
                                <input type="number" id="discount" name="discount" min="0" max="100" value="<?php echo $editProduct['discount_percentage']; ?>">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="visibility" name="visibility" <?php echo $editProduct['visibility'] ? 'checked' : ''; ?>>
                                    <label for="visibility">Visible on Website</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Available Customizations</label>
                                <div class="customization-options">
                                    <?php
                                    $groupedCustomizations = [];
                                    foreach ($allCustomizations as $cust) {
                                        $groupedCustomizations[$cust['category']][] = $cust;
                                    }
                                    ?>
                                    <?php foreach ($groupedCustomizations as $category => $customizations): ?>
                                        <div class="customization-category">
                                            <h4><?php echo htmlspecialchars($category); ?></h4>
                                            <?php foreach ($customizations as $cust): ?>
                                                <div class="customization-option">
                                                    <input type="checkbox" name="available_customizations[]" value="<?php echo $cust['id']; ?>" 
                                                           id="cust-<?php echo $cust['id']; ?>" 
                                                           <?php echo in_array($cust['id'], $editProductCustomizations) ? 'checked' : ''; ?>
                                                           <?php echo !$cust['is_active'] ? 'disabled' : ''; ?>>
                                                    <label for="cust-<?php echo $cust['id']; ?>" style="margin: 0;">
                                                        <?php echo htmlspecialchars($cust['name']); ?> 
                                                        (+$<?php echo number_format($cust['price_adjustment'], 2); ?>)
                                                        <?php if (!$cust['is_active']): ?>
                                                            <span style="color: #dc3545; font-size: 12px;">(Inactive)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 20px;">
                                <button type="submit" class="btn btn-success">Update Product</button>
                                <a href="admin.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Add Product Form -->
                    <div class="form-container">
                        <h3>Add New Product</h3>
                        <form method="POST">
                            <input type="hidden" name="add_product" value="1">
                            
                            <div class="form-group">
                                <label for="name">Product Name *</label>
                                <input type="text" id="name" name="name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="4" required></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="price">Price ($)</label>
                                <input type="number" id="price" name="price" step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" name="category">
                                    <option value="Pastries">Pastries</option>
                                    <option value="Cakes">Cakes</option>
                                    <option value="Cupcakes">Cupcakes</option>
                                    <option value="Cookies">Cookies</option>
                                    <option value="Breads">Breads</option>
                                    <option value="Tarts">Tarts</option>
                                    <option value="Muffins">Muffins</option>
                                    <option value="Custom">Custom</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="stock">Stock Quantity</label>
                                <input type="number" id="stock" name="stock" min="0" value="0">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_daily_special" name="is_daily_special">
                                    <label for="is_daily_special">Mark as Daily Special</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="discount">Discount Percentage (%)</label>
                                <input type="number" id="discount" name="discount" min="0" max="100" value="0">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="visibility" name="visibility" checked>
                                    <label for="visibility">Visible on Website</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Available Customizations</label>
                                <div class="customization-options">
                                    <?php
                                    $groupedCustomizations = [];
                                    foreach ($allCustomizations as $cust) {
                                        $groupedCustomizations[$cust['category']][] = $cust;
                                    }
                                    ?>
                                    <?php foreach ($groupedCustomizations as $category => $customizations): ?>
                                        <div class="customization-category">
                                            <h4><?php echo htmlspecialchars($category); ?></h4>
                                            <?php foreach ($customizations as $cust): ?>
                                                <div class="customization-option">
                                                    <input type="checkbox" name="available_customizations[]" value="<?php echo $cust['id']; ?>" 
                                                           id="cust-<?php echo $cust['id']; ?>" 
                                                           <?php echo $cust['is_active'] ? '' : 'disabled'; ?>>
                                                    <label for="cust-<?php echo $cust['id']; ?>" style="margin: 0;">
                                                        <?php echo htmlspecialchars($cust['name']); ?> 
                                                        (+$<?php echo number_format($cust['price_adjustment'], 2); ?>)
                                                        <?php if (!$cust['is_active']): ?>
                                                            <span style="color: #dc3545; font-size: 12px;">(Inactive)</span>
                                                        <?php endif; ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Add Product</button>
                        </form>
                    </div>
                    
                    <!-- Products Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Manage Products</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($products)): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Status</th>
                                                <th>Special</th>
                                                <th>Discount</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <i class="fas fa-<?php 
                                                            switch($product['category']) {
                                                                case 'Pastries': echo 'croissant'; break;
                                                                case 'Cakes': echo 'birthday-cake'; break;
                                                                case 'Cupcakes': echo 'cupcake'; break;
                                                                default: echo 'cookie';
                                                            }
                                                        ?>" style="color: var(--primary);"></i>
                                                        <span><?php echo htmlspecialchars($product['name']); ?></span>
                                                    </div>
                                                </td>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                <td><?php echo $product['stock_quantity']; ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $product['visibility'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $product['visibility'] ? 'Visible' : 'Hidden'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($product['is_daily_special']): ?>
                                                        <span class="status-badge special-badge">Daily Special</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($product['discount_percentage'] > 0): ?>
                                                        -<?php echo $product['discount_percentage']; ?>%
                                                    <?php endif; ?>
                                                </td>
                                                <td class="actions">
                                                    <a href="?edit_product=<?php echo $product['id']; ?>" class="action-btn edit-btn">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle visibility?');">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="current_visibility" value="<?php echo $product['visibility']; ?>">
                                                        <input type="hidden" name="toggle_visibility" value="1">
                                                        <button type="submit" class="action-btn toggle-btn">
                                                            <i class="fas fa-<?php echo $product['visibility'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                            <?php echo $product['visibility'] ? 'Hide' : 'Show'; ?>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="delete_product" value="1">
                                                        <button type="submit" class="action-btn delete-btn">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 40px;">No products found. Add your first product above.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Customizations Tab -->
            <div id="customizations" class="tab-content">
                <?php if ($editCustomization): ?>
                    <!-- Edit Customization Form -->
                    <div class="form-container">
                        <h3>Edit Customization: <?php echo htmlspecialchars($editCustomization['name']); ?></h3>
                        <form method="POST">
                            <input type="hidden" name="customization_id" value="<?php echo $editCustomization['id']; ?>">
                            <input type="hidden" name="update_customization" value="1">
                            
                            <div class="form-group">
                                <label for="customization_name">Customization Name *</label>
                                <input type="text" id="customization_name" name="customization_name" value="<?php echo htmlspecialchars($editCustomization['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customization_price">Price Adjustment ($)</label>
                                <input type="number" id="customization_price" name="customization_price" step="0.01" min="0" value="<?php echo $editCustomization['price_adjustment']; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customization_category">Category</label>
                                <input type="text" id="customization_category" name="customization_category" value="<?php echo htmlspecialchars($editCustomization['category']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="customization_active" name="customization_active" <?php echo $editCustomization['is_active'] ? 'checked' : ''; ?>>
                                    <label for="customization_active">Active (Available for selection)</label>
                                </div>
                            </div>
                            
                            <div style="display: flex; gap: 15px; margin-top: 20px;">
                                <button type="submit" class="btn btn-success">Update Customization</button>
                                <a href="admin.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Add Customization Form -->
                    <div class="form-container">
                        <h3>Add New Customization Option</h3>
                        <form method="POST">
                            <input type="hidden" name="add_customization" value="1">
                            
                            <div class="form-group">
                                <label for="customization_name">Customization Name *</label>
                                <input type="text" id="customization_name" name="customization_name" placeholder="e.g., Extra Chocolate, Gluten-Free" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customization_price">Price Adjustment ($)</label>
                                <input type="number" id="customization_price" name="customization_price" step="0.01" min="0" value="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="customization_category">Category</label>
                                <input type="text" id="customization_category" name="customization_category" value="Toppings" placeholder="e.g., Toppings, Dietary, Size">
                            </div>
                            
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="customization_active" name="customization_active" checked>
                                    <label for="customization_active">Active (Available for selection)</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Add Customization
                            </button>
                        </form>
                    </div>
                    
                    <!-- Customizations Table -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-cogs"></i> Manage Customization Options</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($allCustomizations)): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Customization</th>
                                                <th>Category</th>
                                                <th>Price</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($allCustomizations as $cust): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($cust['name']); ?></td>
                                                <td><?php echo htmlspecialchars($cust['category']); ?></td>
                                                <td>+$<?php echo number_format($cust['price_adjustment'], 2); ?></td>
                                                <td>
                                                    <span class="status-badge <?php echo $cust['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $cust['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td class="actions">
                                                    <a href="?edit_customization=<?php echo $cust['id']; ?>" class="action-btn edit-btn">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this customization? This will remove it from all products.');">
                                                        <input type="hidden" name="customization_id" value="<?php echo $cust['id']; ?>">
                                                        <input type="hidden" name="delete_customization" value="1">
                                                        <button type="submit" class="action-btn delete-btn">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p style="text-align: center; color: #666; padding: 40px;">No customization options available. Add some to allow customers to customize products.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Orders Tab -->
            <div id="orders" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-shopping-bag"></i> Order Management</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($ordersTableExists && !empty($recentOrders)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Email</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="status-badge <?php 
                                                    switch($order['status']) {
                                                        case 'completed': echo 'status-active'; break;
                                                        case 'pending': echo 'status-inactive'; break;
                                                        default: echo 'special-badge';
                                                    }
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <button class="action-btn edit-btn" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 40px;">
                                <?php echo $ordersTableExists ? 'No orders found.' : 'Orders table not available. Please run the database setup SQL to enable order management.'; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab navigation
        document.querySelectorAll('.sidebar-menu a').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    
                    // Remove active class from all tabs
                    document.querySelectorAll('.sidebar-menu a').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    const tabId = this.getAttribute('data-tab');
                    if (tabId) {
                        document.getElementById(tabId).classList.add('active');
                    }
                }
            });
        });

        // View order details (placeholder function)
        function viewOrder(orderId) {
            alert('Order details view would be implemented here for order ID: ' + orderId);
            // In a real implementation, this would open a modal with order details
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);

        // Add some animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('animated');
            });
        });
    </script>
</body>
</html>