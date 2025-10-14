
<?php
session_start();
ob_start();
// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    error_log("Connection failed: " . $e->getMessage());
    die("Connection failed: Unable to connect to the database.");
}

// Cart functionality
$cartMessage = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $cartMessage = "❌ Invalid request.";
        $messageType = 'error';
    } else {
        if (isset($_POST['add_to_cart'])) {
            $pdo->beginTransaction();
            try {
                $productId = intval($_POST['product_id']);
                $quantity = intval($_POST['quantity']);
                $selectedCustomizations = isset($_POST['customizations']) && is_array($_POST['customizations']) ? array_map('intval', $_POST['customizations']) : [];

                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND visibility = 1 FOR UPDATE");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();

                if ($product) {
                    if ($product['stock_quantity'] >= $quantity) {
                        $sessionId = session_id();
                        $basePrice = $product['price'] * (1 - $product['discount_percentage']/100);
                        $itemCustomizationCost = 0;

                        // Validate and calculate customization cost
                        if (!empty($selectedCustomizations)) {
                            $placeholders = str_repeat('?,', count($selectedCustomizations) - 1) . '?';
                            $stmt = $pdo->prepare("
                                SELECT c.price_adjustment 
                                FROM customizations c 
                                INNER JOIN product_customizations pc ON c.id = pc.customization_id
                                WHERE c.id IN ($placeholders) AND c.is_active = 1 AND pc.product_id = ?
                            ");
                            $stmt->execute(array_merge($selectedCustomizations, [$productId]));
                            $validCustomizations = $stmt->fetchAll();
                            foreach ($validCustomizations as $cust) {
                                $itemCustomizationCost += (float)$cust['price_adjustment'];
                            }
                        }

                        $unitPrice = $basePrice + $itemCustomizationCost;
                        $totalPrice = $unitPrice * $quantity;

                        sort($selectedCustomizations);
                        $customizationKey = json_encode($selectedCustomizations);

                        $stmt = $pdo->prepare("SELECT id, quantity, total_price FROM cart WHERE session_id = ? AND product_id = ? AND selected_customizations = ? FOR UPDATE");
                        $stmt->execute([$sessionId, $productId, $customizationKey]);
                        $existingItem = $stmt->fetch();

                        if ($existingItem) {
                            $newQuantity = $existingItem['quantity'] + $quantity;
                            $newTotalPrice = $unitPrice * $newQuantity;
                            $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, total_price = ? WHERE id = ?");
                            $stmt->execute([$newQuantity, $newTotalPrice, $existingItem['id']]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO cart (session_id, product_id, quantity, selected_customizations, total_price) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$sessionId, $productId, $quantity, $customizationKey, $totalPrice]);
                        }

                        $newStock = $product['stock_quantity'] - $quantity;
                        $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                        $stmt->execute([$newStock, $productId]);

                        $pdo->commit();
                        $cartMessage = "✅ " . htmlspecialchars($product['name']) . " added to cart successfully!";
                        $messageType = 'success';
                    } else {
                        $pdo->rollBack();
                        $cartMessage = "❌ Insufficient stock for " . htmlspecialchars($product['name']);
                        $messageType = 'error';
                    }
                } else {
                    $pdo->rollBack();
                    $cartMessage = "❌ Product not available.";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Add to cart error: " . $e->getMessage());
                $cartMessage = "❌ Error adding to cart.";
                $messageType = 'error';
            }
        }

        if (isset($_POST['update_cart_item'])) {
            $pdo->beginTransaction();
            try {
                $cartId = intval($_POST['cart_id']);
                $newQuantity = intval($_POST['quantity']);
                $sessionId = session_id();

                if ($newQuantity > 0) {
                    $stmt = $pdo->prepare("
                        SELECT c.quantity, c.product_id, c.selected_customizations, p.stock_quantity, p.price, p.discount_percentage, p.name 
                        FROM cart c JOIN products p ON c.product_id = p.id 
                        WHERE c.id = ? AND c.session_id = ? FOR UPDATE
                    ");
                    $stmt->execute([$cartId, $sessionId]);
                    $item = $stmt->fetch();

                    if ($item) {
                        $quantityDiff = $newQuantity - $item['quantity'];
                        if ($item['stock_quantity'] + $item['quantity'] >= $newQuantity) {
                            $basePrice = $item['price'] * (1 - $item['discount_percentage']/100);
                            $selectedCustomizations = json_decode($item['selected_customizations'], true) ?: [];
                            $itemCustomizationCost = 0;

                            if (!empty($selectedCustomizations)) {
                                $placeholders = str_repeat('?,', count($selectedCustomizations) - 1) . '?';
                                $stmt_cust = $pdo->prepare("SELECT price_adjustment FROM customizations WHERE id IN ($placeholders) AND is_active = 1");
                                $stmt_cust->execute($selectedCustomizations);
                                $customizationCosts = $stmt_cust->fetchAll();
                                foreach ($customizationCosts as $cust) {
                                    $itemCustomizationCost += (float)$cust['price_adjustment'];
                                }
                            }

                            $unitPrice = $basePrice + $itemCustomizationCost;
                            $newTotalPrice = $unitPrice * $newQuantity;

                            $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, total_price = ? WHERE id = ? AND session_id = ?");
                            $stmt->execute([$newQuantity, $newTotalPrice, $cartId, $sessionId]);

                            $newStock = $item['stock_quantity'] - $quantityDiff;
                            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                            $stmt->execute([$newStock, $item['product_id']]);

                            $pdo->commit();
                            $cartMessage = "✅ " . htmlspecialchars($item['name']) . " quantity updated!";
                            $messageType = 'success';
                        } else {
                            $pdo->rollBack();
                            $cartMessage = "❌ Only " . ($item['stock_quantity'] + $item['quantity']) . " available for " . htmlspecialchars($item['name']);
                            $messageType = 'error';
                        }
                    } else {
                        $pdo->rollBack();
                        $cartMessage = "❌ Cart item not found.";
                        $messageType = 'error';
                    }
                } else {
                    $pdo->rollBack();
                    $cartMessage = "❌ Invalid quantity.";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Update cart error: " . $e->getMessage());
                $cartMessage = "❌ Error updating cart.";
                $messageType = 'error';
            }
        }

        if (isset($_POST['remove_from_cart'])) {
            $pdo->beginTransaction();
            try {
                $cartId = intval($_POST['cart_id']);
                $sessionId = session_id();

                $stmt = $pdo->prepare("SELECT p.id, p.stock_quantity, c.quantity, p.name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.session_id = ? FOR UPDATE");
                $stmt->execute([$cartId, $sessionId]);
                $item = $stmt->fetch();

                if ($item) {
                    $newStock = $item['stock_quantity'] + $item['quantity'];
                    $stmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
                    $stmt->execute([$newStock, $item['id']]);

                    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND session_id = ?");
                    $stmt->execute([$cartId, $sessionId]);

                    $pdo->commit();
                    $cartMessage = "🗑️ " . htmlspecialchars($item['name']) . " removed from cart.";
                    $messageType = 'success';
                } else {
                    $pdo->rollBack();
                    $cartMessage = "❌ Cart item not found.";
                    $messageType = 'error';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Remove from cart error: " . $e->getMessage());
                $cartMessage = "❌ Error removing from cart.";
                $messageType = 'error';
            }
        }

        if (isset($_POST['checkout'])) {
            $pdo->beginTransaction();
            try {
                $sessionId = session_id();
                $customerName = trim($_POST['customer_name']);
                $customerEmail = filter_var(trim($_POST['customer_email']), FILTER_SANITIZE_EMAIL);
                $customerPhone = trim($_POST['customer_phone']);

                if (empty($customerName) || empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    $pdo->rollBack();
                    $cartMessage = "❌ Please provide valid name and email.";
                    $messageType = 'error';
                } else {
                    $stmt = $pdo->prepare("SELECT c.*, p.name as product_name FROM cart c JOIN products p ON c.product_id = p.id WHERE c.session_id = ? FOR UPDATE");
                    $stmt->execute([$sessionId]);
                    $cartItems = $stmt->fetchAll();

                    if (!empty($cartItems)) {
                        $totalAmount = 0;
                        foreach ($cartItems as $item) {
                            $totalAmount += $item['total_price'];
                        }

                        $orderNumber = 'GT' . date('Ymd') . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);

                        $userId = null;
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$customerEmail]);
                        $user = $stmt->fetch();
                        if ($user) {
                            $userId = (int)$user['id'];
                        }

                        $stmt = $pdo->prepare("INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, total_amount, user_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$orderNumber, $customerName, $customerEmail, $customerPhone, $totalAmount, $userId]);
                        $orderId = $pdo->lastInsertId();

                        foreach ($cartItems as $item) {
                            $unitPrice = $item['total_price'] / $item['quantity'];
                            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_name, quantity, unit_price, customizations) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$orderId, $item['product_name'], $item['quantity'], $unitPrice, $item['selected_customizations']]);
                        }

                        $stmt = $pdo->prepare("DELETE FROM cart WHERE session_id = ?");
                        $stmt->execute([$sessionId]);

                        $pdo->commit();
                        $cartMessage = "✅ Order #$orderNumber placed successfully! Total: Rs. " . number_format($totalAmount, 2);
                        $messageType = 'success';
                    } else {
                        $pdo->rollBack();
                        $cartMessage = "❌ Your cart is empty.";
                        $messageType = 'error';
                    }
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Checkout error: " . $e->getMessage());
                $cartMessage = "❌ Error placing order.";
                $messageType = 'error';
            }
        }
    }
}

