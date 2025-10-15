<?php
// all_bills.php - Enhanced All Bills Page with POS-like Print
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// ---------- FETCH SHOP SETTINGS ----------
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$shop_name    = $settings['shop_name'];
$shop_slogan  = $settings['shop_slogan'];
$shop_tel     = $settings['shop_tel'];
$shop_email   = $settings['shop_email'];
$shop_address = $settings['shop_address'];
$thank_note   = $settings['thank_note'];
$vat_percent  = $settings['vat_percent'];

// ---------- SEARCH & FETCH BILLS ----------
$search = "";
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT * FROM bills 
            WHERE customer_name LIKE '%$search%' 
               OR id LIKE '%$search%' 
            ORDER BY id DESC"; // newest bills first
} else {
    $sql = "SELECT * FROM bills ORDER BY id DESC"; // newest first
}
$billsResult = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Bills</title>
<link rel="stylesheet" href="style1.css">
<style>
<?php
// all_bills.php - Enhanced All Bills Page with POS-like Print
// ---------- DB CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("DB Connection failed: " . $conn->connect_error);

// ---------- FETCH SHOP SETTINGS ----------
$settings = $conn->query("SELECT * FROM settings WHERE id=1")->fetch_assoc();
$shop_name    = $settings['shop_name'];
$shop_slogan  = $settings['shop_slogan'];
$shop_tel     = $settings['shop_tel'];
$shop_email   = $settings['shop_email'];
$shop_address = $settings['shop_address'];
$thank_note   = $settings['thank_note'];
$vat_percent  = $settings['vat_percent'];

// ---------- SEARCH & FETCH BILLS ----------
$search = "";
if (!empty($_GET['search'])) {
    $search = $conn->real_escape_string($_GET['search']);
    $sql = "SELECT * FROM bills 
            WHERE customer_name LIKE '%$search%' 
               OR id LIKE '%$search%' 
            ORDER BY id DESC"; // newest bills first
} else {
    $sql = "SELECT * FROM bills ORDER BY id DESC"; // newest first
}
$billsResult = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Bills</title>
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
  background: #f4f6f9;
  color: #0f172a;
  min-height: 100vh;
  overflow-x: hidden;
  /* Prevent horizontal scroll */
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
  /* High z-index to stay on top */
  height: 70px;
  /* Fixed height for consistency */
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
  top: 70px;
  /* Below header height */
  left: 0;
  width: 260px;
  height: calc(100vh - 70px);
  /* Full height minus header */
  background: #fff;
  padding: 18px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  border-right: 1px solid #e5e7eb;
  overflow-y: auto;
  /* Allow scroll inside sidebar if needed, but header/sidebar fixed */
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
  background: #e10000;
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

.salebtn .tab-btn+.tab-btn {
  margin-top: 8px;
}

.salebtn .tab-btn.active {
  outline: 3px solid #e10000;
  background: #fff;
  color: var(--brand);
}

/* Main Content Area - Adjusted for Fixed Elements */
.layout {
  margin-top: 70px;
  /* Space for header */
  margin-left: 260px;
  /* Space for sidebar */
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
  /* Main content scrolls */
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
  background: #e10000;
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
  background: #b6303083;
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
    margin-left: 220px;
    /* Adjust for smaller sidebar */
  }
}

/* Additional styles from provided CSS */
.card {
  background: rgba(255, 255, 255, 0.1);
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
  font-size: 1.2rem;
  margin-bottom: 10px;
  color: #ffdd57;
}

.card p {
  font-size: 1.5rem;
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
  border: 3px solid #e10000;
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
  background: #e10000;
  color: white;
  font-weight: bold;
  cursor: pointer;
}

