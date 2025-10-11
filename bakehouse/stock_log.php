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

// Build SQL query with filters for stock_log
$sql = "SELECT * FROM stock_log WHERE 1=1";
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
if (!empty($_GET['search'])) {
    $sql .= " AND (partNumber LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

$sql .= " ORDER BY log_timestamp DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="stock_log_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Stock ID', 'Operation', 'Part Number', 'Date', 'Description', 'Quantity', 'Category', 'Status', 'Unit', 'Timestamp']);
    foreach ($logEntries as $row) {
        fputcsv($output, [
            $row['log_id'],
            $row['stock_id'] ?? 'N/A',
            $row['operation'],
            $row['partNumber'] ?? 'N/A',
            $row['date'] ?? 'N/A',
            $row['description'] ?? 'N/A',
            $row['quantity'] ?? 'N/A',
            $row['category'] ?? 'N/A',
            $row['status'] ?? 'N/A',
            $row['unit'] ?? 'N/A',
            date('Y-m-d H:i:s', strtotime($row['log_timestamp']))
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Stock Logs Management Admin UI</title>
  <!-- Export libraries (Chart.js removed) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
    crossorigin="anonymous"></script>
  <link rel="stylesheet" href="style.css">
  <style>
    #sum_dashboard {
        display: none;
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
      left: 0;
      width: 260px;
      height: calc(100vh - 70px);
      background: #fff;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 10px;
      border-right: 1px solid #e5e7eb;
      overflow-y: auto;
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

    .cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

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

    .in-stock {
      background: #d1fae5;
      color: #065f46;
    }

    .low {
      background: #fef3c7;
      color: #92400e;
    }

    .out-of-stock {
      background: #fee2e2;
      color: #991b1b;
    }

    .operation-insert {
      background: #d1fae5;
      color: #065f46;
    }

    .operation-update {
      background: #fef3c7;
      color: #92400e;
    }

    .operation-delete {
      background: #fee2e2;
      color: #991b1b;
    }

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

    @media (max-width: 1000px) {
      .cards {
        grid-template-columns: 1fr;
      }
      .sidebar {
        width: 220px;
      }
      .layout {
        margin-left: 220px;
      }
    }
  </style>
</head>

<body>
  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Stock Logs Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" name="search" placeholder="Search by part number or description..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" /></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <h1>Stock Logs Dashboard</h1>
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
          <button class="tab-btn active" onclick="window.location.href='stock_log.php'">Stock Logs Management</button>
          <button class="tab-btn" onclick="window.location.href='users_log.php'">User Logs Management</button>
          <button class="tab-btn" onclick="window.location.href='log.php'">Sales Logs Management</button>
        </div>
      </nav>
    </aside>

    <!-- Main -->
    <main class="free-area">
      <!-- Popup Section -->
      <section id="sum_dashboard" class="popup">
        <div class="popup-content">
          <span class="close" id="closeSum">&times;</span>
          <h2>Stock Operation Summary</h2>
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
              <button class="info-btn" onclick="filterByOperation('INSERT')">View Details</button>
            </div>
            <div class="card">
              <h3>Update Operations</h3>
              <p><?php echo $stats['UPDATE']; ?></p>
              <button class="info-btn" onclick="filterByOperation('UPDATE')">View Details</button>
            </div>
            <div class="card">
              <h3>Delete Operations</h3>
              <p><?php echo $stats['DELETE']; ?></p>
              <button class="info-btn" onclick="filterByOperation('DELETE')">View Details</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Dashboard -->
      <section id="logs-dashboard" class="panel active">
        <div class="content">
          <h1>Stock Logs Management</h1>
          <div class="toolbar">
            <div class="filter-bar">
              <form method="GET">
                From: <input type="date" name="from" value="<?= htmlspecialchars($_GET['from'] ?? '') ?>">
                To: <input type="date" name="to" value="<?= htmlspecialchars($_GET['to'] ?? '') ?>">
                Action:
                <select name="action">
                  <option value="">All</option>
                  <option <?= (($_GET['action'] ?? '') == "INSERT" ? "selected" : "") ?>>INSERT</option>
                  <option <?= (($_GET['action'] ?? '') == "UPDATE" ? "selected" : "") ?>>UPDATE</option>
                  <option <?= (($_GET['action'] ?? '') == "DELETE" ? "selected" : "") ?>>DELETE</option>
                </select>
                Status:
                <select name="status">
                  <option value="">All</option>
                  <option <?= (($_GET['status'] ?? '') == "In Stock" ? "selected" : "") ?>>In Stock</option>
                  <option <?= (($_GET['status'] ?? '') == "Low" ? "selected" : "") ?>>Low</option>
                  <option <?= (($_GET['status'] ?? '') == "Out of Stock" ? "selected" : "") ?>>Out of Stock</option>
                </select>
                <button type="submit">Filter</button>
              </form>
              <div class="header-right">
                <a class="btn" href="?<?= http_build_query(array_merge($_GET, ["export" => "csv"])) ?>">⬇ CSV</a>
                <button id="btnSumDashboard" class="btn">View Stock Summary</button>
              </div>
            </div>
          </div>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Log ID</th>
                  <th>Stock ID</th>
                  <th>Operation</th>
                  <th>Part Number</th>
                  <th>Date</th>
                  <th>Description</th>
                  <th>Quantity</th>
                  <th>Category</th>
                  <th>Status</th>
                  <th>Unit</th>
                  <th>Timestamp</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($logEntries as $row): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['log_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['stock_id'] ?? 'N/A'); ?></td>
                    <td><span class="badge operation-<?php echo strtolower($row['operation']); ?>"><?php echo htmlspecialchars($row['operation']); ?></span></td>
                    <td><?php echo htmlspecialchars($row['partNumber'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['date'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['description'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['quantity'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['category'] ?? 'N/A'); ?></td>
                    <td><span class="badge <?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>"><?php echo htmlspecialchars($row['status'] ?? 'N/A'); ?></span></td>
                    <td><?php echo htmlspecialchars($row['unit'] ?? 'N/A'); ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($row['log_timestamp'])); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </main>
  </div>

  <script>
    // Toggle summary popup
    const sumDashboard = document.getElementById("sum_dashboard");
    const btnSum = document.getElementById("btnSumDashboard");
    const closeSum = document.getElementById("closeSum");

    btnSum.addEventListener("click", () => {
      sumDashboard.style.display = sumDashboard.style.display === "flex" ? "none" : "flex";
    });

    closeSum.addEventListener("click", () => {
      sumDashboard.style.display = "none";
    });

    sumDashboard.addEventListener("click", (e) => {
      if (e.target === sumDashboard) sumDashboard.style.display = "none";
    });

    // Filter by operation for "View Details" buttons
    function filterByOperation(operation) {
      document.querySelector('select[name="action"]').value = operation;
      document.querySelector('form').submit();
    }

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

      // Client-side search filter
      const searchInput = document.getElementById('globalSearch');
      searchInput.addEventListener('input', () => {
        const filter = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
          const partNumber = row.cells[3].textContent.toLowerCase();
          const description = row.cells[5].textContent.toLowerCase();
          row.style.display = (partNumber.includes(filter) || description.includes(filter)) ? '' : 'none';
        });
      });
    });

    function sortTable(columnIndex) {
      const table = document.querySelector('table');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      const isNumeric = ['quantity'].includes(['Log ID', 'Stock ID', 'Operation', 'Part Number', 'Date', 'Description', 'Quantity', 'Category', 'Status', 'Unit', 'Timestamp'][columnIndex]);
      const isDate = columnIndex === 4 || columnIndex === 10; // Date and Timestamp columns
      
      rows.sort((a, b) => {
        let aVal = a.cells[columnIndex].textContent.trim();
        let bVal = b.cells[columnIndex].textContent.trim();
        
        if (isNumeric) {
          aVal = parseFloat(aVal) || 0;
          bVal = parseFloat(bVal) || 0;
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