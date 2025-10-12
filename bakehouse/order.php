<?php
session_start();
ob_start();

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
$order = null;
$orderItems = [];
$message = '';
$messageType = '';

// Check for order_number in query parameter
if (!isset($_GET['order_number']) || empty($_GET['order_number'])) {
    $message = "❌ Invalid or missing order number.";
    $messageType = 'error';
} else {
    $orderNumber = trim($_GET['order_number']);
    $sessionId = session_id();
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Fetch order details (for logged-in user or guest session)
    $query = "SELECT * FROM orders WHERE order_number = ?";
    $params = [$orderNumber];
    
    if ($userId) {
        $query .= " AND (user_id = ? OR user_id IS NULL)";
        $params[] = $userId;
    } else {
        $query .= " AND user_id IS NULL";
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $order = $stmt->fetch();

    if ($order) {
        // Fetch order items
        $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmt->execute([$order['id']]);
        $orderItems = $stmt->fetchAll();
        
        // If no order items found, set a message
        if (empty($orderItems)) {
            $message = "⚠️ No items found for this order.";
            $messageType = 'warning';
        }
    } else {
        $message = "❌ Order not found or you do not have access to this order.";
        $messageType = 'error';
    }
}

function getCustomizationNames($pdo, $customizationJson) {
    $customizationIds = json_decode($customizationJson, true) ?: [];
    if (empty($customizationIds)) return [];
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
    <title>Order Details - Golden Treat Bakery</title>
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
        .animated { animation-duration: 0.6s; animation-fill-mode: both; }
        .fadeIn { animation-name: fadeIn; }
        .slideIn { animation-name: slideIn; }
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
        .order-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin: 120px auto 60px;
            max-width: 800px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--secondary);
        }
        .order-details p {
            margin: 10px 0;
            font-size: 1.1rem;
        }
        .order-details strong {
            color: var(--primary);
            margin-right: 10px;
        }
        .order-items {
            margin: 20px 0;
        }
        .order-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
            align-items: center;
        }
        .order-item-image {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--secondary), #e8d4a6);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        .order-item-details { flex: 1; }
        .order-item-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--dark);
        }
        .order-item-customizations {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 8px;
        }
        .order-item-customization {
            display: inline-block;
            background: var(--light);
            padding: 4px 10px;
            border-radius: 15px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 0.8rem;
        }
        .order-item-price {
            font-weight: bold;
            color: var(--primary);
        }
        .order-total {
            display: flex;
            justify-content: space-between;
            font-size: 1.3rem;
            font-weight: bold;
            padding: 20px 0;
            border-top: 2px solid var(--secondary);
            margin-top: 20px;
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
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 15px; }
            .nav-links { gap: 15px; }
            .order-container { padding: 20px; }
            .order-item { flex-direction: column; text-align: center; }
            .order-item-image { margin-bottom: 15px; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- Message Display -->
    <?php if (!empty($message)): ?>
        <div class="message <?php echo $messageType; ?> animated">
            <?php echo $message; ?>
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
                <ul class="nav-links">
                    <li><a href="index.php#products">Products</a></li>
                    <li><a href="index.php#about">About</a></li>
                    <li><a href="index.php#contact">Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="order-section">
            <div class="container">
                <h2 class="section-title animated fadeIn">
                    <i class="fas fa-shopping-bag"></i> Order Details
                </h2>
                <div class="order-container animated fadeIn">
                    <?php if ($order): ?>
                        <div class="order-header">
                            <h2>Order #<?php echo htmlspecialchars($order['order_number']); ?></h2>
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Shop
                            </a>
                        </div>
                        <div class="order-details">
                            <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                            <?php if (!empty($order['customer_phone'])): ?>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                            <?php endif; ?>
                            <p><strong>Order Date:</strong> <?php echo date('F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                        <div class="order-items">
                            <h3 style="color: var(--primary); margin-bottom: 15px;">Order Items</h3>
                            <?php foreach ($orderItems as $item): 
                                $customizations = getCustomizationNames($pdo, $item['customizations']);
                            ?>
                                <div class="order-item">
                                    <div class="order-item-image">
                                        <i class="fas fa-bread-slice"></i>
                                    </div>
                                    <div class="order-item-details">
                                        <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                        <?php if (!empty($customizations)): ?>
                                            <div class="order-item-customizations">
                                                <?php foreach ($customizations as $cust): ?>
                                                    <span class="order-item-customization">
                                                        <?php echo htmlspecialchars($cust['name']); ?> (+Rs. <?php echo number_format($cust['price_adjustment'], 2); ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="order-item-price">
                                            Rs. <?php echo number_format($item['unit_price'], 2); ?> × <?php echo $item['quantity']; ?> = Rs. <?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-total">
                            <span>Total Amount:</span>
                            <span>Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                        </div>
                    <?php else: ?>
                        <div class="no-order" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-shopping-bag" style="font-size: 4rem; margin-bottom: 20px; color: var(--secondary);"></i>
                            <h3>No order details available</h3>
                            <p>Please check the order number or return to the shop.</p>
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Shop
                            </a>
                        </div>
                    <?php endif; ?>
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
                        <li><a href="index.php#products">Our Products</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="admin.php">Admin Panel</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> No.12,Kuliyapitiya,Kurunegala</li>
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
        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Header scroll effect
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

        // Intersection Observer for animations
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
    </script>
</body>
</html>