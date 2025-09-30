<?php
// No session or cart functionality needed for About Us page

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
    <title>Golden Treat - About Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Righteous&display=swap" rel="stylesheet">
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
            0%, 100% {
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

        .about-content {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .about-story, .about-mission, .about-team {
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .about-story:hover, .about-mission:hover, .about-team:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }

        .about-story p, .about-mission p {
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            line-height: 1.6;
            color: var(--dark);
            margin-bottom: 20px;
        }

        .about-team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }

        .team-member {
            text-align: center;
            background: var(--light);
            border-radius: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .team-member img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--accent);
        }

        .team-member h3 {
            font-family: 'Righteous', sans-serif;
            font-size: 1.2rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }

        .team-member p {
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            color: var(--dark);
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
            transition: opacity 0.05s ease;
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
            .hero h1 {
                font-size: 3rem;
            }
            .about-story, .about-mission, .about-team {
                padding: 20px;
            }
            .about-team-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    <!-- Progress bar -->
    <div class="progress-bar"></div>
    <!-- Animated particles -->
    <div class="particles"></div>

    <!-- Navigation -->
    <nav>
        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="product2.php">Products</a></li>
            <li><a href="untitled-1.php">Table booking</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="profile.php">Contact</a></li>
        </ul>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="cupcake-particles"></div>
        <div class="hero-content">
            <h1>
                <span class="word word--golden">About</span>
                <span class="welcome-message">Welcome</span>
                <span class="word word--treat">Us</span>
            </h1>
            <p>Discover the story behind Golden Treat's delicious creations</p>
        </div>
    </section>

    <!-- About Us Section -->
    <section class="section" id="about">
        <h2>Our Story</h2>
        <div class="about-content">
            <div class="about-story">
                <p>Golden Treat was founded in 2010 with a simple mission: to bring joy through artisan baked goods. Starting as a small family bakery in the heart of the city, we’ve grown into a beloved destination for pastry lovers, all while staying true to our roots. Our recipes blend traditional techniques with innovative flavors, using only the finest ingredients sourced locally whenever possible.</p>
                <p>Every cake, croissant, and cookie tells a story of craftsmanship, care, and community. From our signature chocolate éclairs to custom wedding cakes, we pour our hearts into every creation, ensuring each bite is a moment of delight.</p>
            </div>
            <div class="about-mission">
                <h2>Our Mission</h2>
                <p>At Golden Treat, we believe in more than just baking. Our mission is to create moments of happiness, celebrate life’s special occasions, and foster a sense of community through our delicious offerings. We strive to maintain sustainability by partnering with local farmers and reducing waste, ensuring our treats are as kind to the planet as they are to your taste buds.</p>
            </div>
            <div class="about-team">
                <h2>Meet Our Team</h2>
                <div class="about-team-grid">
                    <div class="team-member">
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=140&q=80" alt="Team Member">
                        <h3>Emma Baker</h3>
                        <p>Head Pastry Chef</p>
                    </div>
                    <div class="team-member">
                        <img src="https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=140&q=80" alt="Team Member">
                        <h3>James Miller</h3>
                        <p>Master Baker</p>
                    </div>
                    <div class="team-member">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=140&q=80" alt="Team Member">
                        <h3>Sarah Lee</h3>
                        <p>Creative Director</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Create falling cupcakes for hero section
        function createCupcakes() {
            const cupcakeContainer = document.querySelector('.cupcake-particles');
            const items = ['🍪', '🍰', '🍩'];

            for (let i = 0; i < 200; i++) {
                const cupcake = document.createElement('div');
                cupcake.className = 'cupcake';
                cupcake.textContent = items[Math.floor(Math.random() * items.length)];
                cupcake.style.left = Math.random() * 100 + '%';
                cupcake.style.animationDelay = Math.random() * 5 + 's';
                cupcake.style.animationDuration = (Math.random() * 3 + 4) + 's';
                cupcakeContainer.appendChild(cupcake);
            }
        }

        // Particles
        function createParticles() {
            const wrap = document.querySelector('.particles');
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
            document.querySelector('.progress-bar').style.width = progress + '%';
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
            if (window.scrollY > 100) nav.classList.add('scrolled');
            else nav.classList.remove('scrolled');
        }

        // Smooth scroll
        document.querySelectorAll('nav a[href^="#"]').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const target = document.querySelector(a.getAttribute('href'));
                if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            createCupcakes();
            createParticles();
            animateOnScroll();
            window.addEventListener('scroll', () => {
                updateProgressBar();
                animateOnScroll();
                updateNav();
            });
            setTimeout(() => document.querySelector('.loading').classList.add('hidden'), 800);
        });
    </script>
</body>
</html>