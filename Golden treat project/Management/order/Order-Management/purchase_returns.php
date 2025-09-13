<?php
// purchase_returns.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// helper for dynamic bind_param
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// read filters (GET)
$from = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : '';
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : '';
$customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';
$order_id = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? (int)$_GET['order_id'] : '';
$processed_by = isset($_GET['processed_by']) && $_GET['processed_by'] !== '' ? (int)$_GET['processed_by'] : '';
$limit = 2000; // safety limit

$where = [];
$types = '';
$values = [];

if ($from !== '') {
    $where[] = "r.return_date >= ?";
    $types .= 's';
    $values[] = $from;
}
if ($to !== '') {
    $where[] = "r.return_date <= ?";
    $types .= 's';
    $values[] = $to;
}
if ($customer !== '') {
    $where[] = "o.customer LIKE ?";
    $types .= 's';
    $values[] = '%' . $customer . '%';
}
if ($order_id) {
    $where[] = "r.order_id = ?";
    $types .= 'i';
    $values[] = $order_id;
}
if ($processed_by) {
    $where[] = "r.processed_by = ?";
    $types .= 'i';
    $values[] = $processed_by;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

// Query: join returns -> orders and users (for processed_by name)
$sql = "
SELECT
  r.id AS return_id,
  r.order_id,
  r.return_date,
  r.quantity AS returned_quantity,
  r.reason,
  r.refund_amount,
  r.processed_by,
  u.full_name AS processed_by_name,
  r.created_at AS recorded_at,
  o.order_date AS order_date,
  o.customer,
  o.product
FROM returns r
LEFT JOIN orders o ON o.id = r.order_id
LEFT JOIN users u ON u.id = r.processed_by
{$whereSql}
ORDER BY r.return_date DESC, r.id DESC
LIMIT {$limit}
";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
if (!empty($values)) {
    array_unshift($values, $types);
    call_user_func_array([$stmt, 'bind_param'], refValues($values));
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// compute totals
$totalQty = 0;
$totalRefund = 0.0;
foreach ($rows as $r) {
    $totalQty += (int)$r['returned_quantity'];
    $totalRefund += (float)$r['refund_amount'];
}

// quick stat: total returns amount (card)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Purchase Returns - Admin</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
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
  .btnview, .btnupdate, .btndelete, .btnreturn {
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
    background-color: #28a745;
  }
  .btnupdate:hover {
    background-color: #28a745;
  }
  .btnreturn {
    background-color: #f39c12;
  }
  .btnreturn:hover {
    background-color: #d98200;
  }

  /* Delete button (red) */
  .btndelete {
    background-color: #e74c3c;
  }
  .btndelete:hover {
    background-color: #c0392b;
  }
    /* quick overlay: show sales-export as a panel inside the free-area visually */
  #sales-export {
    position: relative;            /* ensure positioned */
    margin-top: 0;
    padding-top: 0;
    /* optionally visually match other .content */
    display: none;                 /* keep hidden by default, show only when .active is present */
  }
  #sales-export.active {
    display: block;
  }

  /* ensure it appears above footer/other content */
  #sales-export .content {
    background: var(--brand);
    border-radius: 14px;
    padding: 18px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    max-width: calc(100% - 48px);
    margin: 0 auto 24px;
  }
  /* Order History button — matches .salebtn buttons */
  .orderhistory {
    background: var(--brand);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 10px 12px;
    margin: 10px 0;
    font-weight: 700;
    font-size: 14px;
    text-align: left;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    width: calc(100% - 20px); 
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
  }

  /* hover / focus */
  .orderhistory:hover,
  .orderhistory:focus {
    filter: brightness(1.06);
    outline: none;
  }

  .orderhistory.active {
    outline: 3px solid rgba(146, 48, 182, 0.35);
  }

  
