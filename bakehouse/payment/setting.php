<?php
session_start();

// ---------- LOGIN ----------
$admin_user = "admin";
$admin_pass = "12345"; // 🔐 change this password

if (isset($_POST['login'])) {
    if ($_POST['username'] === $admin_user && $_POST['password'] === $admin_pass) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = "❌ Invalid login!";
    }
}

// Require login
if (!isset($_SESSION['logged_in'])) {
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<h2>Admin Login</h2>
<form method="post">
    <input type="text" name="username" placeholder="Username"><br><br>
    <input type="password" name="password" placeholder="Password"><br><br>
    <button type="submit" name="login">Login</button>
</form>
<p style="color:red;"><?= isset($error) ? $error : '' ?></p>
</body>
</html>
<?php exit; } ?>

<?php
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// ---------- UPDATE SETTINGS ----------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save'])) {
    $shop_name    = $conn->real_escape_string($_POST['shop_name']);
    $shop_slogan  = $conn->real_escape_string($_POST['shop_slogan']);
    $shop_tel     = $conn->real_escape_string($_POST['shop_tel']);
    $shop_email   = $conn->real_escape_string($_POST['shop_email']);
    $shop_address = $conn->real_escape_string($_POST['shop_address']);
    $thank_note   = $conn->real_escape_string($_POST['thank_note']);
    $vat_percent  = floatval($_POST['vat_percent']);

    $conn->query("UPDATE settings 
                  SET shop_name='$shop_name', shop_slogan='$shop_slogan',
                      shop_tel='$shop_tel', shop_email='$shop_email',
                      shop_address='$shop_address', thank_note='$thank_note',
                      vat_percent=$vat_percent
                  WHERE id=1");
    $msg = "✅ Settings updated successfully!";
}

// ---------- FETCH SETTINGS ----------
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
<title>Update Shop Settings</title>
<style>

body {
  font-family: "Poppins", Arial, sans-serif;
  background-color: #fff5f5;
  margin: 0;
  padding: 0;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
}

.container {
  width: 90%;
  max-width: 700px;
  background: #ffffff;
  padding: 40px 50px;
  border-radius: 14px;
  box-shadow: 0 6px 20px rgba(255, 0, 0, 0.1);
}

h2 {
  text-align: center;
  color: #b30000;
  margin-bottom: 30px;
  font-size: 28px;
  font-weight: 600;
  letter-spacing: 0.5px;
}

form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

label {
  font-weight: 600;
  color: #333;
  margin-bottom: 5px;
}

input[type="text"],
input[type="number"],
textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1.8px solid #e2e2e2;
  border-radius: 10px;
  font-size: 16px;
  background-color: #fffaf9;
  transition: all 0.25s ease;
}

input[type="text"]:focus,
input[type="number"]:focus,
textarea:focus {
  border-color: #d62828;
  box-shadow: 0 0 5px rgba(214, 40, 40, 0.3);
  outline: none;
  background-color: #fff;
}

textarea {
  resize: vertical;
  min-height: 90px;
}

button[name="save"] {
  background: linear-gradient(90deg, #d62828, #b91c1c);
  color: #fff;
  border: none;
  padding: 12px 15px;
  border-radius: 10px;
  font-size: 17px;
  font-weight: 600;
  letter-spacing: 0.3px;
  cursor: pointer;
  transition: all 0.25s ease;
}

button[name="save"]:hover {
  background: linear-gradient(90deg, #b91c1c, #8b0000);
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(185, 28, 28, 0.3);
}

p {
  text-align: center;
  font-size: 15px;
  color: #0a0a0a;
}

.footer-links {
  text-align: center;
  margin-top: 25px;
}

a {
  color: #d62828;
  text-decoration: none;
  font-weight: 600;
  transition: color 0.2s ease;
}

a:hover {
  color: #8b0000;
  text-decoration: underline;
}

/* Responsive tweaks */
@media (max-width: 600px) {
  .container {
    padding: 25px 20px;
  }
  h2 {
    font-size: 24px;
  }
  button[name="save"] {
    font-size: 16px;
  }
}


</style>
  <link rel="stylesheet" href="style1.css">
</head>
<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Sales Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." />
      </div>
    </div>
    <div class="header-right">
      
        <a class="btn" href="?<?= http_build_query(array_merge($_GET,[" export"=>"csv"])) ?>">⬇CSV</a>
      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Sales Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Sales</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn " onclick="window.location.href='index.php'">Bill🧾    </button>
          <button class="tab-btn" onclick="window.location.href='save_bill.php'">All Bills</button>
          <button class="tab-btn active" onclick="window.location.href='setting.php'">⚙️Setting</button>

        </div>
      </nav>
    </aside>
<h2>Shop Settings</h2>
<?php if (isset($msg)) echo "<p style='color:green;'>$msg</p>"; ?>
<form method="post">
<div class="container">
  <h2>Shop Settings</h2>
  <?php if (isset($msg)) echo "<p style='color:green;'>$msg</p>"; ?>
  
  <form method="post">
    <label>Shop Name</label>
    <input type="text" name="shop_name" value="<?= htmlspecialchars($settings['shop_name']) ?>">

    <label>Slogan</label>
    <input type="text" name="shop_slogan" value="<?= htmlspecialchars($settings['shop_slogan']) ?>">

    <label>Telephone</label>
    <input type="text" name="shop_tel" value="<?= htmlspecialchars($settings['shop_tel']) ?>">

    <label>Email</label>
    <input type="text" name="shop_email" value="<?= htmlspecialchars($settings['shop_email']) ?>">

    <label>Address</label>
    <textarea name="shop_address"><?= htmlspecialchars($settings['shop_address']) ?></textarea>

    <label>Thank Note</label>
    <textarea name="thank_note"><?= htmlspecialchars($settings['thank_note']) ?></textarea>

    <label>VAT %</label>
    <input type="number" step="0.01" name="vat_percent" value="<?= $settings['vat_percent'] ?>">

    <button type="submit" name="save">💾 Save Settings</button>
  </form>

  <div class="footer-links">
    <a href="save_bill.php">⬅️ Back to Bills</a> | 
    <a href="logout.php">🚪 Logout</a>
  </div>
</div>

</body>
</html>
