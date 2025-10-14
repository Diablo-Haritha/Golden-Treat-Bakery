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

// Handle filters
$date_filter = $_GET['date_filter'] ?? 'all';
$status_filter = $_GET['status_filter'] ?? 'all';
$customer_filter = $_GET['customer_filter'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'date';
$sort_order = $_GET['sort_order'] ?? 'DESC';

// Build WHERE clause for filters
$where_conditions = [];
$params = [];
$types = '';

if ($date_filter !== 'all') {
    switch($date_filter) {
        case 'today':
            $where_conditions[] = "date = CURDATE()";
            break;
        case 'yesterday':
            $where_conditions[] = "date = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
            break;
        case 'week':
            $where_conditions[] = "date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $where_conditions[] = "date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'custom':
            if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
                $where_conditions[] = "date BETWEEN ? AND ?";
                $params[] = $_GET['start_date'];
                $params[] = $_GET['end_date'];
                $types .= 'ss';
            }
            break;
    }
}

if ($status_filter !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($customer_filter)) {
    $where_conditions[] = "customer LIKE ?";
    $params[] = "%$customer_filter%";
    $types .= 's';
}

$where_sql = '';
if (!empty($where_conditions)) {
    $where_sql = "WHERE " . implode(' AND ', $where_conditions);
}

// Sorting
$allowed_sort = ['date', 'total', 'customer', 'status', 'quantity'];
$sort_column = in_array($sort_by, $allowed_sort) ? $sort_by : 'date';
$order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';

