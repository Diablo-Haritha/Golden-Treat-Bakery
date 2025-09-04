<?php
// ---------- DB: orders CRUD (keep style unchanged) ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Helper for bind_param dynamic refs (used if needed)
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Handle POST actions: add / edit / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add') {
        $order_date = $_POST['order_date'] ?: null;
        $customer   = trim($_POST['customer'] ?? '');
        $product    = trim($_POST['product'] ?? '');
        $quantity   = (int)($_POST['quantity'] ?? 1);
        $price      = (float)($_POST['price'] ?? 0.00);
        $status     = $_POST['status'] ?? 'Pending';


        $stmt = $conn->prepare("INSERT INTO orders (order_date, customer, product, quantity, price, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssids", $order_date, $customer, $product, $quantity, $price, $status);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) $flash_error = "Insert failed: " . $err;
        else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }

    if ($action === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);          
        $order_date = $_POST['order_date'] ?: null;
        $customer   = trim($_POST['customer'] ?? '');
        $product    = trim($_POST['product'] ?? '');
        $quantity   = (int)($_POST['quantity'] ?? 1);
        $price      = (float)($_POST['price'] ?? 0.00);
        $status     = $_POST['status'] ?? 'Pending';

        $stmt = $conn->prepare("UPDATE orders SET order_date = ?, customer = ?, product = ?, quantity = ?, price = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssidsi", $order_date, $customer, $product, $quantity, $price, $status, $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) $flash_error = "Update failed: " . $err;
        else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }


    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();

        if (!$ok) $flash_error = "Delete failed: " . $err;
        else { header("Location: " . $_SERVER['PHP_SELF']); exit; }
    }
}

