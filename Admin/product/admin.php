<?php
// admin.php - Full Admin Panel Code with Customization Integration
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
}

// Database Configuration
$host = 'localhost';
$dbname = 'golden_treat';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Initialize variables
$message = '';
$messageType = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'dashboard';

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Add new product
        if (isset($_POST['add_product'])) {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock_quantity']);
            $is_daily_special = isset($_POST['is_daily_special']) ? 1 : 0;
            $discount = floatval($_POST['discount_percentage']);
            $visibility = isset($_POST['visibility']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, is_daily_special, discount_percentage, visibility, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $category, $is_daily_special, $discount, $visibility, $stock]);
            
            $productId = $pdo->lastInsertId();
            
            // Handle customizations
            if (isset($_POST['customizations']) && is_array($_POST['customizations'])) {
                foreach ($_POST['customizations'] as $customizationId) {
                    $stmt = $pdo->prepare("INSERT INTO product_customizations (product_id, customization_id) VALUES (?, ?)");
                    $stmt->execute([$productId, $customizationId]);
                }
            }
            
            $message = "🎉 Product added successfully!";
            $messageType = 'success';
            $active_tab = 'products';
        }
        
        // Update product
        elseif (isset($_POST['update_product'])) {
            $productId = intval($_POST['product_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock_quantity']);
            $is_daily_special = isset($_POST['is_daily_special']) ? 1 : 0;
            $discount = floatval($_POST['discount_percentage']);
            $visibility = isset($_POST['visibility']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, stock_quantity = ?, is_daily_special = ?, discount_percentage = ?, visibility = ? WHERE id = ?");
            $stmt->execute([$name, $description, $price, $category, $stock, $is_daily_special, $discount, $visibility, $productId]);
            
            // Update customizations
            $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            if (isset($_POST['customizations']) && is_array($_POST['customizations'])) {
                foreach ($_POST['customizations'] as $customizationId) {
                    $stmt = $pdo->prepare("INSERT INTO product_customizations (product_id, customization_id) VALUES (?, ?)");
                    $stmt->execute([$productId, $customizationId]);
                }
            }
            
            $message = "✅ Product updated successfully!";
            $messageType = 'success';
            $active_tab = 'products';
        }
        
        // Add new customization
        elseif (isset($_POST['add_customization'])) {
            $name = trim($_POST['name']);
            $price_adjustment = floatval($_POST['price_adjustment']);
            $category = trim($_POST['category']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO customizations (name, price_adjustment, category, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $price_adjustment, $category, $is_active]);
            
            $message = "🎉 Customization added successfully!";
            $messageType = 'success';
            $active_tab = 'customizations';
        }
        
        // Update customization
        elseif (isset($_POST['update_customization'])) {
            $customizationId = intval($_POST['customization_id']);
            $name = trim($_POST['name']);
            $price_adjustment = floatval($_POST['price_adjustment']);
            $category = trim($_POST['category']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE customizations SET name = ?, price_adjustment = ?, category = ?, is_active = ? WHERE id = ?");
            $stmt->execute([$name, $price_adjustment, $category, $is_active, $customizationId]);
            
            $message = "✅ Customization updated successfully!";
            $messageType = 'success';
            $active_tab = 'customizations';
        }
        
        // Delete product
        elseif (isset($_POST['delete_product'])) {
            $productId = intval($_POST['product_id']);
            
            // Delete from product_customizations first
            $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            // Delete from cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE product_id = ?");
            $stmt->execute([$productId]);
            
            // Delete product
            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$productId]);
            
            $message = "🗑️ Product deleted successfully!";
            $messageType = 'success';
            $active_tab = 'products';
        }
        
        // Delete customization
        elseif (isset($_POST['delete_customization'])) {
            $customizationId = intval($_POST['customization_id']);
            
            // Delete from product_customizations first
            $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE customization_id = ?");
            $stmt->execute([$customizationId]);
            
            // Delete customization
            $stmt = $pdo->prepare("DELETE FROM customizations WHERE id = ?");
            $stmt->execute([$customizationId]);
            
            $message = "🗑️ Customization deleted successfully!";
            $messageType = 'success';
            $active_tab = 'customizations';
        }
        
        // Toggle product visibility
        elseif (isset($_POST['toggle_visibility'])) {
            $productId = intval($_POST['product_id']);
            $currentVisibility = intval($_POST['current_visibility']);
            $newVisibility = $currentVisibility ? 0 : 1;
            
            $stmt = $pdo->prepare("UPDATE products SET visibility = ? WHERE id = ?");
            $stmt->execute([$newVisibility, $productId]);
            
            $message = "👁️ Product visibility " . ($newVisibility ? "enabled" : "disabled") . "!";
            $messageType = 'success';
            $active_tab = 'products';
        }
        
        // Update order status
        elseif (isset($_POST['update_order_status'])) {
            $orderId = intval($_POST['order_id']);
            $status = $_POST['status'];
            
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$status, $orderId]);
            
            $message = "✅ Order status updated to " . ucfirst($status) . "!";
            $messageType = 'success';
            $active_tab = 'orders';
        }

    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get all data for the dashboard
