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
  <link href="button.css" rel="stylesheet">
  <style>
   
    :root {
      --bg: #FFE8B7;
      --primary: #D4AF37;
      --secondary: #8B4513;
      --accent: #FFE5B4;
      --dark: #2C1810;
      --light: #FFF8F0;
      --white: #FFFFFF;
      --gradient-1: linear-gradient(135deg, #321a00ff, #FFE5B4);
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
      color: #2C1810;
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

    .floating-cart::before {}

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
      transition: opacity 0.005s ease;
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

  

    .loginbtn {
      position: fixed;
      top: 10px;
      right: 30px;





      z-index: 1000;

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
      <li><a href="../Customer/index.php">Products</a></li>
      <li><a href="untitled-1.php">Table booking</a></li>
      <li><a href="about.php">About</a></li>
      <li><a href="profile.php">Contact</a></li>
    </ul>
  </nav>

  <!-- Quick actions -->
  <div class="quick-actions">
    <div class="quick-btn" title="Login">Login</div>
    <div class="quick-btn" title="Profile">👤</div>
    <div class="quick-btn" title="Reviews">⭐</div>
    <div class="quick-btn" title="Share">📤</div>


  </div>

  <!-- Floating cart -->
  <div class="floating-cart" id="floatingCart">
   

  </div>
  <!-- Floating cart -->
  <div class="loginbtn" id="floatingCart">

    <button onclick="window.location.href='./login.php';" class="cookie-crumbs">Login</button>

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
        <button onclick="window.location.href='../Customer/index.php';" class="btn">Order Now</button>
        <button onclick="window.location.href='../Customer/index.php';" class="btn">Find Store</button>
        <button onclick="window.location.href='../customer/index.php';" class="btn">Daily Specials</button>
      </div>
    </div>
  </section>


  <!-- Services Section -->




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

   

    // Quick action buttons
    document.querySelectorAll('.quick-btn').forEach((btn, index) => {
      btn.addEventListener('click', () => {
        const paths = ['login.php', 'profile.php', 'reviews.php', 'share.php'];
        window.location.href = paths[index];
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

    // Quick action buttons
    document.querySelectorAll('.quick-btn').forEach((btn, index) => {
      btn.addEventListener('click', () => {
        const paths = ['login.php', 'profile.php', 'reviews.php', 'share.php'];
        window.location.href = paths[index];
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