</style>
</head>
<body>
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Order Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by customer, status or ID..." /></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h1>Purchase Dashboard</h1>
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
          <button class="tab-btn" data-page="sales-dashboard" onclick="window.location.href='order.php'">Purchase Dashboard</button>
          <button class="tab-btn" data-page="sales-export" onclick="window.location.href='order.php#sales-export'">Export Report</button>
          <button type="button" class="orderhistory active" onclick="window.location.href='purchase_returns.php'">Purchase Returns</button>
          <button type="button" class="orderhistory" onclick="window.location.href='purchase_history.php'">Purchase History</button>
        </div>
      </nav>
    </aside>

    <main class="free-area">
      <section id="returns-panel" class="panel active">
        <div class="content" style="background: #fff;"> <!-- keep content white for clarity -->
          <h2 style="color:var(--brand);">Purchase Returns</h2>

          <div class="cards" style="grid-template-columns: repeat(3, 1fr);">
            <div class="card">
              <h3>Total Returned Qty</h3>
              <p><?= (int)$totalQty ?></p>
            </div>
            <div class="card">
              <h3>Total Refunds</h3>
              <p>Rs. <?= number_format($totalRefund,2) ?></p>
            </div>
            <div class="card">
              <h3>Rows</h3>
              <p><?= count($rows) ?></p>
            </div>
          </div>

          <div class="toolbar" style="align-items:flex-start;">
            <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
              <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" />
              <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" />
              <input type="text" name="customer" placeholder="Customer" value="<?= htmlspecialchars($customer) ?>" />
              <input type="number" name="order_id" placeholder="Order ID" value="<?= ($order_id ? (int)$order_id : '') ?>" />
              <input type="number" name="processed_by" placeholder="Processed by (admin id)" value="<?= ($processed_by ? (int)$processed_by : '') ?>" />
              <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
              <button class="btn secondary" id="exportCsv" type="button"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
              <div class="muted" style="margin-left:auto;align-self:center">Showing up to <?= $limit ?> rows</div>
            </form>
          </div>

          <div class="table-wrap" style="margin-top:8px;">
            <table id="returnsTable">
              <thead>
                <tr>
                  <th>Return ID</th>
                  <th>Order ID</th>
                  <th>Order Date</th>
                  <th>Return Date</th>
                  <th>Customer</th>
                  <th>Product</th>
                  <th>Returned Qty</th>
                  <th>Refund (Rs.)</th>
                  <th>Processed By</th>
                  <th>Reason</th>
                  <th>Recorded At</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($rows)): ?>
                  <tr><td colspan="11" style="text-align:center;padding:18px">No returns found for these filters</td></tr>
                <?php else: foreach($rows as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['return_id']) ?></td>
                    <td><?= htmlspecialchars($r['order_id']) ?></td>
                    <td><?= htmlspecialchars($r['order_date']) ?></td>
                    <td><?= htmlspecialchars($r['return_date']) ?></td>
                    <td><?= htmlspecialchars($r['customer']) ?></td>
                    <td><?= htmlspecialchars($r['product']) ?></td>
                    <td><?= (int)$r['returned_quantity'] ?></td>
                    <td><?= number_format((float)$r['refund_amount'],2) ?></td>
                    <td><?= htmlspecialchars($r['processed_by_name'] ?: $r['processed_by']) ?></td>
                    <td><?= htmlspecialchars($r['reason']) ?></td>
                    <td><?= htmlspecialchars($r['recorded_at']) ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </section>
    </main>
  </div>

  <script>
    // CSV export that mirrors order.php export style (exports visible table rows)
    function quoteCSV(s){
      const str = String(s ?? '');
      return /[",\n]/.test(str) ? `"${str.replace(/"/g,'""')}"` : str;
    }
    function toCSV(rows){
      const header = ['Return ID','Order ID','Order Date','Return Date','Customer','Product','Returned Qty','Refund','Processed By','Reason','Recorded At'];
      const lines = [header.join(',')];
      rows.forEach(r => lines.push([
        quoteCSV(r[0]), quoteCSV(r[1]), quoteCSV(r[2]), quoteCSV(r[3]), quoteCSV(r[4]), quoteCSV(r[5]), r[6], r[7], quoteCSV(r[8]), quoteCSV(r[9]), quoteCSV(r[10])
      ].join(',')));
      return lines.join('\n');
    }

    document.getElementById('exportCsv').addEventListener('click', () => {
      const rows = Array.from(document.querySelectorAll('#returnsTable tbody tr')).map(tr => {
        const tds = tr.querySelectorAll('td');
        if (!tds.length) return null;
        return Array.from(tds).map(td => td.textContent.trim());
      }).filter(Boolean);
      if (!rows.length) {
        alert('No rows to export');
        return;
      }
      const csv = toCSV(rows);
      const blob = new Blob([csv], {type:'text/csv'});
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'purchase_returns.csv';
      a.click();
      setTimeout(()=>URL.revokeObjectURL(a.href),1000);
    });

    // wire global quick-search (like order.php)
    (function(){
      const globalSearch = document.getElementById('globalSearch');
      const tbody = document.querySelector('#returnsTable tbody');
      if (!globalSearch || !tbody) return;
      function filterTable(){
        const q = (globalSearch.value || '').trim().toLowerCase();
        const rows = Array.from(tbody.querySelectorAll('tr'));
        if (!q) { rows.forEach(r => r.style.display = ''); return; }
        rows.forEach(r => {
          const tds = Array.from(r.querySelectorAll('td'));
          if (!tds.length) { r.style.display=''; return; }
          const rowText = tds.map(td => (td.textContent || '').toLowerCase()).join(' ');
          r.style.display = rowText.includes(q) ? '' : 'none';
        });
      }
      globalSearch.addEventListener('input', filterTable);
    })();
  </script>
</body>
</html>