.addform-content form button:hover {
  background: #e10000;
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











/* ===== CONTAINER ===== */
.container {
  max-width: 900px;
  margin: auto;
  background: #fff;
  padding: 30px;
  border-radius: 16px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
  animation: fadeIn 0.5s ease;
}

h1 {
  text-align: center;
  color: #7c0101;
  margin-bottom: 20px;
}

label {
  font-weight: 600;
  display: block;
  margin-top: 10px;
  margin-bottom: 6px;
  color: #444;
}

input[type="text"],
input[type="number"] {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 8px;
  transition: 0.2s;
}

input:focus {
  border-color: #830101b1;
  box-shadow: 0 0 6px rgba(123, 44, 44, 0.3);
  outline: none;
}

/* ===== TABLE ===== */
table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

th,
td {
  padding: 12px;
  border: 1px solid #e2e8f0;
  text-align: left;
  font-size: 14px;
}

th {
  background: #edf2f7;
  color: #ea0000;
  font-weight: 700;
}

tr:nth-child(even) {
  background: #f9fafc;
}

td input {
  width: 95%;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 6px;
}

/* ===== BUTTONS ===== */
.add-btn,
.save-btn {
  padding: 12px 18px;
  border: none;
  border-radius: 18px;
  cursor: pointer;
  font-weight: 600;
  margin-top: 20px;
  transition: all 0.2s ease;
}

.add-btn {
  background: #ffffff;
  color: #000000;
  font-size: 30px;
}

.add-btn:hover {
  background: #eb25253b;
  transform: scale(1.05);
  color: #000;
}

.save-btn {
  background: #000000;
  color: #fff;
  float: right;
  font-size: 30px;
}

.save-btn:hover {
  background: #000000;
  transform: scale(1.05);

}

/* ===== ANIMATIONS ===== */
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

/* ===== RESPONSIVE ===== */
@media(max-width:600px) {

  th,
  td {
    font-size: 12px;
    padding: 8px;
  }

  .save-btn,
  .add-btn {
    width: 100%;
    margin-top: 10px;
    float: none;
  }
}

.totals-box {
  margin-top: 20px;
  padding: 15px;
  background: #f9fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  font-size: 16px;
  color: #421a1a;
}

.totals-box p {
  margin: 6px 0;
  font-weight: 600;
}


.card {
  background: #fff;
  border-radius: 10px;
  padding: 20px;
  margin-bottom: 30px;
  position: relative;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
}

.shop-info {
  text-align: center;
  margin-bottom: 15px;
}

.shop-info h2 {
  margin: 0;
  color: #c60000ff;
}

.shop-info p {
  margin: 2px 0;
  font-size: 13px;
}

.table-wrap {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 10px;
}

th,
td {
  padding: 8px;
  border-bottom: 1px dashed #ccc;
  text-align: left;
  font-size: 14px;
}

th {
  color: #a82828;
}

.total-row td {
  font-weight: bold;
}

.print-btn {
  position: absolute;
  top: 20px;
  right: 20px;
  padding: 8px 14px;
  background: #000000;
  color: #fff;
  border: none;
  border-radius: 6px;
  cursor: pointer;
}

.print-btn:hover {
  background: #a80000;
}

@media print {
  body * {
    visibility: hidden;
  }

  .card,
  .card * {
    visibility: visible;
  }

  .card {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    box-shadow: none;
  }

  .print-btn {
    display: none;
  }
}
</style>
<script>
function printBill(id){
    window.print(); // Use CSS media query for POS-like print
}
</script>
</head>
<body> 
<!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Payment Management</div>
      <div class="search-bar">
        <form method="GET" action="" class="search-form">
          <input id="globalSearch" type="text" name="search" 
                 placeholder="🔍 Search by customer, ID or date..." 
                 value="<?= htmlspecialchars($search) ?>">
          <button type="submit"></button>
        </form>
      </div>
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
          <button class="tab-btn " onclick="window.location.href='index.php'">Bill🧾</button>
          <button class="tab-btn active" onclick="window.location.href='all_bills.php'">All Bills</button>
          <button class="tab-btn " onclick="window.location.href='setting.php'">⚙️Setting</button>
        </div>
      </nav>
    </aside>
    <div class="container">
      <h1>All Bills</h1>
      <a href="setting.php" style="float:right; margin-bottom:10px; padding: 8px 16px; background: #e10000; color: #fff; text-decoration: none; border-radius: 6px;">⚙️ Edit Shop Settings</a>

      <?php
      if ($billsResult->num_rows > 0) {
          while ($bill = $billsResult->fetch_assoc()) {
              echo '<div class="card" id="bill-'.$bill['id'].'">';
              echo '<button class="print-btn" onclick="printBill('.$bill['id'].')">🖨️ Print</button>';
              echo '<div class="shop-info">
                      <h2>'.$shop_name.'</h2>
                      <p>'.$shop_slogan.'</p>
                      <p>Tel: '.$shop_tel.' | Email: '.$shop_email.'</p>
                      <p>'.$shop_address.'</p>
                    </div>';
              
              echo '<div class="bill-meta">
                      <p><strong>Bill ID:</strong> #'.$bill['id'].' | <strong>Customer:</strong> '.htmlspecialchars($bill['customer_name']).'</p>
                      <p><strong>Date & Time:</strong> '.date('M d, Y g:i A', strtotime($bill['created_at'])).' | <strong>Payment:</strong> '.$bill['payment_method'].'</p>
                    </div>';

              // Fetch bill items
              $itemsResult = $conn->query("SELECT * FROM bill_items WHERE bill_id=".$bill['id']);
              echo '<table><thead><tr>
                      <th>Item</th>
                      <th>Price (Rs.)</th>
                      <th>Qty</th>
                      <th>Subtotal (Rs.)</th>
                    </tr></thead><tbody>';

              $total = 0;
              while ($item = $itemsResult->fetch_assoc()) {
                  $subtotal = $item['price'] * $item['qty'];
                  echo "<tr>
                          <td>".htmlspecialchars($item['item_name'])."</td>
                          <td style='text-align: right;'>".number_format($item['price'],2)."</td>
                          <td style='text-align: center;'>{$item['qty']}</td>
                          <td style='text-align: right;'>".number_format($subtotal,2)."</td>
                        </tr>";
                  $total += $subtotal;
              }

              $discount = floatval($bill['discount'] ?? 0);
              $vat = ($total - $discount) * ($vat_percent / 100);
              $grandTotal = $total - $discount + $vat;

              echo "<tr class='total-row'><td colspan='3'>Subtotal</td><td>".number_format($total,2)."</td></tr>";
              if ($discount > 0) {
                  echo "<tr class='total-row'><td colspan='3'>Discount</td><td>-".number_format($discount,2)."</td></tr>";
              }
              echo "<tr class='total-row'><td colspan='3'>VAT ({$vat_percent}%)</td><td>".number_format($vat,2)."</td></tr>";
              echo "<tr class='total-row'><td colspan='3'><strong>Grand Total</strong></td><td><strong>".number_format($grandTotal,2)."</strong></td></tr>";
              echo '</tbody></table>';
              echo '<div class="thank-note">'.$thank_note.'</div>';
              echo '</div>';
          }
      } else {
          echo "<div class='no-bills'>No bills found. <a href='index.php'>Create a new bill</a>.</div>";
      }
      $conn->close();
      ?>
    </div>
  </div>
</body>
</html>