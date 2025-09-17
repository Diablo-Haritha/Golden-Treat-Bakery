<?php
// purchase_history.php (CSS replaced with user's provided stylesheet and dataset fixes)

// DB connection
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

// helper for dynamic bind_param
function refValues($arr){
    $refs = [];
    foreach ($arr as $k => $v) $refs[$k] = &$arr[$k];
    return $refs;
}

// Read filters (GET)
$from = isset($_GET['from']) && $_GET['from'] !== '' ? trim($_GET['from']) : '';
$to   = isset($_GET['to'])   && $_GET['to']   !== '' ? trim($_GET['to'])   : '';
$customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';
$order_id = isset($_GET['order_id']) && $_GET['order_id'] !== '' ? (int)$_GET['order_id'] : 0;
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$limit = 2000; // safety limit

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
if ($customer !== '') {
    $where[] = "o.customer LIKE ?";
    $types .= 's';
    $values[] = '%' . $customer . '%';
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

// Query: include returned quantity per order (aggregated) and compute net
$sql = "
SELECT 
  o.id, o.order_date, o.customer, o.product, o.quantity, o.price, o.status,
  COALESCE(r.sum_qty,0) AS returned_qty,
  GREATEST(o.quantity - COALESCE(r.sum_qty,0), 0) AS net_quantity,
  (o.price * GREATEST(o.quantity - COALESCE(r.sum_qty,0), 0)) AS net_value
FROM orders o
LEFT JOIN (
  SELECT order_id, SUM(quantity) AS sum_qty
  FROM returns
  GROUP BY order_id
) r ON r.order_id = o.id
{$whereSql}
ORDER BY o.order_date DESC, o.id DESC
LIMIT {$limit}
";

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

// compute totals
$totalGross = 0.0;
$totalReturnedQty = 0;
$totalNet = 0.0;
foreach ($rows as $r) {
    $qty = (int)($r['quantity'] ?? 0);
    $ret = (int)($r['returned_qty'] ?? 0);
    $netQty = max(0, (int)($r['net_quantity'] ?? 0));
    $price = (float)($r['price'] ?? 0.0);
    $netValue = (float)($r['net_value'] ?? ($price * $netQty));

    $totalGross += $price * $qty;
    $totalReturnedQty += $ret;
    $totalNet += $netValue;
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

          <div class="cards" style="grid-template-columns: repeat(3, 1fr);">
            <div class="card">
              <h3>Total Gross</h3>
              <p>Rs. <?= number_format($totalGross,2) ?></p>
            </div>
            <div class="card">
              <h3>Total Returned Qty</h3>
              <p><?= (int)$totalReturnedQty ?></p>
            </div>
            <div class="card">
              <h3>Total Net Value</h3>
              <p>Rs. <?= number_format($totalNet,2) ?></p>
            </div>
          </div>

          <div class="toolbar">
            <form method="get"  class="filter-bar">
              <input type="date" name="from" value="<?= htmlspecialchars($from ? substr($from,0,10) : '') ?>" />
              <input type="date" name="to" value="<?= htmlspecialchars($to ? substr($to,0,10) : '') ?>" />
              <input type="text" name="customer" placeholder="Customer" value="<?= htmlspecialchars($customer) ?>" />
              <input type="number" name="order_id" placeholder="Order ID" value="<?= ($order_id ? (int)$order_id : '') ?>" />
              <select name="status">
                <option value="">All status</option>
                <?php foreach ($enumList as $st): $sel = ($st === $status) ? 'selected' : ''; ?>
                  <option value="<?= htmlspecialchars($st) ?>" <?= $sel ?>><?= htmlspecialchars($st) ?></option>
                <?php endforeach; ?>
              </select>

              <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
              <button class="btn secondary" id="exportCsv" type="button"><i class="fa-solid fa-file-csv"></i> Export CSV</button>

              <div class="muted" style="margin-left:auto;align-self:center">Showing up to <?= $limit ?> rows</div>
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
                  <th>Net Qty</th>
                  <th>Price (per)</th>
                  <th>Net Value</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="ordersTable">
                <?php if (empty($rows)): ?>
                  <tr><td colspan="11" style="text-align:center;padding:18px">No purchases found for these filters</td></tr>
                <?php else: foreach ($rows as $r): ?>
                  <tr data-status="<?= htmlspecialchars(strtolower($r['status'])) ?>">
                    <td class="col-id"><?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['order_date']) ?></td>
                    <td><?= htmlspecialchars($r['customer']) ?></td>
                    <td><?= htmlspecialchars($r['product']) ?></td>
                    <td><?= (int)$r['quantity'] ?></td>
                    <td><?= (int)$r['returned_qty'] ?></td>
                    <td><?= (int)$r['net_quantity'] ?></td>
                    <td><?= number_format((float)$r['price'],2) ?></td>
                    <td><?= number_format((float)$r['net_value'],2) ?></td>
                    <td><span class="badge <?= htmlspecialchars(str_replace(' ','',$r['status'])) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td class="row-actions">
                      <!-- Hyphenated data attributes -> dataset.orderDate, dataset.netValue, dataset.netQty -->
                      <button class="viewBtn" type="button"
                        data-id="<?= htmlspecialchars($r['id']) ?>"
                        data-order-date="<?= htmlspecialchars($r['order_date']) ?>"
                        data-customer="<?= htmlspecialchars($r['customer']) ?>"
                        data-product="<?= htmlspecialchars($r['product']) ?>"
                        data-quantity="<?= (int)$r['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$r['price'],2,'.','')) ?>"
                        data-returned="<?= (int)$r['returned_qty'] ?>"
                        data-net-qty="<?= (int)$r['net_quantity'] ?>"
                        data-net-value="<?= htmlspecialchars(number_format((float)$r['net_value'],2,'.','')) ?>"
                        data-status="<?= htmlspecialchars($r['status']) ?>">
                        <i class="fa-solid fa-eye"></i>
                      </button>

                      <button class="invoiceBtn" type="button"
                        data-id="<?= htmlspecialchars($r['id']) ?>"
                        data-order-date="<?= htmlspecialchars($r['order_date']) ?>"
                        data-customer="<?= htmlspecialchars($r['customer']) ?>"
                        data-product="<?= htmlspecialchars($r['product']) ?>"
                        data-quantity="<?= (int)$r['quantity'] ?>"
                        data-price="<?= htmlspecialchars(number_format((float)$r['price'],2,'.','')) ?>"
                        data-returned="<?= (int)$r['returned_qty'] ?>"
                        data-net-qty="<?= (int)$r['net_quantity'] ?>"
                        data-net-value="<?= htmlspecialchars(number_format((float)$r['net_value'],2,'.','')) ?>"
                        data-status="<?= htmlspecialchars($r['status']) ?>">
                        <i class="fa-solid fa-file-invoice"></i>
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
          <div><strong>Net Quantity</strong><div id="v_netqty"></div></div>
          <div><strong>Price</strong><div id="v_price"></div></div>
          <div class="full"><strong>Net Value</strong><div id="v_netvalue"></div></div>
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
    elAll('#ordersTable tr').forEach(tr => {
      const tds = Array.from(tr.querySelectorAll('td'));
      if (!tds.length) { tr.style.display = ''; return; }
      const rowText = tds.map(td => td.textContent.toLowerCase()).join(' ');
      const matchesQ = !q || rowText.includes(q);
      const rowStatus = normalize(tr.getAttribute('data-status'));
      const matchesStatus = !status || rowStatus === status;
      tr.style.display = (matchesQ && matchesStatus) ? '' : 'none';
    });
  }
  input?.addEventListener('input', filterRows);
  statusSelect?.addEventListener && statusSelect.addEventListener('change', filterRows);

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
        netQty: tds[6].textContent.trim(),
        price: tds[7].textContent.trim(),
        netValue: tds[8].textContent.trim(),
        status: tds[9].textContent.trim()
      };
    }).filter(Boolean);
  }

  // CSV helper
  function quoteCSV(s){ const str = String(s ?? ''); return /[",\n]/.test(str) ? `"${str.replace(/"/g,'""')}"` : str; }

  // CSV button
  document.getElementById('exportCsv')?.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    if (!rows.length) { alert('No rows to export'); return; }
    const hdr = ['Order ID','Date','Customer','Product','Qty','Returned','Net Qty','Price','Net Value','Status'];
    const lines = [hdr.join(',')];
    rows.forEach(r => lines.push([
      quoteCSV(r.id), quoteCSV(r.date), quoteCSV(r.customer), quoteCSV(r.product),
      r.quantity, r.returned, r.netQty, quoteCSV(r.price), quoteCSV(r.netValue), quoteCSV(r.status)
    ].join(',')));
    const blob = new Blob([lines.join('\n')], { type: 'text/csv' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'purchase_history.csv';
    a.click();
    setTimeout(()=>URL.revokeObjectURL(a.href),1500);
  });

  // XLSX + PDF buttons (create and insert next to CSV)
  const expXlsxBtn = document.createElement('button');
  expXlsxBtn.className = 'btn secondary';
  expXlsxBtn.innerHTML = '<i class="fa-solid fa-file-excel"></i>&nbsp;Excel';
  expXlsxBtn.addEventListener('click', () => {
    const rows = gatherVisibleOrders();
    if (!rows.length) { alert('No rows to export'); return; }
    const sheetRows = rows.map(r => ({ 'Order ID': r.id, 'Date': r.date, 'Customer': r.customer, 'Product': r.product, 'Qty': +r.quantity, 'Price': r.price, 'Status': r.status }));
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
    const head = [['ID','Date','Customer','Product','Qty','Returned','Net Qty','Price','Net Value','Status']];
    const body = rows.map(r => [r.id, r.date, r.customer, r.product, r.quantity, r.returned, r.netQty, r.price, r.netValue, r.status]);
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
    el('#v_netqty').textContent = data.netQty;
    el('#v_price').textContent = data.price;
    el('#v_netvalue').textContent = data.netValue;
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
    invBtn.dataset.netQty = data.netQty;
    invBtn.dataset.netValue = data.netValue;
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
        customer: d.customer,
        product: d.product,
        quantity: d.quantity,
        returned: d.returned,
        netQty: d.netQty,
        netValue: d.netValue,
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
        customer: d.customer,
        product: d.product,
        quantity: d.quantity,
        returned: d.returned,
        netQty: d.netQty,
        netValue: d.netValue,
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
      netQty: d.netQty,
      netValue: d.netValue,
      price: d.price,
      status: d.status
    });
  });

  viewModal.addEventListener('click', function(ev){ if (ev.target === viewModal) closeView(); });

  function escapeHtml(s) { if (s===null||s===undefined) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function openInvoiceWindow(data) {
    const total = Number(data.netValue || 0).toFixed(2);
    const unitPrice = Number(data.price || 0).toFixed(2);
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
              <h1>TechShelf</h1>
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
            <thead><tr><th>Product</th><th>Qty</th><th>Returned</th><th>Net Qty</th><th>Unit Price</th><th>Line Total</th></tr></thead>
            <tbody>
              <tr>
                <td>${escapeHtml(data.product)}</td>
                <td style="width:60px">${escapeHtml(data.quantity)}</td>
                <td style="width:60px">${escapeHtml(data.returned)}</td>
                <td style="width:60px">${escapeHtml(data.netQty)}</td>
                <td style="width:120px">${escapeHtml(unitPrice)}</td>
                <td style="width:120px">${escapeHtml(total)}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr><td colspan="5" class="tot">Total</td><td class="tot">${escapeHtml(total)}</td></tr>
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