// Fetch orders for display
$sql = "SELECT id, order_date, customer, product, quantity, price, status FROM orders ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->get_result();
$orders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Count total orders
$res2 = $conn->query("SELECT COUNT(*) AS total_orders FROM orders");
$row2 = $res2->fetch_assoc();
$totalOrders = (int)$row2['total_orders'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sales Management Admin UI</title>
  <!-- Charts & export -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
  <style>
    :root {
      --brand: #9c0dc7;
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
      box-sizing: border-box
    }

    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f4f6f9;
      color: #0f172a;
      min-height: 100vh;
      display: flex;
      flex-direction: column
    }

    /* Header */
    .header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #fff;
      padding: 12px 16px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, .08)
    }

    .header-left img {
      width: 56px;
      height: auto;
      border-radius: 8px;
      display: block;
      box-shadow: 2px 2px 5px rgba(0, 0, 0, .15)
    }

    .header-middle {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      margin: 0 16px;
      max-width: 720px
    }

    .header-middle-title {
      font-weight: 800;
      font-size: 26px;
      color: var(--brand);
      white-space: nowrap
    }

    .search-bar {
      flex: 1;
      display: flex
    }

    .search-bar input {
      width: 100%;
      padding: 8px 10px;
      border: 1px solid #d1d5db;
      border-radius: 8px
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 12px
    }

    .role-btn {
      background: #111827;
      color: #fff;
      padding: 8px 14px;
      border: none;
      border-radius: 8px;
      cursor: pointer
    }

    .role-btn:hover {
      opacity: .9
    }

    .user-icon {
      width: 28px;
      height: 28px;
      background: linear-gradient(135deg, #bbb, #888);
      border-radius: 50%
    }

    /* Layout */
    .layout {
      flex: 1;
      display: flex;
      min-height: 0
    }

    .sidebar {
      width: 260px;
      background: #fff;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-right: 1px solid #e5e7eb;
      
    }

    .sidebar h1 {
      text-align: center;
      font-size: 20px;
      margin-bottom: 8px;
      color: #0f172a
    }

    .sidebar nav {
      display: flex;
      flex-direction: column;
      gap: 10px
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
      text-align: center
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
      background: #9c0dc7;
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
      background: #30b6a2
    }

    .Bbtn {
      background: #edcd00
    }

    .otherbtn button:hover {
      filter: brightness(1.1)
    }

    .sidebar hr {
      margin: 8px 0
    }

    .sidebar p {
      font-size: 12px;
      color: #6b7280;
      font-weight: 700
    }

    .salebtn button {
      background: var(--brand);
      text-align: left
    }

    .salebtn button.active {
      outline: 3px solid rgba(146, 48, 182, 0.35)
    }

    .sidebar hr {
      margin: 8px 0
    }

    .sidebar p {
      font-size: 12px;
      color: #6b7280;
      font-weight: 700
    }

    /* Sales Management sub-tabs */
    .salebtn {
      display: flex;
      flex-direction: column
    }

    .salebtn .tab-btn {
      background: var(--brand);
      border: none;
      border-radius: 10px;
      color: #fff;
      cursor: pointer;
      padding: 10px 12px;
      font-weight: 700;
      text-align: left
    }

    .salebtn .tab-btn+.tab-btn {
      margin-top: 8px
    }

    .salebtn .tab-btn.active {
      outline: 3px solid rgba(48, 182, 162, .35)
    }

    /* Main area */
    .free-area {
      flex: 1;
      background: #f3f4f6;
      padding: 24px;
      overflow: auto
    }

    .panel {
      display: none
    }

    .panel.active {
      display: block
    }

    .content {
      background: #9c0dc7;
      border-radius: 14px;
      padding: 18px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08);
      display: flex;
      flex-direction: column;
      gap: 16px
    }

    .cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px
    }

    .card {
      background: #fff;
      border-radius: 12px;
      padding: 16px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      text-align: center
    }

    .card h3 {
      font-size: 14px;
      color: #374151
    }

    .card p {
      font-size: 22px;
      font-weight: 800;
      margin-top: 6px;
      color: #0f172a
    }

    .toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center
    }

    .filter-bar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .filter-bar input,
    .filter-bar select,
    .filter-bar button {
      padding: 8px;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      background: #fff
    }

    .btn {
      padding: 9px 12px;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      color: #fff;
      background: var(--primary)
    }

    .btn.secondary {
      background: #10b981
    }

    .btn.warn {
      background: var(--warn);
      color: #000
    }

    .btn.danger {
      background: var(--danger)
    }

    .btn.light {
      background: #e5e7eb;
      color: #111827;
      border: 1px solid #d1d5db
    }

    .table-wrap {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      overflow: auto
    }

    table {
      width: 100%;
      border-collapse: collapse
    }

    th,
    td {
      padding: 12px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
      white-space: nowrap
    }

    th {
      background: #f9fafb;
      font-size: 13px;
      color: #374151;
      cursor: pointer;
      position: sticky;
      top: 0
    }

    tr:hover td {
      background: #fcfcfd
    }

    .badge {
      padding: 4px 8px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700
    }

    .Completed {
      background: #d1fae5;
      color: #065f46
    }

    .Pending {
      background: #fef3c7;
      color: #92400e
    }

    .Cancelled {
      background: #fee2e2;
      color: #991b1b
    }

    .row-actions button {
      padding: 6px 10px;
      border: none;
      border-radius: 8px;
      cursor: pointer
    }

    .row-actions .edit {
      background: var(--warn)
    }

    .row-actions .del {
      background: var(--danger);
      color: #fff
    }

    /* Modal */
    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .35);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 50
    }

    .modal {
      width: 100%;
      max-width: 520px;
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, .25);
      padding: 18px
    }

    .modal h2 {
      margin-bottom: 10px
    }

    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px
    }

    .form-grid .full {
      grid-column: 1/-1
    }

    .modal input,
    .modal select {
      width: 100%;
      padding: 10px;
      border: 1px solid #d1d5db;
      border-radius: 10px
    }

    .modal .footer {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 12px
    }

    /* Analysis */
    .chart-card {
      background: #fff;
      border-radius: 14px;
      padding: 16px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .08)
    }

    .analysis-controls {
      background: #fff;
      border-radius: 14px;
      padding: 12px;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06);
      margin-bottom: 12px
    }

    .analysis-controls .filter-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center
    }

    .analysis-controls label {
      font-size: 12px;
      color: #374151
    }

    .mini-cards {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 12px;
      margin: 12px 0
    }

    .mini-card {
      background: #fff;
      border-radius: 12px;
      padding: 14px;
      text-align: center;
      box-shadow: 0 1px 6px rgba(0, 0, 0, .06)
    }

    .mini-card h4 {
      margin-bottom: 6px;
      font-size: 12px;
      color: #374151
    }

    .mini-card p {
      font-size: 20px;
      font-weight: 800;
      color: #0f172a
    }

    .charts-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px
    }

    .chart-wrap {
      position: relative;
      height: 320px
    }

    /* Export panel */
    .export-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 12px
    }

    .export-grid .row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .muted {
      color: var(--muted);
      font-size: 13px
    }

    @media (max-width:1000px) {
      .cards {
        grid-template-columns: 1fr
      }

      .charts-grid {
        grid-template-columns: 1fr
      }

      .sidebar {
        width: 220px
      }
    }
    /* Common button style */
