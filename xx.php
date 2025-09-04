

<?php
// ---------- DATABASE CONNECTION ----------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// ---------- FETCH SALES DATA ----------
$where = "1=1";
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $from = $_GET['from'];
    $to   = $_GET['to'];
    $where .= " AND date BETWEEN '$from' AND '$to'";
}
if (!empty($_GET['status'])) {
    $status = $_GET['status'];
    $where .= " AND status = '$status'";
}
if (!empty($_GET['customer'])) {
    $customer = $_GET['customer'];
    $where .= " AND customer LIKE '%$customer%'";
}

$sql = "SELECT id, date, customer, total, status FROM sales WHERE $where ORDER BY id DESC";
$result = $conn->query($sql);
$sales = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }}

// ---------- EDIT SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_sale'])) {
    $id     = $_POST['id'];
    $date   = $_POST['date'];
    $customer = $_POST['customer'];
    $total = $_POST['total'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE sales SET date=?, customer=?, total=?, status=? WHERE id=?");
    $stmt->bind_param("ssisi", $date, $customer, $total, $status, $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// ---------- DELETE SALE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sale'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM sales WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}


    // ---------- INSERT FORM DATA ----------
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date     = $_POST['date'];
    $customer = $_POST['customer'];
    $total    = $_POST['total'];
    $status   = $_POST['status'];

    $sql = "INSERT INTO sales (date, customer, total, status) 
            VALUES ('$date', '$customer', '$total', '$status')";

    if ($conn->query($sql) === TRUE) {
       echo "<script>
             alert('✅ Sale record added successfully!')
                window.location.href = window.location.href.split('?')[0]; // Remove query parameters
              </script>";
    } else {
        echo "<p style='color:red;'>❌ Error: " . $conn->error . "</p>";
    }
}

// Count total orders
$sql = "SELECT COUNT(*) AS total_orders FROM sales";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$totalOrders = $row['total_orders'];



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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
  <style>
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
    

    .addform {
   display: none; /* hidden by default */
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
      z-index: 1000;
   
    }
      /* Popup box */
    .addform-content {
      background: #fff;
      padding: 20px;
      align-items: center;
      justify-content: center;
      border: 3px solid #30b6a2;
      border-radius: 15px;
      width: 350px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.2);
      position: relative;
      animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Close button */
    .close {
      position: absolute;
      top: 10px;
      right: 15px;
      font-size: 20px;
      color: red;
      cursor: pointer;
    }

    /* Form styling */
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
      border-right: 1px solid #e5e7eb
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
      background: #9c0dc7
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
      outline: 3px solid rgba(48, 182, 162, .35)
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
      background: #30b6a2;
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
      background: #000000
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
      white-space: nowrap;
      color: #000000ff;
    }

    th {
      background: #f9fafb;
      font-size: 13px;
      color: #000000ff;
      cursor: pointer;
      position: sticky;
      top: 0
    }

    tr:hover td {
      background: #30b6a283
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
  </style>
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
  <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Sales Dashboard</h1>
      <nav>
        <button class="salesbtn" disabled>Sales</button>
        <div class="otherbtn">
     <button class="Sbtn" onclick="window.location.href='stoke.html'">Stock</button>
<button class="Ubtn" onclick="window.location.href='order.html'">Order</button>
<button class="Bbtn" onclick="window.location.href='booking.html'">Booking</button>

        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn active" data-page="sales-dashboard">Sales Dashboard</button>
          <button class="tab-btn" data-page="sales-analysis">Sales Analysis</button>
          <button class="tab-btn" data-page="sales-export">Export Report</button>
        </div>
      </nav>
    </aside>

    <!-- Main -->
     
    <main class="free-area">
      <!-- Dashboard -->
      <section id="sales-dashboard" class="panel active">
        <div class="content">
          <h1>Sales Management</h1>
          <div class="cards">
            <div class="card">
              <h3>Total Sales</h3>
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
          <div class="toolbar">
            <div class="filter-bar">
              <input type="date" id="filterDate" />
              <input type="text" id="filterCustomer" placeholder="Customer Name" />
              <select id="filterStatus">
                <option value="">All Status</option>
                <option>Completed</option>
                <option>Pending</option>
                <option>Cancelled</option>
              </select>
              <button class="btn light" id="btnFilter">Filter</button>
              <button class="btn light" id="btnReset">Reset</button>
            </div>
            <div style="flex:1"></div>
           
            <button class="btn secondary" onclick="openForm()">➕ Add Sale</button>
            <button class="btn" id="btnExportCsv">⬇ CSV</button>
          </div>
            <div class="table-wrap">
            <table id="salesTable">
                     <tr>
                  <th data-sort="id">ID ▲▼</th>
                  <th data-sort="date">Date ▲▼</th>
                  <th data-sort="customer">Customer ▲▼</th>
                  <th data-sort="total">Total ▲▼</th>
                  <th data-sort="status">Status ▲▼</th>
                  <th >Action </th>
    </tr>
  </thead>
  <tbody>
    <?php if(empty($sales)): ?>
      <tr><td colspan="5" class="text-center">No records found</td></tr>
    <?php else: ?>
      <?php foreach($sales as $s): ?>
      <tr>
        <td><?= $s['id'] ?></td>
        <td><?= $s['date'] ?></td>
        <td><?= $s['customer'] ?></td>
        <td>$<?= number_format($s['total'],2) ?></td>
        <td><?= $s['status'] ?></td>
        <td>
             <div class="row-actions">
        <button class="edit" onclick="document.getElementById('edit_id').value='<?= $s['id'] ?>';
                                          document.getElementById('edit_date').value='<?= $s['date'] ?>';
                                          document.getElementById('edit_customer').value='<?= $s['customer'] ?>';
                                          document.getElementById('edit_total').value='<?= $s['total'] ?>';
                                          document.getElementById('edit_status').value='<?= $s['status'] ?>';
                                          openModal('editModal');">✏️</button>
                                          
        <button class="del" onclick="document.getElementById('delete_id').value='<?= $s['id'] ?>'; openModal('deleteModal');">🗑️</button></td>
      </div>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
  </tbody>
</table>
          </div>
        </div>
      </section>



  <!-- Export -->
    <section >
<!-- Popup Form -->
<div id="AddForm" class="addform">
  <div class="addform-content">
    <span class="close" onclick="closeForm()">&times;</span>
    <h2>Add New Sale</h2>
    <form method="POST" action="">
      <label for="date">Date</label>
      <input type="date" name="date" required>

      <label for="customer">Customer</label>
      <input type="text" name="customer" placeholder="Enter customer name" required>

      <label for="total">Total</label>
      <input type="number" step="0.01" name="total" placeholder="Enter total" required>

      <label for="status">Status</label>
      <select name="status" required>
        <option value="Pending">Pending</option>
        <option value="Paid">Paid</option>
        <option value="Cancelled">Cancelled</option>
      </select>

      <button type="submit">Save</button>
    </form>
  </div>
      </div>
<!-- Edit Modal -->
<div class="addform" id="editModal">
  <div class="addform-content">
    <span class="close"onclick="closeModal('editModal')">&times;</span>

    <h2>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Edit Sale</h2>
      <br><br>
    
    <form method="post">
      <input type="hidden" name="id" id="edit_id">
      <input type="date" name="date" id="edit_date" required>
      <input type="text" name="customer" id="edit_customer" required>
      <input type="number" name="total" id="edit_total" required>
      <select name="status" id="edit_status" required>
        <option value="Pending">Pending</option>
        <option value="Paid">Paid</option>
      </select>
      <div class="row-actions">
        <br>
          <hr>
          <br>
        <button type="submit" name="edit_sale" class="edit">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div class="addform" id="deleteModal">
  <div class="addform-content">
    <span class="close"onclick="closeModal('deleteModal')">&times;</span>
     
    <h2>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Delete Sale</h2>
    <br>
    <form method="post">
      <input type="hidden" name="id" id="delete_id">
      <p>Are you sure you want to delete this sale?</p>
      <div class="row-actions">
        <br><br>
       <hr>
        <br><br>
        <button type="submit" name="delete_sale" class="delx">Delete</button>
      </div>
    </form>
  </div>
</div>



      <!-- Analysis -->
      <section id="sales-analysis" class="panel">
        <div class="content">
          <h2>Sales Analysis</h2>
          <div class="analysis-controls">
            <div class="filter-row">
              <label>Year</label>
              <select id="anYear"></select>
              <label>Month</label>
              <select id="anMonth">
                <option value="all">All</option>
                <option value="01">Jan</option>
                <option value="02">Feb</option>
                <option value="03">Mar</option>
                <option value="04">Apr</option>
                <option value="05">May</option>
                <option value="06">Jun</option>
                <option value="07">Jul</option>
                <option value="08">Aug</option>
                <option value="09">Sep</option>
                <option value="10">Oct</option>
                <option value="11">Nov</option>
                <option value="12">Dec</option>
              </select>
              <label>Status</label>
              <select id="anStatus">
                <option value="">All</option>
                <option value="Completed">Completed</option>
                <option value="Pending">Pending</option>
                <option value="Cancelled">Cancelled</option>
              </select>
              <button class="btn light" id="anReset">Reset</button>
            </div>
          </div>

          <div class="mini-cards">
            <div class="mini-card">
              <h4>Revenue</h4>
              <p id="anRevenue">$0</p>
            </div>
            <div class="mini-card">
              <h4>Orders</h4>
              <p id="anOrders">0</p>
            </div>
            <div class="mini-card">
              <h4>Avg Order</h4>
              <p id="anAOV">$0</p>
            </div>
            <div class="mini-card">
              <h4>Top Customer</h4>
              <p id="anTopCustomer">—</p>
            </div>
          </div>

          <div class="charts-grid">
            <div class="chart-card">
              <h3>Revenue Over Time</h3>
              <p class="muted" id="anRangeLabel"></p>
              <div class="chart-wrap"><canvas id="anChartRevenue"></canvas></div>
            </div>
            <div class="chart-card">
              <h3>Top Customers (Revenue)</h3>
              <p class="muted">Top 5 for the selected period.</p>
              <div class="chart-wrap"><canvas id="anChartTopCust"></canvas></div>
            </div>
          </div>
        </div>
      </section>

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

       <!-- Analysis -->
      <section id="sales-analysis" class="panel">
        <div class="content">
          <h2>Sales Analysis</h2>
          <div class="analysis-controls">
            <div class="filter-row">
              <label>Year</label>
              <select id="anYear"></select>
              <label>Month</label>
              <select id="anMonth">
                <option value="all">All</option>
                <option value="01">Jan</option>
                <option value="02">Feb</option>
                <option value="03">Mar</option>
                <option value="04">Apr</option>
                <option value="05">May</option>
                <option value="06">Jun</option>
                <option value="07">Jul</option>
                <option value="08">Aug</option>
                <option value="09">Sep</option>
                <option value="10">Oct</option>
                <option value="11">Nov</option>
                <option value="12">Dec</option>
              </select>
              <label>Status</label>
              <select id="anStatus">
                <option value="">All</option>
                <option value="Completed">Completed</option>
                <option value="Pending">Pending</option>
                <option value="Cancelled">Cancelled</option>
              </select>
              <button class="btn light" id="anReset">Reset</button>
            </div>
          </div>

          <div class="mini-cards">
            <div class="mini-card">
              <h4>Revenue</h4>
              <p id="anRevenue">$0</p>
            </div>
            <div class="mini-card">
              <h4>Orders</h4>
              <p id="anOrders">0</p>
            </div>
            <div class="mini-card">
              <h4>Avg Order</h4>
              <p id="anAOV">$0</p>
            </div>
            <div class="mini-card">
              <h4>Top Customer</h4>
              <p id="anTopCustomer">—</p>
            </div>
          </div>

          <div class="charts-grid">
            <div class="chart-card">
              <h3>Revenue Over Time</h3>
              <p class="muted" id="anRangeLabel"></p>
              <div class="chart-wrap"><canvas id="anChartRevenue"></canvas></div>
            </div>
            <div class="chart-card">
              <h3>Top Customers (Revenue)</h3>
              <p class="muted">Top 5 for the selected period.</p>
              <div class="chart-wrap"><canvas id="anChartTopCust"></canvas></div>
            </div>
          </div>
        </div>
      </section>

    


    
    </main>
  </div>

  <!-- Modal Add/Edit -->
  <div class="modal-backdrop" id="modalBackdrop">
    <div class="modal">
      <h2 id="modalTitle">Add Sale</h2>
      <div class="form-grid">
        <div><label>ID</label><input id="fId" type="number" placeholder="e.g. 1001"></div>
        <div><label>Date</label><input id="fDate" type="date"></div>
        <div class="full"><label>Customer</label><input id="fCustomer" type="text" placeholder="Customer name"></div>
        <div><label>Total</label><input id="fTotal" type="number" step="0.01" placeholder="Amount"></div>
        <div>
          <label>Status</label>
          <select id="fStatus">
            <option>Completed</option>
            <option>Pending</option>
            <option>Cancelled</option>
          </select>
        </div>
      </div>
      <div class="footer">
        <button class="btn light" id="btnCancel">Cancel</button>
        <button class="btn" id="btnSave">Save</button>
      </div>
    </div>
  </div>
<script>
const sales = <?= json_encode($sales) ?>;

// --- Prepare Data by Date ---
const daily = {};
sales.forEach(s => {
  daily[s.date] = (daily[s.date]||0) + Number(s.total);
});
const labels = Object.keys(daily).sort();
const values = labels.map(d => daily[d]);

new Chart(document.getElementById("chartRevenue"), {
  type: "line",
  data: {
    labels,
    datasets: [{
      label: "Revenue",
      data: values,
      borderColor: "blue",
      fill: false
    }]
  }
});

//popup Add 

     function openForm() {
      document.getElementById("AddForm").style.display = "flex"; // show
    }

    function closeForm() {
      document.getElementById("AddForm").style.display = "none"; // hide
    }

//edit and delete
function openModal(id) { document.getElementById(id).style.display = "flex"; }
function closeModal(id) { document.getElementById(id).style.display = "none"; }
  
    
//sort js

document.querySelectorAll("#salesTable th").forEach((th, idx) => {
  th.addEventListener("click", () => {
    const table = th.closest("table");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.querySelectorAll("tr"));
    const asc = th.classList.toggle("asc"); // toggle ascending/descending

    rows.sort((a, b) => {
      let valA = a.cells[idx].innerText.trim();
      let valB = b.cells[idx].innerText.trim();

      // If numeric, compare as numbers
      if(!isNaN(valA) && !isNaN(valB)) {
        valA = Number(valA);
        valB = Number(valB);
      }

      return asc ? (valA > valB ? 1 : -1) : (valA < valB ? 1 : -1);
    });

    rows.forEach(row => tbody.appendChild(row));
  });
});


  


  


  </script>
</body>

</html>