$sessionId = session_id();
$stmt = $pdo->prepare("SELECT c.*, p.name as product_name, p.price as base_price FROM cart c JOIN products p ON c.product_id = p.id WHERE c.session_id = ? ORDER BY c.added_at DESC");
$stmt->execute([$sessionId]);
$cartItems = $stmt->fetchAll();

$cartTotal = 0;
$cartCount = 0;
foreach ($cartItems as $item) {
    $cartTotal += $item['total_price'];
    $cartCount += $item['quantity'];
}

$stmt = $pdo->prepare("SELECT * FROM products WHERE visibility = 1 ORDER BY is_daily_special DESC, category, name");
$stmt->execute();
$products = $stmt->fetchAll();

$productsByCategory = [];
foreach ($products as $product) {
    $category = $product['category'];
    if (!isset($productsByCategory[$category])) {
        $productsByCategory[$category] = [];
    }
    $productsByCategory[$category][] = $product;
}

function getAvailableCustomizations($pdo, $productId) {
    $stmt = $pdo->prepare("
        SELECT c.* FROM customizations c 
        INNER JOIN product_customizations pc ON c.id = pc.customization_id 
        WHERE pc.product_id = ? AND c.is_active = 1 
        ORDER BY c.category, c.name
    ");
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function getCustomizationNames($pdo, $customizationIds) {
    if (empty($customizationIds)) return [];
    $customizationIds = array_map('intval', $customizationIds);
    $placeholders = str_repeat('?,', count($customizationIds) - 1) . '?';
    $stmt = $pdo->prepare("SELECT name, price_adjustment FROM customizations WHERE id IN ($placeholders)");
    $stmt->execute($customizationIds);
    return $stmt->fetchAll();
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat Bakery - Freshly Baked Delights</title>
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
        body {
            cursor: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="%23D4AF37" opacity="0.5"/></svg>'), auto;
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text);
            background: linear-gradient(135deg, #FFE8B7 0%, #fff8e1 100%);
            min-height: 100vh;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideIn { from { transform: translateX(-100%); } to { transform: translateX(0); } }
        @keyframes bounce { 0%, 20%, 53%, 80%, 100% { transform: translate3d(0,0,0); } 40%, 43% { transform: translate3d(0,-8px,0); } 70% { transform: translate3d(0,-4px,0); } 90% { transform: translate3d(0,-2px,0); } }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
        .animated { animation-duration: 0.6s; animation-fill-mode: both; }
        .fadeIn { animation-name: fadeIn; }
        .slideIn { animation-name: slideIn; }
        .bounce { animation-name: bounce; }
        .pulse { animation-name: pulse; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: var(--shadow);
        }
        .btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
        .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-light)); }
        .btn-success { background: linear-gradient(135deg, var(--success), #34ce57); }
        .btn-warning { background: linear-gradient(135deg, var(--warning), #ffd760); }
        .btn-danger { background: linear-gradient(135deg, var(--danger), #e4606d); }
        header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: fixed;
            top: 10px;
            left: 20px;
            z-index: 1000;
            transition: all 0.3s ease;
            border-radius: 5cm;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
        }
        .logo {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            padding-right: 30px;
            gap: 10px;
        }
        .logo span { color: var(--accent); }
        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }
        .nav-links a:hover { color: var(--primary); }
        .nav-links a:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 0;
            background: var(--primary);
            transition: width 0.3s ease;
        }
        .nav-links a:hover:after { width: 100%; }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .cart-icon {
            position: relative;
            font-size: 24px;
            color: var(--primary);
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 10px;
            border-radius: 50%;
        }
        .cart-icon:hover {
            background: var(--light);
            transform: scale(1.1);
        }
        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, var(--danger), #e4606d);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        .hero {
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.8), rgba(160, 82, 45, 0.8)), url('https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=1350&q=80');
            background-size: cover;
            background-position: center;
            height: 80vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
        }
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 800px;
            padding: 0 20px;
        }
        .hero-content h1 {
            font-size: 4rem;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            animation: fadeIn 1s ease;
        }
        .hero-content p {
            font-size: 1.5rem;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            animation: fadeIn 1s ease 0.2s both;
        }
        .section-title {
            text-align: center;
            margin: 60px 0 40px;
            font-size: 2.5rem;
            color: var(--primary);
            position: relative;
        }
        .section-title:after {
            content: '';
            display: block;
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            margin: 15px auto;
            border-radius: 2px;
        }
        .category-section {
            margin: 60px 0;
        }
        .category-title {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 30px;
            padding-left: 20px;
            border-left: 4px solid var(--accent);
        }
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        .product-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
        }
        .product-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }
        .product-card.special {
            border: 3px solid var(--accent);
            animation: pulse 2s infinite;
        }
        .product-badge {
            position: absolute;
            top: 15px;
            right: -30px;
            background: linear-gradient(135deg, var(--accent), #e6c158);
            color: white;
            padding: 8px 40px;
            transform: rotate(45deg);
            font-weight: bold;
            font-size: 14px;
            z-index: 2;
            box-shadow: var(--shadow);
        }
        .customizable-indicator {
            position: absolute;
            top: 15px;
            left: -30px;
            background: linear-gradient(135deg, var(--success), #34ce57);
            color: white;
            padding: 8px 30px;
            transform: rotate(-45deg);
            font-weight: bold;
            font-size: 12px;
            z-index: 2;
            box-shadow: var(--shadow);
        }
        .product-image {
            height: 200px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--secondary), #e8d4a6);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .product-image:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.1);
        }
        .product-image i {
            font-size: 4rem;
            color: var(--primary);
            z-index: 1;
        }
        .product-info {
            padding: 25px;
        }
        .product-info h3 {
            font-size: 1.4rem;
            margin-bottom: 10px;
            color: var(--dark);
        }
        .product-description {
            color: #666;
            margin-bottom: 15px;
            min-height: 60px;
        }
        .price-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
            flex-wrap: wrap;
        }
        .original-price {
            text-decoration: line-through;
            color: #999;
            font-size: 1rem;
        }
        .discounted-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .discount-badge {
            background: linear-gradient(135deg, var(--danger), #e4606d);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        .stock-status {
            font-size: 0.9rem;
            font-weight: 600;
            margin: 10px 0;
            padding: 6px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        .stock-status.in-stock { background: #d4edda; color: #155724; }
        .stock-status.low-stock { background: #fff3cd; color: #856404; }
        .stock-status.out-of-stock { background: #f8d7da; color: #721c24; }
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }
        .quantity-btn {
            width: 35px;
            height: 35px;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .quantity-btn:hover {
            background: var(--primary);
            color: white;
        }
        .quantity-input {
            width: 60px;
            text-align: center;
            padding: 8px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn-add-to-cart {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            font-size: 1rem;
            justify-content: center;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            position: relative;
            max-height: 80vh;
            overflow-y: auto;
            animation: fadeIn 0.3s ease;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s;
        }
        .close:hover { color: black; }
        .form-group { margin-bottom: 20px; }
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
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .customization-category {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        .customization-category h4 {
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        .customization-option {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 12px;
            background: var(--light);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .customization-option:hover {
            background: #f0e6d2;
            transform: translateX(5px);
        }
        .customization-option input[type="checkbox"] {
            margin-right: 15px;
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }
        .customization-name {
            flex: 1;
            font-weight: 600;
        }
        .customization-price {
            color: var(--primary);
            font-weight: bold;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }
        .cart-item {
            display: flex;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        .cart-item-image {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--secondary), #e8d4a6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.5rem;
        }
        .cart-item-details { flex: 1; }
        .cart-item-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--dark);
        }
        .cart-item-customizations {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        .cart-item-customization {
            display: inline-block;
            background: var(--light);
            padding: 4px 10px;
            border-radius: 15px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 0.8rem;
        }
        .cart-item-price {
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 8px;
        }
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.3rem;
            font-weight: bold;
            padding: 20px 0;
            border-top: 2px solid var(--secondary);
            margin-top: 20px;
        }
        .empty-cart {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .empty-cart i {
            font-size: 4rem;
            color: var(--secondary);
            margin-bottom: 20px;
        }
        footer {
            background: linear-gradient(135deg, var(--dark), #2c2c2c);
            color: white;
            padding: 60px 0 20px;
            margin-top: 80px;
        }
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        .footer-column h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--accent);
        }
        .footer-column ul { list-style: none; }
        .footer-column ul li { margin-bottom: 10px; }
        .footer-column ul li a {
            color: #ccc;
            text-decoration: none;
            transition: color 0.3s;
        }
        .footer-column ul li a:hover { color: white; }
        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .social-links a:hover {
            background: var(--accent);
            transform: translateY(-3px);
        }
        .copyright {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #444;
            color: #aaa;
            font-size: 0.9rem;
        }
        .message {
            position: fixed;
            top: 100px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            z-index: 3000;
            animation: slideIn 0.3s ease, fadeIn 0.3s ease;
            box-shadow: var(--shadow-lg);
        }
        .message.success { background: linear-gradient(135deg, var(--success), #34ce57); }
        .message.error { background: linear-gradient(135deg, var(--danger), #e4606d); }
        .message.warning { background: linear-gradient(135deg, var(--warning), #ffd760); }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 15px; }
            .nav-links { gap: 15px; }
            .hero-content h1 { font-size: 2.5rem; }
            .hero-content p { font-size: 1.2rem; }
            .products-grid { grid-template-columns: 1fr; }
            .cart-item { flex-direction: column; text-align: center; }
            .cart-item-image { margin-bottom: 15px; }
            .modal-content { width: 95%; padding: 20px; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }
        .cartbtn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            background: #241300ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            cursor: pointer;
            z-index: 1000;
            transition: all 0.3s ease;
            animation: pulse 2s infinite;
        }
        .cartbtn:hover {
            transform: scale(1.1);
        }
        .customization-list {
            font-size: 0.9rem;
            color: #666;
            margin-top: 10px;
            padding-left: 20px;
        }
        .customization-list li {
            list-style-type: disc;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <!-- Floating cart -->
    <div class="cartbtn">
        <div class="header-actions">
            <div class="cart-icon" id="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count"><?php echo $cartCount; ?></span>
            </div>
        </div>
    </div>

    <!-- Message Display -->
    <?php if (!empty($cartMessage)): ?>
        <div class="message <?php echo $messageType; ?> animated">
            <?php echo $cartMessage; ?>
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.message')?.remove();
            }, 5000);
        </script>
    <?php endif; ?>

    <header>
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo animated fadeIn">
                    <i class="fas fa-cookie-bite"></i>
                    Golden <span>Treat</span>
                </a>
                <ul class="nav-links"></ul>
            </nav>
        </div>
    </header>

    <!-- Cart Modal -->
    <div id="cart-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Your Shopping Cart (<span id="cart-count-display"><?php echo $cartCount; ?></span>)</h2>
                <span class="close" id="close-cart">&times;</span>
            </div>
            <div class="cart-items" id="cart-items-container">
                <?php if (empty($cartItems)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Add some delicious treats to your cart!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($cartItems as $item): 
                        $selectedCustomizations = json_decode($item['selected_customizations'], true) ?: [];
                        $customizationNames = getCustomizationNames($pdo, $selectedCustomizations);
                        $unitPrice = $item['total_price'] / $item['quantity'];
                    ?>
                    <div class="cart-item" data-cart-id="<?php echo $item['id']; ?>">
                        <div class="cart-item-image">
                            <i class="fas fa-bread-slice"></i>
                        </div>
                        <div class="cart-item-details">
                            <div class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <?php if (!empty($customizationNames)): ?>
                                <div class="cart-item-customizations">
                                    <?php foreach ($customizationNames as $cust): ?>
                                        <span class="cart-item-customization">
                                            <?php echo htmlspecialchars($cust['name']); ?> (+Rs. <?php echo number_format($cust['price_adjustment'], 2); ?>)
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div class="cart-item-price">Rs. <?php echo number_format($unitPrice, 2); ?> each</div>
                            <div class="cart-item-actions">
                                <form method="POST" class="quantity-form" style="display: flex; align-items: center; gap: 10px;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="update_cart_item" value="1">
                                    <button type="button" class="quantity-btn minus">-</button>
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="quantity-input">
                                    <button type="button" class="quantity-btn plus">+</button>
                                    <button type="submit" class="btn btn-warning" style="padding: 8px 15px;">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="remove_from_cart" value="1">
                                    <button type="submit" class="btn btn-danger" style="padding: 8px 15px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($cartItems)): ?>
                <div class="cart-total">
                    <span>Total Amount:</span>
                    <span id="cart-total-display">Rs. <?php echo number_format($cartTotal, 2); ?></span>
                </div>
                <button class="btn btn-success" id="checkout-btn" style="width: 100%; padding: 15px; font-size: 1.1rem;">
                    <i class="fas fa-credit-card"></i> Proceed to Checkout
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkout-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Checkout</h2>
                <span class="close" id="close-checkout">&times;</span>
            </div>
            <form method="POST" id="checkout-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="checkout" value="1">
                <div class="form-group">
                    <label for="customer_name">Full Name *</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>
                <div class="form-group">
                    <label for="customer_email">Email Address *</label>
                    <input type="email" id="customer_email" name="customer_email" required>
                </div>
                <div class="form-group">
                    <label for="customer_phone">Phone Number</label>
                    <input type="tel" id="customer_phone" name="customer_phone">
                </div>
                <div class="form-group">
                    <label>Order Summary</label>
                    <div id="checkout-summary"></div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-checkout">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Place Order
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customization Modal -->
    <div id="customization-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Customize Your Order</h2>
                <span class="close" id="close-customization">&times;</span>
            </div>
            <form id="customization-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" id="custom-product-id" name="product_id">
                <input type="hidden" id="custom-quantity" name="quantity" value="1">
                <input type="hidden" name="add_to_cart" value="1">
                <div id="customization-product-info" style="margin-bottom: 20px; padding: 15px; background: var(--light); border-radius: 10px;">
                    <h3 id="customization-product-name"></h3>
                    <div id="customization-base-price"></div>
                </div>
                <div id="customization-options"></div>
                <div id="customization-total" style="margin: 20px 0; padding: 15px; background: var(--light); border-radius: 10px; font-weight: bold; text-align: center;">
                    Total: Rs. <span id="custom-total-price">0.00</span>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" id="cancel-customization">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                </div>
            </form>
        </div>
    </div>

    <main>
        <?php include 'home.html'; ?>
        <section id="products" class="all-products">
            <div class="container">
                <h2 class="section-title animated fadeIn">
                    <i class="fas fa-star"></i> Our Delicious Treats
                </h2>
                <?php if (empty($productsByCategory)): ?>
                    <div class="no-products" style="text-align: center; padding: 60px; color: #666;">
                        <i class="fas fa-cookie-bite" style="font-size: 4rem; margin-bottom: 20px; color: var(--secondary);"></i>
                        <h3>No products available at the moment</h3>
                        <p>Please check back later for our fresh baked goods!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($productsByCategory as $category => $categoryProducts): ?>
                        <div class="category-section animated fadeIn">
                            <h3 class="category-title">
                                <i class="fas fa-<?php 
                                    switch($category) {
                                        case 'Pastries': echo 'croissant'; break;
                                        case 'Cakes': echo 'birthday-cake'; break;
                                        case 'Cupcakes': echo 'cupcake'; break;
                                        case 'Breads': echo 'bread-slice'; break;
                                        case 'Tarts': echo 'pie-chart'; break;
                                        case 'Muffins': echo 'muffin'; break;
                                        default: echo 'cookie';
                                    }
                                ?>"></i>
                                <?php echo htmlspecialchars($category); ?>
                            </h3>
                            <div class="products-grid">
                                <?php foreach ($categoryProducts as $product): 
                                    $availableCustomizations = getAvailableCustomizations($pdo, $product['id']);
                                    $finalPrice = $product['price'] * (1 - $product['discount_percentage']/100);
                                ?>
                                    <div class="product-card animated fadeIn <?php echo $product['is_daily_special'] ? 'special' : ''; ?>" data-customizations='<?php echo json_encode($availableCustomizations); ?>'>
                                        <?php if ($product['is_daily_special']): ?>
                                            <div class="product-badge">Daily Special</div>
                                        <?php endif; ?>
                                        <?php if (!empty($availableCustomizations)): ?>
                                            <div class="customizable-indicator">Customizable</div>
                                        <?php endif; ?>
                                        <div class="product-image">
                                            <i class="fas fa-<?php 
                                                switch($product['category']) {
                                                    case 'Pastries': echo 'croissant'; break;
                                                    case 'Cakes': echo 'birthday-cake'; break;
                                                    case 'Cupcakes': echo 'cupcake'; break;
                                                    case 'Breads': echo 'bread-slice'; break;
                                                    case 'Tarts': echo 'pie-chart'; break;
                                                    case 'Muffins': echo 'muffin'; break;
                                                    default: echo 'cookie';
                                                }
                                            ?>"></i>
                                        </div>
                                        <div class="product-info">
                                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                            <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                            <?php if (!empty($availableCustomizations)): ?>
                                                <div class="customization-list">
                                                    <strong>Available Customizations:</strong>
                                                    <ul>
                                                        <?php foreach ($availableCustomizations as $cust): ?>
                                                            <li><?php echo htmlspecialchars($cust['name']); ?> (+Rs. <?php echo number_format($cust['price_adjustment'], 2); ?>)</li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($product['discount_percentage'] > 0): ?>
                                                <div class="price-container">
                                                    <span class="original-price">Rs. <?php echo number_format($product['price'], 2); ?></span>
                                                    <span class="discounted-price">Rs. <?php echo number_format($finalPrice, 2); ?></span>
                                                    <span class="discount-badge">-<?php echo $product['discount_percentage']; ?>%</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="price-container">
                                                    <span class="discounted-price">Rs. <?php echo number_format($finalPrice, 2); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="stock-status <?php echo $product['stock_quantity'] > 10 ? 'in-stock' : ($product['stock_quantity'] > 0 ? 'low-stock' : 'out-of-stock'); ?>">
                                                <?php if ($product['stock_quantity'] > 10): ?>
                                                    <i class="fas fa-check"></i> In Stock
                                                <?php elseif ($product['stock_quantity'] > 0): ?>
                                                    <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock_quantity']; ?> left!
                                                <?php else: ?>
                                                    <i class="fas fa-times"></i> Out of Stock
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <div class="quantity-selector">
                                                    <button type="button" class="quantity-btn minus">-</button>
                                                    <input type="number" class="quantity-input" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                                                    <button type="button" class="quantity-btn plus">+</button>
                                                </div>
                                                <?php if (!empty($availableCustomizations)): ?>
                                                    <button type="button" class="btn btn-primary btn-add-to-cart customize-btn" 
                                                            data-product-id="<?php echo $product['id']; ?>"
                                                            data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                                                            data-base-price="<?php echo $finalPrice; ?>">
                                                        <i class="fas fa-magic"></i> Customize & Add to Cart
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" class="add-to-cart-form">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="quantity" value="1">
                                                        <input type="hidden" name="customizations[]" value="">
                                                        <input type="hidden" name="add_to_cart" value="1">
                                                        <button type="submit" class="btn btn-primary btn-add-to-cart">
                                                            <i class="fas fa-shopping-cart"></i> Add to Cart
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-add-to-cart" disabled>
                                                    <i class="fas fa-times"></i> Out of Stock
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <section id="about" class="about" style="padding: 80px 0; background: white;">
            <div class="container">
                <h2 class="section-title">About Golden Treat Bakery</h2>
                <div style="text-align: center; max-width: 800px; margin: 0 auto;">
                    <p style="font-size: 1.2rem; line-height: 1.8; color: #666;">
                        Since 2010, Golden Treat Bakery has been serving the community with freshly baked goods made from 
                        the finest ingredients. Our master bakers combine traditional techniques with innovative recipes 
                        to create unforgettable treats that bring joy to every occasion.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <footer id="contact">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Golden Treat Bakery</h3>
                    <p>Freshly baked goods made with love and the finest ingredients.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="#products">Our Products</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
                        <li><a href="admin.php">Admin Panel</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> No.12, Kuliyapitiya, Kurunegala</li>
                        <li><i class="fas fa-phone"></i> (+94) xxx xx xxx</li>
                        <li><i class="fas fa-envelope"></i> info@goldentreat.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Sat: 6AM-8PM, Sun: 7AM-6PM</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                &copy; 2024 Golden Treat Bakery. All rights reserved. | Made By 404 Error
            </div>
        </div>
    </footer>

    <script>
        const cartIcon = document.getElementById('cart-icon');
        const cartModal = document.getElementById('cart-modal');
        const closeCart = document.getElementById('close-cart');
        const customizationModal = document.getElementById('customization-modal');
        const closeCustomization = document.getElementById('close-customization');
        const checkoutModal = document.getElementById('checkout-modal');
        const closeCheckout = document.getElementById('close-checkout');
        const cancelCheckout = document.getElementById('cancel-checkout');
        const checkoutBtn = document.getElementById('checkout-btn');

        cartIcon.addEventListener('click', () => {
            cartModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });

        function closeModal(modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        closeCart.addEventListener('click', () => closeModal(cartModal));
        closeCustomization.addEventListener('click', () => closeModal(customizationModal));
        closeCheckout.addEventListener('click', () => closeModal(checkoutModal));
        cancelCheckout.addEventListener('click', () => closeModal(checkoutModal));

        window.addEventListener('click', (e) => {
            if (e.target === cartModal) closeModal(cartModal);
            if (e.target === customizationModal) closeModal(customizationModal);
            if (e.target === checkoutModal) closeModal(checkoutModal);
        });

        // Sync quantity in product grid with hidden form input
        document.querySelectorAll('.quantity-selector').forEach(selector => {
            const input = selector.querySelector('.quantity-input');
            const form = selector.closest('.product-card').querySelector('.add-to-cart-form input[name="quantity"]');
            selector.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    let value = parseInt(input.value);
                    const max = parseInt(input.max) || Infinity;
                    if (btn.classList.contains('plus')) {
                        value = Math.min(value + 1, max);
                    } else if (btn.classList.contains('minus')) {
                        value = Math.max(value - 1, 1);
                    }
                    input.value = value;
                    if (form) form.value = value;
                });
            });
            input.addEventListener('change', () => {
                let value = parseInt(input.value);
                const max = parseInt(input.max) || Infinity;
                value = Math.max(1, Math.min(value, max));
                input.value = value;
                if (form) form.value = value;
            });
        });

        // Cart modal quantity buttons
        document.querySelectorAll('.quantity-form').forEach(form => {
            const input = form.querySelector('.quantity-input');
            form.querySelectorAll('.quantity-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    let value = parseInt(input.value);
                    const max = parseInt(input.max || 999);
                    if (btn.classList.contains('plus')) {
                        value = Math.min(value + 1, max);
                    } else if (btn.classList.contains('minus')) {
                        value = Math.max(value - 1, 1);
                    }
                    input.value = value;
                    form.querySelector('input[name="quantity"]').value = value;
                });
            });
            input.addEventListener('change', () => {
                let value = parseInt(input.value);
                value = Math.max(1, Math.min(value, parseInt(input.max || 999)));
                input.value = value;
                form.querySelector('input[name="quantity"]').value = value;
            });
        });

        // Customize buttons
        document.querySelectorAll('.customize-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productCard = this.closest('.product-card');
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                const basePrice = parseFloat(this.dataset.basePrice);
                const quantity = parseInt(this.closest('.product-info').querySelector('.quantity-input').value);

                document.getElementById('customization-product-name').textContent = productName;
                document.getElementById('customization-base-price').innerHTML = 
                    `Base Price: Rs. <strong>${basePrice.toFixed(2)}</strong> × ${quantity} = Rs. <strong>${(basePrice * quantity).toFixed(2)}</strong>`;
                document.getElementById('custom-product-id').value = productId;
                document.getElementById('custom-quantity').value = quantity;

                const customizations = JSON.parse(productCard.dataset.customizations || '[]');
                displayCustomizationOptions(customizations, basePrice, quantity);
                customizationModal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            });
        });

        function displayCustomizationOptions(customizations, basePrice, quantity) {
            const container = document.getElementById('customization-options');
            container.innerHTML = '';

            if (customizations.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666; padding: 20px;">No customizations available for this product.</p>';
                updateCustomizationTotal(basePrice, quantity, []);
                return;
            }

            const categories = {};
            customizations.forEach(cust => {
                if (!categories[cust.category]) categories[cust.category] = [];
                categories[cust.category].push(cust);
            });

            let html = '';
            Object.keys(categories).forEach(category => {
                html += `<div class="customization-category"><h4>${category}</h4>`;
                categories[category].forEach(cust => {
                    html += `
                        <div class="customization-option">
                            <input type="checkbox" name="customizations[]" value="${cust.id}" id="cust-${cust.id}" data-price="${cust.price_adjustment}">
                            <label for="cust-${cust.id}" class="customization-name">${cust.name}</label>
                            <span class="customization-price">+Rs. ${parseFloat(cust.price_adjustment).toFixed(2)}</span>
                        </div>
                    `;
                });
                html += `</div>`;
            });
            container.innerHTML = html;

            container.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', () => {
                    const selected = Array.from(container.querySelectorAll('input[type="checkbox"]:checked'))
                                        .map(cb => parseFloat(cb.dataset.price));
                    updateCustomizationTotal(basePrice, quantity, selected);
                });
            });
            updateCustomizationTotal(basePrice, quantity, []);
        }

        function updateCustomizationTotal(basePrice, quantity, selectedPrices) {
            const additional = selectedPrices.reduce((sum, price) => sum + price, 0);
            const total = (basePrice + additional) * quantity;
            document.getElementById('custom-total-price').textContent = total.toFixed(2);
        }

        // Checkout summary
        checkoutBtn?.addEventListener('click', () => {
            const summary = document.getElementById('checkout-summary');
            summary.innerHTML = '';
            <?php foreach ($cartItems as $item): 
                $selectedCustomizations = json_decode($item['selected_customizations'], true) ?: [];
                $customizationNames = getCustomizationNames($pdo, $selectedCustomizations);
            ?>
                const itemDiv = document.createElement('div');
                itemDiv.style.cssText = 'padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; flex-wrap: wrap;';
                let customizations = '';
                <?php if (!empty($customizationNames)): ?>
                    customizations = '<div style="width: 100%; margin-top: 5px; font-size: 0.9rem; color: #666;">Customizations: <?php 
                        foreach ($customizationNames as $cust) {
                            echo htmlspecialchars($cust['name']) . ' (+Rs. ' . number_format($cust['price_adjustment'], 2) . '), ';
                        }
                    ?></div>';
                <?php endif; ?>
                itemDiv.innerHTML = `
                    <span><?php echo htmlspecialchars($item['product_name']); ?> × <?php echo $item['quantity']; ?></span>
                    <span>Rs. <?php echo number_format($item['total_price'], 2); ?></span>
                    ${customizations}
                `;
                summary.appendChild(itemDiv);
            <?php endforeach; ?>
            const totalDiv = document.createElement('div');
            totalDiv.style.cssText = 'padding: 10px; font-weight: bold; border-top: 2px solid #ddd; display: flex; justify-content: space-between;';
            totalDiv.innerHTML = `
                <span>Total:</span>
                <span>Rs. <?php echo number_format($cartTotal, 2); ?></span>
            `;
            summary.appendChild(totalDiv);
            closeModal(cartModal);
            checkoutModal.style.display = 'block';
        });

        // Smooth scroll & animations
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.style.background = 'rgba(255, 255, 255, 0.98)';
                header.style.boxShadow = '0 2px 30px rgba(0,0,0,0.15)';
            } else {
                header.style.background = 'rgba(255, 255, 255, 0.95)';
                header.style.boxShadow = '0 2px 20px rgba(0,0,0,0.1)';
            }
        });

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

        document.querySelectorAll('.animated').forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });

        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mouseenter', () => card.style.transform = 'translateY(-10px) scale(1.02)');
            card.addEventListener('mouseleave', () => card.style.transform = 'translateY(0) scale(1)');
        });
    </script>
</body>
</html>