// Get sales data with filters
$sales_sql = "SELECT * FROM sales $where_sql ORDER BY $sort_column $order, id DESC";
$stmt = $conn->prepare($sales_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$sales_result = $stmt->get_result();
$sales_data = $sales_result->fetch_all(MYSQLI_ASSOC);

// Statistics with same filters
function getStatistic($conn, $column, $function, $where_sql, $params, $types) {
    $sql = "SELECT $function($column) as value FROM sales $where_sql";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['value'] ?? 0;
}

// Calculate statistics with filters
$totalOrders = count($sales_data);
$totalRevenue = getStatistic($conn, 'total', 'SUM', $where_sql, $params, $types);
$todayRevenue = getStatistic($conn, 'total', 'SUM', "WHERE date = CURDATE()", [], '');
$highestSale = getStatistic($conn, 'total', 'MAX', $where_sql, $params, $types);
$averageSale = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

// Get unique customers for filter
$customers_sql = "SELECT DISTINCT customer FROM sales ORDER BY customer";
$customers_result = $conn->query($customers_sql);
$customers = [];
while($row = $customers_result->fetch_assoc()) {
    $customers[] = $row['customer'];
}

// Get status breakdown
$status_breakdown = [];
$status_sql = "SELECT status, COUNT(*) as count, SUM(total) as revenue FROM sales $where_sql GROUP BY status";
$status_stmt = $conn->prepare($status_sql);
if (!empty($params)) {
    $status_stmt->bind_param($types, ...$params);
}
$status_stmt->execute();
$status_result = $status_stmt->get_result();
while($row = $status_result->fetch_assoc()) {
    $status_breakdown[] = $row;
}

// Handle report generation
if (isset($_POST['generate_report'])) {
    $report_type = $_POST['generate_report'];
    if ($report_type === 'excel') {
        generateExcelReport($sales_data, $date_filter, $status_filter, $totalOrders, $totalRevenue, $averageSale, $highestSale, $status_breakdown);
    } elseif ($report_type === 'pdf') {
        generatePDFReport($sales_data, $date_filter, $status_filter, $totalOrders, $totalRevenue, $averageSale, $highestSale, $status_breakdown);
    } elseif ($report_type === 'csv') {
        generateCSVReport($sales_data);
    }
}

function generateExcelReport($data, $date_filter, $status_filter, $totalOrders, $totalRevenue, $averageSale, $highestSale, $status_breakdown) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_report_'.date('Y-m-d_H-i-s').'.csv"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel
    fwrite($output, "\xEF\xBB\xBF");
    
    // Report Header
    fputcsv($output, ['GOLDEN TREAT - SMART SALES REPORT']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Date Filter', ucfirst($date_filter)]);
    fputcsv($output, ['Status Filter', ucfirst($status_filter)]);
    fputcsv($output, ['Total Records', count($data)]);
    fputcsv($output, []);
    
    // Summary Statistics
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Sales', $totalOrders]);
    fputcsv($output, ['Total Revenue', 'Rs. ' . number_format($totalRevenue, 2)]);
    fputcsv($output, ['Average Sale', 'Rs. ' . number_format($averageSale, 2)]);
    fputcsv($output, ['Highest Sale', 'Rs. ' . number_format($highestSale, 2)]);
    fputcsv($output, []);
    
    // Status Breakdown
    fputcsv($output, ['STATUS BREAKDOWN']);
    fputcsv($output, ['Status', 'Count', 'Percentage', 'Revenue']);
    foreach ($status_breakdown as $status) {
        $percentage = $totalOrders > 0 ? ($status['count'] / $totalOrders) * 100 : 0;
        fputcsv($output, [
            $status['status'],
            $status['count'],
            number_format($percentage, 2) . '%',
            'Rs. ' . number_format($status['revenue'], 2)
        ]);
    }
    fputcsv($output, []);
    
    // Detailed Data
    fputcsv($output, ['DETAILED SALES DATA']);
    fputcsv($output, ['ID', 'Customer', 'Date', 'Items', 'Quantity', 'Total Amount', 'Status', 'Payment Method']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['customer'],
            $row['date'],
            $row['items'],
            $row['quantity'],
            number_format($row['total'], 2),
            $row['status'],
            $row['payment_method'] ?? 'N/A'
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['Report End', 'Generated by Golden Treat Sales Management System']);
    
    fclose($output);
    exit;
}

function generatePDFReport($data, $date_filter, $status_filter, $totalOrders, $totalRevenue, $averageSale, $highestSale, $status_breakdown) {
    // Simple HTML to PDF conversion
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="sales_report_'.date('Y-m-d_H-i-s').'.html"');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sales Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #333; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #ffdd57; padding-bottom: 15px; }
        .header h1 { color: #333; margin: 0; }
        .header p { color: #666; margin: 5px 0; }
        .section { margin: 20px 0; }
        .section h2 { background: #ffdd57; padding: 10px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: bold; }
        tr:nth-child(even) { background: #f8f9fa; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
        .stat-box { border: 2px solid #ffdd57; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; color: #333; }
        .stat-label { color: #666; margin-top: 5px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 15px; border-top: 2px solid #ddd; color: #666; }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>GOLDEN TREAT - SALES REPORT</h1>
        <p>Generated on: '.date('Y-m-d H:i:s').'</p>
        <p>Date Filter: '.ucfirst($date_filter).' | Status Filter: '.ucfirst($status_filter).'</p>
    </div>
    
    <div class="section">
        <h2>Summary Statistics</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value">'.$totalOrders.'</div>
                <div class="stat-label">Total Sales</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">Rs. '.number_format($totalRevenue, 2).'</div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">Rs. '.number_format($averageSale, 2).'</div>
                <div class="stat-label">Average Sale</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">Rs. '.number_format($highestSale, 2).'</div>
                <div class="stat-label">Highest Sale</div>
            </div>
        </div>
    </div>
    
    <div class="section">
        <h2>Status Breakdown</h2>
        <table>
            <tr>
                <th>Status</th>
                <th>Count</th>
                <th>Percentage</th>
                <th>Revenue</th>
            </tr>';
    
    foreach ($status_breakdown as $status) {
        $percentage = $totalOrders > 0 ? ($status['count'] / $totalOrders) * 100 : 0;
        echo '<tr>
                <td>'.$status['status'].'</td>
                <td>'.$status['count'].'</td>
                <td>'.number_format($percentage, 2).'%</td>
                <td>Rs. '.number_format($status['revenue'], 2).'</td>
            </tr>';
    }
    
    echo '</table>
    </div>
    
    <div class="section">
        <h2>Detailed Sales Data</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Date</th>
                <th>Items</th>
                <th>Qty</th>
                <th>Total</th>
                <th>Status</th>
            </tr>';
    
    foreach ($data as $row) {
        echo '<tr>
                <td>#'.$row['id'].'</td>
                <td>'.htmlspecialchars($row['customer']).'</td>
                <td>'.$row['date'].'</td>
                <td>'.htmlspecialchars(substr($row['items'], 0, 30)).'</td>
                <td>'.$row['quantity'].'</td>
                <td>Rs. '.number_format($row['total'], 2).'</td>
                <td>'.$row['status'].'</td>
            </tr>';
    }
    
    echo '</table>
    </div>
    
    <div class="footer">
        <p>Golden Treat Sales Management System | Report generated automatically</p>
        <p class="no-print"><button onclick="window.print()">Print this Report</button></p>
    </div>
</body>
</html>';
    exit;
}

function generateCSVReport($data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_data_'.date('Y-m-d_H-i-s').'.csv"');
    
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    
    fputcsv($output, ['ID', 'Customer', 'Date', 'Items', 'Quantity', 'Total', 'Status', 'Payment Method']);
    
    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'],
            $row['customer'],
            $row['date'],
            $row['items'],
            $row['quantity'],
            $row['total'],
            $row['status'],
            $row['payment_method'] ?? 'N/A'
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
  <title>Sales Management Admin UI</title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="style1.css">
  <style>
    * { box-sizing: border-box; }
    
    .card {
      background: rgba(0, 0, 0, 0.34);
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
      color: #ffdd57;
    }

    .card p {
      font-size: 2.0rem;
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

    .report-section {
      background: rgba(0, 0, 0, 0.34);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 25px;
      margin: 20px 0;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }

    .report-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 15px;
    }

    .filters {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      align-items: flex-end;
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .filter-group label {
      font-size: 0.9rem;
      color: #ffdd57;
      font-weight: bold;
    }

    .filter-select, .filter-input {
      padding: 8px 12px;
      border: 2px solid rgba(255, 221, 87, 0.3);
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.95);
      color: #000;
      min-width: 150px;
      transition: all 0.3s;
    }

    .filter-select:focus, .filter-input:focus {
      outline: none;
      border-color: #ffdd57;
      background: rgba(255, 255, 255, 1);
    }

    .report-buttons {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 15px;
    }

    .report-btn {
      padding: 12px 24px;
      border: none;
      border-radius: 10px;
      background: #4CAF50;
      color: white;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.95rem;
    }

    .report-btn.excel { background: #217346; }
    .report-btn.pdf { background: #f44336; }
    .report-btn.csv { background: #2196F3; }
    .report-btn.clear { background: #999; }
    .report-btn.apply { background: #666; }

    .report-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
      filter: brightness(1.1);
    }

    .sales-list {
      background: rgba(0, 0, 0, 0.34);
      backdrop-filter: blur(12px);
      border-radius: 20px;
      padding: 25px;
      margin: 20px 0;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
    }

    .table-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
      flex-wrap: wrap;
      gap: 10px;
    }

    .sort-controls {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .sort-controls select {
      padding: 6px 10px;
      border-radius: 6px;
      border: 2px solid rgba(255, 221, 87, 0.3);
      background: rgba(255, 255, 255, 0.1);
      color: white;
    }

    .sales-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
      overflow-x: auto;
    }

    .sales-table th,
    .sales-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sales-table th {
      background: rgba(255, 221, 87, 0.2);
      color: #ffdd57;
      font-weight: bold;
      cursor: pointer;
      user-select: none;
    }

    .sales-table th:hover {
      background: rgba(255, 221, 87, 0.3);
    }

    .sales-table tr:hover {
      background: rgba(255, 255, 255, 0.05);
    }

    .status-badge {
      padding: 5px 10px;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: bold;
      display: inline-block;
    }

    .status-completed { background: #4CAF50; color: white; }
    .status-pending { background: #ff9800; color: white; }
    .status-cancelled { background: #f44336; color: white; }
    .status-paid { background: #2196F3; color: white; }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 15px;
      margin: 20px 0;
    }

    .stat-card {
      background: rgba(255, 255, 255, 0.1);
      padding: 20px;
      border-radius: 12px;
      text-align: center;
      border: 2px solid rgba(255, 221, 87, 0.2);
      transition: all 0.3s;
    }

    .stat-card:hover {
      border-color: #ffdd57;
      transform: translateY(-3px);
    }

    .stat-value {
      font-size: 1.8rem;
      font-weight: bold;
      color: #ffdd57;
      margin: 5px 0;
    }

    .stat-label {
      font-size: 0.9rem;
      opacity: 0.8;
    }

    .popup {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(8px);
      align-items: center;
      justify-content: center;
    }

    .popup-content {
      background: rgba(30, 30, 30, 0.95);
      backdrop-filter: blur(20px);
      padding: 30px;
      border-radius: 20px;
      width: 450px;
      max-width: 90%;
      color: #fff;
      text-align: left;
      box-shadow: 0 10px 30px rgba(0,0,0,0.5);
      border: 2px solid rgba(255, 221, 87, 0.3);
      animation: fadeIn 0.3s ease-in-out;
    }

    .popup-content h2 {
      color: #ffdd57;
      margin-bottom: 15px;
    }

    .popup-content ul {
      list-style: none;
      padding: 0;
    }

    .popup-content li {
      padding: 8px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .close {
      float: right;
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
      color: #ffdd57;
      transition: all 0.3s;
    }

    .close:hover {
      color: #ffd633;
      transform: rotate(90deg);
    }

    @keyframes fadeIn {
      from {opacity: 0; transform: scale(0.9);}
      to {opacity: 1; transform: scale(1);}
    }

    .custom-date-range {
      display: none;
      gap: 10px;
      align-items: flex-end;
    }

    .custom-date-range.active {
      display: flex;
    }

    .export-indicator {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #4CAF50;
      color: white;
      padding: 15px 25px;
      border-radius: 10px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
      display: none;
      z-index: 2000;
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from { transform: translateX(400px); }
      to { transform: translateX(0); }
    }

    .no-data {
      text-align: center;
      padding: 40px;
      color: rgba(255, 255, 255, 0.5);
      font-size: 1.1rem;
    }

    @media (max-width: 768px) {
      .filters {
        flex-direction: column;
        width: 100%;
      }
      
      .filter-group {
        width: 100%;
      }
      
      .filter-select, .filter-input {
        width: 100%;
        color:black;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Export Indicator -->
  <div id="exportIndicator" class="export-indicator">
    Report generated successfully!
  </div>

  <!-- Header -->
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Sales Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search sales..." /></div>
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
        <button class="salesbtn" onclick="window.location.href='index2.php'">Sales</button>
        <div class="otherbtn">
          <button class="Sbtn" onclick="window.location.href='../stoke/stock.php'">Stock</button>
          <button class="Ubtn" onclick="window.location.href='../order/order.php'">Order</button>
          <button class="Bbtn" onclick="window.location.href='../bookig/booking.html'">Booking</button>
        </div>
        <hr />
        <p>Sales Management</p>
        <div class="salebtn">
          <button class="tab-btn" onclick="window.location.href='index.php'">Sales Dashboard</button>
          <button class="tab-btn active" onclick="window.location.href='index2.php'">Sales SUM</button>
          <button class="tab-btn" onclick="window.location.href='index3.php'">Sales Analysis</button>
        </div>
      </nav>
    </aside>

    <!-- Main -->
    <main class="free-area">
      <section id="sales-dashboard" class="panel active">
        <div class="content">
          <h1>Smart Sales Management</h1>

          <!-- Smart Report Section -->
          <div class="report-section">
            <div class="report-header">
              <h2>Smart Report Generator</h2>
            </div>
            
            <form method="GET" class="filters" id="filterForm">
              <div class="filter-group">
                <label>Date Range</label>
                <select name="date_filter" id="dateFilter" class="filter-select" onchange="toggleCustomDate()">
                  <option value="all" <?= $date_filter === 'all' ? 'selected' : '' ?>>All Time</option>
                  <option value="today" <?= $date_filter === 'today' ? 'selected' : '' ?>>Today</option>
                  <option value="yesterday" <?= $date_filter === 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                  <option value="week" <?= $date_filter === 'week' ? 'selected' : '' ?>>Last 7 Days</option>
                  <option value="month" <?= $date_filter === 'month' ? 'selected' : '' ?>>Last 30 Days</option>
                  <option value="custom" <?= $date_filter === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                </select>
              </div>
              
              <div class="custom-date-range <?= $date_filter === 'custom' ? 'active' : '' ?>" id="customDateRange">
                <div class="filter-group">
                  <label>Start Date</label>
                  <input type="date" name="start_date" class="filter-input" value="<?= $_GET['start_date'] ?? '' ?>">
                </div>
                <div class="filter-group">
                  <label>End Date</label>
                  <input type="date" name="end_date" class="filter-input" value="<?= $_GET['end_date'] ?? '' ?>">
                </div>
              </div>
              
              <div class="filter-group">
                <label>Status</label>
                <select name="status_filter" class="filter-select">
                  <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                  <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                  <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                  <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                  <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                </select>
              </div>
              
              <div class="filter-group">
                <label>Customer</label>
                <input type="text" name="customer_filter" class="filter-input" placeholder="Search customer..." 
                       value="<?= htmlspecialchars($customer_filter) ?>" list="customerList">
                <datalist id="customerList">
                  <?php foreach($customers as $customer): ?>
                    <option value="<?= htmlspecialchars($customer) ?>">
                  <?php endforeach; ?>
                </datalist>
              </div>
              
              <div class="filter-group">
                <label>&nbsp;</label>
                <button type="submit" class="report-btn apply">Apply Filters</button>
              </div>
              
              <div class="filter-group">
                <label>&nbsp;</label>
                <button type="button" class="report-btn clear" onclick="window.location.href='index2.php'">Clear All</button>
              </div>
            </form>

            <div class="stats-grid">
              <div class="stat-card">
                <div class="stat-label">Total Sales</div>
                <div class="stat-value"><?= $totalOrders ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">Rs. <?= number_format($totalRevenue, 2) ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Average Sale</div>
                <div class="stat-value">Rs. <?= number_format($averageSale, 2) ?></div>
              </div>
              <div class="stat-card">
                <div class="stat-label">Highest Sale</div>
                <div class="stat-value">Rs. <?= number_format($highestSale, 2) ?></div>
              </div>
            </div>

            <form method="POST" class="report-buttons" id="reportForm">
              <button type="submit" name="generate_report" value="excel" class="report-btn excel">
                Excel Report
              </button>
              <button type="submit" name="generate_report" value="pdf" class="report-btn pdf">
                PDF Report
              </button>
              <button type="submit" name="generate_report" value="csv" class="report-btn csv">
                CSV Export
              </button>
              <button type="button" class="report-btn" style="background: #9C27B0;" onclick="printTable()">
                Print View
              </button>
            </form>
          </div>

          <!-- Status Breakdown Chart 
          <?php if (!empty($status_breakdown)): ?>
          <div class="report-section">
            <h2>Status Breakdown</h2>
            <div style="max-width: 600px; margin: 20px auto;">
              <canvas id="statusChart"></canvas>
            </div>
            <div class="stats-grid" style="margin-top: 20px;">
              <?php foreach($status_breakdown as $status): ?>
                <div class="stat-card">
                  <div class="stat-label"><?= $status['status'] ?></div>
                  <div class="stat-value"><?= $status['count'] ?></div>
                  <div class="stat-label" style="margin-top: 5px;">
                    Rs. <?= number_format($status['revenue'], 2) ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>-->

          <!-- Sales List -->
          <div class="sales-list">
            <div class="table-controls">
              <h2>Sales List (<?= count($sales_data) ?> records)</h2>
              <div class="sort-controls">
                <label style="color: #ffdd57;">Sort by:</label>
                <form method="GET" style="display: flex; gap: 10px;">
                  <?php foreach(['date_filter', 'status_filter', 'customer_filter'] as $field): ?>
                    <input type="hidden" name="<?= $field ?>" value="<?= $_GET[$field] ?? '' ?>">
                  <?php endforeach; ?>
                  
                  <select name="sort_by" class="filter-select" onchange="this.form.submit()">
                    <option value="date" <?= $sort_by === 'date' ? 'selected' : '' ?>>Date</option>
                    <option value="total" <?= $sort_by === 'total' ? 'selected' : '' ?>>Amount</option>
                    <option value="customer" <?= $sort_by === 'customer' ? 'selected' : '' ?>>Customer</option>
                    <option value="status" <?= $sort_by === 'status' ? 'selected' : '' ?>>Status</option>
                    <option value="quantity" <?= $sort_by === 'quantity' ? 'selected' : '' ?>>Quantity</option>
                  </select>
                  
                  <select name="sort_order" class="filter-select" onchange="this.form.submit()">
                    <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                    <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                  </select>
                </form>
              </div>
            </div>
            
            <?php if (empty($sales_data)): ?>
              <div class="no-data">
                No sales records found with current filters
              </div>
            <?php else: ?>
              <div style="overflow-x: auto;">
                <table class="sales-table" id="salesTable">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>Customer</th>
                      <th>Date</th>
                      
                      <th>Quantity</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sales_data as $sale): ?>
                      <tr>
                        <td><strong>#<?= $sale['id'] ?></strong></td>
                        <td><?= htmlspecialchars($sale['customer']) ?></td>
                        <td><?= date('M d, Y', strtotime($sale['date'])) ?></td>
                       
                        <td><?= $sale['quantity'] ?></td>
                        <td><strong>Rs. <?= number_format($sale['total'], 2) ?></strong></td>
                        <td>
                          <span class="status-badge status-<?= strtolower($sale['status']) ?>">
                            <?= $sale['status'] ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Quick Stats Cards -->
          <div class="cards">
            <div class="card">
              <h3>Today's Revenue</h3>
              <p>Rs. <?= number_format($todayRevenue, 2) ?></p>
              <button class="info-btn" data-popup="popup1">More Info</button>
            </div>

            <div class="card">
              <h3>Total Orders</h3>
              <p><?= $totalOrders ?></p>
              <button class="info-btn" data-popup="popup2">More Info</button>
            </div>

            <div class="card">
              <h3>Average Sale</h3>
              <p>Rs. <?= number_format($averageSale, 2) ?></p>
              <button class="info-btn" data-popup="popup3">More Info</button>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- Popups -->
  <div id="popup1" class="popup">
    <div class="popup-content">
      <span class="close">&times;</span>
      <h2>Today's Revenue</h2>
      <p>Total revenue generated today: <strong>Rs. <?= number_format($todayRevenue, 2) ?></strong></p>
      <ul>
        <li><strong>Filtered Revenue:</strong> Rs. <?= number_format($totalRevenue, 2) ?></li>
        <li><strong>Average Sale:</strong> Rs. <?= number_format($averageSale, 2) ?></li>
        <li><strong>Highest Sale:</strong> Rs. <?= number_format($highestSale, 2) ?></li>
        <li><strong>Date Range:</strong> <?= ucfirst($date_filter) ?></li>
      </ul>
    </div>
  </div>

  <div id="popup2" class="popup">
    <div class="popup-content">
      <span class="close">&times;</span>
      <h2>Total Orders</h2>
      <p>Total orders in current view: <strong><?= $totalOrders ?></strong></p>
      <ul>
        <li><strong>Date Filter:</strong> <?= ucfirst($date_filter) ?></li>
        <li><strong>Status Filter:</strong> <?= ucfirst($status_filter) ?></li>
        <li><strong>Customer Filter:</strong> <?= $customer_filter ?: 'None' ?></li>
        <li><strong>Sort By:</strong> <?= ucfirst($sort_by) ?> (<?= $sort_order ?>)</li>
      </ul>
      <?php if (!empty($status_breakdown)): ?>
        <h3 style="margin-top: 15px; color: #ffdd57;">Status Breakdown:</h3>
        <ul>
          <?php foreach($status_breakdown as $status): ?>
            <li><strong><?= $status['status'] ?>:</strong> <?= $status['count'] ?> orders</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div id="popup3" class="popup">
    <div class="popup-content">
      <span class="close">&times;</span>
      <h2>Average Sale</h2>
      <p>Average sale amount: <strong>Rs. <?= number_format($averageSale, 2) ?></strong></p>
      <ul>
        <li><strong>Total Revenue:</strong> Rs. <?= number_format($totalRevenue, 2) ?></li>
        <li><strong>Total Orders:</strong> <?= $totalOrders ?></li>
        <li><strong>Highest Sale:</strong> Rs. <?= number_format($highestSale, 2) ?></li>
        <li><strong>Today's Revenue:</strong> Rs. <?= number_format($todayRevenue, 2) ?></li>
      </ul>
    </div>
  </div>

  <script>
    // Popup functionality
    document.querySelectorAll('.info-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.getElementById(btn.dataset.popup).style.display = 'flex';
      });
    });

    document.querySelectorAll('.close').forEach(c => {
      c.addEventListener('click', () => {
        c.closest('.popup').style.display = 'none';
      });
    });

    window.addEventListener('click', (e) => {
      document.querySelectorAll('.popup').forEach(popup => {
        if (e.target === popup) popup.style.display = 'none';
      });
    });

    // Enhanced search with highlighting
    document.getElementById('globalSearch').addEventListener('input', function(e) {
      const searchTerm = e.target.value.toLowerCase();
      const rows = document.querySelectorAll('.sales-table tbody tr');
      let visibleCount = 0;
      
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });
      
      // Update count
      const countDisplay = document.querySelector('.table-controls h2');
      if (countDisplay) {
        countDisplay.textContent = `Sales List (${visibleCount} of <?= count($sales_data) ?> records)`;
      }
    });

    // Report generation with feedback
    document.querySelectorAll('.report-btn[type="submit"]').forEach(btn => {
      btn.addEventListener('click', function(e) {
        const reportType = this.value.toUpperCase();
        if (confirm(`Generate and download ${reportType} report with current filters?`)) {
          showExportIndicator();
        } else {
          e.preventDefault();
        }
      });
    });

    function showExportIndicator() {
      const indicator = document.getElementById('exportIndicator');
      indicator.style.display = 'block';
      setTimeout(() => {
        indicator.style.display = 'none';
      }, 3000);
    }

    // Custom date range toggle
    function toggleCustomDate() {
      const select = document.getElementById('dateFilter');
      const customRange = document.getElementById('customDateRange');
      if (select.value === 'custom') {
        customRange.classList.add('active');
      } else {
        customRange.classList.remove('active');
      }
    }

    // Print functionality
    function printTable() {
      const printContent = `
        <html>
        <head>
          <title>Sales Report - Print</title>
          <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #333; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f8f9fa; font-weight: bold; }
            .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0; }
            .stat-box { border: 2px solid #ddd; padding: 15px; text-align: center; }
            @media print { button { display: none; } }
          </style>
        </head>
        <body>
          <h1>Golden Treat - Sales Report</h1>
          <p><strong>Generated:</strong> ${new Date().toLocaleString()}</p>
          <p><strong>Filters:</strong> Date: <?= ucfirst($date_filter) ?>, Status: <?= ucfirst($status_filter) ?></p>
          
          <div class="stats">
            <div class="stat-box">
              <h3>Total Sales</h3>
              <p><?= $totalOrders ?></p>
            </div>
            <div class="stat-box">
              <h3>Total Revenue</h3>
              <p>Rs. <?= number_format($totalRevenue, 2) ?></p>
            </div>
            <div class="stat-box">
              <h3>Average Sale</h3>
              <p>Rs. <?= number_format($averageSale, 2) ?></p>
            </div>
            <div class="stat-box">
              <h3>Highest Sale</h3>
              <p>Rs. <?= number_format($highestSale, 2) ?></p>
            </div>
          </div>
          
          ${document.querySelector('.sales-table').outerHTML}
          
          <button onclick="window.print()" style="margin: 20px auto; display: block; padding: 10px 30px; background: #4CAF50; color: white; border: none; border-radius: 5px; cursor: pointer;">Print This Report</button>
        </body>
        </html>
      `;
      
      const printWindow = window.open('', '_blank');
      printWindow.document.write(printContent);
      printWindow.document.close();
    }

    // Status Chart
    <?php if (!empty($status_breakdown)): ?>
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: [<?php echo implode(',', array_map(function($s) { return "'" . $s['status'] . "'"; }, $status_breakdown)); ?>],
        datasets: [{
          label: 'Orders by Status',
          data: [<?php echo implode(',', array_map(function($s) { return $s['count']; }, $status_breakdown)); ?>],
          backgroundColor: [
            '#4CAF50',
            '#ff9800',
            '#f44336',
            '#2196F3',
            '#9C27B0'
          ],
          borderWidth: 2,
          borderColor: 'rgba(0,0,0,0.1)'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              color: '#fff',
              font: { size: 14 }
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const label = context.label || '';
                const value = context.parsed || 0;
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((value / total) * 100).toFixed(1);
                return `${label}: ${value} (${percentage}%)`;
              }
            }
          }
        }
      }
    });
    <?php endif; ?>

    // Table row click to show details (optional enhancement)
    document.querySelectorAll('.sales-table tbody tr').forEach(row => {
      row.style.cursor = 'pointer';
      row.addEventListener('click', function() {
        this.style.background = 'rgba(255, 221, 87, 0.1)';
        setTimeout(() => {
          this.style.background = '';
        }, 300);
      });
    });

    console.log('Golden Treat Sales Management System');
    console.log('Total Sales: <?= $totalOrders ?> | Revenue: Rs. <?= number_format($totalRevenue, 2) ?>');
  </script>
</body>
</html>