<?php
// purchase_history.php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "gt";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Helper for dynamic bind_param
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// --- Read filters from GET ---
$from = !empty($_GET['from']) ? $_GET['from'] : '';
$to   = !empty($_GET['to'])   ? $_GET['to']   : '';
$customer = !empty($_GET['customer']) ? trim($_GET['customer']) : '';
$status   = !empty($_GET['status']) ? trim($_GET['status']) : '';

// Build WHERE parts dynamically and bind types/values
$where = [];
$types = '';
$values = [];

if ($from !== '') {
    $where[] = "o.order_date >= ?";
    $types .= 's';
    $values[] = $from;
}
if ($to !== '') {
    $where[] = "o.order_date <= ?";
    $types .= 's';
    $values[] = $to;
}
if ($customer !== '') {
    $where[] = "o.customer LIKE ?";
    $types .= 's';
    $values[] = '%' . $customer . '%';
}
if ($status !== '') {
    $where[] = "o.status = ?";
    $types .= 's';
    $values[] = $status;
}

$whereSql = '';
if (!empty($where)) $whereSql = 'WHERE ' . implode(' AND ', $where);

// Query: include returned quantity per order (if any)
$sql = "
SELECT 
  o.id, o.order_date, o.customer, o.product, o.quantity, o.price, o.status,
  COALESCE(r.sum_qty,0) AS returned_qty,
  (o.quantity - COALESCE(r.sum_qty,0)) AS net_quantity,
  (o.price * (o.quantity - COALESCE(r.sum_qty,0))) AS net_value
FROM orders o
LEFT JOIN (
  SELECT order_id, SUM(quantity) AS sum_qty
  FROM returns
  GROUP BY order_id
) r ON r.order_id = o.id
{$whereSql}
ORDER BY o.order_date DESC, o.id DESC
LIMIT 1000"; // safety limit

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
if (!empty($values)) {
    // bind dynamically
    array_unshift($values, $types);
    call_user_func_array([$stmt, 'bind_param'], refValues($values));
}
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Totals for visible rows
$totalGross = 0.0;
$totalNet = 0.0;
$totalReturnedQty = 0;
foreach ($rows as $r) {
    $totalGross += (float)$r['price'] * (int)$r['quantity'];
    $totalReturnedQty += (int)$r['returned_qty'];
    $totalNet += (float)$r['net_value'];
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Purchase History</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
<style>
/* Minimal styles to align with order.php */
body{font-family:Arial,Helvetica,sans-serif;background:#f4f6f9;color:#0f172a;padding:18px}
.container{max-width:1100px;margin:0 auto}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.filter{background:#fff;padding:12px;border-radius:8px;box-shadow:0 1px 6px rgba(0,0,0,.06);margin-bottom:12px;display:flex;gap:8px;flex-wrap:wrap}
.filter input, .filter select {padding:8px;border:1px solid #d1d5db;border-radius:6px}
.btn{padding:8px 12px;border:none;border-radius:8px;background:#007bff;color:#fff;cursor:pointer}
.table-wrap{background:#fff;border-radius:12px;box-shadow:0 1px 6px rgba(0,0,0,.06);overflow:auto;padding:12px}
table{width:100%;border-collapse:collapse}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left}
th{background:#f9fafb;position:sticky;top:0}
.summary{display:flex;gap:12px;margin:12px 0}
.summary .card{background:#fff;padding:12px;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.05)}
.muted{color:#6b7280;font-size:13px}
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h2>Purchase History</h2>
    <div>
      <button class="btn" onclick="window.location.href='order.php'"><i class="fa-solid fa-arrow-left"></i> Back to Orders</button>
    </div>
  </div>

  <form method="get" class="filter" onsubmit="">
    <label for="from" class="muted">From</label>
    <input type="date" id="from" name="from" value="<?= htmlspecialchars($from) ?>">
    <label for="to" class="muted">To</label>
    <input type="date" id="to" name="to" value="<?= htmlspecialchars($to) ?>">
    <input type="text" name="customer" placeholder="Customer name" value="<?= htmlspecialchars($customer) ?>">
    <select name="status">
      <option value="">All status</option>
      <?php
        $enumList = ['Order Received','Payment Confirmed','Queued for Baking','In Preparation','Decorating','Ready for Pickup','Out for Delivery','Completed','Cancelled','Refunded','Returned','Pending'];
        foreach ($enumList as $st) {
          $sel = ($st === $status) ? 'selected' : '';
          echo "<option value=\"".htmlspecialchars($st)."\" $sel>".htmlspecialchars($st)."</option>";
        }
      ?>
    </select>
    <button class="btn" type="submit">Filter</button>
    <button class="btn" type="button" id="exportCsv">Export CSV</button>
  </form>

  <div class="summary">
    <div class="card">
      <div class="muted">Total Gross</div>
      <div><strong>Rs. <?= number_format($totalGross,2) ?></strong></div>
    </div>
    <div class="card">
      <div class="muted">Total Returned Quantity</div>
      <div><strong><?= (int)$totalReturnedQty ?></strong></div>
    </div>
    <div class="card">
      <div class="muted">Total Net Value</div>
      <div><strong>Rs. <?= number_format($totalNet,2) ?></strong></div>
    </div>
  </div>

  <div class="table-wrap">
    <table id="historyTable">
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Date</th>
          <th>Customer</th>
          <th>Product</th>
          <th>Qty</th>
          <th>Returned</th>
          <th>Net Qty</th>
          <th>Price (per)</th>
          <th>Net Value</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php if(empty($rows)): ?>
          <tr><td colspan="10" style="text-align:center;padding:18px">No purchases found</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['id']) ?></td>
            <td><?= htmlspecialchars($r['order_date']) ?></td>
            <td><?= htmlspecialchars($r['customer']) ?></td>
            <td><?= htmlspecialchars($r['product']) ?></td>
            <td><?= (int)$r['quantity'] ?></td>
            <td><?= (int)$r['returned_qty'] ?></td>
            <td><?= (int)$r['net_quantity'] ?></td>
            <td><?= number_format((float)$r['price'], 2) ?></td>
            <td><?= number_format((float)$r['net_value'], 2) ?></td>
            <td><?= htmlspecialchars($r['status']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// Export visible table rows to CSV
function quoteCSV(s){
  const str = String(s ?? '');
  return /[",\n]/.test(str) ? `"${str.replace(/"/g,'""')}"` : str;
}
function toCSV(rows){
  const header = ['Order ID','Date','Customer','Product','Qty','Returned','Net Qty','Price','Net Value','Status'];
  const lines = [header.join(',')];
  rows.forEach(r => lines.push([
    quoteCSV(r[0]), quoteCSV(r[1]), quoteCSV(r[2]), quoteCSV(r[3]), r[4], r[5], r[6], r[7], r[8], quoteCSV(r[9])
  ].join(',')));
  return lines.join('\n');
}
document.getElementById('exportCsv').addEventListener('click', () => {
  const rows = Array.from(document.querySelectorAll('#historyTable tbody tr')).map(tr => {
    const tds = tr.querySelectorAll('td');
    if (!tds.length) return null;
    return Array.from(tds).map(td => td.textContent.trim());
  }).filter(Boolean);
  const csv = toCSV(rows);
  const blob = new Blob([csv], {type:'text/csv'});
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'purchase_history.csv';
  a.click();
  setTimeout(()=>URL.revokeObjectURL(a.href),1000);
});
</script>
</body>
</html>
