<?php
// purchase_returns.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

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
// purchase_returns.php (action=create)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'create') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $refund = isset($_POST['refund_amount']) ? (float)$_POST['refund_amount'] : null;
    $processed_by = $_SESSION['user_id'] ?? null;

    if ($order_id <= 0 || $qty <= 0) {
        // error
    } else {
        $stmt = $conn->prepare("INSERT INTO returns (order_id, return_date, quantity, reason, refund_amount, processed_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $date = date('Y-m-d');
        if ($refund === null) $refund = 0; // or calculate in trigger/app
        $stmt->bind_param("isisd i", $order_id, $date, $qty, $reason, $refund, $processed_by);
        // Note: binding types: i,s,i,s,d,i - adjust if needed
        if (!$stmt->execute()) {
            // handle error
        } else {
            // success — trigger will update orders & history
            header("Location: returns_list.php?success=1");
            exit;
        }
    }
}
// purchase_returns.php (action=restore)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['action'] ?? '') === 'restore') {
    $return_id = (int)($_POST['return_id'] ?? 0);
    $restored_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $flash_error = '';

    if ($return_id <= 0) {
        $flash_error = "Invalid return ID.";
    } else {
        try {
            $conn->begin_transaction();

            // Lock and fetch the return row
            $sel_return = $conn->prepare("SELECT order_id, quantity, refund_amount, reason FROM returns WHERE id_auto = ? FOR UPDATE");
            if (!$sel_return) throw new Exception("Prepare failed (select return): " . $conn->error);
            $sel_return->bind_param("i", $return_id);
            $sel_return->execute();
            $res_return = $sel_return->get_result();
            $return_row = $res_return->fetch_assoc();
            $sel_return->close();

            if (!$return_row) {
                throw new Exception("Return not found (id: $return_id).");
            }

            $order_id = (int)$return_row['order_id'];
            $return_qty = (int)$return_row['quantity'];
            $refund_amount = (float)$return_row['refund_amount'];
            $reason = $return_row['reason'];

            // Lock and fetch the order row
            $sel_order = $conn->prepare("SELECT quantity, price, total_amount, status, original_quantity, original_price, deleted_at FROM orders WHERE id = ? FOR UPDATE");
            if (!$sel_order) throw new Exception("Prepare failed (select order): " . $conn->error);
            $sel_order->bind_param("i", $order_id);
            $sel_order->execute();
            $res_order = $sel_order->get_result();
            $order_row = $res_order->fetch_assoc();
            $sel_order->close();

            if (!$order_row) {
                throw new Exception("Order not found (id: $order_id).");
            }
            if (!empty($order_row['deleted_at'])) {
                throw new Exception("Operation denied: this order has been deleted.");
            }

            $current_qty = (int)$order_row['quantity'];
            $price_per_unit = (float)$order_row['price'];
            $current_total = (float)$order_row['total_amount'];
            $old_status = $order_row['status'];
            $original_qty = (int)($order_row['original_quantity'] ?? ($current_qty + $return_qty)); // Fallback if not set

            // Compute new values
            $new_qty = $current_qty + $return_qty;
            $new_total = max(0.00, $price_per_unit * $new_qty);

            // Determine new status
            if ($new_qty == $original_qty) {
                $new_status = 'Order Received'; // Or whatever the default/pre-return status is
            } elseif ($new_qty == 0) {
                $new_status = 'Returned';
            } else {
                $new_status = 'Partially Returned';
            }

            // Update orders
            $upd_order = $conn->prepare("UPDATE orders SET quantity = ?, total_amount = ?, status = ? WHERE id = ?");
            if (!$upd_order) throw new Exception("Prepare failed (update orders): " . $conn->error);
            $upd_order->bind_param("idsi", $new_qty, $new_total, $new_status, $order_id);
            if (!$upd_order->execute()) {
                throw new Exception("Update orders failed: " . $upd_order->error);
            }
            $upd_order->close();

            // Insert status history
            $note = "Return undone (qty added back: {$return_qty}, reason was: {$reason})";
            $hist = $conn->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            if (!$hist) throw new Exception("Prepare failed (insert history): " . $conn->error);
            if ($restored_by === null) {
                $hist = $conn->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note, created_at) VALUES (?, ?, ?, NULL, ?, NOW())");
                if (!$hist) throw new Exception("Prepare failed (insert history null): " . $conn->error);
                $hist->bind_param("isss", $order_id, $old_status, $new_status, $note);
            } else {
                $hist->bind_param("issis", $order_id, $old_status, $new_status, $restored_by, $note);
            }
            if (!$hist->execute()) {
                throw new Exception("Insert history failed: " . $hist->error);
            }
            $hist->close();

            // Delete the return row
            $del = $conn->prepare("DELETE FROM returns WHERE id_auto = ?");
            if (!$del) throw new Exception("Prepare failed (delete return): " . $conn->error);
            $del->bind_param("i", $return_id);
            if (!$del->execute()) {
                throw new Exception("Delete return failed: " . $del->error);
            }
            $del->close();

            $conn->commit();
            header("Location: purchase_returns.php?restored=1");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $flash_error = "Restore failed: " . $e->getMessage();
        }
    }
    // If error, perhaps set session flash or something
}

