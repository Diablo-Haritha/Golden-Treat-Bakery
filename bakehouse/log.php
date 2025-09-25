<?php
// Database configuration - UPDATE THESE WITH YOUR CREDENTIALS
$servername = "localhost";
$username = "root";  // Replace with your MySQL username
$password = "";  // Replace with your MySQL password
$dbname = "golden_treat";    // Replace with your database name


// Create connection using PDO for better security and error handling
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Query to fetch log entries - ordered by timestamp descending
$sql = "SELECT * FROM sales_log ORDER BY log_timestamp DESC LIMIT 100"; // Limit to prevent overwhelming display
$stmt = $pdo->prepare($sql);
$stmt->execute();
$logEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Build SQL query with filters
$sql = "SELECT * FROM sales_log WHERE 1=1";
$params = [];

if (!empty($_GET['from'])) {
    $sql .= " AND date >= :from";
    $params[':from'] = $_GET['from'];
}
if (!empty($_GET['to'])) {
    $sql .= " AND date <= :to";
    $params[':to'] = $_GET['to'];
}
if (!empty($_GET['action'])) {
    $sql .= " AND operation = :action";
    $params[':action'] = $_GET['action'];
}
if (!empty($_GET['status'])) {
    $sql .= " AND status = :status";
    $params[':status'] = $_GET['status'];
}

$sql .= " ORDER BY log_timestamp DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_log_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Sale ID', 'Operation', 'Date', 'Customer', 'Quantity', 'Total', 'Status', 'User', 'Timestamp']);
    foreach ($logEntries as $row) {
        fputcsv($output, [
            $row['log_id'],
            $row['sale_id'] ?? 'N/A',
            $row['operation'],
            $row['date'] ?? 'N/A',
            $row['customer'] ?? 'N/A',
            $row['quantity'] ?? 'N/A',
            number_format($row['total'] ?? 0, 2),
            $row['status'] ?? 'N/A',
            $row['user'] ?? 'N/A',
            date('Y-m-d H:i:s', strtotime($row['log_timestamp']))
        ]);
    }
    fclose($output);
    exit;
}
?>

?>