.btnview, .btnupdate, .btndelete {
  padding: 8px 16px;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  cursor: pointer;
  transition: all 0.2s ease-in-out;
  margin: 0 4px;
  color: white;
}

/* View button (blue) */
.btnview {
  background-color: #1a73e8;
}
.btnview:hover {
  background-color: #155bb5;
}

/* Update button (orange) */
.btnupdate {
  background-color: #f39c12;
}
.btnupdate:hover {
  background-color: #d98200;
}

/* Delete button (red) */
.btndelete {
  background-color: #e74c3c;
}
.btndelete:hover {
  background-color: #c0392b;
}

</style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Order Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." />
      </div>
    </div>
    <div class="header-right">
  <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Order Dashboard</h1>
      <nav>
        <button class="salesbtn" disabled>Order</button>
        <div class="otherbtn">
     <button class="Sbtn" onclick="window.location.href='stoke.html'">Stock</button>
<button class="Ubtn" onclick="window.location.href='sales.html'">Sales</button>
<button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>

        </div>
        <hr />
        <p>Order Management</p>
        <div class="salebtn">
          <button class="tab-btn active" data-page="sales-dashboard">Order Dashboard</button>
          <button class="tab-btn" data-page="sales-analysis">Return Order</button>
          <button class="tab-btn" data-page="sales-export">Export Report</button>
        </div>
      </nav>
    </aside>

 
<main class="free-area">
        <!-- Main Content -->
    <main class="main-content">
      <!-- Orders Panel -->
      <section id="orders" class="panel active">
         <div class="content">
        <h2>Order Management</h2>
         <div class="cards">
            <div class="card">
              <h3>Total Returns</h3>
              <p id="cardTotal">$0</p>
            </div>
            <div class="card">
              <h3>Total Orders</h3>
              <p id="cardOrders"><?= $totalOrders ?></p>
            </div>
            <div class="card">
              <h3>Total Customers</h3>
              <p id="cardCustomers">0</p>
            </div>
          </div>
        <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Customer</th>
              <th>Product</th>
              <th>Quantity</th>
              <th>Price</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="orderTable">
            <?php if(empty($orders)): ?>
              <tr><td colspan="7" style="text-align:center;padding:18px">No orders found</td></tr>
            <?php else: foreach($orders as $o): ?>
              <tr>
                <td><?= htmlspecialchars($o['id']) ?></td>
                <td><?= htmlspecialchars($o['customer']) ?></td>
                <td><?= htmlspecialchars($o['product']) ?></td>
                <td><?= (int)$o['quantity'] ?></td>
                <td><?= number_format((float)$o['price'], 2) ?></td>
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td>
                  <!-- View (data attributes) -->
                  <button class="btnview"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                    data-customer="<?= htmlspecialchars($o['customer']) ?>"
                    data-product="<?= htmlspecialchars($o['product']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-price="<?= htmlspecialchars($o['price']) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
                    <i class="fa-solid fa-eye"></i>
                  </button>

                  <!-- Update (data attributes) -->
                  <button class="btnupdate"
                    type="button"
                    data-id="<?= htmlspecialchars($o['id']) ?>"
                    data-order-date="<?= htmlspecialchars($o['order_date']) ?>"
                    data-customer="<?= htmlspecialchars($o['customer']) ?>"
                    data-product="<?= htmlspecialchars($o['product']) ?>"
                    data-quantity="<?= (int)$o['quantity'] ?>"
                    data-price="<?= htmlspecialchars($o['price']) ?>"
                    data-status="<?= htmlspecialchars($o['status']) ?>">
                    <i class="fa-regular fa-pen-to-square"></i>
                  </button>

                  <!-- Delete -->
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete order #<?= $o['id'] ?>?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                    <button class="btndelete" type="submit"><i class="fa-solid fa-trash-can"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </section>

      <!-- Other Panels -->
      <section id="dashboard" class="panel"><h2>Dashboard</h2></section>
      <section id="products" class="panel"><h2>Product Management</h2></section>
      <section id="users" class="panel"><h2>User Management</h2></section>
      </div>
    </main>
  </div>

    <!-- Export -->
      <section id="sales-export" class="panel">
        <div class="content">
          <h2>Export Reports</h2>
          <div class="export-grid">
            <div class="row">
              <input type="date" id="expFrom" />
              <input type="date" id="expTo" />
              <select id="expStatus">
                <option value="">All Status</option>
                <option>Completed</option>
                <option>Pending</option>
                <option>Cancelled</option>
              </select>
              <input type="text" id="expCustomer" placeholder="Customer" />
            </div>
            <div class="row">
              <button class="btn" id="expCsv">Export CSV</button>
              <button class="btn secondary" id="expXlsx">Export Excel</button>
              <button class="btn warn" id="expPdf">Export PDF</button>
              <span class="muted">Exports use live, filtered data.</span>
            </div>
          </div>
        </div>
      </section>

  </main>

  <!-- Modal Add/Edit for ORDERS (uses your .modal-backdrop/.modal styles) -->
  <div class="modal-backdrop" id="orderModalBackdrop">
    <div class="modal">
      <h2 id="orderModalTitle">Add Order</h2>
      <form id="orderForm" method="post" style="margin-top:12px">
        <input type="hidden" name="action" id="order_form_action" value="add">
        <input type="hidden" name="id" id="order_form_id" value="0">

        <div class="form-grid">
          <div><label>Date</label><input id="order_form_date" name="order_date" type="date"></div>
          <div><label>Quantity</label><input id="order_form_quantity" name="quantity" type="number" min="1" value="1"></div>
          <div class="full"><label>Price</label><input id="order_form_price" name="price" type="number" step="0.01" min="0" value="0.00" required></div>
          <div class="full"><label>Customer</label><input id="order_form_customer" name="customer" type="text" required></div>
          <div class="full"><label>Product</label><input id="order_form_product" name="product" type="text" required></div>
          <div class="full">
            <label>Status</label>
            <select id="order_form_status" name="status">
              <option>Pending</option>
              <option>Shipped</option>
              <option>Cancelled</option>
            </select>
          </div>
        </div>

        <div class="footer" style="margin-top:12px">
          <button type="button" class="btn light" onclick="closeOrderModal()">Cancel</button>
          <button type="submit" class="btn">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- =========== Your existing JS (unchanged) =========== -->
