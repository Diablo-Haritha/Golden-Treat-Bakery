<?php
// returns.php
// Handles returns: POST action=return (from your order.php return form), and admin listing / JSON endpoint.
// Save as returns.php and set your return form to <form action="returns.php" method="post"> with fields:
// - action=return
// - id = order id (or order_item_id can be sent as order_item_id)
// - return_quantity
// - refund_amount
// - return_reason
// - return_date (optional)

// DB connection (same style)
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

function json_response($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function clean($s) { return trim((string)$s); }

// JSON endpoint for admin listing
if (isset($_GET['ajax']) && ($_GET['ajax'] == '1' || strtolower($_GET['ajax']) === 'true')) {
    $rows = [];
    $sql = "SELECT r.id, r.order_id, r.order_item_id, r.return_date, r.quantity, r.refund_amount, r.reason, r.status, r.created_at,
                   o.customer AS customer_name
            FROM returns r
            LEFT JOIN orders o ON o.id = r.order_id
            ORDER BY r.created_at DESC
            LIMIT 200";
    $res = $conn->query($sql);
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    json_response($rows);
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'return') {
        // Accept either order_item_id (preferred) or order_id
        $order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
        $order_id = isset($_POST['id']) ? (int)$_POST['id'] : 0; // legacy (order.php used id)
        $return_qty = max(0, (int)($_POST['return_quantity'] ?? 0));
        $refund_amount = (float)($_POST['refund_amount'] ?? 0.00);
        $reason = clean($_POST['return_reason'] ?? '');
        $return_date = clean($_POST['return_date'] ?? date('Y-m-d'));
        $redirect = $_POST['return_to'] ?? $_SERVER['HTTP_REFERER'] ?? 'order.php';

        if ($order_item_id <= 0 && $order_id <= 0) {
            $flash_error = "Missing order id.";
            header("Location: $redirect");
            exit;
        }
        if ($return_qty <= 0) {
            $flash_error = "Return quantity must be at least 1.";
            header("Location: $redirect");
            exit;
        }

        $conn->begin_transaction();
        try {
            // If order_item_id provided, validate against order_items.quantity
            if ($order_item_id > 0) {
                $stmt = $conn->prepare("SELECT id, order_id, product_id, quantity, unit_price FROM order_items WHERE id = ?");
                $stmt->bind_param("i", $order_item_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $item = $res ? $res->fetch_assoc() : null;
                $stmt->close();

                if (!$item) throw new Exception("Order item not found.");
                $origQty = (int)$item['quantity'];
                $order_id = (int)$item['order_id'];
            } else {
                // fallback: validate against orders.quantity (if your schema uses orders.quantity)
                $stmt = $conn->prepare("SELECT id, quantity FROM orders WHERE id = ?");
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $ord = $res ? $res->fetch_assoc() : null;
                $stmt->close();
                if (!$ord) throw new Exception("Order not found.");
                $origQty = isset($ord['quantity']) ? (int)$ord['quantity'] : 0;
            }

            if ($return_qty > $origQty) throw new Exception("Return quantity cannot exceed original quantity ($origQty).");

            // Insert into returns table (supporting both schemas)
            if ($order_item_id > 0) {
                $ins = $conn->prepare("INSERT INTO returns (order_item_id, order_id, return_date, quantity, reason, refund_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                $statusDefault = 'Requested';
                $ins->bind_param("iisisds", $order_item_id, $order_id, $return_date, $return_qty, $reason, $refund_amount, $statusDefault);
            } else {
                // older schema where returns(order_id,...) exists
                $ins = $conn->prepare("INSERT INTO returns (order_id, return_date, quantity, reason, refund_amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $statusDefault = 'Requested';
                $ins->bind_param("isidsd", $order_id, $return_date, $return_qty, $reason, $refund_amount, $statusDefault);
            }
            if (!$ins) throw new Exception("Failed to prepare returns insert: " . $conn->error);
            $ok = $ins->execute();
            $err = $ins->error;
            $ins->close();
            if (!$ok) throw new Exception("Failed to record return: " . $err);

            // Update order status. If partial return, set Partially Returned, else Returned.
            // We will compute already-returned quantities to decide.
            $totalPreviouslyReturned = 0;
            $stmt = null;
            if ($order_item_id > 0) {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total FROM returns WHERE order_item_id = ?");
                $stmt->bind_param("i", $order_item_id);
            } else {
                $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) AS total FROM returns WHERE order_id = ?");
                $stmt->bind_param("i", $order_id);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $totalPreviouslyReturned = (int)($row['total'] ?? 0);

            // Determine original qty for comparison (origQty variable above)
            $newTotalReturned = $totalPreviouslyReturned; // already includes the inserted return because DB sum includes it
            // If using order_items: origQty is that item quantity; if using orders.quantity, origQty set earlier.

            $newStatus = 'Returned';
            if ($newTotalReturned < $origQty) $newStatus = 'Partially Returned';
            // Update orders table status (simple approach)
            $upd = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $upd->bind_param("si", $newStatus, $order_id);
            $updOk = $upd->execute();
            $updErr = $upd->error;
            $upd->close();
            if (!$updOk) throw new Exception("Failed to update order status: " . $updErr);

            // Optional: If you want to update order_items quantity or status, do it here (commented)
            // e.g. reduce order_items.quantity or mark returned qty; but inventory team handles stock.

            $conn->commit();
            // all good
            header("Location: " . ($redirect));
            exit;
        } catch (Exception $ex) {
            $conn->rollback();
            $flash_error = "Return failed: " . $ex->getMessage();
            // redirect back with error message (simple)
            // You may want to pass error via session flash in production
            header("Location: " . ($redirect));
            exit;
        }
    }

    // other actions could be supported, e.g. approve, refund: implement later
}

// Admin HTML listing (simple)
$returns = [];
$res = $conn->query("SELECT r.*, o.customer as customer_name FROM returns r LEFT JOIN orders o ON o.id = r.order_id ORDER BY r.created_at DESC LIMIT 200");
if ($res) $returns = $res->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Returns Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
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

  </style>
</head>
<body>
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Returns</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search returns by order, customer or ID..." />
      </div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='order.php'">Orders</button>
      <div class="user-icon"></div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h1>Returns Admin</h1>
      <nav>
        <button class="salesbtn" disabled>Returns</button>
        <hr />
        <p>Manage product returns</p>
      </nav>
    </aside>

    <main class="free-area">
      <section class="panel active">
        <div class="content" style="background: #fff; color: #111;">
          <?php if(!empty($flash_error)): ?>
            <div style="background:#fee;padding:10px;border-radius:6px;color:#900"><?= htmlspecialchars($flash_error) ?></div>
          <?php endif; ?>

          <div class="table-wrap">
            <table>
              <thead><tr><th>ID</th><th>Order ID</th><th>Order Item ID</th><th>Customer</th><th>Qty</th><th>Refund</th><th>Date</th><th>Reason</th><th>Status</th></tr></thead>
              <tbody id="returnsTable">
              <?php if(empty($returns)): ?>
                <tr><td colspan="9" style="text-align:center;padding:18px">No returns</td></tr>
              <?php else: foreach($returns as $r): ?>
                <tr>
                  <td><?= (int)$r['id'] ?></td>
                  <td><?= htmlspecialchars($r['order_id']) ?></td>
                  <td><?= htmlspecialchars($r['order_item_id'] ?? '') ?></td>
                  <td><?= htmlspecialchars($r['customer_name'] ?? '') ?></td>
                  <td><?= (int)$r['quantity'] ?></td>
                  <td><?= number_format((float)$r['refund_amount'] ?? 0, 2) ?></td>
                  <td><?= htmlspecialchars($r['return_date'] ?? $r['created_at']) ?></td>
                  <td><?= htmlspecialchars($r['reason']) ?></td>
                  <td><?= htmlspecialchars($r['status']) ?></td>
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
// client-side search for returns table
(function(){
  const input = document.getElementById('globalSearch');
  const tbody = document.getElementById('returnsTable');
  if (!input || !tbody) return;
  input.addEventListener('input', function(){
    const q = this.value.trim().toLowerCase();
    const rows = Array.from(tbody.querySelectorAll('tr'));
    if (!q) { rows.forEach(r=>r.style.display=''); return; }
    rows.forEach(r=>{
      const txt = Array.from(r.querySelectorAll('td')).map(td=>td.textContent.toLowerCase()).join(' ');
      r.style.display = txt.includes(q) ? '' : 'none';
    });
  });
})();
</script>
</body>
</html>