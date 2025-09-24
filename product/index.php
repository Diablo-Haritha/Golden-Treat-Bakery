<?php
session_start();

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

// Cart functionality
$cartMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_to_cart'])) {
        $productId = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']);
        $selectedCustomizations = isset($_POST['customizations']) ? $_POST['customizations'] : [];
        
        // Validate product exists and is visible
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND visibility = 1");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $product['stock_quantity'] >= $quantity) {
            $sessionId = session_id();
            $basePrice = $product['price'];
            
            // Calculate total price with customizations
            $totalPrice = $basePrice;
            if (!empty($selectedCustomizations)) {
                $placeholders = str_repeat('?,', count($selectedCustomizations) - 1) . '?';
                $stmt = $pdo->prepare("SELECT SUM(price_adjustment) as total FROM customizations WHERE id IN ($placeholders) AND is_active = 1");
                $stmt->execute($selectedCustomizations);
                $customTotal = $stmt->fetchColumn();
                $totalPrice += (float)$customTotal;
            }
            $totalPrice *= $quantity;
            
            // Add to cart
            $stmt = $pdo->prepare("INSERT INTO cart (session_id, product_id, quantity, selected_customizations, total_price) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $productId, $quantity, json_encode($selectedCustomizations), $totalPrice]);
            
            // Update stock
            $newStock = $product['stock_quantity'] - $quantity;
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$newStock, $productId]);
            
            $cartMessage = "Product added to cart successfully!";
        } else {
            $cartMessage = "Product not available or insufficient stock.";
        }
    }
    
    if (isset($_POST['remove_from_cart'])) {
        $cartId = intval($_POST['cart_id']);
        $sessionId = session_id();
        
        // Get product info to restore stock
        $stmt = $pdo->prepare("SELECT p.id, p.stock_quantity, c.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.session_id = ?");
        $stmt->execute([$cartId, $sessionId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            // Restore stock
            $newStock = $item['stock_quantity'] + $item['quantity'];
            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $stmt->execute([$newStock, $item['id']]);
            
            // Remove from cart
            $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
            $stmt->execute([$cartId, $sessionId]);
        }
    }
}

