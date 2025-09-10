<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Golden Treat Bakery - Premium Bakery & Confectionery</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    /* Professional color palette with sophisticated tones */
   :root {
  --sand-light: #FFF8F0; /* from --light */
  --sand-base: #FFE8B7;  /* from --bg */
  --sand-dark: #D4AF37;  /* from --primary */
  --sand-dark2: #8B4513; /* from --secondary */
  
  --button-bg: #D4AF37;       /* from --primary */
  --button-hover-bg: #B8942A; /* adjusted darker gold */
  
  --form-bg: #FFE5B4;         /* from --accent */
  --special-offer-bg: #FFE8B7;/* from --bg */
  
  --golden: #D4AF37;          /* from --primary */
  --golden-dark: #A68A2B;     /* adjusted dark gold */
  
  --shadow-soft: rgba(212, 175, 55, 0.15);  /* soft gold shadow */
  --shadow-medium: rgba(212, 175, 55, 0.25);
  --shadow-deep: rgba(212, 175, 55, 0.4);
  
  --white: #FFFFFF;           /* from --white */
  --text-primary: #2C1810;    /* from --dark */
  --text-secondary: #5C4A36;  /* softened version */
  
  --gradient-1: linear-gradient(135deg, #D4AF37, #FFE5B4);
  --gradient-2: linear-gradient(135deg, #8B4513, #D2691E);
}


    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      font-family: 'Poppins', sans-serif;
      background-color: var(--sand-light);
      color: var(--text-primary);
      line-height: 1.7;
      overflow-x: hidden;
    }

    /* Hero Section - Smaller and More Elegant */
    .hero-section {
      background: linear-gradient(rgba(0, 0, 0, 0.3), rgba(0, 0, 0, 0.5)), url('https://cdn.pixabay.com/photo/2016/11/29/04/54/bread-1867456_1280.jpg');
      background-size: cover;
      background-position: center;
      height: 50vh; /* Reduced height */
      min-height: 350px; /* Reduced minimum height */
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: var(--white);
      position: relative;
      overflow: hidden;
    }

    .hero-content {
      max-width: 700px;
      padding: 1.5rem;
      background: rgba(255, 255, 255, 0.05);
      border: 2px solid rgba(212, 175, 55, 0.3);
      border-radius: 15px;
      backdrop-filter: blur(3px);
      animation: fadeInUp 0.6s ease;
    }

    .hero-section h1 {
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 3rem); /* Slightly smaller but bold */
      font-weight: 700;
      text-shadow: 1px 1px 10px rgba(0, 0, 0, 0.7);
      margin-bottom: 0.8rem;
      letter-spacing: 0.5px;
      line-height: 1.2;
    }

    .hero-section p {
      font-size: 1.1rem;
      font-weight: 300;
      margin-bottom: 1.5rem;
      opacity: 0.9;
      line-height: 1.5;
    }

    .cta-button {
      display: inline-block;
      background: linear-gradient(135deg, var(--golden), var(--golden-dark));
      color: var(--white);
      padding: 0.8rem 2rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 1rem;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .cta-button:hover {
      background: linear-gradient(135deg, var(--golden-dark), var(--golden));
      transform: translateY(-2px) scale(1.02);
      box-shadow: 0 6px 20px rgba(212, 175, 55, 0.6);
    }

    /* Main Header */
    .main-header {
      background-color: var(--white);
      padding: 1rem 0;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 20px var(--shadow-medium);
      border-bottom: 1px solid var(--sand-base);
    }

    .header-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      color: var(--golden);
      font-weight: 700;
      text-decoration: none;
    }

    /* Navigation Bar */
    .navigation-bar {
      background-color: var(--sand-base);
      padding: 0.5rem 0;
      box-shadow: 0 2px 10px var(--shadow-soft);
    }

    .nav-container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 2rem;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .button-bar {
      display: flex;
      gap: 0.5rem;
      justify-content: center;
    }

    button.tab-button {
      background-color: var(--white);
      border: 2px solid var(--sand-dark);
      border-radius: 25px;
      padding: 0.7rem 1.8rem;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 0.95rem;
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px var(--shadow-soft);
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }

    button.tab-button:hover {
      background-color: var(--sand-dark);
      color: var(--white);
      border-color: var(--golden);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px var(--shadow-medium);
    }

    button.tab-button.active {
      background-color: var(--golden);
      color: var(--white);
      border-color: var(--golden);
      box-shadow: 0 3px 10px rgba(212, 175, 55, 0.3);
    }

    /* Cart Container */
    .cart-container {
      position: fixed;
      top: 20px;
      right: 20px;
      background-color: var(--golden);
      border-radius: 50px;
      padding: 0.8rem 1.2rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      box-shadow: 0 4px 15px var(--shadow-medium);
      cursor: pointer;
      color: var(--white);
      font-weight: 600;
      z-index: 120;
      transition: all 0.3s ease;
    }

    .cart-container:hover {
      background-color: var(--golden-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px var(--shadow-deep);
    }

    .cart-icon {
      width: 24px;
      height: 24px;
      fill: var(--white);
    }

    .cart-count {
      background-color: var(--sand-dark2);
      color: var(--white);
      font-size: 0.8rem;
      font-weight: 700;
      padding: 0.2rem 0.6rem;
      border-radius: 15px;
      min-width: 20px;
      text-align: center;
    }

    /* Main Content */
    main {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2rem 2rem;
      transition: margin-right 0.3s ease;
    }

    main.shifted {
      margin-right: 360px;
    }

    /* Section Titles */
    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      text-align: center;
      margin-bottom: 2rem;
      color: var(--text-primary);
      position: relative;
    }

    .section-title::after {
      content: '';
      display: block;
      width: 80px;
      height: 3px;
      background-color: var(--golden);
      margin: 1rem auto 0;
      border-radius: 2px;
    }

    /* Form Container */
    .form-container {
      background-color: var(--white);
      padding: 2.5rem;
      border-radius: 20px;
      box-shadow: 0 8px 25px var(--shadow-medium);
      margin: 2rem 0;
      display: none;
      border: 1px solid var(--sand-base);
      animation: fadeInUp 0.6s ease;
    }

    .form-container.active {
      display: block;
    }

    .form-container h2 {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      margin-bottom: 1.5rem;
      color: var(--text-primary);
      text-align: center;
    }

    form label {
      display: block;
      margin: 1rem 0 0.5rem;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      color: var(--text-primary);
      font-size: 0.95rem;
      letter-spacing: 0.5px;
    }

    form input[type="text"],
    form input[type="number"],
    form select,
    form textarea {
      width: 100%;
      padding: 1rem;
      border: 2px solid var(--sand-base);
      border-radius: 10px;
      font-family: 'Poppins', sans-serif;
      font-size: 1rem;
      background-color: var(--white);
      transition: all 0.3s ease;
    }

    form input:focus,
    form select:focus,
    form textarea:focus {
      border-color: var(--golden);
      box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.1);
      outline: none;
    }

    form textarea {
      height: 120px;
      resize: vertical;
    }

    form button.submit-btn {
      margin-top: 1.5rem;
      padding: 1rem 2.5rem;
      background-color: var(--golden);
      border: none;
      color: var(--white);
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 1rem;
      border-radius: 50px;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
      display: block;
      margin-left: auto;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    form button.submit-btn:hover {
      background-color: var(--golden-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(212, 175, 55, 0.5);
    }

    /* Filter Bar */
    .filter-bar {
      margin: 3rem 0;
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
      justify-content: center;
    }

    .filter-tag {
      background-color: var(--white);
      border: 2px solid var(--sand-base);
      border-radius: 25px;
      padding: 0.8rem 1.5rem;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      cursor: pointer;
      color: var(--text-primary);
      transition: all 0.3s ease;
      box-shadow: 0 2px 8px var(--shadow-soft);
      text-transform: uppercase;
      font-size: 0.9rem;
      letter-spacing: 0.5px;
    }

    .filter-tag:hover {
      background-color: var(--golden);
      color: var(--white);
      border-color: var(--golden);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(212, 175, 55, 0.3);
    }

    .filter-tag.active {
      background-color: var(--golden);
      color: var(--white);
      border-color: var(--golden);
    }

    /* Product Grid */
    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 2rem;
      padding: 1rem 0;
    }

    .product-card {
      background-color: var(--white);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 8px 25px var(--shadow-medium);
      transition: all 0.4s ease;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      border: 1px solid var(--sand-base);
      position: relative;
    }

    .product-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--golden), var(--sand-dark));
    }

    .product-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 35px var(--shadow-deep);
    }

    .product-image {
      width: 100%;
      aspect-ratio: 4 / 3;
      object-fit: cover;
      transition: transform 0.4s ease;
    }

    .product-card:hover .product-image {
      transform: scale(1.05);
    }

    .product-info {
      padding: 1.5rem;
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .product-title {
      font-family: 'Playfair Display', serif;
      font-weight: 700;
      font-size: 1.4rem;
      margin-bottom: 0.8rem;
      color: var(--text-primary);
      line-height: 1.3;
    }

    .product-desc {
      font-size: 0.95rem;
      color: var(--text-secondary);
      margin-bottom: 1rem;
      flex-grow: 1;
      line-height: 1.6;
    }

    .product-price {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.3rem;
      color: var(--golden);
      margin-bottom: 1rem;
    }

    .product-buttons {
      display: flex;
      gap: 1rem;
    }

    button.product-btn {
      flex: 1;
      padding: 0.8rem 1rem;
      border-radius: 25px;
      border: none;
      cursor: pointer;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 0.9rem;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .view-details-btn {
      background-color: var(--sand-base);
      color: var(--text-primary);
      box-shadow: 0 2px 8px var(--shadow-soft);
    }

    .view-details-btn:hover {
      background-color: var(--sand-dark);
      color: var(--white);
      transform: translateY(-1px);
    }

    .add-cart-btn {
      background-color: var(--golden);
      color: var(--white);
      box-shadow: 0 4px 12px rgba(212, 175, 55, 0.4);
    }

    .add-cart-btn:hover {
      background-color: var(--golden-dark);
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(212, 175, 55, 0.5);
    }

    /* Special Offer Section */
    .special-offer-section {
      background: linear-gradient(135deg, var(--special-offer-bg), var(--sand-base));
      margin: 3rem 0;
      border-radius: 20px;
      padding: 3rem;
      box-shadow: 0 8px 25px var(--shadow-medium);
      text-align: center;
      border: 1px solid var(--sand-base);
      position: relative;
      overflow: hidden;
    }

    .special-offer-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, var(--golden), var(--sand-dark));
    }

    .special-offer-section h2 {
      font-family: 'Playfair Display', serif;
      margin-bottom: 1.5rem;
      color: var(--text-primary);
      font-size: 2.2rem;
    }

    .special-offer-text {
      font-size: 1.2rem;
      font-weight: 400;
      color: var(--text-secondary);
      max-width: 600px;
      margin: 0 auto;
      line-height: 1.6;
    }

    /* Modal Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background-color: rgba(45, 27, 15, 0.8);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 130;
      padding: 1rem;
      backdrop-filter: blur(5px);
    }

    .modal-overlay.active {
      display: flex;
      animation: fadeIn 0.3s ease;
    }

    .modal {
      background: var(--white);
      border-radius: 20px;
      max-width: 550px;
      width: 100%;
      box-shadow: 0 15px 40px var(--shadow-deep);
      padding: 2rem;
      position: relative;
      animation: scaleIn 0.4s ease;
      border: 1px solid var(--sand-base);
    }

    .modal img {
      width: 100%;
      aspect-ratio: 4/3;
      object-fit: cover;
      border-radius: 15px;
      margin-bottom: 1.5rem;
      border: 2px solid var(--sand-base);
    }

    .modal h3 {
      font-family: 'Playfair Display', serif;
      margin: 0 0 1rem;
      color: var(--text-primary);
      font-size: 1.8rem;
    }

    .modal p {
      color: var(--text-secondary);
      margin-bottom: 1.5rem;
      line-height: 1.6;
    }

    .modal .modal-price {
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.5rem;
      margin-bottom: 1.5rem;
      color: var(--golden);
    }

    .modal button.close-btn {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: var(--sand-base);
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      font-size: 1.5rem;
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .modal button.close-btn:hover {
      background-color: var(--sand-dark);
      color: var(--white);
      transform: rotate(90deg);
    }

    /* Cart Sidebar */
    .cart-sidebar {
      position: fixed;
      top: 0;
      right: -360px;
      width: 360px;
      height: 100vh;
      background: var(--white);
      box-shadow: -8px 0 30px var(--shadow-deep);
      padding: 2rem;
      border-radius: 20px 0 0 20px;
      transition: right 0.4s ease;
      z-index: 140;
      display: flex;
      flex-direction: column;
      border-left: 1px solid var(--sand-base);
    }

    .cart-sidebar.open {
      right: 0;
    }

    .cart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 2px solid var(--sand-base);
    }

    .cart-sidebar h2 {
      font-family: 'Playfair Display', serif;
      color: var(--text-primary);
      font-size: 1.8rem;
      margin: 0;
    }

    .cart-close-btn {
      background: var(--sand-base);
      border: none;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      font-size: 1.5rem;
      color: var(--text-primary);
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .cart-close-btn:hover {
      background-color: var(--sand-dark);
      color: var(--white);
    }

    .cart-items {
      flex-grow: 1;
      overflow-y: auto;
      padding-right: 0.5rem;
    }

    .cart-item {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
      padding: 1rem;
      border: 1px solid var(--sand-base);
      border-radius: 15px;
      background-color: var(--form-bg);
    }

    .cart-item:last-child {
      margin-bottom: 0;
    }

    .cart-item-img {
      width: 80px;
      height: 80px;
      border-radius: 12px;
      object-fit: cover;
      border: 2px solid var(--sand-base);
      flex-shrink: 0;
    }

    .cart-item-info {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .cart-item-title {
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 0.5rem;
      font-size: 1.1rem;
    }

    .cart-item-price {
      font-weight: 600;
      color: var(--golden);
      font-size: 1rem;
    }

    .cart-item-controls {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      margin-top: 0.8rem;
    }

    .cart-item-controls button.qty-btn {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      border: none;
      background-color: var(--sand-base);
      color: var(--text-primary);
      font-weight: 700;
      font-size: 1.2rem;
      cursor: pointer;
      transition: all 0.3s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .cart-item-controls button.qty-btn:hover {
      background-color: var(--sand-dark);
      color: var(--white);
    }

    .cart-item-quantity {
      font-weight: 700;
      font-size: 1.1rem;
      min-width: 25px;
      text-align: center;
      color: var(--text-primary);
    }

    .cart-delete-btn {
      background-color: #e74c3c;
      color: var(--white);
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 20px;
      font-weight: 600;
      font-size: 0.85rem;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-left: auto;
    }

    .cart-delete-btn:hover {
      background-color: #c0392b;
      transform: translateY(-1px);
    }

    .cart-total {
      margin-top: 2rem;
      padding: 1.5rem;
      background-color: var(--sand-base);
      border-radius: 15px;
      text-align: center;
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 1.4rem;
      color: var(--text-primary);
      border: 2px solid var(--golden);
    }

    /* Animations */
    @keyframes fadeInUp {
      from { 
        opacity: 0; 
        transform: translateY(30px); 
      }
      to { 
        opacity: 1; 
        transform: translateY(0); 
      }
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes scaleIn {
      from { 
        transform: scale(0.9); 
        opacity: 0; 
      }
      to { 
        transform: scale(1); 
        opacity: 1; 
      }
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .header-container,
      .nav-container,
      main {
        padding-left: 1rem;
        padding-right: 1rem;
      }
      
      main.shifted {
        margin-right: 0;
      }
      
      .cart-sidebar {
        width: 100vw;
        border-radius: 0;
        right: -100vw;
      }
      
      .cart-sidebar.open {
        right: 0;
      }
    }

    @media (max-width: 768px) {
      .hero-section {
        height: 40vh;
        min-height: 300px;
      }
      
      .hero-section h1 {
        font-size: 2rem;
      }
      
      .hero-section p {
        font-size: 1rem;
      }
      
      .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
      }
      
      .button-bar {
        gap: 0.3rem;
        flex-wrap: wrap;
      }
      
      button.tab-button {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
      }
      
      .filter-bar {
        gap: 0.5rem;
      }
      
      .filter-tag {
        padding: 0.6rem 1rem;
        font-size: 0.8rem;
      }
    }

    @media (max-width: 480px) {
      .hero-content {
        padding: 1rem;
      }
      
      .hero-section h1 {
        font-size: 1.8rem;
      }
      
      .section-title {
        font-size: 2rem;
      }
      
      .form-container {
        padding: 1.5rem;
      }
      
      .product-card {
        margin-bottom: 1rem;
      }
      
      .product-buttons {
        flex-direction: column;
      }
      
      button.product-btn {
        width: 100%;
      }
    }

    /* Accessibility Improvements */
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
  <!-- Hero Section -->
  <section class="hero-section">
    <div class="hero-content">
      <h1>Golden Treat Bakery</h1>
      <p>Indulge in handcrafted pastries and cakes, crafted with premium ingredients for unforgettable moments.</p>
      <a href="#products" class="cta-button">Discover Our Creations</a>
    </div>
  </section>

  <!-- Main Header with Logo -->
  <header class="main-header">
    <div class="header-container">
      <a href="#" class="logo">Golden Treat</a>
    </div>
  </header>

  <!-- Navigation Bar -->
  <nav class="navigation-bar">
    <div class="nav-container">
      <div class="button-bar" role="navigation" aria-label="Product page controls">
        <button class="tab-button" id="btnCustomFood" type="button" aria-controls="customFoodForm" aria-expanded="false">Customize Food</button>
        <button class="tab-button" id="btnCustomCake" type="button" aria-controls="customCakeForm" aria-expanded="false">Customize Cake</button>
        <button class="tab-button" id="btnSpecialOffer" type="button" aria-controls="specialOfferSection" aria-expanded="false">Special Offer</button>
      </div>
    </div>
  </nav>

  <!-- Cart Icon -->
  <div class="cart-container" role="button" aria-label="View Cart" tabindex="0" id="cartBtn">
    <svg class="cart-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      <path d="M7 18c-1.104 0-1.99.896-1.99 2S5.896 22 7 22s2-.896 2-2-.896-2-2-2zm10 0c-1.104 0-1.99.896-1.99 2s.886 2 1.99 2 2-.896 2-2-.896-2-2-2zM7.344 16l-2.23-8H20v2h-14.558l1.336 4.8H20v2H6.844c-.27 0-.45-.157-.5-.4z"/>
    </svg>
    Cart (<span id="cartCount">0</span>)
  </div>

  <main id="products">
    <!-- Customize Food Form -->
    <section id="customFoodForm" class="form-container" aria-live="polite" aria-hidden="true" tabindex="-1">
      <h2 class="section-title">Custom Food Creations</h2>
      <form id="foodForm" action="#" method="post" novalidate>
        <label for="foodName">Dish Name</label>
        <input type="text" id="foodName" name="foodName" placeholder="Enter your custom dish name" required />
        
        <label for="foodIngredients">Preferred Ingredients</label>
        <textarea id="foodIngredients" name="foodIngredients" placeholder="Specify your desired ingredients and combinations" required></textarea>
        
        <label for="foodSpiceLevel">Spice Level</label>
        <select id="foodSpiceLevel" name="foodSpiceLevel" required>
          <option value="" disabled selected>Select spice preference</option>
          <option value="mild">Mild</option>
          <option value="medium">Medium</option>
          <option value="hot">Hot</option>
          <option value="extra-hot">Extra Hot</option>
        </select>

        <label for="foodQuantity">Quantity</label>
        <input type="number" id="foodQuantity" name="foodQuantity" value="1" min="1" required />

        <button type="submit" class="submit-btn">Submit Custom Order</button>
      </form>
    </section>

    <!-- Customize Cake Form -->
    <section id="customCakeForm" class="form-container" aria-live="polite" aria-hidden="true" tabindex="-1">
      <h2 class="section-title">Bespoke Cake Design</h2>
      <form id="cakeForm" action="#" method="post" novalidate>
        <label for="cakeType">Cake Flavor</label>
        <select id="cakeType" name="cakeType" required>
          <option value="" disabled selected>Select cake flavor</option>
          <option value="chocolate">Chocolate</option>
          <option value="vanilla">Vanilla</option>
          <option value="red-velvet">Red Velvet</option>
          <option value="fruit">Fruit Cake</option>
          <option value="custom">Custom Flavor</option>
        </select>

        <label for="cakeSize">Cake Size</label>
        <select id="cakeSize" name="cakeSize" required>
          <option value="" disabled selected>Select size</option>
          <option value="small">Small (6 inch)</option>
          <option value="medium">Medium (8 inch)</option>
          <option value="large">Large (10 inch)</option>
        </select>

        <label for="cakeMessage">Personal Message</label>
        <input type="text" id="cakeMessage" name="cakeMessage" placeholder="Special message for the cake (optional)" />

        <label for="cakeQuantity">Quantity</label>
        <input type="number" id="cakeQuantity" name="cakeQuantity" value="1" min="1" required />

        <button type="submit" class="submit-btn">Order Custom Cake</button>
      </form>
    </section>

    <!-- Special Offer Section -->
    <section id="specialOfferSection" class="special-offer-section" aria-live="polite" aria-hidden="true" tabindex="-1">
      <h2 class="section-title">Exclusive Offers</h2>
      <p class="special-offer-text">
        Enjoy 20% discount on all premium baked goods and complimentary delivery for orders exceeding $50. Valid for limited time only. Perfect opportunity to indulge in our artisanal creations.
      </p>
    </section>

    <!-- Filter Tags -->
    <aside class="filter-bar" aria-label="Product category filters" role="region">
      <div class="filter-tag active" data-filter="all" tabindex="0" role="button" aria-pressed="true">All Products</div>
      <div class="filter-tag" data-filter="rice" tabindex="0" role="button" aria-pressed="false">Rice Dishes</div>
      <div class="filter-tag" data-filter="kottu" tabindex="0" role="button" aria-pressed="false">Kottu Specialties</div>
      <div class="filter-tag" data-filter="buns" tabindex="0" role="button" aria-pressed="false">Buns & Pastries</div>
      <div class="filter-tag" data-filter="cake" tabindex="0" role="button" aria-pressed="false">Artisan Cakes</div>
    </aside>

    <!-- Products Grid -->
    <section class="products-grid" aria-label="Premium product collection">
      <!-- Sample product cards will be inserted here dynamically -->
    </section>
  </main>

  <!-- Modal for Product Details -->
  <div class="modal-overlay" id="productModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-describedby="modalDesc" tabindex="-1">
    <div class="modal">
      <button class="close-btn" aria-label="Close product details">&times;</button>
      <img id="modalImg" src="" alt="" />
      <h3 id="modalTitle"></h3>
      <p id="modalDesc"></p>
      <div class="modal-price" id="modalPrice"></div>
      <button class="add-cart-btn" id="modalAddCartBtn">Add to Cart</button>
    </div>
  </div>

  <!-- Cart Sidebar -->
  <aside class="cart-sidebar" id="cartSidebar" aria-label="Shopping cart" aria-live="polite">
    <div class="cart-header">
      <h2>Your Selection</h2>
      <button class="cart-close-btn" aria-label="Close cart">&times;</button>
    </div>
    <div class="cart-items" id="cartItems">
      <!-- Cart items will populate here -->
    </div>
    <div class="cart-total" id="cartTotal">Total: $0.00</div>
  </aside>

  <script>
    let products = [];
    
    // Load products from database
    async function loadProducts() {
      try {
        const response = await fetch('api.php?action=getProducts');
        const data = await response.json();
        if (Array.isArray(data)) {
          // Map database fields to frontend format
          products = data.map(p => ({
            id: p.id,
            title: p.title,
            category: p.category === 'food' ? mapFoodCategory(p.title) : 'cake',
            desc: p.description || '',
            price: parseFloat(p.price),
            img: p.image_url || 'https://via.placeholder.com/300x225?text=No+Image',
            specialOffer: p.special_offer || null
          }));
        } else {
          console.error('Error loading products:', data.error);
        }
      } catch (error) {
        console.error('Error loading products:', error);
        // Fallback to sample data if API fails
        products = [
          { id: 1, title: "Premium Chicken Fried Rice", category: "rice", desc: "Exquisite fried rice featuring tender chicken, fresh vegetables, and aromatic spices, masterfully prepared.", price: 8.99, img: "https://cdn.pixabay.com/photo/2017/09/02/13/16/fried-rice-2703294_1280.jpg" },
          { id: 2, title: "Decadent Chocolate Cake", category: "cake", desc: "Rich, moist chocolate cake layered with velvety ganache and premium cocoa, a true indulgence.", price: 15.0, img: "https://cdn.pixabay.com/photo/2017/01/20/00/30/chocolate-1991266_1280.jpg" },
          { id: 3, title: "Classic Vanilla Delight", category: "cake", desc: "Elegant vanilla sponge cake with smooth Swiss meringue buttercream, crafted with Madagascar vanilla.", price: 14.5, img: "https://cdn.pixabay.com/photo/2016/03/05/20/07/cakes-1238127_1280.jpg" }
        ];
      }
    }
    
    // Map food items to appropriate frontend categories
    function mapFoodCategory(title) {
      const titleLower = title.toLowerCase();
      if (titleLower.includes('rice')) return 'rice';
      if (titleLower.includes('kottu')) return 'kottu';
      if (titleLower.includes('bun') || titleLower.includes('bread')) return 'buns';
      return 'rice'; // default category for food items
    }

    const productsGrid = document.querySelector(".products-grid");
    const filterTags = document.querySelectorAll(".filter-tag");
    const btnCustomFood = document.getElementById("btnCustomFood");
    const btnCustomCake = document.getElementById("btnCustomCake");
    const btnSpecialOffer = document.getElementById("btnSpecialOffer");
    const customFoodForm = document.getElementById("customFoodForm");
    const customCakeForm = document.getElementById("customCakeForm");
    const specialOfferSection = document.getElementById("specialOfferSection");
    const tabButtons = [btnCustomFood, btnCustomCake, btnSpecialOffer];

    const cartContainer = document.getElementById("cartBtn");
    const cartCountElem = document.getElementById("cartCount");
    const cartSidebar = document.getElementById("cartSidebar");
    const cartItemsElem = document.getElementById("cartItems");
    const cartTotalElem = document.getElementById("cartTotal");
    const cartCloseBtn = cartSidebar.querySelector(".cart-close-btn");

    const productModal = document.getElementById("productModal");
    const modalImg = document.getElementById("modalImg");
    const modalTitle = document.getElementById("modalTitle");
    const modalDesc = document.getElementById("modalDesc");
    const modalPrice = document.getElementById("modalPrice");
    const modalCloseBtn = productModal.querySelector(".close-btn");
    const modalAddCartBtn = document.getElementById("modalAddCartBtn");

    let currentModalProductId = null;
    let cart = [];

    function renderProducts(filter = "all") {
      productsGrid.innerHTML = "";
      const filtered = filter === "all" ? products : products.filter(p => p.category === filter);
      if (filtered.length === 0) {
        productsGrid.innerHTML = "<p style='grid-column: 1/-1; text-align:center; color:var(--text-secondary); font-size:1.2rem; padding:2rem;'>No products available in this category at the moment.</p>";
        return;
      }
      filtered.forEach(p => {
        const card = document.createElement("article");
        card.className = "product-card";
        card.setAttribute("tabindex", "0");
        card.setAttribute("role", "button");
        card.innerHTML = `
          <img src="${p.img}" alt="${p.title}" class="product-image" loading="lazy" />
          <div class="product-info">
            <h3 class="product-title">${p.title}</h3>
            <p class="product-desc">${p.desc}</p>
            <div class="product-price">$${p.price.toFixed(2)}</div>
            <div class="product-buttons">
              <button class="product-btn view-details-btn" data-id="${p.id}" aria-label="View details for ${p.title}">View Details</button>
              <button class="product-btn add-cart-btn" data-id="${p.id}" aria-label="Add ${p.title} to cart">Add to Cart</button>
            </div>
          </div>
        `;
        productsGrid.appendChild(card);
      });

      document.querySelectorAll(".product-card").forEach(card => {
        card.addEventListener("keydown", (e) => {
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            const viewBtn = card.querySelector(".view-details-btn");
            if (viewBtn) viewBtn.click();
          }
        });
      });

      document.querySelectorAll(".view-details-btn").forEach(btn =>
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          const id = parseInt(e.target.dataset.id, 10);
          openModal(id);
        })
      );
      document.querySelectorAll(".add-cart-btn").forEach(btn =>
        btn.addEventListener("click", (e) => {
          e.stopPropagation();
          const id = parseInt(e.target.dataset.id, 10);
          addToCart(id);
        })
      );
    }

    filterTags.forEach(tag => {
      tag.addEventListener("click", () => {
        updateActiveFilter(tag);
      });
      tag.addEventListener("keydown", e => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          updateActiveFilter(tag);
        }
      });
    });

    function updateActiveFilter(selectedTag) {
      filterTags.forEach(t => {
        t.classList.remove("active");
        t.setAttribute("aria-pressed", "false");
      });
      selectedTag.classList.add("active");
      selectedTag.setAttribute("aria-pressed", "true");
      const filter = selectedTag.getAttribute("data-filter");
      renderProducts(filter);
    }

    tabButtons.forEach(btn => {
      btn.addEventListener("click", () => {
        tabButtons.forEach(b => {
          b.classList.remove("active");
          b.setAttribute("aria-expanded", "false");
        });
        customFoodForm.classList.remove("active");
        customFoodForm.setAttribute("aria-hidden", "true");
        customCakeForm.classList.remove("active");
        customCakeForm.setAttribute("aria-hidden", "true");
        specialOfferSection.classList.remove("active");
        specialOfferSection.setAttribute("aria-hidden", "true");

        btn.classList.add("active");
        btn.setAttribute("aria-expanded", "true");
        if (btn === btnCustomFood) {
          customFoodForm.classList.add("active");
          customFoodForm.setAttribute("aria-hidden", "false");
          customFoodForm.setAttribute("tabindex", "-1");
          const firstInput = customFoodForm.querySelector('input');
          firstInput?.focus();
        } else if (btn === btnCustomCake) {
          customCakeForm.classList.add("active");
          customCakeForm.setAttribute("aria-hidden", "false");
          customCakeForm.setAttribute("tabindex", "-1");
          const firstInput = customCakeForm.querySelector('select');
          firstInput?.focus();
        } else if (btn === btnSpecialOffer) {
          specialOfferSection.classList.add("active");
          specialOfferSection.setAttribute("aria-hidden", "false");
          specialOfferSection.setAttribute("tabindex", "-1");
          specialOfferSection.focus();
        }
      });
    });

    function openModal(productId) {
      const product = products.find(p => p.id === productId);
      if (!product) return;
      currentModalProductId = productId;
      modalImg.src = product.img;
      modalImg.alt = product.title;
      modalTitle.textContent = product.title;
      modalDesc.textContent = product.desc;
      modalPrice.textContent = `$${product.price.toFixed(2)}`;
      productModal.classList.add("active");
      productModal.setAttribute("aria-hidden", "false");
      productModal.focus();
    }

    function closeModal() {
      productModal.classList.remove("active");
      productModal.setAttribute("aria-hidden", "true");
      currentModalProductId = null;
      document.body.style.overflow = '';
    }

    modalCloseBtn.addEventListener("click", closeModal);
    productModal.addEventListener("click", (e) => {
      if (e.target === productModal) {
        closeModal();
      }
    });
    window.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && productModal.classList.contains("active")) {
        closeModal();
      }
    });

    productModal.addEventListener("transitionend", () => {
      if (productModal.classList.contains("active")) {
        document.body.style.overflow = 'hidden';
      }
    });

    function addToCart(productId) {
      const product = products.find(p => p.id === productId);
      if (!product) return;

      const existing = cart.find(i => i.id === productId);
      if (existing) {
        existing.quantity++;
      } else {
        cart.push({ ...product, quantity: 1 });
      }
      updateCartCount();
      renderCartItems();
      openCartSidebar();
      
      const btn = event?.target;
      if (btn) {
        btn.textContent = 'Added!';
        btn.style.backgroundColor = '#27ae60';
        setTimeout(() => {
          btn.textContent = 'Add to Cart';
          btn.style.backgroundColor = '';
        }, 1500);
      }
    }

    function removeFromCart(productId) {
      cart = cart.filter(item => item.id !== productId);
      updateCartCount();
      renderCartItems();
      if (cart.length === 0) closeCartSidebar();
    }

    function changeQuantity(productId, delta) {
      const item = cart.find(i => i.id === productId);
      if (!item) return;

      item.quantity += delta;
      if (item.quantity < 1) {
        removeFromCart(productId);
      } else {
        updateCartCount();
        renderCartItems();
      }
    }

    function updateCartCount() {
      const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
      cartCountElem.textContent = totalItems;
    }

    function renderCartItems() {
      if (cart.length === 0) {
        cartItemsElem.innerHTML = "<p style='color: var(--text-secondary); font-weight: 500; text-align: center; padding: 2rem;'>Your cart is currently empty. Explore our premium collection to begin your order.</p>";
        cartTotalElem.textContent = "Total: $0.00";
        return;
      }
      cartItemsElem.innerHTML = "";
      cart.forEach(item => {
        const itemDiv = document.createElement("div");
        itemDiv.className = "cart-item";
        itemDiv.innerHTML = `
          <img src="${item.img}" alt="${item.title}" class="cart-item-img" loading="lazy" />
          <div class="cart-item-info">
            <h4 class="cart-item-title">${item.title}</h4>
            <div class="cart-item-price">$${item.price.toFixed(2)} each</div>
            <div class="cart-item-controls" aria-label="Quantity controls for ${item.title}">
              <button class="qty-btn decrease-btn" aria-label="Decrease quantity of ${item.title}" data-id="${item.id}">-</button>
              <span class="cart-item-quantity" aria-live="polite">${item.quantity}</span>
              <button class="qty-btn increase-btn" aria-label="Increase quantity of ${item.title}" data-id="${item.id}">+</button>
              <button class="cart-delete-btn" aria-label="Remove ${item.title} from cart" data-id="${item.id}">Remove</button>
            </div>
          </div>
        `;

        itemDiv.querySelector(".decrease-btn").addEventListener("click", () => changeQuantity(item.id, -1));
        itemDiv.querySelector(".increase-btn").addEventListener("click", () => changeQuantity(item.id, 1));
        itemDiv.querySelector(".cart-delete-btn").addEventListener("click", () => removeFromCart(item.id));

        cartItemsElem.appendChild(itemDiv);
      });
      const totalPrice = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
      cartTotalElem.innerHTML = `<strong>Total: $${totalPrice.toFixed(2)}</strong>`;
    }

    function openCartSidebar() {
      cartSidebar.classList.add("open");
      document.querySelector("main").classList.add("shifted");
      cartContainer.setAttribute("aria-expanded", "true");
    }

    function closeCartSidebar() {
      cartSidebar.classList.remove("open");
      document.querySelector("main").classList.remove("shifted");
      cartContainer.setAttribute("aria-expanded", "false");
    }

    cartCloseBtn.addEventListener("click", closeCartSidebar);
    cartContainer.addEventListener("click", () => {
      if (cartSidebar.classList.contains("open")) {
        closeCartSidebar();
      } else {
        openCartSidebar();
      }
    });
    cartContainer.addEventListener("keydown", (e) => {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        cartContainer.click();
      }
    });

    modalAddCartBtn.addEventListener("click", () => {
      if (currentModalProductId !== null) {
        addToCart(currentModalProductId);
        closeModal();
      }
    });

    document.querySelector('.cta-button').addEventListener('click', (e) => {
      e.preventDefault();
      document.getElementById('products').scrollIntoView({ behavior: 'smooth' });
    });

    // Initialize the application
    async function initApp() {
      await loadProducts();
      renderProducts();
      renderCartItems();
      updateCartCount();
    }
    
    // Start the application
    initApp();

        function removeFromCart(productId) {
      cart = cart.filter(item => item.id !== productId);
      updateCartCount();
      renderCartItems();
    }

    function updateCartCount() {
      const totalQty = cart.reduce((sum, item) => sum + item.quantity, 0);
      cartCountElem.textContent = totalQty;
    }

    function renderCartItems() {
      cartItemsElem.innerHTML = '';
      if (cart.length === 0) {
        cartItemsElem.innerHTML = '<p style="text-align:center; color:var(--text-secondary); margin-top:2rem;">Your cart is empty.</p>';
        cartTotalElem.textContent = 'Total: $0.00';
        return;
      }

      let total = 0;
      cart.forEach(item => {
        total += item.price * item.quantity;
        const cartItem = document.createElement('div');
        cartItem.className = 'cart-item';
        cartItem.innerHTML = `
          <img src="${item.img}" alt="${item.title}" class="cart-item-img" />
          <div class="cart-item-info">
            <div class="cart-item-title">${item.title}</div>
            <div class="cart-item-price">$${(item.price * item.quantity).toFixed(2)}</div>
            <div class="cart-item-controls">
              <button class="qty-btn" data-id="${item.id}" data-action="decrease" aria-label="Decrease quantity">-</button>
              <div class="cart-item-quantity">${item.quantity}</div>
              <button class="qty-btn" data-id="${item.id}" data-action="increase" aria-label="Increase quantity">+</button>
            </div>
          </div>
          <button class="cart-delete-btn" data-id="${item.id}" aria-label="Remove ${item.title} from cart">Remove</button>
        `;
        cartItemsElem.appendChild(cartItem);
      });
      cartTotalElem.textContent = `Total: $${total.toFixed(2)}`;

      // Attach event listeners for quantity buttons
      cartItemsElem.querySelectorAll('.qty-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const id = parseInt(btn.dataset.id, 10);
          const action = btn.dataset.action;
          const item = cart.find(i => i.id === id);
          if (!item) return;
          if (action === 'increase') item.quantity++;
          if (action === 'decrease') item.quantity = Math.max(1, item.quantity - 1);
          updateCartCount();
          renderCartItems();
        });
      });

      // Attach event listeners for delete buttons
      cartItemsElem.querySelectorAll('.cart-delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const id = parseInt(btn.dataset.id, 10);
          removeFromCart(id);
        });
      });
    }

    function openCartSidebar() {
      cartSidebar.classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeCartSidebar() {
      cartSidebar.classList.remove('open');
      document.body.style.overflow = '';
    }

    cartContainer.addEventListener('click', openCartSidebar);
    cartCloseBtn.addEventListener('click', closeCartSidebar);


  </script>
</body>
</html>