<?php
// Set timezone to match login.php
date_default_timezone_set('Asia/Kolkata');

// Database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=golden_treat', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Session handling
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();

// Check if user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Create tables if they don't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            mobile VARCHAR(20),
            address VARCHAR(255),
            district VARCHAR(100),
            password VARCHAR(255) NOT NULL,
            role ENUM('customer', 'admin', 'manager') DEFAULT 'customer',
            date_joined DATE
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) UNIQUE NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20),
            total_amount DECIMAL(10, 2) NOT NULL,
            status ENUM('pending', 'confirmed', 'preparing', 'ready', 'completed') DEFAULT 'pending',
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    die("Table creation failed: " . $e->getMessage());
}

// Fetch user_id using email
$user_email = $_SESSION['email'];
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id = ?");
$stmt->execute([$user_email, $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();
    die("User not found for email: " . htmlspecialchars($user_email));
}

// Fetch orders for the user
$stmt = $pdo->prepare("SELECT id, order_number, created_at, status, total_amount, customer_name, customer_email, customer_phone 
                       FROM orders 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = empty($orders) ? "No orders found. Start shopping!" : "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat - My Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Righteous&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --bg: #FFE8B7;
            --primary: #D4AF37;
            --secondary: #8B4513;
            --accent: #FFE5B4;
            --dark: #2C1810;
            --light: #FFF8F0;
            --white: #FFFFFF;
            --gradient-1: linear-gradient(135deg, #D4AF37, #FFE5B4);
            --gradient-2: linear-gradient(135deg, #8B4513, #D2691E);
            --shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
            --shadow-hover: 0 15px 40px rgba(212, 175, 55, 0.3);
            --radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            background: var(--bg);
            position: relative;
        }

        .frosting-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            background: var(--gradient-1);
            animation: spreadFrosting 15s ease-in-out infinite;
            overflow: hidden;
        }

        @keyframes spreadFrosting {
            0% { background: linear-gradient(135deg, #FFE8B7, #D4AF37); }
            50% { background: linear-gradient(225deg, #FFE5B4, #D2691E); }
            100% { background: linear-gradient(135deg, #FFE8B7, #D4AF37); }
        }

        .sprinkles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }

        .sprinkle {
            position: absolute;
            width: 8px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), #ff6f91);
            border-radius: 2px;
            opacity: 0.6;
            animation: fall 4s linear infinite;
        }

        @keyframes fall {
            0% { transform: translateY(-10vh) rotate(0deg); opacity: 0.6; }
            100% { transform: translateY(110vh) rotate(360deg); opacity: 0.2; }
        }

        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--white);
            color: var(--dark);
            padding: 12px 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 4;
            font-size: 0.9rem;
            text-align: center;
            max-width: 90%;
            width: 300px;
            opacity: 0;
            animation: fadeInOut 3s ease forwards;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            10% { opacity: 1; transform: translateX(-50%) translateY(0); }
            90% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }

        .header {
            background: var(--white);
            padding: 15px 20px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 2;
            position: relative;
        }

        .header h1 {
            font-family: 'Dancing Script', cursive;
            font-size: 2rem;
            color: var(--secondary);
        }

        .back-btn {
            background: var(--gradient-1);
            color: var(--white);
            border: none;
            padding: 10px 15px;
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 2;
            position: relative;
        }

        .orders-section h2 {
            font-family: 'Dancing Script', cursive;
            font-size: 2rem;
            color: var(--secondary);
            text-align: center;
            margin-bottom: 20px;
        }

        .no-orders {
            text-align: center;
            color: var(--secondary);
            font-size: 1.1rem;
            padding: 40px;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .orders-table th,
        .orders-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--accent);
        }

        .orders-table th {
            background: var(--gradient-1);
            color: var(--white);
            font-weight: 600;
        }

        .orders-table tr:hover {
            background: var(--light);
        }

        .status-pending { color: #ff9800; font-weight: bold; }
        .status-confirmed { color: #4caf50; font-weight: bold; }
        .status-preparing { color: #2196f3; font-weight: bold; }
        .status-ready { color: #9c27b0; font-weight: bold; }
        .status-completed { color: #4caf50; font-weight: bold; }

        .order-details {
            cursor: pointer;
            color: var(--primary);
            text-decoration: underline;
        }

        .order-details:hover {
            color: var(--secondary);
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 20px;
            border-radius: var(--radius);
            width: 80%;
            max-width: 600px;
            box-shadow: var(--shadow-hover);
        }

        .close {
            color: var(--secondary);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--primary);
        }

        @media (max-width: 768px) {
            .header {
                padding: 10px;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .container {
                margin: 10px;
                padding: 15px;
            }

            .orders-table th,
            .orders-table td {
                padding: 8px;
                font-size: 0.9rem;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <div class="frosting-bg"></div>
    <div class="sprinkles"></div>

    <?php if (!empty($message)): ?>
        <div class="message error">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="header">
        <h1>My Orders</h1>
        <button class="back-btn" onclick="window.location.href='index.php'"><i class="fas fa-arrow-left"></i> Back to Home</button>
    </div>

    <div class="container">
        <div class="orders-section">
            <h2>Your Recent Orders</h2>
            <div id="orders-list">
                <?php if (!empty($orders)): ?>
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                    <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                    <td><span class="status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo htmlspecialchars($order['status']); ?></span></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td><span class="order-details" onclick="showOrderDetails(<?php echo $order['id']; ?>)">View Details</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-orders">No orders found. Start shopping!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="order-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3 id="modal-order-title">Order Details</h3>
            <p><strong>Order Number:</strong> <span id="modal-order-number"></span></p>
            <p><strong>Date:</strong> <span id="modal-order-date"></span></p>
            <p><strong>Customer Name:</strong> <span id="modal-customer-name"></span></p>
            <p><strong>Customer Email:</strong> <span id="modal-customer-email"></span></p>
            <p><strong>Customer Phone:</strong> <span id="modal-customer-phone"></span></p>
            <p><strong>Total Amount:</strong> <span id="modal-total-amount"></span></p>
            <p><strong>Status:</strong> <span id="modal-status"></span></p>
        </div>
    </div>

    <script>
        function createSprinkles() {
            const sprinkleContainer = document.querySelector('.sprinkles');
            for (let i = 0; i < 100; i++) {
                const sprinkle = document.createElement('div');
                sprinkle.className = 'sprinkle';
                sprinkle.style.left = Math.random() * 100 + '%';
                sprinkle.style.animationDelay = Math.random() * 4 + 's';
                sprinkle.style.animationDuration = (Math.random() * 2 + 3) + 's';
                sprinkleContainer.appendChild(sprinkle);
            }
        }

        function showMessage(text, isError = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${isError ? 'error' : 'success'}`;
            messageDiv.textContent = text;
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
        }

        function showOrderDetails(orderId) {
            const orders = <?php echo json_encode($orders); ?>;
            const order = orders.find(o => o.id == orderId);
            if (order) {
                document.getElementById('modal-order-number').textContent = order.order_number;
                document.getElementById('modal-order-date').textContent = order.created_at;
                document.getElementById('modal-customer-name').textContent = order.customer_name;
                document.getElementById('modal-customer-email').textContent = order.customer_email;
                document.getElementById('modal-customer-phone').textContent = order.customer_phone || 'N/A';
                document.getElementById('modal-total-amount').textContent = `$${parseFloat(order.total_amount).toFixed(2)}`;
                document.getElementById('modal-status').textContent = order.status;
                document.getElementById('modal-status').className = `status-${order.status}`;
                document.getElementById('modal-order-title').textContent = `Order #${order.order_number}`;
                document.getElementById('order-modal').style.display = 'block';
            } else {
                showMessage('Order not found.', true);
            }
        }

        document.querySelector('.close').onclick = function() {
            document.getElementById('order-modal').style.display = 'none';
        };

        window.onclick = function(event) {
            const modal = document.getElementById('order-modal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            createSprinkles();
            <?php if (!empty($message)): ?>
                showMessage('<?php echo htmlspecialchars($message); ?>', true);
            <?php endif; ?>
        });
    </script>
</body>
</html>