<?php
session_start();
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

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

// Handle actions for JS API
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action == 'list_products') {
        echo json_encode(['products' => $products]);
        exit;
    } elseif ($action == 'add_to_cart') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = isset($data['product_id']) ? intval($data['product_id']) : 0;
        $qty = isset($data['qty']) ? intval($data['qty']) : 1;

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid product ID']);
            exit;
        }

        // Find stock
        $stock = 0;
        foreach ($products as $p) {
            if ($p['id'] == $id) {
                $stock = intval($p['quantity']);
                break;
            }
        }

        $current = isset($_SESSION['cart'][$id]) ? $_SESSION['cart'][$id] : 0;
        $new_qty = $current + $qty;

        if ($new_qty > $stock) {
            echo json_encode(['ok' => false, 'msg' => 'Out of stock']);
            exit;
        }

        $_SESSION['cart'][$id] = $new_qty;
        echo json_encode(['ok' => true, 'cart' => $_SESSION['cart']]);
        exit;
    } elseif ($action == 'set_cart_qty') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = isset($data['id']) ? intval($data['id']) : 0;
        $qty = isset($data['qty']) ? intval($data['qty']) : 0;

        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid product ID']);
            exit;
        }

        // Find stock
        $stock = 0;
        foreach ($products as $p) {
            if ($p['id'] == $id) {
                $stock = intval($p['quantity']);
                break;
            }
        }

        if ($qty > $stock) {
            echo json_encode(['ok' => false, 'msg' => 'Out of stock']);
            exit;
        }

        if ($qty <= 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
        echo json_encode(['ok' => true, 'cart' => $_SESSION['cart']]);
        exit;
    } elseif ($action == 'get_cart') {
        $items = [];
        $total = 0;
        foreach ($_SESSION['cart'] as $id => $qty) {
            foreach ($products as $p) {
                if ($p['id'] == $id) {
                    $line_total = $p['price'] * $qty;
                    $items[] = [
                        'id' => $id,
                        'name' => $p['name'],
                        'qty' => $qty,
                        'line_total' => $line_total,
                        'emoji' => '🍰'
                    ];
                    $total += $line_total;
                    break;
                }
            }
        }
        echo json_encode(['ok' => true, 'items' => $items, 'total' => $total, 'cart' => $_SESSION['cart']]);
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
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&family=Righteous&display=swap"
        rel="stylesheet">
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

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(180deg);
            }
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
        }

        .btn:hover {
            background: var(--secondary);
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
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
        }

        @keyframes pulse {
            0% {
                box-shadow: var(--shadow);
            }

            50% {
                box-shadow: var(--shadow-hover);
            }

            100% {
                box-shadow: var(--shadow);
            }
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
            background: #2C1810;
            color:#D4AF37;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.2rem;
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
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            nav ul {
                gap: 15px;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                display: none;
            }

            .section {
                padding: 60px 20px;

                background:#D4AF37;
                
            }

            .section h2 {
                font-size: 2.5rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
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
        }

        .modal-content {
            background: var(--white);
            padding: 20px;
            border-radius: 15px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-content h3 {
            font-family: 'Dancing Script', cursive;
            font-size: 1.8rem;
            color: var(--secondary);
            margin-bottom: 20px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .quantity-selector button {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quantity-selector button:hover {
            background: var(--secondary);
            transform: scale(1.1);
        }

        .quantity-selector input {
            width: 60px;
            text-align: center;
            font-family: 'Poppins', sans-serif;
            font-size: 1.2rem;
            border: 1px solid var(--accent);
            border-radius: 5px;
            padding: 5px;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .modal-actions .btn {
            padding: 10px 20px;
        }

        .modal-actions .btn.cancel {
            background: var(--dark);
        }
        /* From Uiverse.io by adamgiebl */ 
.buttonview {
  position: relative;
  display: inline-block;
  margin: 15px;
  padding: 15px 30px;
  text-align: center;
  font-size: 18px;
  letter-spacing: 1px;
  text-decoration: none;
  color: #725AC1;
  background: transparent;
  cursor: pointer;
  transition: ease-out 0.5s;
  border: 2px solid #725AC1;
  border-radius: 10px;
  box-shadow: inset 0 0 0 0 #725AC1;
}

.buttonview:hover {
  color: white;
  box-shadow: inset 0 -100px 0 0 #725AC1;
}

.buttonview:active {
  transform: scale(0.9);
}
    </style>
</head>

<body>

    <div class="progress-bar"></div>
    <div class="particles"></div>

   
       <?php include 'nav_bar.html' ?>
    



    <div class="floating-cart" id="floatingCart">
        <span class="cart-badge" id="cartCount" style="display:none">0</span>
    </div>

    <div class="modal" id="quantityModal">
        <div class="modal-content">
            <h3>Select Quantity</h3>
            <div class="quantity-selector">
                <button onclick="changeQty(-1)">-</button>
                <input type="number" id="quantityInput" value="1" min="1">
                <button onclick="changeQty(1)">+</button>
            </div>
            <div class="modal-actions">
                <button class="btn cancel" onclick="closeModal()">Cancel</button>
                <button class="btn" onclick="confirmQty()">Add to Cart</button>
            </div>
        </div>
    </div>

    <section class="section" id="products">
        <h2>Our Products</h2>
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if (!empty($product['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>"
                        alt="<?php echo htmlspecialchars($product['name']); ?>">
                    <?php else: ?>
                    🍰
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3>
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h3>
                    <p>
                        <?php echo htmlspecialchars($product['description']); ?>
                    </p>
                    <div class="product-price">Rs
                        <?php echo number_format($product['price'], 2); ?>
                    </div>
                    <button class="buttonview" data-id="<?php echo $product['id']; ?>">Add to Cart</button>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <script>
        const api = (a, opt) => fetch(`?action=${a}`, opt);
        const el = sel => document.querySelector(sel);

        window.addEventListener('load', () => {
            setTimeout(() => el('.loading').classList.add('hidden'), 800);
        });

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

        function updateProgressBar() {
            const scrolled = window.pageYOffset;
            const maxHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = (scrolled / maxHeight) * 100;
            el('.progress-bar').style.width = progress + '%';
        }

        function animateOnScroll() {
            document.querySelectorAll('.section').forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.8) section.classList.add('show');
            });
        }

        function updateNav() {
            const nav = document.querySelector('nav');
            if (window.scrollY > 100) nav.classList.add('scrolled'); else nav.classList.remove('scrolled');
        }

        document.querySelectorAll('nav a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const target = document.querySelector(a.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        async function loadProducts() {
            const r = await api('list_products');
            const data = await r.json();
            const grid = el('#productsGrid');
            grid.innerHTML = '';
            (data.products || []).forEach(p => {
                const card = document.createElement('div');
                card.className = 'product-card';
                card.innerHTML = `
                    <div class="product-image">${p.image_path ? `<img src="${p.image_path}" alt="${p.name}">` : '🍰'}</div>
                    <div class="product-info">
                        <h3>${p.name}</h3>
                        <p>${p.description}</p>
                        <div class="product-price">Rs.${Number(p.price).toFixed(2)}</div>
                        <button class="btn add-to-cart" data-id="${p.id}">Add to Cart</button>
                    </div>`;
                card.addEventListener('click', () => viewProduct(p.id));
                const addBtn = card.querySelector('.add-to-cart');
                addBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    openQuantityModal(p.id);
                });
                grid.appendChild(card);
            });
        }

        function pulseCart() {
            const cart = el('#floatingCart');
            cart.style.animation = 'none'; cart.offsetHeight; cart.style.animation = 'pulse .5s ease';
        }

        async function addToCart(id, qty = 1) {
            const r = await api('add_to_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: id, qty })
            });
            const data = await r.json();
            if (data.ok) {
                updateCartCount(data.cart);
                return true;
            } else {
                alert(data.msg || 'Error adding to cart');
                return false;
            }
        }

        function updateCartCount(cartObj) {
            let count = 0;
            Object.values(cartObj || {}).forEach(n => count += Number(n || 0));
            const b = el('#cartCount');
            if (count > 0) { b.style.display = 'inline-block'; b.textContent = count; } else { b.style.display = 'none'; }
        }

        function viewProduct(productId) { alert(`Viewing product ID ${productId} details - This would open a product modal!`); }

        document.querySelectorAll('.quick-btn').forEach((btn, index) => {
            btn.addEventListener('click', () => {
                const paths = ['login.php', 'profile.php', 'reviews.php', 'share.php'];
                window.location.href = paths[index];
            });
        });
        let currentProductId = null;

        function openQuantityModal(productId) {
            currentProductId = productId;
            el('#quantityModal').style.display = 'flex';
            el('#quantityInput').value = 1;
        }

        function closeModal() {
            el('#quantityModal').style.display = 'none';
            currentProductId = null;
        }

        function changeQty(delta) {
            const input = el('#quantityInput');
            let value = parseInt(input.value) + delta;
            if (value < 1) value = 1;
            input.value = value;
        }

        async function confirmQty() {
            const qty = parseInt(el('#quantityInput').value);
            if (currentProductId && qty > 0) {
                const success = await addToCart(currentProductId, qty);
                if (success) {
                    closeModal();
                    pulseCart();
                    const btn = document.querySelector(`.add-to-cart[data-id="${currentProductId}"]`);
                    if (btn) {
                        const oldText = btn.textContent;
                        btn.textContent = 'Added! ✓';
                        btn.style.background = '#4CAF50';
                        setTimeout(() => {
                            btn.textContent = oldText;
                            btn.style.background = '';
                        }, 1200);
                    }
                }
            }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            createParticles();
            animateOnScroll();
            await loadProducts();
            const r = await api('get_cart');
            const d = await r.json();
            if (d.ok) updateCartCount(d.cart || {});
            window.addEventListener('scroll', () => { updateProgressBar(); animateOnScroll(); updateNav(); });
            el('#floatingCart').addEventListener('click', () => {
                window.location.href = 'cart.php';
            });
        });
    </script>
</body>

</html>