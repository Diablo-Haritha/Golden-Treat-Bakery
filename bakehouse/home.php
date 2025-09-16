<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bakehouse Premium - Home</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
<style>
:root {
  --bg: #fff4e1;
  --card: #f3e2d8;
  --ink: #8b5b29;
  --ink-soft: #c69c6d;
  --btn: #d19a6d;
  --btn-hover-dark: #a67143;
  --line: #f5d19d;
  --chip: #ffd1dc;
  --focus: #c37960;
  --shadow: 0 8px 22px rgba(0,0,0,.08);
  --radius: 16px;
}

body {
  margin: 0;
  padding: 0;
  font-family: 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, var(--bg) 0%, #fdf5e6 100%);
  min-height: 100vh;
  color: var(--ink);
}

.navbar {
  background: var(--card);
  padding: 15px 30px;
  box-shadow: var(--shadow);
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 1000;
}

.navbar-logo {
  font-size: 24px;
  font-weight: bold;
  color: var(--ink);
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 10px;
}

.navbar-logo i {
  color: var(--btn);
}

.navbar-links {
  display: flex;
  gap: 30px;
  align-items: center;
}

.navbar-links a {
  text-decoration: none;
  color: var(--ink-soft);
  font-weight: bold;
  font-size: 16px;
  transition: color 0.3s;
  position: relative;
}

.navbar-links a.active {
  color: var(--ink);
}

.navbar-links a::after {
  content: '';
  position: absolute;
  width: 0;
  height: 2px;
  bottom: -5px;
  left: 0;
  background: var(--btn);
  transition: width 0.3s;
}

.navbar-links a:hover::after, .navbar-links a.active::after {
  width: 100%;
}

.navbar-links a:hover {
  color: var(--ink);
}

.navbar-buttons {
  display: flex;
  gap: 20px;
  align-items: center;
}

.nav-btn {
  background: var(--btn);
  color: white;
  padding: 10px 20px;
  border-radius: var(--radius);
  border: none;
  cursor: pointer;
  font-size: 14px;
  font-weight: bold;
  transition: background 0.3s;
}

.nav-btn:hover {
  background: var(--btn-hover-dark);
}

.profile-icon, .cart-btn {
  color: var(--ink-soft);
  font-size: 24px;
  cursor: pointer;
  transition: color 0.3s;
  position: relative;
  padding: 10px;
}

.profile-icon:hover, .cart-btn:hover {
  color: var(--btn);
}

.cart-btn.active {
  color: var(--ink);
}

.cart-btn .cart-count {
  position: absolute;
  top: 0;
  right: 0;
  background: var(--chip);
  color: var(--ink);
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 12px;
}

.hero {
  height: 80vh;
  background: url('https://images.unsplash.com/photo-1556741533-6e6a62bd8b49?auto=format&fit=crop&w=1920&q=80') center/cover no-repeat;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  color: white;
  text-shadow: 0 2px 6px rgba(0,0,0,0.5);
  padding: 20px;
}

.hero h1 {
  font-size: 48px;
  margin-bottom: 20px;
}

.hero p {
  font-size: 20px;
  max-width: 600px;
  margin-bottom: 30px;
}

.hero-buttons {
  display: flex;
  gap: 20px;
  justify-content: center;
  flex-wrap: wrap;
}

.hero-btn, .customize-btn {
  background: var(--btn);
  color: white;
  padding: 12px 24px;
  border-radius: var(--radius);
  border: none;
  font-size: 16px;
  font-weight: bold;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.3s;
  min-width: 180px;
  text-align: center;
}

.hero-btn:hover, .customize-btn:hover {
  background: var(--btn-hover-dark);
}

.featured {
  padding: 50px 20px;
  text-align: center;
}

.featured h2 {
  font-size: 32px;
  color: var(--ink);
  margin-bottom: 30px;
}

.product-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.product-card {
  background: var(--card);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 20px;
  text-align: center;
  transition: transform 0.3s;
}

.product-card:hover {
  transform: scale(1.05);
}

