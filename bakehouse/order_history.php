<?php
// order_history.php (Customer-facing, robust to missing columns)
// Replace your existing file with this. Update DB creds if needed.

ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['temp_user_id'])) $_SESSION['temp_user_id'] = uniqid('guest_', true);
$user_id = $_SESSION['user_id'] ?? $_SESSION['temp_user_id'];

// DB credentials - change if needed
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "golden_treat";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('DB connection failed: ' . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// helpers
function table_exists($conn, $db, $table) {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema = ? AND table_name = ?");
    if (!$stmt) return false;
    $stmt->bind_param('ss', $db, $table);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return intval($r['cnt']) > 0;
}

// return array of column names in a table
function get_columns($conn, $db, $table) {
    $cols = [];
    $stmt = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = ? AND table_name = ?");
    if (!$stmt) return $cols;
    $stmt->bind_param('ss', $db, $table);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $cols[] = $r['COLUMN_NAME'];
    $stmt->close();
    return $cols;
}

if (!table_exists($conn, $DB_NAME, 'orders')) {
    $orders = [];
    $available_cols = [];
} else {
    $available_cols = get_columns($conn, $DB_NAME, 'orders');

    // columns we want to show (preferred order). If missing, we alias empty string so code below can use the keys safely.
    $want = [
        'id' => 'id',
        'full_name' => 'full_name',
        'mobile' => 'mobile',
        'total_amount' => 'total_amount',
        'status' => 'status',
        'tracking_number' => 'tracking_number',
        'created_at' => 'created_at'
    ];

    // build select list: use column if exists else '' AS col
    $select_list = [];
    foreach ($want as $col => $alias) {
        if (in_array($col, $available_cols)) {
            // protect column names with backticks
            $select_list[] = "`$col` AS `$alias`";
        } else {
            // alias as empty string so fetch_assoc has the key regardless
            // For numeric columns (like total_amount) we still return empty string which we'll format later
            $select_list[] = "'' AS `$alias`";
        }
    }

    // always include user_id in WHERE; ensure it's available; if not, we'll fallback to empty set
    if (!in_array('user_id', $available_cols)) {
        // cannot reliably fetch per-user orders if no user_id column
        $orders = [];
    } else {
        $sql = "SELECT " . implode(', ', $select_list) . " FROM `orders` WHERE `user_id` = ? ORDER BY " . (in_array('created_at', $available_cols) ? "`created_at` DESC" : "`id` DESC");
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            // fallback: empty orders
            $orders = [];
        } else {
            $stmt->bind_param('s', $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $orders = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    }
}

// helper to render status label and badge class
function status_label($s){
    $s = strtolower(trim($s ?? 'pending'));
    if (strpos($s,'cancel')!==false) return ['Canceled','badge-danger'];
    if (strpos($s,'deliver')!==false) return ['Delivered','badge-success'];
    if (strpos($s,'ship')!==false) return ['Shipped','badge-info'];
    if (strpos($s,'process')!==false) return ['Processing','badge-warning'];
    return ['Pending','badge-secondary'];
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Orders — Golden Treat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
<style>
  :root{--brand:#C87F3A;--muted:#6b4b3a;--bg:#fffaf0}
  body{background:#fff8e6;color:var(--muted);font-family:Arial,Helvetica,sans-serif;padding:20px}
  .container{max-width:980px;margin:56px auto}
  h1{color:var(--brand);font-weight:700}
  .panel{background:var(--bg);padding:16px;border-radius:10px;border:1px solid #ffe8c4}
  .order-list{margin-top:14px;display:flex;flex-direction:column;gap:12px}
  .order-card{display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff;border-radius:8px;border-left:4px solid #d4af37}
  .muted{color:var(--muted);font-size:0.95rem}
  .badge{padding:6px 10px;border-radius:999px;font-weight:700}
  .btn-brand{background:var(--brand);color:#fff;border:0;border-radius:8px;padding:8px 12px}
  .empty{padding:30px;text-align:center;border:2px dashed #ffdca8;border-radius:10px;background:#fffdf7}
</style>
</head>
<body>
<div class="container">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <div>
      <h1>My Orders</h1>
      <div class="muted">Orders placed with this device/account. For assistance call +94 77 000 0000</div>
    </div>
    <div>
      <a href="cart.php" class="btn btn-outline-secondary"><i class="fa fa-shopping-cart"></i> Shop more</a>
      <a href="order_tracking.php" class="btn btn-brand">Track</a>
    </div>
  </div>

  <div class="panel">
    <?php if (!table_exists($conn,$DB_NAME,'orders')): ?>
      <div class="empty"><strong>No orders table found in database.</strong><div class="muted">Create a table called <code>orders</code> (or adapt this file to your schema).</div></div>
    <?php elseif (!in_array('user_id', $available_cols)): ?>
      <div class="empty"><strong>Your orders table doesn't have a <code>user_id</code> column.</strong><div class="muted">This application expects orders to be linked to a user via <code>user_id</code>. Add that column or update the code to match your schema.</div></div>
    <?php elseif (count($orders) === 0): ?>
      <div class="empty"><strong>You have no orders yet.</strong><div class="muted">Place an order from the shop and it will appear here.</div></div>
    <?php else: ?>
      <div class="order-list">
        <?php foreach ($orders as $o):
            list($label,$badge) = status_label($o['status'] ?? 'pending');
            // safe formatting for amount
            $amount = ($o['total_amount'] !== '' && $o['total_amount'] !== null) ? number_format((float)$o['total_amount'], 2) : '0.00';
            $created = $o['created_at'] ?: 'N/A';
            $tracking = $o['tracking_number'] ?: '—';
        ?>
          <div class="order-card">
            <div>
              <div style="font-weight:700">Order #<?php echo intval($o['id']); ?></div>
              <div class="muted">Placed: <?php echo htmlspecialchars($created); ?> • Total: LKR <?php echo $amount; ?></div>
            </div>

            <div style="text-align:right;display:flex;flex-direction:column;gap:8px;align-items:flex-end">
              <div class="badge <?php echo $badge; ?>"><?php echo $label; ?></div>
              <div style="display:flex;gap:8px">
                <a class="btn btn-outline-primary btn-sm" href="order_tracking.php?order_id=<?php echo intval($o['id']); ?>"><i class="fa fa-truck"></i> Track</a>
                <a class="btn btn-outline-secondary btn-sm" href="order_view.php?order_id=<?php echo intval($o['id']); ?>"><i class="fa fa-eye"></i> View</a>
                <a class="btn btn-brand btn-sm" href="order_edit.php?order_id=<?php echo intval($o['id']); ?>"><i class="fa fa-edit"></i> Edit</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div style="text-align:center;margin-top:18px" class="muted">Golden Treat Bakery • Thank you for ordering</div>
</div>
</body>
</html>
