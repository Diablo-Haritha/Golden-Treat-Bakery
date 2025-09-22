<?php
ini_set('display_errors', 1); // Debug: Enable errors
error_reporting(E_ALL);

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
    die("Connection failed: " . $conn->connect_error);
}

// Fetch products
$products = [];
$sql = "SELECT * FROM products";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
} else {
    die("Error fetching products: " . $conn->error);
}

// Handle actions for JS API (fallback - most will go to cart.php)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action == 'list_products') {
        echo json_encode(['products' => $products]);
        exit;
    }
    
    // Redirect other cart actions to cart.php
    $redirect_actions = ['add_to_cart', 'get_cart', 'set_cart_qty', 'clear_cart'];
    if (in_array($action, $redirect_actions)) {
        $query = http_build_query(['action' => $action]);
        $redirect_url = "cart.php?$query";
        
        // Handle POST data
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post_data = file_get_contents('php://input');
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $post_data
                ]
            ]);
            $response = file_get_contents($redirect_url, false, $context);
        } else {
            $response = file_get_contents($redirect_url);
        }
        
        if ($response !== false) {
            echo $response;
        } else {
            echo json_encode(['ok' => false, 'msg' => 'Failed to process request']);
        }
        exit;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat - Products</title>
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
            --success: #4CAF50;
            --error: #f44336;
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
            cursor: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="%23D4AF37" opacity="0.5"/></svg>'), auto;
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
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            font-family: 'Poppins', sans-serif;
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

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .product-card {
            background: var(--white);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-hover);
        }

        .product-image {
            width: 100%;
            height: 250px;
            background: var(--gradient-1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent 30%, rgba(255, 255, 255, 0.1) 50%, transparent 70%);
            transform: translateX(-100%);
            transition: transform 0.6s;
        }

        .product-card:hover .product-image::before {
            transform: translateX(100%);
        }

        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .stock-badge.out-of-stock {
            background: var(--error);
        }

        .product-info {
            padding: 25px;
        }

        .product-info h3 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .product-info p {
            font-family: 'Poppins', sans-serif;
            color: var(--secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .product-price {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .btn {
            display: block;
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
            width: 100%;
        }

        .btn:hover:not(:disabled) {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        .btn:disabled {
            background: var(--accent);
            cursor: not-allowed;
            transform: none;
            opacity: 0.6;
        }

        .btn.added {
            background: var(--success) !important;
            animation: pulse 0.6s ease;
        }

        .btn.loading {
            position: relative;
            color: transparent;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 16px;
            height: 16px;
            margin: -8px 0 0 -8px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .floating-cart {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 70px;
            height: 70px;
            background: var(--gradient-1);
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

        .floating-cart:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-hover);
        }

        .floating-cart::before {
            content: '🛒';
            font-size: 1.5rem;
        }

        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--secondary);
            color: var(--white);
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.2rem 0.6rem;
            border-radius: 15px;
            min-width: 20px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .quick-actions {
            position: fixed;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 15px;
            z-index: 1000;
        }

        .quick-btn {
            width: 60px;
            height: 60px;
            background: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
            border: none;
        }

        .quick-btn:hover {
            transform: scale(1.1);
            background: var(--primary);
            color: var(--white);
        }

        .progress-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: var(--gradient-1);
            z-index: 9999;
            transition: width 0.3s ease;
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

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--white);
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 450px;
            text-align: center;
            box-shadow: var(--shadow-hover);
            animation: slideIn 0.3s ease;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--secondary);
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--primary);
        }

        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-content h3 {
            font-family: 'Dancing Script', cursive;
            font-size: 2rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }

        .modal-product-name {
            font-family: 'Poppins', sans-serif;
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 20px;
            font-weight: 500;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: var(--light);
            border-radius: 15px;
        }

        .quantity-selector button {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-selector button:hover:not(:disabled) {
            background: var(--secondary);
            transform: scale(1.1);
        }

        .quantity-selector button:disabled {
            background: var(--accent);
            cursor: not-allowed;
            transform: none;
        }

        .quantity-selector input {
            width: 70px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1.4rem;
            border: 2px solid var(--accent);
            border-radius: 10px;
            padding: 8px;
            -moz-appearance: textfield;
        }

        .quantity-selector input::-webkit-outer-spin-button,
        .quantity-selector input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .quantity-selector input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
        }

        .stock-info {
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 8px;
            background: var(--light);
        }

        .stock-info.available {
            color: var(--success);
            border: 1px solid var(--success);
        }

        .stock-info.low {
            color: #ff9800;
            border: 1px solid #ff9800;
        }

        .stock-info.out-of-stock {
            color: var(--error);
            border: 1px solid var(--error);
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-actions .btn {
            padding: 12px 25px;
            min-width: 120px;
            flex: 1;
        }

        .modal-actions .btn.cancel {
            background: var(--dark);
        }

        .modal-actions .btn.cancel:hover:not(:disabled) {
            background: var(--secondary);
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 3000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }

        .toast.error {
            background: var(--error);
        }

        .toast.show {
            transform: translateX(0);
        }

        @media (max-width: 768px) {
            nav ul { gap: 15px; }
            .products-grid { grid-template-columns: 1fr; }
            .quick-actions { display: none; }
            .section { padding: 60px 20px; }
            .section h2 { font-size: 2.5rem; }
            .modal-content { margin: 20px; padding: 20px; }
            .quantity-selector { gap: 10px; }
            .quantity-selector button { width: 40px; height: 40px; }
            .quantity-selector input { width: 60px; font-size: 1.2rem; }
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
    <div class="progress-bar"></div>
    <div class="particles"></div>

    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="#products">Products</a></li>
            <li><a href="untitled-1.php">Table booking</a></li>
            <li><a href="home.php">About</a></li>
            <li><a href="profile.php">Orders</a></li>
            <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
            <li><a href="admin.php">Admin</a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="quick-actions">
        <button class="quick-btn" title="Login">👤</button>
        <button class="quick-btn" title="Profile">👤</button>
        <button class="quick-btn" title="Reviews">⭐</button>
        <button class="quick-btn" title="Share">📤</button>
    </div>

    <div class="floating-cart" id="floatingCart">
        <span class="cart-badge" id="cartCount" style="display:none">0</span>
    </div>

    <!-- Quantity Modal -->
    <div class="modal" id="quantityModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">×</button>
            <h3>Add to Cart</h3>
            <div class="modal-product-name" id="modalProductName">Product Name</div>
            
            <div class="quantity-selector">
                <button type="button" onclick="changeQty(-1)" id="qtyMinus">-</button>
                <input type="number" id="quantityInput" value="1" min="1" max="99">
                <button type="button" onclick="changeQty(1)" id="qtyPlus">+</button>
            </div>
            
            <div class="stock-info" id="stockInfo"></div>
            
            <div class="modal-actions">
                <button class="btn cancel" onclick="closeModal()">Cancel</button>
                <button class="btn" id="confirmBtn" onclick="confirmQty()">Add to Cart</button>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div class="toast" id="toast"></div>

    <section class="section" id="products">
        <h2>Our Delicious Products</h2>
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): 
                $stock = intval($product['quantity']);
                $stockClass = $stock > 10 ? 'available' : ($stock > 0 ? 'low' : 'out-of-stock');
                $stockText = $stock > 0 ? "{$stock} in stock" : 'Out of stock';
            ?>
            <div class="product-card" data-product-id="<?php echo $product['id']; ?>">
                <div class="product-image">
                    <?php if (!empty($product['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="display:none; align-items:center; justify-content:center; width:100%; height:100%;">
                        🍰
                    </div>
                    <?php else: ?>
                    🍰
                    <?php endif; ?>
                    <div class="stock-badge <?php echo $stockClass; ?>">
                        <?php echo $stockText; ?>
                    </div>
                </div>
                <div class="product-info">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                    <div class="product-price">Rs<?php echo number_format($product['price'], 2); ?></div>
                    <button class="btn add-to-cart" 
                            data-id="<?php echo $product['id']; ?>" 
                            data-name="<?php echo htmlspecialchars($product['name']); ?>" 
                            data-stock="<?php echo $stock; ?>"
                            data-price="<?php echo $product['price']; ?>"
                            <?php echo $stock <= 0 ? 'disabled' : ''; ?>>
                        <?php echo $stock > 0 ? 'Add to Cart' : 'Out of Stock'; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <script>
        // Utility functions
        const api = (action, options = {}) => {
            const url = `cart.php?action=${action}`;
            console.log('API Call:', url, options.body ? JSON.parse(options.body) : 'GET'); // Debug: Log full request
            return fetch(url, options)
                .catch(error => {
                    console.error('Fetch error:', error); // Debug: Log network errors
                    throw error;
                });
        };
        
        const el = sel => document.querySelector(sel);
        const els = sel => document.querySelectorAll(sel);

        let currentProduct = null;
        let isAddingToCart = false;

        // Loading screen
        window.addEventListener('load', () => {
            setTimeout(() => {
                const loading = el('.loading');
                if (loading) loading.classList.add('hidden');
            }, 800);
        });

        // Particles animation
        function createParticles() {
            const wrap = el('.particles');
            if (!wrap) return;
            
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

        // Progress bar
        function updateProgressBar() {
            const scrolled = window.pageYOffset;
            const maxHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = (scrolled / maxHeight) * 100;
            el('.progress-bar').style.width = progress + '%';
        }

        // Scroll animations
        function animateOnScroll() {
            els('.section').forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.8) {
                    section.classList.add('show');
                }
            });
        }

        // Navigation scroll effect
        function updateNav() {
            const nav = el('nav');
            if (nav && window.scrollY > 100) {
                nav.classList.add('scrolled');
            } else if (nav) {
                nav.classList.remove('scrolled');
            }
        }

        // Load initial cart count
        async function loadInitialCartCount() {
            try {
                const response = await api('get_cart');
                const data = await response.json();
                console.log('Initial cart load:', data); // Debug
                if (data.ok && data.cart) {
                    updateCartCount(data.cart);
                } else {
                    console.warn('Initial cart load failed:', data.msg); // Debug
                }
            } catch (error) {
                console.error('Error loading initial cart:', error);
            }
        }

        // Update cart badge
        function updateCartCount(cartData) {
            const count = cartData?.item_count || 0;
            const badge = el('#cartCount');
            if (badge) {
                if (count > 0) {
                    badge.style.display = 'inline-block';
                    badge.textContent = count;
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        // Add to cart function
        async function addToCart(productId, qty) {
            if (isAddingToCart) return false;
            
            try {
                isAddingToCart = true;
                console.log('Adding to cart:', { productId, qty }); // Debug
                
                const response = await api('add_to_cart', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, qty })
                });
                
                const data = await response.json();
                console.log('Add to cart response:', data); // Debug
                
                if (data.ok) {
                    updateCartCount(data.cart);
                    showToast(`Added ${data.product?.name || 'item'} to cart!`, 'success');
                    return { success: true, data };
                } else {
                    console.warn('Add to cart failed:', data.msg); // Debug
                    showToast(data.msg || 'Error adding to cart. Check console for details.', 'error');
                    return { success: false, msg: data.msg };
                }
            } catch (error) {
                console.error('Error adding to cart:', error); // Debug
                showToast('Network error: Could not connect to cart.php. Check server.', 'error');
                return { success: false, msg: 'Network error' };
            } finally {
                isAddingToCart = false;
            }
        }

        // View product details
        function viewProduct(productId) {
            const product = currentProduct || { id: productId, name: 'Product' };
            alert(`Viewing details for ${product.name}\nProduct ID: ${productId}\n\nThis would open a detailed product modal!`);
        }

        // Quick action buttons
        document.addEventListener('DOMContentLoaded', () => {
            els('.quick-btn').forEach((btn, index) => {
                btn.addEventListener('click', () => {
                    const actions = [
                        () => window.location.href = 'login.php',
                        () => window.location.href = 'profile.php',
                        () => alert('Reviews coming soon! ⭐'),
                        () => alert('Share this page! 📤')
                    ];
                    actions[index % actions.length]?.();
                });
            });
        });

        // Modal functions
        function openQuantityModal(productId, productName, stock, price) {
            currentProduct = { id: productId, name: productName, stock, price };
            
            const modal = el('#quantityModal');
            const productNameEl = el('#modalProductName');
            const qtyInput = el('#quantityInput');
            const stockInfo = el('#stockInfo');
            const confirmBtn = el('#confirmBtn');
            const qtyMinus = el('#qtyMinus');
            const qtyPlus = el('#qtyPlus');
            
            // Update modal content
            productNameEl.textContent = productName;
            qtyInput.value = 1;
            qtyInput.max = stock;
            
            // Update stock info
            if (stock > 10) {
                stockInfo.textContent = `📦 ${stock} items available`;
                stockInfo.className = 'stock-info available';
                confirmBtn.disabled = false;
                qtyInput.disabled = false;
                qtyMinus.disabled = false;
                qtyPlus.disabled = false;
            } else if (stock > 0) {
                stockInfo.textContent = `⚠️ Only ${stock} items left!`;
                stockInfo.className = 'stock-info low';
                confirmBtn.disabled = false;
                qtyInput.disabled = false;
                qtyMinus.disabled = false;
                qtyPlus.disabled = false;
            } else {
                stockInfo.textContent = '❌ Out of stock';
                stockInfo.className = 'stock-info out-of-stock';
                confirmBtn.disabled = true;
                qtyInput.disabled = true;
                qtyMinus.disabled = true;
                qtyPlus.disabled = true;
            }
            
            modal.style.display = 'flex';
            qtyInput.focus();
        }

        function closeModal() {
            const modal = el('#quantityModal');
            modal.style.display = 'none';
            currentProduct = null;
            isAddingToCart = false;
            
            // Reset button states
            const confirmBtn = el('#confirmBtn');
            const qtyInput = el('#quantityInput');
            const qtyMinus = el('#qtyMinus');
            const qtyPlus = el('#qtyPlus');
            
            confirmBtn.textContent = 'Add to Cart';
            confirmBtn.disabled = false;
            confirmBtn.classList.remove('loading');
            qtyInput.disabled = false;
            qtyMinus.disabled = false;
            qtyPlus.disabled = false;
            qtyInput.value = 1;
        }

        function changeQty(delta) {
            if (!currentProduct) return;
            
            const input = el('#quantityInput');
            if (input.disabled) return;
            
            let value = parseInt(input.value) || 1;
            value += delta;
            
            if (value < 1) value = 1;
            if (value > currentProduct.stock) {
                showToast(`Only ${currentProduct.stock} items available`, 'error');
                value = currentProduct.stock;
            }
            
            input.value = value;
        }

        async function confirmQty() {
            if (!currentProduct || isAddingToCart) return;
            
            const qty = parseInt(el('#quantityInput').value);
            if (qty < 1 || qty > currentProduct.stock) {
                showToast('Invalid quantity', 'error');
                return;
            }
            
            const confirmBtn = el('#confirmBtn');
            const originalText = confirmBtn.textContent;
            confirmBtn.textContent = 'Adding...';
            confirmBtn.classList.add('loading');
            confirmBtn.disabled = true;
            
            const result = await addToCart(currentProduct.id, qty);
            
            if (result.success) {
                closeModal();
                pulseCart();
                
                // Update the add to cart button
                const addBtn = document.querySelector(`.add-to-cart[data-id="${currentProduct.id}"]`);
                if (addBtn) {
                    const originalBtnText = addBtn.textContent;
                    addBtn.textContent = `Added! ✓`;
                    addBtn.classList.add('added');
                    addBtn.disabled = true;
                    
                    setTimeout(() => {
                        addBtn.textContent = originalBtnText;
                        addBtn.classList.remove('added');
                        addBtn.disabled = currentProduct.stock <= 0;
                    }, 2000);
                }
            } else {
                confirmBtn.textContent = 'Retry';
                setTimeout(() => {
                    confirmBtn.textContent = originalText;
                    confirmBtn.disabled = false;
                    confirmBtn.classList.remove('loading');
                }, 2000);
            }
        }

        // Cart pulse animation
        function pulseCart() {
            const cart = el('#floatingCart');
            if (cart) {
                cart.style.animation = 'none';
                cart.offsetHeight;
                cart.style.animation = 'pulse 0.5s ease';
            }
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = el('#toast');
            toast.textContent = message;
            toast.className = `toast ${type}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', async () => {
            console.log('Product page loaded'); // Debug
            
            // Initialize
            createParticles();
            animateOnScroll();
            loadInitialCartCount();
            
            // Add to cart button listeners
            els('.add-to-cart').forEach(btn => {
                btn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const productId = parseInt(btn.dataset.id);
                    const productName = btn.dataset.name;
                    const stock = parseInt(btn.dataset.stock);
                    const price = parseFloat(btn.dataset.price);
                    
                    if (stock <= 0) {
                        showToast('This product is out of stock', 'error');
                        return;
                    }
                    
                    openQuantityModal(productId, productName, stock, price);
                });
            });
            
            // Product card click (view details)
            els('.product-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    if (e.target.closest('.add-to-cart')) return;
                    const productId = card.dataset.productId;
                    viewProduct(productId);
                });
            });
            
            // Floating cart click
            const floatingCart = el('#floatingCart');
            if (floatingCart) {
                floatingCart.addEventListener('click', () => {
                    window.location.href = 'cart.php';
                });
            }
            
            // Modal backdrop click
            const modal = el('#quantityModal');
            if (modal) {
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
            }
            
            // Keyboard support
            document.addEventListener('keydown', (e) => {
                if (el('#quantityModal').style.display === 'flex') {
                    switch(e.key) {
                        case 'Escape':
                            closeModal();
                            break;
                        case 'Enter':
                            if (!e.target.closest('.quantity-selector input')) {
                                confirmQty();
                            }
                            break;
                        case 'ArrowUp':
                            changeQty(1);
                            e.preventDefault();
                            break;
                        case 'ArrowDown':
                            changeQty(-1);
                            e.preventDefault();
                            break;
                    }
                }
            });
            
            // Scroll listeners
            window.addEventListener('scroll', () => {
                updateProgressBar();
                animateOnScroll();
                updateNav();
            });
        });

        // Dynamic product loading (for AJAX refresh)
        async function loadProducts() {
            try {
                const response = await fetch('?action=list_products');
                const data = await response.json();
                const grid = el('#productsGrid');
                
                if (data.products && data.products.length > 0) {
                    grid.innerHTML = '';
                    data.products.forEach(p => {
                        const stock = parseInt(p.quantity);
                        const stockClass = stock > 10 ? 'available' : (stock > 0 ? 'low' : 'out-of-stock');
                        const stockText = stock > 0 ? `${stock} in stock` : 'Out of stock';
                        
                        const card = document.createElement('div');
                        card.className = 'product-card';
                        card.dataset.productId = p.id;
                        card.innerHTML = `
                            <div class="product-image">
                                ${p.image_path ? 
                                    `<img src="${p.image_path}" alt="${p.name}" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div style="display:none; align-items:center; justify-content:center; width:100%; height:100%;">🍰</div>` : 
                                    '🍰'
                                }
                                <div class="stock-badge ${stockClass}">${stockText}</div>
                            </div>
                            <div class="product-info">
                                <h3>${p.name}</h3>
                                <p>${p.description || 'Delicious treat made with love'}</p>
                                <div class="product-price">Rs${Number(p.price).toFixed(2)}</div>
                                <button class="btn add-to-cart" 
                                        data-id="${p.id}" 
                                        data-name="${p.name}" 
                                        data-stock="${stock}" 
                                        data-price="${p.price}"
                                        ${stock <= 0 ? 'disabled' : ''}>
                                    ${stock > 0 ? 'Add to Cart' : 'Out of Stock'}
                                </button>
                            </div>`;
                        
                        // Add event listeners
                        card.addEventListener('click', (e) => {
                            if (e.target.closest('.add-to-cart')) return;
                            viewProduct(p.id);
                        });
                        
                        const addBtn = card.querySelector('.add-to-cart');
                        addBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            openQuantityModal(p.id, p.name, stock, p.price);
                        });
                        
                        grid.appendChild(card);
                    });
                }
            } catch (error) {
                console.error('Error loading products:', error);
                showToast('Error loading products', 'error');
            }
        }
    </script>
</body>
</html>