// read filters (GET)
$from = isset($_GET['from']) && $_GET['from'] !== '' ? $_GET['from'] : '';
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? $_GET['to']   : '';
$customer_name = isset($_GET['customer_name']) ? trim($_GET['customer_name']) : '';
$order_id = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? (int)$_GET['order_id'] : '';
$processed_by = isset($_GET['processed_by']) && $_GET['processed_by'] !== '' ? (int)$_GET['processed_by'] : '';
$limit = 2000; // safety limit

// Simple date validation (ensure format YYYY-MM-DD and valid calendar date)
function validate_date($d) {
    if (!is_string($d) || $d === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
if ($from !== '' && !validate_date($from)) $from = '';
if ($to !== '' && !validate_date($to)) $to = '';

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
if ($customer_name !== '') {
    $where[] = "o.customer_name LIKE ?";
    $types .= 's';
    $values[] = '%' . $customer_name . '%';
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
  o.customer_name,
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
    $retQty = (int)($r['returned_quantity'] ?? 0);
    $refund = (float)($r['refund_amount'] ?? 0.0);
    $totalQty += $retQty;
    $totalRefund += $refund;
}
// optionally round display when outputting:
// Rs. <?= number_format(round($totalRefund, 2), 2) 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Purchase Returns - Admin</title>
  <link rel="stylesheet" href="css/purchase_returns.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>

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
        <div class="content"> <!-- keep content white for clarity -->
          <h2>Purchase Returns</h2>
<?php if (isset($flash_error)): ?>
    <div class="alert error"><?= htmlspecialchars($flash_error) ?></div>
<?php endif; ?>
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

          <div class="toolbar" >
            <form method="get" class="filter-bar">
              <lable>From:
              <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" />
              </label>
              <label>To:
              <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" />
              </label>
              <input type="text" name="customer_name" placeholder="customer_name" value="<?= htmlspecialchars($customer_name) ?>" />
              <input type="number" name="order_id" placeholder="Order ID" value="<?= ($order_id ? (int)$order_id : '') ?>" />
              <input type="number" name="processed_by" placeholder="Processed by (admin id)" value="<?= ($processed_by ? (int)$processed_by : '') ?>" />
              <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
              <button class="btn secondary" id="exportCsv" type="button"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
              <div class="muted" style="margin-left:auto;align-self:center">Showing up to <?= $limit ?> rows</div>
            </form>
          </div>

          <div class="table-wrap" style="margin-top:8px;">
            <table id="returnsTable">
              <thead>
                <tr>
                  <th>Return ID</th>
                  <th> ID</th>
                  <th>Order Date</th>
                  <th>Return Date</th>
                  <th>Customer</th>
                  <th>Product</th>
                  <th>Returned Qty</th>
                  <th>Refund (Rs.)</th>
                  <th>Processed By</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="ordersTable">
                <?php if (empty($rows)): ?>
                  <tr><td colspan="10" style="text-align:center;padding:18px">No returns found for these filters</td></tr>
                <?php else: foreach($rows as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['return_id']) ?></td>
                    <td><?= htmlspecialchars($r['order_id']) ?></td>
                    <td><?= htmlspecialchars($r['order_date']) ?></td>
                    <td><?= htmlspecialchars($r['return_date']) ?></td>
                    <td><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td><?= htmlspecialchars($r['product']) ?></td>
                    <td><?= (int)$r['returned_quantity'] ?></td>
                    <td><?= number_format((float)$r['refund_amount'],2) ?></td>
                    <td><?= htmlspecialchars($r['processed_by_name'] ?: $r['processed_by']) ?></td>
                    <td>
                      <!-- Add your action buttons here, e.g., -->
                     <button class="btnrestore" type="button" data-return-id="<?= htmlspecialchars($r['return_id']) ?>"><i class="fa-solid fa-rotate-left"></i></button> 

                      <!-- view button inside last cell; data-* attributes used to populate modal -->
                      <button class="btnview" type="button"
                        data-return-id="<?= htmlspecialchars($r['return_id']) ?>"
                        data-order-id="<?= htmlspecialchars($r['order_id']) ?>"
                        data-order-date="<?= htmlspecialchars($r['order_date']) ?>"
                        data-return-date="<?= htmlspecialchars($r['return_date']) ?>"
                        data-customer_name="<?= htmlspecialchars($r['customer_name']) ?>"
                        data-product="<?= htmlspecialchars($r['product']) ?>"
                        data-returned-quantity="<?= htmlspecialchars($r['returned_quantity']) ?>"
                        data-refund-amount="<?= htmlspecialchars(number_format((float)$r['refund_amount'],2)) ?>"
                        data-processed-by-name="<?= htmlspecialchars($r['processed_by_name'] ?: $r['processed_by']) ?>"
                        data-recorded-at="<?= htmlspecialchars($r['recorded_at']) ?>"
                        data-reason="<?= htmlspecialchars($r['reason']) ?>"
                        aria-label="View return <?= htmlspecialchars($r['return_id']) ?>">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

        </div>
      </section>
    </main>
  </div>

  <!-- View modal -->
  <div class="modal-backdrop" id="viewModal" role="dialog" aria-modal="true" style="display:none;">
    <div class="modal" id="viewModalInner">
      <h2 id="viewTitle">Purchase</h2>
      <div style="margin-top:8px" id="viewBody">
        <div class="form-grid">
          <div><strong>return ID</strong><div id="v_id"></div></div>
          <div><strong>order id</strong><div id="v_order_id"></div></div>
          <div><strong>order_date</strong><div id="v_order_date"></div></div>
          <div><strong>return_date</strong><div id="v_return_date"></div></div>
          <div class="full"><strong>Customer</strong><div id="v_customer"></div></div>
          <div class="full"><strong>Product</strong><div id="v_product"></div></div>
          <div><strong>Returned Quantity</strong><div id="v_returned_quantity"></div></div>
          <div><strong>refuned amount</strong><div id="v_refund_amount"></div></div>
          <div><strong>processed_by_name</strong><div id="v_processed_by_name"></div></div>
          <div><strong>Price</strong><div id="v_price"></div></div>
          <div class="full"><strong>recorded_at</strong><div id="v_recorded_at"></div></div>
          <div class="full"><strong>reason</strong><div id="v_reason" style="white-space:pre-wrap;"></div></div>
                  <!-- close / invoice buttons — won't break your CSS, just standard HTML -->
      <div class="footer" style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
        <button id ="closeViewBtn" class="btn primary">Close</button>
      </div>
    </div>
  </div>

  <script>
    // CSV export (exports visible table rows)
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
        return Array.from(tds).slice(0,10).map(td => td.textContent.trim()); // ignore Actions cell
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

    // global quick-search
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

    // Modal wiring (populate and show) — no invoice
    (function(){
      const modal = document.getElementById('viewModal');
      const modalInner = document.getElementById('viewModalInner');

      function openModalWithData(data) {
        document.getElementById('v_id').textContent = data.returnId || '';
        document.getElementById('v_order_id').textContent = data.orderId || '';
        document.getElementById('v_order_date').textContent = data.orderDate || '';
        document.getElementById('v_return_date').textContent = data.returnDate || '';
        document.getElementById('v_customer').textContent = data.customer || '';
        document.getElementById('v_product').textContent = data.product || '';
        document.getElementById('v_returned_quantity').textContent = data.returnedQuantity || '';
        document.getElementById('v_refund_amount').textContent = data.refundAmount || '';
        document.getElementById('v_processed_by_name').textContent = data.processedByName || '';
        document.getElementById('v_price').textContent = data.refundAmount || '';
        document.getElementById('v_recorded_at').textContent = data.recordedAt || '';
        document.getElementById('v_reason').innerText = data.reason || '';

        modal.style.display = 'flex';
        modalInner.focus && modalInner.focus();
      }

      function closeModal() {
        modal.style.display = 'none';
      }

      document.querySelectorAll('.btnview').forEach(btn => {
        btn.addEventListener('click', () => {
          const d = btn.dataset;
          openModalWithData({
            returnId: d.returnId,
            orderId: d.orderId,
            orderDate: d.orderDate,
            returnDate: d.returnDate,
            customer: d.customer,
            product: d.product,
            returnedQuantity: d.returnedQuantity,
            refundAmount: d.refundAmount,
            processedByName: d.processedByName,
            recordedAt: d.recordedAt,
            reason: d.reason
          });
        });
      });

      document.getElementById('closeViewBtn').addEventListener('click', closeModal);

      // close on backdrop click
      modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
      });
      // close on Escape
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
      });
    })();
  </script>
</body>
</html>
