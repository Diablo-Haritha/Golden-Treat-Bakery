<?php
// customers.php
// Customers admin + JSON endpoint
// Save as customers.php

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

// JSON endpoint for AJAX
if (isset($_GET['ajax']) && ($_GET['ajax'] == '1' || strtolower($_GET['ajax']) === 'true')) {
    $rows = [];
    $res = $conn->query("SELECT id, name, email, phone, is_active FROM customers ORDER BY name ASC");
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    json_response($rows);
}

// Handle POST actions
$flash_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'add') {
        $name = clean($_POST['name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $billing = clean($_POST['billing_address'] ?? '');
        $shipping = clean($_POST['shipping_address'] ?? '');
        $notes = clean($_POST['notes'] ?? '');
        if ($name === '') { $flash_error = 'Name is required.'; }
        else {
            $stmt = $conn->prepare("INSERT INTO customers (name, email, phone, billing_address, shipping_address, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssss', $name, $email, $phone, $billing, $shipping, $notes);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) $flash_error = 'Insert failed: '. $err;
            else { header('Location: ' . ($_POST['return_to'] ?? 'customers.php')); exit; }
        }
    }
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = clean($_POST['name'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $phone = clean($_POST['phone'] ?? '');
        $billing = clean($_POST['billing_address'] ?? '');
        $shipping = clean($_POST['shipping_address'] ?? '');
        $notes = clean($_POST['notes'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        if ($id <= 0 || $name === '') { $flash_error = 'Invalid input.'; }
        else {
            $stmt = $conn->prepare("UPDATE customers SET name=?, email=?, phone=?, billing_address=?, shipping_address=?, notes=?, is_active=? WHERE id=?");
            $stmt->bind_param('ssssssii', $name, $email, $phone, $billing, $shipping, $notes, $is_active, $id);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) $flash_error = 'Update failed: ' . $err;
            else { header('Location: ' . ($_POST['return_to'] ?? 'customers.php')); exit; }
        }
    }
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) { $flash_error = 'Invalid id.'; }
        else {
            $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->bind_param('i', $id);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) $flash_error = 'Delete failed: ' . $err;
            else { header('Location: ' . ($_POST['return_to'] ?? 'customers.php')); exit; }
        }
    }
}

// Fetch customers for admin HTML
$customers = [];
$res = $conn->query("SELECT id, name, email, phone, is_active, created_at FROM customers ORDER BY id DESC LIMIT 500");
if ($res) $customers = $res->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Customers Admin</title>
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

    * { margin:0; padding:0; box-sizing:border-box }
    body { font-family: Arial, Helvetica, sans-serif; background:#f4f6f9; color:#0f172a; min-height:100vh; display:flex; flex-direction:column }
    .header { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:12px 16px; box-shadow:0 2px 5px rgba(0,0,0,.08) }
    .header-left img { width:56px; height:auto; border-radius:8px }
    .header-middle { display:flex; align-items:center; gap:12px; flex:1; margin:0 16px; max-width:720px }
    .header-middle-title { font-weight:800; font-size:26px; color:var(--brand) }
    .search-bar { flex:1; display:flex }
    .search-bar input { width:100%; padding:8px 10px; border:1px solid #d1d5db; border-radius:8px }
    .header-right { display:flex; align-items:center; gap:12px }
    .role-btn { background:#111827; color:#fff; padding:8px 14px; border:none; border-radius:8px; cursor:pointer }

    .layout { flex:1; display:flex; min-height:0 }
    .sidebar { width:260px; background:#fff; padding:18px; display:flex; flex-direction:column; gap:10px; border-right:1px solid #e5e7eb }
    .sidebar h1{ text-align:center; font-size:20px; margin-bottom:8px }
    .salebtn .tab-btn { background:var(--brand); border:none; border-radius:10px; color:#fff; padding:10px }

    .free-area { flex:1; background:#f3f4f6; padding:24px; overflow:auto }
    .content { background: #fff; border-radius:14px; padding:18px; box-shadow: 0 2px 8px rgba(0,0,0,.08); display:flex; flex-direction:column; gap:16px }
    .table-wrap { background:#fff; border-radius:12px; box-shadow:0 1px 6px rgba(0,0,0,.06); overflow:auto }
    table { width:100%; border-collapse:collapse }
    th, td { padding:12px; border-bottom:1px solid #e5e7eb; text-align:left; white-space:nowrap }
    th { background:#f9fafb; font-size:13px; color:#374151; position:sticky; top:0 }

    .btn { padding:9px 12px; border:none; border-radius:10px; cursor:pointer; color:#fff; background:var(--primary) }
    .btn.add { background: #1a73e8 }
    .btn.edit { background:#f39c12 }
    .btn.del { background:#e74c3c }
    .muted { color:var(--muted); font-size:13px }

    .form-inline { display:flex; gap:8px; align-items:center }
    .form-inline input, .form-inline textarea { padding:8px; border:1px solid #d1d5db; border-radius:8px }

    @media (max-width:1000px) { .sidebar { width:220px } }
  </style>
</head>
<body>
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo"></div>
    <div class="header-middle">
      <div class="header-middle-title">Customers</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by name, email or phone..."></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='order.php'">Orders</button>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <h1>Customers</h1>
      <nav>
        <button class="salebtn tab-btn" disabled>Manage</button>
        <hr>
        <p class="muted">Admin: add, edit or remove customers</p>
      </nav>
    </aside>

    <main class="free-area">
      <section class="panel active">
        <div class="content">
          <?php if(!empty($flash_error)): ?>
            <div style="background:#fee;padding:10px;border-radius:6px;color:#900"><?= htmlspecialchars($flash_error) ?></div>
          <?php endif; ?>

          <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <div>
              <button class="btn add" onclick="document.getElementById('addPanel').style.display='block'">Add Customer</button>
              <small class="muted" style="margin-left:12px">AJAX JSON: <code>?ajax=1</code></small>
            </div>
          </div>

          <div id="addPanel" style="display:none;background:#fff;padding:12px;border-radius:8px">
            <h3>Add Customer</h3>
            <form method="post" class="form-inline">
              <input type="hidden" name="action" value="add">
              <input name="name" placeholder="Name" required>
              <input name="email" type="email" placeholder="Email">
              <input name="phone" placeholder="Phone">
              <button class="btn" type="submit">Save</button>
              <button type="button" onclick="document.getElementById('addPanel').style.display='none'">Cancel</button>
            </form>
          </div>

          <div class="table-wrap">
            <table>
              <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Active</th><th>Created</th><th>Actions</th></tr></thead>
              <tbody id="customersTable">
              <?php if(empty($customers)): ?>
                <tr><td colspan="7" style="text-align:center;padding:18px">No customers yet</td></tr>
              <?php else: foreach($customers as $c): ?>
                <tr>
                  <td><?= (int)$c['id'] ?></td>
                  <td><?= htmlspecialchars($c['name']) ?></td>
                  <td><?= htmlspecialchars($c['email']) ?></td>
                  <td><?= htmlspecialchars($c['phone']) ?></td>
                  <td><?= $c['is_active'] ? 'Yes' : 'No' ?></td>
                  <td><?= htmlspecialchars($c['created_at']) ?></td>
                  <td>
                    <button class="btn edit" onclick="openEdit(<?= (int)$c['id'] ?>, <?= json_encode(addslashes($c['name'])) ?>, <?= json_encode($c['email']) ?>, <?= json_encode($c['phone']) ?>)">Edit</button>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                      <button class="btn del" onclick="return confirm('Delete customer #<?= (int)$c['id'] ?>?')" type="submit">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>

          <div id="editPanel" style="display:none;margin-top:12px;background:#fff;padding:12px;border-radius:8px">
            <h3>Edit Customer</h3>
            <form id="editForm" method="post">
              <input type="hidden" name="action" value="edit">
              <input type="hidden" name="id" id="edit_id" value="">
              <div style="display:flex;gap:8px;flex-wrap:wrap">
                <input type="text" name="name" id="edit_name" placeholder="Name" required>
                <input type="email" name="email" id="edit_email" placeholder="Email">
                <input type="text" name="phone" id="edit_phone" placeholder="Phone">
                <label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="is_active" id="edit_active"> Active</label>
              </div>
              <div style="margin-top:8px">
                <textarea name="notes" id="edit_notes" rows="2" placeholder="Notes" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:8px"></textarea>
              </div>
              <div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end">
                <button class="btn" type="submit">Save</button>
                <button type="button" onclick="document.getElementById('editPanel').style.display='none'">Cancel</button>
              </div>
            </form>
          </div>

        </div>
      </section>
    </main>
  </div>

<script>
// open edit panel with values
function openEdit(id,name,email,phone){
  document.getElementById('editPanel').style.display='block';
  document.getElementById('edit_id').value = id;
  document.getElementById('edit_name').value = name || '';
  document.getElementById('edit_email').value = email || '';
  document.getElementById('edit_phone').value = phone || '';
  document.getElementById('edit_active').checked = true;
}

// client-side search
(function(){
  const input = document.getElementById('globalSearch');
  const tbody = document.getElementById('customersTable');
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