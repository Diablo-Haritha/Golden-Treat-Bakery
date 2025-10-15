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
$msg = '';
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Shop Settings</title>
  <link rel="stylesheet" href="style1.css">
  <style>
    :root {
      --brand: #e10000;
      --ink: #111827;
      --paper: #fff;
      --muted: #6b7280;
      --soft: #e5e7eb;
      --warn: #ffc107;
      --danger: #dc3545;
      --primary: #007bff;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: linear-gradient(135deg, #f4f6f9 0%, #e9ecef 100%);
      color: #0f172a;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* Fixed Header */
    .header {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      padding: 12px 16px;
      z-index: 1000;
      height: 70px;
      box-sizing: border-box;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .header-left img {
      width: 56px;
      height: auto;
      border-radius: 8px;
      display: block;
      box-shadow: 2px 2px 5px rgba(0, 0, 0, .15);
    }

    .header-middle {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      margin: 0 16px;
      max-width: 720px;
    }

    .header-middle-title {
      font-weight: 800;
      font-size: 26px;
      color: var(--brand);
      white-space: nowrap;
    }

    .search-bar {
      flex: 1;
      display: flex;
    }

    .search-bar input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      transition: border-color 0.3s;
    }

    .search-bar input:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(225, 0, 0, 0.1);
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .role-btn {
      background: #111827;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .role-btn:hover {
      opacity: .9;
    }

    .user-icon {
      width: 28px;
      height: 28px;
      background: linear-gradient(135deg, #bbb, #888);
      border-radius: 50%;
    }

    /* Fixed Sidebar */
    .sidebar {
      position: fixed;
      top: 70px;
      left: 0;
      width: 260px;
      height: calc(100vh - 70px);
      background: linear-gradient(180deg, #fff 0%, #f8f9fa 100%);
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-right: 1px solid #e5e7eb;
      overflow-y: auto;
      z-index: 999;
      box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    }

    .sidebar h1 {
      text-align: center;
      font-size: 20px;
      margin-bottom: 8px;
      color: #0f172a;
      font-weight: 700;
    }

    .sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    /* Sidebar groups */
    .salesbtn {
      background: linear-gradient(135deg, var(--brand), #c90000);
      border: none;
      border-radius: 10px;
      color: #fff;
      font-weight: 800;
      font-size: 22px;
      padding: 12px;
      text-align: center;
      transition: transform 0.2s;
    }

    .salesbtn:hover {
      transform: translateY(-2px);
    }

    .otherbtn button {
      border: none;
      border-radius: 10px;
      color: #fff;
      cursor: pointer;
      padding: 10px 12px;
      font-weight: 700;
      gap: 10px;
      transition: filter 0.2s;
    }

    .otherbtn button:hover {
      filter: brightness(1.1);
    }

    .salebtn button {
      background: var(--brand);
      border: none;
      text-align: left;
      padding: 10px;
      margin: 10px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 14px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      color: #fff;
      transition: all 0.2s;
    }

    .salebtn button:hover {
      background: #c90000;
      transform: translateX(5px);
    }

    .Sbtn {
      background: #e37200;
    }

    .Ubtn {
      background: #9c0dc7;
    }

    .Bbtn {
      background: #edcd00;
      color: #000;
    }

    .sidebar hr {
      margin: 8px 0;
      border: none;
      border-top: 1px solid #e5e7eb;
    }

    .sidebar p {
      font-size: 12px;
      color: #6b7280;
      font-weight: 700;
      margin: 0 0 5px 0;
    }

    /* Sales Management sub-tabs */
    .salebtn {
      display: flex;
      flex-direction: column;
    }

    .salebtn .tab-btn {
      background: var(--brand);
      border: none;
      border-radius: 10px;
      color: #fff;
      cursor: pointer;
      padding: 10px 12px;
      font-weight: 700;
      text-align: left;
      transition: all 0.2s;
    }

    .salebtn .tab-btn+.tab-btn {
      margin-top: 8px;
    }

    .salebtn .tab-btn.active {
      outline: 3px solid #e10000;
      background: #fff;
      color: var(--brand);
      box-shadow: 0 2px 5px rgba(225,0,0,0.2);
    }

    /* Main Content Area - Adjusted for Fixed Elements */
    .layout {
      margin-top: 70px;
      margin-left: 260px;
      flex: 1;
      min-height: calc(100vh - 70px);
      display: flex;
      flex-direction: column;
    }

    .free-area {
      flex: 1;
      background: #f3f4f6;
      padding: 24px;
      overflow: auto;
      min-height: 100%;
    }

    /* Settings Form Styles */
    .settings-container {
      max-width: 700px;
      margin: 0 auto;
      background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
      animation: fadeIn 0.5s ease;
    }

    .settings-container h2 {
      text-align: center;
      color: var(--brand);
      margin-bottom: 30px;
      font-size: 28px;
      font-weight: 700;
      letter-spacing: 1px;
      text-shadow: 0 2px 4px rgba(225,0,0,0.1);
    }

    .settings-form {
      display: grid;
      gap: 20px;
    }

    .form-group {
      display: flex;
      flex-direction: column;
    }

    .form-group label {
      font-weight: 600;
      color: var(--ink);
      margin-bottom: 8px;
      font-size: 14px;
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 16px;
      background: #fff;
      transition: all 0.3s ease;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .form-group input:focus,
    .form-group textarea:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(225,0,0,0.1), 0 2px 8px rgba(225,0,0,0.1);
      outline: none;
      transform: translateY(-1px);
    }

    .form-group textarea {
      resize: vertical;
      min-height: 80px;
      font-family: inherit;
    }

    .save-btn {
      background: linear-gradient(135deg, var(--brand), #c90000);
      color: #fff;
      border: none;
      padding: 14px 24px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 12px rgba(225,0,0,0.2);
      align-self: center;
      margin-top: 10px;
    }

    .save-btn:hover {
      background: linear-gradient(135deg, #c90000, var(--brand));
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(225,0,0,0.3);
    }

    .msg {
      padding: 12px 20px;
      border-radius: 10px;
      text-align: center;
      font-weight: 600;
      margin-bottom: 20px;
      animation: slideIn 0.3s ease;
    }

    .msg.success {
      background: linear-gradient(135deg, #d4edda, #c3e6cb);
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .footer-links {
      text-align: center;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e5e7eb;
    }

    .footer-links a {
      color: var(--brand);
      text-decoration: none;
      font-weight: 600;
      margin: 0 15px;
      transition: color 0.2s;
    }

    .footer-links a:hover {
      color: #c90000;
      text-decoration: underline;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Responsive */
    @media (max-width: 768px) {
      .settings-container {
        padding: 20px;
        margin: 10px;
      }

      .settings-form {
        gap: 15px;
      }

      .footer-links a {
        display: block;
        margin: 10px 0;
      }
    }
  </style>
</head>
<body> 
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Sales Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." /></div>
    </div>
    <div class="header-right">

      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Sales Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Bill</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>
        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn" onclick="window.location.href='index.php'">Bill🧾</button>
          <button class="tab-btn" onclick="window.location.href='save_bill.php'">All Bills</button>
          <button class="tab-btn active" onclick="window.location.href='setting.php'">⚙️Setting</button>
        </div>
      </nav>
    </aside>
    <div class="free-area">
      <div class="settings-container">
        <h2>⚙️ Shop Settings</h2>
        <?php if ($msg): ?>
          <div class="msg success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <form method="post" class="settings-form">
          <div class="form-group">
            <label for="shop_name">Shop Name</label>
            <input type="text" id="shop_name" name="shop_name" value="<?= htmlspecialchars($settings['shop_name']) ?>" required>
          </div>

          <div class="form-group">
            <label for="shop_slogan">Slogan</label>
            <input type="text" id="shop_slogan" name="shop_slogan" value="<?= htmlspecialchars($settings['shop_slogan']) ?>">
          </div>

          <div class="form-group">
            <label for="shop_tel">Telephone</label>
            <input type="text" id="shop_tel" name="shop_tel" value="<?= htmlspecialchars($settings['shop_tel']) ?>">
          </div>

          <div class="form-group">
            <label for="shop_email">Email</label>
            <input type="email" id="shop_email" name="shop_email" value="<?= htmlspecialchars($settings['shop_email']) ?>">
          </div>

          <div class="form-group">
            <label for="shop_address">Address</label>
            <textarea id="shop_address" name="shop_address"><?= htmlspecialchars($settings['shop_address']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="thank_note">Thank You Note</label>
            <textarea id="thank_note" name="thank_note"><?= htmlspecialchars($settings['thank_note']) ?></textarea>
          </div>

          <div class="form-group">
            <label for="vat_percent">VAT (%)</label>
            <input type="number" step="0.01" id="vat_percent" name="vat_percent" value="<?= $settings['vat_percent'] ?>" min="0" max="100">
          </div>

          <button type="submit" name="save" class="save-btn">💾 Save Settings</button>
        </form>

        <div class="footer-links">
          <a href="save_bill.php">⬅️ Back to All Bills</a> | 
          <a href="logout.php">🚪 Logout</a>
        </div>
      </div>
    </div>
  </div>
</body>
</html>