<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Logs Management Admin UI</title>
  <!-- Charts & export -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
    crossorigin="anonymous"></script>
  <link rel="stylesheet" href="style.css">
  <style>
    #sum_dashboard{
        display:none;
    }
    :root {
      --brand: #000000;
      --ink: #ffffff;
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
      background: #000000;
      color: #f3f3f3;
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
      outline: 3px solid rgb(0, 0, 0);
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
      outline: 3px solid rgba(0, 0, 0, 0.605);
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
      background: #000000;
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
      background: rgba(252, 252, 252, 0.635);
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
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Logs Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer or action..." />
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
      <h1>Logs Dashboard</h1>
      <nav>
        <button class="salesbtn" onclick="window.location.href='logs.php'">Logs</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='./stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='./order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='./booking/index.html'">Booking</button>
        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn" onclick="window.location.href='../sales/index.php'">Booking Logs Management</button>
          <button class="tab-btn" onclick="window.location.href='../sales/index2.php'">Stock Logs Management</button>
          <button class="tab-btn" onclick="window.location.href='./users_log.php'">User Logs Management</button>
          <button class="tab-btn active" onclick="window.location.href='logs.php'">sales Logs Management</button>
          <!-- Button to open popup -->

        </div>
      </nav>
    </aside>
       <!-- Main -->
    <main class="free-area">


<!-- Popup Section -->
<section id="sum_dashboard" class="popuplog-section"> <!-- Statistics Cards -->
                        <?php
                        $stats = ['INSERT' => 0, 'UPDATE' => 0, 'DELETE' => 0];
                        foreach ($logEntries as $entry) {
                            $stats[$entry['operation']]++;
                        }
                        ?>
                        <div class="cards">
                            <div class="card">
                                <h3>Insert Operations</h3>
                                <p><?php echo $stats['INSERT']; ?></p>
                                <button class="info-btn">View Details</button>
                            </div>
                            <div class="card">
                                <h3>Update Operations</h3>
                                <p><?php echo $stats['UPDATE']; ?></p>
                                <button class="info-btn">View Details</button>
                            </div>
                            <div class="card">
                                <h3>Delete Operations</h3>
                                <p><?php echo $stats['DELETE']; ?></p>
                                <button class="info-btn">View Details</button>
                            </div>
                        </div></section>
 
       
      <!-- Dashboard -->
      <section id="logs-dashboard" class="panel active">
        <div class="content">
          <h1>Logs Management</h1>
     
          <div class="toolbar">
            <div class="filter-bar">
              <!-- FILTER FORM -->
              <form method="GET">
                From: <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
                To: <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
                Action:
                <select name="action">
                  <option value="">All</option>
                  <option <?=(($_GET['action'] ?? '' )=="INSERT" ? "selected" : "" ) ?>>INSERT</option>
                  <option <?=(($_GET['action'] ?? '' )=="UPDATE" ? "selected" : "" ) ?>>UPDATE</option>
                  <option <?=(($_GET['action'] ?? '' )=="DELETE" ? "selected" : "" ) ?>>DELETE</option>
                </select>
                Status:
                <select name="status">
                  <option value="">All</option>
                  <option <?=(($_GET['status'] ?? '' )=="Completed" ? "selected" : "" ) ?>>Completed</option>
                  <option <?=(($_GET['status'] ?? '' )=="Pending" ? "selected" : "" ) ?>>Pending</option>
                  <option <?=(($_GET['status'] ?? '' )=="Paid" ? "selected" : "" ) ?>>Paid</option>
                  <option <?=(($_GET['status'] ?? '' )=="Cancelled" ? "selected" : "" ) ?>>Cancelled</option>
                </select>
                <button type="submit">Filter</button>
              </form>
              <div class="header-right">
                <a class="btn" href="?<?= http_build_query(array_merge($_GET, [" export"=> "csv"])) ?>">⬇CSV</a>
                <button id="btnSumDashboard" class="btn">View Sales Summary</button>

              </div>


            </div>
          </div>
          <div class="table-wrap">
            <!-- Log Table -->
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Log ID</th>
                                        <th>Sale ID</th>
                                        <th>Operation</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>User</th>
                                        <th>Timestamp</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logEntries as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['log_id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['sale_id'] ?? 'N/A'); ?></td>
                                            <td><span class="badge operation-<?php echo strtolower($row['operation']); ?>"><?php echo htmlspecialchars($row['operation']); ?></span></td>
                                            <td><?php echo htmlspecialchars($row['date'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['customer'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['quantity'] ?? 'N/A'); ?></td>
                                            <td>Rs.<?php echo number_format($row['total'] ?? 0, 2); ?></td>
                                            <td><?php echo htmlspecialchars($row['status'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($row['user'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('Y-m-d H:i:s', strtotime($row['log_timestamp'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script>
const sumDashboard = document.getElementById("sum_dashboard");
const btnSum = document.getElementById("btnSumDashboard");
const closeSum = document.getElementById("closeSum");

// Toggle popup on button click
btnSum.addEventListener("click", () => {
  sumDashboard.style.display = sumDashboard.style.display === "flex" ? "none" : "flex";
});

// Close when clicking the X
closeSum.addEventListener("click", () => {
  sumDashboard.style.display = "none";
});

// Optional: Close when clicking outside the popup content
sumDashboard.addEventListener("click", (e) => {
  if (e.target === sumDashboard) sumDashboard.style.display = "none";
});

        // Client-side table sorting
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.querySelector('table');
            if (table) {
                table.querySelectorAll('th').forEach((header, index) => {
                    header.style.cursor = 'pointer';
                    header.addEventListener('click', () => sortTable(index));
                });
            }

            // Tab switching
            const tabs = document.querySelectorAll('.tab-btn');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                });
            });
        });

        function sortTable(columnIndex) {
            const table = document.querySelector('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const isNumeric = ['quantity', 'total'].includes(['Log ID', 'Sale ID', 'Operation', 'Date', 'Customer', 'Quantity', 'Total', 'Status', 'User', 'Timestamp'][columnIndex]);
            const isDate = columnIndex === 3 || columnIndex === 9; // Date and Timestamp columns
            
            rows.sort((a, b) => {
                let aVal = a.cells[columnIndex].textContent.trim();
                let bVal = b.cells[columnIndex].textContent.trim();
                
                if (isNumeric) {
                    aVal = parseFloat(aVal.replace('$', '')) || 0;
                    bVal = parseFloat(bVal.replace('$', '')) || 0;
                    return aVal - bVal;
                } else if (isDate) {
                    return new Date(aVal) - new Date(bVal);
                } else {
                    return aVal.localeCompare(bVal);
                }
            });
            
            rows.forEach(row => tbody.appendChild(row));
        }
    </script>
</body>

</html>