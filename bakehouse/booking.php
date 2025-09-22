<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['ok' => false, 'msg' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reserve_table') {
    function sanitize($data) {
        return trim(htmlspecialchars($data));
    }

    $table_number = intval($_POST['tableNumber']);
    $event_type = sanitize($_POST['eventType']);
    $date = sanitize($_POST['date']);
    $time = sanitize($_POST['time']);
    $name = sanitize($_POST['name']);
    $guests = intval($_POST['guests']);
    $requests = sanitize($_POST['requests'] ?? '');

    // Basic validation
    if (empty($table_number) || empty($event_type) || empty($date) || empty($time) || empty($name) || empty($guests)) {
        echo json_encode(['ok' => false, 'msg' => 'All required fields must be filled']);
        exit;
    }

    // Validate date (not in the past)
    $reservation_date = DateTime::createFromFormat('Y-m-d', $date);
    $today = new DateTime();
    if ($reservation_date < $today->setTime(0, 0, 0)) {
        echo json_encode(['ok' => false, 'msg' => 'Reservation date cannot be in the past']);
        exit;
    }

    // Validate guests
    if ($guests < 1) {
        echo json_encode(['ok' => false, 'msg' => 'Number of guests must be at least 1']);
        exit;
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO reservations (table_number, event_type, reservation_date, reservation_time, name, guests, special_requests) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo json_encode(['ok' => false, 'msg' => 'Prepare failed: ' . $conn->error]);
        exit;
    }

    $stmt->bind_param("issssis", $table_number, $event_type, $date, $time, $name, $guests, $requests);
    $success = $stmt->execute();
    $stmt->close();
    $conn->close();

    echo json_encode(['ok' => $success, 'msg' => $success ? 'Reservation successful' : 'Reservation failed']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table Reservation - Golden Treat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Righteous&display=swap" rel="stylesheet">
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

        .reservation-form {
            background: var(--white);
            padding: 40px;
            border-radius: 20px;
            box-shadow: var(--shadow);
            max-width: 600px;
            margin: 0 auto;
        }

        .reservation-form .form-group {
            margin-bottom: 20px;
        }

        .reservation-form label {
            display: block;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .reservation-form select,
        .reservation-form input,
        .reservation-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--accent);
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .reservation-form select:focus,
        .reservation-form input:focus,
        .reservation-form textarea:focus {
            border-color: var(--primary);
            outline: none;
        }

        .reservation-form textarea {
            resize: vertical;
            min-height: 100px;
        }

        .reservation-form button {
            display: block;
            width: 100%;
            padding: 15px;
            background: var(--gradient-1);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .reservation-form button:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }

        @media (max-width: 768px) {
            nav ul {
                gap: 15px;
            }

            .section h2 {
                font-size: 2.5rem;
            }

            .reservation-form {
                padding: 20px;
            }
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
            <li><a href="index.php">Home</a></li>
            <li><a href="product2.php">Products</a></li>
            <li><a href="login.php">Services</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="profile.php">Contact</a></li>
        </ul>
    </nav>

    <!-- Reservation Section -->
    <section class="section" id="reservation">
        <h2>Reserve a Table</h2>
        <div class="reservation-form">
            <form id="reservationForm" method="post">
                <input type="hidden" name="action" value="reserve_table">
                <div class="form-group">
                    <label for="tableNumber">Table Number</label>
                    <select id="tableNumber" name="tableNumber" required>
                        <option value="" disabled selected>Select a table</option>
                        <option value="1">Table 1</option>
                        <option value="2">Table 2</option>
                        <option value="3">Table 3</option>
                        <option value="4">Table 4</option>
                        <option value="5">Table 5</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="eventType">Event Type</label>
                    <select id="eventType" name="eventType" required>
                        <option value="" disabled selected>Select event type</option>
                        <option value="birthday">Birthday</option>
                        <option value="anniversary">Anniversary</option>
                        <option value="casual">Casual Dining</option>
                        <option value="business">Business Meeting</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date">Date</label>
                    <input type="date" id="date" name="date" placeholder="mm/dd/yyyy" required>
                </div>
                <div class="form-group">
                    <label for="time">Time</label>
                    <input type="time" id="time" name="time" required>
                </div>
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" placeholder="Your Name" required>
                </div>
                <div class="form-group">
                    <label for="guests">Number of Guests</label>
                    <input type="number" id="guests" name="guests" placeholder="Number of Guests" min="1" required>
                </div>
                <div class="form-group">
                    <label for="requests">Special Requests (Optional)</label>
                    <textarea id="requests" name="requests" placeholder="Any special requests"></textarea>
                </div>
                <button type="submit">Reserve Table</button>
            </form>
        </div>
    </section>

    <script>
        // Particles
        function createParticles() {
            const wrap = $('.particles');
            for (let i = 0; i < 50; i++) {
                const d = $('<div>').addClass('particle');
                d.css({
                    left: Math.random() * 100 + '%',
                    top: Math.random() * 100 + '%',
                    width: Math.random() * 10 + 5 + 'px',
                    height: Math.random() * 10 + 5 + 'px',
                    animationDelay: Math.random() * 6 + 's',
                    animationDuration: (Math.random() * 3 + 3) + 's'
                });
                wrap.append(d);
            }
        }

        // Scroll progress
        function updateProgressBar() {
            const scrolled = $(window).scrollTop();
            const maxHeight = $(document).height() - $(window).height();
            const progress = (scrolled / maxHeight) * 100;
            $('.progress-bar').css('width', progress + '%');
        }

        // Scroll animations
        function animateOnScroll() {
            $('.section').each(function() {
                const rect = this.getBoundingClientRect();
                if (rect.top < window.innerHeight * 0.8) {
                    $(this).addClass('show');
                }
            });
        }

        // Nav scroll
        function updateNav() {
            const nav = $('nav');
            if ($(window).scrollTop() > 100) {
                nav.addClass('scrolled');
            } else {
                nav.removeClass('scrolled');
            }
        }

        // Smooth scroll
        $('nav a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.getAttribute('href'));
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top
                }, 1000, 'swing');
            }
        });

        // Loading screen
        $(window).on('load', function() {
            setTimeout(() => $('.loading').addClass('hidden'), 800);
        });

        // Form submission with jQuery AJAX
        $('#reservationForm').on('submit', function(e) {
            e.preventDefault();
            const formData = $(this).serialize();

            $.ajax({
                url: 'reserve_table.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.ok) {
                        alert('Table reserved successfully!');
                        $('#reservationForm')[0].reset();
                    } else {
                        alert(response.msg || 'Reservation failed. Please try again.');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again later.');
                }
            });
        });

        // Init
        $(document).ready(function() {
            createParticles();
            animateOnScroll();
            $(window).on('scroll', function() {
                updateProgressBar();
                animateOnScroll();
                updateNav();
            });
        });
    </script>
</body>
</html>