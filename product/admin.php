<?php
session_start();

// Simple admin authentication
$admin_username = 'admin';
$admin_password = 'password'; // Change this password!

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
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
        <style>
            body {
                background: linear-gradient(135deg, #8B4513, #e0c99d);
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                font-family: Arial, sans-serif;
            }
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 400px;
            }
            .login-container h2 {
                text-align: center;
                margin-bottom: 30px;
                color: #8B4513;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 8px;
                font-weight: bold;
                color: #555;
            }
            input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
            }
            .btn {
                width: 100%;
                padding: 12px;
                background: #8B4513;
                color: white;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                margin-top: 10px;
            }
            .message {
                text-align: center;
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 4px;
                background: #f8d7da;
                color: #721c24;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>Admin Login</h2>
            <?php if (isset($error)): ?>
                <div class="message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
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
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new product
    if (isset($_POST['add_product'])) {
        try {
            $name = trim($_POST['name']);
            $description = trim($_POST['description']);
            $price = floatval($_POST['price']);
            $category = trim($_POST['category']);
            $stock = intval($_POST['stock']);
            $is_daily_special = isset($_POST['is_daily_special']) ? 1 : 0;
            $discount = floatval($_POST['discount']);
            $visibility = isset($_POST['visibility']) ? 1 : 0;
            
            // Insert product
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category, is_daily_special, discount_percentage, visibility, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description, $price, $category, $is_daily_special, $discount, $visibility, $stock]);
            
            $productId = $pdo->lastInsertId();
            
            // Link selected customizations to this product
            if (isset($_POST['available_customizations']) && is_array($_POST['available_customizations'])) {
                foreach ($_POST['available_customizations'] as $customizationId) {
                    $stmt = $pdo->prepare("INSERT INTO product_customizations (product_id, customization_id) VALUES (?, ?)");
                    $stmt->execute([$productId, $customizationId]);
                }
            }
            
            $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Product added successfully!</div>';
        } catch (Exception $e) {
            $message = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;">Error: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Add new customization option
    if (isset($_POST['add_customization'])) {
        try {
            $name = trim($_POST['customization_name']);
            $price = floatval($_POST['customization_price']);
            $category = trim($_POST['customization_category']);
            $is_active = isset($_POST['customization_active']) ? 1 : 0;
            
            $stmt = $pdo->prepare("INSERT INTO customizations (name, price_adjustment, category, is_active) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $price, $category, $is_active]);
            
            $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Customization option added successfully!</div>';
        } catch (Exception $e) {
            $message = '<div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin: 20px 0;">Error: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Toggle visibility
    if (isset($_POST['toggle_visibility'])) {
        $productId = intval($_POST['product_id']);
        $currentVisibility = intval($_POST['current_visibility']);
        $newVisibility = $currentVisibility ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE products SET visibility = ? WHERE id = ?");
        $stmt->execute([$newVisibility, $productId]);
        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Product visibility updated!</div>';
    }
    
    // Delete product
    if (isset($_POST['delete_product'])) {
        $productId = intval($_POST['product_id']);
        
        // Delete product customizations first (foreign key constraint)
        $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?");
        $stmt->execute([$productId]);
        
        // Delete product
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        
        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Product deleted successfully!</div>';
    }
    
    // Delete customization
    if (isset($_POST['delete_customization'])) {
        $customizationId = intval($_POST['customization_id']);
        
        // Delete from product_customizations first
        $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE customization_id = ?");
        $stmt->execute([$customizationId]);
        
        // Delete customization
        $stmt = $pdo->prepare("DELETE FROM customizations WHERE id = ?");
        $stmt->execute([$customizationId]);
        
        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Customization deleted successfully!</div>';
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
        
        // Update product customizations
        $stmt = $pdo->prepare("DELETE FROM product_customizations WHERE product_id = ?");
        $stmt->execute([$productId]);
        
        if (isset($_POST['available_customizations']) && is_array($_POST['available_customizations'])) {
            foreach ($_POST['available_customizations'] as $customizationId) {
                $stmt = $pdo->prepare("INSERT INTO product_customizations (product_id, customization_id) VALUES (?, ?)");
                $stmt->execute([$productId, $customizationId]);
            }
        }
        
        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Product updated successfully!</div>';
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
        
        $message = '<div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin: 20px 0;">Customization updated successfully!</div>';
    }
}

// Get all products
$stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all customizations
$stmt = $pdo->query("SELECT * FROM customizations ORDER BY category, name");
$allCustomizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product for editing
$editProduct = null;
$editProductCustomizations = [];
if (isset($_GET['edit_product'])) {
    $editId = intval($_GET['edit_product']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch(PDO::FETCH_ASSOC);
    
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
    $editCustomization = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Golden Treat Bakery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text);
            background-color: var(--light);
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }

        .admin-header h1 {
            color: var(--primary);
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.3s;
        }

        .btn:hover {
            background-color: var(--primary-light);
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-danger {
            background-color: var(--danger);
        }

        .btn-success {
            background-color: var(--success);
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .stat-card h3 {
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-card p {
            font-size: 24px;
            font-weight: bold;
            color: var(--dark);
        }

        .product-table, .customization-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .product-table th, .product-table td,
        .customization-table th, .customization-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .product-table th, .customization-table th {
            background-color: var(--secondary);
            color: var(--primary);
            font-weight: bold;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: bold;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }

        .special-badge {
            background-color: #fff3cd;
            color: #856404;
        }

        .actions-cell {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
            font-size: 14px;
        }

        .edit-btn {
            background-color: #007bff;
            color: white;
        }

        .delete-btn {
            background-color: var(--danger);
            color: white;
        }

        .toggle-btn {
            background-color: var(--success);
            color: white;
        }

        .toggle-btn.inactive {
            background-color: #6c757d;
        }

        .form-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--primary);
        }

        input, textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-size: 16px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .customization-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .customization-item input {
            flex: 1;
        }

        .remove-customization {
            background: var(--danger);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
        }

        .logout-btn {
            background: var(--danger);
        }

        .nav-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--secondary);
        }

        .nav-tab {
            padding: 12px 24px;
            background: var(--light);
            border: none;
            cursor: pointer;
            font-weight: bold;
            color: var(--dark);
            transition: all 0.3s;
        }

        .nav-tab.active {
            background: var(--primary);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .customization-category {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .customization-category h4 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .customization-option {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .customization-option input[type="checkbox"] {
            margin-right: 10px;
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                gap: 15px;
            }
            
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .actions-cell {
                flex-direction: column;
                gap: 5px;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .nav-tab {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-header">
            <h1><i class="fas fa-user-shield"></i> Admin Dashboard</h1>
            <div>
                <a href="index.php" class="btn" style="background: #28a745; margin-right: 10px;">
                    <i class="fas fa-store"></i> View Store
                </a>
                <a href="?logout=1" class="btn logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <?php 
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: admin.php');
            exit;
        }
        echo $message; 
        ?>
        
        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab active" data-tab="products">Products</button>
            <button class="nav-tab" data-tab="customizations">Customizations</button>
        </div>
        
        <!-- Products Tab -->
        <div id="products" class="tab-content active">
            <?php if ($editProduct): ?>
                <!-- Edit Product Form -->
                <div class="form-container">
                    <h2>Edit Product: <?php echo htmlspecialchars($editProduct['name']); ?></h2>
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
                            <?php
                            // Group customizations by category
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
                                                   <?php echo in_array($cust['id'], $editProductCustomizations) ? 'checked' : ''; ?>>
                                            <label for="cust-<?php echo $cust['id']; ?>">
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
                        
                        <div style="display: flex; gap: 15px; margin-top: 20px;">
                            <button type="submit" class="btn">Update Product</button>
                            <a href="admin.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Add Product Form -->
                <div class="form-container">
                    <h2>Add New Product</h2>
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
                            <?php
                            // Group customizations by category
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
                                            <label for="cust-<?php echo $cust['id']; ?>">
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
                        
                        <button type="submit" class="btn">Add Product</button>
                    </form>
                </div>
                
                <!-- Dashboard Stats -->
                <div class="dashboard-stats">
                    <div class="stat-card">
                        <h3>Total Products</h3>
                        <p><?php echo count($products); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Daily Specials</h3>
                        <p><?php echo count(array_filter($products, fn($p) => $p['is_daily_special'])); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Visible Products</h3>
                        <p><?php echo count(array_filter($products, fn($p) => $p['visibility'])); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Customizations</h3>
                        <p><?php echo count($allCustomizations); ?></p>
                    </div>
                </div>
                
                <!-- Manage Products Table -->
                <h2>Manage Products</h2>
                <table class="product-table">
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
                                    <div style="font-size: 24px;">🥐</div>
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
                            <td class="actions-cell">
                                <a href="?edit_product=<?php echo $product['id']; ?>" class="action-btn edit-btn">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle visibility?');">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="hidden" name="current_visibility" value="<?php echo $product['visibility']; ?>">
                                    <input type="hidden" name="toggle_visibility" value="1">
                                    <button type="submit" class="action-btn toggle-btn <?php echo !$product['visibility'] ? 'inactive' : ''; ?>">
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
            <?php endif; ?>
        </div>
        
        <!-- Customizations Tab -->
        <div id="customizations" class="tab-content">
            <?php if ($editCustomization): ?>
                <!-- Edit Customization Form -->
                <div class="form-container">
                    <h2>Edit Customization: <?php echo htmlspecialchars($editCustomization['name']); ?></h2>
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
                            <button type="submit" class="btn">Update Customization</button>
                            <a href="admin.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Add Customization Form -->
                <div class="form-container">
                    <h2>Add New Customization Option</h2>
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
                
                <!-- Manage Customizations Table -->
                <h2>Manage Customization Options</h2>
                <?php if (!empty($allCustomizations)): ?>
                    <table class="customization-table">
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
                                <td class="actions-cell">
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
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; color: #666;">No customization options available. Add some to allow customers to customize products.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Tab navigation
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and content
                document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });
    </script>
</body>
</html>