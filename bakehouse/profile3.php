<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = filter_var($_POST['fullName'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $mobile = filter_var($_POST['mobile'], FILTER_SANITIZE_STRING);
    $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
    $district = filter_var($_POST['district'], FILTER_SANITIZE_STRING);
    $role = $is_admin && isset($_POST['role']) ? $_POST['role'] : $user['role'];

    if ($full_name && $email && $mobile && $address && $district) {
        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, mobile = ?, address = ?, district = ?, role = ? WHERE id = ?");
        $stmt->execute([$full_name, $email, $mobile, $address, $district, $role, $user_id]);
        $update_success = "Profile updated successfully!";
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $update_error = "Please fill in all required fields.";
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    session_destroy();
    header("Location: home.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bakehouse Premium Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
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

body {
  margin: 0;
  padding: 0;
  font-family: 'Segoe UI', sans-serif;
  background: linear-gradient(135deg, var(--bg) 0%, #fdf5e6 100%);
  min-height: 100vh;
  display: flex;
  justify-content: center;
  padding: 30px 0;
}

.container {
  width: 100%;
  max-width: 1100px;
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  justify-content: center;
}

.profile-card {
  background: var(--card);
  border-radius: var(--radius);
  padding: 40px;
  box-shadow: var(--shadow);
  flex: 1 1 400px;
  min-height: 800px;
  position: relative;
  display: flex;
  flex-direction: column;
}

.welcome-banner {
  background: url('https://images.unsplash.com/photo-1606755962779-253bcd11e5c5?auto=format&fit=crop&w=900&q=80') center/cover no-repeat;
  border-radius: var(--radius);
  height: 180px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 26px;
  font-weight: bold;
  margin-bottom: 25px;
  text-shadow: 0 2px 6px rgba(0,0,0,0.5);
}

.menu-btn {
  position: absolute;
  top: 30px;
  right: 30px;
  background: var(--btn);
  border: none;
  padding: 10px 14px;
  border-radius: var(--radius);
  color: white;
  cursor: pointer;
  font-size: 16px;
  z-index: 10;
}

.side-panel, .settings-panel {
  position: fixed;
  top: 0;
  right: -100%;
  width: 300px;
  height: 100%;
  background: var(--card);
  transition: right 0.3s ease;
  padding: 20px;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.side-panel.active, .settings-panel.active { 
  right: 0; 
  box-shadow: -5px 0 15px rgba(0,0,0,.2);
}

.side-panel h3, .settings-panel h3 { 
  color: var(--ink); 
  margin-bottom: 10px; 
}

.side-panel a, .settings-panel a { 
  text-decoration: none; 
  color: var(--ink-soft); 
  display: flex; 
  align-items: center; 
  gap: 10px; 
  padding: 10px; 
  border-radius: var(--radius); 
  transition: background 0.3s; 
  font-weight: bold;
}

.side-panel a:hover, .settings-panel a:hover { 
  background: var(--btn-hover); 
  color: white; 
}

.close-btn {
  align-self: flex-end;
  background: var(--btn);
  border: none;
  padding: 8px 12px;
  border-radius: var(--radius);
  color: white;
  cursor: pointer;
  font-size: 14px;
  transition: background 0.3s;
}

.close-btn:hover {
  background: var(--btn-hover);
}

.settings-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px;
  border-radius: var(--radius);
  background: var(--line);
  color: var(--ink-soft);
}

.settings-option label {
  font-weight: bold;
}

.settings-option input[type="checkbox"] {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.profile-picture {
  text-align: center;
  margin-bottom: 20px;
}
.profile-picture img {
  width: 140px;
  height: 140px;
  border-radius: 50%;
  object-fit: cover;
  border: 3px solid var(--line);
  transition: transform 0.3s;
}
.profile-picture img:hover { transform: scale(1.05); }
.profile-picture label {
  display: inline-block;
  margin-top: 10px;
  background: var(--btn);
  color: white;
  padding: 8px 12px;
  border-radius: var(--radius);
  cursor: pointer;
  font-size: 14px;
}
.profile-picture input { display: none; }

.profile-details {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
}
.input-box {
  flex: 1 1 45%;
  display: flex;
  flex-direction: column;
}
.input-box label {
  font-weight: bold;
  margin-bottom: 5px;
  color: var(--ink-soft);
}
.input-box input, select {
  padding: 10px;
  border-radius: var(--radius);
  border: 2px solid var(--line);
  outline: none;
}
.input-box input:focus, select:focus { border-color: var(--focus); }

.btn {
  display: inline-block;
  background: var(--btn);
  color: white;
  padding: 12px 25px;
  border-radius: var(--radius);
  border: none;
  cursor: pointer;
  font-size: 16px;
  transition: background 0.3s;
  margin-top: 20px;
  margin-right: 10px;
  align-self: center;
}
.btn:hover { background: var(--btn-hover); }

.orders { margin-top: 30px; }
.orders h3 { color: var(--ink); margin-bottom: 15px; }
.orders table {
  width: 100%;
  border-collapse: collapse;
}
.orders th, .orders td {
  text-align: left;
  padding: 12px;
  border-bottom: 1px solid var(--line);
  color: var(--ink-soft);
}
.orders th { background: var(--chip); }

.analytics { margin-top: 30px; display: flex; flex-wrap: wrap; gap: 20px; }
.analytics .card {
  background: var(--line);
  flex: 1 1 45%;
  padding: 15px;
  border-radius: var(--radius);
  text-align: center;
  color: var(--ink-soft);
  font-weight: bold;
  transition: transform 0.3s;
}
.analytics .card:hover { transform: scale(1.03); }

.social { margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap; }
.social button { flex: 1 1 45%; padding: 12px; border-radius: var(--radius); border: none; cursor: pointer; font-weight: bold; background: var(--btn); color: white; transition: background 0.3s; }
.social button:hover { background: var(--btn-hover); }

.days-since { margin-top: 20px; font-size: 14px; color: var(--ink-soft); }

.order-history {
  max-height: 200px;
  overflow-y: auto;
  margin-top: 15px;
  border: 2px solid var(--line);
  border-radius: var(--radius);
  padding: 5px;
  background: white;
}

@media(max-width: 768px) {
  .input-box { flex: 1 1 100%; }
  .analytics .card { flex: 1 1 100%; }
  .social button { flex: 1 1 100%; }
  .side-panel, .settings-panel { width: 250px; }
}

.error, .success {
  color: var(--ink);
  margin-top: 10px;
  text-align: center;
}
</style>
</head>
<body>
<div class="container">
  <div class="profile-card">
    <button class="menu-btn" onclick="togglePanel()"><i class="fas fa-cog"></i> Menu</button>
    <div class="welcome-banner">Welcome Back, <?php echo htmlspecialchars($user['full_name']); ?>!</div>

    <div class="profile-picture">
      <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=140&q=80" alt="Profile Picture">
      <label for="profile-upload"><i class="fas fa-camera"></i> Change Photo
        <input type="file" id="profile-upload" accept="image/*">
      </label>
    </div>

    <form id="profile-form" method="POST">
      <div class="profile-details">
        <div class="input-box"><label>Full Name</label><input type="text" name="fullName" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly></div>
        <div class="input-box"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly></div>
        <div class="input-box"><label>Mobile Number</label><input type="tel" name="mobile" value="<?php echo htmlspecialchars($user['mobile']); ?>" readonly></div>
        <div class="input-box"><label>Address</label><input type="text" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" readonly></div>
        <div class="input-box"><label>District</label><input type="text" name="district" value="<?php echo htmlspecialchars($user['district']); ?>" readonly></div>
        <div class="input-box"><label>Date Joined</label><input type="text" name="dateJoined" value="<?php echo htmlspecialchars($user['date_joined']); ?>" readonly></div>
        <?php if ($is_admin) { ?>
          <div class="input-box">
            <label>Role</label>
            <select name="role" disabled>
              <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
              <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
            </select>
          </div>
        <?php } ?>
      </div>
      <div class="button-group">
        <button type="button" class="btn" id="edit-btn" onclick="enableEdit()">Edit Profile</button>
        <button type="submit" class="btn" id="update-btn" name="update_profile" style="display: none;">Update Profile</button>
      </div>
      <?php if (isset($update_success)) { ?>
        <div class="success"><?php echo $update_success; ?></div>
      <?php } ?>
      <?php if (isset($update_error)) { ?>
        <div class="error"><?php echo $update_error; ?></div>
      <?php } ?>
    </form>

    <div class="orders">
      <h3>Last 3 Orders</h3>
      <table>
        <thead><tr><th>Order ID</th><th>Product</th><th>Date</th><th>Status</th></tr></thead>
        <tbody>
          <tr><td>#101</td><td>Chocolate Cake</td><td>2025-08-10</td><td>Delivered</td></tr>
          <tr><td>#102</td><td>Blueberry Muffin</td><td>2025-08-08</td><td>Delivered</td></tr>
          <tr><td>#103</td><td>Croissant</td><td>2025-08-05</td><td>Delivered</td></tr>
        </tbody>
      </table>

      <h3>Order History</h3>
      <div class="order-history">
        <p>#100 - Vanilla Cake - 2025-07-30 - Delivered</p>
        <p>#99 - Bread Loaf - 2025-07-28 - Delivered</p>
        <p>#98 - Muffins - 2025-07-25 - Delivered</p>
        <p>#97 - Cupcakes - 2025-07-22 - Delivered</p>
        <p>#96 - Chocolate Tart - 2025-07-20 - Delivered</p>
      </div>
    </div>

    <div class="analytics">
      <div class="card">Days since last login: <span id="days-since"></span></div>
      <div class="card">Total Orders Placed: 25</div>
      <div class="card">Total Amount Spent: $450</div>
      <div class="card">Next Recommended Product: Red Velvet Cake</div>
    </div>

    <div class="social">
      <button><i class="fas fa-share-alt"></i> Share Profile</button>
      <button><i class="fas fa-star"></i> Reviews & Ratings</button>
      <button onclick="downloadInvoice()"><i class="fas fa-download"></i> Download Invoice</button>
    </div>
  </div>
</div>

<div class="side-panel" id="sidePanel">
  <button class="close-btn" onclick="closePanel()"><i class="fas fa-times"></i> Close</button>
  <h3>Settings</h3>
  <a href="#" onclick="toggleSettingsPanel(); return false;"><i class="fas fa-user-cog"></i> Profile Settings</a>
  <a href="#" onclick="confirmLogout(); return false;"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="settings-panel" id="settingsPanel">
  <button class="close-btn" onclick="closeSettingsPanel()"><i class="fas fa-times"></i> Close</button>
  <h3>Profile Settings</h3>
  <div class="settings-option">
    <label for="notifications">Enable Notifications</label>
    <input type="checkbox" id="notifications" checked>
  </div>
  <div class="settings-option">
    <label for="darkMode">Dark Mode</label>
    <input type="checkbox" id="darkMode">
  </div>
  <div class="settings-option">
    <label for="emailPrefs">Receive Promotional Emails</label>
    <input type="checkbox" id="emailPrefs" checked>
  </div>
  <form method="POST">
    <button type="submit" name="delete_account" class="btn"><i class="fas fa-trash-alt"></i> Delete Account</button>
  </form>
</div>

<script>
// Days since last login (client-side calculation for demo)
const lastLoginDate = new Date("2025-08-01");
const today = new Date();
const diffTime = Math.abs(today - lastLoginDate);
const diffDays = Math.floor(diffTime / (1000*60*60*24));
document.getElementById("days-since").textContent = diffDays + " day(s)";

// Enable editing of profile fields
function enableEdit() {
  const inputs = document.querySelectorAll('#profile-form input:not([name="dateJoined"]), #profile-form select');
  inputs.forEach(input => input.removeAttribute('readonly'));
  inputs.forEach(input => input.removeAttribute('disabled'));
  document.getElementById('edit-btn').style.display = 'none';
  document.getElementById('update-btn').style.display = 'inline-block';
}

// Side panel toggle
function togglePanel() {
  document.getElementById('sidePanel').classList.toggle('active');
  document.getElementById('settingsPanel').classList.remove('active');
}

// Close side panel
function closePanel() {
  document.getElementById('sidePanel').classList.remove('active');
}

// Toggle profile settings sub-panel
function toggleSettingsPanel() {
  document.getElementById('settingsPanel').classList.toggle('active');
}

// Close profile settings sub-panel
function closeSettingsPanel() {
  document.getElementById('settingsPanel').classList.remove('active');
}

// Confirm logout
function confirmLogout() {
  if (confirm('Are you sure you want to log out?')) {
    window.location.href = 'logout.php';
  }
}

// Download invoice functionality
function downloadInvoice() {
  try {
    if (!window.jspdf || !window.jspdf.jsPDF) {
      alert('Error: jsPDF library not loaded. Please try again later.');
      return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const profileData = {
      fullName: '<?php echo addslashes($user['full_name']); ?>',
      email: '<?php echo addslashes($user['email']); ?>',
      address: '<?php echo addslashes($user['address']); ?>',
      district: '<?php echo addslashes($user['district']); ?>',
      dateJoined: '<?php echo addslashes($user['date_joined']); ?>'
    };

    const orderRows = document.querySelectorAll('.orders table tbody tr');
    const orders = Array.from(orderRows).map(row => ({
      id: row.cells[0].textContent,
      product: row.cells[1].textContent,
      date: row.cells[2].textContent,
      status: row.cells[3].textContent
    }));

    const totalSpentElement = document.querySelector('.analytics .card:nth-child(3)');
    const totalSpent = totalSpentElement ? totalSpentElement.textContent.replace('Total Amount Spent: ', '') : '$0';

    doc.setFontSize(18);
    doc.text('Bakehouse Premium Invoice', 20, 20);
    doc.setFontSize(12);
    doc.text('Customer Details:', 20, 40);
    doc.text(`Name: ${profileData.fullName}`, 20, 50);
    doc.text(`Email: ${profileData.email}`, 20, 60);
    doc.text(`Address: ${profileData.address}, ${profileData.district}`, 20, 70);
    doc.text(`Date Joined: ${profileData.dateJoined}`, 20, 80);
    doc.text('Recent Orders:', 20, 100);
    const tableColumn = ['Order ID', 'Product', 'Date', 'Status'];
    const tableRows = orders.map(order => [order.id, order.product, order.date, order.status]);
    doc.autoTable({
      startY: 110,
      head: [tableColumn],
      body: tableRows,
      theme: 'grid',
      styles: { fontSize: 10 },
      headStyles: { fillColor: [209, 154, 109] },
      margin: { left: 20, right: 20 }
    });
    doc.text(`Total Amount Spent: ${totalSpent}`, 20, doc.lastAutoTable.finalY + 20);
    doc.setFontSize(10);
    doc.text('Thank you for your business!', 20, doc.lastAutoTable.finalY + 40);
    doc.text('Bakehouse Premium', 20, doc.lastAutoTable.finalY + 50);
    doc.save(`Invoice_${profileData.fullName}_${new Date().toISOString().split('T')[0]}.pdf`);
  } catch (error) {
    console.error('Error generating invoice:', error);
    alert('An error occurred while generating the invoice. Please try again.');
  }
}
</script>
</body>
</html>