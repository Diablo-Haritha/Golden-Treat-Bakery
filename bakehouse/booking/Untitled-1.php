<?php
// DB connection
$host = "localhost";
$user = "root";      // XAMPP default
$pass = "";          // XAMPP default password is empty
$dbname = "golden_treat";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle booking submission via AJAX (JSON fetch)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    $data = json_decode(file_get_contents("php://input"), true);

    if (isset($data["action"]) && $data["action"] === "book_table") {
        $name     = trim(htmlspecialchars($data["name"]));
        $email    = trim(htmlspecialchars($data["email"]));
        $phone    = trim(htmlspecialchars($data["phone"]));
        $date     = $data["date"];
        $time     = $data["time"];
        $guests   = intval($data["guests"]);
        $requests = trim(htmlspecialchars($data["requests"]));

        // Generate next bookingId (BID100X…)
        $res = $conn->query("SELECT bookingId FROM bookings ORDER BY id DESC LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $last = $res->fetch_assoc()["bookingId"];
            $num  = intval(substr($last, 3)) + 1;
            $bookingId = "BID" . str_pad($num, 4, "0", STR_PAD_LEFT);
        } else {
            $bookingId = "BID1001";
        }

        // Insert booking (status defaults to Pending)
        $stmt = $conn->prepare(
            "INSERT INTO bookings (bookingId, customerName, date, time, tableNumber, status) 
             VALUES (?,?,?,?,?,?)"
        );
        $status = "Pending";
        $stmt->bind_param("ssssss", $bookingId, $name, $date, $time, $guests, $status);

        if ($stmt->execute()) {
            echo json_encode(["ok" => true, "msg" => "Booking successful!", "bookingId" => $bookingId]);
        } else {
            echo json_encode(["ok" => false, "msg" => "DB Error: " . $conn->error]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Golden Treat - Table Booking</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>

    .booking-section { max-width:700px; margin:50px auto; padding:40px; background:#fff; border-radius:20px;
                       box-shadow:0 10px 30px rgba(212,175,55,0.2); }
    .booking-section h2 { text-align:center; margin-bottom:30px; color:#8B4513; }
    form { display:flex; flex-direction:column; gap:15px; }
    input, select, textarea { padding:12px; border-radius:8px; border:1px solid #ccc; font-size:1rem; }
    button { padding:12px; background:#D4AF37; border:none; border-radius:8px; color:white; font-size:1.1rem; cursor:pointer; }
    button:hover { background:#b58d2b; }
    .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5);
             justify-content:center; align-items:center; }
    .modal-content { background:#fff; padding:20px; border-radius:15px; text-align:center; max-width:400px; }
     
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
            background: linear-gradient(135deg, #ddbf92ff 0%, #fff8e1 100%);
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
         /* Nav 8: Cookie Crumble */
        .nav8 {
            background: #ffe4b5;
            padding: 20px 40px;
            border-radius: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 20px rgba(139, 69, 19, 0.2);
            height:10px;
        margin-right:50px;
        }

        .nav8 .logo {
            font-size: 28px;
            font-weight: bold;
            color: #8b4513;
            font-family: 'Brush Script MT', cursive;
        }

        .nav8 .logo::before {
            content: '🍪 ';
        }

        .nav8 .menu {
            display: flex;
            gap: 25px;
            list-style: none;
        }

        .nav8 .menu a {
            color: #8b4513;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 50px;
            transition: all 0.3s;
            background: rgba(139, 69, 19, 0);
            font-weight: 600;
        }

        .nav8 .menu a:hover {
            background: #8b4513;
            color: white;
            transform: translateY(-2px);
        }

    
  </style>
</head>
<body>
 <header>
        
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo animated fadeIn">
                    <i class="fas fa-cookie-bite"></i>
                    Golden <span>Treat</span>
                </a>
                <ul class="nav-links"></ul>
                
            
    <!-- Nav 8 -->
        <div class="nav-section">
            
            <nav class="nav8">
                 
                <ul class="menu">
                    <li><a href="../../customer/index.php">Home</a></li>
                    <li><a href="..bakehouse/about.php">About</a></li>
                    
                    <li><a href="#contact">Order</a></li>
                </ul>
            </nav>
        </div></nav>
        </div>
        
    </header>

<section class="hero" id="home">
  <h1>Book Your Table</h1>
  <p>Reserve your spot at Golden Treat Bakery</p>
</section>

<section class="booking-section" id="booking">
  <h2>Table Booking Form</h2>
  <form id="bookingForm">
    <input type="text" id="name" placeholder="Full Name" required>
    <input type="email" id="email" placeholder="Email" required>
    <input type="tel" id="phone" placeholder="Phone Number" required>
    <input type="date" id="date" required>
    <input type="time" id="time" required>
    <select id="guests" required>
      <option value="">Number of Guests</option>
      <?php for($i=1;$i<=20;$i++){ echo "<option value='$i'>$i</option>"; } ?>
    </select>
    <textarea id="requests" rows="4" placeholder="Special Requests (Optional)"></textarea>
    <button type="submit">Book Now</button>
  </form>
</section>

<div class="modal" id="bookingModal">
  <div class="modal-content">
    <h2>Booking Confirmation</h2>
    <p id="modalMsg"></p>
    <button onclick="document.getElementById('bookingModal').style.display='none'">OK</button>
  </div>
</div>

<script>
  const bookingForm = document.getElementById("bookingForm");
  const modal = document.getElementById("bookingModal");
  const modalMsg = document.getElementById("modalMsg");

  bookingForm.addEventListener("submit", async (e) => {
    e.preventDefault();
    const data = {
      action: "book_table",
      name: document.getElementById("name").value.trim(),
      email: document.getElementById("email").value.trim(),
      phone: document.getElementById("phone").value.trim(),
      date: document.getElementById("date").value,
      time: document.getElementById("time").value,
      guests: document.getElementById("guests").value,
      requests: document.getElementById("requests").value.trim()
    };

    const r = await fetch("", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(data)
    });
    const res = await r.json();
    modalMsg.textContent = res.ok ? res.msg + " (ID: " + res.bookingId + ")" : res.msg;
    modal.style.display = "flex";
    if (res.ok) bookingForm.reset();
  });
</script>
</body>
</html>