<script>
  document.addEventListener('DOMContentLoaded', () => {
  const orderModalBackdrop = document.getElementById('orderModalBackdrop');
  const orderForm = document.getElementById('orderForm');
  const inAction = (val) => document.getElementById('order_form_action').value = val;
  const inId = (val) => document.getElementById('order_form_id').value = val;
  const inDate = (val) => document.getElementById('order_form_date').value = val;
  const inCustomer = (val) => document.getElementById('order_form_customer').value = val;
  const inProduct = (val) => document.getElementById('order_form_product').value = val;
  const inQuantity = (val) => document.getElementById('order_form_quantity').value = val;
  const inPrice = (val) => {
    const el = document.getElementById('order_form_price');
    if (el) el.value = (val === undefined || val === null) ? '0.00' : Number(val).toFixed(2);
  };
  const inStatus = (val) => document.getElementById('order_form_status').value = val;
  const saveBtn = () => document.querySelector('#orderModalBackdrop .footer .btn:not(.light)');
  const today = () => new Date().toISOString().slice(0,10);
  // Also ensure that after server submit (page reload) modal is closed
  // ----- wire data-* buttons to modal functions (robust) -----
function parseAndCallOpen(btn, fn) {
  const ds = btn.dataset;
  // dataset properties: orderDate, customer, product, quantity, price, status, id
  const id = ds.id;
  const order_date = ds.orderDate || '';
  const customer = ds.customer || '';
  const product = ds.product || '';
  const quantity = ds.quantity || '1';
  const price = ds.price || '0.00';
  const status = ds.status || '';
  try {
    fn(id, order_date, customer, product, Number(quantity), price, status);
  } catch (err) {
    console.error('Failed to call modal function', err, { id, order_date, customer, product, quantity, price, status });
  }
}

document.querySelectorAll('.btnupdate').forEach(btn => {
  btn.addEventListener('click', (e) => {
    parseAndCallOpen(btn, window.openOrderEdit);
  });
});

document.querySelectorAll('.btnview').forEach(btn => {
  btn.addEventListener('click', (e) => {
    parseAndCallOpen(btn, window.openOrderView);
  });
});


  // Open Add
  window.openOrderAdd = function(){
    document.getElementById('orderModalTitle').textContent = 'Add Order';
    inAction('add');
    inId('0');
    inDate(today());
    inCustomer('');
    inProduct('');
    inQuantity('1');
    inPrice('0.00');
    inStatus('Pending');
    // ensure form enabled
    enableFormFields();
    if (saveBtn()) saveBtn().style.display = '';
    orderModalBackdrop.style.display = 'flex';
  };

  // Open Edit - called by onclick attributes in PHP rows
  // signature now includes price
  window.openOrderEdit = function(id, order_date, customer, product, quantity, price, status){
    console.log('openOrderEdit called with', id, order_date, customer, product, quantity, price, status);
    document.getElementById('orderModalTitle').textContent = 'Edit Order #' + id;
    inAction('edit');
    inId(String(id));
    inDate(order_date || today());
    inCustomer(customer || '');
    inProduct(product || '');
    inQuantity(String(quantity ?? 1));
    inPrice(price ?? '0.00');
    inStatus(status || 'Pending');
    enableFormFields();
    if (saveBtn()) saveBtn().style.display = '';
    orderModalBackdrop.style.display = 'flex';
  };

  // Open View (read-only) — accepts price param too
  window.openOrderView = function(id, order_date, customer, product, quantity, price, status){
    console.log('openOrderView called with', id, order_date, customer, product, quantity, price, status);
    document.getElementById('orderModalTitle').textContent = 'View Order #' + id;
    inAction('view');
    inId(String(id));
    inDate(order_date || today());
    inCustomer(customer || '');
    inProduct(product || '');
    inQuantity(String(quantity ?? 1));
    inPrice(price ?? '0.00');
    inStatus(status || '');
    // disable inputs & hide Save
    disableFormFields();
    const s = saveBtn();
    if (s) s.style.display = 'none';
    orderModalBackdrop.style.display = 'flex';
  };

  function disableFormFields(){
    ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n=>{
      const el = document.getElementById(n);
      if (el) el.setAttribute('disabled','disabled');
    });
  }
  function enableFormFields(){
    ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_price','order_form_status'].forEach(n=>{
      const el = document.getElementById(n);
      if (el) el.removeAttribute('disabled');
    });
  }

  // Close modal
  window.closeOrderModal = function(){
    enableFormFields();
    const s = saveBtn();
    if (s) s.style.display = '';
    orderModalBackdrop.style.display = 'none';
  };

  // Prevent submission when in view mode; otherwise allow normal POST to server
  if (orderForm) {
    orderForm.addEventListener('submit', function(e){
      const act = document.getElementById('order_form_action').value;
      if (act === 'view') {
        e.preventDefault();
        return false;
      }
      // otherwise let the form submit (POST to same page)
    });
  }

  // Close when clicking backdrop
  if (orderModalBackdrop) {
    orderModalBackdrop.addEventListener('click', (ev) => {
      if (ev.target === orderModalBackdrop) closeOrderModal();
    });
  }

  // Close on ESC
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && orderModalBackdrop.style.display === 'flex') {
      closeOrderModal();
    }
  });

  // GLOBAL SEARCH: filter server-rendered rows (search across all visible columns)
  const globalSearch = document.getElementById('globalSearch');
  const tbody = document.getElementById('orderTable');

  function filterTable() {
    const q = (globalSearch?.value || '').trim().toLowerCase();
    if (!tbody) return;
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!q) {
      rows.forEach(r => r.style.display = '');
      return;
    }
    rows.forEach(r => {
      const tds = Array.from(r.querySelectorAll('td'));
      if (!tds.length) { r.style.display = ''; return; }
      // combine all cell text for robust matching (includes price if present)
      const rowText = tds.map(td => (td.textContent || '').toLowerCase()).join(' ');
      const match = rowText.includes(q);
      r.style.display = match ? '' : 'none';
    });
  }

  if (globalSearch) {
    globalSearch.addEventListener('input', filterTable);
  }

  // If the page has "Add" UI element with id btnAdd, wire it
  const btnAdd = document.getElementById('btnAdd');
  if (btnAdd) btnAdd.addEventListener('click', openOrderAdd);

  // Also ensure that after server submit (page reload) modal is closed
  closeOrderModal();
});
</script>
</body>

</html>