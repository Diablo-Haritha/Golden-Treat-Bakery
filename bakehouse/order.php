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
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// Initialize variables
$orders = [];
$message = '';
$messageType = '';
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Validate user_id if set
if ($userId) {
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        // Invalid user_id, clear session
        unset($_SESSION['user_id']);
        $userId = null;
        $message = "⚠️ Your session is invalid. Please log in again.";
        $messageType = 'warning';
    }
}

// If not logged in, redirect or show message
if (!$userId) {
    header('Location: login.php?redirect=order.php');
    exit;
} else {
    try {
        // Fetch all orders for the logged-in user
        $stmt = $pdo->prepare("
            SELECT o.*, COUNT(oi.id) as item_count 
            FROM orders o 
            LEFT JOIN order_items oi ON o.id = oi.order_id 
            WHERE o.user_id = ? 
            GROUP BY o.id 
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
        $orders = $stmt->fetchAll();

        if (empty($orders)) {
            $message = "📭 No orders found. Start shopping to place your first order!";
            $messageType = 'info';
        }
    } catch (PDOException $e) {
        $message = "❌ An error occurred while fetching your orders. Please try again.";
        $messageType = 'error';
        error_log("Orders fetch error: " . $e->getMessage() . " | user_id=$userId");
    }
}

/**
 * Fetch order items summary for a specific order (limited for preview)
 * @param PDO $pdo Database connection
 * @param int $orderId Order ID
 * @return array Array of order items (limited to 3 for preview)
 */
function getOrderItemsPreview($pdo, $orderId) {
    try {
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name 
            FROM order_items oi 
            LEFT JOIN products p ON oi.product_name = p.name 
            WHERE oi.order_id = ? 
            ORDER BY oi.id 
            LIMIT 3
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Order items preview error: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch customization names for order items
 * @param PDO $pdo Database connection
 * @param string $customizationJson JSON string of customization IDs
 * @return array Array of customization names and price adjustments
 */
function getCustomizationNames($pdo, $customizationJson) {
    $customizationIds = json_decode($customizationJson, true) ?: [];
    if (empty($customizationIds)) {
        return [];
    }
    
    try {
        $placeholders = str_repeat('?,', count($customizationIds) - 1) . '?';
        $stmt = $pdo->prepare("SELECT id, name, price_adjustment FROM customizations WHERE id IN ($placeholders) AND is_active = 1");
        $stmt->execute($customizationIds);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Customization fetch error: " . $e->getMessage());
        return [];
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Golden Treat Bakery</title>
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
            --info: #17a2b8;
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
        .btn-info { background: linear-gradient(135deg, var(--info), #20c997); }
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
        .orders-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin: 120px auto 60px;
        }
        .orders-list {
            margin-top: 20px;
        }
        .order-card {
            border: 1px solid var(--border);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            background: var(--light);
        }
        .order-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .order-number {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary);
        }
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
        }
        .order-status.pending { background: var(--warning); }
        .order-status.confirmed { background: var(--success); }
        .order-status.shipped { background: var(--info); }
        .order-status.delivered { background: var(--success); }
        .order-status.cancelled { background: var(--danger); }
        .order-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 0.95rem;
            color: #666;
        }
        .order-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }
        .preview-item {
            background: white;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 0.85rem;
            color: var(--text);
            box-shadow: var(--shadow);
        }
        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.2rem;
            font-weight: bold;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        .order-actions {
            display: flex;
            gap: 10px;
        }
        .no-orders {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        .no-orders i {
            font-size: 4rem;
            color: var(--secondary);
            margin-bottom: 20px;
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
        .message.info { background: linear-gradient(135deg, var(--info), #20c997); }
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
            .orders-container { padding: 20px; }
            .order-header { flex-direction: column; align-items: flex-start; }
            .order-actions { flex-direction: column; width: 100%; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }
    </style>
</head>
<body>
    <!-- Message Display -->
    <?php if (!empty($message)): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?> animated">
            <?php echo htmlspecialchars($message); ?>
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
                    <li><a href="orders.php">My Orders</a></li>
                    <?php if ($userId): ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main>
        <section class="orders-section">
            <div class="container">
                <h2 class="section-title animated fadeIn">
                    <i class="fas fa-shopping-bag"></i> My Orders
                </h2>
                <div class="orders-container animated fadeIn">
                    <?php if (!empty($orders)): ?>
                        <div class="orders-list">
                            <?php foreach ($orders as $order): 
                                $orderItemsPreview = getOrderItemsPreview($pdo, $order['id']);
                                $status = $order['status'] ?? 'Pending';
                            ?>
                                <div class="order-card">
                                    <div class="order-header">
                                        <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                                        <span class="order-status <?php echo strtolower($status); ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                                    </div>
                                    <div class="order-meta">
                                        <span><i class="fas fa-calendar"></i> <?php echo date('F j, Y, g:i A', strtotime($order['created_at'])); ?></span>
                                        <span><i class="fas fa-boxes"></i> <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <?php if (!empty($orderItemsPreview)): ?>
                                        <div class="order-preview">
                                            <?php foreach ($orderItemsPreview as $item): 
                                                $customizations = getCustomizationNames($pdo, $item['customizations']);
                                                $previewText = htmlspecialchars($item['product_name']);
                                                if (!empty($customizations)) {
                                                    $previewText .= ' (';
                                                    foreach ($customizations as $cust) {
                                                        $previewText .= htmlspecialchars($cust['name']) . ', ';
                                                    }
                                                    $previewText = rtrim($previewText, ', ') . ')';
                                                }
                                            ?>
                                                <div class="preview-item"><?php echo $previewText; ?> × <?php echo (int)$item['quantity']; ?></div>
                                            <?php endforeach; ?>
                                            <?php if (count($orderItemsPreview) < $order['item_count']): ?>
                                                <div class="preview-item">... +<?php echo $order['item_count'] - count($orderItemsPreview); ?> more</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="order-total">
                                        <span>Total: Rs. <?php echo number_format($order['total_amount'], 2); ?></span>
                                        <div class="order-actions">
                                            <a href="order.php?order_number=<?php echo urlencode($order['order_number']); ?>" class="btn btn-info" style="padding: 8px 16px; font-size: 0.9rem;">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-orders">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No Orders Yet</h3>
                            <p>Start exploring our delicious treats and place your first order!</p>
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Start Shopping
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
                        <li><a href="orders.php">My Orders</a></li>
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
                &copy; 2025 Golden Treat Bakery. All rights reserved. | Made By 404 Error
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