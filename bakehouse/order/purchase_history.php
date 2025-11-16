<?php
// purchase_history.php (Updated with pagination)

// DB connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "golden_treat";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    http_response_code(500);
    die("DB Connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// helper for dynamic bind_param
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Read filters (GET)
$from = isset($_GET['from']) && $_GET['from'] !== '' ? trim($_GET['from']) : '';
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? trim($_GET['to'])   : '';
$customer_name = isset($_GET['customer_name']) ? trim($_GET['customer_name']) : '';
$order_id = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? (int)$_GET['order_id'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;

// Pagination settings
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Simple date validation (YYYY-MM-DD)
function validate_date($d) {
    if (!is_string($d) || $d === '') return false;
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}
if ($from !== '' && !validate_date($from)) $from = '';
if ($to !== '' && !validate_date($to)) $to = '';

// Build WHERE and bind arrays
$where = [];
$types = '';
$values = [];

if ($from !== '') {
    $where[] = "o.order_date >= ?";
    $types .= 's';
    $values[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = "o.order_date <= ?";
    $types .= 's';
    $values[] = $to . ' 23:59:59';
}
if ($customer_name !== '') {
    $where[] = "o.customer_name LIKE ?";
    $types .= 's';
    $values[] = '%' . $customer_name . '%';
}
if ($order_id) {
    $where[] = "o.id = ?";
    $types .= 'i';
    $values[] = $order_id;
}
if ($status !== '') {
    $where[] = "o.status = ?";
    $types .= 's';
    $values[] = $status;
}

$whereSql = '';
if (!empty($where)) $whereSql = 'WHERE ' . implode(' AND ', $where);

// Count total rows for pagination
$count_sql = "
SELECT COUNT(DISTINCT o.id) as total
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
{$whereSql}
";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt === false) {
    die("Count SQL prepare failed: " . $conn->error);
}
if (!empty($values)) {
    $count_values = $values; // Copy values for count query
    array_unshift($count_values, $types);
    call_user_func_array([$count_stmt, 'bind_param'], refValues($count_values));
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$count_stmt->close();

// Calculate total pages
$total_pages = ceil($total_rows / $records_per_page);
if ($total_pages < 1) $total_pages = 1;

// Ensure current page is within bounds
if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $records_per_page;
}

// Query: Fetch product_name and quantity from order_items, total_amount from orders
$sql = "
SELECT 
    o.id, 
    o.order_date, 
    o.customer_name, 
    GROUP_CONCAT(oi.product_name SEPARATOR ', ') AS product, 
    SUM(oi.quantity) AS quantity, 
    o.total_amount AS price, 
    o.status,
    COALESCE(r.sum_qty, 0) AS returned_qty
FROM orders o
LEFT JOIN order_items oi ON o.id = oi.order_id
LEFT JOIN (
    SELECT order_id, SUM(quantity) AS sum_qty
    FROM returns
    GROUP BY order_id
) r ON r.order_id = o.id
{$whereSql}
GROUP BY o.id
ORDER BY o.order_date DESC, o.id DESC
LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Prepare failed: " . $conn->error);
}
$bind_values = array_merge($values, [$records_per_page, $offset]);
$bind_types = $types . 'ii';
array_unshift($bind_values, $bind_types);
call_user_func_array([$stmt, 'bind_param'], refValues($bind_values));
$stmt->execute();
$res = $stmt->get_result();
$rows = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// compute totals
$totalGross = 0.0;
$totalReturnedQty = 0;
foreach ($rows as $r) {
    $price = (float)($r['price'] ?? 0.0);
    $ret = (int)($r['returned_qty'] ?? 0);
    $totalGross += $price;
    $totalReturnedQty += $ret;
}

// status enum list for UI
$enumList = ['Order Received','Payment Confirmed','Queued for Baking','In Preparation','Decorating','Ready for Pickup','Out for Delivery','Completed','Cancelled','Refunded','Returned','Pending'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Purchase History - Admin</title>
  <link rel="stylesheet" href="css/purchase_history.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.4/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
  <div class="header">
    <div class="header-left"><img src="logo.jpg" alt="Logo" /></div>
    <div class="header-middle">
      <div class="header-middle-title">Order Management</div>
      <div class="search-bar"><input id="globalSearch" type="text" placeholder="Search by ID, customer, product or status..." /></div>
    </div>
    <div class="header-right">
      <button class="role-btn" onclick="window.location.href='index.html'">Dashboard</button>
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
        <p class="muted">Order Management</p>
        <div class="salebtn">
          <button class="tab-btn" onclick="window.location.href='order.php'">Purchase Dashboard</button>
          <button class="tab-btn" onclick="window.location.href='order.php#sales-export'">Export Report</button>
          <button type="button" class="orderhistory" onclick="window.location.href='purchase_returns.php'">Purchase Returns</button>
          <button type="button" class="orderhistory active" onclick="window.location.href='purchase_history.php'">Purchase History</button>
        </div>
      </nav>
    </aside>

    <main class="free-area">
      <section class="panel active">
        <div class="content">
          <h2>Purchase History</h2>

          <div class="cards" style="grid-template-columns: repeat(2, 1fr);">
            <div class="card">
              <h3>Total Gross</h3>
              <p>Rs. <?= number_format($totalGross, 2) ?></p>
            </div>
            <div class="card">
              <h3>Total Returned Qty</h3>
              <p><?= (int)$totalReturnedQty ?></p>
            </div>
          </div>

          <div class="toolbar">
            <form method="get" class="filter-bar">
              <label>From:
                <input type="date" name="from" placeholder="Start date" value="<?= htmlspecialchars($from ? substr($from, 0, 10) : '') ?>" />
              </label>
              <label>To:
                <input type="date" name="to" placeholder="End date" value="<?= htmlspecialchars($to ? substr($to, 0, 10) : '') ?>" />
              </label>
              <input type="text" name="customer_name" placeholder="Customer Name" value="<?= htmlspecialchars($customer_name) ?>" />
              <input type="number" name="order_id" placeholder="Order ID" value="<?= ($order_id ? (int)$order_id : '') ?>" />
              <select name="status">
                <option value="">All status</option>
                <?php foreach ($enumList as $st): $sel = ($st === $status) ? 'selected' : ''; ?>
                  <option value="<?= htmlspecialchars($st) ?>" <?= $sel ?>><?= htmlspecialchars($st) ?></option>
                <?php endforeach; ?>
              </select>

              <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
              <button class="btn secondary" id="exportCsv" type="button"><i class="fa-solid fa-file-csv"></i> Export CSV</button>

              <div class="muted" style="margin-left:auto;align-self:center">Showing <?= count($rows) ?> of <?= $total_rows ?> rows</div>
            </form>
          </div>

          <div class="table-wrap" style="margin-top:8px;">
            <table id="historyTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Date</th>
                  <th>Customer</th>
                  <th>Product</th>
                  <th>Qty</th>
                  <th>Returned</th>
                  <th>Price</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="ordersTable">
                <?php if (empty($rows)): ?>
                  <tr><td colspan="9" style="text-align:center;padding:18px">No purchases found for these filters</td></tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr data-status="<?= htmlspecialchars(strtolower($r['status'])) ?>">
                    <td class="col-id"><?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['order_date'] ? $r['order_date'] : 'N/A') ?></td>
                    <td><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td><?= htmlspecialchars($r['product']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td><?= (int)$r['returned_qty'] ?></td>
                    <td><?= number_format((float)$r['price'], 2) ?></td>
                    <td><span class="badge <?= htmlspecialchars(str_replace(' ', '', $r['status'])) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td class="row-actions">
                      <button class="viewBtn" type="button"
                        data-id="<?= htmlspecialchars($r['id']) ?>"
                        data-order-date="<?= htmlspecialchars($r['order_date'] ? $r['order_date'] : 'N/A') ?>"
                        data-customer_name="<?= htmlspecialchars($r['customer_name']) ?>"
                        data-product="<?= htmlspecialchars($r['product']) ?>"
                        data-quantity="<?= (int)$r['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$r['price'], 2, '.', '')) ?>"
                        data-returned="<?= (int)$r['returned_qty'] ?>"
                        data-status="<?= htmlspecialchars($r['status']) ?>">
                        <i class="fa-solid fa-eye"></i>
                      </button>
                      <button class="invoiceBtn" type="button"
                        data-id="<?= htmlspecialchars($r['id']) ?>"
                        data-order-date="<?= htmlspecialchars($r['order_date'] ? $r['order_date'] : 'N/A') ?>"
                        data-customer_name="<?= htmlspecialchars($r['customer_name']) ?>"
                        data-product="<?= htmlspecialchars($r['product']) ?>"
                        data-quantity="<?= (int)$r['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$r['price'], 2, '.', '')) ?>"
                        data-returned="<?= (int)$r['returned_qty'] ?>"
                        data-status="<?= htmlspecialchars($r['status']) ?>">
                        <i class="fa-solid fa-file-invoice"></i>
                      </button>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>

            <!-- Pagination Controls -->
            <div class="pagination">
              <form method="get" class="pagination-form">
                <!-- Preserve existing filters -->
                <?php if ($from): ?>
                  <input type="hidden" name="from" value="<?= htmlspecialchars($from) ?>">
                <?php endif; ?>
                <?php if ($to): ?>
                  <input type="hidden" name="to" value="<?= htmlspecialchars($to) ?>">
                <?php endif; ?>
                <?php if ($customer_name): ?>
                  <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customer_name) ?>">
                <?php endif; ?>
                <?php if ($order_id): ?>
                  <input type="hidden" name="order_id" value="<?= htmlspecialchars($order_id) ?>">
                <?php endif; ?>
                <?php if ($status): ?>
                  <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                <?php endif; ?>
                
                <!-- Previous Button -->
                <button type="submit" name="page" value="<?= max(1, $current_page - 1) ?>" <?= $current_page <= 1 ? 'disabled' : '' ?>>Previous</button>
                
                <!-- Page Numbers -->
                <?php
                $range = 2; // Number of pages to show before and after current page
                $start = max(1, $current_page - $range);
                $end = min($total_pages, $current_page + $range);

                // Show first page and ellipsis if needed
                if ($start > 1): ?>
                  <button type="submit" name="page" value="1">1</button>
                  <?php if ($start > 2): ?>
                    <span>...</span>
                  <?php endif; ?>
                <?php endif; ?>

                <!-- Page range -->
                <?php for ($i = $start; $i <= $end; $i++): ?>
                  <button type="submit" name="page" value="<?= $i ?>" <?= $i == $current_page ? 'class="active"' : '' ?>><?= $i ?></button>
                <?php endfor; ?>

                <!-- Show last page and ellipsis if needed -->
                <?php if ($end < $total_pages): ?>
                  <?php if ($end < $total_pages - 1): ?>
                    <span>...</span>
                  <?php endif; ?>
                  <button type="submit" name="page" value="<?= $total_pages ?>"><?= $total_pages ?></button>
                <?php endif; ?>

                <!-- Next Button -->
                <button type="submit" name="page" value="<?= min($total_pages, $current_page + 1) ?>" <?= $current_page >= $total_pages ? 'disabled' : '' ?>>Next</button>
              </form>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>

  <!-- View modal -->
  <div class="modal-backdrop" id="viewModal" role="dialog" aria-modal="true">
    <div class="modal" id="viewModalInner">
      <h2 id="viewTitle">Purchase</h2>
      <div style="margin-top:8px" id="viewBody">
        <div class="form-grid">
          <div><strong>Order ID</strong><div id="v_id"></div></div>
          <div><strong>Date</strong><div id="v_date"></div></div>
          <div class="full"><strong>Customer</strong><div id="v_customer"></div></div>
          <div class="full"><strong>Product</strong><div id="v_product"></div></div>
          <div><strong>Quantity</strong><div id="v_quantity"></div></div>
          <div><strong>Returned</strong><div id="v_returned"></div></div>
          <div><strong>Price</strong><div id="v_price"></div></div>
          <div class="full"><strong>Status</strong><div id="v_status"></div></div>
        </div>
      </div>
      <div class="footer" style="display:flex;justify-content:flex-end;gap:8px;margin-top:12px">
        <button class="btn light" onclick="closeView()">Close</button>
        <button class="btn invoiceBtn" id="viewInvoiceBtn"><i class="fa-solid fa-file-invoice"></i>&nbsp;Invoice</button>
      </div>
    </div>
  </div>

<script>
(function(){
  // helpers
  function el(q, root=document){ return root.querySelector(q); }
  function elAll(q, root=document){ return Array.from((root||document).querySelectorAll(q)); }
  function normalize(s){ return (s||'').toString().toLowerCase(); }

  const input = document.getElementById('globalSearch');
  const statusSelect = document.querySelector('select[name="status"]');

  function filterRows(){
    const q = normalize(input?.value || '');
    const status = normalize(statusSelect?.value || '');
    const tbody = document.getElementById('ordersTable');
    if (!tbody) return;
    const rows = elAll('tr', tbody);
    let visibleCount = 0;

    if (!q && !status) {
      rows.forEach(r => {
        r.style.display = '';
        visibleCount++;
      });
    } else {
      rows.forEach(tr => {
        const tds = Array.from(tr.querySelectorAll('td'));
        if (!tds.length) {
          tr.style.display = '';
          visibleCount++;
          return;
        }
        const rowText = tds.map(td => td.textContent.toLowerCase()).join(' ');
        const matchesQ = !q || rowText.includes(q);
        const rowStatus = normalize(tr.getAttribute('data-status'));
        const matchesStatus = !status || rowStatus === status;
        tr.style.display = (matchesQ && matchesStatus) ? '' : 'none';
        if (matchesQ && matchesStatus) visibleCount++;
      });
    }

    // Show a message if no rows are visible
    if (visibleCount === 0 && rows.length > 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:18px">No matching purchases found on this page</td></tr>';
    } else if (visibleCount === 0 && rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:18px">No purchases found for these filters</td></tr>';
    }
  }
  input?.addEventListener('input', filterRows);
  statusSelect?.addEventListener && statusSelect.addEventListener('change', filterRows);

  // Reset to page 1 when filters change
  document.querySelectorAll('.filter-bar input, .filter-bar select').forEach(elem => {
    elem.addEventListener('change', () => {
      const form = elem.closest('form');
      const pageInput = document.createElement('input');
      pageInput.type = 'hidden';
      pageInput.name = 'page';
      pageInput.value = '1';
      form.appendChild(pageInput);
      form.submit();
    });
  });

  // gather visible rows for export
  function gatherVisibleOrders() {
    const rows = Array.from(document.querySelectorAll('#ordersTable tr')).filter(r => r.style.display !== 'none');
    return rows.map(r => {
      const tds = r.querySelectorAll('td');
      if (!tds.length) return null;
      return {
        id: tds[0].textContent.trim(),
        date: tds[1].textContent.trim(),
        customer: tds[2].textContent.trim(),
        product: tds[3].textContent.trim(),
        quantity: tds[4].textContent.trim(),
        returned: tds[5].textContent.trim(),
        price: tds[6].textContent.trim(),
        status: tds[7].textContent.trim()
      };
    }).filter(Boolean);
  }

  // CSV helper
  function quoteCSV(s){ const str = String(s ?? ''); return /[",\n]/.test(str) ? `"${str.replace(/"/g,'""')}"` : str; }

  // CSV button
  document.getElementById('exportCsv')?.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    if (!rows.length) { alert('No rows to export'); return; }
    const hdr = ['Order ID','Date','Customer','Product','Qty','Returned','Price','Status'];
    const lines = [hdr.join(',')];
    rows.forEach(r => lines.push([
      quoteCSV(r.id), quoteCSV(r.date), quoteCSV(r.customer), quoteCSV(r.product),
      r.quantity, r.returned, quoteCSV(r.price), quoteCSV(r.status)
    ].join(',')));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'purchase_history.csv';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1500);
  });

  // XLSX + PDF buttons
  const expXlsxBtn = document.createElement('button');
  expXlsxBtn.className = 'btn secondary';
  expXlsxBtn.innerHTML = '<i class="fa-solid fa-file-excel"></i>&nbsp;Excel';
  expXlsxBtn.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    if (!rows.length) { alert('No rows to export'); return; }
    const sheetRows = rows.map(r => ({
      'Order ID': r.id,
      'Date': r.date,
      'Customer': r.customer,
      'Product': r.product,
      'Qty': +r.quantity,
      'Returned': +r.returned,
      'Price': r.price,
      'Status': r.status
    }));
    const ws = XLSX.utils.json_to_sheet(sheetRows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Purchases');
    XLSX.writeFile(wb, 'purchase_history.xlsx');
  });

  const expPdfBtn = document.createElement('button');
  expPdfBtn.className = 'btn warn';
  expPdfBtn.innerHTML = '<i class="fa-solid fa-file-pdf"></i>&nbsp;PDF';
  expPdfBtn.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    if (!rows.length) { alert('No rows to export'); return; }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const head = [['ID','Date','Customer','Product','Qty','Returned','Price','Status']];
    const body = rows.map(r => [r.id, r.date, r.customer, r.product, r.quantity, r.returned, r.price, r.status]);
    doc.setFontSize(12);
    doc.text('Purchase History', 14, 16);
    doc.autoTable({ startY: 22, head, body, styles: { fontSize: 8 } });
    doc.save('purchase_history.pdf');
  });

  // insert XLSX/PDF after CSV button
  const csvBtn = document.getElementById('exportCsv');
  csvBtn?.parentNode?.insertBefore(expXlsxBtn, csvBtn.nextSibling);
  csvBtn?.parentNode?.insertBefore(expPdfBtn, expXlsxBtn.nextSibling);

  // Modal / Invoice logic
  const viewModal = document.getElementById('viewModal');
  function openView(data){
    el('#viewTitle').textContent = 'Purchase #' + data.id;
    el('#v_id').textContent = data.id;
    el('#v_date').textContent = data.orderDate;
    el('#v_customer').textContent = data.customer;
    el('#v_product').textContent = data.product;
    el('#v_quantity').textContent = data.quantity;
    el('#v_returned').textContent = data.returned;
    el('#v_price').textContent = data.price;
    el('#v_status').textContent = data.status;
    viewModal.style.display = 'flex';

    const invBtn = document.getElementById('viewInvoiceBtn');
    invBtn.dataset.id = data.id;
    invBtn.dataset.orderDate = data.orderDate;
    invBtn.dataset.customer = data.customer;
    invBtn.dataset.product = data.product;
    invBtn.dataset.quantity = data.quantity;
    invBtn.dataset.price = data.price;
    invBtn.dataset.returned = data.returned;
    invBtn.dataset.status = data.status;
  }
  window.closeView = function(){ viewModal.style.display = 'none'; };

  // event delegation for view/invoice buttons
  document.addEventListener('click', function(e){
    const view = e.target.closest('.viewBtn');
    if (view) {
      const d = view.dataset;
      openView({
        id: d.id,
        orderDate: d.orderDate,
        customer: d.customer_name,
        product: d.product,
        quantity: d.quantity,
        returned: d.returned,
        price: d.price,
        status: d.status
      });
      return;
    }
    const inv = e.target.closest('.invoiceBtn');
    if (inv) {
      const d = inv.dataset;
      openInvoiceWindow({
        id: d.id,
        orderDate: d.orderDate,
        customer: d.customer_name,
        product: d.product,
        quantity: d.quantity,
        returned: d.returned,
        price: d.price,
        status: d.status
      });
      return;
    }
  });

  document.getElementById('viewInvoiceBtn')?.addEventListener('click', function(){
    const d = this.dataset;
    openInvoiceWindow({
      id: d.id,
      orderDate: d.orderDate,
      customer: d.customer,
      product: d.product,
      quantity: d.quantity,
      returned: d.returned,
      price: d.price,
      status: d.status
    });
  });

  viewModal.addEventListener('click', function(ev){ if (ev.target === viewModal) closeView(); });

  function escapeHtml(s) { if (s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function openInvoiceWindow(data) {
    const total = Number(data.price || 0).toFixed(2);
    const content = `
      <!doctype html>
      <html>
      <head>
        <meta charset="utf-8">
        <title>Invoice - Order ${escapeHtml(data.id)}</title>
        <style>
          body{font-family:Arial;margin:24px;color:#111}
          .box{max-width:720px;margin:0 auto}
          header{display:flex;justify-content:space-between;align-items:center}
          h1{color:#6b21a8}
          table{width:100%;border-collapse:collapse;margin-top:16px}
          th,td{padding:8px;border:1px solid #ddd;text-align:left}
          .tot{text-align:right;font-weight:800}
          .meta{margin-top:12px}
          .print{margin-top:18px}
          @media print{ .print{display:none} }
        </style>
      </head>
      <body>
        <div class="box">
          <header>
            <div>
              <h1>Golden Treat Bakery</h1>
              <div>Purchase Invoice</div>
            </div>
            <div>
              <div>Order #: <strong>${escapeHtml(data.id)}</strong></div>
              <div>Date: ${escapeHtml(data.orderDate)}</div>
            </div>
          </header>

          <div class="meta">
            <strong>Customer:</strong> ${escapeHtml(data.customer)}
          </div>

          <table>
            <thead><tr><th>Product</th><th>Qty</th><th>Returned</th><th>Total Price</th></tr></thead>
            <tbody>
              <tr>
                <td>${escapeHtml(data.product)}</td>
                <td style="width:60px">${escapeHtml(data.quantity)}</td>
                <td style="width:60px">${escapeHtml(data.returned)}</td>
                <td style="width:120px">${escapeHtml(total)}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td colspan="3" class="tot">Total</td><td class="tot">${escapeHtml(total)}</td></tr>
            </tfoot>
          </table>

          <div class="print">
            <button onclick="window.print()">Print</button>
            <button onclick="window.close()">Close</button>
          </div>
        </div>
      </body>
      </html>
    `;
    const w = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
    if (!w) { alert('Please allow popups to open the invoice.'); return; }
    w.document.open();
    w.document.write(content);
    w.document.close();
  }

  // initial filter run
  filterRows();
})();
</script>
</body>
</html>