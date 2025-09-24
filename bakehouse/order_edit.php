<?php
// order_edit.php (Customer-facing)
// Allows customers to update name/mobile/address only while order not shipped/delivered.
// Uses POST JSON API for updates and renders a friendly form.

ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['temp_user_id'])) $_SESSION['temp_user_id'] = uniqid('guest_', true);
$user_id = $_SESSION['user_id'] ?? $_SESSION['temp_user_id'];

// DB
$DB_HOST="localhost"; $DB_USER="root"; $DB_PASS=""; $DB_NAME="golden_treat";
$conn = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($conn->connect_error) die('DB error: '.$conn->connect_error);
$conn->set_charset("utf8mb4");

function table_exists($conn,$db,$table){
    $st = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema=? AND table_name=?");
    if (!$st) return false;
    $st->bind_param('ss',$db,$table); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return intval($r['cnt'])>0;
}

// JSON API actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!table_exists($conn,$DB_NAME,'orders')) { echo json_encode(['ok'=>false,'msg'=>'orders table missing']); exit; }
    $action = $_GET['action'];

    if ($action === 'get_order') {
        $id = intval($_GET['order_id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid order id']); exit; }
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('is',$id,$user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) { echo json_encode(['ok'=>false,'msg'=>'Order not found or not owned by you']); exit; }
        // optionally items
        $items = [];
        if (table_exists($conn,$DB_NAME,'order_items')) {
            $it = $conn->prepare("SELECT oi.*, p.name AS product_name, p.image AS product_image FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?");
            $it->bind_param('i',$id); $it->execute();
            $res = $it->get_result();
            while ($r = $res->fetch_assoc()) $items[] = $r;
            $it->close();
        }
        echo json_encode(['ok'=>true,'order'=>$order,'items'=>$items]);
        exit;
    }

    if ($action === 'update_order') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $id = intval($data['order_id'] ?? 0);
        if ($id <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid order id']); exit; }
        // fetch order meta and verify ownership
        $stmt = $conn->prepare("SELECT status, user_id FROM orders WHERE id = ? LIMIT 1");
        $stmt->bind_param('i',$id); $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$meta || $meta['user_id'] !== $user_id) { echo json_encode(['ok'=>false,'msg'=>'Order not found or not owned by you']); exit; }
        $cur = strtolower($meta['status'] ?? 'pending');
        if (in_array($cur, ['shipped','delivered'])) {
            echo json_encode(['ok'=>false,'msg'=>'Order cannot be edited after it has been shipped']); exit;
        }
        // allowed fields: full_name, mobile, address
        $allowed = ['full_name','mobile','address'];
        $set = []; $types=''; $vals=[];
        foreach ($allowed as $f) {
            if (isset($data[$f])) { $set[] = "$f = ?"; $types .= 's'; $vals[] = trim($data[$f]); }
        }
        if (count($set) === 0) { echo json_encode(['ok'=>false,'msg'=>'No editable fields provided']); exit; }
        $set[] = "updated_at = NOW()";
        $sql = "UPDATE orders SET ".implode(', ',$set)." WHERE id = ?";
        $types .= 'i'; $vals[] = $id;
        $upd = $conn->prepare($sql);
        if (!$upd) { echo json_encode(['ok'=>false,'msg'=>'DB error: '.$conn->error]); exit; }
        $upd->bind_param($types, ...$vals);
        if (!$upd->execute()) { echo json_encode(['ok'=>false,'msg'=>'Update failed: '.$upd->error]); $upd->close(); exit; }
        $upd->close();
        echo json_encode(['ok'=>true,'msg'=>'Order updated']);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action']);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Edit Order — Golden Treat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{--brand:#C87F3A;--muted:#6b4b3a;--bg:#fffaf0}
  body{background:#fff8e6;color:var(--muted);font-family:Arial,Helvetica,sans-serif;padding:20px}
  .wrap{max-width:760px;margin:48px auto}
  h1{color:var(--brand)}
  .card{background:var(--bg);padding:16px;border-radius:10px;border:1px solid #ffe8c4}
  label{font-weight:600;color:#5a3d2a}
  input,textarea{width:100%;padding:8px;border-radius:6px;border:1px solid #ffdca8}
  .btn-brand{background:var(--brand);color:#fff;border:0;border-radius:8px;padding:8px 12px}
  .muted{color:var(--muted)}
</style>
</head>
<body>
<div class="wrap">
  <h1>Edit Order</h1>
  <div class="card">
    <?php if (!table_exists($conn,$DB_NAME,'orders')): ?>
      <div class="muted">Orders table not found. Create <code>orders</code> or adapt this file.</div>
    <?php else: ?>
      <div class="muted">Enter your order ID and load it. You can update contact & address while the order hasn't been shipped.</div>
      <div style="margin-top:12px;display:flex;gap:8px">
        <input id="orderId" class="form-control" placeholder="Order ID (e.g. 123)">
        <button id="loadBtn" class="btn btn-brand">Load</button>
      </div>

      <div id="formArea" style="margin-top:12px;display:none">
        <form id="editForm">
          <input type="hidden" id="order_id">
          <div class="mb-2">
            <label>Customer name</label>
            <input id="full_name" required>
          </div>
          <div class="mb-2">
            <label>Mobile</label>
            <input id="mobile" required>
          </div>
          <div class="mb-2">
            <label>Address</label>
            <textarea id="address" rows="3" required></textarea>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:8px">
            <button type="button" id="saveBtn" class="btn btn-brand">Save changes</button>
            <a href="order_history.php" class="btn btn-outline-secondary">Back</a>
          </div>
          <div id="msg" class="muted" style="margin-top:10px"></div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
async function ajax(action, data = {}) {
  const res = await fetch('order_edit.php?action=' + encodeURIComponent(action), {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  return res.json();
}

document.getElementById('loadBtn').addEventListener('click', async ()=>{
  const id = parseInt(document.getElementById('orderId').value);
  if (!id) { alert('Enter order id'); return; }
  document.getElementById('msg').textContent = 'Loading...';
  const r = await fetch('order_edit.php?action=get_order&order_id=' + id).then(r=>r.json());
  if (!r.ok) { document.getElementById('msg').textContent = r.msg || 'Not found'; return; }
  const o = r.order;
  document.getElementById('order_id').value = o.id;
  document.getElementById('full_name').value = o.full_name || o.customer_name || '';
  document.getElementById('mobile').value = o.mobile || '';
  document.getElementById('address').value = o.address || '';
  document.getElementById('formArea').style.display = 'block';
  document.getElementById('msg').textContent = '';
});

document.getElementById('saveBtn').addEventListener('click', async ()=>{
  const id = parseInt(document.getElementById('order_id').value || 0);
  if (!id) { alert('Load an order first'); return; }
  const payload = {
    order_id: id,
    full_name: document.getElementById('full_name').value.trim(),
    mobile: document.getElementById('mobile').value.trim(),
    address: document.getElementById('address').value.trim()
  };
  document.getElementById('msg').textContent = 'Saving...';
  const r = await fetch('order_edit.php?action=update_order', {
    method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
  }).then(r=>r.json());
  document.getElementById('msg').textContent = r.ok ? 'Saved successfully.' : ('Error: ' + (r.msg || 'Failed'));
});
</script>
</body>
</html>
