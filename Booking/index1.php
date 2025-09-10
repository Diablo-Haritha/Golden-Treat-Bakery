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

// Fetch products (assume quantity column exists: ALTER TABLE products ADD quantity INT DEFAULT 0;)
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

// Fetch s_products (featured, assume no quantity for featured)
$s_products = [];
$sql = "SELECT * FROM s_products";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $s_products[] = $row;
    }
} else {
    // Optional: only show error if table exists
    // die("Error fetching s_products: " . $conn->error);
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
        $id = intval($data['product_id']);
        $qty = intval($data['qty'] ?? 1);

        // Find stock
        $stock = 0;
        foreach ($products as $p) {
            if ($p['id'] == $id) {
                $stock = intval($p['quantity']);
                break;
            }
        }

        $current = $_SESSION['cart'][$id] ?? 0;
        $new_qty = $current + $qty;

        if ($new_qty > $stock) {
            echo json_encode(['ok' => false, 'msg' => 'Out of stock']);
            exit;
        }

        if (!isset($_SESSION['cart'][$id])) $_SESSION['cart'][$id] = 0;
        $_SESSION['cart'][$id] += $qty;
        echo json_encode(['ok' => true, 'cart' => $_SESSION['cart']]);
        exit;
    } elseif ($action == 'set_cart_qty') {
        $data = json_decode(file_get_contents('php://input'), true);
        $id = intval($data['id']);
        $qty = intval($data['qty']);

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
                        'emoji' => '🍰' // or from DB if added
                    ];
                    $total += $line_total;
                    break;
                }
            }
        }
        echo json_encode(['ok' => true, 'items' => $items, 'total' => $total, 'cart' => $_SESSION['cart']]);
        exit;
    } elseif ($action == 'specials') {
        $specials = array_map(function($p) {
            return ['title' => $p['name'], 'details' => $p['description']];
        }, $s_products);
        echo json_encode(['ok' => true, 'specials' => $specials]);
        exit;
    } elseif ($action == 'subscribe') {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'];
        // TODO: Insert into DB newsletter table
        echo json_encode(['ok' => true]);
        exit;
    }
}

// Function to sanitize input
function sanitize($data) {
    return trim(htmlspecialchars($data));
}

// Add normal product
if(isset($_POST['add_product'])){
    $name = sanitize($_POST['name']);
    $price = floatval($_POST['price']);
    $description = sanitize($_POST['description']);
    $image = sanitize($_POST['image']);
    $quantity = intval($_POST['quantity']);

    $stmt = $conn->prepare("INSERT INTO products (name, price, description, image, quantity) VALUES (?, ?, ?, ?, ?)");
    if(!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("sdssi", $name, $price, $description, $image, $quantity);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Product added successfully!'); window.location = '".$_SERVER['PHP_SELF']."';</script>";
}

// Add featured product (no quantity for featured)
if(isset($_POST['add_featured'])){
    $name = sanitize($_POST['name']);
    $price = floatval($_POST['price']);
    $description = sanitize($_POST['description']);
    $image = sanitize($_POST['image']);

    $stmt = $conn->prepare("INSERT INTO s_products (name, price, description, image) VALUES (?, ?, ?, ?)");
    if(!$stmt) die("Prepare failed: " . $conn->error);

    $stmt->bind_param("sdss", $name, $price, $description, $image);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('Featured product added successfully!'); window.location = '".$_SERVER['PHP_SELF']."';</script>";
}

// ---------- PAGE (HTML + CSS + JS) ----------
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat - Premium Bakery</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Righteous&display=swap" rel="stylesheet">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            padding-top: 100px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .modal-content h2 {
            margin-bottom: 20px;
        }

        .modal-content input,
        .modal-content textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 10px;
            border: 1px solid #ccc;
            font-family: inherit;
        }

        .modal-content button {
            padding: 12px 25px;
            border: none;
            background: var(--primary);
            color: #fff;
            border-radius: 10px;
            cursor: pointer;
        }

        .close {
            position: absolute;
            right: 15px;
            top: 10px;
            font-size: 2rem;
            cursor: pointer;
        }

.featured-product-carousel {
    position: relative;
    max-width: 1100px;
    margin: 60px auto;
    overflow: hidden;
    border-radius: 24px;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.featured-wrapper {
    display: flex;
    transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}

.featured-product {
    min-width: 100%;
    display: flex;
    flex-wrap: wrap;
    border-radius: 24px;
    padding: 30px;
    gap: 25px;
    background: linear-gradient(135deg, rgba(255,255,255,0.15), rgba(255,255,255,0.05));
    transition: transform 0.4s ease, box-shadow 0.4s ease;
}

.featured-product:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
}

