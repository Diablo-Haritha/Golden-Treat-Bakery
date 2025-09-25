<?php
// Set timezone to ensure consistent MySQL NOW() behavior
date_default_timezone_set('Asia/Kolkata');

// Load configuration (e.g., for PHPMailer credentials)
require 'config.php'; // Create a config.php file with sensitive data

// Composer autoloader
require 'vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Database connection
$servername = "localhost";
$username = "root"; // Change to your DB username
$password = ""; // Change to your DB password
$dbname = "golden_treat";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submissions and AJAX requests
session_start();
$message = ""; // For success/error messages

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    if ($_POST['action'] == 'register') {
        // Registration logic
        $fullName = mysqli_real_escape_string($conn, $_POST['reg-fullname']);
        $email = mysqli_real_escape_string($conn, $_POST['reg-email']);
        $mobile = mysqli_real_escape_string($conn, $_POST['reg-mobile']);
        $address = mysqli_real_escape_string($conn, $_POST['reg-address']);
        $district = mysqli_real_escape_string($conn, $_POST['reg-district']);
        $password = mysqli_real_escape_string($conn, $_POST['reg-password']);
        $confirmPassword = mysqli_real_escape_string($conn, $_POST['reg-confirm-password']);
        $role = 'customer';
        $dateJoined = date('Y-m-d');

        if ($password !== $confirmPassword) {
            $message = "Passwords do not match!";
        } elseif (strlen($password) < 8 || !preg_match("/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/", $password)) {
            $message = "Password must be at least 8 characters long and contain letters and numbers!";
        } else {
            $checkEmail = "SELECT * FROM users WHERE email = ?";
            $stmt = $conn->prepare($checkEmail);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $message = "Email already registered!";
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (full_name, email, mobile, address, district, password, role, date_joined) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssss", $fullName, $email, $mobile, $address, $district, $hashedPassword, $role, $dateJoined);
                if ($stmt->execute()) {
                    $message = "Registration successful! Please log in.";
                } else {
                    $message = "Registration failed. Please try again.";
                    error_log("SQL Error (register): " . $conn->error);
                }
            }
            $stmt->close();
        }
    } elseif ($_POST['action'] == 'login') {
        // Login logic
        $email = mysqli_real_escape_string($conn, $_POST['login-email']);
        $password = mysqli_real_escape_string($conn, $_POST['login-password']);

        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['full_name'] = $row['full_name'];

               if (in_array($row['role'], ['admin', 'manager'])) {
                    header("Location: ../Admin/index.php");
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
        $stmt->close();
    } elseif ($_POST['action'] == 'send_otp') {
        // Send OTP logic
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $otp = sprintf("%06d", mt_rand(100000, 999999));

            // Clear previous OTPs
            $stmt = $conn->prepare("DELETE FROM otps WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            // Store new OTP
            $sql = "INSERT INTO otps (user_id, otp, expires_at) VALUES (?, ?, NOW() + INTERVAL 10 MINUTE)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $otp);
            if ($stmt->execute()) {
                $mail = new PHPMailer(true);
                try {
                    $mail->SMTPDebug = SMTP::DEBUG_OFF;
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = SMTP_USERNAME; // From config.php
                    $mail->Password = SMTP_PASSWORD; // From config.php
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    $mail->setFrom(SMTP_USERNAME, 'Golden Treat');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Golden Treat Password Reset OTP';
                    $mail->Body = "Dear User,<br>Your OTP for password reset is: <strong>$otp</strong><br>This OTP is valid for 10 minutes.<br><br>Best regards,<br>Golden Treat Team";
                    $mail->send();
                    echo json_encode(['status' => 'success', 'message' => 'OTP sent to your email!']);
                } catch (Exception $e) {
                    error_log("PHPMailer Error: " . $mail->ErrorInfo);
                    echo json_encode(['status' => 'error', 'message' => 'Failed to send OTP. Please try again.']);
                }
            } else {
                error_log("SQL Error (insert OTP): " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Error storing OTP. Please try again.']);
            }
        } else {
            error_log("No user found for email=$email");
            echo json_encode(['status' => 'error', 'message' => 'Email not found!']);
        }
        $stmt->close();
        exit();
    } elseif ($_POST['action'] == 'verify_otp') {
        // Verify OTP logic
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $otp = trim(mysqli_real_escape_string($conn, $_POST['otp']));

        error_log("Verifying OTP: email=$email, otp=$otp");

        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];

            $sql = "SELECT otp, expires_at FROM otps WHERE user_id = ? AND otp = ? AND expires_at > NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("is", $user_id, $otp);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $stmt = $conn->prepare("DELETE FROM otps WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                echo json_encode(['status' => 'success', 'message' => 'OTP verified successfully!']);
            } else {
                $sql = "SELECT otp, expires_at FROM otps WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $debug_result = $stmt->get_result();
                if ($debug_result->num_rows > 0) {
                    $debug_row = $debug_result->fetch_assoc();
                    error_log("Debug OTP: otp=" . $debug_row['otp'] . ", Expires: " . $debug_row['expires_at']);
                    if ($debug_row['expires_at'] <= date('Y-m-d H:i:s')) {
                        echo json_encode(['status' => 'error', 'message' => 'OTP has expired! Please request a new one.']);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'Invalid OTP entered!']);
                    }
                } else {
                    error_log("No OTP found for user_id=$user_id");
                    echo json_encode(['status' => 'error', 'message' => 'No OTP found! Please request a new one.']);
                }
            }
        } else {
            error_log("No user found for email=$email");
            echo json_encode(['status' => 'error', 'message' => 'Email not found!']);
        }
        $stmt->close();
        exit();
    } elseif ($_POST['action'] == 'reset_password') {
        // Reset password logic
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $new_password = mysqli_real_escape_string($conn, $_POST['new_password']);

        if (strlen($new_password) < 8 || !preg_match("/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/", $new_password)) {
            echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters long and contain letters and numbers!']);
            exit();
        }

        $sql = "SELECT id FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);

            $sql = "UPDATE users SET password = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $hashedPassword, $user_id);
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Password reset successful! Redirecting to login...']);
            } else {
                error_log("SQL Error (reset password): " . $conn->error);
                echo json_encode(['status' => 'error', 'message' => 'Error updating password. Please try again.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Email not found!']);
        }
        $stmt->close();
        exit();
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
        /* CSS remains unchanged from the original */
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
    body {
      cursor: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="%23D4AF37" opacity="0.5"/></svg>'), auto;
    }
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
            color: #FFF8F0;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
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

        .register-mode .form-wrapper {
            order: 2;
        }

        .register-mode .image-section {
            order: 1;
        }

        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            z-index: 4;
            pointer-events: all;
        }

        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow-hover);
            padding: 20px;
            width: 300px;
            z-index: 5;
        }

        .popup.active, .popup-overlay.active {
            display: block;
        }

        .popup h3 {
            font-family: 'Dancing Script', cursive;
            font-size: 1.5rem;
            color: var(--secondary);
            margin-bottom: 10px;
            text-align: center;
        }

        .popup input {
            width: 100%;
            padding: 6px;
            margin: 4px 0;
            border: 1px solid var(--accent);
            border-radius: 8px;
            font-size: 0.8rem;
            background: var(--light);
        }

        .popup button {
            width: 100%;
            padding: 10px;
            background: var(--gradient-1);
            border: none;
            border-radius: 10px;
            color: var(--white);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            margin-top: 10px;
        }

        .popup button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .popup .close-popup {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            font-size: 1.2rem;
            color: var(--secondary);
        }

        #otp-section, #password-section {
            display: none;
        }

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
            .popup {
                width: 90%;
            }
        }
    </style>
