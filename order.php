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
        $status     = $_POST['status'] ?? 'Pending';

        $stmt = $conn->prepare("INSERT INTO orders (order_date, customer, product, quantity, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssis", $order_date, $customer, $product, $quantity, $status);
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
        $status     = $_POST['status'] ?? 'Pending';

        $stmt = $conn->prepare("UPDATE orders SET order_date = ?, customer = ?, product = ?, quantity = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $order_date, $customer, $product, $quantity, $status, $id);
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
$sql = "SELECT id, order_date, customer, product, quantity, status FROM orders ORDER BY id DESC";
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
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="orderTable">
            <?php if(empty($orders)): ?>
              <tr><td colspan="6" style="text-align:center;padding:18px">No orders found</td></tr>
            <?php else: foreach($orders as $o): ?>
              <tr>
                <td><?= htmlspecialchars($o['id']) ?></td>
                <td><?= htmlspecialchars($o['customer']) ?></td>
                <td><?= htmlspecialchars($o['product']) ?></td>
                <td><?= (int)$o['quantity'] ?></td>
                <td><?= htmlspecialchars($o['status']) ?></td>
                <td>
                  <!-- View -->
                  <button class="btnview"
                    onclick="openOrderView(<?= json_encode($o['id']) ?>, <?= json_encode($o['order_date']) ?>, <?= json_encode($o['customer']) ?>, <?= json_encode($o['product']) ?>, <?= (int)$o['quantity'] ?>, <?= json_encode($o['status']) ?>)">
                    View
                  </button>

                  <!-- Update -->
                  <button class="btnupdate"
                    onclick="openOrderEdit(<?= json_encode($o['id']) ?>, <?= json_encode($o['order_date']) ?>, <?= json_encode($o['customer']) ?>, <?= json_encode($o['product']) ?>, <?= (int)$o['quantity'] ?>, <?= json_encode($o['status']) ?>)">
                    Update
                  </button>

                  <!-- Delete -->
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete order #<?= $o['id'] ?>?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $o['id'] ?>">
                    <button class="btndelete" type="submit">Delete</button>
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
    /* ---------- UTIL & STATE ---------- */
    const $ = (s, root = document) => root.querySelector(s);
    const $$ = (s, root = document) => [...root.querySelectorAll(s)];
    const LS_KEY = 'salesData_v1';

    let sales = loadSales();
    let sortState = { key: 'date', dir: 'desc' };
    let editingIndex = null;

    function loadSales() {
      const raw = localStorage.getItem(LS_KEY);
      if (raw) return JSON.parse(raw);
      const seed = [
        { id: 1001, date: '2025-08-01', customer: 'John Doe', total: 250, status: 'Completed' },
        { id: 1002, date: '2025-08-05', customer: 'Jane Smith', total: 120, status: 'Pending' },
        { id: 1003, date: '2025-08-06', customer: 'Acme Corp', total: 640.5, status: 'Completed' },
        { id: 1004, date: '2025-08-08', customer: 'Beta Ltd', total: 300, status: 'Cancelled' },
        { id: 1005, date: '2025-08-13', customer: 'John Doe', total: 99.99, status: 'Completed' }
      ];
      localStorage.setItem(LS_KEY, JSON.stringify(seed));
      return seed;
    }
    function saveSales() { localStorage.setItem(LS_KEY, JSON.stringify(sales)); }

    /* ---------- RENDER (Dashboard) ---------- */
    function formatMoney(n) { return '$' + (+n).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
    function renderCards(data = sales) {
      const uniqCustomers = new Set(data.map(r => r.customer));
      $('#cardTotal').textContent = formatMoney(data.reduce((s, r) => s + (+r.total || 0), 0));
      $('#cardOrders').textContent = data.length;
      $('#cardCustomers').textContent = uniqCustomers.size;
    }
    function rowHTML(r, idx) {
      return `<tr data-idx="${idx}">\
<td>${r.id}</td>\
<td>${r.date}</td>\
<td>${r.customer}</td>\
<td>${formatMoney(r.total)}</td>\
<td><span class="badge ${r.status}">${r.status}</span></td>\
<td class="row-actions"><button class="edit">✏ Edit</button><button class="del">🗑 Delete</button></td>\
</tr>`;
    }
    function getFilters() {
      return { date: $('#filterDate').value.trim(), customer: $('#filterCustomer').value.trim().toLowerCase(), status: $('#filterStatus').value.trim(), search: $('#globalSearch').value.trim().toLowerCase() };
    }
    function applyFilters(data) {
      const { date, customer, status, search } = getFilters();
      return data.filter(r => {
        const okDate = !date || r.date === date;
        const okCust = !customer || r.customer.toLowerCase().includes(customer);
        const okStatus = !status || r.status === status;
        const okSearch = !search || (String(r.id).includes(search) || r.customer.toLowerCase().includes(search) || r.status.toLowerCase().includes(search));
        return okDate && okCust && okStatus && okSearch;
      });
    }
    function applySort(data) {
      const { key, dir } = sortState, mult = dir === 'asc' ? 1 : -1;
      return data.slice().sort((a, b) => {
        let va = a[key], vb = b[key];
        if (key === 'total' || key === 'id') { va = +va; vb = +vb; }
        if (key === 'date') { return (new Date(va) - new Date(vb)) * mult; }
        if (va < vb) return -1 * mult; if (va > vb) return 1 * mult; return 0;
      });
    }
    function renderTable() {
      const filtered = applyFilters(sales);
      const sorted = applySort(filtered);
      const tbody = $('#salesTable tbody');
      if (tbody) tbody.innerHTML = sorted.map(r => rowHTML(r, sales.indexOf(r))).join('');
      renderCards(filtered);
      wireRowButtons();
      updateDashboardChart();
      syncAnalysisIfVisible();
    }

    /* ---------- TABLE INTERACTIONS ---------- */
    function wireRowButtons() {
      $$('#salesTable .edit').forEach(btn => btn.onclick = (e) => {
        const idx = +e.target.closest('tr').dataset.idx; openModal('Edit Sale', sales[idx], idx);
      });
      $$('#salesTable .del').forEach(btn => btn.onclick = (e) => {
        const idx = +e.target.closest('tr').dataset.idx;
        if (confirm('Delete sale #' + sales[idx].id + '?')) { sales.splice(idx, 1); saveSales(); renderTable(); }
      });
    }
    $$('#salesTable thead th').forEach(th => {
      const key = th.dataset.sort; if (!key) return;
      th.onclick = () => { if (sortState.key === key) { sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc'; } else { sortState.key = key; sortState.dir = 'asc'; } renderTable(); };
    });

    /* ---------- MODAL (Add/Edit) ---------- */
    const backdrop = $('#modalBackdrop');
    function openModal(title, record = null, idx = null) {
      $('#modalTitle').textContent = title; editingIndex = idx;
      $('#fId').value = record?.id ?? nextId();
      $('#fDate').value = record?.date ?? new Date().toISOString().slice(0, 10);
      $('#fCustomer').value = record?.customer ?? '';
      $('#fTotal').value = record?.total ?? '';
      $('#fStatus').value = record?.status ?? 'Completed';
      backdrop.style.display = 'flex';
    }
    function closeModal() { backdrop.style.display = 'none'; }
    function nextId() { return (sales.reduce((m, r) => Math.max(m, r.id), 0) || 1000) + 1; }
    $('#btnAdd')?.addEventListener('click', () => openModal('Add Sale'));
    $('#btnCancel')?.addEventListener('click', closeModal);
    $('#btnSave')?.addEventListener('click', () => {
      const rec = { id: +$('#fId').value, date: $('#fDate').value, customer: ($('#fCustomer').value || '').trim() || 'Unknown', total: +$('#fTotal').value || 0, status: $('#fStatus').value };
      if (!rec.date) { alert('Please select a date'); return; }
      if (editingIndex == null) { sales.push(rec); } else { sales[editingIndex] = rec; }
      saveSales(); closeModal(); renderTable();
    });

    /* ---------- FILTERS & SEARCH ---------- */
    $('#btnFilter')?.addEventListener('click', renderTable);
    $('#btnReset')?.addEventListener('click', () => { $('#filterDate').value = ''; $('#filterCustomer').value = ''; $('#filterStatus').value = ''; renderTable(); });
    $('#globalSearch').addEventListener('input', renderTable);

    /* ---------- EXPORTS / CHARTS (unchanged) ---------- */
    function toCSV(rows) { const header = ['ID', 'Date', 'Customer', 'Total', 'Status']; const lines = [header.join(',')]; rows.forEach(r => lines.push([r.id, r.date, quoteCSV(r.customer), r.total, r.status].join(','))); return lines.join('\n'); }
    function quoteCSV(s) { const str = String(s ?? ''); return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str; }
    function download(name, blob) { const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(a.href), 1000); }
    $('#btnExportCsv')?.addEventListener('click', () => { const data = applyFilters(sales); const csv = toCSV(data); download('sales_report.csv', new Blob([csv], { type: 'text/csv' })); });

    function getExportFiltered() {
      const from = $('#expFrom')?.value, to = $('#expTo')?.value, status = $('#expStatus')?.value, customer = $('#expCustomer')?.value?.trim()?.toLowerCase();
      return sales.filter(r => { const okFrom = !from || r.date >= from; const okTo = !to || r.date <= to; const okStatus = !status || r.status === status; const okCust = !customer || r.customer.toLowerCase().includes(customer); return okFrom && okTo && okStatus && okCust; });
    }
    $('#expCsv')?.addEventListener('click', () => { const csv = toCSV(getExportFiltered()); download('sales_export.csv', new Blob([csv], { type: 'text/csv' })); });
    $('#expXlsx')?.addEventListener('click', () => { const rows = getExportFiltered().map(r => ({ ID: r.id, Date: r.date, Customer: r.customer, Total: r.total, Status: r.status })); const ws = XLSX.utils.json_to_sheet(rows); const wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Sales'); XLSX.writeFile(wb, 'sales_export.xlsx'); });
    $('#expPdf')?.addEventListener('click', () => { const { jsPDF } = window.jspdf; const doc = new jsPDF(); doc.setFontSize(14); doc.text('Sales Report', 14, 16); const rows = getExportFiltered().map(r => [r.id, r.date, r.customer, String(r.total), r.status]); doc.autoTable({ startY: 22, head: [["ID", "Date", "Customer", "Total", "Status"]], body: rows }); doc.save('sales_export.pdf'); });

    /* ---------- DASHBOARD CHART & ANALYSIS (unchanged) ---------- */
    let dashChart;
    function monthlyAgg(data) { const byMonth = {}; data.forEach(r => { const k = r.date.slice(0, 7); byMonth[k] = (byMonth[k] || 0) + (+r.total || 0); }); const labels = Object.keys(byMonth).sort(); const values = labels.map(k => +byMonth[k].toFixed(2)); return { labels, values }; }
    function updateDashboardChart() { const canvas = document.getElementById('salesChart'); if (!canvas) return; const { labels, values } = monthlyAgg(sales); if (dashChart) dashChart.destroy(); dashChart = new Chart(canvas, { type: 'line', data: { labels, datasets: [{ label: 'Revenue', data: values, tension: .3 }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } } }); }

    let anChartRevenue = null, anChartTopCust = null;
    function getYears() { return [...new Set(sales.map(r => r.date.slice(0, 4)))].sort(); }
    function filterByYMStatus(data, y, m, status) { return data.filter(r => { const yOk = !y || r.date.startsWith(y); const mOk = (m === 'all' || !m) ? true : r.date.slice(5, 7) === m; const sOk = !status || r.status === status; return yOk && mOk && sOk; }); }
    function dailyAgg(data, y, m) { if (m && m !== 'all') { const daysInMonth = new Date(+y, +m, 0).getDate(); const labels = Array.from({ length: daysInMonth }, (_, i) => String(i + 1).padStart(2, '0')); const map = {}; data.forEach(r => { const d = r.date.slice(8, 10); map[d] = (map[d] || 0) + (+r.total || 0); }); const values = labels.map(d => +(map[d] || 0).toFixed(2)); return { labels, values, unit: 'Day' }; } else { const labels = ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12']; const map = {}; data.forEach(r => { const mm = r.date.slice(5, 7); map[mm] = (map[mm] || 0) + (+r.total || 0); }); const values = labels.map(mm => +(map[mm] || 0).toFixed(2)); return { labels, values, unit: 'Month' }; } }
    function topCustomersAgg(data, topN = 5) { const map = {}; data.forEach(r => { map[r.customer] = (map[r.customer] || 0) + (+r.total || 0); }); const pairs = Object.entries(map).sort((a, b) => b[1] - a[1]).slice(0, topN); return { labels: pairs.map(p => p[0]), values: pairs.map(p => +p[1].toFixed(2)) }; }
    function updateKpis(data) { const rev = data.reduce((s, r) => s + (+r.total || 0), 0); const orders = data.length; const aov = orders ? rev / orders : 0; const tc = topCustomersAgg(data, 1); $('#anRevenue').textContent = formatMoney(rev); $('#anOrders').textContent = String(orders); $('#anAOV').textContent = formatMoney(aov); $('#anTopCustomer').textContent = tc.labels?.[0] || '—'; }
    function setRangeLabel(y, m) { const mapMonth = { '01': 'Jan', '02': 'Feb', '03': 'Mar', '04': 'Apr', '05': 'May', '06': 'Jun', '07': 'Jul', '08': 'Aug', '09': 'Sep', '10': 'Oct', '11': 'Nov', '12': 'Dec' }; $('#anRangeLabel').textContent = (m && m !== 'all') ? `${mapMonth[m]} ${y}` : `Year ${y}`; }
    function drawAnalysis() {
      const y = $('#anYear').value; const m = $('#anMonth').value; const st = $('#anStatus').value; const data = filterByYMStatus(sales, y, m, st); updateKpis(data); setRangeLabel(y, m); const dAgg = dailyAgg(data, y, m); const tAgg = topCustomersAgg(data, 5);
      const c1 = $('#anChartRevenue').getContext('2d'); if (anChartRevenue) anChartRevenue.destroy(); anChartRevenue = new Chart(c1, { type: 'bar', data: { labels: dAgg.labels, datasets: [{ label: `Revenue by ${dAgg.unit}`, data: dAgg.values }] }, options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } } });
      const c2 = $('#anChartTopCust').getContext('2d'); if (anChartTopCust) anChartTopCust.destroy(); anChartTopCust = new Chart(c2, { type: 'pie', data: { labels: tAgg.labels, datasets: [{ label: 'Revenue', data: tAgg.values }] }, options: { responsive: true, maintainAspectRatio: false } });
    }
    function initAnalysis() { const years = getYears(); const yearSel = $('#anYear'); yearSel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join(''); yearSel.value = years[years.length - 1] || new Date().getFullYear().toString(); $('#anMonth').value = 'all'; $('#anStatus').value = ''; drawAnalysis(); }
    function isAnalysisActive() { return $('#sales-analysis').classList.contains('active'); }
    function syncAnalysisIfVisible() { if (isAnalysisActive()) drawAnalysis(); }
    document.addEventListener('change', (e) => { if (['anYear', 'anMonth', 'anStatus'].includes(e.target.id)) { if (isAnalysisActive()) drawAnalysis(); } });
    $('#anReset')?.addEventListener('click', initAnalysis);

    /* ---------- NAVIGATION ---------- */
    function activatePanel(id) { $$('.panel').forEach(p => p.classList.remove('active')); $('#' + id).classList.add('active'); $$('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.page === id)); if (id === 'sales-analysis') { setTimeout(() => { if (!$('#anYear').options.length) initAnalysis(); else drawAnalysis(); }, 50); } }
    $$('.tab-btn').forEach(btn => btn.onclick = () => activatePanel(btn.dataset.page));

    /* ---------- ROLE TOGGLE ---------- */
    let currentRole = 'Manager';
    try { $('#roleBtn').onclick = () => { currentRole = currentRole === 'Manager' ? 'Admin' : 'Manager'; $('#roleBtn').textContent = currentRole; }; } catch(e) {}

    /* ---------- INIT ---------- */
    renderTable();

    // Order Actions (demo only) - preserved (these don't conflict with server CRUD)
    document.querySelectorAll(".btn.view").forEach(btn => {
      btn.addEventListener("click", () => alert("View order details..."));
    });
    document.querySelectorAll(".btn.update").forEach(btn => {
      btn.addEventListener("click", () => alert("Update order status..."));
    });
    document.querySelectorAll(".btn.delete").forEach(btn => {
      btn.addEventListener("click", () => {
        if (confirm("Delete this order?")) btn.closest("tr").remove();
      });
    });

    /* =========== ORDERS CRUD JS (new) =========== */
    function openOrderAdd(){
      document.getElementById('orderModalTitle').textContent = 'Add Order';
      document.getElementById('order_form_action').value = 'add';
      document.getElementById('order_form_id').value = '0';
      document.getElementById('order_form_date').value = new Date().toISOString().slice(0,10);
      document.getElementById('order_form_customer').value = '';
      document.getElementById('order_form_product').value = '';
      document.getElementById('order_form_quantity').value = 1;
      document.getElementById('order_form_status').value = 'Pending';
      document.getElementById('orderModalBackdrop').style.display = 'flex';
    }
    function openOrderEdit(id, order_date, customer, product, quantity, status){
      document.getElementById('orderModalTitle').textContent = 'Edit Order #' + id;
      document.getElementById('order_form_action').value = 'edit';
      document.getElementById('order_form_id').value = id;
      document.getElementById('order_form_date').value = order_date || new Date().toISOString().slice(0,10);
      document.getElementById('order_form_customer').value = customer;
      document.getElementById('order_form_product').value = product;
      document.getElementById('order_form_quantity').value = quantity;
      document.getElementById('order_form_status').value = status;
      document.getElementById('orderModalBackdrop').style.display = 'flex';
    }
    function openOrderView(id, order_date, customer, product, quantity, status){
      // view mode: populate and disable fields
      openOrderEdit(id, order_date, customer, product, quantity, status);
      document.getElementById('order_form_action').value = 'view'; // not submitted
      document.getElementById('order_form_id').value = id;
      // disable inputs
      ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_status'].forEach(idn=>{
        document.getElementById(idn).setAttribute('disabled','disabled');
      });
      // hide save button
      const saveBtn = document.querySelector('#orderModalBackdrop .footer .btn:not(.light)');
      if (saveBtn) saveBtn.style.display = 'none';
    }
    function closeOrderModal(){
      // enable fields and restore save button before hiding
      ['order_form_date','order_form_customer','order_form_product','order_form_quantity','order_form_status'].forEach(idn=>{
        const el = document.getElementById(idn);
        if (el) el.removeAttribute('disabled');
      });
      const saveBtn = document.querySelector('#orderModalBackdrop .footer .btn:not(.light)');
      if (saveBtn) saveBtn.style.display = '';
      document.getElementById('orderModalBackdrop').style.display = 'none';
    }
    // Close modal when clicking backdrop (optional)
    document.getElementById('orderModalBackdrop').addEventListener('click', function(e){
      if (e.target === this) closeOrderModal();
    });

  </script>
</body>

</html>