.featured-image {
    flex: 1 1 420px;
    min-width: 320px;
    height: 340px;
    border-radius: 20px;
    overflow: hidden;
    position: relative;
}

.featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.6s ease;
}

.featured-image:hover img {
    transform: scale(1.08);
}

.featured-info {
    flex: 1 1 420px;
    min-width: 320px;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.featured-info h2 {
    font-size: 2.2rem;
    font-weight: 700;
    margin-bottom: 15px;
    background: linear-gradient(90deg, var(--primary), #ff6f91);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
}

.featured-info p {
    font-size: 1rem;
    line-height: 1.6;
    margin-bottom: 25px;
    color: #444;
}

.featured-price {
    font-size: 2rem;
    font-weight: 800;
    background: linear-gradient(135deg, #ff6f91, var(--primary));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 25px;
}


        .buy-btn {
            padding: 12px 25px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
        }

        /* Carousel buttons */
        .carousel-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2rem;
            background: rgba(0, 0, 0, 0.2);
            color: #fff;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 50%;
            z-index: 10;
        }

        .carousel-btn.prev {
            left: 10px;
        }

        .carousel-btn.next {
            right: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .featured-product {
                flex-direction: column;
                text-align: center;
            }
        }

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

        body {
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

        .cupcake-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            pointer-events: none;
        }

        .cupcake {
            position: absolute;
            font-size: 1.5rem;
            opacity: 0.7;
            animation: fall 5s linear infinite;
        }

        @keyframes fall {
            0% {
                transform: translateY(-100px) rotate(0deg);
                opacity: 0.7;
            }

            100% {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0.2;
            }
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

        .hero {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: var(--gradient-1);
            overflow: hidden;
        }

        .hero-content {
            text-align: center;
            z-index: 2;
            opacity: 0;
            animation: heroFadeIn 2s ease forwards 0.5s;
        }

        .hero h1 {
            font-family: 'Righteous', sans-serif;
            font-size: clamp(3rem, 8vw, 8rem);
            font-weight: 700;
            color: #2C1810;
            margin-bottom: 20px;
            text-shadow: 2px 2px 10px rgba(0, 0, 0, 0.3);
            display: inline-flex;
            align-items: center;
            position: relative;
        }

        .hero h1 .word {
            transition: transform 0.5s ease;
            display: inline-block;
        }

        .hero h1 .welcome-message {
            position: absolute;
            left: 50%;
            transform: translateX(-50%) scale(0);
            opacity: 0;
            color: var(--white);
            font-size: 0.5em;
            transition: all 0.5s ease;
        }

        .hero h1:hover .word--golden {
            transform: translateX(-180px);
        }

        .hero h1:hover .word--treat {
            transform: translateX(100px);
        }

        .hero h1:hover .welcome-message {
            transform: translateX(-50%) scale(1);
            opacity: 1;
        }

        .hero p {
            font-size: 1.2rem;
            color: var(--white);
            margin-bottom: 40px;
            opacity: 0.9;
        }

        @keyframes heroFadeIn {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .btn {
            display: inline-block;
            padding: 15px 40px;
            background: var(--white);
            color: var(--dark);
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 1rem;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
            padding: 20px 59px;

        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: var(--gradient-1);
            transition: left 0.5s ease;
            z-index: -1;
        }

        .btn:hover::before {
            left: 0;
        }

        .btn:hover {
            color: #2C1810;
            font-size: 1.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 30px;
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
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .product-info p {
            color: var(--secondary);
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .product-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 15px;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }

        .service-card {
            background: var(--white);
            padding: 40px 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .service-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 20px;
            display: block;
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
            background: var(--white);
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

        .newsletter {
            background: var(--gradient-2);
            color: var(--white);
            text-align: center;
            padding: 80px 20px;
        }

        .newsletter-form {
            display: flex;
            max-width: 400px;
            margin: 30px auto;
            gap: 10px;
        }

        .newsletter input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
        }

        .newsletter button {
            padding: 15px 30px;
            background: var(--white);
            color: var(--dark);
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        @media (max-width: 768px) {
            nav ul {
                gap: 15px;
            }

            .hero h1 {
                font-size: 3rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .quick-actions {
                display: none;
            }

            .products-grid {
                grid-template-columns: 1fr;
            }
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

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }

        table th,
        table td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: left;
        }

        table th {
            background: var(--light);
        }
    </style>
</head>

<body>
    <!-- Loading screen -->
    <div class="loading">
        <div class="spinner"></div>
    </div>
    <!-- Progress bar -->
    <div class="progress-bar"></div>
    <!-- Animated particles -->
    <div class="particles"></div>


    <!-- Navigation -->
    <nav>
        <ul>
            <li><a href="#home">Home</a></li>
            <li><a href="#products">Products</a></li>
            <li><a href="#services">Services</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#contact">Contact</a></li>
        </ul>
    </nav>

    <!-- Quick actions -->
    <div class="quick-actions">
        <div class="quick-btn" title="Call Us">📞</div>
        <div class="quick-btn" title="Location">📍</div>
        <div class="quick-btn" title="Reviews">⭐</div>
        <div class="quick-btn" title="Share">📤</div>
    </div>

    <!-- Floating cart -->
    <div class="floating-cart" id="floatingCart">
        <span class="cart-badge" id="cartCount" style="display:none">0</span>
    </div>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="cupcake-particles"></div>
        <div class="hero-content">
            <h1>
                <span class="word word--golden">Golden</span>
                <span class="welcome-message">Welcome</span>
                <span class="word word--treat">Treat</span>
            </h1>
            <p>Artisan Bakery • Fresh Daily • Premium Quality</p>
            <div class="action-buttons">
                <a href="#products" class="btn">Explore Menu</a>
                <button class="btn" id="orderBtn">Order Now</button>
                <button class="btn" id="findStoreBtn">Find Store</button>
                <button class="btn" id="specialsBtn">Daily Specials</button>
                 <!-- New Booking button -->
                <a href="Untitled-1.php" class="btn" id="bookingBtn">Book Now</a>

            </div>
        </div>
    </section>


    <!-- s_Products Section -->
    <section class="featured-product-carousel">
        <button class="carousel-btn prev">&lt;</button>
        <div class="featured-wrapper" id="featuredWrapper">
            <?php foreach ($s_products as $product): ?>
            <div class="featured-product">
                <div class="featured-image">
                    <?php if(!empty($product['image'])): ?>
                    <img src="<?= $product['image'] ?>" alt="<?= $product['name'] ?>">
                    <?php else: ?>
                    🍔
                    <?php endif; ?>
                </div>
                <div class="featured-info">
                    <h2>
                        <?= $product['name'] ?>
                    </h2>
                    <p>
                        <?= $product['description'] ?>
                    </p>
                    <div class="featured-price">$
                        <?= $product['price'] ?>
                    </div>
                    <button class="buy-btn">Buy Now</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button class="carousel-btn next">&gt;</button>
    </section>


    <!-- Products Section -->
    <section class="section" id="products">
        <h2>Our Products</h2>
        <div class="products-grid" id="productsGrid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if(!empty($product['image'])): ?>
                    <img src="<?= $product['image'] ?>" alt="<?= $product['name'] ?>"
                        style="width:100%; height:100%; object-fit:cover;">
                    <?php else: ?>
                    🍔
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3>
                        <?= $product['name'] ?>
                    </h3>
                    <p>
                        <?= $product['description'] ?>
                    </p>
                    <div class="product-price">$
                        <?= $product['price'] ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

   <!--Booking --->
 <div class="action-buttons">
    <a href="#products" class="btn">Explore Menu</a>
    <button class="btn" id="orderBtn">Order Now</button>
    <button class="btn" id="findStoreBtn">Find Store</button>
    <button class="btn" id="specialsBtn">Daily Specials</button>
    
</div>

<!-- New Booking button -->
<div class="action-buttons">
    <a href="#products" class="btn">Explore Menu</a>
    <button class="btn" id="orderBtn">Order Now</button>
    <button class="btn" id="findStoreBtn">Find Store</button>
    <button class="btn" id="specialsBtn">Daily Specials</button>
    
</div>


    <!-- Services Section -->
    <section class="section" id="services">
        <h2>Our Services</h2>
        <div class="services-grid">
            <div class="service-card">
                <span class="service-icon">🚚</span>
                <h3>Free Delivery</h3>
                <p>Free delivery on orders over $30 within 5km radius</p>
            </div>
            <div class="service-card">
                <span class="service-icon">👨‍🍳</span>
                <h3>Custom Orders</h3>
                <p>Personalized cakes and catering for special events</p>
            </div>
            <div class="service-card">
                <span class="service-icon">📱</span>
                <h3>Online Ordering</h3>
                <p>Order ahead through our mobile app and skip the line</p>
            </div>
            <div class="service-card">
                <span class="service-icon">🎓</span>
                <h3>Baking Classes</h3>
                <p>Learn from our master bakers in hands-on workshops</p>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter" id="contact">
        <h2>Stay Sweet with Our Newsletter</h2>
        <p>Get exclusive offers, new product alerts, and baking tips</p>
        <div class="newsletter-form">
            <input type="email" id="newsletterEmail" placeholder="Enter your email">
            <button type="submit" id="subscribeBtn">Subscribe</button>
        </div>
    </section>

    <script>
        // Create falling cupcakes for hero section
        function createCupcakes() {
            const cupcakeContainer = document.querySelector('.cupcake-particles');
            const items = ['🍕', '🥐', '🥖']; // list of food icons

            for (let i = 0; i < 200; i++) {
                const cupcake = document.createElement('div');
                cupcake.className = 'cupcake';

                // pick a random emoji from list
                cupcake.textContent = items[Math.floor(Math.random() * items.length)];

                // random position & animation
                cupcake.style.left = Math.random() * 100 + '%';
                cupcake.style.animationDelay = Math.random() * 5 + 's';
                cupcake.style.animationDuration = (Math.random() * 3 + 4) + 's';

                cupcakeContainer.appendChild(cupcake);
            }
        } window.addEventListener('DOMContentLoaded', () => {
            createCupcakes();
        });




        // ---------- UTIL ----------
        const api = (a, opt) => fetch(`?action=${a}`, opt);
        const el = sel => document.querySelector(sel);

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


        // Scroll progress
        function updateProgressBar() {
            const scrolled = window.pageYOffset;
            const maxHeight = document.documentElement.scrollHeight - window.innerHeight;
            const progress = (scrolled / maxHeight) * 100;
            el('.progress-bar').style.width = progress + '%';
        }

        // Scroll animations
        function animateOnScroll() {
            document.querySelectorAll('.section').forEach(section => {
                const rect = section.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.8) section.classList.add('show');
            });
        }

        // Nav scroll
        function updateNav() {
            const nav = document.querySelector('nav');
            if (window.scrollY > 100) nav.classList.add('scrolled'); else nav.classList.remove('scrolled');
        }

        // Smooth scroll
        document.querySelectorAll('nav a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const target = document.querySelector(a.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Render products from DB
        async function loadProducts() {
            const r = await api('list_products');
            const data = await r.json();
            const grid = el('#productsGrid');
            grid.innerHTML = '';
            (data.products || []).forEach(p => {
                const card = document.createElement('div');
                card.className = 'product-card';
                card.innerHTML = `
                    <div class="product-image">${p.emoji || '🍰'}</div>
                    <div class="product-info">
                        <h3>${p.name}</h3>
                        <p>${p.description}</p>
                        <div class="product-price">$${Number(p.price).toFixed(2)}</div>
                        <button class="btn" data-id="${p.id}">${p.button_label || 'Add to Cart'}</button>
                    </div>`;
                card.addEventListener('click', () => viewProduct(p.slug || p.id));
                card.querySelector('.btn').addEventListener('click', async (e) => {
                    e.stopPropagation();
                    await addToCart(p.id);
                    const btn = e.currentTarget;
                    const old = btn.textContent;
                    btn.textContent = 'Added! ✓';
                    btn.style.background = '#4CAF50';
                    btn.style.color = 'white';
                    setTimeout(() => {
                        btn.textContent = old;
                        btn.style.background = '';
                        btn.style.color = '';
                    }, 1200);
                    pulseCart();
                });
                grid.appendChild(card);
            });
        }

        // Cart
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
            if (data.ok) updateCartCount(data.cart);
        }

        async function showCart() {
            const r = await api('get_cart');
            const data = await r.json();
            if (!data.ok) return alert('Cart error');
            if (!data.items.length) return alert('Your cart is empty.');
            const lines = data.items.map(i => `${i.emoji || '•'} ${i.name} x${i.qty} — $${i.line_total.toFixed(2)}`);
            lines.push(`\nTotal: $${data.total.toFixed(2)}`);
            alert(lines.join('\n'));
        }

        function updateCartCount(cartObj) {
            let count = 0;
            Object.values(cartObj || {}).forEach(n => count += Number(n || 0));
            const b = el('#cartCount');
            if (count > 0) { b.style.display = 'inline-block'; b.textContent = count; } else { b.style.display = 'none'; }
        }

        // Product interactions / hero buttons
        function viewProduct(productType) { alert(`Viewing ${productType} details - This would open a product modal!`); }
        el('#orderBtn').addEventListener('click', () => alert('Opening order system - This would redirect to ordering platform!'));
        el('#findStoreBtn').addEventListener('click', () => alert('Opening store locator - This would show nearby stores!'));

        // Specials
        el('#specialsBtn').addEventListener('click', async () => {
            const r = await api('specials');
            const data = await r.json();
            if (data.ok && data.specials.length) {
                alert(data.specials.map(s => `• ${s.title}\n  ${s.details}`).join('\n\n'));
            } else {
                alert('No specials today — come back tomorrow!');
            }
        });

        // Floating cart
        el('#floatingCart').addEventListener('click', showCart);

        // Quick action buttons
        document.querySelectorAll('.quick-btn').forEach((btn, index) => {
            btn.addEventListener('click', () => {
                const actions = ['Calling +1-234-567-8900', 'Opening maps to our location', 'Showing customer reviews', 'Opening share menu'];
                alert(actions[index]);
            });
        });

        // Newsletter
        el('#subscribeBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            const email = el('#newsletterEmail').value.trim();
            if (!email) return alert('Please enter your email address');
            const r = await api('subscribe', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            const data = await r.json();
            if (data.ok) {
                alert(`Thank you for subscribing with ${email}!`);
                el('#newsletterEmail').value = '';
            } else {
                alert(data.msg || 'Subscription failed');
            }
        });

        // Events
        window.addEventListener('scroll', () => { updateProgressBar(); animateOnScroll(); updateNav(); });

        // Init
        document.addEventListener('DOMContentLoaded', async () => {
            createParticles();
            animateOnScroll();
            await loadProducts();
            // set initial cart count
            const r = await api('get_cart'); const d = await r.json(); if (d.ok) updateCartCount(d.cart || {});
        });




        // Product interactions / hero buttons
        function viewProduct(productType) { alert(`Viewing ${productType} details - This would open a product modal!`); }
        // el('#orderBtn').addEventListener('click', () => alert('Opening order system - This would redirect to ordering platform!'));
        // el('#findStoreBtn').addEventListener('click', () => alert('Opening store locator - This would show nearby stores!'));

        // Specials
        // el('#specialsBtn').addEventListener('click', async () => {
        //     const r = await api('specials');
        //     const data = await r.json();
        //     if (data.ok && data.specials.length) {
        //         alert(data.specials.map(s => `• ${s.title}\n  ${s.details}`).join('\n\n'));
        //     } else {
        //         alert('No specials today — come back tomorrow!');
        //     }
        // });

        // Floating cart
        el('#floatingCart').addEventListener('click', showCart);

        // Quick action buttons
        document.querySelectorAll('.quick-btn').forEach((btn, index) => {
            btn.addEventListener('click', () => {
                const actions = ['Calling +1-234-567-8900', 'Opening maps to our location', 'Showing customer reviews', 'Opening share menu'];
                alert(actions[index]);
            });
        });

        // Newsletter
        el('#subscribeBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            const email = el('#newsletterEmail').value.trim();
            if (!email) return alert('Please enter your email address');
            const r = await api('subscribe', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            const data = await r.json();
            if (data.ok) {
                alert(`Thank you for subscribing with ${email}!`);
                el('#newsletterEmail').value = '';
            } else {
                alert(data.msg || 'Subscription failed');
            }
        });
//Booking 
el('#bookingBtn').addEventListener('click', () => {
    alert('Redirecting to booking page or opening booking form!');
    // You can replace the alert with a redirect, e.g.,
    // window.location.href = '/booking.php';
});

        // Events
        window.addEventListener('scroll', () => { updateProgressBar(); animateOnScroll(); updateNav(); });

        // Init
        document.addEventListener('DOMContentLoaded', async () => {
            createParticles();
            animateOnScroll();
            // await loadProducts(); // Optional, since PHP renders, but JS can reload if needed
            const r = await api('get_cart'); const d = await r.json(); if (d.ok) updateCartCount(d.cart || {});
        });



        const wrapper = document.getElementById('featuredWrapper');
        const products_car = document.querySelectorAll('.featured-product');
        const prevBtn = document.querySelector('.carousel-btn.prev');
        const nextBtn = document.querySelector('.carousel-btn.next');

        let index = 0;

        function updateCarousel() {
            wrapper.style.transform = `translateX(-${index * 100}%)`;
        }

        nextBtn.addEventListener('click', () => {
            index = (index + 1) % products_car.length;
            updateCarousel();
        });

        prevBtn.addEventListener('click', () => {
            index = (index - 1 + products_car.length) % products_car.length;
            updateCarousel();
        });

    </script>
</body>

</html>