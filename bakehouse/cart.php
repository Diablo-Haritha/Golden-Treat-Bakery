<?php
session_start();
// For guest users, use a temporary user_id stored in session
if (!isset($_SESSION['temp_user_id'])) {
    $_SESSION['temp_user_id'] = uniqid('guest_', true);
}
$user_id = $_SESSION['temp_user_id'];

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'msg' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action == 'get_cart') {
        $items = [];
        $total = 0;
        $sql = "SELECT c.product_id, c.quantity, p.name, p.price, p.image_path
                FROM cart c
                JOIN products p ON c.product_id = p.id
                WHERE c.user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['ok' => false, 'msg' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['ok' => true, 'items' => [], 'total' => 0, 'debug' => ['user_id' => $user_id, 'item_count' => 0]]);
            $stmt->close();
            exit;
        }
        while ($row = $result->fetch_assoc()) {
            $line_total = $row['price'] * $row['quantity'];
            $items[] = [
                'id' => $row['product_id'],
                'name' => $row['name'],
                'qty' => $row['quantity'],
                'line_total' => $line_total,
                'emoji' => '🍰',
                'image' => $row['image_path'],
                'price' => $row['price']
            ];
            $total += $line_total;
        }
        $stmt->close();
        echo json_encode(['ok' => true, 'items' => $items, 'total' => $total, 'debug' => ['user_id' => $user_id, 'item_count' => count($items)]]);
        exit;
    } elseif ($action == 'set_cart_qty') {
        $data = json_decode(file_get_contents('php://input'), true);
        $product_id = isset($data['id']) ? intval($data['id']) : 0;
        $qty = isset($data['qty']) ? intval($data['qty']) : 0;

        // Validate inputs
        if ($product_id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid product ID']);
            exit;
        }
        if ($qty < 0) {
            echo json_encode(['ok' => false, 'msg' => 'Quantity cannot be negative']);
            exit;
        }

        // Check stock
        $sql = "SELECT quantity FROM products WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            echo json_encode(['ok' => false, 'msg' => 'Product not found']);
            exit;
        }
        $stock = $result->fetch_assoc()['quantity'];
        $stmt->close();

        if ($qty > $stock) {
            echo json_encode(['ok' => false, 'msg' => 'Out of stock']);
            exit;
        }

        if ($qty == 0) {
            $sql = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $user_id, $product_id);
        } else {
            $sql = "UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $qty, $user_id, $product_id);
            $stmt->execute();
            if ($stmt->affected_rows == 0 && $qty > 0) {
                // If no rows updated, insert new record
                $sql = "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sii", $user_id, $product_id, $qty);
            }
        }
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['ok' => $success, 'msg' => $success ? 'Cart updated' : 'Error updating cart', 'debug' => ['product_id' => $product_id, 'qty' => $qty]]);
        exit;
    } elseif ($action == 'clear_cart') {
        $sql = "DELETE FROM cart WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode(['ok' => false, 'msg' => 'Prepare failed: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("s", $user_id);
        $success = $stmt->execute();
        $stmt->close();
        echo json_encode(['ok' => $success, 'msg' => $success ? 'Cart cleared' : 'Error clearing cart']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat - Cart</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Righteous&display=swap" rel="stylesheet">
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg);
            font-family: 'Righteous', sans-serif;
            color: var(--dark);
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        nav {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            padding: 10px 30px;
            box-shadow: var(--shadow);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        nav.scrolled {
            background: rgba(255, 255, 255, 0.95);
            box-shadow: var(--shadow-hover);
        }

        nav ul {
            display: flex;
            list-style: none;
            gap: 30px;
            align-items: center;
        }

        nav a {
            text-decoration: none;
            color: var(--dark);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        nav a:hover {
            color: var(--primary);
            transform: translateY(-2px);
        }

        nav a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-1);
            transition: width 0.3s ease;
        }

        nav a:hover::after {
            width: 100%;
        }

        .section {
            padding: 100px 20px;
            max-width: 1200px;
            margin: 0 auto;
            opacity: 0;
            transform: translateY(50px);
            transition: all 0.8s ease;
        }

        .section.show {
            opacity: 1;
            transform: translateY(0);
        }

        .section h2 {
            font-family: 'Dancing Script', cursive;
            font-size: 3rem;
            text-align: center;
            margin-bottom: 60px;
            color: var(--secondary);
            position: relative;
        }

        .section h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--gradient-1);
            border-radius: 2px;
        }

        .cart-container {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
        }

        .cart-empty {
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            color: var(--secondary);
        }

        .cart-items {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: var(--light);
            border-radius: 15px;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .cart-item-image {
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-item-info {
            flex: 1;
            font-family: 'Poppins', sans-serif;
        }

        .cart-item-info h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .cart-item-info p {
            color: var(--secondary);
            margin-bottom: 10px;
        }

        .cart-item-price {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary);
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-item-controls button {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cart-item-controls button:hover {
            background: var(--secondary);
            transform: scale(1.1);
        }

        .cart-item-controls input {
            width: 50px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            border: 1px solid var(--accent);
            border-radius: 5px;
            padding: 5px;
        }

        .cart-total {
            margin-top: 30px;
            text-align: right;
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
        }

        .cart-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn {
            padding: 12px 25px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 50px;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            text-align: center;
        }

        .btn:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn.clear-cart {
            background: var(--dark);
        }

        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 1;
            transition: opacity 0.5s ease;
        }

        .loading.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid var(--accent);
            border-top: 3px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .section { padding: 60px 20px; }
            .section h2 { font-size: 2.5rem; }
            .cart-item { flex-direction: column; align-items: flex-start; }
            .cart-item-image { width: 80px; height: 80px; }
            .cart-actions { flex-direction: column; align-items: stretch; }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
    <!-- Loading screen -->
    <div class="loading">
        <div class="spinner"></div>
    </div>

    <!-- Animated particles -->
    <div class="particles"></div>

    <!-- Navigation -->
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="index.php#products">Products</a></li>
            <li><a href="login.php">Services</a></li>
            <li><a href="home.php">About</a></li>
            <li><a href="profile.php">Contact</a></li>
        </ul>
    </nav>

    <!-- Cart Section -->
    <section class="section" id="cart">
        <h2>Your Cart</h2>
        <div class="cart-container" id="cartContainer">
            <div class="cart-empty">Your cart is empty.</div>
        </div>
    </section>

    <script>
        // Utility functions
        const api = (action, options = {}) => fetch(`?action=${action}`, options);
        const el = sel => document.querySelector(sel);
        const els = sel => document.querySelectorAll(sel);

        // Loading screen
        window.addEventListener('load', () => {
            setTimeout(() => el('.loading').classList.add('hidden'), 800);
        });

        // Particles
        function createParticles() {
            const wrap = el('.particles');
            for (let i = 0; i < 50; i++) {
                const d = document.createElement('div');
                d.className = 'particle';
                d.style.left = Math.random() * 100 + '%';
                d.style.top = Math.random() * 100 + '%';
                const size = Math.random() * 10 + 5;
                d.style.width = size + 'px';
                d.style.height = size + 'px';
                d.style.animationDelay = Math.random() * 6 + 's';
                d.style.animationDuration = (Math.random() * 3 + 3) + 's';
                wrap.appendChild(d);
            }
        }

        // Scroll animations
        function animateOnScroll() {
            els('.section').forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.8) section.classList.add('show');
            });
        }

        // Nav scroll effect
        function updateNav() {
            const nav = el('nav');
            if (window.scrollY > 100) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        }

        // Render cart
        async function loadCart() {
            try {
                const response = await api('get_cart');
                const data = await response.json();
                console.log('Cart data:', data); // Debug: Log cart data
                const container = el('#cartContainer');
                container.innerHTML = '';

                if (!data.ok || !data.items.length) {
                    container.innerHTML = '<div class="cart-empty">Your cart is empty.</div>';
                    return;
                }

                const itemsDiv = document.createElement('div');
                itemsDiv.className = 'cart-items';
                data.items.forEach(item => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'cart-item';
                    itemDiv.innerHTML = `
                        <div class="cart-item-image">${item.image ? `<img src="${item.image}" alt="${item.name}">` : item.emoji}</div>
                        <div class="cart-item-info">
                            <h3>${item.name}</h3>
                            <p>Price: $${Number(item.price).toFixed(2)}</p>
                            <div class="cart-item-price">Line Total: $${Number(item.line_total).toFixed(2)}</div>
                        </div>
                        <div class="cart-item-controls">
                            <button onclick="updateQty(${item.id}, ${item.qty - 1})">-</button>
                            <input type="number" value="${item.qty}" min="0" onchange="updateQty(${item.id}, this.value)">
                            <button onclick="updateQty(${item.id}, ${item.qty + 1})">+</button>
                        </div>`;
                    itemsDiv.appendChild(itemDiv);
                });

                const totalDiv = document.createElement('div');
                totalDiv.className = 'cart-total';
                totalDiv.textContent = `Total: $${Number(data.total).toFixed(2)}`;

                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'cart-actions';
                actionsDiv.innerHTML = `
                    <button class="btn clear-cart" onclick="clearCart()">Clear Cart</button>
                    <button class="btn" onclick="checkout()">Checkout</button>`;

                container.appendChild(itemsDiv);
                container.appendChild(totalDiv);
                container.appendChild(actionsDiv);
            } catch (error) {
                console.error('Error loading cart:', error);
                el('#cartContainer').innerHTML = '<div class="cart-empty">Error loading cart: ' + error.message + '</div>';
            }
        }

        // Update quantity
        async function updateQty(id, qty) {
            try {
                qty = parseInt(qty);
                console.log('Updating quantity:', { id, qty }); // Debug: Log update action
                const response = await api('set_cart_qty', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, qty })
                });
                const data = await response.json();
                console.log('Update quantity response:', data); // Debug: Log response
                if (data.ok) {
                    await loadCart();
                } else {
                    alert(data.msg || 'Error updating cart');
                }
            } catch (error) {
                console.error('Error updating quantity:', error);
                alert('Error updating cart: ' + error.message);
            }
        }

        // Clear cart
        async function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
                try {
                    const response = await api('clear_cart');
                    const data = await response.json();
                    console.log('Clear cart response:', data); // Debug: Log response
                    if (data.ok) {
                        await loadCart();
                        alert('Cart cleared!');
                    } else {
                        alert(data.msg || 'Error clearing cart');
                    }
                } catch (error) {
                    console.error('Error clearing cart:', error);
                    alert('Error clearing cart: ' + error.message);
                }
            }
        }

        // Checkout placeholder
        function checkout() {
            alert('Proceeding to checkout - This would redirect to a payment page!');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            createParticles();
            animateOnScroll();
            await loadCart();

            window.addEventListener('scroll', () => {
                animateOnScroll();
                updateNav();
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>