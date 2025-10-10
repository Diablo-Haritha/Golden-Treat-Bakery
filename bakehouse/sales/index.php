<?php
// ---------- DATABASE CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// ---------- FETCH SALES with filters ----------
$where = "1=1";
$params = [];
$types = "";

if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    if (strtotime($from) && strtotime($to) && $from <= $to) {
        $where .= " AND date BETWEEN ? AND ?";
        $params[] = $from;
        $params[] = $to;
        $types .= "ss";
    }
}
if (!empty($_GET['status']) && in_array($_GET['status'], ['Pending','Paid','Cancelled','Returned'])) {
    $where .= " AND status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}
if (!empty($_GET['customer'])) {
    $where .= " AND customer LIKE ?";
    $params[] = "%" . $_GET['customer'] . "%";
    $types .= "s";
}

$sql = "SELECT id, date, customer, user_id, quantity, total, status, staff FROM sales WHERE $where ORDER BY id DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$error = "";

// ---------- EDIT SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sale'])) {
    $id = (int)($_POST['id'] ?? 0);
    $date = trim($_POST['date'] ?? '');
    $customer_name = trim($_POST['customer'] ?? '');
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $total = (float)($_POST['total'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';

    if (!$id || !$date || !$customer_name || $total <= 0 || !strtotime($date)) {
        $error = "Invalid input data.";
    } else {
        // Find if customer exists
        $user_id = null;
        $stmt2 = $conn->prepare("SELECT id FROM users WHERE LOWER(full_name) = LOWER(?)");
        $stmt2->bind_param("s", $customer_name);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($row2 = $res2->fetch_assoc()) {
            $user_id = $row2['id'];
        }
        $stmt2->close();

        $stmt3 = $conn->prepare("UPDATE sales SET date=?, customer=?, user_id=?, quantity=?, total=?, status=? WHERE id=?");
        $stmt3->bind_param("ssiiisi", $date, $customer_name, $user_id, $quantity, $total, $status, $id);
        if ($stmt3->execute()) {
            header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        } else {
            $error = "Failed to update sale.";
        }
        $stmt3->close();
    }
}

// ---------- DELETE SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $stmt4 = $conn->prepare("DELETE FROM sales WHERE id=?");
        $stmt4->bind_param("i", $id);
        $stmt4->execute();
        $stmt4->close();
    }
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ---------- ADD SALE ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'add_sale') {
    $date = trim($_POST['date'] ?? '');
    $customer_name = trim($_POST['customer'] ?? '');
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $total = (float)($_POST['total'] ?? 0);
    $status = $_POST['status'] ?? 'Pending';
    $staff = "Admin";  // or from session

    if (!$date || !$customer_name || $total <= 0 || !strtotime($date)) {
        $error = "Please fill all required fields correctly.";
    } else {
        $user_id = null;
        $stmt5 = $conn->prepare("SELECT id FROM users WHERE LOWER(full_name) = LOWER(?)");
        $stmt5->bind_param("s", $customer_name);
        $stmt5->execute();
        $res5 = $stmt5->get_result();
        if ($row5 = $res5->fetch_assoc()) {
            $user_id = $row5['id'];
        }
        $stmt5->close();

        if (!$user_id && !empty($_POST['customer_email'])) {
            // Create new user
            $email = trim($_POST['customer_email']);
            $mobile = trim($_POST['customer_mobile'] ?? '');
            $address = trim($_POST['customer_address'] ?? '');
            $district = trim($_POST['customer_district'] ?? '');

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address.";
            } else {
                $hashedPass = password_hash("gt_" . bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                $stmt6 = $conn->prepare("INSERT INTO users (full_name, email, mobile, address, district, role, date_joined, status, password) VALUES (?, ?, ?, ?, ?, 'customer', CURDATE(), 'Active', ?)");
                $stmt6->bind_param("ssssss", $customer_name, $email, $mobile, $address, $district, $hashedPass);
                if ($stmt6->execute()) {
                    $user_id = $stmt6->insert_id;
                } else {
                    $error = "Failed to create customer. Email may already be in use.";
                }
                $stmt6->close();
            }
        }

        if (!$error) {
            $stmt7 = $conn->prepare("INSERT INTO sales (date, customer, user_id, quantity, total, status, staff) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt7->bind_param("ssiiiss", $date, $customer_name, $user_id, $quantity, $total, $status, $staff);
            if ($stmt7->execute()) {
                header("Location: index.php");
                exit;
            } else {
                $error = "Failed to save sale.";
            }
            $stmt7->close();
        }
    }
}

// ---------- STATS ----------
$stats = [];
$qarr = [
    "todaySales"   => "SELECT COUNT(*) AS val FROM sales WHERE date = CURDATE()",
    "totalOrders"  => "SELECT COUNT(*) AS val FROM sales",
    "totalCus"     => "SELECT COUNT(DISTINCT customer) AS val FROM sales",
    "todayRevenue" => "SELECT IFNULL(SUM(total),0) AS val FROM sales WHERE date = CURDATE()"
];
foreach ($qarr as $k => $q) {
    $r = $conn->query($q)->fetch_assoc();
    $stats[$k] = $r['val'];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales - Golden Treat Bakery</title>
  <link rel="stylesheet" href="../style1.css">   <!-- adjust path -->
  <style>
    .addform { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); z-index:1000; align-items: center; justify-content: center; }
    .addform-content { background: #fff; padding:20px; border-radius:8px; width:90%; max-width:500px; max-height:90vh; overflow-y:auto; position:relative; }
    .close { position: absolute; top:10px; right:15px; font-size:24px; cursor:pointer; }
    #suggestions { position: absolute; background: #fff; border:1px solid #ccc; z-index:2000; max-height:150px; overflow-y:auto; width: calc(100% - 40px); }
    #suggestions div { padding:8px; cursor:pointer; }
    #suggestions div:hover { background:#f0f8ff; }
    .error-alert { position: fixed; top:20px; right:20px; background:#ffdddd; padding:10px; border:1px solid red; border-radius:4px; }
    .badge { padding:4px 8px; border-radius:4px; color:#fff; }
    .Paid { background: #4CAF50; }
    .Pending { background: #ff9800; }
    .Cancelled { background: #f44336; }
    .Returned { background: #607D8B; }
    :root {
  --brand: #30b6a2;
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
  background: #f4f6f9;
  color: #0f172a;
  min-height: 100vh;
  overflow-x: hidden; /* Prevent horizontal scroll */
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

  z-index: 1000; /* High z-index to stay on top */
  height: 70px; /* Fixed height for consistency */
  box-sizing: border-box;
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
  top: 70px; /* Below header height */
  left: 0;
  width: 260px;
  height: calc(100vh - 70px); /* Full height minus header */
  background: #fff;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-right: 1px solid #e5e7eb;
  overflow-y: auto; /* Allow scroll inside sidebar if needed, but header/sidebar fixed */
  z-index: 999;
}

.sidebar h1 {
  text-align: center;
  font-size: 20px;
  margin-bottom: 8px;
  color: #0f172a;
}

.sidebar nav {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

/* Sidebar groups */
.salesbtn {
  background: var(--brand);
  border: none;
  border-radius: 10px;
  color: #fff;
  font-weight: 800;
  font-size: 22px;
  padding: 12px;
  text-align: center;
}

.otherbtn button {
  border: none;
  border-radius: 10px;
  color: #fff;
  cursor: pointer;
  padding: 10px 12px;
  font-weight: 700;
  gap: 10px;
}

.salebtn button {
  background: #30b6a2;
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
}

.Sbtn {
  background: #e37200;
  margin-left: 10px;
}

.Ubtn {
  background: #9c0dc7;
}

.Bbtn {
  background: #edcd00;
}

.otherbtn button:hover {
  filter: brightness(1.1);
}

.sidebar hr {
  margin: 8px 0;
}

.sidebar p {
  font-size: 12px;
  color: #6b7280;
  font-weight: 700;
}

.salebtn button {
  background: var(--brand);
  text-align: left;
}

.salebtn button.active {
  outline: 3px solid rgba(48, 182, 162, .35);
}

.sidebar hr {
  margin: 8px 0;
}

.sidebar p {
  font-size: 12px;
  color: #6b7280;
  font-weight: 700;
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
}

.salebtn .tab-btn + .tab-btn {
  margin-top: 8px;
}

.salebtn .tab-btn.active {
  outline: 3px solid rgba(48, 182, 162, .35);
  background: #fff;
  color: var(--brand);
}

/* Main Content Area - Adjusted for Fixed Elements */
.layout {
  margin-top: 70px; /* Space for header */
  margin-left: 260px; /* Space for sidebar */
  flex: 1;
  min-height: calc(100vh - 70px);
  display: flex;
  flex-direction: column;
}

.free-area {
  flex: 1;
  background: #f3f4f6;
  padding: 24px;
  overflow: auto; /* Main content scrolls */
  min-height: 100%;
}

/* Rest of the CSS remains the same */
.panel {
  display: none;
}

.panel.active {
  display: block;
}

.content {
  background: #30b6a2;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.contents {
  background: #e7ebea;
  border-radius: 14px;
  padding: 18px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  
}


.card {
  background: #fff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
  text-align: center;
}

.card h3 {
  font-size: 14px;
  color: #374151;
}

.card p {
  font-size: 22px;
  font-weight: 800;
  margin-top: 6px;
  color: #0f172a;
}

.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.filter-bar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.filter-bar input,
.filter-bar select,
.filter-bar button {
  padding: 8px;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background: #fff;
}

.btn {
  padding: 9px 12px;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  color: #fff;
  background: var(--primary);
}

.btn.secondary {
  background: #000000;
}

.btn.warn {
  background: var(--warn);
  color: #000;
}

.btn.danger {
  background: var(--danger);
}

.btn.light {
  background: #e5e7eb;
  color: #111827;
  border: 1px solid #d1d5db;
}

.table-wrap {
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
  overflow: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

th,
td {
  padding: 12px;
  border-bottom: 1px solid #e5e7eb;
  text-align: left;
  white-space: nowrap;
  color: #000000ff;
}

th {
  background: #f9fafb;
  font-size: 13px;
  color: #000000ff;
  cursor: pointer;
  position: sticky;
  top: 0;
}

tr:hover td {
  background: #30b6a283;
}

.badge {
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.Completed {
  background: #d1fae5;
  color: #065f46;
}

.Pending {
  background: #fef3c7;
  color: #92400e;
}

.Cancelled {
  background: #fee2e2;
  color: #991b1b;
}

.row-actions button {
  padding: 6px 10px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  gap:30px;
}

.row-actions .edit {
  background: var(--warn);
}

.row-actions .del {
  background: var(--danger);
  color: #fff;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, .35);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 50;
}

.modal {
  width: 100%;
  max-width: 520px;
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
  padding: 18px;
}

.modal h2 {
  margin-bottom: 10px;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.form-grid .full {
  grid-column: 1/-1;
}

.modal input,
.modal select {
  width: 100%;
  padding: 10px;
  border: 1px solid #d1d5db;
  border-radius: 10px;
}

.modal .footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 12px;
}

/* Analysis */
.chart-card {
  background: #fff;
  border-radius: 14px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
}

.analysis-controls {
  background: #fff;
  border-radius: 14px;
  padding: 12px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
  margin-bottom: 12px;
}

.analysis-controls .filter-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.analysis-controls label {
  font-size: 12px;
  color: #374151;
}

.mini-cards {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 12px;
  margin: 12px 0;
}

.mini-card {
  background: #fff;
  border-radius: 12px;
  padding: 14px;
  text-align: center;
  box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
}

.mini-card h4 {
  margin-bottom: 6px;
  font-size: 12px;
  color: #374151;
}

.mini-card p {
  font-size: 20px;
  font-weight: 800;
  color: #0f172a;
}

.charts-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.chart-wrap {
  position: relative;
  height: 320px;
}

/* Export panel */
.export-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.export-grid .row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.muted {
  color: var(--muted);
  font-size: 13px;
}

@media (max-width: 1000px) {
  .cards {
    grid-template-columns: 1fr;
  }

  .charts-grid {
    grid-template-columns: 1fr;
  }

  .sidebar {
    width: 220px;
  }

  .layout {
    margin-left: 220px; /* Adjust for smaller sidebar */
  }
}

/* Additional styles from provided CSS */
.card {
  background: rgba(0, 0, 0, 0.452);
  backdrop-filter: blur(12px);
  border-radius: 20px;
  padding: 25px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
  transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover {
  transform: translateY(-5px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
}

.card h3 {
  font-size: 1.5rem;
  margin-bottom: 10px;
  color: #ffcc00;
}

.card p {
  font-size: 2.1rem;
  font-weight: bold;
  margin: 10px 0;
}

.info-btn {
  margin-top: 10px;
  padding: 10px 15px;
  border: none;
  border-radius: 12px;
  background: #ffdd57;
  color: #333;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s;
}

.info-btn:hover {
  background: #ffd633;
  transform: scale(1.05);
}

/* POPUP STYLES */
.popup {
  display: none;
  position: fixed;
  z-index: 1000;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(8px);
  align-items: center;
  justify-content: center;
}

.popup-content {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(20px);
  padding: 30px;
  border-radius: 20px;
  width: 400px;
  max-width: 90%;
  color: #fff;
  text-align: left;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
  animation: fadeIn 0.3s ease-in-out;
}

.popup-content h2 {
  margin-top: 0;
  font-size: 1.6rem;
  color: #ffdd57;
}

.popup-content ul {
  margin: 15px 0;
  padding-left: 20px;
}

.popup-content li {
  margin: 8px 0;
}

.close {
  float: right;
  font-size: 1.5rem;
  cursor: pointer;
  color: #fff;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

.addform {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.addform-content {
  background: #fff;
  padding: 20px;
  align-items: center;
  justify-content: center;
  border: 3px solid #30b6a2;
  border-radius: 15px;
  width: 350px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  position: relative;
  animation: fadeIn 0.3s ease-in-out;
}

.close {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 20px;
  color: red;
  cursor: pointer;
}

.addform-content form label {
  display: block;
  margin: 10px 0 5px;
  font-weight: bold;
}

.addform-content form input,
.addform-content form select,
.addform-content form button {
  width: 100%;
  padding: 8px;
  margin-bottom: 12px;
  border: 1px solid #ccc;
  border-radius: 6px;
}

.addform-content form button {
  background: #30b6a2;
  color: white;
  font-weight: bold;
  cursor: pointer;
}

.addform-content form button:hover {
  background: #30b6a2;
}

.tab-btn {
  cursor: pointer;
  padding: 10px;
  margin: 5px 0;
  width: 100%;
  text-align: left;
  border: none;
  background: #f0f0f0;
}

.tab-btn.active {
  background: #2563eb;
  color: white;
}

.chart-wrap {
  width: 100%;
  height: 300px;
}
  </style>
</head>
<body>
  <?php if ($error): ?>
    <div class="error-alert">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="header">
    <div class="header-left"><img src="../logo.jpg" alt="Logo"></div>
    <div class="header-middle">
      <div class="header-middle-title">Sales Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..."></div>
    </div>
    <div class="header-right">
      <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>">⬇ CSV</a>
      <button class="role-btn" onclick="window.location.href='../log.php'">Log</button>
      <button class="role-btn" onclick="window.location.href='../index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h1>Sales Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='index.php'">Sales</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../booking/index.html'">Booking</button>
        </div>
        <hr>
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn active" onclick="window.location.href='index.php'">Sales Dashboard</button>
          <button class="tab-btn" onclick="window.location.href='index2.php'">Sales SUM</button>
          <button class="tab-btn" onclick="window.location.href='index3.php'">Sales Analysis</button>
        </div>
      </nav>
    </aside>

    <main class="free-area">
      <section id="sales-dashboard" class="panel active">
        <div class="content">
          <h1>Sales Management</h1>
          <div class="cards">
            <div class="card"><h3>Total Today Sales</h3><p><?= $stats['todaySales'] ?></p></div>
            <div class="card"><h3>Total Orders</h3><p><?= $stats['totalOrders'] ?></p></div>
            <div class="card"><h3>Total Customers</h3><p><?= $stats['totalCus'] ?></p></div>
          </div>

          <div class="toolbar">
            <div class="filter-bar">
              <form method="GET">
                From: <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
                To: <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
                Status:
                <select name="status">
                  <option value="">All</option>
                  <option <?= (($_GET['status'] ?? '') == "Paid" ? "selected" : "") ?>>Paid</option>
                  <option <?= (($_GET['status'] ?? '') == "Pending" ? "selected" : "") ?>>Pending</option>
                  <option <?= (($_GET['status'] ?? '') == "Cancelled" ? "selected" : "") ?>>Cancelled</option>
                  <option <?= (($_GET['status'] ?? '') == "Returned" ? "selected" : "") ?>>Returned</option>
                </select>
                Customer: <input type="text" name="customer" value="<?= $_GET['customer'] ?? '' ?>">
                <button type="submit">Filter</button>
              </form>
            </div>
            <div style="flex:1">
              <button class="btn secondary" onclick="openForm()">➕ Add Sale</button>
            </div>
          </div>

          <div class="table-wrap">
            <table id="salesTable">
              <thead>
                <tr>
                  <th>ID</th><th>Date</th><th>Customer</th><th>Qty</th><th>Total</th><th>Status</th><th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($sales)): ?>
                  <tr><td colspan="7" class="text-center">No records found</td></tr>
                <?php else: ?>
                  <?php foreach ($sales as $s): ?>
                    <tr>
                      <td><?= $s['id'] ?></td>
                      <td><?= $s['date'] ?></td>
                      <td><?= htmlspecialchars($s['customer']) ?></td>
                      <td><?= $s['quantity'] ?></td>
                      <td>LKR <?= number_format($s['total'],2) ?></td>
                      <td><span class="badge <?= $s['status'] ?>"><?= $s['status'] ?></span></td>
                      <td>
                        <button class="edit" onclick="
                          document.getElementById('edit_id').value='<?= $s['id'] ?>';
                          document.getElementById('edit_date').value='<?= $s['date'] ?>';
                          document.getElementById('edit_customer').value='<?= htmlspecialchars($s['customer']) ?>';
                          document.getElementById('edit_quantity').value='<?= $s['quantity'] ?>';
                          document.getElementById('edit_total').value='<?= $s['total'] ?>';
                          document.getElementById('edit_status').value='<?= $s['status'] ?>';
                          openModal('editModal');
                        ">✏</button>
                        <button class="del" onclick="
                          document.getElementById('delete_id').value='<?= $s['id'] ?>';
                          openModal('deleteModal');
                        ">🗑</button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Add Sale Modal -->
  <div id="AddForm" class="addform">
    <div class="addform-content">
      <span class="close" onclick="closeForm()">&times;</span>
      <h2>Add New Sale</h2>
      <form method="POST" action="">
        <input type="hidden" name="action" value="add_sale">

        <label>Date</label>
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required><br>

        <label>Customer Name *</label>
        <input type="text" id="customerInput" name="customer" autocomplete="off" required placeholder="Start typing...">
        <div id="suggestions"></div>

        <div id="newCustomerSection" style="display:none; margin-top:15px; padding:10px; background:#f5f5ff; border:1px solid #ccc; border-radius:6px;">
          <h4>New Customer Details</h4>
          <label>Email *</label>
          <input type="email" name="customer_email" placeholder="Required if new"><br>
          <label>Mobile</label>
          <input type="text" name="customer_mobile" placeholder="077..."><br>
          <label>Address</label>
          <input type="text" name="customer_address" placeholder="Street / City"><br>
          <label>District</label>
          <input type="text" name="customer_district" placeholder="e.g. Colombo"><br>
        </div>

        <label>Quantity</label>
        <input type="number" name="quantity" min="1" value="1" required><br>
        <label>Total (LKR)</label>
        <input type="number" step="0.01" name="total" min="0.01" required><br>
        <label>Status</label>
        <select name="status">
          <option>Pending</option>
          <option>Paid</option>
          <option>Cancelled</option>
          <option>Returned</option>
        </select><br><br>

        <button type="submit" style="width:100%;">Save Sale</button>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div id="editModal" class="addform">
    <div class="addform-content">
      <span class="close" onclick="closeModal('editModal')">&times;</span>
      <h2>Edit Sale</h2>
      <form method="POST" action="">
        <input type="hidden" name="id" id="edit_id">
        <label>Date</label>
        <input type="date" name="date" id="edit_date" required><br>
        <label>Customer Name *</label>
        <input type="text" name="customer" id="edit_customer" required><br>
        <label>Quantity</label>
        <input type="number" name="quantity" id="edit_quantity" min="1" required><br>
        <label>Total (LKR)</label>
        <input type="number" step="0.01" name="total" id="edit_total" min="0.01" required><br>
        <label>Status</label>
        <select name="status" id="edit_status">
          <option>Pending</option>
          <option>Paid</option>
          <option>Cancelled</option>
          <option>Returned</option>
        </select><br><br>
        <button type="submit" name="edit_sale" style="width:100%;">Update Sale</button>
      </form>
    </div>
  </div>

  <!-- Delete Modal -->
  <div id="deleteModal" class="addform">
    <div class="addform-content">
      <span class="close" onclick="closeModal('deleteModal')">&times;</span>
      <h2>Delete Sale</h2>
      <form method="POST" action="">
        <input type="hidden" name="id" id="delete_id">
        <p>Are you sure you want to delete this sale?</p>
        <button type="submit" name="delete_sale" style="background:#f44336; color:#fff; padding:10px; border:none; width:100%; border-radius:4px;">Delete</button>
      </form>
    </div>
  </div>

  <script>
    const allCustomers = <?php
      // Pull existing names from users and past sales
      $tmp = new mysqli($host, $user, $pass, $db);
      $res = $tmp->query("SELECT DISTINCT full_name FROM users UNION SELECT DISTINCT customer FROM sales WHERE customer != ''");
      $arr = [];
      while ($r = $res->fetch_row()) {
        $arr[] = htmlspecialchars($r[0], ENT_QUOTES);
      }
      echo json_encode($arr);
      $tmp->close();
    ?>;

    const input = document.getElementById('customerInput');
    const suggestions = document.getElementById('suggestions');
    const newSection = document.getElementById('newCustomerSection');

    input.addEventListener('input', () => {
      let q = input.value.trim().toLowerCase();
      suggestions.innerHTML = '';
      suggestions.style.display = 'none';
      if (q.length < 1) {
        newSection.style.display = 'none';
        return;
      }
      let matches = allCustomers.filter(n => n.toLowerCase().includes(q)).slice(0, 8);
      if (matches.length) {
        suggestions.style.display = 'block';
        suggestions.innerHTML = matches.map(n => `<div onclick="selectCust('${n.replace(/'/g, "\\'")}')">${n}</div>`).join('');
        newSection.style.display = 'none';
      } else {
        newSection.style.display = 'block';
      }
    });

    function selectCust(name) {
      input.value = name;
      suggestions.style.display = 'none';
      newSection.style.display = 'none';
    }

    document.addEventListener('click', e => {
      if (!e.target.closest('#customerInput') && !e.target.closest('#suggestions')) {
        suggestions.style.display = 'none';
      }
    });

    function openForm() {
      document.getElementById('AddForm').style.display = 'flex';
      input.value = '';
      suggestions.innerHTML = '';
      newSection.style.display = 'none';
    }
    function closeForm() {
      document.getElementById('AddForm').style.display = 'none';
    }
    function openModal(id) {
      document.getElementById(id).style.display = 'flex';
    }
    function closeModal(id) {
      document.getElementById(id).style.display = 'none';
    }

    document.getElementById('globalSearch').addEventListener('input', e => {
      let s = e.target.value.toLowerCase();
      document.querySelectorAll('#salesTable tbody tr').forEach(r => {
        r.style.display = r.textContent.toLowerCase().includes(s) ? '' : 'none';
      });
    });
  </script>
</body>
</html>