</head>
<body>
    <button style="position: absolute; top: 10px; left: 10px; padding: 8px 16px; font-size: 14px; color: #fff; background: orange; border: none; border-radius: 4px; cursor: pointer; z-index: 6;" onmouseover="this.style.background='orange'" onmouseout="this.style.background='orange'" onclick="window.location.href='index.php'">← Back</button>
    <div class="frosting-bg"></div>
    <div class="sprinkles"></div>
    <div class="sprinkle-burst" id="sprinkle-burst"></div>

    <?php if (!empty($message)): ?>
        <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'not') !== false ? 'error' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="container" id="container">
        <div class="form-wrapper">
            <form class="form active" id="login-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <h2>Login to Golden Treat</h2>
                <input type="email" name="login-email" id="login-email" placeholder="Email" required>
                <input type="password" name="login-password" id="login-password" placeholder="Password" required>
                <input type="hidden" name="action" value="login">
                <button type="submit">Login 🍰</button>
                <div class="forgot-password" onclick="showForgotPasswordPopup()">Forgot Password?</div>
                <div class="switch" onclick="showRegister()">Don't have an account? Register here</div>
            </form>

            <form class="form" id="register-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <h2>Register at Golden Treat</h2>
                <input type="text" name="reg-fullname" id="reg-fullname" placeholder="Full Name" required>
                <input type="email" name="reg-email" id="reg-email" placeholder="Email" required>
                <input type="tel" name="reg-mobile" id="reg-mobile" placeholder="Mobile Number" required maxlength="10" minlength="10" pattern="\d{10}" title="Please enter exactly 10 digits" oninput="this.value=this.value.replace(/\D/g,'')">
                <input type="text" name="reg-address" id="reg-address" placeholder="Address" required>
                <input type="text" name="reg-district" id="reg-district" placeholder="District" required>
                <input type="password" name="reg-password" id="reg-password" placeholder="Password (8+ chars, letters & numbers)" required>
                <input type="password" name="reg-confirm-password" id="reg-confirm-password" placeholder="Confirm Password" required>
                <input type="hidden" name="action" value="register">
                <button type="submit">Register 🥐</button>
                <div class="switch" onclick="showLogin()">Already have an account? Login here</div>
            </form>
        </div>

        <div class="image-section">
            <img src="https://images.unsplash.com/photo-1556741533-6e6a62bd8b49?auto=format&fit=crop&w=1000&q=80" alt="Bakery Cakes">
            <div class="text">
                <h1>Welcome to Golden Treat 🍰</h1>
                <p>Freshly baked happiness delivered daily. Log in or sign up to enjoy our delicious cakes, pastries, and breads!</p>
            </div>
        </div>
    </div>

    <div class="popup-overlay" id="popup-overlay" onclick="closeForgotPasswordPopup()"></div>
    <div class="popup" id="forgot-password-popup">
        <span class="close-popup" onclick="closeForgotPasswordPopup()">×</span>
        <h3>Forgot Password</h3>
        <div id="email-section">
            <input type="email" id="forgot-email" placeholder="Enter your email" required>
            <button onclick="sendOtp()">Send OTP</button>
        </div>
        <div id="otp-section">
            <input type="text" id="forgot-otp" placeholder="Enter 6-digit OTP" required maxlength="6" pattern="\d{6}" title="Please enter exactly 6 digits" oninput="this.value = this.value.replace(/\D/g, '')">
            <button onclick="verifyOtp()">Verify OTP</button>
        </div>
        <div id="password-section">
            <input type="password" id="new-password" placeholder="New Password (8+ chars, letters & numbers)" required>
            <input type="password" id="confirm-new-password" placeholder="Re-enter New Password" required>
            <button onclick="resetPassword()">Reset Password</button>
        </div>
    </div>

    <script>
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

        function showForgotPasswordPopup() {
            document.getElementById('popup-overlay').classList.add('active');
            document.getElementById('forgot-password-popup').classList.add('active');
            document.getElementById('email-section').style.display = 'block';
            document.getElementById('otp-section').style.display = 'none';
            document.getElementById('password-section').style.display = 'none';
            document.getElementById('forgot-email').value = '';
            document.getElementById('forgot-otp').value = '';
            document.getElementById('new-password').value = '';
            document.getElementById('confirm-new-password').value = '';
        }

        function closeForgotPasswordPopup() {
            document.getElementById('popup-overlay').classList.remove('active');
            document.getElementById('forgot-password-popup').classList.remove('active');
        }

        function showMessage(text, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${type}`;
            messageDiv.textContent = text;
            document.body.appendChild(messageDiv);
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 3000);
        }

        async function sendOtp() {
            const email = document.getElementById('forgot-email').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!email || !emailRegex.test(email)) {
                showMessage('Please enter a valid email address!', 'error');
                return;
            }

            try {
                const response = await fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_otp&email=${encodeURIComponent(email)}`
                });
                const text = await response.text();
                console.log('Send OTP Raw response:', text);
                const result = JSON.parse(text);
                showMessage(result.message, result.status);
                if (result.status === 'success') {
                    document.getElementById('email-section').style.display = 'none';
                    document.getElementById('otp-section').style.display = 'block';
                    document.getElementById('password-section').style.display = 'none';
                    document.getElementById('forgot-otp').focus();
                } else {
                    document.getElementById('forgot-email').value = '';
                }
            } catch (error) {
                console.error('Send OTP Error:', error);
                showMessage('Error sending OTP. Please try again.', 'error');
                document.getElementById('forgot-email').value = '';
            }
        }

        async function verifyOtp() {
            const email = document.getElementById('forgot-email').value.trim();
            const otp = document.getElementById('forgot-otp').value.trim();
            if (!otp || otp.length !== 6 || !/^\d{6}$/.test(otp)) {
                showMessage('Please enter a valid 6-digit OTP!', 'error');
                return;
            }

            try {
                const response = await fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_otp&email=${encodeURIComponent(email)}&otp=${encodeURIComponent(otp)}`
                });
                const text = await response.text();
                console.log('Verify OTP Raw response:', text);
                const result = JSON.parse(text);
                showMessage(result.message, result.status);
                if (result.status === 'success') {
                    document.getElementById('email-section').style.display = 'none';
                    document.getElementById('otp-section').style.display = 'none';
                    document.getElementById('password-section').style.display = 'block';
                    document.getElementById('new-password').focus();
                } else {
                    document.getElementById('forgot-otp').value = '';
                }
            } catch (error) {
                console.error('Verify OTP Error:', error);
                showMessage('Error verifying OTP. Please try again.', 'error');
                document.getElementById('forgot-otp').value = '';
            }
        }

        async function resetPassword() {
            const email = document.getElementById('forgot-email').value.trim();
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-new-password').value;

            if (!newPassword || !confirmPassword) {
                showMessage('Please fill in both password fields!', 'error');
                return;
            }

            if (newPassword.length < 8 || !/[A-Za-z].*[0-9]|[0-9].*[A-Za-z]/.test(newPassword)) {
                showMessage('Password must be at least 8 characters long and contain letters and numbers!', 'error');
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-new-password').value = '';
                return;
            }

            if (newPassword !== confirmPassword) {
                showMessage('Passwords do not match! Please try again.', 'error');
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-new-password').value = '';
                return;
            }

            try {
                const response = await fetch('<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reset_password&email=${encodeURIComponent(email)}&new_password=${encodeURIComponent(newPassword)}`
                });
                const text = await response.text();
                console.log('Reset Password Raw response:', text);
                const result = JSON.parse(text);
                showMessage(result.message, result.status);
                if (result.status === 'success') {
                    setTimeout(() => {
                        closeForgotPasswordPopup();
                        showLogin();
                    }, 2000);
                } else {
                    document.getElementById('new-password').value = '';
                    document.getElementById('confirm-new-password').value = '';
                }
            } catch (error) {
                console.error('Reset Password Error:', error);
                showMessage('Error resetting password. Please try again.', 'error');
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-new-password').value = '';
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            createSprinkles();
        });
    </script>
</body>
</html>