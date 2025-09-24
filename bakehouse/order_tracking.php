<?php
// order_tracking.php (Customer-facing)
// Allows customers to track using order id (must be owned) OR tracking number (public).
ini_set('display_errors',1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['temp_user_id'])) $_SESSION['temp_user_id'] = uniqid('guest_', true);
$user_id = $_SESSION['user_id'] ?? $_SESSION['temp_user_id'];

$DB_HOST="localhost"; $DB_USER="root"; $DB_PASS=""; $DB_NAME="golden_treat";
$conn = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($conn->connect_error) die('DB error: '.$conn->connect_error);
$conn->set_charset("utf8mb4");

function table_exists($conn,$db,$table){
    $st = $conn->prepare("SELECT COUNT(*) AS cnt FROM information_schema.tables WHERE table_schema=? AND table_name=?");
    if(!$st) return false;
    $st->bind_param('ss',$db,$table); $st->execute();
    $r = $st->get_result()->fetch_assoc(); $st->close();
    return intval($r['cnt'])>0;
}

if (isset($_GET['action']) && $_GET['action'] === 'track') {
    header('Content-Type: application/json; charset=utf-8');
    if (!table_exists($conn,$DB_NAME,'orders')) { echo json_encode(['ok'=>false,'msg'=>'orders table missing']); exit; }
    $oid = intval($_GET['order_id'] ?? 0);
    $tn  = trim($_GET['tracking_number'] ?? '');

    if (!$oid && $tn === '') { echo json_encode(['ok'=>false,'msg'=>'Provide order_id or tracking_number']); exit; }

    if ($oid) {
        // must be owned by current user
        $stmt = $conn->prepare("SELECT id,status,tracking_number,shipping_status_details,created_at,updated_at,estimated_delivery FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->bind_param('is',$oid,$user_id);
    } else {
        // tracking number lookup: allow public tracking
        $stmt = $conn->prepare("SELECT id,status,tracking_number,shipping_status_details,created_at,updated_at,estimated_delivery FROM orders WHERE tracking_number = ? LIMIT 1");
        $stmt->bind_param('s',$tn);
    }
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) { echo json_encode(['ok'=>false,'msg'=>'Order not found or access denied']); exit; }
    echo json_encode(['ok'=>true,'order'=>$order]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Track Order — Golden Treat</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  :root{--brand:#C87F3A;--muted:#6b4b3a}
  body{background:#fff8e6;color:var(--muted);font-family:Arial,Helvetica,sans-serif;padding:20px}
  .wrap{max-width:800px;margin:56px auto}
  h1{color:var(--brand)}
  .panel{background:#fffaf0;padding:16px;border-radius:10px;border:1px solid #ffe8c4}
  .muted{color:var(--muted)}
  .btn-brand{background:var(--brand);color:#fff;border:0;border-radius:8px;padding:8px 12px}
  .timeline{margin-top:12px;display:flex;flex-direction:column;gap:10px}
  .step{display:flex;gap:12px;align-items:flex-start}
  .bullet{width:12px;height:12px;border-radius:50%;background:#f0d6b1;margin-top:6px}
  .content{background:#fff;padding:10px;border-radius:8px;border:1px solid #f3e3d1;flex:1}
</style>
</head>
<body>
<div class="wrap">
  <h1>Track your order</h1>
  <div class="panel">
    <div class="muted">Enter your order ID (visible to logged-in / same-session customers) or tracking number (from your email/SMS).</div>

    <div style="display:flex;gap:8px;margin-top:12px">
      <input id="order_id" class="form-control" placeholder="Order ID (yours only)">
      <input id="tracking_number" class="form-control" placeholder="Tracking number">
      <button id="trackBtn" class="btn btn-brand">Check</button>
    </div>

    <div id="result" style="margin-top:14px"></div>
    <div style="margin-top:10px" class="muted">Need help? <a href="tel:+947700000000">+94 77 000 0000</a> • <a href="mailto:orders@goldentreat.lk">orders@goldentreat.lk</a></div>
  </div>
</div>

<script>
document.getElementById('trackBtn').addEventListener('click', async ()=>{
  const idVal = (document.getElementById('order_id').value||'').trim();
  const tnVal = (document.getElementById('tracking_number').value||'').trim();
  if (!idVal && !tnVal) { alert('Enter order id or tracking number'); return; }

  const params = new URLSearchParams();
  if (parseInt(idVal)) params.set('order_id', parseInt(idVal));
  if (tnVal) params.set('tracking_number', tnVal);

  document.getElementById('result').innerHTML = '<div class="muted">Checking...</div>';
  const res = await fetch('order_tracking.php?action=track&' + params.toString()).then(r=>r.json());
  if (!res.ok) { document.getElementById('result').innerHTML = '<div class="muted">Error: ' + (res.msg || 'Not found') + '</div>'; return; }
  render(res.order);
});

function render(o){
  const steps = [
    {key:'pending', label:'Order received'},
    {key:'processing', label:'Preparing'},
    {key:'shipped', label:'Out for delivery'},
    {key:'delivered', label:'Delivered'}
  ];
  const status = (o.status||'pending').toLowerCase();
  let html = `<div style="display:flex;justify-content:space-between;align-items:center">
                <div><strong>Order #${o.id}</strong><div class="muted">Placed: ${o.created_at || ''}</div></div>
                <div style="text-align:right"><div style="font-weight:700">${o.status || 'Pending'}</div><div class="muted">Tracking: ${o.tracking_number || '—'}</div></div>
              </div>`;
  html += '<div class="timeline">';
  for (const s of steps) {
    const active = stepIndex(s.key) <= stepIndex(status);
    html += `<div class="step"><div class="bullet" style="background:${active ? '#C87F3A' : '#f0d6b1'}"></div><div class="content"><div style="font-weight:700">${s.label}</div><div class="muted">${active ? (s.key===status ? 'Current' : 'Completed') : ''}</div></div></div>`;
  }
  html += `</div>`;
  html += `<div style="margin-top:10px" class="muted"><strong>Last update:</strong> ${o.updated_at || '—'}</div>`;
  html += `<div style="margin-top:6px" class="muted"><strong>Details:</strong> ${o.shipping_status_details || 'No shipping notes available.'}</div>`;
  if (o.estimated_delivery) html += `<div style="margin-top:6px" class="muted"><strong>Estimated delivery:</strong> ${o.estimated_delivery}</div>`;
  document.getElementById('result').innerHTML = html;
}
function stepIndex(k){
  const order=['pending','processing','shipped','delivered'];
  let i = order.indexOf(k);
  return i === -1 ? 0 : i;
}
</script>
</body>
</html>