// Get cart items
$sessionId = session_id();
$stmt = $pdo->prepare("SELECT c.*, p.name as product_name, p.price as base_price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.session_id = ? ORDER BY c.added_at DESC");
$stmt->execute([$sessionId]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate cart total and count
$cartTotal = 0;
$cartCount = 0;
foreach ($cartItems as $item) {
    $cartTotal += $item['total_price'];
    $cartCount += $item['quantity'];
}

// Get all visible products
$stmt = $pdo->prepare("SELECT * FROM products WHERE visibility = 1 ORDER BY is_daily_special DESC, id");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get available customizations for a product
function getAvailableCustomizations($pdo, $productId) {
    $stmt = $pdo->prepare("
        SELECT c.* FROM customizations c 
        INNER JOIN product_customizations pc ON c.id = pc.customization_id 
        WHERE pc.product_id = ? AND c.is_active = 1 
        ORDER BY c.category, c.name
    ");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get customization names by IDs
function getCustomizationNames($pdo, $customizationIds) {
    if (empty($customizationIds)) return [];
    
    $placeholders = str_repeat('?,', count($customizationIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT name, price_adjustment FROM customizations WHERE id IN ($placeholders)");
    $stmt->execute($customizationIds);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat Bakery - Freshly Baked Delights</title>
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
            background-color: #fff;
        }

        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background-color: var(--primary);
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-add-to-cart {
            width: 100%;
            margin-top: 15px;
            padding: 10px;
            font-size: 16px;
        }

        header {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }

        .logo span {
            color: var(--accent);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 25px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .cart-icon {
            position: relative;
            font-size: 24px;
            color: var(--primary);
            cursor: pointer;
        }

        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
        }

        .admin-link {
            background: var(--danger);
            padding: 8px 16px;
            border-radius: 4px;
            margin-left: 15px;
        }

        .hero {
            background: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="500" viewBox="0 0 1200 500"><rect width="1200" height="500" fill="%238B4513"/><circle cx="600" cy="250" r="200" fill="%23e0c99d" opacity="0.3"/></svg>') center/cover no-repeat;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
        }

        .hero-content h1 {
            font-size: 48px;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        .hero-content p {
            font-size: 24px;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }

        .section-title {
            text-align: center;
            margin-bottom: 40px;
            font-size: 32px;
            color: var(--primary);
            position: relative;
        }

        .section-title:after {
            content: '';
            display: block;
            width: 80px;
            height: 4px;
            background-color: var(--accent);
            margin: 10px auto;
            border-radius: 2px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }

        .product-card.special {
            border: 2px solid var(--accent);
            position: relative;
        }

        .product-badge {
            position: absolute;
            top: 15px;
            right: -30px;
            background: var(--accent);
            color: white;
            padding: 5px 30px;
            transform: rotate(45deg);
            font-weight: bold;
            font-size: 14px;
            z-index: 2;
        }

        .customizable-indicator {
            position: absolute;
            top: 15px;
            left: -30px;
            background: var(--success);
            color: white;
            padding: 5px 20px;
            transform: rotate(-45deg);
            font-weight: bold;
            font-size: 12px;
            z-index: 2;
        }

        .product-image {
            height: 200px;
            overflow: hidden;
            background: var(--secondary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 80%;
            height: auto;
            object-fit: cover;
        }

        .product-info {
            padding: 20px;
        }

        .product-info h3 {
            font-size: 22px;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .product-description {
            color: #666;
            margin-bottom: 15px;
            min-height: 60px;
        }

        .price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }

        .price-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }

        .original-price {
            text-decoration: line-through;
            color: #999;
        }

        .discounted-price {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .discount-badge {
            background-color: var(--danger);
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: bold;
        }

        .stock-status {
            font-size: 14px;
            font-weight: 600;
            margin: 10px 0;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
        }

        .stock-status.in-stock {
            background-color: #d4edda;
            color: #155724;
        }

        .stock-status.low-stock {
            background-color: #fff3cd;
            color: #856404;
        }

        .stock-status.out-of-stock {
            background-color: #f8d7da;
            color: #721c24;
        }

        .no-products {
            text-align: center;
            font-size: 18px;
            color: #666;
            grid-column: 1 / -1;
            padding: 40px;
        }

        /* Customization Modal */
        .customization-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .customization-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }

        .customization-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
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

        .customization-category {
            margin-top: 20px;
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
            margin-bottom: 10px;
            padding: 10px;
            background: var(--light);
            border-radius: 8px;
        }

        .customization-option input[type="checkbox"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
        }

        .customization-name {
            flex: 1;
            font-weight: 600;
        }

        .customization-price {
            color: var(--primary);
            font-weight: bold;
        }

        .customization-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }

        /* Cart Modal Styles */
        .cart-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .cart-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
        }

        .cart-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }

        .close-cart {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }

        .close-cart:hover {
            color: black;
        }

        .cart-items {
            margin-bottom: 20px;
        }

        .cart-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            background: var(--secondary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 24px;
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .cart-item-customizations {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }

        .cart-item-customization {
            display: inline-block;
            background: var(--light);
            padding: 3px 8px;
            border-radius: 12px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .cart-item-price {
            font-weight: bold;
            color: var(--primary);
        }

        .remove-item {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 14px;
            margin-left: 15px;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 20px;
            font-weight: bold;
            padding: 20px 0;
            border-top: 2px solid var(--secondary);
            margin-top: 20px;
        }

        .checkout-btn {
            background: var(--success);
            width: 100%;
            padding: 15px;
            font-size: 18px;
            margin-top: 20px;
        }

        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        footer {
            background-color: var(--dark);
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .footer-column h3 {
            font-size: 20px;
            margin-bottom: 20px;
            color: var(--accent);
        }

        .footer-column ul {
            list-style: none;
        }

        .footer-column ul li {
            margin-bottom: 10px;
        }

        .footer-column ul li a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-column ul li a:hover {
            color: white;
        }

        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #aaa;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .hero-content h1 {
                font-size: 36px;
            }
            
            .hero-content p {
                font-size: 18px;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .cart-item {
                flex-direction: column;
            }
            
            .cart-item-image {
                margin-bottom: 15px;
            }
            
            .customization-content {
                width: 95%;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">Golden <span>Treat</span></a>
                <div style="display: flex; align-items: center;">
                    <ul class="nav-links">
                        <li><a href="#products">All Products</a></li>
                    </ul>
                    <div class="cart-icon" id="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cartCount; ?></span>
                    </div>
                    <a href="admin.php" class="admin-link">Admin</a>
                </div>
            </nav>
        </div>
    </header>
    
    <?php if (isset($cartMessage)): ?>
    <div style="background: #d4edda; color: #155724; text-align: center; padding: 15px; margin: 20px 0;">
        <?php echo htmlspecialchars($cartMessage); ?>
    </div>
    <?php endif; ?>
    
    <!-- Cart Modal -->
    <div id="cart-modal" class="cart-modal">
        <div class="cart-modal-content">
            <div class="cart-modal-header">
                <h2>Your Cart (<span id="cart-count-display"><?php echo $cartCount; ?></span>)</h2>
                <span class="close-cart" id="close-cart">&times;</span>
            </div>
            
            <div class="cart-items" id="cart-items-container">
                <?php if (empty($cartItems)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart" style="font-size: 48px; margin-bottom: 20px; color: var(--secondary);"></i>
                        <h3>Your cart is empty</h3>
                        <p>Add some delicious treats to your cart!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cartItems as $item): 
                        $selectedCustomizations = json_decode($item['selected_customizations'], true) ?: [];
                        $customizationNames = getCustomizationNames($pdo, $selectedCustomizations);
                        $totalPrice = $item['total_price'];
                    ?>
                    <div class="cart-item" data-cart-id="<?php echo $item['id']; ?>">
                        <div class="cart-item-image">🎂</div>
                        <div class="cart-item-details">
                            <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <?php if (!empty($customizationNames)): ?>
                                <div class="cart-item-customizations">
                                    <?php foreach ($customizationNames as $cust): ?>
                                        <span class="cart-item-customization">
                                            <?php echo htmlspecialchars($cust['name']); ?> (+$<?php echo number_format($cust['price_adjustment'], 2); ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="cart-item-price">$<?php echo number_format($totalPrice / $item['quantity'], 2); ?> each</div>
                            <div>Quantity: <?php echo $item['quantity']; ?></div>
                        </div>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                            <input type="hidden" name="remove_from_cart" value="1">
                            <button type="submit" class="remove-item" onclick="return confirm('Remove this item from cart?')">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($cartItems)): ?>
                <div class="cart-total">
                    <span>Total:</span>
                    <span id="cart-total-display">$<?php echo number_format($cartTotal, 2); ?></span>
                </div>
                <button class="btn checkout-btn">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Customization Modal -->
    <div id="customization-modal" class="customization-modal">
        <div class="customization-content">
            <div class="customization-header">
                <h2>Customize Your Order</h2>
                <p id="customization-product-name" style="color: var(--primary); margin-top: 5px;"></p>
            </div>
            <form id="customization-form" method="POST">
                <input type="hidden" id="custom-product-id" name="product_id">
                <input type="hidden" id="custom-quantity" name="quantity" value="1">
                <input type="hidden" name="add_to_cart" value="1">
                
                <div id="customization-options">
                    <!-- Customization options will be loaded here by JavaScript -->
                </div>
                
                <div class="customization-actions">
                    <button type="button" id="cancel-customization" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Cart</button>
                </div>
            </form>
        </div>
    </div>
    
    <main>
        <section class="hero">
            <div class="hero-content">
                <h1>Freshly Baked Daily</h1>
                <p>Artisanal pastries, cakes, and breads made with love</p>
                <a href="#products" class="btn btn-primary">Shop Now</a>
            </div>
        </section>
        
        <section class="all-products" id="products">
            <div class="container">
                <h2 class="section-title"><i class="fas fa-cookie-bite"></i> Our Delicious Treats</h2>
                <div class="products-grid">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <div class="product-card <?php echo $product['is_daily_special'] ? 'special' : ''; ?>">
                                <?php if ($product['is_daily_special']): ?>
                                    <div class="product-badge">Daily Special</div>
                                <?php endif; ?>
                                
                                <?php 
                                $availableCustomizations = getAvailableCustomizations($pdo, $product['id']);
                                if (!empty($availableCustomizations)): ?>
                                    <div class="customizable-indicator">Customizable</div>
                                <?php endif; ?>
                                
                                <div class="product-image">
                                    <div style="font-size: 48px; color: var(--primary);">🎂</div>
                                </div>
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="product-description"><?php echo htmlspecialchars(substr($product['description'], 0, 100)) . '...'; ?></p>
                                    
                                    <?php if ($product['discount_percentage'] > 0): ?>
                                        <div class="price-container">
                                            <span class="original-price">$<?php echo number_format($product['price'], 2); ?></span>
                                            <span class="discounted-price">$<?php echo number_format($product['price'] * (1 - $product['discount_percentage']/100), 2); ?></span>
                                            <span class="discount-badge">-<?php echo $product['discount_percentage']; ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="price">$<?php echo number_format($product['price'], 2); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="stock-status <?php echo $product['stock_quantity'] > 10 ? 'in-stock' : ($product['stock_quantity'] > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                                        <?php if ($product['stock_quantity'] > 10): ?>
                                            In Stock
                                        <?php elseif ($product['stock_quantity'] > 0): ?>
                                            Only <?php echo $product['stock_quantity']; ?> left!
                                        <?php else: ?>
                                            Out of Stock
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <?php if (!empty($availableCustomizations)): ?>
                                            <button class="btn btn-add-to-cart customize-btn" 
                                                    data-product-id="<?php echo $product['id']; ?>"
                                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                <i class="fas fa-magic"></i> Customize & Add
                                            </button>
                                        <?php else: ?>
                                            <form method="POST" style="margin-top: 15px;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <input type="hidden" name="add_to_cart" value="1">
                                                <button type="submit" class="btn btn-add-to-cart">
                                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-products">No products available at the moment.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Golden Treat Bakery</h3>
                    <p>Freshly baked goods made with love and the finest ingredients.</p>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#products">All Products</a></li>
                        <li><a href="admin.php">Admin Panel</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Us</h3>
                    <ul>
                        <li>123 Bakery Street</li>
                        <li>Bakerville, BV 12345</li>
                        <li>(555) 123-4567</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 Golden Treat Bakery. All rights reserved.
            </div>
        </div>
    </footer>
    
    <script>
        // Cart functionality
        const cartIcon = document.getElementById('cart-icon');
        const cartModal = document.getElementById('cart-modal');
        const closeCart = document.getElementById('close-cart');
        const customizationModal = document.getElementById('customization-modal');
        const cancelCustomization = document.getElementById('cancel-customization');
        const customizationForm = document.getElementById('customization-form');
        const customizationProductName = document.getElementById('customization-product-name');
        const customProductId = document.getElementById('custom-product-id');
        const customizationOptions = document.getElementById('customization-options');
        
        // Open cart modal
        cartIcon.addEventListener('click', () => {
            cartModal.style.display = 'block';
        });
        
        // Close cart modal
        closeCart.addEventListener('click', () => {
            cartModal.style.display = 'none';
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === cartModal) {
                cartModal.style.display = 'none';
            }
            if (e.target === customizationModal) {
                customizationModal.style.display = 'none';
            }
        });
        
        // Customize buttons
        document.querySelectorAll('.customize-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                
                // Populate modal
                customizationProductName.textContent = productName;
                customProductId.value = productId;
                
                // Load customization options via AJAX
                loadCustomizationOptions(productId);
            });
        });
        
        function loadCustomizationOptions(productId) {
            // In a real app, this would be an AJAX call
            // For this demo, we'll use the data that's already available in the page
            const productCards = document.querySelectorAll('.product-card');
            let customizations = [];
            
            productCards.forEach(card => {
                if (card.querySelector('.customize-btn') && 
                    card.querySelector('.customize-btn').dataset.productId == productId) {
                    // Extract customizations from the page structure
                    // This is a workaround since we don't have AJAX in this single file
                    // In a real app, you'd make an AJAX request to get customizations
                }
            });
            
            // For demo purposes, we'll use hardcoded data based on product ID
            if (productId == 1) { // Chocolate Croissant
                customizations = [
                    {id: 1, name: 'Extra Chocolate', price: 0.75, category: 'Toppings'},
                    {id: 2, name: 'Almond Topping', price: 0.50, category: 'Toppings'},
                    {id: 6, name: 'Gluten-Free', price: 1.00, category: 'Dietary'},
                    {id: 7, name: 'Vegan', price: 1.50, category: 'Dietary'}
                ];
            } else if (productId == 2) { // Blueberry Muffin
                customizations = [
                    {id: 3, name: 'Walnut Topping', price: 0.75, category: 'Toppings'},
                    {id: 4, name: 'Sprinkles', price: 0.25, category: 'Toppings'},
                    {id: 6, name: 'Gluten-Free', price: 1.00, category: 'Dietary'},
                    {id: 8, name: 'Extra Large', price: 2.00, category: 'Size'}
                ];
            } else if (productId == 3) { // Cinnamon Roll
                customizations = [
                    {id: 1, name: 'Extra Chocolate', price: 0.75, category: 'Toppings'},
                    {id: 3, name: 'Walnut Topping', price: 0.75, category: 'Toppings'},
                    {id: 5, name: 'Chocolate Drizzle', price: 0.50, category: 'Toppings'},
                    {id: 6, name: 'Gluten-Free', price: 1.00, category: 'Dietary'}
                ];
            } else if (productId == 4) { // Vanilla Cupcake
                customizations = [
                    {id: 4, name: 'Sprinkles', price: 0.25, category: 'Toppings'},
                    {id: 5, name: 'Chocolate Drizzle', price: 0.50, category: 'Toppings'},
                    {id: 6, name: 'Gluten-Free', price: 1.00, category: 'Dietary'},
                    {id: 9, name: 'Birthday Message', price: 1.00, category: 'Special'}
                ];
            } else if (productId == 5) { // Strawberry Tart
                customizations = [
                    {id: 4, name: 'Sprinkles', price: 0.25, category: 'Toppings'},
                    {id: 5, name: 'Chocolate Drizzle', price: 0.50, category: 'Toppings'},
                    {id: 6, name: 'Gluten-Free', price: 1.00, category: 'Dietary'},
                    {id: 10, name: 'Wedding Decoration', price: 3.00, category: 'Special'}
                ];
            }
            
            displayCustomizationOptions(customizations);
            customizationModal.style.display = 'block';
        }
        
        function displayCustomizationOptions(customizations) {
            if (customizations.length === 0) {
                customizationOptions.innerHTML = '<p>No customizations available for this product.</p>';
                return;
            }
            
            // Group by category
            const categories = {};
            customizations.forEach(cust => {
                if (!categories[cust.category]) {
                    categories[cust.category] = [];
                }
                categories[cust.category].push(cust);
            });
            
            let html = '';
            Object.keys(categories).forEach(category => {
                html += `<div class="customization-category">
                            <h4>${category}</h4>`;
                categories[category].forEach(cust => {
                    html += `
                        <div class="customization-option">
                            <input type="checkbox" name="customizations[]" value="${cust.id}" id="cust-${cust.id}">
                            <label for="cust-${cust.id}" class="customization-name">${cust.name}</label>
                            <span class="customization-price">+$${cust.price.toFixed(2)}</span>
                        </div>
                    `;
                });
                html += `</div>`;
            });
            
            customizationOptions.innerHTML = html;
        }
        
        cancelCustomization.addEventListener('click', () => {
            customizationModal.style.display = 'none';
        });
    </script>
</body>
</html>