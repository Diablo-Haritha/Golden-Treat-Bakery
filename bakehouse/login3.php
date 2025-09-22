<?php
session_start();
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $email = filter_var($_POST['login-email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['login-password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            header("Location: home.php");
            exit();
        } else {
            $login_error = "Incorrect email or password!";
        }
    } elseif (isset($_POST['register'])) {
        $full_name = filter_var($_POST['reg-fullname'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['reg-email'], FILTER_SANITIZE_EMAIL);
        $mobile = filter_var($_POST['reg-mobile'], FILTER_SANITIZE_STRING);
        $address = filter_var($_POST['reg-address'], FILTER_SANITIZE_STRING);
        $district = filter_var($_POST['reg-district'], FILTER_SANITIZE_STRING);
        $password = $_POST['reg-password'];
        $confirm_password = $_POST['reg-confirm-password'];

        if ($password !== $confirm_password) {
            $register_error = "Passwords do not match!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $register_error = "Email already exists!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, mobile, address, district, password, date_joined) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$full_name, $email, $mobile, $address, $district, $hashed_password, date('Y-m-d')]);
                $register_success = "Registration successful! Please log in.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bakehouse Login & Register</title>
<style>
:root {
  --bg: #fff4e1;
  --card: #f3e2d8;
  --ink: #8b5b29;
  --ink-soft: #c69c6d;
  --btn: #d19a6d;
  --btn-hover: #eab8b8;
  --line: #f5d19d;
  --chip: #ffd1dc;
  --focus: #c37960;
  --shadow: 0 8px 22px rgba(0,0,0,.08);
  --radius: 16px;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: 'Segoe UI', sans-serif;
}

body {
  background: var(--bg);
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
}

.container {
  display: flex;
  background: var(--card);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  overflow: hidden;
  max-width: 1000px;
  width: 100%;
  height: 600px;
  position: relative;
}

.form-wrapper {
  flex: 1;
  padding: 40px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  position: relative;
  overflow: hidden;
  order: 1;
}

.form {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  padding: 40px;
  opacity: 0;
  transform: translateX(50px);
  transition: all 0.5s ease;
  pointer-events: none;
}

.form.active {
  opacity: 1;
  transform: translateX(0);
  pointer-events: all;
}

.form h2 {
  margin-bottom: 20px;
  color: var(--ink);
}

.form input {
  padding: 12px;
  margin: 8px 0;
  border: 2px solid var(--line);
  border-radius: var(--radius);
  outline: none;
  font-size: 14px;
  width: 100%;
}

.form input:focus {
  border-color: var(--focus);
}

.form button {
  padding: 12px;
  background: var(--btn);
  color: white;
  border: none;
  border-radius: var(--radius);
  margin-top: 15px;
  font-size: 16px;
  cursor: pointer;
  transition: background 0.3s ease;
  width: 100%;
}

.form button:hover {
  background: var(--btn-hover);
}

.switch {
  margin-top: 15px;
  font-size: 14px;
  text-align: center;
  color: var(--ink-soft);
  cursor: pointer;
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
}

.image-section img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0;
  left: 0;
}

.image-section .text {
  position: relative;
  z-index: 1;
  padding: 20px;
}

.image-section h1 {
  font-size: 28px;
  margin-bottom: 10px;
  color: var(--ink);
}

.image-section p {
  font-size: 16px;
  line-height: 1.4;
  background: var(--chip);
  color: var(--ink);
  padding: 10px;
  border-radius: var(--radius);
}

.register-mode .form-wrapper {
  order: 2;
}

.register-mode .image-section {
  order: 1;
}

.error, .success {
  color: var(--ink);
  margin-top: 10px;
  text-align: center;
}

@media(max-width: 768px) {
  .container {
    flex-direction: column;
    height: auto;
  }
  .image-section {
    height: 250px;
  }
  .register-mode .form-wrapper {
    order: 1;
  }
  .register-mode .image-section {
    order: 2;
  }
}
</style>
</head>
<body>
<div class="container" id="container">
  <div class="form-wrapper">
    <form class="form active" id="login-form" method="POST">
      <h2>Login to Bakehouse</h2>
      <input type="email" name="login-email" placeholder="Email" required>
      <input type="password" name="login-password" placeholder="Password" required>
      <button type="submit" name="login">Login</button>
      <div class="switch" onclick="showRegister()">Don't have an account? Register here</div>
      <?php if (isset($login_error)) { ?>
        <div class="error"><?php echo $login_error; ?></div>
      <?php } ?>
    </form>

    <form class="form" id="register-form" method="POST">
      <h2>Register at Bakehouse</h2>
      <input type="text" name="reg-fullname" placeholder="Full Name" required>
      <input type="email" name="reg-email" placeholder="Email" required>
      <input type="tel" name="reg-mobile" placeholder="Mobile Number" required>
      <input type="text" name="reg-address" placeholder="Address" required>
      <input type="text" name="reg-district" placeholder="District" required>
      <input type="password" name="reg-password" placeholder="Password" required>
      <input type="password" name="reg-confirm-password" placeholder="Confirm Password" required>
      <button type="submit" name="register">Register</button>
      <div class="switch" onclick="showLogin()">Already have an account? Login here</div>
      <?php if (isset($register_error)) { ?>
        <div class="error"><?php echo $register_error; ?></div>
      <?php } ?>
      <?php if (isset($register_success)) { ?>
        <div class="success"><?php echo $register_success; ?></div>
      <?php } ?>
    </form>
  </div>

  <div class="image-section">
    <img src="https://images.unsplash.com/photo-1556741533-6e6a62bd8b49?auto=format&fit=crop&w=1000&q=80" alt="Bakery Cakes">
    <div class="text">
      <h1>Welcome to Sweet Crumbs Bakehouse 🍰</h1>
      <p>Freshly baked happiness delivered daily. Log in or sign up to enjoy our delicious cakes, pastries, and breads!</p>
    </div>
  </div>
</div>

<script>
function showRegister() {
  document.getElementById("login-form").classList.remove("active");
  document.getElementById("register-form").classList.add("active");
  document.getElementById("container").classList.add("register-mode");
}

function showLogin() {
  document.getElementById("register-form").classList.remove("active");
  document.getElementById("login-form").classList.add("active");
  document.getElementById("container").classList.remove("register-mode");
}
</script>
</body>
</html>