try {
    // Products
    $products = $pdo->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
    
    // Customizations
    $customizations = $pdo->query("SELECT * FROM customizations ORDER BY category, name")->fetchAll();
    
    // Orders (if table exists)
    try {
        $orders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 50")->fetchAll();
        
        // Get order items for each order
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $orders = [];
    }
    
    // Dashboard statistics
    $totalProducts = count($products);
    $visibleProducts = count(array_filter($products, fn($p) => $p['visibility']));
    $lowStockProducts = count(array_filter($products, fn($p) => $p['stock_quantity'] < 10 && $p['stock_quantity'] > 0));
    $outOfStockProducts = count(array_filter($products, fn($p) => $p['stock_quantity'] == 0));
    
    $totalCustomizations = count($customizations);
    $activeCustomizations = count(array_filter($customizations, fn($c) => $c['is_active']));
    
    $totalOrders = count($orders);
    $pendingOrders = count(array_filter($orders, fn($o) => $o['status'] == 'pending'));
    $revenue = array_sum(array_column($orders, 'total_amount'));

} catch (Exception $e) {
    die("Error loading data: " . $e->getMessage());
}

// Handle edit modes
$editProduct = null;
$editCustomization = null;

if (isset($_GET['edit_product'])) {
    $productId = intval($_GET['edit_product']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $editProduct = $stmt->fetch();
    
    if ($editProduct) {
        $stmt = $pdo->prepare("SELECT customization_id FROM product_customizations WHERE product_id = ?");
        $stmt->execute([$productId]);
        $editProduct['customizations'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $active_tab = 'products';
    }
}

if (isset($_GET['edit_customization'])) {
    $customizationId = intval($_GET['edit_customization']);
    $stmt = $pdo->prepare("SELECT * FROM customizations WHERE id = ?");
    $stmt->execute([$customizationId]);
    $editCustomization = $stmt->fetch();
    $active_tab = 'customizations';
}

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
            --primary: #0a0908ff;
            --primary-light: #00000057;
            --secondary: #e0c99d;
            --accent: #d4af37;
            --light: #f8f4e9;
            --dark: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --info: #3498db;
            --text: #2c3e50;
            --border: #bdc3c7;
            --shadow: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.15);
            --radius: 12px;
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
            width: 280px;
            background: linear-gradient(180deg, var(--primary) 0%, var(--primary-light) 100%);
            color: white;
            padding: 0;
            box-shadow: var(--shadow-lg);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 30px 25px;
            background: rgba(0,0,0,0.1);
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 700;
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
            gap: 15px;
            padding: 15px 25px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            border-left-color: var(--accent);
            transform: translateX(5px);
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #f8f9fa;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 25px;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .admin-header h2 {
            color: var(--primary);
            font-size: 2.2rem;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }

        .btn-success { background: linear-gradient(135deg, var(--success), #2ecc71); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #e74c3c); }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #f39c12); }
        .btn-info { background: linear-gradient(135deg, var(--info), #3498db); }
        .btn-secondary { background: linear-gradient(135deg, #95a5a6, #7f8c8d); }

        /* Dashboard Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card i {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 20px;
            opacity: 0.8;
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .stat-card .trend {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .trend.up { color: var(--success); }
        .trend.down { color: var(--danger); }

        /* Card Styles */
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, var(--secondary), #060505ff);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: between;
            align-items: center;
        }

        .card-header h3 {
            color: var(--primary);
            font-size: 1.4rem;
            font-weight: 600;
        }

        .card-body {
            padding: 30px;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table th,
        .table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #ecf0f1;
        }

        .table th {
            background: #f8f9fa;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table tr:hover {
            background: #f8f9fa;
        }

        .table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active { background: #d5f4e6; color: #27ae60; }
        .status-inactive { background: #fadbd8; color: #e74c3c; }
        .status-pending { background: #fdebd0; color: #f39c12; }
        .status-completed { background: #d5f4e6; color: #27ae60; }
        .status-processing { background: #d6eaf8; color: #3498db; }

        /* Action Buttons */
        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .action-btn:hover {
            transform: translateY(-1px);
        }

        .btn-edit { background: var(--info); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-view { background: var(--success); color: white; }
        .btn-toggle { background: var(--warning); color: white; }

        /* Form Styles */
        .form-container {
            background: white;
            padding: 40px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--secondary);
        }

        .form-header h3 {
            color: var(--primary);
            font-size: 1.6rem;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
            transform: scale(1.2);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }

        /* Customization Options */
        .customization-options {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            padding: 20px;
        }

        .customization-category {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ecf0f1;
        }

        .customization-category:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .customization-category h4 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .customization-option {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            transition: background 0.3s ease;
        }

        .customization-option:hover {
            background: #ecf0f1;
        }

        .customization-option input {
            margin-right: 15px;
            width: auto;
        }

        .customization-option label {
            margin: 0;
            flex: 1;
            font-weight: normal;
            color: var(--text);
        }

        .customization-price {
            color: var(--success);
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Message Styles */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .message.success {
            background: #d5f4e6;
            color: #27ae60;
            border-left: 4px solid var(--success);
        }

        .message.error {
            background: #fadbd8;
            color: #e74c3c;
            border-left: 4px solid var(--danger);
        }

        .message.warning {
            background: #fdebd0;
            color: #f39c12;
            border-left: 4px solid var(--warning);
        }

        /* Tab Content */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Order Details */
        .order-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .order-items {
            margin-top: 20px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid var(--primary);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .sidebar {
                width: 250px;
            }
            .main-content {
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .actions {
                flex-direction: column;
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Badges */
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success { background: var(--success); color: white; }
        .badge-warning { background: var(--warning); color: white; }
        .badge-danger { background: var(--danger); color: white; }
        .badge-info { background: var(--info); color: white; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: #95a5a6;
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
                <li><a href="#dashboard" class="<?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>" data-tab="dashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#products" class="<?php echo $active_tab == 'products' ? 'active' : ''; ?>" data-tab="products"><i class="fas fa-cookie-bite"></i> Products</a></li>
                <li><a href="#customizations" class="<?php echo $active_tab == 'customizations' ? 'active' : ''; ?>" data-tab="customizations"><i class="fas fa-magic"></i> Customizations</a></li>
                <li><a href="#orders" class="<?php echo $active_tab == 'orders' ? 'active' : ''; ?>" data-tab="orders"><i class="fas fa-shopping-bag"></i> Orders</a></li>
                <li><a href="index.php" target="_blank"><i class="fas fa-store"></i> View Store</a></li>
                <li><a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="admin-header">
                <h2><i class="fas fa-user-shield"></i> Admin Dashboard</h2>
                <div class="user-info">
                    <div class="user-avatar">A</div>
                    <span>Welcome, Administrator!</span>
                    <a href="index.php" target="_blank" class="btn btn-success">
                        <i class="fas fa-store"></i> Back To Store
                    </a>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : ($messageType == 'warning' ? 'exclamation-triangle' : 'exclamation-circle'); ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])) {
                session_destroy();
                header('Location: admin.php');
                exit;
            } ?>

            <!-- Dashboard Tab -->
            <div id="dashboard" class="tab-content <?php echo $active_tab == 'dashboard' ? 'active' : ''; ?>">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-cookie-bite"></i>
                        <h3>Total Products</h3>
                        <div class="number"><?php echo $totalProducts; ?></div>
                        <div class="trend up">+<?php echo $visibleProducts; ?> visible</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-shopping-bag"></i>
                        <h3>Total Orders</h3>
                        <div class="number"><?php echo $totalOrders; ?></div>
                        <div class="trend <?php echo $pendingOrders > 0 ? 'warning' : 'up'; ?>">
                            <?php echo $pendingOrders; ?> pending
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-dollar-sign"></i>
                        <h3>Total Revenue</h3>
                        <div class="number">$<?php echo number_format($revenue, 2); ?></div>
                        <div class="trend up">All time</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-magic"></i>
                        <h3>Customizations</h3>
                        <div class="number"><?php echo $totalCustomizations; ?></div>
                        <div class="trend up">+<?php echo $activeCustomizations; ?> active</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div class="actions" style="justify-content: center; gap: 20px;">
                            <a href="#products" class="btn btn-primary" data-tab="products">
                                <i class="fas fa-plus"></i> Add New Product
                            </a>
                            <a href="#customizations" class="btn btn-info" data-tab="customizations">
                                <i class="fas fa-magic"></i> Manage Customizations
                            </a>
                            <a href="#orders" class="btn btn-success" data-tab="orders">
                                <i class="fas fa-shopping-bag"></i> View Orders
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Products -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-clock"></i> Recent Products</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($products)): ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($products, 0, 5) as $product): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 12px;">
                                                    <i class="fas fa-<?php 
                                                        switch($product['category']) {
                                                            case 'Pastries': echo 'croissant'; break;
                                                            case 'Cakes': echo 'birthday-cake'; break;
                                                            case 'Cupcakes': echo 'cupcake'; break;
                                                            case 'Breads': echo 'bread-slice'; break;
                                                            default: echo 'cookie';
                                                        }
                                                    ?>" style="color: var(--primary); font-size: 1.2rem;"></i>
                                                    <div>
                                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></div>
                                                        <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo $product['category']; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><strong>$<?php echo number_format($product['price'], 2); ?></strong></td>
                                            <td>
                                                <?php if ($product['stock_quantity'] > 10): ?>
                                                    <span class="badge badge-success"><?php echo $product['stock_quantity']; ?> in stock</span>
                                                <?php elseif ($product['stock_quantity'] > 0): ?>
                                                    <span class="badge badge-warning">Low stock (<?php echo $product['stock_quantity']; ?>)</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Out of stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $product['visibility'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $product['visibility'] ? 'Visible' : 'Hidden'; ?>
                                                </span>
                                                <?php if ($product['is_daily_special']): ?>
                                                    <span class="badge badge-warning" style="margin-left: 5px;">Special</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="actions">
                                                <a href="?edit_product=<?php echo $product['id']; ?>&tab=products" class="action-btn btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <input type="hidden" name="current_visibility" value="<?php echo $product['visibility']; ?>">
                                                    <input type="hidden" name="toggle_visibility" value="1">
                                                    <button type="submit" class="action-btn btn-toggle">
                                                        <i class="fas fa-<?php echo $product['visibility'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                        <?php echo $product['visibility'] ? 'Hide' : 'Show'; ?>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-cookie-bite"></i>
                                <h3>No Products Yet</h3>
                                <p>Get started by adding your first product!</p>
                                <a href="#products" class="btn btn-primary" data-tab="products" style="margin-top: 20px;">
                                    <i class="fas fa-plus"></i> Add Product
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Products Tab -->
            <div id="products" class="tab-content <?php echo $active_tab == 'products' ? 'active' : ''; ?>">
                <?php if ($editProduct): ?>
                    <!-- Edit Product Form -->
                    <div class="form-container">
                        <div class="form-header">
                            <h3><i class="fas fa-edit"></i> Edit Product: <?php echo htmlspecialchars($editProduct['name']); ?></h3>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="product_id" value="<?php echo $editProduct['id']; ?>">
                            <input type="hidden" name="update_product" value="1">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Product Name *</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editProduct['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="price">Price ($) *</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo $editProduct['price']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category *</label>
                                    <select id="category" name="category" required>
                                        <option value="Pastries" <?php echo $editProduct['category'] === 'Pastries' ? 'selected' : ''; ?>>Pastries</option>
                                        <option value="Cakes" <?php echo $editProduct['category'] === 'Cakes' ? 'selected' : ''; ?>>Cakes</option>
                                        <option value="Cupcakes" <?php echo $editProduct['category'] === 'Cupcakes' ? 'selected' : ''; ?>>Cupcakes</option>
                                        <option value="Cookies" <?php echo $editProduct['category'] === 'Cookies' ? 'selected' : ''; ?>>Cookies</option>
                                        <option value="Breads" <?php echo $editProduct['category'] === 'Breads' ? 'selected' : ''; ?>>Breads</option>
                                        <option value="Tarts" <?php echo $editProduct['category'] === 'Tarts' ? 'selected' : ''; ?>>Tarts</option>
                                        <option value="Muffins" <?php echo $editProduct['category'] === 'Muffins' ? 'selected' : ''; ?>>Muffins</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock_quantity">Stock Quantity</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo $editProduct['stock_quantity']; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($editProduct['description']); ?></textarea>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="discount_percentage">Discount Percentage (%)</label>
                                    <input type="number" id="discount_percentage" name="discount_percentage" min="0" max="100" value="<?php echo $editProduct['discount_percentage']; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_daily_special" name="is_daily_special" <?php echo $editProduct['is_daily_special'] ? 'checked' : ''; ?>>
                                        <label for="is_daily_special">Mark as Daily Special</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="visibility" name="visibility" <?php echo $editProduct['visibility'] ? 'checked' : ''; ?>>
                                        <label for="visibility">Visible on Website</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Available Customizations</label>
                                <div class="customization-options">
                                    <?php if (!empty($customizations)): ?>
                                        <?php
                                        $groupedCustomizations = [];
                                        foreach ($customizations as $cust) {
                                            if ($cust['is_active']) {
                                                $groupedCustomizations[$cust['category']][] = $cust;
                                            }
                                        }
                                        ?>
                                        <?php foreach ($groupedCustomizations as $category => $categoryCustomizations): ?>
                                            <div class="customization-category">
                                                <h4><?php echo htmlspecialchars($category); ?></h4>
                                                <?php foreach ($categoryCustomizations as $cust): ?>
                                                    <div class="customization-option">
                                                        <input type="checkbox" name="customizations[]" value="<?php echo $cust['id']; ?>" 
                                                               id="cust-<?php echo $cust['id']; ?>" 
                                                               <?php echo in_array($cust['id'], $editProduct['customizations']) ? 'checked' : ''; ?>>
                                                        <label for="cust-<?php echo $cust['id']; ?>">
                                                            <?php echo htmlspecialchars($cust['name']); ?>
                                                        </label>
                                                        <span class="customization-price">+$<?php echo number_format($cust['price_adjustment'], 2); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #7f8c8d; padding: 20px;">No customizations available. Add some in the Customizations tab.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update Product
                                </button>
                                <a href="?tab=products" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Add Product Form -->
                    <div class="form-container">
                        <div class="form-header">
                            <h3><i class="fas fa-plus"></i> Add New Product</h3>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="add_product" value="1">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Product Name *</label>
                                    <input type="text" id="name" name="name" required placeholder="e.g., Chocolate Croissant">
                                </div>
                                
                                <div class="form-group">
                                    <label for="price">Price ($) *</label>
                                    <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category *</label>
                                    <select id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Pastries">Pastries</option>
                                        <option value="Cakes">Cakes</option>
                                        <option value="Cupcakes">Cupcakes</option>
                                        <option value="Cookies">Cookies</option>
                                        <option value="Breads">Breads</option>
                                        <option value="Tarts">Tarts</option>
                                        <option value="Muffins">Muffins</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="stock_quantity">Stock Quantity</label>
                                    <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="0">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description *</label>
                                <textarea id="description" name="description" rows="4" required placeholder="Describe your product..."></textarea>
                            </div>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="discount_percentage">Discount Percentage (%)</label>
                                    <input type="number" id="discount_percentage" name="discount_percentage" min="0" max="100" value="0">
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_daily_special" name="is_daily_special">
                                        <label for="is_daily_special">Mark as Daily Special</label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="visibility" name="visibility" checked>
                                        <label for="visibility">Visible on Website</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Available Customizations</label>
                                <div class="customization-options">
                                    <?php if (!empty($customizations)): ?>
                                        <?php
                                        $groupedCustomizations = [];
                                        foreach ($customizations as $cust) {
                                            if ($cust['is_active']) {
                                                $groupedCustomizations[$cust['category']][] = $cust;
                                            }
                                        }
                                        ?>
                                        <?php foreach ($groupedCustomizations as $category => $categoryCustomizations): ?>
                                            <div class="customization-category">
                                                <h4><?php echo htmlspecialchars($category); ?></h4>
                                                <?php foreach ($categoryCustomizations as $cust): ?>
                                                    <div class="customization-option">
                                                        <input type="checkbox" name="customizations[]" value="<?php echo $cust['id']; ?>" 
                                                               id="cust-<?php echo $cust['id']; ?>">
                                                        <label for="cust-<?php echo $cust['id']; ?>">
                                                            <?php echo htmlspecialchars($cust['name']); ?>
                                                        </label>
                                                        <span class="customization-price">+$<?php echo number_format($cust['price_adjustment'], 2); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p style="text-align: center; color: #7f8c8d; padding: 20px;">No customizations available. Add some in the Customizations tab.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Product
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Products List -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-list"></i> Manage Products (<?php echo count($products); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($products)): ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
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
                                                    <div style="display: flex; align-items: center; gap: 12px;">
                                                        <i class="fas fa-<?php 
                                                            switch($product['category']) {
                                                                case 'Pastries': echo 'croissant'; break;
                                                                case 'Cakes': echo 'birthday-cake'; break;
                                                                case 'Cupcakes': echo 'cupcake'; break;
                                                                case 'Breads': echo 'bread-slice'; break;
                                                                default: echo 'cookie';
                                                            }
                                                        ?>" style="color: var(--primary); font-size: 1.4rem;"></i>
                                                        <div>
                                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></div>
                                                            <div style="font-size: 0.8rem; color: #7f8c8d;">
                                                                <?php echo strlen($product['description']) > 50 ? substr($product['description'], 0, 50) . '...' : $product['description']; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo $product['category']; ?></span>
                                                    <?php if ($product['is_daily_special']): ?>
                                                        <span class="badge badge-warning" style="margin-left: 5px;">Special</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong>$<?php echo number_format($product['price'], 2); ?></strong>
                                                    <?php if ($product['discount_percentage'] > 0): ?>
                                                        <div style="font-size: 0.8rem; color: var(--success);">
                                                            -<?php echo $product['discount_percentage']; ?>%
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($product['stock_quantity'] > 10): ?>
                                                        <span class="badge badge-success"><?php echo $product['stock_quantity']; ?></span>
                                                    <?php elseif ($product['stock_quantity'] > 0): ?>
                                                        <span class="badge badge-warning"><?php echo $product['stock_quantity']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $product['visibility'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $product['visibility'] ? 'Visible' : 'Hidden'; ?>
                                                    </span>
                                                </td>
                                                <td class="actions">
                                                    <a href="?edit_product=<?php echo $product['id']; ?>&tab=products" class="action-btn btn-edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="current_visibility" value="<?php echo $product['visibility']; ?>">
                                                        <input type="hidden" name="toggle_visibility" value="1">
                                                        <button type="submit" class="action-btn btn-toggle">
                                                            <i class="fas fa-<?php echo $product['visibility'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this product? This action cannot be undone.');">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="delete_product" value="1">
                                                        <button type="submit" class="action-btn btn-delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-cookie-bite"></i>
                                    <h3>No Products Found</h3>
                                    <p>Get started by adding your first product using the form above.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Customizations Tab -->
            <div id="customizations" class="tab-content <?php echo $active_tab == 'customizations' ? 'active' : ''; ?>">
                <?php if ($editCustomization): ?>
                    <!-- Edit Customization Form -->
                    <div class="form-container">
                        <div class="form-header">
                            <h3><i class="fas fa-edit"></i> Edit Customization: <?php echo htmlspecialchars($editCustomization['name']); ?></h3>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="customization_id" value="<?php echo $editCustomization['id']; ?>">
                            <input type="hidden" name="update_customization" value="1">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Customization Name *</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editCustomization['name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="price_adjustment">Price Adjustment ($) *</label>
                                    <input type="number" id="price_adjustment" name="price_adjustment" step="0.01" value="<?php echo $editCustomization['price_adjustment']; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category *</label>
                                    <input type="text" id="category" name="category" value="<?php echo htmlspecialchars($editCustomization['category']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_active" name="is_active" <?php echo $editCustomization['is_active'] ? 'checked' : ''; ?>>
                                        <label for="is_active">Active (Available for selection)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save"></i> Update Customization
                                </button>
                                <a href="?tab=customizations" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- Add Customization Form -->
                    <div class="form-container">
                        <div class="form-header">
                            <h3><i class="fas fa-plus"></i> Add New Customization</h3>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="add_customization" value="1">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Customization Name *</label>
                                    <input type="text" id="name" name="name" required placeholder="e.g., Extra Chocolate, Gluten-Free">
                                </div>
                                
                                <div class="form-group">
                                    <label for="price_adjustment">Price Adjustment ($) *</label>
                                    <input type="number" id="price_adjustment" name="price_adjustment" step="0.01" value="0.50" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="category">Category *</label>
                                    <input type="text" id="category" name="category" value="Toppings" required placeholder="e.g., Toppings, Dietary, Size">
                                </div>
                                
                                <div class="form-group">
                                    <div class="checkbox-group">
                                        <input type="checkbox" id="is_active" name="is_active" checked>
                                        <label for="is_active">Active (Available for selection)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus"></i> Add Customization
                                </button>
                                <button type="reset" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Customizations List -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-cogs"></i> Manage Customizations (<?php echo count($customizations); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($customizations)): ?>
                                <div class="table-container">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Price Adjustment</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($customizations as $cust): ?>
                                            <tr>
                                                <td>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($cust['name']); ?></div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info"><?php echo htmlspecialchars($cust['category']); ?></span>
                                                </td>
                                                <td>
                                                    <strong style="color: var(--success);">+$<?php echo number_format($cust['price_adjustment'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo $cust['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                        <?php echo $cust['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td class="actions">
                                                    <a href="?edit_customization=<?php echo $cust['id']; ?>&tab=customizations" class="action-btn btn-edit">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this customization? This will remove it from all products.');">
                                                        <input type="hidden" name="customization_id" value="<?php echo $cust['id']; ?>">
                                                        <input type="hidden" name="delete_customization" value="1">
                                                        <button type="submit" class="action-btn btn-delete">
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
                                <div class="empty-state">
                                    <i class="fas fa-magic"></i>
                                    <h3>No Customizations Found</h3>
                                    <p>Get started by adding your first customization using the form above.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Orders Tab -->
            <div id="orders" class="tab-content <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-shopping-bag"></i> Order Management (<?php echo count($orders); ?>)</h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($orders)): ?>
                            <div class="table-container">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--primary);"><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                                                    <div style="font-size: 0.8rem; color: #7f8c8d;"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>$<?php echo number_format($order['total_amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <select name="status" onchange="this.form.submit()" style="padding: 6px; border-radius: 4px; border: 1px solid #ddd;">
                                                        <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="confirmed" <?php echo $order['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                        <option value="preparing" <?php echo $order['status'] == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                        <option value="ready" <?php echo $order['status'] == 'ready' ? 'selected' : ''; ?>>Ready</option>
                                                        <option value="completed" <?php echo $order['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    </select>
                                                    <input type="hidden" name="update_order_status" value="1">
                                                </form>
                                            </td>
                                            <td>
                                                <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="actions">
                                                <button class="action-btn btn-view" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-bag"></i>
                                <h3>No Orders Yet</h3>
                                <p>Orders will appear here when customers place orders through the website.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Tab navigation
        document.querySelectorAll('.sidebar-menu a[data-tab]').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Remove active class from all tabs
                document.querySelectorAll('.sidebar-menu a').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
                
                // Update URL without reloading
                history.pushState(null, null, '?tab=' + tabId);
            });
        });

        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'dashboard';
            
            document.querySelectorAll('.sidebar-menu a').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
            document.getElementById(tab).classList.add('active');
        });

        // View order details
        function viewOrder(orderId) {
            alert('Order details for order #' + orderId + '\n\nThis would open a detailed order view with items, customer info, etc.');
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

        // Add animations to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.style.animation = 'fadeIn 0.6s ease forwards';
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const requiredFields = this.querySelectorAll('[required]');
                let valid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        valid = false;
                        field.style.borderColor = 'var(--danger)';
                    } else {
                        field.style.borderColor = '';
                    }
                });
                
                if (!valid) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });

        // Real-time stock validation
        document.querySelectorAll('input[name="stock_quantity"]').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });

        // Price validation
        document.querySelectorAll('input[name="price"], input[name="price_adjustment"]').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
            });
        });
    </script>
</body>
</html>