.product-card img {
  width: 100%;
  height: 200px;
  object-fit: cover;
  border-radius: var(--radius);
  margin-bottom: 15px;
}

.product-card h3 {
  font-size: 20px;
  color: var(--ink);
  margin-bottom: 10px;
}

.product-card p {
  font-size: 16px;
  color: var(--ink-soft);
}

.footer {
  background: var(--card);
  padding: 20px;
  text-align: center;
  color: var(--ink-soft);
  font-size: 14px;
  margin-top: 50px;
}

@media (max-width: 768px) {
  .navbar {
    flex-direction: column;
    gap: 10px;
  }
  .navbar-links {
    flex-direction: column;
    gap: 10px;
  }
  .navbar-buttons {
    flex-direction: column;
    gap: 10px;
  }
  .hero h1 {
    font-size: 32px;
  }
  .hero p {
    font-size: 16px;
  }
  .hero-buttons {
    flex-direction: column;
    gap: 10px;
  }
  .hero-btn, .customize-btn {
    padding: 10px 20px;
    font-size: 14px;
    min-width: 160px;
  }
}
</style>
</head>
<body>
  <nav class="navbar">
    <a href="#" class="navbar-logo"><i class="fas fa-birthday-cake"></i>Golden Treat</a>
    <div class="navbar-links">
      <a href="product.html">Products</a>
      <a href="#orders">Orders</a>
      <a href="booking.html">Table Booking</a>
      <a href="aboutus.html">About Us</a>
    </div>
    <div class="navbar-buttons">
      <button class="nav-btn" onclick="window.location.href='login.php'">Login</button>
      <a href="#" class="profile-icon" onclick="checkProfileAccess()"><i class="fas fa-user-circle"></i></a>
      <a href="cart.html" class="cart-btn"><i class="fas fa-shopping-cart"></i><span class="cart-count" id="cart-count">0</span></a>
    </div>
  </nav>

  <section class="hero">
    <h1>Indulge in Sweet Delights</h1>
    <p>Discover freshly baked goods crafted with love at Bakehouse Premium. From cakes to pastries, satisfy your cravings!</p>
    <div class="hero-buttons">
      <a href="product.html" class="hero-btn">Order Now</a>
      <a href="product.html?customize=cake" class="customize-btn">Customize Cake</a>
      <a href="product.html?customize=bun-pizza" class="customize-btn">Customize Buns & Pizza</a>
    </div>
  </section>

  <section class="featured">
    <h2>Our Signature Treats</h2>
    <div class="product-grid">
      <div class="product-card">
        <img src="https://images.unsplash.com/photo-1578985545061-5e7a5e8e740c?auto=format&fit=crop&w=300&q=80" alt="Chocolate Cake">
        <h3>Chocolate Bliss Cake</h3>
        <p>Rich, moist chocolate cake with a creamy ganache topping.</p>
      </div>
      <div class="product-card">
        <img src="https://images.unsplash.com/photo-1559622214-f8a9850965d6?auto=format&fit=crop&w=300&q=80" alt="Croissant">
        <h3>Butter Croissant</h3>
        <p>Flaky, golden croissants baked fresh daily.</p>
      </div>
      <div class="product-card">
        <img src="https://images.unsplash.com/photo-1550617931-eb92628b2d82?auto=format&fit=crop&w=300&q=80" alt="Cupcakes">
        <h3>Assorted Cupcakes</h3>
        <p>Colorful cupcakes with a variety of flavors to choose from.</p>
      </div>
    </div>
  </section>

  <footer class="footer">
    <p>&copy; 2025 Bakehouse Premium. All rights reserved.</p>
  </footer>

  <script>
  function checkProfileAccess() {
    <?php if (isset($_SESSION['user_id'])) { ?>
      window.location.href = 'profile.php';
    <?php } else { ?>
      alert('Please log in to access your profile.');
      window.location.href = 'login.php';
    <?php } ?>
  }

  document.addEventListener('DOMContentLoaded', function() {
    const cart = JSON.parse(localStorage.getItem('cart')) || [];
    document.getElementById('cart-count').textContent = cart.length;
  });
  </script>
</body>
</html>