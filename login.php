<?php
// Database connection
$servername = "localhost";
$username = "root"; // Change to your DB username
$password = ""; // Change to your DB password
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submissions
session_start();
$message = ""; // For success/error messages

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'register') {
            // Registration logic
            $fullName = mysqli_real_escape_string($conn, $_POST['reg-fullname']);
            $email = mysqli_real_escape_string($conn, $_POST['reg-email']);
            $mobile = mysqli_real_escape_string($conn, $_POST['reg-mobile']);
            $address = mysqli_real_escape_string($conn, $_POST['reg-address']);
            $district = mysqli_real_escape_string($conn, $_POST['reg-district']);
            $password = mysqli_real_escape_string($conn, $_POST['reg-password']);
            $confirmPassword = mysqli_real_escape_string($conn, $_POST['reg-confirm-password']);
            $role = 'customer'; // Default role for new users
            $dateJoined = date('Y-m-d');

            if ($password !== $confirmPassword) {
                $message = "Passwords do not match!";
            } else {
                // Check if email exists
                $checkEmail = "SELECT * FROM users WHERE email = '$email'";
                $result = $conn->query($checkEmail);
                if ($result->num_rows > 0) {
                    $message = "Email already registered!";
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "INSERT INTO users (full_name, email, mobile, address, district, password, role, date_joined) 
                            VALUES ('$fullName', '$email', '$mobile', '$address', '$district', '$hashedPassword', '$role', '$dateJoined')";
                    if ($conn->query($sql) === TRUE) {
                        $message = "Registration successful! Please log in.";
                    } else {
                        $message = "Error: " . $conn->error;
                    }
                }
            }
        } elseif ($_POST['action'] == 'login') {
            // Login logic
            $email = mysqli_real_escape_string($conn, $_POST['login-email']);
            $password = mysqli_real_escape_string($conn, $_POST['login-password']);

            $sql = "SELECT * FROM users WHERE email = '$email'";
            $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if (password_verify($password, $row['password'])) {
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['full_name'] = $row['full_name'];

                    // Role-based redirection
                    if ($row['role'] == 'admin') {
                        header("Location: admin.php");
                        exit();
                    } elseif ($row['role'] == 'manager') {
                        header("Location: manager.php");
                        exit();
                    } else {
                        header("Location: index.php");
                        exit();
                    }
                } else {
                    $message = "Incorrect password!";
                }
            } else {
                $message = "Email not found!";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Golden Treat - Login & Register</title>
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
            --radius: 20px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: var(--bg);
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Sprinkle particles (background) */
        .sprinkles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1;
            pointer-events: none;
            overflow: hidden;
        }

        .sprinkle {
            position: absolute;
            width: 8px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), #ff6f91);
            border-radius: 2px;
            opacity: 0.6;
            animation: fall 4s linear infinite;
        }

        @keyframes fall {
            0% { transform: translateY(-10vh) rotate(0deg); opacity: 0.6; }
            100% { transform: translateY(110vh) rotate(360deg); opacity: 0.2; }
        }

        /* Frosting background */
        .frosting-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            background: var(--gradient-1);
            animation: spreadFrosting 15s ease-in-out infinite;
            overflow: hidden;
        }

        @keyframes spreadFrosting {
            0% { background: linear-gradient(135deg, #FFE8B7, #D4AF37); }
            50% { background: linear-gradient(225deg, #FFE5B4, #D2691E); }
            100% { background: linear-gradient(135deg, #FFE8B7, #D4AF37); }
        }

        /* Message styling */
        .message {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--white);
            color: var(--dark);
            padding: 12px 20px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            z-index: 3;
            font-size: 0.9rem;
            text-align: center;
            max-width: 90%;
            width: 300px;
            opacity: 0;
            animation: fadeInOut 3s ease forwards;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            10% { opacity: 1; transform: translateX(-50%) translateY(0); }
            90% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }

        /* Container */
        .container {
            display: flex;
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            max-width: 1200px;
            width: 90%;
            min-height: 600px;
            max-height: 90vh;
            position: relative;
            z-index: 2;
        }

        /* Form section wrapper */
        .form-wrapper {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
            order: 1;
            perspective: 1000px;
            transition: order 0.8s ease;
        }

        /* Forms */
        .form {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            padding: 30px;
            opacity: 0;
            transform: rotateY(90deg) scale(0.95);
            transition: all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: none;
            background: var(--white);
            z-index: 2;
            overflow: hidden;
        }

        .form.active {
            opacity: 1;
            transform: rotateY(0deg) scale(1);
            pointer-events: all;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.3);
        }

        .form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--accent), var(--white));
            opacity: 0;
            transform: translateY(-100%);
            transition: transform 0.8s ease, opacity 0.8s ease;
            border-radius: var(--radius);
            z-index: -1;
        }

        .form.active::before {
            transform: translateY(0);
            opacity: 0.5;
        }

        .form h2 {
            font-family: 'Dancing Script', cursive;
            font-size: 2rem;
            color: var(--secondary);
            margin-bottom: 15px;
            text-align: center;
        }

        .form input {
            width: 100%;
            padding: 6px;
            margin: 4px 0;
            border: 1px solid var(--accent);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
            background: var(--light);
            transition: border-color 0.3s ease, transform 0.3s ease;
        }

        .form input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 5px rgba(212, 175, 55, 0.5);
            transform: scale(1.02);
        }

        .form button {
            width: 100%;
            padding: 10px;
            background: var(--gradient-1);
            border: none;
            border-radius: 10px;
            color: var(--white);
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
            margin-top: 10px;
        }

        .form button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .form button::before {
            content: '🍰';
            position: absolute;
            left: -20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 1.1rem;
            opacity: 0;
            transition: left 0.3s ease, opacity 0.3s ease;
        }

        .form button:hover::before {
            left: 10px;
            opacity: 1;
        }

        .switch, .forgot-password {
            margin-top: 6px;
            font-size: 0.8rem;
            text-align: center;
            color: var(--secondary);
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.3s ease;
        }

        .switch:hover, .forgot-password:hover {
            color: var(--primary);
        }

        /* Transition sprinkle burst */
        .sprinkle-burst {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
            pointer-events: none;
            opacity: 0;
            overflow: hidden;
        }

        .sprinkle-burst.active {
            opacity: 1;
        }

        .burst-particle {
            position: absolute;
            width: 10px;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), #ff6f91);
            border-radius: 3px;
            opacity: 0.8;
            animation: burst 0.8s ease-out forwards;
        }

        @keyframes burst {
            0% { transform: translate(0, 0) rotate(0deg); opacity: 0.8; }
            100% { transform: translate(calc(var(--x) * 1px), calc(var(--y) * 1px)) rotate(360deg); opacity: 0; }
        }

        /* Image Section */
        .image-section {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            overflow: hidden;
            order: 2;
            transition: order 0.8s ease;
        }

        .image-section img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0.8;
            transition: transform 0.5s ease;
        }

        .image-section:hover img {
            transform: scale(1.05);
        }

        .image-section .text {
            position: relative;
            z-index: 1;
            padding: 20px;
            animation: fadeInText 1s ease forwards;
        }

        @keyframes fadeInText {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        .image-section h1 {
            font-family: 'Righteous', sans-serif;
            font-size: 2rem;
            margin-bottom: 15px;
            color: var(--dark);
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            color: #FFF8F0;
        }

        .image-section p {
            font-size: 1rem;
            line-height: 1.5;
            color: var(--dark);
            background: rgba(255, 255, 255, 0.7);
            padding: 12px;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        /* Class to swap positions */
        .register-mode .form-wrapper {
            order: 2;
        }

        .register-mode .image-section {
            order: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                width: 95%;
                min-height: 500px;
                max-height: 90vh;
            }
            .image-section {
                height: 250px;
            }
            .form-wrapper {
                padding: 15px;
                overflow-y: auto;
            }
            .form {
                padding: 15px;
            }
            .form h2 {
                font-size: 1.8rem;
            }
            .form input {
                font-size: 0.75rem;
                padding: 5px;
                margin: 4px 0;
            }
            .form button {
                font-size: 0.85rem;
                padding: 8px;
            }
            .switch, .forgot-password {
                font-size: 0.8rem;
            }
            .register-mode .form-wrapper {
                order: 1;
            }
            .register-mode .image-section {
                order: 2;
            }
            .image-section h1 {
                font-size: 1.5rem;
            }
            .image-section p {
                font-size: 0.85rem;
            }
            .message {
                width: 80%;
                font-size: 0.8rem;
                padding: 10px 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Frosting background -->
    <div class="frosting-bg"></div>
    <!-- Sprinkle particles -->
    <div class="sprinkles"></div>
    <!-- Sprinkle burst for transition -->
    <div class="sprinkle-burst" id="sprinkle-burst"></div>

    <!-- In-page message -->
    <?php if (!empty($message)): ?>
        <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'not') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Container -->
    <div class="container" id="container">
        <!-- Form Section -->
        <div class="form-wrapper">
            <!-- Login Form -->
            <form class="form active" id="login-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <h2>Login to Golden Treat</h2>
                <input type="email" name="login-email" id="login-email" placeholder="Email" required>
                <input type="password" name="login-password" id="login-password" placeholder="Password" required>
                <input type="hidden" name="action" value="login">
                <button type="submit">Login 🍰</button>
                <div class="forgot-password" onclick="forgotPassword()">Forgot Password?</div>
                <div class="switch" onclick="showRegister()">Don't have an account? Register here</div>
            </form>

            <!-- Register Form -->
            <form class="form" id="register-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <h2>Register at Golden Treat</h2>
                <input type="text" name="reg-fullname" id="reg-fullname" placeholder="Full Name" required>
                <input type="email" name="reg-email" id="reg-email" placeholder="Email" required>
                <input type="tel" name="reg-mobile" id="reg-mobile" placeholder="Mobile Number" required>
                <input type="text" name="reg-address" id="reg-address" placeholder="Address" required>
                <input type="text" name="reg-district" id="reg-district" placeholder="District" required>
                <input type="password" name="reg-password" id="reg-password" placeholder="Password" required>
                <input type="password" name="reg-confirm-password" id="reg-confirm-password" placeholder="Confirm Password" required>
                <input type="hidden" name="action" value="register">
                <button type="submit">Register 🥐</button>
                <div class="switch" onclick="showLogin()">Already have an account? Login here</div>
            </form>
        </div>

        <!-- Image Section -->
        <div class="image-section">
            <img src="https://images.unsplash.com/photo-1556741533-6e6a62bd8b49?auto=format&fit=crop&w=1000&q=80" alt="Bakery Cakes">
            <div class="text">
                <h1>Welcome to Golden Treat 🍰</h1>
                <p>Freshly baked happiness delivered daily. Log in or sign up to enjoy our delicious cakes, pastries, and breads!</p>
            </div>
        </div>
    </div>

    <script>
        // Create sprinkle particles
        function createSprinkles() {
            const sprinkleContainer = document.querySelector('.sprinkles');
            for (let i = 0; i < 100; i++) {
                const sprinkle = document.createElement('div');
                sprinkle.className = 'sprinkle';
                sprinkle.style.left = Math.random() * 100 + '%';
                sprinkle.style.animationDelay = Math.random() * 4 + 's';
                sprinkle.style.animationDuration = (Math.random() * 2 + 3) + 's';
                sprinkleContainer.appendChild(sprinkle);
            }
        }

        // Create sprinkle burst for transition
        function createSprinkleBurst() {
            const burstContainer = document.getElementById('sprinkle-burst');
            burstContainer.innerHTML = '';
            for (let i = 0; i < 20; i++) {
                const particle = document.createElement('div');
                particle.className = 'burst-particle';
                const angle = Math.random() * 360;
                const distance = 50 + Math.random() * 100;
                particle.style.setProperty('--x', Math.cos(angle * Math.PI / 180) * distance);
                particle.style.setProperty('--y', Math.sin(angle * Math.PI / 180) * distance);
                particle.style.left = '50%';
                particle.style.top = '50%';
                particle.style.animationDelay = Math.random() * 0.2 + 's';
                burstContainer.appendChild(particle);
            }
            burstContainer.classList.add('active');
            setTimeout(() => {
                burstContainer.classList.remove('active');
                burstContainer.innerHTML = '';
            }, 800);
        }

        // Toggle forms
        function showRegister() {
            createSprinkleBurst();
            document.getElementById("login-form").classList.remove("active");
            document.getElementById("register-form").classList.add("active");
            document.getElementById("container").classList.add("register-mode");
        }

        function showLogin() {
            createSprinkleBurst();
            document.getElementById("register-form").classList.remove("active");
            document.getElementById("login-form").classList.add("active");
            document.getElementById("container").classList.remove("register-mode");
        }

        // Forgot password handler
        function forgotPassword() {
            // Trigger in-page message instead of alert
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message';
            messageDiv.textContent = 'Redirecting to password reset page...';
            document.body.appendChild(messageDiv);
            setTimeout(() => messageDiv.remove(), 3000);
            // In a real app, this would redirect to a password reset form
        }

        // Initialize
        window.addEventListener('DOMContentLoaded', () => {
            createSprinkles();
        });
    </script>
</body>
</html>